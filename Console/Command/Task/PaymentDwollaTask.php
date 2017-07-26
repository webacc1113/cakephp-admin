<?php

class PaymentDwollaTask extends Shell {
	
	function execute($transaction, $dwolla_id) {
		if (empty($dwolla_id)) {
			$message = '#' . $transaction['Transaction']['id'] . ' failed to process, User dwolla_id not found.';
			CakeLog::write('payouts.dwolla', $message);
			$this->PaymentLog->log($transaction, PAYMENT_LOG_ABORTED, array('MV Error' => $message));
			return false;
		}
		
		$models_to_load = array(
			'Transaction',
			'PaymentLog',
			'PaymentMethod',
		);
		foreach ($models_to_load as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		$amount = -1 * $transaction['Transaction']['amount'] / 100; // convert points to dollars
		
		$access_token = $this->PaymentMethod->find('first', array(
			'recursive' => -1,
			'conditions' => array(
				'user_id' => 0,
				'payment_id' => 'master_access_token'
			)
		));
		if (!$access_token) {
			$message = '#' . $transaction['Transaction']['id'] . ' failed to process. Master access token not found.';
			CakeLog::write('payouts.dwolla', $message);
			$this->PaymentLog->log($transaction, PAYMENT_LOG_ABORTED, array('MV Error' => $message));
			return false;
		}
		
		// Send money
		App::import('Vendor', 'autoload', array(
			'file' => 'DwollaSDK' . DS . 'autoload.php'
		));
		$Transactions = new Dwolla\Transactions();
		$Transactions->settings->oauth_token = $access_token['PaymentMethod']['value'];
		$transactionId = $Transactions->send($dwolla_id, $amount);
		CakeLog::write('payouts.dwolla', $transactionId);
		
		if (!empty($transactionId) && $transactionId > 0) {
			$this->PaymentLog->getDatasource()->reconnect();
			$this->PaymentLog->log($transaction, PAYMENT_LOG_SUCCESSFUL, array('dwolla_transaction_id' => $transactionId));
			// find the bonus and pay it out
			$transaction = $this->Transaction->find('first', array(
				'conditions' => array(
					'Transaction.user_id' => $transaction['Transaction']['user_id'],
					'Transaction.type_id' => TRANSACTION_DWOLLA,
					'Transaction.deleted' => null,
				)
			));
			// if it doesn't exist for whatever reason
			if (!$transaction) {
				$transactionSource = $this->Transaction->getDataSource();
				$transactionSource->begin();
				$this->Transaction->create();
				$this->Transaction->save(array('Transaction' => array(
					'type_id' => TRANSACTION_DWOLLA,
					'linked_to_id' => '0', 
					'user_id' => $user['User']['id'],
					'amount' => 50,
					'paid' => false,
					'name' => 'Dwolla Bonus',
					'status' => TRANSACTION_PENDING,
					'executed' => date(DB_DATETIME)
				)));
				$transaction_id = $this->Transaction->getInsertId();
				$transaction = $this->Transaction->findById($transaction_id);
				$this->Transaction->approve($transaction);
				$transactionSource->commit();
			}
			// unpaid - then pay it out
			elseif (!$transaction['Transaction']['paid']) {
				$this->Transaction->create();
				$this->Transaction->approve($transaction);
			}
			
			return true;
		}
		
		$this->PaymentLog->log($transaction, PAYMENT_LOG_FAILED, array('dwolla_error' => '$Transactions->send() did not work! dwolla payment failed.'));
		return false;
	}

	function executeWithdrawal($withdrawal, $dwolla_id) {
		if (empty($dwolla_id)) {
			$message = '#' . $withdrawal['Withdrawal']['id'] . ' failed to process, User dwolla_id not found.';
			CakeLog::write('payouts.dwolla', $message);

			return false;
		}
		
		$models_to_load = array(
			'Withdrawal',
			'Transaction',
			'PaymentMethod'
		);
		foreach ($models_to_load as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		$amount = -1 * $withdrawal['Withdrawal']['amount_cents'] / 100; // convert cents to dollars
		
		/* -- get master access token to handle dwolla payout */
		$master_access_token = $this->PaymentMethod->find('first', array(
			'recursive' => -1,
			'conditions' => array(
				'user_id' => 0,
				'payment_id' => 'master_access_token'
			)
		));
		if (!$master_access_token) {
			$message = '#' . $withdrawal['Withdrawal']['id'] . ' failed to process. Master access token not found.';
			CakeLog::write('payouts.dwolla', $message);

			return false;
		}
		/* get master access token to handle dwolla payout -- */

		/* -- send money using master access token */
		App::import('Vendor', 'autoload', array(
			'file' => 'DwollaSDK' . DS . 'autoload.php'
		));
		$Transactions = new Dwolla\Transactions();
		$Transactions->settings->oauth_token = $master_access_token['PaymentMethod']['value'];
		$transactionId = $Transactions->send($dwolla_id, $amount);
		CakeLog::write('payouts.dwolla', $transactionId);
		/* send money using master access token -- */

		if (!empty($transactionId) && $transactionId > 0) {
			/* -- send $50 to dwolla users as a reward */
			// find the bonus
			$transaction = $this->Transaction->find('first', array(
				'conditions' => array(
					'Transaction.user_id' => $withdrawal['Withdrawal']['user_id'],
					'Transaction.type_id' => TRANSACTION_DWOLLA,
					'Transaction.deleted' => null,
				)
			));
			if (!$transaction) {
				// if it doesn't exist for whatever reason
				$transactionSource = $this->Transaction->getDataSource();
				$transactionSource->begin();
				$this->Transaction->create();
				$this->Transaction->save(array('Transaction' => array(
					'type_id' => TRANSACTION_DWOLLA,
					'linked_to_id' => '0', 
					'user_id' => $user['User']['id'],
					'amount' => 50,
					'paid' => false,
					'name' => 'Dwolla Bonus',
					'status' => TRANSACTION_PENDING,
					'executed' => date(DB_DATETIME)
				)));
				$transaction_id = $this->Transaction->getInsertId();
				$transaction = $this->Transaction->findById($transaction_id);
				$this->Transaction->approve($transaction);
				$transactionSource->commit();
			}
			elseif (!$transaction['Transaction']['paid']) {
				// unpaid - then pay it out
				$this->Transaction->create();
				$this->Transaction->approve($transaction);
			}
			/* send $50 to dwolla users as a reward -- */

			return array(
					'paid_amount_cents' => $withdrawal['Withdrawal']['amount_cents'],
					'response' => '',
					'payment_id' => $transactionId
				);
		}
		
		return false;
	}
}
