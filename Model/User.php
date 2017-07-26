<?php
App::uses('AppModel', 'Model');

class User extends AppModel {
	public $displayField = 'name';
	public $actsAs = array('Containable');
	
	public $validate = array(
		'email' => array(
			'email' => array(
				'rule' => 'email',
				'allowEmpty' => false,
				'message' => 'You did not input a valid email address.', 
			),
			'custom' => array(
				'rule'    => array('validateEmail'),
				'message' => 'This email is already registered.'
			)
		),
		'phone_number' => array(
			'custom' => array(
				'rule' => array('check_mobile_options'),
				'message' => 'Enter a mobile/landline if you are opting below options.'
			)
		)
	);
	
	var $belongsTo = array(
		'Referrer' => array(
			'className' => 'User',
			'foreignKey' => 'referred_by',
			'fields' => array('id', 'email', 'hellbanned')
		)
	);
	
	var $hasOne = array(
		'QueryProfile' => array(
			'foreignKey' => 'user_id'
		)	
	);
	public function fromId($id) {
		$user = $this->find('first', array(
			'conditions' => array(
				'User.id' => $id
			)
		));
		
		return $user;
	}
	
	function check_mobile_options() {
		if (empty($this->data['User']['phone_number'])) {
			if (!empty($this->data['User']['is_mobile_verified']) || !empty($this->data['User']['send_sms'])) {
				return false;
			}
		}
		return true;
	}
	
	public function setUserRevenue($user, $force = false) {
		$update_user_amounts = $user['User']['last_touched'] > $user['UserRevenue']['modified'] || empty($user['UserRevenue']['id']);
		if (!$force && !$update_user_amounts) {
			return false;
		}
		
		App::import('Model', 'Transaction');
		$this->Transaction = new Transaction;
		
		$this->Transaction->bindModel(array('belongsTo' => array(			
			'Offer' => array(
				'className' => 'Offer',
				'foreignKey' => 'linked_to_id',
				'conditions' => array(
					'type_id' => TRANSACTION_OFFER
				),
				'fields' => array('id', 'client_rate', 'award')
			),			
			'Project' => array(
				'className' => 'Project',
				'foreignKey' => 'linked_to_id',
				'conditions' => array(
					'type_id' => TRANSACTION_SURVEY
				),
				'fields' => array('id', 'client_rate', 'award')
			)
		))); 
		
		$transactions = $this->Transaction->find('all', array(
			'conditions' => array(
				'Transaction.user_id' => $user['User']['id'],
				'Transaction.status' => TRANSACTION_APPROVED,
				'Transaction.deleted' => null,
			),
			'contain' => array(
				'Project', 
			),
			'recursive' => -1
		));
		$user_value = array(
			'user_id' => $user['User']['id'],
			'thirty_payout' => null,
			'thirty_revenue' => null,
			'thirty_cost' => null,
			'sixty_payout' => null,
			'sixty_revenue' => null,
			'sixty_cost' => null,
			'ninety_payout' => null,
			'ninety_revenue' => null,
			'ninety_cost' => null,
			'lifetime_payout' => null,
			'lifetime_revenue' => null,
			'lifetime_cost' => null,
			'acquisition_cost' => null
		);
		if ($transactions) {
			$time = mktime();
			foreach ($transactions as $transaction) {
				$diff = $time - strtotime($transaction['Transaction']['executed']);
				$days = round($diff / 86400);
				
				$revenue = $payout = $cost = null;
				
				// revenue generating
				$type = $transaction['Transaction']['type_id'];
				if ($type == TRANSACTION_SURVEY) {
					if ($transaction['Transaction']['amount'] > 5) {
						$revenue = $transaction['Project']['client_rate'] * 100;
					}
					$cost = $transaction['Transaction']['amount'];
				}
				elseif ($type == TRANSACTION_OFFER) {
					if (isset($transaction['Offer']['id']) && !empty($transaction['Offer']['id'])) {
						$revenue = $transaction['Offer']['client_rate'] * 100;
						$cost = $transaction['Transaction']['amount'];
					}
				}
				elseif ($type == TRANSACTION_GOOGLE) {
					$revenue = 50;
					$cost = $transaction['Transaction']['amount'];
				}
				elseif (in_array($type, array(TRANSACTION_REFERRAL, TRANSACTION_POLL, TRANSACTION_POLL_STREAK, TRANSACTION_EMAIL, TRANSACTION_PROFILE, TRANSACTION_OTHER))) {
					$cost = $transaction['Transaction']['amount'];
				}
				elseif ($type == TRANSACTION_WITHDRAWAL && $transaction['Transaction']['paid']) {
					$payout = $transaction['Transaction']['amount'];
				}
								
				if (isset($payout) && !empty($payout)) {
					$payout = $payout * -1; // make it positive so we take advantage of unsigned int				
					if ($days <= 90) {
						$user_value['ninety_payout'] = $user_value['ninety_payout'] + $payout;
					}
					if ($days <= 60) {
						$user_value['sixty_payout'] = $user_value['sixty_payout'] + $payout;
					}
					if ($days <= 30) {
						$user_value['thirty_payout'] = $user_value['thirty_payout'] + $payout;
					}					
					$user_value['lifetime_payout'] = $user_value['lifetime_payout'] + $payout;
				}
				
				if (isset($cost) && !empty($cost)) {			
					if ($days <= 90) {
						$user_value['ninety_cost'] = $user_value['ninety_cost'] + $cost;
					}
					if ($days <= 60) {
						$user_value['sixty_cost'] = $user_value['sixty_cost'] + $cost;
					}
					if ($days <= 30) {
						$user_value['thirty_cost'] = $user_value['thirty_cost'] + $cost;
					}					
					$user_value['lifetime_cost'] = $user_value['lifetime_cost'] + $cost;
				}
				
				if (isset($revenue) && !empty($revenue)) {			
					if ($days <= 90) {
						$user_value['ninety_revenue'] = $user_value['ninety_revenue'] + $revenue;
					}
					if ($days <= 60) {
						$user_value['sixty_revenue'] = $user_value['sixty_revenue'] + $revenue;
					}
					if ($days <= 30) {
						$user_value['thirty_revenue'] = $user_value['thirty_revenue'] + $revenue;
					}					
					$user_value['lifetime_revenue'] = $user_value['lifetime_revenue'] + $revenue;
				}
			}
		}
		
		// set user acquisition cost
		if (!empty($user['User']['origin'])) {
			$origin = $user['User']['origin'];
			if (strpos($origin, ':adp') !== false) {
				$user_value['acquisition_cost'] = 300;
			}
			if (strpos($origin, 'coreg') !== false) {
				$user_value['acquisition_cost'] = 50;
			}
			if (strpos($origin, ':roi') !== false) {
				$user_value['acquisition_cost'] = 300;
			}
			// pt incent
			if (strpos($origin, ':pt2') !== false || strpos($origin, ':pt4') !== false) {
				$user_value['acquisition_cost'] = 225;
			}
			// pt non-incent
			if (strpos($origin, ':pt') !== false || strpos($origin, ':pt3') !== false) {
				$user_value['acquisition_cost'] = 275;
			}
		}
		
		if (!empty($user['UserRevenue']['id'])) {
			$user_value['id'] = $user['UserRevenue']['id'];
		}
		$this->UserRevenue->create();
		$this->UserRevenue->save(array('UserRevenue' => $user_value));
		return true;
	}
	
