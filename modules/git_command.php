<?php

class git_command {

    private $workspace;

    private $command = 'git';

    private static $terminals = array();

    private function __construct($workspace = null) {
        global $CFG;

        $default = $CFG->dataroot . '/github';
        if (!is_dir("$default")) {
            mkdir($default, 0777);
        }
        clearstatcache();

        if (empty($workspace) || !is_dir("$workspace") || !is_writable("$workspace")) {
            $workspace = $default;
        }
        $this->workspace = $workspace;
    }

    public static function init($workspace = null) {

        $cmd = new git_command($workspace);
        $workspace = $cmd->get_workspace();

        if (empty(self::$terminals[$workspace])) {
            self::$terminals[$workspace] = $cmd;
        }
        return self::$terminals[$workspace];
    }

    function get_workspace() {

        return $this->workspace;
    }

    function prepare_params() {

        $param = new stdClass();
        $param->worktree = '';
        $param->url = '';
        $param->branch = 'master';
        $param->other = array();
        return $param;
    }

    function exec($command, $param) {

        if (empty($this->workspace)) {
            return false;
        }

        $command = 'git_'.$command;
        if (method_exists($this, $command)) {
            return $this->$command($param);
        }
        return false;
    }

    private function git_clone($param) {

        $dir = $this->get_worktree($param);
        $param_string = sprintf('clone -b %s %s %s', $param->branch, escapeshellarg($param->url), escapeshellarg($dir));
        return $this->run($this->workspace, $this->command, $param_string);
    }

    private function git_pull($param) {

        $dir = $this->get_worktree($param);
        $param_string = 'pull';
        return $this->run($dir, $this->command, $param_string);
    }

    private function git_log($param) {

        $dir = $this->get_worktree($param);
        $param_string = 'log';
        foreach($param->other as $p) {
            $param_string .= ' '.$p;
        }
        return $this->run($dir, $this->command, $param_string);
    }

    private function git_show($param) {

        $dir = $this->get_worktree($param);
        $param_string = 'show';
        foreach($param->other as $p) {
            $param_string .= ' '.$p;
        }
        return $this->run($dir, $this->command, $param_string);
    }

    private function git_branch($param) {

        $dir = $this->get_worktree($param);
        $param_string = 'branch';
        foreach($param->other as $p) {
            $param_string .= ' '.$p;
        }
        return $this->run($dir, $this->command, $param_string);
    }

    private function git_delete($param) {

        $dir = $this->get_worktree($param);
        $command = 'rm';
        $param_string = sprintf('-rf %s', escapeshellarg($dir));
        return $this->run($this->workspace, $command, $param_string);
    }

    private function get_worktree($param) {

        if (substr($param->worktree, 0, 1) == '/') {
            return $param->worktree;
        }
        return "{$this->workspace}/{$param->worktree}";
    }

    public function run($dir, $command, $param_string) {

        $command = sprintf('cd %s && %s %s', escapeshellarg($dir), $command, $param_string);

        ob_start();
        passthru($command, $return_var);
        $output = ob_get_clean();

        if ($return_var !== 0) {
            throw new Exception($output, $return_var);
        }

        return trim($output);
    }
}
