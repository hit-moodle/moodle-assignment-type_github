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

        $this->view_repos();

        $this->view_feedback();

        $this->view_dates();

        $this->view_footer();
    }

    private function init_group() {
        global $USER;

        $this->group->mode = groups_get_activity_groupmode($this->cm);
        $this->group->id = groups_get_activity_group($this->cm);
        $this->group->ismember = groups_is_member($this->group->id);
    }

    private function view_repos() {
        global $USER, $OUTPUT, $PAGE;

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

        $mform = new mod_assignment_github_edit_form();

        if ($mform->is_cancelled()) {
            redirect($PAGE->url);
        }

        $github_username = optional_param('username', '', PARAM_ALPHANUMEXT);
        $github_repository = optional_param('repo', '', PARAM_ALPHANUMEXT);

        echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
        echo '<h3>'.get_string('githubreposetting', 'assignment_github').'</h3>';

        if(!$repo || $editmode) {
            if ($github_username && $github_repository) {
                $this->save_repo($repo->id, $github_username, $github_repository);
                $this->show_repo();
            } else {
                $this->edit_form($repo);
            }
        } else {
            $this->show_repo($repo);
        }
        echo $OUTPUT->box_end();
    }

    private function show_repo($repository = null) {
        global $USER, $OUTPUT;

        if (!$repository) {
            if($this->group->mode) {
                $repository = $this->github_repo->get_by_group($this->group->id);
            } else {
                $repository = $this->github_repo->get_by_user($USER);
            }
        }

        if ($repository) {
            $table = new html_table();

            $username_row = new html_table_row();
            $username_cell_header = new html_table_cell();
            $username_cell_content = new html_table_cell();
            $repository_row = new html_table_row();
            $repository_cell_header = new html_table_cell();
            $repository_cell_content = new html_table_cell();

            $username_cell_header->text = get_string('username/organization', 'assignment_github');
            $repository_cell_header->text = get_string('repositoryname', 'assignment_github');
            $username_cell_header->header = $repository_cell_header->header = true;
            $username_cell_content->text = $repository->username;
            $repository_cell_content->text = $repository->repo;

            $username_row->cells = array($username_cell_header, $username_cell_content);
            $repository_row->cells = array($repository_cell_header, $repository_cell_content);

            $table->data = array($username_row, $repository_row);

            echo html_writer::table($table);
            return;
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

    private function edit_form($repo = null) {

        // Group mode, check permission
        if ($this->group->mode && !$this->group->ismember) {
            return $this->show_repo();
        }

        $mform = new mod_assignment_github_edit_form();

        if ($repo) {
            $mform->set_data(array('username' => $repo->username, 'repo' => $repo->repo));
        }
        $mform->display();

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

class mod_assignment_github_edit_form extends moodleform {

    function definition() {

        $mform = $this->_form;

        // visible elements
        $mform->addElement('text', 'username', 'Username');
        $mform->setHelpButton('username', array('editusername', 'username', 'assignment_github'));
        $mform->setType('username', PARAM_ALPHANUMEXT);
        $mform->addRule('username', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'repo', 'Repository');
        $mform->setHelpButton('repo', array('editrepository', 'repo', 'assignment_github'));
        $mform->setType('repo', PARAM_ALPHANUMEXT);
        $mform->addRule('repo', get_string('required'), 'required', null, 'client');

        // buttons
        $this->add_action_buttons();
    }
}
