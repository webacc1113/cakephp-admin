<?php
App::uses('AppController', 'Controller');

class TolunaLogsController extends AppController {
	public $uses = array('TolunaLog');
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array();

	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		$conditions = array(
			'TolunaLog.parent_id' => '0'
		);
		if (isset($this->request->query['user_id']) && !empty($this->request->query['user_id'])) {
			$conditions['TolunaLog.user_id'] = $this->request->query['user_id'];
		}
		if (isset($this->request->query['date_from']) && !empty($this->request->query['date_from'])) {
			if (isset($this->request->query['date_to']) && !empty($this->request->query['date_to'])) {
				$conditions['TolunaLog.created >='] = date(DB_DATE, strtotime($this->request->query['date_from'])).' 00:00:00';
				$conditions['TolunaLog.created <='] = date(DB_DATE, strtotime($this->request->query['date_to'])).' 23:59:59';
			}
			else {
				$conditions['TolunaLog.created >='] = date(DB_DATE, strtotime($this->request->query['date_from'])).' 00:00:00';
				$conditions['TolunaLog.created <='] = date(DB_DATE, strtotime($this->request->query['date_from'])).' 23:59:59';
			}
		}
		
		if (isset($this->request->query['country'])) {
			$conditions['TolunaLog.country'] = $this->request->query['country'];
		}

		$paginate = array(
			'TolunaLog' => array(
				'limit' => 50,
				'order' => 'TolunaLog.id DESC',
				'conditions' => $conditions,
				'fields' => array(
					'TolunaLog.id', 'TolunaLog.user_id', 'TolunaLog.created', 'TolunaLog.count', 'TolunaLog.country'
				)
			)
		);
		$this->paginate = $paginate;
		$toluna_logs = $this->paginate();
		foreach ($toluna_logs as $key => $toluna_log) {
			$count = $this->TolunaLog->find('count', array(
				'conditions' => array(
					'TolunaLog.parent_id' => $toluna_log['TolunaLog']['id'],
					'TolunaLog.status' => PROJECT_STATUS_OPEN,
					'TolunaLog.status_active' => true
				)
			));
			$toluna_logs[$key]['TolunaLog']['project_active_count'] = $count;
		}
		$this->set(compact('toluna_logs'));		
	}
		
	public function view($toluna_log_id) {
		$toluna_log = $this->TolunaLog->find('first', array(
			'conditions' => array(
				'TolunaLog.id' => $toluna_log_id
			)
		));
		$toluna_logs = $this->TolunaLog->find('all', array(
			'conditions' => array(
				'TolunaLog.parent_id' => $toluna_log['TolunaLog']['id']
			),
			'order' => 'TolunaLog.id ASC'
		));
		
		$prev_log = $this->TolunaLog->find('first', array(
			'conditions' => array(
				'TolunaLog.user_id' => $toluna_log['TolunaLog']['user_id'],
				'TolunaLog.parent_id' => '0',
				'TolunaLog.id <' => $toluna_log['TolunaLog']['id']
			),
			'order' => 'TolunaLog.id ASC'
		));
		
		$next_log = $this->TolunaLog->find('first', array(
			'conditions' => array(
				'TolunaLog.user_id' => $toluna_log['TolunaLog']['user_id'],
				'TolunaLog.parent_id' => '0',
				'TolunaLog.id >' => $toluna_log['TolunaLog']['id']
			),
			'order' => 'TolunaLog.id ASC'
		));
		
		$this->set(compact('toluna_log', 'toluna_logs', 'projects', 'next_log', 'prev_log'));
	}
}