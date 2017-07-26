<?php
App::import('Lib', 'FedMappings');
App::import('Lib', 'MintVine');
App::uses('HttpSocket', 'Network/Http');

class FedTask extends Shell {
	
	public $uses = array('FedQuestion', 'FedAnswer', 'GeoZip', 'Query', 'QueryStatistic', 'FedSurvey', 'Setting', 'Project', 'ProjectLog', 'SurveyUser', 'SurveyUserVisit');
	
	// Set params for url to send curl
	private $api_key = FED_API_KEY;
	private $api_host = FED_API_HOST;
		
	public function check_query($project, $query_params, $parent_query_id = 0, $quota = null) {
		
		/* filter query has different name - includes quota_id */
		if (!is_null($quota)) {
			$query_name = 'Lucid #' . $project['FedSurvey']['fed_survey_id'] . ' Quota #' . $quota['SurveyQuotaID'];
			$query_old_name = 'Fulcrum #' . $project['FedSurvey']['fed_survey_id'] . ' Quota #' . $quota['SurveyQuotaID'];
		}
		else {
			$query_name = 'Lucid #' . $project['FedSurvey']['fed_survey_id'] . ' Qualifications';
			$query_old_name = 'Fulcrum #' . $project['FedSurvey']['fed_survey_id'] . ' Qualifications';
		}
		
		$query = $this->Query->find('first', array(
			'conditions' => array(
				'Query.query_name' => array($query_name, $query_old_name),
				'Query.survey_id' => $project['Project']['id']
			),
			'order' => 'Query.id DESC' // multiple queries can exist with same name: retrieve the last one
		));
		
		if ($query) {
			$query_id = $query['Query']['id'];
		}
		
		if (isset($query_params['postal_code'])) {
			$query_params['postal_code'] = array_values(array_unique($query_params['postal_code']));
		}
		
		// if we've matched against hispanic, then add the ethnicity values
		if (isset($query_params['hispanic']) && !empty($query_params['hispanic'])) {
			if (isset($query_params['ethnicity']) && !in_array(4, $query_params['ethnicity'])) {
				$query_params['ethnicity'][] = 4; // hardcode hispanics
			}
			elseif (!isset($query_params['ethnicity'])) {
				$query_params['ethnicity'] = array(4); // hardcode hispanics
			}
		}

		// Remove duplicates.
		if (isset($query_params['ethnicity'])) {
			$query_params['ethnicity'] = array_values(array_unique($query_params['ethnicity']));
		}
		
		$create_new_query = false;
		if (!$query) {
			$create_new_query = true;
		}
		elseif ($query && json_encode($query_params) != $query['Query']['query_string']) {
			$create_new_query = true;
			$query_history_ids = Set::extract('/QueryHistory/id', $query);
			$this->Query->delete($query_id);
			foreach ($query_history_ids as $query_history_id) {
				$this->Query->QueryHistory->delete($query_history_id);
			}
			
			$survey_users = $this->SurveyUser->find('all', array(
				'fields' => array('id', 'user_id', 'survey_id'),
				'recursive' => -1,
				'conditions' => array(
					'SurveyUser.survey_id' => $project['Project']['id'],
					'SurveyUser.query_history_id' => $query_history_ids
				)
			));

			$str = '';
			if ($survey_users) {
				foreach ($survey_users as $survey_user) {
					$count = $this->SurveyUserVisit->find('count', array(
						'conditions' => array(
							'SurveyUserVisit.user_id' => $survey_user['SurveyUser']['user_id'],
							'SurveyUserVisit.survey_id' => $survey_user['SurveyUser']['survey_id'],
						)
					));
					
					if ($count < 1) {
						$this->SurveyUser->delete($survey_user['SurveyUser']['id']);
					}
				}
				
				$str = 'Survey users deleted.';
			}
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'query.deleted',
				'description' => 'Query.id ' . $query_id . ' deleted, because qualifications updated. ' . $str
			)));
		}
		elseif ($quota) { // Check if quota has changed
			$query_statistic = $this->QueryStatistic->find('first', array(
				'conditions' => array(
					'QueryStatistic.query_id' => $query_id
				)
			));
		
			if ($query_statistic && $query_statistic['QueryStatistic']['quota'] != ($quota['NumberOfRespondents'] + $query_statistic['QueryStatistic']['completes'])) {
				$this->QueryStatistic->create();
				$this->QueryStatistic->save(array('QueryStatistic' => array(
					'id' => $query_statistic['QueryStatistic']['id'],
					'quota' => $quota['NumberOfRespondents'] + $query_statistic['QueryStatistic']['completes'],
					'closed' => !is_null($quota) && empty($quota['NumberOfRespondents']) ? date(DB_DATETIME) : null
				)), true, array('quota', 'closed'));
			}
		}

		if ($create_new_query) {
			if (count($query_params) == 1 && key($query_params) == 'country') {
				$total = FED_MAGIC_NUMBER; // hardcode this because of memory issues
			}
			else {
				$results = QueryEngine::execute($query_params);
				$total = $results['count']['total'];
			}
			
			$querySource = $this->Query->getDataSource();
			$querySource->begin();
			$this->Query->create();
			$save = $this->Query->save(array('Query' => array(
				'parent_id' => $parent_query_id,
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
				
				// this is a query filter
				if (!is_null($quota)) {
					$this->QueryStatistic->create();
					$this->QueryStatistic->save(array('QueryStatistic' => array(
						'query_id' => $query_id,
						'quota' => $quota['NumberOfRespondents'],
					)));
				}
				
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'query.created',
					'description' => 'Query.id '. $query_id . ' created'
				)));
				
				
				// If this query was already sent, then run this new query too
				if (isset($survey_users) && !empty($survey_users)) {
					$this->Query->bindModel(array('hasOne' => array('QueryStatistic')));
					$query = $this->Query->find('first', array(
						'contain' => array(
							'QueryStatistic'
						),
						'conditions' => array(
							'Query.id' => $query_id,
						)
					));
					
					// don't full launch if its a sampling project
					if ($project['Project']['status'] == PROJECT_STATUS_SAMPLING) {
						$setting = $this->Setting->find('first', array(
							'conditions' => array(
								'Setting.name' => 'fulcrum.sample_size',
								'Setting.deleted' => false
							)
						));
						if (!$setting) { // set the default if not found.
							$setting = array('Setting' => array('value' => 50));
						}

						$survey_reach = ($total < $setting['Setting']['value']) ? $total : $setting['Setting']['value'];
					}
					else {
						$survey_reach = MintVine::query_amount($project, $total, $query);
					}
					
					if ($survey_reach == 0) {
						$message = 'Skipped ' . $project['Project']['id'] . ' because query has no quota left';
						echo $message . "\n";
					}

					if ($survey_reach > 1000) {
						$survey_reach = 1000;
					}
					
					$queryHistorySource = $this->Query->QueryHistory->getDataSource();
					$queryHistorySource->begin();
					$this->Query->QueryHistory->create();
					$this->Query->QueryHistory->save(array('QueryHistory' => array(
						'query_id' => $query['Query']['id'],
						'item_id' => $query['Query']['survey_id'],
						'item_type' => TYPE_SURVEY,
						'count' => null,
						'total' => null,
						'type' => 'sending'
					)));
					$query_history_id = $this->Query->QueryHistory->getInsertId();
					$queryHistorySource->commit();
					$query = ROOT . '/app/Console/cake query create_queries ' . $query['Query']['survey_id'] . ' ' . $query['Query']['id'] . ' ' . $query_history_id . ' ' . $survey_reach;
					CakeLog::write('query_commands', $query);
					// run these synchronously
					exec($query, $output);
					var_dump($output);
					$message = 'Query executed: ' . $query;
					echo $message . "\n";
					
					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $project['Project']['id'],
						'type' => 'query.executed',
						'description' => 'Query.id ' . $query_id . ' executed. ' . $query
					)));
				}
			}
			else {
				$querySource->commit();
			}
			
			$this->FedSurvey->create();
			$this->FedSurvey->save(array('FedSurvey' => array(
				'id' => $project['FedSurvey']['id'],
				'total' => $total
			)), true, array('total'));
		}
		else {
			// if its a filter query and parent has been changed, update the parent id
			if ($query['Query']['parent_id'] && $parent_query_id && $query['Query']['parent_id'] != $parent_query_id) {
				$this->Query->create();
				$this->Query->save(array('Query' => array(
					'id' => $query['Query']['id'],
					'parent_id' => $parent_query_id,
				)), true, array('parent_id'));
			}
		}

		return $query_id;
	}
	
	public function get_fed_question($question_id, $language_id) {
		// Check if we have the question in db
		$this->FedQuestion->primaryKey = 'question_id';
		$this->FedQuestion->bindModel(array('hasMany' => array(
			'FedAnswer' => array(
				'foreignKey' => 'question_id',
				'conditions' => array('FedAnswer.language_id' => $language_id)
			)
		)));
		$fed_question = $this->FedQuestion->find('first', array(
			'conditions' => array(
				'FedQuestion.language_id' => $language_id,
				'FedQuestion.question_id' => $question_id
			)
		));
		
		if (!$fed_question) {
			$HttpSocket = new HttpSocket(array(
				'timeout' => 15,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$request_data = array(
				'key' => $this->api_key
			);
			
			$response = $HttpSocket->get($this->api_host . 'Lookup/v1/' . 'QuestionLibrary/QuestionById/' . $language_id . '/' . $question_id , $request_data);
			$api_question = json_decode($response['body'], true);
			
			// to prevent updating existing question (if primary key is question_id)
			$fedQuestionSource = $this->FedQuestion->getDataSource();
			$fedQuestionSource->begin();
			$this->FedQuestion->primaryKey = 'id';
			$this->FedQuestion->create();
			$this->FedQuestion->save(array('FedQuestion' => array(
				'language_id' => $language_id,
				'question_id' => $question_id,
				'question' => $api_question['Question']['QuestionText'],
				'type' => $api_question['Question']['QuestionType']
			)));
			$new_question_id = $this->FedQuestion->getLastInsertID();
			$fedQuestionSource->commit();

			if (in_array($api_question['Question']['QuestionType'], array('Single Punch', 'Multi Punch', 'Dummy'))) {
				$fed_answers = $this->FedAnswer->find('all', array(
					'conditions' => array(
						'FedAnswer.language_id' => $language_id,
						'FedAnswer.question_id' => $question_id
					)
				));
				if (!$fed_answers) {
					$response = $HttpSocket->get($this->api_host . 'Lookup/v1/' . 'QuestionLibrary/AllQuestionOptions/' . $language_id . '/' . $question_id , $request_data);
					$api_question_options = json_decode($response['body'], true);
					
					foreach ($api_question_options['QuestionOptions'] as $option) {
						$this->FedAnswer->create();
						$this->FedAnswer->save(array('FedAnswer' => array(
							'question_id' => $question_id,
							'language_id' => $language_id,
							'precode' => $option['Precode'],
							'answer' => $option['OptionText']
						)));
					}
				}
			}

			$this->FedQuestion->primaryKey = 'question_id';
			$this->FedQuestion->bindModel(array('hasMany' => array(
				'FedAnswer' => array(
					'foreignKey' => 'question_id',
					'conditions' => array('FedAnswer.language_id' => $language_id)
				)
			)));
			$fed_question = $this->FedQuestion->findById($new_question_id);
		}
		
		return $fed_question;
	}
	
	/*
	 * arg: $query_params - call by reference
	 * arg: $db_question - contain current FedQuestion + FedAnswer records
	 * arg: $question - contain fulcrum api question with choosen precodes (answer ids)
	 */
	function get_query_params(&$query_params, $db_question, $question) {
		$mapping_function = $db_question['FedQuestion']['queryable'];
		if ($mapping_function == 'age') {
			$query_params['age_from'] = min($question['PreCodes']);
			$query_params['age_to'] = max($question['PreCodes']);
		}
		elseif ($mapping_function == 'gender') {
			// two genders is same as all, so this can be excluded
			if (count($question['PreCodes']) > 1) {
				return;
			}

			$query_params['gender'] = FedMappings::$mapping_function(current($question['PreCodes']));
		}
		elseif (in_array($mapping_function, array('hhi'))) {
			foreach ($question['PreCodes'] as $precode) {
				$result = FedMappings::$mapping_function($precode);
				if ($result !== false) {
					$query_params[$mapping_function][] = $result;
				}
			}
		}
		elseif ($mapping_function == 'postal_code') {
			foreach ($question['PreCodes'] as $precode) {
				$query_params['postal_code'][] = $precode;
			}
		}
		elseif ($mapping_function == 'dma') {
			$dmas = $this->GeoZip->getDmas();
			foreach ($question['PreCodes'] as $precode) {
				if (array_key_exists($precode, $dmas)) {
					$query_params['dma_code'][] = $precode;
				}
			}
		}
		elseif ($mapping_function == 'ethnicity') {
			foreach ($question['PreCodes'] as $precode) {
				$result = FedMappings::$mapping_function($precode);
				if ($result !== false) {
					$query_params[$mapping_function][] = $result;
				}
			}
		}
		elseif ($mapping_function == 'hispanic') {
			foreach ($question['PreCodes'] as $precode) {
				$result = FedMappings::$mapping_function($precode);
				if ($result !== false) {
					$query_params[$mapping_function][] = $result;
				}
			}
		}
		elseif ($mapping_function == 'children') {
			foreach ($question['PreCodes'] as $precode) {
				$result = FedMappings::$mapping_function($precode);
				if ($result !== false) {
					$query_params[$mapping_function][] = $result;
				}
			}
		}
		elseif ($mapping_function == 'employment') {
			foreach ($question['PreCodes'] as $precode) {
				$result = FedMappings::$mapping_function($precode);
				if ($result !== false) {
					$query_params[$mapping_function][] = $result;
				}
			}
		}
		elseif ($mapping_function == 'job') {
			foreach ($question['PreCodes'] as $precode) {
				$result = FedMappings::$mapping_function($precode);
				if ($result !== false) {
					$query_params[$mapping_function][] = $result;
				}
			}
		}
		elseif ($mapping_function == 'industry') {
			foreach ($question['PreCodes'] as $precode) {
				$result = FedMappings::$mapping_function($precode);
				if ($result !== false) {
					$query_params[$mapping_function][] = $result;
				}
			}
		}
		elseif ($mapping_function == 'organization_size') {
			foreach ($question['PreCodes'] as $precode) {
				$result = FedMappings::$mapping_function($precode, $question['QuestionID']);
				if ($result !== false) {
					if (is_array($result)) {
						$query_params[$mapping_function] = isset($query_params[$mapping_function]) ? array_merge($query_params[$mapping_function], $result) : $result;
					}
					else {
						$query_params[$mapping_function][] = $result;
					}
				}
			}
		}
		elseif ($mapping_function == 'organization_revenue') {
			foreach ($question['PreCodes'] as $precode) {
				$result = FedMappings::$mapping_function($precode);
				if ($result !== false) {
					$query_params[$mapping_function][] = $result;
				}
			}
		}
		elseif ($mapping_function == 'department') {
			foreach ($question['PreCodes'] as $precode) {
				$result = FedMappings::$mapping_function($precode);
				if ($result !== false) {
					$query_params[$mapping_function][] = $result;
				}
			}
		}
		elseif ($mapping_function == 'education') {
			foreach ($question['PreCodes'] as $precode) {
				$result = FedMappings::$mapping_function($precode);
				if ($result !== false) {
					$query_params[$mapping_function][] = $result;
				}
			}
		}
		elseif ($mapping_function == 'state') { // Question_id = 96 is matched.
			App::import('Model', 'GeoState');
			$State = new GeoState();
			foreach ($question['PreCodes'] as $precode) {
				foreach ($db_question['FedAnswer'] as $answer) {
					if ($precode == $answer['precode']) {
						$geo_state = $State->find('first', array(
							'conditions' => array(
								'GeoState.state' => $answer['answer']
							)
						));
						if ($geo_state) {
							$query_params[$mapping_function][] = $geo_state['GeoState']['state_abbr'];
						}
						
						break;
					}
				}
			}
		}
	}
	
	// Return index of the questions array, for a speicific question
	function searchQuestion($question, &$questions) {
		foreach ($questions as $key => $val) {
			if ($val['FedQuestion']['question'] === $question) {
				return $key;
			}
		}
		
		return null;
	}
	
	// Return index of the questions array, for a speicific queryable
	function searchQueryable($queryable, &$questions) {
		foreach ($questions as $key => $val) {
			if ($val['FedQuestion']['queryable']) {
				if ($val['FedQuestion']['queryable'] == $queryable) {
					return $key;
				}
			}
		}
		
		return false;
	}
	
	function run_queries($project, $launch_type = 'full') {
		$this->Query->bindModel(array('hasOne' => array('QueryStatistic')));
		$queries = $this->Query->find('all', array(
			'contain' => array(
				'QueryStatistic'
			),
			'conditions' => array(
				'Query.survey_id' => $project['Project']['id'],
				'Query.parent_id' => '0'
			)
		));
		if (!$queries) {
			return false;
		}

		$sent = false;
		foreach ($queries as $query) {
			if ($launch_type == 'sample') {
				$count = $this->Query->QueryHistory->find('count', array(
					'conditions' => array(
						'QueryHistory.query_id' => $query['Query']['id'],
						'QueryHistory.type' => 'sent',
					)
				));
				
				// we run sample query only once, to avoid users flooded with unknown ir project invitations
				if ($count > 0) {
					$message = 'Skipped ' . $project['Project']['id'] . ' because query history exists';
					echo $message . "\n";
					CakeLog::write('fulcrum.auto', $message);
					continue;
				}
			}

			$results = QueryEngine::execute(json_decode($query['Query']['query_string'], true));
			if ($results['count']['total'] == 0) {
				$message = 'Skipped ' . $project['Project']['id'] . ' because query has no users';
				echo $message . "\n";
				CakeLog::write('fulcrum.auto', $message);
				continue;
			}

			$survey_reach = 0;
			if ($launch_type == 'sample') {
				$setting = $this->Setting->find('first', array(
					'conditions' => array(
						'Setting.name' => 'fulcrum.sample_size',
						'Setting.deleted' => false
					)
				));
				if (!$setting) { // set the default if not found.
					$setting = array('Setting' => array('value' => 50));
				}
				
				$survey_reach = ($results['count']['total'] < $setting['Setting']['value']) ? $results['count']['total'] : $setting['Setting']['value'];
			}
			else {
				$survey_reach = MintVine::query_amount($project, $results['count']['total'], $query);
			}

			if ($survey_reach == 0) {
				$message = 'Skipped ' . $project['Project']['id'] . ' because query has no quota left';
				echo $message . "\n";
				CakeLog::write('fulcrum.auto', $message);
				continue;
			}
			
			if ($survey_reach > 1000) {
				$survey_reach = 1000;
			}

			$queryHistorySource = $this->Query->QueryHistory->getDataSource();
			$queryHistorySource->begin();
			$this->Query->QueryHistory->create();
			$this->Query->QueryHistory->save(array('QueryHistory' => array(
				'query_id' => $query['Query']['id'],
				'item_id' => $query['Query']['survey_id'],
				'item_type' => TYPE_SURVEY,
				'count' => null,
				'total' => null,
				'type' => 'sending'
			)));
			$query_history_id = $this->Query->QueryHistory->getInsertId();
			$queryHistorySource->commit();
			$str_sample = ($launch_type == 'sample') ? ' 1' : '';
			$query = ROOT . '/app/Console/cake query create_queries ' . $query['Query']['survey_id'] . ' ' . $query['Query']['id'] . ' ' . $query_history_id . ' ' . $survey_reach . $str_sample;
			CakeLog::write('query_commands', $query);
			// run these synchronously
			exec($query, $output);
			var_dump($output);
			$message = 'Query executed: '.$query;
			echo $message . "\n";
			CakeLog::write('fulcrum.auto', $message);
			$sent = true;
		}

		return $sent;
	}
	
	
	public function get_response(&$http, $url, $params) {
		try {
			$response = $http->get($url, $params);
			if ($response->code == 200) {
				return $response;
			}
			else {
				echo 'Error: ' . $response->reasonPhrase. "\n";
				return false;
			}
		} catch (Exception $e) {
			return false;
		}
	}
	
	public function autolaunch_close($project_id) {
		$this->Project->create();
		$this->Project->save(array('Project' => array(
			'id' => $project_id,
			'status' => PROJECT_STATUS_CLOSED,
			'active' => false,
			'ended' => date(DB_DATETIME)
		)), true, array('status', 'active', 'ended'));
		// project log set after each instance
	}

}