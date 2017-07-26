<?php
App::import('Lib', 'Utilities');
App::import('Lib', 'QueryEngine');
App::import('Lib', 'SpectrumMappings');
App::import('Lib', 'MintVine');
App::import('Lib', 'Surveys');
App::import('Lib', 'Reporting');
App::uses('HttpSocket', 'Network/Http');

class SpectrumShell extends AppShell {
 	public $uses = array('ProjectLog', 'SurveyVisitCache', 'Qualification', 'QualificationUser', 'QualificationStatistic', 'Project', 'SurveyUserVisit', 'Client', 'SurveyVisit', 'Group', 'Partner', 'SpectrumQueue', 'User', 'SurveyUser', 'QueryProfile', 'SurveyVisit', 'SpectrumProject', 'Query', 'QueryStatistic', 'Setting', 'ProjectOption', 'SurveyUserQuery', 'SpectrumSurveyGroup');
	public $tasks = array('Spectrum');
	public $failed_rule = "";
	
	public function getOptionParser() {
		$parser = parent::getOptionParser();
		
		$parser->addOption('spectrum_survey_id', array(
			'help' => 'Spectrum Survey ID',
			'boolean' => false
		));
		
		$parser->addOption('project_id', array(
			'help' => 'MintVine Project ID',
			'boolean' => false
		));
		$parser->addOption('force', array(
			'help' => 'Force a change',
			'boolean' => true
		));
		return $parser;
	}
	
	// actually executes things
	public function worker() {
		if (!$this->isActive()) {
			return;
		}
		
		$time_to_run = 12;
		ini_set('memory_limit', '1024M');
		$log_file = 'spectrum.worker';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting worker', $log_file, $log_key);
		
		// load settings, client, group, etc.
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			return false;
		}
		
		App::import('Vendor', 'sqs');
		$sqs = new SQS($this->settings['sqs.access.key'], $this->settings['sqs.access.secret']);
		$i = 0;
		while (true) {
			$results = $sqs->receiveMessage($this->settings['sqs.spectrum.queue']);
			if (!empty($results['Messages'])) {
				$command = $results['Messages'][0]['Body'];
				$this->lecho('Starting '.$command, $log_file, $log_key);
				$query = ROOT . '/app/Console/cake '.$command;
				$this->lecho($query, 'query_commands');
				// run these synchronously
				exec($query, $output);
				$i++;
				
				$this->SpectrumQueue->getDataSource()->reconnect();	
				$spectrum_queue = $this->SpectrumQueue->find('first', array(
					'conditions' => array(
						'SpectrumQueue.command' => $command,
						'SpectrumQueue.executed is null'
					)
				));
				if ($spectrum_queue) {
					$this->SpectrumQueue->create();
					$this->SpectrumQueue->save(array('SpectrumQueue' => array(
						'id' => $spectrum_queue['SpectrumQueue']['id'],
						'executed' => date(DB_DATETIME)
					)), true, array('executed'));
					$spectrum_queue_id = $spectrum_queue['SpectrumQueue']['id']; 
				}
				else {
					// gotta parse out the invite
					$spectrum_survey_id = null;
					if (strpos($command, 'spectrum create --spectrum_survey_id=') !== false) {
						$spectrum_survey_id = str_replace('spectrum create --spectrum_survey_id=', '', $command); 
					}
					elseif (strpos($command, 'spectrum update --spectrum_survey_id=') !== false) {
						$spectrum_survey_id = str_replace('spectrum update --spectrum_survey_id=', '', $command); 
					}
					elseif (strpos($command, 'spectrum invite --spectrum_survey_id ') !== false) {
						$spectrum_survey_id = str_replace('spectrum invite --spectrum_survey_id ', '', $command); 
					}
					if (!empty($spectrum_survey_id)) {
						// if the spectrum queue doesn't exist, then write the value
						$spectrumQueueSource = $this->SpectrumQueue->getDataSource();
						$spectrumQueueSource->begin();
						$this->SpectrumQueue->create();
						$save = $this->SpectrumQueue->save(array('SpectrumQueue' => array(
							'amazon_queue_id' => $results['Messages'][0]['MessageId'],
							'spectrum_survey_id' => $spectrum_survey_id,
							'command' => $command,
							'project_id' => null,
							'executed' => date(DB_DATETIME)
						)));
						if ($save) {
							$spectrum_queue_id = $this->SpectrumQueue->getInsertId();
						}
						$spectrumQueueSource->commit();
					}
				}
				$sqs->deleteMessage($this->settings['sqs.spectrum.queue'], $results['Messages'][0]['ReceiptHandle']);
				if (isset($spectrum_queue_id)) {
					$this->lecho('Processed SpectrumQueue.id#'.$spectrum_queue_id, $log_file, $log_key);
				}
				else {
					$this->lecho('Processed', $log_file, $log_key);
				}
				
				// end scripts after 5 minutes - a bit more graceful than time limit in PHP
				$time_diff = microtime(true) - $time_start; 
				if ($time_diff > (60 * $time_to_run)) {
					$this->lecho('Completed worker (timeout) '.$i.' items (Execution time: '.($time_diff).')', $log_file, $log_key);
					return false;
				}
			}
			else {
				$this->lecho('Completed worker '.$i.' items (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
				break;
			}
		}
	}
	
	// precursor to process, but go through the surveys
	public function links() {
		if (!$this->isActive()) {
			return;
		}
		
		ini_set('memory_limit', '1024M');
		$log_file = 'spectrum.links';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting links', $log_file, $log_key);
		
		// load settings, client, group, etc. 
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			return false;
		}
		$supported_countries = array_keys(unserialize(SUPPORTED_COUNTRIES));
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$params = array(
			'supplier_id' => $this->settings['spectrum.supplier_id'],
			'access_token' => $this->settings['spectrum.access_token'],
		);
		
		// get the all surveys
		$url = $this->settings['spectrum.host'].'/suppliers/surveys'; 
		$response = $http->post($url, $params, array(
			'header' => array(
				'Content-Type' => 'application/x-www-form-urlencoded',
				'cache-control' => 'no-cache'
			)
		));
		$response_body = json_decode($response->body, true);
		
