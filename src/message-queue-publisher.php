<?php

// configs
require_once __DIR__ . '/../configs/daemon.config.php';
// utilities
require_once __DIR__ . '/utils/utils.php';
// logger
require_once __DIR__ . '/utils/logger.php';

use Logger\DaemonLogger;

// read user input
$handle = fopen("php://stdin","rb");
$file_name = fgets($handle);
if(empty($file_name) || !file_exists($file_name)) {
	$file_name = Configs\DEFAULT_FILE_NAME;
}
$key = Utils\Utils::getFileId($file_name, Configs\DEFAULT_PROJECT_ID);

$queue = msg_get_queue($key);

$messages = array(
	array(1, 'message, type 1'),
	array(2, 'message, type 2'),
	array(3, 'message, type 3'),
	array(1, 'message, type 1')
);

foreach($messages as $value) {
	msg_send($queue, $value[0], $value[1]);
}

if(msg_send($queue,$msgtype_send, $message,$serialize_needed, $block_send,$err)) {
   Logger\DEFAULT_LOGGER->debug('Message send');
} else {
   Logger\DEFAULT_LOGGER->error($err);
}

Logger\DEFAULT_LOGGER->debug('send {count($messages)} messages');