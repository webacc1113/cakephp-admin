<?php
App::uses('AppController', 'Controller');

class BalanceMismatchesController extends AppController {
	public $uses = array('BalanceMismatch', 'User');
	
	function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		$limit = 200;
		$conditions = array();
		if (!empty($this->data['user'])) {
			if ($this->data['user']{0} == '#') {
				$user = $this->User->fromId(substr($this->data['user'], 1));
			}
			else {
				$user = $this->User->fromEmail($this->data['user']);
			}
			if ($user) {
				$conditions['BalanceMismatch.user_id'] = $user['User']['id']; 
			}
		}
		
		if (isset($this->data['date_from']) && !empty($this->data['date_from'])) {
			if (isset($this->data['date_to']) && !empty($this->data['date_to'])) {
				$conditions['BalanceMismatch.created >='] = date(DB_DATE, strtotime($this->data['date_from']));
				$conditions['BalanceMismatch.created <='] = date(DB_DATE, strtotime($this->data['date_to']) + 86400);
			}
			else {
				$conditions['BalanceMismatch.created >='] = date(DB_DATE, strtotime($this->data['date_from'])).' 00:00:00';
				$conditions['BalanceMismatch.created <='] = date(DB_DATE, strtotime($this->data['date_from'])).' 23:59:59';
			}
		}
		$paginate = array(
			'BalanceMismatch' => array(
				'limit' => $limit,
				'order' => 'BalanceMismatch.id DESC',
				'conditions' => $conditions
			)
		);
		$this->paginate = $paginate;
		$balance_mismatches = $this->paginate();
		
		$this->set(compact('balance_mismatches'));		
	}
}