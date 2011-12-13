<?php

class github_repos {

    const TYPE_SINGLE_USER = 1;
    const TYPE_GROUP = 2;
    private $_course;
    private $_assignment;
    private $_user;
    private $_group;
    private $_table;
    private $_github;

    public function __construct($course, $assignment, $user, $group) {
        global $CFG;
        $this->_course = $course;
        $this->_assignment = $assignment;
        $this->_user = $user;
        $this->_group = $group;
        $this->_table = 'assignment_github_repos';

        //TODO: get a Github API instance
    }

    public function get_by_user($user) {

        $conditions = array(
            'course' => $this->_course,
            'assignment' => $this->_assignment,
            'gid' => 0,
        );

        if (is_object($user)) {
            $conditions['uid'] = $user->id;
        } else if (is_int($user)) {
            $conditions['uid'] = $user;
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
            'uid' => 0,
        );

        if (is_object($group)) {
            $conditions['gid'] = $group->id;
        } else if (is_int($group)) {
            $conditions['gid'] = $group;
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

        //TODO: time convert to Y-m-d H:i:s ?
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
        
        //TODO:Get url, owner, repo_created from Github API

        $data = array(
            'username' => $username,
            'repo' => $repo,
            'members' => json_encode($members),
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

        if ($type === self::TYPE_SINGLE_USER) {
            $data->uid = $this->_user;
            $data->gid = 0;
        } else if ($type === self::TYPE_GROUP) {
            $data->uid = 0;
            $data->gid = $this->_group;
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

        //TODO:Get url, owner, repo_created from Github API

        $data = array(
            'id' => $id,
            'username' => $username,
            'repo' => $repo,
            'members' => json_encode($members),
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
}
