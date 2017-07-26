<?php
App::uses('AppController', 'Controller');
App::import('Lib', 'QueryEngine');

class QualificationsController extends AppController
{
	public $uses = array('Qualification', 'Question', 'Answer', 'QuestionText', 'GeoState', 'LucidZip', 'Partner', 'SurveyVisit', 'ProjectOption', 'Project', 'RegionMapping');
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array('RequestHandler');

	public function beforeFilter() {
		parent::beforeFilter();
	}

	public function index() {
		
	}

	public function ajax_qualification_status($qualification_id) {
		$qualification = $this->Qualification->find('first', array(
			'fields' => array('Qualification.id', 'Qualification.active', 'Qualification.project_id'),
			'conditions' => array(
				'Qualification.id' => $qualification_id,
				'Qualification.deleted is null'
			),
			'recursive' => -1
		)); 
		$active = false;
		if (!$qualification) {
			return new CakeResponse(array(
				'body' => json_encode(array()),
				'type' => 'json',
				'status' => '404'
			));
		}
		$active_flag_to_save = !$qualification['Qualification']['active']; 
		$this->Qualification->create();
		$this->Qualification->save(array('Qualification' => array(
			'id' => $qualification['Qualification']['id'],
			'active' => $active_flag_to_save
		)), true, array('active'));

		$this->ProjectLog->create();
		$this->ProjectLog->save(array('ProjectLog' => array(
			'project_id' => $qualification['Qualification']['project_id'],
			'user_id' => $this->current_user['Admin']['id'],
			'type' => $active_flag_to_save ? 'qualification.open': 'qualification.closed',
			'description' => 'Qualification #' . $qualification['Qualification']['id'] . ($active_flag_to_save ? ' opened.' : ' closed.'),
		)));

		return new CakeResponse(array(
			'body' => json_encode(array(
				'status' => $active_flag_to_save
			)),
			'type' => 'json',
			'status' => '201'
		));
	}
	
	public function us() {
		$questions = array();
		$questions['gender'] = $this->getQuestion('GENDER');
		$questions['hhi'] = $this->getQuestion('STANDARD_HHI_US_v2');
		$questions['education'] = $this->getQuestion('STANDARD_EDUCATION_v2');
		$questions['relationship'] = $this->getQuestion('STANDARD_RELATIONSHIP');
		$questions['ethnicity'] = $this->getQuestion('ETHNICITY');
		$questions['employment'] = $this->getQuestion('STANDARD_EMPLOYMENT');
		$questions['industry_personal'] = $this->getQuestion('STANDARD_INDUSTRY_PERSONAL');
		$questions['job'] = $this->getQuestion('STANDARD_JOB_TITLE');
		$questions['department'] = $this->getQuestion('STANDARD_COMPANY_DEPARTMENT');
		$questions['hispanic'] = $this->getQuestion('HISPANIC');
		$questions['homeowner'] = $this->getQuestion('STANDARD HOME OWNER');
		$questions['big_ticket'] = $this->getQuestion('Big Ticket Purchases');
		$questions['children'] = $this->getQuestion('Age_and_Gender_of_Child');
		$questions['children_under_18'] = $this->getQuestion('KIDS_STANDARD');
		$questions['parental_status'] = $this->getQuestion('Parental_Status_Standard');
		$questions['org_size'] = $this->getQuestion('STANDARD_NO_OF_EMPLOYEES');
		$questions['org_decisions'] = $this->getQuestion('STANDARD_B2B_DECISION_MAKER');
		$questions['org_rev'] = $this->getQuestion('STANDARD_COMPANY_REVENUE');
		$questions['smartphone'] = $this->getQuestion('STANDARD_SMART_PHONE');
		$questions['ailments'] = $this->getQuestion('STANDARD_SUFFERER_AILMENTS_I');
		$questions['ailments_2'] = $this->getQuestion('STANDARD_SUFFERER_AILMENTS_II');
		$questions['diabetes'] = $this->getQuestion('STANDARD_DIABETES_TYPE');
		$questions['pets'] = $this->getQuestion('STANDARD_PETS');
		$questions['beverage'] = $this->getQuestion('STANDARD_BEVERAGE_P4W');
		$questions['household'] = $this->getQuestion('STANDARD_HOUSEHOLD_TYPE');
		$questions['electronics'] = $this->getQuestion('STANDARD_ELECTRONICS');
		$questions['flights'] = $this->getQuestion('STANDARD_FLIGHT_DESTINATION');


		// geo data
		$states = $this->GeoState->find('all', array(
			'fields' => array('state_abbr', 'state', 'region', 'sub_region'),
			'conditions' => array(
				'id >' => '0'
			),
			'order' => 'GeoState.state ASC'
		));
		$state_regions = $states_list = array();
		foreach ($states as $state) {
			$states_list[$state['GeoState']['state_abbr']] = $state['GeoState']['state_abbr'] . ' - ' . $state['GeoState']['state'];
			$state_regions[$state['GeoState']['state_abbr']] = $state['GeoState']['region'];

			// used for css classes
			$sub_region_list[$state['GeoState']['state_abbr']] = str_replace(' ', '_', $state['GeoState']['sub_region']);

			// get the sub regions for each region
			if (!empty($state['GeoState']['sub_region'])) {
				$sub_regions[$state['GeoState']['region']][] = $state['GeoState']['sub_region'];
			}
		}

		foreach ($sub_regions as $key => $sub_region) {
			$sub_regions[$key] = array_unique($sub_region);
		}

		$dmas = $this->LucidZip->find('list', array(
			'fields' => array('LucidZip.dma', 'LucidZip.dma_name'),
			'conditions' => array(
				'LucidZip.dma !=' => '',
			),
			'order' => 'LucidZip.dma_name ASC',
			'group' => 'LucidZip.dma'
		));

		$this->set(compact('questions', 'states', 'state_regions', 'states_list', 'sub_regions', 'dmas', 'sub_region_list'));
	}

	public function gb() {
		$questions = array();
		$questions['gender'] = $this->getQuestion('GENDER');
		$questions['hhi'] = $this->getQuestion('STANDARD_HHI_INT', 'GB');
		$questions['education'] = $this->getQuestion('STANDARD_EDUCATION');
		$questions['relationship'] = $this->getQuestion('STANDARD_RELATIONSHIP');
		$questions['ethnicity'] = $this->getQuestion('STANDARD_UK_ETHNICITY', 'GB');
		$questions['employment'] = $this->getQuestion('STANDARD_EMPLOYMENT');
		$questions['industry'] = $this->getQuestion('STANDARD_INDUSTRY_PERSONAL');
		$questions['job'] = $this->getQuestion('STANDARD_JOB_TITLE');
		$questions['department'] = $this->getQuestion('STANDARD_COMPANY_DEPARTMENT');
		$questions['homeowner'] = $this->getQuestion('STANDARD HOME OWNER');
		$questions['children'] = $this->getQuestion('Age_and_Gender_of_Child');
		$questions['children_under_18'] = $this->getQuestion('KIDS_STANDARD');
		$questions['parental_status'] = $this->getQuestion('Parental_Status_Standard');
		$questions['org_size'] = $this->getQuestion('STANDARD_NO_OF_EMPLOYEES');
		$questions['org_decisions'] = $this->getQuestion('STANDARD_B2B_DECISION_MAKER');
		$questions['org_rev'] = $this->getQuestion('STANDARD_COMPANY_REVENUE');
		$questions['smartphone'] = $this->getQuestion('STANDARD_SMART_PHONE');
		$questions['ailments'] = $this->getQuestion('STANDARD_SUFFERER_AILMENTS_I');
		$questions['ailments_2'] = $this->getQuestion('STANDARD_SUFFERER_AILMENTS_II');

		$this->set(compact('questions'));
	}

