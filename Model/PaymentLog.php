<?php
App::uses('AppModel', 'Model');

class PaymentLog extends AppModel {
	public $displayField = 'event';
	public $actsAs = array('Containable');
	
	function log($transaction, $status = PAYMENT_LOG_STARTED, $returned_info = '') {
		App::import('Model', 'User');
		$this->User = new User;
		
		$log = $this->find('first', array(
			'fields' => array('id'),
			'conditions' => array(
				'transaction_id' => $transaction['Transaction']['id'],
			)
		));
		if ($log) {
			$this->create();
			$this->save(array('PaymentLog' => array(
				'id' => $log['PaymentLog']['id'],
				'status' => $status,
				'processed' => date(DB_DATETIME),
				'returned_info' => ($returned_info) ? json_encode($returned_info) : '',
			)), null, array('status', 'processed', 'returned_info'));
		}
		else {
			$user = $this->User->find('first', array(
				'fields' => array('username', 'email'),
				'conditions' => array('id' => $transaction['Transaction']['user_id']),
				'recursive' => -1
			));

			$this->create();
			$this->save(array('PaymentLog' => array(
				'username' => $user['User']['username'],
				'user_email' => $user['User']['email'],
				'transaction_id' => $transaction['Transaction']['id'],
				'transaction_name' => $transaction['Transaction']['name'],
				'transaction_amount' => '$' . abs($transaction['Transaction']['amount']) / 100,
				'transaction_created' => $transaction['Transaction']['created'],
				'transaction_executed' => $transaction['Transaction']['executed'],
				'status' => $status,
				'returned_info' => ($returned_info) ? json_encode($returned_info) : '',
			)));
		}
	}
}
