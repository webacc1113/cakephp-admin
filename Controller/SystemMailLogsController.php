<?php
App::uses('AppController', 'Controller');

class SystemMailLogsController extends AppController {

	public $uses = array('SystemMailLog');
	public $helpers = array('Html', 'Time');
	public $components = array('RequestHandler');
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
		
	public function index() {
		$start_time = date(DB_DATE).' 00:00:00'; 
		$end_time = date(DB_DATE).' 23:59:59';
		
		$conditions = array(
			'SystemMailLog.shard_id >' => 0,
			'SystemMailLog.started >=' => date(DB_DATE).' 00:00:00',
			'SystemMailLog.started <=' => date(DB_DATE).' 23:59:59',
			'SystemMailLog.ended is not null', // exclude items currently in process
		);
		if (isset($this->request->query['date']) && !empty($this->request->query['date'])) {
			$date = date(DB_DATE, strtotime($this->request->query['date'])); 
			$start_time = $date.' 00:00:00'; 
			$end_time = $date.' 23:59:59';
					
			$conditions['SystemMailLog.started >='] = $start_time; 
			$conditions['SystemMailLog.started <='] = $end_time; 
			
			$system_mail_log = $this->SystemMailLog->find('first', array(
				'conditions' => array(
					'SystemMailLog.shard_id' => '0', 
					'SystemMailLog.started' => $start_time,
					'SystemMailLog.ended' => $end_time,
				)
			)); 
		}
		else {
			$summed_logs = $this->SystemMailLog->find('first', array(
				'fields' => array(
					'COUNT(started) as counts',
					'MIN(started) as started',
					'ROUND(AVG(SystemMailLog.execution_time_ms)) as execution_time_ms',
					'SUM(SystemMailLog.stuck_emails) as stuck_emails',
					'SUM(SystemMailLog.processing_emails) as processing_emails',
					'SUM(SystemMailLog.sent_emails) as sent_emails',
					'SUM(SystemMailLog.suppressed_emails) as suppressed_emails'
				),
				'conditions' => $conditions
			));
			$system_mail_log = array();
			if ($summed_logs[0]['counts'] > 0) {
				$system_mail_log['SystemMailLog'] = array(
					'shard_id' => '0',
					'started' => $start_time,
					'ended' => $end_time,
					'execution_time_ms' => $summed_logs[0]['execution_time_ms'],
					'stuck_emails' => $summed_logs[0]['stuck_emails'],
					'processing_emails' => $summed_logs[0]['processing_emails'],
					'sent_emails' => $summed_logs[0]['sent_emails'],
					'suppressed_emails' => $summed_logs[0]['suppressed_emails'],
				);
			}
		}
		$paginate = array(
			'conditions' => $conditions,
			'limit' => 50,
			'order' => 'SystemMailLog.started DESC' // created has no index on it; you should always sort on indexed columns
		);
		$this->paginate = $paginate;
		$system_mail_logs = $this->paginate('SystemMailLog');
		$this->set(compact('system_mail_log', 'system_mail_logs'));
	}

	public function summary() {
		$paginate = array(
			'conditions' => array(
				'SystemMailLog.shard_id' => '0'
			),
			'limit' => 50,
			'order' => 'SystemMailLog.started DESC'
		);
		$this->paginate = $paginate;
		$system_mail_logs = $this->paginate('SystemMailLog');
		$this->set(compact('system_mail_logs'));
	}
}
