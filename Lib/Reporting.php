<?php

class Reporting {
		
	public static function source_publishers($source) {		
		$models = array('User');
		foreach ($models as $model) {
			App::import('Model', $model);
			$$model = new $model;
		}
		$conditions = array();
		switch ($source) {
			case 'panthera': 
				$conditions['User.origin LIKE'] = '%:pt';
			break;
			case 'panthera2': 
				$conditions['User.origin LIKE'] = '%:pt2';
			break;
			case 'panthera3': 
				$conditions['User.origin LIKE'] = '%:pt3';
			break;
			case 'panthera4': 
				$conditions['User.origin LIKE'] = '%:pt4';
			break;
			case 'mvf-lander': 
				$conditions['User.origin LIKE'] = 'fb_lander%:mvf';
			break;
			case 'mvf-coreg': 
				$conditions['User.origin'] = 'api:mvf';
			break;
			case 'coreg': 
				$conditions['User.origin'] = 'coreg';
			break;
			case 'rocketroi': 
				$conditions['User.origin'] = 'fb_lander_1:roi';					
			break;
			case 'competeroi': 
				$conditions['User.origin'] = 'compete:roi';					
			break;
			case 'fborganic': 
				$conditions['User.origin'] = 'fborganic';					
			break;
			case 'competeroi2': 
				$conditions['User.origin'] = 'compete:roi2';					
			break;
			case 'facebook': 
				$conditions['User.origin LIKE'] = '%fb_lander%';		
			break;
			case 'fbint': 
				$conditions['User.origin'] = 'fb_lander_2_6:fbint';		
			break;
			case 'fbext': 
				$conditions['User.origin'] = 'fb_lander_2_6:fbext';		
			break;
			case 'ad': 
				$conditions['User.origin LIKE'] = 'fb_lander%:ad';
			break;
			case 'sc-couch-fb-tab': 
				$conditions['User.origin'] = 'fb_lander_2_8:fbint';
			break;
			case 'sc-couch-fb-ext': 
				$conditions['User.origin'] = 'fb_lander_2_8:fbext';
			break;
			case 'sc-couple-fb-tab': 
				$conditions['User.origin'] = 'fb_lander_2_9:fbint';
			break;
			case 'sc-couple-fb-ext': 
				$conditions['User.origin'] = 'fb_lander_2_9:fbext';
			break;
			case 'sc-couple-red-fb-tab': 
				$conditions['User.origin'] = 'fb_lander_2_10:fbint';
			break;
			case 'sc-couple-red-fb-ext': 
				$conditions['User.origin'] = 'fb_lander_2_10:fbext';
			break;
			case 'sc-couple-ill-fb-tab': 
				$conditions['User.origin'] = 'fb_lander_2_11:fbint';
			break;
			case 'sc-couple-ill-fb-ext': 
				$conditions['User.origin'] = 'fb_lander_2_11:fbext';
			break;
			case 'mvm-pt':
				$conditions['User.origin'] = 'mvm:pt';
				break;
			case 'mvm-ad':
				$conditions['User.origin'] = 'mvm:ad';
				break;
			case 'mvm-science':
				$conditions['User.origin'] = 'mvm:science';
				break;
		}

		$publishers = $User->find('all', array(
			'recursive' => -1, 
			'fields' => array('DISTINCT(pub_id)'), 
			'conditions' => $conditions
		));
		
		$return = array();
		if ($publishers) {
			foreach ($publishers as $publisher) {
				if (empty($publisher['User']['pub_id'])) {
					continue; /// todo: handle "empty" publishers
				}
				$return[] = $publisher['User']['pub_id'];
			}
		}
		return $return;
	}
	
