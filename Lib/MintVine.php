<?php

class MintVine {
	
	public static function project_quota_statistics($partner, $quota, $project_id, $partner_query_id = null) {
		App::import('Model', 'ProjectQuotaHistory');
		$ProjectQuotaHistory = new ProjectQuotaHistory;
		
		$date = date(DB_DATE);
		$project_quota_history = $ProjectQuotaHistory->find('first', array(
			'conditions' => array(
				'ProjectQuotaHistory.date' => $date,
				'ProjectQuotaHistory.partner' => $partner,
				'ProjectQuotaHistory.project_id' => $project_id,
				'ProjectQuotaHistory.partner_query_id' => $partner_query_id
			)
		));
		if (!$project_quota_history) {
			$ProjectQuotaHistory->create();
			$ProjectQuotaHistory->save(array('ProjectQuotaHistory' => array(
				'partner' => $partner,
				'project_id' => $project_id,
				'partner_query_id' => $partner_query_id,
				'max_quota' => $quota,
				'min_quota' => $quota,
				'date' => $date
			)));
		}
		else {
			if ($quota > $project_quota_history['ProjectQuotaHistory']['max_quota']) {
				$ProjectQuotaHistory->create();
				$ProjectQuotaHistory->save(array('ProjectQuotaHistory' => array(
					'id' => $project_quota_history['ProjectQuotaHistory']['id'],
					'max_quota' => $quota
				)), false, array('max_quota'));
			}
			if ($quota < $project_quota_history['ProjectQuotaHistory']['min_quota']) {
				$ProjectQuotaHistory->create();
				$ProjectQuotaHistory->save(array('ProjectQuotaHistory' => array(
					'id' => $project_quota_history['ProjectQuotaHistory']['id'],
					'min_quota' => $quota
				)), false, array('min_quota'));
			}
		}
	}
	
	public static function query_string_mappable_values() {
		return array(
			'hhi' => array('title' => 'Household Income', 'data' => USER_HHI),
			'education' => array('title' => 'Education Level', 'data' => USER_EDU),
			'ethnicity' => array('title' => 'Ethnicity', 'data' => USER_ETHNICITY),
			'hispanic' => array('title' => 'Hispanic', 'data' => USER_ORIGIN),
			'relationship' => array('title' => 'Marital Status', 'data' => USER_MARITAL),
			'employment' => array('title' => 'Employment Status', 'data' => USER_EMPLOYMENT),
			'industry' => array('title' => 'Job Industry', 'data' => USER_INDUSTRY),
			'department' => array('title' => 'Job Department', 'data' => USER_DEPARTMENT),
			'job' => array('title' => 'Job Title', 'data' => USER_JOB),
			'housing_own' => array('title' => 'Rent or Own?', 'data' => USER_HOME),
			'housing_purchased' => array('title' => 'Home purchased in 3 years?', 'data' => USER_HOME_OWNERSHIP),
			'housing_plans' => array('title' => 'Home plans', 'data' => USER_HOME_PLANS),
			'children' => array('title' => 'Has Children', 'data' => USER_CHILDREN),
			'organization_size' => array('title' => 'Organization Size', 'data' => USER_ORG_SIZE),
			'organization_revenue' => array('title' => 'Organization Revenue', 'data' => USER_ORG_REVENUE),
			'smartphone' => array('title' => 'Owns Smartphone', 'data' => USER_SMARTPHONE),
			'tablet' => array('title' => 'Owns Tablet', 'data' => USER_TABLET),
			'airlines' => array('title' => 'Have you traveled by plane?', 'data' => USER_TRAVEL),
		);
	}
	
