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

        $this->work_tree = $work_tree;
    }

    function has_work_tree() {

        $dir = getcwd();
        chdir("$this->workspace");
        $result = is_dir("$this->work_tree");
        clearstatcache(true, "$this->work_tree");
        chdir("$dir");
        return $result;
    }

    function pull($create = false, $git = null, $work_tree = null) {

        $params = $this->cmd->prepare_params();
        $params->git = $git;
        if ($create) {
            $command = 'clone';
            $params->work_tree = $work_tree;
        } else {
            $command = 'pull';
            $params->work_tree = $this->work_tree;
        }

        return $this->cmd->exec($command, $params);
    }

    function get_log($custom_params = array()) {

        $params = $this->cmd->prepare_params();
        $params->work_tree = $this->work_tree;
        $default_params = array(
            '--shortstat',
            '--no-merges',
            '--pretty=format:"[C:%H][A:%an]%n[E:%ae][D:%at][%s]%n"',
        );
        $params->other = array_merge($default_params, $custom_params);

        $response = $this->cmd->exec('log', $params);
        if (!$response) {
            return null;
        }
        preg_match_all('/\[C:([^\]]+)\]\[A:([^\n]+)\]\n\[E:([^\]]+)\]\[D:([^\]]+)\]\[([^\n]*)\]\n'.
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

    function get_log_by_range($since = '', $until = '') {

        $params = array();
        if ($since) {
            $params[] = "--since={$since}";
        }
        if ($until) {
            $params[] = "--until={$until}";
        }
        return $this->get_log($params);
    }

    function show_commit() {
    }
}
