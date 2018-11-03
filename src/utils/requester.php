<?php

namespace HttpClient;

// logger
require_once __DIR__ . '/logger.php';

use Logger\DaemonLogger;

class Requester {
	
	const DEFAULT_TIMEOUT = 10;
	const DEFAULT_CONNECT_TIMEOUT = 10;
	const DEFAULT_CONNECT_VERBOSE = false;
	
	const DEFAULT_REDIRECT_MAX_NUMBER = 20;
	
	const DEFAULT_CONNECTION_SSL_VERSION = 3;
	const DEFAULT_CONNECTION_SSL_VERIFYPEER = false;
	const DEFAULT_CONNECTION_SSL_VERIFYHOST = false;
	
	const DEFAULT_CONNECTION_SHOW_HEADER = true;
	const DEFAULT_CONNECTION_SHOW_NOBODY = true;
	
	const DEFAULT_CONNECTION_RETURN_TRANSFER = true;
	// default user agent
	const DEFAULT_USER_AGENT = 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 5.1; Trident/4.0)';
	
	// default url to fetch data from
	private $url = null;
	// default requester instance
	private $request = null;
	// default response message
	private $message = null;
	// default response key
	private $key = null;
	// default response instance
	private $response = null;
	
	public function __construct($url) {
		$this->url = $url;
		$this->init();
	}
	
	public function __destruct() {
        curl_close($this->request);
    }
	
	public function init() {
		if (!filter_var($this->url, FILTER_VALIDATE_URL)) {
			DaemonLogger::getInstance()->error("ERROR: invalid url = {$this->url}");
			exit(1);
		}
		$this->request = curl_init($this->url);
	}
	
	public function send_get() {
		$data = ['method' => 'get'];
        $response = $this->send($data);
        if (isset($response->response->message, $response->response->key)) {
            $this->message = $response->response->message;
            $this->key = $response->response->key;
            return true;
        }
        return false;
    }
	
	public function send_update($message) {
        $data = [
            'method' => 'update',
            'message' => $message
        ];
        $response = $this->send($data);
        if ($response) {
            $this->response = $response;
            return true;
        }
        return false;
    }
	
	public function send($message) {
		if(!$this->check_url_available()) {
			DaemonLogger::getInstance()->error("ERROR: url = {$this->url} is not available");
			return null;
		}
		curl_setopt($this->request, CURLOPT_POST, true);
        curl_setopt($this->request, CURLOPT_RETURNTRANSFER, self::DEFAULT_CONNECTION_RETURN_TRANSFER);
        curl_setopt($this->request, CURLOPT_POSTFIELDS, $message);
		
		//$response = $this->curl_redir_exec($this->request);
		$response = curl_exec($this->request);
        $response = json_decode($response);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $response;
        }
        return null;
	}
	
	public function check_url_available() {
		curl_setopt($this->request, CURLOPT_CONNECTTIMEOUT, self::DEFAULT_CONNECT_TIMEOUT);
		curl_setopt($this->request, CURLOPT_HEADER, self::DEFAULT_CONNECTION_SHOW_HEADER);
		curl_setopt($this->request, CURLOPT_NOBODY, self::DEFAULT_CONNECTION_SHOW_NOBODY);
		curl_setopt($this->request, CURLOPT_RETURNTRANSFER, self::DEFAULT_CONNECTION_RETURN_TRANSFER);

		//$response = $this->curl_redir_exec($this->request);
		$response = curl_exec($this->request);
		if ($response) {
			return true;
		}
		return false;
	}
	
	public function check_http_status() {
		curl_setopt($this->request, CURLOPT_USERAGENT, self::DEFAULT_USER_AGENT);
		curl_setopt($this->request, CURLOPT_RETURNTRANSFER, self::DEFAULT_CONNECTION_RETURN_TRANSFER);
		curl_setopt($this->request, CURLOPT_VERBOSE, self::DEFAULT_CONNECT_VERBOSE);
		curl_setopt($this->request, CURLOPT_TIMEOUT, self::DEFAULT_TIMEOUT);
		curl_setopt($this->request, CURLOPT_SSL_VERIFYPEER, self::DEFAULT_CONNECTION_SSL_VERIFYPEER);
		curl_setopt($this->request, CURLOPT_SSLVERSION, self::DEFAULT_CONNECTION_SSL_VERSION);
		curl_setopt($this->request, CURLOPT_SSL_VERIFYHOST, self::DEFAULT_CONNECTION_SSL_VERIFYHOST);
		
		//$response = $this->curl_redir_exec($this->request);
		$response = curl_exec($this->request);
		$error = curl_error($this->request);
		if (!empty($error)) {
			return $error;
		}
		$httpcode = curl_getinfo($this->request, CURLINFO_HTTP_CODE);
		return $httpcode;
	}
	
	public function curl_redir_exec($ch) {
		static $curl_loops = 0;
		if ($curl_loops >= self::DEFAULT_REDIRECT_MAX_NUMBER) {
			$curl_loops = 0;
			return false;
		}
		
		curl_setopt($ch, CURLOPT_HEADER, self::DEFAULT_CONNECTION_SHOW_HEADER);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, self::DEFAULT_CONNECTION_RETURN_TRANSFER);
		
		$data = curl_exec($ch);
		list($header, $data) = explode("\n\n", $data, 2);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
 
		if ($http_code == 301 || $http_code == 302) {
			$matches = array();
			preg_match('/Location:(.*?)\n/', $header, $matches);
			$url = @parse_url(trim(array_pop($matches)));
			if (!$url) {
				$curl_loops = 0;
				return $data;
			}
			$last_url = parse_url(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL));
   
			if (!$url['scheme']) {
				$url['scheme'] = $last_url['scheme'];
			}
			if (!$url['host']) {
				$url['host'] = $last_url['host'];
			}
			if (!$url['path']) {
				$url['path'] = $last_url['path'];
			}
			$new_url = $url['scheme'] . '://' . $url['host'] . $url['path'] . ($url['query']?'?'.$url['query']:'');
			DaemonLogger::getInstance()->debug("New redirect url = {$new_url} --- {$http_code}");
			curl_setopt($ch, CURLOPT_URL, $new_url);
			return curl_redir_exec($ch);
		} else {
			$curl_loops = 0;
			return $data;
		}
	}
	
	public function get_response_message() {
        return $this->message;
    }
	
	public function get_response_key() {
        return $this->key;
    }
	
	public function is_response_success() {
        return (isset($this->response->response) && $this->response->response === 'Success');
    }
	
	public function get_error_code() {
        return (isset($this->response->errorCode) ? $this->response->errorCode : null);
    }
	
	public function get_error_message() {
        return (isset($this->response->errorMessage) ? $this->response->errorMessage : null);
    }
}