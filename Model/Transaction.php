<?php
App::uses('AppModel', 'Model');

class Transaction extends AppModel {
	public $displayField = 'name';
	public $actsAs = array('Containable');

	var $belongsTo = array(
		'User' => array(
			'className' => 'User',
			'foreignKey' => 'user_id',
			'fields' => array('id', 'referred_by', 'ref_id', 'username', 'fullname', 'email', 'hellbanned', 'balance', 'pending', 'created', 'verified', 'login', 'active', 'deleted_on')
		),
	);

	public function approve($transaction, $delay_payment = false) {
		// if we've already paid it out, then don't
		if (!$delay_payment && $transaction['Transaction']['status'] == TRANSACTION_APPROVED && $transaction['Transaction']['paid']) {
			return false;
		}
		elseif ($delay_payment && $transaction['Transaction']['status'] == TRANSACTION_APPROVED) {
			return false;
		}

		$this->soft_delete($transaction);
		$transaction = $this->unset_unnecessary_values($transaction);
		$transaction['Transaction']['status'] = TRANSACTION_APPROVED;
		$transaction['Transaction']['paid'] = !$delay_payment;

		$transactionSource = $this->getDataSource();
		$transactionSource->begin();
		$this->create();
		$this->save($transaction);
		$transaction_id = $this->getInsertId();
		$transactionSource->commit();
		
		if (!$delay_payment && $transaction['Transaction']['type_id'] == TRANSACTION_MISSING_POINTS) {
			$user = $this->User->find('first', array(
				'recursive' => -1,
				'fields' => array('User.id', 'User.balance', 'User.pending', 'User.missing_points'),
				'conditions' => array(
					'User.id' => $transaction['User']['id']
				)
			));
			$this->User->create();
			$this->User->save(array('User' => array(
				'id' => $user['User']['id'],
				'missing_points' => $user['User']['missing_points'] + $transaction['Transaction']['amount']
			)), array(
				'fieldList' => array('missing_points'),
				'validate' => false,
				'callbacks' => false
			));
		}
	
		return $transaction_id;
	}
	
