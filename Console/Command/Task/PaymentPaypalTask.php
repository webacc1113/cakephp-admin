<?php

class PaymentPaypalTask extends Shell {
	
	public function execute($transaction, $user_paypal) {
		App::import('Model', 'PaymentLog');
		$this->PaymentLog = new PaymentLog;
		
		$amount = -1 * $transaction['Transaction']['amount'] / 100; // convert points to dollars
		if (empty($user_paypal) || empty($transaction['User']['id'])) {
			$this->PaymentLog->log($transaction, PAYMENT_LOG_ABORTED, array('MV Error' => 'Either paypal email or User not found!'));
			return false;
		}
		
		// Set request-specific fields.
		$emailSubject = urlencode('MintVine Redemption');
		$receiverType = urlencode('EmailAddress');
		$currency = urlencode('USD'); 
		
		// Receivers
		// Use '0' for a single receiver. In order to add new ones: (0, 1, 2, 3...)
		// Here you can modify to obtain array data from database.
		$receivers = array(
			0 => array(
				'receiverEmail' => $user_paypal, 
				'amount' => $amount,
				'uniqueID' => $transaction['Transaction']['id'], // 13 chars max
				'note' => " Payout from your MintVine account" // I recommend use of space at beginning of string.
			)
		);
		$receiversLength = count($receivers);

		// Add request-specific fields to the request string.
		$nvpStr="&EMAILSUBJECT=$emailSubject&RECEIVERTYPE=$receiverType&CURRENCYCODE=$currency";
		$receiversArray = array();
		for ($i = 0; $i < $receiversLength; $i++) {
			$receiversArray[$i] = $receivers[$i];
		}

		foreach ($receiversArray as $i => $receiverData) {
			$receiverEmail = urlencode($receiverData['receiverEmail']);
			$amount_send = urlencode($receiverData['amount']);
			$uniqueID = urlencode($receiverData['uniqueID']);
			$note = urlencode($receiverData['note']);
			$nvpStr .= "&L_EMAIL$i=$receiverEmail&L_Amt$i=$amount_send&L_UNIQUEID$i=$uniqueID&L_NOTE$i=$note";
		}
		$httpParsedResponseAr = $this->redeem_paypal('MassPay', $nvpStr);
		CakeLog::write('payouts.paypal', $nvpStr);
		CakeLog::write('payouts.paypal', $httpParsedResponseAr);
		CakeLog::write('payouts.paypal', $transaction);
		CakeLog::write('payouts.paypal', '-----------------------------------------');
		
		if ("SUCCESS" == strtoupper($httpParsedResponseAr["ACK"]) || "SUCCESSWITHWARNING" == strtoupper($httpParsedResponseAr["ACK"])) {
			App::import('Model', 'PaymentMethod');
			$this->PaymentMethod = new PaymentMethod;
			$paypal_account = $this->PaymentMethod->find('first', array(
				'conditions' => array(
					'PaymentMethod.user_id' => $transaction['User']['id'],
					'PaymentMethod.payment_method' => 'paypal',
					'PaymentMethod.value' => $user_paypal
				)
			));
			if ($paypal_account) {
				$this->PaymentMethod->create();
				$this->PaymentMethod->save(array('PaymentMethod' => array(
					'id' => $paypal_account['PaymentMethod']['id'],
					'verified' => true
				)), true, array('verified'));
			}
			$this->PaymentLog->log($transaction, PAYMENT_LOG_SUCCESSFUL, $httpParsedResponseAr);
			return true;
		}
		else { //something went wrong with paypal masspay
			$this->PaymentLog->log($transaction, PAYMENT_LOG_FAILED, $httpParsedResponseAr);
			return false;
		}
	}
	
 	private function redeem_paypal($methodName_ = 'MassPay', $nvpStr_ = '') {
		$environment = 'production';

		$API_UserName = urlencode('matt_api1.brandedresearchinc.com');
		$API_Password = urlencode('BFMP65NNR2D2NEEA');
		$API_Signature = urlencode('AFcWxV21C7fd0v3bYYYRCpSSRl31AKFkVl9wcWP4zrDlO8hoTglLA0pP');

		if ("sandbox" === $environment || "beta-sandbox" === $environment) {
		   $API_Endpoint = "https://api-3t.$environment.paypal.com/nvp";
		}
		else {
		   $API_Endpoint = "https://api-3t.paypal.com/nvp";
		}

		$version = urlencode('51.0');

		// Set the curl parameters.
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $API_Endpoint);

