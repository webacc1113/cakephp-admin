<?php
App::uses('CakeEmail', 'Network/Email');
App::import('Lib', 'MintVineUser');
App::import('Lib', 'Utilities');

class WithdrawalsShell extends AppShell {
	public $uses = array('Withdrawal', 'Project', 'PaymentMethod', 'User', 'HellbanLog', 'TransactionReport');
	
	/*
	 * export withdrawal records for a specific date range into CSV
	 * export only processed ones
	 */
	function export_withdrawal_data() {
		/* -- parsing args */
		if (!isset($this->args[0])) {
			return false; 
		}
		$withdrawal_report_id = $this->args[0];
		
		if (!empty($this->args[1])) {
			$date_from = $this->args[1];
		}
		
		if (!empty($this->args[2])) {
			$date_to = $this->args[2];
		}	
		
		if (isset($date_from) && !empty($date_from)) {
			$conditions['Withdrawal.processed >='] = date(DB_DATE, strtotime($date_from));
		}

		if (isset($date_to) && !empty($date_to)) {
			$conditions['Withdrawal.processed <='] = date(DB_DATE, strtotime($date_to));
		}
		$this->out('Starting exports from '.$date_from.' to '.$date_to); 
		/* parsing args -- */

		/* -- fetching withdrawal records matching criteria */
		$order = 'Withdrawal.id DESC';
		$contain = array(
			'User' => array(
				'fields' => array('User.*'),
				'QueryProfile' => array(
					'fields' => array(
						'country'
					)
				)
			),
			'PaymentMethod'
		);
		$this->Withdrawal->bindItems();
		$conditions['Withdrawal.deleted'] = null;
		
		$withdrawals = $this->Withdrawal->find('all', array(
			'conditions' => $conditions,
			'order' => $order,
			'contain' => $contain
		));

		if (!$withdrawals) {
			$this->out('There is no withdrawal available between the selected date range.'); 
			return false; 
		}
		$total = count($withdrawals); 
		$this->out('Exporting '.$total.' withdrawals'); 
		/* fetching withdrawal records matching criteria -- */

		/* -- crafting export file path */
		if (!is_dir(WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'withdrawal_reports' . DIRECTORY_SEPARATOR)) {
			mkdir(WWW_ROOT . 'files' . DIRECTORY_SEPARATOR . 'withdrawal_reports' . DIRECTORY_SEPARATOR);
		}
		$filename = 'withdrawals_'.date('Y-m-d') .'_'. time() .'.csv';
		$file_dir_path = 'files' . DIRECTORY_SEPARATOR . 'withdrawal_reports' . DIRECTORY_SEPARATOR . $filename;
		$file = WWW_ROOT . $file_dir_path;
		/* crafting export file path -- */
		
		/* -- populating csv file */
		$fp = fopen($file, 'w');
		fputcsv($fp, array(
			'Withdrawal Request At',
			'Withdrawal Approved At',
			'Withdrawal Processed At',
			'Payment Method',
			'Amount',
			'Panelist Id',
			'Country',
			'Total Withdrawal'
		));

		$i = 0; 
		foreach ($withdrawals as $withdrawal) {	
			$country = null;
			if (!empty($withdrawal['User']['QueryProfile']['country'])) {
				$country = $withdrawal['User']['QueryProfile']['country'];
			}

			$total_withdrawal = $this->Withdrawal->find('count', array(
				'conditions' => array(
					'Withdrawal.user_id' => $withdrawal['Withdrawal']['user_id'],
					'Withdrawal.deleted' => null,
				),
				'recursive' => -1
			));

			fputcsv($fp, array(
				$withdrawal['Withdrawal']['created'],
				$withdrawal['Withdrawal']['approved'],
				$withdrawal['Withdrawal']['processed'],
				$withdrawal['PaymentMethod']['payment_method'],
				'$'.number_format(round(-1 * $withdrawal['Withdrawal']['amount_cents'] / 100, 2), 2),
				$withdrawal['Withdrawal']['user_id'],
				$country,
				$total_withdrawal
			));

			$this->out(($i ++) . ' / ' . $total . ': ' . $withdrawal['Withdrawal']['id'] . ' written');
		}
		/* populating csv file -- */

		if (isset($withdrawal_report_id)) {
			CakePlugin::load('Uploader');
			App::import('Vendor', 'Uploader.S3');

			/* -- fetching aws S3 credentials */
			$settings = $this->Setting->find('list', array(
				'fields' => array('name', 'value'),
				'conditions' => array(
					'Setting.name' => array(
						's3.access',
						's3.secret',
						's3.bucket',
						's3.host'
					),
					'Setting.deleted' => false
				)
			));
			$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);
			/* fetching aws S3 credentials -- */

			$this->out('Writing to S3 ' . $file_dir_path . ' from ' . $file);	
			$headers = array(
				'Content-Disposition' => 'attachment; filename=' . $filename
			);
			if ($S3->putObject($S3->inputFile($file), $settings['s3.bucket'] , $file_dir_path, S3::ACL_PRIVATE, array(), $headers)) {
				/* -- log S3 upload action */
				$this->TransactionReport->create();
				$this->TransactionReport->save(array('TransactionReport' => array(
					'id' => $withdrawal_report_id,
					'path' => $file_dir_path,
					'status' => 'complete'
				)), true, array('path', 'status'));	
				/* log S3 upload action -- */
			}
		}

