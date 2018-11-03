<?php

// configs
require_once __DIR__ . '/../configs/daemon.config.php';
// crypto coder
require_once __DIR__ . '/utils/coder.php';
// logger
require_once __DIR__ . '/utils/logger.php';
// http client
require_once __DIR__ . '/utils/requester.php';
// mailer
require_once __DIR__ . '/utils/mailer.php';

use Crypto\Coder;
use Logger\DaemonLogger;
use HttpClient\Requester;
use Mail\Mailer;

// trap the control signals
declare(ticks = 1);

class DaemonService {
	
	// default max number of child processes
    const DEFAULT_MAX_CHILD_PROCESS_NUMBER = 1;
	// default delay
    const DEFAULT_DELAY = 1000;
	
    // flag to control the daemon process start / stop
    private $current_state = FALSE;
    // array to store the child processes
    private $current_child_procs = array();
	// daemon queue key
	private $current_queue_key = null;
	
	// requester instance
	private $requester = null;
	// mailer instance
	private $mailer = null;

    public function __construct($queue_key = '', $url = '') {
		$this->current_queue_key = $queue_key;
		$this->requester = new Requester($url);
		$this->mailer = Mailer::withDefault(Configs\DEFAULT_MAIL);
		$this->init();
    }
	
	private function init() {
		DaemonLogger::getInstance()->debug("Initializing daemon service with key = {$this->current_queue_key}, pid = " . getmypid());
		
		if ($this->is_daemon_active(Configs\DEFAULT_DAEMON_LOCK)) {
			DaemonLogger::getInstance()->debug('Daemon service with pid = ' . getmypid() . ' is already running');
			exit();
		}
		
        pcntl_signal(SIGTERM, array($this, "child_proc_handler"));
        pcntl_signal(SIGCHLD, array($this, "child_proc_handler"));
        pcntl_signal(SIGHUP, array($this, "child_proc_handler"));
        pcntl_signal(SIGUSR1, array($this, "child_proc_handler"));
	}

    public function run() {
		DaemonLogger::getInstance()->debug('Running daemon service with pid = '. getmypid());
		file_put_contents(Configs\DEFAULT_DAEMON_LOCK, getmypid());
		
		if($this->requester->send_get()) {
			while (!$this->current_state) {
				while(count($this->current_child_procs) > self::DEFAULT_MAX_CHILD_PROCESS_NUMBER) {
					 DaemonLogger::getInstance()->debug('Maximum children processes exceeded ' . self::DEFAULT_MAX_CHILD_PROCESS_NUMBER.', waiting...');
					 sleep(DEFAULT_DELAY);
				}
				$this->launch_child_proc();
				sleep(Configs\DEFAULT_DAEMON_DELAY);
			}
		}
    }
	
	protected function launch_child_proc() {
        $pid = pcntl_fork();
        if ($pid == -1) {
            DaemonLogger::getInstance()->debug('Could not launch new child process, exiting');
            return FALSE;
        } else if ($pid) {
			DaemonLogger::getInstance()->debug('Saving new child process with pid = ' . $pid);
            $this->current_child_procs[$pid] = TRUE;
        }  else {
            DaemonLogger::getInstance()->debug('Process with ID = ' . getmypid() . ' started');
			
			$message = Coder::encode($this->requester->get_response_message(), $this->requester->get_response_key());
			if($this->requester->send_update($message) && $this->requester->is_response_success()) {
				DaemonLogger::getInstance()->debug("SUCCESS: message = {$this->requester->get_response_message()}, key = {$this->requester->get_response_key()}");
			} else {
				$error = "ERROR: code = {$this->requester->get_error_code()}, message = {$this->requester->get_error_message()}";
				DaemonLogger::getInstance()->debug($error);
				$this->mailer->set_mail_message($error);
				//$this->mailer->send();
			}
			
			DaemonLogger::getInstance()->debug('Process with ID = ' . getmypid() . ' finished');
            exit(); 
        } 
        return TRUE; 
    }
	
	public function is_daemon_active($pid_file) {
		if(is_file($pid_file)) {
			$pid = file_get_contents($pid_file);
			if(posix_kill($pid, 0)) {
				return TRUE;
			} else {
				if(!unlink($pid_file)) {
					DaemonLogger::getInstance()->error("Cannot delete pid file = {$pid_file}");
					exit(1);
				}
			}
		}
		return FALSE;
	}
	
	public function child_proc_handler($signo, $pid = null, $status = null) {
        switch($signo) {
            case SIGTERM:
                $this->current_state = TRUE;
                break;
			case SIGHUP:
				break;
			case SIGUSR1:
				break;
            case SIGCHLD:
                if (!$pid) {
                    $pid = pcntl_waitpid(-1, $status, WNOHANG); 
                }
                while ($pid > 0) {
                    if ($pid && isset($this->current_child_procs[$pid])) {
                        unset($this->current_child_procs[$pid]);
                    } 
                    $pid = pcntl_waitpid(-1, $status, WNOHANG);
                }
                break;
            default:
                break;
        }
    }
	
	public function __toString(){
		return 'DaemonService [current state = ' . $this->current_state . ', number of child processes = ' . count($this->current_child_procs) . ']';
	}
	
	public function __destruct() {
		DaemonLogger::getInstance()->debug("Uninitializing daemon service with key = {$this->current_queue_key}, pid = " . getmypid());
		unset($this->current_child_procs);
	}
}