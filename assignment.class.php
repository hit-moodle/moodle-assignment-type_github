<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/mod/assignment/lib.php');
require_once(dirname(__FILE__).'/config.php');

class assignment_github extends assignment_base {

    private $group;
    private $github_repo;

    function assignment_github($cmid='staticonly', $assignment=NULL, $cm=NULL, $course=NULL) {
        parent::assignment_base($cmid, $assignment, $cm, $course);
        $this->type = 'github';
        $this->group = new stdClass();
    }

    function view() {

        require_capability('mod/assignment:view', $this->context);
        add_to_log($this->course->id, "assignment", "view", "view.php?id={$this->cm->id}", $this->assignment->id, $this->cm->id);

        $this->view_header();

        $this->view_intro();

        $this->view_dates();

        $this->view_repos();

        $this->view_feedback();

        $this->view_footer();
    }

    private function init_group() {
        global $USER;

        $this->group->mode = groups_get_activity_groupmode($this->cm);
        $this->group->id = groups_get_activity_group($this->cm);
        $this->group->ismember = groups_is_member($this->group->id);
    }

    private function view_repos() {
        global $USER, $OUTPUT;

        $this->init_group();

        if ($this->group->mode == VISIBLEGROUPS && !$this->group->id) {
            //TODO: show all repos' info of this assignment
            //      return
        }

        $editmode = optional_param('edit', 0, PARAM_BOOL);
        $this->github_repo = new github_repo($this->course->id, $this->assignment->id, $USER->id, $this->group->id);

        if($this->group->mode) {
            $repo = $this->github_repo->get_by_group($this->group->id);
        } else {
            $repo = $this->github_repo->get_by_user($USER);
        }

        $github_username = optional_param('username', '', PARAM_ALPHANUMEXT);
        $github_repository = optional_param('repo', '', PARAM_ALPHANUMEXT);
        if(!$repo || $editmode) {
            if ($github_username && $github_repository) {
                $this->save_repo($repo->id, $github_username, $github_repository);
                $this->show_repo();
            } else {
                $this->edit_form();
            }
        } else {
            $this->show_repo($repo);
        }
    }

    private function show_repo($repository = null) {
        global $USER;

        if (!$repository) {
            if($this->group->mode) {
                $repository = $this->github_repo->get_by_group($this->group->id);
            } else {
                $repository = $this->github_repo->get_by_user($USER);
            }
        }

        if ($repository) {
            //TODO: table to show the repository's info
            //      return
        }

        //TODO: show the user/group's repository is not set
    }

    private function save_repo($repoid, $username, $repository) {

        $mode = $this->group->mode;
        $members = array();

        // Group mode, check permission
        if ($mode && !$this->group->ismember) {
            return false;
        }

        if ($mode && $this->group->ismember) {
            //TODO: fill members' info in Array $members
        }

        if ($repoid) {
            return $this->github_repo->update_repo($repoid, $username, $repository, $members);
        } else {
            return $this->github_repo->add_repo($uesrname, $repository, $members, $mode);
        }

        return false;
    }

    private function edit_form() {

        // Group mode, check permission
        if ($this->group->mode && !$this->group->ismember) {
            return $this->show_repo();
        }

        //TODO: form contain username/organization and repository's name
    }

    private function get_members_by_id($id, $fields = array(), $sort = 'lastname ASC') {

        if(is_array($fields) && $fields) {
            foreach($fields as $k => $v) {
                if (substr($v, 0, 2) !== 'u.') {
                    $fields[$k] = 'u.'.$v;
                }
            }
            $fields = implode($fields, ',');
        } else {
            $fields = 'u.*';
        }

        return groups_get_members($id, $fields, $sort);
    }
}
