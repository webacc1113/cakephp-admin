<?php
App::uses('AppController', 'Controller');

class CodesController extends AppController {
	public $uses = array('Code', 'CodeRedemption', 'User');
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array();

	public function beforeFilter() {
		parent::beforeFilter();
	}

	public function index() {
		if ($this->request->is('post') && isset($this->data['Code']) && isset($this->data['delete'])) {
			$deleted = 0;
			foreach ($this->data['Code'] as $id => $value) {
				if ($value == 0 || $id == 'null') {
					continue;
				}

				$this->Code->delete($id);
				$deleted++;
			}
			
			if ($deleted > 0) {
				$this->Session->setFlash('You have deleted ' . $deleted . ' Codes' . '.', 'flash_success');
				$this->redirect(array('action' => 'index'));
			}
		}
		
		$limit = 50;
		$this->Code->bindModel(array(
			'hasMany' => array(
				'CodeRedemption' => array(
					'className' => 'CodeRedemption',
					'foreignKey' => 'code_id'
				)
			)
		));
		$conditions = array();
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		
		$paginate = array(
			'Code' => array(
				'limit' => $limit,
				'order' => 'Code.id DESC',
				'contain' => array(
					'CodeRedemption' => array('fields' => array('code_id'))
				)
			)
		);
		if (!empty($conditions)) {
			$paginate['Code']['conditions'] = $conditions;
		}
		$this->paginate = $paginate;
		$codes = $this->paginate('Code');
		$this->set('codes', $codes);
	}

	public function add() {
		if ($this->request->is('post')) {
			$this->Code->create();
			$data = $this->request->data;
			$data['Code']['expiration'] = sprintf('%s-%s-%s %s:%s', 
				$data['Code']['expiration']['year'],
				$data['Code']['expiration']['month'],
				$data['Code']['expiration']['day'],
				$data['Code']['expiration']['hour'],
				$data['Code']['expiration']['min']
			);
			
			if (empty($this->current_user['Admin']['timezone'])) {
				$this->current_user['Admin']['timezone'] = 'America/Los_Angeles';
			}
				
			$data['Code']['expiration'] = Utils::change_tz_to_utc($data['Code']['expiration'], DB_DATETIME, $this->current_user['Admin']['timezone']);
			$saved = $this->Code->save($data, true, array('code', 'description', 'amount', 'quota', 'expiration'));
			if ($saved) {
				$this->Session->setFlash(__('Promotional code has been saved.'), 'flash_success');
				$this->redirect(array('action' => 'index'));
			}
			$this->Session->setFlash(__('Unable to add the promotional code.'), 'flash_error');
		}
	}

	public function edit($id) {
		$code = $this->Code->find('first', array(
			'conditions' => array(
				'Code.id' => $id,
			),
		));
		if (!$code) {
			throw new NotFoundException(__('Invalid code'));
		}
		if ($this->request->is('post') || $this->request->is('put')) {
			$keys = array_keys($this->request->data['Code']);
			$data = $this->request->data;
			$data['Code']['expiration'] = sprintf('%s-%s-%s %s:%s', 
				$data['Code']['expiration']['year'],
				$data['Code']['expiration']['month'],
				$data['Code']['expiration']['day'],
				$data['Code']['expiration']['hour'],
				$data['Code']['expiration']['min']);
			$data['Code']['expiration'] = Utils::change_tz_to_utc($data['Code']['expiration'], DB_DATETIME, $this->current_user['Admin']['timezone']);
			$saved = $this->Code->save($data, true, array('code', 'description', 'quota', 'expiration', 'amount'));
			if ($saved) {
				$this->Session->setFlash(__('Promotional code has been updated.'), 'flash_success');
				$this->redirect(array('action' => 'index'));
			}
			$this->Session->setFlash(__('Unable to update the promotional code.'), 'flash_error');
		}
		if (!$this->request->data) {
			if (!empty($code['Code']['expiration'])) {
				$code['Code']['expiration'] = Utils::change_tz_from_utc($code['Code']['expiration'], DB_DATETIME);
			}
			$this->request->data = $code;
		}
	}

