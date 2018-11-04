<?php

require_once __DIR__ . '/../vendor/autoload.php';
// configs
require_once __DIR__ . '/../configs/daemon.config.php';
// common utilities
require_once __DIR__ . '/utils/common.php';
// logging
require_once __DIR__ . '/utils/logger.php';
// entry point
require_once __DIR__ . '/daemon-service.php';

use Utils\Logger\DaemonLogger;
use Utils\Common;

// create new child process
$child_pid = pcntl_fork();
if ($child_pid) {
// exit the parent process
    exit();
}
// make the child process the main one
posix_setsid();

// read user input
$handle = fopen("php://stdin", "rb");
$file_name = fgets($handle);
if(empty($file_name) || !file_exists($file_name)) {
	$file_name = Configs\DEFAULT_FILE_NAME;
}
$key = Common::getFileId($file_name, Configs\DEFAULT_PROJECT_ID);

$daemon = new DaemonService($key, Configs\DEFAULT_URL, 1, 60);
$daemon->start();
//$daemon->stop();