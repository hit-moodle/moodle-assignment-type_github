<?php

class git {

    private $_course;

    private $_assignment;

    private $_user;

    private $_group;

    private $_table = 'assignment_github_repos';

    private $_server = array();

    private $_api = array();

    private $_service_list = array();

    /**
     * @param integer $course
     * @param integer $assignment
     * @param integer $user
     * @param integer $group
     */
    public function __construct($course, $assignment, $user = 0, $group = 0) {
        global $CFG, $ASSIGNMENT_GITHUB;

        $this->_course = $course;
        $this->_assignment = $assignment;
        $this->_user = $user;
        $this->_group = $group;

        foreach($ASSIGNMENT_GITHUB->server as $server => $cfg) {
            $this->_server[$cfg['domain']] = $server;
            $this->_api[$server] = $cfg['service'];
        }
    }

    /**
     * Parse the git repository service site accoding to the url
     *
     * @param string $url is a git repository url
     * @return string if we support the site, else null
     */
    public function parse_git_server($url) {

        if (!$url) {
            return null;
        }

        // cut protocol: http, git, ssh ...
        $url_no_protocal = preg_replace('/https?:\/\/([^@]+@)?|https?:\/\/|git@|git:\/\/|ssh:\/\//i', '', $url);
        $param_list = preg_split('/[\/:]/', $url_no_protocal);
        $site = $param_list[0];
        foreach($this->_server as $domain => $server) {
            if (preg_match('/'.$domain.'$/i', $site)) {
                return $server;
            }
        }

        return null;
    }

    /**
     * Get the api object
     *
     * @param string $server
     * @return object if we have the api class, else null
     */
    public function get_api_service($server) {
    
        if (!array_key_exists($server, $this->_api)) {
            return null;
        }

        if (array_key_exists($server, $this->_service_list)) {
            return $this->_service_list[$server];
        }

        $class_name = $this->_api[$server];
        include_once(ASSIGNMENT_GITHUB_MODULES . $class_name . '.php');
        $this->_service_list[$server] = new $class_name();
        return $this->_service_list[$server];
    }

    public function get_api_service_by_url($url) {

        $server = $this->parse_git_server($url);
        return $this->get_api_service($server);
    }

    /**
     * Get user's repository in database
     *
     * @param integer $userid
     * @return object
     */
    public function get_by_user($userid) {

        $conditions = array(
            'course' => $this->_course,
            'assignment' => $this->_assignment,
            'userid' => intval($userid),
            'groupid' => 0,
        );

        $result = $this->get_records($conditions);
        if (!$result || !is_array($result)) {
            return false;
        }

        return array_pop($result);
    }

    /**
     * Get group's repository in database
     *
     * @param integer $groupid
     * @return object
     */
    public function get_by_group($groupid) {

        $conditions = array(
            'course' => $this->_course,
            'assignment' => $this->_assignment,
            'userid' => 0,
            'groupid' => intval($groupid),
        );

        $result = $this->get_records($conditions);
        if (!$result || !is_array($result)) {
            return false;
        }

        return array_pop($result);
    }

    /**
     * List all repos of an assignment
     *
     * @param integer $mode is group mode
     * @return array|false
     */
    public function list_all($mode) {

        $conditions = array(
            'course' => $this->_course,
            'assignment' => $this->_assignment,
        );

        if ($mode == SEPARATEGROUPS || $mode == VISIBLEGROUPS) {
            $conditions['userid'] = 0;
            $key = 'groupid';
        } else if ($mode == NOGROUPS) {
            $conditions['groupid'] = 0;
            $key = 'userid';
        } else {
            throw new Exception(get_string('unknowntype', 'assignment_github'));
            return false;
        }

        $result = $this->get_records($conditions);
        if (!$result || !is_array($result)) {
            return false;
        }

        $repos = array();
        foreach($result as $v) {
            $repos[$v->$key] = $v;
        }

        return $repos;
    }

    /**
     * Get git repository records from database
     *
     * @param array $conditions
     * @param string $sort
     * @param array $fields
     * @param integer $limitfrom
     * @param integer $limitnum
     * @return array
     */
    private function get_records($conditions, $sort='', $fields=array(), $limitfrom=0, $limitnum=0) {
        global $DB;

        if(!$fields) {
            $fields = '*';
        } else {
            $fields = implode($fileds, ',');
        }

        try {
            $result = $DB->get_records($this->_table, $conditions, $sort, $fields, $limitfrom, $limitnum);
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

    private function convert_record($record) {

        $record->members = json_decode($record->members, true);
        return $record;
    }

    private function fetch_repo_info($url) {

        $server = $this->parse_git_server($url);
        if (!$server) {
            throw new Exception(get_string('unknownserver', 'assignment_github'));
            return false;
        }

        $service = $this->get_api_service($server);
        if (!$service) {
            throw new Exception(get_string('serviceerror', 'assignment_github'));
            return false;
        }

        try {
            $repo = $service->get_repo_info($url);
            if (!$repo) {
                throw new Exception(get_string('reponotfind', 'assignment_github'));
                return false;
            }
        } catch (Exception $e) {
            throw new Exception(get_string('serviceerror', 'assignment_github'));
        }

        $repo['server'] = $server;
        return $repo;
    }

    public function add_repo($url, $type) {
        
        try {
            $repo = $this->fetch_repo_info($url);
        } catch(Exception $e) {
            throw new Exception($e->getMessage());
            return false;
        }

        $data = array(
            'repo' => $repo['name'],
            'server' => $repo['server'],
            'url' => $url,
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
            throw new Exception(get_string('serviceerror', 'assignment_github'));
            return false;
        }
    }

    public function update_repo($id, $url) {

        try {
            $repo = $this->fetch_repo_info($url);
        } catch(Exception $e) {
            throw new Exception($e->getMessage());
            return false;
        }

        $data = array(
            'id' => $id,
            'repo' => $repo['name'],
            'server' => $repo['server'],
            'url' => $url,
        );

        return $this->update_record($data);
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

        $data->updated = time();
        $data->updated_user = $this->_user;
        
        try {
            return $DB->update_record($this->_table, $data);
        } catch(Exception $e) {
            throw new Exception(get_string('serviceerror', 'assignment_github'));
            return false;
        }
    }
}
