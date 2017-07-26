<?php
class ReconcilePeanutlabsTask extends Shell {
	// initial cleanup of file
	function cleanFile($data) {
		$header = array_shift($data); // remove header
		$indexes = array();
		$indexes['timestamp'] = array_search('Timestamp', $header);
		$indexes['user_id'] = array_search('User ID', $header);
		$indexes['mv_revenue'] = array_search('MV Revenue', $header);
		$indexes['amount'] = array_search('Panelist Payout', $header);
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
		
		$count = $this->OfferRedemption->find('count', array(
			'conditions' => array(
				'OfferRedemption.partner' => OFFER_PEANUTLABS,
				'OfferRedemption.xtid' => $reconciliation_row['xtid'],
				'OfferRedemption.user_id' => $reconciliation_row['user_id']
			)
		));
		if ($count) {
			return false;
		}
		
		$offer = $this->Offer->find('first', array(
			'conditions' => array(
				'Offer.partner' => OFFER_PEANUTLABS,
				'Offer.offer_partner_id is NULL',
			)
		));
		if (!$offer) {
			$this->Offer->create();
			$this->Offer->save(array('Offer' => array(
				'partner' => OFFER_PEANUTLABS,
				'offer_partner_id' => null,
				'offer_title' => 'Peanutlabs missing offers.',
				'award' => $reconciliation_row['amount']
			)));
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
		* Timestamp
		* Transaction ID
		* User ID
		* Panelist Payout
		* MV Revenue
	*/
	function parseCsvRow($row) {
		$total_revenue = $row[$this->indexes['amount']] + $row[$this->indexes['mv_revenue']];
		$amount = round($total_revenue * 100 * 0.6);
		
		return array(
			'user_id' => $row[$this->indexes['user_id']],
			'amount' => $amount,
			'xtid' => $row[$this->indexes['xtid']],
			'timestamp' => strtotime($row[$this->indexes['timestamp']])
		);
	}
}