<?php
class ReconcileAdgateTask extends Shell {
	// initial cleanup of file
	function cleanFile($data) {
		$header = array_shift($data); // remove header
		$indexes = array();
		$indexes['timestamp'] = array_search('created_at', $header);
		$indexes['user_id'] = array_search('User ID', $header);
		$indexes['amount'] = array_search('Payout', $header);
		$indexes['offer_id'] = array_search('Offer ID', $header);
		$indexes['xtid'] = array_search('Transaction ID', $header);
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
	
	// returns false if no transaction_ids are found; returns 0
	// IMPORTANT: for offers, ONLY reconcile after October 1st, 2015!
	public function needsReconciliationFlag($reconciliation_row) {
		if ($reconciliation_row['timestamp'] < strtotime('10/01/2015')) {
			return false;
		}
		if (!isset($this->OfferRedemption)) {
			App::import('Model', 'OfferRedemption');
			App::import('Model', 'Offer');
			$this->OfferRedemption = new OfferRedemption;
			$this->Offer = new Offer;
		}
		
		$offer = $this->Offer->find('first', array(
			'fields' => array('Offer.id'),
			'conditions' => array(
				'Offer.partner' => OFFER_ADGATE,
				'Offer.offer_partner_id' => $reconciliation_row['offer_id']
			)
		));
		if (!$offer) {
			$this->Offer->create();
			$this->Offer->save(array('Offer' => array(
				'partner' => OFFER_ADGATE,
				'offer_partner_id' => $reconciliation_row['offer_id'],
				'offer_title' => 'Adgate offer #' . $reconciliation_row['offer_id'],
				'award' => $reconciliation_row['amount']
			)));
		}
		else {
			$count = $this->OfferRedemption->find('count', array(
				'conditions' => array(
					'OfferRedemption.partner' => OFFER_ADGATE,
					'OfferRedemption.xtid' => $reconciliation_row['xtid'],
					'OfferRedemption.user_id' => $reconciliation_row['user_id']
				)
			));
			if ($count) {
				return false;
			}
		}
		
		return true;
	}
	
	function getMinMaxDates($data, $time = false) {
		$min_date = $max_date = null;
		
		foreach ($data as $row) {
			if (empty($row[$this->indexes['timestamp']])) {
				continue;
			}
			
			$datetime = strtotime($row[$this->indexes['timestamp']]);
			
			if (is_null($min_date)) {
				$min_date = $datetime; 
			}
			if (is_null($max_date)) {
				$max_date = $datetime;
			}
			if ($min_date > $datetime) {
				$min_date = $datetime;
			}
			if ($max_date < $datetime) {
				$max_date = $datetime;
			}
		}
		
		return array(
			'min_date' => date(DB_DATE, $min_date),
			'max_date' => date(DB_DATE, $max_date)
		);
	}
	
	/* Format: 
		* created_at
		* Payout
		* Offer ID
		* Transaction ID
		* User ID
	*/
	function parseCsvRow($row) {
		$amount = round($row[$this->indexes['amount']] * 100 * 0.6);
		
		return array(
			'offer_id' => $row[$this->indexes['offer_id']],
			'user_id' => $row[$this->indexes['user_id']],
			'amount' => $amount,
			'xtid' => $row[$this->indexes['xtid']],
			'timestamp' => strtotime($row[$this->indexes['timestamp']])
		);
	}
}