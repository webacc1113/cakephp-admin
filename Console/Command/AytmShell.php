<?php
App::uses('Shell', 'Console');
App::uses('HttpSocket', 'Network/Http');
App::import('Lib', 'AytmMappings');
class AytmShell extends Shell {
	
	public $username = 'aytm';
	public $password = 'mvb4cxsy'; // dev
//	public $password = '8xv76feu'; // prod
	public $method;
	public $endpoint = 'http://mintvine.api/aytm/'; //AYTM_ENDPOINT;
		
	/* 
		args: pass in your local host name, for example: http://mintvine.api/aytm/
	*/
	/* this function will work for all GET API endpoints.*/
	function get_api_call() {
		if (!isset($this->args[0])) {
			return false;
		}
		$this->method = $this->args[0];
		$this->HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$this->HttpSocket->configAuth('Basic', $this->username, $this->password);
		$results = $this->HttpSocket->get($this->endpoint.$this->method);
		echo print_r(json_decode($results['body'], true), true)."\n";
	}
	
	function feasibility() {
		if (isset($this->args[0])) {
			$this->endpoint = $this->args[0];
		}
		if (isset($this->args[1])) {
			$this->username = $this->args[1];
		}
		if (isset($this->args[2])) {
			$this->password = $this->args[2];
		}
		$test_query = '{"target_markets":[{"uid":"467338","respondents":"15","career":["15", "1"],"location":{"country":"US","zips":["80100","08123","90017"]}},{"uid":"12345","location":{"country":"US","zips":["90017","92101"]}}]}';
		
		$this->HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$this->HttpSocket->configAuth('Basic', $this->username, $this->password);
		$results = $this->HttpSocket->post($this->endpoint.'/feasibility/', $test_query);
		$return = json_decode($results['body'], true);
		echo print_r($results['body'], true)."\n";
	}
	
	function survey() {
		if (isset($this->args[0])) {
			$this->endpoint = $this->args[0];
		}
		if (isset($this->args[1])) {
			$this->username = $this->args[1];
		}
		if (isset($this->args[2])) {
			$this->password = $this->args[2];
		}
		$this->HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$body = '{"target_markets":[{"uid":"tm-1","gender":["f","m"],"income":[3,4,5],"respondents":20,"status":"start" },{ "uid":"tm-2","gender":["m"],"income":[7],"respondents":10,"status":"stop" },{ "uid":"tm-3","gender":["f"],"income":[7],"respondents":2,"status":"start" }],"uid":"130","survey_url":"http://r.mintvine.dev:8888/test/?uid={{ID}}","preview_url":"http://aytm.com/preview/tl012345" }';
		$this->HttpSocket->configAuth('Basic', $this->username, $this->password);
		$results = $this->HttpSocket->post($this->endpoint.'/survey/', $body);
		print_r($results);
		$return = json_decode($results['body'], true);
		echo print_r($results['body'], true)."\n";
	}
}