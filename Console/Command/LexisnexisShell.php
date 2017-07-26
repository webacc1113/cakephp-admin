<?php

class LexisnexisShell extends AppShell {
	public $uses = array('User', 'QueryProfile', 'UserAddress', 'TwilioNumber', 'FlexidRequest', 'FlexidResponse');

	function get_flexid() {
		$user_id = isset($this->args[0]) ? $this->args[0] : 0;
		$this->User->bindModel(array(
			'hasOne' => array('QueryProfile', 'UserAddress'),
			'belongsTo' => array('TwilioNumber')
		));
		$user = $this->User->find('first', array(
			'conditions' => array(
				'User.id' => $user_id,
				'UserAddress.deleted' => false
			)
		));
		if (!$user) {
			$this->out('User not found');
			return false;
		}

		$user_name = '1629067/BRINCXML';
		$password = 'BR188f6Tv';
		$created = date(DB_DATETIME);
		$nonce = mt_rand();
		$passdigest = base64_decode(pack('H*', sha1(pack('H*', $nonce) . pack('a*', $created) . pack('a*', $password))));
		$ns = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';

		$client = new SoapClient("https://identitymanagement.lexisnexis.com/identity-proofing/services/identityProofingServiceWS/v2?wsdl", array('encoding' => 'ISO-8859-1'));
		$auth = new stdClass();

		$auth->Username = new SoapVar($user_name, XSD_STRING, null, $ns, null, $ns);
		$auth->Password = new SoapVar($password, XSD_STRING, null, $ns, null, $ns);
		$auth->Nonce = new SoapVar($passdigest, XSD_STRING, null, $ns, null, $ns);
		$username_token = new stdClass();
		$username_token->UsernameToken = new SoapVar($auth, SOAP_ENC_OBJECT, null, $ns, 'UsernameToken', $ns);
		$security_sv = new SoapVar(
			new SoapVar($username_token, SOAP_ENC_OBJECT, null, $ns, 'UsernameToken', $ns), SOAP_ENC_OBJECT, null, $ns, 'Security', $ns
		);

		$header = new SoapHeader($ns, 'Security', $security_sv, true);
		$client->__setSoapHeaders(array($header));
		$transaction_id = mt_rand() . '-' . mt_rand();
		$params = array(
			'identityProofingRequest' => array(
				'transactionID' => $transaction_id,
				'locale' => 'en_US',
				'workFlow' => 'FLEXID_WORKFLOW',
				'inputSubject' => array(
					'person' => array(
						'name' => array(
							'first' => $user['UserAddress']['first_name'],
							'last' => $user['UserAddress']['last_name']
						),
						'address' => array(
							'addressline1' => $user['UserAddress']['address_line1'],
							'addressline2' => $user['UserAddress']['address_line2'],
							'city' => $user['UserAddress']['city'],
							'stateCode' => $user['UserAddress']['state'],
							'zip5' => $user['UserAddress']['postal_code']
						),
						'dateOfBirth' => array(
							'Year' => date('Y', strtotime($user['QueryProfile']['birthdate'])),
							'Month' => date('m', strtotime($user['QueryProfile']['birthdate'])),
							'Date' => date('d', strtotime($user['QueryProfile']['birthdate'])),
						),
						'phone' => array(
							'phonePurpose' => 'HOME',
							'phoneNumber' => $user['TwilioNumber']['phone_number']
						),
						'email' => $user['User']['email']
					)
				)
			)
		);
		
		try {
			$flexidRequestSource = $this->FlexidRequest->getDataSource();
			$flexidRequestSource->begin();
			$this->FlexidRequest->create();
			$this->FlexidRequest->save(array('FlexidRequest' => array(
				'user_id' => $user_id,
				'transaction_id' => $transaction_id,
				'locale' => 'en_US',
				'work_flow' => 'FLEXID_WORKFLOW',
				'first_name' => $user['UserAddress']['first_name'],
				'last_name' => $user['UserAddress']['last_name'],
				'address_line1' => $user['UserAddress']['address_line1'],
				'address_line2' => $user['UserAddress']['address_line2'],
				'city' => $user['UserAddress']['city'],
				'state_code' => $user['UserAddress']['state'],
				'zip5' => $user['UserAddress']['postal_code'],
				'birth_year' => date('Y', strtotime($user['QueryProfile']['birthdate'])),
				'birth_month' => date('m', strtotime($user['QueryProfile']['birthdate'])),
				'birth_day' => date('d', strtotime($user['QueryProfile']['birthdate'])),
				'phone_purpose' => 'HOME',
				'phone_number' => $user['TwilioNumber']['phone_number'],
				'email' => $user['User']['email']
			)));
			$flexid_request_id = $this->FlexidRequest->getInsertId();
			$flexidRequestSource->commit();
			$result = $client->invokeIdentityService($params);


		} catch(Exception $e) {
			$this->out($e->getMessage());
		}
	}
}