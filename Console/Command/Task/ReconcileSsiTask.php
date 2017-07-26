<?php

class ReconcileSsiTask extends Shell {

	// initial cleanup of file
	function cleanFile($data) {
		$models_to_load = array('Partner', 'RouterLog', 'SurveyVisit', 'Transaction');
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
		$indexes['timestamp'] = array_search('timestamp_result', $header);
		$indexes['hash'] = array_search('source_data', $header);
		$indexes['status'] = array_search('Result', $header);
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
	
	public function missing_user($reconciliation_row) {
		
		// find the missing hash in 30 min interval
		$survey_visit = $this->SurveyVisit->find('first', array(
			'fields' => array('SurveyVisit.partner_user_id'),
			'conditions' => array(
				'SurveyVisit.hash' => $reconciliation_row['hash'],
				'SurveyVisit.survey_id' => $reconciliation_row['survey_id'],
				'SurveyVisit.partner_id' => $this->partner_id,
				'SurveyVisit.created >=' => date(DB_DATETIME, strtotime($reconciliation_row['timestamp']) - 1800),
				'SurveyVisit.created <=' => date(DB_DATETIME, strtotime($reconciliation_row['timestamp']) + 1800),
			),
			'recursive' => -1
		));
		if (!$survey_visit) {
			return false;
		}
		
		$user_id = explode('-', $survey_visit['SurveyVisit']['partner_user_id']);
		return $user_id[1];
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
			
			// ssi timezone is UTC -5, so we convert it to UTC
			$date_time = strtotime($row[$this->indexes['timestamp']]) + 18000;
			$date_time = date(DB_DATETIME, $date_time);
		}
		
		$survey_id = Utils::parse_project_id_from_hash($row[$this->indexes['hash']]);
		
		// try to find user_id
		$user_id = '0';
		$router_log = $this->RouterLog->find('first', array(
			'conditions' => array(
				'RouterLog.hash' => $row[$this->indexes['hash']],
				'RouterLog.source' => 'ssi'
			)
		));
		if ($router_log) {
			$user_id = $router_log['RouterLog']['user_id'];
		}
		else {
			$survey_visit = $this->SurveyVisit->find('first', array(
				'fields' => array('SurveyVisit.partner_user_id'),
				'conditions' => array(
					'SurveyVisit.hash' => $row[$this->indexes['hash']],
					'SurveyVisit.type' => array(SURVEY_COMPLETED, SURVEY_DUPE), // in case of dupe in endController, we still pay the user, this can be a hidden complete
					'SurveyVisit.survey_id' => $survey_id,
					'SurveyVisit.partner_id' => $this->partner_id,
				),
				'recursive' => -1
			));
			if ($survey_visit) {
				$user_id = explode('-', $survey_visit['SurveyVisit']['partner_user_id']);
				$user_id = $user_id[1];
			}
		}
	
		return array(
			'hash' => $row[$this->indexes['hash']],
			'status' => $row[$this->indexes['status']],
			'survey_id' => $survey_id,
			'user_id' => $user_id,
			'timestamp' => $date_time
		);
	}
}