<?php

if (!function_exists('curl_init')) {
	throw new Exception("Kiind's API Client Library requires the CURL PHP extension.");
}

if (!function_exists('json_decode')) {
	throw new Exception("Kiind's API Client Library requires the JSON PHP extension.");
}

if (!function_exists('getallheaders')) {

	function getallheaders() {
		$headers = '';

		foreach ($_SERVER as $name => $value) {
			if (substr($name, 0, 5) == 'HTTP_') {
				$headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
			}
		}

		return $headers;
	}

}

class KiindRestClient {

	/**
	 * @var string Kiind API key
	 */
	private $apiKey;

	/**
	 * @var string Transaction mode. Can be 'live' or 'test'
	 */
	private $mode;

	/**
	 * @var string error messages returned from Kiind
	 */
	private $errorMessage = false;

	/**
	 * @var string operate in debug mode
	 */
	private $debugMode = false;

	const API_SERVER_TEST = "https://testbed.kiind.me/papi/v1/";
	const API_SERVER_LIVE = "https://www.kiind.me/papi/v1/";

	/**
	 * Sets the initial state of the client
	 * 
	 * @param string $apiKey
	 * @param string $mode 
	 */
	public function __construct($apiKey = false, $mode = 'live', $debugMode = false) {
		$this->apiKey = $apiKey;
		if ($mode == 'live') {
			$this->apiServerUrl = self::API_SERVER_LIVE;
		}
		else {
			$this->apiServerUrl = self::API_SERVER_TEST;
		}

		$this->setMode($mode);
		$this->setDebug($debugMode);
	}

	/**
	 * @return string|bool Error message or false if error message does not exist
	 */
	public function getError() {
		if (!$this->errorMessage) {
			return false;
		}

		$error = $this->errorMessage;
		$this->errorMessage = false;

		return $error;
	}

	/**
	 * @param string $message Error message
	 */
	protected function setError($message) {
		$this->errorMessage = $message;

		return false;
	}

	/**
	 * Parse Kiind API response
	 * 
	 * @param array $response
	 * @return array
	 */
	protected function parse($response) {
		if (!$response['Success']) {
			$this->setError($response['Message']);

			// Exception for /register method
			if ($response['Response']) {
				$this->errorMessage .= " :: " . json_encode($response['Response']);
			}

			return false;
		}

		return $response['Response'];
	}

	/**
	 * Executes POST request against API
	 * 
	 * @param string $request
	 * @param array $params
	 * @param bool $includeToken Include oauth token in request?
	 * @return array|null 
	 */
	function post($request, $params = false) {
		$url = $this->apiServerUrl . $request . "?access_token=" . urlencode($this->apiKey);
		//$url = $this->apiServerUrl . $request;
		//$params['access_token'] = $this->apiKey;

		if ($this->debugMode) {
			echo "Posting request to: {$url} :: With params: \n";
			print_r($params);
		}

		$rawData = $this->curl($url, 'POST', $params);

		if ($this->debugMode) {
			echo "Got response:";
			print_r($rawData);
			echo "\n";
		}

		return $rawData;
	}

	/**
	 * Executes GET requests against API
	 * 
	 * @param string $request
	 * @param array $params
	 * @return array|null Array of results or null if json_decode fails in curl()
	 */
	function get($request, $params = array()) {
		$params['access_token'] = $this->apiKey;
		$delimiter = (strpos($request, '?') === false) ? '?' : '&';
		$url = $this->apiServerUrl . $request . $delimiter . http_build_query($params);
		if ($this->debugMode) {
			echo "Getting request from: {$url} \n";
		}

		$rawData = $this->curl($url, 'GET');

		if ($this->debugMode) {
			echo "Got response:";
			print_r($rawData);
			echo "\n";
		}

		return $rawData;
	}

	/**
	 * Execute curl request
	 * 
	 * @param string $url URL to send requests
	 * @param string $method HTTP method
	 * @param array $params request params
	 * @return array|null Returns array of results or null if json_decode fails
	 */
	protected function curl($url, $method = 'GET', $params = array()) {
		// Encode POST data
		$data = json_encode($params);

		// Set request headers
		$headers = array('Accept: application/json', 'Content-Type: application/json;charset=UTF-8');
		if ($method == 'POST') {
			$headers[] = 'Content-Length: ' . strlen($data);
		}

		// Set up our CURL request
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		
		// Windows require this certificate
		if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN') {
			$ca = dirname(__FILE__);
			curl_setopt($ch, CURLOPT_CAINFO, $ca); // Set the location of the CA-bundle
			curl_setopt($ch, CURLOPT_CAINFO, $ca . '/cacert.pem'); // Set the location of the CA-bundle
		}

		// Initiate request
		$rawData = curl_exec($ch);

		// If HTTP response wasn't 200,
		// log it as an error!
		$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if ($code !== 200) {
			if ($this->debugMode) {
				echo "Here is all the information we got from curl: \n";
				print_r(curl_getinfo($ch));
				print_r(curl_error($ch));
			}

			return array(
				'Success' => false,
				'Message' => "Request failed. Server responded with: {$code}"
			);
		}

		// All done with CURL
		curl_close($ch);

		// Otherwise, assume we got some
		// sort of a response
		return json_decode($rawData, true);
	}

	/**
	 * Sets client mode.  Appropriate values are 'live' and 'test'
	 * 
	 * @param string $mode
	 * @throws InvalidArgumentException
	 * @return void
	 */
	public function setMode($mode = 'live') {
		$mode = strtolower($mode);

		if ($mode != 'live' && $mode != 'test') {
			throw new InvalidArgumentException('Appropriate mode values are live or test');
		}

		$this->mode = $mode;
	}

	/**
	 * @return string Client mode
	 */
	public function getMode() {
		return $this->mode;
	}

	/**
	 * Set debug mode
	 * 
	 * @return boolean True
	 */
	public function setDebug($mode) {
		$this->debugMode = $mode;

		return true;
	}

}
