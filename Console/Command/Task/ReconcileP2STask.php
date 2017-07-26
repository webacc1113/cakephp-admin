<?php

class ReconcileP2STask extends Shell {	
	
	// initial cleanup of file
	function cleanFile($data) {
		$header = array_shift($data); // remove header
		$indexes = array();
		$indexes['user_id_and_hash'] = array_search('Supplier Sub ID', $header);
		$indexes['timestamp'] = array_search('Approval Date', $header);
		$indexes['partner_transaction_id'] = array_search('Transaction Id', $header);
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
		if (!isset($this->RouterLog)) {
			App::import('Model', 'RouterLog');
			$this->RouterLog = new RouterLog;
		}
		
		$count = $this->RouterLog->find('count', array(
			'conditions' => array(
				'RouterLog.source' => 'p2s',
				'RouterLog.hash' => $reconciliation_row['hash'],
				'RouterLog.survey_id' => $reconciliation_row['survey_id'],
				'RouterLog.user_id' => $reconciliation_row['user_id'],
				'RouterLog.partner_transaction_id' => $reconciliation_row['partner_transaction_id'],
			)
		));
		
		// this transaction_id has been found
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
	
	/* Format: 
		* Supplier Sub ID
		* Click Date
		* Approval Date
		* Average Approval Time
	 */
	function parseCsvRow($row) {
		list($user_id, $hash) = explode('@@', $row[$this->indexes['user_id_and_hash']]);
		$survey_id = Utils::parse_project_id_from_hash($hash);
		
		if (empty($row[$this->indexes['timestamp']])) {
			$date_time = '0000-00-00 00:00:00';
		}
		else {
			// convert timestamp to utc
			$date_time = strtotime($row[$this->indexes['timestamp']]);
			$date_time = Utils::change_tz_to_utc(date(DB_DATETIME, $date_time), DB_DATETIME, 'America/Chicago');
		}
		
		
		return array(
			'hash' => $hash,
			'survey_id' => $survey_id,
			'user_id' => $user_id,
			'timestamp' => $date_time,
			'partner_transaction_id' => $row[$this->indexes['partner_transaction_id']]
		);
	}
}