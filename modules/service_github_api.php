<?php

class service_github_api {

    private $_web_root = 'https://github.com';

    private $_map = array(
                          'http' => '/^https:\/\/([\w\d\-_]+)@([^\/]+)\/([\w\d\-_]+)\/([\w\d\-_\.]+)\.git$/i',
                          'ssh' => '/^git@([^:]+):([\w\d\-_]+)\/([\w\d\-_\.]+)\.git$/i',
                          'git' => '/^git:\/\/([^\/]+)\/([\w\d\-_]+)\/([\w\d\-_\.]+)\.git$/i',
                          'web' => '/^https:\/\/([^\/]+)\/([\w\d\-_]+)\/([\w\d\-_\.]+)$/i',
                         );

    private $_rule = array(
                           'http' => 'https://{user}@{server}/{folder}/{repo}.git',
                           'ssh' => 'git@{server}:{folder}/{repo}.git',
                           'git' => 'git://{server}/{folder}/{repo}.git',
                           'web' => 'https://{server}/{folder}/{repo}',
                          );

    private $_client;

    private $_server = 'github';

    public function __construct() {

        include_once(ASSIGNMENT_GITHUB_LIB.'Autoloader.php');
        Github_Autoloader::register();
        $this->_client = new Github_Client();
    }

    public function parse_git_url($url) {

        foreach($this->_map as $type => $pattern) {
            if (preg_match($pattern ,$url, $match)) {
                break;
            }
        }

        if (!$match) {
            return false;
        }

        if ($type == 'http') {
            $git = array(
                'user' => $match[1],
                'server' => $match[2],
                'folder' => $match[3],
                'repo' => $match[4],
            );
        } else {
            $git = array(
                'server' => $match[1],
                'folder' => $match[2],
                'repo' => $match[3],
            );
        }
        $git['type'] = $type;

        return $git;
    }

    public function generate_git_url($git, $type = '') {

        if ($type && array_key_exists($type, $this->_rule)) {
            $rule = $this->_rule[$type];
        } else {
            $rule = $this->_rule[$git['type']];
        }
        unset($git['type']);
        foreach($git as $k => $v) {
            $rule = str_replace('{'.$k.'}', $v, $rule);
        }
        return $rule;
    }

    public function generate_http_url($git) {

        if (!$git) {
            return null;
        }
        
        return array(
            'repo' => $this->_web_root . '/' . $git['folder'] . '/' . $git['repo'],
            'user' => $this->_web_root . '/' . $git['folder'],
        );
    }

    public function generate_http_from_git($url) {

        $git = $this->parse_git_url($url);
        return $this->generate_http_url($git);
    }

    public function generate_commit_url($url, $commit) {

        $git = $this->generate_http_from_git($url);
        $url = $git['repo'];
        return $url . '/commit/' . $commit;
    }

    public function print_nav_menu($url, $return=false) {

        $urls = $this->generate_http_from_git($url);
        $base = $urls['repo'];
        $links = array(
            html_writer::link($base, 'Code', array('target' => '_blank')),
            html_writer::link($base.'/network', 'Network', array('target' => '_blank')),
            html_writer::link($base.'/issues', 'Issues', array('target' => '_blank')),
            html_writer::link($base.'/graphs', 'Stats & Graphs', array('target' => '_blank')),
        );
        $menu = '<div class="git_menu"><ul>';
        foreach($links as $link) {
            $menu .= '<li>'.$link.'</li>';
        }
        $menu .= '</ul></div>';
        if ($return) {
            return $menu;
        }
        echo $menu;
    }

    public function get_repo_info($url) {

        $git = $this->parse_git_url($url);
        if (!$git) {
            throw new Exception(get_string('unrecognizedurl', 'assignment_github'));
            return false;
        }

        try {
            $result = $this->_client->getRepoApi()->show($git['folder'], $git['repo']);
        } catch(Exception $e) {
            throw new Exception($e->getMessage());
            return null;
        }

        if (!$result) {
            return null;
        }

        $repository = array(
            'name' => $result['name'],
            'owner' => $result['owner'],
            'created' => $result['created_at'],
            'public' => intval(!$result['private']),
            'private' => $result['private'],
        );
        return $repository;
    }

    public function get_user($email) {

        try {
            $user = $this->_client->getUserApi()->searchEmail($email);
        } catch (Exception $e) {
            $user = null;
        }
        return $user;
    }

    public function get_user_link($email) {

        $user = $this->get_user($email);
        if ($user) {
            return $this->_web_root . '/' . $user['login'];
        }
    }

    public function auth($username = null, $secret) {

        $this->_client->authenticate(null, $secret, Github_Client::OAUTH_ACCESS_TOKEN);
    }

    public function create($name, $public = true) {

        try {
            $private = !$public;
            return $this->_client->getRepoApi()->create($name, null, null, $private);
        } catch (Exception $e) {
            return null;
        }
        return null;
    }

    public function request_access_url($redirect_uri) {
        global $ASSIGNMENT_GITHUB;

        $cfg = $ASSIGNMENT_GITHUB->server[$this->_server];
        $url = 'https://github.com/login/oauth/authorize?';
        $params = array(
            'client_id='.$cfg['client_id'],
            'scope=user,repo',
            'redirect_uri='.urlencode($redirect_uri),
        );

        return $url . implode('&', $params);
    }

    public function exchange_access_token($code) {
        global $ASSIGNMENT_GITHUB;

        $cfg = $ASSIGNMENT_GITHUB->server[$this->_server];
        $params = array(
            'client_id' => $cfg['client_id'],
            'client_secret' => $cfg['secret'],
            'code' => $code,
        );

        try {
            $response = $this->_client->getHttpClient()->doRequest('https://github.com/login/oauth/access_token', $params, 'POST');
            preg_match('/access_token=([^&]+)&/', $response, $match);
            if (!empty($match[1])) {
                return $match[1];
            }
        } catch (Exception $e) {
            throw new Exception(get_string('serviceerror', 'assignment_github'));
        }

        return null;
    }

    public function get_repo_api() {

        return $this->_client->getRepoApi();
    }
}
