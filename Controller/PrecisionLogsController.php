<?php
App::uses('AppController', 'Controller');

class PrecisionLogsController extends AppController {	
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array();
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		$conditions = array(
			'PrecisionLog.parent_id' => '0'
		);
		
		if (isset($this->request->query['user_id']) && !empty($this->request->query['user_id'])) {
			$conditions['PrecisionLog.user_id'] = $this->request->query['user_id'];
		}
		if (isset($this->request->query['date_from']) && !empty($this->request->query['date_from'])) {
			if (isset($this->request->query['date_to']) && !empty($this->request->query['date_to'])) {
				$conditions['PrecisionLog.created >='] = date(DB_DATE, strtotime($this->request->query['date_from'])).' 00:00:00';
				$conditions['PrecisionLog.created <='] = date(DB_DATE, strtotime($this->request->query['date_to'])).' 23:59:59';
			}
			else {
				$conditions['PrecisionLog.created >='] = date(DB_DATE, strtotime($this->request->query['date_from'])).' 00:00:00';
				$conditions['PrecisionLog.created <='] = date(DB_DATE, strtotime($this->request->query['date_from'])).' 23:59:59';
			}	
		}	
		$paginate = array(
			'PrecisionLog' => array(
				'limit' => 50,
				'order' => 'PrecisionLog.id DESC',
				'conditions' => $conditions,
				'fields' => array(
					'PrecisionLog.id', 'PrecisionLog.user_id', 'PrecisionLog.created', 'PrecisionLog.count'
				)
			)
		);
		
		$this->paginate = $paginate;
		$precision_logs = $this->paginate();
		foreach ($precision_logs as $key => $precision_log) {
			$count = $this->PrecisionLog->find('count', array(
				'conditions' => array(
					'PrecisionLog.parent_id' => $precision_log['PrecisionLog']['id'],
					'PrecisionLog.status' => PROJECT_STATUS_OPEN,
					'PrecisionLog.status_active' => true
				)
			));
			$precision_logs[$key]['PrecisionLog']['project_active_count'] = $count;
		}
		$this->set(compact('precision_logs'));	
	}
	
	public function view($precision_log_id) {
		$precision_log = $this->PrecisionLog->find('first', array(
			'conditions' => array(
				'PrecisionLog.id' => $precision_log_id
			)
		));
		$precision_logs = $this->PrecisionLog->find('all', array(
			'conditions' => array(
				'PrecisionLog.parent_id' => $precision_log['PrecisionLog']['id']
			),
			'order' => 'PrecisionLog.id ASC'
		));
		
		$prev_log = $this->PrecisionLog->find('first', array(
			'conditions' => array(
				'PrecisionLog.user_id' => $precision_log['PrecisionLog']['user_id'],
				'PrecisionLog.parent_id' => '0',
				'PrecisionLog.id <' => $precision_log['PrecisionLog']['id']
			),
			'order' => 'PrecisionLog.id ASC'
		));
		
		$next_log = $this->PrecisionLog->find('first', array(
			'conditions' => array(
				'PrecisionLog.user_id' => $precision_log['PrecisionLog']['user_id'],
				'PrecisionLog.parent_id' => '0',
				'PrecisionLog.id >' => $precision_log['PrecisionLog']['id']
			),
			'order' => 'PrecisionLog.id ASC'
		));
		
		$this->set(compact('precision_log', 'precision_logs', 'next_log', 'prev_log'));
	}
	
}
