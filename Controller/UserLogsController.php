<?php
App::uses('AppController', 'Controller');

class UserLogsController extends AppController {
	public $uses = array('UserLog');
	
	function beforeFilter() {
		parent::beforeFilter();
	}
	
	function index($user_id) {
		if (empty($user_id)) {
			$this->redirect(array('controller' => 'users', 'action' => 'index'));
		}
		$paginate = array(
			'UserLog' => array(
				'limit' => '50',
				'order' => 'UserLog.id DESC'
			)
		);
		$paginate['UserLog']['conditions']['UserLog.user_id'] = $user_id;
		$this->paginate = $paginate;	
		$this->set('user_logs', $this->paginate('UserLog'));
		
	}
}