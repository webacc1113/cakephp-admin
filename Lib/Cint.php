<?php

class Cint {
	
	// does this user match a project?
	public static function doesMatchProject($user, $respondent_quota, $settings) {
		$models_to_import = array('CintRegion', 'GeoZip', 'GeoState', 'CintAnswer', 'Group');
		
		foreach ($models_to_import as $model_to_import) {
			App::import('Model', $model_to_import);
			$$model_to_import = new $model_to_import;
		}
		$group = $Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'cint'
			),
			'recursive' => -1
		));
		// check cutoff loi
		if ($respondent_quota['statistics']['length_of_interview'] > $group['Group']['max_loi_minutes']) {
			return false;
		}
		
		// determine if this respondent quota is good for us
		if ($respondent_quota['fulfillment']['estimated_remaining_completes'] < $settings['cint.cutoff.quota']) {
			return false;
		}
		
		// match basic demographics - gender
		if (isset($respondent_quota['target_group']['gender'])) {
			if ($respondent_quota['target_group']['gender'] != 'U' && $respondent_quota['target_group']['gender'] != $user['QueryProfile']['gender']) {
				return false;
			}
		}
		
		// match basic demographics - age
		if (isset($user['QueryProfile']['birthdate'])) {
			$age = floor((time() - strtotime($user['QueryProfile']['birthdate'])) / 31557600);
			if (isset($respondent_quota['target_group']['min_age']) && $age < $respondent_quota['target_group']['min_age']) {
				return false;
			}
			if (isset($respondent_quota['target_group']['max_age']) && $age > $respondent_quota['target_group']['max_age']) {
				return false;
			}
		}
		
		// match regions
 		if (isset($respondent_quota['target_group']['region_ids']) && !empty($respondent_quota['target_group']['region_ids'])) {
			$region_match = false;
			foreach ($respondent_quota['target_group']['region_ids'] as $region_id) {
				$region = $CintRegion->find('first', array(
					'conditions' => array(
						'cint_id' => $region_id
					)
				));
				if (!$region) {
					continue;
				}
				$states = array(); // set this for later matching into regions
				if ($region['CintRegion']['type'] == 'dma') {
					$region_dma = CintMappings::region($region['CintRegion']['name']);
					$geo_zip = $GeoZip->find('first', array(
						'conditions' => array(
							'GeoZip.dma like' => '%'.$region['CintRegion']['name'].'%',
							'GeoZip.country_code' => 'US'
						)
					));
					if ($geo_zip) {
						if ($geo_zip['GeoZip']['dma_code'] == $user['QueryProfile']['dma_code']) {
							$region_match = true;
							break;
						}
					}
				}
				elseif ($region['CintRegion']['type'] == 'state') {	
					$geo_state = $GeoState->find('first', array(
						'conditions' => array(
							'GeoState.state' => $region['CintRegion']['name']
						)
					));
					if ($geo_state && $user['QueryProfile']['state'] == $geo_state['GeoState']['state_abbr']) {
						$region_match = true;
						break;
					}
				} 
				elseif ($region['CintRegion']['type'] == 'Main regions') {
					switch ($region['CintRegion']['name']) {
						case 'Northeast':
							$states = array('CT', 'ME', 'MA', 'NH', 'RI', 'VT', 'NJ', 'NY', 'PA');
						break;
						case 'South':
							$states = array('DE', 'FL', 'GA', 'MD', 'RI', 'NC', 'SC', 'VA', 'WV', 'AL', 'KY', 'MS', 'TN', 'AR', 'LA', 'OK', 'TX');
						break;
						case 'West':
							$states = array('AZ', 'CO', 'ID', 'MO', 'NV', 'NM',' UT', 'WY', 'AL', 'CA', 'HI', 'OR', 'WA');
						break;
						case 'Midwest':
							$states = array('IL', 'IN', 'MI', 'OH', 'WI', 'IA', 'KS', 'MN', 'MI', 'NE', 'ND', 'SD');
						break;
					}
					if (in_array($user['QueryProfile']['state'], $states)) {
						$region_match = true;
						break;
					}					
				} 
				elseif ($region['CintRegion']['type'] == 'Divisions') {
					switch ($region['CintRegion']['name']) {
						case 'New England':
							$states = array('CT', 'ME', 'MA', 'NH', 'RI', 'VT');
						break;
						case 'South Atlantic':
							$states = array('DE', 'FL', 'GA', 'MD', 'NC', 'SC', 'VA', 'WV');
						break;
						case 'Pacific':
							$states = array('AL', 'CA', 'HI', 'OR', 'WA');
						break;
						case 'West South Central':
							$states = array('AR', 'LA', 'OK', 'TX');
						break;
						case 'West North Central':
							$states = array('IA', 'KS', 'MN', 'MI', 'NE', 'ND', 'SD');
						break;
						case 'Mountain':
							$states = array('AR', 'CO', 'ID', 'MO', 'NV', 'NM', 'UT', 'WY');
						break;
						case 'East South Central':
							$states = array('AL', 'KY', 'MI', 'TN');
						break;
						case 'East North Central':
							$states = array('IL', 'IN', 'MI', 'OH', 'WI');
						break;
						case 'Mid - Atlantic':
							$states = array('NJ', 'NY', 'PA');
						break;
					}
					if (in_array($user['QueryProfile']['state'], $states)) {
						$region_match = true;
						break;
					}	 				
				} 
				elseif ($region['CintRegion']['type'] == 'Counties') {
					// Future Counties
				}
				elseif ($region['CintRegion']['type'] == 'Metropolitan Statistical Areas') {
					$geo_msa = $GeoZip->find('first', array(
						'conditions' => array(
							'GeoZip.zipcode' => $user['QueryProfile']['postal_code'],
							'GeoZip.country_code' => 'US'
						)
					));
					if ($geo_msa) {
						if ($geo_msa['GeoZip']['msa'] == $region['CintRegion']['raw']) {
							$region_match = true;
							break;
						}
					}
				}
			}
			
			if (!$region_match) {
				return false;
			}
		}
		
		// todo: writing the answers locally?
		if (isset($respondent_quota['target_group']['variable_ids']) && !empty($respondent_quota['target_group']['variable_ids'])) {
			$variable_match = true;
			foreach ($respondent_quota['target_group']['variable_ids'] as $variable_id) {
				
				$CintAnswer->bindModel(array('belongsTo' => array(
					'CintQuestion' => array('foreignKey' => 'question_id')
				)));
				$cint_answer = $CintAnswer->find('first', array(
					'contain' => array('CintQuestion'),
					'conditions' => array(
						'CintAnswer.variable_id' => $variable_id
					)
				)); 
				if (!$cint_answer) {
					$variable_match = false;
					break;
				}
				if ($cint_answer['CintQuestion']['question_id'] == 277416) {
					$result = CintMappings::education_profile_277416($cint_answer['CintAnswer']['variable_id']);
				}
				elseif ($cint_answer['CintQuestion']['question_id'] == 369619) {
					$result = CintMappings::education_profile_369619($cint_answer['CintAnswer']['variable_id']);
				}
				elseif ($cint_answer['CintQuestion']['question_id'] == 275024) {
					$result = CintMappings::smartphone_275024($cint_answer['CintAnswer']['variable_id']);
				}
				elseif ($cint_answer['CintQuestion']['question_id'] == 369636) {
					$result = CintMappings::smartphone_369636($cint_answer['CintAnswer']['variable_id']);
				}
				elseif ($cint_answer['CintQuestion']['queryable'] == 'education') {
					$result = CintMappings::education_profile($cint_answer['CintAnswer']['variable_id'], $user['QueryProfile']['country'], true);
				}
				elseif ($cint_answer['CintQuestion']['queryable'] == 'employment') {
					$result = CintMappings::employment_profile($cint_answer['CintAnswer']['variable_id'], $user['QueryProfile']['country'], true);
				}
				elseif (method_exists('CintMappings', $cint_answer['CintQuestion']['queryable'])) {
					$result = CintMappings::$mapping_function($cint_answer['CintAnswer']['variable_id'], $user['QueryProfile']['country'], true);
				}
			}
			return false; // for now let's not even try to map these
		}
				
		return true;
	}
}