<?php
App::import('Lib', 'Utilities');
App::import('Lib', 'MintVine');
App::import('Lib', 'QueryEngine');	
App::uses('HttpSocket', 'Network/Http');

class QualificationShell extends AppShell {
	public $uses = array('SurveyVisitCache', 'Project', 'Qualification', 'Setting', 'SurveyUser', 'QualificationUser', 'QualificationStatistic', 'User', 'Partner', 'SurveyVisit', 'ProjectLog', 'ProjectOption', 'SurveyPartner', 'ProjectAdmin');
	
	public function main() {} 
	
	public function export_users() {
		if (!isset($this->args[0])) {
			$this->out('Please input a country');
			return false;
		}
	
		$country = $this->args[0];
		
		$this->User->unbindModel(array('belongsTo' => array('Referrer'))); 
		$this->User->unbindModel(array('hasOne' => array('QueryProfile')));
		$this->User->bindModel(array('hasOne' => array('QueryProfile' => array(
			'type' => 'INNER'
		)))); 
		$users = $this->User->find('all', array(
			'fields' => array('User.id'),
			'conditions' => array(
				'User.hellbanned' => false,
				'QueryProfile.country' => $country,
				'User.last_touched >' => date(DB_DATETIME, strtotime('-2 weeks')),
			),
			'limit' => 500
		));
		

		// get QE2 profile
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array(
					'qe.mintvine.username',
					'qe.mintvine.password',
					'hostname.qe'
				),
				'Setting.deleted' => false
			)
		));
		$http = new HttpSocket(array(
			'timeout' => 30,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$http->configAuth('Basic', $settings['qe.mintvine.username'], $settings['qe.mintvine.password']);
		
		$user_qualifications = array();
		$total_qualifications = array();
		
		$filename = WWW_ROOT.'files/user_export-'.$country . '.csv';
		$this->out('Writing to '.$filename);
		$fp = fopen($filename, 'w');
		
		$total = count($users);
		$i = 0; 
		foreach ($users as $user) {
			$i++; 
			$this->out($i.'/'.$total);
			try {
				$return = $http->get($settings['hostname.qe'].'/qualifications/'.$user['User']['id']);
			}
			catch (\HttpException $ex) {
				CakeLog::write('qe.user.qualification', $ex->getMessage());
				break;
			}
			catch (\Exception $ex) {
				CakeLog::write('qe.user.qualification', $ex->getMessage());
				break;
			}

			if ($return->code == 200) {
				$qualifications = json_decode($return->body, true);
				$lucid_qualifications = Hash::extract($qualifications, 'answered.lucid');
				if (empty($total_qualifications)) {
					$total_qualifications = array_keys($lucid_qualifications) + Hash::extract($qualifications, 'unanswered.lucid');
					$remove_list = array('active_within_60_days', 'active_within_90_days', 'active_within_month', 'active_within_week');
					foreach ($remove_list as $key_to_remove) {
						$key = array_search($key_to_remove, $total_qualifications); 
						if ($key !== false) {
							unset($total_qualifications[$key]); 
						}
					}
					sort($total_qualifications); 
					$total_qualifications = array('id') + $total_qualifications; 

					// header csv
					fputcsv($fp, $total_qualifications); 
				}
				
				$user_qualification = array($user['User']['id']);
				foreach ($total_qualifications as $qualification_key) {
					if ($qualification_key == 'id') {
						continue;
					}
					if (isset($lucid_qualifications[$qualification_key])) {
						$user_qualification[$qualification_key] = implode(', ', $lucid_qualifications[$qualification_key]); 
					}
					else {
						$user_qualification[$qualification_key] = ''; 
					}
				}
				fputcsv($fp, $user_qualification); 
			}
		}
		
		fclose($fp);
		$this->out('Wrote '.$filename); 
	}
	
	public function process() {
		ini_set('memory_limit', '1024M');
		if (!isset($this->args[0])) {
			$this->out('Please specify a qualification ID to run');
			return false;
		}
		
		// gotta do this weird hacky thing to deal with db sync issues
		$start_time = microtime(true);
		while (true) {
			$this->Qualification->bindModel(array(
				'hasOne' => array('QualificationStatistic')
			));
			$qualification = $this->Qualification->find('first', array(
				'conditions' => array(
					'Qualification.id' => $this->args[0],
					'Qualification.parent_id' => null // children qualifications are never explicitly run
				)
			));
			if ($qualification && ($qualification['Qualification']['deleted'])) {
				$this->out('Qualification not valid to be processed');
				return false;
			}
			
			if ($qualification) {
				break;
			}
			usleep(100); // sleep 100 ms
			$end_time = microtime(true) - $start_time; 
			if ($end_time >= 2) {
				$this->out('Waited 1 second; no syncing');
				return false;
			}
		}
		
		$this->out('Starting #'.$qualification['Qualification']['id']); 
		$this->ProjectLog->create();
		$this->ProjectLog->save(array('ProjectLog' => array(
			'project_id' => $qualification['Qualification']['project_id'],
			'type' => 'qualification.started',
			'description' => '',
		)));
		
		// because we mark the processing flag before entering here, we need a forced override
		if (!is_null($qualification['Qualification']['processing']) && !isset($this->args[1])) {
			$this->out('Qualification already processing');
			return false;
		}

		if (defined('IS_DEV_INSTANCE') && IS_DEV_INSTANCE === true) {
			App::import('Lib', 'MockQueryEngine');
			$panelist_ids = MockQueryEngine::parent_panelists(1); 
		}
		else {
			$results = Utils::qe2_query($qualification['Qualification']['query_json']);
			if (!$results || $results->code != '200') {
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $qualification['Qualification']['project_id'],
					'type' => 'qualification.error',
					'description' => "API access error. " . ($results ? $results->code. ': '.$results->reasonPhrase  : ''),
				)));
				$this->out('API access error');
				return;
			}

			$body = json_decode($results['body'], true); 
			$panelist_ids = $body['panelist_ids']; 
		}
		
		
		$this->out('Found '.count($panelist_ids).' results from QE2');
		$this->ProjectLog->create();
		$this->ProjectLog->save(array('ProjectLog' => array(
			'project_id' => $qualification['Qualification']['project_id'],
			'type' => 'qualification.count',
			'description' => 'Found '.count($panelist_ids).' panelists from QE2',
		)));
		
		// note that we are passing $panelist_ids by reference
		$this->filter($qualification['Qualification']['additional_json'], $panelist_ids);
		$this->out('Found '.count($panelist_ids).' results after filtering through active panelists list');
		$this->ProjectLog->create();
		$this->ProjectLog->save(array('ProjectLog' => array(
			'project_id' => $qualification['Qualification']['project_id'],
			'type' => 'qualification.stats',
			'description' => 'Found '.count($panelist_ids).' results after filtering through active panelists list',
		)));
		
		$i = 0; 
		$disabled_qualification_ids = $this->Qualification->find('list', array(
			'fields' => array('Qualification.id', 'Qualification.id'),
			'conditions' => array(
				'Qualification.project_id' => $qualification['Qualification']['project_id'],
				'Qualification.active' => false,
			)
		));
		$disabled_qualification_users = array();
		if (!empty($disabled_qualification_ids)) {
			$disabled_qualification_users = $this->QualificationUser->find('list', array(
				'fields' => array('QualificationUser.user_id', 'QualificationUser.id'),
				'conditions' => array(
					'QualificationUser.qualification_id' => $disabled_qualification_ids
				)
			));
		}
		
		$survey_users = $this->SurveyUser->find('list', array(
			'fields' => array('SurveyUser.user_id', 'SurveyUser.id'),
			'conditions' => array(
				'SurveyUser.survey_id' => $qualification['Qualification']['project_id']
			),
			'recursive' => -1
		));
		
		if (!empty($panelist_ids)) {
			// todo: technically panelists can exist in multiple panelist groups
			foreach ($panelist_ids as $panelist_id) {
				if (isset($survey_users[$panelist_id])) {
					
					// Move disabled qual_users into this new active qualification
					if (isset($disabled_qualification_users[$panelist_id])) {
						$this->QualificationUser->create();
						$this->QualificationUser->save(array('QualificationUser' => array(
							'id' => $disabled_qualification_users[$panelist_id],
							'qualification_id' => $qualification['Qualification']['id'],
							'award' => $qualification['Qualification']['award']
						)), true, array('qualification_id', 'award'));
						$i++;
					}
					
					continue;
				}
				
				$this->SurveyUser->create();
				$save = $this->SurveyUser->save(array('SurveyUser' => array(
					'survey_id' => $qualification['Qualification']['project_id'],
					'user_id' => $panelist_id	
				)));
			
				if ($save) {
					$this->QualificationUser->create();
					$this->QualificationUser->save(array('QualificationUser' => array(
						'qualification_id' => $qualification['Qualification']['id'],
						'user_id' => $panelist_id,
						'award' => $qualification['Qualification']['award']
					)));
				}
				$i++;
			}
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $qualification['Qualification']['project_id'],
				'type' => 'qualification.invited',
				'description' => 'Added survey invitations for ' . $i . ' panelists',
				'internal_description' => $i
			)));

			$this->Project->unbindModel(array('hasMany' => array('SurveyPartner', 'ProjectOption', 'ProjectAdmin')));
			$project = $this->Project->find('first', array(
				'fields' => array('Project.id', 'SurveyVisitCache.id'),
				'conditions' => array(
					'Project.id' => $qualification['Qualification']['project_id']
				),
			));
			if ($project) {
				$count_invites = $this->SurveyUser->find('count', array(
					'conditions' => array(
						'SurveyUser.survey_id' => $project['Project']['id'],
					),
					'recursive' => -1
				));
				if ($count_invites > 0) {
					$count_email_invites = $this->SurveyUser->find('count', array(
						'conditions' => array(
							'SurveyUser.survey_id' => $project['Project']['id'],
							'SurveyUser.notification' => '1'
						),
						'recursive' => -1
					));
				}
				else {
					$count_email_invites = 0;
				}

				if ($project['SurveyVisitCache']['id'] > 0) {
					$this->SurveyVisitCache->create();
					$this->SurveyVisitCache->save(array('SurveyVisitCache' => array(
						'id' => $project['SurveyVisitCache']['id'],
						'invited' => $count_invites,
						'emailed' => $count_email_invites,
						'modified' => false
					)), array(
						'callbacks' => false,
						'validate' => false,
						'fieldList' => array('emailed', 'invited')
					));
				}
			}

		}
		
		$total = is_null($qualification['Qualification']['total']) ? 0: $qualification['Qualification']['total'];
		$total = $total + $i; 
		
		$this->Qualification->create();
		$this->Qualification->save(array('Qualification' => array(
			'id' => $qualification['Qualification']['id'],
			'active' => true,
			'processing' => null
		)), true, array('processing', 'active'));
		
		$this->QualificationStatistic->create();
		$this->QualificationStatistic->save(array('QualificationStatistic' => array(
			'id' => $qualification['QualificationStatistic']['id'],
			'invited' => $total
		)), true, array('invited')); 
		
		$this->ProjectLog->create();
		$this->ProjectLog->save(array('ProjectLog' => array(
			'project_id' => $qualification['Qualification']['project_id'],
			'type' => 'qualification.open',
			'description' => 'Qualification #'.$qualification['Qualification']['id'].' opened.',
		)));
		$this->out('Completed invitations of qualification #'.$qualification['Qualification']['id'].'; total panelists: '.$total); 
	}
	
	public function refresh() {
		ini_set('memory_limit', '1024M');
		$log_file = 'refresh.qual';
		$time_start = microtime(true);		
		if (!isset($this->args[0])) {
			$this->out('Qualification_id must be provided as an argument');
			return false;
		}
		
		$models_to_import = array('Project', 'ProjectLog', 'Qualification', 'QualificationUser', 'SurveyUser', 'User', 'Setting');
		foreach ($models_to_import as $model_to_import) {
			App::import('Model', $model_to_import);
			$this->$model_to_import = new $model_to_import;
		}
		
		$keys = array(
			'socialglimpz.invite_threshold',
			'qe.mintvine.username',
			'qe.mintvine.password',
			'hostname.qe',
		);
		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => $keys,
				'Setting.deleted' => false
			)
		));
		if (count($settings) != count($keys)) {
			$this->lecho('Failed: One or ore settings are missing!', $log_file);
			return false;
		}
		
		$this->Qualification->bindModel(array(
			'hasOne' => array('QualificationStatistic')
		));
		$qualification = $this->Qualification->find('first', array(
			'conditions' => array(
				'Qualification.id' => $this->args[0],
				'Qualification.deleted is null'
			)
		));
		if (!$qualification) {
			$this->lecho('Qualification not found.', $log_file);
			return false;
		}
		
		$this->Project->bindModel(array('belongsTo' => array('Client')));
		$project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $qualification['Qualification']['project_id'],
			)
		));
		
		if (!$project) {
			$this->lecho('Project not found.', $log_file);
			return false;
		}
		
		$this->lecho('Starting qualifications', $log_file);
		$this->ProjectLog->create();
		$this->ProjectLog->save(array('ProjectLog' => array(
			'project_id' => $project['Project']['id'],
			'type' => 'refresh.started',
			'description' =>  'Qualification #' . $qualification['Qualification']['id'],
		)));
		
		// for testing locally; use the mock data; will generate range of user ids 1 - 2000
		if (defined('IS_DEV_INSTANCE') && IS_DEV_INSTANCE === true) {
			App::import('Lib', 'MockQueryEngine');
			$panelist_ids = MockQueryEngine::parent_panelists(1); 
		}
		else {
			$http = new HttpSocket(array(
				'timeout' => 15,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			
			$http->configAuth('Basic', $settings['qe.mintvine.username'], $settings['qe.mintvine.password']);
			$query_json = $qualification['Qualification']['query_json'];
			if ($project['Group']['key'] == 'socialglimpz') {
				$query_json = json_decode($qualification['Qualification']['query_json'], true);
				$query_json['qualifications']['active_within_week'] = array('true');
				$results = $http->post($settings['hostname.qe'] . '/query?count_only=true', json_encode($query_json), array(
					'header' => array('Content-Type' => 'application/json')
				));
				$body = json_decode($results['body'], true);
				if (!isset($body['count'])) {
					$this->lecho('API error'. print_r($results['body'], true), $log_file);
					return;
				}
				
				if ($body['count'] < $settings['socialglimpz.invite_threshold']) {
					unset($query_json['qualifications']['active_within_week']);
					$query_json['qualifications']['active_within_month'] = array('true');
				}
				
				$query_json = json_encode($query_json);
			}

			$results = Utils::qe2_query($query_json);
			if (!$results || $results->code != '200') {
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $qualification['Qualification']['project_id'],
					'type' => 'refresh.error',
					'description' => "API access error. " . ($results ? $results->code. ': '.$results->reasonPhrase  : ''),
				)));
				$this->lecho('API access error', $log_file);
				return;
			}
			
			$body = json_decode($results['body'], true);
			$panelist_ids = $body['panelist_ids'];
		}
		
		$qe2_count = count($panelist_ids);
		$this->lecho('Panelists returned by QE2: '.$qe2_count.' panelists', $log_file);
		$this->ProjectLog->create();
		$this->ProjectLog->save(array('ProjectLog' => array(
			'project_id' => $project['Project']['id'],
			'type' => 'refresh.count',
			'description' =>  'Panelists returned by QE2: ' . $qe2_count,
		)));
		
		// note $panelist_ids are passed by refference
		$this->filter($qualification['Qualification']['additional_json'], $panelist_ids);
		$survey_users = $this->SurveyUser->find('list', array(
			'fields' => array('SurveyUser.id', 'SurveyUser.user_id'),
			'conditions' => array(
				'SurveyUser.survey_id' => $project['Project']['id']
			)
		));
		$qualification_users = $this->QualificationUser->find('list', array(
			'fields' => array('QualificationUser.id', 'QualificationUser.user_id'),
			'conditions' => array(
				'QualificationUser.deleted' => false,
				'QualificationUser.qualification_id' => $qualification['Qualification']['id']
			)
		));
		$diff = array_diff($panelist_ids, $survey_users); 
		$logs = array(
			'Found '.count($panelist_ids).' results after filtering through active panelists list',
			'Existing panelists in project: '.count($survey_users).'(survey_users)/'.count($qualification_users).' (qualification_users)',
			'Total already in survey: '.count($survey_users),
			'Total diff: '.count($diff)
		);
		$this->ProjectLog->create();
		$this->ProjectLog->save(array('ProjectLog' => array(
			'project_id' => $project['Project']['id'],
			'type' => 'refresh.stats',
			'description' => implode(', ', $logs),
		)));
		$this->lecho(implode(', ', $logs), $log_file); 
			
		if (!empty($diff)) {
			foreach ($diff as $panelist_id) {
				$this->SurveyUser->create();
				$save = $this->SurveyUser->save(array('SurveyUser' => array(
					'survey_id' => $project['Project']['id'],
					'user_id' => $panelist_id	
				)));
				if ($save) {
					$this->QualificationUser->create();
					$this->QualificationUser->save(array('QualificationUser' => array(
						'qualification_id' => $qualification['Qualification']['id'],
						'user_id' => $panelist_id,
						'award' => $project['Project']['award']
					)));
				}
			}
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'refresh.invited',
				'description' => 'Added survey invitations for '.count($diff).' panelists',
				'internal_description' => count($diff)
			)));
			
			// update invited count
			$this->QualificationStatistic->create();
			$this->QualificationStatistic->save(array('QualificationStatistic' => array(
				'id' => $qualification['QualificationStatistic']['id'],
				'invited' => $qualification['QualificationStatistic']['invited'] + count($diff)
			)), true, array('invited')); 
		}
		
		// update the total count on this qualification
		$data = array(
			'id' => $qualification['Qualification']['id'],
			'total' => $qe2_count
		);
		
		// if the qualification has been interrupted previously.
		if (!is_null($qualification['Qualification']['processing'])) {
			$data['processing'] = null;
		}
		
		$this->Qualification->create();
		$this->Qualification->save(array('Qualification' => $data), true, array_keys($data));
		$this->lecho('Completed refresh (Execution time: '.(microtime(true) - $time_start).')', $log_file);
		return true;
	}
	
	// Will filter panelists on Qualification.additional_json & active panelists
	private function filter($additional_json, &$panelist_ids) {
		if (!empty($additional_json)) {
			$additional_filters = json_decode($additional_json, true); 
			
			$exclude_user_ids = array();
			if (isset($additional_filters) && isset($additional_filters['exclude']['user_ids'])) {
				foreach ($additional_filters['exclude']['user_ids'] as $exclude_user_id) {
					$exclude_user_ids[] = $exclude_user_id;
				}
			}
			
			if (isset($additional_filters) && isset($additional_filters['exclude']['completes_from_project'])) {
				$exclude_complete_project_ids = $additional_filters['exclude']['completes_from_project']; 
				if (!empty($exclude_complete_project_ids)) {
					$partner = $this->Partner->find('first', array(
						'fields' => array('Partner.id'),
						'conditions' => array(
							'Partner.key' => 'mintvine'
						)
					));
					$survey_visits = $this->SurveyVisit->find('list', array(
						'fields' => array(
							'SurveyVisit.id', 'SurveyVisit.partner_user_id'
						),
						'conditions' => array(
							'SurveyVisit.type' => SURVEY_COMPLETED,
							'SurveyVisit.partner_id' => $partner['Partner']['id'],
							'SurveyVisit.survey_id' => $exclude_complete_project_ids
						)
					));
					if (!empty($survey_visits)) {
						foreach ($survey_visits as $partner_user_id) {
							list($project_id, $user_id) = explode('-', $partner_user_id);
							$exclude_user_ids[] = $user_id; 
						}
					}
				}
			}
			
			if (isset($additional_filters) && isset($additional_filters['exclude']['clicks_from_project'])) {
				$exclude_click_project_ids = $additional_filters['exclude']['clicks_from_project']; 
				if (!empty($exclude_click_project_ids)) {
					$partner = $this->Partner->find('first', array(
						'fields' => array('Partner.id'),
						'conditions' => array(
							'Partner.key' => 'mintvine'
						)
					));
					$survey_visits = $this->SurveyVisit->find('list', array(
						'fields' => array(
							'SurveyVisit.id', 'SurveyVisit.partner_user_id'
						),
						'conditions' => array(
							'SurveyVisit.type' => SURVEY_CLICK,
							'SurveyVisit.partner_id' => $partner['Partner']['id'],
							'SurveyVisit.survey_id' => $exclude_click_project_ids
						)
					));
					if (!empty($survey_visits)) {
						foreach ($survey_visits as $partner_user_id) {
							list($project_id, $user_id) = explode('-', $partner_user_id);
							$exclude_user_ids[] = $user_id;
						}
					}
				}
			}
			
			if (!empty($exclude_user_ids)) {
				$exclude_user_ids = array_unique($exclude_user_ids);
				foreach ($exclude_user_ids as $exclude_user_id) {
					$key = array_search($exclude_user_id, $panelist_ids);
					if ($key !== false) {
						unset($panelist_ids[$key]); 
					}
				}
			}
		}
		
		// filter these panelist IDs out here by active in last 2 weeks, also the chunks help to process large arrays
		$panelist_id_chunks = array_chunk($panelist_ids, 12000, false);
		$filtered_panelist_ids = array();
		foreach ($panelist_id_chunks as $chunked_ids) {
			$panelists = $this->User->find('list', array(
				'fields' => array('User.id', 'User.id'),
				'conditions' => array(
					'User.id' => $chunked_ids,
					'User.hellbanned' => false,
					'User.last_touched >=' => date(DB_DATETIME, strtotime('-2 weeks')),
					'User.deleted_on' => null,
				),
				'recursive' => -1
			));
			$filtered_panelist_ids = array_merge($filtered_panelist_ids, $panelists); 
		}
		
		$panelist_ids = $filtered_panelist_ids;
		
		// append is the last rule run on purpose and is after the active check; that way they are always invited
		if (!empty($additional_json) && isset($additional_filters) && isset($additional_filters['append']['user_ids'])) {
			foreach ($additional_filters['append']['user_ids'] as $additional_user_id) {
				if (!in_array($additional_user_id, $panelist_ids) && !empty($additional_user_id)) {
					$panelist_ids[] = $additional_user_id;
				}
			}
		}
	}
}
