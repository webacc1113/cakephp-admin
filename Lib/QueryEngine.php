<?php
class QueryEngine {
	
	// special cases to handle 
	public static function qe2_modify_query($json_query) {
		
		$query = json_decode($json_query, true);
		// for qe2, on lucid questions, if we have the fed question and answers and it has all the values, then suppress it
		if ($query['partner'] == 'lucid') {
			App::import('Model', 'Question');
			$Question = new Question;

			// this is a white-list of qualifications we manually manage; so they are marked as ignored in the DB but should be passed on
			$ignored_active_question_ids = array(
				42, // age
				96, // state
				97, // dma
				98, // county
				101, // division
				120, // msa
				122, // region
				14785, // standard HHI US
				640, // STANDARD_SEXUAL_ORIENTATION
				740, // STANDARD_GAMING_PLATFORMS
			); 
			foreach ($query['qualifications'] as $question_id => $answers) {				
				$Question->bindModel(array('hasMany' => array('Answer'))); 
				$question = $Question->find('first', array(
					'contain' => array(
						'Answer' => array(
							'conditions' => array('Answer.ignore' => false)
						)
					),
					'conditions' => array(
						'Question.partner_question_id' => $question_id, 
						'Question.partner' => 'lucid',
					)
				));
				if (!$question) {
					continue;
				}
				
				// filter out ignored question, with the exception of those that are ignored since we manually capture and send them
				if ($question['Question']['ignore'] && !in_array($question['Question']['partner_question_id'], $ignored_active_question_ids)) {
					unset($query['qualifications'][$question_id]);
					continue;
				}
				
				$answer_list = Hash::extract($question, 'Answer.{n}.partner_answer_id');
				if (!empty($answer_list)) {
					foreach ($answers as $key => $partner_answer_id) {
						if (!in_array($partner_answer_id, $answer_list)) {
							unset($query['qualifications'][$question_id][$key]); //Remove ignore answers
						}
					}
					
					if (count($answer_list) == count($query['qualifications'][$question_id])) {
						unset($query['qualifications'][$question_id]);
					}
					else {
						$query['qualifications'][$question_id] = array_values($query['qualifications'][$question_id]);
					}
				}
			}
		
			// todo: how to better handle this case?
			if (empty($query['qualifications'])) {
				return false; 
			}
			$json_query = json_encode($query);
		}
		elseif ($query['partner'] == 'points2shop') {
			App::import('Model', 'Question');
			$Question = new Question;
			foreach ($query['qualifications'] as $question_id => $answers) {
				$Question->bindModel(array('hasMany' => array('Answer'))); 
				$question = $Question->find('first', array(
					'contain' => array(
						'Answer' => array(
							'conditions' => array('Answer.ignore' => false)
						)
					),
					'conditions' => array(
						'Question.partner_question_id' => $question_id,
						'Question.partner' => 'points2shop',
					)
				));
				if (!$question) {
					continue;
				}
				
				$answer_list = Hash::extract($question, 'Answer.{n}.partner_answer_id');				
				if (!empty($answer_list)) {
					foreach ($answers as $key => $partner_answer_id) {
						if (!in_array($partner_answer_id, $answer_list)) {
							unset($query['qualifications'][$question_id][$key]); //Remove ignore answers
						}
					}
					
					if (count($answer_list) == count($query['qualifications'][$question_id])) {
						unset($query['qualifications'][$question_id]);
					}
					else {
						$query['qualifications'][$question_id] = array_values($query['qualifications'][$question_id]);
					}
				}
			}
			if (empty($query['qualifications'])) {
				return false; 
			}
			$json_query = json_encode($query);
		}
		return $json_query;
	}
	
