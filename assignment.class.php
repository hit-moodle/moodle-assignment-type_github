<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/mod/assignment/lib.php');
require_once(dirname(__FILE__).'/config.php');

class assignment_github extends assignment_base {

    private $group;

    private $github_repo;

    private $github_root = 'https://github.com';

    private $capability = array();

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

    /**
     * Init group settings. It will init the private varible group
     * $this->group object
     *   mode: if this assignment is group mode
     *   id: group id
     *   ismember: if current user is in this group
     *   members: array
     */
    private function init_group() {
        global $USER;

        $this->group->mode = groups_get_activity_groupmode($this->cm);
        $aag = has_capability('moodle/site:accessallgroups', $this->context);

        if ($this->group->mode == VISIBLEGROUPS or $aag) {
            $allowedgroups = groups_get_all_groups($this->cm->course, 0, $this->cm->groupingid); // only assigned groups
        } else {
            $allowedgroups = groups_get_all_groups($this->cm->course, $USER->id, $this->cm->groupingid); // only assigned groups
        }

        $this->group->id = groups_get_activity_group($this->cm, true, $allowedgroups);
        $this->group->ismember = groups_is_member($this->group->id);

        if ($this->group->id) {
            $this->group->members = $this->get_members_by_id($this->group->id);
        }
    }

    private function init_permission() {

        if (!$this->group) {
            $this->init_group();
        }
    
        if (has_capability('mod/assignment:grade', $this->context)) {
            $this->capability['view'] = true;
            $this->capability['edit'] = true;
            $this->capability['grade'] = true;
            return;
        }

        if ($this->group->mode == VISIBLEGROUPS) {
            $this->capability['view'] = true;
            if ($this->group->ismember) {
                $this->capability['edit'] = true;
            }
            return;
        }

        if ($this->group->mode == SEPARATEGROUPS) {
            if ($this->group->ismember) {
                $this->capability['view'] = true;
                $this->capability['edit'] = true;
            }
            return;
        }

        // view edit
    }

    /**
     * Try to get the github repository of this assignment
     *
     * @return object
     */
    private function get_repo() {
        global $USER;

        if (!$this->github_repo) {
            $this->github_repo = new github_repo($this->course->id, $this->assignment->id, $USER->id, $this->group->id);
        }

        if($this->group->mode) {
            return $this->github_repo->get_by_group($this->group->id);
        } else {
            return $this->github_repo->get_by_user($USER);
        }
    }

    private function view_repos() {
        global $USER, $OUTPUT, $PAGE;

        $this->init_group();
        $this->init_permission();

        $editmode = optional_param('edit', 0, PARAM_BOOL);
        $this->github_repo = new github_repo($this->course->id, $this->assignment->id, $USER->id, $this->group->id);
        $repo = $this->get_repo();
        
        echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
        echo html_writer::tag('h3', get_string('githubreposetting', 'assignment_github'));

        $mform = new mod_assignment_github_edit_form(null, array('group' => $this->group));
        if ($github_info = $mform->get_submitted_data()) {
            $saved = $this->save_repo($repo->id, $github_info);
            $repo = $this->get_repo();

            if (!$saved) {
                // TODO: display an error message
            }
        }

        if(!$repo || $editmode) {
            $this->edit_form($repo);
        } else {
            $this->show_repo($repo);
        }
        echo $OUTPUT->box_end();
    }

