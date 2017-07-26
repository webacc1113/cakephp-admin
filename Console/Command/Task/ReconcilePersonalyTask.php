<?php
class ReconcilePersonalyTask extends Shell {
	// initial cleanup of file
	function cleanFile($data) {
		$header = array_shift($data); // remove header
		$indexes = array();
		$indexes['timestamp'] = array_search('Lead Date', $header);
		$indexes['user_id'] = (array_search('User ID', $header) !== false) ? array_search('User ID', $header) : array_search('App User ID', $header);
		$indexes['amount'] = array_search('Virtual Currency', $header);
		$indexes['offer_id'] = array_search('Offer ID', $header);
		$indexes['lead_id'] = array_search('Lead ID', $header);
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
		
		// personaly seems to rely on both offer_id and lead_id... 
		// determine if this offer exists yet
		$offer_by_id = $this->Offer->find('first', array(
			'fields' => array('Offer.id'),
			'conditions' => array(
				'Offer.partner' => OFFER_PERSONALY,
				'Offer.offer_partner_id' => $reconciliation_row['offer_id'],
			)
		));
		
		if (empty($offer_by_id)) {
			$offer_by_lead = $this->Offer->find('first', array(
				'fields' => array('Offer.id'),
				'conditions' => array(
					'Offer.partner' => OFFER_PERSONALY,
					'Offer.offer_partner_id' => $reconciliation_row['lead_id'],
				)
			));
			
			if (empty($offer_by_lead)) {
				$this->Offer->create();
				$this->Offer->save(array('Offer' => array(
					'partner' => OFFER_PERSONALY,
					'offer_partner_id' => $reconciliation_row['offer_id'],
					'offer_title' => 'Personaly offer #' . $reconciliation_row['offer_id'],
					'award' => $reconciliation_row['amount']
				)));
				return true;
			}
		}
		
		
		$count = $this->OfferRedemption->find('count', array(
			'conditions' => array(
				'OfferRedemption.partner' => OFFER_PERSONALY,
				'OfferRedemption.xoid' => !empty($offer_by_id) ? $reconciliation_row['offer_id'] : $reconciliation_row['lead_id'],
				'OfferRedemption.user_id' => $reconciliation_row['user_id']
			)
		));
		if ($count) {
			return false;
		}
		
		return true;
	}
	
	// personaly uses dd/mm/yy hh:ii format which confuses php's local settings
	function parseDate($clickdate) {
		list($date, $time) = explode(' ', $clickdate);
		list($hour, $minute) = explode(':', $time); 
		list($day, $month, $year) = explode('/', $date); 
		return date(DB_DATETIME, mktime($hour, $minute, '00', $month, $day, $year));
	}
	
	function getMinMaxDates($data) {
		$min_date = $max_date = null;
		foreach ($data as $row) {
			if (empty($row[$this->indexes['timestamp']])) {
				continue;
			}
			
			// format is in dd/mm/yy hh:ii, so we gotta adjust
			$clickdate = $row[$this->indexes['timestamp']]; 
			$datetime = strtotime($this->parseDate($clickdate));
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
		* Lead ID
		* Click ID
		* Offer ID
		* Survey ID
		* App User ID
		* Pub Lead Rate
		* Virtual Currency
		* Click Date
		* Lead Date
		* Country Code
		* Click IP
		* Support
	*/
	function parseCsvRow($row) {
		return array(
			'offer_id' => !empty($row[$this->indexes['offer_id']]) ? $row[$this->indexes['offer_id']] : $row[$this->indexes['lead_id']],
			'xoid' => !empty($row[$this->indexes['offer_id']]) ? $row[$this->indexes['offer_id']] : $row[$this->indexes['lead_id']],
			'lead_id' => $row[$this->indexes['lead_id']],
			'user_id' => $row[$this->indexes['user_id']],
			'amount' => round($row[$this->indexes['amount']]),
			'offer_title' => 'Offer',
			'timestamp' => strtotime($this->parseDate($row[$this->indexes['timestamp']]))
		);
	}
}