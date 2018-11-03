<?php

namespace HttpClient;

// logger
require_once __DIR__ . '/logger.php';

use Logger\DaemonLogger;

class Requester {
	
	const DEFAULT_TIMEOUT = 10;
	const DEFAULT_CONNECT_TIMEOUT = 10;
	
	const DEFAULT_REDIRECT_MAX_NUMBER = 20;
	
	const DEFAULT_CONNECTION_SSL_VERSION = 3;
	const DEFAULT_CONNECTION_SSL_VERIFY_PEER = false;
	const DEFAULT_CONNECTION_SSL_VERIFY_HOST = false;
	
	const DEFAULT_CONNECTION_SHOW_HEADER = true;
	const DEFAULT_CONNECTION_SHOW_HEADER_OUT = true;
	const DEFAULT_CONNECTION_SHOW_NOBODY = true;
	const DEFAULT_CONNECTION_FOLLOW_LOCATION = true;
	const DEFAULT_CONNECTION_VERBOSE = true;
	const DEFAULT_CONNECTION_RETURN_TRANSFER = true;
	
	// default list of browser
    const DEFAULT_BROWSER_LIST = ['Firefox', 'Safari', 'Opera', 'Flock', 'Internet Explorer', 'Seamonkey', 'Konqueror', 'GoogleBot'];
	// default list of operating systems
	const DEFAULT_OS_LIST = ['Windows 3.1', 'Windows 95', 'Windows 98', 'Windows 2000', 'Windows NT', 'Windows XP', 'Windows Vista', 'Redhat Linux', 'Ubuntu', 'Fedora', 'AmigaOS', 'OS 10.5'];
	// default user agent
	//const DEFAULT_USER_AGENT = 'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 5.1; Trident/4.0)';
	// default list of search engines
	const DEFAULT_SEARCH_ENGINE_LIST = ['https://www.google.com/', 'https://www.google.co.uk/', 'http://www.daum.net/', 'http://www.eniro.se/', 'http://www.naver.com/', 'http://www.yahoo.com/', 'http://www.msn.com/', 'http://www.bing.com/', 'http://www.aol.com/', 'http://www.lycos.com/', 'http://www.ask.com/', 'http://www.altavista.com/', 'http://search.netscape.com/', 'http://www.cnn.com/SEARCH/', 'http://www.about.com/', 'http://www.mamma.com/', 'http://www.alltheweb.com/', 'http://www.voila.fr/', 'http://search.virgilio.it/', 'http://www.bing.com/', 'http://www.baidu.com/', 'http://www.alice.com/', 'http://www.yandex.com/', '  http://www.najdi.org.mk/', 'http://www.seznam.cz/', 'http://www.search.com/', 'http://www.wp.pl/', 'http://online.onetcenter.org/', 'http://www.szukacz.pl/', 'http://www.yam.com/', 'http://www.pchome.com/', 'http://www.kvasir.no/', 'http://sesam.no/', 'http://www.ozu.es/', 'http://www.terra.com/', 'http://www.mynet.com/', 'http://www.ekolay.net/', 'http://www.rambler.ru/'];
	
	// url to fetch data from
	private $url = null;
	// user agent
	private $user_agent = null;
	// referrer
	private $referer = null;
	// proxy
	private $proxy = null;
	
	// default requester instance
	private $request = null;
	// default response message
	private $message = null;
	// default response key
	private $key = null;
	// default response instance
	private $response = null;
	
	public function __construct($url, $user_agent = '', $referer = '', $proxy = '') {
		$this->url = $url;
		$this->user_agent = $user_agent;
		$this->referer = $referer;
		$this->proxy = $proxy;
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
		
		DaemonLogger::getInstance()->debug('Sending get request with data = ' . var_export($data, true));
		
        $response = $this->send($data);
        if ($response && $response['response'] && isset($response['response']['message'], $response['response']['key'])) {
            $this->message = $response['response']['message'];
            $this->key = $response['response']['key'];
            return true;
        }
        return false;
    }
	
	public function send_update($message) {
        $data = [
            'method' => 'update',
            'message' => $message
        ];
		
		DaemonLogger::getInstance()->debug('Sending update request with data = ' . var_export($data, true));
				
        $response = $this->send($data);
        if ($response) {
            $this->response = $response;
            return true;
        }
        return false;
    }
	
