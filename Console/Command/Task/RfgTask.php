<?php
App::import('Lib', 'RfgMappings');
class RfgTask extends Shell {
	
	const RFG_PROJECT_IN_FIELD = 2;
	const RFG_PROJECT_PAUSED = 3;
	const RFG_PROJECT_CLOSED = 4;

	public $uses = array('RfgQueue', 'Project', 'ProjectLog', 'GeoZip', 'GeoState', 'RfgQuestion', 'RfgAnswer');
	
	public function handle_queue(&$sqs, $sqs_queue, &$sqs_batch) {
		$response = $sqs->sendMessageBatch($sqs_queue, $sqs_batch);
		$this->RfgQueue->getDataSource()->reconnect();
		if (!empty($response)) {
			foreach ($response as $rfg_queue_id => $message_id) {
				$this->RfgQueue->create();
				$this->RfgQueue->save(array('RfgQueue' => array(
					'id' => $rfg_queue_id,
					'amazon_queue_id' => $message_id
				)), true, array('amazon_queue_id'));
			}
			
			$sqs_batch = array(); // reset teh batch
		}
	}
	
	public function ir($rfg_project, $statistics = false) {
		$ir = null;
		if ($statistics !== false) {
			$ir = $statistics['projectCR']; // Overall project IR
		}
		
		if (empty($ir)) {
			$ir = $rfg_project['estimatedIR'];
		}
		
		return $ir;
	}

	public function loi($est_loi) {
		if (empty($est_loi)) {
			return $loi = 15;
		}
		
		return $est_loi;
	}
	
