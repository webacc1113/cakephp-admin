<?php
class ReconcileAdwallTask extends Shell {	
	// initial cleanup of file
	function cleanFile($data) {
		$header = array_shift($data); // remove header
		$indexes = array();
		$indexes['timestamp'] = array_search('Credit Date/Time', $header);
		$indexes['user_id'] = array_search('SubID1', $header);
		$indexes['amount'] = array_search('Payout', $header);
		$indexes['offer_id'] = array_search('Offer ID', $header);
		$indexes['offer_title'] = array_search('Offer Name', $header);
		$indexes['status'] = array_search('Status', $header);
		$this->indexes = $indexes;
		
		foreach ($indexes as $val) {
			if ($val === false) {
				return false;
			}
		}
		
		$survey_users = array(
			'45001' => array(),
			'45002' => array()
		);
		
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
				continue;
			}
									
			if ($val[$indexes['status']] != 'Payable') {
				unset($data[$key]);
				continue;
			}
			
			// handle these special cases later
			$offer_id = $val[$indexes['offer_id']];
			$user_id = $val[$indexes['user_id']];
			
			if (in_array($val[$indexes['offer_id']], array('45001', '45002'))) {
				if (!isset($survey_users[$offer_id][$user_id])) {
					$survey_users[$offer_id][$user_id]['amount'] = 0;
				}
				$survey_users[$offer_id][$user_id]['amount'] += round(str_replace('$', '', $val[$indexes['amount']]) * 100 * 0.6);
				$survey_users[$offer_id][$user_id]['date'] = $val[$indexes['timestamp']];
				
				unset($data[$key]);
			}
		}
		$dates = $this->getMinMaxDates($data);
		
		if (!empty($survey_users)) {
			App::import('Model', 'User');
			App::import('Model', 'Offer');
			App::import('Model', 'Transaction');
			$this->User = new User;
			$this->Offer = new Offer;
			$this->Transaction = new Transaction;
			
			foreach ($survey_users as $adwall_project_id => $users) {
				$offer = $this->Offer->find('first', array(
					'conditions' => array(
						'Offer.partner' => 'adwall',
						'Offer.offer_partner_id' => $adwall_project_id
					)
				));
				foreach ($users as $user_id => $user_data) {
					$transaction = $this->Transaction->find('first', array(
						'fields' => array('SUM(amount) as sum_amount'),
						'conditions' => array(
							'Transaction.user_id' => $user_id,
							'Transaction.type_id' => TRANSACTION_OFFER, 
							'Transaction.linked_to_id' => $offer['Offer']['id'],
							'Transaction.executed >=' => $dates['min_date'],
							'Transaction.executed <=' => $dates['max_date'],
							'Transaction.deleted' => null,
						)
					));
					if ($user_data['amount'] > $transaction[0]['sum_amount']) {
						$user = $this->User->find('first', array(
							'recursive' => -1,
							'conditions' => array(
								'User.id' => $user_id
							),
							'fields' => array('id')
						));
						if (!$user) {
							continue;
						}
						$diff = $user_data['amount'] - $transaction[0]['sum_amount'];
						$data[] = array(
							$indexes['timestamp'] => $user_data['date'],
							$indexes['offer_id'] => $adwall_project_id,
							$indexes['offer_title'] => 'Missing Points for Adwall Offers',
							$indexes['amount'] => $diff,
							$indexes['user_id'] => $user['User']['id']
						);
					}
				}
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
		if (in_array($reconciliation_row['offer_id'], array('45001', '45002'))) {
			return true;
		}
		
		if (!isset($this->OfferRedemption)) {
			App::import('Model', 'OfferRedemption');
			App::import('Model', 'Offer');
			$this->OfferRedemption = new OfferRedemption;
			$this->Offer = new Offer;
		}
		
		$offer = $this->Offer->find('first', array(
			'conditions' => array(
				'Offer.partner' => OFFER_ADWALL,
				'Offer.offer_partner_id' => $reconciliation_row['offer_id'],
			)
		));
		if (!$offer) {
			$this->Offer->create();
			$this->Offer->save(array('Offer' => array(
				'partner' => OFFER_ADWALL,
				'offer_partner_id' => $reconciliation_row['offer_id'],
				'offer_title' => $reconciliation_row['offer_title'],
				'award' => $reconciliation_row['amount']
			)));
		}
		else {
			$count = $this->OfferRedemption->find('count', array(
				'conditions' => array(
					'OfferRedemption.partner' => OFFER_ADWALL,
					'OfferRedemption.offer_id' => $offer['Offer']['id'], 
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
		
		
		if ($time) {
			$min_date = date(DB_DATETIME, $min_date + 5 * 3600);
			$max_date = date(DB_DATETIME, $max_date + 5 * 3600);
		}
		else {
			$min_date = date(DB_DATE, $min_date + 5 * 3600);
			$max_date = date(DB_DATE, $max_date + 5 * 3600);
		}
			
		return array(
			'min_date' => $min_date,
			'max_date' => $max_date
		);
	}
	
	/* Format: 
		* Credit Date/Time
		* Offer ID
		* Offer Name
		* IP
		* Hostname
		* Payout
		* Status (Payable and Reversed:)
		* User ID(SubID1)
	*/
	function parseCsvRow($row) {
		if (in_array($row[$this->indexes['offer_id']], array('45001', '45002'))) {
			$amount = $row[$this->indexes['amount']];
		}
		else {
			$amount = round(str_replace('$', '', $row[$this->indexes['amount']]) * 100 * 0.6);
		}
		
		return array(
			'offer_id' => $row[$this->indexes['offer_id']],
			'user_id' => $row[$this->indexes['user_id']],
			'amount' => $amount,
			'offer_title' => $row[$this->indexes['offer_title']],
			'timestamp' => strtotime($row[$this->indexes['timestamp']]) + 5 * 3600 // adjust hours 5 hours - edt vs est?
		);
	}
}