	public function ca() {
		$questions = array();
		$questions['gender'] = $this->getQuestion('GENDER');
		$questions['hhi'] = $this->getQuestion('STANDARD_HHI_INT', 'CA');
		$questions['education'] = $this->getQuestion('STANDARD_EDUCATION');
		$questions['relationship'] = $this->getQuestion('STANDARD_RELATIONSHIP');
		$questions['ethnicity'] = $this->getQuestion('STANDARD_CANADA_ETHNICITY', 'CA');
		$questions['employment'] = $this->getQuestion('STANDARD_EMPLOYMENT');
		$questions['industry'] = $this->getQuestion('STANDARD_INDUSTRY_PERSONAL');
		$questions['job'] = $this->getQuestion('STANDARD_JOB_TITLE');
		$questions['department'] = $this->getQuestion('STANDARD_COMPANY_DEPARTMENT');
		$questions['homeowner'] = $this->getQuestion('STANDARD HOME OWNER');
		$questions['children'] = $this->getQuestion('Age_and_Gender_of_Child');
		$questions['children_under_18'] = $this->getQuestion('KIDS_STANDARD');
		$questions['parental_status'] = $this->getQuestion('Parental_Status_Standard');
		$questions['org_size'] = $this->getQuestion('STANDARD_NO_OF_EMPLOYEES');
		$questions['org_decisions'] = $this->getQuestion('STANDARD_B2B_DECISION_MAKER');
		$questions['org_rev'] = $this->getQuestion('STANDARD_COMPANY_REVENUE');
		$questions['smartphone'] = $this->getQuestion('STANDARD_SMART_PHONE');
		$questions['ailments'] = $this->getQuestion('STANDARD_SUFFERER_AILMENTS_I');
		$questions['ailments_2'] = $this->getQuestion('STANDARD_SUFFERER_AILMENTS_II');

		$this->set(compact('questions'));
	}

	private function getQuestion($key, $country = 'US')
	{
		$this->Question->bindModel(array(
			'hasOne' => array(
				'QuestionText' => array(
					'conditions' => array(
						'QuestionText.country' => $country
					)
				)
			)
		));
		$question = $this->Question->find('first', array(
			'fields' => array(
				'Question.id', 'Question.question', 'QuestionText.text', 'QuestionText.cp_text', 'Question.partner_question_id'
			),
			'conditions' => array(
				'Question.question' => $key,
				'Question.partner' => 'lucid'
			),
		));
		$this->Answer->bindModel(array(
			'hasOne' => array(
				'AnswerText' => array(
					'conditions' => array(
						'AnswerText.country' => $country
					)
				)
			)
		));
		$answers = $this->Answer->find('all', array(
			'fields' => array(
				'Answer.partner_answer_id', 'AnswerText.text'
			),
			'conditions' => array(
				'Answer.ignore' => false,
				'Answer.hide_from_pms' => false,
				'Answer.question_id' => $question['Question']['id']
			)
		));
		if ($answers) {
			$answer_return = array();
			foreach ($answers as $answer) {
				$answer_return[$answer['Answer']['partner_answer_id']] = $answer['AnswerText']['text'];
			}
		}
		return $question + array('Answers' => $answer_return);
	}

