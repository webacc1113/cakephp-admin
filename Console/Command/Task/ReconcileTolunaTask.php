<?php

class ReconcileTolunaTask extends Shell {	
	// initial cleanup of file
	function cleanFile($data) {
		$models_to_load = array('Partner', 'Group', 'Transaction', 'Project', 'SurveyVisit');
		foreach ($models_to_load as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		$partner = $this->Partner->find('first', array(
			'conditions' => array(
				'Partner.key' => array('mintvine'),
				'Partner.deleted' => false
			),
			'fields' => array('Partner.id')
		));
		$this->partner_id = $partner['Partner']['id']; //mintvine partner 
		
		$header = array_shift($data); // remove header
		$indexes = array();
		$indexes['survey_name'] = array_search('Survey', $header);
		$indexes['timestamp'] = array_search('EndDate', $header);
		$indexes['user_id'] = array_search('MemberCode', $header);
		$this->indexes = $indexes;
		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'toluna'
			)
		));
		$this->group_id = $group['Group']['id'];
		
		foreach ($indexes as $val) {
			if ($val === false) {
				return false;
			}
		}
		
		foreach ($data as $key => $val) {
			$has_values = false;
			foreach ($val as $k => $v) {
				if (!empty($v)) {
					$has_values = true;
					break;
				}
			}
			if (!$has_values) {
				unset($data[$key]); 
			}
		}
		
		return $data;
	}
	
	public function find_transaction($reconciliation_row) {
		$transaction = $this->Transaction->find('first', array(
			'fields' => array('Transaction.id'),
			'conditions' => array(
				'Transaction.type_id' => array(TRANSACTION_SURVEY, TRANSACTION_MISSING_POINTS),
				'Transaction.linked_to_id' => $reconciliation_row['survey_id'],
				'Transaction.user_id' => $reconciliation_row['user_id'],
				'Transaction.deleted' => null,
			)
		));
		if (!$transaction) {
			return false;
		}
		
		return $transaction['Transaction']['id'];
	}
	
	function getMinMaxDates($dates, $timestamp) {
		if (empty($timestamp) || $timestamp == '0000-00-00 00:00:00') {
			return $dates;
		}
		
		$timestamp = strtotime($timestamp);
		if (empty($dates['min'])) {
			$dates['min'] = $timestamp; 
		}
		
		if (empty($dates['max'])) {
			$dates['max'] = $timestamp; 
		}
		
		if ($dates['min'] > $timestamp) {
			$dates['min'] = $timestamp; 
		}
		
		if ($dates['max'] < $timestamp) {
			$dates['max'] = $timestamp; 
		}
			
		return $dates;
	}
	
	function parseCsvRow($row) {
		if (empty($row[$this->indexes['timestamp']])) {
			$date_time = '0000-00-00 00:00:00';
		}
		else {
			
			// Toluna timezone is UTC -4, so we convert it to UTC
			$date_time = strtotime($row[$this->indexes['timestamp']]) + 14400;
			$date_time = date(DB_DATETIME, $date_time);
		}
		
		$user_id = $row[$this->indexes['user_id']];
		$project_id = '';
		$hash = '';
		
		// Toluna can have multiple projects with the same name (They don't provide the wave_id in data file, both project name and wave_id uniquely identify a toluna project)
		// So here we find the project for this specific complete (based on the user that completed this survey - A user can have only one complete per project)
		// https://basecamp.com/2045906/projects/1413421/todos/308556691
		$project_ids = $this->Project->find('list', array(
			'fields' => array('Project.id', 'Project.id'),
			'conditions' => array(
				'Project.prj_name' => $row[$this->indexes['survey_name']],
				'Project.group_id' => $this->group_id
			)
		));
		if ($project_ids) {
			$like = array();
			foreach ($project_ids as $id) {
				$like[]['SurveyVisit.partner_user_id LIKE'] = $id.'-'.$user_id.'-%';
			}
			
			$survey_visit = $this->SurveyVisit->find('first', array(
				'fields' => array('SurveyVisit.id', 'SurveyVisit.hash', 'SurveyVisit.survey_id'),
				'conditions' => array(
					'OR' => $like,
					'SurveyVisit.partner_id' => $this->partner_id,
					'SurveyVisit.type' => array(SURVEY_COMPLETED, SURVEY_DUPE), // in case of dupe in endController, we still pay the user, this can be a hidden complete
					'SurveyVisit.survey_id' => $project_ids
				),
				'recursive' => -1
			));
			if ($survey_visit) {
				$project_id = $survey_visit['SurveyVisit']['survey_id'];
				$hash = $survey_visit['SurveyVisit']['hash'];
			}
		}
		
		return array(
			'survey_id' => $project_id,
			'user_id' => $user_id,
			'hash' => $hash,
			'timestamp' => $date_time
		);
	}
}