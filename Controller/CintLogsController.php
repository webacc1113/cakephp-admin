<?php
App::uses('AppController', 'Controller');

class CintLogsController extends AppController {
	public $uses = array('CintLog', 'CintSurvey');
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array();

	public function beforeFilter() {
		parent::beforeFilter();

		CakePlugin::load('Uploader');
		App::import('Vendor', 'Uploader.Uploader');
	}
	
	public function index() {
		if ($this->request->is('post')) {
			$values = array();
			foreach ($this->data['compare'] as $key => $value) {
				if ($value == 1 && count($values) != 2) {
					$values[] = $key;
				}
			}
			$this->redirect(array('action' => 'compare', '?' => array('from' => $values[0], 'to' => $values[1])));
		}
		$conditions = array(
			'CintLog.parent_id' => '0'
		);
		if (isset($this->request->query['country']) && !empty($this->request->query['country'])) {
			$conditions['CintLog.country'] = $this->request->query['country'];
		}
		if (isset($this->request->query['date_from']) && !empty($this->request->query['date_from'])) {
			if (isset($this->request->query['date_to']) && !empty($this->request->query['date_to'])) {
				$conditions['CintLog.created >='] = date(DB_DATE, strtotime($this->request->query['date_from'])).' 00:00:00';
				$conditions['CintLog.created <='] = date(DB_DATE, strtotime($this->request->query['date_to'])).' 23:59:59';
			}
			else {
				$conditions['CintLog.created >='] = date(DB_DATE, strtotime($this->request->query['date_from'])).' 00:00:00';
				$conditions['CintLog.created <='] = date(DB_DATE, strtotime($this->request->query['date_from'])).' 23:59:59';
			}
		}

		$paginate = array(
			'CintLog' => array(
				'limit' => 50,
				'order' => 'CintLog.id DESC',
				'conditions' => $conditions,
				'fields' => array(
					'CintLog.id', 'CintLog.run', 'CintLog.country', 'CintLog.created', 'CintLog.count', 'CintLog.quota'
				)
			)
		);
		$this->paginate = $paginate;
		$cint_logs = $this->paginate();
		$this->set(compact('cint_logs'));		
	}
	
	// view the whole history of this project
	public function view_project($cint_survey_id) {
		
		// just getting the country
		$cint_log = $this->CintLog->find('first', array(
			'conditions' => array(
				'CintLog.cint_survey_id' => $cint_survey_id
			)
		));
		$min = $this->CintLog->find('first', array(
			'conditions' => array(
				'CintLog.cint_survey_id' => $cint_survey_id
			),
			'fields' => array('min(run) as min_run'),
			'recursive' => -1
		));
		$max = $this->CintLog->find('first', array(
			'conditions' => array(
				'CintLog.cint_survey_id' => $cint_survey_id
			),
			'fields' => array('max(run) as max_run'),
			'recursive' => -1
		));
		// get the range
		$ranges = $this->CintLog->find('all', array(
			'conditions' => array(
				'CintLog.country' => $cint_log['CintLog']['country'],
				'CintLog.run >=' => $min[0]['min_run'],
				'CintLog.run <=' => $max[0]['max_run'],
			),
			'fields' => array('DISTINCT(run) as distinct_run')
		));
		
		$list = array();
		if ($ranges) {
			foreach ($ranges as $range) {
				$list[] = $range['CintLog']['distinct_run'];
			}
		}
		$list = array_unique($list);
		asort($list);
		
		db($list);
		db($min);
		db($max);
	}
	
