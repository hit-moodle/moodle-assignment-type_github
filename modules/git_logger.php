<?php

class git_logger {

    private $assignment;

    private $_table = 'assignment_github_logs';

    function __construct($assignment) {
        $this->assignment = intval($assignment);
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
        return $this->get_records($conditions, $sort='date DESC', null, $limitfrom, $limitnum);
    }

    function get_by_group_user($groupid, $userid, $since = '', $until = '', $limitfrom = 0, $limitnum = 0) {

        $conditions = array();
        if ($userid) {
            $conditions['userid'] = $userid;
        }
        $conditions['groupid'] = $groupid;
        return $this->get_records($conditions, $sort='date DESC', null, $limitfrom, $limitnum);
    }

    function get_statistics_by_group($groupid) {
        global $DB;

        $groupid = intval($groupid);
        if (!$groupid) {
            return null;
        }

        $sql = "SELECT
                  email, MAX(author) AS author, COUNT(*) AS commits, SUM(files) AS files,
                  SUM(insertions) AS insertions, SUM(deletions) AS deletions,
                  SUM(insertions)+SUM(deletions) AS total
                FROM {{$this->_table}}
                WHERE `assignment`= ? AND `groupid`= ?
                GROUP BY `email`
                UNION
                SELECT
                  'total' email, '' author, COUNT(*) AS commits, SUM(files) AS files,
                  SUM(insertions) AS insertions, SUM(deletions) AS deletions,
                  SUM(insertions)+SUM(deletions) AS total
                FROM {{$this->_table}}
                WHERE `assignment`= ? AND `groupid`= ?
                GROUP BY `assignment`";
        $params = array($this->assignment, $groupid, $this->assignment, $groupid);
        return $DB->get_records_sql($sql, $params);
    }

    function get_statistics_by_user($userid) {
        global $DB;

        $userid = intval($userid);
        if (!$userid) {
            return null;
        }

        $sql = "SELECT
                  userid, MAX(author) AS author, MAX(email) AS email, COUNT(*) AS commits, SUM(files) AS files,
                  SUM(insertions) AS insertions, SUM(deletions) AS deletions,
                  SUM(insertions)+SUM(deletions) AS total
                FROM {{$this->_table}}
                WHERE `assignment`= ? AND `userid`= ?
                  AND `groupid`=0
                GROUP BY `userid`";
        $params = array($this->assignment, $userid);
        return $DB->get_records_sql($sql, $params);
    }

    function get_statistics_by_email($email) {
        global $DB;

        if (!$email) {
            return null;
        }

        $sql = "SELECT
                  email, MAX(author) AS author, COUNT(*) AS commits, SUM(files) AS files,
                  SUM(insertions) AS insertions, SUM(deletions) AS deletions,
                  SUM(insertions)+SUM(deletions) AS total
                FROM {{$this->_table}}
                WHERE `assignment`= ? AND `email`= ?
                  AND `groupid`=0
                GROUP BY `email`";
        $params = array($this->assignment, $email);
        return $DB->get_records_sql($sql, $params);
    }

    function get_statistics_by_group_email($groupid, $email) {
        global $DB;

        $groupid = intval($groupid);
        if (!$groupid || !$email) {
            return null;
        }

        $sql = "SELECT
                  email, MAX(author) AS author, COUNT(*) AS commits, SUM(files) AS files,
                  SUM(insertions) AS insertions, SUM(deletions) AS deletions,
                  SUM(insertions)+SUM(deletions) AS total
                FROM {{$this->_table}}
                WHERE `assignment`= ? AND `groupid`= ?
                  AND `email`= ?
                GROUP BY `email`";
        $params = array($this->assignment, $groupid, $email);
        return $DB->get_records_sql($sql);
    }

    function get_user_last_commit($userid) {
        global $DB;

        if (!$userid) {
            return null;
        }

        $sql = "SELECT *
                FROM {{$this->_table}}
                WHERE `assignment`= ? AND `userid`= ?
                ORDER BY `date` DESC LIMIT 0, 1";
        return $DB->get_record_sql($sql, array($this->assignment, $userid));
    }

    function list_all_latest_commits($groupmode) {
        global $DB;

        if ($groupmode) {
            $key = 'groupid';
        } else {
            $key = 'userid';
        }

        $sql = "SELECT *
                  FROM (SELECT `{$key}`, MAX(`date`) AS `date`
                        FROM {{$this->_table}}
                        WHERE `assignment`= ?
                        GROUP BY `{$key}`) AS `a`
             LEFT JOIN {{$this->_table}} AS `b` USING(`{$key}`, `date`)";
        return $DB->get_records_sql($sql, array($this->assignment));
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
            $conditions['assignment'] = $this->assignment;
            $result = $DB->get_records($this->_table, $conditions, $sort, $fields, $limitfrom, $limitnum);
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

    function delete_by_group($id) {
        global $DB;

        $select = "assignment = ? AND groupid = ?";
        $params = array($this->assignment, $id);
        $DB->delete_records_select($this->_table, $select, $params);
    }

    function delete_by_user($id) {
        global $DB;

        $select = "assignment = ? AND userid = ?";
        $params = array($this->assignment, $id);
        $DB->delete_records_select($this->_table, $select, $params);
    }
}