	public function rebuildBalances($user) {
		App::import('Model', 'Transaction');
		$this->Transaction = new Transaction;
		
		$amount = $this->Transaction->find('first', array(
			'fields' => array('SUM(amount) as amount'),
			'recursive' => -1,
			'conditions' => array(
				'Transaction.type_id <>' => TRANSACTION_WITHDRAWAL,
				'Transaction.status' => TRANSACTION_APPROVED,
				'Transaction.paid' => '1',
				'Transaction.user_id' => $user['User']['id'],
				'Transaction.deleted' => null,
			)
		));
		$paid_balance = $amount[0]['amount'];
		
		$amount = $this->Transaction->find('first', array(
			'fields' => array('SUM(amount) as amount'),
			'recursive' => -1,
			'conditions' => array(
				'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
				'Transaction.status' => TRANSACTION_APPROVED,
				'Transaction.user_id' => $user['User']['id'],
				'Transaction.deleted' => null,
			)
		));
		$paid_balance = $paid_balance + $amount[0]['amount'];
		
		$amount = $this->Transaction->find('first', array(
			'fields' => array('SUM(amount) as amount'),
			'recursive' => -1,
			'conditions' => array(
				'Transaction.type_id <>' => TRANSACTION_WITHDRAWAL,
				'Transaction.deleted' => null,
				'OR' => array(
					array('Transaction.status' => TRANSACTION_PENDING),
					array(
						'Transaction.status' => TRANSACTION_APPROVED,
						'Transaction.paid' => false
					)
				),
				'Transaction.user_id' => $user['User']['id']
			)
		));
		$unpaid_balance = $amount[0]['amount'];
		
		$amount = $this->Transaction->find('first', array(
			'fields' => array('SUM(amount) as amount'),
			'recursive' => -1,
			'conditions' => array(
				'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
				'Transaction.status' => TRANSACTION_PENDING,
				'Transaction.user_id' => $user['User']['id'],
				'Transaction.deleted' => null,
			)
		));
		$withdrawal_balance = $amount[0]['amount'];
		
		$amount = $this->Transaction->find('first', array(
			'fields' => array('SUM(amount) as amount'),
			'recursive' => -1,
			'conditions' => array(
				'Transaction.status' => TRANSACTION_APPROVED,
				'Transaction.paid' => true,
				'Transaction.type_id <>' => TRANSACTION_WITHDRAWAL,
				'Transaction.user_id' => $user['User']['id'],
				'Transaction.deleted' => null,
			)
		));
		$total_balance = $amount[0]['amount'];
		
		$amount = $this->Transaction->find('first', array(
			'fields' => array('SUM(amount) as amount'),
			'recursive' => -1,
			'conditions' => array(
				'Transaction.type_id' => TRANSACTION_MISSING_POINTS,
				'Transaction.status' => TRANSACTION_APPROVED,
				'Transaction.user_id' => $user['User']['id'],
				'Transaction.deleted' => null,
			)
		));
		$missing_points = $amount[0]['amount'];
		
		$save = array('User' => array(
			'id' => $user['User']['id'],
			'balance' => empty($paid_balance) ? (int) 0: $paid_balance, 
			'pending' => empty($unpaid_balance) ? (int) 0: $unpaid_balance,
			'withdrawal' => empty($withdrawal_balance) ? (int) 0: $withdrawal_balance,
			'total' => empty($total_balance) ? (int) 0: $total_balance,
			'missing_points' => empty($missing_points) ? (int) 0: $missing_points
		)); 
		$this->create();
		$this->save($save, true, array('balance', 'pending', 'withdrawal', 'total', 'missing_points'));
		return $save;
	}
	