	public static function user_sources($source_name, $date_from, $date_to, $time_zone, $pub_id = null, $show_genders = false) {
		$models = array('User', 'Transaction', 'QueryProfile');
		foreach ($models as $model) {
			App::import('Model', $model);
			$$model = new $model;
		}
		
		$total_registrations = $total_activations = $total_points = $average_points = 0;
		
		$conditions['source'] = $conditions['publisher'] = $conditions['created'] = $conditions['verified'] = $conditions['hellbanned'] = array();
		
		$conditions['hellbanned']['User.hellbanned'] = 1;

		if (isset($pub_id) && !is_null($pub_id)) {
			$conditions['publisher']['User.pub_id'] = $pub_id;
		}
		$conditions['source']['User.origin'] = $source_name;
		
		if (isset($date_from) && !empty($date_from)) {
			$date_from_value = date(DB_DATE, strtotime($date_from)).' 00:00:00';
			if (!empty($time_zone)) {
				$dt_from = new DateTime($date_from_value);
				$tz = new DateTimeZone($time_zone);
				$dt_from->setTimeZone($tz);
				$date_from_value = $dt_from->format('Y-m-d H:i:s');
			}
			
			if (isset($date_to) && !empty($date_to)) {
				$date_to_value = date(DB_DATE, strtotime($date_to)).' 23:59:59';
				if (!empty($time_zone)) {
					$dt_to = new DateTime($date_to_value);
					$tz = new DateTimeZone($time_zone);
					$dt_to->setTimeZone($tz);
					$date_to_value = $dt_to->format('Y-m-d H:i:s');
				}
				if ($date_from == $date_to) {
					$conditions['created']['User.created >='] = $conditions['verified']['User.verified >=']	= $conditions['hellbanned']['User.hellbanned_on <='] = $date_from_value;
					$conditions['created']['User.created <='] = $conditions['verified']['User.verified <='] = $conditions['hellbanned']['User.hellbanned_on >='] = $date_to_value;
				}
				else {
					$conditions['created']['User.created >='] = $conditions['verified']['User.verified >='] = $conditions['hellbanned']['User.hellbanned_on >='] = $date_from_value;
					$conditions['created']['User.created <='] = $conditions['verified']['User.verified <='] = $conditions['hellbanned']['User.hellbanned_on <='] = $date_to_value;
				}
			}
			else {
				$conditions['created']['User.created >='] = $conditions['verified']['User.verified >='] = $conditions['hellbanned']['User.hellbanned_on >='] = $date_from_value;
				$conditions['created']['User.created <='] = $conditions['verified']['User.verified <='] = $conditions['hellbanned']['User.hellbanned_on <='] = $date_to_value;
			}
		}
		$users = $User->find('list', array(
			'recursive' => -1,
			'fields' => array('id', 'username'),
			'conditions' => $conditions['source'] + $conditions['created'] + $conditions['publisher'],
			'recursive' => -1
		));
		$males = $females = $activated_males = $activated_females = $total_survey_starts = $total_survey_start_males = $total_survey_start_females = 0;
		if ($show_genders) {
			$males = $QueryProfile->find('count', array(
				'recursive' => -1,
				'conditions' => array(
					'QueryProfile.user_id' => array_keys($users),
					'QueryProfile.gender' => 'M'
				)
			));
			$females = $QueryProfile->find('count', array(
				'recursive' => -1,
				'conditions' => array(
					'QueryProfile.user_id' => array_keys($users),
					'QueryProfile.gender' => 'F'
				)
			));
		}
		$total_registrations = count($users); 
		if ($total_registrations > 0) {
			$users = $User->find('list', array(
				'recursive' => -1,
				'fields' => array('id', 'username'),
				'conditions' => $conditions['source'] + $conditions['verified'] + $conditions['publisher'],
			));
			
			if ($show_genders) {
				$activated_males = $QueryProfile->find('count', array(
					'recursive' => -1,
					'conditions' => array(
						'QueryProfile.user_id' => array_keys($users),
						'QueryProfile.gender' => 'M'
					)
				));
				$activated_females = $QueryProfile->find('count', array(
					'recursive' => -1,
					'conditions' => array(
						'QueryProfile.user_id' => array_keys($users),
						'QueryProfile.gender' => 'F'
					)
				));
			}
			$total_activations = count($users); 
		}
		if ($total_registrations > 0) {
			$users = $User->find('list', array(
				'recursive' => -1,
				'fields' => array('id', 'first_survey'),
				'conditions' => $conditions['source'] + $conditions['publisher'] + array('User.first_survey is not null'),
			));
			$total_survey_starts = count($users);
			if ($total_survey_starts > 0 && $show_genders) {
				$total_survey_start_males = $QueryProfile->find('count', array(
					'recursive' => -1,
					'conditions' => array(
						'QueryProfile.user_id' => array_keys($users),
						'QueryProfile.gender' => 'M'
					)
				));
				$total_survey_start_females = $QueryProfile->find('count', array(
					'recursive' => -1,
					'conditions' => array(
						'QueryProfile.user_id' => array_keys($users),
						'QueryProfile.gender' => 'F'
					)
				));
			}
		}
		
		if ($total_registrations > 0 && $total_activations > 0) {
			$total_points = $User->find('first', array(
				'fields' => 'SUM( User.total ) AS total_points',
				'conditions' => $conditions['source'] + $conditions['created'] + $conditions['publisher'],
				'recursive' => -1
			));
			$total_points = $total_points[0]['total_points'];
		}
		
		if ($total_activations > 0) {
			$average_points = $total_points / $total_activations;
		}
		
		$hellbanned = $User->find('count', array(
			'fields' => 'DISTINCT User.id',
			'conditions' => $conditions['source'] + $conditions['hellbanned'] + $conditions['publisher'],
			'recursive' => -1
		));
		
		return array(
			'source' => $source_name,
			'males' => $males,
			'females' => $females,
			'activated_males' => $activated_males,
			'activated_females' => $activated_females,
			'publisher' => $pub_id,
			'total_registrations' => $total_registrations,
			'total_activations' => $total_activations,
			'total_survey_starts' => $total_survey_starts,
			'total_survey_start_females' => $total_survey_start_females,
			'total_survey_start_males' => $total_survey_start_males,
			'total_points' => $total_points,
			'average_points' => $average_points,
			'hellbanned' => $hellbanned,
		);	
	}
}