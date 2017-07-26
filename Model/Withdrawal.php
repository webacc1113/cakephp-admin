<?php
App::uses('AppModel', 'Model');

class Withdrawal extends AppModel {
	public $name = 'Withdrawal';
	public $actsAs = array('Containable');

	var $belongsTo = array(
		'User' => array(
			'className' => 'User',
			'foreignKey' => 'user_id',
			'fields' => array('id', 'referred_by', 'ref_id', 'username', 'fullname', 'email', 'hellbanned', 'balance', 'pending', 'created', 'verified', 'login', 'active', 'deleted_on')
		),
		'Transaction' => array(
            'className' => 'Transaction',
            'foreignKey' => 'transaction_id'
        )
	);	

	/*
	 * model callback functions
	 */
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

    public function beforeSave($options = array()) {
		if (!isset($this->data[$this->alias]['updated'])) {
			$this->data[$this->alias]['updated'] = date(DB_DATETIME);
		}

		/* -- keep previous `transaction_id` & `status` pair to keep the history of status change in `withdrawal_status_logs` table */
		$this->_prev_transaction_id = $this->_prev_status = null;
		if (isset($this->data[$this->alias]['id'])) {
			$withdrawal = $this->find('first', array(
				'conditions' => array(
					'Withdrawal.id' => $this->data[$this->alias]['id']
				)
			));

			if ($withdrawal && !empty($withdrawal['Withdrawal']['transaction_id'])) {
				$this->_prev_transaction_id = $withdrawal['Withdrawal']['transaction_id'];
			}
			if ($withdrawal && !empty($withdrawal['Withdrawal']['status'])) {
				$this->_prev_status = $withdrawal['Withdrawal']['status'];
			}
		}
		/* keep previous `transaction_id` & `status` pair to keep the history of status change in `withdrawal_status_logs` table -- */

		return true;
	}

	public function afterSave($created, $options = array()) {
		App::import('Model', 'WithdrawalStatusLog');
		$this->WithdrawalStatusLog = new WithdrawalStatusLog;
		
		/* -- fetch `withdrawal` record that was updated/created */
		$withdrawal = $this->find('first', array(
			'conditions' => array(
				'Withdrawal.id' => $this->id
			)
		));
		if (!$withdrawal) {
			return false;
		}
		/* fetch `withdrawal` record that was updated/created -- */

		if ($created) {
			/* -- creates a log into `withdrawal_status_logs` for a newly-created withdrawal record */
			$withdrawalStatusLogSource = $this->WithdrawalStatusLog->getDataSource();
			$withdrawalStatusLogSource->begin();
			$this->WithdrawalStatusLog->create();
			$this->WithdrawalStatusLog->save(array('WithdrawalStatusLog' => array(
				'withdrawal_id' => $withdrawal[$this->alias]['id'],
				'old_transaction_id' => null,
				'old_status' => null,
				'new_transaction_id' => $withdrawal[$this->alias]['transaction_id'],
				'new_status' => $withdrawal[$this->alias]['status'],
				'created' => date(DB_DATETIME)
			)));
			$withdrawalStatusLogSource->commit();
			/* creates a log into `withdrawal_status_logs` for a newly-created withdrawal record -- */

			return true;
		}

		/*
		 * -- `withdrawal` record update operation 
		 */

		/* -- if status has been changed, we need to log into `withdrawal_status_logs` table */
		if ($withdrawal[$this->alias]['status'] != $this->_prev_status) {
			$withdrawalStatusLogSource = $this->WithdrawalStatusLog->getDataSource();
			$withdrawalStatusLogSource->begin();
			$this->WithdrawalStatusLog->create();
			$this->WithdrawalStatusLog->save(array('WithdrawalStatusLog' => array(
				'withdrawal_id' => $withdrawal[$this->alias]['id'],
				'old_transaction_id' => $this->_prev_transaction_id,
				'old_status' => $this->_prev_status,
				'new_transaction_id' => $withdrawal[$this->alias]['transaction_id'],
				'new_status' => $withdrawal[$this->alias]['status'],
				'created' => date(DB_DATETIME)
			)));
			$withdrawalStatusLogSource->commit();
		}
		/* if status has been changed, we need to log into `withdrawal_status_logs` table -- */

		/*
		 * need to update fields from `transactions` table
		 * status, payout_processed, linked_to_id(payment_identifier), amount, name(note), executed(approved)
		 */
		$status = $payout_processed = null;
		switch ($withdrawal[$this->alias]['status']) {
			case WITHDRAWAL_NA:
				$status = TRANSACTION_NA;
				$payout_processed = PAYOUT_UNPROCESSED;
				break;
			case WITHDRAWAL_PENDING:
				$status = TRANSACTION_PENDING;
				$payout_processed = PAYOUT_UNPROCESSED;
				break;
			case WITHDRAWAL_REJECTED:
				$status = TRANSACTION_REJECTED;
				$payout_processed = PAYOUT_UNPROCESSED;
				break;
			case WITHDRAWAL_PAYOUT_UNPROCESSED:
				$status = TRANSACTION_APPROVED;
				$payout_processed = PAYOUT_UNPROCESSED;
				break;
			case WITHDRAWAL_PAYOUT_SUCCEEDED:
				$status = TRANSACTION_APPROVED;
				$payout_processed = PAYOUT_SUCCEEDED;
				break;
			case WITHDRAWAL_PAYOUT_FAILED:
				$status = TRANSACTION_APPROVED;
				$payout_processed = PAYOUT_FAILED;
				break;
		}

		$this->Transaction->create();
		$this->Transaction->save(array('Transaction' => array(
			'id' => $withdrawal[$this->alias]['transaction_id'],
			'status' => $status,
			'payout_processed' => $payout_processed,
			'linked_to_id' => $withdrawal[$this->alias]['payment_identifier'],
			'amount' => $withdrawal[$this->alias]['amount_cents'],
			'name' => $withdrawal[$this->alias]['note'],
			'executed' => $withdrawal[$this->alias]['approved']
		)), true, array('status', 'payout_processed', 'linked_to_id', 'amount', 'name', 'executed'));
		/*
		 * `withdrawal` record update operation --
		 */

		return true;
	}

