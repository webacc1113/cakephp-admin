<?php
App::uses('AppController', 'Controller');
App::import('Vendor', 'sqs');

class SurveysController extends AppController {

	public $uses = array('Admin', 'Project', 'Group', 'SurveyReport', 'SurveyUserVisit', 'Group', 'FedSurvey', 'Role',
		'Transaction', 'User', 'ClientReport', 'SurveyComplete', 'GeoCountry', 'SurveyVisit', 'Qualification', 'QualificationUser', 'QualificationCpi',
		'Partner', 'QueryProfile', 'Client', 'SurveyUser', 'PooledPoint', 'ProjectOption', 'SocialglimpzRespondent', 'UserAddress',
		'Answer', 'AnswerText', 'Question', 'QuestionText', 'ClickTemplate', 'GeoState', 'LucidZip', 'ProjectClickDistribution');
	public $helpers = array('Html', 'Time', 'Csv');
	public $components = array('SurveyAnalysis', 'SurveyTools', 'RequestHandler', 'QueryEngine');
	
	public function beforeFilter() {
		parent::beforeFilter();
		$this->Auth->allow('viper_post');
		CakePlugin::load('Uploader');
		App::import('Vendor', 'Uploader.Uploader'); 
		
		if (in_array($this->request->params['action'], array('add', 'edit'))) {
			$models_to_load = array('Client', 'Partner', 'SurveyLink');
		}
		
		if ($this->request->params['action'] == 'dashboard') {
			$models_to_load = array('Client', 'Partner', 'Report', 'SurveyLink', 'Query', 'QueryHistory', 'ProjectLog');
		}
		if ($this->request->params['action'] == 'download_links') {
			$models_to_load = array('Partner', 'SurveyLink');
		}
		if ($this->request->params['action'] == 'ajax_survey_links') {
			$models_to_load = array('SurveyLink');
		}
		if ($this->request->params['action'] == 'prescreeners') {
			$models_to_load = array('Prescreener');
		}		
		if ($this->request->params['action'] == 'clone_project') {
			$models_to_load = array('Prescreener');
		}		
		if ($this->request->params['action'] == 'retarget') {
			$models_to_load = array('Query', 'SurveyLink', 'RecontactHash', 'HashAlias');
		}
		if ($this->request->params['action'] == 'ajax_fed_qualifications') {
			$models_to_load = array('FedQuestion');
		}
		if ($this->request->params['action'] == 'ajax_rfg_qualifications') {
			$models_to_load = array('RfgQuestion');
		}
		if ($this->request->params['action'] == 'ajax_cint_qualifications') {
			$models_to_load = array('CintQuestion', 'CintAnswer', 'CintLog', 'CintRegion');
		}
		if ($this->request->params['action'] == 'project_logs') {
			$models_to_load = array('ProjectLog');
		}
		if ($this->request->params['action'] == 'ajax_view_qualification') {
			$models_to_load = array('LucidZip');
		}
		if (isset($models_to_load) && !empty($models_to_load)) {
			foreach ($models_to_load as $model) {
				App::import('Model', $model);
				$this->$model = new $model;
			}
		}
	}
	
