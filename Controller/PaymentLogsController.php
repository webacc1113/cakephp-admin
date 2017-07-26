<?php
App::uses('AppController', 'Controller');

class PaymentLogsController extends AppController {
	
	public function beforeFilter() {
		parent::beforeFilter();
	}

	public function index() {
		$status_filter = 'all';
		$conditions = array();
		if (isset($this->request->query['status'])) {
			$status_filter = $this->request->query['status'];
		}

		if ($status_filter != 'all') {
			$conditions['PaymentLog.status'] = $status_filter;
		}
		
		$paginate = array(
			'PaymentLog' => array(
				'conditions' => $conditions,
				'limit' => 50,
				'order' => 'id DESC',
			)
		);
		$this->paginate = $paginate;
		$this->set('logs', $this->paginate());
		$this->set('status_filter', $status_filter);
	}

}