    /**
     * Show a table contains the repository's infomation. If repository is not set, 
     * show a message to the guest.
     *
     * @param object $repository is optional, default to null. If it's null, this method
     *               will call get_repo to get the repository's infomation
     */
    private function show_repo($repository = null) {
        global $USER, $OUTPUT, $PAGE;

        if (!$repository) {
            $repository = $this->get_repo();
        }

        if ($repository) {
            echo $OUTPUT->box_start('generalbox boxaligncenter');
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

            $user_link = html_writer::link($this->github_root . '/' . $repository->username,
                                           $repository->username,
                                           array('target' => '_blank'));
            $repo_link = html_writer::link($repository->url,
                                           $repository->repo,
                                           array('target' => '_blank'));

            $username_cell_content->text = $user_link;
            $repository_cell_content->text = $repo_link;

            $username_row->cells = array($username_cell_header, $username_cell_content);
            $repository_row->cells = array($repository_cell_header, $repository_cell_content);

            $single_row = new html_table_row();
            $single_cell_header = new html_table_cell();
            $single_cell_header->header = true;
            $single_cell_header->text = get_string('memberlist', 'assignment_github');
            $single_row->cells = array($single_cell_header);

            $table->data = array($username_row, $repository_row, $single_row);

            if ($repository->members) {
                foreach($repository->members as $id => $username) {
                    $member_row = new html_table_row();
                    $member_cell_header = new html_table_cell();
                    $member_cell_content = new html_table_cell();
                    $member_cell_header->header = true;
                    $member_cell_header->text = fullname($this->group->members[$id]);
                    $member_cell_content->text = html_writer::link($this->github_root . '/' . $username,
                                                                   $username,
                                                                   array('target' => '_blank'));
                    $member_row->cells = array($member_cell_header, $member_cell_content);
                    $table->data[] = $member_row;
                }
            }

            echo html_writer::table($table);
            echo $OUTPUT->box_end('generalbox boxaligncenter');

            // Group mode and the user is not a member of this group,
            // do not show the edit button
            if (!$this->capability['edit']) {
                return;
            }

            $url = $PAGE->url;
            if ($this->group->mode) {
                $url->param('group', $this->group->id);
            }
            
            echo $OUTPUT->edit_button($url);
            return;
        }

        if ($this->group->mode && !$this->group->id) {
            echo $OUTPUT->notification(get_string('choosegroup', 'assignment_github'));
            return;
        }

        echo $OUTPUT->notification(get_string('repohasnotset', 'assignment_github'));
    }

    /**
     * Save user's github repository settings.
     *
     * @param integer $repoid optional, default to null. If it's null, add a new record, else not, update
     *                the record which id is $repoid.
     * @param object $github_info is the data submitted from edit form.
     * @return bool true if successfully saved, else false.
     */
    private function save_repo($repoid = null, $github_info) {

        $groupmode = $this->group->mode;
        $members = array();

        // Group mode, check permission
        if (!$this->capability['edit']) {
            return false;
        }

        if ($groupmode) {
            foreach($this->group->members as $member) {
                $element_name = 'member_' . $member->id;
                $members[$member->id] = $github_info->$element_name;
            }
        }

        if ($repoid) {
            return $this->github_repo->update_repo($repoid, $github_info->username, $github_info->repo, $members);
        } else {
            return $this->github_repo->add_repo($github_info->username, $github_info->repo, $members, $groupmode);
        }

        return false;
    }

    /**
     * Display a edit form to user
     *
     * @param object $repo optional, if it's not null, repo's infomation will be filled in the form
     */
    private function edit_form($repo = null) {
        global $PAGE;

        // Group mode, check permission
        if (($this->capability['view'] && !$this->capability['edit']) || !$this->group->id) {
            return $this->show_repo($repo);
        }

        $url = $PAGE->url;
        $url->remove_params('edit');
        $mform = new mod_assignment_github_edit_form($url->out(), array('group' => $this->group, 'repo' => $repo));
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
        $repo = $this->_customdata['repo'];

        // visible elements
        $mform->addElement('text', 'username', 'Username');
        @$mform->setHelpButton('username', array('editusername', 'username', 'assignment_github'));
        $mform->setType('username', PARAM_ALPHANUMEXT);
        $mform->addRule('username', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'repo', 'Repository');
        @$mform->setHelpButton('repo', array('editrepository', 'repo', 'assignment_github'));
        $mform->setType('repo', PARAM_ALPHANUMEXT);
        $mform->addRule('repo', get_string('required'), 'required', null, 'client');

        $group = $this->_customdata['group'];
        if ($group->mode) {
            foreach($group->members as $member) {
                $element_name = 'member_' . $member->id;
                $mform->addElement('text', $element_name, get_string('member', 'assignment_github') . fullname($member));
                @$mform->setHelpButton($element_name, array('member', $element_name, 'assignment_github'));
                $mform->setType($element_name, PARAM_ALPHANUMEXT);
            }
        }

        if ($repo) {
            $mform->setDefault('username', $repo->username);
            $mform->setDefault('repo', $repo->repo);
            if ($repo->members) {
                foreach($repo->members as $id => $username) {
                    $element_name = 'member_' . $id;
                    $mform->setDefault($element_name, $username);
                }
            }
        }

        // buttons
        $this->add_action_buttons(false);
    }
}
