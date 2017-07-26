<?php
App::uses('AppController', 'Controller');
App::import('Lib', 'MintVineUser');

class TransactionsController extends AppController {

	public $uses = array('Transaction', 'SurveyUserVisit', 'SurveyVisit', 'Project', 'VirtualMassAdd', 'SurveyVisit', 'Setting', 'TransactionReport', 'UserOption');
	public $helpers = array('Html', 'Time', 'Calculator');
	public $components = array(); 
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function mass_add() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$this->VirtualMassAdd->set($this->request->data); 
			if ($this->VirtualMassAdd->validates()) {
				$inputs = explode("\n", trim($this->request->data['VirtualMassAdd']['inputs'])); 
				array_walk($inputs, create_function('&$val', '$val = trim($val);')); 
				$failed = $failed_inputs = array();
				$i = 0;
				foreach ($inputs as $input) {
					if ($this->request->data['VirtualMassAdd']['identifier_type'] == 'user_id') {
						$user = $this->User->find('first', array(
							'recursive' => -1,
							'conditions' => array(
								'User.id' => $input
							),
							'fields' => array('id')
						));
						if (!$user) {
							$failed[] = 'Could not locate MintVine ID: '.$input;
							$failed_inputs[] = $input;
							continue;
						}
					}
					if ($this->request->data['VirtualMassAdd']['identifier_type'] == 'partner_user_id') {
						$values = explode('-', $input);
						$user = $this->User->find('first', array(
							'recursive' => -1,
							'conditions' => array(
								'User.id' => $values[1]
							),
							'fields' => array('id')
						));
						if (!$user) {
							$failed[] = 'Could not locate partner hash: '.$input;
							$failed_inputs[] = $input;
							continue;
						}
					}
					if ($this->request->data['VirtualMassAdd']['identifier_type'] == 'hash') {
						$survey_visit = $this->SurveyVisit->find('first', array(
							'conditions' => array(
								'SurveyVisit.hash' => $input,
								'SurveyVisit.survey_id' => Utils::parse_project_id_from_hash($input)
							),
							'fields' => array('partner_user_id', 'id')
						));
						if ($survey_visit) {							
							$values = explode('-', $survey_visit['SurveyVisit']['partner_user_id']);
							$user = $this->User->find('first', array(
								'recursive' => -1,
								'conditions' => array(
									'User.id' => $values[1]
								),
								'fields' => array('id')
							));
						}
						if (!$survey_visit || !$user) {
							$failed[] = 'Could not locate hash: '.$input;
							$failed_inputs[] = $input;
							continue;
						}
					}
					if ($this->request->data['VirtualMassAdd']['identifier_type'] == 'email') {
						$user = $this->User->find('first', array(
							'recursive' => -1,
							'conditions' => array(
								'User.email' => $input
							),
							'fields' => array('id')
						));
						if (!$user) {
							$failed[] = 'Could not locate email: '.$input;
							$failed_inputs[] = $input;
							continue;
						}
					}
					$transactionSource = $this->Transaction->getDataSource();
					$transactionSource->begin();
					$this->Transaction->create();
					$this->Transaction->save(array('Transaction' => array(
						'executed' => date(DB_DATETIME),
						'user_id' => $user['User']['id'],
						'status' => TRANSACTION_PENDING,
						'paid' => false,
						'type_id' => TRANSACTION_OTHER,
						'name' => $this->request->data['VirtualMassAdd']['description'],
						'amount' => $this->request->data['VirtualMassAdd']['amount']
					)));
					$transaction_id = $this->Transaction->getInsertId();
					$transaction = $this->Transaction->findById($transaction_id);
					$this->Transaction->approve($transaction);
					$transactionSource->commit();
					$i++;
				}
				if (!empty($failed)) {
					$this->Session->setFlash($i.' users have been credited.<br/><br/>The following credit attempts failed: <ul><li>'.implode('</li><li>', $failed).'</li></ul>', 'flash_error');
					$this->request->data['VirtualMassAdd']['inputs'] = implode("\n", $failed_inputs); 
				}
				else {
					$this->Session->setFlash($i.' users credited.', 'flash_success');
					$this->redirect(array('controller' => 'transactions', 'action' => 'index', '?' => array('type' => TRANSACTION_OTHER)));
				}
			}
		}
	}
		
	public function add() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$user = $this->User->find('first', array(
				'conditions' => array(
					'User.email' => $this->request->data['Transaction']['email'],
					'User.deleted_on' => null
				)
			));
			if (!$user) {
				$this->Session->setFlash('That user email does not exist.', 'flash_error'); 
			}
			elseif (empty($this->request->data['Transaction']['amount'])) {
				$this->Session->setFlash('An amount was not provided.', 'flash_error');
			}
			elseif (empty($this->request->data['Transaction']['description'])) {
				$this->Session->setFlash('A description was not provided.', 'flash_error');
			}
			else {
				$transactionSource = $this->Transaction->getDataSource();
				$transactionSource->begin();
				$this->Transaction->create();
				$this->Transaction->save(array('Transaction' => array(
					'executed' => date(DB_DATETIME),
					'user_id' => $user['User']['id'],
					'status' => TRANSACTION_PENDING,
					'paid' => false,
					'type_id' => TRANSACTION_OTHER,
					'name' => $this->request->data['Transaction']['description'],
					'amount' => $this->request->data['Transaction']['amount']
				)));
				$transaction_id = $this->Transaction->getInsertId();
				$transaction = $this->Transaction->findById($transaction_id);
				$this->Transaction->approve($transaction);
				$transactionSource->commit();
				$this->Session->setFlash('Points successfully gifted.', 'flash_success');
				$this->redirect(array('action' => 'index', '?' => array('type' => TRANSACTION_OTHER, 'user' => '#'.$user['User']['id'])));
			}
		}
	}
	
	public function credit_hashes() {
		App::import('Model', 'TransactionCredit');
		$this->TransactionCredit = new TransactionCredit; 
		
		if ($this->request->is('post') || $this->request->is('put')) {
			$this->request->data['TransactionCredit']['hashes'] = trim($this->request->data['TransactionCredit']['hashes']);
			if (!empty($this->request->data['TransactionCredit']['hashes'])) {
				
				$max_run = $this->TransactionCredit->find('first', array(
					'fields' => array('max(TransactionCredit.run) as max_run'),
				));
				$max_run = empty($max_run[0]['max_run']) ? 0: $max_run[0]['max_run'];
				$max_run++;
					
				$credit_hashes = explode("\n", $this->request->data['TransactionCredit']['hashes']);
				array_walk($credit_hashes, create_function('&$val', '$val = trim($val);')); 
				if (!empty($credit_hashes)) {
					foreach ($credit_hashes as $credit_hash) {
						$transaction_credit = $this->TransactionCredit->find('first', array(
							'fields' => array('TransactionCredit.id'),
							'conditions' => array(
								'TransactionCredit.hash' => $credit_hash
							)
						));
						if ($transaction_credit) {
							continue;
						}
						list($survey_id, $nothing) = explode('m', $credit_hash);
						$survey_visit = $this->SurveyVisit->find('first', array(
							'conditions' => array(
								'SurveyVisit.hash' => $credit_hash
							)
						));
					}
				}
			}
			db($this->request->data); 
		}
	}
	
	public function withdrawals($transaction_id = null) {
		if ($this->request->is('post')) {
			$transaction = $this->Transaction->findById($transaction_id); 
			if ($this->request->data['type'] == 'mark') {
				$this->Transaction->create();
				$this->Transaction->save(array('Transaction' => array(
					'id' => $transaction['Transaction']['id'],
					'payout_processed' => true
				)), true, array('payout_processed'));
    			return new CakeResponse(array(
					'body' => json_encode(array(
					)), 
					'type' => 'json',
					'status' => '201'
				));
			}
			elseif ($this->request->data['type'] == 'delete') {
				$this->Transaction->create();
				$this->Transaction->delete($transaction['Transaction']['id']);
				return new CakeResponse(array(
					'body' => json_encode(array(
						'status' => '1'
					)), 
					'type' => 'json',
					'status' => '201'
				));
			}
		}
		$this->Transaction->bindModel(array(
			'belongsTo' => array(
				'PaymentMethod' => array(
					'foreignKey' => 'linked_to_id'
				),
				'User'
			),
			'hasOne' => array(
				'PaymentLog'
			)
		), false);
		$conditions = array(
			'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
			'Transaction.status' => TRANSACTION_APPROVED,
			'Transaction.executed >' => date(DB_DATETIME, strtotime('-90 days'))
		);
		
		if (isset($this->request->query['q']) && !empty($this->request->query['q'])) {
			$query = $this->request->query['q'];
			$conditions['OR'] = array(
				'PaymentMethod.value LIKE' => '%'.$query.'%',
				'User.email LIKE' => '%'.$query.'%'
			);
			$this->set('type', null);
		}
		if (isset($this->request->query['method']) && !empty($this->request->query['method'])) {
			if ($this->request->query['method'] == 'paypal') {
				$conditions['PaymentMethod.payment_method'] = 'paypal';
			}
			elseif ($this->request->query['method'] == 'dwolla') {
				$conditions['PaymentMethod.payment_method'] = 'dwolla';
			}
			elseif ($this->request->query['method'] == 'giftbit') {
				$conditions['PaymentMethod.payment_method'] = 'gift';
			}
			elseif ($this->request->query['method'] == 'tango') {
				$conditions['PaymentMethod.payment_method'] = 'tango';
			}
			elseif ($this->request->query['method'] == 'mvpay') {
				$conditions['PaymentMethod.payment_method'] = 'mvpay';
			}
		}
		if (!isset($this->request->query['type']) || $this->request->query['type'] == 'failed') {
			$conditions['Transaction.payout_processed'] = PAYOUT_FAILED;
			$this->set('type', 'failed');
		}
		elseif ($this->request->query['type'] == 'succeeded') {
			$conditions['Transaction.payout_processed'] = PAYOUT_SUCCEEDED;
			$this->set('type', 'succeeded');
		}
		elseif ($this->request->query['type'] == 'unprocessed') {
			$conditions['Transaction.payout_processed'] = PAYOUT_UNPROCESSED;
			$this->set('type', 'unprocessed');
		}
		elseif ($this->request->query['type'] == 'all') {
			$this->set('type', 'all');
		}
		
		$conditions['Transaction.deleted'] = null;
		$paginate = array(
			'Transaction' => array(
				'limit' => 100,
				'contain' => array(
					'PaymentMethod',
					'User',
					'PaymentLog'
				),
				'order' => 'Transaction.executed DESC',
			)
		);
		if (!empty($conditions)) {
			$paginate['Transaction']['conditions'] = $conditions;
		}
		$this->paginate = $paginate;
		$transactions = $this->paginate(); 
		if ($transactions) {
			foreach ($transactions as $key => $transaction) {
				// mark the response from the partner as a bool
				$partner_check = null;
				if (isset($transaction['PaymentLog']['returned_info']) && !empty($transaction['PaymentLog']['returned_info'])) {
					$transactions[$key]['PaymentLog']['returned_info'] = $partner_response = json_decode($transaction['PaymentLog']['returned_info'], true);
					if ($transaction['PaymentMethod']['payment_method'] == 'gift') {
						$partner_check = isset($partner_response['status']) && $partner_response['status'] == 200;
					}
					if ($transaction['PaymentMethod']['payment_method'] == 'paypal') {
						$partner_check = $partner_response['ACK'] == 'Success';
					}
					if ($transaction['PaymentMethod']['payment_method'] == 'dwolla') {
						$partner_check = isset($partner_response['dwolla_transaction_id']) && !empty($partner_response['dwolla_transaction_id']);
					}
					if ($transaction['PaymentMethod']['payment_method'] == 'tango') {
						$partner_check = isset($partner_response['success']) && $partner_response['success'] == true;
					}
					if ($transaction['PaymentMethod']['payment_method'] == 'mvpay') {
						$partner_check = isset($partner_response['mvpay_transaction_id']) && !empty($partner_response['mvpay_transaction_id']);
					}
				}
				$transactions[$key]['PaymentLog']['partner_check'] = $partner_check;
			}
		}
		$payment_methods = array(
			'paypal' => 'PayPal',
			'dwolla' => 'Dwolla',
			'giftbit' => 'Giftbit',
			'tango' => 'Tangocard',
			'mvpay' => 'MVPay'
		);
		$this->set(compact('transactions', 'payment_methods'));
	}
	
	public function index() {
		$limit = 50;
		
		App::import('Vendor', 'sqs');
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('sqs.access.key', 'sqs.access.secret', 'sqs.payout.queue'),
				'Setting.deleted' => false
			)
		));
		if (count($settings) < 3) {
			$this->Session->setFlash('Your withdrawals will not process due to missing SQS keys. Do NOT attempt to process payouts!', 'flash_error');
		}
		$sqs = new SQS($settings['sqs.access.key'], $settings['sqs.access.secret']);
		
		if ($this->request->is('post')) {
			$rejected = $approved = $deleted = 0;
			$deleted_user_id = array();
			$rejected_transaction_id = array();
			if (isset($this->data['Transaction']) && !empty($this->data['Transaction'])) {
				foreach ($this->data['Transaction'] as $id => $value) {
					if ($value == 0 || $id == 'null') {
						continue;
					}
					
					// load up transaction
					$transaction = $this->Transaction->findById($id); 	
					if (isset($this->data['approve'])) {
						if ($transaction['Transaction']['type_id'] == TRANSACTION_WITHDRAWAL) {

							$transactionSource = $this->Transaction->getDataSource();
							$transactionSource->begin();
							$this->Transaction->soft_delete($transaction);
							$transaction = $this->Transaction->unset_unnecessary_values($transaction);
							
							$transaction['Transaction']['status'] = TRANSACTION_APPROVED;
							$transaction['Transaction']['executed'] = date(DB_DATETIME);
							
							$this->Transaction->create();
							$this->Transaction->save($transaction);
							$new_transaction_id = $this->Transaction->getInsertId();
							$transactionSource->commit();
							
							// add item into sqs
							$response = $sqs->sendMessage($settings['sqs.payout.queue'], $new_transaction_id);
						}
						else {
							$this->Transaction->approve($transaction);
						}
						
						$approved++;
					}
					
					if (isset($this->data['reject'])) {
						$rejected_transaction_id[] = $transaction['Transaction']['id'];
						$this->Transaction->reject($transaction);
						$rejected++;
					}
					if (isset($this->data['hellban'])) {
						$this->Transaction->reject($transaction);
						MintVineUser::hellban($transaction['User']['id'], 'auto: Hellbanned from withdrawals screen.', $this->current_user['Admin']['id']);
						$rejected++;
					}
					if (isset($this->data['delete'])) {
						if ($transaction) {
							$deleted_user_id[] = $transaction['Transaction']['user_id'];
							$this->Transaction->create();
							$this->Transaction->delete($id);
							$deleted++;
						}
					}
				}
				
				if (!empty($deleted_user_id)) {
					$deleted_user_id = array_unique($deleted_user_id);
					foreach ($deleted_user_id as $user_id) {
						$this->User->recursive = -1;
						$user = $this->User->findById($user_id);
						if ($user) {
							$this->User->rebuildBalances($user);
						}
					}
				}
			}
			if ($rejected > 0 || $approved > 0 || $deleted > 0) {
				$msgs = array();
				if ($rejected > 0) {
					$msgs[] = 'rejected '.$rejected.' transactions'; 
				}
				if ($deleted > 0) {
					$msgs[] = ' deleted '.$deleted.' transactions';
				}
				if ($approved > 0) {
					$msgs[] = ' approved '.$approved.' transactions';
				}
				$this->Session->setFlash('You have '.implode(' and ', $msgs).'.', 'flash_success');
				
				if (isset($this->request->query) && !empty($this->request->query)) {
					$return_url = array('action' => 'index', '?' => $this->request->query);
				}
				else {
					$return_url = array('action' => 'index');
				}
				if ($rejected > 0) {
					$this->redirect(array('action' => 'rejected', '?' => array('id' => implode(',', $rejected_transaction_id))));
				}
				$this->redirect($return_url);
			}
		}
		
		$conditions = array();
		$has_condition_against_foreign_model = false;
		$order = 'Transaction.id DESC';
		if (isset($this->request->query['user'])) {
			$this->request->query['user'] = urldecode($this->request->query['user']);
		}
		
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}

		$transaction_type = unserialize(TRANSACTION_TYPES);
		if (isset($this->data) && !isset($this->data['error'])) {
			if (!isset($this->data['unprocessed'])) {	
				if (!isset($this->data['type']) && !isset($this->data['user']) && !isset($this->request->query['linked_to_id'])) {
					$this->Session->setFlash('Please input a type to search', 'flash_error');
					$this->redirect(array('action' => 'index', '?' => array('error' => true)));
				}
				elseif (isset($this->data['type']) && $this->data['type'] != TRANSACTION_WITHDRAWAL && $this->data['type'] != TRANSACTION_OTHER) {
					if (in_array($this->request->query['type'], array(TRANSACTION_CODE, TRANSACTION_SURVEY, TRANSACTION_OFFER))) {
						if (!isset($this->request->query['linked_to_id'])) {
							$this->Session->setFlash('Please also use a linked_to_id to do a search against type '.$transaction_type[$this->data['type']], 'flash_error');
							$this->redirect(array('action' => 'index', '?' => array('type' => $this->data['type'], 'error' => true)));
						}
					}
					elseif (!isset($this->data['user']) || empty($this->data['user'])) {
						$this->Session->setFlash('Please also input a user_id to do a search against type '.$transaction_type[$this->data['type']], 'flash_error');
						$this->redirect(array('action' => 'index', '?' => array('type' => $this->data['type'], 'error' => true)));
					}
				}
			}
			if (isset($this->data['paid']) && $this->data['paid'] != '') {
				if (isset($this->data['type']) && $this->data['type'] == TRANSACTION_WITHDRAWAL) {
					if ($this->data['paid'] == 0) {
						$conditions['Transaction.paid'] = true; 
						$conditions['Transaction.status'] = TRANSACTION_PENDING; 
						$order = 'Transaction.id ASC';
					}
					elseif ($this->data['paid'] == 1) {
						$conditions['Transaction.paid'] = true; 
						$conditions['Transaction.status'] = TRANSACTION_APPROVED; 
					}
					elseif ($this->data['paid'] == 2) { 
						$conditions['Transaction.status'] = TRANSACTION_REJECTED; 
					}
				}
				else {
					if ($this->data['paid'] != 2) {
						$conditions['Transaction.paid'] = (int)$this->data['paid']; 
					}
					else {
						$conditions['Transaction.status'] = TRANSACTION_REJECTED; 
					}
				}
			}
			if (isset($this->data['type']) && $this->data['type'] != '') {
				$conditions['Transaction.type_id'] = (int)$this->data['type']; 
			}

			if (isset($this->data['payment_method']) && $this->data['payment_method'] != '') {
				$conditions['PaymentMethod.payment_method'] = $this->data['payment_method'];
				$has_condition_against_foreign_model = true;
			}

			if (isset($this->data['user']) && !empty($this->data['user'])) {
				if ($this->data['user']{0} == '#') {
					$user = $this->User->fromId(substr($this->data['user'], 1));
				}
				else {
					$user = $this->User->fromEmail($this->data['user']);
				}
				if ($user) {
					$conditions['Transaction.user_id'] = $user['User']['id']; 
				}
				else {
					$conditions['Transaction.user_id'] = '0'; 
				}
				$limit = 500;
				$this->set('user', $user);
				$this->set('is_user_filter', true);
			}
			
			// comes from payments log
			if (isset($this->data['transaction_id']) && !empty($this->data['transaction_id'])) {
				$transaction = $this->Transaction->findById($this->data['transaction_id']);
				if ($transaction) {
					$conditions['Transaction.user_id'] = $transaction['User']['id'];
				}
				else {
					$conditions['Transaction.user_id'] = '0';
				}
				$limit = 500;
				$this->set('user', $transaction['User']);
				$this->set('is_user_filter', true);
			}

			if (isset($this->data['amount_from']) && $this->data['amount_from'] != '') {
				if (isset($this->data['amount_to']) && $this->data['amount_to'] != '') {
					$conditions['Transaction.amount >='] = $this->data['amount_from'];
					$conditions['Transaction.amount <='] = $this->data['amount_to'];
				}
				else {
					$conditions['Transaction.amount'] = $this->data['amount_from'];
				}
			}
			
			if (isset($this->data['date_from']) && !empty($this->data['date_from'])) {
				if (isset($this->data['date_to']) && !empty($this->data['date_to'])) {
					$conditions['Transaction.executed >='] = date(DB_DATE, strtotime($this->data['date_from'])).' 00:00:00';
					$conditions['Transaction.executed <='] = date(DB_DATE, strtotime($this->data['date_to'])).' 23:59:59';
				}
				else {
					$conditions['Transaction.executed >='] = date(DB_DATE, strtotime($this->data['date_from'])).' 00:00:00';
					$conditions['Transaction.executed <='] = date(DB_DATE, strtotime($this->data['date_from'])).' 23:59:59';
				}
			}
		}
		
		if (isset($this->request->query['unprocessed'])) {
			$conditions['Transaction.paid'] = true;
			$conditions['Transaction.type_id'] = TRANSACTION_WITHDRAWAL;
			$conditions['Transaction.status'] = TRANSACTION_APPROVED;
			$conditions['Transaction.payout_processed'] = false;
		}
		if (isset($this->request->query['name'])) {
			$conditions['Transaction.name LIKE'] = '%'.$this->request->query['name'].'%';
		}

		if (isset($this->request->query['type']) && in_array($this->request->query['type'], array(TRANSACTION_CODE, TRANSACTION_SURVEY, TRANSACTION_OFFER)) && isset($this->request->query['linked_to_id'])) {
			$conditions['Transaction.linked_to_id'] = $this->request->query['linked_to_id'];
		}
		
		if (isset($this->request->query['type']) && $this->request->query['type'] == TRANSACTION_WITHDRAWAL) {
			$this->Transaction->bindModel(array(
				'hasMany' => array(
					'UserAnalysis' => array(
						'foreignKey' => 'transaction_id',
						'order' => 'UserAnalysis.id DESC'
					)
				)
			));
			$contain = array(
				'User',
				'UserAnalysis',
				'PaymentMethod'
			);
		}
		else {
			$contain = array(
				'User',
				'PaymentMethod'			
			);
		}
		
		// 
		if (empty($conditions)) {
			
		}
		else {

			// If conditions contains the one against the foreign model's field, bind models permanently
			// This is necessary because pagination makes a call to find() more than 2 times,
			// but bindModel() applies only to the next find() call.
			if ($has_condition_against_foreign_model) {
				$this->Transaction->bindItems(false);
			}
			else {
				$this->Transaction->bindItems();
			}
		
			$paginate = array(
				'Transaction' => array(
					'limit' => $limit,
					'order' => $order,
					'contain' => $contain
				)
			);

			$conditions['Transaction.deleted'] = null;
			$paginate['Transaction']['conditions'] = $conditions;
		
			$this->paginate = $paginate;
			$transactions = $this->paginate();
		
			// this may be unnecessary: we write these values at maintenance check time
			if ($transactions) {
				$failed = array(	
					'countries' => 'User has accessed the site outside of the US, GB, or CA', 
					'referral' => 'User was referred by a hellbanned user.', 
					'language' => 'User\'s browser language is not English.', 
					'locations' => 'User has used multiple states to access the site.', 
					'logins' => 'User utilized many different IPs for logins & registrations.', 
					'proxy' => 'User has utilized proxy servers.', 
					'timezone' => 'User has used a timezones that does not match their self-reported ZIP code.', 
					/* 'profile' => 'User sped through profile questions', */
					'rejected_transactions' => 'User had more than 5 rejected transactions',
					'frequency' => 'User has had a payout or registered in the last 7 days.',
					'asian_timezone' => 'User accessed from an Asian timezone',
					'distance' => 'User utilized IP addresses that are geographically dispersed.',
					'payout' => 'Large payout requested'/* ,
					'nonrevenue' => '> 90% non-revenue generating activity' */
				);
				foreach ($transactions as $key => $transaction) {
					$note = $transaction['Transaction']['note'];
					if (isset($transaction['UserAnalysis']) && isset($transaction['UserAnalysis'][0])) {
						foreach ($transaction['UserAnalysis'][0] as $column => $score) {
							if (!array_key_exists($column, $failed)) {
								continue;
							}
							if (empty($score)) {
								continue;
							}
							$transactions[$key]['Transaction']['note'] .= "<br/>Scoring: ".$failed[$column]; 
						}
					}
				}
			}
		}

		if (isset($user) && !empty($user)) {
			$title_for_layout = sprintf('Transactions - %s', $user['User']['email']);
		}
		else {
			$title_for_layout = 'Transactions';
		}
		
		if (isset($this->request->query['type']) && $this->request->query['type'] == TRANSACTION_WITHDRAWAL) {
			// sum amount
			$conditions['Transaction.deleted'] = null;
			$sums = $this->Transaction->find('first', array(
				'conditions' => $conditions,
				'fields' => array('SUM(Transaction.amount) as sum_amount'),
			));
			$this->set(compact('sums'));
		}
		
		// check master dwolla funding source balance
		$master_dwolla_error = $this->check_master_dwolla_account();		
		$this->set(compact('transactions', 'title_for_layout', 'master_dwolla_error'));
	}
	
	public function fix() {
		if ($this->request->is('put') || $this->request->is('post')) {
			$this->request->data['Transaction']['new_amount'] = trim($this->request->data['Transaction']['new_amount']);
			$project_id = MintVine::parse_project_id($this->data['Transaction']['project_id']);
			$project = $this->Project->find('first', array(
				'conditions' => array(
					'Project.id' => $project_id
				),
				'fields' => array('id'),
				'recursive' => -1
			));
			$save = true;
			if (!$project) {
				$this->Session->setFlash('That project does not exist.', 'flash_error');
				$save = false;
			}
			if (!is_numeric($this->data['Transaction']['new_amount'])) {
				$this->Session->setFlash('You did not input a valid numeric amount.', 'flash_error');
				$save = false;
			}
			if ($save) {
				$transactions = $this->Transaction->find('all', array(
					'conditions' => array(
						'Transaction.status' => TRANSACTION_PENDING,
						'Transaction.type_id' => TRANSACTION_SURVEY,
						'Transaction.linked_to_id' => $project_id,
						'Transaction.amount' => $this->data['Transaction']['old_amount'],
						'Transaction.deleted' => null,
					),
					'fields' => array('Transaction.id', 'User.id')
				));
				if ($transactions) {
					foreach ($transactions as $transaction) {
						$this->Transaction->soft_delete($transaction);
						$transaction = $this->Transaction->unset_unnecessary_values($transaction);
						$transaction['Transaction']['amount'] = $this->data['Transaction']['new_amount'];
						$this->Transaction->create();
						$this->Transaction->save($transaction);

						$this->User->rebuildBalances($transaction);
					}
				}
				$this->Session->setFlash(count($transactions).' transactions updated.', 'flash_success');
				$this->redirect(array('action' => 'index', '?' => array('type' => TRANSACTION_SURVEY, 'linked_to_id' => $project_id)));
			}
		}
	}
	
	public function inspect($user_id) {			
		App::import('Vendor', 'geoip/geoipcity'); 	
		App::import('Vendor', 'geoip/geoipregionvars'); 
		$gi = geoip_open(APP."Vendor/geoip/GeoIPRegion-115.dat", GEOIP_STANDARD);
		
		$this->User->bindModel(array('hasMany' => array('HellbanLog' => array(
			'fields' => array('HellbanLog.id', 'HellbanLog.user_id', 'HellbanLog.automated'),
			'conditions' => array(
				'HellbanLog.type' => 'unhellban'
			),
			'order' => 'HellbanLog.id DESC'
		))));
		$user = $this->User->findById($user_id);
		$user_survey_visits = $this->SurveyUserVisit->findAllByUserId($user_id, array(), array('SurveyUserVisit.id DESC'));
		foreach ($user_survey_visits as $key => $val) {
			$record = geoip_region_by_addr($gi, $val['SurveyUserVisit']['ip']);
			$region = array();
			if (!empty($record[1])) {
				$region[] = $record[1];
			}
			if (!empty($record[0])) {
				$region[] = $record[0];
			}
			$user_survey_visits[$key]['SurveyUserVisit']['region'] = implode(', ', $region);
		}
		$this->set(compact('user', 'user_survey_visits'));
	}
	
	public function rejected() {
		$limit = 50;
		
		if ($this->request->is('post')) {
			$notes = $this->request->data['note'];
			if ($notes) {
				foreach ($notes as $id => $note) {
					$this->Transaction->create();
					$this->Transaction->save(array('Transaction' => array(
            			'id' => $id,
            			'note' => $note
            		)), true, array('note'));
				}
				$this->Session->setFlash(__('Transaction notes have been updated.'), 'flash_success');
        	    return $this->redirect(array('action' => 'index', '?' => array('type' => 4, 'paid' => 0)));
			}
		}
		
		$conditions = array();
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		if (isset($this->data)) {
			if (isset($this->data['id']) && !empty($this->data['id'])) {
				if (strpos($this->data['id'], ',') !== false) {
					$conditions['Transaction.id'] = explode(',', $this->data['id']);
				}
				else {
					$conditions['Transaction.id'] = $this->data['id'];
				}
			}
		}
		$conditions['Transaction.status'] = TRANSACTION_REJECTED;
		$conditions['Transaction.type_id'] = 4; 
		$conditions['Transaction.deleted'] = null;
		$paginate = array(
			'Transaction' => array(
				'limit' => $limit,
				'order' => 'Transaction.id DESC',
			)
		);
		if (!empty($conditions)) {
			$paginate['Transaction']['conditions'] = $conditions;
		}
		$this->paginate = $paginate;
		$this->set('transactions', $this->paginate('Transaction'));
	}
	
	public function withdrawals_requested() {
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		
		$date_from = '';
		$date_to = '';
		if (isset($this->data) && !empty($this->data)) {
			if (isset($this->data['date_from']) && !empty($this->data['date_from'])) {
				if (isset($this->data['date_to']) && !empty($this->data['date_to'])) {
					$date_from = date(DB_DATE, strtotime($this->data['date_from']));
					$date_to = date(DB_DATE, strtotime($this->data['date_to']));
				}
				else {
					$date_from = date(DB_DATE, strtotime($this->data['date_from']));
					$date_to = date(DB_DATE, strtotime($this->data['date_from']));
				}
			}
		}
		else { // Get the last 3 days' data as default
			$date_from = date(DB_DATE, strtotime("3 days ago"));
			$date_to = date(DB_DATE, strtotime("1 day ago"));
			$this->data = array(
				'date_from' => date('m/d/Y', strtotime($date_from)),
				'date_to' => date('m/d/Y', strtotime($date_to))
			);
		}
		
		$this->Transaction->bindModel(array(
			'belongsTo' => array(
				'PaymentMethod' => array(
					'foreignKey' => 'linked_to_id'
				)
			)
		));
		
		$transactions = $this->Transaction->find('all', array(
			'conditions' => array(
				'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
				'PaymentMethod.payment_method' => array('paypal', 'tango', 'dwolla', 'mvpay'),
				'Transaction.created >=' => $date_from. ' 00:00:00',
				'Transaction.created <=' => $date_to . ' 23:59:59',
				'Transaction.deleted' => null,
			)
		));
		if ($transactions) {
			$data = array();
			foreach ($transactions as $transaction) {
				$dt = new DateTime($transaction['Transaction']['created']);
				$date = $dt->format('m/d');
				if (isset($data[$date][$transaction['PaymentMethod']['payment_method']])) {
					$data[$date][$transaction['PaymentMethod']['payment_method']] = $data[$date][$transaction['PaymentMethod']['payment_method']] + (-1 * $transaction['Transaction']['amount']);
				}
				else {
					$data[$date][$transaction['PaymentMethod']['payment_method']] = (-1 * $transaction['Transaction']['amount']);
				}
			}
			
			$this->set('data', $data);
		}
	}
	
	function withdrawals_report() {
		if ($this->request->is('post')) {	
			$errors = array();
			if (!empty($this->request->data['Transaction']['date_to']) && empty($this->request->data['Transaction']['date_from'])) {
				$errors[] = 'Please enter start date';
			}
			elseif (!empty($this->request->data['Transaction']['date_to']) && !empty($this->request->data['Transaction']['date_from'])) {
				if (strtotime($this->request->data['Transaction']['date_from']) > strtotime($this->request->data['Transaction']['date_to'])) {
					$errors[] = 'Start date should be less than end date';
				}
			}
			if (!empty($this->request->data['Transaction']['date_from'])) {
				if (!empty($this->request->data['Transaction']['date_to'])) {
					$conditions['Transaction.executed >='] = date(DB_DATE, strtotime($this->request->data['Transaction']['date_from'])).  ' 00:00:00';
					$conditions['Transaction.executed <='] = date(DB_DATE, strtotime($this->request->data['Transaction']['date_to'])) . ' 23:59:59';
				}
				else {
					$conditions['Transaction.executed >='] = date(DB_DATE, strtotime($this->request->data['Transaction']['date_from'])).  ' 00:00:00';
				}
			}
			$conditions['Transaction.type_id'] = TRANSACTION_WITHDRAWAL;
			$conditions['Transaction.deleted'] = null;
			$transaction = $this->Transaction->find('count', array(
				'conditions' => $conditions			
			));
			if ($transaction <= 0) {
				$errors[] = 'There is no transaction available between the selected date range.';
			}
			if (empty($errors)) {
				$transactionReportSource = $this->TransactionReport->getDataSource();
				$transactionReportSource->begin();
				$this->TransactionReport->create();
				$this->TransactionReport->save(array('TransactionReport' => array(
					'user_id' => $this->current_user['Admin']['id'],
					'date_from' => !empty($this->request->data['Transaction']['date_from']) ? date(DB_DATE, strtotime($this->request->data['Transaction']['date_from'])) . ' 00:00:00' : null,
					'date_to' => !empty($this->request->data['Transaction']['date_to']) ? date(DB_DATE, strtotime($this->request->data['Transaction']['date_to'])) . ' 23:59:59' : null
				)));
				$transaction_report_id = $this->TransactionReport->getInsertId();
				$transactionReportSource->commit();
				$query = ROOT.'/app/Console/cake transactions export_transaction_data '.$transaction_report_id;
				if (!empty($this->request->data['Transaction']['date_from'])) {
					$query .= ' "' . date(DB_DATE, strtotime($this->request->data['Transaction']['date_from'])) . ' 00:00:00' . '"';
				}
				if (!empty($this->request->data['Transaction']['date_to'])) {
					$query .= ' "' . date(DB_DATE, strtotime($this->request->data['Transaction']['date_to'])) . ' 23:59:59' . '"';
				}				
				$query.= "  > /dev/null 2>&1 &"; 
				CakeLog::write('query_commands', $query); 
				exec($query);
				$this->Session->setFlash('Report being generated - please wait for 10-15 minutes to download report.', 'flash_success');
				$this->redirect(array('controller' => 'transactions', 'action' => 'withdrawals_report'));
			}
			else {
				$this->Session->setFlash(implode('<br />', $errors), 'flash_error');
			}
		}
		
		$this->TransactionReport->bindModel(array('belongsTo' => array(
			'Admin' => array(
				'foreignKey' => 'user_id',
				'fields' => array('id', 'admin_user')
			)
		)));
		
		$limit = 50;
		$paginate = array(
			'TransactionReport' => array(			
				'contain' => array(
					'Admin'
				),
				'limit' => $limit,
				'order' => 'TransactionReport.id DESC',
			)
		);
		$this->paginate = $paginate;
		$this->set('transaction_reports', $this->paginate('TransactionReport'));		
		$this->set(compact('transaction_reports'));
	}
	
	public function report_check($id) {
		$report = $this->TransactionReport->findById($id);
    	return new CakeResponse(array(
			'body' => json_encode(array(
				'status' => $report['TransactionReport']['status'],
				'file' => Router::url(array('controller' => 'transactions', 'action' => 'download', $report['TransactionReport']['id']))
			)), 
			'type' => 'json',
			'status' => '201'
		));
	}
	
	function download($transaction_report_id) {
		if(empty($transaction_report_id)) {
			throw new NotFoundException();
		}
		
		$report = $this->TransactionReport->find('first', array(
			'conditions' => array(
				'TransactionReport.id' => $transaction_report_id
			),
			'fields' => array(
				'id', 'status', 'path'
			)
		));
		
		if ($report) {
			if ($report['TransactionReport']['status'] == 'complete') {
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
				
				CakePlugin::load('Uploader');
				App::import('Vendor', 'Uploader.S3');
				
				$file = $report['TransactionReport']['path'];
							
				// we store with first slash; but remove it for S3
				if (substr($file, 0, 1) == '/') {
					$file = substr($file, 1, strlen($file)); 
				}
				
				$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);
				$url = $S3->getAuthenticatedURL($settings['s3.bucket'], $file, 3600, false, false);
				
				$this->redirect($url);
			}
			else {
				$this->Session->setFlash('A report is already being generated - please wait until it is done.', 'flash_error');
				$this->redirect(array(
					'controller' => 'transactions',
					'action' => 'withdrawals_report'
				));
			}
		}
		else {
			throw new NotFoundException();
		}
	}
	
	public function send_tangocard() {
		$this->loadModel('Tangocard');
		$tangocards = $this->Tangocard->find('list', array(
			'fields' => array('Tangocard.sku', 'Tangocard.name'),
			'conditions' => array(
				'Tangocard.sku <>' => '',
				'Tangocard.deleted' => false,
			),
			'order' => 'Tangocard.name asc'
		));
		$this->set(compact('tangocards'));
		if ($this->request->is('post') || $this->request->is('put')) {
			if (empty($this->request->data['Transaction']['tangocard'])) {
				$this->Transaction->validationErrors['tangocard'] = 'Please select a tangocard';
			}
			
			if (empty($this->request->data['Transaction']['email'])) {
				$this->Transaction->validationErrors['email'] = 'Please enter a valid email address';
			}
			
			if (empty($this->request->data['Transaction']['amount'])) {
				$this->Transaction->validationErrors['amount'] = 'Please provide a valid amount value';
			}
			
			if (empty($this->Transaction->validationErrors)) {
				$user = $this->User->find('first', array(
					'conditions' => array(
						'User.email' => $this->request->data['Transaction']['email'],
						'User.active' => true,
						'User.hellbanned' => false
					)
				));
				if (!$user) {
					$this->Transaction->validationErrors['email'] = 'No active user with this email address found';
				}
			}
			
			if (empty($this->Transaction->validationErrors)) {
				$this->Tango = $this->Components->load('Tango');
				if (!$this->Tango->validate_gift_country($this->request->data['Transaction']['tangocard'], $user)) {
					$this->Transaction->validationErrors['tangocard'] = "Tangocard not available in the user's country";
				}
			}
			
			if (empty($this->Transaction->validationErrors)) {
				$tangocard = $this->Tangocard->find('first', array(
					'conditions' => array(
						'Tangocard.sku' => $this->request->data['Transaction']['tangocard'],
						'Tangocard.deleted' => false,
					)
				));
				if (!empty($tangocard['Tangocard']['parent_id'])) {
					// Calculate the fixed price card conversion amount. (we handle the variable cards conversion in cp)
					// Note: We use Parent card conversion value
					$amount = round($tangocard['Tangocard']['value'] / $tangocard['Parent']['conversion']);
				}
				else {
					$amount = $this->request->data['Transaction']['amount'];
					if ($amount < $tangocard['Tangocard']['min_value'] || $amount > $tangocard['Tangocard']['max_value']) {
						$this->Transaction->validationErrors['amount'] = 'Amount should be in the range [' . $tangocard['Tangocard']['min_value']. ' - '. $tangocard['Tangocard']['max_value']. ']' ;
					}
				}
			}
			
			if (empty($this->Transaction->validationErrors)) {
				$this->loadModel('PaymentMethod');
				$this->loadModel('UserLog');
				$transactionSource = $this->Transaction->getDataSource();
				$transactionSource->begin();
				$this->Transaction->create();
				$this->Transaction->save(array('Transaction' => array(
					'type_id' => TRANSACTION_MANUAL_TANGOCARD,
					'linked_to_id' => '0',
					'user_id' => $user['User']['id'],
					'amount' => $amount,
					'paid' => false,
					'name' => $this->request->data['Transaction']['description'],
					'status' => TRANSACTION_PENDING,
					'executed' => date(DB_DATETIME)
				)));
				$transaction_id = $this->Transaction->getInsertId();
				$transaction = $this->Transaction->findById($transaction_id);
				$this->Transaction->approve($transaction);
				$transactionSource->commit();
				
				// add payment method
				$payment_method = $this->PaymentMethod->find('first', array(
					'conditions' => array(
						'PaymentMethod.user_id' => $user['User']['id'],
						'PaymentMethod.status' => DB_ACTIVE
					)
				));
				if ($payment_method) {
					$this->PaymentMethod->create();
					$this->PaymentMethod->save(array('PaymentMethod' => array(
						'id' => $payment_method['PaymentMethod']['id'],
						'status' => DB_DEACTIVE
					)), true, array('status'));
				}
				$paymentMethodSource = $this->PaymentMethod->getDataSource();
				$paymentMethodSource->begin();
				$this->PaymentMethod->create();
				$this->PaymentMethod->save(array('PaymentMethod' => array(
					'payment_method' => 'tango',
					'user_id' => $user['User']['id'],
					'value' => $tangocard['Tangocard']['name'],
					'payment_id' => $tangocard['Tangocard']['sku'],
					'status' => DB_ACTIVE
				)));
				$payment_method_id = $this->PaymentMethod->getInsertId();
				$payment_method = $this->PaymentMethod->findById($payment_method_id);
				$paymentMethodSource->commit();
				
				$this->Transaction->create();
				$this->Transaction->save(array('Transaction' => array(
					'amount' => $amount * -1,
					'user_id' => $user['User']['id'],
					'linked_to_id' => $payment_method_id,
					'linked_to_name' => $payment_method['PaymentMethod']['payment_method'],
					'paid' => true,
					'name' => 'Tangocard sent by MintVine',
					'status' => TRANSACTION_PENDING,
					'executed' => date(DB_DATETIME),
					'type_id' => TRANSACTION_WITHDRAWAL
				)));
				$this->UserLog->create();
				$this->UserLog->save(array('UserLog' => array(
					'user_id' => $user['User']['id'],
					'type' => 'transaction.created',
					'transaction_id' => $this->Transaction->getLastInsertID()
				)));
				$transactionSource->commit();
				
				$this->Session->setFlash(__('Withdrawal transaction has been created successfully, and will be processed as soon as approved.'), 'flash_success');
				$this->redirect(array('controller' => 'transactions', 'action' => 'index', 'action' => 'index', '?' => array('type' => 4, 'paid' => 0)));
			}
			
		}
	}
	
	function check_master_dwolla_account() {
		$errors = null;
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array(
					'dwolla_v2.master.access_token', 
					'dwolla_v2.master.account_id', 
					'dwolla_v2.api_url',
					'dwolla_v2.minimum.balance'
				),
				'Setting.deleted' => false
			)
		));
		
		if (count($settings) < 4) {
			$errors = 'Your MVPay withdrawals will not process due to the missing dwolla API settings for MVPay.';
			return $errors;
		}
		
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
			
			// get master dwolla funding source balance
			$funding_source_balance = 0;
			$source_balance = $fundingSourcesApi->getBalance($settings['dwolla_v2.api_url'] . 'funding-sources/' . $master_funding_source_id); 
			
			if (isset($source_balance->balance->{'value'})) {
				$funding_source_balance = $source_balance->balance->{'value'};
			}
			
			if ($funding_source_balance <= $settings['dwolla_v2.minimum.balance']) {
				$errors = 'Your MVPay withdrawals will not process due to the low dwolla balance.';
			}
		}
		catch (Exception $e) {
			$errors = json_decode($e->getResponseBody());
			if (isset($errors->message)) {
				$errors = 'Your MVPay withdrawals will not process due to the following Dwolla API error : "' . $errors->message .'"';
			}
		}
		
		return $errors;
	}
}
