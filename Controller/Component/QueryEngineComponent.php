<?php
App::uses('Component', 'Controller');
class QueryEngineComponent extends Component {
	
	// prioritize users
	function prioritize_users($results) {
		App::import('Model', 'User');
		$this->User = new User;
		
		$users = $results['users'];	
		if (empty($users)) {
			return array();
		}
		
		// when grabbing the users; split preference between last active users 
		// get extended user profile information
		$user_emails = array();
		$users = $this->User->find('all', array(
			'fields' => array('id', 'email', 'ref_id', 'last_touched', 'total', 'send_survey_email', 'send_email', 'fulcrum'),
			'conditions' => array(
				'User.id' => array_keys($users)
			),
			'order' => array(
				'User.last_touched DESC', 
			),
			'recursive' => -1
		));
		return $users;
	}
		
	function execute($query, $survey_id = null, $query_type = 'post') {
		// remove type during execution, or it tries to pass it to the DB
		if (isset($query['type'])) {
			unset($query['type']);
		}
		
		unset($query['survey_id']);
		if ($query_type == 'pre') {
			foreach ($query as $keys => $values) {
				if (in_array($keys, array('postal_code', 'postal_prefix', 'county_fips'))) {
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
		$models_to_import = array('QueryProfile', 'User', 'UserProfileAnswer', 'RegionMapping', 'LucidZip');
		foreach ($models_to_import as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		// convert county FIPs into ZIP codes
		if (isset($query['county_fips']) && !empty($query['county_fips'])) {
			$zips = array();
			foreach ($query['county_fips'] as $county_fip) {
				$state_code = (int) substr($county_fip, 0, 2); 
				$county_code = (int) substr($county_fip, 2, 3); 
				$lucid_zips = $this->LucidZip->find('list', array(
					'fields' => array('LucidZip.zipcode'),
					'conditions' => array(
						'LucidZip.state_fips' => $state_code,
						'LucidZip.county_fips' => $county_code
					),
					'recursive' => -1
				));
				$lucid_zips = array_values(array_unique($lucid_zips));
				$zips = array_merge($zips, $lucid_zips); 
			}
			if (!empty($query['postal_code'])) {
				$query['postal_code'] = array_merge($query['postal_code'], $zips);
			}
			else {
				$query['postal_code'] = $zips;
			}
			unset($query['county_fips']);
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
			$user_ids = $this->UserProfileAnswer->find('list', array(
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
		
		// if country is other then US
		if (isset($query['country']) && $query['country'] != 'US') {
			unset($query['state']);
			unset($query['dma_code']);
			unset($query['county_fips']);
			if (in_array($query['country'], array('CA', 'GB')) && isset($query['region'.$query['country']])) {
				$region_mappings = $this->RegionMapping->find('list', array(
					'fields' => array('postal_prefix', 'region'),
					'conditions' => array(
						'RegionMapping.region' => $query['region'.$query['country']]
					)
				));
			}
		}
		
		// postal prefix conditions
		$postal_prefix_conditions = array();
		if (isset($region_mappings) && !empty($region_mappings)) {
			foreach ($region_mappings as $key => $region_mapping) {
				$postal_prefix_conditions[] = array('postal_code LIKE' => $key. '%'); 
			}
		}
		
		if (isset($query['postal_prefix']) && !empty($query['postal_prefix'])) {
			foreach ($query['postal_prefix'] as $postal_prefix) {
				$postal_prefix_conditions[] = array('postal_code LIKE' => $postal_prefix. '%');
			}
		}
		
		$conditions = $query;
		// remove temp params
		unset($conditions['regionCA']);
		unset($conditions['regionGB']);
		unset($conditions['postal_prefix']);
		
		// bringing postal conditions togather - full + prefix
		if (isset($conditions['postal_code']) && !empty($conditions['postal_code']) && !empty($postal_prefix_conditions)) {
			$postal_conditions['OR'] = array(
				'postal_code' => $conditions['postal_code'],
				'OR' => $postal_prefix_conditions
			);
			unset($conditions['postal_code']);
		}
		elseif (!empty($postal_prefix_conditions)) {
			$postal_conditions['OR'] = $postal_prefix_conditions;
		}
		
		if (isset($conditions['existing_project_id'])) {
			unset($conditions['existing_project_id']);
		}
		if (isset($conditions['exclude_user_id'])) {
			unset($conditions['exclude_user_id']);
		}
		
		if (isset($conditions['hispanic']) && isset($conditions['ethnicity'])) {
			if (in_array(4, $conditions['ethnicity'])) {
				$conditions['OR'] = array('hispanic' => $conditions['hispanic'], 'hispanic is null');
				unset($conditions['hispanic']);
			}
		}
		
		// the order of this check is important - must be after the first OR condition
		if (!empty($postal_conditions)) {
			if (isset($conditions['OR'])) {
				$conditions['AND'] = array(
					'OR' => $conditions['OR'],
					$postal_conditions
				);
				unset($conditions['OR']);
			}
			else {
				$conditions['OR'] = $postal_conditions['OR'];
			}
		}
		
		if (isset($conditions['keyword']) && !empty($conditions['keyword'])) {
			$users = $this->User->find('list', array(
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
		
		$conditions['QueryProfile.ignore'] = false;
		$users = $this->QueryProfile->find('all', array(
			'fields' => array('user_id'),
			'conditions' => $conditions
		));
		$dbquery = $this->QueryProfile->getLastQuery();
		
		if (!empty($query['exclude_user_id'])) {
			if (is_string($query['exclude_user_id'])) {
				$exclude_user_ids = explode("\n", $query['exclude_user_id']);
				array_walk($exclude_user_ids, create_function('&$val', '$val = trim($val);'));
			}
		}
		if (!empty($query['existing_project_id'])) {
			$existing_project_ids = explode(',', $query['existing_project_id']);
			array_walk($existing_project_ids, create_function('&$val', '$val = trim($val);'));
			foreach ($existing_project_ids as $key => $existing_project_id) {
				$project_id = MintVine::parse_project_id($existing_project_id);
				if (!$project_id) {
					continue;
				}
				$existing_project_ids[$key] = $project_id;
			}
			if (!empty($existing_project_ids)) {				
				App::import('Model', 'SurveyUserVisit');
				$this->SurveyUserVisit = new SurveyUserVisit;
				$excluded_from_project_users = $this->SurveyUserVisit->find('list', array(
					'recursive' => -1,
					'conditions' => array(
						'SurveyUserVisit.survey_id' => $existing_project_ids,
						'SurveyUserVisit.status' => SURVEY_COMPLETED
					),
					'fields' => array('user_id')
				));
			}
		}
		
		if ($users) {
			ini_set('max_execution_time', 180);
			$return_users = array();
			$users = array_values($users);
			foreach ($users as $user) {
				
				// excluded users removed manually
				if (!empty($query['exclude_user_id']) && isset($exclude_user_ids)) { 
					if (in_array($user['QueryProfile']['user_id'], $exclude_user_ids)) {
						continue;
					}
				}
				if (!empty($query['existing_project_id']) && isset($excluded_from_project_users)) {
					if (in_array($user['QueryProfile']['user_id'], $excluded_from_project_users)) {
						continue;
					}
				}
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
		
		$return['users'] = $users;			
		
		return $return;
	}
}