	public static function query_string_to_readable($query_string) {
		// clean up birthday into one value
		if (isset($query_string['birthdate <=']) && isset($query_string['birthdate >'])) {
			if (isset($query_string['birthdate <='])) {
				$date = new DateTime($query_string['birthdate <=']);
				$now = new DateTime();
				$interval = $now->diff($date);
				$year = $interval->y;
				
				$query_string['birthday'][] = $year;
				unset($query_string['birthdate <=']);
			}
			if (isset($query_string['birthdate >'])) {
				$date = new DateTime($query_string['birthdate >']);
				$now = new DateTime();
				$interval = $now->diff($date);
				$year = $interval->y;
				
				$query_string['birthday'][] = $year;
				unset($query_string['birthdate >']);
			}
		}
		
		if (isset($query_string['state']) && !empty($query_string['state'])) {
			App::import('Model', 'GeoState');
			$GeoState = new GeoState;
			$states = $GeoState->find('list', array(
				'fields' => array('GeoState.state_abbr', 'GeoState.state'),
				'conditions' => array(
					'GeoState.state_abbr' => $query_string['state']
				)
			));
			$query_string['state'] = array();
			foreach ($states as $abbr => $state) {
				$query_string['state'][$abbr] = $state.' ('.$abbr.')';
			}
		}
		if (isset($query_string['country']) && !empty($query_string['country'])) {
			App::import('Model', 'GeoCountry');
			$GeoCountry = new GeoCountry;
			$country = $GeoCountry->find('first', array(
				'conditions' => array(
					'GeoCountry.ccode' => $query_string['country']
				)
			));
			$query_string['country'] = $country['GeoCountry']['country'];
		}
		if (isset($query_string['dma_code']) && !empty($query_string['dma_code'])) {	
			App::import('Model', 'GeoZip');
			$GeoZip = new GeoZip;
			$dmas = $GeoZip->getDmas();
			$list = array();
			foreach ($query_string['dma_code'] as $dma) {
				$list[$dma] = $dmas[$dma];
			}
			$query_string['dma_code'] = $list;
		}
		if (isset($query_string['postal_code']) && !empty($query_string['postal_code'])) {
			$query_string['postal_code'] = implode('; ', $query_string['postal_code']);
		}
		$mappings = MintVine::query_string_mappable_values(); 
		foreach ($mappings as $key => $val) {			
			if (isset($query_string[$key])) {
				$val = unserialize($val['data']);
				$list = array();
				foreach ($query_string[$key] as $value) {
					$list[$value] = $val[$value];
				}
				$query_string[$key] = $list;
			}
		}
		return $query_string;
	}
	
	public static function query_string_to_readable_qe2($query_string) {
		// clean up birthday into ranges
		if (isset($query_string['age'])) {
			sort($query_string['age']);
			$age_from = $age_to = null;
			foreach ($query_string['age'] as $age) {
				if ($age_from === null) {
					$age_from = $age_to = $age;
				}
				elseif ($age_to < ($age - 1)) {
					$query_string['age_ranges'][] = ($age_from == $age_to) ? $age_from : ($age_from . ' - ' . $age_to);
					$age_from = $age_to = $age;
				}
				else {
					$age_to = $age;
				}
			}
			$query_string['age_ranges'][] =($age_from == $age_to) ? $age_from : ($age_from . '-' . $age_to);
			unset($query_string['age']);
		}
		
		if (isset($query_string['state']) && !empty($query_string['state'])) {
			App::import('Model', 'GeoState');
			$GeoState = new GeoState;
			$states = $GeoState->find('list', array(
				'fields' => array('GeoState.state_abbr', 'GeoState.state'),
				'conditions' => array(
					'GeoState.state_abbr' => $query_string['state']
				)
			));
			$query_string['state'] = array();
			foreach ($states as $abbr => $state) {
				$query_string['state'][$abbr] = $state.' ('.$abbr.')';
			}
		}
		if (isset($query_string['country']) && !empty($query_string['country'])) {
			App::import('Model', 'GeoCountry');
			$GeoCountry = new GeoCountry;
			$countries = $GeoCountry->find('list', array(
				'fields' => array('GeoCountry.ccode', 'GeoCountry.country'),
				'conditions' => array(
					'GeoCountry.ccode' => $query_string['country']
				)
			));
			$query_string['country'] = array();
			foreach ($countries as $abbr => $country) {
				$query_string['country'][$abbr] = $country.' ('.$abbr.')';
			}
		}
		if (isset($query_string['dma_code']) && !empty($query_string['dma_code'])) {	
			App::import('Model', 'GeoZip');
			$GeoZip = new GeoZip;
			$dmas = $GeoZip->getDmas();
			$list = array();
			foreach ($query_string['dma_code'] as $dma) {
				$list[$dma] = $dmas[$dma];
			}
			$query_string['dma_code'] = $list;
		}
		if (isset($query_string['postal_code']) && !empty($query_string['postal_code'])) {
			$query_string['postal_code'] = implode('; ', $query_string['postal_code']);
		}
		$mappings = MintVine::query_string_mappable_values(); 
		foreach ($mappings as $key => $val) {			
			if (isset($query_string[$key])) {
				$val = unserialize($val['data']);
				$list = array();
				foreach ($query_string[$key] as $value) {
					$list[$value] = $val[$value];
				}
				$query_string[$key] = $list;
			}
		}
		return $query_string;
	}
	
