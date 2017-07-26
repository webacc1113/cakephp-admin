<?php
App::uses('AppController', 'Controller');

class FlexidRequestsController extends AppController {
	public $helpers = array('Text', 'Html', 'Time');
	public $uses = array('FlexidRequest', 'User', 'FlexidResponse');
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		$this->FlexidRequest->bindModel(array(
			'hasOne' => array('FlexidResponse' => array(
				'foreignKey' => 'request_id'
			)),
			'belongsTo' => array('User' => array(
				'fields' => array('User.id', 'User.username')
			))
		));
		$conditions = array();
		if (isset($this->request->query['date']) && !empty($this->request->query['date'])) {
			$date = date(DB_DATE, strtotime($this->request->query['date']));
			$start_time = $date . ' 00:00:00';
			$conditions['FlexidRequest.created >='] = $start_time;
		}
		$paginate = array(
			'FlexidRequest' => array(
				'conditions' => $conditions,
				'order' => 'FlexidRequest.id ASC',
				'limit' => '50'
			)
		);
		$this->paginate = $paginate;
		$flexid_requests = $this->paginate('FlexidRequest');
		$verification_indexes = array(
			'00' => 'Nothing verified',
			'10' => 'Critical ID elements not verified, are associated with different person or do not exist',
			'20' => 'Minimal verification, critical ID elements not verified, are associated with different person',
			'30' => 'Several ID elements verified',
			'40' => 'Last name, address and phone verified; first name, phone verification failures',
			'50' => 'Full name, address, phone verified'
		);
		$this->set(compact('flexid_requests', 'verification_indexes'));
	}	
}