	public function ajax_deduper($project_id) {
		$project = $this->Project->find('first', array(
			'fields' => array('Project.dedupe', 'Project.group_id'),
			'conditions' => array(
				'Project.id' => $project_id
			),
			'contain' => array('ProjectAdmin')
		));
		if (!$this->Admins->can_access_project($this->current_user, $project)) {
			return new CakeResponse(array('status' => '401'));
		}
		
		$existing_project = $project;
		if ($this->request->is('put') || $this->request->is('post')) {
			$project['Project']['dedupe'] = !$project['Project']['dedupe'];
			
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project_id, 
				'dedupe' => $project['Project']['dedupe']
			)), true, array('dedupe'));
			
			if ($existing_project['Project']['dedupe']) {
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project_id,
					'user_id' => $this->current_user['Admin']['id'],
					'type' => 'survey.updated',
					'description' => 'Deduper updated from 1 to 0',
				)));
			}
			else {
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project_id,
					'user_id' => $this->current_user['Admin']['id'],
					'type' => 'survey.updated',
					'description' => 'Deduper updated from 0 to 1',
				)));
			}
		}
		
    	return new CakeResponse(array(
			'body' => json_encode(array(
				'dedupe' => $project['Project']['dedupe'],
			)), 
			'type' => 'json',
			'status' => '201'
		));
	}
	
	public function ajax_status($project_id = null) {
		if ($this->request->is('put') || $this->request->is('post')) {
			$data = $this->request->data;
			if (empty($data['id'])) {
    			return new CakeResponse(array(
					'body' => json_encode(''), 
					'type' => 'json',
					'status' => '400'
				));
			}
			$existing_project = $project = $this->Project->find('first', array(
				'conditions' => array(
					'Project.id' => $data['id']
				),
				'fields' => array('id', 'ended', 'status', 'group_id'),
				'contain' => array('ProjectAdmin')
			));
			if (!$this->Admins->can_access_project($this->current_user, $existing_project)) {
				return new CakeResponse(array('status' => '401'));
			}
			
			if ($project) {
				if ($this->data['status'] == PROJECT_STATUS_CLOSED || $this->data['status'] == PROJECT_STATUS_INVOICED) {
					if (empty($project['Project']['ended'])) {
						$this->Project->create();
						$this->Project->save(array('Project' => array(
							'id' => $project['Project']['id'],
							'active' => false,
							'ended' => date(DB_DATETIME)
						)), true, array('active', 'ended'));
					}
					else {
						$this->Project->create();
						$this->Project->save(array('Project' => array(
							'id' => $project['Project']['id'],
							'active' => false
						)), true, array('active'));
					}
					
					Utils::save_margin($project['Project']['id']);
				}
				elseif ($this->data['status'] == PROJECT_STATUS_OPEN) {
					$this->Project->create();
					$this->Project->save(array('Project' => array(
						'id' => $project['Project']['id'],
						'ended' => null,
						'active' => true
					)), true, array('active', 'ended'));
				}
			}
			
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $data['id'], 
				'status' => $this->data['status']
			)), true, array('status'));
			
			if ($this->data['status'] == PROJECT_STATUS_OPEN) {
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $data['id'],
					'user_id' => $this->current_user['Admin']['id'],
					'type' => 'status.opened',
					'description' => 'Status updated from "' . $existing_project['Project']['status'] . '" to "' . $this->data['status'] . '"',
				)));
			}
			elseif ($this->data['status'] == PROJECT_STATUS_CLOSED) {
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $data['id'],
					'user_id' => $this->current_user['Admin']['id'],
					'type' => 'status.closed',
					'description' => 'Status updated from "' . $existing_project['Project']['status'] . '" to "' . $this->data['status'] . '"',
				)));
				
			}
			elseif ($this->data['status'] == PROJECT_STATUS_INVOICED) {
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $data['id'],
					'user_id' => $this->current_user['Admin']['id'],
					'type' => 'status.invoiced',
					'description' => 'Status updated from "' . $existing_project['Project']['status'] . '" to "' . $this->data['status'] . '"',
				)));
			}
			
			$statuses = unserialize(PROJECT_STATUSES);
			
    		return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => $this->data['status'],
					'selector' => '#status-link-'.$data['id'],
					'text' => $statuses[$this->data['status']]
				)), 
				'type' => 'json',
				'status' => '201'
			));
		}
		$this->data = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $project_id
			),
			'recursive' => -1
		));
		$this->RequestHandler->respondAs('application/json'); 
		$this->response->statusCode('200');
		$this->layout = '';
	}
	
	public function ajax_delete_partner($project_id, $partner_id) {
		if (!$this->Admins->can_access_project($this->current_user, $project_id)) {
			return new CakeResponse(array('status' => '401'));
		}
		
		$survey_partner = $this->Project->SurveyPartner->find('first', array(
			'conditions' => array(
				'SurveyPartner.survey_id' => $project_id,
				'SurveyPartner.partner_id' => $partner_id
			),
			'recursive' => -1
		));
		$success = $this->Project->SurveyPartner->delete($survey_partner['SurveyPartner']['id']);
		$this->ProjectLog->create();
		$this->ProjectLog->save(array('ProjectLog' => array(
			'project_id' => $project_id,
			'user_id' => $this->current_user['Admin']['id'],
			'type' => 'survey_partner.deleted',
			'description' => 'Survey Partner ID: ' . $partner_id,
		)));
		
		
    	return new CakeResponse(array(
			'body' => json_encode(array(
				'status' => $success
			)), 
			'type' => 'json',
			'status' => '201'
		));
	}
	
	public function ajax_save_partner() {
		// add 
		$errors = array();
		$save = false;
		if ($this->request->is('post') || $this->request->is('put')) {
			if (!$this->Admins->can_access_project($this->current_user, $this->request->data['SurveyPartner']['survey_id'])) {
				return new CakeResponse(array('status' => '401'));
			}
			
			$this->Project->SurveyPartner->create();
			$save = $this->Project->SurveyPartner->save($this->request->data);
			if (!$save) {
				$validation_errors = $this->Project->SurveyPartner->validationErrors;
				if (!empty($validation_errors)) {
					foreach ($validation_errors as $error) {
						$errors[] = current($error);
					}
				}
			}
			else {
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $this->request->data['SurveyPartner']['survey_id'],
					'user_id' => $this->current_user['Admin']['id'],
					'type' => 'survey_partner.created'
				)));
			}
		}
    	return new CakeResponse(array(
			'body' => json_encode(array(
				'errors' => implode(' ', $errors)
			)), 
			'type' => 'json',
			'status' => isset($save) && $save ? '201': '400'
		));
	}	
	
	public function partner_edit($project_id, $partner_id) {
		if (!$this->Admins->can_access_project($this->current_user, $project_id)) {
			$this->Session->setFlash('You are not authorized to access this feature.', 'flash_error');
			$this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $project_id));
		}
		
		$survey_partner = $this->Project->SurveyPartner->find('first', array(
			'conditions' => array(
				'SurveyPartner.survey_id' => $project_id,
				'SurveyPartner.partner_id' => $partner_id
			)
		));
		if (!$survey_partner) {
			$this->Session->setFlash('That survey partner could not be found.', 'flash_error');
			$this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $project_id));
		}
		
		if ($this->request->is('post') || $this->request->is('put')) {
			$keys = array_keys($this->request->data['SurveyPartner']);
			$this->request->data['SurveyPartner']['id'] = $survey_partner['SurveyPartner']['id'];
			$this->Project->SurveyPartner->create();
			if ($this->Project->SurveyPartner->save($this->request->data, true, $keys)) {
				unset($this->request->data['SurveyPartner']['partner_link']);
				$partner_logs = Utils::get_field_diffs($this->request->data['SurveyPartner'], $survey_partner['SurveyPartner']);
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project_id,
					'user_id' => $this->current_user['Admin']['id'],
					'type' => 'survey_partner.updated',
					'description' => 'Survey Partner updated: ' . implode(', ', $partner_logs),
				)));
				
				$this->Session->setFlash('Partner edited.', 'flash_success');
				$this->redirect(array('action' => 'dashboard', $project_id));
			}
		}
		else {
			$this->data = $survey_partner;
		}
				
		$partner_list = $this->Partner->find('list', array(
			'fields' => array('id', 'partner_name'),
			'order' => 'Partner.partner_name ASC',
			'conditions' => array(
				'Partner.deleted' => false
			)
		));
		
		$project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $project_id
			),
			'recursive' => -1
		));
		
		$this->set(compact('survey_partner', 'project', 'partner_list'));
	}
	
	public function reset($project_id) {
		$project = $this->Project->findById($project_id);
		if (!$project) {
			$this->redirect(array('action' => 'index'));
		}
		
		if (!$this->Admins->can_access_project($this->current_user, $project)) {
			$this->Session->setFlash('You are not authorized to access that project.', 'flash_error');
			$this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}
		
		if ($this->request->is('put') || $this->request->is('post')) {
			$this->SurveyTools->reset($project_id);
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project_id,
				'user_id' => $this->current_user['Admin']['id'],
				'type' => 'project.reset'
			)));
			$this->Session->setFlash('Your data has been reset for this project.', 'flash_success');
			$this->redirect(array('action' => 'dashboard', $project_id));
		}
		$this->set(compact('project'));
	}
	
	public function refresh_statistics($project_id) {
		$project = $this->Project->findById($project_id);
		if (!$project) {
			$this->redirect(array('action' => 'index'));
		}
		
		if (!$this->Admins->can_access_project($this->current_user, $project)) {
			$this->Session->setFlash('You are not authorized to access that project.', 'flash_error');
			$this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}
		
		if ($this->SurveyTools->refresh_statistics($project_id)) {
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project_id,
				'user_id' => $this->current_user['Admin']['id'],
				'type' => 'survey.updated',
				'description' => 'Statistics refreshed'
			)));
			$this->Session->setFlash('Statistics for this project has been refreshed', 'flash_success');
		}
		else {
			$this->Session->setFlash('Statistics not refreshed. SurveyVisitCache record may not exist.', 'flash_success');
		}
		
		$this->redirect(array('action' => 'dashboard', $project_id));
	}
	
	public function survey_dupes($project_id) {		
		$this->loadModel('SystemDupeLog');
		$this->loadModel('FpIp');
				
		if (isset($this->request->query['id'])) {
			$system_dupe_log = $this->SystemDupeLog->find('first', array(
				'conditions' => array(
					'SystemDupeLog.id' => $this->request->query['id']
				)
			));
			list($first_octet, $second_octet, $third_octet, $fourth_octet) = explode('.', $system_dupe_log['SystemDupeLog']['ip_address']);
		
			// first check for ip sensitivity
			$conditions = array(
				'survey_id' => $project_id
			);
			
			if ($system_dupe_log['SystemDupeLog']['created'] < '2017-05-04 00:00:00') {
				if ($system_dupe_log['SystemDupeLog']['ip_sensitivity'] == 2) {
					$conditions['ip LIKE'] = $first_octet.'.'.$second_octet.'%';	
				}
				elseif ($system_dupe_log['SystemDupeLog']['ip_sensitivity'] == 3) {
					$conditions['ip LIKE'] = $first_octet.'.'.$second_octet.'.'.$third_octet.'%';	
				}
				else {
					$conditions['ip'] = $system_dupe_log['SystemDupeLog']['ip_address'];
				}
			}
			else {
				if ($system_dupe_log['SystemDupeLog']['ip_sensitivity'] == 2) {
					$conditions['ip LIKE'] = $first_octet.'.'.$second_octet.'.%';	
				}
				elseif ($system_dupe_log['SystemDupeLog']['ip_sensitivity'] == 3) {
					$conditions['ip LIKE'] = $first_octet.'.'.$second_octet.'.'.$third_octet.'.%';	
				}
				else {
					$conditions['ip'] = $system_dupe_log['SystemDupeLog']['ip_address'];
				}
			}
			$fp_ips = $this->FpIp->find('all', array(
				'conditions' => $conditions,
				'order' => 'FpIp.id ASC'
			));
			
			// figure out where to inject the sytem dupe record
			if ($fp_ips) {
				$listing_records = array();
				foreach ($fp_ips as $fp_ip) {
					$listing_records[$fp_ip['FpIp']['id']] = $fp_ip['FpIp']['created']; 
				} 
				arsort($listing_records);
				$matched_id = false;
				foreach ($listing_records as $fp_ip => $timestamp) {
					if ($timestamp < $system_dupe_log['SystemDupeLog']['created']) { 
						$matched_id = $fp_ip; 
						break;
					}
				}
			}
			$this->set(compact('fp_ips', 'system_dupe_log', 'matched_id'));
			$this->render('survey_dupe_single');
		}
		else {		
			$system_dupe_logs = $this->SystemDupeLog->find('all', array(
				'recursive' => -1,
				'conditions' => array(
					'SystemDupeLog.project_id' => $project_id
				),
				'order' => 'SystemDupeLog.id DESC'
			));
		}
		$this->set(compact('project_id', 'system_dupe_logs')); 
	}
	
	public function dashboard($project_id = null) {
		if (empty($project_id)) {
			$this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}
		$project_id = MintVine::parse_project_id($project_id);
		$this->Project->bindInvoices();		
		$this->Project->bindRates();		
		$this->Project->bindFedSurvey();
		$this->Project->bindCintSurvey();
		$this->Project->bindRfgSurvey();
		$this->Project->bindProjectLog();
		$this->Project->bindSpectrumProject();
		$this->Project->bindProjectIr();
		$project = $this->Project->find('first', array(
			'fields' => array('*'),
			'conditions' => array(
				'Project.id' => $project_id
			),
			'contain' => array(
				'Client',
				'Group', 
				'SurveyVisitCache',
				'Invoice',
				'SurveyPartner' => array('Partner'),
				'HistoricalRates',
				'FedSurvey',
				'CintSurvey',
				'RfgSurvey',
				'SpectrumProject',
				'ProjectOption' => array(
					'conditions' => array(
						'name' => array('pushed', 'pushed_email_subject', 'pushed_email_template', 'cint_required_capabilities', 'sqs_url', 'links.count', 'links.unused')
					)
				),
				'ProjectAdmin',
				'ProjectIr'
			)
		));
		if (!$project) {
			$this->Session->setFlash('Project not found.', 'flash_error');
			$this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}
		
		if ($project['Group']['key'] == 'fulcrum' && !empty($project['FedSurvey']['fed_survey_id'])) {
			$settings = $this->Setting->find('list', array(
				'fields' => array('Setting.name', 'Setting.value'),
				'conditions' => array(
					'Setting.name' => array('lucid.host', 'lucid.api.key', 'lucid.supplier.code'),
					'Setting.deleted' => false
				)
			));
			$api_key = array(
				'key' => $settings['lucid.api.key']
			);
			$HttpSocket = new HttpSocket(array(
				'timeout' => 2,
				'ssl_verify_host' => false
			));
			try {
				$response = $HttpSocket->get($settings['lucid.host'] . 'Supply/v1/' . 'SurveyStatistics/BySurveyNumber/' . $project['FedSurvey']['fed_survey_id'] . '/'.  $settings['lucid.supplier.code'] .'/Global/Trailing', $api_key);
			}
			catch (Exception $e) {
			}
			
			if (isset($response) && $response->code == '200') {
				$lucid_survey_statistics = json_decode($response['body'], true);
				$this->loadModel('LucidEpcStatistic');
				$lucid_epc_statistics = $this->LucidEpcStatistic->find('all', array(
					'fields' => array('LucidEpcStatistic.trailing_epc_cents', 'LucidEpcStatistic.created'),
					'conditions' => array(
						'LucidEpcStatistic.project_id' => $project_id
					),
					'recursive' => -1,
					'order' => 'LucidEpcStatistic.id DESC'
				));
				$this->set(compact('lucid_survey_statistics', 'lucid_epc_statistics'));
			}
		}

		if (!$this->Admins->can_access_project($this->current_user, $project)) {
			$this->Session->setFlash('You are not authorized to access that project.', 'flash_error');
			$this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}

		if ($project['Group']['key'] == 'mintvine') {
			$click_templates = $this->ClickTemplate->find('all', array(
				'order' => 'ClickTemplate.id ASC'
			));
			$this->set(compact('click_templates'));
		}
		
		if ($project['Project']['temp_qualifications'] || ($project['Group']['key'] == 'mintvine' && empty($queries)) || $project['Group']['key'] == 'points2shop') {
			$this->Qualification->bindModel(array(
				'hasOne' => array('QualificationStatistic')
			));
			$qualifications = $this->Qualification->find('all', array(
				'conditions' => array(
					'Qualification.project_id' => $project['Project']['id'],
					'Qualification.parent_id' => null,
					'Qualification.deleted is null'
				),
				'order' => 'Qualification.id ASC'
			));
			$child_qualifications = array();
			if ($qualifications) {
				foreach ($qualifications as $qualification) {
					$this->Qualification->bindModel(array(
						'hasOne' => array('QualificationStatistic')
					));
					$child_qualification = $this->Qualification->find('all', array(
						'conditions' => array(
							'Qualification.project_id' => $project['Project']['id'],
							'Qualification.parent_id' => $qualification['Qualification']['id'],
							'Qualification.deleted is null'
						),
						'order' => 'Qualification.id ASC'
					)); 
					if ($child_qualification) {
						$child_qualifications[$qualification['Qualification']['id']] = $child_qualification;
					}
				}
			}
			$survey_user_count = $this->SurveyUser->find('count', array(
				'conditions' => array(
					'SurveyUser.survey_id' => $project['Project']['id']
				),
				'recursive' => -1
			));
			$this->set(compact('qualifications', 'child_qualifications', 'survey_user_count'));
		}

		if ($project['Group']['key'] == 'mintvine') {
			$questions = array();
			$geo = array();
			$questions['hhi'] = $this->getQuestion('STANDARD_HHI_US_v2');
			$questions['ethnicity'] = $this->getQuestion('ETHNICITY');
			$questions['hispanic'] = $this->getQuestion('HISPANIC');

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
				$state_regions[] = $state['GeoState']['region'];
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
			$geo['region'] = array_keys(array_flip($state_regions));
			$geo['state'] = $states_list;
			$project_click_distributions = $this->ProjectClickDistribution->find('all', array(
				'conditions' => array(
					'ProjectClickDistribution.project_id' => $project_id,
					'ProjectClickDistribution.deleted is null'
				),
				'order' => 'ProjectClickDistribution.created DESC'
			));
			$this->set(compact('project_click_distributions', 'questions', 'geo'));
		}

		$project_logs = $this->ProjectLog->find('all', array(
			'conditions' => array(
				'ProjectLog.project_id' => $project_id,
				'ProjectLog.type like' => '%status%'
			),
			'fields' => array('type'),
			'order' => 'ProjectLog.id ASC',
		));
		
		if (!empty($project['Project']['client_project_id'])) {
			if ($project['Client']['key'] == 'fulcrum') {
				$fed_survey = $this->FedSurvey->find('first', array(
					'conditions' => array(
						'FedSurvey.fed_survey_id' => $project['Project']['client_project_id'],
						'FedSurvey.survey_id <>' => $project['Project']['id']
					)
				));
				if ($fed_survey && $fed_survey['FedSurvey']['survey_id'] > 0) {
					$this->set('duplicate_fulcrum_found', $fed_survey);
				}
			}
		}

		$sampled_to_live = false;
		if (count($project_logs) > 1) {
			for ($i = 0; $i < count($project_logs) - 1; $i++) {
				$current_log = $project_logs[$i];
				$next_log = $project_logs[$i + 1];
				if ($current_log['ProjectLog']['type'] == 'status.sample' && $next_log['ProjectLog']['type'] == 'status.opened') {
					$sampled_to_live = true;
					break;
				}
			}
		}
		$this->set('sampled_to_live', $sampled_to_live);
				
		if (!empty($project['ProjectOption'])) {
			foreach ($project['ProjectOption'] as $key => $project_option) {
				$project['ProjectOption'][$project_option['name']] = $project_option['value'];
				unset($project['ProjectOption'][$key]);
			}
		}
		if (isset($project['ProjectOption']['sqs_url'])) {
			App::import('Vendor', 'sqs');
			$settings = $this->Setting->find('list', array(
				'fields' => array('Setting.name', 'Setting.value'),
				'conditions' => array(
					'Setting.name' => array('sqs.access.key', 'sqs.access.secret'),
					'Setting.deleted' => false
				)
			));
			if (count($settings) >= 2) {
				$sqs = new SQS($settings['sqs.access.key'], $settings['sqs.access.secret']);
				$results = $sqs->getQueueAttributes($project['ProjectOption']['sqs_url']);
				$this->set('sqs_number', $results['Attributes']['ApproximateNumberOfMessages']);
			}
		}

		$reports = $this->Report->find('all', array(
			'conditions' => array(
				'Report.type' => 'report',
				'Report.survey_id' => $project_id
			),
			'order' => 'Report.modified DESC'
		));
		$partner_list = $this->Partner->find('list', array(
			'fields' => array('id', 'partner_name'),
			'order' => 'Partner.partner_name ASC',
			'conditions' => array(
				'Partner.deleted' => false
			)
		));
		
		if (in_array($project['Project']['status'], array(PROJECT_STATUS_CLOSED, PROJECT_STATUS_INVOICED))) {
			$client_reports = $this->ClientReport->find('all', array(
				'conditions' => array(
					'ClientReport.survey_id' => $project['Project']['id']
				)
			));
		}
		
		$this->Query->bindModel(array('hasOne' => array('QueryStatistic')));
		$queries = $this->Query->find('all', array(
			'conditions' => array(
				'Query.survey_id' => $project['Project']['id']
			),
			'order' => 'Query.id ASC'
		));
		if ($queries) {
			$children_queries = array();
			foreach ($queries as $id => $query) {
				if (empty($query['Query']['parent_id'])) {
					continue;
				}
				$children_queries[$query['Query']['parent_id']][] = $query;
				unset($queries[$id]);
			}
			$queries_list = array();
			foreach ($queries as $query) {
				$queries_list[] = $query;
				if (isset($children_queries[$query['Query']['id']])) {
					foreach ($children_queries[$query['Query']['id']] as $query) {
						$queries_list[] = $query;
					}
				}
			}
			$queries = $queries_list;
		
			// compress query history data
			foreach ($queries as $id => $query) {
				$total = $sent = $last_sent = null;
				$active = true; // activity used to be stored on histories; let's bubble it up to queries now
 				if (!empty($query['QueryHistory'])) {
					$total = 0;
					foreach ($query['QueryHistory'] as $query_history) {
						if ($query_history['type'] == 'created') {
							$total = $query_history['total']; 
						}
						elseif ($query_history['type'] == 'sent') {
							if (is_null($sent)) {
								$sent = 0;
							}
							$sent = $sent + $query_history['count']; 
							if ($active) {
								$active = $query_history['active'];
							}
							$last_sent = $query_history['created'];
						}
					}
				}
				$queries[$id]['Query']['active'] = $active;
				$queries[$id]['Query']['total'] = $total;
				$queries[$id]['Query']['sent'] = $sent;
				$queries[$id]['Query']['last_sent'] = $last_sent;
			}
		}
 		$this->set(compact('client_reports', 'project', 'reports', 'partner_list', 'queries'));
		
		$total_count = $this->SurveyUser->find('count', array(
			'recursive' => -1,
			'conditions' => array(
				'SurveyUser.survey_id' => $project['Project']['id']
			)
		));
				
		$group = $this->Group->find('first', array(
			'fields' => array('id'), 
			'conditions' => array(
				'Group.key' => 'socialglimpz'
			)
		));
		
		if ($group && ($project['Project']['group_id'] == $group['Group']['id'])) {
			$socialglimpz_rejects = $this->SocialglimpzRespondent->find('count', array(
				'conditions' => array(
					'SocialglimpzRespondent.survey_id' => $project['Project']['id'],
					'SocialglimpzRespondent.status' => 'rejected'
				)
			));
			$this->set('socialglimpz_rejects', $socialglimpz_rejects);
		}

		$this->set(compact('total_count'));
		if (isset($project['Client']) && !empty($project['Client'])) {
			$title_for_layout = sprintf('Survey #%d - %s', $project['Project']['id'], $project['Client']['client_name']);
		}
		else {
			$title_for_layout = sprintf('Survey #%d', $project['Project']['id']);
		}
		$this->set(compact('title_for_layout'));
	}
	
	public function index() {
		return $this->redirect(array('controller' => 'projects', 'action' => 'index'));
	}
	
	public function convert_respondent_ids($project_id) {
		if (!$this->Admins->can_access_project($this->current_user, $project_id)) {
			$this->Session->setFlash('You are not authorized to access that project.', 'flash_error');
			$this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}
		
		if ($this->request->is('post') || $this->request->is('put')) {
			if (!isset($this->data['Project']['file']) 
				|| $this->data['Project']['file']['error'] != 0 
				|| $this->data['Project']['file']['type'] != 'text/csv') {
				$this->Session->setFlash('Please upload a valid CSV file.', 'flash_error'); 
			}
			else {
				$file = file_get_contents($this->data['Project']['file']['tmp_name']);
				$respondent_ids = explode("\n", $file);
				$respondent_ids = array_map('trim', $respondent_ids);
				foreach ($respondent_ids as $key => $row) {
					$rows = str_getcsv($row);
					$respondent_ids[$key] = $rows[0];
				}
				$csvs = array();
				foreach ($respondent_ids as $respondent_id) {
					if (empty($respondent_id)) {
						continue;
					}
					$row = $this->SurveyReport->findBySurveyIdAndPartnerUserId($project_id, $respondent_id);
					if (!$row) {
						$row = $this->SurveyReport->findBySurveyIdAndHash($project_id, $respondent_id);
					}
					if ($row) {
						$survey_user = $this->SurveyUserVisit->findBySurveyIdAndIp($project_id, $row['SurveyReport']['ip']);
						if ($survey_user) {
							$csvs[] = array(
								$respondent_id, 
								$survey_user['SurveyUserVisit']['user_id']
							); 
						}
						else {
							$csvs[] = array($respondent_id);
						}
					}
				}
				
				$csv_file = fopen('php://output', 'w');

				header('Content-type: application/csv');
				header('Content-Disposition: attachment; filename="converted.csv"');

				// Each iteration of this while loop will be a row in your .csv file where each field corresponds to the heading of the column
				foreach ($csvs as $csv) {
					fputcsv($csv_file, $csv, ',', '"');
				}

				fclose($csv_file);
				$this->autoRender = false;
				$this->layout = false;
				$this->render(false);
			}
		}
	}
		
	public function analysis($project_id) {
		$project = $this->Project->findById($project_id);
		if (!$this->Admins->can_access_project($this->current_user, $project)) {
			$this->Session->setFlash('You are not authorized to access that project.', 'flash_error');
			$this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}
		
		if ($this->request->is('post')) {
			
			$this->SurveyAnalysis->link_users($project_id); // associate mintvine users 
			$visits = $this->SurveyReport->find('all', array(
				'conditions' => array(
					'result' => SURVEY_COMPLETED, 
					'survey_id' => $project_id
				), 
				'order' => array(
					'SurveyReport.started DESC'
				)
			));
			
			if (empty($visits)) {
				$this->Session->setFlash('You must generate a report in order to generate analytics on it. If you have already generated a report, then this report had no MintVine users to analyze.', 'flash_error'); 
				$this->redirect(array('action' => 'analysis', $project_id));
			}
			$visits = $this->SurveyAnalysis->check_speed($project, $visits);
			$this->set(compact('visits'));			
		}
	}
		
	public function add($group_id = null) {
		if (empty($group_id)) {
			$this->Session->setFlash('Project must be created with a group', 'flash_error');
			return $this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}
		$project_staff = $this->Role->get_administrators(array('project_managers', 'account_managers', 'sales_managers'));
		
		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.id' => $group_id
			)
		));
		if (!$group) {
			$this->Session->setFlash('Project must be created with a group', 'flash_error');
			return $this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}
		if (!$this->current_user['AdminRole']['admin'] && !in_array($group['Group']['id'], $this->current_user['AdminGroup'])) {
			$this->Session->setFlash('You do not have permissions to access this group to create a project.', 'flash_error');
			return $this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}
		
		if ($this->request->is('post') || $this->request->is('put')) {				
			$admin_ids = array();
			if (isset($this->request->data['ProjectAdmin']['pm_id']) && !empty($this->request->data['ProjectAdmin']['pm_id'])) {
				$admin_ids = array_merge($admin_ids, $this->request->data['ProjectAdmin']['pm_id']); 
			}
			if (isset($this->request->data['ProjectAdmin']['am_id']) && !empty($this->request->data['ProjectAdmin']['am_id'])) {
				$admin_ids = array_merge($admin_ids, $this->request->data['ProjectAdmin']['am_id']); 
			}
			$this->request->data['ProjectAdmin']['id'] = $admin_ids;			
			$interview_date_error = false;
			if (!empty($this->request->data['ProjectOption']['interview_date'])) {
				$interview_date = $this->request->data['ProjectOption']['interview_date'];				
				$interview_date = $interview_date['year'] . '-' . $interview_date['month'] . '-' . $interview_date['day'] . '
				' . $interview_date['hour'] . ':' . $interview_date['min'] . ' ' . $interview_date['meridian'];				
				$interview_date = date('Y-m-d H:i:s', strtotime($interview_date));
				$user_timezone = new DateTimeZone($this->current_user['Admin']['timezone']);
				$date = new DateTime($interview_date, $user_timezone);	
				$gmt = new DateTimeZone('UTC');	
				$date->setTimezone($gmt);
				$interview_date = $date->format('Y-m-d H:i:s');
				$this->request->data['ProjectOption']['interview_date'] = $interview_date;					
			}
			
			$save = false;
			$project = array(
				'Project' => array(
					'group_id' => $group['Group']['id'],
					'prj_name' => $this->request->data['Project']['prj_name'],
					'client_id' => $this->request->data['Project']['client_id'],
					'date_created' => date(DB_DATETIME),
					'client_rate' => $this->request->data['Project']['client_rate'],
					'partner_rate' => $this->request->data['Project']['partner_rate'],
					'user_payout' => $this->request->data['Project']['award'] / 100,
					'nq_award' => $this->request->data['Project']['nq_award'],	
					'quota' => $this->request->data['Project']['quota'],
					'router' => $this->request->data['Project']['router'],
					'singleuse' => $this->request->data['Project']['singleuse'],
					'landerable' => $this->request->data['Project']['landerable'],
					'bid_ir' => $this->request->data['Project']['bid_ir'],
					'priority' => $this->request->data['Project']['priority'],
	 				'est_length' => $this->request->data['Project']['est_length'],
	 				'prj_description' => $this->request->data['Project']['prj_description'],
	 				'recontact_id' => $this->request->data['Project']['recontact_id'],
					'status' => PROJECT_STATUS_OPEN,
					'country' => $this->request->data['Project']['country'],
					'language' => !empty($this->request->data['Project']['language']) ? $this->request->data['Project']['language']: null,
					'survey_name' => $this->request->data['Project']['survey_name'],
					'description' => $this->request->data['Project']['description'],
					'minimum_time' => $this->request->data['Project']['minimum_time'],
					'award' => $this->request->data['Project']['award'],
					'pool' => !empty($this->request->data['Project']['pool']) ? $this->request->data['Project']['pool']: '0',
					'active' => false,
					'dedupe' => isset($this->request->data['Project']['dedupe']) && $this->request->data['Project']['dedupe'] == 1,
					'client_survey_link' => $this->request->data['Project']['client_survey_link'],
					'client_end_action' => $this->request->data['Project']['client_end_action'],
					'public' => isset($this->request->data['Project']['public']) && $this->request->data['Project']['public'] == 1,				
					'prescreen' => isset($this->request->data['Project']['prescreen']) && $this->request->data['Project']['prescreen'] == 1,		
					'skip_mv_prescreen' => isset($this->request->data['Project']['skip_mv_prescreen']) && $this->request->data['Project']['skip_mv_prescreen'] == 1,		
					'desktop' => $this->request->data['Project']['desktop'],
					'mobile' => $this->request->data['Project']['mobile'],
					'tablet' => $this->request->data['Project']['tablet'],
					'address_required' => $this->request->data['Project']['address_required'],
					'ip_sensitivity' => $this->request->data['Project']['ip_sensitivity'],
					'ip_dupes' => $this->request->data['Project']['ip_dupes']
				), 
				'ProjectAdmin' => $this->request->data['ProjectAdmin']
			);
			
			$client = $this->Client->find('first', array(
				'conditions' => array(
					'Client.id' => $this->request->data['Project']['client_id'],
					'Client.deleted' => false
				)
			));
			
			$projectSource = $this->Project->getDataSource();
			$projectSource->begin();
			$this->Project->create();
			$save = $this->Project->save($project);
			if ($save) {
				$project_id = $this->Project->getInsertId();
				$project = $this->Project->findById($project_id);
				$projectSource->commit();
				
				// add fed survey for fulcrum projects to block automation
				if (!empty($project['Project']['client_project_id']) && $client && $client['Client']['key'] == 'fulcrum') {
					$this->FedSurvey->create();
					$this->FedSurvey->save(array('FedSurvey' => array(
						'survey_id' => $project_id,
						'fed_survey_id' => $project['Project']['client_project_id'],
						'status' => 'skipped.adhoc'
					)));
				}
				
				// set up admins
				$admin_ids = array();
				if (isset($this->request->data['ProjectAdmin']['pm_id']) && !empty($this->request->data['ProjectAdmin']['pm_id'])) {
					$admin_ids = array_merge($admin_ids, $this->request->data['ProjectAdmin']['pm_id']); 
				}
				if (isset($this->request->data['ProjectAdmin']['am_id']) && !empty($this->request->data['ProjectAdmin']['am_id'])) {
					$admin_ids = array_merge($admin_ids, $this->request->data['ProjectAdmin']['am_id']); 
				}
				if (!empty($admin_ids)) {
					foreach ($admin_ids as $admin_id) {
						$this->Project->ProjectAdmin->create();
						$this->Project->ProjectAdmin->save(array('ProjectAdmin' => array(
							'project_id' => $project_id,
							'admin_id' => $admin_id,
							'is_pm' => isset($this->request->data['ProjectAdmin']['pm_id']) && is_array($this->request->data['ProjectAdmin']['pm_id']) && in_array($admin_id, $this->request->data['ProjectAdmin']['pm_id']),
							'is_am' => isset($this->request->data['ProjectAdmin']['am_id']) && is_array($this->request->data['ProjectAdmin']['am_id']) && in_array($admin_id, $this->request->data['ProjectAdmin']['am_id']),
						)));
					}
				}
					
				// add default partners
				$partner = $this->Partner->find('first', array(
					'conditions' => array(
						'Partner.key' => array('mintvine'),
						'Partner.deleted' => false
					),
					'fields' => array('Partner.id', 'Partner.key')
				));					
				$complete_url = HOSTNAME_WWW.'/surveys/complete/{{ID}}/'.($this->request->data['Project']['client_end_action'] == 's2s' ? '?s2s=1': '');
				$nq_url = HOSTNAME_WWW.'/surveys/nq/{{ID}}/'.($this->request->data['Project']['client_end_action'] == 's2s' ? '?s2s=1': '');
				$oq_url = HOSTNAME_WWW.'/surveys/oq/{{ID}}/'.($this->request->data['Project']['client_end_action'] == 's2s' ? '?s2s=1': '');
				$pause_url = HOSTNAME_WWW.'/surveys/paused/'.($this->request->data['Project']['client_end_action'] == 's2s' ? '?s2s=1': '');
				$fail_url = HOSTNAME_WWW.'/surveys/sec/{{ID}}/'.($this->request->data['Project']['client_end_action'] == 's2s' ? '?s2s=1': '');
				
				$this->Project->SurveyPartner->create();
				$this->Project->SurveyPartner->save(array('SurveyPartner' => array(
					'survey_id' => $project_id,
					'partner_id' => $partner['Partner']['id'],
					'rate' => round($this->request->data['Project']['award'] / 100, 2),
					'complete_url' => $complete_url,
					'nq_url' => $nq_url,
					'oq_url' => $oq_url,
					'pause_url' => $pause_url,
					'fail_url' => $fail_url,
				)));
				$save = true;
				// process survey options
				if (!empty($this->data['ProjectOption'])) {
					foreach ($this->data['ProjectOption'] as $key => $value) {
						$value = trim($value);
						if (empty($value) && $key != 'is_chat_interview') {
							continue;
						}
						$this->Project->ProjectOption->create();
						$this->Project->ProjectOption->save(array('ProjectOption' => array(
							'project_id' => $project_id,
							'name' => $key,
							'value' => $value
						)));
					}
				}
				
				// process survey links
				if (!empty($this->data['Project']['client_links']) && !empty($this->data['Project']['client_links']['tmp_name'])) {
					$links = $this->SurveyTools->process_links($this->data['Project']['client_links']['tmp_name'], $project_id);
					$link_count = !empty($links['links']) ? count($links['links']) : 0; 
				
					$this->Project->ProjectOption->create();
					$this->Project->ProjectOption->save(array('ProjectOption' => array(
						'project_id' => $project_id,
						'name' => 'links.count',
						'value' => $link_count
					)));
					
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $project_id,
						'type' => 'links.uploaded',
						'user_id' => $this->current_user['Admin']['id'],
						'description' => 'Uploaded ' . $this->data['Project']['client_links']['name'] . ' with ' . $link_count . ' links from ' . $links['rows']
					)));
					
					$this->Project->ProjectOption->create();
					$this->Project->ProjectOption->save(array('ProjectOption' => array(
						'project_id' => $project_id,
						'name' => 'links.unused',
						'value' => $link_count
					)));
					
					if (!empty($links['links'])) {
						$this->Project->save(array('Project' => array(
							'id' => $project_id,
							'has_links' => true
						)), true, array('has_links'));
					}
					
					$query = ROOT . '/app/Console/cake survey_links sync_to_sqs ' . $project_id;
					CakeLog::write('query_commands', $query);
					// run these synchronously
					exec($query.'  &> /dev/null &'); 
				}
				
				// for typeform surveys, save the file and update the client_url
				if (isset($this->request->data['Project']['typeform_html']) && empty($this->request->data['Project']['typeform_html']['error'])) {
					if (move_uploaded_file($this->request->data['Project']['typeform_html']['tmp_name'], TYPEFORM_UPLOAD.'/'.$this->request->data['Project']['typeform_html']['name'])) {
						$this->Project->create();
						$this->Project->save(array('Project' => array(
							'id' => $project_id,
							'client_survey_link' => TYPEFORM_URL.'/'.$this->request->data['Project']['typeform_html']['name'].'?uid={{ID}}'
						)), true, array('client_survey_link'));
						
						$this->Project->ProjectOption->create();
						$this->Project->ProjectOption->save(array('ProjectOption' => array(
							'project_id' => $project_id,
							'name' => 'typeform_nonce', 
							'value' => $this->request->data['Project']['typeform_nonce']
						)));
					}
				}
			}
			else {
				$projectSource->commit();
			}
			
			if ($save) {				
				// write project log
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project_id,
					'user_id' => $this->current_user['Admin']['id'],
					'type' => 'survey.created'
				)));
				
				// for recontact projects; write the flag
				if (isset($this->request->data['Project']['recontact_id']) && !empty($this->request->data['Project']['recontact_id'])) {
					$this->SurveyTools->set_recontact_flag($this->request->data['Project']['recontact_id'], $project_id, $this->current_user);
				}
				
				$this->Session->setFlash('Your project has been created.', 'flash_success'); 
				$this->redirect(array('action' => 'dashboard', $project_id)); 
			}
			else {
				$this->Session->setFlash('There was an error saving your project. Please review it.', 'flash_error');
			}
        }
		
		$countries = $this->GeoCountry->returnAsList();
		$languages = Utils::language_codes();
		
		$mintvine_group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'mintvine'
			)
		));
		
		$clients = $this->Client->find('list', array(
			'fields' => array('Client.id', 'Client.client_name'),
			'conditions' => array(
				'Client.group_id' => array($mintvine_group['Group']['id'], $group['Group']['id']),
				'Client.deleted' => false
			), 
			'order' => 'Client.client_name ASC'
		));		
		
		$account_managers = $this->Role->get_administrators(array('account_managers', 'sales_managers'));
		$project_managers = $this->Role->get_administrators(array('project_managers'));
		$nonce = String::uuid();
		
		$this->set(compact('clients', 'countries', 'languages', 'project_staff', 'account_managers', 'project_managers', 'nonce', 'group'));
	}
	
	public function download_links($project_id) {
		$this->SurveyLink->bindModel(array('belongsTo' => array('Partner')));
		$project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $project_id
			),
			'fields' => array('id', 'code', 'group_id'),
			'contain' => array('ProjectAdmin'),
		));
		if (!$this->Admins->can_access_project($this->current_user, $project)) {
			$this->Session->setFlash('You are not authorized to access that project.', 'flash_error');
			$this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}
		
		$survey_links = $this->SurveyLink->find('all', array(
			'conditions' => array(
				'SurveyLink.survey_id' => $project_id,
			)
		));
		$data = array();
		if ($survey_links) {
			foreach ($survey_links as $survey_link) {
				$url = HOSTNAME_REDIRECT.'/go/'.$project['Project']['id'].'-'.$project['Project']['code'].'?pid='.$survey_link['Partner']['id'].'&sid='.$survey_link['SurveyLink']['id'].'&uid=';							
				$data[] = array(
					$url, 
					$survey_link['SurveyLink']['partner_user_id'], 
					$survey_link['Partner']['partner_name']
				);
			}
		}		
	   	$filename = 'links-'.$project_id.'-'.gmdate(DB_DATE, time()) . '.csv';
  		$csv_file = fopen('php://output', 'w');

		header('Content-type: application/csv');
		header('Content-Disposition: attachment; filename="' . $filename . '"');

		// Each iteration of this while loop will be a row in your .csv file where each field corresponds to the heading of the column
		foreach ($data as $row) {
			fputcsv($csv_file, $row, ',', '"');
		}

		fclose($csv_file);
		$this->autoRender = false;
		$this->layout = false;
		$this->render(false);
	}
	
	public function ajax_convert_project_to_fulcrum($project_id) {
		if (!$this->Admins->can_access_project($this->current_user, $project_id)) {
			return new CakeResponse(array('status' => '401'));
		}
		
		if ($this->request->is('ajax') && $this->request->is('post')) { 
			$group = $this->Group->find('first', array(
				'fields' => array('id', 'key'),
				'conditions' => array(
					'Group.key' => 'fulcrum'
				)
			));
			$this->Project->bindModel(array(
				'hasOne' => array(
					'FedSurvey' => array(
						'className' => 'FedSurvey',
						'foreignKey' => 'survey_id'
					)
				)
			));
			$project = $this->Project->find('first', array(
				'conditions' => array(
					'Project.id' => $project_id
				)
			));
			if (!$project || $project['Project']['group_id'] != $group['Group']['id']) {
				return new CakeResponse(array(
					'body' => json_encode(array(
						'message' => 'You have already converted this project to an ad-hoc project.'
					)),
					'type' => 'json',
					'status' => '400'
				));
			}
			else {
				$mintvine_group = $this->Group->find('first', array(
					'fields' => array('Group.id'),
					'conditions' => array(
						'Group.key' => 'mintvine'
					)
				));
				$this->Session->setFlash('This project has been converted to an adhoc project.', 'flash_success');
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project_id,
					'group_id' => $mintvine_group['Group']['id'],
					'ignore_autoclose' => true,
					'mask' => null,
				)), true, array('group_id', 'mask', 'ignore_autoclose'));
				
				if (!empty($project['FedSurvey']['id'])) {
					$this->Project->FedSurvey->create();
					$this->Project->FedSurvey->save(array('FedSurvey' => array(
						'id' => $project['FedSurvey']['id'],
						'status' => 'skipped.adhoc'
					)), true, array('status'));
				}
				return new CakeResponse(array(
					'body' => json_encode(array(
						'message' => ''
					)),
					'type' => 'json',
					'status' => '201'
				));
			}
		}
		return new CakeResponse(array(
			'body' => json_encode(array(
				'message' => 'Invalid request'
			)),
			'type' => 'json',
			'status' => '400'
		));
	}
	
	public function ajax_fed_qualifications($project_id) {
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
			
			$this->FedQuestion->primaryKey = 'question_id';
			$this->FedQuestion->bindModel(array('hasMany' => array(
				'FedAnswer' => array(
					'foreignKey' => 'question_id',
					'conditions' => array('FedAnswer.language_id' => $survey_allocation['SupplierAllocationSurvey']['CountryLanguageID'])
				)
			)));
			$fed_question = $this->FedQuestion->find('first', array(
				'conditions' => array(
					'FedQuestion.question_id' => $question['QuestionID'],
					'FedQuestion.language_id' => $survey_allocation['SupplierAllocationSurvey']['CountryLanguageID']
				)
			));
			
			if (!$fed_question) {
				$fed_question = array('FedQuestion' => array(
					'question' => 'Question id: '.$question['QuestionID']. ' not found!'
				));
				continue;
			}
			
			// Mobile and tablet questions need different treatment
			if (in_array($fed_question['FedQuestion']['question_id'], array(8213, 8214))) {
				if ($question['PreCodes'][0] == 'true') {
					$fed_question['FedAnswer'] = array(0 => array('answer' => 'Yes'));
				}
				elseif ($question['PreCodes'][0] == 'false') {
					$fed_question['FedAnswer'] = array(0 => array('answer' => 'No'));
				}
			}
			elseif (!empty($fed_question['FedAnswer'])) {
				foreach ($fed_question['FedAnswer'] as $key => $answer) {
					if (array_search($answer['precode'], $question['PreCodes']) === FALSE) {
						unset($fed_question['FedAnswer'][$key]);
					}
				}
			}
			else {
				$fed_question['FedAnswer'][] = array('answer' => $question['PreCodes'][0] .' - '. $question['PreCodes'][count($question['PreCodes'])-1]);
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
			
			$quota_qualifications = array();
			foreach ($quota['Questions'] as $question) {
				if ($question['LogicalOperator'] != 'OR') {
					continue;
				}
				
				$this->FedQuestion->primaryKey = 'question_id';
				$this->FedQuestion->bindModel(array('hasMany' => array(
					'FedAnswer' => array(
						'foreignKey' => 'question_id',
						'conditions' => array('FedAnswer.language_id' => $survey_allocation['SupplierAllocationSurvey']['CountryLanguageID'])
					)
				)));
				$fed_question = $this->FedQuestion->find('first', array(
					'conditions' => array(
						'FedQuestion.question_id' => $question['QuestionID'],
						'FedQuestion.language_id' => $survey_allocation['SupplierAllocationSurvey']['CountryLanguageID']
					)
				));

				if (!$fed_question) {
					$fed_question = array('FedQuestion' => array(
						'question' => 'Question id: ' . $question['QuestionID'] . ' not found!'
					));
					continue;
				}

				if (!empty($fed_question['FedAnswer'])) {
					foreach ($fed_question['FedAnswer'] as $key => $answer) {
						if (array_search($answer['precode'], $question['PreCodes']) === FALSE) {
							unset($fed_question['FedAnswer'][$key]);
						}
					}
				}
				else {
					$fed_question['FedAnswer'][] = array('answer' => $question['PreCodes'][0] . ' - ' . $question['PreCodes'][count($question['PreCodes']) - 1]);
				}

				$quota_qualifications[] = $fed_question;
			}
			
			$quotas[$quota['SurveyQuotaID']] = $quota_qualifications;
		}

		$this->set(compact('offerwall_qualifications', 'quotas'));
	}
	
	public function ajax_rfg_qualifications($rfg_id) {
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('rfg.host',	'rfg.apid',	'rfg.secret'),
				'Setting.deleted' => false
			)
		));
		$command = "{ 'command' : 'livealert/targeting/1' , 'rfg_id' : '" . $rfg_id . "' }";
		$rfg_project = $this->SurveyTools->execute_rfg_api($command, $settings);
		if (!$rfg_project || in_array($rfg_project['result'], array(1, 2)) || empty($rfg_project['response'])) {
			echo 'Rfg api response not found.';
			echo '<p><pre>';
			print_r($rfg_project);
			echo '</pre></p>';
			return;
		}
		
		$rfg_project = $rfg_project['response'];
		$parent_qualifications = array();
		if (!empty($rfg_project['datapoints'])) {
			foreach ($rfg_project['datapoints'] as $target) {
				if ($target['name'] == 'Age') {
					$min = min(Set::extract('/min', $target['values']));
					$max = max(Set::extract('/max', $target['values']));
					$parent_qualifications[] = array(
						'RfgQuestion' => array('question' => 'Age'),
						'RfgAnswer' => array(array('answer' => 'Min: ' . $min . ' Max: ' . $max))
					);
					continue;
				}
				elseif ($target['name'] == 'List of Zips') {
					$zips = Set::extract('/freelist', $target['values']);
					$parent_qualifications[] = array(
						'RfgQuestion' => array('question' => 'List of Zips'),
						'RfgAnswer' => array(array('answer' => implode(' ', $zips)))
					);
					continue;
				}
				
				$rfg_question = $this->RfgQuestion->find('first', array(
					'conditions' => array(
						'rfg_name' => $target['name']
					)
				));
				if (!$rfg_question) {
					$parent_qualifications[] = array('RfgQuestion' => array(
						'question' => 'Question : ' . $target['name'] . ' not found!'
					));
					continue;
				}
				
				// Some questions have this strange name
				if ($rfg_question['RfgQuestion']['question'] == '???') {
					$rfg_question['RfgQuestion']['question'] = $rfg_question['RfgQuestion']['rfg_name'];
				}
				
				$choices = Set::extract('/choice', $target['values']);
				if ($target['name'] == 'Computer Check') {
					if (in_array(1, $choices) || in_array(2, $choices)) {
						$rfg_question['RfgAnswer'] = array(array('answer' => 'Desktop'));
					}
					elseif (in_array(3, $choices)) {
						$rfg_question['RfgAnswer'] = array(array('answer' => 'Mobile'));
					}
					elseif (in_array(4, $choices)) {
						$rfg_question['RfgAnswer'] = array(array('answer' => 'Tablet'));
					}
				}
				elseif (!empty($rfg_question['RfgAnswer'])) {
					foreach ($rfg_question['RfgAnswer'] as $key => $answer) {
						if (!in_array($answer['key'], $choices)) {
							unset($rfg_question['RfgAnswer'][$key]);
						}
					}
				}
				else {
					$rfg_question['RfgAnswer'][] = array('answer' => print_r($target['values'], true));
				}

				$parent_qualifications[] = $rfg_question;
			}
		}
		
		$quotas = array();
		if (isset($rfg_project['quotas']) && !empty($rfg_project['quotas'])) {
			foreach ($rfg_project['quotas'] as $quota) {
				$quota_qualifications = array();
				foreach ($quota['datapoints'] as $target) {
					if ($target['name'] == 'Age') {
						$min = min(Set::extract('/min', $target['values']));
						$max = max(Set::extract('/max', $target['values']));
						$quota_qualifications[] = array(
							'RfgQuestion' => array('question' => 'Age'),
							'RfgAnswer' => array(array('answer' => 'Min: ' . $min . ' Max: ' . $max))
						);
						continue;
					}
					elseif ($target['name'] == 'List of Zips') {
						$zips = Set::extract('/freelist', $target['values']);
						$quota_qualifications[] = array(
							'RfgQuestion' => array('question' => 'List of Zips'),
							'RfgAnswer' => array(array('answer' => implode(' ', $zips)))
						);
						continue;
					}

					$rfg_question = $this->RfgQuestion->find('first', array(
						'conditions' => array(
							'rfg_name' => $target['name']
						)
					));
					if (!$rfg_question) {
						$quota_qualifications[] = array('RfgQuestion' => array(
								'question' => 'Question : ' . $target['name'] . ' not found!'
						));
						continue;
					}
					
					// Some questions have this strange name
					if ($rfg_question['RfgQuestion']['question'] == '???') {
						$rfg_question['RfgQuestion']['question'] = $rfg_question['RfgQuestion']['rfg_name'];
					}
					
					$choices = Set::extract('/choice', $target['values']);
					if (!empty($rfg_question['RfgAnswer'])) {
						foreach ($rfg_question['RfgAnswer'] as $key => $answer) {
							if (!in_array($answer['key'], $choices)) {
								unset($rfg_question['RfgAnswer'][$key]);
							}
						}
					}
					else {
						$rfg_question['RfgAnswer'][] = array('answer' => print_r($target['values'], true));
					}

					$quota_qualifications[] = $rfg_question;
				}

				$quotas[] = $quota_qualifications;
			}
		}

		$this->set(compact('parent_qualifications', 'quotas'));
	}

	public function ajax_cint_qualifications($project_id, $country) {
		if (!$this->Admins->can_access_project($this->current_user, $project_id)) {
			return new CakeResponse(array('status' => '401'));
		}
		
		$quotas = array();
		$project_option = $this->ProjectOption->find('first', array(
			'fields' => array('id', 'value'),
			'conditions' => array(
				'name' => 'cint_log.'.$country.'.id',
				'project_id' => 0
			)
		));
		if ($project_option) {
			$cint_logs = $this->CintLog->find('all', array(
				'conditions' => array(
					'cint_survey_id' => $project_id,
					'parent_id' => $project_option['ProjectOption']['value'],
				)
			));
			
			if ($cint_logs) {
				foreach ($cint_logs as $cint_log) {
					$quota = json_decode($cint_log['CintLog']['raw'], true);
					if (!empty($quota['target_group']['region_ids'])) {
						foreach ($quota['target_group']['region_ids'] as $region_id) {
							$region = $this->CintRegion->find('first', array(
								'conditions' => array(
									'cint_id' => $region_id
								)
							));
							if ($region) {
								$quota['Regions'][] = $region;
							}
						}
					}
					
					foreach ($quota['target_group']['variable_ids'] as $variable_id) {
						$this->CintQuestion->primaryKey = 'question_id';
						$this->CintAnswer->bindModel(array('belongsTo' => array(
							'CintQuestion' => array('foreignKey' => 'question_id')
						)));
						$answer = $this->CintAnswer->find('first', array(
							'contain' => array('CintQuestion'),
							'conditions' => array(
								'CintAnswer.variable_id' => $variable_id
							)
						)); 
						if ($answer) {
							$quota['Variables'][] = $answer;
						}
					}
					
					$quotas[] = $quota;
				}
			}
		}
		
		$this->set('quotas', $quotas);
	}

	public function ajax_spectrum_qualifications($spectrum_survey_id) {
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
			return new CakeResponse(array(
				'status' => 200,
				'body' => 'Api request failed.',
			));
		}
		$this->set('qualifications_and_quotas', $response_body);
	}
	
	public function ajax_survey_links($project_id) {
		$project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $project_id
			),
			'contain' => array('ProjectAdmin')
		));
		if (!$this->Admins->can_access_project($this->current_user, $project)) {
			return new CakeResponse(array('status' => '401'));
		}
		
		$this->SurveyLink->bindModel(array('belongsTo' => array(
			'Partner' => array(
				'fields' => array('Partner.partner_name')
			)
		))); 
		$survey_links = $this->SurveyLink->find('all', array(
			'conditions' => array(
				'SurveyLink.survey_id' => $project_id,
			),
			'order' => 'SurveyLink.used ASC, SurveyLink.partner_id ASC'
		));
		$links = $existing = $errors = array();
		foreach ($survey_links as $survey_link) {
			$links[] = $survey_link['SurveyLink'];
		}
		$good_links = $this->SurveyTools->check_valid_links($links, $project);
		if (!$good_links) {
			foreach ($links as $link) {
				if (!empty($project['Project']['recontact_id']) && empty($link['user_id']) && empty($link['partner_user_id'])) {
					$errors[$link['link']] = 'ERROR: Empty User ID';
				}
				if (in_array($link['link'], $existing)) {
					$errors[$link['link']] = 'ERROR: Dupe link';
				}
				$existing[] = $link['link'];
			}
		}
		$this->set(compact('errors', 'survey_links', 'project', 'good_links', 'error_type'));
		$this->RequestHandler->respondAs('application/json'); 
		$this->response->statusCode('200');
		$this->layout = '';
	}
		
	public function ajax_pause($project_id) {
		$project = $this->Project->find('first', array(
			'fields' => array(
				'Project.id', 'Project.active', 'Project.ended', 'Project.started', 'Project.group_id'
			),
			'conditions' => array(
				'Project.id' => $project_id
			),
			'contain' => array('ProjectAdmin')
		));
		
		if (!$this->Admins->can_access_project($this->current_user, $project)) {
			return new CakeResponse(array('status' => '401'));
		}
		
		if ($this->request->is('post') || $this->request->is('put')) {
			$save = array('Project' => array('id' => $project['Project']['id']));
			$save_keys = array('active');
			
			// if we are pausing
			if ($project['Project']['active']) {
				$save['Project']['active'] = false;

				// but you can set the ended timestamp as many times as you want
				$save['Project']['ended'] = date(DB_DATETIME);
				$save_keys[] = 'ended'; 
			}
			else {
				$save['Project']['active'] = true;
				
				// cannot set the start time more than once
				if (empty($project['Project']['started'])) {
					$save['Project']['started'] = date(DB_DATETIME);
					$save_keys[] = 'started';
				}
				
				$save['Project']['ended'] = null;
				$save_keys[] = 'ended'; 
			}
			
			$project_logs = Utils::get_field_diffs($save['Project'], $project['Project']);
			$log_description = implode(', ', $project_logs);
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project_id,
				'user_id' => $this->current_user['Admin']['id'],
				'type' => 'survey.updated',
				'description' => $log_description,
			)));
			
			$this->Project->create();
			$this->Project->save($save, true, $save_keys);
		}
    	return new CakeResponse(array(
			'body' => json_encode(array(
				'icon' => Utils::survey_status($save, 'icon'),
				'button' => Utils::survey_status($save, 'button')
			)),
			'type' => 'json',
			'status' => '201'
		));
	}
	
	public function ajax_security_partners($survey_partner_id) {
		$survey_partner = $this->Project->SurveyPartner->findById($survey_partner_id);
		if (!$this->Admins->can_access_project($this->current_user, $survey_partner['SurveyPartner']['survey_id'])) {
			return new CakeResponse(array('status' => '401'));
		}
		
		if ($this->request->is('post') || $this->request->is('put')) {
			$this->Project->SurveyPartner->create();
			$this->Project->SurveyPartner->save(array('SurveyPartner' => array(
				'id' => $survey_partner['SurveyPartner']['id'],
				'security' => !$survey_partner['SurveyPartner']['security']
			)), true, array('security')); 
		}
		
		if ($survey_partner['SurveyPartner']['security']) {
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $survey_partner['SurveyPartner']['survey_id'],
				'user_id' => $this->current_user['Admin']['id'],
				'type' => 'survey_partner.updated',
				'description' => 'Security updated from 1 to 0',
			)));
		}
		else {
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $survey_partner['SurveyPartner']['survey_id'],
				'user_id' => $this->current_user['Admin']['id'],
				'type' => 'survey_partner.updated',
				'description' => 'Security updated from 0 to 1',
			)));
		}
		
		$status = !$survey_partner['SurveyPartner']['security'] ? 'unpaused': 'paused';
    	return new CakeResponse(array(
			'body' => json_encode(array(
				'status' => $status
			)), 
			'type' => 'json',
			'status' => '201'
		));
	}
	
	public function ajax_pause_partners($survey_partner_id) {
		$survey_partner = $this->Project->SurveyPartner->findById($survey_partner_id);
		if (!$this->Admins->can_access_project($this->current_user, $survey_partner['SurveyPartner']['survey_id'])) {
			return new CakeResponse(array('status' => '401'));
		}
		
		if ($this->request->is('post') || $this->request->is('put')) {
			$this->Project->SurveyPartner->create();
			$this->Project->SurveyPartner->save(array('SurveyPartner' => array(
				'id' => $survey_partner['SurveyPartner']['id'],
				'paused' => !$survey_partner['SurveyPartner']['paused']
			)), true, array('paused')); 
			
			if ($survey_partner['SurveyPartner']['paused']) {
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $survey_partner['SurveyPartner']['survey_id'],
					'user_id' => $this->current_user['Admin']['id'],
					'type' => 'survey_partner.updated',
					'description' => 'Survey partner started, partner ID: ' . $survey_partner_id,
				)));
			}
			else {
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $survey_partner['SurveyPartner']['survey_id'],
					'user_id' => $this->current_user['Admin']['id'],
					'type' => 'survey_partner.updated',
					'description' => 'Survey partner paused, partner ID: ' . $survey_partner_id,
				)));
			}
		}
		
		$status = $survey_partner['SurveyPartner']['paused'] ? 'unpaused': 'paused';
    	return new CakeResponse(array(
			'body' => json_encode(array(
				'status' => $status
			)), 
			'type' => 'json',
			'status' => '201'
		));
	}
	
	public function load_members($project_id) {
		if (!$this->Admins->can_access_project($this->current_user, $project_id)) {
			$this->Session->setFlash('You are not authorized to access that project.', 'flash_error');
			$this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}
		if ($this->request->is('put') || $this->request->is('post')) {
			$user_ids = explode("\n", trim($this->request->data['Survey']['user_ids'])); 
			$i = 0;
			foreach ($user_ids as $user_id) {
				$user = $this->User->find('first', array(
					'fields' => array('User.id'),
					'conditions' => array(
						'User.id' => $user_id
					),
					'recursive' => -1
				));
				if (!$user) {
					continue;
				} 
				$survey_user = $this->SurveyUser->find('count', array(
					'conditions' => array(
						'SurveyUser.user_id' => $user_id,
						'SurveyUser.survey_id' => $project_id
					)
				));
				if ($survey_user) {
					continue;
				}
				$this->SurveyUser->create();
				$save = $this->SurveyUser->save(array('SurveyUser' => array(
					'survey_id' => $project_id,
					'user_id' => $user_id
				)));
				if ($save) {
					$i++;
				}
			}
			if ($i > 0) {
				$this->Session->setFlash($i.' items added.', 'flash_success');
				return $this->redirect(array('action' => 'load_members', $project_id));
			}
		}
	}
	
	public function exclude($project_id) {
		if (!$this->Admins->can_access_project($this->current_user, $project_id)) {
			$this->Session->setFlash('You are not authorized to access that project.', 'flash_error');
			$this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}
		
		if ($this->request->is('put') || $this->request->is('post')) {
			$exclude_user_ids = array();
			if (isset($this->data['Survey']['project_id']) && !empty($this->data['Survey']['project_id'])) {
				$existing_project_ids = explode(',', $this->data['Survey']['project_id']);
				array_walk($existing_project_ids, create_function('&$val', '$val = trim($val);'));
				foreach ($existing_project_ids as $key => $existing_project_id) {
					$project_id = MintVine::parse_project_id($existing_project_id);
					if (!$project_id) {
						continue;
					}
					$existing_project_ids[$key] = $project_id;
				}
			
				$survey_visits = $this->SurveyUserVisit->find('list', array(
					'fields' => array('user_id'),
					'recursive' => -1,
					'conditions' => array(
						'SurveyUserVisit.survey_id' => $existing_project_id,
						'SurveyUserVisit.status' => SURVEY_COMPLETED
					)
				));
				if ($survey_visits && !empty($survey_visits)) {
					foreach ($survey_visits as $user_id) {
						$exclude_user_ids[] = $user_id;
					}
				}
			}
			
			if (isset($this->data['Survey']['user_ids']) && !empty($this->data['Survey']['user_ids'])) {
				$user_ids = explode("\n", trim($this->data['Survey']['user_ids']));
				$exclude_user_ids = array_merge($exclude_user_ids, $user_ids);
			}
			
			$exclude_user_ids = array_unique($exclude_user_ids);
			if (!empty($exclude_user_ids)) {
				foreach ($exclude_user_ids as $user_id) {
					$survey_visit = $this->SurveyUserVisit->find('first', array(
						'conditions' => array(
							'SurveyUserVisit.user_id' => $user_id,
							'SurveyUserVisit.survey_id' => $project_id
						)
					));
					// create an entry
					if (!$survey_visit) {
						$this->SurveyUserVisit->create();
						$this->SurveyUserVisit->save(array('SurveyUserVisit' => array(
							'user_id' => $user_id,
							'survey_id' => $project_id,
							'status' => SURVEY_NQ_EXCLUDED,
							'redeemed' => true // mark it as paid when it really isn't
						)));
					}
					// basically any non-complete state; mark as excluded now
					if ($survey_visit && !in_array($survey_visit['SurveyUserVisit']['status'], array(SURVEY_NQ_EXCLUDED, SURVEY_COMPLETED, SURVEY_NQ, SURVEY_INTERNAL_NQ))) {
						$this->SurveyUserVisit->create();
						$this->SurveyUserVisit->save(array('SurveyUserVisit' => array(
							'id' => $survey_visit['SurveyUserVisit']['id'],
							'status' => SURVEY_NQ_EXCLUDED,
							'redeemed' => true // mark it as paid when it really isn't
						)), true, array('status', 'redeemed'));
					}
				}
				
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project_id,
					'user_id' => $this->current_user['Admin']['id'],
					'type' => 'query.updated',
					'description' => 'User excluded IDs: ("' . implode(', ', $exclude_user_ids) . '")',
				)));
			}
			$this->Session->setFlash(count($exclude_user_ids).' users have been excluded from this survey. They will no longer see the survey (if they were previously invited), nor will they be invited through future queries.', 'flash_success');
			$this->redirect(array('action' => 'dashboard', $project_id));
		}
	}
	
	public function retarget($project_id) {
		$project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $project_id
			),
			'contain' => array('ProjectAdmin')
		));
		if (!$this->Admins->can_access_project($this->current_user, $project)) {
			$this->Session->setFlash('You are not authorized to access that project.', 'flash_error');
			$this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}
		
		// this is used if multiple projects point to a single recontact URL which would cause confusion
		// this get written as a project.option for warning the PMs to clean this up
		$multiple_recontact_flag_warning = false; 
		$multiple_recontact_project_ids = array();
		
		if ($this->request->is('put') || $this->request->is('post')) {
			
			// unset the client survey link if it's set to prevent system confusion
			if (!empty($project['Project']['client_survey_link'])) {
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'client_survey_link' => ''
				)), array(
					'callbacks' => false,
					'fieldList' => array('client_survey_link')
				));
			}
			$mv_partner = $this->Partner->find('first', array(
				'conditions' => array(
					'Partner.key' => 'mintvine',
					'Partner.deleted' => false
				)
			));
			if ($this->request->data['Project']['type'] == 'untargeted') {
				$respondent_ids = explode("\n", trim($this->request->data['Project']['hashes']));
				$respondent_ids = array_map('trim', $respondent_ids);
				foreach ($respondent_ids as $key => $val) {
					if (empty($val)) {
						unset($respondent_ids[$key]);
					}
				}
				if (!empty($respondent_ids)) {
					$user_ids = array();
					$i = 0;
					foreach ($respondent_ids as $respondent_id) {
						if (empty($respondent_id)) {
							continue;
						}
						$user = $this->SurveyAnalysis->user_from_hash($respondent_id, $mv_partner);
						if ($user && $user['user_id']) {
							$user_ids[] = $user['user_id'];
							$i++;
						}
					}
				}
				else {
					$this->Session->setFlash('You did not input a valid set of hashes.', 'flash_error');
				}
			}
			elseif ($this->request->data['Project']['type'] == 'targeted') {
				
				$client = $this->Client->findById($this->data['Project']['client']);
				
				if (!$client) {
					$this->Session->setFlash('Recontact URLs have not been set-up for this client yet.', 'flash_error');
					$this->redirect(array('controller' => 'surveys', 'action' => 'retarget', $project_id));
				}
				else {
					if (!empty($this->data['Project']['file']['error'])) {
						$this->Session->setFlash('You did not upload a valid file.', 'flash_error');
						$this->redirect(array('controller' => 'surveys', 'action' => 'retarget', $project_id));
					}
					else {
						$data = Utils::csv_to_array($this->request->data['Project']['file']['tmp_name']);
						$save = true;
						
						// sanitize the input
						foreach ($data as $key => $val) {
							if (!isset($val[0]) || !isset($val[1])) {
								unset($data[$key]);
								continue;
							}
							$val[0] = trim($val[0]); // URL
							$val[1] = trim($val[1]); // UID
							if (empty($val[0]) || empty($val[1])) {
								unset($data[$key]);
								continue;
							}
						}
											
						// validate the input
						foreach ($data as $val) {
							list($url, $respondent_id) = $val;
							if (!is_numeric(Utils::parse_project_id_from_hash($respondent_id))) {
								$save = false;
								$this->Session->setFlash($respondent_id.' is not a valid hash.', 'flash_error');
								break;
							}
							if (empty($project['Project']['recontact_id']) && strpos($url, '{{ID}}') === false) {
								$save = false;
								$this->Session->setFlash($url.' is not a valid URL - it is missing the {{ID}} variable.', 'flash_error');
								break;
							}
						}
						
						if ($save) {
							$i = count($data);
							$j = 0;
							foreach ($data as $val) {
								list($url, $respondent_id) = $val;
								$user = $this->SurveyAnalysis->user_from_hash($respondent_id, $mv_partner);	
								if ($user) {
									// mintvine user
									if ($user['user_id']) {
										$user_ids[] = $user['user_id'];	
										$survey_link = array('SurveyLink' => array(
											'survey_id' => $project_id,
											'link' => $url,
											'user_id' => $user['user_id'],
											'partner_user_id' => $respondent_id,
											'sort_order' => $j,
											'active' => true
										));
										$j++;
										if (!empty($project['Project']['recontact_id'])) {
											$survey_link['SurveyLink']['forced_hash'] = $respondent_id;
											$count = false;
											
											if (!$multiple_recontact_flag_warning) {
												// having multiple 
												$dupe_recontacts = $this->RecontactHash->find('list', array(
													'conditions' => array(
														'RecontactHash.hash' => $respondent_id,
														'RecontactHash.original_project_id' => $project['Project']['recontact_id'],
														'RecontactHash.project_id <>' => $project_id,
														'RecontactHash.completed' => false
													),
													'recursive' => -1,
													'fields' => array('RecontactHash.id', 'RecontactHash.project_id')
												));
												if (!empty($dupe_recontacts)) {
													$multiple_recontact_flag_warning = true; // this gets written at the end of processing
													$multiple_recontact_project_ids = $multiple_recontact_project_ids + $dupe_recontacts; 
													// merge project IDs
												}
											}
											$this->RecontactHash->create();
											$this->RecontactHash->save(array('RecontactHash' => array(
												'hash' => $respondent_id,
												'original_project_id' => $project['Project']['recontact_id'],
												'project_id' => $project_id
											)));
											
											$alias = '';
											if ($client['Client']['key'] == 'mvrg') {
												if (strpos($url, 'GID') !== false) {
													list($nothing, $alias) = explode('GID=', $url);
													$alias = trim($alias);
											
													if (!empty($alias)) {
														$hash_alias = $this->HashAlias->find('first', array(
															'fields' => array('HashAlias.id'),
															'conditions' => array(
																'HashAlias.alias' => $alias,
																'HashAlias.project_id' => $project_id
															)
														));
												
														if (!$hash_alias) {
															$this->HashAlias->create();
															$this->HashAlias->save(array('HashAlias' => array(
																'id' => $hash_alias['HashAlias']['id'],
																'hash' => $respondent_id,
															)), true, array('hash'));
														}
														else {
															$this->HashAlias->create();
															$this->HashAlias->save(array('HashAlias' => array(
																'hash' => $respondent_id,
																'alias' => $alias,
																'project_id' => $project_id
															)));
														}
													}
												}
											}
										}
										
										$this->SurveyLink->create();
										$this->SurveyLink->save($survey_link);
									}
									// regular survey user
									else {
										$survey_link = array('SurveyLink' => array(
											'survey_id' => $project_id,
											'partner_id' => $user['partner_id'],
											'link' => $url,
											'partner_user_id' => $user['partner_user_id'],
											'sort_order' => $j,
											'active' => true
										)); 
										$j++;
										if (!empty($project['Project']['recontact_id'])) {
											$survey_link['SurveyLink']['forced_hash'] = $respondent_id;
											if (!$multiple_recontact_flag_warning) {
												// having multiple 
												$dupe_recontacts = $this->RecontactHash->find('list', array(
													'conditions' => array(
														'RecontactHash.hash' => $respondent_id,
														'RecontactHash.original_project_id' => $project['Project']['recontact_id'],
														'RecontactHash.project_id <>' => $project_id,
														'RecontactHash.completed' => false
													),
													'recursive' => -1,
													'fields' => array('RecontactHash.id', 'RecontactHash.project_id')
												));
												if (!empty($dupe_recontacts)) {
													$multiple_recontact_flag_warning = true; // this gets written at the end of processing
													$multiple_recontact_project_ids = $multiple_recontact_project_ids + $dupe_recontacts; 
													// merge project IDs
												}
											}
											$this->RecontactHash->create();
											$this->RecontactHash->save(array('RecontactHash' => array(
												'hash' => $respondent_id,
												'original_project_id' => $project['Project']['recontact_id'],
												'project_id' => $project_id
											)));
										}
										$this->SurveyLink->create();
										$this->SurveyLink->save($survey_link);
									}
								}
							}
						}
					}
				}
				if (isset($multiple_recontact_flag_warning) && $multiple_recontact_flag_warning) {
					$multiple_recontact_project_ids = array_unique($multiple_recontact_project_ids);
					$this->Project->ProjectOption->create();
					$this->Project->ProjectOption->save(array('ProjectOption' => array(
						'project_id' => $project_id,
						'name' => 'duplicate.recontacts',
						'value' => json_encode($multiple_recontact_project_ids)
					)));
				}
			}
							
			if (!empty($user_ids)) {
				$query = array(
					'query_name' => '#'.$project_id.' retarget',
					'query_string' => json_encode(array('user_id' => $user_ids)),
					'survey_id' => $project_id
				);

				$querySource = $this->Query->getDataSource();
				$querySource->begin();
				$this->Query->create();
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
				
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project_id,
					'user_id' => $this->current_user['Admin']['id'],
					'type' => 'query.updated',
					'query_id' => $query_id,
					'description' => 'User recontacted IDs (' . implode(', ', $user_ids) . ')',
				)));
				
				$this->Session->setFlash('Your recontact has been complete; we found '.count($user_ids).' users from the '.$i.' hashes you uploaded. View the query below to send the notification.', 'flash_success');
			}
			else {
				$this->Session->setFlash('No users could be found on this recontact.', 'flash_error');
			}
			$this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $project_id));
		}
		
		$project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $project_id
			)
		));	
		$clients = $this->Client->find('list', array(
			'conditions' => array(
				'Client.group_id' => $project['Project']['group_id'],
				'Client.deleted' => false
			), 
			'order' => 'Client.client_name ASC'
		));
		
		$this->set(compact('project', 'clients'));
	}
	
	public function edit($project_id) {
		$this->Project->bindInvoices();
		$survey_record = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $project_id
			),
			'contain' => array(
				'Client',
				'Group',
				'SurveyVisitCache',
				'Invoice',
				'ProjectOption' => array(
					'conditions' => array(
						'name NOT' => array('pushed_email_template', 'pushed', 'pushed_email_subject')
					)
				),
				'ProjectAdmin'
			)
		));
		if (!$survey_record) {
		   	throw new NotFoundException(__('Invalid survey'));
		}
		
		$project_staff = $this->Role->get_administrators(array('project_managers', 'account_managers', 'sales_managers'));
		
		if (!$this->Admins->can_access_project($this->current_user, $survey_record)) {
			$this->Session->setFlash('You are not authorized to access that project.', 'flash_error');
			$this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}
		
		$fed_survey = $this->FedSurvey->find('first', array(
			'conditions' => array(
				'FedSurvey.survey_id' => $project_id
			)
		));
		
		if ($this->request->is('post') || $this->request->is('put')) {
			$project_logs = array();
			//Getting existing project data to use for project logs
			$existing_project = $this->Project->find('first', array(
				'conditions' => array('Project.id' => $project_id),
				'recursive' => -1
			));
			if (!empty($this->request->data['ProjectOption']['interview_date'])) {
				$interview_date = $this->request->data['ProjectOption']['interview_date'];				
				$interview_date = $interview_date['year'] . '-' . $interview_date['month'] . '-' . $interview_date['day'] . '
				' . $interview_date['hour'] . ':' . $interview_date['min'] . ' ' . $interview_date['meridian'];				
				$interview_date = date('Y-m-d H:i:s', strtotime($interview_date));
				$user_timezone = new DateTimeZone($this->current_user['Admin']['timezone']);
				$date = new DateTime($interview_date, $user_timezone);	
				$gmt = new DateTimeZone('UTC');	
				$date->setTimezone($gmt);
				$interview_date = $date->format('Y-m-d H:i:s');	
				$this->request->data['ProjectOption']['interview_date'] = $interview_date;
					
			}
			$old_client_action = $survey_record['Project']['client_end_action'];
			$save = false;
			$project = array(
				'Project' => array(
					'id' => $project_id,
					'prj_name' => $this->request->data['Project']['prj_name'],
					'client_id' => $this->request->data['Project']['client_id'],
					'client_rate' => $this->request->data['Project']['client_rate'],
					'award' => $this->request->data['Project']['award'],
					'partner_rate' => $this->request->data['Project']['partner_rate'],
					'user_payout' => $this->request->data['Project']['award'] / 100,
					'nq_award' => $this->request->data['Project']['nq_award'],
					'quota' => $this->request->data['Project']['quota'],
					'router' => $this->request->data['Project']['router'],
					'singleuse' => $this->request->data['Project']['singleuse'],
					'landerable' => $this->request->data['Project']['landerable'],
					'bid_ir' => $this->request->data['Project']['bid_ir'],
					'priority' => $this->request->data['Project']['priority'],
	 				'est_length' => $this->request->data['Project']['est_length'],
	 				'prj_description' => $this->request->data['Project']['prj_description'],
	 				'recontact_id' => $this->request->data['Project']['recontact_id'],
	 				'client_project_id' => $this->request->data['Project']['client_project_id'],
	 				'modified' => date(DB_DATETIME),
					'survey_name' => $this->request->data['Project']['survey_name'],
					'country' => $this->request->data['Project']['country'],
					'language' => !empty($this->request->data['Project']['language']) ? $this->request->data['Project']['language']: null,
					'description' => $this->request->data['Project']['description'],
					'minimum_time' => $this->request->data['Project']['minimum_time'],
					'pool' => empty($this->request->data['Project']['pool']) ? '0': $this->request->data['Project']['pool'],
					'dedupe' => isset($this->request->data['Project']['dedupe']) && $this->request->data['Project']['dedupe'] == 1,
					'client_survey_link' => $this->request->data['Project']['client_survey_link'],
					'client_end_action' => $this->request->data['Project']['client_end_action'],
					'public' => isset($this->request->data['Project']['public']) && $this->request->data['Project']['public'] == 1,				
					'prescreen' => isset($this->request->data['Project']['prescreen']) && $this->request->data['Project']['prescreen'] == 1,
					'skip_mv_prescreen' => isset($this->request->data['Project']['skip_mv_prescreen']) && $this->request->data['Project']['skip_mv_prescreen'] == 1,
					'desktop' => $this->request->data['Project']['desktop'],
					'mobile' => $this->request->data['Project']['mobile'],
					'tablet' => $this->request->data['Project']['tablet'],
					'address_required' => $this->request->data['Project']['address_required'],
					'ip_sensitivity' => $this->request->data['Project']['ip_sensitivity'],
					'ip_dupes' => $this->request->data['Project']['ip_dupes']
				),
				'ProjectAdmin' => $this->request->data['ProjectAdmin']	
			);
			
			if (isset($this->request->data['Project']['ignore_autoclose']) && $this->request->data['Project']['ignore_autoclose']) {
				$project['Project']['ignore_autoclose'] = $this->request->data['Project']['ignore_autoclose'];
			}
			
			$this->Project->create();
			$save = $this->Project->save($project, true, array_merge(array_keys($project['Project']), array('epc')));
			if ($save) {				
				// update fulcrum fed_survey if necessary
				$client = $this->Client->find('first', array(
					'conditions' => array(
						'Client.id' => $this->request->data['Project']['client_id'],
						'Client.deleted' => false
					)
				));
				
				if ($client && $client['Client']['key'] == 'fulcrum') {
					$fed_survey = $this->FedSurvey->find('first', array(
						'conditions' => array(
							'FedSurvey.survey_id' => $project_id
						)
					));
					if (!empty($this->request->data['Project']['client_project_id'])) {
						if ($fed_survey) {
							$this->FedSurvey->create();
							$this->FedSurvey->save(array('FedSurvey' => array(
								'id' => $fed_survey['FedSurvey']['id'],
								'fed_survey_id' => $this->request->data['Project']['client_project_id'],
								'type' => 'skipped.adhoc'
							)), true, array('fed_survey_id', 'skipped.adhoc'));
						}
						else {
							$this->FedSurvey->create();
							$this->FedSurvey->save(array('FedSurvey' => array(
								'survey_id' => $project_id,
								'fed_survey_id' => $this->request->data['Project']['client_project_id'],
								'status' => 'skipped.adhoc'
							)));
						}
					}
					elseif ($fed_survey) {
						$this->FedSurvey->delete($fed_survey['FedSurvey']['id']);
					}
				}
		
				$links_deleted = isset($this->data['Project']['delete_old_links']) && $this->data['Project']['delete_old_links'] == 1;
				if ($links_deleted) {
					$survey_links = $this->SurveyLink->find('all', array(
						'recursive' => -1,
						'conditions' => array(
							'SurveyLink.survey_id' => $project_id
						),
						'fields' => array('SurveyLink.id')
					));
					if ($survey_links) {
						foreach ($survey_links as $survey_link) {
							$this->SurveyLink->delete($survey_link['SurveyLink']['id']); 
						}
					}
					
					// purge sqs
					$settings = $this->Setting->find('list', array(
						'fields' => array('Setting.name', 'Setting.value'),
						'conditions' => array(
							'Setting.name' => array('sqs.access.key', 'sqs.access.secret'),
							'Setting.deleted' => false
						)
					));
	
					$sqs = new SQS($settings['sqs.access.key'], $settings['sqs.access.secret']);
					$project_option = $this->ProjectOption->find('first', array(
						'conditions' => array(
							'ProjectOption.project_id' => $project_id,
							'ProjectOption.name' => 'sqs_url'
						)
					));
					if ($project_option) {
						$this->ProjectOption->delete($project_option['ProjectOption']['id']);
						$results = $sqs->deleteQueue($project_option['ProjectOption']['value']);
						$this->Project->create();
						$this->Project->save(array('Project' => array(
							'id' => $project_id,
							'sqs' => null
						)), true, array('sqs'));
					}
				}
				if (!empty($this->data['Project']['client_links']) && !empty($this->data['Project']['client_links']['tmp_name'])) {
					$links = $this->SurveyTools->process_links($this->data['Project']['client_links']['tmp_name'], $project_id);
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $project_id,
						'type' => 'links.uploaded',
						'user_id' => $this->current_user['Admin']['id'],
						'description' => 'Uploaded ' . $this->data['Project']['client_links']['name'] . ' with ' . ( !empty($links['links']) ? count($links['links']) : 0) . ' links from ' . $links['rows']
					)));
					$query = ROOT . '/app/Console/cake survey_links sync_to_sqs ' . $project_id;
					CakeLog::write('query_commands', $query);
					// run these synchronously
					exec($query.'  &> /dev/null &'); 
				}
				
				$this->Project->reset_surveylinks_count($project_id);
				
				$save = true;
				
				// for typeform surveys, save the file and update the client_url
				if (isset($this->request->data['Project']['typeform_html']) && empty($this->request->data['Project']['typeform_html']['error'])) {
					if (move_uploaded_file($this->request->data['Project']['typeform_html']['tmp_name'], TYPEFORM_UPLOAD.'/'.$this->request->data['Project']['typeform_html']['name'])) {
						$this->Project->create();
						$this->Project->save(array('Project' => array(
							'id' => $project_id,
							'client_survey_link' => TYPEFORM_URL.'/'.$this->request->data['Project']['typeform_html']['name'].'?uid={{ID}}'
						)), true, array('client_survey_link'));
					}
				}
				
				if (!empty($this->request->data['ProjectOption'])) {
					if (!empty($survey_record['ProjectOption'])) {
						foreach ($survey_record['ProjectOption'] as $project_option) {
							if (!isset($this->request->data['ProjectOption'][$project_option['name']]) || $this->request->data['ProjectOption'][$project_option['name']] == $project_option['value']) {
								unset($this->request->data['ProjectOption'][$project_option['name']]);
								continue;
							}
							$this->Project->ProjectOption->create();
							$this->Project->ProjectOption->save(array('ProjectOption' => array(
								'id' => $project_option['id'],
								'value' => $this->request->data['ProjectOption'][$project_option['name']]
							)), true, array('value'));
							unset($this->request->data['ProjectOption'][$project_option['name']]);
						}
					}
					if (!empty($this->request->data['ProjectOption'])) {
						foreach ($this->request->data['ProjectOption'] as $key => $val) {
							if (empty($val)) {
								continue;
							}
							$this->Project->ProjectOption->create();
							$this->Project->ProjectOption->save(array('ProjectOption' => array(
								'project_id' => $project_id,
								'name' => $key,
								'value' => $val
							)));
						}
					}
				}
			}
			
			if ($save) {
				$project_logs = array_merge($project_logs, Utils::get_field_diffs($this->request->data['Project'], $existing_project['Project']));
				$log_description = implode(', ', $project_logs);
				if (!empty($project_logs)) {
					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $project_id,
						'user_id' => $this->current_user['Admin']['id'],
						'type' => 'survey.updated',
						'description' => $log_description,
					)));
				}
				
				// manage project administrators
				$admin_ids = array();
				if (isset($this->request->data['ProjectAdmin']['pm_id']) && !empty($this->request->data['ProjectAdmin']['pm_id'])) {
					$admin_ids = array_merge($admin_ids, $this->request->data['ProjectAdmin']['pm_id']); 
				}
				if (isset($this->request->data['ProjectAdmin']['am_id']) && !empty($this->request->data['ProjectAdmin']['am_id'])) {
					$admin_ids = array_merge($admin_ids, $this->request->data['ProjectAdmin']['am_id']); 
				}
				
				// update current project admins if they have changed.
				if (!empty($survey_record['ProjectAdmin'])) {
					foreach ($survey_record['ProjectAdmin'] as $project_admin) {
						if (!in_array($project_admin['admin_id'], $admin_ids)) {
							$this->Project->ProjectAdmin->delete($project_admin['id']);
							continue;
						}
						
						$is_pm = $project_admin['is_pm'];
						$is_am = $project_admin['is_am'];
						if (!$is_pm && isset($this->request->data['ProjectAdmin']['pm_id']) && is_array($this->request->data['ProjectAdmin']['pm_id']) && in_array($project_admin['admin_id'], $this->request->data['ProjectAdmin']['pm_id'])) {
							$is_pm = true;
						}
						elseif ($is_pm && isset($this->request->data['ProjectAdmin']['pm_id']) && is_array($this->request->data['ProjectAdmin']['pm_id']) && !in_array($project_admin['admin_id'], $this->request->data['ProjectAdmin']['pm_id'])) {
							$is_pm = false;
						}
						
						if (!$is_am && isset($this->request->data['ProjectAdmin']['am_id']) && is_array($this->request->data['ProjectAdmin']['am_id']) && in_array($project_admin['admin_id'], $this->request->data['ProjectAdmin']['am_id'])) {
							$is_am = true;
						}
						elseif ($is_am && isset($this->request->data['ProjectAdmin']['am_id']) && is_array($this->request->data['ProjectAdmin']['am_id']) && !in_array($project_admin['admin_id'], $this->request->data['ProjectAdmin']['am_id'])) {
							$is_am = false;
						}
						
						$this->Project->ProjectAdmin->create();
						$this->Project->ProjectAdmin->save(array('ProjectAdmin' => array(
							'id' => $project_admin['id'],
							'is_pm' => $is_pm,
							'is_am' => $is_am,
						)), true, array('is_am', 'is_pm'));
						
						$index = array_search($project_admin['admin_id'], $admin_ids);
						if ($index !== FALSE) {
							unset($admin_ids[$index]);
						}
					}
				}
				
				// Add the new project admins if any
				if (!empty($admin_ids)) {
					foreach ($admin_ids as $admin_id) {
						$this->Project->ProjectAdmin->create();
						$this->Project->ProjectAdmin->save(array('ProjectAdmin' => array(
							'project_id' => $project_id,
							'admin_id' => $admin_id,
							'is_pm' => isset($this->request->data['ProjectAdmin']['pm_id']) && is_array($this->request->data['ProjectAdmin']['pm_id']) && in_array($admin_id, $this->request->data['ProjectAdmin']['pm_id']),
							'is_am' => isset($this->request->data['ProjectAdmin']['am_id']) && is_array($this->request->data['ProjectAdmin']['am_id']) && in_array($admin_id, $this->request->data['ProjectAdmin']['am_id']),
						)));
					}
				}
				
				// for recontact projects; write the flag
				if (isset($this->request->data['Project']['recontact_id']) && !empty($this->request->data['Project']['recontact_id'])) {
					$this->SurveyTools->set_recontact_flag($this->request->data['Project']['recontact_id'], $project_id, $this->current_user);
				}
				elseif (!empty($existing_project['Project']['recontact_id']) && $existing_project['Project']['recontact_id'] != $this->request->data['Project']['recontact_id']) {
					$this->Project->create();
					$this->Project->save(array('Project' => array(
						'id' => $existing_project['Project']['recontact_id'],
						'has_recontact_project' => false
					)), true, array('has_recontact_project')); 
				}
				
				$this->Session->setFlash('We have updated your project.', 'flash_success'); 
				$this->redirect(array('action' => 'dashboard', $project_id)); 
			}
			else {
				$this->Session->setFlash('There was an error updating your project. Please review the fields.', 'flash_error');			
			}

    	}

    	if (!$this->request->data) {
        	$this->request->data = $survey_record;
			
			if (!empty($survey_record['ProjectOption'])) {
				foreach ($survey_record['ProjectOption'] as $project_option) {
					if ($project_option['name'] == 'interview_date') {
						$gmt = new DateTimeZone('UTC');							
						$date = new DateTime($project_option['value'], $gmt);							
						$user_timezone = new DateTimeZone($this->current_user['Admin']['timezone']);
						$date->setTimezone($user_timezone);
						$interview_date = $date->format('Y-m-d H:i:s');
						$this->request->data['ProjectOption'][$project_option['name']] = $interview_date;
					}
					else {
						$this->request->data['ProjectOption'][$project_option['name']] = $project_option['value'];
					}
				}
			}
    	}
		
		$mintvine_group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'mintvine'
			)
		));
		$clients = $this->Client->find('list', array(
			'conditions' => array(
				'Client.group_id' => array($mintvine_group['Group']['id'], $survey_record['Project']['group_id']),
				'Client.deleted' => false
			),
			'order' => 'Client.client_name ASC'
		));
		$countries = $this->GeoCountry->returnAsList();
		$languages = Utils::language_codes();
		
		$this->loadModel('Role');
		$account_managers = $this->Role->get_administrators(array('account_managers', 'sales_managers'));
		$project_managers = $this->Role->get_administrators(array('project_managers'));
		$selected_pms = $selected_ams = array();
		if (!empty($survey_record['ProjectAdmin'])) {
			foreach ($survey_record['ProjectAdmin'] as $project_admin) {
				if ($project_admin['is_pm']) {
					$selected_pms[] = $project_admin['admin_id'];
				}
				if ($project_admin['is_am']) {
					$selected_ams[] = $project_admin['admin_id'];
				}
			}
		}
		
		if ($this->data['Client']['key'] == 'typeform') {
			if (!isset($this->data['ProjectOption']['typeform_nonce'])) {
				$nonce = String::uuid();
				$this->Project->ProjectOption->create();
				$this->Project->ProjectOption->save(array('ProjectOption' => array(
					'project_id' => $project_id,
					'name' => 'typeform_nonce', 
					'value' => $nonce
				)));
				$this->set('typeform_nonce', $nonce);
			}
			else {
				$this->set('typeform_nonce', $this->data['ProjectOption']['typeform_nonce']);
			}
		}
		
		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.id' => $survey_record['Project']['group_id']
			)
		));		
		$groups = $this->Group->find('list', array(
			'fields' => array('id', 'key'),
			'conditions' => array(
				'Group.performance_checks' => true
			)
		));
		
		$this->set(compact('clients', 'countries', 'fed_survey', 'country', 'languages', 'project_staff', 'account_managers', 'project_managers', 'selected_pms', 'selected_ams', 'group', 'groups'));
	}
	
	public function get_prj_info() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$project = $this->Project->find('first', array(
				'fields' => array(
					'Project.user_payout', 'Project.est_length', 'Project.quota', 'Project.client_id', 'Project.group_id'
				),
				'conditions' => array(
					'Project.id' => $this->request->data['project_id']
				),
				'contain' => array('ProjectAdmin')
			));
			if (!$this->Admins->can_access_project($this->current_user, $project)) {
				return new CakeResponse(array('status' => '401'));
			}
		
    		return new CakeResponse(array(
				'body' => json_encode(array(
					'user_payout' => $project['Project']['user_payout'],
					'est_length' => $project['Project']['est_length'],
					'est_quantity' => $project['Project']['quota'],
					'client_id' => $project['Project']['client_id']
				)), 
				'type' => 'json',
				'status' => '201'
			));
    	}
	}
	
	public function prescreeners($project_id) {
		if (!$this->Admins->can_access_project($this->current_user, $project_id)) {
			$this->Session->setFlash('You are not authorized to access this feature.', 'flash_error');
			$this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $project_id));
		}
		
		$project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $project_id,
			),
			'recursive' => -1
		));
		$current_prescreeners = $this->Prescreener->find('all', array(
			'conditions' => array(
				'Prescreener.survey_id' => $project_id,
			),
			'order' => 'Prescreener.id ASC'
		));
		
		if ($this->request->is('put') || $this->request->is('post')) {
			$prescreener_list = array();
			if ($current_prescreeners) {
				foreach ($current_prescreeners as $prescreener) {
					$prescreener_list[$prescreener['Prescreener']['question']] = $prescreener['Prescreener']['id'];
				}
			}
			
			$questions = $this->data['Prescreener']['question'];
			$answers = $this->data['Prescreener']['answers'];
			foreach ($questions as $key => $question) {
				$questions[$key] = $question = trim($question);
				$answer = trim($answers[$key]);
				if (empty($question) || empty($answer)) {
					unset($questions[$key]);
					continue;
				}
				$this->Prescreener->create();
				if (!array_key_exists($question, $prescreener_list)) {
					$this->Prescreener->save(array('Prescreener' => array(
						'survey_id' => $project_id,
						'question' => $question,
						'answers' => $answer
					)));
				}
				else {
					$this->Prescreener->save(array('Prescreener' => array(
						'id' => $prescreener_list[$question],
						'question' => $question,
						'answers' => $answer
					)));
				}
			}
			
			// delete removed questions
			foreach ($prescreener_list as $question => $prescreener_id) {
				if (!in_array($question, $questions)) {
					$this->Prescreener->delete($prescreener_id);
				}
			}
			
			// update project's prescreen_type
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project_id,
				'prescreen_type' => $this->data['Prescreener']['prescreen_type']
			)), true, array('prescreen_type'));
			
			$this->Session->setFlash('Your prescreener questions have been saved.', 'flash_success');
			$this->redirect(array('action' => 'prescreeners', $project_id));
		}
		else {
			if ($current_prescreeners) {
				foreach ($current_prescreeners as $prescreener) {
					$this->request->data['Prescreener']['question'][] = $prescreener['Prescreener']['question'];
					$this->request->data['Prescreener']['answers'][] = $prescreener['Prescreener']['answers'];
				}
				$this->request->data['Prescreener']['prescreen_type'] = $project['Project']['prescreen_type'];
			}
			
		}
		$this->set(compact('project'));
	}
	
	public function complete_analysis($project_id) {
		$project = $this->Project->find('first', array(
			'contain' => array('ProjectAdmin'),
			'conditions' => array(
				'Project.id' => $project_id
			)
		));
		if (!$project) {
		   	throw new NotFoundException(__('Invalid survey'));
		}
		
		if (!$this->Admins->can_access_project($this->current_user, $project)) {
			$this->Session->setFlash('You are not authorized to access that project.', 'flash_error');
			$this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}
		
		$completes = $this->SurveyComplete->find('list', array(
			'fields' => array('id', 'hash'),
			'conditions' => array(
				'SurveyComplete.survey_id' => $project_id,
			)
		));
		
		if (empty($completes)) {
			$this->Session->setFlash('You have not yet listed any accepted hashes from the client.', 'flash_error');
			$this->redirect(array('action' => 'complete', $project_id));
		}
		
		
		if ($this->request->is('put') || $this->request->is('post')) {
			$survey_visits = $this->SurveyVisit->find('all', array(
				'fields' => array('id', 'partner_id', 'hash'),
				'conditions' => array(
					'SurveyVisit.type' => SURVEY_COMPLETED,
					'SurveyVisit.survey_id' => $project_id
				)
			));
		
			$partners = array();
			if ($survey_visits) {
				foreach ($survey_visits as $survey_visit) {
					$partner_id = $survey_visit['SurveyVisit']['partner_id'];
					if (!isset($partners[$partner_id])) {
						$partners[$partner_id] = array('reported' => '0', 'confirmed' => '0');
					}
					$partners[$partner_id]['reported']++;
					if (in_array($survey_visit['SurveyVisit']['hash'], $completes)) {
						$partners[$partner_id]['confirmed']++;
					}
				}
			}
			if (!empty($partners)) {
				foreach ($partners as $partner_id => $data) {
					$this->ClientReport->create();
					$client_report = $this->ClientReport->find('first', array(
						'conditions' => array(
							'ClientReport.partner_id' => $partner_id,
							'ClientReport.survey_id' => $project_id
						)
					));
					if ($client_report) {
						$this->ClientReport->save(array('ClientReport' => array(
							'id' => $client_report['ClientReport']['id'],
							'reported' => $data['reported'],
							'confirmed' => $data['confirmed']
						)), true, array('reported', 'confirmed'));
					}
					else {
						$this->ClientReport->save(array('ClientReport' => array(
							'reported' => $data['reported'],
							'confirmed' => $data['confirmed'],
							'partner_id' => $partner_id,
							'survey_id' => $project_id
						)));
					}
				}
			}
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project_id,
				'complete_client_report' => true
			)), true, array('complete_client_report')); 
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project_id,
				'user_id' => $this->current_user['Admin']['id'],
				'type' => 'client_report.generated',
				'description' => 'Client complete report uploaded',
			)));
			
			$this->Session->setFlash('Your client report has been generated.', 'flash_success');
			$this->redirect(array('action' => 'dashboard', $project_id));
		}
		$this->set(compact('project'));
	}
	
	public function complete($project_id) {
		$project = $this->Project->find('first', array(
			'contain' => array('ProjectAdmin'),
			'conditions' => array(
				'Project.id' => $project_id
			),
			'recursive' => -1
		));
		if (!$project) {
		   	throw new NotFoundException(__('Invalid survey'));
		}
		
		if (!$this->Admins->can_access_project($this->current_user, $project)) {
			$this->Session->setFlash('You are not authorized to access that project.', 'flash_error');
			$this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}
		
		$this->loadModel('SurveyComplete');
		$completes = $this->SurveyComplete->find('list', array(
			'fields' => array('id', 'hash'),
			'conditions' => array(
				'SurveyComplete.survey_id' => $project_id
			)
		));
		
		if ($this->request->is('put') || $this->request->is('post')) {
			$msg = '';
			if (isset($this->request->data['Project']['clear']) && $this->request->data['Project']['clear'] == 1) {
				$msg = 'Your previously imported hashes have been successfully deleted.<br \>';
				$survey_completes = $this->SurveyComplete->find('all', array(
					'recursive' => -1,
					'conditions' => array(
						'SurveyComplete.survey_id' => $project_id
					),
					'fields' => array('SurveyComplete.id')
				));
				if ($survey_completes) {
					foreach ($survey_completes as $survey_complete) {
						$this->SurveyComplete->delete($survey_complete['SurveyComplete']['id']); 
					}
				}
			}
			
			$skipped_hashes = array();
			$hashes = preg_split("/(\\n| )/", $this->request->data['Project']['hashes']);
			if (!empty($hashes)) {
				foreach ($hashes as $hash) {
					$hash = strtolower(trim($hash));
					
					// validate the presense of 'm'
					if (empty($hash) || strpos($hash, 'm') === false) {
						$skipped_hashes[] = $hash;
						continue;
					}
					
					// validate project_id
					$arr_hash = explode('m', $hash);
					if ($arr_hash[0] != $project_id) {
						$skipped_hashes[] = $hash;
						continue;
					}
					
					// validate the length of the hash
					unset($arr_hash[0]);
					$hash_part = implode('m', $arr_hash);
					if (strlen($hash_part) != 27) {
						$skipped_hashes[] = $hash;
						continue;
					}
					
					// routers allow for multiple of the same hashes to be input
					if (!$project['Project']['router']) {
						$count = $this->SurveyComplete->find('count', array(
							'conditions' => array(
								'SurveyComplete.survey_id' => $project_id,
								'SurveyComplete.hash' => $hash
							)
						));
						if ($count > 0) {
							continue;
						}
					}
					
					$this->SurveyComplete->create();
					$this->SurveyComplete->save(array('SurveyComplete' => array(
						'survey_id' => $project_id,
						'hash' => $hash,
						'status' => 'imported'
					)));
				}
				
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project_id,
					'user_id' => $this->current_user['Admin']['id'],
					'type' => 'survey_complete.updated',
					'description' => 'Hashes set from client: ' . implode(', ', $hashes),
				)));
				if (empty($skipped_hashes)) {
					$msg .= 'Your new hashes have been successfully imported.';
					$this->Session->setFlash($msg, 'flash_success');
					$this->redirect(array('action' => 'complete_analysis', $project_id));
				}
				else {
					$msg .= 'The following hashes are invalid and could not be saved. <br />'. implode('<br />', $skipped_hashes);
					$msg .= '<br /><br />Click here to <a class="btn btn-mini btn-success" href="/surveys/complete_analysis/'.$project_id.'">Generate client report</a>';
					$this->Session->setFlash($msg, 'flash_error');
					$this->redirect(array('action' => 'complete', $project_id));
				}
			}
		}
		
		$this->set(compact('completes', 'project'));
	}
	
	function static_link_csv_generator() {
		if ($this->request->is('put') || $this->request->is('post')) {
			$this->Project->setValidation('static_list_generator');
			$this->Project->set($this->request->data);
			if ($this->Project->validates()) {
				$this->request->data['Project']['hashes'] = explode("\r\n", $this->request->data['Project']['hashes']);
				foreach ($this->request->data['Project']['hashes'] as $hash) {
					$url = str_ireplace('{{ID}}', $hash, $this->request->data['Project']['url']);
					$data[] = array($url => $hash);
				}				
				$this->set(compact('data'));
				$this->layout = null;
				$this->render('static_link_csv');
			}
			else {	
				$this->Session->setFlash('Please insert required fields.', 'flash_error');
			}
		}
	}

	public function ajax_save_pushed($status = null) {
		App::import('Model', 'ProjectOption');
		$this->ProjectOption = new ProjectOption;
		$errors = array();
		$save = false;
		if ($this->request->is('post') || $this->request->is('put')) {
			$project_id = $this->request->data['ProjectOption']['project_id'];
			if (!$this->Admins->can_access_project($this->current_user, $project_id)) {
				return new CakeResponse(array('status' => '401'));
			}
			
			unset($this->request->data['ProjectOption']['project_id']);
			$project_options = $this->ProjectOption->find('all', array(
				'conditions' => array(
					'project_id' => $project_id,
					'name' => array('pushed', 'pushed_user_id', 'pushed_email_subject', 'pushed_email_template')
				)
			));
			
			$this->request->data['ProjectOption']['pushed'] = $status;
			$this->request->data['ProjectOption']['pushed_user_id'] = '0';
			if (!empty($project_options)) {
				foreach ($project_options as $project_option) {					
					$this->Project->ProjectOption->create();
					$save = $this->Project->ProjectOption->save(array('ProjectOption' => array(
						'id' => $project_option['ProjectOption']['id'],
						'value' => $this->request->data['ProjectOption'][$project_option['ProjectOption']['name']]
					)), true, array('value'));
					unset($this->request->data['ProjectOption'][$project_option['ProjectOption']['name']]);
				}
			}
			if (!empty($this->request->data['ProjectOption'])) {
				foreach ($this->request->data['ProjectOption'] as $key => $val) {
					if (empty($val)) {
						continue;
					}
					$this->Project->ProjectOption->create();
					$save = $this->Project->ProjectOption->save(array('ProjectOption' => array(
						'project_id' => $project_id,
						'name' => $key,
						'value' => $val
					)));
				}
			}
		}
		
		if (!$save) {
			$validation_errors = $this->ProjectOption->validationErrors;
			if (!empty($validation_errors)) {
				foreach ($validation_errors as $error) {
					$errors[] = current($error);
				}
			}
			else {
				$errors[] = 'Project Option is already exists.';
			}
		}
		else {
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project_id,
				'user_id' => $this->current_user['Admin']['id'],
				'type' => 'project.updated',
				'description' => 'Project options pushed'
			)));
		}
    	return new CakeResponse(array(
			'body' => json_encode(array(
				'errors' => implode(' ', $errors)
			)), 
			'type' => 'json',
			'status' => isset($save) && $save ? '201': '400'
		));
	}
	
	public function ajax_test_mode($project_id) {
		if (!$this->Admins->can_access_project($this->current_user, $project_id)) {
			return new CakeResponse(array('status' => '401'));
		}
		
		$project = $this->Project->findById($project_id);
		if ($this->request->is('put') || $this->request->is('post')) {
			$project['Project']['test_mode'] = !$project['Project']['test_mode'];
			
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project_id, 
				'test_mode' => $project['Project']['test_mode']
			)), true, array('test_mode'));
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project_id,
				'user_id' => $this->current_user['Admin']['id'],
				'type' => 'project.updated',
				'description' => 'Test mode '.($project['Project']['test_mode'] ? 'activated': 'deactivated'),
			)));
		}
		
    	return new CakeResponse(array(
			'body' => json_encode(array(
				'test_mode' => $project['Project']['test_mode'],
			)), 
			'type' => 'json',
			'status' => '201'
		));
	}
	
	function viper_post() {
		if ($this->request->is(array('put', 'post'))) {
			if (!empty($this->request->data['project_id']) && !empty($this->request->data['viper_id'])) {
				$project = $this->Project->find('first', array(
					'conditions' => array(
						'Project.id' => $this->request->data['project_id']
					),
					'contain' => array('ProjectAdmin')
				));
				if (!$this->Admins->can_access_project($this->current_user, $project)) {
					return new CakeResponse(array('status' => '401'));
				}
				
				if ($project && empty($project['Project']['viper_id'])) {
					$project_option = $this->ProjectOption->find('first', array(
						'conditions' => array(
							'ProjectOption.project_id' => $this->request->data['project_id'],
							'ProjectOption.name' => 'viper_id'
						)
					));
					if (!empty($project_option)) {
						$this->ProjectOption->create();
						$this->ProjectOption->save(array(
							'ProjectOption' => array(
								'id' => $project_option['ProjectOption']['id'],
								'value' => $this->request->data['viper_id']
							)
						));
					}
					else {
						$this->ProjectOption->create();
						$this->ProjectOption->save(array(
							'ProjectOption' => array(
								'project_id' => $this->request->data['project_id'],
								'name' => 'viper_id',
								'value' => $this->request->data['viper_id']
							)
						));
					}
					
					$this->Project->save(array(
						'id' => $this->request->data['project_id'],
						'viper_id' => $this->request->data['viper_id']
					), true, array('viper_id'));
					
					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $this->request->data['project_id'],
						'user_id' => $this->current_user['Admin']['id'],
						'type' => 'project.simpleset',
						'description' => '#' . $this->request->data['viper_id'],
					)));
					
					return new CakeResponse(array(
						'body' => json_encode(array(
							'message' => 'Success.',
						)), 
						'type' => 'json',
						'status' => '201'
					));	
				}
			}
		}
		
		throw new NotFoundException();
	}
	
	public function project_logs($project_id) {
		if (empty($project_id)) {
			$this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}
		
		if (!$this->Admins->can_access_project($this->current_user, $project_id)) {
			$this->Session->setFlash('You are not authorized to access that project.', 'flash_error');
			$this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}
		
		$paginate = array(
			'ProjectLog' => array(
				'limit' => '50',
				'order' => 'ProjectLog.id DESC'
			)
		);
		$paginate['ProjectLog']['conditions']['ProjectLog.project_id'] = $project_id;
		if (!isset($this->request->query['all']) || $this->request->query['all'] == false) {
			$paginate['ProjectLog']['conditions']['ProjectLog.type !='] = array('updated', 'survey.updated', 'project.updated', 'qualification.updated');
		}
		
		$this->paginate = $paginate;	
		$this->set('project_logs', $this->paginate('ProjectLog'));
	}
	
	public function group_logs($group_id) {
		if (empty($group_id)) {
			$this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}
		
		if (!$this->current_user['AdminRole']['admin']) {
			$this->Session->setFlash('Only Administrator can access this feature.', 'flash_error');
			$this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}
		
		$paginate = array(
			'ProjectLog' => array(
				'limit' => '50',
				'order' => 'ProjectLog.id DESC',
				'conditions' => array(
					'Project.group_id' => $group_id
				)
			)
		);
		$this->paginate = $paginate;
		$this->set('project_logs', $this->paginate('ProjectLog'));
		
	}
	
	public function rfg_log($rfg_id = false) {
		if (!$rfg_id) {
			$this->Session->setFlash('Provide rfg_id please.', 'flash_error');
		}
		else{
			$settings = $this->Setting->find('list', array(
				'fields' => array('Setting.name', 'Setting.value'),
				'conditions' => array(
					'Setting.name' => array('rfg.host', 'rfg.apid', 'rfg.secret'),
					'Setting.deleted' => false
				)
			));
			$command = "{ 'command' : 'livealert/log/1' , 'rfg_id' : '" . $rfg_id . "' }";
			$logs = $this->SurveyTools->execute_rfg_api($command, $settings);
			if ($logs && !in_array($logs['result'], array(1, 2))) {
				$this->set(compact('logs'));
			}
			else {
				$this->Session->setFlash('Rfg api response not found.', 'flash_error');
			}
		}
	}
	
	function ss_redirect($project_params) {
		if (empty($project_params)) {
			$this->redirect('/');
		}
		$this->redirect(HOSTNAME_VIPER. '/surveys/ss_redirect/' . $project_params . '?t=' . time() . '&key='. sha1('brandedredirect'));
	}
	
	function get_respondents($project_id = null) {
		if (empty($project_id)) {
			throw new NotFoundException();
		}
		
		$project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $project_id,
				'Project.status' => PROJECT_STATUS_CLOSED
			),
			'recursive' => -1,
			'fields' => array(
				'Project.id', 'Project.status', 'Project.prj_name'
			)
		));		
		if (!$project) {
			throw new NotFoundException();
		}
		App::import('Model', 'RespondentReport');
		$this->RespondentReport = new RespondentReport;
		if ($this->request->is(array('put', 'post'))) {	
			$respondents = $this->SurveyUserVisit->find('all', array(
				'conditions' => array(
					'SurveyUserVisit.survey_id' => $project_id,
					'SurveyUserVisit.status' => $this->request->data['Survey']['status']
				)
			));
			if ($respondents) {
				$respondentReportSource = $this->RespondentReport->getDataSource();
				$respondentReportSource->begin();
				$this->RespondentReport->create();
				$this->RespondentReport->save(array('RespondentReport' => array(
					'survey_id' => $project['Project']['id'],
					'status' => 'queued',
					'user_id' => $this->current_user['Admin']['id'],
					'filters' => json_encode($this->request->data['Survey']['status'])
				)));
				$respondent_report_id = $this->RespondentReport->getInsertId();
				$respondentReportSource->commit();
				
				$query = ROOT.'/app/Console/cake report get_respondents '.$respondent_report_id;
				$query.= " > /dev/null &"; 
				exec($query, $output);
				
				$this->Session->setFlash('Report being generated - please wait for 10-15 minutes to dowalond report.', 'flash_success');
				$this->redirect(array('controller' => 'surveys', 'action' => 'get_respondents', $project_id));
			}
			else {
				$this->Session->setFlash('No data found.', 'flash_error');
				$this->redirect(array('controller' => 'surveys', 'action' => 'get_respondents', $project_id));
			}
		}
		
		$this->RespondentReport->bindModel(array(
			'belongsTo' => array(
				'Admin' => array(
					'foreignKey' => 'user_id',
					'fields' => array('id', 'admin_user')
				)
			)
		));
		
		$limit = 50;
		$paginate = array(
			'RespondentReport' => array(
				'conditions' => array(
					'RespondentReport.survey_id' => $project_id
				),
				'contain' => array(
					'Admin'
				),
				'limit' => $limit,
				'order' => 'RespondentReport.id DESC',
			)
		);
		$this->paginate = $paginate;		
		$this->set('respondents', $this->paginate('RespondentReport'));
		$this->set('project', $project);
	}
	
	function ajax_check_respondent_report($report_id) {
		App::import('Model', 'RespondentReport');
		$this->RespondentReport = new RespondentReport;
		$report = $this->RespondentReport->findById($report_id);
    	return new CakeResponse(array(
			'body' => json_encode(array(
				'status' => $report['RespondentReport']['status'],
				'file' => Router::url(array('controller' => 'surveys', 'action' => 'download_respondent_report', $report['RespondentReport']['id']))
			)), 
			'type' => 'json',
			'status' => '201'
		));
	}
	
	function download_respondent_report($report_id) {
		if(empty($report_id)) {
			throw new NotFoundException();
		}
		App::import('Model', 'RespondentReport');
		$this->RespondentReport = new RespondentReport;
		$report = $this->RespondentReport->find('first', array(
			'conditions' => array(
				'RespondentReport.id' => $report_id
			),
			'fields' => array(
				'id', 'status', 'path'
			)
		));
		
		if ($report) {
			if ($report['RespondentReport']['status'] == 'complete') {
				$settings = $this->Setting->find('list', array(
					'fields' => array('name', 'value'),
					'conditions' => array(
						'Setting.name' => array(
							's3.access',
							's3.secret',
							's3.bucket',
							's3.host'
						),
						'Setting.deleted' => false
					)
				));
				
				CakePlugin::load('Uploader');
				App::import('Vendor', 'Uploader.S3');
				
				$file = $report['RespondentReport']['path'];
							
				// we store with first slash; but remove it for S3
				if (substr($file, 0, 1) == '/') {
					$file = substr($file, 1, strlen($file)); 
				}
				
				$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);
				$url = $S3->getAuthenticatedURL($settings['s3.bucket'], $file, 3600, false, false);
				
				$this->redirect($url);
			}
			else {
				$this->Session->setFlash('A report is already being generated - please wait until it is done.', 'flash_error');
				$this->redirect(array(
					'controller' => 'surveys',
					'action' => 'get_respondents',
					$report['RespondentReport']['survey_id']
				));
			}
		}
		else {
			throw new NotFoundException();
		}
	}
	
	function raw($project_log_id = null) {
		if (empty($project_log_id)) {
			throw new NotFoundException();
		}
		
		$project_log = $this->ProjectLog->find('first', array(
			'conditions' => array(
				'ProjectLog.id' => $project_log_id
			),
			'fields' => array('id', 'failed_data'),
			'recursive' => -1
		));
		$this->layout = null;
		$this->set(compact('project_log'));
	}
	
	public function ajax_spectrum_api_json($spectrum_survey_id) {
		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => array('spectrum.host', 'spectrum.supplier_id', 'spectrum.access_token'),
				'Setting.deleted' => false
			)
		));
		
		$params = array(
			'supplier_id' => $settings['spectrum.supplier_id'],
			'access_token' => $settings['spectrum.access_token']
		);
		$header = array('header' => array(
			'Content-Type' => 'application/x-www-form-urlencoded',
			'cache-control' => 'no-cache'
		));
		
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false
		));
		
		$url = $settings['spectrum.host'].'/suppliers/surveys';
		$response = $http->post($url, $params, $header);
		$response_body = json_decode($response->body, true);
		if ($response->code != 200 || empty($response_body['apiStatus']) || $response_body['apiStatus'] =! 'success') {
			return new CakeResponse(array(
				'status' => 200,
				'body' => 'Api request failed for '. $url
			));
		}
		
		$spectrum_survey_json = false;
		if (!empty($response_body['surveys'])) {
			foreach ($response_body['surveys'] as $survey) {
				if ($survey['survey_id'] == $spectrum_survey_id) {
					$spectrum_survey_json = json_encode($survey, JSON_PRETTY_PRINT);
					break;
				}
			}
		}
		
		if (!$spectrum_survey_json) {
			return new CakeResponse(array(
				'status' => 200,
				'body' => 'Survey not found in current allocation.',
			));
		}
		
		$params['survey_id'] = $spectrum_survey_id;
		$url = $settings['spectrum.host'].'/suppliers/surveys/qualifications-quotas';
		$response = $http->post($url, $params, $header);
		$response_body = json_decode($response->body, true);
		if ($response->code != 200 || empty($response_body['apiStatus']) || $response_body['apiStatus'] =! 'Success') {
			return new CakeResponse(array(
				'status' => 200,
				'body' => 'Api request failed for '. $url,
			));
		}
		$qualifications_and_quotas_json = json_encode($response_body, JSON_PRETTY_PRINT);
		$this->set(compact('spectrum_survey_json', 'qualifications_and_quotas_json'));
	}
	
	public function clone_project($project_id = null) {
		$survey_record = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $project_id
			),
			'contain' => array(
				'Client',
				'Group',
				'SurveyVisitCache',
				'ProjectAdmin'
			)
		));
		if (!$survey_record) {
		   	throw new NotFoundException(__('Invalid survey'));
		}
		
		if ($this->request->is('put') || $this->request->is('post')) {
			$project = array(
				'Project' => array(
					'group_id' => $survey_record['Project']['group_id'],
					'prj_name' => $survey_record['Project']['prj_name'],
					'client_id' => $survey_record['Project']['client_id'],
					'date_created' => date(DB_DATETIME),
					'client_rate' => $survey_record['Project']['client_rate'],
					'award' => $survey_record['Project']['award'],
					'partner_rate' => $survey_record['Project']['partner_rate'],
					'user_payout' => $survey_record['Project']['award'] / 100,
					'nq_award' => $survey_record['Project']['nq_award'],
					'quota' => $survey_record['Project']['quota'],
					'router' => $survey_record['Project']['router'],
					'singleuse' => $survey_record['Project']['singleuse'],
					'landerable' => $survey_record['Project']['landerable'],
					'bid_ir' => $survey_record['Project']['bid_ir'],
					'est_length' => $survey_record['Project']['est_length'],
					'prj_description' => $survey_record['Project']['prj_description'],
					'status' => PROJECT_STATUS_OPEN,
					'active' => false,
					'client_project_id' => $survey_record['Project']['client_project_id'],
					'survey_name' => $survey_record['Project']['survey_name'],
					'ignore_autoclose' => $survey_record['Project']['ignore_autoclose'],
					'country' => $survey_record['Project']['country'],
					'language' => $survey_record['Project']['language'],
					'description' => $survey_record['Project']['description'],
					'minimum_time' => $survey_record['Project']['minimum_time'],
					'pool' => $survey_record['Project']['pool'],
					'dedupe' => $survey_record['Project']['dedupe'],
					'client_survey_link' => $survey_record['Project']['client_survey_link'],
					'client_end_action' => $survey_record['Project']['client_end_action'],
					'public' => $survey_record['Project']['public'],
					'prescreen' => $survey_record['Project']['prescreen'],
					'desktop' => $survey_record['Project']['desktop'],
					'mobile' => $survey_record['Project']['mobile'],
					'tablet' => $survey_record['Project']['tablet'],
					'address_required' => $survey_record['Project']['address_required']
				),
				'ProjectAdmin' => $survey_record['ProjectAdmin']	
			);
			
			$projectSource = $this->Project->getDataSource();
			$projectSource->begin();
			$this->Project->create();
			$save = $this->Project->save($project);
			if ($save) {	
				$project_id_clone = $this->Project->getInsertId();
				$projectSource->commit();
				
				// set up admins
				if (!empty($survey_record['ProjectAdmin'])) {
					foreach ($survey_record['ProjectAdmin'] as $project_admin) {
						$this->Project->ProjectAdmin->create();
						$this->Project->ProjectAdmin->save(array('ProjectAdmin' => array(
							'project_id' => $project_id_clone,
							'admin_id' => $project_admin['admin_id'],
							'is_pm' => $project_admin['is_pm'],
							'is_am' => $project_admin['is_am'],
						)));
					}
				}
				
				// add mintvine as a default partner on all projects
				$partner = $this->Partner->find('first', array(
					'conditions' => array(
						'Partner.key' => array('mintvine'),
						'Partner.deleted' => false
					),
					'fields' => array('Partner.id', 'Partner.key')
				));					
				$complete_url = HOSTNAME_WWW.'/surveys/complete/{{ID}}/'.($survey_record['Project']['client_end_action'] == 's2s' ? '?s2s=1': '');
				$nq_url = HOSTNAME_WWW.'/surveys/nq/{{ID}}/'.($survey_record['Project']['client_end_action'] == 's2s' ? '?s2s=1': '');
				$oq_url = HOSTNAME_WWW.'/surveys/oq/{{ID}}/'.($survey_record['Project']['client_end_action'] == 's2s' ? '?s2s=1': '');
				$pause_url = HOSTNAME_WWW.'/surveys/paused/'.($survey_record['Project']['client_end_action'] == 's2s' ? '?s2s=1': '');
				$fail_url = HOSTNAME_WWW.'/surveys/sec/{{ID}}/'.($survey_record['Project']['client_end_action'] == 's2s' ? '?s2s=1': '');
				
				$this->Project->SurveyPartner->create();
				$this->Project->SurveyPartner->save(array('SurveyPartner' => array(
					'survey_id' => $project_id_clone,
					'partner_id' => $partner['Partner']['id'],
					'rate' => round($survey_record['Project']['award'] / 100, 2),
					'complete_url' => $complete_url,
					'nq_url' => $nq_url,
					'oq_url' => $oq_url,
					'pause_url' => $pause_url,
					'fail_url' => $fail_url,
				)));
				
				// clone prescreeners
				if ($survey_record['Project']['prescreen']) {
					$prescreeners = $this->Prescreener->find('all', array(
						'conditions' => array(
							'Prescreener.survey_id' => $project_id,
						),
						'order' => 'Prescreener.id ASC'
					));
					if ($prescreeners) {
						foreach ($prescreeners as $prescreen) {
							$this->Prescreener->create();
							$this->Prescreener->save(array('Prescreener' => array(
								'survey_id' => $project_id_clone,
								'question' => $prescreen['Prescreener']['question'],
								'answers' => $prescreen['Prescreener']['answers']
							)));
						}
					}
				}
				
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'project_id' => $project_id,
					'name' => 'cloned',
					'value' => $project_id
				)));
				
			}
			else {
				$projectSource->commit();
			}
			
			if ($save) {				
				// write project log
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project_id_clone,
					'user_id' => $this->current_user['Admin']['id'],
					'type' => 'survey.created'
				)));
				
				$this->Session->setFlash('Your project has been cloned.', 'flash_success'); 
				$this->redirect(array('action' => 'dashboard', $project_id_clone)); 
			}
			else {
				$this->Session->setFlash('There was an error creating clone for this project. Please review it.', 'flash_error');
			}
		}
		$this->set(compact('survey_record'));	
	}

	public function ajax_get_processing() {
		$project_id = $this->request->data['project_id'];
		$this->Qualification->bindModel(array(
				'hasOne' => array('QualificationStatistic')
		));
		$qualifications = $this->Qualification->find('all', array(
			'conditions' => array(
				'Qualification.project_id' => $project_id,
				'Qualification.parent_id' => null,
				'Qualification.deleted is null'
			),
			'order' => 'Qualification.id ASC'
		));
		$processings = array();
		foreach ($qualifications as $qualification) {
			$processings[] = $qualification['Qualification'] + $qualification['QualificationStatistic'];
		}
		return new CakeResponse(array(
			'body' => json_encode(array(
				'processings' => $processings
			)),
			'type' => 'json',
			'status' => '201'
		));
	}

	public function ajax_edit_quotas($qualification_id) {
		$qualification = $this->Qualification->find('first', array(
			'fields' => array('Qualification.id', 'Qualification.project_id', 'Qualification.name', 'Qualification.quota', 'Qualification.cpi', 'Qualification.award'),
			'conditions' => array(
				'Qualification.id' => $qualification_id,
				'Qualification.deleted is null'
			)
		));
		if ($this->request->is('post')) {
			$request_data = $this->request->data;
			$this->Qualification->save(array('Qualification' => $request_data));

			$diff = array_diff($request_data, $qualification['Qualification']);
			if (count($diff) > 0) {
				$log = '';
				foreach ($diff as $key => $val) {
					$log .= $key . ' was updated from "' . $qualification['Qualification'][$key] . '" to "' . $val . '", ';
				}
				$log = substr($log, 0, -2);
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $qualification['Qualification']['project_id'],
					'user_id' => $this->current_user['Admin']['id'],
					'type' => 'qualification.updated',
					'description' => 'Qualification #' . $qualification_id . ' updated: ' . $log
				)));
			}

			$qualification = $this->Qualification->findById($qualification_id);
			return new CakeResponse(array(
				'body' => json_encode($qualification['Qualification']),
				'type' => 'json',
				'status' => '201'
			));
		}

		$qualification_info = $qualification['Qualification'];
		$this->set(compact('qualification_info'));
	}

	public function ajax_view_qualification($qualification_id) {
		$qualification = $this->Qualification->find('first', array(
			'fields' => array('Qualification.query_json', 'Qualification.additional_json', 'Qualification.name'),
			'conditions' => array(
				'Qualification.id' => $qualification_id,
				'Qualification.deleted is null'
			),
			'recursive' => -1
		));
		$json = json_decode($qualification['Qualification']['query_json'], true);
		$country_language_mapping = array(
			6 => 'CA', // Canada
			8 => 'GB', // UK
			9 => 'US' // US
		);
		ksort($json['qualifications']);
		$qualifications = array();
		foreach ($json['qualifications'] as $question_id => $answer_ids) {
			if ($json['partner'] == 'lucid') {
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
					if ($question_id == 98) {
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
						if ($lucid_zips) {
							foreach ($lucid_zips as $lucid_zip) {
								$questions['Answer'][]['AnswerText'] = array('text' => $lucid_zip['LucidZip']['county']);
							}	
						}
						else {
							$questions['Answer'][]['AnswerText'] = array('text' => $answer_ids[0] . ' - ' . $answer_ids[count($answer_ids) - 1]);
						}
					}
					else {
						$questions['Answer'][]['AnswerText'] = array('text' => $answer_ids[0] . ' - ' . $answer_ids[count($answer_ids) - 1]);
					}
				}
				$qualifications[] = $questions;
			}
		}
		$additional_info = array();
		$additional_json = json_decode($qualification['Qualification']['additional_json'], true);
		if (!empty($additional_json)) {
			$exclude_info = array(
				'user_ids' => array('id' => 'exclude_user_id', 'label' => 'Exclude User IDs'),
				'completes_from_project' => array('id' => 'existing_complete_project_id', 'label' => 'Exclude Completes from Project(s)'),
				'clicks_from_project' => array('id' => 'existing_click_project_id', 'label' => 'Exclude Clicks from Project(s)'),
			);
			if (isset($additional_json['append'])) {
				$additional_info['user_id']['answers'] = $additional_json['append']['user_ids'];
				$additional_info['user_id']['label'] = 'Target Additional User IDs';
			}
			if (isset($additional_json['exclude'])) {
				foreach ($additional_json['exclude'] as $key => $answers) {
					$additional_info[$exclude_info[$key]['id']] = array(
						'answers' => $answers,
						'label' => $exclude_info[$key]['label']
					);
				}
			}
		}
		$qualification_name = $qualification['Qualification']['name'];
		$partner = $json['partner'];
		$this->set(compact('qualifications', 'additional_info', 'partner', 'qualification_name', 'json'));
	}

	public function edit_user_qualifications($qualification_id = null) {
		// getting the form
		if (!is_null($qualification_id)) {
			$qualification = $this->Qualification->findById($qualification_id);
		}
		else { // on saving the form
			$qualification = $this->Qualification->findById($this->request->data['Qualification']['id']);
		}
		if ($this->request->is('post') || $this->request->is('put')) {
			$additional_json = null;
			if (trim($this->request->data['user_id']) != '') {
				$user_ids = explode("\n", trim($this->request->data['user_id']));
				array_walk($user_ids, create_function('&$val', '$val = trim($val);'));
				if (!empty($user_ids)) {
					foreach ($user_ids as $key => $user_id) {
						if (strpos($user_id, 'm') === false) {
							if (strlen($user_id) <= 5) {
								$user_ids[$key] = $user_id;
							}
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
					$additional_json['append']['user_ids'] = array_unique($user_ids);
				}
			}
			else {
				$additional_json['append']['user_ids'] = array(); 
			}

			if (trim($this->request->data['exclude_user_id']) != '') {
				$exclude_user_ids = explode("\n", trim($this->request->data['exclude_user_id']));
				array_walk($exclude_user_ids, create_function('&$val', '$val = trim($val);'));
				if (!empty($exclude_user_ids)) {
					foreach ($exclude_user_ids as $key => $user_id) {
						if (strpos($user_id, 'm') === false) {
							if (strlen($user_id) <= 5) {
								$exclude_user_ids[$key] = $user_id;
							}
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
					$additional_json['exclude']['user_ids'] = array_unique($exclude_user_ids);
				}
			}
			else {
				$additional_json['exclude']['user_ids'] = array();
			}

			if (trim($this->request->data['existing_complete_project_id']) != '') {
				$existing_complete_project_ids = explode("\n", trim($this->request->data['existing_complete_project_id']));
				array_walk($existing_complete_project_ids, create_function('&$val', '$val = trim($val);'));
				if (!empty($existing_complete_project_ids)) {
					$exclude_complete_project_ids = array();
					foreach ($existing_complete_project_ids as $existing_complete_project_id) {
						$exclude_complete_project_ids[] = MintVine::parse_project_id($existing_complete_project_id);
					}
					$additional_json['exclude']['completes_from_project'] = $exclude_complete_project_ids;
				}
			}
			else {
				$additional_json['exclude']['completes_from_project'] = array();
			}

			if (trim($this->request->data['existing_click_project_id']) != '') {
				$existing_click_project_ids = explode("\n", trim($this->request->data['existing_click_project_id']));
				array_walk($existing_click_project_ids, create_function('&$val', '$val = trim($val);'));
				if (!empty($existing_click_project_ids)) {
					$exclude_click_project_ids = array();
					foreach ($existing_click_project_ids as $existing_click_project_id) {
						$exclude_click_project_ids[] = MintVine::parse_project_id($existing_click_project_id);
					}
					$additional_json['exclude']['clicks_from_project'] = $exclude_click_project_ids;
				}
			}
			else {
				$additional_json['exclude']['clicks_from_project'] = array();
			}
			$this->Qualification->create();
			$saved = $this->Qualification->save(array('Qualification' => array(
				'id' => $qualification['Qualification']['id'],
				'additional_json' => json_encode($additional_json),
				'modified' => date(DB_DATETIME)
			)), true, array('additional_json'));


			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $qualification['Qualification']['project_id'],
				'user_id' => $this->current_user['Admin']['id'],
				'type' => 'qualification.updated',
				'description' => 'Qualification #' . $qualification['Qualification']['id'] . ' updated with changed additional_json.'
			)));

			$this->Session->setFlash('Your qualification has been successfully updated.', 'flash_success');
			return $this->redirect(array('action' => 'dashboard', $qualification['Qualification']['project_id'])); 
		}
		$additional_json = json_decode($qualification['Qualification']['additional_json'], true);
		$this->set(compact('additional_json', 'qualification_id'));
	}
	
	public function ajax_soft_launch($project_id) {
		if (!$this->Admins->can_access_project($this->current_user, $project_id)) {
			return new CakeResponse(array('status' => '401'));
		}
		
		$project = $this->Project->findById($project_id);
		if ($this->request->is('put') || $this->request->is('post')) {
			$project['Project']['soft_launch'] = !$project['Project']['soft_launch'];
			
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project_id, 
				'soft_launch' => $project['Project']['soft_launch']
			)), true, array('soft_launch'));
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project_id,
				'user_id' => $this->current_user['Admin']['id'],
				'type' => 'project.updated',
				'description' => 'Soft launch '.($project['Project']['soft_launch'] ? 'activated': 'deactivated'),
			)));
		}
		
    	return new CakeResponse(array(
			'body' => json_encode(array(
				'soft_launch' => $project['Project']['soft_launch'],
			)), 
			'type' => 'json',
			'status' => '201'
		));
	}

	public function ajax_delete_subquota() {
		if ($this->request->is('post')) {
			$qualification_id = $this->request->data['id'];
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
				'body' => json_encode(array(
					'status' => '1'
				)),
				'type' => 'json',
				'status' => '201'
			));
		}
	}
	
	public function refresh_qualification($id = null) {
		if (!$id) {
			throw new NotFoundException();
		}
		
		$qualification = $this->Qualification->find('first', array(
			'conditions' => array(
				'Qualification.id' => $id,
				'Qualification.deleted is null'
			),
			'recursive' => -1
		));
		if (!$qualification) {
			$this->Session->setFlash('Qualification not found.', 'flash_error');
			$this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}
		
		if (!$this->Admins->can_access_project($this->current_user, $qualification['Qualification']['project_id'])) {
			$this->Session->setFlash('You are not authorized to access that project.', 'flash_error');
			$this->redirect(array('controller' => 'projects', 'action' => 'index'));
		}
		
		if ($qualification['Qualification']['refreshed'] && strtotime($qualification['Qualification']['refreshed']) > strtotime('-1 hour')) {
			$time_remaining = strtotime($qualification['Qualification']['refreshed']) - strtotime('-1 hour');
			$time_remaining = round($time_remaining / 60);
			$this->Session->setFlash('This qualification has been refreshed recently, you can refresh it again, after '.$time_remaining.' minutes.', 'flash_error');
		}
		else {
			$query = ROOT.'/app/Console/cake qualification refresh '.$qualification['Qualification']['id'];
			$query.= " > /dev/null &"; 
			exec($query, $output);
			
			$this->Qualification->create();
			$flag = $this->Qualification->save(array('Qualification' => array(
				'id' => $qualification['Qualification']['id'],
				'refreshed' => date(DB_DATETIME)
			)), array(
				'callbacks' => false,
				'fieldList' => array('refreshed')
			));
			$this->Session->setFlash('Qualification is being refreshed ...', 'flash_success');
		}
		
		$this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $qualification['Qualification']['project_id']));
	}
	
	public function oac_import() {
		if ($this->request->is('post')) {
			if (!$this->request->data['Survey']['file'] 
				|| $this->request->data['Survey']['file']['size'] == 0
				|| $this->request->data['Survey']['file']['error'] > 0) {
				$this->Session->setFlash(__('Please select a valid CSV file'), 'flash_error');
				$this->redirect(array('action' => 'oac_import'));
			}

			$csv_users = Utils::csv_to_array($this->request->data['Survey']['file']['tmp_name']);
			if (empty($csv_users)) {
				$this->Session->setFlash(__('The file is empty.'), 'flash_error');
				$this->redirect(array('action' => 'oac_import'));
			}
			
			$this->loadModel('PartnerUser');
			unset($csv_users[0]);
			$i = 0;
			foreach ($csv_users as $csv_user) {
				$user_id = $csv_user[0];
				$count = $this->User->find('count', array(
					'conditions' => array(
						'User.deleted_on' => null,
						'User.id' => $user_id,
						'User.hellbanned' => false
					)
				));		

				if ($count < 1) {
					continue;
				}
				
				$partner_user = $this->PartnerUser->find('first', array(
					'fields' => array('PartnerUser.id', 'PartnerUser.user_id'),
					'conditions' => array(
						'user_id' => $user_id,
						'partner' => 'oac'
					)
				));
				if ($partner_user) {
					$this->PartnerUser->create();
					$this->PartnerUser->save(array('PartnerUser' => array(
						'id' => $partner_user['PartnerUser']['id'],
						'uid' => trim($csv_user[1]),
					)), true, array('uid'));
				}
				else {
					$this->PartnerUser->create();
					$this->PartnerUser->save(array('PartnerUser' => array(
						'uid' => trim($csv_user[1]),
						'user_id' => trim($csv_user[0]),
						'partner' => 'oac'
					)));
				}
			}
			
			$this->Session->setFlash(__('Data imported successfully!'), 'flash_success');
		}
	}
	
	public function invite_panelists($project_id) {
		$this->Project->bindModel(array('hasMany' => array('Query' => array(
			'className' => 'Query',
			'foreignKey' => 'survey_id'
		))));
		$project = $this->Project->find('first', array(
			'fields' => array('Project.id', 'Project.group_id', 'Project.temp_qualifications', 'Project.award', 'Project.client_rate'),
			'conditions' => array(
				'Project.id' => $project_id
			),
			'contain' => array(
				'Query' => array(
					'fields' => array('id')
				)
			),
			'recursive' => -1
		));
		$mintvine_group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'mintvine'
			)
		));
		
		if (!$this->Admins->can_access_project($this->current_user, $project)) {
			$this->Session->setFlash('You are not authorized to access that project.', 'flash_error');
			return $this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $project_id));
		}
		
		if ($project['Project']['group_id'] != $mintvine_group['Group']['id'] || !empty($project['Query'])) {
			$this->Session->setFlash('You can invite panelists for Mintvine partner, also the project should be QE2 only.', 'flash_error');
			return $this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $project_id));
		}	
		
		if ($this->request->is('put') || $this->request->is('post')) {
			$user_ids = array();
			if (empty($this->request->data['User']['user_id'])) {
				$this->Session->setFlash('Please enter some User IDs (enter one per line).', 'flash_error');
				return $this->redirect(array('controller' => 'surveys', 'action' => 'invite_panelists', $project_id));
			}
			
			$user_ids = explode("\n", trim($this->request->data['User']['user_id']));
			array_walk($user_ids, create_function('&$val', '$val = trim($val);'));
			if ($this->request->data['User']['type'] == 'anon_id') {
				$this->loadModel('PartnerUser');
				$user_ids = $this->PartnerUser->find('list', array(
					'fields' => array('PartnerUser.user_id'),
					'conditions' => array(
						'partner' => 'oac',
						'uid' => $user_ids
					)
				));
			}
			
			$user_ids = $this->User->find('list', array(
				'fields' => array('User.id'),
				'conditions' => array(
					'User.active' => true,
					'User.deleted_on' => null,
					'User.hellbanned' => false,
					'User.id' => $user_ids
				)
			));
			
			if (!empty($user_ids)) {
				$additional_json['append']['user_ids'] = $user_ids;
				$json_query = array(
					'partner' => 'mintvine',
					'qualifications' => array()
				);
				$json_query = json_encode($json_query);
				$qualificationSource = $this->Qualification->getDataSource();
				$qualificationSource->begin();

				$this->Qualification->create();
				$saved = $this->Qualification->save(array('Qualification' => array(
					'project_id' => $project_id,
					'name' => 'Manual Invite',
					'query_hash' => md5($json_query),
					'query_json' => $json_query,
					'additional_json' => json_encode($additional_json),
					'quota' => count($user_ids),
					'total' => count($user_ids),
 					'cpi' => $project['Project']['client_rate'],
					'active' => true,
				)));

				if ($saved) {
					$qualification_id = $this->Qualification->getInsertId();
					$qualificationSource->commit();
					
					foreach ($user_ids as $user_id) {
						$this->SurveyUser->create();
						$this->SurveyUser->save(array('SurveyUser' => array(
							'survey_id' => $project_id,
							'user_id' => $user_id
						))); 
						
						$this->QualificationUser->create();
						$this->QualificationUser->save(array('QualificationUser' => array(
							'qualification_id' => $qualification_id,
							'user_id' => $user_id,
							'award' => $project['Project']['award']
						)));
					}
					
					if (!$project['Project']['temp_qualifications']) {
						$this->Project->create();
						$this->Project->save(array('Project' => array(
							'id' => $project['Project']['id'],
							'temp_qualifications' => true
						)), true, array('temp_qualifications'));
					}

					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $project['Project']['id'],
						'user_id' => $this->current_user['Admin']['id'],
						'type' => 'qualification.created',
						'description' => 'Qualification #' . $qualification_id . ' created.',
					)));
					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $project['Project']['id'],
						'user_id' => $this->current_user['Admin']['id'],
						'type' => 'project.manual.invites',
						'description' => count($user_ids).' manual invites issued',
					)));
					$this->Session->setFlash('Your qualification has been added; we are processing and will be inviting the panelists now.', 'flash_success');
				}
				else {
					$this->Session->setFlash('There was an error saving the qualification.', 'flash_error');
					$qualificationSource->commit();
				}
			}
			else {
				$this->Session->setFlash('Users not found.', 'flash_error');
			}
			
			return $this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $project_id));
		}
	}

	public function ajax_click_templates($project_id) {
		if ($this->request->is('Post')) {
			$request_data = $this->request->data;
			$projectClickDistributionSource = $this->ProjectClickDistribution->getDataSource();
			$projectClickDistributionSource->begin();

			// get sumed percentage
			$conditions = array(
				'ProjectClickDistribution.project_id' => $project_id,
				'ProjectClickDistribution.key' => $request_data['key'],
				'ProjectClickDistribution.other' => false,
			);
			if ($request_data['key'] == 'age_gender') {
				$conditions['ProjectClickDistribution.gender'] = $request_data['gender'] == 'male' ? 1 : 2;
			}
			$sumed_percentage = $this->ProjectClickDistribution->find('first', array(
				'fields' => array('SUM(ProjectClickDistribution.percentage) AS total_percentage'),
				'conditions' => $conditions
			));
			$total_percentage = $sumed_percentage[0]['total_percentage'];
			if ($request_data['key'] == 'age') {
				$age_distributions = $this->ProjectClickDistribution->find('all', array(
					'conditions' => array(
						'ProjectClickDistribution.project_id' => $project_id,
						'ProjectClickDistribution.key' => 'age',
						'ProjectClickDistribution.other' => false
					)
				));

				$overlap_flag = false;
				foreach ($age_distributions as $age_distribution) {
					$age_from = $age_distribution['ProjectClickDistribution']['age_from'];
					$age_to = $age_distribution['ProjectClickDistribution']['age_to'];
					for ($i = $request_data['age_from']; $i <= $request_data['age_to']; $i ++) {
						if ($age_from <= $i && $i <= $age_to) {
							$overlap_flag = true;
							break 2;
						}
					}
				}
				if ($overlap_flag) {
					$this->Session->setFlash('Age ranges can not overlap.', 'flash_error');
					return $this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $project_id));
				}

				if (($total_percentage + $request_data['percentage']) > 100) {
					$this->Session->setFlash('The sum of the percentages can not exceed 100%.', 'flash_error');
					return $this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $project_id));
				}

				$request_data['project_id'] = $project_id;
				$this->ProjectClickDistribution->create();
				$this->ProjectClickDistribution->save(array('ProjectClickDistribution' => $request_data));
				$other_percentage = 100 - ($total_percentage + $request_data['percentage']);
				$other_distribution = array(
					'project_id' => $project_id,
					'key' => 'age',
					'other' => true,
					'percentage' => $other_percentage
				);
				$age_other_distribution = $this->ProjectClickDistribution->find('first', array(
					'conditions' => array(
						'ProjectClickDistribution.project_id' => $project_id,
						'ProjectClickDistribution.key' => 'age',
						'ProjectClickDistribution.other' => true
					)
				));
				if ($age_other_distribution) {
					$other_distribution['id'] = $age_other_distribution['ProjectClickDistribution']['id'];
				}
				$this->ProjectClickDistribution->create();
				$this->ProjectClickDistribution->save(array('ProjectClickDistribution' => $other_distribution));
			}
			elseif ($request_data['key'] == 'gender') {
				$total_percentage = $request_data['male']['percentage'] + $request_data['female']['percentage'];
				if ($total_percentage != 100) {
					$this->Session->setFlash('The sum of the gender percentages should be 100%.', 'flash_error');
					return $this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $project_id));
				}
				$gender_distributions = $this->ProjectClickDistribution->find('all', array(
					'conditions' => array(
						'ProjectClickDistribution.project_id' => $project_id,
						'ProjectClickDistribution.key' => 'gender',
					)
				));
				if ($gender_distributions) {
					foreach ($gender_distributions as $gender_distribution) {
						$this->ProjectClickDistribution->delete($gender_distribution['ProjectClickDistribution']['id']);
					}
				}
				foreach ($request_data as $key => $data) {
					if ($key != 'key') {
						$this->ProjectClickDistribution->create();
						$this->ProjectClickDistribution->save(array('ProjectClickDistribution' => array(
							'project_id' => $project_id,
							'key' => 'gender',
							'gender' => ($key == 'male' ? 1 : 2),
							'percentage' => $data['percentage'],
							'click_quota' => $data['click_quota'],
							'clicks' => $data['clicks'],
						)));
					}
				}
			}
			elseif ($request_data['key'] == 'age_gender') {
				$request_data['gender'] = $request_data['gender'] == 'male' ? 1 : 2;
				$age_gender_distributions = $this->ProjectClickDistribution->find('all', array(
					'conditions' => array(
						'ProjectClickDistribution.project_id' => $project_id,
						'ProjectClickDistribution.key' => 'age_gender',
						'ProjectClickDistribution.gender' => $request_data['gender'],
						'ProjectClickDistribution.other' => false
					)
				));

				$overlap_flag = false;
				foreach ($age_gender_distributions as $age_gender_distribution) {
					$age_from = $age_gender_distribution['ProjectClickDistribution']['age_from'];
					$age_to = $age_gender_distribution['ProjectClickDistribution']['age_to'];
					for ($i = $request_data['age_from']; $i <= $request_data['age_to']; $i ++) {
						if ($age_from <= $i && $i <= $age_to) {
							$overlap_flag = true;
							break 2;
						}
					}
				}
				if ($overlap_flag) {
					$this->Session->setFlash('Age ranges can not overlap.', 'flash_error');
					return $this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $project_id));
				}

				if (($total_percentage + $request_data['percentage']) > 100) {
					$this->Session->setFlash('The sum of the percentages can not exceed 100%.', 'flash_error');
					return $this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $project_id));
				}
				$request_data['project_id'] = $project_id;
				$this->ProjectClickDistribution->create();
				$this->ProjectClickDistribution->save(array('ProjectClickDistribution' => $request_data));
				$other_percentage = 100 - ($total_percentage + $request_data['percentage']);
				$other_distribution = array(
					'project_id' => $project_id,
					'key' => 'age_gender',
					'other' => true,
					'gender' => $request_data['gender'],
					'percentage' => $other_percentage
				);
				$age_gender_other_distribution = $this->ProjectClickDistribution->find('first', array(
					'conditions' => array(
						'ProjectClickDistribution.project_id' => $project_id,
						'ProjectClickDistribution.key' => 'age_gender',
						'ProjectClickDistribution.gender' => $request_data['gender'],
						'ProjectClickDistribution.other' => true
					)
				));
				if ($age_gender_other_distribution) {
					$other_distribution['id'] = $age_gender_other_distribution['ProjectClickDistribution']['id'];
				}
				$this->ProjectClickDistribution->create();
				$this->ProjectClickDistribution->save(array('ProjectClickDistribution' => $other_distribution));
			}
			else {
				$total_percentage = 0;
				foreach ($request_data[$request_data['key']] as $answer_id => $data) {
					if (!empty($data['percentage'])) {
						$total_percentage += $data['percentage'];
					}
				}
				if ($total_percentage > 100) {
					$this->Session->setFlash('The sum of the percentages can not exceed 100%.', 'flash_error');
					return $this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $project_id));
				}
				$current_distributions = $this->ProjectClickDistribution->find('all', array(
					'conditions' => array(
						'ProjectClickDistribution.project_id' => $project_id,
						'ProjectClickDistribution.key' => $request_data['key']
					)
				));
				foreach ($current_distributions as $current_distribution) {
					$this->ProjectClickDistribution->delete($current_distribution['ProjectClickDistribution']['id']);
				}

				foreach ($request_data[$request_data['key']] as $answer_id => $data) {
					if (!empty($data['percentage'])) {
						$this->ProjectClickDistribution->create();
						$this->ProjectClickDistribution->save(array('ProjectClickDistribution' => array(
							'project_id' => $project_id,
							'key' => $request_data['key'],
							'answer_id' => $answer_id,
							'percentage' => $data['percentage'],
							'click_quota' => $data['click_quota'],
							'clicks' => $data['clicks']
						)));
					}
				}

				$other_percentage = 100 - $total_percentage;
				$other_distribution = array(
					'project_id' => $project_id,
					'key' => $request_data['key'],
					'other' => true,
					'percentage' => $other_percentage
				);
				$this->ProjectClickDistribution->create();
				$this->ProjectClickDistribution->save(array('ProjectClickDistribution' => $other_distribution));
			}
			$projectClickDistributionSource->commit();
			$this->Session->setFlash('Click distribution has been added.', 'flash_success');
			return $this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $project_id));
		}
		$questions = array();
		$geo = array();
		$questions['hhi'] = $this->getQuestion('STANDARD_HHI_US_v2');
		$questions['ethnicity'] = $this->getQuestion('ETHNICITY');
		$questions['hispanic'] = $this->getQuestion('HISPANIC');

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
			$state_regions[] = $state['GeoState']['region'];
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
		$geo['region'] = array_keys(array_flip($state_regions));
		$geo['state'] = $states_list;
		$project_click_distributions = $this->ProjectClickDistribution->find('all', array(
			'conditions' => array(
				'ProjectClickDistribution.project_id' => $project_id,
				'ProjectClickDistribution.deleted is null'
			)
		));
		$this->set(compact('questions', 'geo', 'project_click_distributions'));
	}

	private function getQuestion($key, $country = 'US') {
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

	public function ajax_delete_click_distribution() {
		$id = $this->request->data['id'];
		$distribution = $this->ProjectClickDistribution->find('first', array(
			'conditions' => array(
				'ProjectClickDistribution.id' => $id
			)
		));
		$key = $distribution['ProjectClickDistribution']['key'];
		$conditions = array(
			'ProjectClickDistribution.project_id' => $distribution['ProjectClickDistribution']['project_id'],
			'ProjectClickDistribution.key' => $distribution['ProjectClickDistribution']['key'],
			'ProjectClickDistribution.other' => true,
		);
		if ($key == 'age_gender') {
			$conditions['ProjectClickDistribution.gender'] = $distribution['ProjectClickDistribution']['gender'];
		}
		$other_distribution = $this->ProjectClickDistribution->find('first', array(
			'conditions' => $conditions
		));
		$new_other_percentage = $other_distribution['ProjectClickDistribution']['percentage'] + $distribution['ProjectClickDistribution']['percentage'];
		$new_other_click_quota = $other_distribution['ProjectClickDistribution']['click_quota'] + $distribution['ProjectClickDistribution']['click_quota'];
		$new_other_clicks = $other_distribution['ProjectClickDistribution']['clicks'] + $distribution['ProjectClickDistribution']['clicks'];
		if ($new_other_percentage == 100) {
			$this->ProjectClickDistribution->save(array('ProjectClickDistribution' => array(
				'id' => $id,
				'deleted' => date(DB_DATETIME),
			)));
			$this->ProjectClickDistribution->save(array('ProjectClickDistribution' => array(
				'id' => $other_distribution['ProjectClickDistribution']['id'],
				'deleted' => date(DB_DATETIME),
			)));
			$return_data = array(
				$id => 0,
				$other_distribution['ProjectClickDistribution']['id'] => 0
			);
		}
		else {
			$this->ProjectClickDistribution->create();
			$this->ProjectClickDistribution->save(array('ProjectClickDistribution' => array(
				'id' => $other_distribution['ProjectClickDistribution']['id'],
				'project_id' => $distribution['ProjectClickDistribution']['project_id'],
				'other' => true,
				'percentage' => $new_other_percentage,
				'click_quota' => $new_other_click_quota,
				'clicks' => $new_other_clicks
			)));

			$this->ProjectClickDistribution->delete($id);
			$return_data = array(
				$id => 0,
				$other_distribution['ProjectClickDistribution']['id'] => array(
					'percentage' => $new_other_percentage,
					'click_quota' => $new_other_click_quota,
					'clicks' => $new_other_clicks
				)
			);
		}
		return new CakeResponse(array(
			'body' => json_encode($return_data),
			'type' => 'json',
			'status' => '201'
		));
	}
}
