<?php
App::uses('Shell', 'Console');
App::uses('HttpSocket', 'Network/Http');

class SocialGlimpzShell extends Shell {
	
	public $method;

	public function getOptionParser() {
		$parser = parent::getOptionParser();

		$parser->addOption('user', array(
			'help' => 'API username',
			'boolean' => false
		));
		$parser->addOption('pass', array(
			'help' => 'API password',
			'boolean' => false
		));

		$parser->addOption('host', array(
			'help' => 'Hostname',
			'boolean' => false
		));
		return $parser;
	}
	
	/* 
		args: pass in your local host name, for example: http://mintvine.api/socialglimpz/
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
		$this->HttpSocket->configAuth('Basic', $this->params['user'], $this->params['pass']);
		$results = $this->HttpSocket->get( $this->params['host'].$this->method);
		echo print_r(json_decode($results['body'], true), true)."\n";
	}
	
	/* this function is used for creating survey.*/
	function create_survey() { 
		$this->out('Creating survey'); 		
		if (!isset($this->args[0])) {
			$this->out('Title missing'); 
			return false;
		}		
		$this->HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$this->HttpSocket->configAuth('Basic', $this->params['user'], $this->params['pass']);
		$post_url = $this->params['host'].'/polls/create/'; 
		$this->out('Posting to '.$post_url); 
		$results = $this->HttpSocket->post($post_url, array(
			'title' => isset($this->args[0]) ? $this->args[0] : null,
			'link' => isset($this->args[1]) ? $this->args[1] : null,
			'quota' => isset($this->args[2]) ? $this->args[2] : null,
			'cpi' => isset($this->args[3]) ? $this->args[3] : null,
			'ir' => isset($this->args[4]) ? $this->args[4] : null,
			'loi' => isset($this->args[5]) ? $this->args[5] : null,
		));
		print_r($results); 
		$return = json_decode($results['body'], true);
		echo print_r($return, true)."\n";
	}
	
	/* this function is used for updating survey.*/
	function update_survey() {
		if (!isset($this->args[0])) {
			return false;
		}
		$this->HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$this->HttpSocket->configAuth('Basic', $this->params['user'], $this->params['pass']);
		$post_url = $this->params['host'].'/polls/update/'; 
		$this->out('Posting to '.$post_url); 
		$results = $this->HttpSocket->post($post_url.$this->args[0], array(
			'title' => isset($this->args[1]) ? $this->args[1] : null,
			'link' => isset($this->args[2]) ? $this->args[2] : null,
			'quota' => isset($this->args[3]) ? $this->args[3] : null,
			'cpi' => isset($this->args[4]) ? $this->args[4] : null,
			'ir' => isset($this->args[5]) ? $this->args[5] : null,
			'loi' => isset($this->args[6]) ? $this->args[6] : null,
		));
		print_r($results['body']);
		echo print_r(json_decode($results['body'], true), true)."\n";
	}	
	
	/* this function is used for updating survey.*/
	function close_survey() {
		if (!isset($this->args[0])) {
			return false;
		}
		$this->HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$this->HttpSocket->configAuth('Basic', $this->params['user'], $this->params['pass']);
		$post_url = $this->params['host'].'/polls/close/'; 
		$this->out('Posting to '.$post_url); 
		$results = $this->HttpSocket->post($post_url.$this->args[0]);
		echo $results;
		return;
	}
	
	function launch_survey() {
		if (!isset($this->args[0])) {
			return false;
		}
		$this->HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$this->HttpSocket->configAuth('Basic', $this->params['user'], $this->params['pass']);
		$post_url = $this->params['host'].'/polls/launch/'; 
		$this->out('Posting to '.$post_url); 
		
		$results = $this->HttpSocket->post($post_url.$this->args[0], array(
			'id' => $this->args[0]
		));
		print_r($results);
		echo print_r(json_decode($results['body'], true), true)."\n";
	}
	
	function reject_respondents() {
		if (!isset($this->args[0])) {
			return false;
		}
		$this->HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$this->HttpSocket->configAuth('Basic', $this->params['user'], $this->params['pass']);
		$post_url = $this->params['host'].'/polls/reject/'; 
		$this->out('Posting to '.$post_url); 
		
		$results = $this->HttpSocket->post($post_url.$this->args[0], array(
			'id' => $this->args[0],
			'respondents' => $this->args[1]
		));
		echo print_r(json_decode($results['body'], true), true)."\n";
	}
	
	function accept_respondents() {
		if (!isset($this->args[0])) {
			return false;
		}
		$this->HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$this->HttpSocket->configAuth('Basic', $this->params['user'], $this->params['pass']);
		$post_url = $this->params['host'].'/polls/accept/'; 
		$this->out('Posting to '.$post_url); 
		
		$results = $this->HttpSocket->post($post_url.$this->args[0], array(
			'id' => $this->args[0],
			'respondents' => $this->args[1]
		));
		echo print_r(json_decode($results['body'], true), true)."\n";
	}
	
	function create_target() {
		if (!isset($this->args[0])) {
			return false;
		}
		$this->HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$this->HttpSocket->configAuth('Basic', $this->params['user'], $this->params['pass']);
		$post_url = $this->params['host'].'/polls/create_target/'; 
		$this->out('Posting to '.$post_url); 
		
		$results = $this->HttpSocket->post($post_url.$this->args[0], array(
			'gender' => isset($this->args[1]) && !empty($this->args[1]) ? $this->args[1] : null,
			'age' => isset($this->args[2]) && !empty($this->args[2]) ? $this->args[2] : null,
			'postal_code' => isset($this->args[3]) && !empty($this->args[3]) ? $this->args[3] : null,
			'dma' => isset($this->args[4]) && !empty($this->args[4]) ? $this->args[4] : null,
			'name' => isset($this->args[5]) && !empty($this->args[5]) ? $this->args[5] : null,
			'quota' => isset($this->args[6]) && !empty($this->args[6]) ? $this->args[6] : null,
		));
		echo print_r(json_decode($results['body'], true), true)."\n";
	}
	
	function create_user_target() {
		if (!isset($this->args[0])) {
			return false;
		}
		$this->HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$this->HttpSocket->configAuth('Basic', $this->params['user'], $this->params['pass']);
		$post_url = $this->params['host'].'/polls/create_target/'; 
		$this->out('Posting to '.$post_url); 
		
		$results = $this->HttpSocket->post($post_url.$this->args[0], array(
			'name' => isset($this->args[1]) && !empty($this->args[1]) ? $this->args[1] : null,
			'user_id' => isset($this->args[2]) && !empty($this->args[2]) ? $this->args[2] : null,
		));
		echo $results['body']; 
		echo print_r(json_decode($results['body'], true), true)."\n";
	}
	
	/* this function is used for delete target.*/
	function delete_target() {
		if (!isset($this->args[0])) {
			return false;
		}
		
		$this->HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$this->HttpSocket->configAuth('Basic', $this->params['user'], $this->params['pass']);
		$post_url = $this->params['host'].'/polls/delete_target/'; 
		$this->out('Posting to '.$post_url); 
		$results = $this->HttpSocket->delete($post_url.$this->args[0], array(
			'id' => $this->args[0]
		));
		echo print_r(json_decode($results['body'], true), true)."\n";
	}
	
	function main() {
		
	}
}