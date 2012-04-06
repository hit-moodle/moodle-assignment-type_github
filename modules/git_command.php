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
        $param->worktree = '';
        $param->url = '';
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
        $command = sprintf('clone %s %s', escapeshellarg($param->url), escapeshellarg($dir));
        return $this->run($this->workspace, $command);
    }

    private function git_pull($param) {

        $dir = $this->get_worktree($param);
        $command = 'pull';
        return $this->run($dir, $command);
    }

    private function git_log($param) {

        $dir = $this->get_worktree($param);
        $command = 'log';
        foreach($param->other as $p) {
            $command .= ' '.$p;
        }
        return $this->run($dir, $command);
    }

    private function git_show($param) {

        $dir = $this->get_worktree($param);
        $command = 'show';
        foreach($param->other as $p) {
            $command .= ' '.$p;
        }
        return $this->run($dir, $command);
    }

    private function get_worktree($param) {

        if (substr($param->worktree, 0, 1) == '/') {
            return $param->worktree;
        }
        return "{$this->workspace}/{$param->worktree}";
    }

    public function run($dir, $command_string) {

        $command = sprintf('cd %s && git %s', escapeshellarg($dir), $command_string);

        ob_start();
        passthru($command, $return_var);
        $output = ob_get_clean();

        if ($return_var !== 0) {
            throw new Exception($output, $return_var);
        }

        return trim($output);
    }
}
