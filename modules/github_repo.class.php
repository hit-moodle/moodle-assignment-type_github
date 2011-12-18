<?php

class github_repo {

    private $_course;
    private $_assignment;
    private $_user;
    private $_group;
    private $_table;
    private $_github;
    private $_time_fields = array('repo_created', 'created', 'updated');

    public function __construct($course, $assignment, $user, $group = 0) {
        global $CFG;

        $this->_course = $course;
        $this->_assignment = $assignment;
        $this->_user = $user;
        $this->_group = $group;
        $this->_table = 'assignment_github_repos';
        $this->_github = new Github_Client();
    }

    public function get_by_user($user) {

        $conditions = array(
            'course' => $this->_course,
            'assignment' => $this->_assignment,
            'groupid' => 0,
        );

        if (is_object($user)) {
            $conditions['userid'] = $user->id;
        } else if (is_int($user)) {
            $conditions['userid'] = $user;
        } else {
            return false;
        }

        $result = $this->get_records($conditions);
        if (!$result || !is_array($result)) {
            return false;
        }

        return array_pop($result);
    }

    public function get_by_group($group) {

        $conditions = array(
            'course' => $this->_course,
            'assignment' => $this->_assignment,
            'userid' => 0,
        );

        if (is_object($group)) {
            $conditions['groupid'] = $group->id;
        } else if (is_int($group)) {
            $conditions['groupid'] = $group;
        } else {
            return false;
        }

        $result = $this->get_records($conditions);
        if (!$result || !is_array($result)) {
            return false;
        }

        return array_pop($result);
    }

    private function convert_record($record) {

        $record->members = json_decode($record->members, true);

        // time convert to Y-m-d H:i:s
        foreach($this->_time_fields as $field) {
            $record->$field = date('Y-m-d H:i:s', $record->$field);
        }
        return $record;
    }

    private function get_records($conditions, $sort='', $fields=array(), $limitfrom=0, $limitnum=0) {
        global $DB;

        if(!$fields) {
            $fields = '*';
        } else if (is_array($fields)) {
            $fields = implode($fileds, ',');
        } else {
            return false;
        }

        try {
            $result = $DB->get_records($this->_table, $conditions, $sort, $fields, $limitfrom=0, $limitnum=0);
            if ($result) {
                foreach($result as $k => $v) {
                    $result[$k] = $this->convert_record($v);
                }
            }
            return $result;
        } catch(Exception $e) {
            return false;
        }
    }

    public function add_repo($username, $repo, $members, $type) {
        
        try {
            $repo = $this->get_repo_info($username, $repo);
        } catch(Exception $e) {
            throw new Exception($e);
            return false;
        }

        $data = array(
            'username' => $username,
            'repo' => $repo,
            'members' => json_encode($members),
            'url' => $repo->url,
            'owner' => $repo->owner,
            'repo_created' => strtotime($repo->created_at),
        );

        return $this->add_record($data, $type);
    }

    private function add_record($data, $type) {
        global $DB;

        if (is_array($data)) {
            $data = (object)$data;
        } else if (!is_object($data)) {
            return false;
        }

        $data->course = $this->_course;
        $data->assignment = $this->_assignment;

        if ($type == NOGROUPS) {
            $data->userid = $this->_user;
            $data->groupid = 0;
        } else if ($type == SEPARATEGROUPS || $type == VISIBLEGROUPS) {
            $data->userid = 0;
            $data->groupid = $this->_group;
        } else {
            throw new Exception(get_string('unknowntype', 'assignment_github'));
            return false;
        }

        $data->created = time();
        $data->created_user = $this->_user;

        try {
            return $DB->insert_record($this->_table, $data);
        } catch(Exception $e) {
            return false;
        }
    }

    public function update_repo($id, $username, $repo, $members) {

        try {
            $repo = $this->get_repo_info($username, $repo);
        } catch(Exception $e) {
            throw new Exception($e);
            return false;
        }

        $data = array(
            'id' => $id,
            'username' => $username,
            'repo' => $repo,
            'members' => json_encode($members),
            'url' => $repo->url,
            'owner' => $repo->owner,
            'repo_created' => strtotime($repo->created_at),
        );

        return $this->update_record($data, $type);
    }

    private function update_record($data) {
        global $DB;

        if (is_array($data)) {
            $data = (object)$data;
        } else if (!is_object($data)) {
            return false;
        }

        if (!$data->id) {
            return false;
        }
        
        try {
            return $DB->update_record($this->_table, $data);
        } catch(Exception $e) {
            return false;
        }
    }

    private function get_repo_info($username, $repository) {

        try {
            $repo = $this->_github->getRepoApi()->show($username, $repository);
        } catch(Exception $e) {
            throw new Exception($e);
        }

        return $repo;
    }
}
