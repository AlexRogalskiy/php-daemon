<?php

// configs
require_once __DIR__ . '/../configs/daemon.config.php';
// logger
require_once __DIR__ . '/utils/logger.php';

use Logger\DaemonLogger;

// trap the control signals
declare(ticks = 1); 

class DaemonService {
	// default max number of child processes
    const DEFAULT_MAX_CHILD_PROCESS_NUMBER = 10;
    const DEFAULT_DELAY = 1000;
	
    // flag to control the daemon process start / stop
    protected $current_state = FALSE;
    // array to store the child processes
    protected $current_child_procs = array();

    public function __construct($queue_key = '') {
        DaemonLogger::getInstance()->debug("Сonstructed daemon service with key = {$queue_key}");
		
        pcntl_signal(SIGTERM, array($this, "childProcessHandler"));
        pcntl_signal(SIGCHLD, array($this, "childProcessHandler"));
        pcntl_signal(SIGHUP, array($this, "childProcessHandler"));
        pcntl_signal(SIGUSR1, array($this, "childProcessHandler"));
    }

    public function run() {
        DaemonLogger::getInstance()->debug('Running daemon service');
		
		if ($this->isDaemonActive(Configs\DEFAULT_DAEMON_LOCK)) {
			DaemonLogger::getInstance()->debug('Daemon is already active');
			exit();
		}

        // Пока $stop_server не установится в TRUE, гоняем бесконечный цикл
		while (!$this->current_state) {
            // Если уже запущено максимальное количество дочерних процессов, ждем их завершения
            while(count($this->current_child_procs) >= self::DEFAULT_MAX_CHILD_PROCESS_NUMBER) {
                 DaemonLogger::getInstance()->debug('Maximum children process exceeded '.self::DEFAULT_MAX_CHILD_PROCESS_NUMBER.', waiting...');
                 sleep(self::DEFAULT_DELAY);
            }
            $this->launchChildProcess();
        }
    }
	
	protected function launchChildProcess() { 
        $pid = pcntl_fork();
        if ($pid == -1) {
            DaemonLogger::getInstance()->debug('Could not launch new child process, exiting');
            return FALSE;
        } else if ($pid) {
            $this->current_child_procs[$pid] = TRUE;
        }  else {
            DaemonLogger::getInstance()->debug("Process with ID = {getmypid()}");
			file_put_contents(Configs\DEFAULT_DAEMON_LOCK, getmypid());
            exit(); 
        } 
        return TRUE; 
    }
	
	public function isDaemonActive($pid_file) {
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
	
	public function childProcessHandler($signo, $pid = null, $status = null) {
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
		return "DaemonService [current state = {$this->current_state}, number of child processes = {count($this->current_child_procs)}";
	}
	
	public function __destruct() {
		DaemonLogger::getInstance()->debug('Uninitializing DaemonService');
		unset($this->current_child_procs);
	}
}