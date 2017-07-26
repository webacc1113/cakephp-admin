<?php

class TangoTask extends Shell {
	
	public function get_response(&$http, $url, $params) {
		try {
			$response = $http->get($url, $params, array('header' => array(
				'Accept' => 'application/json',
				'Content-Type' => 'application/json; charset=UTF-8'
			)));
			CakeLog::write('tango.account', print_r($response, true) . "\n");
			if ($response->code == 200) {
				return $response;
			}
			else {
				echo 'Error: ' . $response->reasonPhrase. "\n";
				return false;
			}
		} catch (Exception $e) {
			return false;
		}
	}
	
	public function credentials() {
		App::import('Model', 'Setting');
		$this->Setting = new Setting;
		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'name' => array('tango.platform', 'tango.key', 'tango.api_host'),
				'Setting.deleted' => false
			)
		));

		if (!isset($settings['tango.api_host']) || !isset($settings['tango.platform']) || !isset($settings['tango.key'])) {
			return false;
		}

		return $settings;
	}

}