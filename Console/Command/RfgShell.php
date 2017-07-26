<?php
App::import('Lib', 'QueryEngine');
App::import('Lib', 'RfgMappings');
App::import('Vendor', 'site_profile');
App::import('Lib', 'Utilities');
App::import('Lib', 'MintVine');
App::uses('HttpSocket', 'Network/Http');

class RfgShell extends AppShell {

	const RFG_PROJECT_IN_FIELD = 2;
	const RFG_PROJECT_PAUSED = 3;
	const RFG_PROJECT_CLOSED = 4;

	public $uses = array('Client', 'Group', 'Partner', 'Project', 'Query', 'RfgSurvey', 'RfgQuestion', 'RfgAnswer', 'GeoZip', 'GeoState', 'Prescreener', 'RfgQueue', 'ProjectLog', 'SurveyVisitCache', 'ProjectOption', 'QueryStatistic', 'SurveyUser');
	public $tasks = array('Rfg');

	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser->addSubcommand('debug', array(
			'help' => 'debug an Rfg survey',
			'parser' => array(
				'description' => 'debug an Rfg survey. Rfg survey id must be provided as an argument!',
				'arguments' => array(
					array(
						'required' => true,
						'help' => 'Please provide the Rfg survey Id'
					)
				)
			)
		))
		->addSubcommand('process', array(
			'help' => 'Processes a particular survey or all the RFG inventory. ',
				'parser' => array(
				'description' => 'Processes a particuar survey or all the RFG inventory. ',
				'options' => array(
					'project_id' => array(
						'short' => 'm',
						'default' => null,
						'help' => 'Send in an MV project_id'
					),
					'rfg_survey_id' => array(
						'short' => 'r',
						'default' => null,
						'help' => 'Send in an rfg_survey_id'
					)
				)
			)
		))
		->addSubcommand('create', array(
			'help' => 'create a project, given an rfg_survey_id.',
			'parser' => array(
				'description' => 'create a project, given an rfg_survey_id.',
				'arguments' => array(
					'rfg_survey_id' => array(
						'required' => true,
						'help' => 'Please provide the Rfg survey Id'
					)
				)
			)
		))
		->addSubcommand('update', array(
			'help' => 'Update a particuar survey.',
			'parser' => array(
				'description' => 'Update a particuar survey.',
				'options' => array(
					'project_id' => array(
						'short' => 'm',
						'default' => null,
						'help' => 'Send in an MV project_id'
					),
					'rfg_survey_id' => array(
						'short' => 'r',
						'default' => null,
						'help' => 'Send in an rfg_survey_id'
					)
				)
			)
		))
		->addSubcommand('sends', array(
			'help' => 'Goes through and figures out which projects to do follow-up sends to.',
			'parser' => array(
				'description' => 'Goes through and figures out which projects to do follow-up sends to.',
				'options' => array(
					'project_id' => array(
						'short' => 'm',
						'default' => null,
						'help' => 'Send in an MV project_id'
					),
					'rfg_survey_id' => array(
						'short' => 'r',
						'default' => null,
						'help' => 'Send in an rfg_survey_id'
					)
				)
			)
		))
		->addSubcommand('invite', array(
			'help' => 'Invite survey users.',
			'parser' => array(
				'description' => 'Invite survey users',
				'options' => array(
					'project_id' => array(
						'short' => 'm',
						'default' => null,
						'help' => 'Send in an MV project_id'
					),
					'rfg_survey_id' => array(
						'short' => 'r',
						'default' => null,
						'help' => 'Send in an rfg_survey_id'
					)
				)
			)
		))
		->addSubcommand('qualifications', array(
			'help' => 'Load/update qualifications of an Rfg project, given an rfg_survey_id.',
			'parser' => array(
				'description' => 'Load/update qualifications of an Rfg project, given an rfg_survey_id.',
				'options' => array(
					'rfg_survey_id' => array(
						'short' => 'r',
						'default' => null,
						'help' => 'Send in an rfg survey id'
					)
				)
			)
		));

