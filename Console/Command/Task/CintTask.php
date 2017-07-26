<?php
App::import('Lib', 'CintMappings');
App::uses('CakeEmail', 'Network/Email');

class CintTask extends Shell {
	
	public $uses = array('User', 'CintLog', 'QueryProfile', 'SurveyUser', 'Project', 'Nonce', 'SurveyLink', 'CintQuestion', 'CintAnswer', 'Query', 'QueryStatistic', 'QueryProfile', 'Setting', 'ProjectOption', 'PartnerUser', 'ProjectCompleteHistory', 'CintSurvey');
	private $options = array('header' => array(
		'Accept' => 'application/json',
		'Content-Type' => 'application/json; charset=UTF-8'
	));

	public function get_api_keys($country) {
		$settings = $this->Setting->find('all', array(
			'conditions' => array(
				'Setting.name' => array(
					'cint.api_key_' . $country,
					'cint.api_secret_' . $country,
				),
				'Setting.deleted' => false
			)
		));
		
		if (!$settings || count($settings) != 2) {
			echo 'Api keys not found. Need the following 6 api keys in settings table' . "\n".
			'cint.api_key_' . $country . "\n".
			'cint.api_secret_' . $country . "\n";
			return false;
		}
		
		$keys = array(
			'api_key_' . $country => 'cint.api_key_' . $country,
			'api_secret_' . $country => 'cint.api_secret_' . $country,
		);
		
		$db_keys = array();
		foreach ($settings as $setting) {
			$db_keys[$setting['Setting']['name']] = $setting['Setting']['value'];
		}
		
		foreach ($keys as $k => $key) {
			if (isset($db_keys[$key])) {
				$this->$k = $db_keys[$key];
			}
			else {
				echo $key.' Api key not found in settings table!';
				return false;
			}
		}
		
		return true;
	}
	