		if ($response->code != 200 || !isset($response_body['apiStatus']) && $response_body['apiStatus'] =! 'success') {
			$this->lecho('FAILED: Surveys look up failed: '.$url, $log_file, $log_key);
			$this->lecho($response, $log_file, $log_key);
			$this->lecho('Completed links (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		
		if (!isset($response_body['surveys']) || empty($response_body['surveys'])) {
			$this->lecho('FAILED: No surveys found', $log_file, $log_key); 
			return false;
		}
		
		$spectrum_surveys = $response_body['surveys'];
		$this->lecho('Starting to process '.count($spectrum_surveys).' surveys', $log_file, $log_key);
		
		/* $survey response looks like:
      		{
				"cpi":21.45,
				"survey_id":34,
				"survey_name":"Survey for supplier",
				"countries":[
					{"name":"United States","code":"US"}
				],
				"languages":[
					{"name":"English","code":"eng"}
				],
				"category":"Automotive",
				"category_code":211,
				"survey_api_state":2,
				"mobile_conversion_rate":0,
				"survey_status":"Live",
				"field_start_date":"2016-07-10",
				"field_time_remaining":2,
				"field_end_date":"2016-07-19",
				"account_name":"Mintvine",
				"last_complete_date":"",
				"supplier_completes":{
					"needed":10,
					"achieved":0,
					"remaining":10
				},
				"survey_conversion_rate":0,
				"loi":0
			}
		*/
		foreach ($spectrum_surveys as $spectrum_survey) {
			//Skip if quota already filled
			if ($spectrum_survey['supplier_completes']['remaining'] <= 0) {
				continue;
			}
			if (isset($this->params['spectrum_survey_id']) && $spectrum_survey['survey_id'] != $this->params['spectrum_survey_id']) {
				continue;
			}
			$this->SpectrumProject->getDataSource()->reconnect();
			$spectrum_project = $this->SpectrumProject->find('first', array(
				'conditions' => array(
					'SpectrumProject.spectrum_survey_id' => $spectrum_survey['survey_id']
				),
				'fields' => array('id', 'status'),
				'recursive' => -1,
				'order' => 'SpectrumProject.id DESC'
			));
			
			$this->lecho('[START] #'.$spectrum_survey['survey_id'], $log_file, $log_key);
			
			// if it's already imported, skip it
			if ($spectrum_project && $spectrum_project['SpectrumProject']['status'] != 'failed.link') {
				$this->lecho('[SKIP] #'.$spectrum_survey['survey_id'].': already imported', $log_file, $log_key);
				continue;
			}
			
			$is_supported_country = false;
			if (!empty($spectrum_survey['countries'])) {
				foreach ($spectrum_survey['countries'] as $country) {
					if (in_array($country['code'], $supported_countries)) {
						$is_supported_country = true;
						break;
					}
				}
			}
			
			// Figure out whether we want this survey or not
			if (!$is_supported_country) {
				$this->lecho('[SKIP] #S'.$spectrum_survey['survey_id'].': unsupported countries ('. json_encode($spectrum_survey['countries']) .')', $log_file, $log_key);
				
				// add in a placeholder Spectrum id # so we don't try to re-import this
				// note: beforeSave() will prevent dupes when project_id = 0 so no need to validate the existence of this
				$this->SpectrumProject->create();
				$this->SpectrumProject->save(array('SpectrumProject' => array(
					'project_id' => '0',
					'spectrum_survey_id' => $spectrum_survey['survey_id'],
					'status' => 'skipped.country'
				)));
				continue;
			}
			
			if ($spectrum_project) {
				$this->SpectrumProject->delete($spectrum_project['SpectrumProject']['id']); // delete the failed link response
			}
		}
		$this->lecho('Completed links (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
	}
	
	// goes through and orchestrates the work to be done
	public function process() {
		if (!$this->isActive()) {
			return;
		}
		
		ini_set('memory_limit', '1024M');
		$log_file = 'spectrum.process';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting process', $log_file, $log_key);
		
		// load settings, client, group, etc. 
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			return false;
		}
		
		if (isset($this->params['spectrum_survey_id']) && isset($this->params['project_id'])) {
			$this->lecho('FAILED: You cannot define both a Spectrum and MintVine project ID', $log_file, $log_key);
			$this->lecho('Completed process (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		
		// validate the project_id
		if (isset($this->params['project_id'])) {
			$target_spectrum_survey = $this->SpectrumProject->find('first', array(
				'conditions' => array(
					'SpectrumProject.project_id' => $this->params['project_id']
				)
			));
			if (!$target_spectrum_survey) {
				$this->lecho('FAILED: Failed to find project for Project.id#'.$this->params['project_id'], $log_file, $log_key);
				$this->lecho('Completed process (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
				return false;
			}
		}
		else {
			$target_spectrum_survey = false;
		}
		
		$supported_countries = array_keys(unserialize(SUPPORTED_COUNTRIES));
		
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$params = array(
			'supplier_id' => $this->settings['spectrum.supplier_id'],
			'access_token' => $this->settings['spectrum.access_token'],
		);
		
		// get our total allocation across the board
		$url = $this->settings['spectrum.host'].'/suppliers/surveys'; 
		$response = $http->post($url, $params, array(
			'header' => array(
				'Content-Type' => 'application/x-www-form-urlencoded',
				'cache-control' => 'no-cache'
			)
		));
		$response_body = json_decode($response->body, true);
		
		if ($response->code != 200 || !isset($response_body['apiStatus']) || $response_body['apiStatus'] =! 'success') {
			$this->lecho('FAILED: Surveys look up failed: '.$url, $log_file, $log_key);
			$this->lecho($response, $log_file, $log_key);
			$this->lecho('Completed links (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		elseif (!isset($response_body['surveys']) || empty($response_body['surveys'])) {
			$this->lecho('FAILED: No surveys found', $log_file, $log_key); 
			return false;
		}
		$allocated_surveys = $response_body['surveys'];
		$this->lecho('Processing '.count($allocated_surveys).' projects', $log_file, $log_key);
		
		/*
			Surevey response
			{
				"cpi":21.45,
				"survey_id":34,
				"survey_name":"Survey for supplier",
				"countries":[
					{"name":"United States","code":"US"}
				],
				"languages":[
					{"name":"English","code":"eng"}
				],
				"category":"Automotive",
				"category_code":211,
				"survey_api_state":2,
				"mobile_conversion_rate":0,
				"survey_status":"Live",
				"field_start_date":"2016-07-10",
				"field_time_remaining":2,
				"field_end_date":"2016-07-19",
				"account_name":"Mintvine",
				"last_complete_date":"",
				"supplier_completes":{
					"needed":10,
					"achieved":0,
					"remaining":10
				},
				"survey_conversion_rate":0,
				"loi":0
			}
		*/
		$i = 0;
		$sqs_batch = array();
		$allocated_survey_ids = array();
		foreach ($allocated_surveys as $allocated_survey) {
			$i++;
			$allocated_survey_ids[] = $allocated_survey['survey_id'];
			// operating against a single Spectrum survey
			if (isset($this->params['spectrum_survey_id']) && $allocated_survey['survey_id'] != $this->params['spectrum_survey_id']) {
				continue;
			}
			
			// operating on a single MV project
			if (isset($this->params['project_id']) && $target_spectrum_survey && $target_spectrum_survey['SepctrumProject']['spectrum_survey_id'] != $allocated_survey['survey_id']) {
				continue;
			}
			
			$is_supported_country = false;
			if (!empty($allocated_survey['countries'])) {
				foreach ($allocated_survey['countries'] as $country) {
					if (in_array($country['code'], $supported_countries)) {
						$is_supported_country = true;
						break;
					}
				}
			}
			
			// Figure out whether we want this survey or not
			if (!$is_supported_country) {
				$this->lecho('[SKIP] #S'.$allocated_survey['survey_id'].': unsupported countries ('. json_encode(Hash::extract($allocated_survey['countries'], '{n}.code')) .')', $log_file, $log_key);
				
				// add in a placeholder Spectrum id # so we don't try to re-import this
				$this->SpectrumProject->create();
				$this->SpectrumProject->save(array('SpectrumProject' => array(
					'project_id' => '0',
					'spectrum_survey_id' => $allocated_survey['survey_id'],
					'status' => 'skipped.country'
				)));
				continue;
			}
			
			$this->SpectrumProject->getDatasource()->reconnect();
			$this->Project->bindModel(array(
				'hasOne' => array(
					'SpectrumProject'
				)
			));
			$project = $this->Project->find('first', array(
				'contain' => array('SpectrumProject'),
				'conditions' => array(
					'SpectrumProject.spectrum_survey_id' => $allocated_survey['survey_id']
				)
			));
			
			// see if this project exists; if not, create it!
			if (empty($project)) {
				// Spectrum project shouldn't exist at all for projects that don't exist
				$spectrum_project = $this->SpectrumProject->find('first', array(
					'conditions' => array(
						'SpectrumProject.spectrum_survey_id' => $allocated_survey['survey_id']
					),
					'order' => 'SpectrumProject.id DESC'
				));
				if ($spectrum_project) {
					if ($spectrum_project['SpectrumProject']['status'] == 'failed.link') {
						$this->SpectrumProject->delete($spectrum_project['SpectrumProject']['id']); 
					}
					else {
						$this->lecho($i.': [SKIP] CREATE #S'.$allocated_survey['survey_id'].': exists SpectrumProject.id#'.$spectrum_project['SpectrumProject']['id'], $log_file, $log_key);
						continue;
					}
				}
				$command = 'spectrum create --spectrum_survey_id='.$allocated_survey['survey_id'];
				
				$spectrumQueueSource = $this->SpectrumQueue->getDataSource();
				$spectrumQueueSource->begin();
				$this->SpectrumQueue->create();
				$save = $this->SpectrumQueue->save(array('SpectrumQueue' => array(
					'spectrum_survey_id' => $allocated_survey['survey_id'],
					'command' => $command,
					'project_id' => null
				)));
				if ($save) {
					$spectrum_queue_id = $this->SpectrumQueue->getInsertId();
					$spectrumQueueSource->commit();
					$sqs_batch[$spectrum_queue_id] = $command;
					$this->lecho($i.': Create #S'.$allocated_survey['survey_id'], $log_file, $log_key);	
				}
				else {
					$spectrumQueueSource->commit();
					$this->lecho($i.': Creation of #S'.$allocated_survey['survey_id'].' skipped as it already exists in queue.', $log_file, $log_key);
				}
				continue; // note: loop ends here for new projects
			}
			else {
				// if the project exists, let's update the metadata around it
				// note, because quotas/status are all tied to update, the status checks are also done here
				$command = 'spectrum update --spectrum_survey_id='.$allocated_survey['survey_id'];
				$spectrumQueueSource = $this->SpectrumQueue->getDataSource();
				$spectrumQueueSource->begin();
				$this->SpectrumQueue->create();
				$save = $this->SpectrumQueue->save(array('SpectrumQueue' => array(
					'spectrum_survey_id' => $allocated_survey['survey_id'],
					'command' => $command,
					'project_id' => null
				)));
				if ($save) {
					$spectrum_queue_id = $this->SpectrumQueue->getInsertId();
					$spectrumQueueSource->commit();
					$sqs_batch[$spectrum_queue_id] = $command;
					$this->lecho($i.': Update #S'.$allocated_survey['survey_id'], $log_file, $log_key);
				}
				else {
					$spectrumQueueSource->commit();
					$this->lecho($i.': Update of #S'.$allocated_survey['survey_id'].' skipped as it already exists in queue.', $log_file, $log_key);
				}
			}
		}
		$this->lecho('Found '.count($sqs_batch).' SQS items to send', $log_file, $log_key);
		
		// process all of the amazon queues
		$i = 0;
		App::import('Vendor', 'sqs');
		$sqs = new SQS($this->settings['sqs.access.key'], $this->settings['sqs.access.secret']);
		if (isset($sqs_batch) && !empty($sqs_batch)) {
			$chunks = array_chunk($sqs_batch, 10, true); 
			if (!empty($chunks)) {
				foreach ($chunks as $batch) {
					$response = $sqs->sendMessageBatch($this->settings['sqs.spectrum.queue'], $batch);
					$this->lecho('WRITING----------', 'spectrum.sqs');
					$this->lecho(print_r($batch, true), 'spectrum.sqs');
					$this->lecho(print_r($response, true), 'spectrum.sqs');
					$this->lecho('-----------------', 'spectrum.sqs');
					
					if (!empty($response)) {
						foreach ($response as $spectrum_queue_id => $message_id) {
							$this->SpectrumQueue->create();
							$this->SpectrumQueue->save(array('SpectrumQueue' => array(
								'id' => $spectrum_queue_id,
								'amazon_queue_id' => $message_id
							)), true, array('amazon_queue_id')); 
							$i++;
						}
					}
				}
			}
		}
		$this->lecho('Sent '.$i.' SQS items', $log_file, $log_key);
		
		// close all projects that are on longer found in our allocation
		if (!isset($this->params['spectrum_survey_id']) && !isset($this->params['project_id'])) {
			// find all projects that are open/staging/completed in our spectrum set, but are not in our allocation: these are closed
			$this->Project->bindModel(array(
				'hasOne' => array(
					'SpectrumProject'
				)
			));
			
			$this->Project->getDatasource()->reconnect();
			$all_projects = $this->Project->find('all', array(
				'fields' => array(
					'Project.id', 'SpectrumProject.spectrum_survey_id'
				),
				'conditions' => array(
					'Project.group_id' => $this->spectrum_group['Group']['id'],
					'Project.status' => array(PROJECT_STATUS_OPEN, PROJECT_STATUS_STAGING)
				)
			));
			if ($all_projects) {
				$i = 0;
				foreach ($all_projects as $project) {
					if (!in_array($project['SpectrumProject']['spectrum_survey_id'], $allocated_survey_ids)) {
						$this->Project->create();
						$this->Project->save(array('Project' => array(
							'id' => $project['Project']['id'],
							'status' => PROJECT_STATUS_CLOSED,
							'active' => false,
							'ended' => date(DB_DATETIME)
						)), true, array('status', 'active', 'ended'));
				
						$this->ProjectLog->create();
						$this->ProjectLog->save(array('ProjectLog' => array(
							'project_id' => $project['Project']['id'],
							'type' => 'status.closed.spectrum',
							'description' => 'Closed by Spectrum API - not found in allocation'
						)));
						Utils::save_margin($project['Project']['id']);
						$i++;
						$this->lecho($i.': Closed #S'.$project['SpectrumProject']['spectrum_survey_id'].' - no longer in allocations', $log_file, $log_key);	
					}
				}
			}
		}
		$this->lecho('Completed process (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
	}
	
	// create a project, given a spectrum_survey_id
	public function create() {
		if (!$this->isActive()) {
			return;
		}
		
		$log_file = 'spectrum.create';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		
		if (!isset($this->params['spectrum_survey_id'])) {
			$this->lecho('FAILED: You are missing spectrum_survey_id', $log_file, $log_key);
			$this->lecho('Completed create (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			$this->lecho('Completed create (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			// this method logs the errors already
			return false;
		}
		$this->lecho('Starting create #S'.$this->params['spectrum_survey_id'], $log_file, $log_key);
		
		$spectrum_project = $this->SpectrumProject->find('first', array(
			'conditions' => array(
				'SpectrumProject.spectrum_survey_id' => $this->params['spectrum_survey_id']
			),
			'order' => 'SpectrumProject.id DESC'
		));
		if ($spectrum_project && $spectrum_project['SpectrumProject']['project_id'] > 0) {
			$this->lecho('FAILED: #S'.$this->params['spectrum_survey_id'].' has already been created.', $log_file, $log_key);
			$this->lecho('Completed create (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		
		$spectrum_survey_api = $this->getSpectrumSurvey(array('Survey', 'SurveyQuotasAndQualifications')); 
		if (!$spectrum_survey_api) {
			$this->lecho('FAILED: #S'.$this->params['spectrum_survey_id'].' not found in allocations.', $log_file, $log_key);
			$this->lecho('Completed create (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		
		$spectrum_countries = Set::extract('Survey.countries.{n}.code', $spectrum_survey_api);
		if (!in_array('US', $spectrum_countries)) {
			$this->lecho('FAILED: #S'.$this->params['spectrum_survey_id'].' does not have US as country.', $log_file, $log_key);
			$this->lecho('Completed create (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		
		$spectrum_survey = $spectrum_survey_api['Survey'];
		$survey_quotas = $spectrum_survey_api['SurveyQuotas'];
		// extract partner rate, client rate and award amounts
		$payouts = $this->Spectrum->payout($spectrum_survey);
		$overall_quota = $this->Spectrum->quota($spectrum_survey);
		$direct_allocation = false; // todo: Need to confirm with api
		$client_link = $this->client_link($spectrum_survey, $log_file, $log_key);
		$bid_ir = $this->Spectrum->ir($spectrum_survey);
		$loi = $this->Spectrum->loi($spectrum_survey);
		$min_time = round($loi / 4);
		if ($min_time < 1) {
			$min_time = 1;
		}
		
		$save = false;
		$projectSource = $this->Project->getDataSource();
		$projectSource->begin();
		$this->Project->create();
		$project_data = array('Project' => array(
			'prj_name' => $spectrum_survey['survey_name'],
			'client_id' => $this->spectrum_client['Client']['id'],
			'date_created' => date(DB_DATETIME),
			'bid_ir' => $bid_ir,
			'client_rate' => $payouts['client_rate'],
			'partner_rate' => $payouts['partner_rate'],
			'user_payout' => $payouts['partner_rate'],
			'quota' => $overall_quota,
			'est_length' => $loi,
			'minimum_time' => $min_time,
			'country' => 'US',
			'group_id' => $this->spectrum_group['Group']['id'],
			'status' => PROJECT_STATUS_STAGING,
			'client_project_id' => $spectrum_survey['survey_id'],
			'singleuse' => true,
			'touched' => date(DB_DATETIME),
			'language' => $this->Spectrum->language($spectrum_survey),
			'survey_name' => 'Survey for you!',
			'award' => $payouts['award'],
			'active' => false, // after qualifications load, we'll activate it
			'dedupe' => true,
			'client_survey_link' => $client_link,
			'description' => 'Survey for you!'
		));
		if ($this->Project->save($project_data)) {
			$project_id = $this->Project->getInsertId();
			
			MintVine::project_quota_statistics('spectrum', $overall_quota, $project_id);
			
			// Update mask field
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project_id,
				'mask' => $spectrum_survey['survey_id']
			)), true, array('mask'));
			$projectSource->commit();
			
			$spectrumProjectSource = $this->SpectrumProject->getDataSource();
			$spectrumProjectSource->begin();
			$this->SpectrumProject->create();
			$this->SpectrumProject->save(array('SpectrumProject' => array(
				'project_id' => $project_id,
				'spectrum_survey_id' => $spectrum_survey['survey_id'],
				'current_quota' => $overall_quota,
				'status' => SPECTRUM_SURVEY_CREATED,
				'direct' => $direct_allocation
			)));
			$spectrum_project = $this->SpectrumProject->find('first', array(
				'conditions' => array(
					'SpectrumProject.id' => $this->SpectrumProject->getInsertId()
				)
			));
			$spectrumProjectSource->commit();
			$this->set_security_group($spectrum_project, $spectrum_survey);
			
			// add mintvine as a partner
			$this->Project->SurveyPartner->create();
			$this->Project->SurveyPartner->save(array('SurveyPartner' => array(
				'survey_id' => $project_id,
				'partner_id' => $this->mv_partner['Partner']['id'],
				'rate' => $payouts['partner_rate'],
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
			$this->lecho('[ERROR] Failed saving survey due to internal error', $log_file, $log_key); 
			$this->lecho(print_r($this->Project->validationErrors, true), $log_file, $log_key); 
			$this->lecho(print_r($project_data, true), $log_file, $log_key); 
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
			
			if (!defined('IS_DEV_INSTANCE') || IS_DEV_INSTANCE === false) {
				$this->post_on_slack('Spectrum project created <https://cp.mintvine.com/surveys/dashboard/'.$project_id.'>');
			}
			$this->lecho('[SUCCESS] #S'.$spectrum_survey['survey_id'].' created successfully as Project.id#'.$project_id, $log_file, $log_key); 
			// run qualifications on this project
			$this->params['spectrum_survey_id'] = $spectrum_survey['survey_id'];
			$this->qqq($spectrum_survey_api);
			
			// launch this project
			$this->Project->bindModel(array('hasOne' => array(
				'SpectrumProject' => array(
					'className' => 'SpectrumProject',
					'foreignKey' => 'project_id'
				)
			)));
			$project = $this->Project->find('first', array(
				'conditions' => array(
					'Project.id' => $project_id
				)
			));
			$this->launchProject($project, $spectrum_survey_api, $log_file, $log_key);
			
			// alert direct allocations to slack
			if ($direct_allocation) {
				// post to slack
				$setting = $this->Setting->find('first', array(
					'conditions' => array(
						'Setting.name' => 'slack.alerts.webhook',
						'Setting.deleted' => false
					),
					'fields' => array('Setting.value')
				));
				if ($setting) {
					$http = new HttpSocket(array(
						'timeout' => '2',
						'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
					));
					$http->post($setting['Setting']['value'], json_encode(array(
						'text' => 'Direct allocation project created from Spectrum: #'.$project_id.' <https://cp.mintvine.com/surveys/dashboard/'.$project_id.'>',
						'link_names' => 1,
						'username' => 'bernard'
					)));
				}
			}
		}
		$this->lecho('Completed create (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
	}
	
	// update a project, given a spectrum_survey_id
	/* 
		specific order of operations for updating a project, in order of speed
		(1) update core metadata (not status)
		(2) update quota
		(3) check status
		(4) update qualifications
		(5) if closed/staging, check status again
	*/
	public function update() {
		if (!$this->isActive()) {
			return;
		}
		
		$log_file = 'spectrum.update';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting update', $log_file, $log_key); 
		if (!isset($this->params['spectrum_survey_id']) && !isset($this->params['project_id'])) {
			$this->lecho('FAILED: You need at least spectrum_survey_id or project_id set to update', $log_file, $log_key);
			return false;
		}
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			$this->lecho('Completed update (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			// this method logs the errors already
			return false;
		}
		if (isset($this->params['spectrum_survey_id'])) {
			$spectrum_project = $this->SpectrumProject->find('first', array(
				'conditions' => array(
					'SpectrumProject.spectrum_survey_id' => $this->params['spectrum_survey_id']
				),
				'order' => 'SpectrumProject.id DESC'
			));
			
			if (empty($spectrum_project['SpectrumProject']['project_id'])) {
				$this->lecho('Spectrum project #S'. $this->params['spectrum_survey_id'] .' has not been imported.', $log_file, $log_key);
				return false;
			}
			$project_id = $spectrum_project['SpectrumProject']['project_id'];
		}
		else {
			$project_id = $this->params['project_id']; 
		}
		
		$this->Project->bindModel(array(
			'hasOne' => array(
				'SpectrumProject'
			)
		));
		$project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $project_id
			)
		));
		
		$mintvine_group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'mintvine'
			)
		));
		// block ad-hoc updates; this is a sanity check to prevent items added into queue from updating if a PM has switched to ad-hoc
		if ($project['SpectrumProject']['status'] == 'skipped.adhoc' || empty($project['Project']['group_id']) || $project['Project']['group_id'] == $mintvine_group['Group']['id']) {
			return false;
		}
		
		$this->lecho('Updating Spectrum project Project.id#'. $project_id, $log_file, $log_key);
		$spectrum_survey_api = $this->getSpectrumSurvey(array('Survey', 'SurveyQuotasAndQualifications'));
		if (!$spectrum_survey_api) {
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
				'type' => 'status.closed.spectrum',
				'description' => 'Not found in allocation'
			)));
			Utils::save_margin($project['Project']['id']);
			$this->lecho('FAILED: #S'.$this->params['spectrum_survey_id'].' not found in allocations.', $log_file, $log_key);
			$this->lecho('Completed create (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		$spectrum_survey = $spectrum_survey_api['Survey'];
		$survey_quotas = $spectrum_survey_api['SurveyQuotas'];
		
		// extract partner rate, client, rate and award amounts
		$payouts = $this->Spectrum->payout($spectrum_survey);
		$overall_quota = $this->Spectrum->quota($spectrum_survey);
		$direct_allocation = false; // TODO: Need to confirm with api
		$client_link = $this->client_link($spectrum_survey, $log_file, $log_key);
		$bid_ir = $this->Spectrum->ir($spectrum_survey);
		$loi = $this->Spectrum->loi($spectrum_survey);
		$min_time = round($loi / 4);
		if ($min_time < 1) {
			$min_time = 1;
		}
		
		MintVine::project_quota_statistics('spectrum', $overall_quota, $project['Project']['id']);
		
		// for existing projects, update overall_quota with the # of completes
		// note: this is used below for checking closed status
		$survey_visit_cache = $this->SurveyVisitCache->find('first', array(
			'fields' => array('complete', 'click', 'nq', 'overquota'),
			'conditions' => array(
				'SurveyVisitCache.survey_id' => $project['Project']['id']
			),
			'recursive' => -1
		));
		if ($overall_quota && $overall_quota > 0 && $survey_visit_cache) {
			$overall_quota = $overall_quota + $survey_visit_cache['SurveyVisitCache']['complete'];
		}
		
		$this->set_security_group($project, $spectrum_survey);
		
		$project_data = array('Project' => array(
			'id' => $project['Project']['id'],
			'client_rate' => $payouts['client_rate'],
			'award' => $payouts['award'],
			'client_id' => $this->spectrum_client['Client']['id'],
			'prj_name' => $spectrum_survey['survey_name'],
			'bid_ir' => $bid_ir,
			'partner_rate' => $payouts['partner_rate'],
			'user_payout' => $payouts['partner_rate'],
			'quota' => $overall_quota,
			'est_length' => $loi,
			'minimum_time' => $min_time,
			'language' => $this->Spectrum->language($spectrum_survey),
			'client_survey_link' => $client_link,
		));
		
		$project_changed = Utils::array_values_changed($project_data['Project'], $project['Project']);
		if ($project_changed) {
			// if rate changes we update partners
			if ($project['Project']['client_rate'] != $payouts['client_rate']) {
				$survey_partners = $this->Project->SurveyPartner->find('all', array(
					'conditions' => array(
						'SurveyPartner.survey_id' => $project['Project']['id'],
						'partner_id' => $this->mv_partner['Partner']['id'],
					))
				);
				foreach ($survey_partners as $survey_partner) {
					$this->Project->SurveyPartner->create();
					$this->Project->SurveyPartner->save(array('SurveyPartner' => array(
						'id' => $survey_partner['SurveyPartner']['id'],
						'rate' => $payouts['partner_rate'],
					)), true, array('rate'));
				}
			}
			
			// update the project touch date
			$project_data['Project']['touched'] = date(DB_DATETIME); 
			$project_changed[] = 'touched';
			$this->Project->create();
			$this->Project->save($project_data, true, $project_changed);
			$this->lecho('[SUCCESS] Project Project.id#'.$project['Project']['id'].' updated with new fields', $log_file, $log_key); 
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'updated',
				'description' => json_encode($project_changed)
			)));
		}
		else {
			// update the last touched timestamp
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'touched' => date(DB_DATETIME)
			)), true, array('touched'));
			
			$this->lecho('[SKIPPED] Project Project.id#'.$project['Project']['id'].' does not need to be updated', $log_file, $log_key); 
		}
		
		$project_status = $project['Project']['status'];
		
		// if this project is open, and Spectrum is reporting a closed project, then close it
		// no need to check project.active
		if (!$project['Project']['ignore_autoclose'] && $project['Project']['status'] == PROJECT_STATUS_OPEN) {
			$still_open = true;
			if ($this->Spectrum->is_closed($spectrum_survey)) {
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
					'type' => 'status.closed.spectrum',
					'description' => 'Closed by Spectrum'
				)));
				Utils::save_margin($project['Project']['id']);
				$still_open = false;
				$this->lecho('[SUCCESS] Project Project.id#'.$project['Project']['id'].' closed by Spectrum', $log_file, $log_key); 
			}
			// after update, the new rules fail some internal mechanisms
			elseif (!$this->checkInternalSpectrumRules($spectrum_survey_api, $log_file, $log_key)) {
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
					'type' => 'status.closed.rules',
					'failed_rule' => (isset($this->failed_rule)) ? $this->failed_rule : '',
					'failed_data' => json_encode($spectrum_survey),
					'description' => 'Closed because it failed our project filter rules'
				)));
				Utils::save_margin($project['Project']['id']);
				$still_open = false;
				$this->failed_rule = '';
			}
			else {
				// run a performance-based check
				$mvapi = new HttpSocket(array(
					'timeout' => 15,
					'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
				));
				$mvapi->configAuth('Basic', $this->settings['api.mintvine.username'], $this->settings['api.mintvine.password']);
				$results = $mvapi->post($this->settings['hostname.api'].'/surveys/test_survey_status/'.$project['Project']['id']);
				$response = json_decode($results['body'], true);
				
				if ($results['close_project']) {
					$still_open = false;
					$this->lecho('[CLOSED] #'.$project['Project']['id'].' Closed for performance reaasons', $log_file, $log_key); 
				}
			}
			
			// figure out to send to more sample, or "launch" if sampling
			if ($still_open) {
				// projects that get taken out of our allocation are "stopped" - take them out of that state
				if (!$project['Project']['active']) {
					$this->Project->create();
					$this->Project->save(array('Project' => array(
						'id' => $project['Project']['id'],
						'active' => true
					)), true, array('active')); 
				}
			}
		}
		// if this project is closed, then try to reopen it
		elseif (!$project['Project']['ignore_autoclose'] && $project['Project']['status'] == PROJECT_STATUS_CLOSED) {
			// if this project is now available and open
			if (!$this->Spectrum->is_closed($spectrum_survey) && $this->checkInternalSpectrumRules($spectrum_survey_api, $log_file, $log_key)) {
				$last_close_status = $this->ProjectLog->find('first', array(
					'conditions' => array(
						'ProjectLog.project_id' => $project['Project']['id'],
						'ProjectLog.type LIKE' => 'status.closed%'
					),
					'order' => 'ProjectLog.id DESC'
				));
				
				// status.closed.spectrum - If the project was explicitly closed by Spectrum, re-open if it's now open
				// status.closed.rules - If the project was close becoz of rules, re-open as they changed now like bid_ir, loi etc
				if (!$last_close_status || in_array($last_close_status['ProjectLog']['type'], array('status.closed.spectrum', 'status.closed.rules'))) {
					$need_to_reopen = false;
					if (empty($survey_visit_cache['SurveyVisitCache']['complete'])) {
						$need_to_reopen = true;
					}
					else {
						$mvapi = new HttpSocket(array(
							'timeout' => 15,
							'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
						));
						$mvapi->configAuth('Basic', $this->settings['api.mintvine.username'], $this->settings['api.mintvine.password']);
						$results = $mvapi->get($this->settings['hostname.api'].'/surveys/test_survey_status/'.$project['Project']['id']);
						$response = json_decode($results['body'], true);
						
						if ($response['open_project']) { 
							$need_to_reopen = true;
						}
					}
					
					if ($need_to_reopen) {
						// reopen project
						$count = $this->SurveyUser->find('count', array(
							'conditions' => array(
								'SurveyUser.survey_id' => $project['Project']['id']
							),
							'recursive' => -1
						));
						
						$this->Project->create();
						$this->Project->save(array('Project' => array(
							'id' => $project['Project']['id'],
							'status' => $count == 0 ? PROJECT_STATUS_STAGING : PROJECT_STATUS_OPEN,
							'ended' => null,
							'active' => true
						)), true, array('status', 'active', 'ended'));
						
						$this->ProjectLog->create();
						$this->ProjectLog->save(array('ProjectLog' => array(
							'project_id' => $project['Project']['id'],
							'type' => 'status.opened.spectrum',
							'description' => 'Reopened by Spectrum'
						)));
						$project_status = ($count == 0) ? PROJECT_STATUS_STAGING : PROJECT_STATUS_OPEN;
					}
				}
			}
		}
		elseif (!$project['Project']['ignore_autoclose'] && $project['Project']['status'] == PROJECT_STATUS_STAGING) {
			$this->launchProject($project, $spectrum_survey_api, $log_file, $log_key);
		}
		
	
		// write a value that stores the last invite time
		$project_option = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.project_id' => $project['Project']['id'],
				'ProjectOption.name' => 'spectrum.lastrun.updated'
			)
		));
		if ($project_option) {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'id' => $project_option['ProjectOption']['id'],
				'value' => date(DB_DATETIME),
			)), true, array('value'));
		}
		else {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'name' => 'spectrum.lastrun.updated',
				'value' => date(DB_DATETIME),
				'project_id' => $project['Project']['id']
			)));
		}
		
		// update qualifications as last step
		$this->params['spectrum_survey_id'] = $project['SpectrumProject']['spectrum_survey_id'];
		if ($project_status == PROJECT_STATUS_OPEN) {
			$this->qqq();
		}
		$this->lecho('Completed update (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
	}
	
	protected function launchProject($project, $spectrum_survey_api, $log_file, $log_key) {
		// bypass for ignored clients
		if ($project['Client']['do_not_autolaunch']) {
			return false;
		}
		
		if ($this->settings['spectrum.autolaunch'] == 'true' && $this->checkInternalSpectrumRules($spectrum_survey_api, $log_file, $log_key)) {
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'status' => PROJECT_STATUS_OPEN,
				'ended' => null,
				'started' => date(DB_DATETIME),
				'active' => true
			)), true, array('status', 'active', 'ended', 'started'));
	
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'status.opened',
				'internal_description' => 'auto',
			)));
			
			$this->lecho('[LAUNCHED] #' . $project['Project']['id'].' (#S'.$this->params['spectrum_survey_id'].')', $log_file, $log_key);
			$this->post_on_slack('Spectrum project launched <https://cp.mintvine.com/surveys/dashboard/'.$project['Project']['id'].'>');
		}
	}
	
	// performance-based closed rules are handled on the API; this is strictly to handle bad properties
	protected function checkInternalSpectrumRules($spectrum_survey_api, $log_file = null, $log_key = null) {
		$spectrum_survey = $spectrum_survey_api['Survey'];
		$survey_quotas = $spectrum_survey_api['SurveyQuotas'];
		
		// extract partner rate, client, rate and award amounts
		$payouts = $this->Spectrum->payout($spectrum_survey);
		$client_link = $this->client_link($spectrum_survey, $log_file, $log_key);
		$ir = $this->Spectrum->ir($spectrum_survey);
		$loi = $this->Spectrum->loi($spectrum_survey);
		
		$return = true;
		// flooring rules
		if (empty($payouts['award'])) {
			$return = false;
			$message = '[FAILED RULE] Empty award';
		}
		elseif (empty($client_link)) {
			$return = false;
			$message = '[FAILED RULE] Empty link';
		}
		// award is less than floor
		elseif ($payouts['award'] < $this->settings['spectrum.floor.award']) {
			$return = false;
			$message = '[FAILED RULE] Award floor ('.$payouts['award'].' < '.$this->settings['spectrum.floor.award'].')';
		}
		// check floor EPC
		elseif (!empty($ir) && ($payouts['client_rate'] * $ir / 100) <= ($this->spectrum_group['Group']['epc_floor_cents'] / 100)) {
			$return = false;
			$message = '[FAILED RULE] EPC floor '.$payouts['client_rate'].' '.$ir.' ('.($payouts['client_rate'] * $ir / 100).' <= '.($this->spectrum_group['Group']['epc_floor_cents'] / 100).')';
		}
		// ratio floor rule
		elseif (!empty($loi) && $loi * $this->settings['spectrum.floor.loi.ratio'] > $payouts['award']) {
			$return = false;
			$message = '[FAILED RULE] Award ratio ('.($loi * $this->settings['spectrum.floor.loi.ratio']).' > '.$payouts['award'].')';
		}
		// long LOIs are skipped
		elseif ($loi > $this->settings['spectrum.max.loi']) {
			$return = false;
			$message = '[FAILED RULE] Long LOI ('.$loi.' minutes)';
		}
		
		// unsupported country
		$supported_countries = array_keys(unserialize(SUPPORTED_COUNTRIES));
		$is_supported_country = false;
		if (!empty($spectrum_survey['countries'])) {
			foreach ($spectrum_survey['countries'] as $country) {
				if (in_array($country['code'], $supported_countries)) {
					$is_supported_country = true;
					break;
				}
			}
		}
		if (!$is_supported_country) {
			$return = false;
			$message = '[FAILED RULE] Unsupported countries' . json_encode($spectrum_survey['countries']);
		}
		if (!empty($message)) {
			$this->failed_rule = $message;
		}
		if (!empty($log_file) && !empty($log_key) && !empty($message)) {
			$this->lecho($message, $log_file, $log_key);
		}
		return $return;
	}
	
	// goes to API and retrieves a single project's items
	protected function getSpectrumSurvey($items_to_return = array('Survey', 'SurveyQuotasAndQualifications')) {
		$return = array();
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$params = array(
			'supplier_id' => $this->settings['spectrum.supplier_id'],
			'access_token' => $this->settings['spectrum.access_token']
		);
		$header = array('header' => array(
			'Content-Type' => 'application/x-www-form-urlencoded',
			'cache-control' => 'no-cache'
		));
		
		if (in_array('Survey', $items_to_return)) {
			// For now there is no endpoint for single survey info by survey number, they are doing work on this, once complete, will replace with that one
			$url = $this->settings['spectrum.host'].'/suppliers/surveys';
			$response = $http->post($url, $params, $header);
			$response_body = json_decode($response->body, true);
			
			if ($response->code != 200 || !isset($response_body['apiStatus']) || $response_body['apiStatus'] =! 'success') {
				return false;
			}
			
			if (!empty($response_body['surveys'])) {
				foreach ($response_body['surveys'] as $survey) {
					if ($survey['survey_id'] == $this->params['spectrum_survey_id']) {
						$return['Survey'] = $survey;
						break;
					}
				}
			}
			if (empty($return['Survey'])) {
				return false;
			}
		}
		if (in_array('SurveyQuotasAndQualifications', $items_to_return)) {
			$url = $this->settings['spectrum.host'].'/suppliers/surveys/qualifications-quotas';
			$params['survey_id'] = $this->params['spectrum_survey_id'];
			$response = $http->post($url, $params, $header);
			$response_body = json_decode($response->body, true);
			$return['SurveyQuotas'] = $response_body['quotas'];
			$return['SurveyQualifications'] = $response_body['qualifications'];
		}
		return $return;
	}
	
	private function loadSettings($log_file, $log_key, $time_start) {
		$keys = array(
			// Pure spectrum API credentials
			'spectrum.active',
			'spectrum.host',
			'spectrum.supplier_id',
			'spectrum.access_token',
			
			// various settings
			'spectrum.floor.loi.ratio',
			'spectrum.ir_cutoff',
			'spectrum.floor.award',
			'spectrum.autolaunch',
			'spectrum.sample_threshold',
			'spectrum.max.loi',
			
			//SQS settings
			'sqs.access.key',
			'sqs.access.secret',
			'sqs.spectrum.queue',
			
			// QE2 API
			'qe.mintvine.username',
			'qe.mintvine.password',
			'hostname.qe',
			
			// mv API
			'api.mintvine.username',
			'api.mintvine.password',
			'hostname.api',
			
			// slack channels for alerts
			'slack.qe2.webhook',
			'qqq.active'
		);
		$this->settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => $keys,
				'Setting.deleted' => false
			)
		));
		if (count($this->settings) != count($keys)) {
			$diff_keys = array_diff($keys,  array_keys($this->settings));
			$this->lecho('FAILED: You are missing required Spectrum settings :'.implode(', ', $diff_keys), $log_file, $log_key);
			$this->lecho('Completed loadSettings (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		
		$this->spectrum_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'spectrum'
			)
		));
		$this->spectrum_client = $this->Client->find('first', array(
			'conditions' => array(
				'Client.key' => 'spectrum',
			)
		));
		$this->mv_partner = $this->Partner->find('first', array(
			'conditions' => array(
				'Partner.key' => 'mintvine'
			)
		));
		if (!$this->spectrum_group || !$this->spectrum_client || !$this->mv_partner) {
			$this->lecho('FAILED: Missing client, group, or partner', $log_file, $log_key);
			$this->lecho('Completed loadSettings (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		return true;
	}
	
	private function client_link($spectrum_survey, $log_file = null, $log_key = null) {
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$params = array(
			'supplier_id' => $this->settings['spectrum.supplier_id'],
			'access_token' => $this->settings['spectrum.access_token'],
			'survey_id' => $spectrum_survey['survey_id'],
		);
		$header = array('header' => array(
			'Content-Type' => 'application/x-www-form-urlencoded',
			'cache-control' => 'no-cache'
		));
		
		$url = $this->settings['spectrum.host'].'/suppliers/surveys/register'; 
		$response = $http->post($url, $params, $header);
		$response_body = json_decode($response->body, true);
		if ($response->code != 200 || !isset($response_body['apiStatus']) || $response_body['apiStatus'] =! 'success') {
			if (!empty($log_file) && !empty($log_key)) {
				$this->lecho('FAILED: Link look up failed for S#'.$spectrum_survey['survey_id'], $log_file, $log_key);
				$this->lecho($response, $log_file, $log_key);
			}
			return false;
		}
		elseif (empty($response_body['survey_entry_url'])) {
			if (!empty($log_file) && !empty($log_key)) {
				$this->lecho('FAILED: Emptry Link for S#'.$spectrum_survey['survey_id'], $log_file, $log_key);
				$this->lecho($response, $log_file, $log_key);
			}
			return false;
		}
		
		return $response_body['survey_entry_url'].'&ps_supplier_respondent_id={{USER}}&ps_supplier_sid={{ID}}';
	}
	
	private function isActive() {
		$spectrum_settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => 'spectrum.active',
				'Setting.deleted' => false
			),
			'fields' => array(
				'Setting.name',
				'Setting.value'
			)
		));
		
		if ($spectrum_settings && $spectrum_settings['spectrum.active'] == 'true') {
			return true;
		}
		return false;
	}
	
	public function performance() {
		if (!isset($this->args[0])) {
			$offset = '1';
		}
		else {
			$offset = $this->args[0];
		}
		$ts = strtotime('-'.$offset.' hours');
		$this->out('Executing since '.date(DB_DATETIME, $ts)."\n");
		$spectrum_queues = $this->SpectrumQueue->find('all', array(
			'fields' => array('SpectrumQueue.created', 'SpectrumQueue.executed'),
			'conditions' => array(
				'SpectrumQueue.created >=' => date(DB_DATETIME, $ts),
				'SpectrumQueue.executed is not null'
			)
		));
		if (!$spectrum_queues) {
			$this->out('No data found.');
			return;
		}
		$count = 0;
		$diffs = array();
		$min = 1000000;
		$max = 0;
		foreach ($spectrum_queues as $spectrum_queue) {
			$diff = strtotime($spectrum_queue['SpectrumQueue']['executed']) - strtotime($spectrum_queue['SpectrumQueue']['created']);
			if ($diff < $min) {
				$min = $diff;
			}
			if ($diff > $max) {
				$max = $diff;
			}
			$diffs[] = $diff;
		}
		$this->out('Total work items in past '.$offset.' hours: '.(number_format(count($spectrum_queues)))."\n");
		
		$avg = round(array_sum($diffs) / count($diffs));
		$this->out('Average time to execution '.number_format($avg).' seconds'."\n");
		$this->out('Min: '.number_format($min)."\n");
		$this->out('Max: '.number_format($max)."\n");
	}
	
	public function offerwall() {
		$log_file = 'spectrum.offerwall';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true); 
		
		// load settings, client, group, etc. 
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			return false;
		}
		
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$params = array(
			'supplier_id' => $this->settings['spectrum.supplier_id'],
			'access_token' => $this->settings['spectrum.access_token'],
		);
		
		// get our total allocation across the board
		$url = $this->settings['spectrum.host'].'/suppliers/surveys'; 
		$response = $http->post($url, $params, array(
			'header' => array(
				'Content-Type' => 'application/x-www-form-urlencoded',
				'cache-control' => 'no-cache'
			)
		));
		$response_body = json_decode($response->body, true);
		print_r($response_body); 
	}
	
	// output all API calls for a given spectrum_survey_id survey
	public function debug() {
		if (!isset($this->params['spectrum_survey_id'])) {
			$this->out('FAILED: You are missing spectrum_survey_id');
			return false;
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
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$params = array(
			'supplier_id' => $settings['spectrum.supplier_id'],
			'access_token' => $settings['spectrum.access_token']
		);
		$header = array('header' => array(
			'Content-Type' => 'application/x-www-form-urlencoded',
			'cache-control' => 'no-cache'
		));
		
		// For now there is no endpoint for single survey info by survey number, they are doing work on this, once complete, will replace with that one
		$url = $settings['spectrum.host'].'/suppliers/surveys';
		$response = $http->post($url, $params, $header);
		$response_body = json_decode($response->body, true);
		$survey_data = array();
		if (!empty($response_body['surveys'])) {
			foreach ($response_body['surveys'] as $survey) {
				if ($survey['survey_id'] == $this->params['spectrum_survey_id']) {
					$survey_data = $survey;
					break;
				}
			}
		}
		
		if (empty($survey_data)) {
			$this->out('No survey found.');
			return;
		}
		$this->out($url);
		$this->out(print_r($survey_data), true);
		
		$url = $settings['spectrum.host'].'/suppliers/surveys/qualifications-quotas';
		$params['survey_id'] = $this->params['spectrum_survey_id'];
		$response = $http->post($url, $params, $header);
		$response_body = json_decode($response->body, true);
		
		$this->out($url);
		$this->out(print_r($response_body), true);
	}
	
	public function import_zipcodes() {
		ini_set('memory_limit', '2048M');
		App::import('Model', 'SpectrumZip');
		$this->SpectrumZip = new SpectrumZip;
		
		if (!isset($this->args[0]) || !is_file($this->args[0])) {
			$file = WWW_ROOT.'files/spectrum_zip_table_v3.csv';
		}
		else {
			$file = $this->args[0];
		}
		
		$csv = array_map('str_getcsv', file($file));
	    array_walk($csv, function(&$a) use ($csv) {
	      $a = array_combine($csv[0], $a);
	    });
	    array_shift($csv); # remove column header
		
		$this->out(count($csv).' rows');
		$i = 0; 
		if (!empty($csv)) {
			foreach ($csv as $row) {
				/*
					[ZipCode] => 00501
					[Region] => Northeast
					[Division] => Middle Atlantic
					[StateFullName] => New York
					[State] => NY
					[StateANSI] => 36
					[CSA Name] => New York-Newark, NY-NJ-CT-PA
					[CSA Code] => 408
					[MSA_Name] => New York-Northern New Jersey-Long Island NY-NJ-CT-PA
					[MSAANSI] => 5602
					[County] => SUFFOLK
					[CountyANSI] => 103
				*/
				$spectrum_zip = $this->SpectrumZip->find('first', array(
					'fields' => array('SpectrumZip.id'),
					'conditions' => array(
						'SpectrumZip.zipcode' => $row['ZipCode']
					)
				));
				$save_data = array('SpectrumZip' => array(
					'zipcode' => $row['ZipCode'],
					'region' => $row['Region'],
					'division' => $row['Division'],
					'state_full' => $row['StateFullName'],
					'state_abbr' => $row['State'],
					'state_ansi' => $row['StateANSI'],
					'csa_name' => $row['CSA Name'],
					'csa_code' => $row['CSA Code'],
					'msa_name' => $row['MSA_Name'],
					'msa_ansi' => $row['MSAANSI'],
					'county' => $row['County'],
					'county_ansi' => $row['CountyANSI']
				));
				
				if ($spectrum_zip) {
					$save_data['SpectrumZip']['id'] = $spectrum_zip['SpectrumZip']['id'];
				}
				$this->SpectrumZip->create();
				$this->SpectrumZip->save($save_data);
				$i++;
			}
			$this->out('Import complete: '.$i.' records created/updated');
		}
	}
	
	private function post_on_slack($message) {
		// post to slack
		$slack_setting = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'slack.alerts.spectrum',
				'Setting.deleted' => false
			),
			'fields' => array('Setting.value')
		));
		if ($slack_setting) {
			$http = new HttpSocket(array(
				'timeout' => '2',
				'ssl_verify_host' => false
			));
			$http->post($slack_setting['Setting']['value'], json_encode(array(
				'text' => $message, 
				'link_names' => 1,
				'username' => 'bernard'
			)));
			$this->out($message);
		}
	}
	
	public function qqq($spectrum_survey_api = false) {
		if (!$this->isActive()) {
			$this->out('Spectrum is not active');
		}
		
		// for testing locally; use the mock data; will generate range of user ids 1 - 2000
		if (defined('IS_DEV_INSTANCE') && IS_DEV_INSTANCE === true) {
			App::import('Lib', 'MockQueryEngine');
		}
		$spectrum_settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array(
					'slack.alerts.webhook'
				),
				'Setting.deleted' => false
			)
		));
		
		if (count($spectrum_settings) < 1) {
			$this->out('ERROR: Missing settings');
			return false;
		}
		
		ini_set('memory_limit', '1024M');
		$log_file = 'spectrum.qe2';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting qualifications', $log_file, $log_key); 
		
		if (!isset($this->params['spectrum_survey_id'])) {
			$this->lecho('FAILED: You need to define the spectrum_survey_id', $log_file, $log_key);
			$this->lecho('Completed qu+alifications (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			// this method logs the errors already
			$this->lecho('Completed qualifications (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		
		// be sure we retrieve the correct spectrum_project; an active one!
		$spectrum_project = $this->SpectrumProject->find('first', array(
			'conditions' => array(
				'SpectrumProject.spectrum_survey_id' => $this->params['spectrum_survey_id'],
				'SpectrumProject.project_id >' => '0'
			),
			'order' => 'SpectrumProject.id DESC'
		));
		$this->Project->bindModel(array(
			'hasOne' => array(
				'SpectrumProject' => array(
					'className' => 'SpectrumProject',
					'foreignKey' => 'project_id'
				)
			)
		));
		if ($spectrum_project) {
			$project = $this->Project->find('first', array(
				'conditions' => array(
					'Project.id' => $spectrum_project['SpectrumProject']['project_id']
				)
			));
		}
		
		if (!$spectrum_project || !$project || empty($project['Project']['country'])) {
			$this->lecho('FAILED: Could not locate the MV project that is associated with #S'.$this->params['spectrum_survey_id'], $log_file, $log_key);
			$this->lecho('Completed qualifications (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		
		$query_count = $this->Query->find('count', array(
			'conditions' => array(
				'Query.survey_id' => $project['Project']['id']
			),
			'recursive' => -1
		));
		
		if ($query_count > 0) { // Keep old projects without updates
			return true;
		}
		
		if (!$spectrum_survey_api) {
			$spectrum_survey_api = $this->getSpectrumSurvey(array('Survey', 'SurveyQuotasAndQualifications')); 
		}
		
		if (empty($spectrum_survey_api['SurveyQualifications'])) {
			$this->lecho('Could not find any qualifications for #S'.$this->params['spectrum_survey_id'], $log_file, $log_key);
			$this->lecho('Completed qualifications (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		$spectrum_survey = $spectrum_survey_api['Survey'];
		$survey_quotas = $spectrum_survey_api['SurveyQuotas'];
		$survey_qualifications = $spectrum_survey_api['SurveyQualifications'];
		
		// determine: are we running this again and do we need to do something?
		$qualification_hash = md5(serialize($survey_qualifications));
		
		$this->lecho('Running qualification hash check on '.'#S'.$this->params['spectrum_survey_id'].' ('.$qualification_hash.')', $log_file, $log_key);
		
		$qualification_hash_option = $this->ProjectOption->find('first', array(
			'fields' => array('ProjectOption.id', 'ProjectOption.name', 'ProjectOption.value'),
			'conditions' => array(
				'ProjectOption.project_id' => $project['Project']['id'],
				'ProjectOption.name' => 'spectrum.qe2.qualification',
			)
		));
		
		$payouts = $this->Spectrum->payout($spectrum_survey);
		$overall_quota = $this->Spectrum->quota($spectrum_survey);
		
		if (empty($this->params['force']) && $qualification_hash_option && $qualification_hash_option['ProjectOption']['value'] == $qualification_hash) {
			// update the qualification quotas
			if (!empty($survey_quotas)) {
				foreach ($survey_quotas as $survey_quota) {
					$this->Qualification->bindModel(array('hasOne' => array('QualificationStatistic'))); 
					$qualification = $this->Qualification->find('first', array(
						'conditions' => array(
							'Qualification.project_id' => $project['Project']['id'],
							'Qualification.partner_qualification_id' => $survey_quota['quota_id'],
							'Qualification.deleted is null'
						)
					));
					if ($qualification) {
						$this->Qualification->create();
						$original_qualification_data = array(
							'id' => $qualification['Qualification']['id'],
							'cpi' => $qualification['Qualification']['cpi'],
							'award' => $qualification['Qualification']['award'],
							'quota' =>  $qualification['Qualification']['quota']
						);
						$qualification_data = array(
							'id' => $qualification['Qualification']['id'],
							'cpi' => $payouts['client_rate'],
							'award' => $payouts['award'],
							'quota' =>  $survey_quota['supplier_completes']['open_quantity'] + $qualification['QualificationStatistic']['completes']
						);
						$this->Qualification->save(array('Qualification' => $qualification_data), true, array('quota', 'cpi', 'award'));

						$diff = array_diff($qualification_data, $original_qualification_data);
						if (count($diff) > 0) {
							$log = '';
							foreach ($diff as $key => $val) {
								$log .= $key . ' was updated from "' . $original_qualification_data[$key] . '" to "' . $val . '", ';
							}
							$log = substr($log, 0, -2);
							$this->ProjectLog->create();
							$this->ProjectLog->save(array('ProjectLog' => array(
								'project_id' => $project['Project']['id'],
								'type' => 'qualification.updated',
									'description' => 'Qualification #' . $qualification['Qualification']['id'] . ' updated: ' . $log
							)));
						}
					}
				}
			}
			
			$this->lecho('[Skipped] QE2 Qualifications did not change for #S' . $this->params['spectrum_survey_id'], $log_file, $log_key);
			return false;
		}
		
		if (!$qualification_hash_option) {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'project_id' => $project['Project']['id'],
				'name' => 'spectrum.qe2.qualification',
				'value' => $qualification_hash
			)));
		}
		else {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'id' => $qualification_hash_option['ProjectOption']['id'],
				'value' => $qualification_hash
			)), true, array('value'));
		}
		
		$query_body = array(
			'partner' => 'mintvine',
			'qualifications' => array()
		);
		
		$qualifications_params = array('country' => array($project['Project']['country']));
		$parent_query_match = true;
		$desktop = true;
		$tablet = true;
		$mobile = true;
		$skipped_parent_conditions = array(); // Skipped because of contain all answers set
		foreach ($survey_qualifications as $survey_qualification) {
			// Handle devices
			if ($survey_qualification['code'] == 219) {
				$desktop = false;
				$tablet = false;
				$mobile = false;
				foreach ($survey_qualification['conditions'] as $condition) {
					if ($condition['code'] == 111) {
						$desktop = true;
					}
					elseif ($condition['code'] == 112) {
						$mobile = true;
					}
					elseif ($condition['code'] == 113) {
						$tablet = true;
					}
				}
			}
			// we only support OR type conditions.
			elseif ($survey_qualification['operator'] != 'Or') {
				$this->lecho('Invalid operator "'.$survey_qualification['operator'].'" for qualification "'.$survey_qualification['name'].'" found in #S' . $this->params['spectrum_survey_id'], $log_file, $log_key);
				$parent_query_match = false;
			}
			elseif (SpectrumMappings::is_mapped($survey_qualification['code'])) {
				// $qualifications_params - Call by reference
				$skipped_key = $this->Spectrum->mappings($qualifications_params, $survey_qualification);
				if ($skipped_key) {
					$skipped_parent_conditions[] = $skipped_key;
				}
			}
			else {
				$this->lecho('Qualification is out of our mappings "'.$survey_qualification['name'].'" => "'.$survey_qualification['code'].'" found in #S' . $this->params['spectrum_survey_id'], $log_file, $log_key);
				$parent_query_match = false;
			}
		}
		
		// Update mobile, tablet & desktop flags
		$this->Project->create();
		$this->Project->save(array('Project' => array(
			'id' => $project['Project']['id'],
			'mobile' => $mobile,
			'tablet' => $tablet,
			'desktop' => $desktop,
		)), true, array('mobile', 'tablet', 'desktop'));
		
		asort($qualifications_params);
		$query_body['qualifications'] = $query_body['qualifications'] + $qualifications_params;
		$query_json = $raw_query_json = json_encode($query_body);
		$query_json = QueryEngine::qe2_modify_query($query_json);
		CakeLog::write($log_file, $raw_query_json);
		CakeLog::write($log_file, $query_json);	
		$query_hash = md5($query_json); 
		
		$this->lecho('Created query ('.$query_hash.')', $log_file, $log_key);
		
		// create the qualification if it doesn't exist
		$this->Qualification->bindModel(array('hasOne' => array('QualificationStatistic')));
		$total_qualification = $this->Qualification->find('first', array(
			'conditions' => array(
				'Qualification.project_id' => $project['Project']['id'],
				'Qualification.parent_id' => null,
				'Qualification.deleted is null'
			)
		));
		
		$created_qualification_ids = array();
		if (!$total_qualification) {
			$qualificationSource = $this->Qualification->getDataSource();
			$qualificationSource->begin();
			$this->Qualification->create();
			$this->Qualification->save(array('Qualification' => array(
				'project_id' => $project['Project']['id'],
				'name' => '#S'.$project['Project']['mask'].' - Qualification',
				'query_hash' => $query_hash,
				'query_json' => $query_json,
				'raw_json' => $raw_query_json,
				'active' => false, /* disable this until this process is complete in case notification service kicks off */
			)));
			$parent_qualification_id = $this->Qualification->getInsertId();
			$qualificationSource->commit();
			$this->lecho('Created qualification ('.$parent_qualification_id.')', $log_file, $log_key);
			$created_qualification_ids[] = $parent_qualification_id;

			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'qualification.created',
				'description' => 'Qualification #'.$parent_qualification_id.' created.',
			)));
		}
		else {
			$parent_qualification_id = $total_qualification['Qualification']['id'];
			$this->lecho('Qualification already exists ('.$parent_qualification_id.')', $log_file, $log_key);
		}
		
		// because quotas are constantly updated; we need to add our completes to it so we don't exclude ourselves
		$existing_completes = 0;
		if ($total_qualification) {
			$existing_completes = $total_qualification['QualificationStatistic']['completes'];
		}
		
		// write the quota
		$parent_qualification = $this->Qualification->findById($parent_qualification_id);
		$original_qualification_data = array(
			'id' => $parent_qualification['Qualification']['id'],
			'quota' => $parent_qualification['Qualification']['quota'],
			'cpi' => $parent_qualification['Qualification']['cpi'],
			'award' => $parent_qualification['Qualification']['award']
		);
		$qualification_data = array(
			'id' => $parent_qualification_id,
			'quota' => $overall_quota + $existing_completes,
			'cpi' => $payouts['client_rate'],
			'award' => $payouts['award']
		);
		$this->Qualification->create();
		$this->Qualification->save(array('Qualification' => $qualification_data), true, array('award', 'cpi', 'quota'));

		$diff = array_diff($qualification_data, $original_qualification_data);
		if (count($diff) > 0) {
			$log = '';
			foreach ($diff as $key => $val) {
				$log .= $key . ' was updated from "' . $original_qualification_data[$key] . '" to "' . $val . '", ';
			}
			$log = substr($log, 0, -2);
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'qualification.updated',
				'description' => 'Qualification #' . $parent_qualification_id . ' updated: ' . $log,
			)));
		}

		$this->lecho('Updated qualification '.$parent_qualification_id.' with new data', $log_file, $log_key);
		
		if (!$project['Client']['do_not_autolaunch']) {
			if (defined('IS_DEV_INSTANCE') && IS_DEV_INSTANCE === true) {
				$panelist_ids = MockQueryEngine::parent_panelists(1); 
			}
			else {
				$panelist_ids = QueryEngine::qe2($this->settings, $query_json);
			}
			$this->lecho('Panelists returned by QE2: '.$qe2_count.' panelists', $log_file, $log_key);
		
			// post to slack channel
			// todo: need to handle case where we now have 0 panelists, but previously had panelists; this could be a server error so we need to be careful
			if (empty($panelist_ids)) {
				if (!defined('IS_DEV_INSTANCE') || IS_DEV_INSTANCE === false) {
					if (defined('WORKER_NAME')) {
						$identifier = WORKER_NAME; 
					}
					elseif (defined('SERVER_HOSTNAME')) {
						$identifier = SERVER_HOSTNAME;
					}
					else {
						$identifier = '';
					}
					$http = new HttpSocket(array(
						'timeout' => '2',
						'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
					));
					$http->post($this->settings['slack.qe2.webhook'], json_encode(array(
						'text' => 'No matching panelists from QE2: #'.$project['Project']['id'].' <https://cp.mintvine.com/surveys/dashboard/'.$project['Project']['id'].'> ('.$identifier.')', 
						'link_names' => 1,
						'username' => 'bernard'
					)));
					$http->post($this->settings['slack.qe2.webhook'], json_encode(array(
						'text' => 'Original Query: '.$raw_query_json, 
						'link_names' => 1,
						'username' => 'bernard'
					)));
					$http->post($this->settings['slack.qe2.webhook'], json_encode(array(
						'text' => 'Executed Query: '.$query_json, 
						'link_names' => 1,
						'username' => 'bernard'
					)));
				}
				return false;
			}
			
			// filter these panelist IDs out here by active in last 30 days
			if (!empty($panelist_ids)) {
				$panelist_id_chunks = array_chunk($panelist_ids, 12000, false);
				$filtered_panelist_ids = array();
				$this->out('Chunked into '.count($panelist_id_chunks));
				foreach ($panelist_id_chunks as $chunked_ids) {
					$users = $this->User->find('list', array(
						'fields' => array('User.id', 'User.id'),
						'conditions' => array(
							'User.id' => $chunked_ids,
							'User.hellbanned' => false,
							'User.last_touched >=' => date(DB_DATETIME, strtotime('-30 days')),
							'User.deleted_on' => null,
						),
						'recursive' => -1
					));
					$filtered_panelist_ids = array_merge($filtered_panelist_ids, $users); 
				}
				$panelist_ids = $filtered_panelist_ids;
			}
			$invite_count = $qe2_count = count($panelist_ids);
			
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
					'QualificationUser.qualification_id' => $parent_qualification_id
				)
			));
			$this->lecho('Existing panelists in project: '.count($survey_users).'/'.count($qualification_users), $log_file, $log_key);
		
			// todo: delete qualification_users too
			// if this is an update of an existing qualification, and it's been changed, we need to resend invitations
			if ($qualification_hash_option) {
				// these panelists no longer are in the query
				$missing_diff = array_diff($survey_users, $panelist_ids); 
				if (!empty($missing_diff)) {
					// determine which users already accessed this project, then resend invitations for all other panelists
					$survey_user_visits_userids = $this->SurveyUserVisit->find('list', array(
						'fields' => array('SurveyUserVisit.user_id'),
						'conditions' => array(
							'SurveyUserVisit.survey_id' => $project['Project']['id']
						)
					));
				
					// these panelists have no records of going into the project; we can safely remove their invitations
					$unclicked_diff = array_diff($missing_diff, $survey_user_visits_userids); 
					$this->lecho('Deleting previously invited panelists ('.count($unclicked_diff).') ', $log_file, $log_key);
				
					if (!empty($unclicked_diff)) {
						$lookup_survey_users = array_flip($survey_users);
						$lookup_qualification_users = array_flip($qualification_users);
						foreach ($unclicked_diff as $user_id) {
							$this->SurveyUser->delete($lookup_survey_users[$user_id]);
							$this->QualificationUser->delete($lookup_qualification_users[$user_id]);
						}
					
						// reload the survey users list
						$survey_users = $this->SurveyUser->find('list', array(
							'fields' => array('SurveyUser.id', 'SurveyUser.user_id'),
							'conditions' => array(
								'SurveyUser.survey_id' => $project['Project']['id']
							)
						));
					}
				}
			}
			
			// determine which panelists have not been invited yet
			$diff = array_diff($panelist_ids, $survey_users);
			$this->lecho('Total matched from QE2: '.$qe2_count, $log_file, $log_key);
			$this->lecho('Total already in survey: '.count($survey_users), $log_file, $log_key);
			$this->lecho('Total diff: '.count($diff), $log_file, $log_key);
			
			// these data sets can quickly get large; break it into chunks of 500 to make it a bit easier on the db
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
							'qualification_id' => $parent_qualification_id,
							'user_id' => $panelist_id,
							'award' => $project['Project']['award']
						)));
					}
				}
			}
			
			// update the total count on this qualification
			$this->set_qualification_invite_count($parent_qualification_id, $qe2_count, $invite_count);
		}
		
		// need to create subquotas
		/* 
		[SurveyQuotas] => Array(
			[0] => Array(
				[quota_type] => layered
				[quota_id] => 723e10d4-d366-49a2-9742-e67c43dfcbcf
				[associated_qualifications_and_conditions] => Array(
					[0] => Array(
						[qualification_name] => age
						[qualification_code] => 212
						[conditions] => Array(
							[0] => Array(
								[from] => 50
								[to] => 54
							)
						)
					)
				)
				[supplier_completes] => Array(
					[needed] => 13
					[achieved] => 0
					[remaining] => 13
					[open_quantity] => 13
				)
				[last_complete_date] =>
				[quota_conversion] => 0
			)
		)
		*/
		if (!empty($survey_quotas)) {
			$all_quota_ids = array();
			foreach ($survey_quotas as $survey_quota) {
				if ($survey_quota['quota_type'] == 'nested') {  // todo: Need to handle this when they will implement it
					$this->lecho('Nested quota found for #S'. $this->params['spectrum_survey_id'], $log_file, $log_key);
					continue;
				}
				
				$query_body_for_quota = $query_body;
				$all_quota_ids[] = $survey_quota['quota_id'];
				
				$query_params = array(); // to be sent to query engine
				$query_match = true;
				foreach ($survey_quota['associated_qualifications_and_conditions'] as $question) {
					if (!SpectrumMappings::is_mapped($question['qualification_code'])) {
						$query_match = false;
						$this->lecho('Qualification is out of our mappings "'.$question['qualification_name'].'" => "'.$question['qualification_code'].'" found in #S' . $this->params['spectrum_survey_id'], $log_file, $log_key);
						continue;
					}
					// $query_params - Call by reference
					$this->Spectrum->mappings($query_params, $question);
				}
				
				foreach ($query_params as $question => $options) {
					// overwrite the old values...
					if (isset($query_body['qualifications'][$question]) || in_array($question, $skipped_parent_conditions)) {
						$query_body_for_quota['qualifications'][$question] = $options; 
					}
				}
				$query_json = json_encode($query_body_for_quota);
				$query_hash = md5($query_json);
				
				$this->Qualification->bindModel(array('hasOne' => array('QualificationStatistic')));
				$child_qualification = $this->Qualification->find('first', array(
					'conditions' => array(
						'Qualification.parent_id' => $parent_qualification_id,
						'Qualification.partner_qualification_id' =>  $survey_quota['quota_id'],
						'Qualification.deleted is null'
					)
				));
				
				// two cases where we create a qualification: new one is different, or doesn't exist
				if (!$child_qualification || $query_hash != $child_qualification['Qualification']['query_hash']) {
					if ($child_qualification && $query_hash != $child_qualification['Qualification']['query_hash']) {
						$this->Qualification->delete($child_qualification['Qualification']['id']);
					}
					
					$existing_completes = 0;
					if ($child_qualification) {
						$existing_completes = $child_qualification['Qualification']['completes'];
					}
					$qualificationSource = $this->Qualification->getDataSource();
					$qualificationSource->begin();
					$this->Qualification->create();
					$this->Qualification->save(array('Qualification' => array(
						'project_id' => $project['Project']['id'],
						'parent_id' => $parent_qualification_id,
						'partner_qualification_id' => $survey_quota['quota_id'],
						'name' => '#S'.$project['Project']['mask'].' - '.$survey_quota['quota_id'],
						'query_hash' => $query_hash,
						'query_json' => $query_json,
						'quota' => $survey_quota['supplier_completes']['open_quantity'] + $existing_completes,
						'cpi' => $payouts['client_rate'],
						'award' => $payouts['award'],
					)));
					$child_qualification_id = $this->Qualification->getInsertId();
					$qualificationSource->commit();
					$created_qualification_ids[] = $child_qualification_id;

					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $project['Project']['id'],
						'type' => 'qualification.created',
						'description' => 'Qualification #'.$child_qualification_id.' created.',
					)));

					$this->lecho('Created child qualification '.$child_qualification_id, $log_file, $log_key);
				}
				
				// CPI change - quota changes are done separately because they happen so frequently
				if ($child_qualification && $child_qualification['Qualification']['cpi'] != $payouts['client_rate']) {
					$original_qualification_data = array(
						'id' => $child_qualification['Qualification']['id'],
						'cpi' => $child_qualification['Qualification']['cpi'],
						'award' => $child_qualification['Qualification']['award']
					);
					$qualification_data = array(
						'id' => $child_qualification['Qualification']['id'],
						'cpi' => $payouts['client_rate'],
						'award' => $payouts['award']
					);
					$this->Qualification->create();
					$this->Qualification->save(array('Qualification' => $qualification_data), true, array('cpi', 'award'));

					$diff = array_diff($qualification_data, $original_qualification_data);
					if (count($diff) > 0) {
						$log = '';
						foreach ($diff as $key => $val) {
							$log .= $key . ' was updated from "' . $original_qualification_data[$key] . '" to "' . $val . '", ';
						}
						$log = substr($log, 0, -2);
						$this->ProjectLog->create();
						$this->ProjectLog->save(array('ProjectLog' => array(
							'project_id' => $project['Project']['id'],
							'type' => 'qualification.updated',
							'description' => 'Qualification #' . $child_qualification['Qualification']['id'] . ' updated: ' . $log
						)));
					}
				}
			}
			
			// extract all quotas and delete the ones that no longer exist
			$child_qualifications = $this->Qualification->find('list', array(
				'fields' => array('Qualification.id', 'Qualification.partner_qualification_id'),
				'conditions' => array(
					'Qualification.parent_id' => $parent_qualification_id,
					'Qualification.deleted is null'
				),
				'recursive' => -1
			));
			$missing_child_qualifications = array_diff($child_qualifications, $all_quota_ids); 
			if (!empty($missing_child_qualifications)) {
				$child_qualifications = array_flip($child_qualifications); 
				foreach ($missing_child_qualifications as $missing_qualification_id) {
					$this->Qualification->delete($child_qualifications[$missing_qualification_id]); 
				}
			}
		}
		
		if (count($diff) > 0) {
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'invited.web',
				'description' => 'Added survey invitations for '.count($diff).' panelists',
				'internal_description' => count($diff)
			)));
		}
		
		$this->lecho('Completed qe2 qualifications (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
		
		// activate all the created qualifications
		if (!empty($created_qualification_ids)) {
			foreach ($created_qualification_ids as $created_qualification_id) {
				$this->Qualification->create();
				$this->Qualification->save(array('Qualification' => array(
					'id' => $created_qualification_id,
					'active' => true
				)), true, array('active'));

				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'qualification.open',
					'description' => 'Qualification #'.$created_qualification_id.' opened.',
				)));
			}
		}
		
		if (!$project['Project']['temp_qualifications']) {
			// set the qualifications flag on the project
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'temp_qualifications' => true
			)), true, array('temp_qualifications'));
		}
		
		// for clients that are not to be autolaunched, leave them in staging + inactive
		if (!$project['Client']['do_not_autolaunch'] && ($project['Project']['status'] != PROJECT_STATUS_STAGING || $project['Project']['active'])) {
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'status' => PROJECT_STATUS_STAGING,
				'active' => false
			)), true, array('status' ,'active'));
			
			// write a value that stores the last invite time
			$project_option = $this->ProjectOption->find('first', array(
				'conditions' => array(
					'ProjectOption.project_id' => $project['Project']['id'],
					'ProjectOption.name' => 'spectrum.nolaunch.reason'
				)
			));
			if (!$project_option) {
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'name' => 'spectrum.nolaunch.reason',
					'value' => 'client.skipped',
					'project_id' => $project['Project']['id']
				)));
			}
		}
		return true;
	}
	
	private function set_qualification_invite_count($qualification_id, $qe2_count, $invite_count) {
		// update the total count on this qualification
		$this->QualificationStatistic->bindModel(array(
			'belongsTo' => array('Qualification')
		));
		$qualification_statistic = $this->QualificationStatistic->find('first', array(
			'fields' => array('Qualification.id', 'Qualification.total', 'Qualification.project_id', 'QualificationStatistic.id', 'QualificationStatistic.invited'),
			'conditions' => array(
				'QualificationStatistic.qualification_id' => $qualification_id
			)
		));
		if ($qualification_statistic && $qualification_statistic['Qualification']['total'] != $qe2_count) {
			$this->Qualification->create();
			$this->Qualification->save(array('Qualification' => array(
				'id' => $qualification_statistic['Qualification']['id'],
				'total' => $qe2_count
			)), true, array('total'));

			$log = 'total was updated from "' . $qualification_statistic['Qualification']['total'] . '" to "' . $qe2_count . '".';
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $qualification_statistic['Qualification']['project_id'],
				'type' => 'qualification.updated',
				'description' => 'Qualification #'.$qualification_id.' updated: ' . $log
			)));
		}
		
		if ($qualification_statistic && $qualification_statistic['QualificationStatistic']['invited'] != $invite_count) {
			$this->QualificationStatistic->create();
			$this->QualificationStatistic->save(array('QualificationStatistic' => array(
				'id' => $qualification_statistic['QualificationStatistic']['id'],
				'invited' => $invite_count
			)), true, array('invited'));
		}
	}
	
	public function set_security_group($spectrum_project, $spectrum_survey) {
		// update the security group values
		$survey_group_exists = false;
		$api_group_spectrum_survey_ids = array();
		if (!empty($spectrum_survey['survey_grouping']['survey_ids'])) {
			$api_group_spectrum_survey_ids = $spectrum_survey['survey_grouping']['survey_ids'];
			$survey_group_exists = true; 
		}
		
		if ($survey_group_exists != $spectrum_project['SpectrumProject']['survey_group_exists']) {
			$this->SpectrumProject->create();
			$this->SpectrumProject->save(array('SpectrumProject' => array(
				'id' => $spectrum_project['SpectrumProject']['id'],
				'survey_group_exists' => $survey_group_exists
			)), true, array('survey_group_exists'));
		}
		
		$db_group_spectrum_survey_ids = $this->SpectrumSurveyGroup->find('list', array(
			'fields' => array('SpectrumSurveyGroup.id', 'SpectrumSurveyGroup.group_spectrum_survey_id'),
			'conditions' => array(
				'SpectrumSurveyGroup.project_id' => $spectrum_project['SpectrumProject']['project_id'],
				'SpectrumSurveyGroup.deleted is null'
			)
		));
		
		if ($survey_group_exists) {
			$missing_from_db = array_diff($api_group_spectrum_survey_ids, $db_group_spectrum_survey_ids); 
			$missing_from_api = array_diff($db_group_spectrum_survey_ids, $api_group_spectrum_survey_ids); 
		}
		else {
			$missing_from_db = array();
			$missing_from_api = $db_group_spectrum_survey_ids;
		}
		
		if (!empty($missing_from_db)) {
			$spectrum_projects = $this->SpectrumProject->find('list', array(
				'fields' => array('SpectrumProject.spectrum_survey_id', 'SpectrumProject.project_id'),
				'conditions' => array(
					'SpectrumProject.spectrum_survey_id' => $missing_from_db
				)
			));
			foreach ($missing_from_db as $group_spectrum_survey_id) {
				$this->SpectrumSurveyGroup->create();
				$this->SpectrumSurveyGroup->save(array('SpectrumSurveyGroup' => array(
					'project_id' => $spectrum_project['SpectrumProject']['project_id'],
					'spectrum_survey_id' => $spectrum_project['SpectrumProject']['spectrum_survey_id'],
					'group_spectrum_survey_id' => $group_spectrum_survey_id,
					'group_project_id' => isset($spectrum_projects[$group_spectrum_survey_id]) ? $spectrum_projects[$group_spectrum_survey_id] : ''
				)));
			}
		}
		
		if (!empty($missing_from_api)) {
			foreach ($missing_from_api as $group_spectrum_survey_id) {
				$key = array_search($group_spectrum_survey_id, $db_group_spectrum_survey_ids); 
				if ($key !== false) {
					$this->SpectrumSurveyGroup->create();
					$this->SpectrumSurveyGroup->save(array('SpectrumSurveyGroup' => array(
						'id' => $key,
						'deleted' => date(DB_DATETIME),
						'modified' => false
					)), true, array('deleted')); 
				}
			}
		}
	}
}