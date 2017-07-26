<?php

class ReconcileCintTask extends Shell {	
	// initial cleanup of file
	function cleanFile($data) {
		App::import('Model', 'Partner');
		$this->Partner = new Partner;

		$partner = $this->Partner->find('first', array(
			'conditions' => array(
				'Partner.key' => array('mintvine'),
				'Partner.deleted' => false
			),
			'fields' => array('Partner.id')
		));
		$this->partner_id = $partner['Partner']['id']; //mintvine partner 

		$header = array_shift($data);
		$indexes = array();
		$indexes['user_id'] = array_search('Member id', $header);
		$indexes['timestamp'] = array_search('Survey completed', $header);
		$indexes['cint_survey_id'] = array_search('Project id', $header);
		$indexes['status'] = array_search('Respondent status', $header);
		$this->indexes = $indexes;
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
		App::import('Model', 'Transaction');
		$this->Transaction = new Transaction;
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
		$user_id = $row[$this->indexes['user_id']];
		$project_id = '';
		$hash = '';
		if (empty($row[$this->indexes['timestamp']])) {
			$date_time = '0000-00-00 00:00:00';
		}
		else {
			
			// cint timezone is UTC+2, so we convert it to UTC
			$date_time = strtotime($row[$this->indexes['timestamp']]) - 7200;
			$date_time = date(DB_DATETIME, $date_time);
		}
		
		$models_to_load = array('CintSurvey', 'SurveyVisit', 'QueryProfile');
		foreach ($models_to_load as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		$query_profile = $this->QueryProfile->find('first', array(
			'fields' => array('QueryProfile.country'),
			'conditions' => array(
				'QueryProfile.user_id' => $user_id
			)
		));
		if ($query_profile) {
			$cint_survey = $this->CintSurvey->find('first', array(
				'fields' => array('CintSurvey.survey_id'),
				'conditions' => array(
					'CintSurvey.cint_survey_id' => $row[$this->indexes['cint_survey_id']],
					'CintSurvey.country' => $query_profile['QueryProfile']['country'],
				),
				'fields' => array('CintSurvey.survey_id'),
				'recursive' => -1
			));
			if ($cint_survey) {
				$project_id = $cint_survey['CintSurvey']['survey_id'];
				if ($date_time != '0000-00-00 00:00:00') {					
					$survey_visit = $this->SurveyVisit->find('first', array(
						'fields' => array('SurveyVisit.hash'),
						'conditions' => array(
							'SurveyVisit.partner_user_id LIKE ' => $project_id.'-'.$user_id.'-%',
							'SurveyVisit.type' => array(SURVEY_COMPLETED, SURVEY_DUPE), // in case of dupe in endController, we still pay the user, this can be a hidden complete
							'SurveyVisit.survey_id' => $project_id,
							'SurveyVisit.partner_id' => $this->partner_id,
							'SurveyVisit.created >=' => date(DB_DATETIME, strtotime($date_time) - 600),
							'SurveyVisit.created <=' => date(DB_DATETIME, strtotime($date_time) + 600),
						),
						'recursive' => -1
					));
					if ($survey_visit) {
						$hash = $survey_visit['SurveyVisit']['hash'];
					}
				}
			}
		}
		
		return array(
			'survey_id' => $project_id,
			'user_id' => $user_id,
			'hash' => $hash,
			'timestamp' => $date_time,
		);
	}
}