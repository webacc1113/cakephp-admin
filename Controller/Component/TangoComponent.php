<?php

App::uses('Component', 'Controller');
class TangoComponent extends Component {
	
	// If $local is true, we return our locally saved account info, else we retrieve latest account data from api and send through
	public function account($local = false) {
		App::import('Model', 'Setting');
		$this->Setting = new Setting;
		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => array('tango.account', 'tango.api_host', 'tango.platform', 'tango.key'),
				'Setting.deleted' => false
			)
		));

		if (!isset($settings['tango.account']) || !isset($settings['tango.api_host']) || !isset($settings['tango.platform']) || !isset($settings['tango.key'])) {
			CakeLog::write('tango.account', 'Error: One or more Tango setting(s) not found!');
			return false;
		}

		$obj_account = json_decode($settings['tango.account']);
		if ($local == 'local') {
			return $obj_account;
		}
		
		App::uses('HttpSocket', 'Network/Http');
		$HttpSocket = new HttpSocket(array(
			'timeout' => 5,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$HttpSocket->configAuth('Basic', $settings['tango.platform'], $settings['tango.key']);
		try {
			$response = $HttpSocket->get($settings['tango.api_host'] . 'accounts/' . $obj_account->customer . '/' . $obj_account->identifier);
		} catch (Exception $e) {
			CakeLog::write('tango.account', 'Api call failed, please try again!');
			return false;
		}

		if ($response->code != 200) {
			CakeLog::write('tango.account', 'Error: ' . $response->reasonPhrase);
			return false;
		}

		$response = json_decode($response);
		return $response->account;
	}
	
	public function credentials() {
		App::import('Model', 'Setting');
		$this->Setting = new Setting;
		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => array('tango.platform', 'tango.key', 'tango.api_host'),
				'Setting.deleted' => false
			)
		));

		if (!isset($settings['tango.api_host']) || !isset($settings['tango.platform']) || !isset($settings['tango.key'])) {
			return false;
		}
		
		return $settings;
	}
	
	public function save_cc($cc) {
		App::import('Model', 'Setting');
		$this->Setting = new Setting;
		$setting = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'tango.cc',
				'Setting.deleted' => false
			)
		));
		if ($setting) { // update
			$this->Setting->create();
			$this->Setting->save(array('Setting' => array(
				'id' => $setting['Setting']['id'],
				'value' => json_encode($cc)
			)));
		}
		else { // create
			$this->Setting->create();
			$this->Setting->save(array('Setting' => array(
				'name' => 'tango.cc',
				'value' => json_encode($cc)
			)));
			return true;
		}

		// Unregister exisiting credit card if any
		$account = $this->account('local');
		$credentials = $this->credentials();
		if (!$account || !$credentials) {
			return false;
		}
		
		CakeLog::write('tango.account', 'cc_register : New credit card saved and cc_token updated in settings.');
		App::uses('HttpSocket', 'Network/Http');
		$HttpSocket = new HttpSocket(array(
			'timeout' => 5,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$HttpSocket->configAuth('Basic', $credentials['tango.platform'], $credentials['tango.key']);
		$request = array(
			'customer' => $account->customer,
			'account_identifier' => $account->identifier,
			'cc_token' => json_decode($setting['Setting']['value'])->cc_token
		);
		try {
			$response = $HttpSocket->post($credentials['tango.api_host'] . 'cc_unregister', json_encode($request));
			CakeLog::write('tango.account', 'cc_unregister : ' . print_r($response, true));
		} catch (Exception $e) {
			CakeLog::write('tango.account', 'cc_unregister : Api call failed.');
			return false;
		}

		$response = json_decode($response);
		if ($response->success) {
			return true;
		}
		
		return false;
	}
	
	public function validate_gift_country($sku, $user) {
		App::import('Model', 'Tangocard');
		$this->Tangocard = new Tangocard;
		$card = $this->Tangocard->find('first', array(
			'contain' => array('Parent'),
			'conditions' => array(
				'Tangocard.sku' => $sku,
				'Tangocard.deleted' => false,
			)
		));
		if (!$card) {
			return false;
		}

		$country = 'US';
		if (isset($user['QueryProfile']['country']) && in_array($user['QueryProfile']['country'], array_keys(unserialize(SUPPORTED_COUNTRIES)))) {
			$country = $user['QueryProfile']['country'];
		}

		if (!$card['Tangocard']['parent_id'] && $card['Tangocard']['allowed_' . strtolower($country)]) {
			return true;
		}
		elseif (!empty($card['Parent']) && $card['Parent']['allowed_' . strtolower($country)]) {
			return true;
		}

		return false;
	}
}