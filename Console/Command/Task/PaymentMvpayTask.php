<?php

class PaymentMvpayTask extends Shell {
	
	function execute($transaction, $funding_source_id) {
		if (empty($funding_source_id)) {
			$message = '#' . $transaction['Transaction']['id'] . ' failed to process, User funding_source_id not found.';
			CakeLog::write('payouts.mvpay', $message);
			$this->PaymentLog->log($transaction, PAYMENT_LOG_ABORTED, array('MV Error' => $message));
			return false;
		}
		
		$models_to_load = array(
			'Transaction',
			'PaymentLog',
			'PaymentMethod',
			'Setting',
		);
		foreach ($models_to_load as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		$amount = -1 * $transaction['Transaction']['amount'] / 100; // convert points to dollars
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array(
					'dwolla_v2.master.access_token', 
					'dwolla_v2.master.account_id', 
					'dwolla_v2.api_url'
				),
				'Setting.deleted' => false
			)
		));
		
		if (!isset($settings['dwolla_v2.api_url']) || empty($settings['dwolla_v2.api_url'])) {
			CakeLog::write('payouts.mvpay', '#' . $transaction['Transaction']['id'] . ' failed to process. dwolla_v2.api_url not found.');
			$this->PaymentLog->log($transaction, PAYMENT_LOG_ABORTED, array('MV Error' => 'setting dwolla_v2.api_url not found'));
			return false;
		}
		
		if (!isset($settings['dwolla_v2.master.access_token']) || empty($settings['dwolla_v2.master.access_token'])) {
			CakeLog::write('payouts.mvpay', '#' . $transaction['Transaction']['id'] . ' failed to process. Master access token not found.');
			$this->PaymentLog->log($transaction, PAYMENT_LOG_ABORTED, array('MV Error' => 'setting dwolla_v2.master.access_token not found'));
			return false;
		}
		
		if (!isset($settings['dwolla_v2.master.account_id']) || empty($settings['dwolla_v2.master.account_id'])) {
			CakeLog::write('payouts.mvpay', '#' . $transaction['Transaction']['id'] . ' failed to process. Master account id not found.');
			$this->PaymentLog->log($transaction, PAYMENT_LOG_ABORTED, array('MV Error' => 'setting dwolla_v2.master.account_id not found'));
			return false;
		}
		
		// send money
		App::import('Vendor', 'autoload_v2', array(
			'file' => 'DwollaV2SDK' . DS . 'vendor' . DS . 'autoload.php'
		));
		DwollaSwagger\Configuration::$access_token = $settings['dwolla_v2.master.access_token'];
		$apiClient = new DwollaSwagger\ApiClient($settings['dwolla_v2.api_url']);
		$master_funding_source_id = null;
		
		try {
			// get master account id
			$fundingSourcesApi = new DwollaSwagger\FundingsourcesApi($apiClient);
			$accountUrl = $settings['dwolla_v2.api_url'] . 'accounts/' . $settings['dwolla_v2.master.account_id'];
			$account = $fundingSourcesApi->getAccountFundingSources($accountUrl, false); // removed => false (get only active funding sources)
			
			// get master funding source id
			if (isset($account->_embedded->{'funding-sources'}[0])) {
				$source_funding_sources = $account->_embedded->{'funding-sources'};
				foreach ($source_funding_sources as $funding_source) {
					// find funding source with type as 'balance' for dwolla master funding source
					if ($funding_source->type == 'balance') {
						$master_funding_source_id = $funding_source->id;
						break;
					}
				}
			}
		}
		catch (Exception $e) {
			$errors = json_decode($e->getResponseBody());
			$this->PaymentLog->getDatasource()->reconnect();
			$this->PaymentLog->log($transaction, PAYMENT_LOG_FAILED, $errors);
			CakeLog::write('payouts.mvpay', '#' . $transaction['Transaction']['id'] . ' failed to process. ' . json_encode($errors));
			return false;
		}
		
		if (!$master_funding_source_id) {
			$message = '#' . $transaction['Transaction']['id'] . ' failed to process. Master funding source not found.';
			CakeLog::write('payouts.mvpay', $message);
			$this->PaymentLog->log($transaction, PAYMENT_LOG_FAILED, array('API error' => $message));
			return false;
		}
		
		try {
			// transfer amount from master funding source to customer funding source
			$transfersApi = new DwollaSwagger\TransfersApi($apiClient);
			$transfer = $transfersApi->create(array(
				'_links' => array(
					'source' => array(
						'href' => $settings['dwolla_v2.api_url'] . 'funding-sources/' . $master_funding_source_id,
					),
					'destination' => array(
						'href' => $settings['dwolla_v2.api_url'] . 'funding-sources/' . $funding_source_id
					)
				),
				'amount' => array(
					'currency' => 'USD',
					'value' => $amount
				),
				'metadata' => array(
					'paymentId' => $transaction['Transaction']['id'],
					'note' => " Payout from your MintVine account",
				),
			));
			
			// fetch transactionId from response url
			$transactionId = substr($transfer, strrpos($transfer, '/') + 1);
			CakeLog::write('payouts.mvpay', '#' . $transaction['Transaction']['id'] . ' mvpay transactionId: ' . $transactionId);
			
			if (!empty($transactionId)) {
				$this->PaymentLog->getDatasource()->reconnect();
				$this->PaymentLog->log($transaction, PAYMENT_LOG_SUCCESSFUL, array('mvpay_transaction_id' => $transactionId));
				return true;
			}
		}
		catch (Exception $e) {
			$errors = json_decode($e->getResponseBody());
			$this->PaymentLog->getDatasource()->reconnect();
			$this->PaymentLog->log($transaction, PAYMENT_LOG_FAILED, $errors);
			CakeLog::write('payouts.mvpay', '#' . $transaction['Transaction']['id'] . ' failed to process. '. json_encode($errors));
			return false;
		}
	}

	function executeWithdrawal($withdrawal, $funding_source_id) {
		/* -- check if mvpay receiver is not empty */
		if (empty($funding_source_id)) {
			$message = '#' . $withdrawal['Withdrawal']['id'] . ' failed to process, User funding_source_id not found.';
			CakeLog::write('payouts.mvpay', $message);

			return false;
		}
		/* check if mvpay receiver is not empty -- */
		
		/* -- pre-load models to be used */
		$models_to_load = array(
			'Withdrawal',
			'PaymentMethod',
			'Setting',
		);
		foreach ($models_to_load as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		/* pre-load models to be used -- */
		
		// convert cents to dollars
		$amount = -1 * $withdrawal['Withdrawal']['amount_cents'] / 100;

		/* -- load mvpay master credentials */
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array(
					'dwolla_v2.master.access_token', 
					'dwolla_v2.master.account_id', 
					'dwolla_v2.api_url'
				),
				'Setting.deleted' => false
			)
		));
		
		if (!isset($settings['dwolla_v2.api_url']) || empty($settings['dwolla_v2.api_url'])) {
			CakeLog::write('payouts.mvpay', '#' . $withdrawal['Withdrawal']['id'] . ' failed to process. dwolla_v2.api_url not found.');
			return false;
		}
		
		if (!isset($settings['dwolla_v2.master.access_token']) || empty($settings['dwolla_v2.master.access_token'])) {
			CakeLog::write('payouts.mvpay', '#' . $withdrawal['Withdrawal']['id'] . ' failed to process. Master access token not found.');
			return false;
		}
		
		if (!isset($settings['dwolla_v2.master.account_id']) || empty($settings['dwolla_v2.master.account_id'])) {
			CakeLog::write('payouts.mvpay', '#' . $withdrawal['Withdrawal']['id'] . ' failed to process. Master account id not found.');
			return false;
		}
		/* load mvpay master credentials -- */


		// send money ...
		App::import('Vendor', 'autoload_v2', array(
			'file' => 'DwollaV2SDK' . DS . 'vendor' . DS . 'autoload.php'
		));
		DwollaSwagger\Configuration::$access_token = $settings['dwolla_v2.master.access_token'];
		$apiClient = new DwollaSwagger\ApiClient($settings['dwolla_v2.api_url']);
		
		/* -- get master funding source id */
		$master_funding_source_id = null;
		try {
			// get master account id
			$fundingSourcesApi = new DwollaSwagger\FundingsourcesApi($apiClient);
			$accountUrl = $settings['dwolla_v2.api_url'] . 'accounts/' . $settings['dwolla_v2.master.account_id'];
			$account = $fundingSourcesApi->getAccountFundingSources($accountUrl, false); // removed => false (get only active funding sources)
			
			if (isset($account->_embedded->{'funding-sources'}[0])) {
				$source_funding_sources = $account->_embedded->{'funding-sources'};
				foreach ($source_funding_sources as $funding_source) {
					// find funding source with type as 'balance' for dwolla master funding source
					if ($funding_source->type == 'balance') {
						$master_funding_source_id = $funding_source->id;
						break;
					}
				}
			}
		}
		catch (Exception $e) {
			$errors = json_decode($e->getResponseBody());
			CakeLog::write('payouts.mvpay', '#' . $withdrawal['Withdrawal']['id'] . ' failed to process. ' . json_encode($errors));
			return false;
		}

		if (!$master_funding_source_id) {
			$message = '#' . $withdrawal['Withdrawal']['id'] . ' failed to process. Master funding source not found.';
			CakeLog::write('payouts.mvpay', $message);
			return false;
		}
		/* get master funding source id -- */

		/* -- transfer amount from master funding source to customer funding source */
		try {
			$transfersApi = new DwollaSwagger\TransfersApi($apiClient);
			$transfer = $transfersApi->create(array(
				'_links' => array(
					'source' => array(
						'href' => $settings['dwolla_v2.api_url'] . 'funding-sources/' . $master_funding_source_id,
					),
					'destination' => array(
						'href' => $settings['dwolla_v2.api_url'] . 'funding-sources/' . $funding_source_id
					)
				),
				'amount' => array(
					'currency' => 'USD',
					'value' => $amount
				),
				'metadata' => array(
					'paymentId' => $withdrawal['Withdrawal']['id'],
					'note' => " Payout from your MintVine account",
				),
			));
			
			// fetch transactionId from response url
			$transactionId = substr($transfer, strrpos($transfer, '/') + 1);
			CakeLog::write('payouts.mvpay', '#' . $withdrawal['Withdrawal']['id'] . ' mvpay transactionId: ' . $transactionId);
			
			if (!empty($transactionId)) {
				return array(
					'paid_amount_cents' => $withdrawal['Withdrawal']['amount_cents'],
					'response' => $transfer,
					'payment_id' => $transactionId
				);
			}
		}
		catch (Exception $e) {
			$errors = json_decode($e->getResponseBody());
			CakeLog::write('payouts.mvpay', '#' . $withdrawal['Withdrawal']['id'] . ' failed to process. '. json_encode($errors));
			return false;
		}
		/* transfer amount from master funding source to customer funding source -- */

		return false;
	}
}