	public function compare() {
		if (!isset($this->request->query['from']) || !isset($this->request->query['to'])) {
			$this->Session->setFlash('To compare two runs, please supply both runs.', 'flash_error');
			return $this->redirect(array('action' => 'index'));
		}
		$lower = min(array($this->request->query['from'], $this->request->query['to'])); 
		$upper = max(array($this->request->query['from'], $this->request->query['to'])); 
		
		$lower = $this->CintLog->find('first', array(
			'conditions' => array(
				'CintLog.run' => $lower,
				'CintLog.parent_id' => '0'
			),
			'fields' => array(
				'CintLog.id', 'CintLog.run', 'CintLog.country', 'CintLog.created', 'CintLog.count', 'CintLog.quota', 'CintLog.country'
			)
		));
		$upper = $this->CintLog->find('first', array(
			'conditions' => array(
				'CintLog.run' => $upper,
				'CintLog.parent_id' => '0'
			),
			'fields' => array(
				'CintLog.id', 'CintLog.run', 'CintLog.country', 'CintLog.created', 'CintLog.count', 'CintLog.quota', 'CintLog.country'
			)
		));
		if (!$upper || !$lower) {
			$this->Session->setFlash('Could not find those runs', 'flash_error'); 
			return $this->redirect(array('action' => 'index', '?' => array('from' => $this->request->query['from'], 'to' => $this->request->query['to'])));
		}
		if ($lower['CintLog']['country'] != $upper['CintLog']['country']) {
			$this->Session->setFlash('Cannot compare runs from different countries.', 'flash_error');
			return $this->redirect(array('action' => 'index', '?' => array('from' => $this->request->query['from'], 'to' => $this->request->query['to'])));
		}
		$lower_logs = $this->CintLog->find('all', array(
			'conditions' => array(
				'CintLog.parent_id' => $lower['CintLog']['id']
			),
			'order' => 'CintLog.cint_survey_id ASC'
		));
		$upper_logs = $this->CintLog->find('all', array(
			'conditions' => array(
				'CintLog.parent_id' => $upper['CintLog']['id']
			),
			'order' => 'CintLog.cint_survey_id ASC'
		));
		
		// determine rows
		$total_list = $lower_list = $upper_list = array();
		$lower_logs_keyed = $upper_logs_keyed = array();
		foreach ($lower_logs as $lower_log) {
			$lower_logs_keyed[$lower_log['CintLog']['cint_survey_id']] = $lower_log;
			$lower_list[] = $lower_log['CintLog']['cint_survey_id'];
		}
		foreach ($upper_logs as $upper_log) {
			$upper_logs_keyed[$upper_log['CintLog']['cint_survey_id']] = $upper_log;
			$upper_list[] = $upper_log['CintLog']['cint_survey_id'];
		}
		$lower_list = array_unique($lower_list);
		$upper_list = array_unique($upper_list);
		$total_list = $lower_list + $upper_list; 
		asort($total_list);
		
		$surveys = $this->CintSurvey->find('list', array(
			'fields' => array('cint_survey_id', 'survey_id'),
			'conditions' => array(
				'CintSurvey.cint_survey_id' => $total_list,
				'CintSurvey.country' => $lower['CintLog']['country']
			)
		));
		
		$upper_diff = array_diff($lower_list, $upper_list);
		$lower_diff = array_diff($upper_list, $lower_list);
		$this->set(compact('lower_logs_keyed', 'upper_logs_keyed', 'upper', 'lower', 'total_list', 'surveys'));
	}
	
	public function run($cint_log_id) {
		$cint_log = $this->CintLog->find('first', array(
			'conditions' => array(
				'CintLog.id' => $cint_log_id
			)
		));
		$cint_logs = $this->CintLog->find('all', array(
			'conditions' => array(
				'CintLog.parent_id' => $cint_log['CintLog']['id']
			),
			'order' => 'CintLog.cint_survey_id ASC'
		));
		
		// grab unique cint_survey_ids
		$cint_survey_ids = array();
		$open_projects_completes = 0;
		foreach ($cint_logs as $cint_tmp_log) {
			$cint_survey_ids[] = $cint_tmp_log['CintLog']['cint_survey_id'];
			if ($cint_tmp_log['CintLog']['status'] == PROJECT_STATUS_OPEN) {
				$open_projects_completes += $cint_tmp_log['CintLog']['quota'];
			}
		}
		
		$cint_survey_ids = array_unique($cint_survey_ids);
		$surveys = $this->CintSurvey->find('list', array(
			'fields' => array('cint_survey_id', 'survey_id'),
			'conditions' => array(
				'CintSurvey.cint_survey_id' => $cint_survey_ids,
				'CintSurvey.country' => $cint_log['CintLog']['country']
			)
		));
		$this->set(compact('cint_log', 'cint_logs', 'surveys', 'open_projects_completes'));
	}
}