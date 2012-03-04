<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/mod/assignment/lib.php');
require_once(dirname(__FILE__).'/config.php');

class assignment_github extends assignment_base {

    private $group;

    private $git;

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

        if (!$this->git) {
            $this->git = new git($this->course->id, $this->assignment->id, $USER->id, $this->group->id);
        }

        if($this->group->mode) {
            return $this->git->get_by_group($this->group->id);
        } else {
            return $this->git->get_by_user($USER->id);
        }
    }

    private function view_repos() {
        global $USER, $OUTPUT, $PAGE;

        $this->init_group();
        $this->init_permission();

        $editmode = optional_param('edit', 0, PARAM_BOOL);
        $repo = $this->get_repo();
        
        echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
        echo html_writer::tag('h3', get_string('githubreposetting', 'assignment_github'));

        $mform = new mod_assignment_github_edit_form(null, array('group' => $this->group));
        if (!$mform->is_cancelled() && $github_info = $mform->get_submitted_data()) {
            $saved = $this->save_repo($repo->id, $github_info);
            $repo = $this->get_repo();

            if (!$saved) {
                // TODO: display an error message
                // $OUTPUT->container_end_all();
                // return;
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
            $service = $this->git->get_api_service($repository->server);

            echo $OUTPUT->box_start('generalbox boxaligncenter');
            echo html_writer::tag('h4', get_string('repository', 'assignment_github'));
            $table = new html_table();

            $repository_row = new html_table_row();
            $repository_cell_header = new html_table_cell();
            $repository_cell_content = new html_table_cell();

            $repository_cell_header->text = get_string('repositoryname', 'assignment_github');
            $repository_cell_header->header = true;

            $links = $service->generate_http_from_git($repository->url);
            $repo_link = html_writer::link($links['repo'],
                                           $repository->repo,
                                           array('target' => '_blank'));

            $repository_cell_content->text = $repo_link;

            $repository_row->cells = array($repository_cell_header, $repository_cell_content);

            $table->data = array($repository_row);
            echo html_writer::table($table);

            if ($repository->members && $this->capability['edit']) {
                echo html_writer::tag('h4', get_string('memberlist', 'assignment_github'));
                $member_table = new html_table();
                foreach($repository->members as $id => $email) {
                    $member_row = new html_table_row();
                    $member_cell_header = new html_table_cell();
                    $member_cell_content = new html_table_cell();
                    $member_cell_header->header = true;
                    $member_cell_header->text = fullname($this->group->members[$id]);
                    $member_cell_content->text = html_writer::link('mailto:' . $email,
                                                                   $email,
                                                                   array('target' => '_blank'));
                    $member_row->cells = array($member_cell_header, $member_cell_content);
                    $member_table->data[] = $member_row;
                }
                echo html_writer::table($member_table);
            }

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
            $url->param('sesskey', sesskey());
            $url->param('edit', '1');
            echo $OUTPUT->single_button($url, get_string('turneditingon'));
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

        try {
            if ($repoid) {
                return $this->git->update_repo($repoid, $github_info->url, $members);
            } else {
                return $this->git->add_repo($github_info->url, $members, $groupmode);
            }
        } catch (Exception $e) {
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
        $mform->addElement('text', 'url', 'Repository');
        @$mform->setHelpButton('url', array('editrepository', 'url', 'assignment_github'));
        $mform->setType('url', PARAM_TEXT);
        $mform->addRule('url', get_string('required'), 'required', null, 'client');

        $group = $this->_customdata['group'];
        if ($group->mode) {
            foreach($group->members as $member) {
                $element_name = 'member_' . $member->id;
                $mform->addElement('text', $element_name, get_string('member', 'assignment_github') . fullname($member));
                @$mform->setHelpButton($element_name, array('member', $element_name, 'assignment_github'));
                $mform->setType($element_name, PARAM_EMAIL);
                $mform->addRule($element_name, get_string('required'), 'required', null, 'client');
            }
        }

        if ($repo) {
            $mform->setDefault('url', $repo->url);
        }

        if ($group->members) {
            foreach($group->members as $id => $user) {
                $element_name = 'member_' . $id;
                if ($repo->members[$id]) {
                    $email = $repo->members[$id];
                } else {
                    $email = $user->email;
                }
                $mform->setDefault($element_name, $email);
            }
        }

        // buttons
        $this->add_action_buttons();
    }
}