	public function unreject($transaction, $paid = false) {
		if ($transaction['Transaction']['status'] != TRANSACTION_REJECTED) {
			return true;
		}
		
		$this->soft_delete($transaction);
		$transaction = $this->unset_unnecessary_values($transaction);
		$transaction['Transaction']['status'] = TRANSACTION_APPROVED;
		$transaction['Transaction']['paid'] = !$paid;

		$this->create();
		$this->save($transaction);
		$transaction_id = $this->getInsertId();

		// return clawed back points
		$this->User->rebuildBalances($transaction);
		
		// if we're rejecting a transaction, update the total count for users
		if ($transaction['Transaction']['type_id'] == TRANSACTION_SURVEY) {
			$count = $this->find('count', array(
				'conditions' => array(
					'Transaction.user_id' => $transaction['Transaction']['user_id'],
					'Transaction.status' => TRANSACTION_REJECTED,
					'Transaction.type_id' => TRANSACTION_SURVEY,
					'Transaction.deleted' => null,
				)
			));
			$this->User->create();
			$this->User->save(array('User' => array(
				'id' => $transaction['Transaction']['user_id'],
				'rejected_transactions' => $count
			)), array(
				'callbacks' => false,
				'validate' => false,
				'fieldList' => array('rejected_transactions')
			));
		}
		
		return $transaction_id;
	}
	public function reject($transaction) {
		// can't reject twice
		if ($transaction['Transaction']['status'] == TRANSACTION_REJECTED) {
			return true;
		}

		$this->soft_delete($transaction);
		$new_transaction = $this->unset_unnecessary_values($transaction);
		$new_transaction['Transaction']['status'] = TRANSACTION_REJECTED;
		$new_transaction['Transaction']['paid'] = true;

		$transactionSource = $this->getDataSource();
		$transactionSource->begin();
		$this->create();
		$this->save($new_transaction);
		$transaction_id = $this->getInsertId();
		$transactionSource->commit();
		
		
		// for withdrawals - we need to clear the pending status and deduct from the balance
		if ($transaction['Transaction']['type_id'] == TRANSACTION_WITHDRAWAL) {
			$this->User->create();
			$this->User->save(array('User' => array(
				'id' => $transaction['User']['id'],
				'withdrawal' => '0'
			)), array(
				'fieldList' => array('withdrawal'),
				'validate' => false,
				'callbacks' => false
			)); 
		}
		elseif ($transaction['Transaction']['status'] == TRANSACTION_APPROVED) {
			// claw back points
			if ($transaction['Transaction']['paid']) {
				$this->User->create();
				$this->User->save(array('User' => array(
					'id' => $transaction['User']['id'],
					'balance' => $transaction['User']['balance'] - $transaction['Transaction']['amount']
				)), array(
					'fieldList' => array('balance'),
					'validate' => false,
					'callbacks' => false
				)); 
			}
			else {
				$pending = $transaction['User']['pending'] - $transaction['Transaction']['amount'];
				$this->User->create();
				$this->User->save(array('User' => array(
					'id' => $transaction['User']['id'],
					'pending' => $pending
				)), array(
					'fieldList' => array('pending'),
					'validate' => false,
					'callbacks' => false
				)); 
			}
		}
		elseif ($transaction['Transaction']['status'] == TRANSACTION_PENDING) {
			$pending = $transaction['User']['pending'] - $transaction['Transaction']['amount'];
			$this->User->create();
			$this->User->save(array('User' => array(
				'id' => $transaction['User']['id'],
				'pending' => $pending
			)), array(
				'fieldList' => array('pending'),
				'validate' => false,
				'callbacks' => false
			)); 
		}
				
		// if we're rejecting a transaction, update the total count for users
		if ($transaction['Transaction']['type_id'] == TRANSACTION_SURVEY) {
			$count = $this->find('count', array(
				'conditions' => array(
					'Transaction.user_id' => $transaction['Transaction']['user_id'],
					'Transaction.status' => TRANSACTION_REJECTED,
					'Transaction.type_id' => TRANSACTION_SURVEY,
					'Transaction.deleted' => null,
				)
			));
			$this->User->create();
			$this->User->save(array('User' => array(
				'id' => $transaction['Transaction']['user_id'],
				'rejected_transactions' => $count
			)), array(
				'callbacks' => false,
				'validate' => false,
				'fieldList' => array('rejected_transactions')
			));
		}
		
		return $transaction_id;
	}
	
	public function afterFind($results, $primary = false) {
		if ($primary === false) {
			if (isset($results['params'])) {
				$results['params'] = unserialize($results['params']);
			}
		}
		else {
			foreach ($results as $key => $val) {
				if (isset($val[$this->alias]['params'])) {
					$results[$key][$this->alias]['params'] = unserialize($val[$this->alias]['params']);
				}
			}
		}
		return $results;
	}
	
	public function bindItems($reset = true) {
		$this->bindModel(array('belongsTo' => array(			
			'Project' => array(
				'className' => 'Project',
				'foreignKey' => 'linked_to_id',
				'conditions' => array(
					'Transaction.type_id' => TRANSACTION_SURVEY
				),
				'fields' => array('id', 'prj_name', 'survey_name')
			),	
			'Poll' => array(
				'className' => 'Poll',
				'foreignKey' => 'linked_to_id',
				'conditions' => array(
					'Transaction.type_id' => TRANSACTION_POLL
				),
				'fields' => array('id', 'poll_question')
			),
			'PollStreak' => array(
				'className' => 'Poll',
				'foreignKey' => 'linked_to_id',
				'conditions' => array(
					'Transaction.type_id' => TRANSACTION_POLL_STREAK
				),
				'fields' => array('id', 'poll_question')
			),
			'Offer' => array(
				'className' => 'Offer',
				'foreignKey' => 'linked_to_id',
				'conditions' => array(
					'Transaction.type_id' => TRANSACTION_OFFER
				),
				'fields' => array('id', 'offer_title')
			),
			'Code' => array(
				'className' => 'Code',
				'foreignKey' => 'linked_to_id',
				'conditions' => array(
					'Transaction.type_id' => TRANSACTION_CODE
				)
			),
			'ReferralTransaction' => array(
				'className' => 'Transaction',
				'foreignKey' => 'linked_to_id',
				'conditions' => array(
					'Transaction.type_id' => TRANSACTION_REFERRAL
				)
			),
			'PaymentMethod' => array(
				'className' => 'PaymentMethod',
				'foreignKey' => 'linked_to_id',
				'conditions' => array(
					'Transaction.type_id' => TRANSACTION_WITHDRAWAL
				),
				'fields' => array('id', 'payment_method')
			)
		)), $reset);
	}
	
