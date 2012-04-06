<?php

define('CLI_SCRIPT', true);

require(dirname(dirname(dirname(dirname(dirname(dirname(__FILE__)))))).'/config.php');
require_once($CFG->dirroot.'/mod/assignment/type/github/assignment.class.php');

$CFG->debug = DEBUG_NORMAL;

$params = implode(' ', $argv);

// check params
preg_match('/--cm=(\d+)/', $params, $match);
if ($match) {
    $cmid = $match[1];
}

$cms = array();
if (!empty($cmid)) {
    $cms[] = $cmid;
} else {
    $conditions = array('modulename' => 'assignment', 'type' => 'github');
    $sql = "SELECT cm.id, m.name
              FROM {course_modules} cm
              JOIN {modules} md ON md.id = cm.module
              JOIN {assignment} m ON m.id = cm.instance
             WHERE md.name = :modulename AND m.assignmenttype = :type";
    $result = $DB->get_records_sql($sql, $conditions);
    if (empty($result)) {
        fwrite(STDERR, 'No Github Assignment');
        die;
    }
    foreach(array_keys($result) as $cmid) {
        $cms[] = $cmid;
    }
}

try {
    foreach($cms as $cmid) {
        $task = new sync_git_repos($cmid);
        $task->sync();
    }
} catch (Exception $e) {
    fwrite(STDERR, $e->getMessage());
    die;
}

class sync_git_repos {

    private $_assignmentinstance;

    private $_groupmode;

    private $_git;

    private $_analyzer;

    private $_logger;

    private $_submissions;

    public function __construct($cmid) {
        global $DB;

        if (! $cm = get_coursemodule_from_id('assignment', $cmid)) {
            throw new Exception(get_string('invalidcoursemodule', 'error'));
            return;
        }
        
        if (! $assignment = $DB->get_record("assignment", array("id"=>$cm->instance))) {
            throw new Exception(get_string('invalidid', 'assignment'));
            return;
        }
        
        if (! $course = $DB->get_record("course", array("id"=>$assignment->course))) {
            throw new Exception(get_string('coursemisconf', 'assignment'));
            return;
        }
        
        if ($assignment->assignmenttype != 'github') {
            throw new Exception(get_string('invalidtype', 'assignment'));
            return;
        }

        $this->_assignmentinstance = new assignment_github($cm->id, $assignment, $cm, $course);
        $this->_groupmode = groups_get_activity_groupmode($cm);
        $this->_git = new git($course->id, $assignment->id);
        $this->_analyzer = new git_analyzer();
        $this->_logger = new git_logger($assignment->id);
    }

    public function sync() {

        $this->show_message('Start sync repos of assignment: '.$this->_assignmentinstance->assignment->id);
        $repos = $this->list_all_repos();

        if (!empty($repos)) {
            foreach($repos as $id => $repo) {
                $worktree = $this->generate_worktree($id);
                $this->_analyzer->set_worktree($worktree);
                $this->show_message("Current work tree: [{$worktree}] updating...");

                if ($this->_analyzer->has_worktree()) {
                    $this->_analyzer->pull();
                } else {

                    // convert url to git:// first, in case user use other protocol
                    $service =& $this->_git->get_api_service($repo->server);
                    $git = $service->parse_git_url($repo->url);
                    if ($git['type'] == 'git') {
                        $url = $repo->url;
                    } else {
                        $url = $service->generate_git_url($git, 'git');
                    }
                    $this->_analyzer->pull($url);
                }

                $logs = $this->_analyzer->get_log();
                if ($logs) {
                    $this->show_message('Analyzing...');
                    $this->store_logs($id, $repo, $logs);
                } else {
                    $this->show_error("Failed to get log of work tree: [{$worktree}] repo: [{$repo->url}]");
                }
            }
        } else {
            $this->show_message('No repos');
        }
        $this->show_message('Sync finished');
    }

    private function generate_worktree($id) {

        $prefix = "A{$this->_assignmentinstance->assignment->id}";
        if ($this->_groupmode) {
            $suffix = "G{$id}";
        } else {
            $suffix = "U{$id}";
        }
        return "{$prefix}-{$suffix}";
    }

    private function get_submissions() {

        $results = $this->_assignmentinstance->get_submissions();
        $submissions = array();
        foreach($results as $submission) {
            if (!empty($submission->userid)) {
                $submissions[$submission->userid] = $submission;
            }
        }
        return $submissions;
    }

    private function list_all_repos() {

        return $this->_assignmentinstance->list_all();
    }

    private function get_user_email($userid) {

        if (empty($this->_submissions)) {
            $this->_submissions = $this->get_submissions();
        }

        if (empty($this->_submissions[$userid])) {
            return '';
        }

        return $this->_submissions[$userid]->data1;
    }

    private function store_logs($id, $repo, $logs) {

        $assignment = $this->_assignmentinstance->assignment->id;
        if ($this->_groupmode) {
            $members = groups_get_members($id, 'u.*', 'lastname ASC');
            $emails = array();
            foreach($members as $userid => $member) {
                $email = $this->get_user_email($userid);
                if ($email) {
                    $emails[$email] = $userid;
                }
            }
            $old_logs = $this->_logger->get_by_group($id);
        } else {
            $old_logs = $this->_logger->get_by_user($id);
        }

        // write logs
        $logs = array_diff_key($logs, $old_logs);
        $result = true;
        foreach($logs as $log) {
            $log->assignment = $assignment;
            if ($this->_groupmode) {
                if (empty($emails[$log->email])) {
                    $log->userid = 0;
                } else {
                    $log->userid = $emails[$log->email];
                }
                $log->groupid = $id;
            } else {
                $log->userid = $id;
                $log->groupid = 0;
            }
            $result = $result && $this->_logger->add_record($log);
        }

        // update submission time
        foreach($members as $userid => $member) {
            $last_commit = $this->_logger->get_user_last_commit($userid);
            if (empty($last_commit) || empty($this->_submissions[$userid])) {
                continue;
            }

            if ($last_commit->date > $this->_submissions[$userid]->timemodified) {
                $data = new stdClass();
                $data->timemodified = $last_commit->date;
                $this->_assignmentinstance->update_submission($userid, $data);

                // refresh cache
                $this->_submissions[$userid] = $this->_assignmentinstance->get_submission($userid);
            }
        }

        if ($result) {
            $this->show_message(count($logs).' new commits');
        } else {
            $this->show_error('Error occurred while inserting logs');
        }
    }

    private function show_message($message) {

        $message = '['.date('Y-m-d H:i:s').'] '.$message.PHP_EOL;
        fwrite(STDOUT, $message);
    }

    private function show_error($message) {

        $message = '['.date('Y-m-d H:i:s').'][ERROR]'.$message.PHP_EOL;
        fwrite(STDERR, $message);
    }
}
