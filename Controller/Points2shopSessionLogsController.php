<?php
App::uses('AppController', 'Controller');

class Points2shopSessionLogsController extends AppController {
	public $uses = array(
		'Group', 
		'Points2shopSessionLog',
		'Project'
	);
	public $components = array();

	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		$conditions = array();
		if (!empty($this->request->query['user_id'])) {
			$conditions['Points2shopSessionLog.user_id'] = $this->request->query['user_id'];
		}

		if (!empty($this->request->query['project_id'])) {
			$conditions['Points2shopSessionLog.project_id'] = $this->request->query['project_id'];
		}

		$paginate = array(
			'Points2shopSessionLog' => array(
				'limit' => 50,
				'order' => 'Points2shopSessionLog.id DESC',
				'conditions' => $conditions,
			)
		);
		$this->paginate = $paginate;
		$points2shop_session_logs = $this->paginate('Points2shopSessionLog');
		
		// create a map of project_ids for each p2s project
		$points2shop_project_ids = array();
		if ($points2shop_session_logs) {
			foreach ($points2shop_session_logs as $points2shop_session_log) {
				if (empty($points2shop_session_log['Points2shopSessionLog']['filtered_values'])) {
					continue;
				}
				$filtered_values = json_decode($points2shop_session_log['Points2shopSessionLog']['filtered_values'], true); 
				if (!empty($filtered_values)) {
					
					// take out any items prefixed with # which are MV ids
					foreach ($filtered_values as $id => $message) {
						if ($id{0} == '#') {
							unset($filtered_values[$id]); 
						}
					}
				}
				$points2shop_project_ids = array_merge($points2shop_project_ids, array_keys($filtered_values)); 
			}
		}
		$points2shop_project_ids = array_unique($points2shop_project_ids); 
		
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'points2shop'
			),
			'recursive' => -1
		)); 
		
		$project_ids = $this->Project->find('list', array(
			'fields' => array('Project.mask', 'Project.id'),
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'Project.mask' => $points2shop_project_ids
			),
			'recursive' => -1
		)); 
		$this->set(compact('points2shop_session_logs', 'project_ids'));
	}

	public function detail($id) {
		$points2shop_session_log = $this->Points2shopSessionLog->find('first', array(
			'conditions' => array(
				'Points2shopSessionLog.id' => $id
			),
		));
		$this->set(compact('points2shop_session_log'));
	}
}