	public function active() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$id = $this->request->data['id'];
			$code = $this->Code->find('first', array(
				'conditions' => array(
					'Code.id' => $id,
				),
			));
			$active = ($code['Code']['active']) ? 0 : 1;
			$this->Code->save(array('Code' => array(
				'id' => $id,
				'active' => $active,
			)), true, array('active'));
			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => $active
				)),
				'type' => 'json',
				'status' => '201'
			));
		}
	}

	public function autocode() {
		return new CakeResponse(array(
			'body' => json_encode(array(
				'code' => strtoupper(Utils::rand())
			)),
			'type' => 'json',
			'status' => '201'
		));
	}

	public function redeem_user($userId = '') {
		if ($this->request->is('post')) {
			App::import('Model', 'Transaction');
			$this->Transaction = new Transaction;

			$data = $this->request->data['CodeRedemption'];
			$codeId = $data['code_id'];
			$userId = $data['user_id'];
			$code = $this->Code->find('first', array(
				'conditions' => array(
					'Code.id' => $codeId,
				),
			));
			$user = $this->User->find('first', array(
				'conditions' => array(
					'User.id' => $userId,
				),
				'recursive' => -1
			));
			if (empty($code)) {
				$this->Session->setFlash(__('Please choose a code.'), 'flash_error');
				$this->redirect($this->here);
			}

			$redeemCount = $this->CodeRedemption->find('count', array(
				'conditions' => array(
					'CodeRedemption.code_id' => $codeId
				),
			));
			$notExpired = strtotime($code['Code']['expiration']) > time();
			$underQuota = empty($code['Code']['quota']) || $code['Code']['quota'] > $redeemCount;
			$redeemed = $this->CodeRedemption->find('count', array(
				'conditions' => array(
					'code_id' => $codeId,
					'user_id' => $userId
				),
			));

			$allowException = true;
			if (($allowException || ($notExpired && $underQuota)) && !$redeemed) {
				$amount = $code['Code']['amount'];
				$this->CodeRedemption->create();

				$redemptionSaved = $this->CodeRedemption->save(array('CodeRedemption' => $data
				), true, array('code_id', 'user_id', 'paid'));
				
				$transactionSource = $this->Transaction->getDataSource();
				$transactionSource->begin();
				$this->Transaction->create();
				$transactionSaved = $this->Transaction->save(array('Transaction' => array(
					'type_id' => TRANSACTION_CODE,
					'linked_to_id' => $code['Code']['id'],
					'linked_to_name' => $code['Code']['code'],
					'user_id' => $user['User']['id'],
					'amount' => $amount,
					'paid' => false,
					'name' => 'Points for redeem a promotional code '.$code['Code']['code'],
					'status' => TRANSACTION_PENDING,
					'executed' => date(DB_DATETIME)
				)), true, array('type_id', 'linked_to_id', 'user_id', 'amount', 'paid', 'name', 'status', 'executed'));
				$transaction_id = $this->Transaction->getInsertId();
				$transaction = $this->Transaction->findById($transaction_id);
				$this->Transaction->approve($transaction);
				$transactionSource->commit();

				if ($redemptionSaved && $transactionSaved) {
					$this->Session->setFlash(__('Reemed successfully.'), 'flash_success');
				}
				else {
					$this->Session->setFlash(__('Could not redeem.'), 'flash_error');
				}
				$this->redirect($this->here);
			}

			else {
				if (!$notExpired) {
					$errorMessage = 'The code is expired';
				}

				else if (!$underQuota) {
					$errorMessage = 'Over quota';
				}

				else if ($redeemed) {
					$errorMessage = 'Already redeemed';
				}

				$this->Session->setFlash(__('Could not redeem: ' . $errorMessage), 'flash_error');
				$this->redirect($this->here);
			}
		}

		$user = $this->User->find('first', array(
			'conditions' => array(
				'User.id' => $userId
			),
		));
		if (empty($user)) {
			$this->Session->setFlash(__('Unable to find user to redeem a code.'), 'flash_error');
			$this->redirect(array('action' => 'redeem'));
		}
		$this->Code->bindModel(array(
			'hasMany' => array(
				'CodeRedemption' => array(
					'className' => 'CodeRedemption',
					'foreignKey' => 'code_id'
				)
			)
		));
		// get all codes with no exception (over quota, expired)
		$codes = $this->Code->find('all', array(
			'order' => array('Code.id DESC')
		));
		$codesList = array();
		foreach ($codes as $code) {
			if (empty($code['Code']['quota'])) {
				$quota = 'unlimited';
			}
			else {
				$quota = $code['Code']['quota'];
			}
			$used = count($code['CodeRedemption']);
			$expiration = Utils::change_tz_from_utc($code['Code']['expiration'], 'Y-m-d');
			$point = $code['Code']['amount'];
			$codesList[$code['Code']['id']] = 
				$code['Code']['code'] . " [$point pts] (quota:$used/$quota,exp:$expiration)";
		}

		$codeRedemptions = $this->CodeRedemption->find('all', array(
			'conditions' => array(
				'CodeRedemption.user_id' => $userId
			),
			'order' => array('CodeRedemption.created DESC'),
			'contain' => array('Code'),
		));
		$this->set(compact('user', 'codesList', 'codeRedemptions'));
	}
}