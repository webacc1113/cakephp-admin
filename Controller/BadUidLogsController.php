<?php
App::uses('AppController', 'Controller');

class BadUidLogsController extends AppController {
	
	public $uses = array('BadUidLog', 'BadUidMatch', 'SurveyVisit', 'Project', 'User');
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	function index() {
		$conditions = array(
			'BadUidLog.end_action' => 'success'
		);
		if (isset($this->request->query['type']) && $this->request->query['type']) {
			$conditions = array(
				'BadUidLog.end_action' => ($this->request->query['type'] == 'null') ? null : $this->request->query['type']
			);
		}
		if (isset($this->request->query['query_string'])) {
			$this->request->query['query_string'] = trim($this->request->query['query_string']);
			
			if (!empty($this->request->query['query_string'])) {
				$conditions[] = array(
					'BadUidLog.query_string LIKE' => '%'.trim($this->request->query['query_string']).'%'
				);
			}
		}
		
		$this->BadUidLog->bindModel(array('hasMany' => array(
			'BadUidMatch'
		)));
		$this->paginate = array(
			'BadUidLog' => array(
				'conditions' => $conditions,
				'order' => 'BadUidLog.id DESC',
				'limit' => 100
			)
		);
		$bad_uid_logs = $this->paginate('BadUidLog');
		if ($bad_uid_logs) {
			foreach ($bad_uid_logs as $key => $bad_uid_log) {
				$ip_match_count = $user_agent_match_count = 0;
				if (!empty($bad_uid_log['BadUidMatch'])) {
					foreach ($bad_uid_log['BadUidMatch'] as $bad_uid_match) {
						if ($bad_uid_match['type'] == 'ip_address') {
							$ip_match_count++;
						}
						if ($bad_uid_match['type'] == 'user_agent') {
							// user_agent matched
							$user_agent_match_count++;
						}
					}
				}
				$bad_uid_logs[$key]['BadUidLog']['ip_address_match'] = $ip_match_count;
				$bad_uid_logs[$key]['BadUidLog']['user_agent_match'] = $user_agent_match_count;
			}
		}
		$this->set(compact('bad_uid_logs'));
	}
	
	function view_server_info($bad_uid_log_id = null) {
		if (empty($bad_uid_log_id)) {
			throw new NotFoundException();
		}
		
		$bad_uid_log = $this->BadUidLog->find('first', array(
			'fields' => array('BadUidLog.server_info'),
			'conditions' => array(
				'BadUidLog.id' => $bad_uid_log_id
			)
		));
		$this->layout = null;
		$this->set(compact('bad_uid_log'));
	}
	
	function matches($bad_uid_log_id = null) {
		if (empty($bad_uid_log_id)) {
			throw new NotFoundException();
		}
		
		$this->BadUidLog->bindModel(array('hasMany' => array(
			'BadUidMatch'
		)));
		$this->SurveyVisit->bindModel(array('belongsTo' => array(
			'Project' => array(
				'foreignKey' => 'survey_id'
			)
		)));
		$this->BadUidMatch->bindModel(array('belongsTo' => array(
			'BadUidLog',
			'SurveyVisit'
		)));
		$bad_uid_logs = $this->BadUidLog->find('first', array(
			'conditions' => array(
				'BadUidLog.id' => $bad_uid_log_id
			),
			'contain' => array(
				'BadUidMatch' => array(
					'SurveyVisit' => array('Project')
				)
			)
		));
		
		if (!empty($bad_uid_logs['BadUidMatch'])) {
			foreach ($bad_uid_logs['BadUidMatch'] as $key => $bad_uid_log) {
				$user_id = explode('-', $bad_uid_log['SurveyVisit']['partner_user_id']);
				if (!isset($user_id[1])) {
					continue;
				} 
				$user = $this->User->find('first', array(
					'conditions' => array(
						'User.id' => $user_id[1]
					)
				));
				if (empty($user)) {
					continue;
				} 
				$bad_uid_logs['BadUidMatch'][$key]['User'] = $user['User'];
			}
		}
		$this->set(compact('bad_uid_logs'));
	}
}
