<?php

class ReconcileOffertoroTask extends Shell {	
	// initial cleanup of file
	function cleanFile($data) {
		$header = array_shift($data); // remove header
		$indexes = array();
		$indexes['timestamp'] = array_search('Date', $header);
		$indexes['user_id'] = array_search('User ID', $header);
		$indexes['amount'] = array_search('Amount', $header);
		$indexes['offer_id'] = array_search('Offer ID', $header);
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
			'conditions' => array(
				'Offer.partner' => OFFER_OFFERTORO,
				'Offer.offer_partner_id' => $reconciliation_row['offer_id'],
			)
		));
		if (!$offer) {
			$this->Offer->create();
			$this->Offer->save(array('Offer' => array(
				'partner' => OFFER_OFFERTORO,
				'offer_partner_id' => $reconciliation_row['offer_id'],
				'offer_title' => $reconciliation_row['offer_title'],
				'award' => $reconciliation_row['amount']
			)));
		}
		else {
			$count = $this->OfferRedemption->find('count', array(
				'conditions' => array(
					'OfferRedemption.partner' => OFFER_OFFERTORO,
					'OfferRedemption.offer_id' => $offer['Offer']['id'], 
					'OfferRedemption.user_id' => $reconciliation_row['user_id']
				)
			));
			if ($count > 0) {
				return false;
			}
		}
		
		return true;
	}
	
		
	function getMinMaxDates($data) {
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
		* Offer ID
		* User ID
		* Pubs payout
		* Amount
		* Date
	 */
	function parseCsvRow($row) {
		return array(
			'offer_id' => $row[$this->indexes['offer_id']],
			'user_id' => $row[$this->indexes['user_id']],
			'amount' => $row[$this->indexes['amount']],
			'offer_title' => 'Offertoro offer #' . $row[$this->indexes['offer_id']],
			'timestamp' => strtotime($row[$this->indexes['timestamp']])
		);
	}
}