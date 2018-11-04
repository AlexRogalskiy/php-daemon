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

use Utils\Crypto\Coder;
use Utils\Logger\DaemonLogger;
use Utils\HttpClient\Requester;
use Utils\Mail\Mailer;

// trap the control signals
//declare(ticks = 1);
pcntl_async_signals(true);

class DaemonService {
	
	// default max number of child processes
    const DEFAULT_MAX_CHILD_PROC_NUMBER = 5;
	// default delay for checking number of currently available child processes
    const DEFAULT_MAX_CHILD_PROC_DELAY = 60;
	
	// queue to store signal processes
	private $sig_proc_queue = array();
	// flag to check usage of signal queue
	private $use_sig_queue = false;
	
    // flag to control current state of daemon process
    private $current_proc_state = FALSE;
    // array to store the child processes
    private $current_child_procs = array();
	// array to store the child process signals
    private $current_child_sig_queue = array();
	
	// daemon queue key
	private $queue_key = null;
	// max number of child processes
	private $max_child_proc_number = null;
	// max delay for cheking child processes limit
	private $max_child_proc_delay = null;
	
	// requester instance
	private $requester = null;
	// mailer instance
	private $mailer = null;

    public function __construct($queue_key = '', $url = '', $max_child_proc_number = null, $max_child_proc_delay = null, $use_sig_queue = false) {
		$this->queue_key = $queue_key;
		$this->max_child_proc_number = isset($max_child_proc_number) ? $max_child_proc_number : self::DEFAULT_MAX_CHILD_PROC_NUMBER;
		$this->max_child_proc_delay = isset($max_child_proc_delay) ? $max_child_proc_delay : self::DEFAULT_MAX_CHILD_PROC_DELAY;
		$this->use_sig_queue = $use_sig_queue;
		
		$this->requester = new Requester($url);
		$this->mailer = Mailer::withDefault(Configs\DEFAULT_MAIL);
		
		$this->init();
    }
	
	private function init() {
		DaemonLogger::getInstance()->debug("Initializing daemon process with key = {$this->queue_key}, pid = " . getmypid());
		
		if (!extension_loaded('pcntl')) {
			DaemonLogger::getInstance()->error('ERROR: pcntl extension is not loaded');
			exit(-1);
		}
		
		$this->register_sig_handlers();
	}
	
	public function service_shutdown_handler() {
		DaemonLogger::getInstance()->error('Running daemon service shutdown handler for pid = ' . getmypid());
		posix_kill(posix_getpid(), SIGTERM);
		exit();
	}
	
	private function register_sig_handlers() {
		// kill (default signal)
        pcntl_signal(SIGTERM, array($this, "sig_proc_handler"));
		// Ctrl + C
        pcntl_signal(SIGINT, array($this, "sig_proc_handler"));
        pcntl_signal(SIGCHLD, array($this, "sig_proc_handler"));
		// kill -s HUP
        pcntl_signal(SIGHUP, array($this, "sig_proc_handler"));
        pcntl_signal(SIGUSR1, array($this, "sig_proc_handler"));
		
		// shutdown handler
		register_shutdown_function(array($this, 'service_shutdown_handler'));
	}

    public function start() {
		DaemonLogger::getInstance()->debug('Running daemon service with pid = ' . getmypid());
		
		if ($this->is_daemon_active(Configs\DEFAULT_DAEMON_LOCK)) {
			DaemonLogger::getInstance()->debug('Daemon service with pid = ' . getmypid() . ' is already running');
			exit();
		} else {
			file_put_contents(Configs\DEFAULT_DAEMON_LOCK, getmypid());
		}
		
		if($this->requester->send_get()) {
			while (!$this->current_proc_state) {
				pcntl_signal_dispatch();
				
				while(count($this->current_child_procs) >= $this->max_child_proc_number) {
					 DaemonLogger::getInstance()->debug("Maximum children processes number exceeded the limit {$this->max_child_proc_number}, waiting for child processes to terminate");
					 sleep($this->max_child_proc_delay);
				}
				
				if($this->use_sig_queue) {
					foreach($this->sig_proc_queue as $idx => $sig) {
						$this->sig_proc_handler($sig);
						unset($this->sig_proc_queue[$idx]);
					}
				}
				
				if(!$this->current_proc_state) $this->launch_child_proc();
			}
		} else {
			DaemonLogger::getInstance()->debug("Remote host is not available = {$this->requester->get_url()}");
		}
    }
	