	public static function qe2($settings, $json_query) {
		
		$query = json_decode($json_query, true);
		// for qe2, on lucid questions, if we have the fed question and answers and it has all the values, then suppress it
		if ($query['partner'] == 'lucid') {
			App::import('Model', 'FedAnswer');
			$FedAnswer = new FedAnswer;
			
			foreach ($query['qualifications'] as $question_id => $answers) {
				$fed_answers = $FedAnswer->find('list', array(
					'fields' => array('FedAnswer.id', 'FedAnswer.precode'),
					'conditions' => array(
						'FedAnswer.question_id' => $question_id,
						'FedAnswer.language_id' => '9'
					)
				));
				if (!empty($fed_answers) && count($answers) == count($fed_answers)) {
					unset($query['qualifications']['question_id']);
				}
			}
		
			// todo: how to better handle this case?
			if (empty($query['qualifications'])) {
				return false; 
			}
			$json_query = json_encode($query);
		}
		
		$mt_start = microtime(true);
		$http = new HttpSocket(array(
			'timeout' => 120, 
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$http->configAuth('Basic', $settings['qe.mintvine.username'], $settings['qe.mintvine.password']);
		$results = $http->post($settings['hostname.qe'].'/query', $json_query, array(
			'header' => array('Content-Type' => 'application/json')
		));
		$mt_end = microtime(true);
		$diff = $mt_end - $mt_start; 
		if ($query['partner'] == 'lucid') {
			CakeLog::write('lucid.qe2.api', 'Executed '.$json_query.' in '.$diff.' with response '.print_r($results, true));
		}
		elseif ($query['partner'] == 'points2shop') {
			CakeLog::write('points2shop.qe2.api', 'Executed '.$json_query.' in '.$diff.' with response '.print_r($results, true));
		}

		$body = json_decode($results['body'], true); 
		$panelist_ids = $body['panelist_ids']; 
		return $panelist_ids;
	}
	
	// prioritize users
	public static function prioritize_users($results) {
		App::import('Model', 'User');
		$User = new User;
		
		App::import('Model', 'QueryProfile');
		$QueryProfile = new QueryProfile;
		
		$users = $results['users'];	
		if (empty($users)) {
			return array();
		}
		
		// when grabbing the users; split preference between last active users 
		// get extended user profile information
		$user_emails = array();
		$users = $User->find('all', array(
			'fields' => array('id', 'email', 'ref_id', 'last_touched', 'total', 'send_survey_email', 'send_email'),
			'conditions' => array(
				'User.id' => array_keys($users)
			),
			'order' => array(
				'User.send_email DESC', 
				'User.send_survey_email DESC', 
				'DATE_FORMAT(User.last_touched, "%y-%m-%d") DESC', 
				'User.total DESC'
			),
			'limit' => $number_of_users,
			'recursive' => -1
		));
		return $users;
	}
		
	public static function execute($query, $survey_id = null, $query_type = 'post') {
		// remove type during execution, or it tries to pass it to the DB
		if (isset($query['type'])) {
			unset($query['type']);
		}	
		unset($query['survey_id']);
		
		if ($query_type == 'pre') {
			foreach ($query as $keys => $values) {
				if (in_array($keys, array('postal_code'))) {
					continue;
				}
				if (empty($values)) {
					unset($query[$keys]);
					continue;
				}
				if (in_array($keys, array('user_id'))) {
					continue;
				}
				if (is_array($values)) {
					$save = array();
					foreach ($values as $key => $value) {						
						if ($value == 1) {
							$save[] = $key;
						}
					}
					if (!empty($save)) {
						$query[$keys] = $save;
					}
					else {
						unset($query[$keys]);
					}
				}
			}
		}
		
		$models_to_import = array('QueryProfile', 'User', 'UserProfileAnswer');
		foreach ($models_to_import as $model) {
			App::import('Model', $model);
			$$model = new $model;
		}
				
		// user_ids are a special use case
		if (!empty($query['user_id'])) {
			if (is_string($query['user_id'])) {
				$user_ids = explode("\n", $query['user_id']);
				array_walk($user_ids, create_function('&$val', '$val = trim($val);'));
				$query['user_id'] = $user_ids;
			}
		}
		
		// find profile answers
		if (!empty($query['profiles'])) {
			$user_ids = $UserProfileAnswer->find('list', array(
				'fields' => array('id', 'user_id'),
				'conditions' => array(
					'UserProfileAnswer.profile_answer_id' => $query['profiles']
				)
			));
			if ($user_ids) {
				$user_ids = array_values(array_unique($user_ids)); // reset keys
				if (isset($query['user_id'])) {
					$query['user_id'] = $query['user_id'] + $user_ids;
				}
				else {
					$query['user_id'] = $user_ids;
				} 
			}
			else {
				if (!isset($query['user_id'])) {
					$query['user_id'] = -1;
				}
			}
			unset($query['profiles']);
		}
		
		if (!empty($query['age_from']) || !empty($query['age_to'])) {
			if (isset($query['age_from']) && !empty($query['age_from']) && empty($query['age_to'])) {
				$query['age_to'] = $query['age_from'];
			}
			if (isset($query['age_to']) && !empty($query['age_to']) && empty($query['age_from'])) {
				$query['age_from'] = $query['age_to'];
			}
		
			$seconds_in_year = 31556940;
			if (!empty($query['age_from'])) {
				$query['birthdate <='] = date(DB_DATE, time() - $query['age_from'] * $seconds_in_year);
				unset($query['age_from']);
			}
			if (!empty($query['age_to'])) {
				// add a day to make sure you capture the days correctly
				$query['birthdate >'] = date(DB_DATE, time() - $query['age_to'] * $seconds_in_year - $seconds_in_year + 86400);
				unset($query['age_to']);
			}
		}
		
		$conditions = $query;
		if (isset($conditions['hispanic']) && isset($conditions['ethnicity'])) {
			if (in_array(4, $conditions['ethnicity'])) {
				$conditions['OR'] = array('hispanic' => $conditions['hispanic'], 'hispanic is null');
				unset($conditions['hispanic']);
			}
		}
		
		if (isset($conditions['keyword']) && !empty($conditions['keyword'])) {
			$users = $User->find('list', array(
				'fields' => array('id', 'id'),
				'conditions' => array(
					'OR' => array(
						'User.email LIKE' => '%'.mysql_escape_string($conditions['keyword']).'%',
						'User.username LIKE' => '%'.mysql_escape_string($conditions['keyword']).'%',
					)
				)
			));
			if ($users) {
				$users = array_values($users);
				if (isset($conditions['user_id']) && !empty($conditions['user_id'])) {
					$conditions['user_id'] = $conditions['user_id'] + $users;
				}
				else {
					$conditions['user_id'] = $users;
				}
			}
			else {
			//	$conditions['user_id'] = -1;
			}
			unset($conditions['keyword']);
		}
		$users = $QueryProfile->find('all', array(
			'fields' => array('user_id'),
			'conditions' => $conditions,
			'joins' => array(
    		    array(
		            'alias' => 'User',
		            'table' => 'users',
		            'conditions' => array(
						'QueryProfile.user_id = User.id',
						'User.active' => true,
						'User.hellbanned' => false
					)
		        )
			)
		));
		$dbquery = $QueryProfile->getLastQuery();
		if ($users) {
			$return_users = array();
			$users = array_values($users);
			foreach ($users as $user) {
				$return_users[$user['QueryProfile']['user_id']] = $user['QueryProfile']['user_id'];
			}
			$users = $return_users;
			unset($return_users);
		}
		
		$return = array(
			'query' => $query,
			'count' => array(
				'total' => count($users),
			),
			'dbquery' => $dbquery
		);
		
		// remove all users who already invited to this survey
		if (!empty($survey_id)) {			
			App::import('Model', 'SurveyUser');
			$SurveyUser = new SurveyUser;
		
			// find users who are already in this survey
			$survey_users = $SurveyUser->find('list', array(
				'fields' => array('id', 'user_id'),
				'conditions' => array(
					'SurveyUser.survey_id' => $survey_id
				),
				'recursive' => -1
			));
			if (!empty($users) && $survey_users) {
				foreach ($survey_users as $user_id) {
					unset($users[$user_id]);
				}
				$return['count']['total'] = count($users);
			}
		}
		$return['users'] = $users;			
		
		return $return;
	}
	
	public static function convert_query_to_v2_format($query_string) {		
		// convert birthdates to a special case
		if (isset($query_string['birthdate <=']) && isset($query_string['birthdate >'])) {
			$youngest = $query_string['birthdate <='];
			$oldest = $query_string['birthdate >'];
			
			$datetime1 = date_create($youngest);
			$datetime2 = date_create($oldest);
			
			$high_age = date_diff(new DateTime(), $datetime2);				
			$low_age = date_diff(new DateTime(), $datetime1);
			
			$ages = range($low_age->format('%Y'), $high_age->format('%Y'));
			unset($query_string['birthdate <=']);
			unset($query_string['birthdate >']);
			$query_string['birthdate'] = $ages;
		}
		
		if (isset($query_string['age_to']) && isset($query_string['age_from'])) {
			$age_to = $query_string['age_to'];
			$age_from = $query_string['age_from'];
			$query_string['birthdate'] = range($age_from, $age_to);
			unset($query_string['age_from']);
			unset($query_string['age_to']);
		}
		foreach ($query_string as $key => $val) {
			if (!is_array($val)) {
				$query_string[$key] = array($val);
			}
			if (is_array($val)) {
				$query_string[$key] = array_values(array_unique($val)); 
			}
		}
		return $query_string;
	}
}
