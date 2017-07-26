<?php

class UserAnalyzerTask extends Shell {
	public $uses = array('IpProxy', 'QueryProfile', 'UserIp', 'User', 'GeoZip', 'SurveyUserVisit', 'SurveyVisit', 'Transaction', 'GeoCountry', 'GeoState', 'UserAnalysis', 'Setting', 'TwilioNumber');
	
	/* 
	 *
	 */
    public function analyze($user, $transaction_id = null) {
		$weights = unserialize(USER_ANALYSIS_WEIGHTS);
		$total_of_weights = array_sum($weights); 
		
		$user_analysis = array(
			'countries' => null,
			'referred' => null,
			'language' => null,
			'locations' => null,
			'logins' => null,
			'proxy' => null,
			'distance' => null,
			'frequency' => null,
			'minfraud' => null,
			/* 'profile' => null, */
			'rejected_transactions' => null,
			'offerwall' => null,
			'poll_revenue' => null,
			'mobile_verified' => null,
			'duplicate_number' => null,
			'ip_address' => null,
			'payout' => null /* ,
			'nonrevenue' => null */
		);
		// is referring user hellbanned?
		if ($user['User']['referred_by'] > 0) {
			$referred_user = $this->User->find('first', array(
				'recursive' => -1,
				'fields' => array('hellbanned'),
				'conditions' => array(
					'User.id' => $user['User']['referred_by']
				)
			));
			if ($referred_user['User']['hellbanned']) {
				$user_analysis['referred'] = $weights['referral'];
			}
			else {
				$user_analysis['referred'] = '0';
			}
		}
		
		if ($user['User']['is_mobile_verified'] == true) {
			$user_analysis['mobile_verified'] = 0;
		}
		else {
			$user_analysis['mobile_verified'] = null;			
		}
		
		$examine_http_languages = $examine_countries = $examine_frequencies = $examine_asian_timezone = $examine_states = $examine_timezones = $examine_proxies = $examine_minfraud = array();
		
		if (!empty($user['QueryProfile']['country'])) {
			$examine_countries[] = $user['QueryProfile']['country'];
		}
		$examine_states[$user['QueryProfile']['state']] = 1;
		
		$examine_login_ips = array();
		$login_ips_conditions = array(
			'UserIp.user_id' => $user['User']['id'],
			'UserIp.type' => array('login', 'register')
		);
			
		if (empty($transaction_id)) {
			$last_withdrawal = $this->Transaction->find('first', array(
				'fields' => array(
					'Transaction.created'
				),
				'conditions' => array(
					'Transaction.user_id' => $user['User']['id'],
					'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
					'Transaction.status' => TRANSACTION_APPROVED,
					'Transaction.deleted' => null,
				),
				'order' => 'Transaction.id DESC'
			));
			if ($last_withdrawal) {
				echo 'Since '.$last_withdrawal['Transaction']['created']."\n";
				$login_ips_conditions[] = array(
					'UserIp.created >' => $last_withdrawal['Transaction']['created']
				);
			}
		}
		else {
			$last_withdrawal = $this->Transaction->find('first', array(
				'fields' => array(
					'Transaction.id', 'Transaction.created'
				),
				'conditions' => array(
					'Transaction.user_id' => $user['User']['id'],
					'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
					'Transaction.status' => TRANSACTION_APPROVED,
					'Transaction.id <' => $transaction_id,
					'Transaction.deleted' => null,
				),
				'order' => 'Transaction.id DESC'
			));
			if ($last_withdrawal) {
				echo 'Since '.$last_withdrawal['Transaction']['created']."\n";
				$login_ips_conditions[] = array(
					'UserIp.created >' => $last_withdrawal['Transaction']['created']
				);
			}
			$current_transaction = $this->Transaction->find('first', array(
				'conditions' => array(
					'Transaction.id' => $transaction_id,
					'Transaction.deleted' => null,
				)
			));
			if ($current_transaction) {
				$login_ips_conditions[] = array(
					'UserIp.created <' => $current_transaction['Transaction']['created']
				);
				if (abs($current_transaction['Transaction']['amount']) >= 50000) {
					$user_analysis['payout'] = $weights['payout']; // large payouts need to be flagged
				}
			}
		}
		if (!empty($last_withdrawal)) {
			$transaction_offerwall_conditions = array(
				'Transaction.user_id' => $user['User']['id'],
				'Transaction.type_id' => array(TRANSACTION_OFFER, TRANSACTION_SURVEY),
				'Transaction.amount >' => '0',
				'Transaction.status' => TRANSACTION_APPROVED,
				'Transaction.created >' => $last_withdrawal['Transaction']['created']
			);
		}
		else {
			$transaction_offerwall_conditions = array(
				'Transaction.user_id' => $user['User']['id'],
				'Transaction.type_id' => array(TRANSACTION_OFFER, TRANSACTION_SURVEY),
				'Transaction.amount >' => '0',
				'Transaction.status' => TRANSACTION_APPROVED,
				'Transaction.deleted' => null,
			);
		}
		
		$transaction_offerwalls = $this->Transaction->find('all', array(
			'conditions' => $transaction_offerwall_conditions,
			'fields' => array(
				'sum(amount) as revenue',
				'type_id'
			),
			'recursive' => -1,
			'group' => array(
				'type_id'
			)
		));
		
		
		
		if ($transaction_offerwalls) {
			$survey_transaction_reward = 0;
			$offer_transaction_reward = 0;
			foreach ($transaction_offerwalls as $transaction_offerwall) {
				if ($transaction_offerwall['Transaction']['type_id'] == TRANSACTION_SURVEY) {
					$survey_transaction_reward = $transaction_offerwall['0']['revenue'];
				}
				elseif ($transaction_offerwall['Transaction']['type_id'] == TRANSACTION_OFFER) {
					$offer_transaction_reward = $transaction_offerwall['0']['revenue'];
				}
			}
			
			$total_reward = $survey_transaction_reward + $offer_transaction_reward;
			$offer_contribution = round(100 * ($offer_transaction_reward/$total_reward), 2);
			if ($offer_contribution == 100) {
				$user_analysis['offerwall'] =  $weights['offerwall'];
			}
		}
		
		if (!empty($last_withdrawal)) {
			$transaction_poll_conditions = array(
				'Transaction.user_id' => $user['User']['id'],
				'Transaction.type_id' => array(TRANSACTION_POLL, TRANSACTION_POLL_STREAK, TRANSACTION_SURVEY),
				'Transaction.amount >' => '0',
				'Transaction.status' => TRANSACTION_APPROVED,
				'Transaction.created >' => $last_withdrawal['Transaction']['created']
			);
		}
		else {
			$transaction_poll_conditions = array(
				'Transaction.user_id' => $user['User']['id'],
				'Transaction.type_id' => array(TRANSACTION_POLL, TRANSACTION_POLL_STREAK, TRANSACTION_SURVEY),
				'Transaction.amount >' => '0',
				'Transaction.status' => TRANSACTION_APPROVED,
				'Transaction.deleted' => null,
			);
		}
		
		$transaction_polls = $this->Transaction->find('all', array(
			'conditions' => $transaction_poll_conditions,
			'fields' => array(
				'sum(amount) as revenue',
				'type_id'
			),
			'recursive' => -1,
			'group' => array(
				'type_id'
			)
		));
		
		
		
		if ($transaction_polls) {
			$survey_transaction_reward = 0;
			$poll_transaction_reward = 0;
			foreach ($transaction_polls as $transaction_poll) {
				if ($transaction_poll['Transaction']['type_id'] == TRANSACTION_SURVEY) {
					$survey_transaction_reward = $transaction_poll['0']['revenue'];
				}
				elseif ($transaction_poll['Transaction']['type_id'] == TRANSACTION_POLL) {
					$poll_transaction_reward = $transaction_poll['0']['revenue'];
				}
				elseif ($transaction_poll['Transaction']['type_id'] == TRANSACTION_POLL_STREAK) {
					$poll_transaction_reward = $transaction_poll['0']['revenue'];
				}
			}
			
			$total_reward = $survey_transaction_reward + $poll_transaction_reward;
			$poll_contribution = round(100 * ($poll_transaction_reward/$total_reward), 2);
			if ($poll_contribution == 100) {
				$user_analysis['poll_revenue'] =  $weights['poll_revenue'];
			}
		}
		
		// analyze frequency
		$user_created = $this->User->find('first', array(
			'conditions' => array(
				'User.id' => $user['User']['id']
			),
			'recursive' => -1,
			'fields' => array('User.created')
		));
		if ($user_created) {
			$examine_frequencies[] = $user_created['User']['created'];
		}
		if ($last_withdrawal) {
			$examine_frequencies[] = $last_withdrawal['Transaction']['created'];
		}
		if (!empty($examine_frequencies)) {
			foreach ($examine_frequencies as $examine_frequency) {
				if (strtotime($examine_frequency) >= strtotime('-7 days')) {
					$user_analysis['frequency'] = $weights['frequency'] / 2; 
				}
				if (strtotime($examine_frequency) >= strtotime('-3 days')) {
					$user_analysis['frequency'] = $weights['frequency']; 
				}
			}
		}
		
		// analyze revenues
		if (empty($transaction_id)) {
			// get the transactions for this user
			$transactions = $this->Transaction->find('all', array(
				'recursive' => -1,
				'conditions' => array(
					'Transaction.user_id' => $user['User']['id'],
					'Transaction.amount >' => '0',
					'Transaction.status' => TRANSACTION_APPROVED,
					'Transaction.deleted' => null,
				),
				'order' => 'Transaction.id DESC'
			));
		}
		else {
			if ($current_transaction) {
				$transactions = $this->Transaction->find('all', array(
					'recursive' => -1,
					'conditions' => array(
						'Transaction.user_id' => $user['User']['id'],
						'Transaction.amount >' => '0',
						'Transaction.status' => TRANSACTION_APPROVED,
						'Transaction.id <' => $current_transaction['Transaction']['id'],
						'Transaction.deleted' => null,
					),
					'order' => 'Transaction.id DESC'
				));
				$withdrawal_amount = $current_transaction['Transaction']['amount'] * -1;
			}
		}
		if ($transactions) {
			$sum = $rev = 0;
			foreach ($transactions as $transaction) {
				if (isset($withdrawal_amount) && $withdrawal_amount < ($sum + $transaction['Transaction']['amount'])) {
					break;
				}
				if (in_array($transaction['Transaction']['type_id'], array(TRANSACTION_SURVEY, TRANSACTION_OFFER, TRANSACTION_GROUPON))) {
					$rev = $rev + $transaction['Transaction']['amount'];
				}
				$sum = $sum + $transaction['Transaction']['amount'];
			}
			$nonrevpct = 1 - ($rev / $sum);
			if ($nonrevpct >= 0.90) {
				// 75 - 100% 
				// royk: this won't be necessary once nonrev weightings come back
				if (isset($weights['nonrevenue'])) {
					$user_analysis['nonrevenue'] = $weights['nonrevenue'];
				}
			}
		}
		
		$visits = $this->UserIp->find('list', array(
			'fields' => array('id', 'ip_address'),
			'conditions' => $login_ips_conditions
		));
		if ($visits) {
			$examine_login_ips = $visits;
		}
			
		$ip_conditions = array(
			'UserIp.user_id' => $user['User']['id']
		);
		if (!empty($last_withdrawal)) {
			$ip_conditions[] = array(
				'UserIp.created >' => $last_withdrawal['Transaction']['created']
			);
		}
		$visits = $this->UserIp->find('all', array(
			'conditions' => $ip_conditions,
			'order' => 'UserIp.id DESC'
		));
		
		echo 'Total '.count($visits)."\n";
		
		if (!$visits) {
			return false;
		}
		
		$setting = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'proxy.service'
			)
		));
		if ($setting && $setting['Setting']['value'] == 'ipintel') {
			$proxy_threshold = IPINTEL_PROXY_THRESHOLD;
			$proxy_service = 'ipintel';
		}
		else {
			$proxy_threshold = MAXMIND_PROXY_THRESHOLD;
			$proxy_service = 'maxmind';
		}
		
		foreach ($visits as $visit) {
			if (!empty($visit['UserIp']['country'])) {
				$examine_countries[] = $visit['UserIp']['country'];
			}
			
			// examine timezones codes
			if (!empty($visit['UserIp']['timezone'])) {
				$zip = $this->GeoZip->find('first', array(
					'conditions' => array(
						'GeoZip.zipcode' => $user['QueryProfile']['postal_code'],
						'GeoZip.country_code' => 'US'
					)
				));
				if ($zip && !is_null($zip['GeoZip']['timezone'])) {
					if ($zip['GeoZip']['timezone'] == $visit['UserIp']['timezone']) {
						if (!isset($examine_timezones['yes'])) {
							$examine_timezones['yes'] = 0;
						}
						$examine_timezones['yes']++;
					}
					else {
						if (!isset($examine_timezones['no'])) {
							$examine_timezones['no'] = 0;
						}
						$examine_timezones['no']++;
					}
				}
			}
			
			// set asian timezone check
			if (!empty($visit['UserIp']['timezone'])) {
				$examine_asian_timezone[] = $visit['UserIp']['timezone'];
			}
			
			// examine languages
			if (!empty($visit['UserIp']['user_language'])) {
				$http_languages = Utils::http_languages($visit['UserIp']['user_language']);
				if (!empty($http_languages)) {
					foreach ($http_languages as $key => $score) {
						if ($score != 1) {
							continue;
						}
						$lang = strtolower($key);
						if (strpos($lang, '-') !== false) {
							list($lang, $whatever) = explode('-', $lang);
						}
						if (!isset($examine_http_languages[$lang])) {
							$examine_http_languages[$lang] = 0;
						}
						$examine_http_languages[$lang] = $examine_http_languages[$lang] + $score;
					}
				}
			}
			
			// states
			if (!empty($visit['UserIp']['state'])) {
				if (!isset($examine_states[$visit['UserIp']['state']])) {
					$examine_states[$visit['UserIp']['state']] = 0;
				}
				$examine_states[$visit['UserIp']['state']]++;
			}
			
			// if we haven't done a proxy check - build a fuller picture
			if (!in_array($visit['UserIp']['country'], array_keys(unserialize(SUPPORTED_COUNTRIES)))) {
				$proxyScore =  $this->IpProxy->getProxyScore($visit['UserIp']['ip_address'], $proxy_service);
				if ($proxyScore === false) {
					continue;
				}
				
				$this->UserIp->create();
				$this->UserIp->save(array('UserIp' => array(
					'id' => $visit['UserIp']['id'],
					'proxy' => $proxyScore >= $proxy_threshold
				)), true, array('proxy'));
				
				$examine_proxies[] = $proxyScore;
				$settings = $this->Setting->find('list', array(
					'fields' => array('Setting.name', 'Setting.value'),
					'conditions' => array(
						'Setting.name' => 'maxmind.license',
						'Setting.deleted' => false
					)
				));
				
				// check minfraud to see what it scores out - only score the most recent proxy fail value
				if ($proxyScore >= $proxy_threshold && empty($examine_minfraud)) {
					if (is_null($visit['UserIp']['fraud_score'])) {
						$url = 'https://minfraud.maxmind.com/app/ccv2r?'
							.'l='.$settings['maxmind.license']
							.'&i='.$visit['UserIp']['ip_address']
							.'&country='.(!isset($user['GeoCountry']['ccode']) || empty($user['GeoCountry']['ccode']) ? 'US': $user['GeoCountry']['ccode'])
							.'&domain='.substr(strrchr($user['User']['email'], "@"), 1)
							.'&emailMD5='.md5(strtolower($user['User']['email']))
							.'&postal='.$user['QueryProfile']['postal_code']; 
						$http = new HttpSocket(array(
							'timeout' => 2,
							'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
						));
						try {
							$results = $http->get($url);
							$results = explode(';', $results->body);
							if ($results[0] != 'BadRequest') {
								$fraudresults = array();
								foreach ($results as $key => $val) {
									$v = explode('=', $val);	
									$fraudresults[$v[0]] = $v[1];
								}
								if (isset($fraudresults['riskScore'])) {
									$this->UserIp->create();
									$this->UserIp->save(array('UserIp' => array(
										'id' => $visit['UserIp']['id'],
										'fraud_score' => $fraudresults['riskScore']
									)), true, array('fraud_score'));
									$examine_minfraud[] = $fraudresults['riskScore'];
								}
							}							
					    }
						catch (Exception $e) {
							// maxmind is down
					    }
					}
					else {
						$examine_minfraud[] = $visit['UserIp']['fraud_score'];
					}
				}
			}
			
			// analyze IP address
			if ($visit['UserIp']['ip_address'] == '8.8.8.8') {
				$user_analysis['ip_address'] = $weights['ip_address'];
			}
		}
		
		// todo: in the long term this should really be a comparison between the IP address + the country of the timezone
		if (!empty($examine_asian_timezone)) {
			$user_analysis['asian_timezone'] = 0;
			$examine_asian_timezone = array_unique($examine_asian_timezone);
			foreach ($examine_asian_timezone as $tz) {
				if (strpos($tz, 'Asia/') !== false) {
					$user_analysis['asian_timezone'] = $weights['asian_timezone'];
				}
			}
		}
		
		if (!empty($examine_login_ips)) {
			$total = count($examine_login_ips);
			$unique = count(array_unique($examine_login_ips));
			$pct = $unique / $total;
			// give users the ability to log-in from multiple IP addresses (this may not be a good one to have in the long term)
			if ($unique > 5) {
				if ($pct <= 0.1) {
					$user_analysis['logins'] = 0;
				} 
				elseif ($pct > 0.1 && $pct < 0.5) {
					$user_analysis['logins'] = $weights['logins'] / 2;
				}
				elseif ($pct >= 0.5) {
					$user_analysis['logins'] = $weights['logins'];
				}
			}
			else {
				$user_analysis['logins'] = 0;
			}
		}
		
		// we only look at one minfraud score to cut down on costs
		if (!empty($examine_minfraud)) {
			$user_analysis['minfraud'] = current($examine_minfraud) / 100 * $weights['minfraud']; 
		}
		
		if (!empty($user['User']['rejected_transactions']) && $user['User']['rejected_transactions'] > 5) {
			$user_analysis['rejected_transactions'] = $weights['rejected_transactions'];
		}
		
		if (!empty($examine_timezones)) {
			$total = array_sum($examine_timezones); 
			if (!isset($examine_timezones['no'])) {
				$examine_timezones['no'] = 0;
			}
			if (($examine_timezones['no'] / $total < 0.25)) {
				$user_analysis['timezone'] = '0';
			}
			else {
				$user_analysis['timezone'] = $weights['timezone'];
			}
		}
		
		if (!empty($examine_proxies)) {
			$max_proxy = max($examine_proxies);
			$max_proxy_limit = ($proxy_service == 'ipintel') ? 0.90 : 1; // For ipintel the value upto 0.90 is low risk
			if ($max_proxy < $max_proxy_limit) {
				$user_analysis['proxy'] = '0';
			}
			elseif ($max_proxy <= $proxy_threshold) {
				$user_analysis['proxy'] = $weights['proxy'] / 2;
			}
			else {
				$user_analysis['proxy'] = $weights['proxy'];
			}
		}
		
		if (!empty($examine_http_languages)) {
			$user_analysis['language'] = '0';
			foreach ($examine_http_languages as $language => $score) {
				if ($language != 'en') {
					$user_analysis['language'] = $weights['language'];
					break;
				}
			}
		}
		
		if (!empty($examine_countries)) {
			$examine_countries = array_unique($examine_countries);
			foreach ($examine_countries as $key => $country) {
				if (!empty($country) && in_array($country, array('US', 'CA', 'GB'))) {
					unset($examine_countries[$key]);
				}
			}
			if (empty($examine_countries)) {
				$user_analysis['countries'] = '0';
			}
			else {
				$user_analysis['countries'] = $weights['countries'];
			}
		}
		
		if (!empty($examine_states) && count($visits) > 1) {
			$user_analysis['locations'] = '0';
			print_r($examine_states);
			if (count($examine_states) > 1) {
				$user_analysis['locations'] = $weights['locations'];
			}
		}
		
		if (!empty($user['User']['twilio_number_id'])) {
			$duplicate_numbers = $this->User->find('count', array(
				'conditions' => array(
					'User.twilio_number_id' => $user['User']['twilio_number_id'],
					'User.deleted_on' => null
				),
				'recursive' => -1
			));
			if ($duplicate_numbers > 1) {
				$user_analysis['duplicate_number'] = $weights['duplicate_number'];	
			}
		}
		
		// find out a raw and normal score
		$score = $total = 0;		
		foreach ($weights as $key => $weight) {
			if (isset($user_analysis[$key]) && !is_null($user_analysis[$key])) {
				$total = $total + $weight;				
				if ($user_analysis[$key] !== false) {
					$score = $user_analysis[$key] + $score; 
				}
			}
		}
		
		$user_analysis['user_id'] = $user['User']['id']; 
		// no data
		if (empty($total)) {
			$user_analysis['score'] = '101'; 
			$user_analysis['raw'] = $score; 
			$user_analysis['total'] = $total; 
		}
		else {
			$user_analysis['score'] = round(100 * ($score / $total), 2); 
			$user_analysis['raw'] = $score; 
			$user_analysis['total'] = $total; 
		}
		
		if (!empty($transaction_id)) {
			$user_analysis['transaction_id'] = $transaction_id;
		}
		
		$userAnalysisSource = $this->UserAnalysis->getDataSource();
		$userAnalysisSource->begin();
		$this->UserAnalysis->create();
		$this->UserAnalysis->save(array('UserAnalysis' => $user_analysis));
		$analysis_id = $this->UserAnalysis->getInsertId();
		$analysis = $this->UserAnalysis->findById($analysis_id);
		$userAnalysisSource->commit();
		return $analysis;
	}
}