<?php
App::uses('AppController', 'Controller');

class QueriesController extends AppController {
	public $uses = array('Query', 'GeoCountry', 'GeoState', 'GeoZip', 'SurveyUser', 'SurveyVisit', 'Project', 'SurveyLink', 'Profile', 'ProfileQuestion', 'QueryStatistic', 'RegionMapping');
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array('QueryEngine', 'RequestHandler');
	
	public function beforeFilter() {
		parent::beforeFilter();
		
		if ($this->request->params['action'] == 'send') {
			$models_to_load = array('Nonce', 'MailQueue');
		}
		if ($this->request->params['action'] == 'ajax_retarget') {
			$models_to_load = array('SurveyUserVisit', 'SurveyReport', 'Report', 'Partner');
		}
		
		if (isset($models_to_load) && !empty($models_to_load)) {
			foreach ($models_to_load as $model) {
				App::import('Model', $model);
				$this->$model = new $model;
			}
		}
	}
	
	public function index() {
		
	}
	
	public function filter($query_id) {
		App::import('Vendor', 'SiteProfile');
		$query = $this->Query->find('first', array(
			'conditions' => array(
				'Query.id' => $query_id
			)
		));
		$query_string = json_decode($query['Query']['query_string'], true);
		$query_string = MintVine::query_string_to_readable($query_string); 
		$mappings = MintVine::query_string_mappable_values(); 
		foreach ($mappings as $key => $val) {	
			if (isset($query_string[$key])) {
				$val = unserialize($val['data']);
				$list = array();
				foreach ($query_string[$key] as $value) {
					$list[] = $value;
				}
				$query_string[$key] = $list;
			}
		}
		
		$datapoints = array();
		
		// determine the genders to show
		if (isset($query_string['gender'])) {
			$query_string['gender'] = ($query_string['gender'] == 'M') ? array('M' => 'Male') : array('F' => 'Female');
		}
		else {
			$query_string['gender'] = unserialize(USER_PROFILE_GENDERS);
		}
		
		$countries = $this->GeoCountry->returnAsList();
		
		$states = $this->GeoState->find('all', array(
			'fields' => array('state_abbr', 'state', 'region'),
			'conditions' => array(
				'id >' => '0'
			),
			'order' => 'GeoState.state ASC'
		));
		$state_regions = $states_list = array();
		foreach ($states as $state) {
			$states_list[$state['GeoState']['state_abbr']] = $state['GeoState']['state_abbr'].' - '.$state['GeoState']['state'];
			$state_regions[$state['GeoState']['state_abbr']] = $state['GeoState']['region'];
		}
		
		$dmas = $this->GeoZip->find('list', array(
			'fields' => array('dma_code', 'dma'),
			'conditions' => array(
				'GeoZip.dma_code !=' => '',
				'GeoZip.country_code' => 'US'
			),
			'order' => 'dma asc',
			'group' => 'dma_code'
		));
		
		$this->Query->bindModel(array('hasOne' => array('QueryStatistic')));
		$this->Query->unbindModel(array('hasMany' => array('QueryHistory')));
		$other_queries = $this->Query->find('all', array(
			'fields' => array(
				'Query.query_name', 'QueryStatistic.quota'
			),
			'conditions' => array(
				'Query.parent_id' => $query_id
			)
		));
		$this->set(compact('query', 'query_string', 'countries', 'state_regions', 'states_list',  'dmas', 'mappings', 'other_queries', 'states'));
	}
		
