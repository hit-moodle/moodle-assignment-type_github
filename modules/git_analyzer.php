<?php

class git_analyzer {

    private $worktree;

    private $cmd;

    private $workspace;

    private static $_analyzers = array();

    private function __construct($worktree, $workspace = null) {
        $this->cmd = new git_command($workspace);
        $this->worktree = $worktree;
        $this->workspace = $this->cmd->get_workspace();
    }

    public static function init($worktree, $workspace = null) {

        if (empty($worktree)) {
            return null;
        }

        if (empty(self::$_analyzers[$worktree])) {
            self::$_analyzers[$worktree] = new git_analyzer($worktree, $workspace);
        }

        return self::$_analyzers[$worktree];
    }

    function worktree_exists() {

        $dir = getcwd();
        chdir("$this->workspace");
        $result = is_dir("$this->worktree");
        clearstatcache(true, "$this->worktree");
        chdir("$dir");
        return $result;
    }

    function pull($url = null) {

        $params = $this->cmd->prepare_params();
        $params->worktree = $this->worktree;
        if (!empty($url)) {
            $command = 'clone';
            $params->url = $url;
        } else {
            $command = 'pull';
        }

        return $this->cmd->exec($command, $params);
    }

    function get_log($custom_params = array()) {

        $params = $this->cmd->prepare_params();
        $params->worktree = $this->worktree;
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

    function get_branches() {

        $params = $this->cmd->prepare_params();
        $params->worktree = $this->worktree;
        $output = $this->cmd->exec('branch', $params);
        return array_filter(preg_replace('/[\s\*]/', '', explode("\n", $output)));
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

    function delete() {

        $params = $this->cmd->prepare_params();
        $params->worktree = $this->worktree;
        return $this->cmd->exec('delete', $params);
    }
}
