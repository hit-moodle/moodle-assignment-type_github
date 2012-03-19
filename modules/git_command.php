<?php

class git_command {

    private $workspace;

    function __construct($workspace = null) {
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

    function get_workspace() {

        return $this->workspace;
    }

    function prepare_params() {

        $param = new stdClass();
        $param->work_tree = '';
        $param->git = '';
        $param->target_dir = '';
        $param->other = array();
        return $param;
    }

    function exec($command, $param) {

        if (empty($this->workspace)) {
            return false;
        }

        $command = 'git_'.$command;
        $dir = getcwd();
        chdir("$this->workspace");
        if (method_exists($this, $command)) {
            $result = $this->$command($param);
        } else {
            $result = false;
        }
        chdir("$dir");
        return $result;
    }

    private function git_clone($param) {

        if (!$param->git || !$param->target_dir) {
            return false;
        }

        $command = 'git clone '.$param->git.' '.$param->target_dir;
        return shell_exec($command);
    }

    private function git_pull($param) {

        if (!is_dir("$param->work_tree")) {
            clearstatcache();
            return false;
        }

        chdir("$param->work_tree");
        $command = 'git pull';
        return shell_exec($command);
    }

    private function git_log($param) {

        if (!is_dir("$param->work_tree")) {
            clearstatcache();
            return false;
        }

        chdir("$param->work_tree");
        $command = 'git log';
        foreach($param->other as $p) {
            $command .= ' '.$p;
        }

        return shell_exec($command);
    }
}
