<?php

namespace Utils;

// logger
require_once __DIR__ . '/logger.php';

use Utils\Logger\DaemonLogger;

if(!function_exists('ftok') ) {
	function ftok($file_name = '', $project_id = '') {
	   $file_stats = @stat(trim($file_name));
	   if (!$state) {
		   return -1;
	   }
	   return sprintf('%u', (($file_stats['ino'] & 0xffff) | (($file_stats['dev'] & 0xff) << 16) | ((ord($project_id) & 0xff) << 24)));
	}
}

class Common {
    public static function getFileId($input_file_name = '', $input_project_id = '') {
		$key = ftok($input_file_name, $input_project_id);
		if($key == -1) {
			DaemonLogger::getInstance()->debug("cannot create id by file = {$input_file_name}, project id = {$input_project_id}");
			exit(1);
		}
		return $key;
	}
}