	public function stop() {
		if (!$this->is_daemon_active(Configs\DEFAULT_DAEMON_LOCK)) {
			DaemonLogger::getInstance()->debug('Daemon service with pid = ' . getmypid() . ' is not running');
		} else {
			DaemonLogger::getInstance()->debug('Daemon service with pid = ' . getmypid() . ' has been terminated');
			$this->unlink_pid_file(Configs\DEFAULT_DAEMON_LOCK);
		}
		exit();
	}
	
	protected function launch_child_proc() {
        $pid = pcntl_fork();
        if ($pid == -1) {
            DaemonLogger::getInstance()->debug('Could not launch new child process with pid = {$pid}, exiting');
            return FALSE;
        } else if ($pid) {
			DaemonLogger::getInstance()->debug("Registering new child process with pid = {$pid}");
            $this->current_child_procs[$pid] = TRUE;
			if(isset($this->current_child_sig_queue[$pid])) {
				DaemonLogger::getInstance()->debug("Processing already existing child process with pid = {$pid}");
				$this->sig_proc_handler(SIGCHLD, $pid, $this->current_child_sig_queue[$pid]);
				unset($this->current_child_sig_queue[$pid]); 
			}
        }  else {
			$this->exec_child_proc();
        } 
        return TRUE;
    }
	
	private function exec_child_proc() {
        DaemonLogger::getInstance()->debug('Process with ID = ' . getmypid() . ' started');
			
		$message = Coder::encode($this->requester->get_response_message(), $this->requester->get_response_key());
		if($this->requester->send_update($message) && $this->requester->is_response_success()) {
			DaemonLogger::getInstance()->debug("SUCCESS: message = {$this->requester->get_response_message()}, key = {$this->requester->get_response_key()}");
		} else {
			$error = "ERROR: code = {$this->requester->get_error_code()}, message = {$this->requester->get_error_message()}";
			DaemonLogger::getInstance()->error($error);
			$this->mailer->set_mail_message($error);
			$this->mailer->send();
		}
			
		DaemonLogger::getInstance()->debug('Process with ID = ' . getmypid() . ' finished');
		sleep(Configs\DEFAULT_DAEMON_DELAY);
		exit();
	}
	
	public function is_daemon_active($pid_file) {
		if(is_file($pid_file)) {
			$pid = file_get_contents($pid_file);
			if($pid && posix_kill($pid, SIGTERM)) {
				pcntl_waitpid($pid, $status);
				return TRUE;
			} else {
				$this->unlink_pid_file($pid_file);
			}
		}
		return FALSE;
	}
	
	private function unlink_pid_file($pid_file) {
		if(!unlink($pid_file)) {
			DaemonLogger::getInstance()->error("ERROR: cannot delete file with pid = {$pid_file}");
			exit(-1);
		}
	}
	
	public function sig_proc_handler($sig, $pid = null, $status = null) {
		if(isset($this->use_sig_queue) && $this->use_sig_queue) {
			$this->sig_proc_queue[] = $sig;
		} else {
			$this->child_proc_handler($sig, $pid, $status);
		}
	}
	
	public function child_proc_handler($signo, $pid = null, $status = null) {
        switch($signo) {
            case SIGTERM:
                $this->current_proc_state = TRUE;
                break;
			case SIGINT:
				$this->current_proc_state = TRUE;
				break;
			case SIGHUP:
				$this->launch_child_proc();
				break;
			case SIGUSR1:
				break;
            case SIGCHLD:
                if (is_null($pid)) {
                    $pid = pcntl_waitpid(-1, $status, WNOHANG);
                }
                while ($pid > 0) {
                    if (is_numeric($pid) && isset($this->current_child_procs[$pid])) {
						$exitCode = pcntl_wexitstatus($status); 
						if($exitCode != 0) {
							DaemonLogger::getInstance()->debug("process with pid = {$pid} exited with status = {$exitCode}");
						}
                        unset($this->current_child_procs[$pid]);
                    } else if(is_numeric($pid)) {
						DaemonLogger::getInstance()->debug("adding process with pid = {$pid} to the signal queue");
						$this->current_child_sig_queue[$pid] = $status; 
					}
                    $pid = pcntl_waitpid(-1, $status, WNOHANG);
                }
                break;
            default:
				DaemonLogger::getInstance()->debug("Unhandled signal = ${signo}, pid = {$pid}, status = {$status}");
                break;
        }
    }
	
	public function __toString(){
		return 'DaemonService [current state = ' . $this->current_proc_state . ', number of child processes = ' . count($this->current_child_procs) . ']';
	}
	
	public function __destruct() {
		DaemonLogger::getInstance()->debug("Uninitializing daemon process with key = {$this->queue_key}, pid = " . getmypid());
		unset($this->current_child_procs);
	}
}