	public function beforeDelete($cascade = true) {
		$withdrawal_to_be_deleted_id = $this->id;
		$withdrawal = $this->find('first', array(
			'conditions' => array(
				'Withdrawal.id' => $withdrawal_to_be_deleted_id
			)
		));
		if (!$withdrawal) {
			return false;
		}

		/* -- soft-delete relevant transaction record */
		$this->Transaction->delete($withdrawal[$this->alias]['transaction_id']);
		/* soft-delete relevant transaction record -- */

		/* -- soft-delete withdrawal record */
		$this->create();
		$this->save(array('Withdrawal' => array(
			'id' => $withdrawal_to_be_deleted_id,
			'deleted' => date(DB_DATETIME),
			'modified' => false
		)), array(
			'fieldList' => array('deleted'),
			'callbacks' => false,
			'validate' => false
		));
		/* soft-delete withdrawal record -- */

		return false;
	}

	public function afterDelete() {
		
	}

	/*
	 * model util functions
	 */
	public function fromId($id) {
		return $this->find('first', array(
			'conditions' => array(
				'Withdrawal.id' => $id
			)
		));
	}

	public function bindItems($reset = true) {
		$this->bindModel(array('belongsTo' => array(
			'PaymentMethod' => array(
				'className' => 'PaymentMethod',
				'foreignKey' => 'payment_identifier',
				'fields' => array('id', 'payment_method')
			)
		)), $reset);
	}

	public function unset_unnecessary_values($withdrawal) {
		unset($withdrawal['Withdrawal']['id']);
		unset($withdrawal['Withdrawal']['deleted']);
		unset($withdrawal['Withdrawal']['updated']);

		return $withdrawal;
	}

	public function soft_delete($withdrawal) {
		/* -- soft_delete withdrawal record */
		$this->create();
		$this->save(array('Withdrawal' => array(
			'id' => $withdrawal['Withdrawal']['id'],
			'deleted' => date(DB_DATETIME)
		)), array(
			'fieldList' => array('deleted'),
			'callbacks' => false,
			'validate' => false
		));
		/* soft_delete withdrawal record -- */

		/* -- soft_delete matching transaction record */
		$transaction = $this->Transaction->find('first', array(
			'recursive' => -1,
			'conditions' => array(
				'Transaction.id' => $withdrawal['Withdrawal']['transaction_id']
			)
		));
		if (!$transaction) {
			return false;
		}
		$this->Transaction->soft_delete($transaction);
		/* soft_delete matching transaction record -- */
	}

