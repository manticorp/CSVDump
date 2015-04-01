<?php

define('APPLICATION_PATH', realpath(__DIR__.'/../'));

$db = array();
$db['host']     = "localhost";
$db['user']     = "root";
$db['password'] = "password";
$db['db']       = "test";

$memoryLimit = '512M';  // Memory Limit for scripts to run - increase for large inputs (default: 512M)
$timezone    = 'UTC';   // Timezone
$timeLimit   = 60*10;   // Time limit - increase for large inputs (default: 10 minutes)

$vardir = APPLICATION_PATH . DIRECTORY_SEPARATOR;
$puOptions = array(
    'lineBreak'   => "\n",
    'filename'    => (isset($_GET['progressFn']) ? $vardir . $_GET['progressFn'] : $vardir . 'progress.json'),
    'totalStages' => 1,
    'autocalc'    => true,
);