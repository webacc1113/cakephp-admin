<?php
App::import('Lib', 'SpectrumMappings');
class SpectrumTask extends Shell {
	public $uses = array('Client');
	
	public function payout($spectrum_survey) {
		$return = array(
			'client_rate' => $spectrum_survey['cpi'],
			'partner_rate' => 0, // dollars
			'award' => 0 // points
		);
		
		if (!empty($return['client_rate'])) {
			$return['partner_rate'] = round($return['client_rate'] * 4 / 10, 2);
		}
		if (!empty($return['partner_rate'])) {
			// no partner payout greater than $2.00
			if ($return['partner_rate'] > 2) {
				$return['partner_rate'] = 2;
			}
			$return['award'] = $return['partner_rate'] * 100;
		}
		return $return;
	}
	
	public function quota($spectrum_survey) {
		return $spectrum_survey['supplier_completes']['remaining'];
	}
	
	public function ir($spectrum_survey) {
		return $spectrum_survey['survey_conversion_rate']; // todo: Need to confirm if it in % or just ratio?
	}
	
	public function loi($spectrum_survey) {
		$loi = $spectrum_survey['loi'];
		if (empty($loi)) {
			$loi = 15; // fallback bc system can't handle 0 loi very well
		}
		return $loi;
	}
	
	public function language($spectrum_survey) {
		$language_code = false;
		if (!empty($spectrum_survey['languages'])) {
			foreach ($spectrum_survey['languages'] as $language) {
				if ($language['code'] == 'eng') {
					$language_code = 'eng';
					break;
				}
			}
			if (!$language_code) {
				$language_code = $spectrum_survey['languages']['0']['code'];
			}
		}
		return SpectrumMappings::language($language_code);
	}
	
	public function is_closed($spectrum_survey) {
		$close_project = false;
		if ($spectrum_survey['survey_status'] != 'Live') {
			$close_project = true;
		}
		elseif ($spectrum_survey['supplier_completes']['remaining'] <= 0) {
			$close_project = true;
		}
		return $close_project;
	}
	