		$this->out('Completed!');
	}


	/*
	 * checks if the command contains the arg, if so, it must be a valid withdrawal_id
	 * waiting for new message from SQS
	 * message body is assumed to contain withdrawal_id
	 * perform sanity check on the withdrawal record
	 * execute payout, and update record
	 */
	public function payouts() {
		$logging_key = strtoupper(Utils::rand('4'));

		/* -- recipients to be copied emails */
		$bcc_setting = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'payouts.bcc',
				'Setting.deleted' => false
			)
		));
		if ($bcc_setting) {
			$bcc = explode(',', $bcc_setting['Setting']['value']);
			array_walk($bcc, create_function('&$val', '$val = trim($val);')); 
		}
		else {
			$bcc = array();
		}
		/* recipients to be copied emails -- */

		/* -- reset assets' url */
		$setting = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('cdn.url'),
				'Setting.deleted' => false
			)
		));
		if (!empty($setting['cdn.url']) && (!defined('IS_DEV_INSTANCE') || !IS_DEV_INSTANCE)) {
			Configure::write('App.cssBaseUrl', $setting['cdn.url'] . '/');
			Configure::write('App.jsBaseUrl', $setting['cdn.url'] . '/');
			Configure::write('App.imageBaseUrl', $setting['cdn.url'] . '/img/');
		}
		/* reset assets' url -- */

		/* -- initialize e-mailer using Mailgun */		
		CakePlugin::load('Mailgun');
		
		$email = new CakeEmail();
		$email->config('mailgun');
		$email->from(array(EMAIL_SENDER => 'MintVine'))
			->replyTo(array(REPLYTO_EMAIL => 'MintVine'))
			->emailFormat('html');
		/* initialize e-mailer -- */

		/* -- load necessary models */
		$models_to_import = array('Withdrawal', 'Transaction', 'PaymentMethod', 'User', 'WithdrawalStatusLog', 'CashNotification');
		foreach ($models_to_import as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		/* load necessary models -- */

		/* -- setting up amazon sqs listener */
		App::import('Vendor', 'sqs');
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('sqs.access.key', 'sqs.access.secret', 'sqs.payout.queue'),
				'Setting.deleted' => false
			)
		));
		if (count($settings) < 3) {
			return false; // SQS configuration not found
		}

		$sqs = new SQS($settings['sqs.access.key'], $settings['sqs.access.secret']);		
		/* setting up amazon sqs listener -- */

		/* --------------------------------------------------------------- */

		/*
		 * starting loop for getting SQS message
		 * on receiving message, try to process it
		 */
		$this->lecho('Starting', 'payouts', $logging_key);
	
		$processed_ids = array(); // contains the array of withdrawal_ids processed in a loop
		$i = 0;
		while (true) {
			$withdrawal_id = null;

			/* -- fetching withdrawal_id from SQS or argument list */
			if (!isset($this->args[0])) {
				$results = $sqs->receiveMessage($settings['sqs.payout.queue']);
				if (!empty($results['Messages'])) {
					$withdrawal_id = $results['Messages'][0]['Body'];
				}
				else {
					// Queue is empty
					$this->lecho('Completed processing '.$i.' payouts', 'payouts', $logging_key); 
					break;
				}
			}
			else {
				$withdrawal_id = $this->args[0];
			}

			if (!$withdrawal_id) {
				break;
			}
			/* fetching withdrawal_id from SQS or argument list -- */

			/* -- sanity check; the moment we see the same transaction ID, kill the loop */
			if (in_array($withdrawal_id, $processed_ids)) {
				$this->lecho('Completed processing ' . $i . ' payouts', 'payouts', $logging_key); 
				break;
			}
			/* sanity check -- */

			/* -- finding withdrawal & transaction records */
			$withdrawal = $this->Withdrawal->find('first', array(
				'conditions' => array(
					'Withdrawal.id' => $withdrawal_id,
					'Withdrawal.deleted' => null,
				)
			));
			if (!$withdrawal) {
				if (!isset($this->args[0])) {
					$sqs->deleteMessage($settings['sqs.payout.queue'], $results['Messages'][0]['ReceiptHandle']);
				}
				$this->lecho('Could not find #' . $withdrawal_id, 'payouts', $logging_key);
				continue;
			}

			$transaction = $this->Transaction->find('first', array(
				'conditions' => array(
					'Transaction.id' => $withdrawal['Withdrawal']['transaction_id'],
					'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
					'Transaction.deleted' => null,
				)
			));
			if (!$transaction) {
				$this->lecho('#' . $withdrawal_id . 'is invalid', 'payouts', $logging_key);
				$this->Withdrawal->delete($withdrawal_id);
				continue;
			}
			/* finding withdrawal & transaction records -- */

			$this->lecho('Processing #' . $withdrawal_id, 'payouts', $logging_key); 
			$processed_ids[] = $withdrawal_id;

			/* -- sanity check against CashNotification */
			$notification = $this->CashNotification->find('first', array(
				'conditions' => array(
					'user_id' => $withdrawal['Withdrawal']['user_id'],
					'amount' => (-1 * $withdrawal['Withdrawal']['amount_cents']),
					'created >=' => date(DB_DATETIME, time() - 43200)
				)
			));
			if ($notification) {
				$this->lecho('Withdrawal #' . $withdrawal_id . ' has been marked as paid already via cash notifications.', 'payouts', $logging_key); 
				continue;
			}
			/* sanity check against CashNotification -- */

			/* -- fetch payment method 
			 * if payment_method id is linked to withdrawal, we use that payment method, else we use the active payment_mehtod
			 */
			if ($withdrawal['Withdrawal']['payment_identifier']) {
				$conditions = array('id' => $withdrawal['Withdrawal']['payment_identifier']);
			}
			else {
				$conditions = array(
					'user_id' => $withdrawal['User']['id'],
					'status' => DB_ACTIVE,
					'payment_method' => array('paypal', 'dwolla', 'tango', 'mvpay')
				);
			}
			$payment_method = $this->PaymentMethod->find('first', array('conditions' => $conditions));
			$this->lecho('#' . $withdrawal_id.' linked to payment method #' . $payment_method['PaymentMethod']['id'], 'payouts', $logging_key); 
			$this->lecho($payment_method, 'payouts', $logging_key); 
			/* fetch payment method -- */

			/* -- filter withdrawals which are approved & unprocessed ones */
			if ($withdrawal['Withdrawal']['status'] == WITHDRAWAL_PAYOUT_SUCCEEDED) {
				$this->lecho('#' . $withdrawal_id . ' has been marked as processed.', 'payouts', $logging_key); 
				if (!isset($this->args[0])) {
					$sqs->deleteMessage($settings['sqs.payout.queue'], $results['Messages'][0]['ReceiptHandle']);
				}
				continue;
			}

			if ($withdrawal['Withdrawal']['status'] == WITHDRAWAL_PAYOUT_FAILED) {
				// two business days to resolve issues before automatically returning the cash
				if (Utils::business_days($withdrawal['Withdrawal']['processed'], 2) < date(DB_DATETIME)) {
					
					if (!empty($withdrawal['User']['email'])) {
						$email = new CakeEmail();
						$email->config('mailgun');
						$email->from(array(EMAIL_SENDER => 'MintVine'))
							->replyTo(array(REPLYTO_EMAIL => 'MintVine'))
							->emailFormat('html');
						
						$email->template('payout_failure')
							->viewVars(array(
								'type' => $payment_method['PaymentMethod']['payment_method'],
								'payment_value' => $payment_method['PaymentMethod']['value'],
								'transaction' => $transaction,
								'username' => $withdrawal['User']['username']
							))
							->to(array($withdrawal['User']['email']))
							->subject('MintVine Payout Failure');
						
						if (!empty($bcc)) {
							$email->bcc($bcc);
						}
						$email->send();
					}
					
					$this->Withdrawal->create();
					$this->Withdrawal->delete($withdrawal_id);
					
					// After deleting the withdrawal we need to rebuild the user balance
					$this->User->rebuildBalances($withdrawal);
		
					CakeLog::write('payouts.returned', 'Returned ' . (-1 * $withdrawal['Withdrawal']['amount_cents']) . ' points to '.$withdrawal['User']['email']);
					if (!isset($this->args[0])) {
						// mark this as received and add it back into the queue until it processes
						$sqs->deleteMessage($settings['sqs.payout.queue'], $results['Messages'][0]['ReceiptHandle']);
						$this->lecho('Deleted #' . $withdrawal_id . ' due to repeated failure attempts', 'payouts', $logging_key); 
					}
					continue;
				}
			}
			/* filter withdrawals which are approved & unprocessed ones -- */

			/* -- process payout */
			$this->lecho('#' . $withdrawal_id . ' processing via '.$payment_method['PaymentMethod']['payment_method'], 'payouts', $logging_key); 
			$save = false;

			if ($payment_method && $payment_method['PaymentMethod']['payment_method'] == 'paypal') {
				$this->PaymentPaypal = $this->Tasks->load('PaymentPaypal');
				$save = $this->PaymentPaypal->executeWithdrawal($withdrawal, $payment_method['PaymentMethod']['value']);
				if ($save === false) {
					$this->lecho('[FAILED] #' . $withdrawal_id . ' - please view payouts.paypal logs', 'payouts', $logging_key); 
				}
			}
			elseif ($payment_method && $payment_method['PaymentMethod']['payment_method'] == 'dwolla') {
				$this->PaymentDwolla = $this->Tasks->load('PaymentDwolla');
				$save = $this->PaymentDwolla->executeWithdrawal($withdrawal, $payment_method['PaymentMethod']['payment_id']);
				if ($save === false) {
					$this->lecho('[FAILED] #' . $withdrawal_id . ' - please view payouts.dwolla logs', 'payouts', $logging_key); 
				}
			}
			elseif ($payment_method && $payment_method['PaymentMethod']['payment_method'] == 'dwolla_id') {
				$this->PaymentDwolla = $this->Tasks->load('PaymentDwolla');
				$save = $this->PaymentDwolla->executeWithdrawal($withdrawal, $payment_method['PaymentMethod']['value']);
				if ($save === false) {
					$this->lecho('[FAILED] #' . $withdrawal_id . ' - please view payouts.dwolla logs', 'payouts', $logging_key); 
				}
			}
			elseif ($payment_method && $payment_method['PaymentMethod']['payment_method'] == 'tango') {
				$this->PaymentTango = $this->Tasks->load('PaymentTango');
				$save = $this->PaymentTango->executeWithdrawal($withdrawal, $payment_method['PaymentMethod']['payment_id']);
				if ($save === false) {
					$this->lecho('[FAILED] #'.$withdrawal_id.' - please view payouts.tango logs', 'payouts', $logging_key); 
				}
			}
			elseif ($payment_method && $payment_method['PaymentMethod']['payment_method'] == 'mvpay') {
				$this->PaymentMvpay = $this->Tasks->load('PaymentMvpay');
				$save = $this->PaymentMvpay->executeWithdrawal($withdrawal, $payment_method['PaymentMethod']['payment_id']);
				if ($save === false) {
					$this->lecho('[FAILED] #' . $withdrawal_id . ' - please view payouts.mvpay logs', 'payouts', $logging_key); 
				}
			}
			else {
				print_r($payment_method); exit();
				$this->lecho('#' . $withdrawal_id . ' using invalid payment type', 'payouts', $logging_key); 
			}
			/* process payout -- */

			/* 
			 * ---------------------------------------------------------------
			 * $save contains either array, or false
			 * if array, it indicates that payout 's successful, and $save contains array('paid_amount_cents' => 'X', 'response' => 'X', 'payment_id' => 'X')
			 * if false, it indicates that payout 's failed
			 * ---------------------------------------------------------------
			 */

			/* -- update withdrawal/transaction record based on payout result */
			if ($save !== false) {
				$i++;

				$this->lecho('#' . $withdrawal_id . ' PAID', 'payouts', $logging_key); 
				if (!isset($this->args[0])) {
					$sqs->deleteMessage($settings['sqs.payout.queue'], $results['Messages'][0]['ReceiptHandle']);
				}

				/* ---- mark withdrawal as processed */
				$this->Withdrawal->getDatasource()->reconnect();
				$this->Withdrawal->save(array('Withdrawal' => array(
					'id' => $withdrawal['Withdrawal']['id'],
					'status' => WITHDRAWAL_PAYOUT_SUCCEEDED,
					'response' => $save['response'],
					'paid_amount_cents' => $save['paid_amount_cents'],
					'payment_id' => $save['payment_id'],
					'processed' => date(DB_DATETIME)
				)), true, array('status', 'response', 'paid_amount_cents', 'payment_id', 'processed'));
				/* mark withdrawal as processed ---- */

				// update user balance
				$this->User->rebuildBalances($withdrawal);

				/* ---- cash out notification */
				$this->CashNotification->create();
				$this->CashNotification->save(array('CashNotification' => array(
					'user_id' => $withdrawal['Withdrawal']['user_id'],
					'type' => $payment_method['PaymentMethod']['payment_method'],
					'amount' =>  (-1 * $save['amount_cents'])
				)));
				/* cash out notification ---- */

				/* ---- send cash out email */
				$user = $this->User->find('first', array(
					'conditions' => array(
						'User.id' => $withdrawal['Withdrawal']['user_id']
					),
					'fields' => array('id', 'username', 'ref_id', 'email', 'medvine'),
					'recursive' => -1
				));
				
				// skip medvine
				if ($user['User']['medvine']) {
					continue;
				}
			
				$trustpilot_count = $this->UserOption->find('count', array(
					'conditions' => array(
						'UserOption.user_id' => $user['User']['id'],
						'UserOption.name' => 'trustpilot.invite',
					)
				));
				
				$email->template('payout')
					->viewVars(array(
						'user_name' => $user['User']['username'],
						'user_id' => $user['User']['id'],
						'payment_method' => $payment_method['PaymentMethod']['payment_method'],
						'payment_id' => $payment_method['PaymentMethod']['payment_id'],
						'amount' => (-1 * $save['paid_amount_cents']) / 100,
						'unsubscribe_link' => HOSTNAME_WWW.'/users/emails/' . $user['User']['ref_id'],
						'trustpilot' => ($trustpilot_count == 0) ? true : false
					))
					->to(array($user['User']['email']));
				if ($trustpilot_count == 0) {
					$trustpilot_setting = $this->Setting->find('first', array(
						'fields' => array('Setting.value'),
						'conditions' => array(
							'Setting.name' => 'trustpilot.email',
							'Setting.deleted' => false
						)
					));
					if ($trustpilot_setting && !empty($trustpilot_setting['Setting']['value'])) {
						$email->bcc($trustpilot_setting['Setting']['value']);
					}
				}
				
				$email->subject('MintVine Payout Complete')
					->send();
				if ($trustpilot_count == 0) {
					$this->UserOption->create();
					$this->UserOption->save(array('UserOption' => array(
						'user_id' => $user['User']['id'],
						'name' => 'trustpilot.invite',
						'value' => date(DB_DATETIME)
					)));
				}
				/* send cash out email ---- */
			}
			else {
				/* ---- mark withdrawal as failed */
				$this->Withdrawal->getDatasource()->reconnect();
				$this->Withdrawal->save(array('Withdrawal' => array(
					'id' => $withdrawal['Withdrawal']['id'],
					'processed' => date(DB_DATETIME),
					'status' => WITHDRAWAL_PAYOUT_FAILED
				)), true, array('status'));
				/* mark withdrawal as failed ---- */

				if (!isset($this->args[0])) {
					// mark this as received and add it back into the queue until it processes
					$sqs->deleteMessage($settings['sqs.payout.queue'], $results['Messages'][0]['ReceiptHandle']);
					$this->lecho('Requeued #' . $withdrawal_id . ' for processing', 'payouts', $logging_key); 
				}
			}
			/* update withdrawal/transaction record based on payout result -- */
		
		}

		$this->lecho('Ending', 'payouts', $logging_key);
	}
}