	public static function country_name($country_code) {
		if ($country_code == 'US') {
			return 'United States';
		}
		if ($country_code == 'CA') {
			return 'Canada';
		}
		if ($country_code == 'GB') {
			return 'United Kingdom';
		}
		return $country_code;
	}
	
	public static function construct_lander_url($lander) {
		if (strpos($lander['Source']['abbr'], ':') !== false) {
			list($trash, $abbr) = explode(':', $lander['Source']['abbr']);
		}
		else {
			$abbr = $lander['Source']['abbr'];
		}
		return HOSTNAME_WWW.$lander['LanderUrl']['path'].'?utm_source='.$abbr; 
	}
	
	public static function parse_project_id($query) {
		$query = str_replace(' ', '', $query); 
		if ($query{0} == '#') {
			$query = substr($query, 1);
		}
		App::import('Model', 'Group');
		$Group = new Group;
		
		$prefixes = $Group->find('list', array(
			'fields' => array('prefix', 'id'),
			'conditions' => array(
				'Group.prefix is not null'
			),
			'recursive' => -1,
		));
		
		App::import('Model', 'Project');
		$Project = new Project;
		
		$prefix = (is_numeric($query{1})) ? $query{0} : substr($query, 0, 2);
		if (isset($prefixes[$prefix])) {
			$project = $Project->find('first', array(
				'conditions' => array(
					'Project.group_id' => $prefixes[$prefix],
					'Project.mask' => ltrim($query, $prefix)
				),
				'recursive' => -1,
				'fields' => array('id')
			));
		}
		else {
			$project = $Project->find('first', array(
				'conditions' => array(
					'Project.id' => $query
				),
				'recursive' => -1,
				'fields' => array('id')
			));
		}
		return $project ? $project['Project']['id']: false; 
	}
	
	// keep logic in sync with r's surveyvisitcache model
	public static function drop_rate($project) {		
		$terminating_sum = $project['SurveyVisitCache']['complete'] + $project['SurveyVisitCache']['nq'] + $project['SurveyVisitCache']['overquota'] +  $project['SurveyVisitCache']['speed'] + $project['SurveyVisitCache']['fraud']; 
		$initial_clicks = $project['SurveyVisitCache']['click']; 
		
		$drops = 100 - round(($terminating_sum / $initial_clicks) * 100);
		return $drops; 	
	}
	
	public static function project_id($project) {
		// fed uses MV project ids
		if (isset($project['Project']['Group'])) {
			$group = $project['Project']['Group']; 
		}
		elseif (isset($project['Group'])) {
			$group = $project['Group']; 
		}
		else {
			App::import('Model', 'Group');
			$Group = new Group;
			$group = $Group->find('first', array(
				'conditions' => array(
					'Group.id' => $project['Project']['group_id']
				)
			));
			$group = $group['Group']; 
		}

		if (isset($group) && $group['use_mask']) {
			if (!empty($group['prefix'])) {
				return $group['prefix'] . $project['Project']['mask'];
			}
			return $project['Project']['mask'];	
		}
		return $project['Project']['id'];
	}
	
	// determine the IR of the project
	public static function project_ir($project) {
		
		// used the cache value first; this is less accurate than the real-time statistics;
		// todo: this can probably be removed in the future, but for now use it to be consistent w/ other places
		if (!empty($project['SurveyVisitCache']['ir'])) {
			$ir = $project['SurveyVisitCache']['ir'];
		}
		// if we have actual data
		elseif (!empty($project['SurveyVisitCache']['complete']) && !empty($project['SurveyVisitCache']['click'])) {
			$ir = round($project['SurveyVisitCache']['complete'] / $project['SurveyVisitCache']['click'], 2) * 100;
		}
		elseif (!empty($project['Project']['bid_ir'])) {
			$ir = $project['Project']['bid_ir']; 
		}
		else {
			$ir = 0; 
		}
		return $ir;
	}
	
	// to be used for fresh projects to figure out how many users to send a given query to
	public static function estimate_query_send($query, $project) {
		$quota = null;
		
		if (!is_null($query['QueryStatistic']['quota'])) {
			$quota = $query['QueryStatistic']['quota'];
			if (!empty($project['Project']['quota'])) { // 0 is unlimited
				$quota = min(array($project['Project']['quota'], $query['QueryStatistic']['quota']));
			}
		}
		elseif (!empty($project['Project']['quota'])) {
			$quota = $project['Project']['quota'];
		}
		else {
			return false; // this shouldn't happen
		}
		// 15% 20 quota = 20 * 100 / 15
		if (empty($project['Project']['bid_ir'])) {
			return $quota;
		}
		return round($quota * 100 / $project['Project']['bid_ir']); 
	}
	
