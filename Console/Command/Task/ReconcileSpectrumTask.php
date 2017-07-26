<?php

class ReconcileSpectrumTask extends Shell {

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
		$header = array_map('trim', $header);
		$indexes = array();
		$indexes['user_id'] = array_search('Supplier Respondent ID', $header);
		$indexes['timestamp'] = array_search('PS Exit Datetime', $header);
		$indexes['hash'] = array_search('Supplier Sid', $header);
		$indexes['status'] = array_search('Respondent Status Description', $header);
		
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

	public function needsReconciliationFlag($reconciliation_row) {
		App::import('Model', 'SurveyVisit');
		$this->SurveyVisit = new SurveyVisit;
		$count = $this->SurveyVisit->find('count', array(
			'conditions' => array(
				'SurveyVisit.hash' => $reconciliation_row['hash'],
				'SurveyVisit.type' => array(SURVEY_COMPLETED, SURVEY_DUPE),
				'SurveyVisit.survey_id' => $reconciliation_row['survey_id'],
				'SurveyVisit.partner_id' => $this->partner_id,
			),
			'recursive' => -1
		));
		if ($count > 0) {
			return false;
		}
		
		return true;
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
		$survey_id = Utils::parse_project_id_from_hash($row[$this->indexes['hash']]);
		if (empty($row[$this->indexes['timestamp']])) {
			$date_time = '0000-00-00 00:00:00';
		}
		else {
			
			// PS is providing the date in a weired format e.g 11-01-2016 : 11:53:05
			// removing the extra colon between date and time
			$date_time = str_replace(' : ', ' ', $row[$this->indexes['timestamp']]);
			$date_time_arr = explode('-', $date_time);
			$month = $date_time_arr[0];
			$day = $date_time_arr[1];
			$date_time_arr[0] = $day;
			$date_time_arr[1] = $month;
			$date_time = implode('-', $date_time_arr);
			
			// PureSpectrum timezone is UTC -7 hours, so we convert it to UTC
			$date_time = strtotime($date_time) + (60 * 60 * 7);
			$date_time = date(DB_DATETIME, $date_time);
		}
		
		return array(
			'hash' => $row[$this->indexes['hash']],
			'survey_id' => $survey_id,
			'user_id' => $row[$this->indexes['user_id']],
			'timestamp' => $date_time,
		);
	}
}
