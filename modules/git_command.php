<?php

class git_command {

    private $workspace;

    private $command;

    private static $terminals = array();

    private function __construct($workspace = null) {
        global $ASSIGNMENT_GITHUB;

        $default = $ASSIGNMENT_GITHUB->workspace;
        if (!is_dir("$default")) {
            mkdir($default, 0777);
        }
        clearstatcache();

        if (empty($workspace) || !is_dir("$workspace") || !is_writable("$workspace")) {
            $workspace = $default;
        }
        $this->workspace = $workspace;
        $this->command = $ASSIGNMENT_GITHUB->command;
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
        global $ASSIGNMENT_GITHUB;

        $param = new stdClass();
        $param->worktree = '';
        $param->url = '';
        $param->branch = $ASSIGNMENT_GITHUB->branch;
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
        return $this->delete_dir($dir);
    }

    private function get_worktree($param) {

        if (substr($param->worktree, 0, 1) == '/') {
            return $param->worktree;
        }
        return "{$this->workspace}/{$param->worktree}";
    }

    public function delete($pattern) {

        $worktrees = scandir($this->workspace);
        foreach($worktrees as $worktree) {
            if ($worktree != '.' && $worktree != '..') {
                if (preg_match($pattern, $worktree)) {
                    $this->delete_dir($this->workspace.'/'.$worktree);
                }
            }
        }
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

    private function delete_dir($dir) {

        $dir = rtrim($dir, '/');
        if (!is_dir($dir)) {
            return;
        }
        $objects = scandir($dir);
        foreach($objects as $object) {
            if ($object != '.' && $object != '..') {
                $target = $dir.'/'.$object;
                if (filetype($target) == 'dir') {
                    $this->delete_dir($target);
                } else {
                    unlink($target);
                }
            }
        }
        rmdir($dir);
    }
}
