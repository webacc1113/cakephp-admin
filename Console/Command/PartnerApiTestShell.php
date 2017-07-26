<?php

App::uses('CakeEmail', 'Network/Email');
App::uses('HttpSocket', 'Network/Http');

class PartnerApiTestShell extends AppShell {
	var $uses = array();
	public $tasks = array('Cint');

	function cint_test() {
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.deleted' => false
			)
		));

		$this->out('Verifying CINT is online!');

		if (!empty($settings['cint.host']) && !empty($settings['cint.active'])) {
			$http = new HttpSocket(array(
				'timeout' => 15,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));

			$api_key = $settings['cint.us.key'];
			$api_secret = $settings['cint.us.secret'];

			$options = array('header' => array(
				'Accept' => 'application/json',
				'Content-Type' => 'application/json; charset=UTF-8'
			));

			$http->configAuth('Basic', $api_key, $api_secret);
			$url = $this->Cint->api_url($settings['cint.host'], $http, 'panel/respondent-quotas', $api_key);
			$response = $http->get($url, array(), $options);

			$setting = $this->Setting->find('first', array(
				'conditions' => array(
					'Setting.name' => 'cint.active',
					'Setting.deleted' => false
				)
			));

			if ($setting && $response->code != 200 && $settings['cint.active'] == 'true') {
				$this->out('CINT seems offline - Turning CINT off!');
				
				// Notify slack
				$http = new HttpSocket(array(
					'timeout' => '2',
					'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
				));
				$http->post($settings['slack.partnerapis.webhook'], json_encode(array(
					'text' => '@channel CINT seems offline - Turning CINT off! Response Code: '.$response->code,
					'link_names' => 1,
					'username' => 'bernard'
				))); 
				
				$this->Setting->create();
				$this->Setting->save(array('Setting' => array(
					'id' => $setting['Setting']['id'],
					'value' => 'false',
					'user_id' => '0',
					'description' => 'CINT offline - Auto-off'
				)), true, array('value', 'user_id', 'description'));
			}
			elseif ($setting && $response->code == 200 && $settings['cint.active'] == 'false') {
				if ($setting['Setting']['user_id'] == '0') {
					$this->out('CINT seems online - Turning CINT on!');

					// Notify slack
					$http = new HttpSocket(array(
						'timeout' => '2',
						'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
					));
					$http->post($settings['slack.partnerapis.webhook'], json_encode(array(
						'text' => '@channel CINT seems online - Turning CINT on!',
						'link_names' => 1,
						'username' => 'bernard'
					))); 

					$this->Setting->create();
					$this->Setting->save(array('Setting' => array(
						'id' => $setting['Setting']['id'],
						'value' => 'true',
						'user_id' => '0',
						'description' => 'CINT online - Auto-on'
					)), true, array('value', 'user_id', 'description'));
				}
			}
		}
	}

	function lucid_test() {
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.deleted' => false
			)
		));

		$this->out('Verifying LUCID is online!');

		if (!empty($settings['lucid.api.key']) && !empty($settings['lucid.supplier.code']) && !empty($settings['lucid.host']) && !empty($settings['lucid.active'])) {
			$http = new HttpSocket(array(
				'timeout' => 15,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$params = array('key' => $settings['lucid.api.key']);
			$url = $settings['lucid.host'].'Supply/v1/Surveys/AllOfferwall/'.$settings['lucid.supplier.code']; 
			$response = $http->get($url, $params);

			$setting = $this->Setting->find('first', array(
				'conditions' => array(
					'Setting.name' => 'lucid.active',
					'Setting.deleted' => false
				)
			));

			if ($setting && $response->code != 200 && $settings['lucid.active'] == 'true') {
				$this->out('LUCID seems offline - Turning LUCID off!');

				// Notify slack
				$http = new HttpSocket(array(
					'timeout' => '2',
					'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
				));
				$http->post($settings['slack.partnerapis.webhook'], json_encode(array(
					'text' => '@channel LUCID seems offline - Turning LUCID off! Response Code: '.$response->code,
					'link_names' => 1,
					'username' => 'bernard'
				))); 

				$this->Setting->create();
				$this->Setting->save(array('Setting' => array(
					'id' => $setting['Setting']['id'],
					'value' => 'false',
					'user_id' => '0',
					'description' => 'LUCID offline - Auto-off'
				)), true, array('value', 'user_id', 'description'));
			}
			elseif ($setting && $response->code == 200 && $settings['lucid.active'] == 'false') {
				if ($setting['Setting']['user_id'] == '0') {
					$this->out('LUCID seems online - Turning LUCID on!');
	
					// Notify slack
					$http = new HttpSocket(array(
						'timeout' => '2',
						'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
					));
					$http->post($settings['slack.partnerapis.webhook'], json_encode(array(
						'text' => '@channel LUCID seems online - Turning LUCID on!',
						'link_names' => 1,
						'username' => 'bernard'
					))); 

					$this->Setting->create();
					$this->Setting->save(array('Setting' => array(
						'id' => $setting['Setting']['id'],
						'value' => 'true',
						'user_id' => '0',
						'description' => 'LUCID online - Auto-on'
					)), true, array('value', 'user_id', 'description'));
				}
			}
		}
	}

	function precision_test() {
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.deleted' => false
			)
		));

		$this->out('Verifying PRECISION is online!');
		
		if (!empty($settings['precision_sample.host']) && !empty($settings['precision_sample.active'])) {

			$http = new HttpSocket(array(
				'timeout' => 15,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$response = $http->post($settings['precision_sample.host'] . '/GetSurveys', json_encode(array(
				'UserGuid' => 'D8A36763-0109-44F1-8EE0-CCE2A4F5D66C'
			)), array('header' => array(
				'Content-Type' => 'application/json'
			)));

			$setting = $this->Setting->find('first', array(
				'conditions' => array(
					'Setting.name' => 'precision.active',
					'Setting.deleted' => false
				)
			));

			if ($setting && $response->code != 200 && $settings['precision_sample.active'] == 'true') {
				$this->out('PRECISION seems offline - Turning PRECISION off!');
	
				// Notify slack
				$http = new HttpSocket(array(
					'timeout' => '2',
					'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
				));
				$http->post($settings['slack.partnerapis.webhook'], json_encode(array(
					'text' => '@channel PRECISION seems offline - Turning PRECISION off! Response Code: '.$response->code,
					'link_names' => 1,
					'username' => 'bernard'
				))); 

				$this->Setting->create();
				$this->Setting->save(array('Setting' => array(
					'id' => $setting['Setting']['id'],
					'value' => 'false',
					'user_id' => '0',
					'description' => 'PRECISION offline - Auto-off'
				)), true, array('value', 'user_id', 'description'));
			}
			elseif ($setting && $response->code == 200 && $settings['precision_sample.active'] == 'false') {
				if ($setting['Setting']['user_id'] == '0') {
					$this->out('PRECISION seems online - Turning PRECISION on!');
	
					// Notify slack
					$http = new HttpSocket(array(
						'timeout' => '2',
						'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
					));
					$http->post($settings['slack.partnerapis.webhook'], json_encode(array(
						'text' => '@channel PRECISION seems online - Turning PRECISION on!',
						'link_names' => 1,
						'username' => 'bernard'
					))); 

					$this->Setting->create();
					$this->Setting->save(array('Setting' => array(
						'id' => $setting['Setting']['id'],
						'value' => 'true',
						'user_id' => '0',
						'description' => 'PRECISION online - Auto-on'
					)), true, array('value', 'user_id', 'description'));
				}
			}
		}
	}

	function toluna_test() {
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.deleted' => false
			)
		));

		$this->out('Verifying TOLUNA is online!');

		if (!empty($settings['toluna.api_endpoint']) && !empty($settings['toluna.guid.us']) && !empty($settings['toluna.active'])) {

			$http = new HttpSocket(array(
				'timeout' => 15,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$response = $http->get($settings['toluna.api_endpoint'], array(
				'memberCode' => 761225,
				'partnerGuid' => $settings['toluna.guid.us']
			));

			$setting = $this->Setting->find('first', array(
				'conditions' => array(
					'Setting.name' => 'toluna.active',
					'Setting.deleted' => false
				)
			));

			if ($setting && $response->code != 200 && $settings['toluna.active'] == 'true') {
				$this->out('TOLUNA seems offline - Turning TOLUNA off! Response Code: '.$response->code);
	
				// Notify slack
				$http = new HttpSocket(array(
					'timeout' => '2',
					'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
				));
				$http->post($settings['slack.partnerapis.webhook'], json_encode(array(
					'text' => '@channel TOLUNA seems offline - Turning TOLUNA off!',
					'link_names' => 1,
					'username' => 'bernard'
				))); 

				$this->Setting->create();
				$this->Setting->save(array('Setting' => array(
					'id' => $setting['Setting']['id'],
					'value' => 'false',
					'user_id' => '0',
					'description' => 'TOLUNA offline - Auto-off'
				)), true, array('value', 'user_id', 'description'));
			}
			elseif ($setting && $response->code == 200 && $settings['toluna.active'] == 'false') {
				if ($setting['Setting']['user_id'] == '0') {
					$this->out('TOLUNA seems online - Turning TOLUNA on!');
	
					// Notify slack
					$http = new HttpSocket(array(
						'timeout' => '2',
						'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
					));
					$http->post($settings['slack.partnerapis.webhook'], json_encode(array(
						'text' => '@channel TOLUNA seems online - Turning TOLUNA on!',
						'link_names' => 1,
						'username' => 'bernard'
					))); 

					$this->Setting->create();
					$this->Setting->save(array('Setting' => array(
						'id' => $setting['Setting']['id'],
						'value' => 'true',
						'user_id' => '0',
						'description' => 'TOLUNA online - Auto-on'
					)), true, array('value', 'user_id', 'description'));
				}
			}
		}
	}

	function point2shop_test() {
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.deleted' => false
			)
		));

		$this->out('Verifying P2S is online!');
		if (!empty($settings['points2shop.secret']) && !empty($settings['points2shop.active'])) {

			$http = new HttpSocket(array(
				'timeout' => 15,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$survey_api_endpoint = 'https://www.your-surveys.com/suppliers_api/surveys';
		
			$response = $http->get($survey_api_endpoint, array(
				'limit' => 1
			), array('header' => array(
				'X-YourSurveys-Api-Key' => $settings['points2shop.secret']
			)));

			$setting = $this->Setting->find('first', array(
				'conditions' => array(
					'Setting.name' => 'points2shop.active',
					'Setting.deleted' => false
				)
			));

			if ($setting && $response->code != 200 && $settings['points2shop.active'] == 'true') {
				$this->out('P2S seems offline - Turning P2S off!');
	
				// Notify slack
				$http = new HttpSocket(array(
					'timeout' => '2',
					'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
				));
				$http->post($settings['slack.partnerapis.webhook'], json_encode(array(
					'text' => '@channel P2S seems offline - Turning P2S off! Response Code: '.$response->code,
					'link_names' => 1,
					'username' => 'bernard'
				))); 

				$this->Setting->create();
				$this->Setting->save(array('Setting' => array(
					'id' => $setting['Setting']['id'],
					'value' => 'false',
					'user_id' => '0',
					'description' => 'P2S offline - Auto-off'
				)), true, array('value', 'user_id', 'description'));
			}
			elseif ($setting && $response->code == 200 && $settings['points2shop.active'] == 'false') {
				if ($setting['Setting']['user_id'] == '0') {
					$this->out('P2S seems online - Turning P2S on!');

					// Notify slack
					$http = new HttpSocket(array(
						'timeout' => '2',
						'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
					));
					$http->post($settings['slack.partnerapis.webhook'], json_encode(array(
						'text' => '@channel P2S seems online - Turning P2S on!',
						'link_names' => 1,
						'username' => 'bernard'
					))); 

					$this->Setting->create();
					$this->Setting->save(array('Setting' => array(
						'id' => $setting['Setting']['id'],
						'value' => 'true',
						'user_id' => '0',
						'description' => 'P2S online - Auto-on'
					)), true, array('value', 'user_id', 'description'));
				}
			}
		}
	}
	
}