	public function fromEmail($email) {
		return $this->find('first', array(
			'conditions' => array(
				'User.email' => $email
			)
		));
	}
	
	public function beforeSave($options = array()) {
		if (isset($this->data[$this->alias]['password']) && !empty($this->data[$this->alias]['password'])) {
			$this->data[$this->alias]['password'] = AuthComponent::password($this->data[$this->alias]['password']);
		}
		if (isset($this->data[$this->alias]['temp_password']) && !empty($this->data[$this->alias]['temp_password'])) {
			$this->data[$this->alias]['temp_password'] = AuthComponent::password($this->data[$this->alias]['temp_password']);
		}
		return true;
	}
	
	public function beforeDelete($cascade = true) {
		$this->recursive = -1;
		$this->updateAll(array(
			'deleted_on' => '"' . date(DB_DATETIME) . '"',
		), array(
			'User.id' => $this->id
		));
		
		App::import('Model', 'QueryProfile');
		$this->QueryProfile = new QueryProfile;
		$query_profile = $this->QueryProfile->find('first', array(
			'conditions' => array(
				'QueryProfile.user_id' => $this->id
			)
		));
		if ($query_profile) {
			$this->QueryProfile->create();
			$this->QueryProfile->save(array('QueryProfile' => array(
				'id' => $query_profile['QueryProfile']['id'],
				'ignore' => true
			)), true, array('ignore'));
		}
		
		// deactivate all payment types
		App::import('Model', 'PaymentMethod'); 
		$this->PaymentMethod = new PaymentMethod;
		$payment_methods = $this->PaymentMethod->find('all', array(
			'conditions' => array(
				'PaymentMethod.user_id' => $this->id,
				'PaymentMethod.status' => DB_ACTIVE
			),
			'recursive' => -1,
			'fields' => array('id')
		));	
		if ($payment_methods) {
			foreach ($payment_methods as $payment_method) {
				$this->PaymentMethod->create();
				$this->PaymentMethod->save(array('PaymentMethod' => array(
					'id' => $payment_method['PaymentMethod']['id'],
					'status' => DB_DEACTIVE
				)), array('status'));
			}
		}
		
		// delete all active survey issues
		App::import('Model', 'HistoryRequest'); 
		$this->HistoryRequest = new HistoryRequest;
		$history_requests = $this->HistoryRequest->find('all', array(
			'conditions' => array(
				'HistoryRequest.user_id' => $this->id,
				'HistoryRequest.status' => SURVEY_REPORT_REQUEST_PENDING
			),
			'recursive' => -1,
			'fields' => array('id')
		));
		if ($history_requests) {
			foreach ($history_requests as $history_request) {
				$this->HistoryRequest->delete($history_request['HistoryRequest']['id']);
			}
		}
		
		return false;
	}
	
	public function validateEmail() {
		if (isset($this->data[$this->alias]['id'])) {
			$count = $this->find('count', array(
				'conditions' => array(
					'User.id <>' => $this->data[$this->alias]['id'],
					'User.email' => $this->data[$this->alias]['email'],
					'User.deleted_on' => null
				),
				'recursive' => -1
			));
		}
		else {
			$count = $this->find('count', array(
				'conditions' => array(
					'User.email' => $this->data[$this->alias]['email'],
					'User.deleted_on' => null
				),
				'recursive' => -1
			));
		}
		if ($count > 0) {
			return false;
		}
		return true;
	}
}