		return $parser;
	}

	private function loadSettings($log_file, $log_key, $time_start) {
		$keys = array(
			// Rfg API credentials
			'rfg.host',
			'rfg.apid',
			'rfg.secret',
			// various settings
			'rfg.active',
			'rfg.floor.loi.ratio',
			'rfg.ir_cutoff',
			'rfg.floor.award',
			'rfg.floor.epc',
			'rfg.autolaunch',
			'rfg.sample_threshold',
			'rfg.followup.ceiling',
			// mv API
			'api.mintvine.username',
			'api.mintvine.password',
			'hostname.api'
		);
		$this->settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => $keys,
				'Setting.deleted' => false
			)
		));
		if (count($this->settings) != count($keys)) {
			$this->lecho('FAILED: You are missing required Rfg settings', $log_file, $log_key);
			$this->lecho('Completed loadSettings (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			return false;
		}

		$this->rfg_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'rfg'
			)
		));
		$this->rfg_client = $this->Client->find('first', array(
			'conditions' => array(
				'Client.key' => 'rfg',
				'Client.deleted' => false
			)
		));
		$this->mv_partner = $this->Partner->find('first', array(
			'conditions' => array(
				'Partner.key' => 'mintvine',
				'Partner.deleted' => false
			)
		));
		if (!$this->rfg_group || !$this->rfg_client || !$this->mv_partner) {
			$this->lecho('FAILED: Missing client, group, or partner', $log_file, $log_key);
			$this->lecho('Completed loadSettings (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			return false;
		}
		return true;
	}
	
	// goes through and orchestrates the work to be done
	public function process() {
		ini_set('memory_limit', '1024M');
		$log_file = 'rfg.process';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting process', $log_file, $log_key);

		// load settings, client, group, etc. 
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			return false;
		}
		
		if ($this->settings['rfg.active'] != 'true') {
			$this->lecho('RFG not active from settings.', $log_file, $log_key);
			return false;
		}
		
		if (isset($this->params['rfg_survey_id']) && isset($this->params['project_id'])) {
			$this->lecho('FAILED: You cannot define both an Rfg and MintVine project ID', $log_file, $log_key);
			$this->lecho('Completed process (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			return false;
		}

		// validate the project_id
		if (isset($this->params['project_id'])) {
			$target_rfg_survey = $this->RfgSurvey->find('first', array(
				'conditions' => array(
					'RfgSurvey.survey_id' => $this->params['project_id']
				)
			));
			$this->lecho('FAILED: Failed to find project #' . $this->params['project_id'], $log_file, $log_key);
			$this->lecho('Completed process (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			return false;
		}
		else {
			$target_rfg_survey = false;
		}
		
		App::import('Vendor', 'sqs');
		$sqs_settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('sqs.access.key', 'sqs.access.secret', 'sqs.rfg.queue'),
				'Setting.deleted' => false
			)
		));
		$sqs = new SQS($sqs_settings['sqs.access.key'], $sqs_settings['sqs.access.secret']);
		$command = "{ 'command' : 'livealert/inventory/1' }";
		$result = $this->execute_api($command, $log_file, $log_key);
		if (!$result) {
			$this->lecho('Completed process (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			return false;
		}
		
		if (empty($result['response']['projects'])) {
			$this->lecho('Projects not found in Rfg inventory.: ' . $command, $log_file, $log_key);
			$this->lecho('Completed process (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			return false;
		}
		
		//cakeLog::write('rfg-inventory', print_r($result, true));
		$this->lecho('Processing ' . count($result['response']['projects']) . ' projects', $log_file, $log_key);
		
		$allocated_survey_ids = array();
		$desired_countries = array('CA', 'GB', 'US');
		$sqs_batch = array();
		$i = 0;
		foreach ($result['response']['projects'] as $allocated_survey) {
			$i++;
			$allocated_survey_ids[] = $allocated_survey['rfg_id'];
			
			// operating against a single rfg survey
			if (isset($this->params['rfg_survey_id']) && $allocated_survey['rfg_id'] != $this->params['rfg_survey_id']) {
				continue;
			}

			// operating on a single MV project
			if (isset($this->params['project_id']) && $target_rfg_survey && $target_rfg_survey['RfgSurvey']['rfg_survey_id'] != $allocated_survey['rfg_id']) {
				continue;
			}
			
			if (!in_array($allocated_survey['country'], $desired_countries)) {
				$this->lecho('[SKIP] ' . $i . ' #' . $allocated_survey['rfg_id'] . ': unsupported country (' . $allocated_survey['country'] . ')', $log_file, $log_key);
				$this->RfgSurvey->create();
				$this->RfgSurvey->save(array('RfgSurvey' => array(
					'survey_id' => '0',
					'rfg_survey_id' => $allocated_survey['rfg_id'],
					'status' => 'skipped.country'
				)));
				continue;
			}

			$this->RfgSurvey->getDatasource()->reconnect();
			$this->Project->bindModel(array(
				'hasOne' => array(
					'RfgSurvey' => array(
						'className' => 'RfgSurvey',
						'foreignKey' => 'survey_id'
					)
				)
			));
			$project = $this->Project->find('first', array(
				'contain' => array('RfgSurvey'),
				'conditions' => array(
					'RfgSurvey.rfg_survey_id' => $allocated_survey['rfg_id']
				)
			));
			// see if this project exists; if not, create it!
			if (!$project || empty($project)) {
				if ($allocated_survey['state'] != self::RFG_PROJECT_IN_FIELD) {
					$this->lecho('[SKIP] ' . $i . ' #' . $allocated_survey['rfg_id'] . ': Project not active.', $log_file, $log_key);
					$this->RfgSurvey->create();
					$this->RfgSurvey->save(array('RfgSurvey' => array(
						'survey_id' => '0',
						'rfg_survey_id' => $allocated_survey['rfg_id'],
						'status' => 'skipped.state'
					)));
					continue;
				}

				// rfg survey shouldn't exist at all for projects that don't exist
				$rfg_survey = $this->RfgSurvey->find('first', array(
					'conditions' => array(
						'RfgSurvey.rfg_survey_id' => $allocated_survey['rfg_id']
					),
					'order' => 'RfgSurvey.id DESC'
				));
				if ($rfg_survey) {
					// this needs to be looked into: why do we have a failed link but the system generated it?
					if ($rfg_survey['RfgSurvey']['status'] == 'failed.link') {
						$this->RfgSurvey->delete($rfg_survey['RfgSurvey']['id']);
					}
					else {
						$this->lecho($i . ': [SKIP] CREATE #' . $allocated_survey['rfg_id'] . ': exists (' . $rfg_survey['RfgSurvey']['id'] . ')', $log_file, $log_key);
						continue;
					}
				}
				$command = 'rfg create ' . $allocated_survey['rfg_id'];

				$rfgQueueSource = $this->RfgQueue->getDataSource();
				$rfgQueueSource->begin();
				$this->RfgQueue->create();
				$save = $this->RfgQueue->save(array('RfgQueue' => array(
					'rfg_survey_id' => $allocated_survey['rfg_id'],
					'command' => $command,
					'survey_id' => null
				)));
				if ($save) {
					$rfg_queue_id = $this->RfgQueue->getInsertId();
					$rfgQueueSource->commit();
					$sqs_batch[$rfg_queue_id] = $command;
					if (count($sqs_batch) == 10) {
						$this->Rfg->handle_queue($sqs, $sqs_settings['sqs.rfg.queue'], $sqs_batch);
					}
					
					$this->lecho($i . ': Create #R' . $allocated_survey['rfg_id'], $log_file, $log_key);
				}
				else {
					$rfgQueueSource->commit();
					$this->lecho($i . ': Creation of #R' . $allocated_survey['rfg_id'] . ' skipped as it already exists in queue.', $log_file, $log_key);
				}
			}
			else {
				
				// if the project exists, let's update the metadata around it
				// note, because quotas/status are all tied to update, the status checks are also done here
				$command = 'rfg update --rfg_survey_id=' . $allocated_survey['rfg_id'];
				$rfgQueueSource = $this->RfgQueue->getDataSource();
				$rfgQueueSource->begin();
				$this->RfgQueue->create();
				$save = $this->RfgQueue->save(array('RfgQueue' => array(
					'rfg_survey_id' => $allocated_survey['rfg_id'],
					'command' => $command,
					'survey_id' => null
				)));
				if ($save) {
					$rfg_queue_id = $this->RfgQueue->getInsertId();
					$rfgQueueSource->commit();
					$sqs_batch[$rfg_queue_id] = $command;
					if (count($sqs_batch) == 10) {
						$this->Rfg->handle_queue($sqs, $sqs_settings['sqs.rfg.queue'], $sqs_batch);
					}
					$this->lecho($i . ': Update #R' . $allocated_survey['rfg_id'], $log_file, $log_key);
				}
				else {
					$rfgQueueSource->commit();
					$this->lecho($i . ': Update of #R' . $allocated_survey['rfg_id'] . ' skipped as it already exists in queue.', $log_file, $log_key);
				}
			}
			
		}
		
		// Process the last chunk less then 10
		if (isset($sqs_batch) && !empty($sqs_batch)) {
			$this->Rfg->handle_queue($sqs, $sqs_settings['sqs.rfg.queue'], $sqs_batch);
		}

		// close all projects that are no longer found in our allocation
		if (!isset($this->params['rfg_survey_id']) && !isset($this->params['project_id'])) {
			// find all projects that are open/staging/completed in our rfg set, but are not in our allocation: these are closed
			$this->Project->bindModel(array(
				'hasOne' => array(
					'RfgSurvey' => array(
						'className' => 'RfgSurvey',
						'foreignKey' => 'survey_id'
					)
				)
			));
			$all_projects = $this->Project->find('all', array(
				'fields' => array(
					'Project.id', 'RfgSurvey.rfg_survey_id'
				),
				'conditions' => array(
					'Project.group_id' => $this->rfg_group['Group']['id'],
					'Project.status' => array(PROJECT_STATUS_OPEN, PROJECT_STATUS_STAGING, PROJECT_STATUS_SAMPLING)
				)
			));
			if ($all_projects) {
				foreach ($all_projects as $all_project) {
					if (!in_array($all_project['RfgSurvey']['rfg_survey_id'], $allocated_survey_ids)) {
						$this->Project->create();
						$this->Project->save(array('Project' => array(
							'id' => $all_project['Project']['id'],
							'status' => PROJECT_STATUS_CLOSED,
							'active' => false,
							'ended' => date(DB_DATETIME)
						)), true, array('status', 'active', 'ended'));

						$this->ProjectLog->create();
						$this->ProjectLog->save(array('ProjectLog' => array(
							'project_id' => $all_project['Project']['id'],
							'type' => 'status.closed.rfg',
							'description' => 'Closed by RFG - not found in inventory'
						)));
						Utils::save_margin($all_project['Project']['id']);
						$this->lecho($i . ': Closed #R' . $allocated_survey['rfg_id'] . ' - no longer in inventory', $log_file, $log_key);
					}
				}
			}
		}
		$this->lecho('Completed process (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
	}
	
	// actually executes things
	public function worker() {
		$time_to_run = 12;
		ini_set('memory_limit', '1024M');
		$log_file = 'rfg.worker';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting worker', $log_file, $log_key);

		App::import('Vendor', 'sqs');
		$sqs_settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('sqs.access.key', 'sqs.access.secret', 'sqs.rfg.queue', 'rfg.active'),
				'Setting.deleted' => false
			)
		));
		if (isset($sqs_settings['rfg.active']) && $sqs_settings['rfg.active'] != 'true') {
			$this->lecho('RFG not active from settings.', $log_file, $log_key);
			return false;
		}
				
		$i = 0;
		$sqs = new SQS($sqs_settings['sqs.access.key'], $sqs_settings['sqs.access.secret']);
		while (true) {
			$results = $sqs->receiveMessage($sqs_settings['sqs.rfg.queue']);
			if (!empty($results['Messages'])) {
				$command = $results['Messages'][0]['Body'];
				$this->lecho('Starting ' . $command, $log_file, $log_key);
				$query = ROOT . '/app/Console/cake ' . $command;
				CakeLog::write('query_commands', $query);
				// run these synchronously
				exec($query, $output);
				$i++;

				$this->RfgQueue->getDataSource()->reconnect();
				$rfg_queue = $this->RfgQueue->find('first', array(
					'conditions' => array(
						'RfgQueue.command' => $command,
						'RfgQueue.executed is null'
					)
				));
				if ($rfg_queue) {
					$this->RfgQueue->create();
					$this->RfgQueue->save(array('RfgQueue' => array(
						'id' => $rfg_queue['RfgQueue']['id'],
						'executed' => date(DB_DATETIME)
					)), true, array('executed'));
					$rfg_queue_id = $rfg_queue['RfgQueue']['id'];
				}
				else {
					// gotta parse out the invite
					$rfg_survey_id = null;
					if (strpos($command, 'rfg create') !== false) {
						$rfg_survey_id = str_replace('rfg create', '', $command);
					}
					elseif (strpos($command, 'rfg update --rfg_survey_id=') !== false) {
						$rfg_survey_id = str_replace('rfg update --rfg_survey_id=', '', $command);
					}
					//elseif (strpos($command, 'rfg invite --rfg_survey_id ') !== false) {
						//$rfg_survey_id = str_replace('rfg invite --rfg_survey_id ', '', $command);
					//}
					if (!empty($rfg_survey_id)) {
						// if the Rfg queue doesn't exist, then write the value
						$rfgQueueSource = $this->RfgQueue->getDataSource();
						$rfgQueueSource->begin();
						$this->RfgQueue->create();
						$save = $this->RfgQueue->save(array('RfgQueue' => array(
							'amazon_queue_id' => $results['Messages'][0]['MessageId'],
							'fed_survey_id' => $rfg_survey_id,
							'command' => $command,
							'survey_id' => null,
							'executed' => date(DB_DATETIME)
						)));
						if ($save) {
							$rfg_queue_id = $this->RfgQueue->getInsertId();
						}
						$rfgQueueSource->commit();
					}
				}
				$sqs->deleteMessage($sqs_settings['sqs.rfg.queue'], $results['Messages'][0]['ReceiptHandle']);
				if (isset($rfg_queue_id)) {
					$this->lecho('Processed #' . $rfg_queue_id, $log_file, $log_key);
				}
				else {
					$this->lecho('Processed', $log_file, $log_key);
				}
				
				// end scripts after 5 minutes - a bit more graceful than time limit in PHP
				$time_diff = microtime(true) - $time_start;
				if ($time_diff > (60 * $time_to_run)) {
					return false;
				}
			}
			if (empty($results['Messages'])) {
				$this->lecho('Completed worker ' . $i . ' items (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
				break;
			}
		}
	}

	// create a project, given an rfg_survey_id
	public function create() {
		$log_file = 'rfg.create';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);

		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			$this->lecho('Completed create (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			// this method logs the errors already
			return false;
		}
		
		$this->lecho('Starting create ' . $this->args[0], $log_file, $log_key);
		$rfg_survey = $this->RfgSurvey->find('first', array(
			'conditions' => array(
				'RfgSurvey.rfg_survey_id' => $this->args[0]
			),
			'order' => 'RfgSurvey.id DESC'
		));
		if ($rfg_survey && $rfg_survey['RfgSurvey']['survey_id'] > 0) {
			$this->lecho('FAILED: #' . $this->args[0] . ' has already been created.', $log_file, $log_key);
			$this->lecho('Completed create (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			return false;
		}

		$command = "{ 'command' : 'livealert/targeting/1' , 'rfg_id' : '" . $this->args[0] . "' }";
		$result = $this->execute_api($command, $log_file, $log_key);
		if (!$result) {
			$this->lecho('Completed process (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			return false;
		}
		
		$rfg_project = $result['response'];
		$command = "{ 'command' : 'livealert/stats/1' , 'rfg_id' : '" . $this->args[0] . "' }";
		$result = $this->execute_api($command, $log_file, $log_key);
		if (!$result) {
			$this->lecho('Completed process (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			return false;
		}

		$rfg_project['stats'] = $result['response'];
		
		// Get the survey link
		$command = "{ 'command' : 'livealert/createLink/1' , 'rfg_id' : '" . $this->args[0] . "' }";
		$result = $this->execute_api($command, $log_file, $log_key);
		if (!$result || empty($result['response']['link'])) {
			$this->lecho('Completed process (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			$this->RfgSurvey->create();
			$this->RfgSurvey->save(array('RfgSurvey' => array(
				'survey_id' => '0',
				'rfg_survey_id' => $this->args[0],
				'status' => 'failed.link'
			)));
			return false;
		}
		
		$client_link = $result['response']['link']  . '&rid={{ID}}';

		// extract partner rate, client, rate and award amounts
		$payouts = $this->Rfg->payout($rfg_project['cpi']);
		$overall_quota = $this->Rfg->quota($rfg_project);
		$ir = $this->Rfg->ir($rfg_project);
		$loi = $this->Rfg->loi($rfg_project['estimatedLOI']);
		$save = false;
		$projectSource = $this->Project->getDataSource();
		$projectSource->begin();
		$this->Project->create();
		$project_data = array('Project' => array(
			'prj_name' => $rfg_project['title'],
			'client_id' => $this->rfg_client['Client']['id'],
			'date_created' => date(DB_DATETIME),
			'bid_ir' => $ir,
			'client_rate' => $payouts['client_rate'],
			'partner_rate' => $payouts['partner_rate'],
			'user_payout' => $payouts['partner_rate'],
			'quota' => $overall_quota,
			'est_length' => $loi,
			'group_id' => $this->rfg_group['Group']['id'],
			'status' => PROJECT_STATUS_OPEN,
			'client_project_id' => $rfg_project['rfg_id'],
			'singleuse' => true,
			'touched' => date(DB_DATETIME),
			'country' => $rfg_project['country'],
			'language' => 'en',
			'survey_name' => $rfg_project['title'],
			'award' => $payouts['award'],
			'active' => false, // after qualifications load, we'll activate it
			'dedupe' => true,
			'client_survey_link' => $client_link,
			'description' => 'Survey for you!'
		));
		if ($this->Project->save($project_data)) {
			$project_id = $this->Project->getInsertId();
			$projectSource->commit();
			
			$data = array('RfgSurvey' => array(
				'survey_id' => $project_id,
				'rfg_survey_id' => $rfg_project['rfg_id'],
				'current_quota' => $overall_quota,
				'status' => FEDSURVEY_CREATED,
			));
			if (!empty($rfg_survey)) {
				$data['RfgSurvey']['id'] = $rfg_survey['RfgSurvey']['id'];
			}
			
			$this->RfgSurvey->create();
			$this->RfgSurvey->save($data);

			// add mintvine as a partner
			$this->Project->SurveyPartner->create();
			$this->Project->SurveyPartner->save(array('SurveyPartner' => array(
				'survey_id' => $project_id,
				'partner_id' => $this->mv_partner['Partner']['id'],
				'rate' => $payouts['partner_rate'],
				'complete_url' => 'https://' . HOSTNAME_WWW . '/surveys/complete/{{ID}}/',
				'nq_url' => 'https://' . HOSTNAME_WWW . '/surveys/nq/{{ID}}/',
				'oq_url' => 'https://' . HOSTNAME_WWW . '/surveys/oq/{{ID}}/',
				'pause_url' => 'https://' . HOSTNAME_WWW . '/surveys/paused/',
				'fail_url' => 'https://' . HOSTNAME_WWW . '/surveys/sec/{{ID}}/',
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

			$this->lecho('[SUCCESS] #R' . $rfg_project['rfg_id'] . ' created successfully (#' . $project_id . ')', $log_file, $log_key);
			// run qualifications on this project
			$this->qualifications($rfg_project);

			// launch this project 
			$this->Project->bindModel(array('hasOne' => array(
				'RfgSurvey' => array(
					'className' => 'RfgSurvey',
					'foreignKey' => 'survey_id'
				)
			)));
			$project = $this->Project->find('first', array(
				'conditions' => array(
					'Project.id' => $project_id
				)
			));
			$this->launchProject($project, $rfg_project, $log_file, $log_key); 
		}
		
		$this->lecho('Completed create (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
	}
	
	// update a project, given an rfg_survey_id
	/*
	  specific order of operations for updating a project, in order of speed
	  (1) update core metadata (not status)
	  (2) update quota
	  (3) check status
	  (4) update qualifications
	  (5) if closed/staging, check status again
	 */
	public function update() {
		$log_file = 'rfg.update';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting update', $log_file, $log_key);
		if (!isset($this->params['rfg_survey_id']) && !isset($this->params['project_id'])) {
			$this->lecho('FAILED: You need at least rfg_project_id or project_id set to update', $log_file, $log_key);
			return false;
		}
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			// this method logs the errors already
			return false;
		}
		if (isset($this->params['rfg_survey_id'])) {
			$rfg_survey = $this->RfgSurvey->find('first', array(
				'conditions' => array(
					'RfgSurvey.rfg_survey_id' => $this->params['rfg_survey_id']
				),
				'order' => 'RfgSurvey.id DESC'
			));

			if (empty($rfg_survey['RfgSurvey']['survey_id'])) {
				$this->lecho('That Rfg project has not been imported.', $log_file, $log_key);
				return false;
			}
			
			$project_id = $rfg_survey['RfgSurvey']['survey_id'];
		}
		else {
			$project_id = $this->params['project_id'];
		}

		$this->Project->bindModel(array(
			'hasOne' => array(
				'RfgSurvey' => array(
					'className' => 'RfgSurvey',
					'foreignKey' => 'survey_id'
				)
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
		if ($project['RfgSurvey']['status'] == 'skipped.adhoc' || empty($project['Project']['group_id']) || $project['Project']['group_id'] == $mintvine_group['Group']['id']) {
			return false;
		}

		// todo: this should be status-dependent
		// todo: sorting/organizing by status?
		// sanity check on touched updates - don't update projects that have already been updated within 10 minutes
		if (!is_null($project['Project']['touched']) && strtotime('-10 minutes') < strtotime($project['Project']['touched'])) {
			$this->lecho('FAILED: Updated within last 10 minutes: ' . date('H:i:A', strtotime('-10 minutes')) . ' vs ' . date('H:i:A', strtotime($project['Project']['touched'])), $log_file, $log_key);
			return false;
		}
		$command = "{ 'command' : 'livealert/targeting/1' , 'rfg_id' : '" . $project['RfgSurvey']['rfg_survey_id'] . "' }";
		$result = $this->execute_api($command, $log_file, $log_key);
		if (!$result) {
			$this->lecho('Completed process (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			return false;
		}

		$rfg_project = $result['response'];
		$command = "{ 'command' : 'livealert/stats/1' , 'rfg_id' : '" . $project['RfgSurvey']['rfg_survey_id'] . "' }";
		$result = $this->execute_api($command, $log_file, $log_key);
		if (!$result) {
			$this->lecho('Completed process (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			return false;
		}
		
		$rfg_project['stats'] = $result['response'];
		$payouts = $this->Rfg->payout($rfg_project['cpi']);
		$overall_quota = $this->Rfg->quota($rfg_project);
		
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

		$ir = $this->Rfg->ir($rfg_project);
		$loi = $this->Rfg->loi($rfg_project['estimatedLOI']);
		$project_data = array('Project' => array(
			'id' => $project['Project']['id'],
			'client_rate' => $payouts['client_rate'],
			'award' => $payouts['award'],
			'client_id' => $this->rfg_client['Client']['id'],
			'prj_name' => $rfg_project['title'],
			'bid_ir' => $ir,
			'partner_rate' => $payouts['partner_rate'],
			'user_payout' => $payouts['partner_rate'],
			'quota' => $overall_quota,
			'est_length' => $loi,
			'language' => 'en',
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
			$this->lecho('[SUCCESS] Project #' . $project['Project']['id'] . ' updated with new fields', $log_file, $log_key);
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

			$this->lecho('[SKIPPED] Project #' . $project['Project']['id'] . ' does not need to be updated', $log_file, $log_key);
		}

		$project_status = $project['Project']['status'];
		// if this project is open, and Rfg is reporting a closed project, then close it
		// no need to check survey.active
		if (!$project['Project']['ignore_autoclose'] && in_array($project['Project']['status'], array(PROJECT_STATUS_OPEN, PROJECT_STATUS_SAMPLING))) {
			$still_open = true;
			if ($this->Rfg->is_closed($rfg_project)) {
				$this->Rfg->close_project($project, 'status.closed.rfg', 'Closed by Rfg');
				$still_open = false;
				$this->lecho('[SUCCESS] Project #' . $project['Project']['id'] . ' closed by Rfg', $log_file, $log_key);
			}
			// after update, evaluate the project with some internal mechanisms
			elseif (!$this->checkInternalRfgRules($rfg_project, $log_file, $log_key)) {
				$this->Rfg->close_project($project, 'status.closed.rules', 'Failed our internal filter rules');
				$still_open = false;
			}
			else {
				// run a performance-based check
				$mvapi = new HttpSocket(array(
					'timeout' => 15,
					'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
				));
				$mvapi->configAuth('Basic', $this->settings['api.mintvine.username'], $this->settings['api.mintvine.password']);
				$results = $mvapi->post($this->settings['hostname.api'] . '/surveys/test_survey_status/' . $project['Project']['id']);
				$response = json_decode($results['body'], true);

				if ($results['close_project']) {
					$still_open = false;
					$this->lecho('[CLOSED] #' . $project['Project']['id'] . ' Closed for performance reaasons', $log_file, $log_key);
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
				
				if ($project['Project']['status'] == PROJECT_STATUS_SAMPLING) {
					// first, check the performance of the sampling project itself; b/c it's specific to rfg we keep it 
					// here insead of the API
					// check to see if clicks have exceeded with no completes
					if ($survey_visit_cache['SurveyVisitCache']['click'] >= $this->settings['rfg.sample_threshold'] && $survey_visit_cache['SurveyVisitCache']['complete'] == 0) {
						$this->Rfg->close_project($project, 'status.closed.sample', 'Closed with ' . $survey_visit_cache['SurveyVisitCache']['click'] . ' clicks and no complete', true); // last param tells to force the ended date as of now.
					}
					// if we have completes, test to see if we should close it
					elseif ($survey_visit_cache['SurveyVisitCache']['complete'] > 0) {
						$actual_ir = round($survey_visit_cache['SurveyVisitCache']['complete'] / $survey_visit_cache['SurveyVisitCache']['click'], 2) * 100;
						if ($actual_ir < $this->settings['fulcrum.ir_cutoff']) {
							if ($project['SurveyVisitCache']['click'] >= $this->settings['fulcrum.sample_threshold']) {
								$this->Rfg->close_project($project, 'status.closed.ir', 'IR ' . $actual_ir . '% with ' . $survey_visit_cache['SurveyVisitCache']['click'] . ' clicks', true); // last param tells to force the ended date as of now.
							}
						}
						// move project to open from sampling and do a full launch if the IR on sampling looks good
						else {
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

							$this->runQuery($project, 'full');
						}
					}
				}
			}
		}
		// if this project is closed, then try to reopen it
		elseif (!$project['Project']['ignore_autoclose'] && $project['Project']['status'] == PROJECT_STATUS_CLOSED) {
			// if this project is now available and open
			if (!$this->Rfg->is_closed($rfg_project) && $this->checkInternalRfgRules($rfg_project, $log_file, $log_key)) {
				//todo: check api status

				$last_close_status = $this->ProjectLog->find('first', array(
					'conditions' => array(
						'ProjectLog.project_id' => $project['Project']['id'],
						'ProjectLog.type LIKE' => 'status.closed%'
					),
					'order' => 'ProjectLog.id DESC'
				));
				// if we didn't close it for auto rules
				if (!$last_close_status || $last_close_status['ProjectLog']['type'] != 'status.closed.auto') {
					$mvapi = new HttpSocket(array(
						'timeout' => 15,
						'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
					));
					$mvapi->configAuth('Basic', $this->settings['api.mintvine.username'], $this->settings['api.mintvine.password']);
					$results = $mvapi->post($this->settings['hostname.api'] . '/surveys/test_survey_status/' . $project['Project']['id']);
					$response = json_decode($results['body'], true);

					if ($response['open_project']) {
						$project_log = $this->ProjectLog->find('first', array(
							'conditions' => array(
								'ProjectLog.project_id' => $project['Project']['id'],
								'ProjectLog.type' => 'status.closed.rfg'
							)
						));
						// If the project was explicitly closed by Lucid, re-open if it's now open
						if ($project_log) {
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
								'status' => PROJECT_STATUS_OPEN,
								'ended' => null,
								'active' => true,
							)), true, array('status', 'active', 'ended'));

							$this->ProjectLog->create();
							$this->ProjectLog->save(array('ProjectLog' => array(
								'project_id' => $project['Project']['id'],
								'type' => 'status.opened.rfg',
								'description' => 'Reopened by Rfg'
							)));
							$project_status = PROJECT_STATUS_OPEN;
						}
					}
				}
			}
		}
		elseif (!$project['Project']['ignore_autoclose'] && $project['Project']['status'] == PROJECT_STATUS_STAGING) {
			$this->launchProject($project, $rfg_project, $log_file, $log_key);
		}

		// write a value that stores the last invite time
		$project_option = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.project_id' => $project['Project']['id'],
				'ProjectOption.name' => 'rfg.lastrun.updated'
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
				'name' => 'rfg.lastrun.updated',
				'value' => date(DB_DATETIME),
				'project_id' => $project['Project']['id']
			)));
		}

		// update qualifications as last step
		if (in_array($project_status, array(PROJECT_STATUS_OPEN, PROJECT_STATUS_SAMPLING))) {
			$this->qualifications($rfg_project);
		}
		$this->lecho('Completed update (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
	}
	
	protected function launchProject($project, $rfg_project, $log_file, $log_key) {
		if ($this->settings['rfg.autolaunch'] == 'true' && $this->checkInternalRfgRules($rfg_project, $log_file, $log_key)) {

			// reload the parent to see if there was 100% qualifications matching to the query engine
			$qualifications_match = $this->Project->field('qualifications_match', array('Project.id' => $project['Project']['id']));

			// should we be a "fast" follower or the bold leader? note: this should be tweaked when sampling
			if ($qualifications_match && 
				isset($rfg_project['stats']['projectCR']) && 
				$rfg_project['stats']['projectCR'] >= $this->settings['rfg.ir_cutoff']) {
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
				$this->runQuery($project, 'full');
				$this->lecho('[LAUNCHED] #' . $project['Project']['id'] . ' (#R' . $rfg_project['rfg_id'] . ')', $log_file, $log_key);
			}
		}
	}
	
	protected function runQuery($project, $launch_type = 'full') {
		// dev instances should not actually execute
		if (defined('IS_DEV_INSTANCE') && IS_DEV_INSTANCE === true) {
			return true;
		}
		
		// from royk: temporarily disable invites altogether for RFG
		return true; 
		
		$this->Query->bindModel(array('hasOne' => array('QueryStatistic')));
		$queries = $this->Query->find('all', array(
			'contain' => array(
				'QueryStatistic'
			),
			'conditions' => array(
				'Query.survey_id' => $project['Project']['id'],
				'Query.parent_id' => '0'
			)
		));
		if (!$queries) {
			return false;
		}

		$sent = false;
		foreach ($queries as $query) {
			if ($launch_type == 'sample') {
				$count = $this->Query->QueryHistory->find('count', array(
					'conditions' => array(
						'QueryHistory.query_id' => $query['Query']['id'],
						'QueryHistory.type' => 'sent',
					)
				));

				// we run sample query only once, to avoid users flooded with unknown ir project invitations
				if ($count > 0) {
					$message = 'Skipped ' . $project['Project']['id'] . ' because query history exists';
					echo $message . "\n";
					CakeLog::write('rfg.auto', $message);
					continue;
				}
			}

			$results = QueryEngine::execute(json_decode($query['Query']['query_string'], true));
			if ($results['count']['total'] == 0) {
				$message = 'Skipped ' . $project['Project']['id'] . ' because query has no users';
				echo $message . "\n";
				CakeLog::write('rfg.auto', $message);
				continue;
			}

			$survey_reach = 0;
			if ($launch_type == 'sample') {
				$setting = $this->Setting->find('first', array(
					'conditions' => array(
						'Setting.name' => 'rfg.sample_size',
						'Setting.deleted' => false
					)
				));
				if (!$setting) { // set the default if not found.
					$setting = array('Setting' => array('value' => 50));
				}

				$survey_reach = ($results['count']['total'] < $setting['Setting']['value']) ? $results['count']['total'] : $setting['Setting']['value'];
			}
			else {
				$survey_reach = MintVine::query_amount($project, $results['count']['total'], $query);
			}

			if ($survey_reach == 0) {
				$message = 'Skipped ' . $project['Project']['id'] . ' because query has no quota left';
				echo $message . "\n";
				CakeLog::write('rfg.auto', $message);
				continue;
			}

			if ($survey_reach > 1000) {
				$survey_reach = 1000;
			}

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
			$str_sample = ($launch_type == 'sample') ? ' 1' : '';
			$query_str = ROOT . '/app/Console/cake query create_queries ' . $query['Query']['survey_id'] . ' ' . $query['Query']['id'] . ' ' . $query_history_id . ' ' . $survey_reach . $str_sample;
			CakeLog::write('query_commands', $query_str);
			// run these synchronously
			exec($query_str, $output);
			var_dump($output);
			$message = 'Query executed: ' . $query_str;
			echo $message . "\n";
			CakeLog::write('rfg.auto', $message);
			$sent = true;
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'query.executed',
				'query_id' => $query['Query']['id'],
				'description' => 'Total sent : ' . $survey_reach . ', ' . $launch_type . ' launch'
			)));
		}

		return $sent;
	}

	// Output a given Rfg survey qualifications, quotas and other info
	// arg[0] = rfg_survey_id
	public function debug() {
		$log_file = 'rfg.debug';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			return false;
		}
		
		if (isset($this->args[0]) && !empty($this->args[0])) {
			$command = "{ 'command' : 'livealert/targeting/1' , 'rfg_id' : '" . $this->args[0] . "' }";
		}
		else {
			$command = "{ 'command' : 'livealert/inventory/1' }";
		}
		$result = $this->execute_api($command, $log_file, $log_key);
		print_r($result);
		if (defined('IS_DEV_INSTANCE') && IS_DEV_INSTANCE === true) {
			CakeLog::write($this->args[0], print_r($result, true));
		}
	}

	public function qualifications($rfg_project = false) {
		ini_set('memory_limit', '1024M');
		$log_file = 'rfg.qualifications';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		
		if (isset($rfg_project['rfg_id'])) {
			$rfg_id = $rfg_project['rfg_id'];
		}
		elseif (isset($this->params['rfg_survey_id'])) {
			$rfg_id = $this->params['rfg_survey_id'];
		}
		else {
			$this->lecho('Please send in rfg_survey_id', $log_file, $log_key);
			return false;
		}
		
		$this->lecho('Starting qualifications', $log_file, $log_key);
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			// this method logs the errors already
			$this->lecho('Completed qualifications (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			return false;
		}
		
		$desiredLanguage = 'en-US';
		$rfgSurvey = $this->RfgSurvey->find('first', array(
			'conditions' => array(
				'RfgSurvey.rfg_survey_id' => $rfg_id
			),
			'order' => 'RfgSurvey.id desc'
		));
		
		if (!$rfgSurvey) {
			$this->lecho('Rfg survey not found. #'.  $rfg_id, $log_file, $log_key);
			return false;
		}

		$this->Project->bindModel(array('hasOne' => array(
			'RfgSurvey' => array(
				'className' => 'RfgSurvey',
				'foreignKey' => 'survey_id'
			)
		)));
		$project = $this->Project->find('first', array(
			'contain' => array('RfgSurvey'),
			'conditions' => array(
				'Project.id' => $rfgSurvey['RfgSurvey']['survey_id'],
			)
		));
		if (!$project) {
			$this->lecho('MV Project not found. #' . $rfg_id, $log_file, $log_key);
			return false;
		}

		if ($rfg_project == false) { // rfg_project is only passed in, when another rfg command call qualifications.
			$command = "{ 'command' : 'livealert/targeting/1' , 'rfg_id' : '" . $rfg_id . "' }";
			$rfg_project = $this->execute_api($command, $log_file, $log_key);
			$rfg_project = $rfg_project['response'];
			if (!$rfg_project) {
				$this->lecho('Completed qualifications (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
				return false;
			}
		}
		
		if (empty($rfg_project['datapoints'])) {
			$this->lecho('Data points (qualifications) not found for ' . $rfg_id, $log_file, $log_key);
			$this->lecho('Completed qualifications (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			return false;
		}
		
		// Save project qualification raw if stats available. stats only available if qualifications() is called from create() or update()
		if (isset($rfg_project['stats'])) {
			$this->RfgSurvey->create();
			$this->RfgSurvey->save(array('RfgSurvey' => array(
				'id' => $rfgSurvey['RfgSurvey']['id'],
				'raw' => json_encode($rfg_project)
			)), true, array('raw'));
		}

		$desired_language = 'en-US';
		$parent_query_params = array('country' => $rfg_project['country']);
		$has_prescreener = false;
		$prescreen_questions = array();
		$desktop = $tablet = $mobile = true;
		$parent_query_match = true;
		foreach ($rfg_project['datapoints'] as $target) {
			if ($target['name'] == 'Computer Check') {
				/* Desktop - 1, Laptop - 2, Phone/Smartphone - 3, Tablet - 4, Other - 5 */
				$desktop = $tablet = $mobile = false;
				$choices = Set::extract('/choice', $target['values']);
				if (in_array(1, $choices) || in_array(2, $choices)) {
					$desktop = true;
				}
				elseif (in_array(3, $choices)) {
					$mobile = true;
				}
				elseif (in_array(4, $choices)) {
					$tablet = true;
				}
			}
			elseif (RfgMappings::is_mapped($target['name'])) {
				if (!$this->Rfg->mapping($parent_query_params, $target)) {
					$parent_query_match = false;
				}
			}
			// Handle prescreeners if any
			else {
				$parent_query_match = false;
				$rfg_question = $this->RfgQuestion->find('first', array(
					'conditions' => array(
						'rfg_name' => $target['name']
					)
				));
				if (!$rfg_question) {
					$datapoint = $this->datapoint($target['name'], $log_file, $log_key);
					$rfg_question_id = $this->Rfg->save_question($datapoint);
					if (!$rfg_question_id) {
						$this->lecho('Question "'.$target['name'].'" not saved for MV project ' . $project['Project']['id'], $log_file, $log_key);
						continue;
					}
					
					$rfg_question = $this->RfgQuestion->find('first', array(
						'conditions' => array(
							'rfg_name' => $target['name']
						)
					));
				}
				
				$answers = array();
				$has_dqs = false;
				$targets = Set::extract('/choice', $target['values']);
				foreach ($rfg_question['RfgAnswer'] as $answer) {
					if (in_array($answer['key'], $target)) {
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
					$prescreen_questions[] = $rfg_question['RfgQuestion']['question'];
					$prescreener = $this->Prescreener->find('first', array(
						'conditions' => array(
							'survey_id' => $project['Project']['id'],
							'question' => $rfg_question['RfgQuestion']['question']
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
							'description' => $rfg_question['RfgQuestion']['question']
						)));
					}

					$this->Prescreener->save(array('Prescreener' => array(
						'survey_id' => $project['Project']['id'],
						'question' => $rfg_question['RfgQuestion']['question'],
						'answers' => implode("\n", $answers)
					)));
					$has_prescreener = true;
				}
			}
		}
		
		if (false && $prescreen_questions) {
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'prj_description' => implode("\n", $prescreen_questions)
			)), true, array('prj_description'));
		}
		
		$this->Project->create();
		$this->Project->save(array('Project' => array(
			'id' => $project['Project']['id'],
			'desktop' => $desktop,
			'mobile' => $mobile,
			'tablet' => $tablet,
			'prescreen' => false // force no more prescreener
		)), true, array('desktop', 'mobile', 'tablet', 'prescreen'));

		// Handle quotas qualifications and filter queries.
		if (isset($rfg_project['quotas']) && !empty($rfg_project['quotas'])) {
			foreach ($rfg_project['quotas'] as $quota_key => $quota) {
				$query_params = array(); // to be sent to query engine
				$query_match = true;
				$query_params['country'] = $rfg_project['country'];
				foreach ($quota['datapoints'] as $target) {
					if (!RfgMappings::is_mapped($target['name'])) {
						$query_match = false;
						continue;
					}
					
					if (!$this->Rfg->mapping($query_params, $target)) {
						$query_match = false;
					}
				}

				if (!empty($query_params)) {

					// Add master params to filter query if not present
					foreach ($parent_query_params as $key => $value) {
						if (!isset($query_params[$key])) {
							$query_params[$key] = $value;
						}
					}

					$query_id = $this->getQuery($project, $query_params, array('key' => $quota_key + 1, 'completes_left' => (isset($quota['completesLeft'])) ? $quota['completesLeft'] : '0'));
					
					// Determine if this query is a complete match
					$this->Query->create();
					$this->Query->save(array('Query' => array(
						'id' => $query_id,
						'qualifications_match' => (!$parent_query_match) ? false : $query_match,
						'modified' => false
					)), true, array('qualifications_match', 'modified'));
				}
			}
		}
		elseif (!empty($parent_query_params)) { // Make the datapoint as a standalone query, if no quotas found
			$query_id = $this->getQuery($project, $parent_query_params);
		}
		
		$this->Project->create();
		$this->Project->save(array('Project' => array(
			'id' => $project['Project']['id'],
			'qualifications_match' => $parent_query_match
		)), true, array('qualifications_match'));
			
		// If any query is created
		if (isset($query_id)) {
			$this->RfgSurvey->create();
			$this->RfgSurvey->save(array('RfgSurvey' => array(
				'id' => $project['RfgSurvey']['id'],
				'status' => FEDSURVEY_QUALIFICATIONS_LOADED
			)), true, array('status'));

			$status = FEDSURVEY_QUALIFICATIONS_LOADED;

			if ($project['RfgSurvey']['status'] == FEDSURVEY_CREATED) {
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'active' => true
				)), true, array('active'));
			}
		}
		else {
			$this->RfgSurvey->create();
			$this->RfgSurvey->save(array('RfgSurvey' => array(
				'id' => $project['RfgSurvey']['id'],
				'status' => FEDSURVEY_QUALIFICATIONS_EMPTY
			)), true, array('status'));
			$status = FEDSURVEY_QUALIFICATIONS_EMPTY;
		}

		$this->lecho('[SUCCESS] Qualifications for #' . $project['Project']['id'] . ': ' . $status, $log_file, $log_key);
		$this->lecho('Completed qualifications (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
	}
	
	// arg $quota = array('key', 'completes_left')
	protected function getQuery($project, $query_params, $quota = null) {
		if ($quota) {
			$query_name = '#' . $project['RfgSurvey']['rfg_survey_id'] . ' Quota #' . $quota['key'];
		}
		else {
			$query_name = '#' . $project['RfgSurvey']['rfg_survey_id'] . ' Qualifications';
		}
		
		$query = $this->Query->find('first', array(
			'conditions' => array(
				'Query.query_name' => $query_name,
				'Query.survey_id' => $project['Project']['id']
			),
			'order' => 'Query.id DESC' // multiple queries can exist with same name: retrieve the last one
		));

		if ($query) {
			$query_id = $query['Query']['id'];
		}

		// If we've matched against hispanic, then add the ethnicity values
		if (isset($query_params['hispanic']) && !empty($query_params['hispanic'])) {
			if (isset($query_params['ethnicity']) && !in_array(4, $query_params['ethnicity'])) {
				$query_params['ethnicity'][] = 4; // hardcode hispanics
			}
			elseif (!isset($query_params['ethnicity'])) {
				$query_params['ethnicity'] = array(4); // hardcode hispanics
			}
		}

		// Remove duplicates.
		if (isset($query_params['ethnicity'])) {
			$query_params['ethnicity'] = array_values(array_unique($query_params['ethnicity']));
		}

		$create_new_query = false;
		if (!$query) {
			$create_new_query = true;
		}
		elseif ($query && json_encode($query_params) != $query['Query']['query_string']) {
			$create_new_query = true;
			$query_history_ids = Set::extract('/QueryHistory/id', $query);
			$this->Query->delete($query_id);
			foreach ($query_history_ids as $query_history_id) {
				$this->Query->QueryHistory->delete($query_history_id);
			}

			$survey_users = $this->SurveyUser->find('all', array(
				'fields' => array('id', 'user_id', 'survey_id'),
				'recursive' => -1,
				'conditions' => array(
					'SurveyUser.survey_id' => $project['Project']['id'],
					'SurveyUser.query_history_id' => $query_history_ids
				)
			));

			$str = '';
			if ($survey_users) {
				foreach ($survey_users as $survey_user) {
					$count = $this->SurveyUserVisit->find('count', array(
						'conditions' => array(
							'SurveyUserVisit.user_id' => $survey_user['SurveyUser']['user_id'],
							'SurveyUserVisit.survey_id' => $survey_user['SurveyUser']['survey_id'],
						)
					));

					if ($count < 1) {
						$this->SurveyUser->delete($survey_user['SurveyUser']['id']);
					}
				}

				$str = 'Survey users deleted.';
			}

			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'query.deleted',
				'description' => 'Query.id ' . $query_id . ' deleted, because qualifications updated. ' . $str
			)));
		}
		elseif ($quota) { // Check if quota has changed
			$query_statistic = $this->QueryStatistic->find('first', array(
				'conditions' => array(
					'QueryStatistic.query_id' => $query_id
				)
			));
			
			$query_quota = $quota['completes_left'] + $query_statistic['QueryStatistic']['completes'];
			if ($query_statistic && $query_statistic['QueryStatistic']['quota'] != $query_quota) {
				$this->QueryStatistic->create();
				$this->QueryStatistic->save(array('QueryStatistic' => array(
					'id' => $query_statistic['QueryStatistic']['id'],
					'quota' => ($query_quota > 0) ? $query_quota : '0',
					'closed' => !is_null($quota) && empty($quota['completes_left']) ? date(DB_DATETIME) : null
				)), true, array('quota', 'closed'));
			}
		}

		if ($create_new_query) {
			if (count($query_params) == 1 && key($query_params) == 'country') {
				$total = FED_MAGIC_NUMBER; // hardcode this because of memory issues
			}
			else {
				$results = QueryEngine::execute($query_params);
				$total = $results['count']['total'];
			}

			$querySource = $this->Query->getDataSource();
			$querySource->begin();
			$this->Query->create();
			$save = false;
			$save = $this->Query->save(array('Query' => array(
				'parent_id' => 0,
				'query_name' => $query_name,
				'query_string' => json_encode($query_params),
				'survey_id' => $project['Project']['id']
			)));
			if ($save) {
				$query_id = $this->Query->getInsertId();
				$querySource->commit();
				$this->Query->QueryHistory->create();
				$this->Query->QueryHistory->save(array('QueryHistory' => array(
					'query_id' => $query_id,
					'item_id' => $project['Project']['id'],
					'item_type' => TYPE_SURVEY,
					'type' => 'created',
					'total' => $total
				)));

				// this is a query filter
				if (!is_null($quota)) {
					$this->QueryStatistic->create();
					$this->QueryStatistic->save(array('QueryStatistic' => array(
						'query_id' => $query_id,
						'quota' => ($quota['completes_left'] > 0) ? $quota['completes_left'] : '0',
					)));
				}

				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'query.created',
					'description' => 'Query.id ' . $query_id . ' created'
				)));

				// If this query was already sent, then run this new query too
				if (isset($survey_users) && !empty($survey_users)) {
					$this->Query->bindModel(array('hasOne' => array('QueryStatistic')));
					$query = $this->Query->find('first', array(
						'contain' => array(
							'QueryStatistic'
						),
						'conditions' => array(
							'Query.id' => $query_id,
						)
					));

					// don't full launch if its a sampling project
					if ($project['Project']['status'] == PROJECT_STATUS_SAMPLING) {
						$setting = $this->Setting->find('first', array(
							'conditions' => array(
								'Setting.name' => 'rfg.sample_size',
								'Setting.deleted' => false
							)
						));
						if (!$setting) { // set the default if not found.
							$setting = array('Setting' => array('value' => 50));
						}

						$survey_reach = ($total < $setting['Setting']['value']) ? $total : $setting['Setting']['value'];
					}
					else {
						$survey_reach = MintVine::query_amount($project, $total, $query);
					}

					if ($survey_reach == 0) {
						$message = 'Skipped ' . $project['Project']['id'] . ' because query has no quota left';
						echo $message . "\n";
					}

					if ($survey_reach > 1000) {
						$survey_reach = 1000;
					}

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
					$query_str = ROOT . '/app/Console/cake query create_queries ' . $query['Query']['survey_id'] . ' ' . $query['Query']['id'] . ' ' . $query_history_id . ' ' . $survey_reach;
					CakeLog::write('query_commands', $query_str);
					// run these synchronously
					exec($query_str, $output);
					var_dump($output);
					$message = 'Query executed: ' . $query_str;
					echo $message . "\n";

					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $project['Project']['id'],
						'type' => 'query.executed',
						'query_id' => $query['Query']['id'],
						'description' => 'New query executed, total sent : ' . $survey_reach
					)));
				}
			}
			else {
				$querySource->commit();
			}

			$this->RfgSurvey->create();
			$this->RfgSurvey->save(array('RfgSurvey' => array(
				'id' => $project['RfgSurvey']['id'],
				'total' => $total
			)), true, array('total'));
		}
		
		return $query_id;
	}

	// goes through and figures out which projects to do follow-up sends to
	public function sends() {
		ini_set('memory_limit', '1024M');
		$log_file = 'rfg.sends';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting Sends.', $log_file, $log_key);

		// load settings, client, group, etc. 
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			return false;
		}
		
		if ($this->settings['rfg.active'] != 'true') {
			$this->lecho('RFG not active from settings.', $log_file, $log_key);
			return false;
		}

		if (isset($this->params['rfg_survey_id']) && isset($this->params['project_id'])) {
			$this->lecho('FAILED: You cannot define both a Rfg and MintVine project ID', $log_file, $log_key);
			$this->lecho('Completed process (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			return false;
		}

		// validate the project_id
		if (isset($this->params['project_id'])) {
			$target_rfg_survey = $this->RfgSurvey->find('first', array(
				'conditions' => array(
					'RfgSurvey.survey_id' => $this->params['project_id']
				)
			));
			if (!$target_rfg_survey) {
				$this->lecho('FAILED: Failed to find project #' . $this->params['project_id'], $log_file, $log_key);
				$this->lecho('Completed process (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
				return false;
			}
		}
		// validate the project_id
		if (isset($this->params['rfg_survey_id'])) {
			$target_rfg_survey = $this->RfgSurvey->find('first', array(
				'conditions' => array(
					'RfgSurvey.rfg_survey_id' => $this->params['rfg_survey_id']
				)
			));
			if (!$target_rfg_survey) {
				$this->lecho('FAILED: Failed to find Lucid project #' . $this->params['project_id'], $log_file, $log_key);
				$this->lecho('Completed process (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
				return false;
			}
		}

		$this->Project->bindModel(array(
			'hasOne' => array(
				'RfgSurvey' => array(
					'className' => 'RfgSurvey',
					'foreignKey' => 'survey_id'
				)
			)
		));
		if (isset($target_rfg_survey)) {
			$projects = $this->Project->find('all', array(
				'conditions' => array(
					'Project.id' => $target_rfg_survey['RfgSurvey']['survey_id'],
					'Project.status' => array(PROJECT_STATUS_OPEN),
					'Project.group_id' => $this->rfg_group['Group']['id'],
				)
			));
		}
		else {
			$projects = $this->Project->find('all', array(
				'conditions' => array(
					'Project.status' => array(PROJECT_STATUS_OPEN),
					'Project.group_id' => $this->rfg_group['Group']['id'],
				)
			));
		}
		if (!$projects) {
			$this->lecho('FAILED: No projects found to resend', $log_file, $log_key);
			return false;
		}

		App::import('Vendor', 'sqs');
		$sqs_settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('sqs.access.key', 'sqs.access.secret', 'sqs.rfg.queue'),
				'Setting.deleted' => false
			)
		));
		$i = 0;
		$sqs = new SQS($sqs_settings['sqs.access.key'], $sqs_settings['sqs.access.secret']);

		$sqs_batch = array();
		foreach ($projects as $project) {
			$queries = $this->Query->find('first', array(
				'conditions' => array(
					'Query.survey_id' => $project['Project']['id']
				)
			));
			$total = $sent = 0;
			foreach ($queries['QueryHistory'] as $query_history) {
				if ($query_history['type'] == 'created') {
					$total = $query_history['total'];
				}
				elseif ($query_history['type'] == 'sent') {
					$sent = $query_history['count'] + $sent;
				}
			}
			$count = $this->SurveyUser->find('count', array(
				'conditions' => array(
					'SurveyUser.survey_id' => $project['Project']['id']
				)
			));
			// choose the larger number; actual sends vs. projected in query engine
			$sent = max(array($sent, $count));
			$total = round(0.96 * $total); // we consider within 96% "done" for sends
			if ($sent >= $total) {
				$this->lecho('#R' . $project['RfgSurvey']['rfg_survey_id'] . ' has no more panelists to send to.', $log_file, $log_key);
				continue;
			}

			$command = 'rfg invite --rfg_survey_id ' . $project['RfgSurvey']['rfg_survey_id'];

			$this->RfgQueue->create();
			$save = $this->RfgQueue->save(array('RfgQueue' => array(
				'rfg_survey_id' => $project['RfgSurvey']['rfg_survey_id'],
				'command' => $command,
				'survey_id' => $project['Project']['id']
			)));

			if ($save) {
				$this->lecho('[SUCCESS] #R' . $project['RfgSurvey']['rfg_survey_id'], $log_file, $log_key);
				$rfgQueueSource = $this->RfgQueue->getDataSource();
				$rfgQueueSource->begin();
				$rfg_queue_id = $this->RfgQueue->getInsertId();
				$rfgQueueSource->commit();
				$sqs_batch[$rfg_queue_id] = $command;
				if (count($sqs_batch) == 10) {
					$this->Rfg->handle_queue($sqs, $sqs_settings['sqs.rfg.queue'], $sqs_batch);
				}
			}
		}

		if (isset($sqs_batch) && !empty($sqs_batch)) {
			$this->Rfg->handle_queue($sqs, $sqs_settings['sqs.rfg.queue'], $sqs_batch);
		}
	}

	public function invite() {
		$log_file = 'rfg.invite';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);

		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			$this->lecho('Completed invite (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			// this method logs the errors already
			return false;
		}

		if (!isset($this->params['rfg_survey_id']) && !isset($this->params['project_id'])) {
			$this->lecho('FAILED: You are missing lucid_project_id', $log_file, $log_key);
			$this->lecho('Completed invite (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			return false;
		}

		if (isset($this->params['rfg_survey_id'])) {
			$rfg_survey = $this->RfgSurvey->find('first', array(
				'conditions' => array(
					'RfgSurvey.rfg_survey_id' => $this->params['rfg_survey_id']
				),
				'recursive' => -1,
				'order' => 'RfgSurvey.id DESC'
			));
			$project_id = $rfg_survey['RfgSurvey']['survey_id'];
		}
		else {
			$project_id = $this->params['project_id'];
		}

		// launch this project
		$this->Project->bindModel(array('hasOne' => array(
			'RfgSurvey' => array(
				'className' => 'RfgSurvey',
				'foreignKey' => 'survey_id'
			)
		)));
		$project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $project_id
			)
		));
		if (!$project || $project['Project']['status'] != PROJECT_STATUS_OPEN || $project['Project']['group_id'] != $this->rfg_group['Group']['id']) {
			$this->lecho('FAILED: Invalid project to resend', $log_file, $log_key);
			$this->lecho('Completed invite (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			return false;
		}
		$this->lecho('Starting invite #R' . $project['RfgSurvey']['rfg_survey_id'], $log_file, $log_key);

		// these invites can be triggered and delayed greatly by query sends: while the scheduling may place them in the queue 
		// in half-hour increments; there is no guarantee the execution happens
		// to that end, run these invites only once every 30 minutes
		$start_timer = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.project_id' => $project['Project']['id'],
				'ProjectOption.name' => 'rfg.started.queried'
			)
		));
		$end_timer = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.project_id' => $project['Project']['id'],
				'ProjectOption.name' => 'rfg.ended.invited'
			)
		));

		if ($start_timer && strtotime('-30 minutes') <= strtotime($start_timer['ProjectOption']['value'])) {
			$this->lecho('FAILED: query started within 30 minutes (' . date(DB_DATETIME) . ' ' . $start_timer['ProjectOption']['value'] . ')', $log_file, $log_key);
			$this->lecho('Completed invite (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			return false;
		}
		// won't solve the case where queries die - projects could get "stuck"
		if ($start_timer && $end_timer && strtotime($start_timer['ProjectOption']['value']) >= strtotime($end_timer['ProjectOption']['value'])) {
			$this->lecho('FAILED: query started later than ended (probably running a live query) (' . $start_timer['ProjectOption']['value'] . ' ' . $end_timer['ProjectOption']['value'] . ')', $log_file, $log_key);
			$this->lecho('Completed invite (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			return false;
		}

		if ($end_timer && strtotime('-30 minutes') <= strtotime($end_timer['ProjectOption']['value'])) {
			$this->lecho('FAILED: query finished within 30 minutes (' . date(DB_DATETIME) . ' ' . $end_timer['ProjectOption']['value'] . ')', $log_file, $log_key);
			$this->lecho('Completed invite (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			return false;
		}

		if (!$start_timer) {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'project_id' => $project['Project']['id'],
				'name' => 'rfg.started.queried',
				'value' => date(DB_DATETIME)
			)));
		}
		else {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'id' => $start_timer['ProjectOption']['id'],
				'value' => date(DB_DATETIME)
			)), true, array('value'));
		}

		// do a sanity check
		$survey_visit_cache = $this->SurveyVisitCache->find('first', array(
			'fields' => array('complete', 'click', 'nq', 'overquota', 'prescreen_clicks'),
			'conditions' => array(
				'SurveyVisitCache.survey_id' => $project['Project']['id']
			),
			'recursive' => -1
		));
		$clicks = 0;
		if ($survey_visit_cache) {
			if ($survey_visit_cache['SurveyVisitCache']['click'] > $clicks) {
				$clicks = $survey_visit_cache['SurveyVisitCache']['click'];
			}
		}
		if ($project['Project']['prescreen']) {
			if ($survey_visit_cache['SurveyVisitCache']['prescreen_clicks'] > $clicks) {
				$clicks = $survey_visit_cache['SurveyVisitCache']['prescreen_clicks'];
			}
		}
		$count = $this->SurveyUser->find('count', array(
			'conditions' => array(
				'SurveyUser.survey_id' => $project['Project']['id']
			)
		));
		if (($clicks * 10) < $count) {
			$this->lecho('FAILED: project requires at least a 10% engagement to invite further', $log_file, $log_key);
			$this->lecho('Completed invite (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			return false;
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
			$this->lecho('FAILED: no queries on this project', $log_file, $log_key);
			$this->lecho('Completed invite (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			return false;
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

			// if updated within last hour
			if ($query_history && strtotime('-30 minutes') < strtotime($query_history['QueryHistory']['created'])) {
				$this->lecho('#' . $query['Query']['id'] . ' skipped: sent within past half hour', $log_file, $log_key);
				continue;
			}
			// skip inactive queries
			if ($query_history && !$query_history['QueryHistory']['active']) {
				$this->lecho('#' . $query['Query']['id'] . ' skipped: inactive queries', $log_file, $log_key);
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
				$this->lecho('#' . $query['Query']['id'] . ' skipped: maxed', $log_file, $log_key);
				continue;
			}
			$query_amount = MintVine::query_amount($project, $results['count']['total'], $query);
			if ($query_amount > ($results['count']['total'] - $user_count)) {
				$query_amount = $results['count']['total'] - $user_count;
			}
			// hard limit defined by rfg.followup.ceiling
			if ($query_amount > $this->settings['rfg.followup.ceiling']) {
				$query_amount = $this->settings['rfg.followup.ceiling'];
			}

			if (empty($query_amount)) {
				$this->lecho('#' . $query['Query']['id'] . ' skipped: no query amount', $log_file, $log_key);
				continue;
			}
			
			$this->lecho('[SUCCESS] #' . $project['Project']['id'] . ' sending to ' . $query_amount, $log_file, $log_key);
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
			
			$query_str = ROOT . '/app/Console/cake query create_queries ' . $query['Query']['survey_id'] . ' ' . $query['Query']['id'] . ' ' . $query_history_id . ' ' . $query_amount;
			CakeLog::write('query_commands', $query_str);
			// run these synchronously
			exec($query_str, $output);
			$this->lecho($query_str, $log_file, $log_key);
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'query.executed',
				'query_id' => $query['Query']['id'],
				'description' => 'Total sent : '.$query_amount.', by Rfg invite.'
			)));
			
			// write a value that stores the last completed invite time
			if ($end_timer) {
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'id' => $end_timer['ProjectOption']['id'],
					'value' => date(DB_DATETIME),
				)), true, array('value'));
			}
			else {
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'name' => 'rfg.ended.invited',
					'value' => date(DB_DATETIME),
					'project_id' => $project['Project']['id']
				)));
			}
		}
		$this->lecho('Completed invite (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
	}

	// performance-based closing rules are handled on the API; this is strictly to handle bad properties
	protected function checkInternalRfgRules($rfg_project, $log_file = null, $log_key = null) {
		$loi = $this->Rfg->loi($rfg_project['estimatedLOI']);
		$ir = $this->Rfg->ir($rfg_project, $rfg_project['stats']); // Using 2nd param, brings us the overall proejct IR based on the latest statistics
		$payouts = $this->Rfg->payout($rfg_project['cpi']);

		$return = true;
		// flooring rules
		if (empty($payouts['award'])) {
			$return = false;
			$message = '[FAILED RULE] Empty award';
		}
		// award is less than floor
		elseif ($payouts['award'] < $this->settings['rfg.floor.award']) {
			$return = false;
			$message = '[FAILED RULE] Award floor (' . $payouts['award'] . ' < ' . $this->settings['rfg.floor.award'] . ')';
		}
		// check floor EPC
		elseif (!empty($ir) && ($payouts['client_rate'] * $ir / 100) <= $this->settings['rfg.floor.epc']) {
			$return = false;
			$message = '[FAILED RULE] EPC floor ' . $payouts['client_rate'] . ' ' . $ir . ' (' . ($payouts['client_rate'] * $ir / 100) . ' <= ' . $this->settings['rfg.floor.epc'] . ')';
		}
		// ratio floor rule
		elseif (!empty($loi) && $loi * $this->settings['rfg.floor.loi.ratio'] > $payouts['award']) {
			$return = false;
			$message = '[FAILED RULE] Award ratio (' . ($loi * $this->settings['rfg.floor.loi.ratio']) . ' > ' . $payouts['award'] . ')';
		}
		// long LOIs are skipped
		elseif ($loi >= 30) {
			$return = false;
			$message = '[FAILED RULE] Long LOI (' . $loi . ' minutes)';
		}
		// unsupported country
		elseif (!in_array($rfg_project['country'], array('CA', 'GB', 'US'))) {
			$return = false;
			$message = '[FAILED RULE] Unsupported country (' . $rfg_project['country'] . ')';
		}
		if (!empty($log_file) && !empty($log_key) && !empty($message)) {
			$this->lecho($message, $log_file, $log_key);
		}
		return $return;
	}

	function execute_api($command, $log_file, $log_key) {
		$key = hex2bin($this->settings['rfg.secret']);
		$time = time();
		$hash = hash_hmac('sha1', $time . $command, $key);
		$api_url = $this->settings['rfg.host'] . "?apid=" . $this->settings['rfg.apid'] . "&time=" . $time . "&hash=" . $hash;

		$ch = curl_init($api_url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $command);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // should be removed
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($command))
		);
		$result = curl_exec($ch);
		curl_close($ch);
		if (empty($result)) {
			$this->lecho('FAILED: No reponse from API', $log_file, $log_key);
		}

		$result = json_decode($result, true);
		if ($result['result'] == 1) {
			$this->lecho("FAILED: Api lookup failed :" . $command ."\n". "COMMAND ERROR: " . $result['message'], $log_file, $log_key);
			return false;
		}
		else if ($result['result'] == 2) {
			$this->lecho("FAILED: Api lookup failed :" . $command . "\n" . "PROTOCOL ERROR: " . $result['message'], $log_file, $log_key);
			return false;
		}

		return $result;
	}
	
	// actually executes things
	public function empty_queue() {
		ini_set('memory_limit', '1024M');
		$log_file = 'rfg.empty.queue';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		App::import('Vendor', 'sqs');
		$sqs_settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('sqs.access.key', 'sqs.access.secret', 'sqs.rfg.queue'),
				'Setting.deleted' => false
			)
		));
		$i = 0;
		$sqs = new SQS($sqs_settings['sqs.access.key'], $sqs_settings['sqs.access.secret']);
		while (true) {
			$results = $sqs->receiveMessage($sqs_settings['sqs.rfg.queue']);
			CakeLog::write('sqs', print_r($results, true));
			if (!empty($results['Messages'])) {
				$sqs->deleteMessage($sqs_settings['sqs.rfg.queue'], $results['Messages'][0]['ReceiptHandle']);
				$i++;
			}
			
			if (empty($results['Messages'])) {
				$this->lecho('Queue emptied ' . $i . ' items removed.', $log_file, $log_key);
				break;
			}
		}
	}
	
	public function debug_datapoints() {
		ini_set('memory_limit', '1024M');
		$log_file = 'rfg.debug.datapoints';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);

		$this->lecho('Starting qualifications', $log_file, $log_key);
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			// this method logs the errors already
			$this->lecho('Completed save qualifications (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			return false;
		}
		
		if (!isset($this->args[0])) {
			$command = "{ 'command' : 'livealert/listDatapoints/1' }";
		}
		else {
			$command = "{ 'command' : 'livealert/datapoint/1' , 'name' : '".$this->args[0]."' }";
		}
		$result = $this->execute_api($command, $log_file, $log_key);
		if ($result['result'] != 0) {
			echo 'API Error';
		}
		print_r($result);
	}
	
	public function save_qualifications() {
		ini_set('memory_limit', '1024M');
		$log_file = 'rfg.save.qualifications';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);

		$this->lecho('Starting qualifications', $log_file, $log_key);
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			// this method logs the errors already
			$this->lecho('Completed save qualifications (Execution time: ' . (microtime(true) - $time_start) . ')', $log_file, $log_key);
			return false;
		}
		
		$desired_language = 'en-US';
		$command = "{ 'command' : 'livealert/listDatapoints/1' }";
		$result = $this->execute_api($command, $log_file, $log_key);
		if ($result['result'] != 0) {
			echo 'API Error';
		}

		$count = 0;
		foreach ($result['response']['names'] as $datapoint_name) {
			$count = $this->RfgQuestion->find('count', array(
				'conditions' => array(
					'rfg_name' => $datapoint_name
				)
			));
			if ($count > 0) {
				echo 'Question ' . $datapoint_name . ' exist.' . "\n";
				continue;
			}
			
			$datapoint = $this->datapoint($datapoint_name, $log_file, $log_key);
			if ($rfg_question_id = $this->Rfg->save_question($datapoint)) {
				echo 'New question id ' . $rfg_question_id . ' saved.' . "\n";
			}
		}
	}
	
	protected function datapoint($datapoint_name, $log_file, $log_key, $i = 0) {
		$command = "{ 'command' : 'livealert/datapoint/1' , 'name' : '$datapoint_name' }";
		$datapoint = $this->execute_api($command, $log_file, $log_key);
		if (empty($datapoint['response'])) {
			if ($i == 4) {
				return false;
			}
			
			$i++;
			$this->datapoint($datapoint_name, $log_file, $log_key, $i);
		}
		
		return $datapoint['response'];
	}

}

// production centos does not contain hex2bin (php 5.3.3)
if (!function_exists('hex2bin')) {

	function hex2bin($hexstr) {
		$n = strlen($hexstr);
		$sbin = '';
		$i = 0;
		while ($i < $n) {
			$a = substr($hexstr, $i, 2);
			$c = pack("H*", $a);
			if ($i == 0) {
				$sbin = $c;
			}
			else {
				$sbin .= $c;
			}
			$i+=2;
		}
		return $sbin;
	}

}
