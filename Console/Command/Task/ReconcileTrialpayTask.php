<?php

class ReconcileTrialpayTask extends Shell {	
	// initial cleanup of file
	function cleanFile($data) {
		$header = array_shift($data); // remove header
		
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
	
	// returns false if no transaction_ids are found; returns 0
	public function needsReconciliationFlag($reconciliation_row, $source) {
		
		if (!isset($this->OfferRedemption)) {
			App::import('Model', 'OfferRedemption');
			App::import('Model', 'Offer');
			$this->OfferRedemption = new OfferRedemption;
			$this->Offer = new Offer; 
		}
		
		return false;
	}
	
	function getMinMaxDates($data) {
		$first_row = current($data);
		$last_row = end($data);
		$max_transaction_date = date(DB_DATE, strtotime($first_row[0]));
		$min_transaction_date = date(DB_DATE, strtotime($last_row[0]));
		
		// we assume the files are in some kind of order; either asc or descending
		if ($max_transaction_date < $min_transaction_date) {
			$min_date = $max_transaction_date;
			$max_date = $min_transaction_date;
		}
		else {
			$max_date = $max_transaction_date;
			$min_date = $min_transaction_date;
		}
		return array('min_date' => $min_date, 'max_date' => $max_date);
	}
	
	/* Format: 
		* Timestamp
		* Reference #
		* 
	 */
	function parseCsvRow($row) {
		list($user_id, $hash) = explode('@@', $row[0]);
		$survey_id = Utils::parse_project_id_from_hash($hash);
		return array(
			'hash' => $hash,
			'survey_id' => $survey_id,
			'user_id' => $user_id,
			'timestamp' => strtotime($row[1])
		);
	}
}