<?php
App::uses('HttpSocket', 'Network/Http');
App::uses('CakeEmail', 'Network/Email');
App::uses('ComponentCollection', 'Controller');
App::uses('TangoComponent', 'Controller/Component');
CakePlugin::load('Mailgun');

class TangoShell extends AppShell {
	
	public $uses = array('Setting');
	
	public $tasks = array('Tango');
	
	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser->addSubcommand('create', array(
			'help' => 'Create a new Tango account and save the identifier in settings locally. Note: Creating a new account will delete the identifier of previous account from setting. Moving forward we will use this new account',
			'parser' => array(
				'description' => 'Create a new Tango account and save the identifier in settings locally. Note: Creating a new account will delete the identifier of previous account from setting. Moving forward we will use this new account',
				'arguments' => array(
					'customer' => array(
						'help' => 'Customer Name. Minimum 3 character long', 
						'required' => true
					),
					'identifier' => array(
						'help' => 'The identifier that will be used to uniquely identify this account going forward.'."\n".
							'Must be unique to the platform, but not globally'."\n".
							'Minimum 5 character long'."\n".
							'Should contain alpha numeric characters with out space e.g HRDept'."\n",
						'required' => true
					),
					'email' => array(
						'help' => 'Email of the account', 
						'required' => true
					)
				)
			)
		));
		return $parser;
	}

	
	// args: $customer
	// args: $identifier
	// args: $email
	public function create() {
		$credentials = $this->Tango->credentials();
		if (!$credentials) {
			echo "One or more of tango api credentials not found."."\n";
			return;
		}
		
		$HttpSocket = new HttpSocket(array(
			'timeout' => 5,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$HttpSocket->configAuth('Basic', $credentials['tango.platform'], $credentials['tango.key']);
		$request_data = array(
			'customer' => $this->args[0],
			'identifier' => $this->args[1],
			'email' => $this->args[2]
		);
		
		try {
			$response = $HttpSocket->post($credentials['tango.api_host'] . 'accounts', json_encode($request_data));
			CakeLog::write('tango.account', print_r($response, true) . "\n");
		} catch (Exception $e) {
			echo 'Api failed to post'."\n";
			return;
		}
		
		if ($response->code != 201) {
			echo 'Error: ' . $response->reasonPhrase . "\n";
			return;
		}

		$response = json_decode($response);
		$setting = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'tango.account',
				'Setting.deleted' => false
			)
		));
		
		if (!$setting) {
			$settingSource = $this->Setting->getDataSource();
			$settingSource->begin();
			$this->Setting->create();
			$this->Setting->save(array('Setting' => array(
				'name' => 'tango.account',
				'value' => ''
			)));
			$setting['Setting']['id'] = $this->Setting->getInsertId();
			$settingSource->commit();
		}
		
		$this->Setting->create();
		$this->Setting->save(array('Setting' => array(
			'id' => $setting['Setting']['id'],
			'value' => json_encode($response->account)
		)));
		
		echo "New account ".  $response->account->identifier." created"."\n";
	}
	
	public function autofund() {
		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => array('tango.refresh_threshold', 'tango.fill_balance', 'tango.notification_emails'),
				'Setting.deleted' => false
			)
		));

		if (!isset($settings['tango.refresh_threshold']) || !isset($settings['tango.fill_balance']) || !isset($settings['tango.notification_emails'])) {
			echo "One or more of the Tango settings not found." . "\n";
		}
		
		// Load TangoComponent 
		$collection = new ComponentCollection();
		$this->TangoComponent = new TangoComponent($collection);
		$account = $this->TangoComponent->account();
		if ($account->available_balance >= $settings['tango.refresh_threshold'] * 100) {
			echo "TangoCard balance is $".round($account->available_balance/100, 2). "\n";
			return;
		}
		
		$credentials = $this->Tango->credentials();
		if (!$credentials) {
			echo "One or more of tango api credentials not found." . "\n";
			return;
		}
		
		$cc_data = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => 'tango.cc',
				'Setting.deleted' => false
			)
		));

		if (!$cc_data) {
			echo "Credit card not registered. Please register a credit card first." . "\n";
			return;
		}
		
		$request = array(
			'customer' => $account->customer,
			'account_identifier' => $account->identifier,
			'client_ip' => '127.0.0.1',
			'amount' => $settings['tango.fill_balance'] * 100,
			'cc_token' => json_decode($cc_data['tango.cc'])->cc_token,
			'security_code' => json_decode($cc_data['tango.cc'])->cvv
		);

		$HttpSocket = new HttpSocket(array(
			'timeout' => 5,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$HttpSocket->configAuth('Basic', $credentials['tango.platform'], $credentials['tango.key']);
		$error = '';
		try {
			$response = $HttpSocket->post($credentials['tango.api_host'] . 'cc_fund', json_encode($request));
			CakeLog::write('tango.fund', print_r($response, true));
		} catch (Exception $e) {
			$message = "Api call failed, please try again.";
			CakeLog::write('tango.fund', $message);
			echo $message . "\n";
			return;
		}

		$response = json_decode($response);
		if (!$response->success) {
			if (isset($response->invalid_inputs_message)) {
				$message = $response->invalid_inputs_message;
			}
			elseif (isset($response->denial_message)) {
				$message = $response->denial_message;
			}
			
			CakeLog::write('tango.fund', $message);
			echo $message . "\n";
			return;
		}
		
		$email_body = "Hi Admin,<br /><br />
			Tango card account balance was <strong>$" . 
			round($account->available_balance/100, 2) . 
			"</strong> and is now funded with <strong>$" . 
			$settings['tango.fill_balance'].
			"</strong>. Total balance now is <strong>$" . 
			(round($account->available_balance / 100, 2) + $settings['tango.fill_balance']) . 
			"</strong><br /><br />Thank you.";
		
		App::uses('CakeEmail', 'Network/Email');
		$email = new CakeEmail();
		$email->config('mailgun');
		$email->from(array(EMAIL_SENDER => 'MintVine'))
			->replyTo(array(REPLYTO_EMAIL => 'MintVine'))
			->emailFormat('html')
			->to(json_decode($settings['tango.notification_emails'], true))
			->subject('TangoCard accound funded!');
		$email->send($email_body);
		echo 'Credit card funded successfully!'. "\n";
	}
}