	public function fromId($id) {
		return $this->find('first', array(
			'conditions' => array(
				'Transaction.id' => $id
			)
		));
	}
	
	public function beforeSave($options = array()) {
		if (!isset($this->data[$this->alias]['modified'])) {
			$this->data[$this->alias]['modified'] = date(DB_DATETIME);
		}
		if (!isset($this->data[$this->alias]['id'])) {
			// $this->_previous_balance = null;
			// $transaction = $this->find('first', array(
			// 	'conditions' => array(
			// 		'Transaction.user_id' => $this->data[$this->alias]['user_id'],
			// 		'Transaction.deleted' => null,
			// 	),
			// 	'order' => 'Transaction.id DESC',
			// 	'recursive' => -1,
			// 	'fields' => array('Transaction.id', 'Transaction.user_balance')
			// ));
			// if ($transaction && !empty($transaction['Transaction']['user_balance'])) {
			// 	$this->_previous_balance = $transaction['Transaction']['user_balance'];
			// }
		}
		return true;
	}
		
	public function afterSave($created, $options = array()) {
		if ($created) {
			$transaction_id = $this->id;
			$user = $this->User->find('first', array(
				'fields' => array('User.id', 'User.balance', 'User.pending'),
				'conditions' => array(
					'User.id' => $this->data[$this->alias]['user_id']
				),
				'recursive' => -1,
			)); 
			if (!$user) {
				return;
			}
			// if (is_null($this->_previous_balance)) {
			// 	$this->_previous_balance = $user['User']['balance'];
			// }
			if ($this->data[$this->alias]['status'] == TRANSACTION_APPROVED) {
				if ($this->data[$this->alias]['type_id'] != TRANSACTION_WITHDRAWAL) {
					if ($this->data[$this->alias]['paid']) {
						$this->User->create();
						$this->User->save(array('User' => array(
							'id' => $user['User']['id'],
							'last_touched' => date(DB_DATETIME),
							'balance' => $user['User']['balance'] + $this->data[$this->alias]['amount']
						)), true, array('balance', 'last_touched'));

						//update user balance
						// $new_user_balance = $this->_previous_balance + $this->data[$this->alias]['amount'];
						// $this->create();
						// $this->save(array('Transaction' => array(
						// 	'id' => $transaction_id,
						// 	'user_balance' => $new_user_balance
						// )), array(
						// 	'fieldList' => array('user_balance'),
						// 	'callbacks' => false,
						// 	'validate' => false
						// ));
					}
					else {
						$this->User->create();
						$this->User->save(array('User' => array(
							'id' => $user['User']['id'],
							'last_touched' => date(DB_DATETIME),
							'pending' => $user['User']['pending'] + $this->data[$this->alias]['amount']
						)), true, array('pending', 'last_touched'));

						//update user balance
						// $this->create();
						// $this->save(array('Transaction' => array(
						// 	'id' => $transaction_id,
						// 	'user_balance' => $this->_previous_balance
						// )), array(
						// 	'fieldList' => array('user_balance'),
						// 	'callbacks' => false,
						// 	'validate' => false
						// ));
					}
				}
				elseif ($this->data[$this->alias]['type_id'] == TRANSACTION_WITHDRAWAL) {
					if ($this->data[$this->alias]['paid']) {
						$amount = $this->data[$this->alias]['amount'];
						// $new_user_balance = $this->_previous_balance + $amount;
						// $this->create();
						// $this->save(array('Transaction' => array(
						// 	'id' => $transaction_id,
						// 	'user_balance' => $new_user_balance
						// )), array(
						// 	'fieldList' => array('user_balance'),
						// 	'callbacks' => false,
						// 	'validate' => false
						// ));
						$this->User->create();
						$this->User->save(array('User' => array(
							'id' => $user['User']['id'],
							'withdrawal' => 0,
							'balance' => $user['User']['balance'] + $amount
						)), true, array('withdrawal', 'balance'));
					}
					else {
						//update user balance
						// $this->create();
						// $this->save(array('Transaction' => array(
						// 	'id' => $transaction_id,
						// 	'user_balance' => $this->_previous_balance
						// )), array(
						// 	'fieldList' => array('user_balance'),
						// 	'callbacks' => false,
						// 	'validate' => false
						// ));
					}
				}
			} 
			elseif ($this->data[$this->alias]['status'] == TRANSACTION_PENDING) {
				if ($this->data[$this->alias]['type_id'] != TRANSACTION_WITHDRAWAL) {
					$this->User->create();
					$this->User->save(array('User' => array(
						'id' => $user['User']['id'],
						'last_touched' => date(DB_DATETIME),
						'pending' => $user['User']['pending'] + $this->data[$this->alias]['amount']
					)), true, array('pending', 'last_touched'));	
				}
				else {
					$this->User->create();
					$this->User->save(array('User' => array(
						'id' => $user['User']['id'],
						'last_touched' => date(DB_DATETIME),
						'withdrawal' => $this->data[$this->alias]['amount']
					)), true, array('withdrawal', 'last_touched'));
				}
				//update user balance
				// $this->create();
				// $this->save(array('Transaction' => array(
				// 	'id' => $transaction_id,
				// 	'user_balance' => $this->_previous_balance
				// )), array(
				// 	'fieldList' => array('user_balance'),
				// 	'callbacks' => false,
				// 	'validate' => false
				// ));
			}
			elseif ($this->data[$this->alias]['status'] == TRANSACTION_REJECTED) {
				// $this->create();
				// $this->save(array('Transaction' => array(
				// 	'id' => $transaction_id,
				// 	'user_balance' => $this->_previous_balance
				// )), array(
				// 	'fieldList' => array('user_balance'),
				// 	'callbacks' => false,
				// 	'validate' => false
				// ));
			}
		}
		elseif (isset($this->data[$this->alias]['status']) && $this->data[$this->alias]['status'] == TRANSACTION_APPROVED && isset($this->data[$this->alias]['user_id'])) {			
			$this->User->create();
			$this->User->save(array('User' => array(
				'id' => $this->data[$this->alias]['user_id'],
				'last_touched' => date(DB_DATETIME),
			)), true, array('last_touched'));
		}
	}
	
