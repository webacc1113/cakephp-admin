<?php
App::import('Lib', 'Utilities');
App::import('Lib', 'QueryEngine');
App::import('Lib', 'FedMappings');
App::import('Lib', 'Surveys');
App::uses('HttpSocket', 'Network/Http');

class FedShell extends AppShell {
	public $uses = array('ProjectLog', 'SurveyVisitCache', 'Project', 'ProjectRate', 'Client', 'SurveyVisit', 'Group', 'Partner', 'Prescreener', 'FedQuestion', 'FedAnswer', 'User', 'SurveyUser', 'SurveyVisit', 'FedSurvey', 'Query', 'GeoZip', 'QueryStatistic', 'Setting', 'ProjectOption');
	public $tasks = array('Fed');
	
	// Set params for url to send curl
	private $api_key = FED_API_KEY;
	private $api_host = FED_API_HOST;
	private $supplier_code = FED_SUPPLIER_CODE;

	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser->addSubcommand('import', array(
			'help' => 'If $survey_id option is provided, imports only that survey, otherwise import $limit number of fulcrum surveys',
			'parser' => array(
				'description' => 'If $survey_id option is provided, imports only that survey, otherwise import $limit number of fulcrum surveys',
				'options' => array(
					'survey_id' => array(
						'short' => 's',
						'default' => null,
						'help' => __('Send in a Fulcrum survey ID number')
					),
					'limit' => array(
						'short' => 'l',
						'default' => null,
						'help' => __('# of projects to import')
					)
				)
			)
		));
		$parser->addSubcommand('update', array(
			'help' => 'If $survey_id option is provided, update only that survey, else update all saved fulcrum surveys.',
			'parser' => array(
				'description' => 'If $survey_id option is provided, update only that survey, else update all saved fulcrum surveys.',
				'options' => array(
					'survey_id' => array(
						'short' => 's',
						'default' => null,
						'help' => __('Send in a Fulcrum survey ID number')
					)
				)
			)
		));
		$parser->addSubcommand('qualifications', array(
			'help' => 'If $survey_id option is provided, save/update qualification only for that survey, else save/update all saved fulcrum surveys.',
			'parser' => array(
				'description' => 'If $survey_id option is provided, save/update qualification only for that survey, else save/update all saved fulcrum surveys.',
				'options' => array(
					'survey_id' => array(
						'short' => 's',
						'default' => null,
						'help' => __('Send in a Fulcrum survey ID number')
					)
				)
			)
		));
		return $parser;
	}
	
	public function replay_failed_links() {
		
		$lucid_settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array(
					'lucid.maintenance'
				),
				'Setting.deleted' => false
			),
			'fields' => array(
				'Setting.name',
				'Setting.value'
			)
		));
		
		if (count($lucid_settings) < 1) {
			return false;
		}
		
		if ($lucid_settings['lucid.maintenance'] == 'true') {		
			return false;
		}
		
		if (!isset($this->args[0])) {
			$fed_surveys = $this->FedSurvey->find('all', array(
				'conditions' => array(
					'FedSurvey.status' => 'failed.link',
					'FedSurvey.modified >' => date(DB_DATETIME, strtotime('-2 days')),
					'FedSurvey.modified <' => date(DB_DATETIME, strtotime('-2 hours'))
				)
			));
		}
		else {
			$fed_surveys = array(array('FedSurvey' => array('fed_survey_id' => $this->args[0]))); 
		}
		if (!$fed_surveys) {
			return;
		}
		$HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$request_data = array('key' => $this->api_key);

		foreach ($fed_surveys as $fed_survey) {
			$response = $HttpSocket->post(
				$this->api_host . 'Supply/v1/' . 'SupplierLinks/Create/' . $fed_survey['FedSurvey']['fed_survey_id'] . '/' . $this->supplier_code . '?key=' . $this->api_key, 
				json_encode(array(
					'SupplierLinkTypeCode' => 'OWS',
					'TrackingTypeCode' => 'NONE'
				)),
				array(
					'header' => array('Content-Type' => 'application/json')
				)
			);
			$body = json_decode($response['body']);
			print_r($body);
		}
	}
	
	/* 
	 * This method manages the autosampling process
	 * args: dryrun (optional)
	 * args: MV project id - if this argument exist, autolaunch will process only this project (helpful for testing). 
	 * args: limit
	 * Auto launch New fulcrum surveys (for which no statistics data available)*/
	public function autosample() {
		$lucid_settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array(
					'lucid.maintenance'
				),
				'Setting.deleted' => false
			),
			'fields' => array(
				'Setting.name',
				'Setting.value'
			)
		));
		
		if (count($lucid_settings) < 1) {
			return false;
		}
		
		if ($lucid_settings['lucid.maintenance'] == 'true') {		
			return false;
		}
		ini_set('memory_limit', '740M');
		$dryrun = isset($this->args[0]) && $this->args[0] == 'dryrun';
		$log_file = $dryrun ? 'fulcrum.auto.dryrun': 'fulcrum.auto';
		$limit = isset($this->args[2]) && !empty($this->args[2]) ? $this->args[2]: false;
		
		$autolaunch_setting = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'fulcrum.autolaunch',
				'Setting.deleted' => false
			)
		));
		if (!$autolaunch_setting || $autolaunch_setting['Setting']['value'] != 'true') {
			echo 'Autolaunch disabled';
			return;
		}
		
		$groups = $this->Group->find('list', array(
			'fields' => array('id', 'key'),
			'conditions' => array(
				'Group.key' => array('fulcrum')
			)
		));
		$clients = $this->Client->find('all', array(
			'conditions' => array(
				'Client.key' => array('fulcrum'),
				'Client.deleted' => false
			)
		));
		if (empty($groups) || empty($clients)) {
			echo 'Missing groups or clients';
			return;
		}
		
		$HttpSocket = new HttpSocket(array(
			'timeout' => 5,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$request_data = array('key' => $this->api_key);
				
		// Stage 1: Send sample
		$this->Project->bindModel(array(
			'hasOne' => array(
				'FedSurvey' => array(
					'className' => 'FedSurvey',
					'foreignKey' => 'survey_id'
				)
			)
		));
		$conditions = array(
			'Project.group_id' => array_keys($groups),
			'Project.active' => true,
			'Project.status' => array(PROJECT_STATUS_STAGING, PROJECT_STATUS_SAMPLING),
		);
		if (isset($this->args[1]) && !empty($this->args[1])) {
			$conditions['Project.id'] = $this->args[1];
		}
		
		$projects = $this->Project->find('all', array(
			'conditions' => $conditions,
			'order' => 'Project.id DESC'
		));
		if (!$projects) {
			echo "projects not found for autolaunch"."\n";
			return;
		}
		
		$sample_threshold = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'fulcrum.sample_threshold',
				'Setting.deleted' => false
			)
		));
		if (!$sample_threshold) { // set the default if not found.
			$sample_threshold = array('Setting' => array('value' => 25));
		}
		
		$ir_cutoff = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'fulcrum.ir_cutoff',
				'Setting.deleted' => false
			)
		));
		if (!$ir_cutoff) { // set the default if not found.
			$ir_cutoff = array('Setting' => array('value' => 10));
		}
		
		$message = 'Processing ' . count($projects) . ' projects';
		echo $message . "\n";
		CakeLog::write($log_file, $message);
		$i = 0;
		$launched_projects = array();

		foreach ($projects as $project) {
			echo 'Starting #'.$project['Project']['id']. ' ('.$project['Project']['prj_name'].')'."\n";
			// Addressing 2006 MySQL server has gone away - error
			$this->Project->getDatasource()->reconnect();
			
			// Fulcrum only 
			if (!empty($project['FedSurvey']['fed_survey_id'])) {
				
				// first retrieve the statistics
				$response = $this->Fed->get_response($HttpSocket, $this->api_host . 'Supply/v1/' . 'SurveyStatistics/BySurveyNumber/' . $project['FedSurvey']['fed_survey_id'] . '/' . $this->supplier_code, $request_data);
				if (!$response) {
					$message = "[SKIPPED] Api call failed to get survey statistics for " . $project['Project']['id'];
					echo $message . "\n";
					CakeLog::write($log_file, $message);
					continue;
				}

				$survey_statistics = json_decode($response['body'], true);
				// a precondition check for both staging + sampling to see if fulcrum returned data when we have no click data
				if (isset($survey_statistics['SurveyStatistics']['GlobalTrailingSystemConversion']) && $survey_statistics['SurveyStatistics']['GlobalTrailingSystemConversion'] > 0) {
					$actual_ir = $survey_statistics['SurveyStatistics']['GlobalTrailingSystemConversion'] * 100;

					// Update bid_ir
					$this->Project->create();
					$this->Project->save(array('Project' => array(
						'id' => $project['Project']['id'],
						'bid_ir' => $actual_ir,
					)), true, array('bid_ir'));
					
					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $project['Project']['id'],
						'type' => 'updated',
						'description' => 'Project IR updated to '.$actual_ir.'%'
					)));

					// close the staging project if fulcrum ir is less then 10 
					if ($actual_ir < $ir_cutoff['Setting']['value'] && empty($project['SurveyVisitCache']['complete'])) {
						if (!$dryrun) {
							$this->Fed->autolaunch_close($project['Project']['id']);
							
							$this->ProjectLog->create();
							$this->ProjectLog->save(array('ProjectLog' => array(
								'project_id' => $project['Project']['id'],
								'type' => 'status.closed.ir',
								'description' => 'Fulcrum reported an IR of ' . $actual_ir . '%'
							)));
						}
						
						$message = '[' . strtoupper(PROJECT_STATUS_CLOSED) . '] ' . $project['Project']['id'] . ' fulcrum reported an IR of ' . $actual_ir . '%';
						echo $message . "\n";
						CakeLog::write($log_file, $message);
						continue;
					}

					// reported IR and full qualifications match will be handled by autolaunch()
					if ($project['Project']['qualifications_match']) {
						continue;
					}
				}
			}
			
			// sanity check to make sure payout/cpi are set correctly	
			$payout = ($project['ProjectRate']['id']) ? $project['ProjectRate']['client_rate'] : $project['Project']['client_rate'];
			if ($payout == 0) {
				$message = '[SKIPPED] ' . $project['Project']['id'] . ' with a payout of 0';
				echo $message . "\n";
				CakeLog::write($log_file, $message);
				continue;
			}
			
			// grab all staging projects
			if ($project['Project']['status'] == PROJECT_STATUS_STAGING) {
				if ($project['Project']['quota'] < 100) {
					echo "Skipped ".$project['Project']['id']." because of quota\n";
					continue;					
				}
				if ($project['Project']['bid_ir'] < 15) {
					echo "Skipped ".$project['Project']['id']." because of IR \n";
					continue;					
				}
				
				$message = '['.strtoupper(PROJECT_STATUS_SAMPLING).'] #'.$project['Project']['id'];
				echo $message."\n";
				CakeLog::write($log_file, $message);
				if (!$dryrun) {
					// move project to sampling
					$this->Project->create();
					$this->Project->save(array('Project' => array(
						'id' => $project['Project']['id'], 
						'status' => PROJECT_STATUS_SAMPLING,
						'started' => date(DB_DATETIME)
					)), true, array('status', 'started'));
					
					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $project['Project']['id'],
						'type' => 'status.sample',
						'description' => 'From autosample'
					)));
					$sent = $this->Fed->run_queries($project, 'sample');
					$message = 'Sample pushed to #'.$project['Project']['id']; 					
					
					echo $message . "\n";
					CakeLog::write($log_file, $message);
				}
			}
			// we are looking at projects that have already sampled
			elseif ($project['Project']['status'] == PROJECT_STATUS_SAMPLING) {
				
				// if clicks exceed threshold with 0 completes, close the project
				if ($project['SurveyVisitCache']['click'] >= $sample_threshold['Setting']['value'] && $project['SurveyVisitCache']['complete'] == 0) {
					if (!$dryrun) {
						$this->Fed->autolaunch_close($project['Project']['id']);
						
						$this->ProjectLog->create();
						$this->ProjectLog->save(array('ProjectLog' => array(
							'project_id' => $project['Project']['id'],
							'type' => 'status.closed.sample',
							'description' => 'Closed with '.$project['SurveyVisitCache']['click'].' clicks and no complete'
						)));
					}
					
					$message = '['.strtoupper(PROJECT_STATUS_CLOSED).'] #' . $project['Project']['id'] . ' closed, because completes are still 0 & clicks has reached to ' . $sample_threshold['Setting']['value'];
					echo $message . "\n";
					CakeLog::write($log_file, $message);
					continue;
				}
				
				// if we have completes
				if ($project['SurveyVisitCache']['complete'] > 0) {
					$ir = round($project['SurveyVisitCache']['complete'] / $project['SurveyVisitCache']['click'], 2) * 100;
					if ($ir < $ir_cutoff['Setting']['value']) {
						// if we've reached the click threshold, fail. otherwise, give some time for the IR to recover
						if ($project['SurveyVisitCache']['click'] >= $sample_threshold['Setting']['value']) {
							if (!$dryrun) {
								$this->Fed->autolaunch_close($project['Project']['id']);
								
								$this->ProjectLog->create();
								$this->ProjectLog->save(array('ProjectLog' => array(
									'project_id' => $project['Project']['id'],
									'type' => 'status.closed.ir',
									'description' => 'IR '.$ir.'% with '.$project['SurveyVisitCache']['click'].' clicks'
								)));
							}
							
							$message = '['.strtoupper(PROJECT_STATUS_CLOSED).'] #' . $project['Project']['id'] . ': IR is ' . $ir.' with '.$project['SurveyVisitCache']['click'].' clicks';
							echo $message . "\n";
							CakeLog::write($log_file, $message);
						}
					}
					else { // successful: we have completes that match what we want!
						if (!$dryrun) {
							$this->Project->create();
							$this->Project->save(array('Project' => array(
								'id' => $project['Project']['id'],
								'status' => PROJECT_STATUS_OPEN,
								'started' => date(DB_DATETIME)
							)), true, array('status', 'started'));
							
							$this->ProjectLog->create();
							$this->ProjectLog->save(array('ProjectLog' => array(
								'project_id' => $project['Project']['id'],
								'type' => 'status.opened.sample',
								'description' => 'From autosampling'
							)));
							
							$sent = $this->Fed->run_queries($project, 'full');
						}
						
						$message = '['.strtoupper(PROJECT_STATUS_OPEN).'] #'.$project['Project']['id'].' launched from autosample';
						echo $message . "\n";
						CakeLog::write($log_file, $message);
						
						// used to clean up survey_invites later
						$launched_projects[] = $project['Project']['id'];
					}
				}
			}
			
			$i++;
			if (isset($limit) && !empty($limit)) {
				if ($i == $limit) {
					break;
				}
			}
		}
		
		// for launched projects, unhide survey invitations so they show up in the surveys tab
		if (!empty($launched_projects) && !$dryrun) {
			foreach ($launched_projects as $project_id) {
				$survey_users = $this->SurveyUser->find('all', array(
					'fields' => array('id'),
					'recursive' => -1,
					'conditions' => array(
						'SurveyUser.survey_id' => $project_id,
						'SurveyUser.hidden' => SURVEY_HIDDEN_SAMPLING
					)
				));

				if (!$survey_users) {
					continue;
				}

				foreach ($survey_users as $survey_user) {
					$this->SurveyUser->create();
					$this->SurveyUser->save(array('SurveyUser' => array(
						'id' => $survey_user['SurveyUser']['id'],
						'hidden' => '0'
					)), true, array('hidden'));
				}
			}
		}
	}
	
	public function autolaunch() {	
	}
	
	public function followup_sends() {
		$time_start = microtime(true);
		$log_key = strtoupper(Utils::rand(3));
		if (isset($this->args[0])) {
			$log_key .= '-'.$this->args[0];
		}
		
		ini_set('memory_limit', '1024M');
		App::import('Lib', 'MintVine');
		$dryrun = isset($this->args[0]) && $this->args[0] == 'dryrun';
		$this->lecho('Starting', 'fulcrum.followup_sends', $log_key); 
		$fed_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'fulcrum'
			)
		));
		if (!$fed_group) {
			echo 'Missing group';
			return;
		}
		$settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array('fulcrum.ir_cutoff', 'api.mintvine.username', 'api.mintvine.password', 'hostname.api'),
				'Setting.deleted' => false
			),
			'recursive' => -1,
			'fields' => array('Setting.name', 'Setting.value')
		));
		if (!$settings['fulcrum.ir_cutoff']) {
			$settings['fulcrum.ir_cutoff'] = 10;
		}
		
		if (isset($this->args[0]) && $this->args[0] != 'dryrun') {
			$projects = $this->Project->find('all', array(
				'conditions' => array(
					'Project.group_id' => $fed_group['Group']['id'],
					'Project.status' => PROJECT_STATUS_OPEN,
					'Project.active' => true,
					'Project.id' => $this->args[0]
				),
				'order' => 'Project.id DESC'
			));
		}
		else {
			$projects = $this->Project->find('all', array(
				'conditions' => array(
					'Project.group_id' => $fed_group['Group']['id'],
					'Project.status' => PROJECT_STATUS_OPEN,
					'Project.active' => true,
				),
				'order' => 'Project.id DESC'
			));
		}
		
		if (!$projects) {
			echo "Open fulcrum projects not found." . "\n";
			return;
		}
		$this->lecho('Processing ' . count($projects) . ' projects', 'fulcrum.followup_sends', $log_key); 
				
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$http->configAuth('Basic', $settings['api.mintvine.username'], $settings['api.mintvine.password']);
		
		$i = 0;
		foreach ($projects as $project) {
			$this->lecho('#'.$project['Project']['id'], 'fulcrum.followup_sends', $log_key); 
			if (empty($project['SurveyVisitCache']['complete'])) {
				$this->lecho('#'.$project['Project']['id'].' skipped: no completes', 'fulcrum.followup_sends', $log_key); 
				continue;
			}
			$results = $http->get($settings['hostname.api'].'/surveys/test_survey_status/'.$project['Project']['id']);
			$results = json_decode($results['body'], true);
			
			if ($results['close_project']) {
				$this->lecho('#'.$project['Project']['id'].' skipped: close project', 'fulcrum.followup_sends', $log_key); 
				continue;
			}
			
			$this->Query->getDataSource()->reconnect();		
			$this->Query->bindModel(array(
				'hasOne' => array('QueryStatistic'),
				'hasMany' => array('QueryHistory')
			));
			$queries = $this->Query->find('all', array(
				'contain' => array(
					'QueryStatistic',
					'QueryHistory'
				),
				'conditions' => array(
					'Query.survey_id' => $project['Project']['id'],
					'Query.parent_id' => '0'
				)
			));
			if (!$queries) {
				$this->lecho('#'.$project['Project']['id'].' skipped: no query', 'fulcrum.followup_sends', $log_key); 
				continue;
			}
			foreach ($queries as $query) {
				
				$this->Query->getDataSource()->reconnect();		
				// Get the most recent queryHistory
				$query_history = $this->Query->QueryHistory->find('first', array(
					'conditions' => array(
						'QueryHistory.query_id' => $query['Query']['id'],
						'QueryHistory.type' => 'sent',
					),
					'order' => 'QueryHistory.id desc'
				));
				// skip inactive queries
				if (!$query_history['QueryHistory']['active']) {
					$this->lecho('#'.$project['Project']['id'].' skipped: inactive queries', 'fulcrum.followup_sends', $log_key); 
					continue;
				}
				$query_history_ids = array();
				if (!empty($query['QueryHistory'])) {
					foreach ($query['QueryHistory'] as $query_history) {
						$query_history_ids[] = $query_history['id'];
					}					
				}
				$user_count = $this->SurveyUser->find('count', array(
					'conditions' => array(
						'SurveyUser.survey_id' => $project['Project']['id'],
						'SurveyUser.query_history_id' => $query_history_ids
					)
				));
				$results = QueryEngine::execute(json_decode($query['Query']['query_string'], true));
				
				// sometimes a few users are missed; no reason to keep running if we're at 96%
				if ($user_count >= round($results['count']['total'] * 0.96)) {
					$this->lecho('#'.$project['Project']['id'].' skipped: maxed', 'fulcrum.followup_sends', $log_key); 
					continue;
				}
				$query_amount = MintVine::query_amount($project, $results['count']['total'], $query); 
				$query_amount = $query_amount * 3; // just bump it up
				if ($query_amount > ($results['count']['total'] - $user_count)) {
					$query_amount = $results['count']['total'] - $user_count;
				}
				
				if (!$dryrun) {
					$this->lecho('#'.$project['Project']['id'].' sending to '.$query_amount, 'fulcrum.followup_sends', $log_key); 
					$queryHistorySource = $this->Query->QueryHistory->getDataSource();
					$queryHistorySource->begin();
					$this->Query->QueryHistory->create();
					$this->Query->QueryHistory->save(array('QueryHistory' => array(
						'query_id' => $query['Query']['id'],
						'item_id' => $query['Query']['survey_id'],
						'item_type' => TYPE_SURVEY,
						'count' => null,
						'total' => null,
						'type' => 'sending'
					)));
					$query_history_id = $this->Query->QueryHistory->getInsertId();
					$queryHistorySource->commit();
					$query = ROOT . '/app/Console/cake query create_queries ' . $query['Query']['survey_id'] . ' ' . $query['Query']['id'] . ' ' . $query_history_id . ' ' . $query_amount;
					CakeLog::write('query_commands', $query);
					// run these synchronously
					exec($query, $output);
				}
				else {
					echo $project['Project']['id'].' querying '.$query_amount."\n";
				}
			}
		}
		$this->lecho('Finished ('.(microtime(true) - $time_start).' )', 'fulcrum.followup_sends', $log_key); 
	}
	
	public function resend() {
		$lucid_settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array(
					'lucid.maintenance',
					'lucid.active',
				),
				'Setting.deleted' => false
			),
			'fields' => array(
				'Setting.name',
				'Setting.value'
			)
		));
		
		if (count($lucid_settings) < 2) {
			return false;
		}
		
		if ($lucid_settings['lucid.active'] == 'false' || $lucid_settings['lucid.maintenance'] == 'true') {
			return false;
		}		
		$dryrun = isset($this->args[0]) && $this->args[0] == 'dryrun';
		$log_file = $dryrun ? 'fulcrum.resend.dryrun' : 'fulcrum.resend';
		$fed_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'fulcrum'
			)
		));

		if (!$fed_group) {
			echo 'Missing group';
			return;
		}
		$resend = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'fulcrum.resend',
				'Setting.deleted' => false
			)
		));

		if (!$resend) { // set the default if not found.
			$resend = array('Setting' => array('value' => 30));
		}

		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.group_id' => $fed_group['Group']['id'],
				'Project.status' => PROJECT_STATUS_OPEN,
				'Project.active' => true,
			),
			'order' => 'Project.id DESC'
		));
		if (!$projects) {
			echo "Open fulcrum projects not found." . "\n";
			return;
		}

		$message = 'Processing ' . count($projects) . ' projects';
		echo $message . "\n";
		CakeLog::write($log_file, $message);
		foreach ($projects as $project) {
			$queries = $this->Query->find('all', array(
				'conditions' => array(
					'Query.survey_id' => $project['Project']['id'],
				),
				'recursive' => -1
			));
			if (!$queries) {
				$message = 'Queries not found for Project id: ' . $project['Project']['id'];
				echo $message . "\n";
				CakeLog::write($log_file, $message);
				continue;
			}

			foreach ($queries as $query) {
				
				// Skip if the query has already been resent
				if (!is_null($query['Query']['resent'])) {
					$message = 'Skipped ' . $project['Project']['id'] . ' query has already been resent on '.  $query['Query']['resent'];
					echo $message . "\n";
					CakeLog::write($log_file, $message);
					continue;
				}
				
				// Get the most recent queryHistory
				$query_history = $this->Query->QueryHistory->find('first', array(
					'conditions' => array(
						'QueryHistory.query_id' => $query['Query']['id'],
						'QueryHistory.type' => 'sent',
					),
					'order' => 'QueryHistory.id desc'
				));
				
				// Skip if last query is sent recently (before the fulcrum.resend setting minutes)
				if ($query_history && strtotime($query_history['QueryHistory']['created']) > strtotime('-' . $resend['Setting']['value'] . ' minutes')) {
					$message = 'Skipped ' . $project['Project']['id'] . ' query is sent recently.';
					echo $message . "\n";
					CakeLog::write($log_file, $message);
					continue;
				}

				if ($dryrun) {
					$message = 'Project ' . $query['Query']['survey_id'] . ', query id: ' . $query['Query']['id'] . ' will be resent.';
					echo $message . "\n";
					CakeLog::write($log_file, $message);
				}
				else {
					$exec_query = ROOT . '/app/Console/cake query resend ' . $query['Query']['id'];
					$this->Query->create();
					$this->Query->save(array('Query' => array(
						'id' => $query['Query']['id'],
						'resent' => date(DB_DATETIME)
					)), true, array('resent'));
					
					CakeLog::write('query_commands', $exec_query);
					exec($exec_query, $output);
					$message = 'Query resent: ' . $exec_query;
					echo $message . "\n";
					CakeLog::write($log_file, $message);
				}
			}
		}
	}

	public function lookups() {
		$lucid_settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array(
					'lucid.maintenance'
				),
				'Setting.deleted' => false
			),
			'fields' => array(
				'Setting.name',
				'Setting.value'
			)
		));
		
		if (count($lucid_settings) < 1) {
			return false;
		}
		
		if ($lucid_settings['lucid.maintenance'] == 'true') {
			return false;
		}
		$HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$request_data = array(
			'key' => $this->api_key
		);
		$response = $HttpSocket->get($this->api_host . 'Lookup/v1/BasicLookups/BundledLookups/StudyTypes', $request_data);
		$response['body'] = json_decode($response['body'], true);
		print_r($response);
	}
	
	// output all API calls for a given federated survey
	public function survey() {
		$lucid_settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array(
					'lucid.maintenance'
				),
				'Setting.deleted' => false
			),
			'fields' => array(
				'Setting.name',
				'Setting.value'
			)
		));
		
		if (count($lucid_settings) < 1) {
			return false;
		}
		
		if ($lucid_settings['lucid.maintenance'] == 'true') {		
			return false;
		}
		if (isset($this->args[0])) {
			$survey_id = $this->args[0];
		}
		$HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$request_data = array('key' => $this->api_key);
		if (isset($survey_id)) {
			$response = $HttpSocket->get($this->api_host . 'Supply/v1/' . 'SurveyQuotas/BySurveyNumber/' . $survey_id . '/' . $this->supplier_code, $request_data);
			echo 'SurveyQuotas/BySurveyNumber'."\n";
			print_r(json_decode($response['body'], true));

			$response = $HttpSocket->get($this->api_host . 'Supply/v1/' . 'SurveyQualifications/BySurveyNumberForOfferwall/' . $survey_id, $request_data);
			echo 'SurveyQualifications/BySurveyNumberForOfferwall'."\n";
			print_r(json_decode($response['body'], true));
			
			$response = $HttpSocket->get($this->api_host . 'Supply/v1/' . 'Surveys/SupplierAllocations/BySurveyNumber/' . $survey_id, $request_data);
			echo 'Surveys/SupplierAllocations/BySurveyNumber'."\n";
			print_r(json_decode($response['body'], true));
			
			$response = $HttpSocket->get($this->api_host . 'Supply/v1/' . 'SurveyStatistics/BySurveyNumber/' . $survey_id . '/' . $this->supplier_code, $request_data);
			echo 'SurveyStatistics/BySurveyNumber'."\n";
			print_r(json_decode($response['body'], true));
		}
		else {
			$response = $HttpSocket->get($this->api_host . 'Supply/v1/' . 'Surveys/SupplierAllocations/All', $request_data);
			$allocated_surveys = print_r(json_decode($response['body'], true));
			print_r($allocated_surveys);
		}
	}
	
	/* args: $fed_survey_id (optional) - if this argument exist, only this fulcrum survey will be imported. */
	public function import() {
		$lucid_settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array(
					'lucid.maintenance'
				),
				'Setting.deleted' => false
			),
			'fields' => array(
				'Setting.name',
				'Setting.value'
			)
		));
		
		if (count($lucid_settings) < 1) {
			return false;
		}
		
		if ($lucid_settings['lucid.maintenance'] == 'true') {		
			return false;
		}
		
		$logging_key = strtoupper(Utils::rand('4'));
		
		$this->lecho('Starting', 'fulcrum.import', $logging_key); 
		$is_single_import = isset($this->params['survey_id']) && !empty($this->params['survey_id']); 
		$has_limit = isset($this->params['limit']) && !empty($this->params['limit']); 
		$import_count = 0; 
		
		if ($is_single_import) {
			$this->lecho('Importing a single project: #F'.$this->params['survey_id'], 'fulcrum.import', $logging_key);
		}
		
		$fed_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'fulcrum'
			)
		));
		$fed_client = $this->Client->find('first', array(
			'conditions' => array(
				'Client.key' => 'fulcrum',
				'Client.deleted' => false
			)
		));
		$mv_partner = $this->Partner->find('first', array(
			'conditions' => array(
				'Partner.key' => 'mintvine',
				'Partner.deleted' => false
			)
		));
		if (!$fed_group || !$fed_client || !$mv_partner) {
			$this->lecho('Missing client, group, or partner', 'fulcrum.import', $logging_key);
			return;
		}
		
		$HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$request_data = array('key' => $this->api_key);
		$desired_countries = array(
			6, // Canada
			8, // UK
			9 // US
		);

		// Create desired allocated surveys in database
		$response = $this->Fed->get_response($HttpSocket, $this->api_host . 'Supply/v1/Surveys/AllOfferwall/' . $this->supplier_code, $request_data);
		if (!$response) {
			$this->lecho('Supply/v1/Surveys/AllOfferwall not available', 'fulcrum.import', $logging_key);
			$this->lecho('Completed', 'fulcrum.import', $logging_key);
			return;
		}
		
		$all_surveys = json_decode($response['body'], true);
		$overall_skip = array(
			'country' => 0,
			'completion' => 0,
			'loi' => 0,
			'conversion' => 0,
			'cpi' => 0
		);
		$this->lecho('Total Supply of '.count($all_surveys['Surveys']), 'fulcrum.import', $logging_key);
		$this->lecho($all_surveys['Surveys'], 'fulcrum.import', $logging_key);

		// Go through all the offerwall surveys and apply for allocation by creating supplier links
		foreach ($all_surveys['Surveys'] as $survey) {
			if ($is_single_import) {
				if ($survey['SurveyNumber'] != $this->params['survey_id']) {
					continue;
				}
			}
			$this->FedSurvey->getDataSource()->reconnect();			
			$fed_survey = $this->FedSurvey->find('first', array(
				'conditions' => array(
					'FedSurvey.fed_survey_id' => $survey['SurveyNumber']
				),
				'fields' => array('id', 'status'),
				'recursive' => -1
			));
			// if it's already imported, skip it
			if ($fed_survey && $fed_survey['FedSurvey']['status'] != 'failed.link') {
				continue;
			}
			
			// skip innovate projects for now - note: also need to check all supplierallocation calls
			if ($survey['StudyTypeID'] == 23) {
				$this->lecho('Skipping #F'.$survey['SurveyNumber'].': unsupported link type', 'fulcrum.import', $logging_key);
				// add in a placeholder Fulcrum # so we don't try to re-import this
				$this->FedSurvey->create();
				$this->FedSurvey->save(array('FedSurvey' => array(
					'survey_id' => '0',
					'fed_survey_id' => $survey['SurveyNumber'],
					'status' => 'skipped.link.unsupported'
				)));
				continue;
			}

			// Figure out whether we want this survey or not
			if (!in_array($survey['CountryLanguageID'], $desired_countries)) { // only US, UK and CA surveys
				$overall_skip['country']++;
				$this->lecho('Skipping #F'.$survey['SurveyNumber'].': unsupported country', 'fulcrum.import', $logging_key);
				// add in a placeholder Fulcrum # so we don't try to re-import this
				$this->FedSurvey->create();
				$this->FedSurvey->save(array('FedSurvey' => array(
					'survey_id' => '0',
					'fed_survey_id' => $survey['SurveyNumber'],
					'status' => 'skipped.country'
				)));
				continue;
			}
			
			try {
				$response = $HttpSocket->post(
					$this->api_host . 'Supply/v1/' . 'SupplierLinks/Create/' . $survey['SurveyNumber'] . '/' . $this->supplier_code . '?key=' . $this->api_key, 
					json_encode(array(
						'SupplierLinkTypeCode' => 'OWS',
						'TrackingTypeCode' => 'NONE'
					)),
					array(
						'header' => array('Content-Type' => 'application/json')
					)
				);
				if ($response->code == 200) {
					$this->lecho('Link creation succeeded on #F'.$survey['SurveyNumber'], 'fulcrum.import', $logging_key);
					if ($fed_survey) {
						$this->FedSurvey->delete($fed_survey['FedSurvey']['id']); // delete the failed link response
					}
				}
				else {
					$this->lecho('Failed link creation on #F'.$survey['SurveyNumber'], 'fulcrum.import', $logging_key);
					$this->lecho($response, 'fulcrum.import', $logging_key);
					$this->lecho($response, 'fulcrum.import.link', $logging_key);
					$this->FedSurvey->create();
					$this->FedSurvey->save(array('FedSurvey' => array(
						'survey_id' => '0',
						'fed_survey_id' => $survey['SurveyNumber'],
						'status' => 'failed.link'
					)));
				}
			} catch (Exception $e) {
				$this->lecho('Failed link creation on #F'.$survey['SurveyNumber'], 'fulcrum.import', $logging_key);
				$this->lecho($e, 'fulcrum.import', $logging_key);
				$this->FedSurvey->create();
				$this->FedSurvey->save(array('FedSurvey' => array(
					'survey_id' => '0',
					'fed_survey_id' => $survey['SurveyNumber'],
					'status' => 'failed.link'
				)));
				// note with both of these failed links; we allow for recover on the next future import
			}
		}
		
		$this->lecho($overall_skip, 'fulcrum.import', $logging_key); 
		
		if ($is_single_import) {
			$response = $this->Fed->get_response($HttpSocket, $this->api_host . 'Supply/v1/Surveys/SupplierAllocations/BySurveyNumber/' . $this->params['survey_id'], $request_data);
			if (!$response) {
				$this->lecho('Single import of project failed', 'fulcrum.import', $logging_key); 
				$this->lecho($response, 'fulcrum.import', $logging_key); 
				$this->lecho('Completed', 'fulcrum.import', $logging_key);
				return;
			}
			$response = json_decode($response['body'], true);
			$this->lecho('Single import of project succeeded', 'fulcrum.import', $logging_key); 			
			$this->lecho($response, 'fulcrum.import', $logging_key); 
			$allocated_surveys['SupplierAllocationSurveys'] = array($response['SupplierAllocationSurvey']);
		}
		else {
			$response = $this->Fed->get_response($HttpSocket, $this->api_host . 'Supply/v1/Surveys/SupplierAllocations/All', $request_data);
			if (!$response) {
				$this->lecho('Multiple import of projects failed', 'fulcrum.import', $logging_key); 
				$this->lecho($response, 'fulcrum.import', $logging_key); 
				$this->lecho('Completed', 'fulcrum.import', $logging_key);
				return;
			}			
			$allocated_surveys = json_decode($response['body'], true);
			$this->lecho('Multiple import of project succeeded:', 'fulcrum.import', $logging_key); 			
			$this->lecho($allocated_surveys, 'fulcrum.import', $logging_key); 
		}
		
		$i = 0;
		$skipped = array(
			'total' => count($allocated_surveys['SupplierAllocationSurveys']),
			'exists' => 0, // project already exists
			'link' => 0, // failed link generation
			'loi' => 0, // loi too long
			'conversion' => 0, // conversion too little
			'quota' => 0, // quota too low
			'errors' => 0, // general API errors,
			'country' => 0
		);
		foreach ($allocated_surveys['SupplierAllocationSurveys'] as $survey) {
			
			// not sure when this happens...
			if (empty($survey['SurveyNumber'])) {
				continue;
			}
			
			$fed_survey = $this->FedSurvey->find('first', array(
				'conditions' => array(
					'FedSurvey.fed_survey_id' => $survey['SurveyNumber'],
					'FedSurvey.survey_id' => '0',
				)
			));
			
			// skip innovate projects for now
			if ($survey['StudyTypeID'] == 23) {
				$this->lecho('Skipping #F'.$survey['SurveyNumber'].': unsupported link type', 'fulcrum.import', $logging_key);
				// update placeholder Fulcrum # so we don't try to re-import this
				$this->FedSurvey->create();
				$this->FedSurvey->save(array('FedSurvey' => array(
					'id' => $fed_survey['FedSurvey']['id'],
					'survey_id' => '0',
					'status' => 'skipped.link.unsupported'
				)), true, array('status', 'survey_id'));
				continue;
			}
			
			// this project does not fit one of our pre-entry requirements (LOI or country)
			if ($fed_survey && $fed_survey['FedSurvey']['status'] == 'skipped.country') {
				$skipped['country']++; 
				continue;
			}
			
			// link failed to be created; don't create this project
			if ($fed_survey && $fed_survey['FedSurvey']['status'] == 'failed.link') {
				$skipped['link']++; 
				continue;
			}
			
			// this project has been linked to an adhoc project by a PM and should not be handled automatically
			if ($fed_survey && $fed_survey['FedSurvey']['status'] == 'skipped.adhoc') {
				$skipped['link']++; 
				continue;
			}
			
			// there shouldn't be a fed_survey entry for any exsiting projects
			if ($fed_survey) {
				$skipped['exists']++; 
				continue; 
			}
			
			// Addressing 2006 MySQL server has gone away - error
			$this->Project->getDatasource()->reconnect();
			$this->Project->bindModel(array(
				'hasOne' => array(
					'FedSurvey' => array(
						'className' => 'FedSurvey',
						'foreignKey' => 'survey_id'
					)
				)
			));
			$project = $this->Project->find('first', array(
				'contain' => array(
					'FedSurvey'
				),
				'conditions' => array(
					'FedSurvey.fed_survey_id' => $survey['SurveyNumber']
				)
			));
			
			if ($project) {
				$skipped['exists']++; 
				continue;
			}
			
			if(!empty($survey['LengthOfInterview']) && $survey['LengthOfInterview'] > 30) {				
				$this->lecho('Skip import of #F'.$survey['SurveyNumber'].' due to a LOI of '.$survey['LengthOfInterview'], 'fulcrum.import', $logging_key);
				$this->FedSurvey->create();
				$this->FedSurvey->save(array('FedSurvey' => array(
					'survey_id' => '0',
					'fed_survey_id' => $survey['SurveyNumber'],
					'status' => 'skipped.loi'
				)));
				$skipped['loi']++;
				continue;
			}

			$response = $this->Fed->get_response($HttpSocket, $this->api_host . 'Supply/v1/SurveyQuotas/BySurveyNumber/' . $survey['SurveyNumber'] . '/' . $this->supplier_code, $request_data);
			if (!$response) {
				$this->lecho('Failed to retrieve quotas on #F'.$survey['SurveyNumber'], 'fulcrum.import', $logging_key);
				$skipped['errors']++;
				// don't save fed_survey here so we can re-import in case the API is down
				continue;
			}
			$survey_quotas = json_decode($response['body'], true);
			$this->lecho('Quotas retrieved for #F'.$survey['SurveyNumber'], 'fulcrum.import', $logging_key);
			$this->lecho($survey_quotas, 'fulcrum.import', $logging_key);
						
			$response = $this->Fed->get_response($HttpSocket, $this->api_host . 'Supply/v1/' . 'Surveys/SupplierAllocations/BySurveyNumber/' . $survey['SurveyNumber'], $request_data);
			if (!$response) {
				$this->lecho('Failed to retrieve supplier allocation on #F'.$survey['SurveyNumber'], 'fulcrum.import', $logging_key);
				$skipped['errors']++;
				continue;
			}
			
			$survey_allocation = json_decode($response['body'], true);
			$this->lecho('Allocation retrieved for #F'.$survey['SurveyNumber'], 'fulcrum.import', $logging_key);
			$this->lecho($survey_allocation, 'fulcrum.import', $logging_key);
			
			$payout = (isset($survey_quotas['SurveyQuotas'][0]['QuotaCPI'])) ? $survey_quotas['SurveyQuotas'][0]['QuotaCPI'] : 0;
			$payout_to_partners = round($payout * 4 / 10, 2);
			$award = $payout_to_partners;
			if ($award > 2) {
				$award = 2;
			}
			
			$client_survey_link = '';
			$survey_quota = 0;

			foreach ($survey_quotas['SurveyQuotas'] as $quota) {
				if ($quota['SurveyQuotaType'] == 'Total') {
					$survey_quota = $quota['NumberOfRespondents'];
				}
			}
			
			$direct_allocation = false;
			if (isset($survey_allocation['SupplierAllocationSurvey']['SupplierAllocations'][0]['TargetModels'][0]['LiveSupplierLink'])) {
				$direct_allocation = true;
				$this->lecho('Direct #F' . $survey['SurveyNumber'] . ': Direct Supplier Allocation', 'fulcrum.import', $logging_key);
				$client_survey_link = $survey_allocation['SupplierAllocationSurvey']['SupplierAllocations'][0]['TargetModels'][0]['LiveSupplierLink'];
			}
			elseif (isset($survey_allocation['SupplierAllocationSurvey']['OfferwallAllocations'][0]['TargetModel']['LiveSupplierLink'])) {
				$client_survey_link = $survey_allocation['SupplierAllocationSurvey']['OfferwallAllocations'][0]['TargetModel']['LiveSupplierLink'];
			}
			else {
				// this shouldn't happen anymore since we are being more rigid at creation time... but just inc ase
				$this->lecho('Skipping #F'.$survey['SurveyNumber'].': Missing client link', 'fulcrum.import', $logging_key);
				$this->FedSurvey->create();
				$this->FedSurvey->save(array('FedSurvey' => array(
					'survey_id' => '0',
					'fed_survey_id' => $survey['SurveyNumber'],
					'status' => 'failed.link'
				)));
				$skipped['link']++;
				continue;
			}
			
			// Sanity check - this should have been done at link creation time
			if (!in_array($survey['CountryLanguageID'], $desired_countries)) {
				$this->lecho('Skipping #F'.$survey['SurveyNumber'].': unsupported country', 'fulcrum.import', $logging_key);
				// add in a placeholder Fulcrum # so we don't try to re-import this
				$this->FedSurvey->create();
				$this->FedSurvey->save(array('FedSurvey' => array(
					'survey_id' => '0',
					'fed_survey_id' => $survey['SurveyNumber'],
					'status' => 'skipped.country'
				)));
				$skipped['country']++;
				continue;
			}
			
			$save = false;
			$projectSource = $this->Project->getDataSource();
			$projectSource->begin();
			$this->Project->create();
			$save = $this->Project->save(array('Project' => array(
				'prj_name' => 'Fulcrum #' . $survey['SurveyNumber']. ' ' .$survey['SurveyName'],
				'client_id' => $fed_client['Client']['id'],
				'date_created' => date(DB_DATETIME),
				'bid_ir' => $survey['Conversion'],
				'client_rate' => $payout,
				'partner_rate' => ($payout > 0 ) ? $payout_to_partners : 0,
				'user_payout' => ($award > 0 ) ? $award : 0,
				'quota' => ($survey_quota > 0) ? $survey_quota : 1,
				'est_length' => !empty($survey['LengthOfInterview']) ? $survey['LengthOfInterview']: 15,
				'group_id' => $fed_group['Group']['id'],
				'status' => PROJECT_STATUS_STAGING,
				'client_project_id' => $survey['SurveyNumber'],
				'singleuse' => true,
				'country' => FedMappings::country($survey_allocation['SupplierAllocationSurvey']['CountryLanguageID']),
				'language' => FedMappings::language($survey_allocation['SupplierAllocationSurvey']['CountryLanguageID']),
				'survey_name' => 'Survey for you!',
				'award' => ($award > 0 ) ? intval($award * 100) : 5,
				'active' => false, // after qualifications load, we'll activate it
				'dedupe' => 1,
				'client_survey_link' => ($client_survey_link) ? $client_survey_link . '{{ID}}' : '',
				'description' => 'Survey for you!',
			)));
			if ($save) {
				$project_id = $this->Project->getInsertId();
				
				// Update mask field
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project_id,
					'mask' => $survey['SurveyNumber']
				)), true, array('mask'));
				$projectSource->commit();
				$date = $survey['FieldBeginDate'];
				
				$this->Project->FedSurvey->create();
				$this->Project->FedSurvey->save(array('FedSurvey' => array(
					'survey_id' => $project_id,
					'fed_survey_id' => $survey['SurveyNumber'],
					'current_quota' => $survey_quota,
					'status' => FEDSURVEY_CREATED,
					'direct' => $direct_allocation
				)));
				
				// add mintvine as a partner
				$this->Project->SurveyPartner->create();
				$this->Project->SurveyPartner->save(array('SurveyPartner' => array(
					'survey_id' => $project_id,
					'partner_id' => $mv_partner['Partner']['id'],
					'rate' => ($payout > 0 ) ? $payout_to_partners : 0,
					'complete_url' => HOSTNAME_WWW.'/surveys/complete/{{ID}}/',
					'nq_url' => HOSTNAME_WWW.'/surveys/nq/{{ID}}/',
					'oq_url' => HOSTNAME_WWW.'/surveys/oq/{{ID}}/',
					'pause_url' => HOSTNAME_WWW.'/surveys/paused/',
					'fail_url' => HOSTNAME_WWW.'/surveys/sec/{{ID}}/',
				)));
				$save = true;
			}
			elseif ($this->Project->validationErrors) {
				$projectSource->commit();
				$this->lecho('[ERROR] Failed saving survey due to internal error', 'fulcrum.auto', $logging_key); 
				$this->lecho($this->Project->validationErrors, 'fulcrum.auto', $logging_key); 
			}
			else {
				$projectSource->commit();
			}

			if ($save) {
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project_id,
					'type' => 'created',
					'description' => ''
				)));
				
				$this->lecho('[SUCCESS] #F'.$survey['SurveyNumber'].' created successfully (#'.$project_id.')', 'fulcrum.auto', $logging_key); 
				// Save qualifications for this imported survey
				$this->params['survey_id'] = $survey['SurveyNumber'];
				$this->qualifications();
				$import_count++;
				if ($has_limit && $import_count >= $this->params['limit']) {
					break;
				}
			}
			else {
				$this->lecho('[ERROR] Failed saving project due to internal error', 'fulcrum.auto', $logging_key); 
				$this->lecho($this->Project->validationErrors, 'fulcrum.auto', $logging_key); 
				continue;
			}
		}
		$this->lecho('Total skip count: '.array_sum($skipped), 'fulcrum.import', $logging_key); 
		$this->lecho($skipped, 'fulcrum.import', $logging_key); 
		$this->lecho('Completed', 'fulcrum.import', $logging_key);
	}
	
	public function create_entry_link() {
		$lucid_settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array(
					'lucid.maintenance'
				),
				'Setting.deleted' => false
			),
			'fields' => array(
				'Setting.name',
				'Setting.value'
			)
		));
		
		if (count($lucid_settings) < 1) {
			return false;
		}
		
		if ($lucid_settings['lucid.maintenance'] == 'true') {		
			return false;
		}
		
		if (!isset($this->args[0])) {
			return;
		}
		$HttpSocket = new HttpSocket(array(
			'timeout' => 5,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$request_data = array('key' => $this->api_key);
		$response = $HttpSocket->post(
			$this->api_host . 'Supply/v1/' . 'SupplierLinks/Create/' . $this->args[0] . '/' . $this->supplier_code . '?key=' . $this->api_key, 
			json_encode(array(
				'SupplierLinkTypeCode' => 'OWS',
				'TrackingTypeCode' => 'NONE'
			)),
			array(
				'header' => array('Content-Type' => 'application/json')
			)
		); 
		print_r(json_decode($response->body, true));
	}
	
	// Update project & survey modals + qualifications (prescreeners + queries)
	function update() {
		$lucid_settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array(
					'lucid.maintenance'
				),
				'Setting.deleted' => false
			),
			'fields' => array(
				'Setting.name',
				'Setting.value'
			)
		));
		
		if (count($lucid_settings) < 1) {
			return false;
		}
		
		if ($lucid_settings['lucid.maintenance'] == 'true') {		
			return false;
		}
		$logging_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		ini_set('memory_limit', '740M');
			
		$status = !isset($this->args[0]) || $this->args[0] == 'open' ? array(PROJECT_STATUS_STAGING, PROJECT_STATUS_OPEN): array(PROJECT_STATUS_STAGING);
		$this->lecho('Starting '.implode(', ', $status), 'fulcrum.update', $logging_key);
		
		$mv_partner = $this->Partner->find('first', array(
			'conditions' => array(
				'Partner.key' => 'mintvine',
				'Partner.deleted' => false
			)
		));
		if (!$mv_partner) {
			$this->lecho('ERROR: MintVine partner is missing.', 'fulcrum.update', $logging_key);
			return;
		}
		
		$fed_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'fulcrum'
			)
		));
		
		if (!isset($this->params['survey_id'])) {
			$this->FedSurvey->bindModel(array(
				'belongsTo' => array(
					'Project' => array(
						'className' => 'Project',
						'foreignKey' => 'survey_id'
					)
				)
			));
			$fed_surveys = $this->FedSurvey->find('list', array(
				'fields' => array('id', 'survey_id'),
				'contain' => array('Project'),
				'conditions' => array(
					'Project.group_id' => $fed_group['Group']['id'],
					'Project.status' => $status
				),
				'order' => 'Project.id DESC' // update open most recently created first
			));
			$this->lecho('Updating '.count($fed_surveys).' surveys.', 'fulcrum.update', $logging_key);
		}
		else {
			$this->FedSurvey->bindModel(array(
				'belongsTo' => array(
					'Project' => array(
						'className' => 'Project',
						'foreignKey' => 'survey_id'
					)
				)
			));
			$fed_surveys = $this->FedSurvey->find('list', array(
				'fields' => array('id', 'survey_id'),
				'contain' => array('Project'),
				'conditions' => array(
					'Project.group_id' => $fed_group['Group']['id'],
					'FedSurvey.fed_survey_id' => $this->params['survey_id']
				)
			));
			$this->lecho('Updating #F '.$this->params['survey_id'], 'fulcrum.update', $logging_key);
		}
		if (empty($fed_surveys)) {
			$this->lecho('No projects found to update', 'fulcrum.update', $logging_key);
			return;
		}
		
		$this->Project->bindModel(array(
			'hasOne' => array(
				'FedSurvey' => array(
					'className' => 'FedSurvey',
					'foreignKey' => 'survey_id'
				)
			)
		));
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.id' => array_values($fed_surveys),
				'Project.group_id' => $fed_group['Group']['id'],
			)
		));
		if (!$projects) {
			return;
		}
		
		$HttpSocket = new HttpSocket(array(
			'timeout' => 5,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$request_data = array('key' => $this->api_key);
		
		
		$api_settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array('api.mintvine.username', 'api.mintvine.password', 'hostname.api'),
				'Setting.deleted' => false
			),
			'fields' => array('name', 'value')
		));
		
		$api_http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$api_http->configAuth('Basic', $api_settings['api.mintvine.username'], $api_settings['api.mintvine.password']);
		
		$i = 0; 
		// Go through each project and update accordingly
		foreach ($projects as $project) {
			$i++;
			$this->lecho('[#'.$project['Project']['id'].'] Starting '.$i.'/'.count($projects), 'fulcrum.update', $logging_key);
			
			// update the timestamps so we don't retrieve the same projects over and over again.
			if (!isset($this->params['survey_id'])) {
				$this->FedSurvey->create();
				$this->FedSurvey->save(array('FedSurvey' => array(
					'id' => $project['FedSurvey']['id'],
					'last_updated' => date(DB_DATETIME)
				)), true, array('last_updated'));
				$this->lecho('[#'.$project['Project']['id'].'] Timestamp updated to '.date(DB_DATETIME), 'fulcrum.update', $logging_key);
			}
			$url = 'Supply/v1/SurveyQuotas/BySurveyNumber/'.$project['FedSurvey']['fed_survey_id'].'/'.$this->supplier_code;
			$response = $this->Fed->get_response($HttpSocket, $this->api_host . $url, $request_data);
			if (!$response) {
				$this->lecho('[#'.$project['Project']['id'].'] Failed to retrieve '.$url, 'fulcrum.update', $logging_key);
				continue;
			}
			$survey_quotas = json_decode($response['body'], true);
			$this->lecho($survey_quotas, 'fulcrum.update', $logging_key);
			
			$survey_quota = 0;
			foreach ($survey_quotas['SurveyQuotas'] as $quota) {
				if ($quota['SurveyQuotaType'] == 'Total') {
					$survey_quota = $quota['NumberOfRespondents'];
				}
			}
			$this->lecho('[#'.$project['Project']['id'].'] Survey quota: '.$survey_quota, 'fulcrum.update', $logging_key);

			$url = 'Supply/v1/Surveys/SupplierAllocations/BySurveyNumber/'.$project['FedSurvey']['fed_survey_id'];
			$response = $this->Fed->get_response($HttpSocket, $this->api_host . $url, $request_data);
			if (!$response) {
				$this->lecho('[#'.$project['Project']['id'].'] Failed to retrieve '.$url, 'fulcrum.update', $logging_key);
				continue;
			}
			$survey_allocation = json_decode($response['body'], true);
			$this->lecho($survey_allocation, 'fulcrum.update', $logging_key);
			
			$url = 'Supply/v1/SurveyStatistics/BySurveyNumber/'.$project['FedSurvey']['fed_survey_id'].'/'.$this->supplier_code; 
			$response = $this->Fed->get_response($HttpSocket, $this->api_host . $url, $request_data);
			if (!$response) {
				$this->lecho('[#'.$project['Project']['id'].'] Failed to retrieve '.$url, 'fulcrum.update', $logging_key);
				continue;
			}			
			$survey_statistics = json_decode($response['body'], true);
			$this->lecho($survey_statistics, 'fulcrum.update', $logging_key);
			
			$payout = (isset($survey_quotas['SurveyQuotas'][0]['QuotaCPI'])) ? $survey_quotas['SurveyQuotas'][0]['QuotaCPI'] : 0;
			$payout_to_partners = round($payout * 4 / 10, 2);
			$award = $payout_to_partners;
			if ($award > 2) {
				$award = 2;
			}
			$this->lecho('[#'.$project['Project']['id'].'] Award: '.$award.'; Payout: '.$payout.'; Payout To Partners: '.$payout_to_partners, 'fulcrum.update', $logging_key);

			$direct_allocation = false;
			$client_survey_link = '';
			if (isset($survey_allocation['SupplierAllocationSurvey']['SupplierAllocations'][0]['TargetModels'][0]['LiveSupplierLink'])) {
				$direct_allocation = true;
				$client_survey_link = $survey_allocation['SupplierAllocationSurvey']['SupplierAllocations'][0]['TargetModels'][0]['LiveSupplierLink'];
			}
			elseif (isset($survey_allocation['SupplierAllocationSurvey']['OfferwallAllocations'][0]['TargetModel']['LiveSupplierLink'])) {
				$client_survey_link = $survey_allocation['SupplierAllocationSurvey']['OfferwallAllocations'][0]['TargetModel']['LiveSupplierLink'];
			}
			$this->lecho('[#'.$project['Project']['id'].'] Client Survey Link: '.$client_survey_link, 'fulcrum.update', $logging_key);

			$this->FedSurvey->create();
			$this->FedSurvey->save(array('FedSurvey' => array(
				'id' => $project['FedSurvey']['id'],
				'direct' => $direct_allocation
			)), true, array('direct'));
			
			// Update Project & Survey models
			$updated_project = array('Project' => array(
				'id' => $project['Project']['id'],
			));

			// Get current client_rate
			if ($project['Project']['project_rate_id']) {
				$project_rate = $this->ProjectRate->find('first', array(
					'conditions' => array(
						'id' => $project['Project']['project_rate_id']
					),
					'fields' => array('client_rate')
				));
				$project_rate = $project_rate['ProjectRate']['client_rate'];
			}
			else {
				$project_rate = $project['Project']['client_rate'];
			}

			$payout_changed = false;
			// if api client_rate changes, we either save a new projectRate row, or find an existing one to use.
			if ($project_rate != $payout) {
				$payout_changed = true;
				$project_rate = $this->ProjectRate->find('first', array(
					'conditions' => array(
						'client_rate' => $payout,
						'project_id' => $project['Project']['id']
					),
					'fields' => array('id')
				));

				if ($project_rate) {
					$updated_project['Project']['project_rate_id'] = $project_rate['ProjectRate']['id'];
				}
				else {
					$projectRateSource = $this->ProjectRate->getDataSource();
					$projectRateSource->begin();
					$this->ProjectRate->create();
					$save = $this->ProjectRate->save(array('ProjectRate' => array(
						'project_id' => $project['Project']['id'],
						'client_rate' => $payout,
						'award' => ($award > 0 ) ? intval($award * 100) : 0,
					)));
					if ($save) {
						$updated_project['Project']['project_rate_id'] = $this->ProjectRate->getLastInsertID();
					}
					$projectRateSource->commit();
				}
				$updated_project['Project']['user_payout'] = ($award > 0) ? $award : 0;
			}

			if ($survey_statistics['SurveyStatistics']['GlobalTrailingSystemConversion']) {
				$bid_ir = $survey_statistics['SurveyStatistics']['GlobalTrailingSystemConversion'] * 100;
			}
			else {
				$bid_ir = $survey_allocation['SupplierAllocationSurvey']['Conversion'];
			}
			if (empty($bid_ir)) {
				$bid_ir = $survey_allocation['SupplierAllocationSurvey']['BidIncidence'];
			}

			$this->lecho('[#'.$project['Project']['id'].'] Bid IR: '.$bid_ir, 'fulcrum.update', $logging_key);

			if ($project['Project']['bid_ir'] != $bid_ir) {
				$updated_project['Project']['bid_ir'] = $bid_ir;
			}

			if ($survey_statistics['SurveyStatistics']['TrailingObservedLOI']) {
				$est_length = $survey_statistics['SurveyStatistics']['TrailingObservedLOI'];
			}
			elseif ($survey_allocation['SupplierAllocationSurvey']['LengthOfInterview']) {
				$est_length = $survey_allocation['SupplierAllocationSurvey']['LengthOfInterview'];
			}
			else {
				$est_length = 15;
			}
			
			$this->lecho('[#'.$project['Project']['id'].'] Estimated Length: '.$est_length, 'fulcrum.update', $logging_key);
			
			if ($project['Project']['est_length'] != $est_length) {
				$updated_project['Project']['est_length'] = $est_length;
			}
			
			if ($project['Project']['country'] != FedMappings::country($survey_allocation['SupplierAllocationSurvey']['CountryLanguageID'])) {
				$updated_project['Project']['country'] = FedMappings::country($survey_allocation['SupplierAllocationSurvey']['CountryLanguageID']);
			}

			// If updated quota is available, we add our completes to it.
			$survey_visit_cache = $this->SurveyVisitCache->find('first', array(
				'fields' => array('complete'),
				'conditions' => array(
					'SurveyVisitCache.survey_id' => $project['Project']['id']
				),
				'recursive' => -1
			));
			if ($survey_quota && $survey_quota > 0) {
				$updated_quota = $survey_quota + $survey_visit_cache['SurveyVisitCache']['complete'];
				if ($project['Project']['quota'] != $updated_quota) {
					$updated_project['Project']['quota'] = $updated_quota;
				}
			}
			
			$project_updated_keys = array_keys($updated_project['Project']);
			if (count($project_updated_keys) > 1) {
				$existing_project = $this->Project->find('first', array(
					'conditions' => array(
						'Project.id' => $updated_project['Project']['id']
					)
				));
				$this->Project->create();
				$this->Project->save($updated_project, true, $project_updated_keys);
				
				$this->lecho('[#'.$project['Project']['id'].'] Saving Project:', 'fulcrum.update', $logging_key);
				$this->lecho($updated_project, 'fulcrum.update', $logging_key);
			
				unset($project_updated_keys[0]); // id
				$log_str = '';
				foreach ($project_updated_keys as $updated_key) {
					$log_str .= $updated_key . ' updated from ' . $existing_project['Project'][$updated_key] . ' to ' . $updated_project['Project'][$updated_key] . '; ';
				}
				
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $updated_project['Project']['id'],
					'type' => 'updated',
					'description' => $log_str
				)));
			}

			// don't update survey name or paused status as it may have been edited by MV
			$updated_project = array('Project' => array(
				'id' => $project['Project']['id'],
				'language' => FedMappings::language($survey_allocation['SupplierAllocationSurvey']['CountryLanguageID']),
				'client_survey_link' => ($client_survey_link) ? $client_survey_link . '{{ID}}' : '',
			));

			if ($payout_changed) {
				$updated_project['Project']['award'] = ($award > 0) ? intval($award * 100) : 0;
			}
			
			$this->Project->create();
			$this->Project->save($updated_project, true, array_keys($updated_project['Project']));

			$this->lecho('[#'.$project['Project']['id'].'] Saving Survey:', 'fulcrum.update', $logging_key);
			$this->lecho($updated_project, 'fulcrum.update', $logging_key);
			
			// Update Survey partner payouts
			$survey_partner = $this->Project->SurveyPartner->find('first', array(
				'conditions' => array(
					'SurveyPartner.survey_id' => $project['Project']['id'],
					'partner_id' => $mv_partner['Partner']['id'],
				))
			);

			if ($survey_partner) {
				$this->Project->SurveyPartner->create();
				$this->Project->SurveyPartner->save(array('SurveyPartner' => array(
					'id' => $survey_partner['SurveyPartner']['id'],
					'rate' => ($payout > 0 ) ? $payout_to_partners : 0,
				)), true, array('rate'));
			}

			$this->lecho('[#'.$project['Project']['id'].'] Completed', 'fulcrum.update', $logging_key);
			
			// on update, do a manual check on closing rules
			if (in_array($project['Project']['status'], array(PROJECT_STATUS_OPEN, PROJECT_STATUS_SAMPLING))) {
				$results = $api_http->post($api_settings['hostname.api'].'/surveys/test_survey_status/'.$project['Project']['id']);
			}
			
			// Update qualifications
			$this->params['survey_id'] = $project['FedSurvey']['fed_survey_id'];
			$this->qualifications();
		}
		$this->lecho('Completed (Execution time: '.(microtime(true) - $time_start).')', 'fulcrum.update', $logging_key);
	}
	
	function close() {
		$lucid_settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array(
					'lucid.maintenance'
				),
				'Setting.deleted' => false
			),
			'fields' => array(
				'Setting.name',
				'Setting.value'
			)
		));
		
		if (count($lucid_settings) < 1) {
			return false;
		}
		
		if ($lucid_settings['lucid.maintenance'] == 'true') {		
			return false;
		}
		
		if (isset($this->args[0])) {
			$fed_surveys = $this->FedSurvey->find('list', array(
				'fields' => array('id', 'survey_id'),
				'conditions' => array(
					'FedSurvey.fed_survey_id' => $this->args[0]
				)
			));
		}
		else {
			$this->FedSurvey->bindModel(array(
				'belongsTo' => array(
					'Project' => array(
						'className' => 'Project',
						'foreignKey' => 'survey_id'
					)
				)
			));

			$fed_surveys = $this->FedSurvey->find('list', array(
				'fields' => array('id', 'survey_id'),
				'contain' => array('Project'),
				'conditions' => array(
					'Project.status' => array(PROJECT_STATUS_OPEN, PROJECT_STATUS_STAGING, PROJECT_STATUS_SAMPLING)
				),
				'order' => 'Project.status ASC, Project.id DESC' // update open most recently created first
			));
		}
		
		if (empty($fed_surveys)) {
			echo 'Projects not found!';
			return;
		}
		
		$fed_group = $this->Group->find('first', array(
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
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.id' => array_values($fed_surveys),
				'Project.group_id' => $fed_group['Group']['id']
			)
		));
		if (!$projects) {
			return;
		}
		
		$HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$request_data = array('key' => $this->api_key);
		
		// determine allocated_surveys only once here (needed to determine closed surveys).
		$allocated_surveys_ids = array();
		$response = $this->Fed->get_response($HttpSocket, $this->api_host . 'Supply/v1/' . 'Surveys/SupplierAllocations/All', $request_data);
		if (!$response) {
			echo "Api call failed to get Supplier allocations." . "\n";
		}
		else {
			$allocated_surveys = json_decode($response['body'], true);
			foreach ($allocated_surveys['SupplierAllocationSurveys'] as $survey) {
				$allocated_surveys_ids[$survey['SurveyNumber']] = $survey['SurveyNumber'];
			}
		}

		$HttpSocket->config['timeout'] = 5;
		foreach ($projects as $project) {
			$force_close = false;
			if ($project['Project']['status'] == PROJECT_STATUS_SAMPLING && $project['Project']['active']) {
				$survey_visit = $this->SurveyVisit->find('first', array(
					'conditions' => array(
						'SurveyVisit.survey_id' => $project['Project']['id'],
					),
					'fields' => array('id', 'created'),
					'order' => 'SurveyVisit.id DESC',
					'limit' => 1
				));
				if ($survey_visit && strtotime($survey_visit['SurveyVisit']['created']) < strtotime('-24 hours')) {					
					$this->Project->getDatasource()->reconnect();
					
					$this->Project->create();
					$this->Project->save(array('Project' => array(
						'id' => $project['Project']['id'],
						'status' => PROJECT_STATUS_CLOSED,
						'active' => false,
						// update ended if it's blank - otherwise leave the old value
						'ended' => empty($project['Project']['ended']) ? date(DB_DATETIME) : $project['Project']['ended']
					)), true, array('status', 'active', 'ended'));

					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $project['Project']['id'],
						'type' => 'status.closed.fulcrum',
						'description' => 'Closed by lack of activity in the past 24 hours as a sampling project'
					)));
					continue;
				}
			}
			$response = $this->Fed->get_response($HttpSocket, $this->api_host . 'Supply/v1/SurveyQuotas/BySurveyNumber/' . $project['FedSurvey']['fed_survey_id'] . '/' . $this->supplier_code, $request_data);
			if (!$response) {
				echo "Api call failed to get quota for " . $project['FedSurvey']['fed_survey_id'] . "\n";
				continue;
			}

			$survey_quotas = json_decode($response['body'], true);
			$survey_quota = 0;
			foreach ($survey_quotas['SurveyQuotas'] as $quota) {
				if ($quota['SurveyQuotaType'] == 'Total') {
					$survey_quota = $quota['NumberOfRespondents'];
				}
			}
			
			// Close only if the project is still marked open
			if (!in_array($project['Project']['status'], array(PROJECT_STATUS_OPEN, PROJECT_STATUS_STAGING, PROJECT_STATUS_SAMPLING))) {
				echo "Project id: ".$project['Project']['id']." is not Open.";
				continue;
			}
			
			$close = false;
			$message = '';
			
			$allocation_call_successful = true;
			try {
				$survey_allocation = $HttpSocket->get($this->api_host . 'Supply/v1/Surveys/SupplierAllocations/BySurveyNumber/' . $project['FedSurvey']['fed_survey_id'], $request_data);
				if ($response->code != 200) {
					$allocation_call_successful = false;
					echo 'Error: ' . $response->reasonPhrase . "\n";
				}
			} catch (Exception $e) {
				$allocation_call_successful = false;
				echo 'Error: Survey Allocation api call failed.' . "\n";
			}
			
			$internal_description = null;
			
			if ($allocation_call_successful) {
				$survey_allocation = json_decode($survey_allocation['body'], true);
				
				if (!$survey_allocation['SupplierAllocationSurvey']['SurveyNumber']) { // not having allocation means project is closed
					$close = true;
					$message = "Project id: " . $project['Project']['id'] . " closed no allocation.";
				}
					
				if ($survey_allocation['SupplierAllocationSurvey']['LengthOfInterview'] > 30) {
					$close = true;
					$message = "Project id: " . $project['Project']['id'] . " closed because loi > 30.";
					$internal_description = 'loi';
				}
			}
			
			if ((string) $survey_quotas['SurveyStillLive'] !== '1') {
				$close = true;
				$message = "Project id: " . $project['Project']['id'] . " closed by fulcrum.";
			}
			
			if (!$close && $survey_quota == 0) {
				$close = true;
				$message = "Project id: " . $project['Project']['id'] . " closed, because survey quota is 0.";
			}

			if (!$close && !empty($allocated_surveys_ids) && !isset($allocated_surveys_ids[$project['FedSurvey']['fed_survey_id']])) {
				$close = true;
				$message = "Project id: " . $project['Project']['id'] . " closed, because survey not found in Fulcrum allocation.";
			}
			
			if ($close) {
				$this->Project->getDatasource()->reconnect();
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'ignore_autoclose' => true,
					'status' => PROJECT_STATUS_CLOSED,
					'active' => false,
					// update ended if it's blank - otherwise leave the old value
					'ended' => empty($project['Project']['ended']) ? date(DB_DATETIME) : $project['Project']['ended']
				)), true, array('status', 'ignore_autoclose', 'active', 'ended'));
				
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'status.closed.fulcrum',
					'internal_description' => $internal_description,
					'description' => 'Closed by Fulcrum'
				)));
				
				echo "Fulcrum Survey id: " . $project['FedSurvey']['fed_survey_id'] . "\n";
				echo "Survey Quota: " . $survey_quota . "\n";
				echo "SurveyStillLive : " . (((string) $survey_quotas['SurveyStillLive'] == '1') ? 'Yes' : 'No') . "\n";
				echo $message . "\n\n";
				CakeLog::write('fulcrum.auto', $message);
			}
			else {
				echo "Project id: ".$project['Project']['id']. " is OK". "\n";
			}
		}
	}
	
	function reopen_irs() {
		
		$lucid_settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array(
					'lucid.maintenance'
				),
				'Setting.deleted' => false
			),
			'fields' => array(
				'Setting.name',
				'Setting.value'
			)
		));
		
		if (count($lucid_settings) < 1) {
			return false;
		}
		
		if ($lucid_settings['lucid.maintenance'] == 'true') {		
			return false;
		}
		
		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'fulcrum'
			)
		));
		$settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => 'fulcrum.ir_cutoff',
				'Setting.deleted' => false
			),
			'fields' => array('Setting.name', 'Setting.value')
		));
		$this->Project->bindModel(array(
			'hasOne' => array(
				'FedSurvey' => array(
					'className' => 'FedSurvey',
					'foreignKey' => 'survey_id'
				)
			)
		));
		$this->Project->unbindModel(array(
			'hasMany' => array(
				'SurveyPartner',
				'ProjectOption'
			),
			'belongsTo' => array(
				'ProjectRate',
				'Client',
			)
		));
		// look at projects closed in the last 24 hours with low IRs
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.bid_ir <' => $settings['fulcrum.ir_cutoff'],
				'Project.status' => PROJECT_STATUS_CLOSED,
				'Project.ignore_autoclose' => false,
				'Project.group_id' => $group['Group']['id'],
				'Project.ended >' => date(DB_DATETIME, strtotime('-24 hours')),
				'Project.loi is null'
			)
		));
		echo 'Found '.count($projects)."\n";
			
		$HttpSocket = new HttpSocket(array(
			'timeout' => 5,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$request_data = array('key' => $this->api_key);
		
		foreach ($projects as $project) {
			if (strtotime('-1 hour') <= strtotime($project['FedSurvey']['modified'])) {
				continue;
			}
			if (!empty($project['SurveyVisitCache']['complete'])) {
				continue;
			}
			$url = $this->api_host . 'Supply/v1/' . 'SurveyStatistics/BySurveyNumber/' . $project['FedSurvey']['fed_survey_id'] . '/'. $this->supplier_code. '/Global/Trailing';
			$response = $this->Fed->get_response($HttpSocket, $url, $request_data);
			$response = json_decode($response['body'], true);
			if ($response['SurveyStatistics']['SystemConversion'] > round($settings['fulcrum.ir_cutoff'] / 100, 2)) {
				
				$quota_response = $this->Fed->get_response($HttpSocket, $this->api_host . 'Supply/v1/SurveyQuotas/BySurveyNumber/' . $project['FedSurvey']['fed_survey_id'] . '/' . $this->supplier_code, $request_data);
				$survey_quotas = json_decode($quota_response['body'], true);
				$survey_quota = 0;
				foreach ($survey_quotas['SurveyQuotas'] as $quota) {
					if ($quota['SurveyQuotaType'] == 'Total') {
						$survey_quota = $quota['NumberOfRespondents'];
					}
				}
				
				if ($survey_quotas['SurveyStillLive'] != 1) {
					continue;
				}
				if (empty($survey_quota)) {
					continue;
				}
				
				$count = $this->SurveyUser->find('count', array(
					'conditions' => array(
						'SurveyUser.survey_id' => $project['Project']['id']
					),
					'recursive' => -1
				));
				
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'status' => $count > 0 ? PROJECT_STATUS_OPEN: PROJECT_STATUS_STAGING,
					'bid_ir' => $response['SurveyStatistics']['SystemConversion'] * 100,
					'active' => true,
					'ended' => null,
				)), true, array('status', 'bid_ir', 'active', 'ended'));
				
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'status.reopened',
					'description' => 'Reopened due to high IR reported from Fulcrum ('.($response['SurveyStatistics']['SystemConversion'] * 100).')'
				)));
				
				echo $project['Project']['id']."\n";
			}
		}
	}
	
	function reopen() {
		
		$lucid_settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array(
					'lucid.maintenance'
				),
				'Setting.deleted' => false
			),
			'fields' => array(
				'Setting.name',
				'Setting.value'
			)
		));
		
		if (count($lucid_settings) < 1) {
			return false;
		}
		
		if ($lucid_settings['lucid.maintenance'] == 'true') {		
			return false;
		}
		
		$time_start = microtime(true);
		$logging_key = strtoupper(Utils::rand('4'));
		
		$this->lecho('Starting', 'fulcrum.reopen', $logging_key);
		$HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$request_data = array('key' => $this->api_key);
		$response = $this->Fed->get_response($HttpSocket, $this->api_host . 'Supply/v1/' . 'Surveys/SupplierAllocations/All', $request_data);
		if (!$response) {
			echo "Api call failed to get survey allocations!" . "\n";
			return;
		}
		
		$allocated_surveys = json_decode($response['body'], true);		
		$allocated_survey_ids = array();
		foreach ($allocated_surveys['SupplierAllocationSurveys'] as $survey) {
			$allocated_survey_ids[] = $survey['SurveyNumber'];
		}
		
		$settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array('api.mintvine.username', 'api.mintvine.password', 'hostname.api', 'fulcrum.ir_cutoff'),
				'Setting.deleted' => false
			),
			'fields' => array('name', 'value')
		));
		
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$http->configAuth('Basic', $settings['api.mintvine.username'], $settings['api.mintvine.password']);
		
		$this->FedSurvey->bindModel(array(
			'belongsTo' => array(
				'Project' => array(
					'className' => 'Project',
					'foreignKey' => 'survey_id'
				)
			)
		));

		$fed_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'fulcrum'
			)
		));
		
		$fed_surveys = $this->FedSurvey->find('all', array(
			'fields' => array('FedSurvey.fed_survey_id', 'FedSurvey.survey_id', 'Project.status', 'Project.id'),
			'conditions' => array(
				'Project.group_id' => $fed_group['Group']['id'],
				'Project.status' => PROJECT_STATUS_CLOSED,
				'FedSurvey.fed_survey_id' => $allocated_survey_ids,
				
			)
		));
		
		$this->lecho('Found '.count($fed_surveys).' closed projects that are in active Fulcrum allocation.', 'fulcrum.reopen', $logging_key);
		
		$i = 0;
		foreach ($fed_surveys as $fed_survey) {
			$response = $this->Fed->get_response($HttpSocket, $this->api_host . 'Supply/v1/' . 'SurveyQuotas/BySurveyNumber/' . $fed_survey['FedSurvey']['fed_survey_id'] . '/' . $this->supplier_code, $request_data);
			if (!$response) {
				$this->lecho('Fulcrum API failure for #F'.$fed_survey['FedSurvey']['fed_survey_id'], 'fulcrum.reopen', $logging_key);
				continue;
			}
			
			$survey_quotas = json_decode($response['body'], true);			
			$survey_quota = 0;
			$rate = 0;
			foreach ($survey_quotas['SurveyQuotas'] as $quota) {
				if ($quota['SurveyQuotaType'] == 'Total') {
					$survey_quota = $quota['NumberOfRespondents'];
				}
				if ($quota['QuotaCPI'] > $rate) {
					$rate = $quota['QuotaCPI'];
				}
			}
			if ($rate == 0) {
				continue;
			}
			if ($survey_quotas['SurveyStillLive'] != 1 || $survey_quota == 0) {
				continue;
			}
			$ended = $this->Project->find('first', array(
				'conditions' => array(
					'Project.id' => $fed_survey['FedSurvey']['survey_id']
				),
				'fields' => array('ended')
			));
			if (strtotime($ended['Project']['ended']) + 86400 * 7 < time()) {
				continue;
			}
			$project_log = $this->ProjectLog->find('first', array(
				'conditions' => array(
					'ProjectLog.project_id' => $fed_survey['Project']['id'],
					'ProjectLog.type LIKE' => 'status.closed%'
				),
				'order' => 'ProjectLog.id DESC'
			));
			if (!$project_log) {
				continue;
			}
			if ($project_log) {
				if (!in_array($project_log['ProjectLog']['type'], array('status.closed.auto', 'status.closed.fulcrum'))) {
					continue;
				}
				// we write an internal description on these fulcrum closes if other non-Fulcrum related rules trigger (long LOI)
				if ($project_log['ProjectLog']['type'] == 'status.closed.fulcrum' && !is_null($project_log['ProjectLog']['internal_description'])) {
					continue;
				}
				if ($project_log['ProjectLog']['type'] == 'status.closed.auto' && $project_log['ProjectLog']['internal_description'] != 'ir.client.low') {
					continue;
				}
			}
			
			// update internal data before re-opening so the closing rules execute correctly
			$this->params['survey_id'] = $fed_survey['FedSurvey']['fed_survey_id'];
			$this->update(); 
			
			// for projects that were closed due to low IR, see if the IR got higher
			if ($project_log['ProjectLog']['type'] == 'status.closed.auto' && $project_log['ProjectLog']['internal_description'] == 'ir.client.low') {
				$refreshed_project = $this->Project->find('first', array(
					'conditions' => array(
						'Project.id' => $fed_survey['Project']['id']
					),
					'recursive' => -1,
					'fields' => array('bid_ir')
				));
				// 
				if ($refreshed_project['Project']['bid_ir'] < $settings['fulcrum.ir_cutoff']) {
					$this->lecho('Skipped reopening #'.$fed_survey['Project']['id'].' because IR is still too low ('.$refreshed_project['Project']['bid_ir'].'%)', 'fulcrum.reopen', $logging_key);
					continue;
				}
			}

			$count = $this->SurveyUser->find('count', array(
				'conditions' => array(
					'SurveyUser.survey_id' => $fed_survey['Project']['id']
				),
				'recursive' => -1
			));
			
			// reopen project
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $fed_survey['Project']['id'],
				'ignore_autoclose' => false,
				'status' => $count == 0 ? PROJECT_STATUS_STAGING : PROJECT_STATUS_OPEN,
				'ended' => null,
				'active' => true,
			)), true, array('status', 'ignore_autoclose', 'active', 'ended'));
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $fed_survey['Project']['id'],
				'type' => 'status.opened.fulcrum',
				'description' => 'Reopened by Fulcrum'
			)));
			
			$results = $http->post($settings['hostname.api'].'/surveys/test_survey_status/'.$fed_survey['Project']['id']);
			$response = json_decode($results['body'], true); 
			
			if (!$response['close_project']) {
				$i++;
				$this->lecho('Reopened #'.$fed_survey['Project']['id'], 'fulcrum.reopen', $logging_key);
			}
			else {
				$this->lecho('Reopened #'.$fed_survey['Project']['id'].' but then closed due to internal rules', 'fulcrum.reopen', $logging_key);
			}
		}
		$this->lecho('Completed re-opening '.$i.' projects (Execution time: '.(microtime(true) - $time_start).')', 'fulcrum.reopen', $logging_key);
	}
	
	// we store the actions in the DB
	/*
 	update fed_questions set queryable = 'age' where question_id = 42;
	update fed_questions set queryable = 'gender' where question_id = 43;
	update fed_questions set queryable = 'postal_code' where question_id = 45;
	update fed_questions set queryable = 'hhi' where question_id = 14785 and language_id = 9;
	update fed_questions set queryable = 'dma' where question_id = 97 and language_id = 9;
	update fed_questions set queryable = 'ethnicity' where question_id = 113 and language_id = 9;
	update fed_questions set queryable = 'hispanic' where question_id = 47;
	update fed_questions set queryable = 'employment' where question_id = 2189;
	update fed_questions set queryable = 'industry' where question_id = 5729; 
	update fed_questions set queryable = 'industry' where question_id = 643; //both the industry questions have same answers set
	update fed_questions set queryable = 'state' where question_id = 96;
	update fed_questions set `queryable` = 'organization_size' where `question_id` = 644;
	update fed_questions set `queryable` = 'organization_size' where `question_id` = 22467;
	update fed_questions set queryable = 'job' where question_id = 15297;
	update fed_questions set `queryable` = 'organization_revenue' where `question_id` = 645;
	update fed_questions set `queryable` = 'children' where `question_id` = 7064;
	update fed_questions set `queryable` = 'department' where `question_id` = 646;
	update fed_questions set `queryable` = 'education' where `question_id` = 633;
	*/
	function qualifications() {
		$lucid_settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array(
					'lucid.maintenance'
				),
				'Setting.deleted' => false
			),
			'fields' => array(
				'Setting.name',
				'Setting.value'
			)
		));
		
		if (count($lucid_settings) < 1) {
			return false;
		}
		
		if ($lucid_settings['lucid.maintenance'] == 'true') {		
			return false;
		}
		
		ini_set('memory_limit', '1024M');
		$is_single_import = isset($this->params['survey_id']) && !empty($this->params['survey_id']); 
		
		// start logging
		$logging_key = strtoupper(Utils::rand('4'));
		$this->lecho('Starting', 'fulcrum.qualifications', $logging_key); 
		if ($is_single_import) {
			$this->lecho('Running qualifications for a single project: #F'.$this->params['survey_id'], 'fulcrum.import', $logging_key);
		}
		
		$fed_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'fulcrum'
			)
		));
		
		if ($is_single_import) {
			$fed_surveys = $this->FedSurvey->find('list', array(
				'fields' => array('id', 'survey_id'),
				'conditions' => array(
					'FedSurvey.fed_survey_id' => $this->params['survey_id']
				)
			));
		}
		else {
			$this->FedSurvey->bindModel(array(
				'belongsTo' => array(
					'Project' => array(
						'className' => 'Project',
						'foreignKey' => 'survey_id'
					)
				)
			));
			$fed_surveys = $this->FedSurvey->find('list', array(
				'fields' => array('id', 'survey_id'),
				'contain' => array('Project'),
				'conditions' => array(
					'Project.status' => array(PROJECT_STATUS_OPEN, PROJECT_STATUS_STAGING)
				)
			));
		}
		if (empty($fed_surveys)) {
			$this->lecho('Finished', 'fulcrum.qualifications', $logging_key); 
			return;
		}
		$this->Project->bindModel(array(
			'hasOne' => array(
				'FedSurvey' => array(
					'className' => 'FedSurvey',
					'foreignKey' => 'survey_id'
				)
			)
		));
		$projects = $this->Project->find('all', array(
			'contain' => array('FedSurvey'),
			'conditions' => array(
				'Project.group_id' => $fed_group['Group']['id'],
				'Project.id' => array_values($fed_surveys),
			)
		));
		if (!$projects) {
			$this->lecho('Finished', 'fulcrum.qualifications', $logging_key); 
			return;
		}
		
		$HttpSocket = new HttpSocket(array(
			'timeout' => 5,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$request_data = array('key' => $this->api_key);
		foreach ($projects as $project) {
			$this->lecho('Starting', 'fulcrum.qualifications', $logging_key.'-'.$project['Project']['id']);
			$response = $this->Fed->get_response($HttpSocket, $this->api_host . 'Supply/v1/SurveyQualifications/BySurveyNumberForOfferwall/' . $project['FedSurvey']['fed_survey_id'], $request_data);
			if (!$response) {
				$this->lecho('Supply/v1/SurveyQualifications/BySurveyNumberForOfferwall (FAILED)', 'fulcrum.qualifications', $logging_key.'-'.$project['Project']['id']); 
				continue;
			}

			$survey_qualifications = json_decode($response['body'], true);
			$this->lecho('Supply/v1/SurveyQualifications/BySurveyNumberForOfferwall', 'fulcrum.qualifications', $logging_key.'-'.$project['Project']['id']); 
			$this->lecho($survey_qualifications, 'fulcrum.qualifications', $logging_key); 
				
			$response = $this->Fed->get_response($HttpSocket, $this->api_host . 'Supply/v1/Surveys/SupplierAllocations/BySurveyNumber/' . $project['FedSurvey']['fed_survey_id'], $request_data);
			if (!$response) {
				$this->lecho('Supply/v1/Surveys/SupplierAllocations/BySurveyNumber (FAILED)', 'fulcrum.qualifications', $logging_key.'-'.$project['Project']['id']); 
				continue;
			}
			$survey_allocation = json_decode($response['body'], true);
			$this->lecho('Supply/v1/Surveys/SupplierAllocations/BySurveyNumber', 'fulcrum.qualifications', $logging_key.'-'.$project['Project']['id']); 
			$this->lecho($survey_allocation, 'fulcrum.qualifications', $logging_key); 
			
			if ($survey_qualifications['ResultCount'] < 1) {
				$this->lecho('No qualifications found', 'fulcrum.qualifications', $logging_key.'-'.$project['Project']['id']); 
				continue;
			}
			if ($survey_allocation['ResultCount'] == 0) {
				// sometimes it seems that fulcrum doesn't have the qualifications ready yet for the supplierallocation
				// let this pass; another run will pick it up
				continue;
			}
			$has_prescreener = false;
			$parent_query_params = array(); // to be sent to query engine
			$parent_query_match = true;
			$tablet = true;
			$mobile = true;
			$desktop = true;
			$parent_query_params['country'] = FedMappings::country($survey_allocation['SupplierAllocationSurvey']['CountryLanguageID']);
			$questions = array();
			$killed = false; // used when we detect qualification types we will not support, like panel recruit
			$fed_questions_count = count($survey_qualifications['SurveyQualification']['Questions']);
			foreach ($survey_qualifications['SurveyQualification']['Questions'] as $question) {
				// https://basecamp.com/2045906/projects/1413421/todos/200199322
				// any project that is a panel recruit, automatically close it and turn off automation checks
				if ($question['QuestionID'] == '2204') {
					$killed = true;
					break;
				}
				
				// we only support OR type prescreeners.
				if ($question['LogicalOperator'] != 'Or') {
					$parent_query_match = false;
					continue;
				}
				
				$fed_question = $this->Fed->get_fed_question($question['QuestionID'], $survey_allocation['SupplierAllocationSurvey']['CountryLanguageID']);
				if (!$fed_question) {
					$parent_query_match = false;
					continue;
				}
				
				// Handle mobile flag
				if ($question['QuestionID'] == 8214) { 
					if ($question['PreCodes'][0] == 'true') {
						$desktop = false;
						$tablet = false;
					}
					else {
						$mobile = false;
					}
				}
				
				// Handle tabets flag
				if ($question['QuestionID'] == 8213) {
					if ($question['PreCodes'][0] == 'true') {
						$tablet = true; // explicitly declare as the above mobile question may have made it false
						$desktop = false;
					}
					else {
						$tablet = false;
					}
				}
				
				// handle the prescreeners
				if (empty($fed_question['FedQuestion']['queryable']) && in_array($fed_question['FedQuestion']['type'],  array('Single Punch', 'Multi Punch')) && !empty($fed_question['FedAnswer'])) {
					$parent_query_match = false;
					$answers = array();
					$has_dqs = false;
					foreach ($fed_question['FedAnswer'] as $answer) {
						if (array_search($answer['precode'], $question['PreCodes']) !== FALSE) {
							$answers[] = $answer['answer'];
						}
						else {
							$answers[] = '[x] ' . $answer['answer'];
							$has_dqs = true;
						}
					}
				
					if (empty($answers)) {
						continue;
					}
				
					// only show prescreeners for items that DQ users
					if ($has_dqs) {
						$questions[] = $fed_question['FedQuestion']['question'];
						$prescreener = $this->Prescreener->find('first', array(
							'conditions' => array(
								'survey_id' => $project['Project']['id'],
								'question' => $fed_question['FedQuestion']['question']
							)
						));
						$this->Prescreener->create();
						if ($prescreener) {
							$this->Prescreener->id = $prescreener['Prescreener']['id'];
						}
						else {							
							$this->ProjectLog->create();
							$this->ProjectLog->save(array('ProjectLog' => array(
								'project_id' => $project['Project']['id'],
								'type' => 'prescreener.created',
								'description' => $fed_question['FedQuestion']['question']
							)));
						}
						
						$this->Prescreener->save(array('Prescreener' => array(
							'survey_id' => $project['Project']['id'],
							'question' => $fed_question['FedQuestion']['question'],
							'answers' => implode("\n", $answers)
						)));
						$has_prescreener = true;
						
					}
					continue;
				}
				
				// $parent_query_params - Call by reference
				$this->Fed->get_query_params($parent_query_params, $fed_question, $question);
			}
			
			if ($killed) {
				$this->Project->getDatasource()->reconnect();
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'ignore_autoclose' => true,
					'status' => PROJECT_STATUS_CLOSED,
					'active' => false,
					// update ended if it's blank - otherwise leave the old value
					'ended' => empty($project['Project']['ended']) ? date(DB_DATETIME) : $project['Project']['ended']
				)), true, array('status', 'ignore_autoclose', 'status', 'active', 'ended'));
				
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'status.closed.qualifications',
					'description' => 'Closed due to bad qualifications: panel recruit'
				)));
				continue;
			}
			// Update mobile, tablet & desktop flags
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'mobile' => $mobile,
				'tablet' => $tablet,
				'desktop' => $desktop,
			)), true, array('mobile', 'tablet', 'desktop'));

			// Process parent query
			if (!empty($parent_query_params)) {
				
				// determine if this is a complete match
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'qualifications_match' => $parent_query_match,
					'modified' => false
				)), true, array('qualifications_match', 'modified'));
				
				if ($project['Project']['qualifications_match'] != $parent_query_match) {
					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $project['Project']['id'],
						'type' => 'query',
						'description' => 'Project #'.$project['Project']['id'].' qualifications match: '.($parent_query_match ? 'true': 'false')
					)));
				}
			
				$parent_query_id = $this->Fed->check_query($project, $parent_query_params); 
			}
			
			// Create filter queries from Survey Quota
			$response = $this->Fed->get_response($HttpSocket, $this->api_host . 'Supply/v1/' . 'SurveyQuotas/BySurveyNumber/' . $project['FedSurvey']['fed_survey_id'] . '/' . $this->supplier_code, $request_data);
			if (!$response) {
				continue;
			}
			
			$survey_quotas = json_decode($response['body'], true);
			foreach ($survey_quotas['SurveyQuotas'] as $quota) {
				// Skip Overall quota
				if ($quota['SurveyQuotaType'] == 'Total') {
					continue;
				}
				
				$query_params = array(); // to be sent to query engine
				$query_match = true;
				$query_params['country'] = FedMappings::country($survey_allocation['SupplierAllocationSurvey']['CountryLanguageID']);
				foreach ($quota['Questions'] as $question) {
					if ($question['LogicalOperator'] != 'OR') {
						$query_match = false;
						continue;
					}
					
					$fed_question = $this->Fed->get_fed_question($question['QuestionID'], $survey_allocation['SupplierAllocationSurvey']['CountryLanguageID']);
					if (!$fed_question) {
						echo 'Error retrieving question record. Question id: ' . $question['QuestionID'] . "\n";
						$query_match = false;
						continue;
					}

					if (empty($fed_question['FedQuestion']['queryable'])) {
						$query_match = false;
						continue;
					}

					// $query_params - Call by reference
					$this->Fed->get_query_params($query_params, $fed_question, $question);
				}
				
				if (!empty($query_params)) {
					
					// Add master params to filter query if not present
					foreach ($parent_query_params as $key => $value) {
						if (!isset($query_params[$key])) {
							$query_params[$key] = $value;
						}
					}
					
					$query_id = $this->Fed->check_query($project, $query_params, $parent_query_id, $quota);
					// Determine if this query is a complete match
					$this->Query->create();
					$this->Query->save(array('Query' => array(
						'id' => $query_id,
						'qualifications_match' => (!$parent_query_match) ? false : $query_match,
						'modified' => false
					)), true, array('qualifications_match', 'modified'));
				}
			}

			// once completed, set the fed status
			if (!empty($parent_query_params) || !empty($query_params)) {
				$this->FedSurvey->create();
				$this->FedSurvey->save(array('FedSurvey' => array(
					'id' => $project['FedSurvey']['id'],
					'status' => FEDSURVEY_QUALIFICATIONS_LOADED
				)), true, array('status'));
				
				$fed_status = FEDSURVEY_QUALIFICATIONS_LOADED;
				
				if ($project['FedSurvey']['status'] == FEDSURVEY_CREATED) {
					$this->Project->create();
					$this->Project->save(array('Project' => array(
						'id' => $project['Project']['id'],
						'active' => true
					)), true, array('active'));
				}
			}
			else {
				$this->FedSurvey->create();
				$this->FedSurvey->save(array('FedSurvey' => array(
					'id' => $project['FedSurvey']['id'],
					'status' => FEDSURVEY_QUALIFICATIONS_EMPTY
				)), true, array('status'));
				$fed_status = FEDSURVEY_QUALIFICATIONS_EMPTY;
			}
			
			$this->lecho('[SUCCESS] Qualifications for #'.$project['Project']['id'].': '.$fed_status, 'fulcrum.qualifications', $logging_key.'-'.$project['Project']['id']); 
					
			if ($has_prescreener) {
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'prescreen' => true
				)), true, array('prescreen'));
			}
			
			// Save Questions in prj_description
			if ($questions) {
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'prj_description' => implode("\n", $questions)
				)), true, array('prj_description'));
			}
		}
	}
	
	// import all questions from fed; the script above will also grab on demand if it doesn't exit
	// countrylanguage is required; 6 - canada; 8 - UK; 9- US
	public function questions() {
		$lucid_settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array(
					'lucid.maintenance'
				),
				'Setting.deleted' => false
			),
			'fields' => array(
				'Setting.name',
				'Setting.value'
			)
		));
		
		if ($lucid_settings['lucid.maintenance'] == 'true') {		
			return false;
		}
		
		if (!isset($this->args[0])) {
			return;
		}
		
		$HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$request_data = array('key' => $this->api_key);
		$response = $HttpSocket->get($this->api_host . 'Lookup/v1/' . 'QuestionLibrary/AllQuestions/' . $this->args[0], $request_data);
		$questions = json_decode($response['body'], true);
		
		if (isset($questions['Questions']) && !empty($questions['Questions'])) {
			foreach ($questions['Questions'] as $question) {
				$fed_question = $this->FedQuestion->find('first', array(
					'conditions' => array(
						'FedQuestion.question_id' => $question['QuestionID'],
						'FedQuestion.language_id' => $this->args[0]
					)
				));
				if ($fed_question) {
					$this->FedQuestion->create();
					$this->FedQuestion->save(array('FedQuestion' => array(
						'id' => $fed_question['FedQuestion']['id'],
						'question' => $question['QuestionText'],
						'type' => $question['QuestionType']
					)), true, array('question', 'type'));
				}
				else {
					$fedQuestionSource = $this->FedQuestion->getDataSource();
					$fedQuestionSource->begin();
					$this->FedQuestion->create();
					$this->FedQuestion->save(array('FedQuestion' => array(
						'question' => $question['QuestionText'],
						'type' => $question['QuestionType'],
						'question_id' => $question['QuestionID'],
						'language_id' => $this->args[0]
					)));
					$fed_question = $this->FedQuestion->findById($this->FedQuestion->getInsertId());
					$fedQuestionSource->commit();
				}
				
				if (in_array($fed_question['FedQuestion']['type'], array('Single Punch', 'Multi Punch', 'Dummy'))) {
					// Check if we have the answers in db
					$fed_answers = $this->FedAnswer->find('all', array(
						'conditions' => array(
							'FedAnswer.language_id' => $this->args[0],
							'FedAnswer.question_id' => $question['QuestionID']
						)
					));
					if (!$fed_answers) {
						$response = $HttpSocket->get($this->api_host . 'Lookup/v1/' . 'QuestionLibrary/AllQuestionOptions/' . $this->args[0] . '/' . $question['QuestionID'], $request_data);
						$api_question_options = json_decode($response['body'], true);
						
						foreach ($api_question_options['QuestionOptions'] as $option) {
							if (empty($option['Precode'])) {
								continue;
							}
							$this->FedAnswer->create();
							$this->FedAnswer->save(array('FedAnswer' => array(
								'question_id' => $fed_question['FedQuestion']['question_id'],
								'language_id' => $this->args[0],
								'precode' => $option['Precode'],
								'answer' => $option['OptionText']
							)));
						}
					}
				}
			}
		}
	}
	
	// Export qualifications analytics
	function export_qualifications() {
		ini_set('memory_limit', '1024M');
		$fed_questions = $this->FedQuestion->find('all', array(
			'fields' => array('FedQuestion.id', 'FedQuestion.question_id', 'FedQuestion.question', 'FedQuestion.queryable'),
			'conditions' => array(
				'FedQuestion.language_id' => 9
			)
		));
		
		$this->FedSurvey->bindModel(array(
			'belongsTo' => array(
				'Project' => array(
					'className' => 'Project',
					'foreignKey' => 'survey_id'
				)
			)
		));
		
		$this->FedSurvey->Project->bindModel(array(
			'hasMany' => array(
				'Prescreener' => array(
					'className' => 'Prescreener',
					'foreignKey' => 'survey_id'
				),
				'Query' => array(
					'className' => 'Query',
					'foreignKey' => 'survey_id'
				),
			)
		));
		
		$fed_surveys = $this->FedSurvey->find('all', array(
			'contain' => array(
				'Project' => array(
					'Prescreener' => array('fields' => 'question'),
					'Query' => array('fields' => 'query_string'),
				)
			)
		));
		$count = count($fed_surveys);
		if (!$fed_surveys) {
			echo 'Fulcrum Surveys not found!';
			return;
		}
		
		$output = fopen(WWW_ROOT . 'files/fed_qualifications.csv', 'w');
		fputcsv($output, array(
			'Question Id', 
			'Question', 
			'Occurance (total of '.$count.' projects)', 
			'Percentage (total of ' . $count . ' projects)',
			'Status'
		));
		foreach ($fed_surveys as $survey) {
			if ($survey['Project']['Prescreener']) {
				foreach ($survey['Project']['Prescreener'] as $prescreener) {
					if ($prescreener['question'] && $key = $this->Fed->searchQuestion($prescreener['question'], $fed_questions)) {
						(isset($fed_questions[$key]['FedQuestion']['inc'])) ? 
							$fed_questions[$key]['FedQuestion']['inc']++ : 
							$fed_questions[$key]['FedQuestion']['inc'] = 1;
					}
				}
			}
			
			if ($survey['Project']['Query']) {
				$params = array();
				foreach ($survey['Project']['Query'] as $query) {
					if (empty($query['query_string'])) {
						continue;
					}
					$query_strings = json_decode($query['query_string'], true); 
					if (!is_array($query_strings)) {
						continue;
					}
					$keys = array_keys($query_strings);
					$index = array_search('birthdate <=', $keys);
					if ($index) {
						$keys[$index] = 'age';
					}
					
					$index = array_search('birthdate >', $keys);
					if ($index) {
						$keys[$index] = 'age';
					}
					
					$index = array_search('dma_code', $keys);
					if ($index) {
						$keys[$index] = 'dma';
					}
					
					$params = array_merge($params, $keys);
				}
				
				$params = array_unique($params);
				if ($params) {
					foreach ($params as $param) {
						$key = $this->Fed->searchQueryable($param, $fed_questions);
						if ($key !== false) {
							(isset($fed_questions[$key]['FedQuestion']['inc'])) ?
								$fed_questions[$key]['FedQuestion']['inc']++ :
								$fed_questions[$key]['FedQuestion']['inc'] = 1;
						}
					}
				}
			}
		}
		
		foreach ($fed_questions as $fed_question) {
			fputcsv($output, array(
				$fed_question['FedQuestion']['question_id'],
				$fed_question['FedQuestion']['question'],
				(isset($fed_question['FedQuestion']['inc'])) ? $fed_question['FedQuestion']['inc']: 0,
				(isset($fed_question['FedQuestion']['inc'])) ? round(($fed_question['FedQuestion']['inc'] * 100)/$count, 2). '%' : '0%',
				($fed_question['FedQuestion']['queryable']) ? 'matched' : ''
			));
		}
		
		fclose($output);
		echo "/files/fed_qualifications.csv created" . "\n";
	}

	public function dump() {
		$HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$request_data = array('key' => $this->api_key);
		// Global Trailing statistics
		//$response = $this->Fed->get_response($HttpSocket, $this->api_host . 'Supply/v1/SurveyStatistics/All/' . $this->supplier_code . '/Global/Trailing', $request_data);
		
		// Supplier Lifetime statistics
		//$response = $this->Fed->get_response($HttpSocket, $this->api_host . 'Supply/v1/SurveyStatistics/All/' . $this->supplier_code . '/Supplier/Lifetime', $request_data);
		
		// Supplier Trailing statistics
		$response = $this->Fed->get_response($HttpSocket, $this->api_host . 'Supply/v1/SurveyStatistics/All/' . $this->supplier_code . '/Supplier/Trailing', $request_data);
		if (!$response) {
			return;
		}
		
		$response = json_decode($response['body'], true);
		CakeLog::write('fed-supplier-trailing-statistics', print_r($response, true));
		echo 'Live statistics Log created.';
	}
}