		// Turn off the server and peer verification (TrustManager Concept).
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);

		// Set the API operation, version, and API signature in the request.
		$nvpreq = "METHOD=$methodName_&VERSION=$version&PWD=$API_Password&USER=$API_UserName&SIGNATURE=$API_Signature$nvpStr_";

		// Set the request as a POST FIELD for curl.
		curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);

		// Get response from the server.
		$httpResponse = curl_exec($ch);

		if (!$httpResponse) {
			//exit("$methodName_ failed: " . curl_error($ch) . '(' . curl_errno($ch) . ')');
			return array('PayPal Error' => "$methodName_ failed: " . curl_error($ch) . '(' . curl_errno($ch) . ')');
		}

		// Extract the response details.
		$httpResponseAr = explode("&", $httpResponse);

		$httpParsedResponseAr = array();
		foreach ($httpResponseAr as $i => $value) {
		   $tmpAr = explode("=", $value);
		   if (sizeof($tmpAr) > 1) {
		       $httpParsedResponseAr[$tmpAr[0]] = $tmpAr[1];
		   }
		}

		if ((0 == sizeof($httpParsedResponseAr)) || !array_key_exists('ACK', $httpParsedResponseAr)) {
		   //exit("Invalid HTTP Response for POST request($nvpreq) to $API_Endpoint.");
		   return array('PayPal Error' => "Invalid HTTP Response for POST request($nvpreq) to $API_Endpoint.");
		}

		return $httpParsedResponseAr;
	}

	public function executeWithdrawal($withdrawal, $user_paypal) {
		if (empty($user_paypal) || empty($withdrawal['User']['id'])) {
			return false;
		}

		$amount = -1 * $withdrawal['Withdrawal']['amount_cents'] / 100; // convert cents to dollars
		
		// Set request-specific fields.
		$emailSubject = urlencode('MintVine Redemption');
		$receiverType = urlencode('EmailAddress');
		$currency = urlencode('USD'); 
		
		// Receivers
		// Use '0' for a single receiver. In order to add new ones: (0, 1, 2, 3...)
		// Here you can modify to obtain array data from database.
		$receivers = array(
			0 => array(
				'receiverEmail' => $user_paypal, 
				'amount' => $amount,
				'uniqueID' => $withdrawal['Withdrawal']['id'], // 13 chars max
				'note' => " Payout from your MintVine account" // I recommend use of space at beginning of string.
			) 
		);
		$receiversLength = count($receivers);

		// Add request-specific fields to the request string.
		$nvpStr="&EMAILSUBJECT=$emailSubject&RECEIVERTYPE=$receiverType&CURRENCYCODE=$currency";
		$receiversArray = array();
		for ($i = 0; $i < $receiversLength; $i++) {
			$receiversArray[$i] = $receivers[$i];
		}

		foreach ($receiversArray as $i => $receiverData) {
			$receiverEmail = urlencode($receiverData['receiverEmail']);
			$amount_send = urlencode($receiverData['amount']);
			$uniqueID = urlencode($receiverData['uniqueID']);
			$note = urlencode($receiverData['note']);
			$nvpStr .= "&L_EMAIL$i=$receiverEmail&L_Amt$i=$amount_send&L_UNIQUEID$i=$uniqueID&L_NOTE$i=$note";
		}
		$httpParsedResponseAr = $this->redeem_paypal('MassPay', $nvpStr);
		CakeLog::write('payouts.paypal', $nvpStr);
		CakeLog::write('payouts.paypal', $httpParsedResponseAr);
		CakeLog::write('payouts.paypal', $withdrawal);
		CakeLog::write('payouts.paypal', '-----------------------------------------');
		
		if ("SUCCESS" == strtoupper($httpParsedResponseAr["ACK"]) || "SUCCESSWITHWARNING" == strtoupper($httpParsedResponseAr["ACK"])) {
			/* -- set user paypal to `verified` */
			App::import('Model', 'PaymentMethod');
			$this->PaymentMethod = new PaymentMethod;
			$paypal_account = $this->PaymentMethod->find('first', array(
				'conditions' => array(
					'PaymentMethod.user_id' => $withdrawal['User']['id'],
					'PaymentMethod.payment_method' => 'paypal',
					'PaymentMethod.value' => $user_paypal
				)
			));
			if ($paypal_account) {
				$this->PaymentMethod->create();
				$this->PaymentMethod->save(array('PaymentMethod' => array(
					'id' => $paypal_account['PaymentMethod']['id'],
					'verified' => true
				)), true, array('verified'));
			}
			/* set user paypal to `verified` -- */

			return array(
					'paid_amount_cents' => $withdrawal['Withdrawal']['amount_cents'],
					'response' => json_encode($httpParsedResponseAr),
					'payment_id' => $httpParsedResponseAr["CORRELATIONID"]
				);
		}
		else {
			// something went wrong with paypal masspay
			return false;
		}
	}
}
