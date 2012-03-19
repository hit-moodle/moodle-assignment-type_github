<?php

class git_analyzer {

    private $work_tree;

    private $cmd;

    private $workspace;

    function __construct($workspace = null) {
        $this->cmd = new git_command($workspace);
        $this->workspace = $this->cmd->get_workspace();
    }

    function set_work_tree($work_tree) {

        $dir = getcwd();
        chdir("$this->workspace");
        if (is_dir("$work_tree")) {
            $this->work_tree = $work_tree;
        }
        chdir("$dir");
    }

    function get_log() {

        if (empty($this->work_tree)) {
            return null;
        }

        $params = $this->cmd->prepare_params();
        $params->work_tree = $this->work_tree;
        $params->other = array(
            '--shortstat',
            '--no-merges',
            '--pretty=format:"[C:%H][A:%an][E:%ae][D:%at][%s]%n"',
        );

        $response = $this->cmd->exec('log', $params);
        if (!$response) {
            return null;
        }
        preg_match_all('/\[C:([^\]]+)\]\[A:([^\]]+)\]\[E:([^\]]+)\]\[D:([^\]]+)\]\[([^\n]*)\]\n'.
                       '\s*(\d+) files changed, (\d+) insertions\(\+\), (\d+) deletions\(-\)/i',
                       $response, $matches, PREG_SET_ORDER);
        if (!$matches) {
            return null;
        }

        $logs = array();
        foreach($matches as $match) {
            $log = new stdClass();
            $log->commit = $match[1];
            $log->author = $match[2];
            $log->email = $match[3];
            $log->date = $match[4];
            $log->subject = $match[5];
            $log->files = $match[6];
            $log->insertions = $match[7];
            $log->deletions = $match[8];
            $logs[$log->commit] = $log;
        }
        return $logs;
    }

    function get_log_by_range() {
    }

    function get_log_by_commit() {
    }

    function get_detail_by_commit() {
    }
}
