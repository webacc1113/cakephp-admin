<?php
class PaymentTangoTask extends Shell {
	function execute($transaction, $sku) {
		// Load PaymentComponent 
		App::uses('ComponentCollection', 'Controller');
		App::uses('Controller', 'Controller');
		App::uses('TangoComponent', 'Controller/Component');
		$collection = new ComponentCollection();
		$this->Tango = new TangoComponent($collection);
		$controller = new Controller();
		$this->Tango->initialize($controller);
		
		$models_to_load = array(
			'User',
			'PaymentLog',
			'Tangocard',
			'TangocardOrder',
			'TangoOrderLog',
			'Setting',
			'Transaction'
		);
		foreach ($models_to_load as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}

		$txn_key = '[' . $transaction['Transaction']['id'] . '] ';
		CakeLog::write('payouts.tango', $txn_key . ' Starting with ' . $sku);
		if (-1 * $transaction['Transaction']['amount'] > 50000) {
			$message = $txn_key . ' FAILED: amount (' . (-1 * $transaction['Transaction']['amount']) . ') Tango card can not exceed $500';
			echo $message . "\n";
			CakeLog::write('payouts.tango', $message);
			$this->PaymentLog->log($transaction, PAYMENT_LOG_ABORTED, array('MV Error' => $message));
			return false;
		}
		
		$tangocard = $this->Tangocard->find('first', array(
			'conditions' => array(
				'Tangocard.sku' => $sku,
				'Tangocard.deleted' => false,
			),
			'order' => 'Tangocard.id DESC'
		));
		if (!$tangocard) {
			
			// If Tangocard not found, we delete the transaction so that user can re initiate the withdrawal
			$this->Transaction->create();
			$this->Transaction->delete($transaction['Transaction']['id']);
			
			// After deleting the transaction we need to rebuild the user balance
			$this->User->rebuildBalances($transaction);
			
			$message = $txn_key . ' FAILED : MintVine Tangocard not found. sku(' . $sku . ') Returned '.(-1 * $transaction['Transaction']['amount']).' points to '.$transaction['User']['email'];
			echo $message . "\n";
			CakeLog::write('payouts.tango', $message);
			CakeLog::write('payouts.returned', 'Returned '.(-1 * $transaction['Transaction']['amount']).' points to '.$transaction['User']['email']);
			$this->PaymentLog->log($transaction, PAYMENT_LOG_ABORTED, array('MV Error' => $message));
			return false;
		}

		// Validate only child cards against transaction amount, as child cards have fixed value.
		if (!empty($tangocard['Tangocard']['parent_id'])) {
			$fixed_card_converted_value = round($tangocard['Tangocard']['value'] / $tangocard['Parent']['conversion']);
			if ($fixed_card_converted_value != (-1 * $transaction['Transaction']['amount'])) {
				$message = $txn_key . ' FAILED: Transaction amount(' . abs($transaction['Transaction']['amount']) . ') do not match the gift card amount(' . $fixed_card_converted_value . ')';
				echo $message . "\n";
				CakeLog::write('payouts.tango', $message);
				$this->PaymentLog->log($transaction, PAYMENT_LOG_ABORTED, array('MV Error' => $message));
				return false;
			}
		}


		$credentials = $this->Tango->credentials();
		if (!isset($credentials['tango.api_host']) || !isset($credentials['tango.platform']) || !isset($credentials['tango.key'])) {
			$message = $txn_key . ' Error: Any of the following Tangocard settings not found. tango.api_host, tango.platform, tango.key';
			echo $message . "\n";
			CakeLog::write('payouts.tango', $message);
			$this->PaymentLog->log($transaction, PAYMENT_LOG_ABORTED, array('MV Error' => $message));
			return false;
		}

		$account = $this->Tango->account();
		if (!$account) {
			$message = $txn_key . ' Error: We got an error accessing Tango account, check Tango logs please';
			echo $message . "\n";
			CakeLog::write('payouts.tango', $message);
			$this->PaymentLog->log($transaction, PAYMENT_LOG_ABORTED, array('MV Error' => $message));
			return false;
		}
		
		// Sanity check on TangocardOrder
		$count = $this->TangocardOrder->find('count', array(
			'conditions' => array(
				'transaction_id' => $transaction['Transaction']['id']
			)
		));
		if ($count > 0) {
			$message = $txn_key . ' FAILED: Tangocard order already exist for this transaction.';
			$this->out($message);
			CakeLog::write('payouts.tango', $message);
			$this->PaymentLog->log($transaction, PAYMENT_LOG_ABORTED, array('MV Error' => $message));
			return false;
		}
		
		// Sanity checks on TangoOrderLog
		$count = $this->TangoOrderLog->find('count', array(
			'conditions' => array(
				'transaction_id' => $transaction['Transaction']['id'],
				'success' => true
			)
		));
		if ($count > 0) {
			$message = $txn_key . ' FAILED: A successful order has already been logged in TangoOrderLogs for this transaction.';
			$this->out($message);
			CakeLog::write('payouts.tango', $message);
			$this->PaymentLog->log($transaction, PAYMENT_LOG_ABORTED, array('MV Error' => $message));
			return false;
		}

		if (empty($transaction['User']['firstname'])) {
			$transaction['User']['firstname'] = $transaction['User']['username'];
		}
		
		$request = array(
			'customer' => $account->customer,
			'account_identifier' => $account->identifier,
			'recipient' => array(
				'name' => (!empty($transaction['User']['firstname'])) ? $transaction['User']['firstname'] : 'MintVine Member',
				'email' => $transaction['User']['email']
			),
			'sku' => $sku,
			'external_id' => $transaction['Transaction']['id']
		);

		// We add the amount property to variable tango cards only, Tangocard api will raise a validation error if amount is added to fixed price cards
		if (empty($tangocard['Tangocard']['parent_id'])) {
			$request['amount'] = -1 * round($transaction['Transaction']['amount'] * $tangocard['Tangocard']['conversion']);
		}
		
		// check whether to send the reward email by tango or by ourself
		$setting = $this->Setting->find('first', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('tango.emails'),
				'Setting.deleted' => false
			)
		));
		if ($setting && $setting['Setting']['value'] == 'true') {
			$request['reward_message'] = 'Thank you for choosing MintVine!';
			$request['reward_subject'] = 'MintVine Payout';
			$request['reward_from'] = 'MintVine';
			$request['send_reward'] = true;
			$sent_mv = false;
		}
		else {
			$request['send_reward'] = false;
			$sent_mv = true;
		}

		// Save TangoOrderLog 
		$this->TangoOrderLog->create();
		$this->TangoOrderLog->save(array('TangoOrderLog' => array(
			'transaction_id' => $transaction['Transaction']['id'],
			'request' => json_encode($request),
		)));
		$tango_order_log_id = $this->TangoOrderLog->getLastInsertID();
		
		CakeLog::write('payouts.tango', 'data to post: ' . print_r($request, true));
		App::uses('HttpSocket', 'Network/Http');
		$HttpSocket = new HttpSocket(array(
			'timeout' => 5,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$HttpSocket->configAuth('Basic', $credentials['tango.platform'], $credentials['tango.key']);
		try {
			$json_response = $HttpSocket->post($credentials['tango.api_host'] . 'orders', json_encode($request));
			CakeLog::write('payouts.tango', print_r($json_response, true));
		} 
		catch (Exception $e) {
			$message = "Api call failed, please try again.";
			CakeLog::write('payouts.tango', $message);
			echo $message . "\n";
			return false;
		}

		$response = json_decode($json_response);
		$this->TangoOrderLog->create();
		$this->TangoOrderLog->save(array('TangoOrderLog' => array(
			'id' => $tango_order_log_id,
			'response' => $json_response,
			'success' => $response->success
		)), true, array('response', 'success'));
		
		if (!$response->success) {
			if (isset($response->error_message)) {
				$message = $response->error_message;
			}
			else {
				$message = 'Unknown error - please check logs';
			}

			CakeLog::write('payouts.tango', $message);
			echo $message . "\n";
			$this->PaymentLog->log($transaction, PAYMENT_LOG_FAILED, $response);
			return false;
		}
		
		// Save the order info, only if the order is successful.
		$reward = (property_exists($response->order, 'reward') && !empty($response->order->reward)) ? json_decode(json_encode($response->order->reward), true) : array();
		$tangocard_order = array('TangocardOrder' => array(
			'user_id' => $transaction['Transaction']['user_id'],
			'transaction_id' => $transaction['Transaction']['id'],
			'order_id' => $response->order->order_id,
			'sku' => $response->order->sku,
			'amount' => $response->order->amount_charged->value,
			'denomination' => $response->order->denomination->currency_code.' '.round($response->order->denomination->value / 100, 2),
			'recipient_name' => $response->order->recipient->name,
			'recipient_email' => $response->order->recipient->email,
			'sent_mv' => $sent_mv,
			'response' => $json_response,
		));
		
		// Send tangocard order email if not sent by tango
		if ($sent_mv) {
			$email = new CakeEmail();
			$email->config('mailgun');
			$email->from(array(EMAIL_SENDER => 'MintVine'))
				->replyTo(array(REPLYTO_EMAIL => 'MintVine'))
				->emailFormat('html')
				->template('payout_tango')
				->viewVars(array(
					'user_name' => $transaction['User']['username'],
					'amount' => $response->order->denomination->currency_code.' '.round($response->order->denomination->value / 100, 2),
					'reward' => $reward,
					'transaction_name' => (!empty($tangocard['Tangocard']['transaction_name'])) ? $tangocard['Tangocard']['transaction_name'] : 'Gift Certificate',
					'redemption_instructions' => !empty($tangocard['Tangocard']['parent_id']) ? $tangocard['Parent']['redemption_instructions'] : $tangocard['Tangocard']['redemption_instructions'],
					'disclaimer' => !empty($tangocard['Tangocard']['parent_id']) ? $tangocard['Parent']['disclaimer'] : $tangocard['Tangocard']['disclaimer'],
					'unsubscribe_link' => HOSTNAME_WWW.'/users/emails/' . $transaction['User']['ref_id']
				))
				->to(array($transaction['User']['email']))
				->subject('MintVine Payout');
			if ($email->send()) {
				$message = 'Redemption email sent';
			}
			else {
				$message = 'Redemption email NOT sent';
			}

			CakeLog::write('payouts.tango', $message);
			echo $message . "\n";
			$tangocard_order['TangocardOrder']['first_send'] = date(DB_DATETIME);
		}
		else {
			$tangocard_order['TangocardOrder']['delivered_at'] = $response->order->delivered_at;
		}
		
		$this->TangocardOrder->create();
		$this->TangocardOrder->save($tangocard_order);

		CakeLog::write('payouts.tango', 'response: ' . print_r($response, true));
		CakeLog::write('payouts.tango', '----');
		$this->PaymentLog->log($transaction, PAYMENT_LOG_SUCCESSFUL, $response);
		return true;
	}

	function executeWithdrawal($withdrawal, $sku) {
		/* -- load PaymentComponent & necessary models */
		App::uses('ComponentCollection', 'Controller');
		App::uses('Controller', 'Controller');
		App::uses('TangoComponent', 'Controller/Component');
		$collection = new ComponentCollection();
		$this->Tango = new TangoComponent($collection);
		$controller = new Controller();
		$this->Tango->initialize($controller);
		
		$models_to_load = array(
			'User',
			'Withdrawal',
			'Tangocard',
			'TangocardOrder',
			'TangoOrderLog',
			'Setting',
			'Transaction'
		);
		foreach ($models_to_load as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		/* load PaymentComponent & necessary models -- */
	
		$txn_key = '[' . $withdrawal['Withdrawal']['id'] . '] ';
		CakeLog::write('payouts.tango', $txn_key . ' Starting with ' . $sku);

		/* -- prevent amount from exceeding $500 */
		if (-1 * $withdrawal['Withdrawal']['amount_cents'] > 50000) {
			$message = $txn_key . ' FAILED: amount (' . (-1 * $withdrawal['Withdrawal']['amount_cents']) . ') Tango card can not exceed $500';
			echo $message . "\n";
			CakeLog::write('payouts.tango', $message);

			return false;
		}
		/* prevent amount from exceeding $500 -- */
		
		/* -- fetch the receiver tangocard by sku */
		$tangocard = $this->Tangocard->find('first', array(
			'conditions' => array(
				'Tangocard.sku' => $sku,
				'Tangocard.deleted' => false,
			),
			'order' => 'Tangocard.id DESC'
		));
		if (!$tangocard) {
			// if Tangocard not found, we delete the withdrawal (transaction record deleted in cascade), so that user can re-initiate the withdrawal
			$this->Withdrawal->create();
			$this->Withdrawal->delete($withdrawal['Withdrawal']['id']);
			
			// after deleting the withdrawal, we need to rebuild the user balance
			$this->User->rebuildBalances($withdrawal);
			
			$message = $txn_key . ' FAILED : MintVine Tangocard not found. sku(' . $sku . ') Returned '.(-1 * $withdrawal['Withdrawal']['amount_cents']).' points to '.$withdrawal['User']['email'];
			echo $message . "\n";
			CakeLog::write('payouts.tango', $message);
			CakeLog::write('payouts.returned', 'Returned '.(-1 * $withdrawal['Withdrawal']['amount_cents']).' points to '.$withdrawal['User']['email']);

			return false;
		}
		// validate only child cards against withdrawal amount, as child cards have fixed value.
		if (!empty($tangocard['Tangocard']['parent_id'])) {
			$fixed_card_converted_value = round($tangocard['Tangocard']['value'] / $tangocard['Parent']['conversion']);
			if ($fixed_card_converted_value != (-1 * $withdrawal['Withdrawal']['amount_cents'])) {
				$message = $txn_key . ' FAILED: Withdrawal amount(' . abs($withdrawal['Withdrawal']['amount_cents']) . ') do not match the gift card amount(' . $fixed_card_converted_value . ')';
				echo $message . "\n";
				CakeLog::write('payouts.tango', $message);

				return false;
			}
		}
		/* fetch the receiver tangocard by sku -- */

		$credentials = $this->Tango->credentials();
		if (!isset($credentials['tango.api_host']) || !isset($credentials['tango.platform']) || !isset($credentials['tango.key'])) {
			$message = $txn_key . ' Error: Any of the following Tangocard settings not found. tango.api_host, tango.platform, tango.key';
			echo $message . "\n";
			CakeLog::write('payouts.tango', $message);

			return false;
		}

		$account = $this->Tango->account();
		if (!$account) {
			$message = $txn_key . ' Error: We got an error accessing Tango account, check Tango logs please';
			echo $message . "\n";
			CakeLog::write('payouts.tango', $message);

			return false;
		}

		/* -- sanity check on TangocardOrder */
		$count = $this->TangocardOrder->find('count', array(
			'conditions' => array(
				'transaction_id' => $withdrawal['Withdrawal']['transaction_id']
			)
		));
		if ($count > 0) {
			$message = $txn_key . ' FAILED: Tangocard order already exist for this withdrawal.';
			$this->out($message);
			CakeLog::write('payouts.tango', $message);

			return false;
		}
		/* sanity check on TangocardOrder -- */

		/* -- sanity checks on TangoOrderLog */
		$count = $this->TangoOrderLog->find('count', array(
			'conditions' => array(
				'transaction_id' => $withdrawal['Withdrawal']['transaction_id'],
				'success' => true
			)
		));
		if ($count > 0) {
			$message = $txn_key . ' FAILED: A successful order has already been logged in TangoOrderLogs for this withdrawal.';
			$this->out($message);
			CakeLog::write('payouts.tango', $message);

			return false;
		}
		/* sanity checks on TangoOrderLog -- */

		if (empty($withdrawal['User']['firstname'])) {
			$withdrawal['User']['firstname'] = $withdrawal['User']['username'];
		}
		
		$request = array(
			'customer' => $account->customer,
			'account_identifier' => $account->identifier,
			'recipient' => array(
				'name' => (!empty($withdrawal['User']['firstname'])) ? $withdrawal['User']['firstname'] : 'MintVine Member',
				'email' => $withdrawal['User']['email']
			),
			'sku' => $sku,
			'external_id' => $withdrawal['Withdrawal']['id']
		);

		/* -- we add the amount property to variable tango cards only, Tangocard api will raise a validation error if amount is added to fixed price cards */
		if (empty($tangocard['Tangocard']['parent_id'])) {
			$request['amount'] = -1 * round($withdrawal['Withdrawal']['amount_cents'] * $tangocard['Tangocard']['conversion']);
		}
		/* we add the amount property to variable tango cards only, Tangocard api will raise a validation error if amount is added to fixed price cards -- */
		
		/* -- check whether to send the reward email by tango or by ourself */
		$setting = $this->Setting->find('first', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('tango.emails'),
				'Setting.deleted' => false
			)
		));
		if ($setting && $setting['Setting']['value'] == 'true') {
			$request['reward_message'] = 'Thank you for choosing MintVine!';
			$request['reward_subject'] = 'MintVine Payout';
			$request['reward_from'] = 'MintVine';
			$request['send_reward'] = true;
			$sent_mv = false;
		}
		else {
			$request['send_reward'] = false;
			$sent_mv = true;
		}
		/* check whether to send the reward email by tango or by ourself -- */

		/* -- save TangoOrderLog, process payout */
		// logging into TangoOrderLog
		$this->TangoOrderLog->create();
		$this->TangoOrderLog->save(array('TangoOrderLog' => array(
			'transaction_id' => $withdrawal['Withdrawal']['transaction_id'],
			'request' => json_encode($request),
		)));
		$tango_order_log_id = $this->TangoOrderLog->getLastInsertID();

		// send request to Tango
		CakeLog::write('payouts.tango', 'data to post: ' . print_r($request, true));
		App::uses('HttpSocket', 'Network/Http');
		$HttpSocket = new HttpSocket(array(
			'timeout' => 5,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$HttpSocket->configAuth('Basic', $credentials['tango.platform'], $credentials['tango.key']);
		try {
			$json_response = $HttpSocket->post($credentials['tango.api_host'] . 'orders', json_encode($request));
			CakeLog::write('payouts.tango', print_r($json_response, true));
		} 
		catch (Exception $e) {
			$message = "Api call failed, please try again.";
			CakeLog::write('payouts.tango', $message);
			echo $message . "\n";

			return false;
		}

		// logging into TangoOrderLog
		$response = json_decode($json_response);
		$this->TangoOrderLog->create();
		$this->TangoOrderLog->save(array('TangoOrderLog' => array(
			'id' => $tango_order_log_id,
			'response' => $json_response,
			'success' => $response->success
		)), true, array('response', 'success'));

		// if payout fails, ...
		if (!$response->success) {
			if (isset($response->error_message)) {
				$message = $response->error_message;
			}
			else {
				$message = 'Unknown error - please check logs';
			}

			CakeLog::write('payouts.tango', $message);
			echo $message . "\n";

			return false;
		}
		/* save TangoOrderLog, process payout -- */

		/* -- save the order info, only if the order is successful */
		$reward = (property_exists($response->order, 'reward') && !empty($response->order->reward)) ? json_decode(json_encode($response->order->reward), true) : array();
		$tangocard_order = array('TangocardOrder' => array(
			'user_id' => $withdrawal['Withdrawal']['user_id'],
			'transaction_id' => $withdrawal['Withdrawal']['transaction_id'],
			'order_id' => $response->order->order_id,
			'sku' => $response->order->sku,
			'amount' => $response->order->amount_charged->value,
			'denomination' => $response->order->denomination->currency_code.' '.round($response->order->denomination->value / 100, 2),
			'recipient_name' => $response->order->recipient->name,
			'recipient_email' => $response->order->recipient->email,
			'sent_mv' => $sent_mv,
			'response' => $json_response,
		));
		
		// Send tangocard order email if not sent by tango
		if ($sent_mv) {
			$email = new CakeEmail();
			$email->config('mailgun');
			$email->from(array(EMAIL_SENDER => 'MintVine'))
				->replyTo(array(REPLYTO_EMAIL => 'MintVine'))
				->emailFormat('html')
				->template('payout_tango')
				->viewVars(array(
					'user_name' => $withdrawal['User']['username'],
					'amount' => $response->order->denomination->currency_code.' '.round($response->order->denomination->value / 100, 2),
					'reward' => $reward,
					'transaction_name' => (!empty($tangocard['Tangocard']['transaction_name'])) ? $tangocard['Tangocard']['transaction_name'] : 'Gift Certificate',
					'redemption_instructions' => !empty($tangocard['Tangocard']['parent_id']) ? $tangocard['Parent']['redemption_instructions'] : $tangocard['Tangocard']['redemption_instructions'],
					'disclaimer' => !empty($tangocard['Tangocard']['parent_id']) ? $tangocard['Parent']['disclaimer'] : $tangocard['Tangocard']['disclaimer'],
					'unsubscribe_link' => HOSTNAME_WWW.'/users/emails/' . $withdrawal['User']['ref_id']
				))
				->to(array($withdrawal['User']['email']))
				->subject('MintVine Payout');
			if ($email->send()) {
				$message = 'Redemption email sent';
			}
			else {
				$message = 'Redemption email NOT sent';
			}

			CakeLog::write('payouts.tango', $message);
			echo $message . "\n";
			$tangocard_order['TangocardOrder']['first_send'] = date(DB_DATETIME);
		}
		else {
			$tangocard_order['TangocardOrder']['delivered_at'] = $response->order->delivered_at;
		}
		
		$this->TangocardOrder->create();
		$this->TangocardOrder->save($tangocard_order);
		/* save the order info, only if the order is successful -- */

		CakeLog::write('payouts.tango', 'response: ' . print_r($response, true));
		CakeLog::write('payouts.tango', '----');

		return array(
				'paid_amount_cents' => (-1 * $response->order->amount_charged->value),
				'response' => $json_response,
				'payment_id' => $response->order->order_id
			);
	}

}
