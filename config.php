<?php

define('ASSIGNMENT_GITHUB_ROOT', dirname(__FILE__).'/');
define('ASSIGNMENT_GITHUB_MODULES', dirname(__FILE__).'/modules/');
define('ASSIGNMENT_GITHUB_LIB', dirname(__FILE__).'/lib/Github/');

require_once(ASSIGNMENT_GITHUB_LIB.'Autoloader.php');
require_once(ASSIGNMENT_GITHUB_MODULES.'git.php');
require_once(ASSIGNMENT_GITHUB_MODULES.'git_command.php');
require_once(ASSIGNMENT_GITHUB_MODULES.'git_analyzer.php');
require_once(ASSIGNMENT_GITHUB_MODULES.'git_logger.php');

Github_Autoloader::register();

// plugin config
global $ASSIGNMENT_GITHUB;
$ASSIGNMENT_GITHUB = new stdClass();
$ASSIGNMENT_GITHUB->command = 'git';                         // path of git on the server
$ASSIGNMENT_GITHUB->workspace = $CFG->dataroot . '/github';  // default path where the plugin place the repositories
$ASSIGNMENT_GITHUB->branch = 'master';                       // the branch which the plugin analyze by default

$ASSIGNMENT_GITHUB->server = array(
    'github' => array('domain' => 'github.com', 'service' => 'service_github_api'),
    // 'bitbucket' => array('domain' => 'bitbucket.org', 'service' => 'service_bitbucket_api'),
    // 'googlecode' => array('domain' => 'code.google.com', 'service' => 'service_googlecode_api'),
    // 'sourceforge' => array('domain' => 'sourceforge.net', 'service' => 'service_sourceforge_api'),
);
