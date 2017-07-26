<?php
App::uses('Shell', 'Console');
App::import('Lib', 'Utilities');
App::import('Lib', 'MintVine');
App::import('Lib', 'QueryEngine');
App::uses('HttpSocket', 'Network/Http');

class Points2shopShell extends AppShell {
	var $uses = array('Setting', 'User', 'Group', 'Client', 'Project', 'Partner', 'ProjectLog', 'Question', 'QuestionText', 'Answer', 'AnswerText', 'Points2shopQueue', 'Query', 'Qualification', 'QualificationStatistic', 'QualificationUser', 'SurveyUser', 'SurveyVisitCache', 'Points2shopLog', 'ProjectOption', 'LucidZip');
	public $tasks = array('Points2shop');

	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser->addOption('points2shop_project_id', array(
			'help' => 'Points2Shop Survey ID',
			'boolean' => false
		));
		$parser->addOption('project_id', array(
			'help' => 'MintVine Project ID',
			'boolean' => false
		));
		return $parser;
	}

	public function debug() {
		if (!isset($this->args[0])) {
			$this->out('Please define a country'); 
			return;
		}
		$country = $this->args[0];
		$limit = 100; 
		if (isset($this->args[1])) {
			$limit = $this->args[1]; 
		}
		
		$required_settings = array(
			'points2shop.secret.api',
			'points2shop.host',
			'points2shop.active',
			'sqs.points2shop.queue',
			'sqs.access.secret',
			'sqs.access.key'
		); 
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => $required_settings,
				'Setting.deleted' => false
			),
			'recursive' => -1
		));

		if (count($settings) < count($required_settings)) {
			$this->out('Missing required settings'); 
			return;
		}
		
		$HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));

		$points2shop_header = array(
			'header' => array(
				'X-YourSurveys-Api-Key' => $settings['points2shop.secret.api']
			),
		);

		$request_data = array(
			'country' => $country == 'GB' ? 'UK' : $country,
			'limit' => 10000,
		);
		
		$response = $HttpSocket->get($settings['points2shop.host'] . '/suppliers_api/surveys', $request_data, $points2shop_header);
		$response = json_decode($response, true);
		$p2s_projects = $response['surveys'];
		print_r($p2s_projects);		
	}
	
	// this function polls the p2s api and determines whether we need to create or update a survey and passes that to SQS
	// see lucid's process() as an example
	// closing projects can be done here
	public function process_surveys() {
		$required_settings = array(
			'points2shop.secret.api',
			'points2shop.host',
			'points2shop.active',
			'sqs.points2shop.queue',
			'sqs.access.secret',
			'sqs.access.key'
		); 
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => $required_settings,
				'Setting.deleted' => false
			),
			'recursive' => -1
		));

		if (count($settings) < count($required_settings)) {
			$this->out('Missing required settings'); 
			return;
		}

		// the default case should be off
		if ($settings['points2shop.active'] != 'true') {
			$this->out('Points2shop integration disabled');
			return;
		}
		
		ini_set('memory_limit', '1024M');
		$log_file = 'points2shop.process';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting process', $log_file, $log_key);

		$HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));

		$points2shop_header = array(
			'header' => array(
				'X-YourSurveys-Api-Key' => $settings['points2shop.secret.api']
			),
		);

		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'points2shop', // this is the new group
			),
			'recursive' => -1,
		));

		App::import('Vendor', 'sqs');
		$sqs = new SQS($settings['sqs.access.key'], $settings['sqs.access.secret']);
		$i = 0;
		$p2s_project_ids = $sqs_batch = array();
		$supported_countries = array_keys(unserialize(SUPPORTED_COUNTRIES));
		foreach ($supported_countries as $country) {
			$request_data = array(
				'country' => $country == 'GB' ? 'UK' : $country,
				'limit' => 10000,
			);
			
			$response = $HttpSocket->get($settings['points2shop.host'] . 'suppliers_api/surveys', $request_data, $points2shop_header);
			$response = json_decode($response, true);
			$p2s_projects = $response['surveys'];

			$this->lecho('Processing '.count($p2s_projects).' projects, country: ' . $country, $log_file, $log_key);
			foreach ($p2s_projects as $p2s_project) {
				$i++;
				$p2s_project_ids[] = $p2s_project['project_id'];

				// save live inventory
				$this->Points2shop->save_log($p2s_project, $log_file, $log_key);

				$this->Project->getDatasource()->reconnect();
				$project = $this->Project->find('first', array(
					'conditions' => array(
						'Project.mask' => $p2s_project['project_id'],
						'Project.group_id' => $group['Group']['id']
					),
					'recursive' => -1,
				));

				if (!$project) {
					$command = 'points2shop create --points2shop_project_id=' . $p2s_project['project_id'];
					$points2shopQueueSource = $this->Points2shopQueue->getDataSource();
					$points2shopQueueSource->begin();
					$this->Points2shopQueue->create();
					$save = $this->Points2shopQueue->save(array('Points2shopQueue' => array(
						'points2shop_survey_id' => $p2s_project['project_id'],
						'command' => $command,
						'survey_id' => null
					)));
					if ($save) {
						$p2s_queue_id = $this->Points2shopQueue->getInsertId();
						$points2shopQueueSource->commit();
						$sqs_batch[$p2s_queue_id] = $command;
						$this->lecho($i . ': Create #F' . $p2s_project['project_id'], $log_file, $log_key);
					}
					else {
						$points2shopQueueSource->commit();
						$this->lecho($i.': Creation of #F' . $p2s_project['project_id'] . ' skipped as it already exists in queue.', $log_file, $log_key);
					}
				} 
				else {
					$command = 'points2shop update --points2shop_project_id=' . $p2s_project['project_id'];
					$points2shopQueueSource = $this->Points2shopQueue->getDataSource();

					$existing_queue = $this->Points2shopQueue->find('first', array(
						'conditions' => array(
							'Points2shopQueue.points2shop_survey_id' => $p2s_project['project_id'],
							'Points2shopQueue.command' => $command
						),
						'order' => 'Points2shopQueue.id DESC'
					));
					// if the queue has been updated within the past 30 minutes, skip it
					if (!empty($existing_queue) && (strtotime('-30 minutes ago') <= strtotime($existing_queue['Points2shopQueue']['created']) || is_null($existing_queue['Points2shopQueue']['executed']))) {
						continue;
					}
					
					$points2shopQueueSource->begin();
					$this->Points2shopQueue->create();
					$save = $this->Points2shopQueue->save(array('Points2shopQueue' => array(
						'points2shop_survey_id' => $p2s_project['project_id'],
						'command' => $command,
						'survey_id' => null
					)));
					if ($save) {
						$p2s_queue_id = $this->Points2shopQueue->getInsertId();
						$points2shopQueueSource->commit();
						$sqs_batch[$p2s_queue_id] = $command;
						$this->lecho($i.': Update #F' . $p2s_project['project_id'], $log_file, $log_key);
					}
					else {
						$points2shopQueueSource->commit();
						$this->lecho($i . ': Update of #F' . $p2s_project['project_id'] . ' skipped as it already exists in queue.', $log_file, $log_key);
					}
				}
			}
		}

		$this->lecho('Found ' . count($sqs_batch) . ' SQS items to send', $log_file, $log_key);

		// process all of the amazon queues
		if (isset($sqs_batch) && !empty($sqs_batch)) {
			$i = 0;
			$chunks = array_chunk($sqs_batch, 10, true);
			if (!empty($chunks)) {
				foreach ($chunks as $batch) {
					$response = $sqs->sendMessageBatch($settings['sqs.points2shop.queue'], $batch);
					CakeLog::write('points2shop.sqs', 'WRITING----------');
					CakeLog::write('points2shop.sqs', print_r($batch, true));
					CakeLog::write('points2shop.sqs', print_r($response, true));
					CakeLog::write('points2shop.sqs', '-----------------');

					if (!empty($response)) {
						foreach ($response as $p2s_queue_id => $message_id) {
							$this->Points2shopQueue->create();
							$this->Points2shopQueue->save(array('Points2shopQueue' => array(
								'id' => $p2s_queue_id,
								'amazon_queue_id' => $message_id
							)), true, array('amazon_queue_id'));
							$i++;
						}
					}
				}
			}
		}
		$this->lecho('Sent '. $i .' SQS items', $log_file, $log_key);

		// close all projects that are on longer found in p2s API
		$this->Project->getDatasource()->reconnect();
		$mv_projects = $this->Project->find('all', array(
			'fields' => array(
				'Project.id', 'Project.status', 'Project.ended', 'Project.mask', 'Project.active'
			),
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'Project.status' => array(PROJECT_STATUS_OPEN, PROJECT_STATUS_STAGING)
			),
			'recursive' => -1
		));

		if ($mv_projects) {
			foreach ($mv_projects as $mv_project) {
				if (in_array($mv_project['Project']['mask'], $p2s_project_ids)) {
					continue;
				}
				// Pause project if it's open
				if ($mv_project['Project']['active']) {
					$this->Project->create();
					$this->Project->save(array('Project' => array(
						'id' => $mv_project['Project']['id'],
						'active' => false,
						'ended' => date(DB_DATETIME)
					)), true, array('active', 'ended'));

					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $mv_project['Project']['id'],
						'type' => 'status.paused.points2shop',
						'description' => 'Closed by P2S - not found in allocation'
					)));
					$this->lecho('Paused #F' . $mv_project['Project']['mask'] . ' - no longer in p2s API', $log_file, $log_key);
				}
				elseif ($mv_project['Project']['status'] != PROJECT_STATUS_CLOSED) {
					// Close project if it's paused
					$this->Project->create();
					$this->Project->save(array('Project' => array(
						'id' => $mv_project['Project']['id'],
						'status' => PROJECT_STATUS_CLOSED,
						'ended' => date(DB_DATETIME)
					)), true, array('status', 'ended'));

					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $mv_project['Project']['id'],
						'type' => 'status.closed.points2shop',
						'internal_description' => 'closed.wall',
						'description' => 'Closed by P2S - not found in allocation'
					)));
					Utils::save_margin($mv_project['Project']['id']);
					$this->lecho('Closed #F' . $mv_project['Project']['mask'] . ' - no longer in p2s API', $log_file, $log_key);
				}				
			}
		}
		$this->lecho('Completed process (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);			
	}
	
	// set to run every minute, this worker actually creates/updates the projects by polling SQS and getting jobs to execute (see lucidshell::worker()
	public function worker() {
		$required_settings = array(
			'points2shop.active',
			'sqs.access.key', 
			'sqs.access.secret', 
			'sqs.points2shop.queue'
		); 
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => $required_settings,
				'Setting.deleted' => false
			)
		));

		if (count($settings) < count($required_settings)) {
			$this->out('Missing required settings');
			return;
		}

		if ($settings['points2shop.active'] != 'true') {
			$this->out('Points2shop integration disabled');
			return;
		}

		$time_to_run = 12;
		ini_set('memory_limit', '1024M');
		$log_file = 'points2shop.worker';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting worker', $log_file, $log_key);

		App::import('Vendor', 'sqs');
		$i = 0;
		$sqs = new SQS($settings['sqs.access.key'], $settings['sqs.access.secret']);
		while (true) {
			$results = $sqs->receiveMessage($settings['sqs.points2shop.queue']);
			if (!empty($results['Messages'])) {
				$command = $results['Messages'][0]['Body'];
				$this->lecho('Starting '.$command, $log_file, $log_key);
				$query = ROOT . '/app/Console/cake ' . $command;
				CakeLog::write('query_commands', $query);
				
				exec($query, $output);
				$i++;

				$this->Points2shopQueue->getDataSource()->reconnect();
				$points2shop_queue = $this->Points2shopQueue->find('first', array(
					'conditions' => array(
						'Points2shopQueue.command' => $command,
						'Points2shopQueue.executed is null'
					)
				));
				if ($points2shop_queue) {
					$this->Points2shopQueue->create();
					$this->Points2shopQueue->save(array('Points2shopQueue' => array(
						'id' => $points2shop_queue['Points2shopQueue']['id'],
						'worker' => defined('WORKER_NAME') ? WORKER_NAME: null,
						'executed' => date(DB_DATETIME)
					)), true, array('executed', 'worker'));
					$points2shop_queue_id = $points2shop_queue['Points2shopQueue']['id'];
				}
				else {
					// gotta parse out the invite
					$points2shop_survey_id = null;
					if (strpos($command, 'points2shop create --points2shop_project_id=') !== false) {
						$points2shop_survey_id = str_replace('points2shop create --points2shop_project_id=', '', $command);
					}
					elseif (strpos($command, 'points2shop update --points2shop_project_id=') !== false) {
						$points2shop_survey_id = str_replace('points2shop update --points2shop_project_id=', '', $command);
					}
					if (!empty($points2shop_survey_id)) {
						$points2shopQueueSource = $this->Points2shopQueue->getDataSource();
						$points2shopQueueSource->begin();
						$this->Points2shopQueue->create();
						$save = $this->Points2shopQueue->save(array('Points2shopQueue' => array(
							'amazon_queue_id' => $results['Messages'][0]['MessageId'],
							'points2shop_survey_id' => $points2shop_survey_id,
							'command' => $command,
							'survey_id' => null,
							'executed' => date(DB_DATETIME)
						)));
						if ($save) {
							$points2shop_queue_id = $this->Points2shopQueue->getInsertId();
						}
						$points2shopQueueSource->commit();
					}
				}
				$sqs->deleteMessage($settings['sqs.points2shop.queue'], $results['Messages'][0]['ReceiptHandle']);
				if (isset($points2shop_queue_id)) {
					$this->lecho('Processed '.$points2shop_queue_id, $log_file, $log_key);
				}
				else {
					$this->lecho('Processed', $log_file, $log_key);
				}

				$time_diff = microtime(true) - $time_start;
				if ($time_diff > (60 * $time_to_run)) {
					$this->lecho('Completed worker (timeout) '.$i.' items (Execution time: '.($time_diff).')', $log_file, $log_key);
					return false;
				}
			}
			if (empty($results['Messages'])) {
				$this->lecho('Completed worker '.$i.' items (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
				break;
			}
		}		
	}
	
	// creates the project into staging first
	public function create() {
		$log_file = 'points2shop.create';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);

		if (!$this->load_settings($log_file, $log_key, $time_start)) {
			$this->lecho('Settings not found', $log_file, $log_key);
			return false;
		}

		if ($this->settings['points2shop.active'] != 'true') {
			return false;
		}

		if (!isset($this->params['points2shop_project_id'])) {
			$this->lecho('FAILED: You are missing points2shop_project_id', $log_file, $log_key);
			$this->lecho('Completed create (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		$this->lecho('Starting create ' . $this->params['points2shop_project_id'], $log_file, $log_key);

		$points2shop_survey = $this->Project->find('first', array(
			'conditions' => array(
				'Project.mask' => $this->params['points2shop_project_id'],
				'Project.group_id' => $this->points2shop_group['Group']['id'],
			)
		));
		if ($points2shop_survey) {
			$this->lecho('FAILED: #' . $this->params['points2shop_project_id'] . ' has already been created.', $log_file, $log_key);
			$this->lecho('Completed create (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}

		$points2shop_survey = $this->get_points2shop_project();
		
		if (empty($points2shop_survey)) {
			$this->lecho('That Points2Shop project does not exist anymore.', $log_file, $log_key);
			return false;
		}

		$payouts = $this->Points2shop->payout($points2shop_survey);
		$overall_quota = $points2shop_survey['remaining_completes'];
		$client_link = $this->Points2shop->client_link($points2shop_survey);

		$bid_ir = $this->Points2shop->bid_ir($points2shop_survey, $this->settings['points2shop.default.ir']);
		$loi = $this->Points2shop->loi($points2shop_survey, $this->settings['points2shop.default.loi']);
		$min_time = round($loi / 4);
		if ($min_time <= 1) {
			$min_time = null;
		}

		$save = false;
		$projectSource = $this->Project->getDataSource();
		$projectSource->begin();
		$this->Project->create();
		$project_data = array('Project' => array(
			'prj_name' => isset($points2shop_survey['name']) ? $points2shop_survey['name'] : 'Survey for you!',
			'mask' => $points2shop_survey['project_id'],
			'client_id' => $this->points2shop_client['Client']['id'],
			'date_created' => date(DB_DATETIME),
			'bid_ir' => $bid_ir,
			'client_rate' => $payouts['client_rate'],
			'partner_rate' => $payouts['partner_rate'],
			'user_payout' => $payouts['partner_rate'],
			'quota' => $overall_quota,
			'est_length' => $loi,
			'minimum_time' => $min_time,
			'group_id' => $this->points2shop_group['Group']['id'],
			'status' => PROJECT_STATUS_STAGING,
			'client_project_id' => $points2shop_survey['project_id'],
			'singleuse' => true,
			'touched' => date(DB_DATETIME),
			'country' => $points2shop_survey['country'],
			'survey_name' => 'Survey for you!',
			'award' => $payouts['award'],
			'nq_award' => '0', 
			'active' => false,
			'dedupe' => true,
			'client_survey_link' => $client_link,
			'description' => 'Survey for you!',
			'temp_qualifications' => false, // skip qe2 checks; treat this as an offerwall integration
 		));
		if ($this->Project->save($project_data)) {
			$project_id = $this->Project->getInsertId();
			MintVine::project_quota_statistics('points2shop', $overall_quota, $project_id);
			$projectSource->commit();

			// save points2shop project data as json object
			$this->Points2shop->save_points2shop_project($project_id, $points2shop_survey, $log_file, $log_key);

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
			$this->lecho('[ERROR] Failed saving survey due to internal error', $log_file, $log_key);
			$this->lecho(print_r($this->Project->validationErrors, true), $log_file, $log_key);
		}
		$projectSource->commit();

		if ($save) {
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project_id,
				'type' => 'created',
				'description' => ''
			)));

			$this->lecho('[SUCCESS] #F' . $points2shop_survey['project_id'] . ' created successfully (#' . $project_id . ')', $log_file, $log_key);
			// run qualifications on this project
			$this->params['points2shop_project_id'] = $points2shop_survey['project_id'];
			$this->qualifications($points2shop_survey);

			// launch this project
			$project = $this->Project->find('first', array(
				'conditions' => array(
					'Project.id' => $project_id
				)
			));
			
			$this->launch_project($project, $points2shop_survey, $log_file, $log_key, true);
		}
		$this->lecho('Completed create (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);		
	}

	public function qualifications($p2s_project = null) {
		$required_settings = array(
			'points2shop.active',
			'qqq.active'
		);
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => $required_settings,
				'Setting.deleted' => false
			),
			'recursive' => -1
		));

		if (count($settings) < count($required_settings)) {
			return false;
		}

		if ($settings['points2shop.active'] != 'true') {
			return false;
		}
		ini_set('memory_limit', '1024M');
		$log_file = 'points2shop.qualifications';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting qualifications', $log_file, $log_key);
		if (!isset($this->params['points2shop_project_id'])) {
			$this->lecho('FAILED: You need to define the points2shop_project_id', $log_file, $log_key);
			$this->lecho('Completed qualifications (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}

		$project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.mask' => $this->params['points2shop_project_id'],
				'Project.group_id' => $this->points2shop_group['Group']['id']
			)
		));
		if (!$project || empty($project['Project']['country'])) {
			$this->lecho('FAILED: Could not locate the MV project that is associated with #F'.$this->params['points2shop_project_id'], $log_file, $log_key);
			$this->lecho('Completed qualifications (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}

		if (!$p2s_project) {
			$p2s_project = $this->get_points2shop_project();
		}
		if (isset($settings['qqq.active']) && $settings['qqq.active'] == 'true') {
			$query_count = $this->Query->find('count', array(
				'conditions' => array(
					'Query.survey_id' => $project['Project']['id']
				),
				'recursive' => -1
			));
			if ($query_count == 0) {
				if (isset($p2s_project['quotas']) && count($p2s_project['quotas']) > 0) {
					$this->Project->create();
					$this->Project->save(array('Project' => array(
						'id' => $project['Project']['id'],
						'subquals' => true
					)), true, array('subquals'));
				}
				else {
					$this->Project->create();
					$this->Project->save(array('Project' => array(
						'id' => $project['Project']['id'],
						'subquals' => false
					)), true, array('subquals'));
				}
				$this->qqq();
				return true;
			}
		}
	}

	public function qqq() {
		if (defined('IS_DEV_INSTANCE') && IS_DEV_INSTANCE === true) {
			App::import('Lib', 'MockQueryEngine');
		}
		ini_set('memory_limit', '1024M');
		$log_file = 'points2shop.qe2';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		if (!$this->load_settings($log_file, $log_key, $time_start)) {
			$this->lecho('Settings not found', $log_file, $log_key);
			return false;
		}

		$this->lecho('Starting qualifications', $log_file, $log_key);
		if (!isset($this->params['points2shop_project_id'])) {
			$this->lecho('FAILED: You need to define the points2shop_project_id', $log_file, $log_key);
			$this->lecho('Completed qualifications (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}

		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'points2shop',
			),
			'recursive' => -1,
		));

		$project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.mask' => $this->params['points2shop_project_id'],
				'Project.group_id' => $group['Group']['id']
			)
		));

		if (!$project || empty($project['Project']['country'])) {
			$this->lecho('FAILED: Could not locate the MV project that is associated with #F'.$this->params['points2shop_project_id'], $log_file, $log_key);
			$this->lecho('Completed qualifications (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}

		// load data off of the points2shop api
		$p2s_project = $this->get_points2shop_project();
		$total_qualifications_list = $this->Points2shop->qualifications($p2s_project);
		if (empty($total_qualifications_list)) {
			 if ($project['Project']['status'] != PROJECT_STATUS_CLOSED) {
			 	// close project if no quals provided
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'status' => PROJECT_STATUS_CLOSED,
					'ended' => date(DB_DATETIME)
				)), true, array('status', 'ended'));

				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'status.closed.points2shop',
					'internal_description' => 'closed.wall',
					'description' => 'Project closed, no qualifications found'
				)));				
				
				$this->lecho('Project closed due to no qualifications', $log_file, $log_key);
			}

			$this->lecho('No qualifications found', $log_file, $log_key);
			return false;
		}
		// if quals exist and the project is marked as public, unmark it
		elseif ($project['Project']['public']) {
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'public' => false,
			)), true, array('public'));
			$this->lecho('#F'.$this->params['points2shop_project_id']. ' qualifications found, and the project is no more public.', $log_file, $log_key);
		}

		$query_body = array(
			'partner' => 'points2shop',
			'qualifications' => array(
				'country' => array(!empty($project['Project']['country']) ? $project['Project']['country']: 'US')
			)
		);

		if (!empty($total_qualifications_list)) {
			foreach ($total_qualifications_list as $question => $answers) {
				$query_body['qualifications'][$question] = $answers;
			}
		}
		asort($query_body['qualifications']);
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
		$create_parent_qualification = false;
		$previous_qualification_id = null;
		$payouts = $this->Points2shop->payout($p2s_project);
		$created_qualification_ids = array();
		if (!$total_qualification) {
			$create_parent_qualification = true;
		}
		elseif ($total_qualification['Qualification']['query_hash'] != $query_hash) {
			$previous_qualification_id = $total_qualification['Qualification']['id'];
			$create_parent_qualification = true;
			$this->Qualification->create();
			$this->Qualification->save(array('Qualification' => array(
				'id' => $total_qualification['Qualification']['id'],
				'deleted' => date(DB_DATETIME),
			)), true, array('deleted'));
			$parent_qualification_id = $total_qualification['Qualification']['id'];
			$this->lecho('Parent qualification deleted ('.$total_qualification['Qualification']['id'].')', $log_file, $log_key);

			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'qualification.deleted',
				'description' => 'Qualification #'.$parent_qualification_id.' deleted.',
			)));
		}
		else {
			$parent_qualification_id = $total_qualification['Qualification']['id'];
			$this->lecho('Qualification already exists ('.$parent_qualification_id.')', $log_file, $log_key);
		}

		if ($create_parent_qualification) {
			$qualificationSource = $this->Qualification->getDataSource();
			$qualificationSource->begin();
			$this->Qualification->create();
			$this->Qualification->save(array('Qualification' => array(
				'project_id' => $project['Project']['id'],
				'name' => $project['Project']['mask'],
				'query_hash' => $query_hash,
				'query_json' => $query_json,
				'raw_json' => $raw_query_json,
				'quota' => $p2s_project['remaining_completes'],
				'cpi' => $payouts['client_rate'],
				'award' => $payouts['award'],
				'active' => $this->settings['points2shop.live.invites'] == 'true'
			)));
			$parent_qualification_id = $this->Qualification->getInsertId();
			$qualificationSource->commit();

			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'qualification.created',
				'description' => 'Qualification #'.$parent_qualification_id.' created.',
			)));

			$this->lecho('Created qualification ('.$parent_qualification_id.')', $log_file, $log_key);
			$created_qualification_ids[] = $parent_qualification_id;

			// Update QualificationUsers to new qualification_id
			if (!is_null($previous_qualification_id)) {
				$qualification_users = $this->QualificationUser->find('list', array(
					'fields' => array('QualificationUser.id', 'QualificationUser.user_id'),
					'conditions' => array(
						'QualificationUser.qualification_id' => $previous_qualification_id,
						'QualificationUser.deleted' => false
					)
				));
				//Get panelists who qualify to new qualification
				if (defined('IS_DEV_INSTANCE') && IS_DEV_INSTANCE === true) {
					$panelist_ids = MockQueryEngine::parent_panelists(1);
				}
				else {
					if ($this->settings['points2shop.live.invites'] == 'true') {
						$panelist_ids = QueryEngine::qe2($this->settings, $query_json);
					}
					else {
						$panelist_ids = array();
					}
				}
				$updated_users = array();
				if ($qualification_users) {
					foreach ($qualification_users as $qualification_user_id => $user_id) {
						if (in_array($user_id, $panelist_ids)) {
							$updated_users[] = $user_id;
							$this->QualificationUser->create();
							$this->QualificationUser->save(array(
								'id' => $qualification_user_id,
								'qualification_id' => $parent_qualification_id,
							), true, array('qualification_id'));
						}
					}
				}
				if ($updated_users) {
					$this->lecho('New qualification_id updated to #'.$parent_qualification_id . ' for UserIDs (' . implode(',', $updated_users) . ')', $log_file, $log_key);	
				}				
			}
		}
		
		//child qualifications
		if (!empty($p2s_project['quotas']) && $parent_qualification_id) {
			foreach ($p2s_project['quotas'] as $survey_quota) {
				if (!is_array($survey_quota)) {
					continue;
				}
				$query_body_for_quota = array(
					'partner' => 'points2shop',
					'qualifications' => array(
						'country' => array(!empty($project['Project']['country']) ? $project['Project']['country']: 'US')
					)
				);
				foreach ($survey_quota['conditions'] as $question => $answers) {
					if (empty($question) || empty($answers)) {
						continue;
					}
					$mv_question = $this->Question->find('first', array(
						'conditions' => array(
							'Question.question' => $question,
							'Question.partner' => 'points2shop',
						),
						'fields' => array('Question.partner_question_id'),
						'recursive' => -1,
					));
					if (!empty($mv_question['Question']['partner_question_id'])) {
						$query_body_for_quota['qualifications'][$mv_question['Question']['partner_question_id']] = $answers;
					}
				}
				$child_query_json = json_encode($query_body_for_quota);
				$child_query_hash = md5($child_query_json);

				$this->Qualification->bindModel(array('hasOne' => array('QualificationStatistic')));
				$child_qualification = $this->Qualification->find('first', array(
					'conditions' => array(
						'Qualification.parent_id' => $parent_qualification_id,
						'Qualification.partner_qualification_id' =>  $survey_quota['id'],
						'Qualification.deleted is null'
					)
				));

				if (!$child_qualification || $child_query_hash != $child_qualification['Qualification']['query_hash']) {
					if ($child_qualification && $child_query_hash != $child_qualification['Qualification']['query_hash']) {
						if (!empty($parent_qualification_id)) {
							$this->Qualification->delete($child_qualification['Qualification']['id']);
						}
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
						'partner_qualification_id' => $survey_quota['id'],
						'name' => $survey_quota['id'],
						'query_hash' => $child_query_hash,
						'query_json' => $child_query_json,
						'quota' => $survey_quota['remaining_completes'] + $existing_completes,
						'cpi' => $payouts['client_rate'],
						'award' => $payouts['award'],
						'active' => $this->settings['points2shop.live.invites'] == 'true'
					)));
					$child_qualification_id = $this->Qualification->getInsertId();
					
					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $project['Project']['id'],
						'type' => 'qualification.created',
						'description' => 'Qualification #' . $child_qualification_id . ' created.',
					)));
					
					$qualificationSource->commit();
					$created_qualification_ids[] = $child_qualification_id;
					$this->lecho('Created child qualification '.$child_qualification_id, $log_file, $log_key);
				}
			}
		}

		//"platform_types": ["ios_tablet", "android_kindle", "ios_phone", "desktop", "android_phone", "ios_ipod", "android_tablet"]
		$desktop = $mobile = $tablet = false;
		$platform_types = $p2s_project['platform_types'];
		if (in_array('desktop', $platform_types)) {
			$desktop = true;
		}
		if (in_array('ios_phone', $platform_types) || in_array('android_phone', $platform_types)) {
			$mobile = true;
		}
		if (in_array('android_tablet', $platform_types) || in_array('ios_tablet', $platform_types)) {
			$tablet = true;
		}

		// Update mobile, tablet & desktop flags
		$this->Project->create();
		$this->Project->save(array('Project' => array(
			'id' => $project['Project']['id'],
			'mobile' => $mobile,
			'tablet' => $tablet,
			'desktop' => $desktop,
		)), true, array('mobile', 'tablet', 'desktop'));

		// disable manual invites completely for now
		if (false) {
			if (defined('IS_DEV_INSTANCE') && IS_DEV_INSTANCE === true) {
				$panelist_ids = MockQueryEngine::parent_panelists(1);
			}
			else {
				if ($this->settings['points2shop.live.invites'] == 'true') {
					$panelist_ids = QueryEngine::qe2($this->settings, $query_json);
				}
				else {
					$panelist_ids = array(); 	
				}				
			}
			$qe2_count = count($panelist_ids);
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
					// commented out to avoid posting an alert
					// $http->post($this->settings['slack.qe2.webhook'], json_encode(array(
					// 	'text' => 'No matching panelists from QE2: #'.$project['Project']['id'].' <https://cp.mintvine.com/surveys/dashboard/'.$project['Project']['id'].'> ('.$identifier.')',
					// 	'link_names' => 1,
					// 	'username' => 'bernard'
					// )));
					// $http->post($this->settings['slack.qe2.webhook'], json_encode(array(
					// 	'text' => 'Original Query: '.$raw_query_json,
					// 	'link_names' => 1,
					// 	'username' => 'bernard'
					// )));
					// $http->post($this->settings['slack.qe2.webhook'], json_encode(array(
					// 	'text' => 'Executed Query: '.$query_json,
					// 	'link_names' => 1,
					// 	'username' => 'bernard'
					// )));
				}
				return false;
			}

			// filter these panelist IDs out here by active in last three weeks
			if (!empty($panelist_ids)) {
				$panelist_id_chunks = array_chunk($panelist_ids, 12000, false);
				$filtered_panelist_ids = array();
				$this->out('Chunked into '.count($panelist_id_chunks));
				foreach ($panelist_id_chunks as $chunked_ids) {
					$users = $this->User->find('list', array(
						'fields' => array('User.id', 'User.id'),
						'conditions' => array(
							'User.deleted_on' => null,
							'User.id' => $chunked_ids,
							'User.hellbanned' => false,
							'User.last_touched >=' => date(DB_DATETIME, strtotime('-21 days'))
						),
						'recursive' => -1
					));
					$filtered_panelist_ids = $filtered_panelist_ids + $users;
				}
				$panelist_ids = $filtered_panelist_ids;
			}
			$invite_count = count($panelist_ids);

			$survey_users = $this->SurveyUser->find('list', array(
				'fields' => array('SurveyUser.id', 'SurveyUser.user_id'),
				'conditions' => array(
					'SurveyUser.survey_id' => $project['Project']['id']
				)
			));
			$qualification_users = $this->QualificationUser->find('list', array(
				'fields' => array('QualificationUser.id', 'QualificationUser.user_id'),
				'conditions' => array(
					'QualificationUser.qualification_id' => $parent_qualification_id,
					'QualificationUser.deleted' => false
				)
			));
			$this->lecho('Existing panelists in project: '.count($survey_users).'/'.count($qualification_users), $log_file, $log_key);
			
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

			// update the total count on this qualification
			$this->set_qualification_invite_count($parent_qualification_id, $qe2_count, $invite_count);
		}
	}
	
	public function launch_project($project, $points2shop_survey, $log_file, $log_key, $from_create = false) {
		// bypass for ignored clients
		if ($project['Client']['do_not_autolaunch']) {
			return false;
		}
		// check skip_project answer
		$launch_project = true;
		$total_qualifications_list = $this->Points2shop->qualifications($points2shop_survey);
		$this->Question->bindModel(array(
			'hasMany' => array(
				'Answer' => array(
					'foreignKey' => 'question_id',
					'conditions' => array(
						'Answer.question_id' => 'Question.id'
					)
				)
			),
		));
		foreach ($total_qualifications_list as $question_id => $answers) {
			$question = $this->Question->find('first', array(
				'fields' => array('Question.id', 'Question.question'),
				'conditions' => array(
					'Question.partner_question_id' => $question_id,
					'Question.partner' => 'points2shop',
				),
				'contain' => array(
					'Answer' => array(
						'fields' => array('Answer.id', 'Answer.partner_answer_id'),
						'conditions' => array(
							'Answer.partner_answer_id' => $answers,
							'Answer.skip_project' => true
						),
					)
				)
			));
			
			if (!empty($question['Answer']) && count($question['Answer']) == count($answers)) {
				$launch_project = false;
				break;
			}
		}
		
		if (!$launch_project) {
			$answer_ids = Set::extract('/Answer/partner_answer_id', $question);
			
			// only send slack alert on create. otherwise we will keep sending the alerts because the update shell run every 15 minutes
			if ($from_create) {
				$http = new HttpSocket(array(
					'timeout' => '2',
					'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
				));
				try {
					// $http->post($this->settings['slack.alerts.webhook'], json_encode(array(
					// 	'text' => '[Launch Failed] Project <https://cp.mintvine.com/surveys/dashboard/'.$project['Project']['id'].'|#F'. $this->params['points2shop_project_id'] .'>  have "Skip project" qualifications. Question: '.$question['Question']['question'].' (partner question_id: ' . $question_id . ', Answer ids: '.implode(', ', $answer_ids).')',
					// 	'link_names' => 1,
					// 	'username' => 'bernard'
					// )));
				} 
				catch (Exception $ex) {
					$this->lecho('Slack api error: Slack alert not sent', $log_file, $log_key);
				}
			}
			
			$this->lecho('FAILED: '.$project['Project']['id'].' (#F'.$this->params['points2shop_project_id'].') have "Skip project" qualifications. Question: '.$question['Question']['question'].' (partner question_id: '.$question_id. ', Answer ids: '.implode(', ', $answer_ids).')' , $log_file, $log_key);
			return false;
		}

		if ($this->can_launch($points2shop_survey, $log_file, $log_key) && !$this->Points2shop->is_closed($points2shop_survey)) {			
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'status' => PROJECT_STATUS_OPEN,
				'ended' => null,
				'started' => empty($project['Project']['started']) ? date(DB_DATETIME) : $project['Project']['started'],
				'active' => true
			)), true, array('status', 'active', 'ended', 'started'));

			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'status.opened',
				'internal_description' => 'auto',
			)));
			$this->lecho('[LAUNCHED] #' . $project['Project']['id'].' (#F'.$this->params['points2shop_project_id'].')', $log_file, $log_key);
		}
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
				'description' => 'Qualification #'.$qualification_id.' updated: ' . $log,
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

	public function get_points2shop_project() {
		$http = new HttpSocket(array(
			'timeout' => 30,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$params = array(
			'project_id' => $this->params['points2shop_project_id']
		);
		$header = array(
			'header' => array(
				'X-YourSurveys-Api-Key' => $this->settings['points2shop.secret.api']
			),
		);
		$response = $http->get($this->settings['points2shop.host'] . 'suppliers_api/surveys', $params, $header);
		$response = json_decode($response, true);
		if (!empty($response['surveys'][0])) {
			// convert from non-standard to standard
			if ($response['surveys'][0]['country'] == 'UK') {
				$response['surveys'][0]['country'] = 'GB';
			}
			return $response['surveys'][0];	
		}
		else {
			return array();
		}
	}

	private function load_settings($log_file, $log_key, $time_start) {
		$required_settings = array(
			'points2shop.active',
			'points2shop.secret.api',
			'points2shop.host',
			'points2shop.live.invites',
			'points2shop.default.loi',
			'points2shop.default.ir',

			'qqq.active',
			'qe.mintvine.username',
			'qe.mintvine.password',
			'hostname.qe',
			
			'points2shop.floor.award',
			'points2shop.floor.loi.ratio',
			'points2shop.sample_threshold',

			'api.mintvine.username',
			'api.mintvine.password',
			'hostname.api',
		);
		$this->settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => $required_settings,
				'Setting.deleted' => false
			),
			'recursive' => -1
		));

		if (count($this->settings) != count($required_settings)) {
			$diff_keys = array_diff($required_settings,  array_keys($this->settings));
			$this->lecho('FAILED: You are missing required Points2shop settings: '.implode(', ', $diff_keys), $log_file, $log_key);
			$this->lecho('Completed loadSettings (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}

		$this->points2shop_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'points2shop', // this is the new group
			),
			'recursive' => -1,
		));
		$this->mv_partner = $this->Partner->find('first', array(
			'conditions' => array(
				'Partner.key' => 'mintvine',
				'Partner.deleted' => false
			)
		));
		$this->points2shop_client = $this->Client->find('first', array(
			'fields' => array('Client.id'),
			'conditions' => array(
				'Client.param_type' => 'points2shop',
				'Client.deleted' => false
			)
		));

		if (!$this->points2shop_group || !$this->points2shop_client || !$this->mv_partner) {
			$this->lecho('FAILED: Missing client, group, or partner', $log_file, $log_key);
			$this->lecho('Completed loadSettings (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		return true;
	}
	
	// updates information about the project; including handling qualification diffs
	public function update() {
		$log_file = 'points2shop.update';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting update', $log_file, $log_key);
		if (!isset($this->params['points2shop_project_id']) && !isset($this->params['project_id'])) {
			$this->lecho('FAILED: You need at least points2shop_project_id or project_id set to update', $log_file, $log_key);
			return false;
		}
		if (!$this->load_settings($log_file, $log_key, $time_start)) {
			return false;
		}
		if (isset($this->params['points2shop_project_id'])) {
			$project = $this->Project->find('first', array(
				'conditions' => array(
					'Project.mask' => $this->params['points2shop_project_id'],
					'Project.group_id' => $this->points2shop_group['Group']['id'],
				)
			));
		}
		elseif (isset($this->params['project_id'])) {
			$project_id = $this->params['project_id'];
			$project = $this->Project->find('first', array(
				'conditions' => array(
					'Project.id' => $project_id,
					'Project.group_id' => $this->points2shop_group['Group']['id'],
				)
			));
		}

		if (!$project) {
			$this->lecho('That Points2Shop project has not been imported.', $log_file, $log_key);
			return false;
		}

		$points2shop_survey = $this->get_points2shop_project();
		
		if (empty($points2shop_survey)) {
			$this->lecho('That Points2Shop project does not exist anymore.', $log_file, $log_key);
			return false;
		}

		// save points2shop project data as json object
		$this->Points2shop->save_points2shop_project($project['Project']['id'], $points2shop_survey, $log_file, $log_key);

		$payouts = $this->Points2shop->payout($points2shop_survey);
		$overall_quota = $points2shop_survey['remaining_completes'];
		MintVine::project_quota_statistics('points2shop', $overall_quota, $project['Project']['id']);

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

		$client_link = $this->Points2shop->client_link($points2shop_survey);
		$bid_ir = $this->Points2shop->bid_ir($points2shop_survey, $this->settings['points2shop.default.ir']);
		$loi = $this->Points2shop->loi($points2shop_survey, $this->settings['points2shop.default.loi']);

		$min_time = round($loi / 4);
		if ($min_time <= 1) {
			$min_time = null;
		}

		$project_data = array('Project' => array(
			'id' => $project['Project']['id'],
			'client_rate' => $payouts['client_rate'],
			'award' => $payouts['award'],
			'client_id' => $this->points2shop_client['Client']['id'],
			'prj_name' => isset($points2shop_survey['name']) ? $points2shop_survey['name'] : 'Survey for you!',
			'bid_ir' => $bid_ir,
			'partner_rate' => $payouts['partner_rate'],
			'user_payout' => $payouts['partner_rate'],
			'quota' => $overall_quota,
			'est_length' => $loi,
			'country' => $points2shop_survey['country'],
			'minimum_time' => $min_time,
			'client_survey_link' => $client_link
		));

		$project_changed = Utils::array_values_changed($project['Project'], $project_data['Project']);
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
			$this->lecho('[SUCCESS] Project #'.$project['Project']['id'].' updated with new fields', $log_file, $log_key);
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

			$this->lecho('[SKIPPED] Project #'.$project['Project']['id'].' does not need to be updated', $log_file, $log_key);
		}
		
		 $is_closed = false;
		// if this project is open, and Points2shop is reporting a closed project, then close it
		// no need to check survey.active
		if (!$project['Project']['ignore_autoclose'] && $project['Project']['status'] == PROJECT_STATUS_OPEN) {
			$still_open = true;
			if ($this->Points2shop->is_closed($points2shop_survey)) {
				$is_closed = true;
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'status' => PROJECT_STATUS_CLOSED,
					'active' => false,
					'ended' => empty($project['Project']['ended']) ? date(DB_DATETIME) : $project['Project']['ended']
				)), true, array('status', 'active', 'ended'));

				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'status.closed.points2shop',
					'description' => 'Closed by Points2shop'
				)));
				Utils::save_margin($project['Project']['id']);
				$still_open = false;
				$this->lecho('[SUCCESS] Project #'.$project['Project']['id'].' closed by Points2shop', $log_file, $log_key);
			}
			// after update, the new rules fail some internal mechanisms
			elseif (!$this->can_launch($points2shop_survey, $log_file, $log_key)) {
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
					'failed_data' => json_encode($points2shop_survey),
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
			if (!$this->Points2shop->is_closed($points2shop_survey) && $this->can_launch($points2shop_survey, $log_file, $log_key)) {
				$last_close_status = $this->ProjectLog->find('first', array(
					'conditions' => array(
						'ProjectLog.project_id' => $project['Project']['id'],
						'ProjectLog.type LIKE' => 'status.closed%'
					),
					'order' => 'ProjectLog.id DESC'
				));
				// if we didn't close it for performance reasons, reopen it
				if (!$last_close_status || $last_close_status['ProjectLog']['type'] != 'status.closed.auto') {
					$this->Project->create();
					$this->Project->save(array('Project' => array(
						'id' => $project['Project']['id'],
						'status' => PROJECT_STATUS_OPEN,
						'ended' => null,
						'active' => true
					)), true, array('status', 'active', 'ended'));

					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $project['Project']['id'],
						'type' => 'status.opened.points2shop',
						'description' => 'Reopened by Points2shop'
					)));
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

					if ($response['open_project']) {
						$this->lecho('[REOPENED] #'.$project['Project']['id'], $log_file, $log_key);
					}
				}
			}
		}
		elseif (!$project['Project']['ignore_autoclose'] && $project['Project']['status'] == PROJECT_STATUS_STAGING) {
			if ($this->can_launch($points2shop_survey, $log_file, $log_key) && !$this->Points2shop->is_closed($points2shop_survey)) {
				$this->launch_project($project, $points2shop_survey, $log_file, $log_key);
			}
			else {
				$is_closed = true;
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'status' => PROJECT_STATUS_CLOSED,
					'active' => false,
					'ended' => empty($project['Project']['ended']) ? date(DB_DATETIME) : $project['Project']['ended']
				)), true, array('status', 'active', 'ended'));

				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'status.closed.rules',
					'failed_rule' => (isset($this->failed_rule)) ? $this->failed_rule : '',
					'failed_data' => json_encode($points2shop_survey),
					'description' => 'Closed because it failed our project filter rules'
				)));
			}
		}
		if ($is_closed == false) {
			$this->qqq();
		}
	}
	
	// internal method, given a p2s project, whether it should laanch; look at settings examples in lucid
	private function can_launch($project, $log_file = null, $log_key = null) {
		$loi = $this->Points2shop->loi($project, $this->settings['points2shop.default.loi']);;
		$bid_ir = $this->Points2shop->bid_ir($project, $this->settings['points2shop.default.ir']);

		$client_link = $this->Points2shop->client_link($project);
		$payouts = $this->Points2shop->payout($project);

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
		elseif ($payouts['award'] < $this->settings['points2shop.floor.award']) {
			$return = false;
			$message = '[FAILED RULE] Award floor ('.$payouts['award'].' < '.$this->settings['points2shop.floor.award'].')';
		}
		// check floor EPC
		elseif (!empty($bid_ir) && !empty($this->points2shop_group['Group']['epc_floor_cents']) && ($payouts['client_rate'] * $bid_ir / 100) <= ($this->points2shop_group['Group']['epc_floor_cents'] / 100)) {
			$return = false;
			$message = '[FAILED RULE] EPC floor '.$payouts['client_rate'].' '.$bid_ir.' ('.($payouts['client_rate'] * $bid_ir / 100).' <= '.($this->points2shop_group['Group']['epc_floor_cents'] / 100).')';
		}
		// ratio floor rule
		elseif (!empty($loi) && $loi * $this->settings['points2shop.floor.loi.ratio'] > $payouts['award']) {
			$return = false;
			$message = '[FAILED RULE] Award ratio ('.($loi * $this->settings['points2shop.floor.loi.ratio']).' > '.$payouts['award'].')';
		}
		// long LOIs are skipped
		elseif (!empty($this->points2shop_group['Group']['max_loi_minutes']) && $loi >= $this->points2shop_group['Group']['max_loi_minutes']) {
			$return = false;
			$message = '[FAILED RULE] Long LOI ('.$loi.' minutes)';
		}
		if (!empty($message)) {
			$this->failed_rule = $message;
		}
		if (!empty($log_file) && !empty($log_key) && !empty($message)) {
			$this->lecho($message, $log_file, $log_key);
		}
		return $return;
	}

	/* This function will be used to get all availble surveys the first parameter passed will work as a limit, by default limit will be 1*/
	function get_surveys() {
		$limit = 100;
		if (isset($this->args[0]) && $this->args[0]) {			
			$limit = $this->args[0];
		}
				
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('points2shop.secret.api'),
				'Setting.deleted' => false
			)
		));
		
		if (!$settings) {
			echo 'Setting not defined.';
			return false;
		}
		
		$survey_api_endpoint = 'https://www.your-surveys.com/suppliers_api/surveys';
		
		$this->HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$response = $this->HttpSocket->get($survey_api_endpoint, array(
			'limit' => $limit
		), array('header' => array(
			'X-YourSurveys-Api-Key' => $settings['points2shop.secret.api']
		)));
		
		$projects = json_decode($response);
		print_r($projects);
		$complete = 0;
		$cpi = 0;
		$num = 0;
		$rev = 0;
		print_r($projects);
		foreach ($projects->surveys AS $project) {
			if ($project->remaining_completes > 0) {
				$complete = $complete + $project->remaining_completes;
				$cpi = $cpi + $project->cpi;
				$num++;
				$rev = $rev + ($project->cpi * $project->remaining_completes);
				echo 'Project: '.$project->project_id.' Completes: '.$project->remaining_completes.' CPI: '.$project->cpi.' IR: '.$project->conversion_rate."\r\n";
			}
		}
		
		$avgcpi = $cpi / $num;
		echo 'Total Projects Open: '.$num.' Total Completes Available: '.$complete.' Average CPI: '.$avgcpi.' Total Revenue: '.$rev."\r\n"; 
		
		//echo '<pre>';print_r(json_decode($response));
	}
	
	function get_user_survey() {
		if (!isset($this->args[0]) && !$this->args[0]) {
			return false;
		}
		
		$limit = 1;
		if (isset($this->args[1]) && $this->args[1]) {			
			$limit = $this->args[1];
		}
		
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('points2shop.secret.api'),
				'Setting.deleted' => false
			)
		));
		
		if (!$settings) {
			echo 'Setting not defined.';
			return false;
		}
		
		$this->User->bindModel(array(
			'hasOne' => array(
				'QueryProfile',
				'UserIp'
			)
		));
		
		$user = $this->User->find('first', array(
			'conditions' => array(
				'User.id' => $this->args[0]
			),
			'fields' => array(
				'User.email', 'User.id', 'QueryProfile.birthdate', 'QueryProfile.postal_code', 'QueryProfile.gender', 'UserIp.ip_address'
			)
		));
		
		if (!$user) {
			echo 'User not exist.';
			return false;
		}
		//pr ($user); die;
		
		$survey_api_endpoint = 'https://www.your-surveys.com/suppliers_api/surveys/user';
		
		$this->HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$response = $this->HttpSocket->get($survey_api_endpoint, array(
			'user_id' => $this->args[0],
			'date_of_birth' => $user['QueryProfile']['birthdate'],
			'limit' => $limit,
			'email' => $user['User']['email'],
			'gender' => $user['QueryProfile']['gender'],
			'zip' => $user['QueryProfile']['postal_code'],
			'ip_address' => $user['UserIp']['ip_address'],
		), array('header' => array(
			'X-YourSurveys-Api-Key' => $settings['points2shop.secret.api']
		)));
				
		echo '<pre>';print_r(json_decode($response));
	}

	function get_user_survey_sample() {
		$limit = 10;
		
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('points2shop.secret.api'),
				'Setting.deleted' => false
			)
		));
		
		if (!$settings) {
			echo 'Setting not defined.';
			return false;
		}
		
		$this->User->bindModel(array(
			'hasOne' => array(
				'QueryProfile',
				'UserIp'
			)
		));
		
		$users = $this->User->find('all', array(
			'conditions' => array(
				'User.hellbanned' => false
			),
			'order' => 'RAND()',
			'limit' => '100',
			'fields' => array(
				'User.email', 'User.id', 'QueryProfile.birthdate', 'QueryProfile.postal_code', 'QueryProfile.gender', 'UserIp.ip_address'
			)
		));

		$output = array();
		$survey_api_endpoint = 'https://www.your-surveys.com/suppliers_api/surveys/user';
		foreach ($users as $user) {		
			$this->HttpSocket = new HttpSocket(array(
				'timeout' => 15,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$response = $this->HttpSocket->get($survey_api_endpoint, array(
				'user_id' => $user['User']['id'],
				'date_of_birth' => $user['QueryProfile']['birthdate'],
				'limit' => $limit,
				'email' => $user['User']['email'],
				'gender' => $user['QueryProfile']['gender'],
				'zip' => $user['QueryProfile']['postal_code'],
				'ip_address' => $user['UserIp']['ip_address'],
			), array('header' => array(
				'X-YourSurveys-Api-Key' => $settings['points2shop.secret.api']
			)));
			$surveys = json_decode($response);
			foreach ($surveys->surveys as $survey) {
				$output[$survey->project_id]['cpi'] = $survey->cpi;
				$output[$survey->project_id]['hit'] = $output[$survey->project_id]['hit'] + 1;
				$output[$survey->project_id]['ir'] = $survey->conversion_rate;
			}
		}
		
		$estrev = 0;
		$avgcpi = 0;
		$num = 0;
		foreach ($output as $key=>$survey) {
			$estrev = $estrev + ($survey['hit'] * $survey['ir'] * $survey['cpi']);
			$output[$key]['estrev'] = $survey['hit'] * $survey['ir'] * $survey['cpi'];
			$avgcpi = $avgcpi + $survey['cpi'];
			echo 'Project ID: '.$key.' CPI: '.$survey['cpi'].' IR: '.$survey['ir'].' HITS: '.$survey['hit']."\r\n";
			$num++;
		}
		$avgcpi = $avgcpi / $num;
		echo "Estimated Revenue: ".$estrev." Avg CPI: ".$avgcpi."\r\n";
	}
	
	function get_survey_profilers() {
		$country = '';
		if (isset($this->args[0]) && $this->args[0]) {
			$country = $this->args[0];
		}
		
		$limit = 1;
		if (isset($this->args[1]) && $this->args[1]) {			
			$limit = $this->args[1];
		}
		
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('points2shop.secret.api'),
				'Setting.deleted' => false
			)
		));
		
		$survey_api_endpoint = 'https://www.your-surveys.com/suppliers_api/surveys/profilers';
		
		$this->HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$response = $this->HttpSocket->get($survey_api_endpoint, array(
			'limit' => $limit,
			'country' => $country
		), array('header' => array(
			'X-YourSurveys-Api-Key' => $settings['points2shop.secret.api']
		)));
		
		echo '<pre>';print_r(json_decode($response));
	}
	
	function manage_projects() {
		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => array('hostname.www'),
				'Setting.deleted' => false
			)
		));
		
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'p2s'
			)
		));
		
		$client = $this->Client->find('first', array(
			'fields' => array('Client.id'),
			'conditions' => array(
				'Client.param_type' => 'points2shop',
				'Client.deleted' => false
			)
		));
		
		if (!$group || !$client) {
			CakeLog::write('points2shop.projects', 'Missing Points2Shop group and/or client'); 
			return;
		}
		
		$supported_countries = array_keys(unserialize(SUPPORTED_COUNTRIES));
		foreach ($supported_countries as $country) {
			$projects = $this->Project->find('all', array(
				'conditions' => array(
					'Project.status' => PROJECT_STATUS_OPEN,
					'Project.group_id' => $group['Group']['id'],
					'Project.country' => $country,
				),
				'order' => 'Project.id DESC'
			));
			
			if (!$projects) {
				$this->out('No project detected for '.$country.'; creating new project');
				$this->create_router_project($client, $group, $settings, $country);
			}
		}
		
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.status' => PROJECT_STATUS_OPEN,				
				'Project.group_id' => $group['Group']['id'],
				'Project.active' => true
			),
			'order' => 'Project.id DESC'
		));
		
		foreach ($projects as $project) {
			$click_count = $project['SurveyVisitCache']['click'];
			if ($click_count >= 40000) {
				$country = $project['Project']['country'];
				if (empty($country)) {
					continue;
				}
				$this->out('Creating Points2Shop project');
				$this->close_projects($project['Project']['id']);
				$this->create_router_project($client, $group, $settings, $country);
			}
		}
	}
	
	private function create_router_project($client, $group, $settings, $country) {
		$survey_name = 'Points Place! A MintVine Funnel Survey';
		$cpi = 1.25;
		$payout = 70; 
		$loi = 15;
		$bid_ir = 15;
		
		$projectSource = $this->Project->getDataSource();
		$projectSource->begin();
		$this->Project->create();			
		$save = $this->Project->save(array('Project' => array(
			'client_id' => $client['Client']['id'],
			'group_id' => $group['Group']['id'],
			'status' => PROJECT_STATUS_OPEN,
			'bid_ir' => $bid_ir,
			'est_length' => $loi,
			'router' => true,
			'singleuse' => false, 
			'quota' => '10000', 
			'client_rate' => $cpi,
			'partner_rate' => $cpi,
			'prj_name' => 'Points2Shop Router',
			'user_payout' => round($payout / 100, 2),
			'award' => $payout,
			'mobile' => false,
			'desktop' => true,
			'tablet' => true,
			'started' => date(DB_DATETIME),
			'active' => true,
			'dedupe' => false,
			'public' => true,
			'country' => $country,
			'language' => 'en',
			'minimum_time' => 1,
			'survey_name' => $survey_name,
			'mask' => null,
			'description' => 'New Daily Surveys on Points Place!',
			'client_end_action' => 's2s',
			// temporary workaround; something in r.mintvine.com is corrupting these URLs
			'client_survey_link' => str_replace('https://', 'http://', $settings['hostname.www'].'/p2s/go/?uid={{ID}}'),
		)));
		
		if ($save) {
			$project_id = $this->Project->getInsertId();
			$projectSource->commit();
			// add mintvine as a partner
			$mv_partner = $this->Partner->findByKey('MintVine');
			$this->Project->SurveyPartner->create();
			$this->Project->SurveyPartner->save(array('SurveyPartner' => array(
				'survey_id' => $project_id,
				'partner_id' => $mv_partner['Partner']['id'],
				'rate' => round($payout / 100, 2), // award
				'complete_url' => $settings['hostname.www'].'/surveys/complete/{{ID}}/',
				'nq_url' => $settings['hostname.www'].'/surveys/nq/{{ID}}/',
				'oq_url' => $settings['hostname.www'].'/surveys/oq/{{ID}}/',
				'pause_url' => $settings['hostname.www'].'/surveys/paused/',
				'fail_url' => $settings['hostname.www'].'/surveys/sec/{{ID}}/',
			)));
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project_id,
				'type' => 'project.created'
			)));
			
			$setting = $this->Setting->find('first', array(
				'conditions' => array(
					'Setting.name' => 'slack.alerts.webhook',
					'Setting.deleted' => false
				),
				'fields' => array('Setting.value')
			));
			$http = new HttpSocket(array(
				'timeout' => '2',
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$http->post($setting['Setting']['value'], json_encode(array(
				'text' => 'Created new Points2Shop router project #'.$project_id.' <https://cp.mintvine.com/surveys/dashboard/'.$project_id.'>', 
				'link_names' => 1,
				'username' => 'bernard'
			)));
			$this->out('Created #'.$project_id.' ('.$country.')'); 
		}
		else {
			$projectSource->commit();
		}
	}
	
	public function close_projects($project_id = null) {
		if (empty($project_id)) {
			return;
		}
		
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'p2s'
			)
		));
		if (!$group) {
			return;
		}
		
		$project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $project_id,
				'Project.status' => PROJECT_STATUS_OPEN,					
				'Project.group_id' => $group['Group']['id'],
			),
			'order' => 'Project.id DESC'
		));
		
		if (!$project) {
			return;
		}
				
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
			'type' => 'status.closed',
			'description' => ''
		)));
		Utils::save_margin($project['Project']['id']);
		$setting = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'slack.alerts.webhook',
				'Setting.deleted' => false
			),
			'fields' => array('Setting.value')
		));
		$http = new HttpSocket(array(
			'timeout' => '2',
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$http->post($setting['Setting']['value'], json_encode(array(
			'text' => 'Closed Points2Shop router project #'.$project['Project']['id'].' <https://cp.mintvine.com/surveys/dashboard/'.$project['Project']['id'].'>', 
			'link_names' => 1,
			'username' => 'bernard'
		)));

		$this->out('Closed #'.$project['Project']['id'].' for being a dupe');
		CakeLog::write('auto.close', '#' . $project['Project']['id'] . ' closed.');
	}
	
	// send in argument of date
	public function reconcile_data() {
		if (!isset($this->args[0])) {
			$this->out('Send in an argument for date');
			return false;
		}
		
		App::import('Model', 'RouterLog');
		$this->RouterLog = new RouterLog;
		
		$router_logs = $this->RouterLog->find('all', array(
			'conditions' => array(
				'RouterLog.type' => 'success',
				'RouterLog.created' => date('Y-m-d', strtotime($this->args[0])),
				'RouterLog.source' => 'p2s',
			)
		));
		
		$this->out('Total entries: '.count($router_logs));
		
		foreach ($router_logs as $router_log) {
			
		}
	}
	
	public function update_question_answer_list() {
		$country_names = array(
			6 => 'CA',
			8 => 'GB',
			9 => 'US'
		);

		$required_settings = array(
			'points2shop.api.key',
			'points2shop.host',
			'slack.questions.webhook'
		);
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => $required_settings,
				'Setting.deleted' => false
			),
			'recursive' => -1
		));

		if (count($settings) != count($required_settings)) {
			$this->out('ERROR: Missing required settings');
			return;
		}

		$HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));

		$points2shop_header = array(
			'header' => array(
				'X-YourSurveys-Api-Key' => $settings['points2shop.api.key']
			),
		);
		$count = 0;
		$limit = 100;
		foreach ($country_names as $country) {
			$offset = 0;
			while (true) {
				$request_data = array(
					'country' => ($country == 'GB') ? 'UK' : $country,
					'limit' => $limit,
					'offset' => $offset,
				);
				$offset = $offset + $limit;
				$this->Question->getDatasource()->reconnect();
				$response = $HttpSocket->get($settings['points2shop.host'] . '/suppliers_api/surveys/profilers', $request_data, $points2shop_header);
				$body = json_decode($response['body'], true);
				
				if (empty($body['profilers'])) {
					break;
				}
				
				foreach ($body['profilers'] as $question) {
					if (empty($question['name'])) {
						continue; 
					}
					
					$behavior = null; 
					if (isset($question['type']) && $question['type'] == 'single_punch') {
						$question['type'] = QUESTION_TYPE_SINGLE; 
					}
					elseif (isset($question['type']) && $question['type'] == 'multi_punch') {
						$question['type'] = QUESTION_TYPE_MULTIPLE; 
					}
					elseif (isset($question['type']) && $question['type'] == 'open_ended') {
						$question['type'] = QUESTION_TYPE_TEXT_OPEN_END; 
					}
					elseif (isset($question['type']) && $question['type'] == 'int_open_ended') {
						$question['type'] = QUESTION_TYPE_NUMERIC_OPEN_END; 
						$behavior = 'bigset'; 
						if ($question['name'] == 'age') {
							$behavior = 'date'; 
						}
					}
					
					$this->out('Processing question ' . $country . ': ' . $question['profiler_id'] . ' - ' . $question['question']);
					$mintvine_question = $this->Question->find('first', array(
						'fields' => array('Question.id', 'Question.question_type', 'Question.behavior'),
						'conditions' => array(
							'Question.partner_question_id' => $question['profiler_id'],
							'Question.partner' => 'points2shop'
						),
						'recursive' => -1
					));

					if (!$mintvine_question) {
						$questionDataSource = $this->Question->getDataSource();
						$questionDataSource->begin();
						$this->Question->create();
						$saved = $this->Question->save(array('Question' => array(
							'partner_question_id' => $question['profiler_id'],
							'partner' => 'points2shop',
							'question' => $question['name'],
							'question_type' => isset($question['type']) ? $question['type'] : '',
							'logic_group' => null,
							'order' => null,
							'skipped_answer_id' => null,
							'behavior' => $behavior
						)));
						if ($saved) {
							$question_id = $this->Question->getInsertId();
							$mintvine_question = $this->Question->findById($question_id);
							$questionDataSource->commit();
							$message = 'New Points2Shop Question saved';
							$message .= "\nID: " . $question['profiler_id'];
							$message .= "\nQuestion: " . $question['name'];
							$this->out($message);
							Utils::slack_alert($settings['slack.questions.webhook'], $message);
						}
						else {
							$questionDataSource->commit();
							$message = 'Points2Shop Question failed to save.';
							$message .= "\nID: " . $question['profiler_id'];
							$message .= "\nQuestion: " . $question['name'];
							$this->out($message);
							Utils::slack_alert($settings['slack.questions.webhook'], $message);
							
							// Proceed to next question since question text and answers depend on question
							continue;
						}
					}
					elseif ($mintvine_question['Question']['behavior'] != $behavior || (isset($question['type']) && $mintvine_question['Question']['question_type'] != $question['type'])) {
						$this->Question->create();
						$this->Question->save(array('Question' => array(
							'id' => $mintvine_question['Question']['id'],
							'behavior' => $behavior,
							'question_type' => isset($question['type']) ? $question['type'] : ''
						)), true, array('question_type', 'behavior')); 
					}

					// // Question texts
					if ($mintvine_question) {
						$question_text = $this->QuestionText->find('first', array(
							'fields' => array('QuestionText.id'),
							'conditions' => array(
								'QuestionText.question_id' => $mintvine_question['Question']['id'],
								'QuestionText.country' => $country
							),
							'recursive' => -1
						));

						if (!$question_text) {
							$this->QuestionText->create();
							$saved = $this->QuestionText->save(array('QuestionText' => array(
								'question_id' => $mintvine_question['Question']['id'],
								'country' => $country,
								'text' => $question['question']
							)));
							if (!$saved) {
								$this->out('QUESTION TEXT FAIL: '.$question['name'].' (#'.$question['profiler_id'].')'); 
							}
						}
					}

					// Answers
					if (empty($question['answers'])) {
						continue; 
					}
					
					foreach ($question['answers'] as $answer) {
						if (empty($answer['answer_id'])) {
							continue;
						}
						
						$mintvine_answer = $this->Answer->find('first', array(
							'fields' => array('Answer.id'),
							'conditions' => array(
								'Answer.partner_answer_id' => $answer['answer_id'],
								'Answer.question_id' =>  $mintvine_question['Question']['id'],
							),
							'recursive' => -1
						));
						if (!$mintvine_answer) {
							$answerDataSource = $this->Answer->getDataSource();
							$answerDataSource->begin();
							$this->Answer->create();
							$saved = $this->Answer->save(array('Answer' => array(
								'answer' => $answer['answer'],
								'partner_answer_id' => $answer['answer_id'],
								'question_id' => $mintvine_question['Question']['id'],
								'ignore' => false
							)));

							// Prefer not to slack notify on answer creation
							if ($saved) {
								$answer_id = $this->Answer->getInsertId();
								$mintvine_answer = $this->Answer->findById($answer_id);
								$answerDataSource->commit();
							}
							else {
								$answerDataSource->commit();
								$this->out('ANSWER FAIL: '.$question['name'].': '.$answer['answer']); 
							}
						}

						// Answer text
						if ($mintvine_answer) {
							$answer_text = $this->AnswerText->find('first', array(
								'fields' => array('AnswerText.id'),
								'conditions' => array(
									'AnswerText.answer_id' => $mintvine_answer['Answer']['id'],
									'AnswerText.country' => $country
								),
								'recursive' => -1
							));

							if (!$answer_text) {
								$this->AnswerText->create();
								$saved = $this->AnswerText->save(array('AnswerText' => array(
									'answer_id' => $mintvine_answer['Answer']['id'],
									'country' => $country,
									'text' => $answer['answer']
								)));

								if (!$saved) {
									$this->out('ANSWER TEXT FAIL: '.$question['name'].': '.$answer['answer']); 
								}
							}
						}
					}
				}
			}
		}
		
		$this->out('import completed!'); 
	}
	
	public function import_fips() {
		$fileName = "p2s_county_ids.csv";
		if (isset($this->args[0]) && !empty($this->args[0])) {
			$fileName = $this->args[0];
		}
		$path = APP . WEBROOT_DIR . '/files/' . $fileName;

		$csv = array_map('str_getcsv', file($path));

		foreach ($csv as $row) {
			$id = $row[0];
			$fips = $row[1];

			$state = substr($fips, 0, 2);
			$county = substr($fips, 2, 3);

			$lucid_zips = $this->LucidZip->find('all', array(
				'fields' => array('LucidZip.id', 'LucidZip.county_fips', 'LucidZip.state_fips', 'LucidZip.p2s_county_id'),
				'conditions' => array(
					'LucidZip.county_fips' => intval($county),
					'LucidZip.state_fips' => intval($state)
				),
				'recursive' => -1
			));

			foreach ($lucid_zips as $lucid_zip) {
				$lucid_zip['LucidZip']['p2s_county_id'] = $id;
				$this->LucidZip->save($lucid_zip, true, array('p2s_county_id'));
			}
		}
	}

	public function qe_panelists_logs() {
		$required_settings = array(
			'qe.mintvine.username',
			'qe.mintvine.password',
			'hostname.qe',
		); 
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => $required_settings,
				'Setting.deleted' => false
			),
			'recursive' => -1
		));
		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'points2shop',
			),
			'recursive' => -1,
		));

		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'Project.status' => PROJECT_STATUS_OPEN,
				'Project.active' => true,
			),
			'fields' => array('Project.id', 'Project.mask'),
			'recursive' => -1,
		));

		if (!$projects) {
			$this->out('There are no projects to process');
			return;
		}
		App::import('Model', 'Points2shopPanelistsLog');
		$this->Points2shopPanelistsLog = new Points2shopPanelistsLog;

		App::uses('HttpSocket', 'Network/Http');
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false
		));
		$http->configAuth('Basic', $settings['qe.mintvine.username'], $settings['qe.mintvine.password']);

		foreach ($projects as $project) {
			$qualification = $this->Qualification->find('first', array(
				'conditions' => array(
					'Qualification.project_id' => $project['Project']['id'],
					'Qualification.parent_id' => null,
					'Qualification.deleted' => null,
				),
			));
			if (!$qualification) {
				continue;
			}
			$results = $http->post($settings['hostname.qe'].'/query', $qualification['Qualification']['query_json'], array(
				'header' => array('Content-Type' => 'application/json')
			));
			$results = json_decode($results['body'], true);
			$error = (!empty($results['error'])) ? $results['error'] : '';
			$panelist_ids = isset($results['panelist_ids']) ? $results['panelist_ids'] : array();

			if (!empty($panelist_ids)) {
				$panelist_id_chunks = array_chunk($panelist_ids, 12000, false);
				$filtered_panelist_ids = array();
				foreach ($panelist_id_chunks as $chunked_ids) {
					$users = $this->User->find('list', array(
						'fields' => array('User.id', 'User.id'),
						'conditions' => array(
							'User.deleted_on' => null,
							'User.id' => $chunked_ids,
							'User.hellbanned' => false,
							'User.last_touched >=' => date(DB_DATETIME, strtotime('-21 days'))
						),
						'recursive' => -1
					));
					$filtered_panelist_ids = $filtered_panelist_ids + $users;
				}				
				$panelist_ids = $filtered_panelist_ids;
			}

			$points2shop_panelists_log = $this->Points2shopPanelistsLog->find('first', array(
				'conditions' => array(
					'Points2shopPanelistsLog.project_id' => $project['Project']['id']
				),
				'recursive' => -1,
			));

			if ($points2shop_panelists_log) {
				$this->Points2shopPanelistsLog->getDatasource()->reconnect();
				$this->Points2shopPanelistsLog->create();
				$this->Points2shopPanelistsLog->save(array('Points2shopPanelistsLog' => array(
					'id' => $points2shop_panelists_log['Points2shopPanelistsLog']['id'],
					'query_json' => $qualification['Qualification']['query_json'],
					'panelists_count' => count($panelist_ids),
					'panelists_json' => json_encode($panelist_ids),
					'error' => $error,
				)), true, array('query_json', 'panelists_count', 'panelists_json', 'error'));
			}
			else {
				$this->Points2shopPanelistsLog->getDatasource()->reconnect();
				$this->Points2shopPanelistsLog->create();
				$this->Points2shopPanelistsLog->save(array('Points2shopPanelistsLog' => array(
					'project_id' => $project['Project']['id'],
					'query_json' => $qualification['Qualification']['query_json'],
					'panelists_count' => count($panelist_ids),
					'panelists_json' => json_encode($panelist_ids),
					'error' => $error,
				)));
			}
			$this->out('ProjectID: ' . $project['Project']['id'] . ' processed.');
		}
		$this->out('Finished.');
	}
}