	public function send(array $message) {
		if(!$this->check_url_availability()) {
			DaemonLogger::getInstance()->error("ERROR: url = {$this->url} is not available");
			return false;
		}
		
		if($this->proxy) {
            curl_setopt($this->request, CURLOPT_PROXY, $this->proxy);
        }
		curl_setopt($this->request, CURLOPT_POST, true);
		curl_setopt($this->request, CURLOPT_USERAGENT, $this->user_agent());
		curl_setopt($this->request, CURLOPT_REFERER, $this->get_referrer());
		curl_setopt($this->request, CURLOPT_FOLLOWLOCATION, self::DEFAULT_CONNECTION_FOLLOW_LOCATION);
		//curl_setopt($this->request, CURLOPT_VERBOSE, self::DEFAULT_CONNECTION_VERBOSE);
        curl_setopt($this->request, CURLOPT_RETURNTRANSFER, self::DEFAULT_CONNECTION_RETURN_TRANSFER);
        curl_setopt($this->request, CURLOPT_HEADER, self::DEFAULT_CONNECTION_SHOW_HEADER);
        //curl_setopt($this->request, CURLOPT_HEADER_OUT, self::DEFAULT_CONNECTION_SHOW_HEADER_OUT);
        curl_setopt($this->request, CURLOPT_POSTFIELDS, $message);
		
		$response = $this->get_http_response($this->request);
		$header_size = curl_getinfo($this->request, CURLINFO_HEADER_SIZE);
		$header = substr($response, 0, $header_size);
		$body = substr($response, $header_size);
		
        $response = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $response;
        }
        return null;
	}
	
	private function get_http_response($ch) {
		//$response = $this->curl_redir_exec($this->request);
		$response = curl_exec($ch);
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$connected_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
		if (404 === $code) {
			DaemonLogger::getInstance()->error("ERROR: content not found, url = {$connected_url}");
            return null;
        } else if(500 <= $code) {
			DaemonLogger::getInstance()->error("ERROR: internal server error, url = {$connected_url}");
            return null;
		}
        if ($response === false) {
			$errno = curl_errno($ch);
            $error = curl_error($ch);
			DaemonLogger::getInstance()->error("ERROR: cannot fetch from url = {$connected_url}, error code = {$errno}, error message = {$error}");
			return null;
        }
        if (false !== strpos($response, 'Error 525')) {
			DaemonLogger::getInstance()->error("ERROR: cannot fetch from url = {$connected_url}, response = {$response}");
			return null;
        }
		return $response;
	}
	
	public function check_url_availability() {
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
		curl_setopt($this->request, CURLOPT_USERAGENT, $this->user_agent());
		curl_setopt($this->request, CURLOPT_RETURNTRANSFER, self::DEFAULT_CONNECTION_RETURN_TRANSFER);
		curl_setopt($this->request, CURLOPT_VERBOSE, self::DEFAULT_CONNECTION_VERBOSE);
		curl_setopt($this->request, CURLOPT_TIMEOUT, self::DEFAULT_TIMEOUT);
		curl_setopt($this->request, CURLOPT_SSL_VERIFYPEER, self::DEFAULT_CONNECTION_SSL_VERIFY_PEER);
		curl_setopt($this->request, CURLOPT_SSLVERSION, self::DEFAULT_CONNECTION_SSL_VERSION);
		curl_setopt($this->request, CURLOPT_SSL_VERIFYHOST, self::DEFAULT_CONNECTION_SSL_VERIFY_HOST);
		
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
			DaemonLogger::getInstance()->debug("New redirect url = {$new_url}, http code = {$http_code}");
			curl_setopt($ch, CURLOPT_URL, $new_url);
			return curl_redir_exec($ch);
		} else {
			$curl_loops = 0;
			return $data;
		}
	}
	
    private function user_agent() {
		if($this->user_agent) {
            return $this->user_agent;
        }
        return self::DEFAULT_BROWSER_LIST[mt_rand(0, 7)] . '/' . mt_rand(1, 8) . '.' . mt_rand(0, 9) . ' (' . self::DEFAULT_OS_LIST[mt_rand(0, 11)] . ' ' . mt_rand(1, 7) . '.' . mt_rand(0, 9) . '; en-US;)';
    }
	
	private function get_referrer() {
        if($this->referer) {
            return $this->referer;
        }
        return self::DEFAULT_SEARCH_ENGINE_LIST[mt_rand(0, count(self::DEFAULT_SEARCH_ENGINE_LIST) - 1)];
    }
	
	public function get_response_message() {
        return $this->message;
    }
	
	public function get_response_key() {
        return $this->key;
    }
	
	public function is_response_success() {
        return (isset($this->response['response']) && $this->response['response'] === 'Success');
    }
	
	public function get_error_code() {
        return (isset($this->response['errorCode']) ? $this->response['errorCode'] : null);
    }
	
	public function get_error_message() {
        return (isset($this->response['errorMessage']) ? $this->response['errorMessage'] : null);
    }
}