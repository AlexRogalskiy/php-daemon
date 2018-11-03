<?php

namespace Request;

// logger
require_once __DIR__ . '/logger.php';

class Requester {
	
	const DEFAULT_CONNECTION_TIMEOUT = 10;
	
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
			Logger\DEFAULT_LOGGER->error("ERROR: invalid url = {$this->url}");
			exit(1);
		}
		$this->request = curl_init($this->url);
	}
	
	public function send_get() {
		if(!check_url_availible()) {
			Logger\DEFAULT_LOGGER->error("ERROR: url = {$this->url} is not available");
			return;
		}
        $data = ['method' => 'get'];
        $response = $this->send($data);
        if ( isset( $response->response->message, $response->response->key ) ) {
            $this->message = $response->response->message;
            $this->key = $response->response->key;
            return true;
        }
        return false;
    }
	
	public function send_update($message) {
		if(!check_url_availible()) {
			Logger\DEFAULT_LOGGER->error("ERROR: url = {$this->url} is not available");
			return;
		}
        $data = [
            'method' => 'update',
            'message' => $message
        ];
        $response = $this->send( $data );
        if ( $response !== null ) {
            $this->response = $response;
            return true;
        }
        return false;
    }
	
	public function send_post($message) {
		if(!check_url_availible()) {
			Logger\DEFAULT_LOGGER->error("ERROR: url = {$this->url} is not available");
			return;
		}
		curl_setopt($this->request, CURLOPT_POST, true);
        curl_setopt($this->request, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->request, CURLOPT_POSTFIELDS, $data);
        $result = json_decode(curl_exec($this->request));
        if ( json_last_error() === JSON_ERROR_NONE ) {
            return $result;
        }
        return null;
	}
	
	public function check_url_availible() {
		curl_setopt($this->request, CURLOPT_CONNECTTIMEOUT, self::DEFAULT_CONNECTION_TIMEOUT);
		curl_setopt($this->request, CURLOPT_HEADER, true);
		curl_setopt($this->request, CURLOPT_NOBODY, true);
		curl_setopt($this->request, CURLOPT_RETURNTRANSFER, true);

		$response = curl_exec($this->request);
		curl_close($this->request);

		if ($response) {
			return true;
		}
		return false;
	}
	
	public function get_response_message() {
        return $this->message;
    }
	
	public function get_response_key() {
        return $this->key;
    }
	
	public function is_success() {
        return isset( $this->response->response ) && $this->response->response === 'Success';
    }
	
	public function get_error_code() {
        return ( isset( $this->response->errorCode ) ? $this->response->errorCode : null );
    }
	
	public function get_error_message() {
        return ( isset( $this->response->errorMessage ) ? $this->response->errorMessage : null );
    }
}