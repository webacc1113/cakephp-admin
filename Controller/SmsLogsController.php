<?php
App::uses('AppController', 'Controller');

class SmsLogsController extends AppController {

	public $uses = array('VerificationSmsLog');
	public $helpers = array('Html', 'Time');
	public $components = array('RequestHandler');
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
		
	public function index() {
		$this->VerificationSmsLog->bindModel(array('belongsTo' => array(
			'Admin' => array(
				'foreignKey' => 'user_id',
				'fields' => array('Admin.id', 'Admin.admin_user'),
				'conditions' => array(
					'VerificationSmsLog.type' => 'admin'
				)
			),
			'User' => array(
				'fields' => array('User.id', 'User.username'),
				'conditions' => array(
					'VerificationSmsLog.type' => 'user'
				)
			)
		)));
		$conditions = array();
		if (isset($this->request->query['phone_number']) && !empty($this->request->query['phone_number'])) {
			$conditions['VerificationSmsLog.from LIKE '] = '%' . $this->request->query['phone_number'] . '%';
		}
		if (isset($this->request->query['type']) && !empty($this->request->query['type'])) {
			$conditions['VerificationSmsLog.type'] = $this->request->query['type'];
		}
		$paginate = array(
			'conditions' => $conditions,
			'limit' => 50,
			'order' => 'VerificationSmsLog.created DESC' // created has no index on it; you should always sort on indexed columns
		);
		$this->paginate = $paginate;
		$sms_logs = $this->paginate('VerificationSmsLog');
		$this->set(compact('sms_logs'));
	}
}
