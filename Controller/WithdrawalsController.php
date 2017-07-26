<?php
App::uses('AppController', 'Controller');
App::import('Lib', 'MintVineUser');

class WithdrawalsController extends AppController {

	public $uses = array('Withdrawal', 'Transaction', 'SurveyUserVisit', 'SurveyVisit', 'Project', 'VirtualMassAdd', 'SurveyVisit', 'Setting', 'TransactionReport');
	public $helpers = array('Html', 'Time', 'Calculator');
	public $components = array();
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		/* -- SQS setting */
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
		/* SQS setting -- */

		/* -- bottom button handlers (via POST) */
		if ($this->request->is('post')) {
			$rejected = $approved = $deleted = 0;
			$deleted_user_id = array();
			$rejected_withdrawal_id = array();

			if (isset($this->data['Withdrawal']) && !empty($this->data['Withdrawal'])) {
				foreach ($this->data['Withdrawal'] as $id => $value) {
					if ($value == 0 || $id == 'null') {
						continue;
					}

					// load up withdrawal
					$withdrawal = $this->Withdrawal->findById($id);
					
					if (isset($this->data['approve'])) {
						$new_withdrawal_id = $this->Withdrawal->approve($withdrawal);

						// add item into sqs
						$response = $sqs->sendMessage($settings['sqs.payout.queue'], $new_withdrawal_id);
						$approved++;
					}

					if (isset($this->data['reject'])) {
						$rejected_withdrawal_id[] = $withdrawal['Withdrawal']['id'];
						$this->Withdrawal->reject($withdrawal);
						$rejected++;
					}

					if (isset($this->data['hellban'])) {
						$this->Withdrawal->reject($withdrawal);
						MintVineUser::hellban($withdrawal['User']['id'], 'auto: Hellbanned from withdrawals screen.', $this->current_user['Admin']['id']);
						$rejected++;
					}

					if (isset($this->data['delete'])) {
						if ($withdrawal) {
							$deleted_user_id[] = $withdrawal['Withdrawal']['user_id'];
							$this->Withdrawal->create();
							$this->Withdrawal->delete($id);
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

			/* -- form result messages */
			if ($rejected > 0 || $approved > 0 || $deleted > 0) {
				$msgs = array();
				if ($rejected > 0) {
					$msgs[] = 'rejected '.$rejected.' withdrawals'; 
				}
				if ($deleted > 0) {
					$msgs[] = ' deleted '.$deleted.' withdrawals';
				}
				if ($approved > 0) {
					$msgs[] = ' approved '.$approved.' withdrawals';
				}
				$this->Session->setFlash('You have '.implode(' and ', $msgs).'.', 'flash_success');
				
				if (isset($this->request->query) && !empty($this->request->query)) {
					$return_url = array('action' => 'index', '?' => $this->request->query);
				}
				else {
					$return_url = array('action' => 'index');
				}

				if ($rejected > 0) {
					$this->redirect(array('action' => 'rejected', '?' => array('id' => implode(',', $rejected_withdrawal_id))));
				}

				$this->redirect($return_url);
			}
			/* form result messages -- */
		}
		/* bottom button handlers -- */

		/* -- filtering withdrawals based on query params */
		$conditions = array();
		$conditions['Withdrawal.deleted'] = null;

		// get valid user value
		if (isset($this->request->query['user'])) {
			$this->request->query['user'] = urldecode($this->request->query['user']);
		}
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		if (isset($this->data) && !isset($this->data['error'])) {
			if (isset($this->data['status']) && $this->data['status'] != '') {
				$conditions['Withdrawal.status'] = $this->data['status'];
			}

			if (isset($this->data['payment_method']) && $this->data['payment_method'] != '') {
				$conditions['Withdrawal.payment_type'] = $this->data['payment_method'];
			}

			if (isset($this->data['user']) && !empty($this->data['user'])) {
				if ($this->data['user']{0} == '#') {
					$user = $this->User->fromId(substr($this->data['user'], 1));
				}
				else {
					$user = $this->User->fromEmail($this->data['user']);
				}
				if ($user) {
					$conditions['Withdrawal.user_id'] = $user['User']['id']; 
				}
				else {
					$conditions['Withdrawal.user_id'] = '0'; 
				}

				$this->set('user', $user);
			}
			
			if (isset($this->data['amount_from']) && $this->data['amount_from'] != '') {
				if (isset($this->data['amount_to']) && $this->data['amount_to'] != '') {
					$conditions['ABS(Withdrawal.amount_cents) >='] = $this->data['amount_from'] * 100;
					$conditions['ABS(Withdrawal.amount_cents) <='] = $this->data['amount_to'] * 100;
				}
				else {
					$conditions['ABS(Withdrawal.amount_cents) >='] = $this->data['amount_from'] * 100;
				}
			}
			
			if (isset($this->data['date_from']) && !empty($this->data['date_from'])) {
				if (isset($this->data['date_to']) && !empty($this->data['date_to'])) {
					$conditions['Withdrawal.processed >='] = date(DB_DATE, strtotime($this->data['date_from'])).' 00:00:00';
					$conditions['Withdrawal.processed <='] = date(DB_DATE, strtotime($this->data['date_to'])).' 23:59:59';
				}
				else {
					$conditions['Withdrawal.processed >='] = date(DB_DATE, strtotime($this->data['date_from'])).' 00:00:00';
					$conditions['Withdrawal.processed <='] = date(DB_DATE, strtotime($this->data['date_from'])).' 23:59:59';
				}
			}
		}
		/* filtering withdrawals based on query params -- */

		/* -- binding models */
		$this->Withdrawal->bindModel(array(
			'hasMany' => array(
				'UserAnalysis' => array(
					'foreignKey' => 'transaction_id',
					'order' => 'UserAnalysis.id DESC'
				)
			)
		));
		$this->Withdrawal->bindItems(false);
		/* binding models -- */

		/* -- finding withdrawals */
		$paginate = array(
			'Withdrawal' => array(
				'limit' => 50,
				'order' => 'Withdrawal.id DESC',
				'conditions' => $conditions,
				'contain' => array(
								'User',
								'UserAnalysis',
								'PaymentMethod'
							)
			)
		);
		$this->paginate = $paginate;
		$withdrawals = $this->paginate();
		/* finding withdrawals -- */

		if (isset($user) && !empty($user)) {
			$title_for_layout = sprintf('Withdrawals - %s', $user['User']['email']);
		}
		else {
			$title_for_layout = 'Withdrawals';
		}
		
		/* -- sum amount */
		$sums = $this->Withdrawal->find('first', array(
			'conditions' => $conditions,
			'fields' => array('SUM(Withdrawal.amount_cents) as sum_amount'),
		));
		$this->set(compact('sums'));
		/* sum amount -- */
		
		$this->set(compact('withdrawals', 'title_for_layout'));
	}

	public function results() {
		$this->Withdrawal->bindModel(array(
			'belongsTo' => array(
				'User'
			),
			'hasOne' => array(
				'PaymentLog' => array(
					'foreignKey' => 'transaction_id'
				)
			)
		), false);

		if (isset($this->request->query['q']) && !empty($this->request->query['q'])) {
			$query = $this->request->query['q'];
			$conditions['OR'] = array(
				'Withdrawal.payment_identifier LIKE' => '%'.$query.'%',
				'User.email LIKE' => '%'.$query.'%'
			);
			$this->set('type', null);
		}

		if (isset($this->request->query['method']) && !empty($this->request->query['method'])) {
			if ($this->request->query['method'] == 'paypal') {
				$conditions['Withdrawal.payment_type'] = 'paypal';
			}
			elseif ($this->request->query['method'] == 'dwolla') {
				$conditions['Withdrawal.payment_type'] = 'dwolla';
			}
			elseif ($this->request->query['method'] == 'giftbit') {
				$conditions['Withdrawal.payment_type'] = 'gift';
			}
			elseif ($this->request->query['method'] == 'tango') {
				$conditions['Withdrawal.payment_type'] = 'tango';
			}
		}

		if (!isset($this->request->query['type']) || $this->request->query['type'] == 'failed') {
			$conditions['Withdrawal.status'] = WITHDRAWAL_PAYOUT_FAILED;
			$this->set('type', 'failed');
		}
		elseif ($this->request->query['type'] == 'succeeded') {
			$conditions['Withdrawal.status'] = WITHDRAWAL_PAYOUT_SUCCEEDED;
			$this->set('type', 'succeeded');
		}
		elseif ($this->request->query['type'] == 'unprocessed') {
			$conditions['Withdrawal.status'] = WITHDRAWAL_PAYOUT_UNPROCESSED;
			$this->set('type', 'unprocessed');
		}
		elseif ($this->request->query['type'] == 'all') {
			$this->set('type', 'all');
		}
		
		$conditions['Withdrawal.deleted'] = null;
		$paginate = array(
			'Withdrawal' => array(
				'limit' => 100,
				'contain' => array(
					'User',
					'PaymentLog' => array(
						'foreignKey' => 'transaction_id'
					)
				),
				'order' => 'Withdrawal.processed DESC',
			)
		);

		if (!empty($conditions)) {
			$paginate['Withdrawal']['conditions'] = $conditions;
		}
		$this->paginate = $paginate;
		$withdrawals = $this->paginate(); 

		$payment_methods = array(
			'paypal' => 'PayPal',
			'dwolla' => 'Dwolla',
			'giftbit' => 'Giftbit',
			'tango' => 'Tangocard'
		);
		$this->set(compact('withdrawals', 'payment_methods'));
	}

	public function withdrawal($withdrawal_id = null) {
		if ($this->request->is('post')) {
			$withdrawal = $this->Withdrawal->findById($withdrawal_id); 
			if ($this->request->data['type'] == 'mark') {
				$this->Withdrawal->create();
				$this->Withdrawal->save(array('Withdrawal' => array(
					'id' => $withdrawal['Withdrawal']['id'],
					'status' => WITHDRAWAL_PAYOUT_SUCCEEDED
				)), true, array('status'));

    			return new CakeResponse(array(
					'body' => json_encode(array(
					)), 
					'type' => 'json',
					'status' => '201'
				));
			}
			elseif ($this->request->data['type'] == 'delete') {
				$this->Withdrawal->create();
				$this->Withdrawal->delete($withdrawal['Withdrawal']['id']);

				return new CakeResponse(array(
					'body' => json_encode(array(
						'status' => '1'
					)), 
					'type' => 'json',
					'status' => '201'
				));
			}
		}
	}

	public function requested() {
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

		$withdrawals = $this->Withdrawal->find('all', array(
			'conditions' => array(
				'Withdrawal.payment_type' => array('paypal', 'tango', 'dwolla'),
				'Withdrawal.created >=' => $date_from. ' 00:00:00',
				'Withdrawal.created <=' => $date_to . ' 23:59:59',
				'Withdrawal.deleted' => null,
			)
		));
		if ($withdrawals) {
			$data = array();
			foreach ($withdrawals as $withdrawal) {
				$dt = new DateTime($withdrawal['Withdrawal']['created']);
				$date = $dt->format('m/d');
				if (isset($data[$date][$withdrawal['Withdrawal']['payment_type']])) {
					$data[$date][$withdrawal['Withdrawal']['payment_type']] = $data[$date][$withdrawal['Withdrawal']['payment_type']] + (-1 * $withdrawal['Withdrawal']['amount_cents']);
				}
				else {
					$data[$date][$withdrawal['Withdrawal']['payment_type']] = (-1 * $withdrawal['Withdrawal']['amount_cents']);
				}
			}
			
			$this->set('data', $data);
		}
	}

	function report() {
		if ($this->request->is('post')) {	
			$errors = array();
			if (!empty($this->request->data['Withdrawal']['date_to']) && empty($this->request->data['Withdrawal']['date_from'])) {
				$errors[] = 'Please enter start date';
			}
			elseif (!empty($this->request->data['Withdrawal']['date_to']) && !empty($this->request->data['Withdrawal']['date_from'])) {
				if (strtotime($this->request->data['Withdrawal']['date_from']) > strtotime($this->request->data['Withdrawal']['date_to'])) {
					$errors[] = 'Start date should be less than end date';
				}
			}
			if (!empty($this->request->data['Withdrawal']['date_from'])) {
				if (!empty($this->request->data['Withdrawal']['date_to'])) {
					$conditions['Withdrawal.processed >='] = date(DB_DATE, strtotime($this->request->data['Withdrawal']['date_from'])).  ' 00:00:00';
					$conditions['Withdrawal.processed <='] = date(DB_DATE, strtotime($this->request->data['Withdrawal']['date_to'])) . ' 23:59:59';
				}
				else {
					$conditions['Withdrawal.processed >='] = date(DB_DATE, strtotime($this->request->data['Withdrawal']['date_from'])).  ' 00:00:00';
				}
			}
			$conditions['Withdrawal.deleted'] = null;
			$withdrawal = $this->Withdrawal->find('count', array(
				'conditions' => $conditions			
			));
			if ($withdrawal <= 0) {
				$errors[] = 'There is no withdrawal available between the selected date range.';
			}

			if (empty($errors)) {
				/* -- getting new report id */

				$transactionReportSource = $this->TransactionReport->getDataSource();
				$transactionReportSource->begin();
				$this->TransactionReport->create(false);
				$this->TransactionReport->save(array('TransactionReport' => array(
					'user_id' => $this->current_user['Admin']['id'],
					'report_type' => 0,
					'date_from' => !empty($this->request->data['Withdrawal']['date_from']) ? date(DB_DATE, strtotime($this->request->data['Withdrawal']['date_from'])) . ' 00:00:00' : null,
					'date_to' => !empty($this->request->data['Withdrawal']['date_to']) ? date(DB_DATE, strtotime($this->request->data['Withdrawal']['date_to'])) . ' 23:59:59' : null
				)));
				$withdrawal_report_id = $this->TransactionReport->getInsertId();
				$transactionReportSource->commit();

				/* getting new report id -- */

				/* -- fabricating bash query for running withdrawal report generation command */
				$query = ROOT . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Console' . DIRECTORY_SEPARATOR . 'cake withdrawals export_withdrawal_data ' . $withdrawal_report_id;
				if (!empty($this->request->data['Withdrawal']['date_from'])) {
					$query .= ' "' . date(DB_DATE, strtotime($this->request->data['Withdrawal']['date_from'])) . ' 00:00:00' . '"';
				}
				if (!empty($this->request->data['Withdrawal']['date_to'])) {
					$query .= ' "' . date(DB_DATE, strtotime($this->request->data['Withdrawal']['date_to'])) . ' 23:59:59' . '"';
				}				
				$query.= " > /dev/null 2>&1 &";

				CakeLog::write('query_commands', $query); 
				exec($query);
				/* fabricating bash query for running withdrawal report generation command -- */

				$this->Session->setFlash('Report being generated - please wait for 10-15 minutes to download report.', 'flash_success');
				$this->redirect(array('controller' => 'withdrawals', 'action' => 'report'));
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
				'conditions' => array(
					'TransactionReport.report_type' => 0 // withdrawal report only
				),
				'contain' => array(
					'Admin'
				),
				'limit' => $limit,
				'order' => 'TransactionReport.id DESC',
			)
		);
		$this->paginate = $paginate;
		$this->set('withdrawal_reports', $this->paginate('TransactionReport'));		
		$this->set(compact('withdrawal_reports'));
	}

	public function report_check($withdrawal_report_id) {
		$report = $this->TransactionReport->findById($withdrawal_report_id);
    	return new CakeResponse(array(
			'body' => json_encode(array(
				'status' => $report['TransactionReport']['status'],
				'file' => Router::url(array('controller' => 'withdrawals', 'action' => 'download', $report['TransactionReport']['id']))
			)), 
			'type' => 'json',
			'status' => '201'
		));
	}

	function download($withdrawal_report_id) {
		if (empty($withdrawal_report_id)) {
			throw new NotFoundException();
		}
		
		$report = $this->TransactionReport->find('first', array(
			'conditions' => array(
				'TransactionReport.id' => $withdrawal_report_id
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

	public function send_manual_payout() {
		$this->loadModel('Tangocard');

		if ($this->request->is('post') || $this->request->is('put')) {
			$this->loadModel('PaymentMethod');

			/* -- empty validation check */
			if (empty($this->request->data['Withdrawal']['payment_method'])) {
				$this->Withdrawal->validationErrors['payment_method'] = 'Please select a payment method';
			}
			
			if (empty($this->request->data['Withdrawal']['email'])) {
				$this->Withdrawal->validationErrors['email'] = 'Please enter a valid email address';
			}
			
			if (empty($this->request->data['Withdrawal']['amount'])) {
				$this->Withdrawal->validationErrors['amount'] = 'Please provide a valid amount value';
			}
			else {
				$amount = $this->request->data['Withdrawal']['amount'];
			}

			if ($this->request->data['Withdrawal']['payment_method'] == 'tango' && empty($this->request->data['Withdrawal']['tangocard'])) {
				$this->Withdrawal->validationErrors['tangocard'] = 'Please select a tangocard';
			}
			/* -- empty validation check */

			/* -- email exists? */
			if (empty($this->Withdrawal->validationErrors)) {
				$user = $this->User->find('first', array(
					'conditions' => array(
						'User.email' => $this->request->data['Withdrawal']['email'],
						'User.active' => true,
						'User.hellbanned' => false
					)
				));
				if (!$user) {
					$this->Withdrawal->validationErrors['email'] = 'No active user with this email address found';
				}
			}
			/* email exists? -- */

			/* -- payment method validation */
			if (empty($this->Withdrawal->validationErrors) && $this->request->data['Withdrawal']['payment_method'] != 'tango') {
				$payment_method = $this->PaymentMethod->find('first', array(
					'conditions' => array(
						'user_id' => $user['User']['id'],
						'payment_method' => $this->request->data['Withdrawal']['payment_method']
					)
				));

				if (!$payment_method) {
					$this->Withdrawal->validationErrors['payment_method'] = 'Not available for the user';
				}
			}
			
			if (empty($this->Withdrawal->validationErrors) && $this->request->data['Withdrawal']['payment_method'] == 'tango') {
				$this->Tango = $this->Components->load('Tango');
				if (!$this->Tango->validate_gift_country($this->request->data['Withdrawal']['tangocard'], $user)) {
					$this->Withdrawal->validationErrors['tangocard'] = "Tangocard not available in the user's country";
				}
			}

			if (empty($this->Withdrawal->validationErrors) && $this->request->data['Withdrawal']['payment_method'] == 'tango') {
				$tangocard = $this->Tangocard->find('first', array(
					'conditions' => array(
						'Tangocard.sku' => $this->request->data['Withdrawal']['tangocard'],
						'Tangocard.deleted' => false,
					)
				));
				if (!empty($tangocard['Tangocard']['parent_id'])) {
					// Calculate the fixed price card conversion amount. (we handle the variable cards conversion in cp)
					// Note: We use Parent card conversion value
					$amount = round($tangocard['Tangocard']['value'] / $tangocard['Parent']['conversion']);
				}
				else {
					if ($amount < $tangocard['Tangocard']['min_value'] || $amount > $tangocard['Tangocard']['max_value']) {
						$this->Withdrawal->validationErrors['amount'] = 'Amount should be in the range [' . $tangocard['Tangocard']['min_value']. ' - '. $tangocard['Tangocard']['max_value']. ']' ;
					}
				}
			}
			/* payment method validation -- */

			if (empty($this->Withdrawal->validationErrors)) {
				if ($this->request->data['Withdrawal']['payment_method'] == 'tango') {
					// manually send payout via tango card
					$this->loadModel('PaymentMethod');
					$this->loadModel('UserLog');

					/* -- add new active tango payment method */
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
					$tango_payment_method_id = $this->PaymentMethod->getInsertId();
					$tango_payment_method = $this->PaymentMethod->findById($tango_payment_method_id);
					$paymentMethodSource->commit();
					/* add new active tango payment method -- */

					/* -- 1st transaction record */
					$transactionSource = $this->Transaction->getDataSource();
					$transactionSource->begin();
					$this->Transaction->create();
					$this->Transaction->save(array('Transaction' => array(
						'type_id' => TRANSACTION_MANUAL_TANGOCARD,
						'user_id' => $user['User']['id'],
						'amount' => $amount,
						'linked_to_id' => '0',
						'name' => $this->request->data['Withdrawal']['description'],
						'description' => $this->request->data['Withdrawal']['description'],
						'paid' => false,
						'status' => TRANSACTION_PENDING,
						'executed' => date(DB_DATETIME)
					)));
					$transaction_id = $this->Transaction->getInsertId();
					$transaction = $this->Transaction->findById($transaction_id);
					$this->Transaction->approve($transaction);
					$transactionSource->commit();
					/* 1st transaction record -- */

					/* -- 2nd transaction record */
					$this->Transaction->create();
					$this->Transaction->save(array('Transaction' => array(
						'type_id' => TRANSACTION_WITHDRAWAL,
						'user_id' => $user['User']['id'],
						'linked_to_id' => $tango_payment_method_id,
						'linked_to_name' => $tango_payment_method['PaymentMethod']['payment_method'],
						'amount' => $amount * -1,
						'name' => 'Tangocard sent by MintVine',
						'description' => 'Tangocard sent by MintVine',
						'paid' => true,
						'status' => TRANSACTION_PENDING,
						'executed' => date(DB_DATETIME)						
					)));
					/* 2nd transaction record -- */

					$this->Withdrawal->create();
					$this->Withdrawal->save(array('Withdrawal' => array(
						'user_id' => $user['User']['id'],
						'transaction_id' => $this->Transaction->getLastInsertID(),
						'payment_type' => $tango_payment_method['PaymentMethod']['payment_method'],
						'payment_identifier' => $tango_payment_method_id,
						'amount_cents' => $amount * -1,
						'paid_amount_cents' => null,
						'status' => WITHDRAWAL_PENDING,
						'note' => 'Tangocard sent by MintVine'
					)));

					$this->UserLog->create();
					$this->UserLog->save(array('UserLog' => array(
						'user_id' => $user['User']['id'],
						'type' => 'transaction.created',
						'transaction_id' => $this->Transaction->getLastInsertID()
					)));
				}
				else {
					// manually send payout via paypal/dwolla
					$this->loadModel('UserLog');

					/* -- activate payment method */
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

					$payment_method = $this->PaymentMethod->find('first', array(
						'conditions' => array(
							'user_id' => $user['User']['id'],
							'payment_method' => $this->request->data['Withdrawal']['payment_method']
						)
					));
					$this->PaymentMethod->create();
					$this->PaymentMethod->save(array('PaymentMethod' => array(
						'id' => $payment_method['PaymentMethod']['id'],
						'status' => DB_ACTIVE
					)), true, array('status'));
					/* activate payment method -- */

					$transactionSource = $this->Transaction->getDataSource();
					$transactionSource->begin();
					$transaction_type = array('paypal' => TRANSACTION_MANUAL_PAYPAL, 'dwolla' => TRANSACTION_MANUAL_DWOLLA);
					$this->Transaction->create();
					$this->Transaction->save(array('Transaction' => array(
						'type_id' => $transaction_type[$this->request->data['Withdrawal']['payment_method']],
						'user_id' => $user['User']['id'],
						'amount' => $amount,
						'linked_to_id' => '0',
						'name' => $this->request->data['Withdrawal']['description'],
						'description' => $this->request->data['Withdrawal']['description'],
						'paid' => false,
						'status' => TRANSACTION_PENDING,
						'executed' => date(DB_DATETIME)
					)));
					$transaction_id = $this->Transaction->getInsertId();
					$transaction = $this->Transaction->findById($transaction_id);
					$this->Transaction->approve($transaction);
					$transactionSource->commit();

					$this->Withdrawal->create();
					$this->Withdrawal->save(array('Withdrawal' => array(
						'user_id' => $user['User']['id'],
						'transaction_id' => $transaction_id,
						'payment_type' => $payment_method['PaymentMethod']['payment_method'],
						'payment_identifier' => $payment_method['PaymentMethod']['payment_id'],
						'amount_cents' => $amount * -1,
						'paid_amount_cents' => null,
						'status' => WITHDRAWAL_PENDING,
						'note' => 'Manual payout sent by MintVine'
					)));

					$this->UserLog->create();
					$this->UserLog->save(array('UserLog' => array(
						'user_id' => $user['User']['id'],
						'type' => 'transaction.created',
						'transaction_id' => $transaction_id
					)));
				}

				$this->Session->setFlash(__('Withdrawal transaction has been created successfully, and will be processed as soon as approved.'), 'flash_success');
				$this->redirect(array('controller' => 'withdrawals', 'action' => 'index'));
			}
		}

		$payment_methods = array(
			'paypal' => 'PayPal',
			'dwolla' => 'Dwolla',
			'tango' => 'Tango'
		);
		$tangocards = $this->Tangocard->find('list', array(
			'fields' => array('Tangocard.sku', 'Tangocard.name'),
			'conditions' => array(
				'Tangocard.sku <>' => '',
				'Tangocard.deleted' => false,
			),
			'order' => 'Tangocard.name asc'
		));

		$this->set(compact('payment_methods', 'tangocards'));
	}

	public function rejected() {
		$limit = 50;
		
		if ($this->request->is('post')) {
			$notes = $this->request->data['note'];
			if ($notes) {
				foreach ($notes as $id => $note) {
					$this->Withdrawal->create();
					$this->Withdrawal->save(array('Withdrawal' => array(
            			'id' => $id,
            			'note' => $note
            		)), true, array('note'));
				}
				$this->Session->setFlash(__('Withdrawal notes have been updated.'), 'flash_success');
			}
		}

		$conditions = array();
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		if (isset($this->data)) {
			if (isset($this->data['id']) && !empty($this->data['id'])) {
				if (strpos($this->data['id'], ',') !== false) {
					$conditions['Withdrawal.id'] = explode(',', $this->data['id']);
				}
				else {
					$conditions['Withdrawal.id'] = $this->data['id'];
				}
			}
		}
		$conditions['Withdrawal.status'] = WITHDRAWAL_REJECTED;
		$conditions['Withdrawal.deleted'] = null;
		$paginate = array(
			'Withdrawal' => array(
				'limit' => $limit,
				'order' => 'Withdrawal.id DESC',
			)
		);
		if (!empty($conditions)) {
			$paginate['Withdrawal']['conditions'] = $conditions;
		}
		$this->paginate = $paginate;
		$this->set('withdrawals', $this->paginate('Withdrawal'));
	}
}