	public function payout($cpi) {
		$cpi = substr($cpi, 1); // convert $x.xx to x.xx
		$return = array(
			'client_rate' => 0, // dollars
			'partner_rate' => 0, // dollars
			'award' => 0 // points
		);
		if (!empty($cpi)) {
			$return['client_rate'] = $cpi;
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
	public function quota($rfg_project) {
		$quota = $rfg_project['desiredCompletes'] - $rfg_project['currentCompletes'];
		if ($quota > 0) {
			return $quota;
		}
		else {
			return 0;
		}
	}
	
	public function is_closed($rfg_project) {
		$close_project = false;
		if ($rfg_project['state'] != self::RFG_PROJECT_IN_FIELD) {
			$close_project = true;
		}
		
		if (!$close_project && ($rfg_project['desiredCompletes'] - $rfg_project['currentCompletes'] <= 0) ) {
			$close_project = true;
		}
		
		return $close_project;
	}
	
	public function close_project($project, $project_log_type, $project_log_desc, $force_ended_date = false) {
		$this->Project->create();
		$this->Project->save(array('Project' => array(
			'id' => $project['Project']['id'],
			'status' => PROJECT_STATUS_CLOSED,
			'active' => false,
			// update ended if it's blank - otherwise leave the old value
			'ended' => empty($project['Project']['ended']) || $force_ended_date ? date(DB_DATETIME) : $project['Project']['ended']
		)), true, array('status', 'active', 'ended'));

		$this->ProjectLog->create();
		$this->ProjectLog->save(array('ProjectLog' => array(
			'project_id' => $project['Project']['id'],
			'type' => $project_log_type,
			'description' => $project_log_desc
		)));
	}
	
	public function save_question($datapoint) {
		$desired_language = 'en-US';
		if (empty($datapoint['question']) || empty($datapoint['answers']) || empty($datapoint['question'][$desired_language])) {
			return false;
		}
		
		$rfgQuestionSource = $this->RfgQuestion->getDataSource();
		$rfgQuestionSource->begin();
		$this->RfgQuestion->create();
		$save = $this->RfgQuestion->save(array('RfgQuestion' => array(
			'rfg_name' => $datapoint['name'],
			'rfg_property' => $datapoint['property'],
			'question' => $datapoint['question']['en-US'],
			'type' => $datapoint['type'],
		)));

		// create its answers
		if ($save) {
			$rfg_question_id = $this->RfgQuestion->getInsertId();
			$rfgQuestionSource->commit();
			foreach ($datapoint['answers'] as $key => $answer) {
				if ($key == 0 || !isset($answer[$desired_language])) {
					continue; // skip first typical null answer
				}
				
				// rfg sometimes returns empty answers
				if (empty($answer[$desired_language])) {
					continue;
				}
				$this->RfgAnswer->create();
				$this->RfgAnswer->save(array('RfgAnswer' => array(
					'rfg_question_id' => $rfg_question_id,
					'answer' => $answer[$desired_language],
					'key' => $key,
				)));
			}

			return $rfg_question_id;
		}
		else {
			$rfgQuestionSource->commit();
		}
		return false;
	}
	
	public function mapping(&$query_params, $target) {
		if ($target['name'] == 'Age') {
			$min = min(Set::extract('/min', $target['values']));
			$max = max(Set::extract('/max', $target['values']));
			$query_params['age_from'] = $min;
			$query_params['age_to'] = $max;
		}
		elseif ($target['name'] == 'DMA (US)') {
			$choices = Set::extract('/choice', $target['values']);
			$rfg_question = $this->RfgQuestion->find('first', array(
				'contain' => array(
					'RfgAnswer' => array(
						'conditions' => array(
							'RfgAnswer.key' => $choices
						)
					)
				),
				'conditions' => array(
					'rfg_name' => $target['name']
				)
			));
			if (!$rfg_question) {
				return false;
			}
			
			$dmas = $this->GeoZip->find('list', array(
				'fields' => array('dma', 'dma_code'),
				'conditions' => array(
					'GeoZip.dma_code !=' => '',
					'GeoZip.country_code' => 'US'
				),
				'order' => 'dma asc',
				'group' => 'dma_code'
			));
			foreach ($rfg_question['RfgAnswer'] as $answer) {
				$ans = str_replace('-', ' - ', $answer['answer']);
				$ans = ucwords(strtolower($ans));
				if (strpos($ans, '(')) {
					$arr_ans = explode('(', $ans);
					$arr_ans[1] = ucfirst($arr_ans[1]);
					$ans = implode('(', $arr_ans);
				}
				
				if (isset($dmas[$ans])) {
					$query_params['dma_code'][] = $dmas[$ans];
				}
				elseif ($dma = RfgMappings::dma($answer['answer'], $dmas)) {
					$query_params['dma_code'][] = $dma;
				}
			}
			
			if (empty($query_params['dma_code'])) {
				return false;
			}
		}
		elseif ($target['name'] == 'State (US)') {
			$choices = Set::extract('/choice', $target['values']);
			$rfg_question = $this->RfgQuestion->find('first', array(
				'contain' => array(
					'RfgAnswer' => array(
						'conditions' => array(
							'RfgAnswer.key' => $choices
						)
					)
				),
				'conditions' => array(
					'rfg_name' => $target['name']
				)
			));
			if (!$rfg_question) {
				return false;
			}
			
			$selected_states = array();
			foreach ($rfg_question['RfgAnswer'] as $answer) {
				$selected_states[] = substr($answer['answer'], 0, 2);
			}
			
			$states = $this->GeoState->find('list', array(
				'conditions' => array(
					'GeoState.state_abbr' => $selected_states
				),
				'fields' => array('state_abbr'),
			));
			if (!empty($states)) {
				$query_params['state'] = array_values($states);
			}
		}
		elseif ($target['name'] == 'List of Zips') {
			$zip_list = $zip_full = $zip_selected = array();
			$zip_conditions = array(
				'GeoZip.country_code' => 'US'
			);
			foreach ($target['values'] as $value) {
				$zip_list = array_merge($zip_list, explode(',', $value['freelist']));
			}
			
			if (empty($zip_list)) {
				return;
			}
			
			foreach ($zip_list as $zip) {
				if (empty($zip)) {
					continue;
				}
				
				if (strpos($zip, '*')) {
					$zip_conditions['OR'][] = array('GeoZip.zipcode LIKE' => '%' . rtrim($zip, '*'));
					continue;
				}
				
				if (strlen($zip) == 4) { // sometimes zeros are missing from the front of US postal codes
					$zip = '0' . $zip;
				}
				
				$zip_full[] = $zip;
			}
			
			if (isset($target['usesWildcards']) && !empty($zip_conditions['OR'])) {
				$geo_zips = $this->GeoZip->find('list', array(
					'conditions' => $zip_conditions,
					'fields' => array('zipcode')
				));
				
				if ($geo_zips) {
					$zip_selected = $geo_zips;
				}
			}
			
			if (!empty($zip_full)) {
				$geo_zips = $this->GeoZip->find('list', array(
					'conditions' => array(
						'GeoZip.zipcode' => $zip_full,
						'GeoZip.country_code' => 'US'
					),
					'fields' => array('zipcode')
				));
				if ($geo_zips) {
					$zip_selected = array_merge($zip_selected, $geo_zips);
				}
			}
			
			if (!empty($zip_selected)) {
				$query_params['postal_code'] = array_values(array_unique($zip_selected));
			}
		}
		else {
			$params = RfgMappings::to_mv_query($target);
			if ($target['name'] != 'Gender' && empty($params)) {
				return false;
			}
			
			if (is_array($params)) {
				$query_params = array_merge($query_params, $params);
			}
		}
		
		return true;
	}

}