	/* -- New Query Engine view -- */
	public function query($country = null) {
		$questions = array();
		if (is_null($country)) {
			return $this->redirect(array('action' => 'query', 'us'));
		}
		if ($this->request->is('post') && isset($this->request->query['project_id']) && $this->request->query['project_id'] > 0) {
			// todo: verify that we are posting on a valid project
			$project = $this->Project->find('first', array(
				'conditions' => array(
					'Project.id' => $this->request->query['project_id']
				)
			));
			if (!$project || !in_array($project['Group']['key'], array('mintvine', 'socialglimpz'))) {
				$this->Session->setFlash('This is an invalid project', 'flash_error');
				return $this->redirect(array('controller' => 'qualifications', 'action' => 'query', $country));
			}

			$query_body = array(
				'partner' => 'lucid',
				'qualifications' => array(
					'country' => array(strtoupper($country))
				)
			);
			// hardcoding the gender
			if (isset($this->request->data['Query'][43])) {
				if (!empty($this->request->data['Query'][43])) {
					$query_body['qualifications'][43] = array($this->request->data['Query'][43]);
				}
				else {
					$query_body['qualifications'][43] = array(1, 2);
				}
				unset($this->request->data['Query'][43]);
			}
			// hardcoding the age
			$age_values = array(
				!isset($this->request->data['Query']['age_from']) || empty($this->request->data['Query']['age_from']) ? 14 : $this->request->data['Query']['age_from'],
				!isset($this->request->data['Query']['age_to']) || empty($this->request->data['Query']['age_to']) || $this->request->data['Query']['age_to'] > 100 ? 100 : $this->request->data['Query']['age_to']
			);
			// hardcoding the zip_code - US
			if (isset($this->request->data['Query'][45])) {
				$zipcodes = str_replace("\r", '', $this->request->data['Query'][45]);
				$zip_codes = explode("\n", $zipcodes);
				$zip_codes = array_filter($zip_codes, function($value) {
					return $value !== '';
				});
				$query_body['qualifications'][45] = $zip_codes;
				unset($this->request->data['Query'][45]);
			}
			// hardcoding the postal_prefix - CA
			if (isset($this->request->data['Query'][1008])) {
				$postalcodes = str_replace("\r", '', $this->request->data['Query'][1008]);
				$postalcodes = explode("\n", $postalcodes);
				$postal_prefixes = array();
				foreach ($postalcodes as $postalcode) {
					$postal_prefixes[] = strtoupper(substr($postalcode, 0, 3));
				}
				$query_body['qualifications'][1008] = $postal_prefixes;
				unset($this->request->data['Query'][1008]);
			}
			// hardcoding the postal_prefix - GB
			if (isset($this->request->data['Query'][12370])) {
				$postalcodes = str_replace("\r", '', $this->request->data['Query'][12370]);
				$postalcodes = explode("\n", $postalcodes);
				$postal_prefixes = array();
				foreach ($postalcodes as $postalcode) {
					if (strpos($postalcode, ' ')) {
						$postal_prefix = explode(' ', $postalcode);
						$postal_prefixes[] = $postal_prefix[0];
					}
					else {
						$count = strlen($postalcode);
						if ($count == 7) {
							$length = 4;
						}
						elseif ($count == 5) {
							$length = 2;
						}
						else {
							$length = 3;
						}

						$postal_prefixes[] = substr($postalcode, 0, $length);
					}
				}
				$query_body['qualifications'][12370] = $postal_prefixes;
				unset($this->request->data['Query'][12370]);
			}
			$query_body['qualifications'][42] = range(min($age_values), max($age_values));
			
			unset($this->request->data['Query']['age_from']);
			unset($this->request->data['Query']['age_to']);
			// other location filters; move to main array
			if (!empty($this->request->data['Query'])) {
				foreach ($this->request->data['Query'] as $question_id => $answer_ids) {
					if (!is_numeric($question_id)) {
						continue;
					}
					$this->request->data[$question_id] = $answer_ids;
				}
				unset($this->request->data['Query']);
			}
			$additional_json = null;
			// iterate through all qualification matches
			if (!empty($this->request->data)) {
				// first grab the custom user qualifications
				if (isset($this->request->data['user_id'])) {
					$user_ids = explode("\n", trim($this->request->data['user_id'])); 
					array_walk($user_ids, create_function('&$val', '$val = trim($val);')); 
					if (!empty($user_ids)) {
						foreach ($user_ids as $key => $user_id) {
							if (strpos($user_id, 'm') === false) {
								continue;
							}
							list($project_id, $junk) = explode('m', $user_id);
							$survey_visit = $this->SurveyVisit->find('first', array(
								'fields' => array('SurveyVisit.partner_user_id'),
								'conditions' => array(
									'SurveyVisit.hash' => $user_id,
									'SurveyVisit.survey_id' => $project_id
								),
								'recursive' => -1
							));
							if ($survey_visit) {
								list($project_id, $user_id, $trash) = explode('-', $survey_visit['SurveyVisit']['partner_user_id']);
								$user_ids[$key] = $user_id; 
							}
						}
						$additional_json['append']['user_ids'] = $user_ids; 
					}
					unset($this->request->data['user_id']); 
				}
				if (isset($this->request->data['exclude_user_id'])) {
					$exclude_user_ids = explode("\n", trim($this->request->data['exclude_user_id'])); 
					array_walk($exclude_user_ids, create_function('&$val', '$val = trim($val);')); 
					if (!empty($exclude_user_ids)) {
						foreach ($exclude_user_ids as $user_id) {
							if (strpos($user_id, 'm') === false) {
								continue;
							}
							list($project_id, $junk) = explode('m', $user_id);
							$survey_visit = $this->SurveyVisit->find('first', array(
								'fields' => array('SurveyVisit.partner_user_id'),
								'conditions' => array(
									'SurveyVisit.hash' => $user_id,
									'SurveyVisit.survey_id' => $project_id
								),
								'recursive' => -1
							));
							if ($survey_visit) {
								list($project_id, $user_id, $trash) = explode('-', $survey_visit['SurveyVisit']['partner_user_id']);
								$exclude_user_ids[$key] = $user_id; 
							}
						}
						$additional_json['exclude']['user_ids'] = $exclude_user_ids; 
					}
					unset($this->request->data['exclude_user_id']); 
				}
				if (isset($this->request->data['existing_complete_project_id']) && trim($this->request->data['existing_complete_project_id']) != '') {
					$existing_complete_project_ids = explode("\n", trim($this->request->data['existing_complete_project_id'])); 
					if (!empty($existing_complete_project_ids)) {
						array_walk($existing_complete_project_ids, create_function('&$val', '$val = trim($val);')); 
					
						$exclude_complete_project_ids = array();
						foreach ($existing_complete_project_ids as $existing_complete_project_id) {
							$exclude_complete_project_ids[] = MintVine::parse_project_id($existing_complete_project_id); 
						}
					
						$additional_json['exclude']['completes_from_project'] = $exclude_complete_project_ids; 
					}
					unset($this->request->data['existing_complete_project_id']); 
				}
				if (isset($this->request->data['existing_click_project_id']) && trim($this->request->data['existing_click_project_id']) != '') {
					$existing_click_project_ids = explode("\n", trim($this->request->data['existing_click_project_id'])); 
					array_walk($existing_click_project_ids, create_function('&$val', '$val = trim($val);')); 
					if (!empty($existing_click_project_ids)) {
						$exclude_click_project_ids = array();
						foreach ($existing_click_project_ids as $existing_click_project_id) {
							$exclude_click_project_ids[] = MintVine::parse_project_id($existing_click_project_id); 
						}
						$additional_json['exclude']['clicks_from_project'] = $exclude_click_project_ids; 
					}
					unset($this->request->data['existing_click_project_id']); 
				}
				foreach ($this->request->data as $question_id => $answer_ids) {
					if (!is_numeric($question_id)) {
						continue;
					}
					$selected_answer_ids = array();
					foreach ($answer_ids as $key => $answer_id) {
						if (!empty($answer_id)) {
							if (!is_numeric($key)) {
								// states and regions
								$selected_answer_ids = array_merge($selected_answer_ids, array_map('intval', explode(',', $key)));
								$selected_answer_ids = array_unique($selected_answer_ids);
							}
							else {
								$selected_answer_ids[] = $key;
							}
						}
					}
					if (empty($selected_answer_ids)) {
						continue;
					}
					$query_body['qualifications'][$question_id] = $selected_answer_ids;
				}
			}
			
			$uuid = md5(json_encode($query_body));
			$qualification = $this->Qualification->find('first', array(
				'conditions' => array(
					'Qualification.project_id' => $project['Project']['id'],
					'Qualification.partner_qualification_id' => $uuid,
					'Qualification.deleted is null'
				)
			));
			if (!$qualification) {
				$json_query = json_encode($query_body);
				$qualificationSource = $this->Qualification->getDataSource();
				$qualificationSource->begin();

				// qualification name cannot be null
				if (empty($this->request->data['Qualification']['name'])) {
					$this->Session->setFlash('Qualification name cannot be null. Please enter name', 'flash_error');
					return $this->redirect(array('controller' => 'qualifications', 'action' => 'query', $country, '?' => array('project_id' => $project['Project']['id'])));
				}
				
				$this->Qualification->create();
				$saved = $this->Qualification->save(array('Qualification' => array(
					'project_id' => $project['Project']['id'],
					'partner_qualification_id' => $uuid,
					'name' => $this->request->data['Qualification']['name'],
					'query_hash' => md5($json_query),
					'query_json' => $json_query,
					'additional_json' => !is_null($additional_json) ? json_encode($additional_json): null,
					'quota' => $this->request->data['Qualification']['quota'],
					'cpi' => $this->request->data['Qualification']['cpi'],
					'award' => $this->request->data['Qualification']['award'],
					'active' => false,
					'processing' => date(DB_DATETIME)
				)));
				
				if ($saved) {
					$qualification_id = $this->Qualification->getInsertId();
					$qualificationSource->commit();
				}
				else {
					$this->Session->setFlash('There was a database error', 'flash_error'); 
					$qualificationSource->commit();
				}
				
				if ($saved) {
					// backend script to execute query so that it's not blocking
					$exec_query = ROOT . '/app/Console/cake qualification process ' . $qualification_id . ' force';
					$exec_query .= "  > /dev/null 2>&1 &";
					exec($exec_query);
					CakeLog::write('query_commands', $exec_query);
					$this->Project->create();
					$this->Project->save(array('Project' => array(
						'id' => $project['Project']['id'],
						'temp_qualifications' => true
					)), true, array('temp_qualifications'));
					
					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $project['Project']['id'],
						'user_id' => $this->current_user['Admin']['id'],
						'type' => 'qualification.created',
						'description' => 'Qualification #' . $qualification_id . ' created.',
					)));
					$this->Session->setFlash('Your qualification has been added; we are processing and will be inviting the panelists now.', 'flash_success');
				}
				return $this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $project['Project']['id']));
			} 
			else {
				$this->Session->setFlash('This qualification has already been created.', 'flash_error');
				return $this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $project['Project']['id']));
			}
		}

		$country = strtoupper($country);
		$this->Question->bindModel(array(
			'hasOne' => array(
				'QuestionText' => array(
					'conditions' => array(
						'QuestionText.country' => $country
					)
				)
			)
		));
		$high_usage_questions = $this->Question->find('all', array(
			'fields' => array(
				'Question.id', 'Question.question', 'QuestionText.text', 'Question.partner_question_id', 'QuestionText.cp_text'
			),
			'conditions' => array(
				'Question.ignore' => false,
				'Question.high_usage is not null',
				'Question.partner' => 'lucid'
			),
			'order' => 'Question.high_usage ASC'
		));

		$questions = $question_keys = array();
		foreach ($high_usage_questions as $high_usage_question) {
			$this->Answer->bindModel(array('hasOne' => array('AnswerText')));
			$answers = $this->Answer->find('all', array(
				'fields' => array(
					'Answer.partner_answer_id', 'AnswerText.text'
				),
				'conditions' => array(
					'Answer.ignore' => false,
					'Answer.hide_from_pms' => false,
					'Answer.question_id' => $high_usage_question['Question']['id'],
					'AnswerText.country' => $country
				)
			));
			if ($answers) {
				$answer_return = array();
				foreach ($answers as $answer) {
					$answer_return[$answer['Answer']['partner_answer_id']] = $answer['AnswerText']['text'];
				}
				$high_usage_question = $high_usage_question + array('Answers' => $answer_return);
				$questions[$high_usage_question['Question']['question']] = $high_usage_question;
				$question_keys[] = $high_usage_question['Question']['question'];
			}
		}
		$country = strtolower($country);
		$project_option_name = 'qev.us.count';
		if ($country == 'ca') {
			$project_option_name = 'qev.ca.count';
		}
		else if ($country == 'gb') {
			$project_option_name = 'qev.gb.count';
		}
		$project_option = $this->ProjectOption->find('first', array(
			'fields' => array(
				'ProjectOption.value'
			),
			'conditions' => array(
				'ProjectOption.project_id' => 0,
				'ProjectOption.name' => $project_option_name
			),
		));
		$count = $project_option ? $project_option['ProjectOption']['value'] : '0';
		$gender_question = $this->getQuestion('GENDER');
		$gender_question_id = $gender_question['Question']['id'];
		if (isset($this->request->query['project_id']) && $this->request->query['project_id'] > 0) {
			$project_id = $this->request->query['project_id'];
			$project = $this->Project->find('first', array(
				'conditions' => array(
					'Project.id' => $project_id
				)
			));
			$this->set(compact('project_id', 'project'));
		}
		$this->set(compact('questions', 'question_keys', 'country', 'count', 'gender_question'));
	}

	public function ajax_get_dmas() {
		$dmas = $this->LucidZip->find('all', array(
			'fields' => array('LucidZip.dma', 'LucidZip.dma_name'),
			'conditions' => array(
				'LucidZip.dma !=' => '',
			),
			'order' => 'LucidZip.dma_name ASC',
			'group' => 'LucidZip.dma'
		));
		return new CakeResponse(array(
			'body' => json_encode(array(
				'dmas' => $dmas
			)),
			'type' => 'json',
			'status' => '201'
		));
	}

	public function ajax_get_regions() {
		$country = $this->request->query['country'];
		$return_data = array();
		if ($country == 'us') {
			$states = $this->GeoState->find('all', array(
				'fields' => array('GeoState.state_abbr', 'GeoState.state', 'GeoState.region', 'GeoState.sub_region'),
				'conditions' => array(
						'GeoState.id >' => '0'
				),
				'order' => 'GeoState.state ASC'
			));
			foreach ($states as $state) {
				$lucid_zip = $this->LucidZip->find('first', array(
					'fields' => array('LucidZip.lucid_precode'),
					'conditions' => array(
							'LucidZip.state_abbr' => $state['GeoState']['state_abbr']
					)
				));
				$states_precodes[$state['GeoState']['sub_region']][] = $lucid_zip['LucidZip']['lucid_precode'];
				// get the sub regions for each region
				if (!empty($state['GeoState']['sub_region'])) {
					$sub_regions[$state['GeoState']['region']][] = $state['GeoState']['sub_region'];
				}
			}
			foreach ($sub_regions as $key => $sub_region) {
				$sub_regions[$key] = array_unique($sub_region);
			}
			$return_data['sub_regions'] = $sub_regions;
			$return_data['states_precodes'] = $states_precodes;
		}
		else {
			$partner_question_id = ($country == 'gb') ? 12452 : 29459;
			$region_data = $this->getAnswersFromPartnerQuestionId($partner_question_id, $country);
			$return_data['regions'] = $region_data;
		}
		$return_data['country'] = $country;
		return new CakeResponse(array(
			'body' => json_encode($return_data),
			'type' => 'json',
			'status' => '201'
		));
	}

	public function ajax_get_states() {
		$states = $this->GeoState->find('all', array(
			'fields' => array('GeoState.state_abbr', 'GeoState.state', 'GeoState.region', 'GeoState.sub_region'),
			'conditions' => array(
				'GeoState.id >' => '0'
			),
			'order' => 'GeoState.state ASC'
		));
		$state_regions = $states_list = array();
		foreach ($states as $state) {
			$lucid_zip = $this->LucidZip->find('first', array(
				'fields' => array('LucidZip.lucid_precode'),
				'conditions' => array(
					'LucidZip.state_abbr' => $state['GeoState']['state_abbr']
				)
			));
			$states_list[$lucid_zip['LucidZip']['lucid_precode']] = $state['GeoState']['state_abbr'] . ' - ' . $state['GeoState']['state'];
			$state_regions[$state['GeoState']['state_abbr']] = $state['GeoState']['region'];
			// used for css classes
			$sub_region_list[$state['GeoState']['state_abbr']] = str_replace(' ', '_', $state['GeoState']['sub_region']);
			// get the sub regions for each region
			if (!empty($state['GeoState']['sub_region'])) {
				$sub_regions[$state['GeoState']['region']][] = $state['GeoState']['sub_region'];
			}
		}
		foreach ($sub_regions as $key => $sub_region) {
			$sub_regions[$key] = array_unique($sub_region);
		}
		$return_data = compact('state_regions', 'states_list', 'sub_region_list');
		return new CakeResponse(array(
			'body' => json_encode($return_data),
			'type' => 'json',
			'status' => '201'
		));
	}

	public function ajax_get_counties() {
		$country = $this->request->query['country'];
		$return_data = array();
		if ($country == 'us') {
			$states = $this->GeoState->find('all', array(
				'fields' => array('GeoState.state_abbr', 'GeoState.state', 'GeoState.region', 'GeoState.sub_region'),
				'conditions' => array(
					'GeoState.id >' => '0'
				),
				'order' => 'GeoState.state ASC'
			));
			$states_list = array();
			foreach ($states as $state) {
				$states_list[$state['GeoState']['state_abbr']] = $state['GeoState']['state_abbr'] . ' - ' . $state['GeoState']['state'];
			}
			$return_data['states_list'] = $states_list;
		}
		else {
			$partner_question_id = ($country == 'gb') ? 12453 : 1015;
			$counties = $this->getAnswersFromPartnerQuestionId($partner_question_id, $country);
			$return_data['counties'] = $counties;
		}
		$return_data['country'] = $country;
		return new CakeResponse(array(
			'body' => json_encode($return_data),
			'type' => 'json',
			'status' => '201'
		));
	}

	public function ajax_parse_zipcodes() {
		if (isset($this->request->data['Query']['zip_file'])) {
			if (empty($this->request->data['Query']['zip_file']['error']) && (!empty($this->request->data['Query']['zip_file']['tmp_name']))) {
				$csvs = Utils::csv_to_array($this->request->data['Query']['zip_file']['tmp_name']);
				$zips = $postal_prefix = array();
				foreach ($csvs as $csv) {
					foreach ($csv as $key => $val) {
						if (in_array(strlen($val), array(2, 3))) {
							$postal_prefix[] = $val;
						}
						else {
							$zips[] = $val;
						}
					}
				}
				if (!empty($postal_prefix)) {
					$this->request->data['Query']['postal_prefix'] = $postal_prefix;
				}
				if (!empty($zips)) {
					$this->request->data['Query']['postal_code'] = array_values($zips);
				}
			}
		}
		return new CakeResponse(array(
			'body' => json_encode(array(
				'zipcodes' => $zips
			)),
			'type' => 'json',
			'status' => '201'
		));
	}

	public function ajax_search_question() {
		$search_keyword = $this->request->data['keyword'];
		$country = strtoupper($this->request->data['country']);
		$questions = $this->Question->find('all', array(
			'fields' => array(
				'Question.id', 'Question.question', 'QuestionText.text', 'Question.partner_question_id', 'QuestionText.cp_text'
			),
			'joins' => array(
				array('table' => 'question_texts',
					'alias' => 'QuestionText',
					'type' => 'INNER',
					'conditions' => array(
						'Question.id = QuestionText.question_id'
					)
				),
				array('table' => 'answers',
					'alias' => 'Answer',
					'type' => 'INNER',
					'conditions' => array(
						'Question.id = Answer.question_id'
					)
				),
				array('table' => 'answer_texts',
					'alias' => 'AnswerText',
					'type' => 'INNER',
					'conditions' => array(
						'Answer.id = AnswerText.answer_id'
					)
				)
			),
			'conditions' => array(
				'Question.ignore' => false,
				'QuestionText.country' => $country,
				'AnswerText.country' => $country,
				'Question.partner' => 'lucid',
				'Answer.ignore' => false,
				'Answer.hide_from_pms' => false,
				'OR' => array(
					'QuestionText.text LIKE ' => '%' . $search_keyword . '%',
					'QuestionText.cp_text LIKE ' => '%' . $search_keyword . '%',
					'AnswerText.text LIKE ' => '%' . $search_keyword . '%'
				)
			),
			'group' => array(
				'Question.id'
			)
		));
		$question_ids = $questions_return = array();
		foreach ($questions as $question) {
			$question_ids[] = $question['Question']['id'];
			$questions_return[$question['Question']['id']] = $question['Question'] + $question['QuestionText'];
		}
		$this->Answer->bindModel(array(
			'hasOne' => array(
				'AnswerText' => array(
					'conditions' => array(
						'AnswerText.country' => $country,
					)
				)
			)
		));
		$answers = $this->Answer->find('all', array(
			'fields' => array(
				'Answer.question_id', 'Answer.partner_answer_id', 'AnswerText.text'
			),
			'conditions' => array(
				'Answer.question_id' => $question_ids
			)
		));
		$answers_return = array();
		foreach ($answers as $answer) {
			foreach ($question_ids as $question_id) {
				if ($answer['Answer']['question_id'] == $question_id) {
					$answers_return[$question_id][$answer['Answer']['partner_answer_id']] = $answer['AnswerText']['text'];
				}
			}
		}
		return new CakeResponse(array(
			'body' => json_encode(array(
				'questions' => $questions_return,
				'answers' => $answers_return
			)),
			'type' => 'json',
			'status' => '201'
		));
	}

	public function getAnswersFromPartnerQuestionId($partner_question_id, $country) {
		$answers = $this->Question->find('all', array(
			'fields' => array(
				'Question.partner_question_id', 'Answer.partner_answer_id', 'AnswerText.text'
			),
			'joins' => array(
				array('table' => 'question_texts',
					'alias' => 'QuestionText',
					'type' => 'INNER',
					'conditions' => array(
						'Question.id = QuestionText.question_id'
					)
				),
				array('table' => 'answers',
					'alias' => 'Answer',
					'type' => 'INNER',
					'conditions' => array(
						'Question.id = Answer.question_id'
					)
				),
				array('table' => 'answer_texts',
					'alias' => 'AnswerText',
					'type' => 'INNER',
					'conditions' => array(
						'Answer.id = AnswerText.answer_id'
					)
				)
			),
			'conditions' => array(
				'Question.partner_question_id' => $partner_question_id,
				'Question.ignore' => false,
				'QuestionText.country' => strtoupper($country),
				'AnswerText.country' => strtoupper($country),
				'Question.partner' => 'lucid',
				'Answer.ignore' => false,
				'Answer.hide_from_pms' => false,
			)
		));
		$return_data = array();
		foreach ($answers as $answer) {
			$arr = array();
			$arr['partner_question_id'] = $answer['Question']['partner_question_id'];
			$arr['answer_id'] = $answer['Answer']['partner_answer_id'];
			$arr['label'] = $answer['AnswerText']['text'];
			$return_data[] = $arr;
		}
		return $return_data;
	}

	public function query_api_count($country) {
		/*
		here is some sample data to outline the structure
		$this->request->data = array('Query' => array(
			43 => 1, 
			633 => array(3, 4),
			96 => array(9, 42, 51)
		)); 
		*/
		if (!$this->request->is('post')) {
			return new CakeResponse(array(
				'body' => json_encode(array(
					'message' => 'Queries are required to be POSTed'
				)),
				'type' => 'json',
				'status' => '400'
			));
		}
		if (!isset($this->request->data['Query']) || empty($this->request->data['Query'])) {
			return new CakeResponse(array(
				'body' => json_encode(array(
					'message' => 'No qualifications posted'
				)),
				'type' => 'json',
				'status' => '400'
			));
		}
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array(
					'qe.mintvine.username',
					'qe.mintvine.password',
					'hostname.qe',
				),
				'Setting.deleted' => false
			)
		));
		$query_body = array(
			'partner' => 'lucid',
			'qualifications' => array(
				'country' => array(strtoupper($country)),
				'active_within_month' => array('true')
			)
		);
		$qualifications = $this->request->data['Query'];
		foreach ($qualifications as $qualification => $value) {
			if (!is_array($value)) {
				$query_body['qualifications'][$qualification] = array($value);
			}
			else {
				$query_body['qualifications'][$qualification] = $value;
			}
		}
		$active_time_ranges = array('active_within_week', 'active_within_60_days', 'active_within_90_days');
		foreach ($active_time_ranges as $active_time_range) {
			if (array_key_exists($active_time_range, $query_body['qualifications'])) {
				unset($query_body['qualifications']['active_within_month']);
				break;
			}
		}
		App::uses('HttpSocket', 'Network/Http');
		$http = new HttpSocket(array(
			'timeout' => 30,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$http->configAuth('Basic', $settings['qe.mintvine.username'], $settings['qe.mintvine.password']);
		$results = $http->post($settings['hostname.qe'].'/query?count_only=true', json_encode($query_body), array('header' => array(
			'Content-Type' => 'application/json'
		)));
		$body = json_decode($results['body'], true);
		return new CakeResponse(array(
			'body' => json_encode(array(
				'count' => $body['count'],
				'query' => $query_body
			)),
			'type' => 'json',
			'status' => '201'
		));
	}

	public function child_qualification($parent_qualification_id, $child_qualification_id = null) {
		if ($this->request->is('post') ) {
			if (isset($this->request->data['Query']['child_qualification_id'])) {
				$child_qualification_id = $this->request->data['Query']['child_qualification_id'];
			}
			$parent_qualification = $this->Qualification->find('first', array(
				'conditions' => array(
					'Qualification.id' => $parent_qualification_id
				)
			));
			$query_body = json_decode($parent_qualification['Qualification']['query_json']);
			$query_body = get_object_vars($query_body);
			$qualifications = get_object_vars($query_body['qualifications']);
			$query_body['qualifications'] = $qualifications;
			if (isset($this->request->data['Query'][43])) {
				if (!is_array($this->request->data['Query'][43])) {
					$query_body['qualifications'][43] = array($this->request->data['Query'][43]);
					unset($this->request->data['Query'][43]);
				}
				else if (count(array_diff($this->request->data['Query'][43], array(1, 2))) == 0) {
					unset($this->request->data['Query']['43']);
				}
			}
			$default_age_from = min($qualifications[42]);
			$default_age_to = max($qualifications[42]);
			$age_values = array(
				!isset($this->request->data['Query']['age_from']) || empty($this->request->data['Query']['age_from']) ? $default_age_from : $this->request->data['Query']['age_from'],
				!isset($this->request->data['Query']['age_to']) || empty($this->request->data['Query']['age_to']) || $this->request->data['Query']['age_to'] >= $default_age_to ? $default_age_to : $this->request->data['Query']['age_to']
			);
			$query_body['qualifications'][42] = range(min($age_values), max($age_values));
			unset($this->request->data['Query']['age_from']);
			unset($this->request->data['Query']['age_to']);
			// hardcoding the zip_code - US
			if (isset($this->request->data['Query'][45])) {
				if ($this->request->data['Query'][45] != '') {
					$zipcodes = str_replace("\r", '', $this->request->data['Query'][45]);
					$zip_codes = explode("\n", $zipcodes);
					$zip_codes = array_filter($zip_codes, function ($value) {
						return $value !== '';
					});
					$query_body['qualifications'][45] = $zip_codes;
				}
				unset($this->request->data['Query'][45]);
			}
			// hardcoding the zip_code - GB
			if (isset($this->request->data['Query'][12370])) {
				if ($this->request->data['Query'][12370] != '') {
					$postal_prefixes = str_replace("\r", '', $this->request->data['Query'][12370]);
					$postal_prefixes = explode("\n", $postal_prefixes);
					$postal_prefixes = array_filter($postal_prefixes, function ($value) {
						return $value !== '';
					});
					$query_body['qualifications'][12370] = $postal_prefixes;
				}
				unset($this->request->data['Query'][12370]);
			}
			// hardcoding the zip_code - CA
			if (isset($this->request->data['Query'][1008])) {
				if ($this->request->data['Query'][1008] != '') {
					$postal_prefixes = str_replace("\r", '', $this->request->data['Query'][1008]);
					$postal_prefixes = explode("\n", $postal_prefixes);
					$postal_prefixes = array_filter($postal_prefixes, function ($value) {
						return $value !== '';
					});
					$query_body['qualifications'][1008] = $postal_prefixes;
				}
				unset($this->request->data['Query'][1008]);
			}
			// other location filters; move to main array
			if (!empty($this->request->data['Query'])) {
				foreach ($this->request->data['Query'] as $question_id => $answer_ids) {
					if (!is_numeric($question_id)) {
						continue;
					}
					$this->request->data[$question_id] = $answer_ids;
				}
				unset($this->request->data['Query']);
			}
			// iterate through all qualification matches
			if (!empty($this->request->data)) {
				foreach ($this->request->data as $question_id => $answer_ids) {
					if (!is_numeric($question_id)) {
						continue;
					}
					$selected_answer_ids = array();
					foreach ($answer_ids as $key => $answer_id) {
						if (!empty($answer_id)) {
							if (!is_numeric($key)) {
								// states and regions
								$selected_answer_ids = array_merge($selected_answer_ids, array_map('intval', explode(',', $key)));
								$selected_answer_ids = array_unique($selected_answer_ids);
							}
							else {
								$selected_answer_ids[] = $key;
							}
						}
					}
					if (empty($selected_answer_ids)) {
						continue;
					}
					$query_body['qualifications'][$question_id] = array_unique($selected_answer_ids);
				}
			}
			
			// clean up the qualification of unnecessary selections
			$parent_query = json_decode($parent_qualification['Qualification']['query_json'], true);
			foreach ($query_body['qualifications'] as $key => $value) {
				if ($key == 'country') {
					continue;
				}
				// if answers exist, and they match against the parent; dont set them at all
				if (isset($parent_query['qualifications'][$key])) {
					$parent_comp = $parent_query['qualifications'][$key]; 
					$diff = array_diff($parent_comp, $value); 
					if (empty($diff)) {
						unset($query_body['qualifications'][$key]); 
					}
				}
				
				// if answers do not exist in parents; then check to make sure they aren't everything
				else {
					$question = $this->Question->find('first', array(
						'conditions' => array(
							'Question.partner' => $query_body['partner'],
							'Question.partner_question_id' => $key
						)
					)); 
					$answers = $this->Answer->find('list', array(
						'fields' => array('Answer.partner_answer_id'),
						'conditions' => array(
							'Answer.ignore' => false,
							'Answer.question_id' => $question['Question']['id']
						)
					)); 
					$answer_diff = array_diff($answers, $value); 
					if (empty($answer_diff)) {
						unset($query_body['qualifications'][$key]); 
					}
				}
			}
			// block creating an empty qualification.
			if (count($query_body['qualifications']) <= 1) {
				$this->Session->setFlash('Empty child qualification can not be created.', 'flash_error');
				return $this->redirect(array('controller' => 'qualifications', 'action' => 'child_qualification', $parent_qualification_id));
			}
			$uuid = md5(json_encode($query_body));
			$json_query = json_encode($query_body);
			if (is_null($child_qualification_id)) {
				$qualification = $this->Qualification->find('first', array(
					'conditions' => array(
						'Qualification.project_id' => $parent_qualification['Qualification']['project_id'],
						'Qualification.partner_qualification_id' => $uuid,
						'Qualification.parent_id' => $parent_qualification_id, 
						'Qualification.deleted is null'
					)
				));
				if (!$qualification) {
					$qualificationSource = $this->Qualification->getDataSource();
					$qualificationSource->begin();
					$this->Qualification->create();
					$this->Qualification->save(array('Qualification' => array(
						'project_id' => $parent_qualification['Qualification']['project_id'],
						'parent_id' => $parent_qualification_id,
						'partner_qualification_id' => $uuid,
						'name' => $this->request->data['Qualification']['name'],
						'query_hash' => md5($json_query),
						'query_json' => $json_query,
						'quota' => $parent_qualification['Qualification']['quota'],
						'cpi' => $parent_qualification['Qualification']['cpi'],
						'award' => $parent_qualification['Qualification']['award'],
						'active' => false,
						'processing' => date(DB_DATETIME)
					)));
					$qualification_id = $this->Qualification->getInsertId();
					$qualificationSource->commit();

					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $parent_qualification['Qualification']['project_id'],
						'user_id' => $this->current_user['Admin']['id'],
						'type' => 'qualification.created',
						'description' => 'Qualification #' . $qualification_id . ' created.'
					)));

					$this->Session->setFlash('Your qualification has been created.', 'flash_success');
				}
				else {
					$this->Session->setFlash('This qualification has already been created.', 'flash_error');
				}
			}
			else {
				$qualification = $this->Qualification->find('all', array(
					'conditions' => array(
						'Qualification.project_id' => $parent_qualification['Qualification']['project_id'],
						'Qualification.partner_qualification_id' => $uuid,
						'Qualification.deleted is null'
					)
				));
				if (count($qualification) > 1) {
					$this->Session->setFlash('This qualification has already been created.', 'flash_error');
				}
				else {
					$this->Qualification->save(array('Qualification' => array(
						'id' => $child_qualification_id,
						'project_id' => $parent_qualification['Qualification']['project_id'],
						'parent_id' => $parent_qualification_id,
						'partner_qualification_id' => $uuid,
						'name' => $this->request->data['Qualification']['name'],
						'query_hash' => md5($json_query),
						'query_json' => $json_query,
						'quota' => $this->request->data['Qualification']['quota'],
						'cpi' => $this->request->data['Qualification']['cpi'],
						'award' => $this->request->data['Qualification']['award'],
						'active' => false,
						'processing' => date(DB_DATETIME)
					)));

					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $parent_qualification['Qualification']['project_id'],
						'user_id' => $this->current_user['Admin']['id'],
						'type' => 'qualification.updated',
						'description' => 'Qualification #' . $child_qualification_id . ' updated with new data.'
					)));

					$this->Session->setFlash('Your qualification "' . $this->request->data['Qualification']['name'] . '" has been saved.', 'flash_success');
				}
			}
			return $this->redirect(array('controller' => 'qualifications', 'action' => 'child_qualification', $parent_qualification_id));
		}
		$parent_qualification = $this->Qualification->find('first', array(
			'conditions' => array(
				'Qualification.id' => $parent_qualification_id,
				'Qualification.deleted is null'
			)
		));

		$child_qualifications = $this->Qualification->find('all', array(
			'conditions' => array(
				'Qualification.parent_id' => $parent_qualification_id,
				'Qualification.deleted is null'
			)
		));
		$parent_qualification_info = $this->get_qualification_from_json($parent_qualification['Qualification']['query_json']);
		$parent_qualification_json = json_decode($parent_qualification['Qualification']['query_json']);
		$child_qualifications_info = $changed_child_qualifications = $child_qualifications_json = array();
		foreach ($child_qualifications as $child_qualification) {
			$child_qualification_info = $this->get_qualification_from_json($child_qualification['Qualification']['query_json']);
			// inherit parent qualification if have not qualification
			foreach ($parent_qualification_info['panelists'] as $key => $value) {
				if (!isset($child_qualification_info['panelists'][$key])) {
					$child_qualification_info['panelists'][$key] = $value;
				}
			}
			foreach ($parent_qualification_info['questions'] as $key => $value) {
				if (!isset($child_qualification_info['questions'][$key])) {
					$child_qualification_info['questions'][$key] = $value;
				}
			}
			$child_qualifications_json[$child_qualification['Qualification']['id']] = json_decode($child_qualification['Qualification']['query_json']);
			$child_qualifications_info[$child_qualification['Qualification']['id']] = $child_qualification_info;
			$changed_child_qualifications[$child_qualification['Qualification']['id']] = $child_qualification;
		}
		$child_qualifications = $changed_child_qualifications;
		$country = $parent_qualification_info['country'];
		// figure out what qualifications we can actually target
		$targetable_fields = array();
		foreach ($parent_qualification_info['panelists'] as $key => $value) {
			if ($key == 'gender') {
				if ($value['answers'] == 'all') {
					$targetable_fields['panelists']['gender']['answers'] = array('M', 'F');
				}
				else if ($value['answers'] == '1') {
					$targetable_fields['panelists']['gender']['answers'] = array('M');
				}
				else {
					$targetable_fields['panelists']['gender']['answers'] = array('F');
				}
			}
			elseif ($key == 'age') {
				$targetable_fields['panelists']['age']['answers']['min'] = min($value['answers']);
				$targetable_fields['panelists']['age']['answers']['max'] = max($value['answers']);
			}
			else if ($key == 'has_location') {
				$locations = array(
					'US' => array('states', 'zip_codes', 'dmas', 'counties'),
					'GB' => array('regions', 'postal_prefixes', 'counties'),
					'CA' => array('regions', 'postal_prefixes', 'provinces'),
				);
				foreach ($child_qualifications as $child_qualification) {
					$child_qualification_info = $child_qualifications_info[$child_qualification['Qualification']['id']];
					if (isset($child_qualification_info['panelists']['has_location'])) {
						foreach ($locations[$country] as $location) {
							if (isset($child_qualification_info['panelists'][$location])) {
								$options = $child_qualification_info['panelists'][$location];
								foreach ($options['answers'] as $id => $option) {
									$targetable_fields['panelists'][$location]['answers'][$id] = $option;
								}
								$targetable_fields['panelists'][$location]['partner_question_id'] = $options['partner_question_id'];
								ksort($targetable_fields['panelists'][$location]);
							}
						}
					}
				}
			}
			else {
				if (count($value['answers']) > 1) {
					ksort($value['answers']);
					$targetable_fields['panelists'][$key] = $value;
				}
			}
		}

		if (!empty($parent_qualification_info['questions'])) {
			$default_question_keys = array(
				'US' => array('STANDARD_HHI_US_v2', 'ETHNICITY', 'HISPANIC'),
				'GB' => array('STANDARD_HHI_INT', 'STANDARD_UK_ETHNICITY'),
				'CA' => array('STANDARD_HHI_INT', 'STANDARD_CANADA_ETHNICITY')
			);
			foreach ($parent_qualification_info['questions'] as $question_key => $question) {
				if ($question_key == 'has_HHI') {
					foreach ($child_qualifications as $child_qualification) {
						$child_qualification_info = $child_qualifications_info[$child_qualification['Qualification']['id']];
						$hhi_question_key = $default_question_keys[$country][0];
						if (isset($child_qualification_info['questions'][$hhi_question_key])) {
							$hhi_question = $child_qualification_info['questions'][$hhi_question_key];
							foreach ($hhi_question['Answers'] as $answer_id => $answer) {
								$targetable_fields['questions'][$hhi_question_key][$answer_id] = $answer;
							}
							ksort($targetable_fields['questions'][$hhi_question_key]);
						}
					}
				}
				else if ($question_key == 'has_Race') {
					foreach ($child_qualifications as $child_qualification) {
						$child_qualification_info = $child_qualifications_info[$child_qualification['Qualification']['id']];
						$race_question_key = $default_question_keys[$country][1];
						if (isset($child_qualification_info['questions'][$race_question_key])) {
							$race_question = $child_qualification_info['questions'][$race_question_key];
							foreach ($race_question['Answers'] as $answer_id => $answer) {
								$targetable_fields['questions'][$race_question_key][$answer_id] = $answer;
								ksort($targetable_fields['questions'][$race_question_key]);
							}
						}
					}
				}
				else if ($question_key == 'has_Hispanic') {
					foreach ($child_qualifications as $child_qualification) {
						$child_qualification_info = $child_qualifications_info[$child_qualification['Qualification']['id']];
						$hispanic_question_key = $default_question_keys[$country][2];
						if (isset($child_qualification_info['questions'][$hispanic_question_key])) {
							$hispanic_question = $child_qualification_info['questions'][$hispanic_question_key];
							foreach ($hispanic_question['Answers'] as $answer_id => $answer) {
								$targetable_fields['questions'][$hispanic_question_key][$answer_id] = $answer;
								ksort($targetable_fields['questions'][$hispanic_question_key]);
							}
						}
					}
				}
				else {
					$answers = Hash::extract($question, 'Answers');
					if (count($answers) > 1) {
						$targetable_fields['questions'][$question['Question']['question']] = $answers;
					}
				}
			}
		}
		
		$default_questions = $this->get_default_questions($parent_qualification_info['country']);
		$this->set(compact('targetable_fields', 'parent_qualification_json', 'child_qualifications_json', 'default_questions'));
		if (is_null($child_qualification_id)) {
			$this->set(compact('parent_qualification_info', 'parent_qualification', 'child_qualifications_info', 'child_qualifications'));
		}
		else {
			$this->set(compact('parent_qualification_info', 'parent_qualification', 'child_qualifications_info', 'child_qualifications', 'child_qualification_id'));
		}
	}

	public function get_qualification_from_json($query_json) {
		$qualification_json = json_decode($query_json);
		$qualifications = $qualification_json->qualifications;
		$country = $qualifications->country[0];
		$panelists = $questions = $answers_return = array();
		foreach ($qualifications as $partner_question_id => $answer_ids) {
			if ($partner_question_id == 'country') {
				continue;
			}
			if ($partner_question_id == '43') {
				if ($answer_ids[0] == '' || count($answer_ids) == 2) {
					$panelists['gender']['answers'] = 'all';
				}
				else {
					$panelists['gender']['answers'] = $answer_ids[0];
				}
				$panelists['gender']['partner_question_id'] = $partner_question_id;
				$panelists['gender']['label'] = 'Gender';
			}
			else if ($partner_question_id == '42') {
				$panelists['age'] = array(
					'answers' => $answer_ids,
					'partner_question_id' => $partner_question_id,
					'label' => 'Age',
				);
			}
			else if ($partner_question_id == '96') {
				$states = $this->LucidZip->find('list', array(
					'fields' => array('LucidZip.lucid_precode', 'LucidZip.state_abbr'),
					'conditions' => array(
							'LucidZip.lucid_precode' => $answer_ids
					),
					'order' => 'LucidZip.state_abbr'
				));
				$panelists['states'] = array(
					'answers' => $states,
					'partner_question_id' => $partner_question_id,
					'label' => 'States',
				);
			}
			else if ($partner_question_id == '45') {
				$panelists['zip_codes'] = array(
					'answers' => $answer_ids,
					'partner_question_id' => $partner_question_id,
					'label' => 'Zip Codes',
				);
			}
			else if ($partner_question_id == '97') {
				$dmas = $this->LucidZip->find('list', array(
					'fields' => array('LucidZip.dma', 'LucidZip.dma_name'),
					'conditions' => array(
							'LucidZip.dma' => $answer_ids,
					),
					'order' => 'LucidZip.dma_name ASC',
					'group' => 'LucidZip.dma'
				));
				$panelists['dmas'] = array(
					'answers' => $dmas,
					'partner_question_id' => $partner_question_id,
					'label' => 'DMAs',
				);
			}
			else if ($partner_question_id == '98') {
				$lucid_zips = $this->LucidZip->find('all', array(
					'fields' => array(
						'LucidZip.state_fips', 'LucidZip.county_fips', 'LucidZip.county'
					),
					'conditions' => array(
						'CONCAT(LPAD(LucidZip.state_fips, 2, 0), LPAD(LucidZip.county_fips, 3, 0))' => $answer_ids
					),
					'group' => array('LucidZip.state_fips', 'LucidZip.county_fips'),
					'order' => 'LucidZip.county ASC'
				));
				foreach ($lucid_zips as $lucid_zip) {
					$formatted_county = str_pad($lucid_zip['LucidZip']['state_fips'], 2, '0', STR_PAD_LEFT) . str_pad($lucid_zip['LucidZip']['county_fips'], 3, '0', STR_PAD_LEFT);
					if (!isset($panelists['counties'][$formatted_county])) {
						$panelists['counties']['answers'][$formatted_county] = $lucid_zip['LucidZip']['county'];
					}
				}
				$panelists['counties']['label'] = 'Counties';
				$panelists['counties']['partner_question_id'] = $partner_question_id;
			}
			else if ($partner_question_id == '12370' || $partner_question_id == '1008') {
				$panelists['postal_prefixes'] = array(
					'answers' => $answer_ids,
					'partner_question_id' => $partner_question_id,
					'label' => 'Postal Prefixes',
				);
			}
			else {
				$this->Question->bindModel(array(
					'hasOne' => array(
						'QuestionText' => array(
							'conditions' => array(
								'QuestionText.country' => $country
							)
						)
					)
				));
				$question = $this->Question->find('first', array(
					'fields' => array(
						'Question.id', 'Question.question', 'QuestionText.text', 'Question.partner_question_id', 'QuestionText.cp_text'
					),
					'conditions' => array(
						'Question.ignore' => false,
						'Question.partner_question_id' => $partner_question_id,
						'Question.partner' => 'lucid'
					),
				));
				$this->Answer->bindModel(array('hasOne' => array('AnswerText')));
				$answers = $this->Answer->find('all', array(
					'fields' => array(
						'Answer.partner_answer_id', 'AnswerText.text'
					),
					'conditions' => array(
						'Answer.ignore' => false,
						'Answer.hide_from_pms' => false,
						'Answer.question_id' => $question['Question']['id'],
						'Answer.partner_answer_id' => $answer_ids,
						'AnswerText.country' => $country
					)
				));
				if ($answers) {
					$answer_return = array();
					foreach ($answers as $answer) {
						$answer_return[$answer['Answer']['partner_answer_id']] = $answer['AnswerText']['text'];
					}
					if ($partner_question_id == '12452') {
						$panelists['counties'] = array(
							'answers' => $answer_return,
							'partner_question_id' => $partner_question_id,
							'label' => 'Counties',
						);
						continue;
					}
					if ($partner_question_id == '12453' || $partner_question_id == '29459') {
						$panelists['regions'] = array(
							'answers' => $answer_return,
							'partner_question_id' => $partner_question_id,
							'label' => 'Regions',
						);
						continue;
					}
					if ($partner_question_id == '1015') {
						$panelists['provinces'] = array(
							'answers' => $answer_return,
							'partner_question_id' => $partner_question_id,
							'label' => 'Provinces',
						);
						continue;
					}
					
					$question = $question + array('Answers' => $answer_return);
					$questions[$question['Question']['question']] = $question;
				}
			}
		}
		if (count($panelists) <= 1) {
			$panelists['has_location'] = false;
		}
		$default_question_keys = array(
			'US' => array('STANDARD_HHI_US_v2' => 'HHI', 'ETHNICITY' => 'Race', 'HISPANIC' => 'Hispanic'),
			'GB' => array('STANDARD_HHI_INT' => 'HHI', 'STANDARD_UK_ETHNICITY' => 'Race'),
			'CA' => array('STANDARD_HHI_INT' => 'HHI', 'STANDARD_CANADA_ETHNICITY' => 'Race')
		);
		foreach ($default_question_keys[$country] as $default_question_key => $value) {
			if (!array_key_exists($default_question_key, $questions)) {
				$questions['has_' . $value] = false;
			}
		}
		
		return array('panelists' => $panelists, 'questions' => $questions, 'country' => $country);
	}

	public function get_default_questions($country) {
		$default_questions = array();
		$default_question_keys = array(
			'US' => array('STANDARD_HHI_US_v2' => 'HHI', 'ETHNICITY' => 'Race', 'HISPANIC' => 'Hispanic'),
			'GB' => array('STANDARD_HHI_INT' => 'HHI', 'STANDARD_UK_ETHNICITY' => 'Race'),
			'CA' => array('STANDARD_HHI_INT' => 'HHI', 'STANDARD_CANADA_ETHNICITY' => 'Race')
		);
		foreach ($default_question_keys[$country] as $default_question_key => $value) {
			$default_question = $this->getQuestion($default_question_key, $country);
			$default_questions[$value] = $default_question;
		}
		return $default_questions;
	}

	public function ajax_edit_qualifications() {
		$query_body = $this->request->data['child_qualification_json'];
		$qualification_id = $this->request->data['qualification_id'];
		$qualification = $this->Qualification->find('first', array(
			'conditions' => array(
				'Qualification.id' => $qualification_id
			)
		));
		$this->Qualification->save(array('Qualification' => array(
			'id' => $qualification_id,
			'query_json' => json_encode($query_body)
		)));

		$this->ProjectLog->create();
		$this->ProjectLog->save(array('ProjectLog' => array(
			'project_id' => $qualification['Qualification']['project_id'],
			'user_id' => $this->current_user['Admin']['id'],
			'type' => 'qualification.updated',
			'description' => 'Child qualification #' . $qualification_id . ' updated with changed query.'
		)));
		return new CakeResponse(array(
			'body' => 'sucess',
			'type' => 'json',
			'status' => '201'
		));
	}

	public function ajax_delete_qualification() {
		$qualification_id = $this->request->data['qualification_id'];
		$this->Qualification->save(array('Qualification' => array(
			'id' => $qualification_id,
			'deleted' => date(DB_DATETIME),
		)));
		$qualification = $this->Qualification->findById($qualification_id);

		$this->ProjectLog->create();
		$this->ProjectLog->save(array('ProjectLog' => array(
			'project_id' => $qualification['Qualification']['project_id'],
			'user_id' => $this->current_user['Admin']['id'],
			'type' => 'qualification.deleted',
			'description' => 'Qualification #' . $qualification_id . ' deleted.'
		)));

		return new CakeResponse(array(
			'body' => $qualification_id,
			'type' => 'json',
			'status' => '201'
		));
	}

	public function refresh_users() {
		$project_id = $this->request->query['project_id'];
		if ($this->request->is('post')) {
			$qualifications = $this->Qualification->find('all', array(
				'conditions' => array(
					'Qualification.project_id' => $project_id,
					'Qualification.deleted is null',
					'Qualification.parent_id is null',
					'Qualification.active' => true
				)
			));
			foreach ($qualifications as $qualification) {
				$exec_query = ROOT . '/app/Console/cake qualification process ' . $qualification['Qualification']['id'] . ' force';
				$exec_query .= "  > /dev/null 2>&1 &";
				exec($exec_query);
			}
			$this->Session->setFlash('Qualification users have been refreshed successfully.', 'flash_success');
			return $this->redirect(array('controller' => 'surveys','action' => 'dashboard', $project_id));
		}
	}
}