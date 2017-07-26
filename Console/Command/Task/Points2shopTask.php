<?php

class Points2shopTask extends AppShell {
	public $uses = array('Client', 'Points2shopLog', 'Points2shopProject', 'Question');

	public function payout($survey) {
		$return = array(
			'client_rate' => 0, // dollars
			'partner_rate' => 0, // dollars
			'award' => 0 // points
		);
		if (!empty($survey['cpi'])) {
			$return['client_rate'] = $survey['cpi'];
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
	public function client_link($survey) {
		$client_link = null;
		if (!empty($survey['entry_link'])) {
			$survey['entry_link'] = str_replace('SUBID', '{{USER}}', $survey['entry_link']); // ssi is user_id
			$client_link = $survey['entry_link'].'&ssi2={{ID}}'; // ssi2 is where the uids are stored
		}
		return $client_link;
	}

	public function qualifications($survey) {
		$total_qualifications = array();
		if (!empty($survey['qualifications'])) {
			foreach ($survey['qualifications'] as $question => $answers) {
				if (empty($question) || empty($answers)) {
					continue;
				}
				if (!isset($total_qualifications[$question])) {
					$total_qualifications[$question] = $answers;
				}
				else {
					$total_qualifications[$question] = array_merge($total_qualifications[$question], $answers);
				}				
			}
		}
		if (!empty($survey['quotas'])) {
			foreach ($survey['quotas'] as $conditions) {
				if (is_array($conditions)) {
					foreach ($conditions['conditions'] as $question => $answers) {
						if (empty($question) || empty($answers)) {
							continue;
						}
						if (!isset($total_qualifications[$question])) {
							$total_qualifications[$question] = $answers;	
						}
						else {
							$total_qualifications[$question] = array_merge($total_qualifications[$question], $answers);
						}
					}
				}
			}
		}

		if (!empty($total_qualifications)) {
			foreach ($total_qualifications as $question => $answers) {
				$answers = array_unique($answers);
				sort($answers);
				$mv_question = $this->Question->find('first', array(
					'conditions' => array(
						'Question.question' => $question,
						'Question.partner' => 'points2shop',
					),
					'fields' => array('Question.partner_question_id'),
					'recursive' => -1,
				));
				unset($total_qualifications[$question]);
				if ($mv_question) {
					$total_qualifications[$mv_question['Question']['partner_question_id']] = $answers;
				}
			}
		}
		return $total_qualifications;
	}

	public function is_closed($survey) {
		$close_project = false;
		if ($survey['remaining_completes'] == 0) {
			$close_project = true;
		}
		return $close_project;
	}

	public function bid_ir($survey, $default_bid_ir = null) {
		$bid_ir = 0;
		if (!empty($survey['conversion_rate'])) {
			$bid_ir = $survey['conversion_rate'] * 100;
		}
		if ($bid_ir == 0) {
			$bid_ir = $default_bid_ir;
		}
		return $bid_ir;
	}

	public function loi($survey, $default_loi = null) {
		$loi = 0;
		if (!empty($survey['loi'])) {
			$loi = $survey['loi'];
		}
		if ($loi == 0) {
			$loi = $default_loi;
			//Log error in stackify
			$stackify_error = array(
				'ProjectID' => $survey['project_id'],
				'Timestamp' => date('M d, Y H:i:s'),
				'loi' => $survey['loi']
			);
			CakeLog::write('points2shop.loi', print_r($stackify_error, true));
		}
		return  $loi;
	}

	public function save_log($survey, $log_file, $log_key) {
		$log_data = array('Points2shopLog' => array(
			'p2s_project_id' => $survey['project_id'],
			'cpi' => $survey['cpi'],
			'name' => isset($survey['name']) ? $survey['name'] : null,
			'remaining_completes' => $survey['remaining_completes'],
			'survey_group_id' => $survey['survey_group_id'],
			'study_type' => isset($survey['study_type']) ? $survey['study_type'] : null,
			'country' => $survey['country'],
			'loi' => $survey['loi'],
			'qualifications' => isset($survey['qualifications']) ? json_encode($survey['qualifications']) : null,
			'entry_link' => $survey['entry_link'],
			'quotas' => isset($survey['quotas']) ? json_encode($survey['quotas']) : null,
			'platform_type' => isset($survey['platform_type']) ? $survey['platform_type'] : null,
			'conversion_rate' => $survey['conversion_rate'],
			'survey_group_ids' => isset($survey['survey_group_ids']) ? $survey['survey_group_ids'] : null,
		));

		$points2shop_log = $this->Points2shopLog->find('first', array(
			'conditions' => array(
				'Points2shopLog.p2s_project_id' => $survey['project_id']
			),
		));

		$save = true;
		// if data isn't changed don't save it
		if ($points2shop_log && !Utils::array_values_changed($points2shop_log['Points2shopLog'], $log_data['Points2shopLog'])) {
			$save = false;
		}

		if ($save) {
			$this->Points2shopLog->create();
			$this->Points2shopLog->save($log_data);
			$this->lecho('Log created for ' . $survey['project_id'], $log_file, $log_key);
		}
	}

	public function save_points2shop_project($project_id, $survey, $log_file, $log_key) {
		$points2shop_project = $this->Points2shopProject->find('first', array(
			'conditions' => array(
				'Points2shopProject.project_id' => $project_id,
			),
			'recursive' => -1,
			'fields' => array(
				'Points2shopProject.id',
				'Points2shopProject.project_id',
			),
		));
		if ($points2shop_project) {
			$this->Points2shopProject->create();
			$this->Points2shopProject->save(array('Points2shopProject' => array(
				'id' => $points2shop_project['Points2shopProject']['id'],
				'points2shop_json' => json_encode($survey)
			)), true, array('points2shop_json'));
			$this->lecho('MV Points2shopProject updated for project_id: ' . $project_id, $log_file, $log_key);
		}
		else {
			$this->Points2shopProject->create();
			$this->Points2shopProject->save(array('Points2shopProject' => array(
				'project_id' => $project_id,
				'points2shop_project_id' => $survey['project_id'],
				'points2shop_json' => json_encode($survey)
			)));
			$this->lecho('MV Points2shopProject created for project_id: ' . $project_id, $log_file, $log_key);
		}
	}
}