	public function approve($withdrawal) {
		/* -- can't approve twice */
		if ($withdrawal['Withdrawal']['status'] == WITHDRAWAL_PAYOUT_UNPROCESSED ||
			$withdrawal['Withdrawal']['status'] == WITHDRAWAL_PAYOUT_SUCCEEDED ||
			$withdrawal['Withdrawal']['status'] == WITHDRAWAL_PAYOUT_FAILED) {
			return false;
		}
		/* can't approve twice -- */

		/* -- approve relevant transaction record */
		$transaction = $this->Transaction->find('first', array(
			'recursive' => -1,
			'conditions' => array(
				'Transaction.id' => $withdrawal['Withdrawal']['transaction_id']
			)
		));
		if (!$transaction) {
			return false;
		}
		$new_transaction_id = $this->Transaction->approve($transaction);
		/* approve relevant transaction record -- */

		/* -- withdrawal stats log, we are not calling callback on withdrawal record save on approve() */
		App::import('Model', 'WithdrawalStatusLog');
		$this->WithdrawalStatusLog = new WithdrawalStatusLog;

		$withdrawalStatusLogSource = $this->WithdrawalStatusLog->getDataSource();
		$withdrawalStatusLogSource->begin();
		$this->WithdrawalStatusLog->create();
		$this->WithdrawalStatusLog->save(array('WithdrawalStatusLog' => array(
			'withdrawal_id' => $withdrawal[$this->alias]['id'],
			'old_transaction_id' => $withdrawal[$this->alias]['transaction_id'],
			'old_status' => $withdrawal[$this->alias]['status'],
			'new_transaction_id' => $new_transaction_id,
			'new_status' => WITHDRAWAL_PAYOUT_UNPROCESSED,
			'created' => date(DB_DATETIME)
		)));
		$withdrawalStatusLogSource->commit();
		/* withdrawal stats log, we are not calling callback on withdrawal record save on approve() -- */

		/* -- approve withdrawal record */
		$this->create();
		$this->save(array('Withdrawal' => array(
			'id' => $withdrawal['Withdrawal']['id'],
			'transaction_id' => $new_transaction_id,
			'status' => WITHDRAWAL_PAYOUT_UNPROCESSED,
			'approved' => date(DB_DATETIME)
		)), array(
			'fieldList' => array('transaction_id', 'status', 'approved'),
			'callbacks' => false,
			'validate' => false
		));
		/* approve withdrawal record -- */

		return $withdrawal['Withdrawal']['id'];
	}

	public function reject($withdrawal) {
		/* -- can't reject twice */
		if ($withdrawal['Withdrawal']['status'] == WITHDRAWAL_REJECTED) {
			return false;
		}
		/* can't reject twice -- */

		/* -- reject relevant transaction */
		$transaction = $this->Transaction->find('first', array(
			'recursive' => -1,
			'conditions' => array(
				'Transaction.id' => $withdrawal['Withdrawal']['transaction_id']
			)
		));
		if (!$transaction) {
			return false;
		}
		$new_transaction_id = $this->Transaction->reject($transaction);
		/* reject relevant transaction -- */

		/* -- withdrawal status log, manually update status log and block callback */
		App::import('Model', 'WithdrawalStatusLog');
		$this->WithdrawalStatusLog = new WithdrawalStatusLog;

		$withdrawalStatusLogSource = $this->WithdrawalStatusLog->getDataSource();
		$withdrawalStatusLogSource->begin();
		$this->WithdrawalStatusLog->create();
		$this->WithdrawalStatusLog->save(array('WithdrawalStatusLog' => array(
			'withdrawal_id' => $withdrawal[$this->alias]['id'],
			'old_transaction_id' => $withdrawal[$this->alias]['transaction_id'],
			'old_status' => $withdrawal[$this->alias]['status'],
			'new_transaction_id' => $new_transaction_id,
			'new_status' => WITHDRAWAL_REJECTED,
			'created' => date(DB_DATETIME)
		)));
		$withdrawalStatusLogSource->commit();
		/* withdrawal status log, manually update status log and block callback -- */

		/* -- reject withdrawal record */
		$this->create();
		$this->save(array('Withdrawal' => array(
			'id' => $withdrawal['Withdrawal']['id'],
			'transaction_id' => $new_transaction_id,
			'status' => WITHDRAWAL_REJECTED
		)), array(
			'fieldList' => array('transaction_id', 'status', 'approved'),
			'callbacks' => false,
			'validate' => false
		));
		/* reject withdrawal record -- */

		return $withdrawal['Withdrawal']['id'];
	}

}