	public function mappings(&$query_params, $question) {
		App::import('Vendor', 'site_profile');
		// Using code because api owener changing name of question on api revisions
		if (isset($question['qualification_code'])) {
			$question_code = $question['qualification_code'];
		}
		else {
			$question_code = $question['code'];
		}
		
		if ($question_code == 211) {
			// two genders is same as all, so this can be excluded
			if (count($question['conditions']) > 1) {
				return 'gender';
			}
			$result = SpectrumMappings::gender($question['conditions'][0]['code']);
			if ($result) {
				$query_params['gender'] = array($result);
			}
		}
		elseif ($question_code == 212) {
			$birthdates = array();
			foreach ($question['conditions'] as $condition) { // In case api return multiple age groups 20-30, 40-50 etc
				$birthdates = array_merge($birthdates, range($condition['from'], $condition['to']));
			}
			$query_params['birthdate'] = array_unique($birthdates);
		}
		elseif ($question_code == 213) {
			$hhi = array();
			foreach ($question['conditions'] as $condition) {
				$result = SpectrumMappings::hhi($condition['from'], $condition['to']); // return an array of options that comes in this range
				if ($result) {
					$hhi = array_merge($hhi, $result);
				}
			}
			$query_params['hhi'] = array_unique($hhi);
			if (count(unserialize(USER_HHI)) == count($query_params['hhi'])) {
				unset($query_params['hhi']);
				return 'hhi';
			}
		}
		elseif ($question_code == 214) {
			$ethnicity = array(); 
			foreach ($question['conditions'] as $condition) {
				$result = SpectrumMappings::ethnicity($condition['code']);
				if (!empty($result) || $result === '0') {
					$ethnicity[] = $result;
				}
			}
			$query_params['ethnicity'] = array_unique($ethnicity);
			if (count(unserialize(USER_ETHNICITY)) == count($query_params['ethnicity'])) {
				unset($query_params['ethnicity']);
				return 'ethnicity';
			}
		}
		elseif ($question_code == 215) {
			$employment = array();
			foreach ($question['conditions'] as $condition) {
				$result = SpectrumMappings::employment($condition['code']);
				if (!empty($result) || $result === '0') {
					$employment[] = $result;
				}
			}
			$query_params['employment'] = array_unique($employment);
			if (count(unserialize(USER_EMPLOYMENT)) == count($query_params['employment'])) {
				unset($query_params['employment']);
				return 'employment';
			}
		}
		elseif ($question_code == 216) {
			$education = array();
			foreach ($question['conditions'] as $condition) {
				$result = SpectrumMappings::education($condition['code']);
				if ($result) {
					$education[] = $result;
				}
			}
			$query_params['education'] = array_unique($education);
			if (count(unserialize(USER_EDU)) == count($query_params['education'])) {
				unset($query_params['education']);
				return 'education';
			}
		}
		elseif ($question_code == 217) {
			$relationship = array();
			foreach ($question['conditions'] as $condition) {
				$result = SpectrumMappings::relationship($condition['code']);
				if ($result) {
					$relationship[] = $result;
				}
			}
			$query_params['relationship'] = array_unique($relationship);
			if (count(unserialize(USER_MARITAL)) == count($query_params['relationship'])) {
				unset($query_params['relationship']);
				return 'relationship';
			}
		}
		elseif ($question_code == 218) {
			// two children conditions children yes or no means is same as all, so this can be excluded
			if (count($question['conditions']) > 1) {
				return 'children';
			}
			$result = SpectrumMappings::children($question['conditions'][0]['code']);
			if (!empty($result) || $result === '0') {
				$query_params['children'] = array($result);
			}
		}
		elseif ($question_code == 223) { // regions
			if (!isset($query_params['state'])) {
				$query_params['state'] = array();
			}
			
			App::import('Model', 'GeoState');
			$GeoState = new GeoState();
			foreach ($question['conditions'] as $region) {
				$geo_states = $GeoState->find('list', array(
					'fields' => array('GeoState.id', 'GeoState.state_abbr'),
					'conditions' => array(
						'GeoState.region' => $region['name']
					)
				));
				
				if ($geo_states) {
					$query_params['state'] = array_merge($query_params['state'], $geo_states);
				}
			}
			$query_params['state'] = array_unique($query_params['state']);
		}
		elseif ($question_code == 224) { // divisions = sub_region
			if (!isset($query_params['state'])) {
				$query_params['state'] = array();
			}
			
			App::import('Model', 'GeoState');
			$GeoState = new GeoState();
			foreach ($question['conditions'] as $division) {
				$geo_states = $GeoState->find('list', array(
					'fields' => array('GeoState.id', 'GeoState.state_abbr'),
					'conditions' => array(
						'GeoState.sub_region' => $division['name']
					)
				));
				
				if ($geo_states) {
					$query_params['state'] = array_merge($query_params['state'], $geo_states);
				}
			}
			$query_params['state'] = array_unique($query_params['state']);
		}
		elseif ($question_code == 225) { // states
			App::import('Model', 'GeoState');
			$GeoState = new GeoState();
			foreach ($question['conditions'] as $state) {
				$geo_state = $GeoState->find('first', array(
					'conditions' => array(
						'GeoState.state' => $state['name']
					)
				));
				if ($geo_state) {
					$query_params['state'][] = $geo_state['GeoState']['state_abbr'];
				}
			}
			$query_params['state'] = array_unique($query_params['state']);
		}
		elseif ($question_code == 226) { // MSA
			if (!isset($query_params['msa'])) {
				$query_params['msa'] = array();
			}
			
			foreach ($question['conditions'] as $msa) {
				$query_params['msa'][] = $msa['code'];
			}
			$query_params['msa'] = array_unique($query_params['msa']);
		}
		elseif ($question_code == 227) { // CSA
			if (!isset($query_params['csa'])) {
				$query_params['csa'] = array();
			}
			
			foreach ($question['conditions'] as $csa) {
				$query_params['csa'][] = $csa['code'];
			}
			$query_params['csa'] = array_unique($query_params['csa']);
		}
		elseif ($question_code == 228) { // County
			if (!isset($query_params['county_fips'])) {
				$query_params['county_fips'] = array();
			}
			foreach ($question['conditions'] as $county) {
				$query_params['county_fips'][] = $county['code'];
			}
			$query_params['county_fips'] = array_unique($query_params['county_fips']);
		}
		elseif ($question_code == 229) { //Zipcodes
			if (!isset($query_params['postal_code'])) {
				$query_params['postal_code'] = array();
			}
			foreach ($question['conditions'] as $postal_codes) {
				if (!empty($postal_codes['values'])) {
					foreach ($postal_codes['values'] as $postal_code) {
						$query_params['postal_code'][] = str_pad($postal_code, 5, '0', STR_PAD_LEFT);
					}
				}
			}
			if (!empty($query_params['postal_code'])) {
				$query_params['postal_code'] = array_unique($query_params['postal_code']);
			}
			else {
				unset($query_params['postal_code']);
			}
		}
		elseif ($question_code == 231) { //DMA
			if (!isset($query_params['dma_code'])) {
				$query_params['dma_code'] = array();
			}
			
			foreach ($question['conditions'] as $dma) {
				$query_params['dma_code'][] = $dma['code'];
			}
			$query_params['dma_code'] = array_unique($query_params['dma_code']);
		}
	}
}