	public function write_log(&$http, $api_data, $country) {
		$last_run = $this->CintLog->find('first', array(
			'fields' => array('max(run) as run'),
		)); 
		if (!$last_run || empty($last_run[0]['run'])) {
			$run = 1;
		}
		else {
			$run = $last_run[0]['run'] + 1;
		}
		
		// Get and store the panelist-pool if any
		foreach ($api_data as $key => $quota) {
			if (!isset($quota['links'])) {
				continue;
			}
			
			foreach ($quota['links'] as $link) {
				if (isset($link['rel']) && $link['rel'] == 'panelist-pool') {
					try {
						$panelist_pool = $http->get($link['href'], array(), $this->options);
						$api_data[$key]['panelist_pool'] = json_decode($panelist_pool, true);
					} 
					catch (Exception $ex) {
					}
				}
			}
		}
		
		// write the initial cint log values
		$json_api_data = json_encode($api_data);
		$cintLogSource = $this->CintLog->getDataSource();
		$cintLogSource->begin();
		$this->CintLog->create();
		$this->CintLog->save(array('CintLog' => array(
			'run' => $run,
			'country' => $country,
			'raw' => $json_api_data
		)));
		$cint_parent_id = $this->CintLog->getInsertId();
		$cintLogSource->commit();
		
		//Save the recent cint_log id in project_options for fast access
		$project_option = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'name' => 'cint_log.'.$country.'.id',
				'project_id' => 0
			)
		));
		if ($project_option) {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'id' => $project_option['ProjectOption']['id'],
				'value' => $cint_parent_id,
				'project_id' => 0
			)), true, array('value', 'project_id'));
		}
		else {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'name' => 'cint_log.'.$country.'.id',
				'value' => $cint_parent_id,
				'project_id' => 0
			)));
		}
		
		$i = $k = 0;
		$cint_log_ids = array();
		foreach ($api_data as $quota) {
			// Cint sometimes returns empty strings for these values; set them to notable defaults
			if (empty($quota['fulfillment']['estimated_remaining_completes'])) {
				$quota['fulfillment']['estimated_remaining_completes'] = 0; 
			}
			if (empty($quota['statistics']['completes'])) {
				$quota['statistics']['completes'] = 0; 
			}
			
			$cintLogSource = $this->CintLog->getDataSource();
			$cintLogSource->begin();
			$this->CintLog->create();
			$this->CintLog->save(array('CintLog' => array(
				'run' => $run,
				'parent_id' => $cint_parent_id,
				'country' => $country,
				'cint_survey_id' => $quota['project_id'],
				'cint_quota_id' => $quota['id'],
				'quota' => $quota['fulfillment']['estimated_remaining_completes'],
				'statistic_conversion' => $quota['statistics']['conversion_rate'],
				'statistic_ir' => $quota['statistics']['incidence_rate'],
				'statistic_completes' => $quota['statistics']['completes'],
				'raw' => json_encode($quota)
			)));
			
			$cint_log_ids[] = $this->CintLog->getInsertId();
			$cintLogSource->commit();
			$i++;
			$k = $k + $quota['fulfillment']['estimated_remaining_completes']; // total completes
			
			// To get projects table primary key 
			$survey = $this->CintSurvey->find('first', array(
				'fields' => array('CintSurvey.survey_id'),
				'conditions' => array(
					'CintSurvey.cint_survey_id' => $quota['project_id'],
					'CintSurvey.country' => $country
				),
				'recursive' => -1
			));
			if (!$survey) {
				continue;
			}
			$complete = (int) $quota['fulfillment']['estimated_remaining_completes'];
			
			$date = date('Y-m-d');
			
			//Fixed stackify error
			$project_complete_history = array();
			if ($survey && isset($survey['CintSurvey']['survey_id'])) {
				$project_complete_history = $this->ProjectCompleteHistory->find('first', array(
					'conditions' => array(
						'ProjectCompleteHistory.partner' => 'cint',
						'ProjectCompleteHistory.project_id' => $survey['CintSurvey']['survey_id'],
						'ProjectCompleteHistory.date' => $date
					)
				));
			}
			
			// we are updating an existing value
			if ($project_complete_history) {
				$max_completes = max($project_complete_history['ProjectCompleteHistory']['max_completes'], $complete); 
				$min_completes = min($project_complete_history['ProjectCompleteHistory']['min_completes'], $complete); 
				
				$this->ProjectCompleteHistory->create();
				$this->ProjectCompleteHistory->save(array('ProjectCompleteHistory' => array(
					'id' => $project_complete_history['ProjectCompleteHistory']['id'],
					'max_completes' => $max_completes,
					'min_completes' => $min_completes
				)), true, array('max_completes', 'min_completes'));
				$this->out('Updated '.$quota['project_id'].' to '.$max_completes.'/'.$min_completes);
				
			}
			else {
				// for the first one of today, set the min/max for yesterday just once
				$yesterday = date('Y-m-d', strtotime('yesterday'));
				
				$yesterday_project_complete_history = $this->ProjectCompleteHistory->find('first', array(
					'conditions' => array(
						'ProjectCompleteHistory.partner' => 'cint',
						'ProjectCompleteHistory.project_id' => $survey['CintSurvey']['survey_id'],
						'ProjectCompleteHistory.date' => $yesterday
					)
				));
				if ($yesterday_project_complete_history) {
					$max_completes = max($yesterday_project_complete_history['ProjectCompleteHistory']['max_completes'], $complete); 
					$min_completes = min($yesterday_project_complete_history['ProjectCompleteHistory']['min_completes'], $complete); 
					
					$this->ProjectCompleteHistory->create();
					$this->ProjectCompleteHistory->save(array('ProjectCompleteHistory' => array(
						'id' => $yesterday_project_complete_history['ProjectCompleteHistory']['id'],
						'max_completes' => $max_completes,
						'min_completes' => $min_completes
					)), true, array('max_completes', 'min_completes'));
					$this->out('Finalized '.$quota['project_id'].' to '.$max_completes.'/'.$min_completes);
				}
			
				$this->ProjectCompleteHistory->create();
				$this->ProjectCompleteHistory->save(array('ProjectCompleteHistory' => array(
					'partner' => 'cint',
					'project_id' => $survey ? $survey['CintSurvey']['survey_id']: null,
					'partner_project_id' => $quota['project_id'],
					'max_completes' => $complete,
					'min_completes' => $complete,
					'date' => $date
				)));
				$this->out('Created '.$quota['project_id'].' with '.$complete);
			}
		}
		$this->CintLog->create();
		$this->CintLog->save(array('CintLog' => array(
			'id' => $cint_parent_id,
			'count' => $i,
			'quota' => $k
		)), true, array('count', 'quota'));
		return $cint_log_ids;
	}
	
	public function check_query($project, $query_params, $quota) {
		$query_name = 'Cint #' . $project['CintSurvey']['cint_survey_id'] . ' Quota #' . $quota['id'];
		$query = $this->Query->find('first', array(
			'conditions' => array(
				'Query.cint_quota_id' => $quota['id'],
				'Query.survey_id' => $project['Project']['id']
			)
		));
		$query_has_changed = $query && json_encode($query_params) != $query['Query']['query_string'];
		$create_new_query = false;
		if ($query && $query_has_changed) {
			foreach ($query['QueryHistory'] as $query_history) {
				// If any of the queryHistory type is sent, we create a new query. Else this query will be updated.
				if ($query_history['type'] == 'sent') {
					$create_new_query = true;
					break;
				}
			}
		}
		elseif (!$query) {
			$create_new_query = true; // create a query if it doesn't exist
		}

		if (count($query_params) == 1 && key($query_params) == 'country') {
			$total = FED_MAGIC_NUMBER; // hardcode this because of memory issues
		}
		else {
			$results = QueryEngine::execute($query_params);
			$total = $results['count']['total'];
		}

		if ($create_new_query) {
			$querySource = $this->Query->getDataSource();
			$querySource->begin();
			$this->Query->create();
			$save = false;
			$save = $this->Query->save(array('Query' => array(
				'cint_quota_id' => $quota['id'],
				'query_name' => $query_name,
				'query_string' => json_encode($query_params),
				'survey_id' => $project['Project']['id']
			)));
			if ($save) {
				$query_id = $this->Query->getInsertId();
				$querySource->commit();
				$this->Query->QueryHistory->create();
				$this->Query->QueryHistory->save(array('QueryHistory' => array(
					'query_id' => $query_id,
					'item_id' => $project['Project']['id'],
					'item_type' => TYPE_SURVEY,
					'type' => 'created',
					'total' => $total
				)));

				$this->QueryStatistic->create();
				$this->QueryStatistic->save(array('QueryStatistic' => array(
					'query_id' => $query_id,
					'quota' => $quota['fulfillment']['estimated_remaining_completes'],
				)));
			}
			else {
				$querySource->commit();
			}
		}
		// only update query if it's changed. This use case is reached, when any of the QueryHostory of this query has NO 'sent' type.
		elseif ($query_has_changed) {
			$this->Query->save(array('Query' => array(
				'id' => $query['Query']['id'],
				'cint_quota_id' => $quota['id'],
				'query_string' => json_encode($query_params)
			)), true, array('query_string', 'cint_quota_id'));

			// wipe the old history
			$query_histories = $this->Query->QueryHistory->find('all', array(
				'recursive' => -1,
				'conditions' => array(
					'QueryHistory.query_id' => $query['Query']['id']
				),
				'fields' => array('QueryHistory.id')
			));
			if ($query_histories) {
				foreach ($query_histories as $query_history) {
					$this->Query->QueryHistory->delete($query_history['QueryHistory']['id']); 
				}
			}

			// write the new history with the count
			$this->Query->QueryHistory->create();
			$this->Query->QueryHistory->save(array('QueryHistory' => array(
				'query_id' => $query['Query']['id'],
				'item_id' => $project['Project']['id'],
				'item_type' => TYPE_SURVEY,
				'type' => 'created',
				'total' => $total
			)));
			$query_id = $query['Query']['id'];

			// update query quota
			$query_statistic = $this->QueryStatistic->find('first', array(
				'conditions' => array(
					'QueryStatistic.query_id' => $query_id
				)
			));
			// if the row exists and the quota is being sent to update, update it; doens't matter if it's filter or not
			if ($query_statistic && $query_statistic['QueryStatistic']['quota'] != ($quota['fulfillment']['estimated_remaining_completes'] + $query_statistic['QueryStatistic']['completes'])) {
				$this->QueryStatistic->create();
				$this->QueryStatistic->save(array('QueryStatistic' => array(
					'id' => $query_statistic['QueryStatistic']['id'],
					'quota' => $quota['fulfillment']['estimated_remaining_completes'] + $query_statistic['QueryStatistic']['completes']
				)), true, array('quota'));
			}
		}
		// check if quota has changed
		else {
			$query_id = $query['Query']['id'];
			$query_statistic = $this->QueryStatistic->find('first', array(
				'conditions' => array(
					'QueryStatistic.query_id' => $query_id
				)
			));
			if ($query_statistic && $query_statistic['QueryStatistic']['quota'] != ($quota['fulfillment']['estimated_remaining_completes'] + $query_statistic['QueryStatistic']['completes'])) {
				$this->QueryStatistic->create();
				$this->QueryStatistic->save(array('QueryStatistic' => array(
					'id' => $query_statistic['QueryStatistic']['id'],
					'quota' => $quota['fulfillment']['estimated_remaining_completes'] + $query_statistic['QueryStatistic']['completes'],
					'closed' => empty($quota['fulfillment']['estimated_remaining_completes']) ? date(DB_DATETIME) : null
				)), true, array('quota', 'closed'));
			}
		}
		
		return $query_id;
	}

	// Update profile fields on cint
	public function update_profile(&$http, $user, $url, $cint_user = null) {
		if (!$url) {
			echo "User self link not found!" . "\n";
		}

		$ethnicity = CintMappings::ethnicity($user['QueryProfile']['ethnicity'], $user['QueryProfile']['country']);
		$hhi = CintMappings::hhi($user['QueryProfile']['hhi'], $user['QueryProfile']['country']);
		$marital = CintMappings::relationship($user['QueryProfile']['relationship'], $user['QueryProfile']['country']);
		$home = CintMappings::housing_own($user['QueryProfile']['housing_own'], $user['QueryProfile']['country']);
		$edu = CintMappings::education_profile($user['QueryProfile']['education'], $user['QueryProfile']['country']);
		$employment = CintMappings::employment_profile($user['QueryProfile']['employment'], $user['QueryProfile']['country']);
		$industry = CintMappings::industry($user['QueryProfile']['industry'], $user['QueryProfile']['country']);
		$org_size = CintMappings::organization_size($user['QueryProfile']['organization_size'], $user['QueryProfile']['country']);
		$department = CintMappings::department($user['QueryProfile']['department'], $user['QueryProfile']['country']);
		$job = CintMappings::job($user['QueryProfile']['job'], $user['QueryProfile']['country']);
		$smart_phone = CintMappings::smartphone($user['QueryProfile']['smartphone'], $user['QueryProfile']['country']);
		$user_profile = array();
		
		if ($ethnicity !== FALSE) { // Question id = 274997
			$user_profile['panelist']['variables'][] = $ethnicity; 
		}
		
		if ($hhi !== FALSE) { // Question id = 275006
			$user_profile['panelist']['variables'][] = $hhi;
		}
		
		if ($marital !== FALSE) { // Question id = 274999
			$user_profile['panelist']['variables'][] = $marital;
		}
		
		if ($home !== FALSE) { // Question id = 275000
			$user_profile['panelist']['variables'][] = $home;
		}
		
		if ($edu !== FALSE) { // Question id = 275010
			$user_profile['panelist']['variables'][] = $edu;
		}
		
		if ($employment !== FALSE) { // Question id = 277433
			$user_profile['panelist']['variables'][] = $employment;
		}
		
		if ($industry !== FALSE) { // Question id = 275013
			$user_profile['panelist']['variables'][] = $industry;
		}
		
		if ($org_size !== FALSE) { // Question id = 275014
			$user_profile['panelist']['variables'][] = $org_size;
		}
		
		if ($department !== FALSE) { // Question id = 275015
			$user_profile['panelist']['variables'][] = $department;
		}
		
		if ($job !== FALSE) { // Question id = 275017
			$user_profile['panelist']['variables'][] = $job;
		}
		
		if ($smart_phone !== FALSE) { // Question id = 275024
			$user_profile['panelist']['variables'][] = $smart_phone;
		}
		
		if (defined('CINT_DEBUG') && CINT_DEBUG) {
			CakeLog::write('cint-update', 'User Profile fields :' . "\n" . print_r(json_encode($user_profile), true));
		}

		try {
			$results = $http->patch($url, json_encode($user_profile), $this->options);
		} catch (Exception $e) {
			echo "Api call failed, updating user profile. User id: ". $user['User']['id'] . "\n";
			return;
		}
		
		if (defined('CINT_DEBUG') && CINT_DEBUG) {
			CakeLog::write('cint-update', 'User Profile fields save result:' . "\n" . print_r(json_decode($results, true), true).print_r($results, true));
		}

		if ($results->code == 204) {
			$partner_user = $this->PartnerUser->find('first', array(
				'conditions' => array(
					'PartnerUser.user_id' => $user['User']['id'],
					'PartnerUser.partner' => 'cint'
				)
			));
			if ($partner_user) {
				$this->PartnerUser->save(array('PartnerUser' => array(
					'id' => $partner_user['PartnerUser']['id'],
					'last_exported' => date(DB_DATETIME)
				)), false, array('last_exported'));
			}
			else {
				$this->PartnerUser->create();
				$this->PartnerUser->save(array('PartnerUser' => array(							
					'last_exported' => date(DB_DATETIME),
					'user_id' => $user['User']['id'],
					'uid' => $cint_user['panelist']['key'],
					'partner' => 'cint'
				)));
			}
			echo "User id: " . $user['User']['id'] . ", profile data updated!" . "\n";
		}
		else {
			echo "User id: " . $user['User']['id'] . " profile data not updated. Error: " . $results->code . "\n";
			print_r($user_profile);
			print_r(json_decode($results->body, true));
		}
	}
	
	/* Get Cint user by member_id
	 * a) First get panelists url
	 * b) then get request with member_id to that url
	 */

	public function user(&$http, $user_id, $panelists_url) {
		try {
			$results = $http->get($panelists_url, array('member_id' => $user_id), $this->options);
		} catch (Exception $e) {
			echo "Api call failed, retrieving user id: " . $user['User']['id'] . "\n";
			return;
		}
		
		return json_decode($results, true);
	}

	public function api_url($host, &$http, $link_type, $api_key) {
		try {
			$results = $http->get($host . 'panels/' . $api_key, array(), $this->options);
		} catch(Exception $e) {
			echo "Api call failed when getting links!" . "\n";
			return;
		}
		
		$results = json_decode($results, true);
		if (!isset($results['links'])) {
			echo "links not found!" . "\n";
			return;
		}

		$url = '';
		foreach ($results['links'] as $link) {
			if ($link['rel'] == $link_type) {
				$url = $link['href'];
			}
		}

		if (!$url) {
			echo "api url not found!" . "\n";
			return;
		}

		return $url;
	}
	
	public function sanitize($str) {
		return preg_replace("/[^{\\ }a-zA-Z]/", '', $str);
	}
	
	function save_question($variable_record, $country) {
		// add/update cint_question
		
		$cint_question = $this->CintQuestion->find('first', array(
			'conditions' => array(
				'CintQuestion.question_id' => $variable_record['@ident'],
			)
		));

		if ($cint_question) {
			$this->CintQuestion->create();
			$this->CintQuestion->save(array('CintQuestion' => array(
				'id' => $cint_question['CintQuestion']['id'],
				'question' => $variable_record['label']['@'],
				'question_native_text' => $variable_record['label']['text']['@'],
				'type' => $variable_record['@type'],
				'country' => $country
			)), true, array('question', 'question_native_text', 'type', 'country'));
			echo 'CintQuestion.id ' . $cint_question['CintQuestion']['id'] . ' updated.' . "\n";
		}
		else {
			$this->CintQuestion->create();
			$this->CintQuestion->save(array('CintQuestion' => array(
				'question_id' => $variable_record['@ident'],
				'question' => $variable_record['label']['@'],
				'question_native_text' => $variable_record['label']['text']['@'],
				'type' => $variable_record['@type'],
				'country' => $country
			)));
			echo 'CintQuestion.question_id ' . $variable_record['@ident'] . ' created.' . "\n";
		}

		if (!isset($variable_record['values']['value'])) {
			echo 'answers not fond for question id: ' . $variable_record['@ident']. "\n";
		}
		
		// Add/update cint_answers fot this question
		foreach ($variable_record['values']['value'] as $answer) {
			$cint_answer = $this->CintAnswer->find('first', array(
				'conditions' => array(
					'CintAnswer.question_id' => $variable_record['@ident'],
					'CintAnswer.answer_id' => $answer['@code'],
				)
			));

			if ($cint_answer) {
				$this->CintAnswer->create();
				$this->CintAnswer->save(array('CintAnswer' => array(
					'id' => $cint_answer['CintAnswer']['id'],
					'variable_id' => $answer['@cint:variable-id'],
					'answer' => $answer['@'],
					'answer_native_text' => $answer['text']['@'],
				)), true, array('variable_id', 'answer', 'answer_native_text'));
				echo 'CintAnswer.id ' . $cint_answer['CintAnswer']['id'] . ' updated.' . "\n";
			}
			else {
				$this->CintAnswer->create();
				$this->CintAnswer->save(array('CintAnswer' => array(
					'answer_id' => $answer['@code'],
					'variable_id' => $answer['@cint:variable-id'],
					'question_id' => $variable_record['@ident'],
					'answer' => $answer['@'],
					'answer_native_text' => $answer['text']['@'],
				)));
				echo 'CintAnswer.variable id ' . $answer['@cint:variable-id'] . ' created.' . "\n";
			}

			
		}
	}
	
	function get_query_params(&$query_params, $answer, $country = 'US') {
		$result = false;
		$mapping_function = $answer['CintQuestion']['queryable'];
		if ($answer['CintQuestion']['question_id'] == 277416) {
			$result = CintMappings::education_profile_277416($answer['CintAnswer']['variable_id']);
		}
		elseif ($answer['CintQuestion']['question_id'] == 369619) {
			$result = CintMappings::education_profile_369619($answer['CintAnswer']['variable_id']);
		}
		elseif ($answer['CintQuestion']['question_id'] == 275024) {
			$result = CintMappings::smartphone_275024($answer['CintAnswer']['variable_id']);
		}
		elseif ($answer['CintQuestion']['question_id'] == 369636) {
			$result = CintMappings::smartphone_369636($answer['CintAnswer']['variable_id']);
		}
		elseif ($mapping_function == 'education') {
			$result = CintMappings::education_profile($answer['CintAnswer']['variable_id'], $country, true);
		}
		elseif ($mapping_function == 'employment') {
			$result = CintMappings::employment_profile($answer['CintAnswer']['variable_id'], $country, true);
		}
		elseif (method_exists('CintMappings', $mapping_function)) {
			$result = CintMappings::$mapping_function($answer['CintAnswer']['variable_id'], $country, true);
		}

		if ($result !== false) {
			$query_params[$mapping_function][] = $result;
		}
	}

}