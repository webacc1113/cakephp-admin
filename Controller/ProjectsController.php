<?php
App::uses('AppController', 'Controller');

class ProjectsController extends AppController {

	public $uses = array('Admin', 'Project', 'Role', 'SurveyReport', 'SurveyUserVisit', 'Qualification', 'Transaction', 'User', 'ClientReport', 'SurveyComplete', 'GeoCountry', 'SurveyVisit', 'Partner', 'QueryProfile', 'SocialglimpzRespondent', 'Group', 'Question', 'Answer', 'AnswerText', 'FedQuestion', 'Client');
	public $helpers = array('Html', 'Time');
	public $components = array('SurveyAnalysis', 'SurveyTools', 'RequestHandler');
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
		
	public function index() {
		$limit = 200;
		$conditions = array();
		$mintvine_group = $this->Group->find('first', array(
			'fields' => array('Group.id', 'Group.key', 'calculate_margin'),
			'conditions' => array(
				'Group.key' => 'mintvine'
			)
		));
		$this->set(compact('mintvine_group'));
		
		$admin_roles = $this->Admin->roles($this->current_user);
		if (!isset($this->request->query['admin_id']) && empty($this->request->query['admin_id'])) {
			if (array_search('administrators', $admin_roles) === FALSE) {
				if (array_search('account_managers', $admin_roles) || array_search('project_managers', $admin_roles)) {
					$admin_id = $this->current_user['Admin']['id'];
				}
			}
		}
		
		$groups = $this->Admin->groups($this->current_user);
		if (isset($this->request->query['group_id']) && $this->request->query['group_id'] && isset($groups[$this->request->query['group_id']])) {
			$conditions['Project.group_id'] = $this->request->query['group_id'];
			$group = $this->Group->find('first', array(
				'conditions' => array(
					'Group.id' => $this->request->query['group_id']
				)
			));
			$this->set(compact('group'));
			if (!isset($this->request->query['q'])) {
				if (isset($admin_id)) {
					return $this->redirect(array('?' => array('group_id' => $this->request->query['group_id'], 'admin_id' => $admin_id)));
				}
			}
		}
		else {
			if (!isset($this->request->query['group_id']) || empty($this->request->query['group_id'])) {
				$group_id = $mintvine_group['Group']['id'];
			}
			else {
				$group_id = key($groups);
			}
			if (!isset($this->request->query['q'])) {
				if (is_null($group_id)) {
					return $this->redirect('/');
				}
				// if we're trying to access a non-permissioned group, redirect to one that i do have
				if (isset($admin_id)) {
					return $this->redirect(array('?' => array('group_id' => $group_id, 'admin_id' => $admin_id)));
				}
				return $this->redirect(array('?' => array('group_id' => $group_id)));
			}
		}
					
		// if Administrator or PM - generate list of all PMs to see their project load
		if ($this->current_user['AdminRole']['projects'] == true) {
			$admins = $this->Role->get_administrators(array('project_managers', 'account_managers', 'sales_managers'));
			$this->set(compact('admins'));
		}		
		
		if (isset($this->request->query['client_id'])) {
			$conditions['Project.client_id'] = $this->request->query['client_id'];
		}
		
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		
		$status_filter = PROJECT_STATUS_OPEN;
		if (isset($this->request->query['status']) && $this->request->query['status']) {
			$status_filter = $this->request->query['status'];
		}
		
		if ($status_filter != 'all') {
			$conditions['Project.status'] = $status_filter == PROJECT_STATUS_OPEN ? PROJECT_STATUS_OPEN : $status_filter;
		}
		
		if (isset($this->request->query['q']) && $this->request->query['q']) {
			$query = $this->request->query['q'];
			if ($query{0} == '#') {
				$project_id = MintVine::parse_project_id($query); 
				if ($project_id) {
					$this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $project_id));
				}
				else {
					$this->Session->setFlash('We could not find project ' . $this->request->query['q'], 'flash_error');
				}
			}
			else {
				// first try to find an exact single match with that client project id
				if (empty($this->request->query['group_id'])) {
					$search_project = $this->Project->find('all', array(
						'conditions' => array(
							'Project.client_project_id' => $query
						)
					));
					if ($search_project && count($search_project) == 1) {
						$this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $search_project[0]['Project']['id'])); 
					}
				}
				$conditions['OR'] = array(
					'Project.id like' => '%'.$query.'%',
					'Project.prj_name like' => '%'.$query.'%',
					'Project.survey_name like' => '%'.$query.'%',
					'Project.survey_code like' => '%'.$query.'%',
					'Project.mask like' => '%'.$query.'%',
					'Project.description' => '%'.$query.'%',
				);
				
				if (isset($this->request->query['global'])) {
					unset($conditions['Project.group_id']); 
				}
				unset($conditions['Project.status']);
				$status_filter = false;
			}
		}
				
		$this->Project->bindInvoices();
		$this->Project->bindFedSurvey();
		
		$paginate = array(
			'Project' => array(
				'conditions' => $conditions,
				'fields' => array('*'),
				'limit' => $limit,
				'order' => 'Project.id DESC',
				'contain' => array(
					'Client',
					'Group',
					'SurveyVisitCache',
					'Invoice',
					'FedSurvey',
					'ProjectOption' => array(
						'conditions' => array(
							'ProjectOption.name' => array('fulcrum.floor', 'links.count')
						)
					)
				)
			)
		);
		
		// for users who do not have access to projects, need to figure out which projects they have access to here
		if (!$this->current_user['AdminRole']['projects']) {
			$paginate['Project']['joins'][] = array(
	            'alias' => 'ProjectAdmin',
	            'table' => 'project_admins',
	            'type' => 'INNER',
	            'conditions' => array(
					'Project.id = ProjectAdmin.project_id',
					'ProjectAdmin.admin_id' => $this->current_user['Admin']['id']
				)
			);
		}
		
		if (isset($this->request->query['admin_id']) && !empty($this->request->query['admin_id'])) {
			$paginate['Project']['joins'][] = array(
	            'alias' => 'ProjectAdmin',
	            'table' => 'project_admins',
	            'type' => 'INNER',
	            'conditions' => array(
					'Project.id = ProjectAdmin.project_id',
					'ProjectAdmin.admin_id' => $this->request->query['admin_id']
				)
			);
		}
		if (isset($this->request->query['client_id']) && !empty($this->request->query['client_id'])) {
			$client = $this->Client->find('first', array(
				'fields' => array('Client.id', 'Client.client_name'),
				'conditions' => array(
					'Client.id' => $this->request->query['client_id']
				)
			));
			$this->set(compact('client'));
		}
		$this->paginate = $paginate;
		$projects = $this->paginate('Project');
		
		if (empty($projects) && empty($this->request->query['group_id'])) {
			return $this->redirect(array('action' => 'index', '?' => array('group_id' => $mintvine_group['Group']['id'])));
		}
		
		$group = $this->Group->find('first', array(
			'fields' => array('id'), 
			'conditions' => array(
				'Group.key' => 'socialglimpz'
			),
			'recursive' => -1
		));
		if (!empty($projects) && !empty($group)) {
			foreach ($projects as $key => $project) {
				if ($project['Project']['group_id'] == $group['Group']['id']) {
					$socialglimpz_rejects = $this->SocialglimpzRespondent->find('count', array(
						'conditions' => array(
							'SocialglimpzRespondent.survey_id' => $project['Project']['id'],
							'SocialglimpzRespondent.status' => 'rejected'
						)
					));
					$projects[$key]['Project']['socialglimpz_rejects'] = $socialglimpz_rejects;
				}
			}
		}
		$this->set(compact('projects', 'status_filter', 'groups'));
		
	}
	
	public function ajax_qualification_information_points2shop($qualification_id, $project_id = '') {
		$qualification = $this->Qualification->find('first', array(
			'fields' => array('Qualification.query_json'),
			'conditions' => array(
				'Qualification.id' => $qualification_id,
				'Qualification.deleted is null'
			),
			'recursive' => -1
		));
		if ($qualification && !empty($qualification['Qualification']['query_json'])) {
			$query_json = $qualification['Qualification']['query_json'];
			$json = json_decode($qualification['Qualification']['query_json'], true);
			$query_json = json_encode($json, JSON_PRETTY_PRINT);
		}
		else {
			$query_json = false;
		}
		$qualifications = array();
		if (!empty($json['qualifications'])) {
			ksort($json['qualifications']);
			foreach ($json['qualifications'] as $partner_question_id => $answer_ids) {
				$this->Answer->bindModel(array('hasOne' => array(
					'AnswerText' => array(
						'fields' => array('AnswerText.text')
					)
				)));
				$qes_conditions = $ans_conditions = array();
				if (isset($json['qualifications']['country'])) {
					$qes_conditions['QuestionText.country'] = $json['qualifications']['country'];
					$ans_conditions['AnswerText.country'] = $json['qualifications']['country'];
				}
				
				$this->Question->bindModel(array(
					'hasOne' => array(
						'QuestionText' => array(
							'fields' => array('QuestionText.id', 'QuestionText.text'),
							'conditions' => $qes_conditions
						)
					),
					'hasMany' => array(
						'Answer' => array(
							'foreignKey' => 'question_id',
							'conditions' => array(
								'Answer.ignore' => false,
								'Answer.question_id' => 'Question.id'
							)
						)
					),
				));
				$questions = $this->Question->find('first', array(
					'fields' => array('Question.question', 'Question.partner_question_id'),
					'conditions' => array(
						'Question.partner_question_id' => $partner_question_id
					),
					'order' => 'Question.partner_question_id asc',
					'contain' => array(
						'QuestionText',
						'Answer' => array(
							'fields' => array('Answer.id'),
							'conditions' => array(
								'Answer.ignore' => false,
								'Answer.partner_answer_id' => $answer_ids
							),
							'AnswerText' => array(
								'fields' => array('AnswerText.text'),
								'conditions' => $ans_conditions
							)
						)
					)
				));
				if (!empty($questions) && empty($questions['Answer'])) {
					$questions['Answer'][]['AnswerText'] = array('text' => $answer_ids[0] . ' - ' . $answer_ids[count($answer_ids) - 1]);
				}
				
				$qualifications[] = $questions;
			}
		}

		$project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $project_id,
			),
			'fields' => array(
				'Project.id',
				'Project.mask'
			),
			'recursive' => -1,
		));

		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array(
					'points2shop.secret',
					'points2shop.host',
				),
				'Setting.deleted' => false
			),
			'recursive' => -1
		));

		$http = new HttpSocket(array(
			'timeout' => 30,
			'ssl_verify_host' => false
		));
		$params = array(
			'project_id' => $project['Project']['mask']
		);
		$header = array(
			'header' => array(
				'X-YourSurveys-Api-Key' => $settings['points2shop.secret']
			),
		);
		$response = $http->get($settings['points2shop.host'] . '/suppliers_api/surveys', $params, $header);
		$response = json_decode($response, true);

		if (!empty($response['surveys'][0])) {
			$survey_allocation =  $response['surveys'][0];
		}
		else {
			$survey_allocation =  array();
			// If project does not exist in API, fetch it from Points2shopProject
			App::import('Model', 'Points2shopProject');
			$this->Points2shopProject = new Points2shopProject;
			$points2shop_project = $this->Points2shopProject->find('first', array(
				'conditions' => array(
					'Points2shopProject.project_id' => $project_id,
				),
				'recursive' => -1,
			));
			if ($points2shop_project) {
				$survey_allocation = json_decode($points2shop_project['Points2shopProject']['points2shop_json'], true);
			}			
		}

		$p2s_qualifications = array();
		foreach ($survey_allocation['qualifications'] as $question => $answer_ids) {
			$qes_conditions = $ans_conditions = array();
			$this->Answer->bindModel(array('hasOne' => array(
				'AnswerText' => array(
					'fields' => array('AnswerText.text')
				)
			)));
			
			$this->Question->bindModel(array(
				'hasOne' => array(
					'QuestionText' => array(
						'fields' => array('QuestionText.id', 'QuestionText.text'),
					)
				),
				'hasMany' => array(
					'Answer' => array(
						'foreignKey' => 'question_id',
						'conditions' => array(
							'Answer.ignore' => false,
							'Answer.question_id' => 'Question.id'
						)
					)
				),
			));
			$p2s_question = $this->Question->find('first', array(
				'fields' => array('Question.question', 'Question.partner_question_id'),
				'conditions' => array(
					'Question.question' => $question
				),
				'order' => 'Question.partner_question_id asc',
				'contain' => array(
					'QuestionText',
					'Answer' => array(
						'fields' => array('Answer.id'),
						'conditions' => array(
							'Answer.ignore' => false,
							'Answer.partner_answer_id' => $answer_ids
						),
						'AnswerText' => array(
							'fields' => array('AnswerText.text'),
							'conditions' => $ans_conditions
						)
					)
				)
			));
			
			if (!$p2s_question) {
				$p2s_question = array('Question' => array(
					'question' => 'Question id: '.$question. ' not found!'
				));
				continue;
			}
			
			if (!empty($p2s_question) && empty($p2s_question['Answer'])) {
				$p2s_question['Answer'][]['AnswerText'] = array('text' => $answer_ids[0] . ' - ' . $answer_ids[count($answer_ids) - 1]);
			}
			$p2s_qualifications[] = $p2s_question;
		}
		
			
		$quotas = array();
		foreach ($survey_allocation['quotas'] as $survey_quota) {
			if (!is_array($survey_quota)) {
				continue;
			}
			$qes_conditions = $ans_conditions = array();
			$quota_qualifications = array();
			foreach ($survey_quota['conditions'] as $question => $answers) {
				if (empty($question) || empty($answers)) {
					continue;
				}
				
				$this->Answer->bindModel(array('hasOne' => array(
					'AnswerText' => array(
						'fields' => array('AnswerText.text')
					)
				))); 
				
				$this->Question->bindModel(array(
					'hasOne' => array(
						'QuestionText' => array(
							'fields' => array('QuestionText.id', 'QuestionText.text'),
							'conditions' => $qes_conditions
						)
					),
					'hasMany' => array(
						'Answer' => array(
							'foreignKey' => 'question_id',
							'conditions' => array(
								'Answer.ignore' => false,
								'Answer.question_id' => 'Question.id'
							)
						)
					),
				));
				$p2s_question = $this->Question->find('first', array(
					'fields' => array('Question.question', 'Question.partner_question_id'),
					'conditions' => array(
						'Question.question' => $question
					),
					'order' => 'Question.partner_question_id asc',
					'contain' => array(
						'QuestionText',
						'Answer' => array(
							'fields' => array('Answer.id'),
							'conditions' => array(
								'Answer.ignore' => false,
								'Answer.partner_answer_id' => $answers
							),
							'AnswerText' => array(
								'fields' => array('AnswerText.text'),
								'conditions' => $ans_conditions
							)
						)
					)
				));
				if (!empty($p2s_question) && empty($p2s_question['Answer'])) {
					$p2s_question['Answer'][]['AnswerText'] = array('text' => $question . ' - ' . $answers[count($answers) - 1]);
				}
				$quota_qualifications[] = $p2s_question;
			}			
			$quotas[$survey_quota['id']] = $quota_qualifications;
		}

		$this->set(compact('p2s_qualifications', 'quotas', 'query_json', 'qualifications'));
	}
	
	public function ajax_qualification_information_spectrum($qualification_id, $spectrum_survey_id = '') {
		App::import('Vendor', 'SiteProfile');
		$qualification = $this->Qualification->find('first', array(
			'fields' => array('Qualification.query_json'),
			'conditions' => array(
				'Qualification.id' => $qualification_id,
				'Qualification.deleted is null'
			),
			'recursive' => -1
		));
		if ($qualification && !empty($qualification['Qualification']['query_json'])) {
			$query_json = $qualification['Qualification']['query_json'];
			$json = json_decode($qualification['Qualification']['query_json'], true);
			$query_json = json_encode($json, JSON_PRETTY_PRINT);
		}
		else {
			$query_json = false;
		}
		
		$qualifications = array();
		if (!empty($json['qualifications'])) {
			ksort($json['qualifications']);
			$qualifications = MintVine::query_string_to_readable_qe2($json['qualifications']); 
			$mappings = MintVine::query_string_mappable_values();
		}
		
		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => array('spectrum.host', 'spectrum.supplier_id', 'spectrum.access_token'),
				'Setting.deleted' => false
			)
		));
		
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false
		));
		$params = array(
			'supplier_id' => $settings['spectrum.supplier_id'],
			'access_token' => $settings['spectrum.access_token'],
			'survey_id' => $spectrum_survey_id
		);
		$header = array('header' => array(
			'Content-Type' => 'application/x-www-form-urlencoded',
			'cache-control' => 'no-cache'
		));
		$url = $settings['spectrum.host'].'/suppliers/surveys/qualifications-quotas';
		$response = $http->post($url, $params, $header);
		$response_body = json_decode($response->body, true);
		if ($response->code != 200 || empty($response_body['apiStatus']) || $response_body['apiStatus'] =! 'Success') {
			$response_body = array();
		}
		$this->set('qualifications_and_quotas', $response_body);
		$this->set(compact('query_json', 'qualifications', 'mappings'));
	}
	
	public function ajax_qualification_information($qualification_id, $project_id = '') {

		$qualification = $this->Qualification->find('first', array(
			'fields' => array('Qualification.query_json'),
			'conditions' => array(
				'Qualification.id' => $qualification_id,
				'Qualification.deleted is null'
			),
			'recursive' => -1
		));
		if ($qualification && !empty($qualification['Qualification']['query_json'])) {
			$query_json = $qualification['Qualification']['query_json'];
			$json = json_decode($qualification['Qualification']['query_json'], true);
			$query_json = json_encode($json, JSON_PRETTY_PRINT);
		}
		else {
			$query_json = false;
		}
		$country_language_mapping = array(
			6 => 'CA', // Canada
			8 => 'GB', // UK
			9 => 'US' // US
		);
		$qualifications = array();
		if (!empty($json['qualifications'])) {
			ksort($json['qualifications']);
			foreach ($json['qualifications'] as $question_id => $answer_ids) {
				$this->Answer->bindModel(array('hasOne' => array(
					'AnswerText' => array(
						'fields' => array('AnswerText.text')
					)
				))); 
				$qes_conditions = $ans_conditions = array();
				if (isset($json['qualifications']['country'])) {
					$qes_conditions['QuestionText.country'] = $json['qualifications']['country'];
					$ans_conditions['AnswerText.country'] = $json['qualifications']['country'];
				}
				
				$this->Question->bindModel(array(
					'hasOne' => array(
						'QuestionText' => array(
							'fields' => array('QuestionText.id', 'QuestionText.text'),
							'conditions' => $qes_conditions
						)
					),
					'hasMany' => array(
						'Answer' => array(
							'foreignKey' => 'question_id',
							'conditions' => array(
								'Answer.ignore' => false,
								'Answer.question_id' => 'Question.id'
							)
						)
					),
				));
				$questions = $this->Question->find('first', array(
					'fields' => array('Question.question', 'Question.partner_question_id'),
					'conditions' => array(
						'Question.partner_question_id' => $question_id
					),
					'order' => 'Question.partner_question_id asc',
					'contain' => array(
						'QuestionText',
						'Answer' => array(
							'fields' => array('Answer.id'),
							'conditions' => array(
								'Answer.ignore' => false,
								'Answer.partner_answer_id' => $answer_ids
							),
							'AnswerText' => array(
								'fields' => array('AnswerText.text'),
								'conditions' => $ans_conditions
							)
						)
					)
				));
				
				if (!empty($questions) && empty($questions['Answer'])) {
					$questions['Answer'][]['AnswerText'] = array('text' => $answer_ids[0] . ' - ' . $answer_ids[count($answer_ids) - 1]);
				}
				
				$qualifications[] = $questions;
			}	
		}
		$HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$request_data = array(
			'key' => FED_API_KEY
		);
		$response = $HttpSocket->get(FED_API_HOST . 'Supply/v1/' . 'Surveys/SupplierAllocations/BySurveyNumber/' . $project_id, $request_data);		
		$survey_allocation =  json_decode($response['body'], true);
		
		$response = $HttpSocket->get(FED_API_HOST . 'Supply/v1/' . 'SurveyQualifications/BySurveyNumberForOfferwall/' . $project_id, $request_data);		
		$survey_qualifications = json_decode($response['body'], true);
		
		$offerwall_qualifications = array();
		foreach ($survey_qualifications['SurveyQualification']['Questions'] as $question) {
			if ($question['LogicalOperator'] != 'Or') {
				continue;
			}
			$qes_conditions = $ans_conditions = array();
			if (isset($survey_allocation['SupplierAllocationSurvey']['CountryLanguageID'])) {
				if (isset($country_language_mapping[$survey_allocation['SupplierAllocationSurvey']['CountryLanguageID']])) {
					$qes_conditions['QuestionText.country'] = $country_language_mapping[$survey_allocation['SupplierAllocationSurvey']['CountryLanguageID']];
					$ans_conditions['AnswerText.country'] = $country_language_mapping[$survey_allocation['SupplierAllocationSurvey']['CountryLanguageID']];
				}
			}
			$this->Answer->bindModel(array('hasOne' => array(
				'AnswerText' => array(
					'fields' => array('AnswerText.text')
				)
			))); 
			
			$this->Question->bindModel(array(
				'hasOne' => array(
					'QuestionText' => array(
						'fields' => array('QuestionText.id', 'QuestionText.text'),
						'conditions' => $qes_conditions
					)
				),
				'hasMany' => array(
					'Answer' => array(
						'foreignKey' => 'question_id',
						'conditions' => array(
							'Answer.ignore' => false,
							'Answer.question_id' => 'Question.id'
						)
					)
				),
			));
			$fed_question = $this->Question->find('first', array(
				'fields' => array('Question.question', 'Question.partner_question_id'),
				'conditions' => array(
					'Question.partner_question_id' => $question['QuestionID']
				),
				'order' => 'Question.partner_question_id asc',
				'contain' => array(
					'QuestionText',
					'Answer' => array(
						'fields' => array('Answer.id'),
						'conditions' => array(
							'Answer.ignore' => false,
							'Answer.partner_answer_id' => $question['PreCodes']
						),
						'AnswerText' => array(
							'fields' => array('AnswerText.text'),
							'conditions' => $ans_conditions
						)
					)
				)
			));
			
			if (!$fed_question) {
				$fed_question = array('Question' => array(
					'question' => 'Question id: '.$question['QuestionID']. ' not found!'
				));
				continue;
			}
			
			if (!empty($fed_question) && empty($fed_question['Answer'])) {
				$fed_question['Answer'][]['AnswerText'] = array('text' => $question['PreCodes'][0] . ' - ' . $question['PreCodes'][count($question['PreCodes']) - 1]);
			}
			$offerwall_qualifications[] = $fed_question;
		}
		
		$response = $HttpSocket->get(FED_API_HOST . 'Supply/v1/' . 'SurveyQuotas/BySurveyNumber/' . $project_id . '/' . FED_SUPPLIER_CODE, $request_data);	
		
		$survey_quotas = json_decode($response['body'], true);	
			
		$quotas = array();
		foreach ($survey_quotas['SurveyQuotas'] as $quota) {
			// Skip Overall quota
			if ($quota['SurveyQuotaType'] == 'Total') {
				continue;
			}
			$qes_conditions = $ans_conditions = array();
			if (isset($survey_allocation['SupplierAllocationSurvey']['CountryLanguageID'])) {
				if (isset($country_language_mapping[$survey_allocation['SupplierAllocationSurvey']['CountryLanguageID']])) {
					$qes_conditions['QuestionText.country'] = $country_language_mapping[$survey_allocation['SupplierAllocationSurvey']['CountryLanguageID']];
					$ans_conditions['AnswerText.country'] = $country_language_mapping[$survey_allocation['SupplierAllocationSurvey']['CountryLanguageID']];
				}
			}
			$quota_qualifications = array();
			foreach ($quota['Questions'] as $question) {
				if ($question['LogicalOperator'] != 'OR') {
					continue;
				}
				
				$this->Answer->bindModel(array('hasOne' => array(
					'AnswerText' => array(
						'fields' => array('AnswerText.text')
					)
				))); 
				
				$this->Question->bindModel(array(
					'hasOne' => array(
						'QuestionText' => array(
							'fields' => array('QuestionText.id', 'QuestionText.text'),
							'conditions' => $qes_conditions
						)
					),
					'hasMany' => array(
						'Answer' => array(
							'foreignKey' => 'question_id',
							'conditions' => array(
								'Answer.ignore' => false,
								'Answer.question_id' => 'Question.id'
							)
						)
					),
				));
				$fed_question = $this->Question->find('first', array(
					'fields' => array('Question.question', 'Question.partner_question_id'),
					'conditions' => array(
						'Question.partner_question_id' => $question['QuestionID']
					),
					'order' => 'Question.partner_question_id asc',
					'contain' => array(
						'QuestionText',
						'Answer' => array(
							'fields' => array('Answer.id'),
							'conditions' => array(
								'Answer.ignore' => false,
								'Answer.partner_answer_id' => $question['PreCodes']
							),
							'AnswerText' => array(
								'fields' => array('AnswerText.text'),
								'conditions' => $ans_conditions
							)
						)
					)
				));
				if (!empty($fed_question) && empty($fed_question['Answer'])) {
					$fed_question['Answer'][]['AnswerText'] = array('text' => $question['PreCodes'][0] . ' - ' . $question['PreCodes'][count($question['PreCodes']) - 1]);
				}
				$quota_qualifications[] = $fed_question;
			}
			
			$quotas[$quota['SurveyQuotaID']] = $quota_qualifications;
		}

		$this->set(compact('offerwall_qualifications', 'quotas')); 
		$this->set(compact('query_json', 'qualifications'));
	}
	
	function ajax_show_partners($project_id) {
		if (!$this->Project->exists($project_id)) {
			throw new NotFoundException(__('Invalid Project ID!'));
		}
		
		$project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $project_id
			),			
			'contain' => array(
				'SurveyPartner' => array('Partner'),
				'ProjectAdmin'
			)
		));
		if (!$this->Admins->can_access_project($this->current_user, $project)) {
			return new CakeResponse(array('status' => '401'));
		}
		
		if ($project['SurveyPartner']) {			
			$view = new View($this, false);
			return new CakeResponse(array(
				'body' => json_encode(array(
					'partners' => $view->element('ajax_partners', array(
						'project' => $project,
						'project_id' => $project_id, 
						'partners' => $project['SurveyPartner'], 
						'status' => ($project['Project']['active']) ? 'live' : 'paused'
					))
				)),
				'type' => 'json',
				'status' => '201'
			));
		}
		else {
			return new CakeResponse(array(
				'body' => json_encode(array()),
				'type' => 'json',
				'status' => '400'
			));
		}
	}

	public function ajax_search_clients() {
		$term = $this->request->query['term'];
		$clients = $this->Client->find('list', array(
			'fields' => array('Client.id', 'Client.client_name'),
			'conditions' => array(
				'Client.client_name LIKE ' => '%' . $term . '%',
			),
			'order' => 'Client.client_name ASC'
		));
		$searched_clients = array();
		foreach ($clients as $key => $value) {
			$searched_clients[] = array('id' => $key, 'label' => $value, 'value' => $value);
		}
		return new CakeResponse(array(
			'body' => json_encode($searched_clients),
			'type' => 'json',
			'status' => '201'
		));
	}
}
