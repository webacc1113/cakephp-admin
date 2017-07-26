<?php

class LucidTask extends Shell {
	public $uses = array('GeoZip', 'Client');
	
	public function mappings(&$query_params, $db_question, $question) {
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
		elseif (in_array($mapping_function, array('hhi_v2'))) {
			foreach ($question['PreCodes'] as $precode) {
				$result = FedMappings::$mapping_function($precode);
				if ($result !== false) {
					$query_params['hhi'][] = $result;
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
		elseif ($mapping_function == 'education_v2') {
			foreach ($question['PreCodes'] as $precode) {
				$result = FedMappings::$mapping_function($precode);
				if ($result !== false) {
					$query_params['education'][] = $result;
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
		elseif ($mapping_function == 'region') {
			if (!isset($query_params['state'])) {
				$query_params['state'] = array();
			}
			
			App::import('Model', 'GeoState');
			$State = new GeoState();
			foreach ($question['PreCodes'] as $precode) {
				foreach ($db_question['FedAnswer'] as $answer) {
					if ($precode == $answer['precode']) {
						$geo_states = $State->find('list', array(
							'fields' => array('GeoState.id', 'state_abbr'),
							'conditions' => array(
								'GeoState.region' => $answer['answer']
							)
						));
						if ($geo_states) {
							$query_params['state'] = array_merge($query_params['state'], $geo_states);
						}
						
						break;
					}
				}
			}
		}
	}
	
	public function is_closed($lucid_project) {
		$close_project = false;
		if ($lucid_project['SurveyStillLive'] != '1') {
			$close_project = true;
		}
		if (!$close_project) {
			foreach ($lucid_project['SurveyQuotas'] as $survey_quota) {
				if ($survey_quota['SurveyQuotaType'] == 'Total' && $survey_quota['NumberOfRespondents'] == 0) {
					$close_project = true;
				}
				break;
			}
		}
		return $close_project;
	}
	
	public function ir($lucid_project, $survey_statistics = false) {
		$ir = null;
		if ($survey_statistics !== false) {
			if ($survey_statistics['SupplierSystemConversion'] > 0 && !empty($survey_statistics['GlobalTrailingSystemConversion'])) {
				$ir = $survey_statistics['GlobalTrailingSystemConversion'] * 100; // in pct already 
			}
		}
		if (empty($ir)) {
			$ir = $lucid_project['Conversion'];
		}
		if (empty($ir)) {
			$ir = $lucid_project['BidIncidence'];
		}
		return $ir;
	}
	
	public function loi($lucid_project, $survey_statistics = false) {
		$loi = null;
		if ($survey_statistics !== false) {
			if ($survey_statistics['TrailingObservedLOI']) {
				$loi = $survey_statistics['TrailingObservedLOI'];
			}
		}
		if (empty($loi)) {
			$loi = $lucid_project['LengthOfInterview'];
		}
		if (empty($loi)) {
			$loi = 15; // fallback bc system can't handle 0 loi very well
		}
		return $loi;
	}
	
	// the full list of qualifications for a lucid project are spread out all over the place; compress and capture them all in one place for consistency	
	public function qualifications($lucid_project) {
		$total_qualifications = array(); // stores question_id + answers
		
		if (isset($lucid_project['SurveyQualification']['Questions']) && !empty($lucid_project['SurveyQualification']['Questions'])) {
			foreach ($lucid_project['SurveyQualification']['Questions'] as $question) {
				if (empty($question)) {
					continue;
				}
				if (!isset($total_qualifications[$question['QuestionID']])) {
					$total_qualifications[$question['QuestionID']] = $question['PreCodes']; 
				}
				else {
					$total_qualifications[$question['QuestionID']] = array_merge($total_qualifications[$question['QuestionID']], $question['PreCodes']); 
				}
			}
		}
		if (isset($lucid_project['SurveyQuotas']) && !empty($lucid_project['SurveyQuotas'])) {
			$survey_quotas = Set::extract($lucid_project['SurveyQuotas'], '{n}.Questions');
			if (!empty($survey_quotas)) {
				foreach ($survey_quotas as $survey_quota_questions) {
					if (empty($survey_quota_questions)) {
						continue;
					}
					foreach ($survey_quota_questions as $question) {
						if (empty($question)) {
							continue;
						}
						if (!isset($total_qualifications[$question['QuestionID']])) {
							$total_qualifications[$question['QuestionID']] = $question['PreCodes']; 
						}
						else {
							$total_qualifications[$question['QuestionID']] = array_merge($total_qualifications[$question['QuestionID']], $question['PreCodes']); 
						}
					}			
				}
			}
		}
		
		if (!empty($total_qualifications)) {
			foreach ($total_qualifications as $question_id => $precodes) {
				$precodes = array_unique($precodes);
				sort($precodes);
				$total_qualifications[$question_id] = $precodes; 
			}
		}
		return $total_qualifications; 
	}
		
	// accepts the SurveyQuotas from GetSurveyQuotasBySurveyNumberAndSupplierCode
	// returns an array of client_rate, partner_rate, and award
	/* 
		[SurveyQuotas] => Array
        (
            [0] => Array
                (
                    [SurveyQuotaID] => 548093
                    [SurveyQuotaType] => Total
                    [QuotaCPI] => 1.5
                    [Conversion] => 11
                    [NumberOfRespondents] => 747
                    [Questions] => 
                )

        )
	*/
	public function payout($survey_quotas) {
		$return = array(
			'client_rate' => 0, // dollars
			'partner_rate' => 0, // dollars
			'award' => 0 // points
		);
		if (!empty($survey_quotas)) {
			foreach ($survey_quotas as $survey_quota) {
				if ($survey_quota['SurveyQuotaType'] == 'Total') {
					$return['client_rate'] = $survey_quota['QuotaCPI'];
					break;
				}
			}
		}
		
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
	
	// accepts the SurveyQuotas from GetSurveyQuotasBySurveyNumberAndSupplierCode
	// returns the total quota
	public function quota($survey_quotas) {
		$quota = 0;
		if (!empty($survey_quotas)) {
			foreach ($survey_quotas as $survey_quota) {
				if ($survey_quota['SurveyQuotaType'] == 'Total') {
					$quota = $survey_quota['NumberOfRespondents'];
					break;
				}
			}
		}
		return $quota;
	}
	
	/* 
	 [OfferwallAllocations] => Array
        (
            [0] => Array
                (
                    [SupplierCode] => 5292
                    [SupplierName] => Branded Research
                    [OfferwallCompletes] => 5
                    [AllocationRemaining] => 0
                    [HedgeRemaining] => 747
                    [TargetModel] => Array
                        (
                            [SupplierLinkType] => 18
                            [LiveSupplierLink] => http://www.samplicio.us/router/default.aspx?SID=d6656cbc-cdd8-401e-980f-c0e4c5e139b5&PID=
                            [SupplierLinkSID] => d6656cbc-cdd8-401e-980f-c0e4c5e139b5
                            [TargetCCPI] => 1.5
                        )

                )

        )

	 */
	public function client_link($survey) {
		$client_link = null;
		if (!empty($survey['SupplierAllocations'])) {
			foreach ($survey['SupplierAllocations'] as $survey_allocation) {
				if (isset($survey_allocation['TargetModels'][0]['LiveSupplierLink'])) {
					$client_link = $survey_allocation['TargetModels'][0]['LiveSupplierLink'];
					break;
				}
			}
		}
		elseif (!empty($survey['OfferwallAllocations'])) {
			foreach ($survey['OfferwallAllocations'] as $offerwall_allocation) {
				if (isset($offerwall_allocation['TargetModel']['LiveSupplierLink'])) {
					$client_link = $offerwall_allocation['TargetModel']['LiveSupplierLink']; 
					break;
				}
			}
		}
		if (!empty($client_link)) {
			$client_link = $client_link.'{{ID}}';
		}
		return $client_link ; 
	}
	
	public function direct_allocation($survey_allocations) {
		$direct_allocation = false;
		if (!empty($survey_allocations)) {
			foreach ($survey_allocations as $survey_allocation) {
				if (isset($survey_allocation['TargetModels'][0]['LiveSupplierLink'])) {
					$direct_allocation = true;
					break;
				}
			}
		}
		return $direct_allocation; 
	}
	
	public function client($client_name, $group_id) {
		if (!$client_name) {
			return false;
		}
		
		$client_key = Inflector::slug(strtolower($client_name), '-');
		$client = $this->Client->find('first', array(
			'fields' => array('Client.id'),
			'conditions' => array(
				'Client.key' => $client_key,
				'Client.group_id' => $group_id,
				'Client.deleted' => false
			)
		));
		
		if ($client) {
			return $client['Client']['id'];
		}
		
		$clientSource = $this->Client->getDataSource();
		$clientSource->begin();
		$this->Client->create();
		$this->Client->save(array('Client' => array(
			'client_name' => $client_name,
			'group_id' => $group_id,
			'key' => $client_key
		)));
		$client_id = $this->Client->getLastInsertID();
		$clientSource->commit();
		return $client_id;
		
	}
}