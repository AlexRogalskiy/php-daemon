<?php

// configs
require_once __DIR__ . '/../configs/daemon.config.php';
// utilities
require_once __DIR__ . '/../src/utils/common.php';
// logger
require_once __DIR__ . '/../src/utils/logger.php';

use Utils\Logger\DaemonLogger;
use Utils\Common;

// read user input
$handle = fopen("php://stdin","rb");
$file_name = fgets($handle);
if(empty($file_name) || !file_exists($file_name)) {
	$file_name = Configs\DEFAULT_FILE_NAME;
}
$key = Common::getFileId($file_name, Configs\DEFAULT_PROJECT_ID);

$queue = msg_get_queue($key);

$messages = array(
	array(1, 'message, type 1'),
	array(2, 'message, type 2'),
	array(3, 'message, type 3'),
	array(1, 'message, type 1')
);

foreach($messages as $value) {
	if(msg_send($queue, $value[0], $value[1], 0, 4096, $err)) {
		DaemonLogger::getInstance()->debug('Message send');
	} else {
		DaemonLogger::getInstance()->error($err);
	}
}

DaemonLogger::getInstance()->debug('send messages = ' . count($messages));
//posix_kill(posix_getpid(), SIGUSR1);