	// a helper which determines how many panelists to send a project to
	/// $ceiling is max
	public static function query_amount($project, $ceiling, $query = null) {
		$ir = 15; // if IR ends up not being sent anywhere, then set it to 15% as a default value
		
		// We use query quota if available
		if (isset($query['QueryStatistic']) && !is_null($query['QueryStatistic']['quota'])) {
			$quota = $query['QueryStatistic']['quota'];
			if (!empty($query['QueryStatistic']['completes']) && $query['QueryStatistic']['clicks'] > 20) {
				$ir = round($query['QueryStatistic']['completes'] / $query['QueryStatistic']['clicks'], 2) * 100;
			}
			elseif (!empty($project['Project']['bid_ir'])) {
				$ir = $project['Project']['bid_ir'];
			}
			if (!empty($query['QueryStatistic']['completes'])) {
				$quota = $quota - $query['QueryStatistic']['completes'];
			}
		} 
		else { // Use project quota
			// todo: calculate better way of calculating IR. on a project with 6/2/3, it's preferable to ignore the small click ratio
			$quota = $project['Project']['quota'];
			if (!empty($project['SurveyVisitCache']['complete']) && $project['SurveyVisitCache']['click'] > 20) {
				$ir = round($project['SurveyVisitCache']['complete'] / $project['SurveyVisitCache']['click'], 2) * 100;
			}
			elseif (!empty($project['Project']['bid_ir'])) {
				$ir = $project['Project']['bid_ir'];
			}

			if (!empty($project['SurveyVisitCache']['complete'])) {
				$quota = $quota - $project['SurveyVisitCache']['complete'];
			}
		}
		
		if ($ir < 1) {
			return '0';
		}
		
		if ($quota < 1) {
			return '0';
		}
		
		if ($ceiling < 1) {
			return '0';
		}
		
		$amount = floor(($quota / ($ir / 100)));
		$amount = $amount * 2; // not every panelist participates; 50% participation rate
		if ($ceiling && $amount > $ceiling) {
			return $ceiling;
		}
		
		return $amount;		
	}
	
	public static function get_postback_pixel($user, $user_acquisition, $html) {	
		$html = str_replace('{{USER_ID}}', $user['User']['id'], $html); 
		if (!empty($user_acquisition['UserAcquisition']['params'])) {
			$params = $user_acquisition['UserAcquisition']['params']; 
			if (!empty($params)) {
				foreach ($params as $key => $val) {
					$html = str_replace('{{'.$key.'}}', $val, $html);
				}
			}			
		}
		
		// strip unused {{}}
		preg_match_all('/{{[A-Za-z]+}}/', $html, $matches);
		if (isset($matches[0]) && !empty($matches[0])) {
			foreach ($matches[0] as $matched) {
				$html = str_replace($matched, '', $html);
			}
		}
		
		return $html;
	}
	
