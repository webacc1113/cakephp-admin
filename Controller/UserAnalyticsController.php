<?php
App::uses('AppController', 'Controller');

class UserAnalyticsController extends AppController {

	public $uses = array('UserAnalytic', 'User');
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		if (isset($this->request->query['user_id']) && $this->request->query['user_id'] > 0) {
			$user_analytics = $this->UserAnalytic->find('all', array(
				'conditions' => array(
					'UserAnalytic.user_id' => $this->request->query['user_id']
				)
			));
			if (!$user_analytics) {
				$this->Session->setFlash('User analytics could not be found.', 'flash_error');
				$this->redirect(array('controller' => 'user_analytics', 'action' => 'index'));
			}
			$paginate = array(
				'UserAnalytic' => array(
					'order' => 'UserAnalytic.id DESC',
					'limit' => '100',
					'conditions' => array(
						'UserAnalytic.user_id' => $this->request->query['user_id']
					)
				)
			);
			$this->paginate = $paginate;
			$this->set('user_analytics', $this->paginate('UserAnalytic'));
			$this->set('user', $this->User->find('first', array(
				'fields' => array('segment_identify'),
				'conditions' => array(
					'User.id' => $this->request->query['user_id']
				)
			)));
		}
	}
}