	public function ajax_status($query_history_id) {
		$this->Query->QueryHistory->bindModel(array('belongsTo' => array('Query')));
		$query_history = $this->Query->QueryHistory->findById($query_history_id);
		if (!$this->Admins->can_access_project($this->current_user, $query_history['Query']['survey_id'])) {
			return new CakeResponse(array('status' => '401'));
		}
		
		$this->Query->QueryHistory->create();
		$this->Query->QueryHistory->save(array('QueryHistory' => array(
			'id' => $query_history_id,
			'active' => !$query_history['QueryHistory']['active']
		)), true, array('active'));
		
    	return new CakeResponse(array(
			'body' => json_encode(array(
				'button' => $query_history['QueryHistory']['active'] ? 'btn-danger': 'btn-success',
				'icon' => $query_history['QueryHistory']['active'] ? 'icon-stop': 'icon-play'
			)), 
			'type' => 'json',
			'status' => '201'
		));
	}
	public function ajax_status_by_query($query_id, $query_status) {
		$query = $this->Query->find('first', array(
			'conditions' => array(
				'Query.id' => $query_id
			),
			'fields' => array(
				'Query.id', 'Query.parent_id', 'Query.survey_id'
			),
			'recursive' => -1
		));
		if (!$this->Admins->can_access_project($this->current_user, $query['Query']['survey_id'])) {
			return new CakeResponse(array('status' => '401'));
		}
		
		$query_histories = $this->Query->QueryHistory->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'QueryHistory.query_id' => $query_id,
				'QueryHistory.type' => 'sent'
			)
		));
		if ($query_histories) {
			foreach ($query_histories as $query_history) {
				$this->Query->QueryHistory->create();
				$this->Query->QueryHistory->save(array('QueryHistory' => array(
					'id' => $query_history['QueryHistory']['id'],
					'active' => !$query_status
				)), true, array('active'));
			}
		}
    	return new CakeResponse(array(
			'body' => json_encode(array(
				'query_id' => empty($query['Query']['parent_id']) ? $query['Query']['id']: '0',
				'button' => $query_status ? 'btn-danger': 'btn-success',
				'query_status' => $query_status ? '0': '1',
				'icon' => $query_status ? 'icon-stop': 'icon-play'
			)), 
			'type' => 'json',
			'status' => '201'
		));
	}
	
	public function ajax_resend($query_id = null) {		
		if ($this->request->is('put') || $this->request->is('post')) {
			$query = $this->Query->findById($this->data['id']);
			if (!$this->Admins->can_access_project($this->current_user, $query['Query']['survey_id'])) {
				return new CakeResponse(array('status' => '401'));
			}
			
			$exec_query = ROOT.'/app/Console/cake query resend '.$query['Query']['id'];
			$exec_query.= "  > /dev/null 2>&1 &"; 
			CakeLog::write('query_commands', $exec_query); 
			exec($exec_query, $output);
			
			$this->Session->setFlash('A new query has been generated from the existing user list and has been scheduled to mail out.', 'flash_success');
			sleep(1);
			$this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $query['Query']['survey_id']));
		}
		else {
			$query = $this->Query->findById($query_id);
		}
		$this->layout = 'ajax';
		$this->set(compact('query'));
	}
	
	public function ajax_preview() {
		// process zip file if it exists and grab only relevant ZIP codes
		if (isset($this->request->data['Query']['zip_file'])) {
			if (empty($this->request->data['Query']['zip_file']['error']) && (!empty($this->request->data['Query']['zip_file']['tmp_name']))) {
				$csvs = Utils::csv_to_array($this->request->data['Query']['zip_file']['tmp_name']);
				$zips = $postal_prefix = array();
				foreach ($csvs as $csv) {
					foreach ($csv as $key => $val) {
						if (in_array(strlen($val), array(2, 3))) {
							$postal_prefix[] = $val; 
						}
						elseif (isset($this->request->data['Query']['country']) && $this->request->data['Query']['country'] == 'CA') {
							$zips[] = Utils::format_ca_postcode($val);
						}
						elseif (isset($this->request->data['Query']['country']) && $this->request->data['Query']['country'] == 'GB') {
							$zips[] = Utils::format_uk_postcode($val);
						}
						else {
							$zips[] = $val;
						}
					}
				}
				
				if (!empty($postal_prefix)) {
					$this->request->data['Query']['postal_prefix'] = $postal_prefix;
				}
				
				$zips = $this->GeoZip->find('list', array(
					'fields' => array('id', 'zipcode'),
					'conditions' => array(
						'GeoZip.zipcode' => $zips,
					)
				));
				if (!empty($zips)) {
					$this->request->data['Query']['postal_code'] = array_values($zips);
				}
			}
			
			unset($this->request->data['Query']['zip_file']);
		}
		
		$survey_id = $this->request->data['Query']['survey_id'];
		if (!empty($survey_id) && !$this->Admins->can_access_project($this->current_user, $survey_id)) {
			return new CakeResponse(array('status' => '401'));
		}
				
		$has_filter = isset($this->request->data['Query']['parent_id']);
		if ($has_filter) {
			$query = $this->Query->find('first', array(
				'recursive' => -1,
				'fields' => array('Query.id', 'Query.query_string', 'Query.query_name'),
				'conditions' => array(
					'Query.id' => $this->request->data['Query']['parent_id']
				)
			));
			$query_string = json_decode($query['Query']['query_string'], true); 
			// combine the queries
			foreach ($this->request->data['Query'] as $key => $val) {
				if ($key == 'gender') {
					if (empty($val)) {
						unset($this->request->data['Query'][$key]); 
					}
				}
				elseif (is_array($val)) {
					$delete = true;
					foreach ($val as $k => $v) {
						if ($v == 1) {
							$delete = false;
							break;
						}
					}
					if ($delete) {
						unset($this->request->data['Query'][$key]);
					}
					else {
						foreach ($val as $k => $v) {
							if (empty($v)) {
								unset($this->request->data['Query'][$key][$k]);
							}
						}
					}
				}
			}
			$this->set('parent_id', $this->request->data['Query']['parent_id']);
			unset($this->request->data['Query']['parent_id']); 
			$results = $this->QueryEngine->execute($this->request->data['Query'], $survey_id, 'pre');
			foreach ($query_string as $key => $val) {
				if (isset($results['query'][$key])) {
					$query_string[$key] = $results['query'][$key];
				}
			}
		}
		else {
			$results = $this->QueryEngine->execute($this->request->data['Query'], $survey_id, 'pre');
		}
		
		$project = $this->Project->find('first', array(
			'fields' => array('Project.id', 'Project.prj_name', 'Project.group_id', 'Project.mask', 'Project.quota'),
			'contain' => array(
				'Group' => array(
					'fields' => array('Group.name')
				)
			),
			'conditions' => array(
				'Project.id' => $survey_id
			),
			'recursive' => -1
		));
		
		$this->set(compact('results', 'survey_id', 'project', 'has_filter', 'query'));
		$this->RequestHandler->respondAs('application/json'); 
		$this->response->statusCode('201');
		$this->layout = '';
	}
	
	public function ajax_retarget($project_id) {
		if (!$this->Admins->can_access_project($this->current_user, $project_id)) {
			return new CakeResponse(array('status' => '401'));
		}
			
		if ($this->request->is('post') || $this->request->is('put')) {			
			$respondent_ids = explode("\n", $this->request->data['Project']['hashes']);
			$respondent_ids = array_map('trim', $respondent_ids);
			$user_ids = array();
			$project = $this->Project->find('first', array(
				'conditions' => array(
					'Project.id' => $project_id
				),
				'fields' => array('client_survey_link'),
				'recursive' => -1
			));
			
			$i = 0;
			$mv_partner = $this->Partner->find('first', array(
				'conditions' => array(
					'Partner.key' => 'mintvine',
					'Partner.deleted' => false
				)
			));
			$j = 0; 
			foreach ($respondent_ids as $respondent_id) {
				if (empty($respondent_id)) {
					continue;
				}
				$survey_id = Utils::parse_project_id_from_hash($respondent_id);
				$i++;
				$row = $this->SurveyVisit->find('first', array(
					'conditions' => array(
						'SurveyVisit.survey_id' => $survey_id,
						'SurveyVisit.type' => SURVEY_COMPLETED,
						'SurveyVisit.partner_user_id' => $respondent_id
					)
				));
				if (!$row) {
					$row = $this->SurveyVisit->find('first', array(
						'conditions' => array(
							'SurveyVisit.survey_id' => $survey_id,
							'SurveyVisit.type' => SURVEY_COMPLETED,
							'SurveyVisit.hash' => $respondent_id
						)
					));
				}
				if ($row) {
					if ($row['SurveyVisit']['partner_id'] == $mv_partner['Partner']['id']) {
						$partner_user_ids = explode('-', $row['SurveyVisit']['partner_user_id']); 
						$user_id = $partner_user_ids[1];
						$user_ids[] = $user_id;
						if (isset($this->request->data['Project']['custom_links'])) {
							$this->SurveyLink->create();
							$this->SurveyLink->save(array('SurveyLink' => array(
								'survey_id' => $project_id,
								'link' => $project['Project']['client_survey_link'].'&oldid='.$respondent_id,
								'user_id' => $user_id,
								'sort_order' => $j,
								'active' => true
							)));
							$j++;
						}						
					}
					else {	
						if (isset($this->request->data['Project']['custom_links'])) {
							$this->SurveyLink->create();
							$this->SurveyLink->save(array('SurveyLink' => array(
								'survey_id' => $project_id,
								'partner_id' => $row['SurveyVisit']['partner_id'],
								'link' => $project['Project']['client_survey_link'].'&oldid='.$respondent_id,
								'partner_user_id' => $row['SurveyVisit']['partner_user_id'],
								'sort_order' => $j,
								'active' => true
							)));
							$j++;
						}
					}
				}	
			}
			
			if (empty($user_ids)) {
				$this->Session->setFlash('We could not locate any users based on the uploaded user hashes.', 'flash_error');
			}
			else {								
				if (!empty($user_ids)) {
					$querySource = $this->Query->getDataSource();
					$querySource->begin();
					$this->Query->create();
				
					$query = array(
						'query_name' => '#'.$project_id.' retarget',
						'query_string' => json_encode(array('user_id' => $user_ids)),
						'survey_id' => $project_id
					);
			
					if ($this->Query->save(array('Query' => $query))) {
						$query_id = $this->Query->getInsertId();
						$querySource->commit();
						// create the first query history
						$query = (array) json_decode($query['query_string']);
						$results = $this->QueryEngine->execute($query);
				
						$this->Query->QueryHistory->create();
						$this->Query->QueryHistory->save(array('QueryHistory' => array(
							'query_id' => $query_id,
							'item_id' => $project_id,
							'item_type' => TYPE_SURVEY,
							'total' => $results['count']['total'],
							'type' => 'created'
						)));
					}
					else {
						$querySource->commit();	
					}
				}
				$this->Session->setFlash('Your recontact has been complete; we found '.count($user_ids).' users from the '.$i.' hashes you uploaded. View the query below to send the notification.', 'flash_success');
			}
		}
    	return new CakeResponse(array(
			'body' => json_encode(array(
				'count' => array(
					'hashes' => $i,
					'users' => count($user_ids)
				)
			)), 
			'type' => 'json',
			'status' => '201'
		));
	}
	
	public function send() {
		ini_set('memory_limit', '1024M');
		set_time_limit(1200);
		$log = false;
		
		if ($this->request->is('post') || $this->request->is('put')) {
			$survey_reach = $this->data['reach'];
			$redirect_url = array('controller' => 'surveys', 'action' => 'dashboard', $this->data['survey_id']);
			
			if (!isset($this->data['query'])) {
				$this->Session->setFlash('You did not select a valid query.', 'flash_error');
				$this->redirect($redirect_url);
			}
			if (empty($survey_reach) || $survey_reach < 0) {
				$this->Session->setFlash('You did not specify a valid reach number - please set a number greater than zero.', 'flash_error');
				$this->redirect($redirect_url);
			}
			
			$query_id = $this->data['query'];
			$survey_id = $this->data['survey_id'];
			if (!$this->Admins->can_access_project($this->current_user, $survey_id)) {
				$this->Session->setFlash('You are not authorized to access this project.', 'flash_error');
				$this->redirect($redirect_url);
			}
			
			$queryHistorySource = $this->Query->QueryHistory->getDataSource();
			$queryHistorySource->begin();
			$this->Query->QueryHistory->create();
			$this->Query->QueryHistory->save(array('QueryHistory' => array(
				'query_id' => $query_id,
				'item_id' => $survey_id,
				'item_type' => TYPE_SURVEY,
				'count' => null,
				'total' => null,
				'type' => 'sending'
			)));
			$query_history_id = $this->Query->QueryHistory->getInsertId();
			$queryHistorySource->commit();
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $survey_id,
				'user_id' => $this->current_user['Admin']['id'],
				'type' => 'query.executed',
				'query_id' => $query_id,
				'description' => 'Query executed from cp, total sent : ' . $survey_reach
			)));
			
			$query = ROOT.'/app/Console/cake query create_queries '.$survey_id.' '.$query_id.' '.$query_history_id.' '.$survey_reach;
			$query.= "  > /dev/null 2>&1 &"; 
			
			CakeLog::write('query_commands', $query); 
			// /var/www/html/cp.mintvine.com/web/app/Console/cake query create_queries 12632 16695 32042 1 > /dev/null 2>&1 &
			exec($query, $output);
						
			$this->Session->setFlash('We are generating your query and will be sending out the mails shortly.', 'flash_success');
			$this->redirect($redirect_url);
		}
	}
	
	public function ajax_data($query_id) {
		$this->Query->bindModel(array('hasOne' => array('QueryStatistic')));
		$this->Query->unbindModel(array('hasMany' => array('QueryHistory')));
		$query = $this->Query->find('first', array(
			'contain' => array('QueryStatistic'),
			'conditions' => array(
				'Query.id' => $query_id,
			),
			'recursive' => -1
		));
		$project = $this->Project->find('first', array(
			'fields' => array('Project.id', 'Project.bid_ir', 'Project.quota', 'Project.group_id'),
			'contain' => array(
				'SurveyVisitCache' => array(
					'fields' => array('SurveyVisitCache.click', 'SurveyVisitCache.complete')
				),
				'ProjectAdmin'
			),
			'conditions' => array(
				'Project.id' => $query['Query']['survey_id']
			),
			'recursive' => -1
		));
		if (!$this->Admins->can_access_project($this->current_user, $project)) {
			return new CakeResponse(array('status' => '401'));
		}
		
		$qs = (array) json_decode($query['Query']['query_string']);
		$results = $this->QueryEngine->execute($qs, $query['Query']['survey_id']);
		App::import('Lib', 'MintVine');
		$survey_reach = MintVine::query_amount($project, $results['count']['total'], $query);
		if (empty($project['Project']['quota'])) {
			$survey_reach = $results['count']['total']; 
		}
		
		return new CakeResponse(array(
			'body' => json_encode(array(
				'total' => $results['count']['total'],
				'suggested' => $survey_reach
			)), 
			'type' => 'json',
			'status' => '201'
		));
	}
	
	public function ajax_quick_send($survey_id = null) {
		if ($this->request->is('put') || $this->request->is('post')) {
			if (!$this->Admins->can_access_project($this->current_user, $this->request->data['survey_id'])) {
				$this->Session->setFlash('You are not authorized to access this project.', 'flash_error');
				return $this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $this->request->data['survey_id']));
			}
			
			$query = ROOT.'/app/Console/cake query mass_send '.$this->request->data['survey_id'];
			$query.= "  > /dev/null 2>&1 &"; 
			CakeLog::write('query_commands', $query); 
			exec($query, $output);
			
			$this->Session->setFlash('Mass query sent', 'flash_success');
			return $this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $this->request->data['survey_id']));
			
		}
		$project = $this->Project->find('first', array(
			'fields' => array('Project.id', 'Project.bid_ir', 'Project.quota', 'SurveyVisitCache.ir', 'SurveyVisitCache.click', 'SurveyVisitCache.complete'),
			'conditions' => array(
				'Project.id' => $survey_id
			)
		));
		$this->Query->bindModel(array('hasOne' => array('QueryStatistic')));
		$this->Query->unbindModel(array('hasMany' => array('QueryHistory')));
		$queries = $this->Query->find('all', array(
			'conditions' => array(
				'Query.survey_id' => $survey_id,
				'Query.parent_id' => '0'
			),
			'order' => 'query_name asc'
		));
		
		$totals = array();
		if ($queries) {
			foreach ($queries as $query) {				
				$qs = (array) json_decode($query['Query']['query_string']);
				$results = $this->QueryEngine->execute($qs, $query['Query']['survey_id']);
				$totals[$query['Query']['id']] = $results['count']['total'];
			}
		}
		$this->set(compact('queries', 'project', 'totals'));
		
	//	$this->RequestHandler->respondAs('application/json'); 
	//	$this->response->statusCode('201');
	//	$this->layout = '';
	}
	
	public function ajax_send($survey_id) {
		if (!$this->Admins->can_access_project($this->current_user, $survey_id)) {
			return new CakeResponse(array('status' => '401'));
		}
		
		$queries = $this->Query->find('list', array(
			'recursive' => -1,
			'fields' => array('id', 'query_name'),
			'conditions' => array(
				'Query.survey_id' => $survey_id,
				'Query.parent_id' => '0'
			),
			'order' => 'query_name asc'
		));
		$this->set(compact('queries', 'survey_id'));
		
		$this->RequestHandler->respondAs('application/json'); 
		$this->response->statusCode('201');
		$this->layout = '';
	}
	
	public function ajax_view($query_id) {
		App::import('Vendor', 'SiteProfile');
		$query = $this->Query->find('first', array(
			'conditions' => array(
				'Query.id' => $query_id
			),
			'fields' => array('Query.query_string', 'Query.profile_filters', 'Query.survey_id'),
			'recursive' => -1
		)); 
		if (!$this->Admins->can_access_project($this->current_user, $query['Query']['survey_id'])) {
			return new CakeResponse(array('status' => '401'));
		}
			
		$query_string = (array) json_decode($query['Query']['query_string']);
		$profile_filters = (array) json_decode($query['Query']['profile_filters']);
		
		if (!empty($profile_filters)) {
			$profile_answers = $this->ProfileQuestion->ProfileAnswer->find('list', array(
				'fields' => array('id', 'profile_question_id'),
				'conditions' => array(
					'ProfileAnswer.id' => $profile_filters
				),
			));
			if ($profile_answers) {
				$profile_questions = $this->ProfileQuestion->find('all', array(
					'fields' => array('ProfileQuestion.name', 'Profile.name'),
					'conditions' => array(
						'ProfileQuestion.id' => $profile_answers
					),
					'order' => 'ProfileQuestion.order asc'
				));
				$this->set(compact('profile_questions', 'profile_answers'));
			}
		}
		
		$query_string = MintVine::query_string_to_readable($query_string); 
		$mappings = MintVine::query_string_mappable_values(); 
		foreach ($mappings as $key => $val) {			
			if (isset($query_string[$key])) {
				$val = unserialize($val['data']);
				$list = array();
				foreach ($query_string[$key] as $value) {
					$list[] = $value;
				}
				$query_string[$key] = $list;
			}
		}
		$this->set(compact('query_string', 'mappings'));
		$this->RequestHandler->respondAs('application/json'); 
		$this->response->statusCode('201');
		$this->layout = '';
	}
	
	public function ajax_view_qe2($query_id) {
		App::import('Vendor', 'SiteProfile');
		$query = $this->Query->find('first', array(
			'conditions' => array(
				'Query.id' => $query_id
			),
			'fields' => array('Query.query_string', 'Query.survey_id'),
			'recursive' => -1
		)); 
		if (!$this->Admins->can_access_project($this->current_user, $query['Query']['survey_id'])) {
			return new CakeResponse(array('status' => '401'));
		}
		
		$query_string = (array) json_decode($query['Query']['query_string']);
		$query_string = MintVine::query_string_to_readable_qe2($query_string); 
		$mappings = MintVine::query_string_mappable_values(); 
		$this->set(compact('query_string', 'mappings'));
		$this->RequestHandler->respondAs('application/json'); 
		$this->response->statusCode('201');
		$this->layout = '';
	}
	
	public function add($survey_id = null) {
		App::import('Vendor', 'SiteProfile');
		
		if ($this->request->is('post') || $this->request->is('put')) {
			if (!empty($survey_id) && !$this->Admins->can_access_project($this->current_user, $survey_id)) {
				$this->Session->setFlash('You are not authorized to access this project.', 'flash_error');
				$this->redirect(array('controller' => 'projects', 'action' => 'index'));
			}
			
			$query = array(
				'query_name' => $this->request->data['Query']['name'],
				'query_string' => $this->request->data['Query']['string'],
				'survey_id' => $survey_id,
				'zips_csv' => isset($this->request->data['Query']['zips_csv']) ? $this->request->data['Query']['zips_csv']: null,
			);
			if (isset($this->request->data['Query']['parent_id'])) {
				$query['parent_id'] = $this->request->data['Query']['parent_id'];
			}
			if (isset($this->request->data['Query']['profiles']) && !empty($this->request->data['Query']['profiles'])) {
				$query['profile_filters'] = $this->request->data['Query']['profiles'];
			}
			$querySource = $this->Query->getDataSource();
			$querySource->begin();
			if ($this->Query->save($query)) {
				$query_id = $this->Query->getInsertId();
				$querySource->commit();
				
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $survey_id,
					'user_id' => $this->current_user['Admin']['id'],
					'type' => 'query.created',
					'query_id' => $query_id
				)));
				
				// create the first query history
				$query = (array) json_decode($this->request->data['Query']['string']);
				$results = $this->QueryEngine->execute($query, $survey_id, 'post');
				$this->QueryStatistic->create();
				$this->QueryStatistic->save(array('QueryStatistic' => array(
					'query_id' => $query_id,
					'quota' => !empty($this->request->data['QueryStatistic']['quota']) ? $this->request->data['QueryStatistic']['quota']: null
				)));
				
				$this->Query->QueryHistory->create();
				$this->Query->QueryHistory->save(array('QueryHistory' => array(
					'query_id' => $query_id,
					'item_id' => $survey_id,
					'item_type' => TYPE_SURVEY,
					'total' => $results['count']['total'],
					'type' => 'created'
				)));
				
				$this->Session->setFlash('Your query has been created.', 'flash_success');
				if (isset($this->request->data['Query']['redirect']) && $this->request->data['Query']['redirect']) {
					$redirect = array(
						'action' => 'filter', 
						$this->request->data['Query']['parent_id']
					);
				}
				else {
					$redirect = array(
						'controller' => 'surveys',
						'action' => 'dashboard',
						$survey_id
					);
				}
				
				$this->redirect($redirect);
			}
			else {
				$querySource->commit();
			}
		}
		
		$countries = $this->GeoCountry->returnAsList();
		
		$states = $this->GeoState->find('all', array(
			'fields' => array('state_abbr', 'state', 'region', 'sub_region'),
			'conditions' => array(
				'id >' => '0'
			),
			'order' => 'GeoState.state ASC'
		));
		$state_regions = $states_list = array();
		foreach ($states as $state) {
			$states_list[$state['GeoState']['state_abbr']] = $state['GeoState']['state_abbr'].' - '.$state['GeoState']['state'];
			$state_regions[$state['GeoState']['state_abbr']] = $state['GeoState']['region'];
			
			// used for css classes
			$sub_region_list[$state['GeoState']['state_abbr']] = str_replace(' ', '_',$state['GeoState']['sub_region']);
			
			// get the sub regions for each region
			if (!empty($state['GeoState']['sub_region'])) {
				$sub_regions[$state['GeoState']['region']][] = $state['GeoState']['sub_region'];
			}
		}
		
		foreach ($sub_regions as $key => $sub_region) {
			$sub_regions[$key] = array_unique($sub_region);	            
		}
		
		$dmas = $this->GeoZip->find('list', array(
			'fields' => array('dma_code', 'dma'),
			'conditions' => array(
				'GeoZip.dma_code !=' => '',
				'GeoZip.country_code' => 'US'
			),
			'order' => 'dma asc',
			'group' => 'dma_code'
		));
		
		$regions_ca = $this->RegionMapping->find('all', array(
			'fields' => array('distinct(region)'),
			'conditions' => array(
				'RegionMapping.country_code' => 'CA'
			),
			'order' => 'RegionMapping.region asc',
		));
		$regions_gb = $this->RegionMapping->find('all', array(
			'fields' => array('distinct(region)'),
			'conditions' => array(
				'RegionMapping.country_code' => 'GB'
			),
			'order' => 'region asc',
		));
		
		$profiles = $this->Profile->find('list', array(
			'fields' => array('id', 'name'),
			'order' => 'Profile.name asc'
		));
		
		$project = $this->Project->find('first', array(
			'fields' => array('Project.id', 'Project.prj_name', 'Project.group_id', 'Project.mask', 'Project.quota'),
			'contain' => array(
				'Group' => array(
					'fields' => array('Group.key', 'Group.name')
				)
			),
			'conditions' => array(
				'Project.id' => $survey_id
			),
			'recursive' => -1
		));
		
		$this->set(compact('countries', 'state_regions', 'states_list', 'sub_regions', 'sub_region_list','dmas', 'survey_id', 'project', 'profiles', 'regions_ca', 'regions_gb'));
	}
	
	function add_from_csv($survey_id) {
		if (!$this->Admins->can_access_project($this->current_user, $survey_id)) {
			$this->Session->setFlash('You are not authorized to access this project.', 'flash_error');
			$this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}
		
		$links = $this->SurveyLink->find('all', array(
			'conditions' => array('survey_id' => $survey_id),
			'fields' => array('user_id')
		));
		
		if (!$links) {
			throw new NotFoundException(__('Survey links not found. Please add survey links by CSV file!'));
		}
		
		foreach ($links as $link) {
			$arr_user_id['user_id'][] = $link['SurveyLink']['user_id'];
		}
		
		$query = array(
			'query_name' => 'Users from CSV (generated at '.date("F j, Y, g:i a").')',
			'query_string' => json_encode($arr_user_id),
			'survey_id' => $survey_id,
		);

		$querySource = $this->Query->getDataSource();
		$querySource->begin();
		$this->Query->create();
		if ($this->Query->save($query)) {
			$query_id = $this->Query->getInsertId();
			$querySource->commit();
			
			// create the first query history
			//$query = (array) json_decode($this->request->data['Query']['string']);
			$results = $this->QueryEngine->execute($arr_user_id, $survey_id, 'post');
			$this->Query->QueryHistory->create();
			$this->Query->QueryHistory->save(array('QueryHistory' => array(
					'query_id' => $query_id,
					'item_id' => $survey_id,
					'item_type' => TYPE_SURVEY,
					'total' => $results['count']['total'],
					'type' => 'created'
					)));

			$this->Session->setFlash('Your query has been created.', 'flash_success');
			$this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $survey_id));
		}
		else {
			$querySource->commit();
		}
	}
	
	public function ajax_update_quota($query_statistic_id = null) {
		$this->QueryStatistic->bindModel(array(
			'belongsTo' => array(
				'Query' => array(
					'className' => 'Query',
					'foreignKey' => 'query_id'
				)
			)
		));
		if ($this->request->is('put') || $this->request->is('post')) {
			$query_statistic = $this->QueryStatistic->find('first', array(
				'contain' => array(
					'Query' => array('fields' => array('Query.id', 'Query.survey_id'))
				),
				'conditions' => array(
					'QueryStatistic.id' => $this->request->data['id']
				)
			));
			$redirect = array(
				'controller' => 'surveys', 
				'action' => 'dashboard', 
				$query_statistic['Query']['survey_id'], 
			);
			
			if (isset($this->request->data['QueryStatistic']['group_id']) && !empty($this->request->data['QueryStatistic']['group_id'])) {
				$redirect['?'] = array('group_id' => $this->request->data['QueryStatistic']['group_id']);
			}
			
			if (!$query_statistic) {
				$this->Session->setFlash('query statistic record not found!', 'flash_error');
				$this->redirect($redirect);
			}
			
			if (!$this->Admins->can_access_project($this->current_user, $query_statistic['Query']['survey_id'])) {
				$this->Session->setFlash('You are not authorized to access this project!', 'flash_error');
				$this->redirect($redirect);
			}
			
			$this->QueryStatistic->create();
			$this->QueryStatistic->save(array('QueryStatistic' => array(
				'id' => $query_statistic['QueryStatistic']['id'],
				'quota' => $this->request->data['quota'] == '' ? null: $this->request->data['quota']
			)), true, array('quota'));

			$this->Session->setFlash('Query quota changed successfully!', 'flash_success');
			$this->redirect($redirect);
		}
		else {
			$query_statistic = $this->QueryStatistic->find('first', array(
				'contain' => array(
					'Query' => array('fields' => array('Query.id', 'Query.survey_id'))
				),
				'conditions' => array(
					'QueryStatistic.id' => $query_statistic_id
				)
			));
			$this->set(compact('query_statistic'));
		}
		
		$this->layout = 'ajax';
	}
	
	public function ajax_get_counties($state_code) {
		App::import('Model', 'LucidZip');
		$this->LucidZip = new LucidZip;
		$lucid_zips = $this->LucidZip->find('all', array(
			'fields' => array(
				'LucidZip.state_fips', 'LucidZip.county_fips', 'LucidZip.county'
			),
			'conditions' => array(
				'LucidZip.state_abbr' => $state_code
			),
			'order' => 'LucidZip.county ASC'
		));
		
		$return_values = array();
		foreach ($lucid_zips as $lucid_zip) {
			$formatted_county = str_pad($lucid_zip['LucidZip']['state_fips'], 2, '0', STR_PAD_LEFT).str_pad($lucid_zip['LucidZip']['county_fips'], 3, '0', STR_PAD_LEFT);
			if (!isset($return_values[$formatted_county])) {
				$return_values[$formatted_county] = $lucid_zip['LucidZip']['county'];
			}
		}
    	return new CakeResponse(array(
			'body' => json_encode(array(
				'counties' => $return_values
			)), 
			'type' => 'json',
			'status' => '201'
		));
	}
}