	public function afterDelete() {
		
	}
	
	public function beforeDelete($cascade = true) {
		$transaction_to_be_deleted_id = $this->id;
		
		$this->create();
		$this->save(array('Transaction' => array(
			'id' => $transaction_to_be_deleted_id,
			'deleted' => date(DB_DATETIME),
			'modified' => false
		)), array(
			'fieldList' => array('deleted'),
			'callbacks' => false,
			'validate' => false
		));
		
		$transaction = $this->find('first', array(
			'conditions' => array(
				'Transaction.id' => $transaction_to_be_deleted_id
			)
		));
		$user = $this->User->find('first', array(
			'fields' => array('User.id', 'User.balance', 'User.pending'),
			'conditions' => array(
				'User.id' => $transaction['Transaction']['user_id']
			),
			'recursive' => -1
		)); 
		
		if ($transaction['Transaction']['type_id'] == TRANSACTION_WITHDRAWAL) {
			$this->User->create();
			$this->User->save(array('User' => array(
				'id' => $user['User']['id'],
				'withdrawal' => '0'
			)), array(
				'fieldList' => array('withdrawal'),
				'callbacks' => false,
				'validate' => false
			)); 
		}
		else {
			// undo transaction
			if ($transaction['Transaction']['status'] == TRANSACTION_APPROVED) {
				$this->User->create();
				$this->User->save(array('User' => array(
					'id' => $user['User']['id'],
					'balance' => $user['User']['balance'] - $transaction['Transaction']['amount']
				)),  array(
					'fieldList' => array('balance'),
					'callbacks' => false,
					'validate' => false
				)); 
			}
			elseif ($transaction['Transaction']['status'] == TRANSACTION_PENDING) {
				$this->User->create();
				$this->User->save(array('User' => array(
					'id' => $user['User']['id'],
					'pending' => $user['User']['pending'] - $transaction['Transaction']['amount']
				)),  array(
					'fieldList' => array('pending'),
					'callbacks' => false,
					'validate' => false
				)); 
			}
		}
		return false;
	}