	public static function approve_history_request($history_request_id, $amount, $admin_id = null) {
		App::import('Model', 'HistoryRequest');
		$HistoryRequest = new HistoryRequest;
		$HistoryRequest->bindModel(array(
			'belongsTo' => array(
				'Transaction'
			)
		));
		$history_request = $HistoryRequest->find('first', array(
			'conditions' => array(
				'HistoryRequest.id' => $history_request_id
			)
		));
		
		if (!$history_request) {
			return array('status' => false);
		}
		if (empty($amount) || $amount < 0 || $amount > (int) $history_request['Project']['award']) {
			return array(
				'status' => false,
				'error' => 'Must be a positive numeric value, and should not exceed actual project points!'
			);
		}
		if ($history_request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_APPROVED) {
			// already approved
			return array(
				'status' => false,
				'error' => 'This history request has already been approved!'
			);
		}
		
		App::import('Model', 'Transaction');
		$Transaction = new Transaction;
	
		// if existing transaction exists; delete it first!
		if ($history_request['Transaction']['id'] > 0) {
			$existing_transaction = array('Transaction' => $history_request['Transaction']); 
			$Transaction->soft_delete($existing_transaction);
		}
		
		// create new transaction that will pay out
		$transactionSource = $Transaction->getDataSource();
		$transactionSource->begin();
		$Transaction->create();
		$Transaction->save(array('Transaction' => array(
			'type_id' => TRANSACTION_MISSING_POINTS,
			'linked_to_id' => $history_request['Project']['id'],
			'linked_to_name' => $history_request['Project']['survey_name'],
			'user_id' => $history_request['HistoryRequest']['user_id'],
			'amount' => $amount,
			'paid' => false,
			'name' => 'Survey Completion - '.$history_request['Project']['survey_name'],
			'status' => TRANSACTION_PENDING,
			'executed' => date(DB_DATETIME)
		)));			
		$transaction_id = $Transaction->getInsertId();
		$transaction = $Transaction->find('first', array(
			'conditions' => array(
				'Transaction.id' => $transaction_id,
				'Transaction.deleted' => null,
			)
		));
		$transaction_id = $Transaction->approve($transaction); 		
		$transactionSource->commit();
		
		if (!$history_request['Project']['router']) {
			App::import('Model', 'PanelistHistory');
			$PanelistHistory = new PanelistHistory;
			$PanelistHistory->create();
			$PanelistHistory->save(array('PanelistHistory' => array(
				'id' => $history_request['HistoryRequest']['panelist_history_id'],
				'transaction_id' => $transaction_id,
			)), true, array('transaction_id')); 
		}	
	
		// mark the internal survey user click as completed to prevent multiple entries
		App::import('Model', 'SurveyUserVisit');
		$SurveyUserVisit = new SurveyUserVisit;
		$survey_user_visit = $SurveyUserVisit->find('first', array(
			'conditions' => array(
				'SurveyUserVisit.user_id' => $history_request['HistoryRequest']['user_id'],
				'SurveyUserVisit.survey_id' => $history_request['HistoryRequest']['project_id']
			)
		));
		if ($survey_user_visit && $survey_user_visit['SurveyUserVisit']['status'] == SURVEY_CLICK) {
			$SurveyUserVisit->create();
			$SurveyUserVisit->save(array('SurveyUserVisit' => array(
				'id' => $survey_user_visit['SurveyUserVisit']['id'],
				'status' => SURVEY_COMPLETED,
				'redeemed' => true
			)), true, array('status', 'redeemed'));
		}
		
		$HistoryRequest->create();
		$HistoryRequest->save(array('HistoryRequest' => array(
			'id' => $history_request_id,
			'transaction_id' => $transaction_id,
			'admin_id' => $admin_id, 
			'status' => SURVEY_REPORT_REQUEST_APPROVED
		)), true, array('transaction_id', 'admin_id', 'status'));
		
		if ($history_request['HistoryRequest']['send_email']) {
			App::import('Model', 'Setting');
			$Setting = new Setting;
			$setting = $Setting->find('list', array(
				'fields' => array('Setting.name', 'Setting.value'),
				'conditions' => array(
					'Setting.name' => array('cdn.url'),
					'Setting.deleted' => false
				)
			));
			if (!empty($setting['cdn.url']) && (!defined('IS_DEV_INSTANCE') || !IS_DEV_INSTANCE)) {
				Configure::write('App.cssBaseUrl', $setting['cdn.url'] . '/');
				Configure::write('App.jsBaseUrl', $setting['cdn.url'] . '/');
				Configure::write('App.imageBaseUrl', $setting['cdn.url'] . '/img/');
			}
			
			$admin = array();
			if ($admin_id) {
				App::import('Model', 'Admin');
				$Admin = new Admin;
				$admin = $Admin->find('first', array(
					'fields' => array('Admin.id', 'Admin.admin_user'),
					'conditions' => array(
						'Admin.id' => $admin_id
					)
				));
			}
			
			// send email notification to user				
			$email = new CakeEmail();
			$email->config('mailgun');
			$email->from(array(EMAIL_SENDER => 'MintVine'))
				->replyTo(array(REPLYTO_EMAIL => 'MintVine'))
				->emailFormat('html')
				->template('history_request_accepted')
				->viewVars(array(
					'user_name' => $history_request['User']['username'],
					'user_timezone' => $history_request['User']['timezone'],
					'survey_id' => $history_request['HistoryRequest']['project_id'],
					'points' => $amount,
					'reported_at' => $history_request['HistoryRequest']['created'],
					'approved_at' => $history_request['HistoryRequest']['modified'],
					'approved_by' => isset($admin['Admin']['admin_user']) ? $admin['Admin']['admin_user'] : null
				))
				->to(array($history_request['User']['email']))
				->subject('MintVine Transaction Approved');
			$email->send();
		}
		
		return array(
			'status' => true,
			'transaction_id' => $transaction_id
		);
	}
}