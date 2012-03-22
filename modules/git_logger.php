<?php

class git_logger {

    private $assignment;

    private $_table = 'assignment_github_logs';

    function __construct($assignment) {
        $this->assignment = $assignment;
    }

    function get_by_commit($commit) {

        $conditions = array();
        $conditions['commit'] = $commit;
        return $this->get_records($conditions);
    }

    function get_by_group($id, $since = '', $until = '', $limitfrom = 0, $limitnum = 0) {

        return $this->get_by_group_user($id, 0, $since, $until, $limitfrom, $limitnum);
    }

    function get_by_user($id, $since = '', $until = '', $limitfrom = 0, $limitnum = 0) {

        $conditions = array();
        $conditions['userid'] = $id;
        $conditions['groupid'] = 0;
        return $this->get_records($conditions);
    }

    function get_by_group_user($groupid, $userid, $since = '', $until = '', $limitfrom = 0, $limitnum = 0) {

        $conditions = array();
        if ($userid) {
            $conditions['userid'] = $userid;
        }
        $conditions['groupid'] = $groupid;
        return $this->get_records($conditions);
    }

    public function add_record($log) {
        global $DB;
        if ($log->assignment != $this->assignment) {
            return false;
        }
        return $DB->insert_record($this->_table, $log);
    }

    private function get_records($conditions, $sort='date DESC', $fields=array(), $limitfrom=0, $limitnum=0) {
        global $DB;

        if(!$fields) {
            $fields = '*';
        } else {
            $fields = implode($fileds, ',');
        }

        try {
            $logs = array();
            $result = $DB->get_records($this->_table, $conditions, $sort, $fields, $limitfrom=0, $limitnum=0);
            if ($result) {
                foreach($result as $k => $v) {
                    $logs[$v->commit] = $v;
                }
            }
            return $logs;
        } catch(Exception $e) {
            return false;
        }
    }
}
