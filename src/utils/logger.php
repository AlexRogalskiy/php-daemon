<?php

namespace Utils\Logger;

// configs
require_once __DIR__ . '/../../configs/daemon.config.php';

error_reporting(E_ALL);

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

define('DEFAULT_DISPLAY_ERRORS', true);
define('DEFAULT_LOG_NAME', 'daemon-service');
define('DEFAULT_ERROR_LOG', __DIR__ . '/../../logs/error.log');
define('STD_INPUT_LOG', '/dev/null');
define('STD_OUTPUT_LOG',  __DIR__ . '/../../logs/daemon-out.log');
define('STD_ERROR_LOG',  __DIR__ . '/../../logs/daemon-err.log');

ini_set('error_log', DEFAULT_ERROR_LOG);
ini_set('display_errors', DEFAULT_DISPLAY_ERRORS);

fclose(STDIN);
fclose(STDOUT);
fclose(STDERR);

$STDIN = fopen(STD_INPUT_LOG, 'r');
$STDOUT = fopen(STD_OUTPUT_LOG, 'ab');
$STDERR = fopen(STD_ERROR_LOG, 'ab');

class DaemonLogger {
	
	protected static $_instance;
	protected static $logger = null;

    private function __construct($log_name = 'logger', $log_output = null, $log_level = null) {  
		self::$logger = new Logger($log_name);
		self::$logger->pushHandler($this->getDefaultStream($log_output, $log_level));
    }
	
	private function getDefaultFormatter($log_output_format = '[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n', $log_date_format = 'Y n j, g:i a') {
		return new LineFormatter($log_output_format, $log_date_format);
	}
	
	private function getDefaultStream($log_output = '', $log_level = Logger::DEBUG) {
		$stream = new StreamHandler($log_output, $log_level);
		$stream->setFormatter($this->getDefaultFormatter());
		return $stream;
	}

    public static function getInstance() {
        if (self::$_instance === null) {
            self::$_instance = new self(DEFAULT_LOG_NAME, STD_OUTPUT_LOG);  
        }
        return self::$_instance;
    }
	
	public function debug($value) {
		self::$logger->debug($value . PHP_EOL);
	}
	
	public function error($value) {
		self::$logger->error($value . PHP_EOL);
	}
	
	public function warn($value) {
		self::$logger->warn($value . PHP_EOL);
	}
 
    private function __clone() {
    }

    private function __wakeup() {
    }
}