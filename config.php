<?php

define('ASSIGNMENT_GITHUB_ROOT', dirname(__FILE__).'/');
define('ASSIGNMENT_GITHUB_MODULES', dirname(__FILE__).'/modules/');
define('ASSIGNMENT_GITHUB_LIB', dirname(__FILE__).'/lib/Github/');

require_once(ASSIGNMENT_GITHUB_LIB.'Autoloader.php');
require_once(ASSIGNMENT_GITHUB_MODULES.'git.php');

Github_Autoloader::register();