	public function soft_delete($transaction) {
		$this->create();
		$this->save(array('Transaction' => array(
			'id' => $transaction['Transaction']['id'],
			'deleted' => date(DB_DATETIME),
			'modified' => false
		)), array(
			'fieldList' => array('deleted'),
			'callbacks' => false,
			'validate' => false
		));
		$user = $this->User->find('first', array(
			'recursive' => -1,
			'fields' => array('User.id', 'User.balance', 'User.pending', 'User.missing_points'),
			'conditions' => array(
				'User.id' => $transaction['Transaction']['user_id']
			)
		));
		if ($transaction['Transaction']['status'] == TRANSACTION_PENDING) {
			if ($transaction['Transaction']['type_id'] == TRANSACTION_WITHDRAWAL) {
				$this->User->create();
				$this->User->save(array('User' => array(
					'id' => $user['User']['id'],
					'withdrawal' => '0',
				)), array(
					'fieldList' => array('withdrawal'),
					'validate' => false,
					'callbacks' => false
				));
			}
			else {
				$pending = $user['User']['pending'] - $transaction['Transaction']['amount'];
				$this->User->create();
				$this->User->save(array('User' => array(
					'id' => $user['User']['id'],
					'pending' => $pending
				)), array(
					'fieldList' => array('pending'),
					'validate' => false,
					'callbacks' => false
				));
			}
		}
		elseif ($transaction['Transaction']['status'] == TRANSACTION_APPROVED) {
			if ($transaction['Transaction']['paid']) {
				$balance = $user['User']['balance'] - $transaction['Transaction']['amount'];
				$this->User->create();
				$this->User->save(array('User' => array(
					'id' => $user['User']['id'],
					'balance' => $balance,
				)), array(
					'fieldList' => array('balance'),
					'validate' => false,
					'callbacks' => false
				));
			}
			else {
				$pending = $user['User']['pending'] - $transaction['Transaction']['amount'];
				$this->User->create();
				$this->User->save(array('User' => array(
					'id' => $user['User']['id'],
					'pending' => $pending,
				)), array(
					'fieldList' => array('pending'),
					'validate' => false,
					'callbacks' => false
				)); 
			}

			if ($transaction['Transaction']['type_id'] == TRANSACTION_MISSING_POINTS) {
				$missing_points = $user['User']['missing_points'] - $transaction['Transaction']['amount'];
				$this->User->create();
				$this->User->save(array('User' => array(
					'id' => $user['User']['id'],
					'missing_points' => $missing_points,
				)), array(
					'fieldList' => array('missing_points'),
					'validate' => false,
					'callbacks' => false
				));
			}
		}
	}
	public function unset_unnecessary_values($transaction) {
		$transaction['Transaction']['original_id'] = $transaction['Transaction']['id'];
		unset($transaction['Transaction']['id']);
		unset($transaction['Transaction']['deleted']);
		unset($transaction['Transaction']['user_balance']);
		unset($transaction['Transaction']['modified']);
		return $transaction;
	}
}
