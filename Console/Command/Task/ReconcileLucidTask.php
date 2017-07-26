<?php

class ReconcileLucidTask extends Shell {
		
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
		
		$header = array_shift($data); // remove header
		$indexes = array();
		$indexes['survey_id'] = array_search('Complete Survey Number', $header);
		$indexes['timestamp'] = array_search('Complete Time', $header);
		$indexes['hash'] = array_search('MID', $header);
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
		App::import('Model', 'SurveyVisit');
		$this->SurveyVisit = new SurveyVisit;
		
		// find the missing hash in 30 min interval, I was able to find a missing hash in a diff of 13 minutes
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
		App::import('Model', 'SurveyVisit');
		$this->SurveyVisit = new SurveyVisit;
		
		$survey_id = Utils::parse_project_id_from_hash($row[$this->indexes['hash']]);
		$survey_visit = $this->SurveyVisit->find('first', array(
			'fields' => array('SurveyVisit.partner_user_id'),
			'conditions' => array(
				'SurveyVisit.hash' => $row[$this->indexes['hash']],
				'SurveyVisit.partner_id' => $this->partner_id,
				'SurveyVisit.type' => array(SURVEY_COMPLETED, SURVEY_DUPE), // in case of dupe in endController, we still pay the user, this can be a hidden complete
				'SurveyVisit.survey_id' => $survey_id
			),
			'recursive' => -1
		));
		
		$user_id = '0';
		if ($survey_visit) {
			$user_id = explode('-', $survey_visit['SurveyVisit']['partner_user_id']);
			$user_id = $user_id[1];
		}
		
		if (empty($row[$this->indexes['timestamp']])) {
			$date_time = '0000-00-00 00:00:00';
		}
		else {
			// convert timestamp to utc
			$date_time = date(DB_DATETIME, strtotime($row[$this->indexes['timestamp']]));
			$date_time = Utils::change_tz_to_utc($date_time, DB_DATETIME, 'America/Chicago');
		}
		
		
		return array(
			'hash' => $row[$this->indexes['hash']],
			'survey_id' => $survey_id,
			'user_id' => $user_id,
			'timestamp' => $date_time,
		);
	}
}