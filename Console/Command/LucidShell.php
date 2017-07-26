<?php
App::import('Lib', 'Utilities');
App::import('Lib', 'QueryEngine');
App::import('Lib', 'FedMappings');
App::import('Lib', 'MintVine');
App::import('Lib', 'Surveys');
App::import('Lib', 'Reporting');
App::uses('HttpSocket', 'Network/Http');

class LucidShell extends AppShell {
 	public $uses = array(
		'Answer', 
		'AnswerText', 
		'Client', 
		'FedAnswer', 
		'FedQuestion', 
		'FedSurvey', 
		'FulcrumSurveyGroup', 
		'GeoZip', 
		'Group', 
		'LucidEpcStatistic',
		'LucidQueue',
		'LucidStudyType',
		'Partner', 
		'Prescreener', 
		'Project', 
		'ProjectCompleteHistory', 
		'ProjectLog', 
		'ProjectOption', 
		'ProjectQuotaHistory', 
		'Qualification', 
		'QualificationStatistic', 
		'QualificationUser',
		'Query', 
		'QueryProfile', 
		'QueryStatistic', 
		'Question', 
		'QuestionText', 
		'Reconciliation', 
		'ReconciliationAnalysis', 
		'Setting', 
		'SurveyUser', 
		'SurveyVisit',  
		'SurveyVisitCache', 
		'SurveyUserVisit',
		'Transaction', 
		'User'
	);
	public $tasks = array('Lucid', 'ReconcileLucid');
	public $failed_rule = "";

	public function getOptionParser() {
		$parser = parent::getOptionParser();

		$parser->addOption('lucid_project_id', array(
			'help' => 'Lucid Survey ID',
			'boolean' => false
		));
		$parser->addOption('force', array(
			'help' => 'Force a change',
			'boolean' => true
		));

		$parser->addOption('project_id', array(
			'help' => 'MintVine Project ID',
			'boolean' => false
		));

		$parser->addOption('project_ids', array(
			'help' => 'Lucid Survey IDs',
			'boolean' => false
		));
		return $parser;
	}

	public function performance() {
		if (!isset($this->args[0])) {
			$offset = '1';
		}
		else {
			$offset = $this->args[0];
		}
		$ts = strtotime('-'.$offset.' hours');
		echo 'Executing since '.date(DB_DATETIME, $ts)."\n";
		$lucid_queues = $this->LucidQueue->find('all', array(
			'fields' => array('LucidQueue.created', 'LucidQueue.executed'),
			'conditions' => array(
				'LucidQueue.created >=' => date(DB_DATETIME, $ts),
				'LucidQueue.executed is not null'
			)
		));

		$count = 0;
		$diffs = array();
		$min = 1000000;
		$max = 0;
		foreach ($lucid_queues as $lucid_queue) {
			$diff = strtotime($lucid_queue['LucidQueue']['executed']) - strtotime($lucid_queue['LucidQueue']['created']);
			if ($diff < $min) {
				$min = $diff;
			}
			if ($diff > $max) {
				$max = $diff;
			}
			$diffs[] = $diff;
		}
		$this->out('Total work items in past '.$this->args[0].' hours: '.(number_format(count($lucid_queues))));

		$avg = round(array_sum($diffs) / count($diffs));
		$this->out('Average time to execution '.number_format($avg).' seconds');
		$this->out('Min: '.number_format($min)); 
		$this->out('Max: '.number_format($max)); 
	}

	// actually executes things
	public function worker() {
		$lucid_settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array(
					'lucid.active',
					'lucid.maintenance'
				),
				'Setting.deleted' => false
			),
			'fields' => array(
				'Setting.name',
				'Setting.value'
			)
		));

		if (count($lucid_settings) < 2) {
			return;
		}

		if ($lucid_settings['lucid.active'] == 'false' || $lucid_settings['lucid.maintenance'] == 'true') {
			return;
		}

		$time_to_run = 12;
		ini_set('memory_limit', '1024M');
		$log_file = 'lucid.worker';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting worker', $log_file, $log_key);

		App::import('Vendor', 'sqs');
		$sqs_settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('sqs.access.key', 'sqs.access.secret', 'sqs.lucid.queue'),
				'Setting.deleted' => false
			)
		));
		$i = 0;
		$sqs = new SQS($sqs_settings['sqs.access.key'], $sqs_settings['sqs.access.secret']);
		while (true) {
			$results = $sqs->receiveMessage($sqs_settings['sqs.lucid.queue']);
			if (!empty($results['Messages'])) {
				$command = $results['Messages'][0]['Body'];
				$this->lecho('Starting '.$command, $log_file, $log_key);
				$query = ROOT . '/app/Console/cake '.$command;
				CakeLog::write('query_commands', $query);
				// run these synchronously
				exec($query, $output);
				$i++;

				$this->LucidQueue->getDataSource()->reconnect();
				$lucid_queue = $this->LucidQueue->find('first', array(
					'conditions' => array(
						'LucidQueue.command' => $command,
						'LucidQueue.executed is null'
					)
				));
				if ($lucid_queue) {
					$this->LucidQueue->create();
					$this->LucidQueue->save(array('LucidQueue' => array(
						'id' => $lucid_queue['LucidQueue']['id'],
						'worker' => defined('WORKER_NAME') ? WORKER_NAME: null,
						'executed' => date(DB_DATETIME)
					)), true, array('executed', 'worker'));
					$lucid_queue_id = $lucid_queue['LucidQueue']['id'];
				}
				else {
					// gotta parse out the invite
					$fed_survey_id = null;
					if (strpos($command, 'lucid create --lucid_project_id=') !== false) {
						$fed_survey_id = str_replace('lucid create --lucid_project_id=', '', $command);
					}
					elseif (strpos($command, 'lucid update --lucid_project_id=') !== false) {
						$fed_survey_id = str_replace('lucid update --lucid_project_id=', '', $command);
					}
					elseif (strpos($command, 'lucid invite --lucid_project_id ') !== false) {
						$fed_survey_id = str_replace('lucid invite --lucid_project_id ', '', $command);
					}
					if (!empty($fed_survey_id)) {
						// if the lucid queue doesn't exist, then write the value
						$lucidQueueSource = $this->LucidQueue->getDataSource();
						$lucidQueueSource->begin();
						$this->LucidQueue->create();
						$save = $this->LucidQueue->save(array('LucidQueue' => array(
							'amazon_queue_id' => $results['Messages'][0]['MessageId'],
							'fed_survey_id' => $fed_survey_id,
							'command' => $command,
							'survey_id' => null,
							'executed' => date(DB_DATETIME)
						)));
						if ($save) {
							$lucid_queue_id = $this->LucidQueue->getInsertId();
						}
						$lucidQueueSource->commit();
					}
				}
				$sqs->deleteMessage($sqs_settings['sqs.lucid.queue'], $results['Messages'][0]['ReceiptHandle']);
				if (isset($lucid_queue_id)) {
					$this->lecho('Processed '.$lucid_queue_id, $log_file, $log_key);
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
			if (empty($results['Messages'])) {
				$this->lecho('Completed worker '.$i.' items (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
				break;
			}
		}
	}

	// precursor to process, but go through the offerwall and generate links for all relevant projects
	public function links() {
		$lucid_settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array(
					'lucid.active',
					'lucid.maintenance'
				),
				'Setting.deleted' => false
			),
			'fields' => array(
				'Setting.name',
				'Setting.value'
			)
		));

		if (count($lucid_settings) < 2) {
			return;
		}

		if ($lucid_settings['lucid.active'] == 'false' || $lucid_settings['lucid.maintenance'] == 'true') {
			return;
		}

		ini_set('memory_limit', '1024M');
		$log_file = 'lucid.links';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting links', $log_file, $log_key);

		// load settings, client, group, etc.
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			return false;
		}

		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$params = array('key' => $this->settings['lucid.api.key']);

		$desired_countries = array(
			6, // Canada
			8, // UK
			9 // US
		);

		// get the total offerwall
		$url = $this->settings['lucid.host'].'Supply/v1/Surveys/AllOfferwall/'.$this->settings['lucid.supplier.code'];
		$response = $http->get($url, $params);

		if ($response->code != 200) {
			$this->lecho('FAILED: Offerwall look up failed: '.$url, $log_file, $log_key);
			$this->lecho($response, $log_file, $log_key);
			$this->lecho('Completed links (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		$all_offerwalls = Set::extract('Surveys', json_decode($response->body, true));
		$this->lecho('Starting to process '.count($all_offerwalls).' projects', $log_file, $log_key);
		/* $offerwall_item looks like:
      		  (
		            [SurveyName] => Study_8633143
		            [SurveyNumber] => 157547
		            [SurveySID] => 6e79de21-bfa6-45dd-83d7-704f765c15ed
		            [AccountName] => P2Sample
		            [CountryLanguageID] => 47
		            [LengthOfInterview] => 0
		            [BidIncidence] => 20
		            [Conversion] => 0
		            [CPI] => 0.88
		            [FieldEndDate] => /Date(1437714000000-0500)/
		            [IndustryID] => 30
		            [StudyTypeID] => 22
		            [OverallCompletes] => 0
		            [TotalRemaining] => 250
		            [CompletionPercentage] => 0
		            [SurveyGroup] =>
		            [SurveyGroupID] =>
		            [SurveyGroupExists] => 0
		            [BidLengthOfInterview] => 45
		            [TerminationLengthOfInterview] => 0
		            [SurveyQuotaCalcTypeID] => 1
		            [IsTrueSample] =>
		            [SurveyMobileConversion] => 0
		            [SampleTypeID] =>
		        )
		*/
		foreach ($all_offerwalls as $offerwall_item) {
			// see if we've created a successful link; if not, try creating it again
			// note: on dev, do not run this: it will conflict creation on production

			// only import this item if it exists in offerwall
			if (isset($this->params['lucid_project_id']) && $offerwall_item['SurveyNumber'] != $this->params['lucid_project_id']) {
				continue;
			}
			$this->FedSurvey->getDataSource()->reconnect();
			$fed_survey = $this->FedSurvey->find('first', array(
				'conditions' => array(
					'FedSurvey.fed_survey_id' => $offerwall_item['SurveyNumber']
				),
				'fields' => array('id', 'status'),
				'recursive' => -1,
				'order' => 'FedSurvey.id DESC'
			));

			$this->lecho('[START] #'.$offerwall_item['SurveyNumber'], $log_file, $log_key);
			// if it's already imported, skip it
			if ($fed_survey && $fed_survey['FedSurvey']['status'] != 'failed.link') {
				$this->lecho('[SKIP] #'.$offerwall_item['SurveyNumber'].': already imported', $log_file, $log_key);
				continue;
			}

			$lucid_study_type = $this->LucidStudyType->find('first', array(
				'conditions' => array(
					'LucidStudyType.key' => $offerwall_item['StudyTypeID']
				),
				'fields' => array('LucidStudyType.name')
			));

			// skip innovate projects for now - note: also need to check all supplierallocation calls
			if ($offerwall_item['StudyTypeID'] == 23) {
				$this->lecho('[SKIP] #'.$offerwall_item['SurveyNumber'].': StudyTypeID 23', $log_file, $log_key);
				// add in a placeholder Lucid # so we don't try to re-import this
				// note: beforeSave() will prevent dupes when survey_id = 0 so no need to validate the existence of this
				$this->FedSurvey->create();
				$this->FedSurvey->save(array('FedSurvey' => array(
					'survey_id' => '0',
					'fed_survey_id' => $offerwall_item['SurveyNumber'],
					'status' => 'skipped.link.unsupported',
					'survey_type_id' => $offerwall_item['StudyTypeID'],
					'survey_type' => $lucid_study_type['LucidStudyType']['name']
				)));
				continue;
			}

			// Figure out whether we want this survey or not
			if (!in_array($offerwall_item['CountryLanguageID'], $desired_countries)) { // only US, UK and CA surveys
				$this->lecho('[SKIP] #'.$offerwall_item['SurveyNumber'].': unsupported country ('.$offerwall_item['CountryLanguageID'].')', $log_file, $log_key);
				// add in a placeholder Lucid # so we don't try to re-import this
				// note: beforeSave() will prevent dupes when survey_id = 0 so no need to validate the existence of this
				$this->FedSurvey->create();
				$this->FedSurvey->save(array('FedSurvey' => array(
					'survey_id' => '0',
					'fed_survey_id' => $offerwall_item['SurveyNumber'],
					'status' => 'skipped.country',
					'survey_type_id' => $offerwall_item['StudyTypeID'],
					'survey_type' => $lucid_study_type['LucidStudyType']['name'],
				)));
				continue;
			}

			// try catch this so we can log the exceptions
			try {
				$url = $this->settings['lucid.host'].'Supply/v1/SupplierLinks/Create/'.$offerwall_item['SurveyNumber'].'/'.$this->settings['lucid.supplier.code'].'?key=' . $this->settings['lucid.api.key'];

				$url_params = $this->Setting->find('first', array(
					'fields' => array('Setting.value'),
					'conditions' => array(
						'Setting.name' => 'lucid.url.params',
						'Setting.deleted' => false
					),
					'recursive' => -1
				));

				if (!isset($url_params['Setting']['value']) || empty($url_params['Setting']['value'])) {
					$response = $http->post($url, json_encode(array(
						'SupplierLinkTypeCode' => 'OWS',
						'TrackingTypeCode' => 'NONE'
					)), array(
						'header' => array('Content-Type' => 'application/json')
					));
				}
				else {
					$response = $http->post($url, json_encode(array(
						// additional params are appended at end of survey link starting with "&"
						// e.g: "&STATUS=[%InitialStatus%]&COMPLETE_DATE=[%LastDate%]"
						'SupplierLinkTypeCode' => 'OWS',
						'TrackingTypeCode' => 'NONE',
						'DefaultLink' => HOSTNAME_REDIRECT."/nq/?uid=[%MID%]".$url_params['Setting']['value'],
						'SuccessLink' => HOSTNAME_REDIRECT."/success/?uid=[%MID%]".$url_params['Setting']['value'],
						'FailureLink' => HOSTNAME_REDIRECT."/nq/?uid=[%MID%]".$url_params['Setting']['value'],
						'OverQuotaLink' => HOSTNAME_REDIRECT."/quota/?uid=[%MID%]".$url_params['Setting']['value'],
						'QualityTerminationLink' => HOSTNAME_REDIRECT."/nq/?uid=[%MID%]".$url_params['Setting']['value']
					)), array(
						'header' => array('Content-Type' => 'application/json')
					));
				}
				if ($response->code == 200) {
					$this->lecho('[SUCCESS] Link created #F'.$offerwall_item['SurveyNumber'], $log_file, $log_key);
					if ($fed_survey) {
						$this->FedSurvey->delete($fed_survey['FedSurvey']['id']); // delete the failed link response
					}
				}
				else {
					$this->lecho('[FAILED] #'.$offerwall_item['SurveyNumber'], $log_file, $log_key);
					$this->lecho($response, 'fulcrum.import.link', $log_key);
					// note: beforeSave() will prevent dupes when survey_id = 0 so no need to validate the existence of this
					$this->FedSurvey->create();
					$this->FedSurvey->save(array('FedSurvey' => array(
						'survey_id' => '0',
						'fed_survey_id' => $offerwall_item['SurveyNumber'],
						'status' => 'failed.link',
						'survey_type_id' => $offerwall_item['StudyTypeID'],
						'survey_type' => $lucid_study_type['LucidStudyType']['name']
					)));
				}
			} catch (Exception $e) {
				$this->lecho('[FAILED] #'.$offerwall_item['SurveyNumber'], $log_file, $log_key);
				$this->lecho($e, 'fulcrum.import.link', $log_key);
				$this->FedSurvey->create();
				$this->FedSurvey->save(array('FedSurvey' => array(
					'survey_id' => '0',
					'fed_survey_id' => $offerwall_item['SurveyNumber'],
					'status' => 'failed.link',
					'survey_type_id' => $offerwall_item['StudyTypeID'],
					'survey_type' => $lucid_study_type['LucidStudyType']['name']
				)));
				// note with both of these failed links; we allow for recover on the next future import
			}
		}
		$this->lecho('Completed links (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
	}

	// goes through and orchestrates the work to be done
	public function process() {
		$lucid_settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array(
					'lucid.active',
					'lucid.maintenance'
				),
				'Setting.deleted' => false
			),
			'fields' => array(
				'Setting.name',
				'Setting.value'
			)
		));

		if (count($lucid_settings) < 2) {
			$this->out('Missing required settings');
			return;
		}

		if ($lucid_settings['lucid.active'] == 'false' || $lucid_settings['lucid.maintenance'] == 'true') {
			$this->out('Lucid integration disabled');
			return;
		}
		ini_set('memory_limit', '1024M');
		$log_file = 'lucid.process';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting process', $log_file, $log_key);

		// load settings, client, group, etc.
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			return false;
		}

		if (isset($this->params['lucid_project_id']) && isset($this->params['project_id'])) {
			$this->lecho('FAILED: You cannot define both a Lucid and MintVine project ID', $log_file, $log_key);
			$this->lecho('Completed process (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}

		// validate the project_id
		if (isset($this->params['project_id'])) {
			$target_fed_survey = $this->FedSurvey->find('first', array(
				'conditions' => array(
					'FedSurvey.survey_id' => $this->params['project_id']
				)
			));
			$this->lecho('FAILED: Failed to find project #'.$this->params['project_id'], $log_file, $log_key);
			$this->lecho('Completed process (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		else {
			$target_fed_survey = false;
		}

		App::import('Vendor', 'sqs');
		$sqs_settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('sqs.access.key', 'sqs.access.secret', 'sqs.lucid.queue'),
				'Setting.deleted' => false
			)
		));
		$sqs = new SQS($sqs_settings['sqs.access.key'], $sqs_settings['sqs.access.secret']);

		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$params = array('key' => $this->settings['lucid.api.key']);

		// get our total allocation across the board
		$url = $this->settings['lucid.host'].'Supply/v1/Surveys/SupplierAllocations/All/'.$this->settings['lucid.supplier.code'];
		$response = $http->get($url, $params);

		if ($response->code != 200) {
			$this->lecho('FAILED: Allocation look up failed: '.$url, $log_file, $log_key);
			$this->lecho($response, $log_file, $log_key);
			$this->lecho('Completed process (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		$allocated_surveys = Set::extract('SupplierAllocationSurveys', json_decode($response['body'], true));

		// this script takes more than 3 minutes to run; shuffling this will prevent the eventual convergence
		shuffle($allocated_surveys);

		$this->lecho('Processing '.count($allocated_surveys).' projects', $log_file, $log_key);
		/* $allocated_survey looks like:
			  (
	            [SurveyName] => IX-Campaign-5637
	            [SurveyNumber] => 98066
	            [SurveySID] => 4089c3d6-24c2-4794-aa20-052c4329e876
	            [AccountName] => Innovate MR
	            [CountryLanguageID] => 9
	            [LengthOfInterview] => 7
	            [BidIncidence] => 20
	            [Conversion] => 2
	            [FieldBeginDate] => /Date(1417413600000-0600)/
	            [FieldEndDate] => /Date(1420005600000-0600)/
	            [IndustryID] => 30
	            [StudyTypeID] => 23
	            [Priority] => 11
	            [SurveyGroup] =>
	            [SurveyGroupID] =>
	            [SurveyGroupExists] => 0
	            [BidLengthOfInterview] => 10
	            [TerminationLengthOfInterview] => 1
	            [SurveyQuotaCalcTypeID] => 1
	            [IsTrueSample] =>
	            [SurveyMobileConversion] => 0
	            [SampleTypeID] =>
	        )
		*/
		$allocated_survey_ids = array();
		$desired_countries = array(
			6, // Canada
			8, // UK
			9 // US
		);
		$sqs_batch = array();
		$i = 0;
		$filter_accounts = explode(',', $this->settings['lucid.filter.account_name']);
		array_walk($filter_accounts, create_function('&$val', '$val = trim($val);'));
		foreach ($allocated_surveys as $allocated_survey) {
			$i++;
			$allocated_survey_ids[] = $allocated_survey['SurveyNumber'];
			// operating against a single Lucid survey
			if (isset($this->params['lucid_project_id']) && $allocated_survey['SurveyNumber'] != $this->params['lucid_project_id']) {
				continue;
			}

			if (!empty($filter_accounts) && in_array($allocated_survey['AccountName'], $filter_accounts)) {
				continue;
			}

			// operating on a single MV project
			if (isset($this->params['project_id']) && $target_fed_survey && $target_fed_survey['FedSurvey']['fed_survey_id'] != $allocated_survey['SurveyNumber']) {
				continue;
			}

			// another sanity check we don't import study type 23 or countries
			// skip Recruit - Panel (11), Ad Effectiveness Research (23), and Community Build (8)
			if (in_array($allocated_survey['StudyTypeID'], array('11', '23', '8'))) {
				$this->lecho('[SKIP] '.$i.' #'.$allocated_survey['SurveyNumber'].': StudyTypeID '.$allocated_survey['StudyTypeID'], $log_file, $log_key);
				// add in a placeholder Lucid # so we don't try to re-import this
				// note: beforeSave() will prevent dupes when survey_id = 0 so no need to validate the existence of this
				$status = 'skipped.study.type';
				if ($allocated_survey['StudyTypeID'] == 11) {
					$status = 'skipped.panel.recruit';
				}
				elseif ($allocated_survey['StudyTypeID'] == 8) {
					$status = 'skipped.panel.community.build';
				}
				elseif ($allocated_survey['StudyTypeID'] == 23) {
					$status = 'skipped.link.unsupported';
				}

				$this->FedSurvey->create();
				$this->FedSurvey->save(array('FedSurvey' => array(
					'survey_id' => '0',
					'fed_survey_id' => $allocated_survey['SurveyNumber'],
					'status' => $status,
					'survey_type_id' => $allocated_survey['StudyTypeID']
				)));
				continue;
			}

			if (!in_array($allocated_survey['CountryLanguageID'], $desired_countries)) { // only US, UK and CA surveys
				$this->lecho('[SKIP] '.$i.' #'.$allocated_survey['SurveyNumber'].': unsupported country ('.$allocated_survey['CountryLanguageID'].')', $log_file, $log_key);
				// add in a placeholder Lucid # so we don't try to re-import this
				// note: beforeSave() will prevent dupes when survey_id = 0 so no need to validate the existence of this$this->FedSurvey->create();
				$this->FedSurvey->save(array('FedSurvey' => array(
					'survey_id' => '0',
					'fed_survey_id' => $allocated_survey['SurveyNumber'],
					'status' => 'skipped.country',
					'survey_type_id' => $allocated_survey['StudyTypeID']
				)));
				continue;
			}

			$this->FedSurvey->getDatasource()->reconnect();
			$this->Project->bindModel(array(
				'hasOne' => array(
					'FedSurvey' => array(
						'className' => 'FedSurvey',
						'foreignKey' => 'survey_id'
					)
				)
			));
			$project = $this->Project->find('first', array(
				'contain' => array('FedSurvey'),
				'conditions' => array(
					'FedSurvey.fed_survey_id' => $allocated_survey['SurveyNumber']
				)
			));
			// see if this project exists; if not, create it!
			if (!$project || empty($project)) {

				// fed survey shouldn't exist at all for projects that don't exist
				$fed_survey = $this->FedSurvey->find('first', array(
					'conditions' => array(
						'FedSurvey.fed_survey_id' => $allocated_survey['SurveyNumber']
					),
					'order' => 'FedSurvey.id DESC'
				));
				if ($fed_survey) {
					// this needs to be looked into: why do we have a failed link but the system generated it?
					if ($fed_survey['FedSurvey']['status'] == 'failed.link') {
						$this->FedSurvey->delete($fed_survey['FedSurvey']['id']);
					}
					else {
						$this->lecho($i.': [SKIP] CREATE #'.$allocated_survey['SurveyNumber'].': exists ('.$fed_survey['FedSurvey']['id'].')', $log_file, $log_key);
						continue;
					}
				}
				$command = 'lucid create --lucid_project_id='.$allocated_survey['SurveyNumber'];

				$lucidQueueSource = $this->LucidQueue->getDataSource();
				$lucidQueueSource->begin();
				$this->LucidQueue->create();
				$save = $this->LucidQueue->save(array('LucidQueue' => array(
					'fed_survey_id' => $allocated_survey['SurveyNumber'],
					'command' => $command,
					'survey_id' => null
				)));
				if ($save) {
					$lucid_queue_id = $this->LucidQueue->getInsertId();
					$lucidQueueSource->commit();
					$sqs_batch[$lucid_queue_id] = $command;
					$this->lecho($i.': Create #F'.$allocated_survey['SurveyNumber'], $log_file, $log_key);
				}
				else {
					$lucidQueueSource->commit();
					$this->lecho($i.': Creation of #F'.$allocated_survey['SurveyNumber'].' skipped as it already exists in queue.', $log_file, $log_key);
				}
				continue; // note: loop ends here for new projects
			}
			else {
				// fed survey shouldn't exist at all for projects that don't exist
				$fed_survey = $this->FedSurvey->find('first', array(
					'conditions' => array(
						'FedSurvey.fed_survey_id' => $allocated_survey['SurveyNumber']
					),
					'order' => 'FedSurvey.id DESC'
				));
				if ($fed_survey && $fed_survey['FedSurvey']['status']  == 'skipped.adhoc') {
					$this->lecho('Skipped #F'.$allocated_survey['SurveyNumber'].' as it is an adhoc project.', $log_file, $log_key);
					continue;
				}
				// if the project exists, let's update the metadata around it
				// note, because quotas/status are all tied to update, the status checks are also done here
				$command = 'lucid update --lucid_project_id='.$allocated_survey['SurveyNumber'];
				$lucidQueueSource = $this->LucidQueue->getDataSource();
				$lucidQueueSource->begin();
				$this->LucidQueue->create();
				$save = $this->LucidQueue->save(array('LucidQueue' => array(
					'fed_survey_id' => $allocated_survey['SurveyNumber'],
					'command' => $command,
					'survey_id' => null
				)));
				if ($save) {
					$lucid_queue_id = $this->LucidQueue->getInsertId();
					$lucidQueueSource->commit();
					$sqs_batch[$lucid_queue_id] = $command;
					$this->lecho($i.': Update #F'.$allocated_survey['SurveyNumber'], $log_file, $log_key);
				}
				else {
					$lucidQueueSource->commit();
					$this->lecho($i.': Update of #F'.$allocated_survey['SurveyNumber'].' skipped as it already exists in queue.', $log_file, $log_key);
				}
			}
		}

		$this->lecho('Found '.count($sqs_batch).' SQS items to send', $log_file, $log_key);

		// process all of the amazon queues
		if (isset($sqs_batch) && !empty($sqs_batch)) {
			$i = 0;
			$chunks = array_chunk($sqs_batch, 10, true);
			if (!empty($chunks)) {
				foreach ($chunks as $batch) {
					$response = $sqs->sendMessageBatch($sqs_settings['sqs.lucid.queue'], $batch);
					CakeLog::write('lucid.sqs', 'WRITING----------');
					CakeLog::write('lucid.sqs', print_r($batch, true));
					CakeLog::write('lucid.sqs', print_r($response, true));
					CakeLog::write('lucid.sqs', '-----------------');

					if (!empty($response)) {
						foreach ($response as $lucid_queue_id => $message_id) {
							$this->LucidQueue->create();
							$this->LucidQueue->save(array('LucidQueue' => array(
								'id' => $lucid_queue_id,
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
		if (!isset($this->params['lucid_project_id']) && !isset($this->params['project_id'])) {
			// find all projects that are open/staging/completed in our fulcrum set, but are not in our allocation: these are closed
			$this->Project->bindModel(array(
				'hasOne' => array(
					'FedSurvey' => array(
						'className' => 'FedSurvey',
						'foreignKey' => 'survey_id'
					)
				)
			));

			$this->Project->getDatasource()->reconnect();
			$all_projects = $this->Project->find('all', array(
				'fields' => array(
					'Project.id', 'FedSurvey.fed_survey_id'
				),
				'conditions' => array(
					'Project.group_id' => $this->lucid_group['Group']['id'],
					'Project.status' => array(PROJECT_STATUS_OPEN, PROJECT_STATUS_STAGING, PROJECT_STATUS_SAMPLING)
				)
			));
			if ($all_projects) {
				foreach ($all_projects as $all_project) {
					if (!in_array($all_project['FedSurvey']['fed_survey_id'], $allocated_survey_ids)) {
						$this->Project->create();
						$this->Project->save(array('Project' => array(
							'id' => $all_project['Project']['id'],
							'status' => PROJECT_STATUS_CLOSED,
							'active' => false,
							// update ended if it's blank - otherwise leave the old value
							'ended' => date(DB_DATETIME)
						)), true, array('status', 'active', 'ended'));

						$this->ProjectLog->create();
						$this->ProjectLog->save(array('ProjectLog' => array(
							'project_id' => $all_project['Project']['id'],
							'type' => 'status.closed.fulcrum',
							'description' => 'Closed by Lucid - not found in allocation'
						)));
						Utils::save_margin($all_project['Project']['id']);
						$this->lecho($i.': Closed #F'.$allocated_survey['SurveyNumber'].' - no longer in allocations', $log_file, $log_key);
					}
				}
			}
		}
		$this->lecho('Completed process (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
	}

	// goes through and figures out which projects to do follow-up sends to
	public function sends() {
		$lucid_settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array(
					'lucid.active',
					'lucid.maintenance'
				),
				'Setting.deleted' => false
			),
			'fields' => array(
				'Setting.name',
				'Setting.value'
			)
		));

		if (count($lucid_settings) < 2) {
			return;
		}

		if ($lucid_settings['lucid.active'] == 'false' || $lucid_settings['lucid.maintenance'] == 'true') {
			return;
		}

		ini_set('memory_limit', '1024M');
		$log_file = 'lucid.sends';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting process', $log_file, $log_key);

		// load settings, client, group, etc.
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			return false;
		}

		if (isset($this->params['lucid_project_id']) && isset($this->params['project_id'])) {
			$this->lecho('FAILED: You cannot define both a Lucid and MintVine project ID', $log_file, $log_key);
			$this->lecho('Completed process (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}

		// validate the project_id
		if (isset($this->params['project_id'])) {
			$target_fed_survey = $this->FedSurvey->find('first', array(
				'conditions' => array(
					'FedSurvey.survey_id' => $this->params['project_id']
				)
			));
			if (!$target_fed_survey) {
				$this->lecho('FAILED: Failed to find project #'.$this->params['project_id'], $log_file, $log_key);
				$this->lecho('Completed process (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
				return false;
			}
		}
		// validate the project_id
		if (isset($this->params['lucid_project_id'])) {
			$target_fed_survey = $this->FedSurvey->find('first', array(
				'conditions' => array(
					'FedSurvey.fed_survey_id' => $this->params['lucid_project_id']
				)
			));
			if (!$target_fed_survey) {
				$this->lecho('FAILED: Failed to find Lucid project #'.$this->params['project_id'], $log_file, $log_key);
				$this->lecho('Completed process (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
				return false;
			}
		}

		$this->Project->bindModel(array(
			'hasOne' => array(
				'FedSurvey' => array(
					'className' => 'FedSurvey',
					'foreignKey' => 'survey_id'
				)
			)
		));
		if (isset($target_fed_survey)) {
			$projects = $this->Project->find('all', array(
				'conditions' => array(
					'Project.id' => $target_fed_survey['FedSurvey']['survey_id'],
					'Project.status' => array(PROJECT_STATUS_OPEN),
					'Project.group_id' => $this->lucid_group['Group']['id'],
					'Project.temp_qualifications' => false
				)
			));
		}
		else {
			$projects = $this->Project->find('all', array(
				'conditions' => array(
					'Project.status' => array(PROJECT_STATUS_OPEN),
					'Project.group_id' => $this->lucid_group['Group']['id'],
					'Project.temp_qualifications' => false
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
				'Setting.name' => array('sqs.access.key', 'sqs.access.secret', 'sqs.lucid.queue'),
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
			if (!empty($queries['QueryHistory'])) {
				foreach ($queries['QueryHistory'] as $query_history) {
					if ($query_history['type'] == 'created') {
						$total = $query_history['total'];
					}
					elseif ($query_history['type'] == 'sent') {
						$sent = $query_history['count'] + $sent;
					}
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
				$this->lecho('#F'.$project['FedSurvey']['fed_survey_id'].' has no more panelists to send to.', $log_file, $log_key);
				continue;
			}

			$command = 'lucid invite --lucid_project_id '.$project['FedSurvey']['fed_survey_id'];

			$lucidQueueSource = $this->LucidQueue->getDataSource();
			$lucidQueueSource->begin();
			$this->LucidQueue->create();
			$save = $this->LucidQueue->save(array('LucidQueue' => array(
				'fed_survey_id' => $project['FedSurvey']['fed_survey_id'],
				'command' => $command,
				'survey_id' => $project['Project']['id']
			)));

			if ($save) {
				$this->lecho('[SUCCESS] #F'.$project['FedSurvey']['fed_survey_id'], $log_file, $log_key);
				$lucid_queue_id = $this->LucidQueue->getInsertId();
				$sqs_batch[$lucid_queue_id] = $command;
			}
			$lucidQueueSource->commit();
		}


		// process all of the amazon queues
		if (isset($sqs_batch) && !empty($sqs_batch)) {
			$chunks = array_chunk($sqs_batch, 10, true);
			if (!empty($chunks)) {
				foreach ($chunks as $batch) {
					$response = $sqs->sendMessageBatch($sqs_settings['sqs.lucid.queue'], $batch);
					CakeLog::write('lucid.sqs', 'WRITING----------');
					CakeLog::write('lucid.sqs', print_r($batch, true));
					CakeLog::write('lucid.sqs', print_r($response, true));
					CakeLog::write('lucid.sqs', '-----------------');

					if (!empty($response)) {
						foreach ($response as $lucid_queue_id => $message_id) {
							$this->LucidQueue->create();
							$this->LucidQueue->save(array('LucidQueue' => array(
								'id' => $lucid_queue_id,
								'amazon_queue_id' => $message_id
							)), true, array('amazon_queue_id'));
						}
					}
				}
			}
		}
	}

	public function invite() {
		$log_file = 'lucid.invite';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);

		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			$this->lecho('Completed invite (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			// this method logs the errors already
			return false;
		}

		if (!isset($this->params['lucid_project_id']) && !isset($this->params['project_id'])) {
			$this->lecho('FAILED: You are missing lucid_project_id', $log_file, $log_key);
			$this->lecho('Completed invite (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}

		if (isset($this->params['lucid_project_id'])) {
			$fed_survey = $this->FedSurvey->find('first', array(
				'conditions' => array(
					'FedSurvey.fed_survey_id' => $this->params['lucid_project_id']
				),
				'recursive' => -1,
				'order' => 'FedSurvey.id DESC'
			));
			$project_id = $fed_survey['FedSurvey']['survey_id'];
		}
		else {
			$project_id = $this->params['project_id'];
		}

		// launch this project
		$this->Project->bindModel(array('hasOne' => array(
			'FedSurvey' => array(
				'className' => 'FedSurvey',
				'foreignKey' => 'survey_id'
			)
		)));
		$project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $project_id
			)
		));
		if (!$project || $project['Project']['status'] != PROJECT_STATUS_OPEN || $project['Project']['group_id'] != $this->lucid_group['Group']['id']) {
			$this->lecho('FAILED: Invalid project to resend', $log_file, $log_key);
			$this->lecho('Completed invite (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		$this->lecho('Starting invite #F'.$this->params['lucid_project_id'], $log_file, $log_key);

		// these invites can be triggered and delayed greatly by query sends: while the scheduling may place them in the queue
		// in half-hour increments; there is no guarantee the execution happens
		// to that end, run these invites only once every 20 minutes
		$start_timer = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.project_id' => $project['Project']['id'],
				'ProjectOption.name' => 'lucid.started.queried'
			)
		));
		$end_timer = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.project_id' => $project['Project']['id'],
				'ProjectOption.name' => 'lucid.lastrun.invited'
			)
		));
		if ($start_timer) {
			$this->lecho('Start Timer: '.$start_timer['ProjectOption']['value'], $log_file, $log_key);
		}
		if ($end_timer) {
			$this->lecho('End Timer: '.$end_timer['ProjectOption']['value'], $log_file, $log_key);
		}

		if ($start_timer && strtotime('-20 minutes') <= strtotime($start_timer['ProjectOption']['value'])) {
			$this->lecho('FAILED: query started within 20 minutes ('.date(DB_DATETIME).' '.$start_timer['ProjectOption']['value'].')', $log_file, $log_key);
			$this->lecho('Completed invite (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		// won't solve the case where queries die - projects could get "stuck"
		if ($start_timer && $end_timer && strtotime($start_timer['ProjectOption']['value']) >= strtotime($end_timer['ProjectOption']['value'])) {
			$this->lecho('FAILED: query started later than ended (probably running a live query) ('.$start_timer['ProjectOption']['value'].' '.$end_timer['ProjectOption']['value'].')', $log_file, $log_key);
			$this->lecho('Completed invite (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}

		if ($end_timer && strtotime('-20 minutes') <= strtotime($end_timer['ProjectOption']['value'])) {
			$this->lecho('FAILED: query finished within 20 minutes ('.date(DB_DATETIME).' '.$end_timer['ProjectOption']['value'].')', $log_file, $log_key);
			$this->lecho('Completed invite (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}

		if (!$start_timer) {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'project_id' => $project['Project']['id'],
				'name' => 'lucid.started.queried',
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
		if ($start_timer) {
			$this->lecho('Timer updated to '.date(DB_DATETIME), $log_file, $log_key);
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
			$this->lecho('Completed invite (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}

		$this->lecho('Switching DBs', $log_file, $log_key);
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
		$this->lecho('Found '.count($queries).' queries', $log_file, $log_key);
		if (!$queries) {
			$this->lecho('FAILED: no queries on this project', $log_file, $log_key);
			$this->lecho('Completed invite (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
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
			if ($query_history && strtotime('-20 minutes') < strtotime($query_history['QueryHistory']['created'])) {
				$this->lecho('#'.$query['Query']['id'].' skipped: sent within past half hour', $log_file, $log_key);
				continue;
			}
			// skip inactive queries
			if ($query_history && !$query_history['QueryHistory']['active']) {
				$this->lecho('#'.$query['Query']['id'].' skipped: inactive queries', $log_file, $log_key);
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
			$this->lecho('Starting query: '.$query['Query']['query_string'], $log_file, $log_key);
			$results = QueryEngine::execute(json_decode($query['Query']['query_string'], true));
			// sometimes a few users are missed; no reason to keep running if we're at 96%
			if ($user_count >= round($results['count']['total'] * 0.96)) {
				$this->lecho('#'.$query['Query']['id'].' skipped: maxed', $log_file, $log_key);
				continue;
			}
			$query_amount = MintVine::query_amount($project, $results['count']['total'], $query);
			if ($query_amount > ($results['count']['total'] - $user_count)) {
				$query_amount = $results['count']['total'] - $user_count;
			}
			// hard limit defined by lucid.followup.ceiling
			if ($query_amount > $this->settings['lucid.followup.ceiling']) {
				$query_amount = $this->settings['lucid.followup.ceiling'];
			}

			if (empty($query_amount)) {
				$this->lecho('#'.$query['Query']['id'].' skipped: no query amount', $log_file, $log_key);
				continue;
			}
			$this->lecho('[SUCCESS] #'.$project['Project']['id'].' sending to '.$query_amount, $log_file, $log_key);
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
				'description' => 'Total sent : '.$query_amount.', by lucid invite.'
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
					'name' => 'lucid.lastrun.invited',
					'value' => date(DB_DATETIME),
					'project_id' => $project['Project']['id']
				)));
			}
		}
		$this->lecho('Completed invite (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
	}

	// create a project, given a lucid_project_id
	public function create() {
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
		$log_file = 'lucid.create';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);

		if (!isset($this->params['lucid_project_id'])) {
			$this->lecho('FAILED: You are missing lucid_project_id', $log_file, $log_key);
			$this->lecho('Completed create (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			$this->lecho('Completed create (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			// this method logs the errors already
			return false;
		}
		$this->lecho('Starting create '.$this->params['lucid_project_id'], $log_file, $log_key);

		$fed_survey = $this->FedSurvey->find('first', array(
			'conditions' => array(
				'FedSurvey.fed_survey_id' => $this->params['lucid_project_id']
			),
			'order' => 'FedSurvey.id DESC'
		));
		if ($fed_survey && $fed_survey['FedSurvey']['survey_id'] > 0) {
			$this->lecho('FAILED: #'.$this->params['lucid_project_id'].' has already been created.', $log_file, $log_key);
			$this->lecho('Completed create (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}

		$lucid_project = $this->getLucidProject(array('SupplierAllocationSurvey', 'SurveyQuotas', 'SurveyStatistics', 'SurveyQualification', 'SurveyGroups'));
		$lucid_survey = $lucid_project['SupplierAllocationSurvey'];
		$survey_quotas = $lucid_project['SurveyQuotas'];
		
		if (in_array($lucid_survey['StudyTypeID'], array('11', '23'))) {
			$this->lecho('FAILED: #'.$this->params['lucid_project_id'].' is type '.$lucid_survey['StudyTypeID'], $log_file, $log_key);
			$this->lecho('Completed create (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}

		// extract partner rate, client, rate and award amounts
		$payouts = $this->Lucid->payout($lucid_project['SurveyQuotas']);
		$overall_quota = $this->Lucid->quota($survey_quotas);
		$direct_allocation = $this->Lucid->direct_allocation($lucid_survey['SupplierAllocations']);
		$client_link = $this->Lucid->client_link($lucid_survey);
		$bid_ir = $this->Lucid->ir($lucid_survey);
		$loi = $this->Lucid->loi($lucid_survey);
		$min_time = round($loi / 4);
		if ($min_time <= 1) {
			$min_time = null;
		}

		$client_id = $this->Lucid->client($lucid_survey['AccountName'], $this->lucid_group['Group']['id']);
		if (!$client_id) {
			$client_id = $this->lucid_client['Client']['id'];
		}

		$save = false;
		$projectSource = $this->Project->getDataSource();
		$projectSource->begin();
		$this->Project->create();
		$project_data = array('Project' => array(
			'prj_name' => $lucid_survey['SurveyName'],
			'client_id' => $client_id,
			'date_created' => date(DB_DATETIME),
			'bid_ir' => $bid_ir,
			'client_rate' => $payouts['client_rate'],
			'partner_rate' => $payouts['partner_rate'],
			'user_payout' => $payouts['partner_rate'],
			'quota' => $overall_quota,
			'quota_type' => ($lucid_survey['SurveyQuotaCalcTypeID'] == '2') ? QUOTA_TYPE_CLICKS : QUOTA_TYPE_COMPLETES,
			'est_length' => $loi,
			'minimum_time' => $min_time,
			'group_id' => $this->lucid_group['Group']['id'],
			'status' => PROJECT_STATUS_STAGING,
			'client_project_id' => $lucid_survey['SurveyNumber'],
			'singleuse' => true,
			'touched' => date(DB_DATETIME),
			'country' => FedMappings::country($lucid_survey['CountryLanguageID']),
			'language' => FedMappings::language($lucid_survey['CountryLanguageID']),
			'survey_name' => 'Survey for you!',
			'award' => $payouts['award'],
			'active' => false, // after qualifications load, we'll activate it
			'dedupe' => true,
			'client_survey_link' => $client_link,
			'description' => 'Survey for you!'
		));
		if ($this->Project->save($project_data)) {
			$project_id = $this->Project->getInsertId();

			MintVine::project_quota_statistics('fulcrum', $overall_quota, $project_id);

			// Update mask field
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project_id,
				'mask' => $lucid_survey['SurveyNumber']
			)), true, array('mask'));
			$projectSource->commit();

			$date = $lucid_survey['FieldBeginDate'];
			$lucid_study_type = $this->LucidStudyType->find('first', array(
				'conditions' => array(
					'LucidStudyType.key' => $lucid_survey['StudyTypeID']
				),
				'fields' => array('LucidStudyType.name')
			));

			$fedSurveySource = $this->FedSurvey->getDataSource();
			$fedSurveySource->begin();
			$this->FedSurvey->create();
			$this->FedSurvey->save(array('FedSurvey' => array(
				'survey_id' => $project_id,
				'fed_survey_id' => $lucid_survey['SurveyNumber'],
				'survey_type_id' => $lucid_survey['StudyTypeID'],
				'current_quota' => $overall_quota,
				'status' => FEDSURVEY_CREATED,
				'survey_type_id' => $lucid_survey['StudyTypeID'],
				'survey_type' => $lucid_study_type['LucidStudyType']['name'],
				'direct' => $direct_allocation
			)));
			$fed_survey = $this->FedSurvey->find('first', array(
				'conditions' => array(
					'FedSurvey.id' => $this->FedSurvey->getInsertId()
				)
			));
			$fedSurveySource->commit();
			$this->set_security_group($fed_survey, $lucid_project);

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

			$this->lecho('[SUCCESS] #F'.$lucid_survey['SurveyNumber'].' created successfully (#'.$project_id.')', $log_file, $log_key);
			// run qualifications on this project
			$this->params['lucid_project_id'] = $lucid_survey['SurveyNumber'];
			$this->qualifications($lucid_project);

			// launch this project
			$this->Project->bindModel(array('hasOne' => array(
				'FedSurvey' => array(
					'className' => 'FedSurvey',
					'foreignKey' => 'survey_id'
				)
			)));
			$project = $this->Project->find('first', array(
				'conditions' => array(
					'Project.id' => $project_id
				)
			));
			$this->launchProject($project, $lucid_project, $log_file, $log_key, true);

			// alert direct allocations to slack
			if ($direct_allocation && (!defined('IS_DEV_INSTANCE') || IS_DEV_INSTANCE === false)) {
				$setting = $this->Setting->find('first', array(
					'fields' => array('Setting.value'),
					'conditions' => array(
						'Setting.name' => 'slack.alerts.webhook',
						'Setting.deleted' => false
					)
				));
				if (!empty($setting['Setting']['value'])) {
					$http = new HttpSocket(array(
						'timeout' => '2',
						'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
					));
					$http->post($setting['Setting']['value'], json_encode(array(
						'text' => 'Direct allocation project created from Lucid: #'.$project_id.' <https://cp.mintvine.com/surveys/dashboard/'.$project_id.'>',
						'link_names' => 1,
						'username' => 'bernard'
					)));
				}
			}
		}
		
		$this->lecho('Completed create (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
	}

	public function qualifications($lucid_project = false) {
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
		$log_file = 'lucid.qualifications';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting qualifications', $log_file, $log_key);
		if (!isset($this->params['lucid_project_id'])) {
			$this->lecho('FAILED: You need to define the lucid_project_id', $log_file, $log_key);
			$this->lecho('Completed qualifications (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			// this method logs the errors already
			$this->lecho('Completed qualifications (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		// be sure we retrieve the correct fed_survey; an active one!
		$fed_survey = $this->FedSurvey->find('first', array(
			'conditions' => array(
				'FedSurvey.fed_survey_id' => $this->params['lucid_project_id'],
				'FedSurvey.survey_id >' => '0'
			),
			'order' => 'FedSurvey.id DESC'
		));
		$this->Project->bindModel(array('hasOne' => array(
			'FedSurvey' => array(
				'className' => 'FedSurvey',
				'foreignKey' => 'survey_id'
			)
		)));
		$project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $fed_survey['FedSurvey']['survey_id']
			)
		));
		if (!$fed_survey || !$project || empty($project['Project']['country'])) {
			$this->lecho('FAILED: Could not locate the MV project that is associated with #F'.$this->params['lucid_project_id'], $log_file, $log_key);
			$this->lecho('Completed qualifications (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}

		if (!$lucid_project) {
			$lucid_project = $this->getLucidProject(array('SurveyQualification', 'SurveyQuotas'));
		}

		// new qqq activation codepath
		if (isset($this->settings['qqq.active']) && $this->settings['qqq.active'] == 'true') {
			$query_count = $this->Query->find('count', array(
				'conditions' => array(
					'Query.survey_id' => $project['Project']['id']
				),
				'recursive' => -1
			));
			// if this is already marked; then we are doing an update; otherwise for new queries that match execute qqq
			if ($project['Project']['temp_qualifications'] || $query_count == 0) {
				if (count($lucid_project['SurveyQuotas']) > 1) {
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

		// move this down past qqq
		if (empty($lucid_project['SurveyQualification'])) {
			$this->lecho('Could not find any qualifications for #F'.$this->params['lucid_project_id'], $log_file, $log_key);
			$this->lecho('Completed qualifications (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}

		$has_prescreener = false;
		$parent_query_params = array(); // to be sent to query engine
		$parent_query_match = true;
		$tablet = true;
		$mobile = true;
		$desktop = true;
		$parent_query_params['country'] = $project['Project']['country'];
		$questions = array();
		$questions_count = count($lucid_project['SurveyQualification']['Questions']);
		$qualification_hash = md5(serialize($lucid_project['SurveyQualification']));
		$qualification_hash_option = array();
		if (!empty($project['ProjectOption'])) {
			foreach ($project['ProjectOption'] as $project_option) {
				if ($project_option['name'] == 'lucid_qualification_hash') {
					$qualification_hash_option = $project_option;
				}
			}
		}

		if (isset($lucid_project['SurveyQualification']['Questions'])) {

			// Save hash of surveyQualification
			$project_option = array('ProjectOption' => array(
				'project_id' => $project['Project']['id'],
				'name' => 'lucid_qualification_hash',
				'value' => $qualification_hash,
			));
			if ($qualification_hash_option) {
				$project_option['ProjectOption']['id'] = $qualification_hash_option['id'];
				$this->ProjectOption->create();
				$this->ProjectOption->save($project_option, true, array('value'));
			}
			else {
				$this->ProjectOption->create();
				$this->ProjectOption->save($project_option);
			}

			// Handle qualifications to transform into queries
			foreach ($lucid_project['SurveyQualification']['Questions'] as $question) {

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

				if ($this->settings['lucid.queryengine'] == 'v2') {
					$parent_query_params['qualifications'][$question['QuestionID']] = $question['PreCodes'];
				}
				else {
					// we only support OR type prescreeners.
					if ($question['LogicalOperator'] != 'Or') {
						$parent_query_match = false;
						continue;
					}

					// manually map this to avoid an extra API call
					if ($project['Project']['country'] == 'US') {
						$countryId = 9;
					}
					elseif ($project['Project']['country'] == 'GB') {
						$countryId = 8;
					}
					elseif ($project['Project']['country'] == 'CA') {
						$countryId = 6;

					}
					// TODO: deal with countryID
					$fed_question = $this->getLucidQuestion($question['QuestionID'], $countryId);
					if (!$fed_question) {
						$parent_query_match = false;
						continue;
					}

					// handle the prescreeners
					if (empty($fed_question['FedQuestion']['queryable']) && in_array($fed_question['FedQuestion']['type'],  array(QUESTION_TYPE_SINGLE, QUESTION_TYPE_MULTIPLE)) && !empty($fed_question['FedAnswer'])) {
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
					$this->Lucid->mappings($parent_query_params, $fed_question, $question, $this->settings);
				}
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

		// Process parent query
		if (!empty($parent_query_params)) {

			// determine if this is a complete match
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'qualifications_match' => $parent_query_match,
				'modified' => false
			)), true, array('qualifications_match', 'modified'));
			$parent_query_id = $this->getQuery($project, $parent_query_params);
		}

		$survey_quotas = $lucid_project['SurveyQuotas'];
		foreach ($survey_quotas as $quota) {
			// Skip Overall quota
			if ($quota['SurveyQuotaType'] == 'Total') {
				continue;
			}

			$query_params = array(); // to be sent to query engine
			$query_match = true;
			foreach ($quota['Questions'] as $question) {
				if ($this->settings['lucid.queryengine'] == 'v2') {
					$query_params['qualifications'][$question['QuestionID']] = $question['PreCodes'];
				}
				else {
					if ($question['LogicalOperator'] != 'OR') {
						$query_match = false;
						continue;
					}

					$fed_question = $this->getLucidQuestion($question['QuestionID'], $countryId);
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
					$this->Lucid->mappings($query_params, $fed_question, $question);
				}
			}

			if (!empty($query_params)) {
				$query_params['country'] = $project['Project']['country'];
				// Add master params to filter query if not present
				if ($this->settings['lucid.queryengine'] == 'v2') {
					foreach ($parent_query_params['qualifications'] as $key => $value) {
						if (!isset($query_params['qualifications'][$key])) {
							$query_params['qualifications'][$key] = $value;
						}
					}
				}
				else {
					foreach ($parent_query_params as $key => $value) {
						if (!isset($query_params[$key])) {
							$query_params[$key] = $value;
						}
					}
				}

				$query_id = $this->getQuery($project, $query_params, $parent_query_id, $quota);
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

		$this->lecho('[SUCCESS] Qualifications for #'.$project['Project']['id'].': '.$fed_status, $log_file, $log_key);

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
		$this->lecho('Completed qualifications (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
	}

	// update a project, given a fed_survey_id
	/*
		specific order of operations for updating a project, in order of speed
		(1) update core metadata (not status)
		(2) update quota
		(3) check status
		(4) update qualifications
		(5) if closed/staging, check status again
	*/
	public function update() {
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

		$log_file = 'lucid.update';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting update', $log_file, $log_key);
		if (!isset($this->params['lucid_project_id']) && !isset($this->params['project_id'])) {
			$this->lecho('FAILED: You need at least lucid_project_id or project_id set to update', $log_file, $log_key);
			return false;
		}
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			// this method logs the errors already
			return false;
		}
		if (isset($this->params['lucid_project_id'])) {
			$fed_survey = $this->FedSurvey->find('first', array(
				'conditions' => array(
					'FedSurvey.fed_survey_id' => $this->params['lucid_project_id']
				),
				'order' => 'FedSurvey.id DESC'
			));

			if (empty($fed_survey['FedSurvey']['survey_id'])) {
				$this->lecho('That Lucid project has not been imported.', $log_file, $log_key);
				return false;
			}
			$project_id = $fed_survey['FedSurvey']['survey_id'];
		}
		else {
			$project_id = $this->params['project_id'];
		}

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

		$mintvine_group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'mintvine'
			)
		));
		// block ad-hoc updates; this is a sanity check to prevent items added into queue from updating if a PM has switched to ad-hoc
		if ($project['FedSurvey']['status'] == 'skipped.adhoc' || empty($project['Project']['group_id']) || $project['Project']['group_id'] == $mintvine_group['Group']['id']) {
			return false;
		}

		// todo: this should be status-dependent
		// todo: sorting/organizing by status?
		// sanity check on touched updates - don't update projects that have already been updated within 10 minutes
		if (false && !is_null($project['Project']['touched']) && strtotime('-10 minutes') < strtotime($project['Project']['touched'])) {
			$this->lecho('FAILED: Updated within last 10 minutes: '.date('H:i:A', strtotime('-10 minutes')).' vs '.date('H:i:A', strtotime($project['Project']['touched'])), $log_file, $log_key);
			return false;
		}

		$lucid_project = $this->getLucidProject(array('SupplierAllocationSurvey', 'SurveyQuotas', 'SurveyQualification', 'SurveyStatistics', 'SurveyGroups'));
		$lucid_survey = $lucid_project['SupplierAllocationSurvey'];

		$payouts = $this->Lucid->payout($lucid_project['SurveyQuotas']);
		$overall_quota = $this->Lucid->quota($lucid_project['SurveyQuotas']);
		MintVine::project_quota_statistics('fulcrum', $overall_quota, $project['Project']['id']);

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

		$direct_allocation = $this->Lucid->direct_allocation($lucid_survey['SupplierAllocations']);
		$client_link = $this->Lucid->client_link($lucid_survey);
		$bid_ir = $this->Lucid->ir($lucid_survey, $lucid_project['SurveyStatistics']);
		$loi = $this->Lucid->loi($lucid_survey, $lucid_project['SurveyStatistics']);
		$min_time = round($loi / 4);
		if ($min_time <= 1) {
			$min_time = null;
		}

		$client_id = $this->Lucid->client($lucid_survey['AccountName'], $this->lucid_group['Group']['id']);
		if (!$client_id) {
			$client_id = $this->lucid_client['Client']['id'];
		}

		$this->set_security_group($project, $lucid_project);

		$project_data = array('Project' => array(
			'id' => $project['Project']['id'],
			'client_rate' => $payouts['client_rate'],
			'award' => $payouts['award'],
			'client_id' => $client_id,
			'prj_name' => $lucid_survey['SurveyName'],
			'bid_ir' => $bid_ir,
			'partner_rate' => $payouts['partner_rate'],
			'user_payout' => $payouts['partner_rate'],
			'quota' => $overall_quota,
			'quota_type' => ($lucid_survey['SurveyQuotaCalcTypeID'] == '2') ? QUOTA_TYPE_CLICKS : QUOTA_TYPE_COMPLETES,
			'est_length' => $loi,
			'country' => FedMappings::country($lucid_survey['CountryLanguageID']),
			'minimum_time' => $min_time,
			'language' => FedMappings::language($lucid_survey['CountryLanguageID']),
			'client_survey_link' => $client_link
		));
		
		//if SurveyTypeID is changed we update its value
		if ($project['FedSurvey']['survey_type_id'] != $lucid_survey['StudyTypeID']) {
			$lucid_study_type = $this->LucidStudyType->find('first', array(
				'conditions' => array(
					'LucidStudyType.key' => $lucid_survey['StudyTypeID']
				),
				'fields' => array('LucidStudyType.name')
			));
			
			if ($lucid_study_type) {
				$this->FedSurvey->create();
				$this->FedSurvey->save(array('FedSurvey' => array(
					'id' => $project['FedSurvey']['id'],
					'survey_type_id' => $lucid_survey['StudyTypeID'],
					'survey_type' => $lucid_study_type['LucidStudyType']['name'],
					'last_updated' => date(DB_DATETIME)
				)), true, array('survey_type_id', 'survey_type', 'last_updated'));
			}
			
			$this->lecho('SurveyTypeID updated for  #F' . $this->params['lucid_project_id'], $log_file, $log_key);
		}
		
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

		$project_status = $project['Project']['status'];
		// if this project is open, and Lucid is reporting a closed project, then close it
		// no need to check survey.active
		if (!$project['Project']['ignore_autoclose'] && in_array($project['Project']['status'], array(PROJECT_STATUS_OPEN, PROJECT_STATUS_SAMPLING))) {
			$still_open = true;
			if ($this->Lucid->is_closed($lucid_project)) {
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
					'description' => 'Closed by Lucid'
				)));
				Utils::save_margin($project['Project']['id']);
				$still_open = false;
				$this->lecho('[SUCCESS] Project #'.$project['Project']['id'].' closed by Lucid', $log_file, $log_key);
			}
			// after update, the new rules fail some internal mechanisms
			elseif (!$this->checkInternalLucidRules($lucid_project, $log_file, $log_key)) {
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
					'failed_data' => json_encode($lucid_project),
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
				if ($project['Project']['status'] == PROJECT_STATUS_SAMPLING) {
					// first, check the performance of the sampling project itself; b/c it's specific to lucid we keep it
					// here insead of the API

					// check to see if clicks have exceeded with no completes
					if ($survey_visit_cache['SurveyVisitCache']['click'] >= $this->settings['fulcrum.sample_threshold'] && $survey_visit_cache['SurveyVisitCache']['complete'] == 0) {
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
							'type' => 'status.closed.sample',
							'description' => 'Closed with '.$survey_visit_cache['SurveyVisitCache']['click'].' clicks and no complete'
						)));
						Utils::save_margin($project['Project']['id']);
					}
					// if we have completes, test to see if we should close it
					elseif ($survey_visit_cache['SurveyVisitCache']['complete'] > 0) {
						$actual_ir = round($survey_visit_cache['SurveyVisitCache']['complete'] / $survey_visit_cache['SurveyVisitCache']['click'], 2) * 100;
						if ($actual_ir < $this->settings['fulcrum.ir_cutoff']) {
							if ($project['SurveyVisitCache']['click'] >= $this->settings['fulcrum.sample_threshold']) {
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
									'type' => 'status.closed.ir',
									'description' => 'IR '.$actual_ir.'% with '.$survey_visit_cache['SurveyVisitCache']['click'].' clicks'
								)));
								Utils::save_margin($project['Project']['id']);
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
			if (!$this->Lucid->is_closed($lucid_project) && $this->checkInternalLucidRules($lucid_project, $log_file, $log_key)) {
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
					$results = $mvapi->post($this->settings['hostname.api'].'/surveys/test_survey_status/'.$project['Project']['id']);
					$response = json_decode($results['body'], true);

					if ($response['open_project']) {
						$project_log = $this->ProjectLog->find('first', array(
							'conditions' => array(
								'ProjectLog.project_id' => $project['Project']['id'],
								'ProjectLog.type' => 'status.closed.fulcrum'
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
								'status' => $count == 0 ? PROJECT_STATUS_STAGING : PROJECT_STATUS_OPEN,
								'ended' => null,
								'active' => true
							)), true, array('status', 'active', 'ended'));

							$this->ProjectLog->create();
							$this->ProjectLog->save(array('ProjectLog' => array(
								'project_id' => $project['Project']['id'],
								'type' => 'status.opened.fulcrum',
								'description' => 'Reopened by Lucid'
							)));
							$project_status = $count == 0 ? PROJECT_STATUS_STAGING : PROJECT_STATUS_OPEN;
						}
					}

				}
			}
		}
		elseif (!$project['Project']['ignore_autoclose'] && $project['Project']['status'] == PROJECT_STATUS_STAGING) {
			$this->launchProject($project, $lucid_project, $log_file, $log_key);
		}


		// write a value that stores the last invite time
		$project_option = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.project_id' => $project['Project']['id'],
				'ProjectOption.name' => 'lucid.lastrun.updated'
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
				'name' => 'lucid.lastrun.updated',
				'value' => date(DB_DATETIME),
				'project_id' => $project['Project']['id']
			)));
		}

		// update qualifications as last step
		$this->params['lucid_project_id'] = $project['FedSurvey']['fed_survey_id'];
		if (in_array($project_status, array(PROJECT_STATUS_OPEN, PROJECT_STATUS_SAMPLING, PROJECT_STATUS_STAGING))) {
			$this->qualifications();
		}

		$this->lecho('Completed update (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
	}

	protected function launchProject($project, $lucid_project, $log_file, $log_key, $from_create = false) {

		// bypass for ignored clients
		if ($project['Client']['do_not_autolaunch']) {
			return false;
		}

		// check skip_project answer
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
		
		$launch_project = true;
		$total_qualifications_list = $this->Lucid->qualifications($lucid_project);
		foreach ($total_qualifications_list as $question_id => $precodes) {
			if (in_array($question_id, array(42, 43, 45))) {
				continue;
			}
			
			$question = $this->Question->find('first', array(
				'fields' => array('Question.id', 'Question.question'),
				'conditions' => array(
					'Question.partner_question_id' => $question_id,
					'Question.partner' => 'lucid',
				),
				'contain' => array(
					'Answer' => array(
						'fields' => array('Answer.id', 'Answer.partner_answer_id'),
						'conditions' => array(
							'Answer.partner_answer_id' => $precodes,
							'Answer.skip_project' => true
						),
					)
				)
			));
			
			if (!empty($question['Answer']) && count($question['Answer']) == count($precodes)) {
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
					$http->post($this->settings['slack.alerts.webhook'], json_encode(array(
						'text' => '[Launch Failed] Project <https://cp.mintvine.com/surveys/dashboard/'.$project['Project']['id'].'|#F'. $this->params['lucid_project_id'] .'>  have "Skip project" qualifications. Question: '.$question['Question']['question'].' (partner question_id: ' . $question_id . ', Answer ids: '.implode(', ', $answer_ids).')',
						'link_names' => 1,
						'username' => 'bernard'
					)));
				} 
				catch (Exception $ex) {
					$this->lecho('Slack api error: Slack alert not sent', $log_file, $log_key);
				}
			}
			
			$this->lecho('FAILED: '.$project['Project']['id'].' (#F'.$this->params['lucid_project_id'].') have "Skip project" qualifications. Question: '.$question['Question']['question'].' (partner question_id: '.$question_id. ', Answer ids: '.implode(', ', $answer_ids).')' , $log_file, $log_key);
			return false;
		}

		if ($this->settings['fulcrum.autolaunch'] == 'true' && $this->checkInternalLucidRules($lucid_project, $log_file, $log_key) && !$this->Lucid->is_closed($lucid_project)) {
			$direct_allocation = $this->Lucid->direct_allocation($lucid_project['SupplierAllocationSurvey']['SupplierAllocations']);

			// reload the parent to see if there was 100% qualifications matching to the query engine
			$qualifications_match = $this->Project->field('qualifications_match', array('Project.id' => $project['Project']['id']));

			// should we be a "fast" follower or the bold leader? note: this should be tweaked when sampling
			if ($project['Project']['temp_qualifications'] || $direct_allocation || ($qualifications_match && isset($lucid_project['SurveyStatistics']['GlobalTrailingSystemConversion']) && $lucid_project['SurveyStatistics']['GlobalTrailingSystemConversion'] >= round($this->settings['fulcrum.ir_cutoff'] / 100, 2))) {
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
				if (!$project['Project']['temp_qualifications']) {
					$this->runQuery($project, 'full');
				}
				$this->lecho('[LAUNCHED] #' . $project['Project']['id'].' (#F'.$this->params['lucid_project_id'].')', $log_file, $log_key);
			}
			// sample this project instead
			else {
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'status' => PROJECT_STATUS_SAMPLING,
					'started' => date(DB_DATETIME),
					'active' => true
				)), true, array('status', 'started', 'active'));

				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'status.sample',
					'description' => 'From autosample'
				)));
				if (!$project['Project']['temp_qualifications']) {
					$this->runQuery($project, 'sample');
				}
				
				$this->lecho('[SAMPLED] #' . $project['Project']['id'].' (#F'.$this->params['lucid_project_id'].')', $log_file, $log_key);
			}
		}
		else {
			$this->lecho('[FAILED LAUNCH] #' . $project['Project']['id'].' (#F'.$this->params['lucid_project_id'].') Failed internal rules.', $log_file, $log_key);
		}
	}

	protected function runQuery($project, $launch_type = 'full') {
		// dev instances should not actually execute
		if (defined('IS_DEV_INSTANCE') && IS_DEV_INSTANCE === true) {
			return true;
		}
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
					CakeLog::write('fulcrum.auto', $message);
					continue;
				}
			}

			$results = QueryEngine::execute(json_decode($query['Query']['query_string'], true));
			if ($results['count']['total'] == 0) {
				$message = 'Skipped ' . $project['Project']['id'] . ' because query has no users';
				echo $message . "\n";
				CakeLog::write('fulcrum.auto', $message);
				continue;
			}

			$survey_reach = 0;
			if ($launch_type == 'sample') {
				$setting = $this->Setting->find('first', array(
					'conditions' => array(
						'Setting.name' => 'fulcrum.sample_size',
						'Setting.deleted' => false
					)
				));
				$survey_reach = ($results['count']['total'] < $setting['Setting']['value']) ? $results['count']['total'] : $setting['Setting']['value'];
			}
			else {
				$survey_reach = MintVine::query_amount($project, $results['count']['total'], $query);
			}

			if ($survey_reach == 0) {
				$message = 'Skipped ' . $project['Project']['id'] . ' because query has no quota left';
				echo $message . "\n";
				CakeLog::write('fulcrum.auto', $message);
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

			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'query.executed',
				'query_id' => $query['Query']['id'],
				'description' => 'Total sent : ' . $survey_reach . ', ' . $launch_type . ' launch'
			)));

			$message = 'Query executed: '.$query_str;
			echo $message . "\n";
			CakeLog::write('fulcrum.auto', $message);
			$sent = true;
		}

		return $sent;
	}

	protected function getQuery($project, $query_params, $parent_query_id = 0, $quota = null) {

		/* filter query has different name - includes quota_id */
		if (!is_null($quota)) {
			$query_name = 'Lucid #' . $project['FedSurvey']['fed_survey_id'] . ' Quota #' . $quota['SurveyQuotaID'];
			$query_old_name = 'Fulcrum #' . $project['FedSurvey']['fed_survey_id'] . ' Quota #' . $quota['SurveyQuotaID'];
		}
		else {
			$query_name = 'Lucid #' . $project['FedSurvey']['fed_survey_id'] . ' Qualifications';
			$query_old_name = 'Fulcrum #' . $project['FedSurvey']['fed_survey_id'] . ' Qualifications';
		}
		// these old names are related to https://basecamp.com/2045906/projects/1413421/todos/244843263

		$query = $this->Query->find('first', array(
			'conditions' => array(
				'Query.query_name' => array($query_name, $query_old_name),
				'Query.survey_id' => $project['Project']['id']
			),
			'order' => 'Query.id DESC' // multiple queries can exist with same name: retrieve the last one
		));

		if ($query) {
			$query_id = $query['Query']['id'];
		}

		if ($this->settings['lucid.queryengine'] == 'v2') {
			$country = $query_params['country'];
			unset($query_params['country']);
			$query_params['partner'] = 'lucid';

			App::uses('HttpSocket', 'Network/Http');
			$http = new HttpSocket(array(
				'timeout' => 30,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$http->configAuth('Basic', $this->settings['qe.mintvine.username'], $this->settings['qe.mintvine.password']);
			try {
				$results = $http->post($this->settings['hostname.qe'].'/query', json_encode($query_params), array(
					'header' => array('Content-Type' => 'application/json')
				));
			} catch (Exception $ex) {
				echo 'QE2 api call failed.'. "\n";
				return false;
			}

			$results = json_decode($results['body'], true);
			if (!isset($results['panelist_ids'])) {
				return false;
			}

			$query_params = array('country' => $country);
			if (!empty($results['panelist_ids'])) {
				$query_params['user_id'] = array_values($results['panelist_ids']);
			}
		}
		else {
			if (isset($query_params['postal_code'])) {
				$query_params['postal_code'] = array_values(array_unique($query_params['postal_code']));
			}

			// if we've matched against hispanic, then add the ethnicity values
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
						$qualification_user = $this->QualificationUser->find('first', array(
							'fields' => array('QualificationUser.id'),
							'conditions' => array(
								'QualificationUser.deleted' => false,
								'QualificationUser.user_id' => $survey_user['SurveyUser']['user_id'],
								'QualificationUser.qualification_id' => $parent_qualification_id
							)
						));
						if ($qualification_user) {
							$this->QualificationUser->delete($qualification_user['QualificationUser']['id']);
						}
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

			if ($query_statistic && $query_statistic['QueryStatistic']['quota'] != ($quota['NumberOfRespondents'] + $query_statistic['QueryStatistic']['completes'])) {
				$this->QueryStatistic->create();
				$this->QueryStatistic->save(array('QueryStatistic' => array(
					'id' => $query_statistic['QueryStatistic']['id'],
					'quota' => $quota['NumberOfRespondents'] + $query_statistic['QueryStatistic']['completes'],
					'closed' => !is_null($quota) && empty($quota['NumberOfRespondents']) ? date(DB_DATETIME) : null
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
				'parent_id' => $parent_query_id,
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
						'quota' => $quota['NumberOfRespondents'],
					)));
				}

				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'query.created',
					'description' => 'Query.id '. $query_id . ' created'
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
								'Setting.name' => 'fulcrum.sample_size',
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

			$this->FedSurvey->create();
			$this->FedSurvey->save(array('FedSurvey' => array(
				'id' => $project['FedSurvey']['id'],
				'total' => $total
			)), true, array('total'));
		}
		else {
			// if its a filter query and parent has been changed, update the parent id
			if ($query['Query']['parent_id'] && $parent_query_id && $query['Query']['parent_id'] != $parent_query_id) {
				$this->Query->create();
				$this->Query->save(array('Query' => array(
					'id' => $query['Query']['id'],
					'parent_id' => $parent_query_id,
				)), true, array('parent_id'));
			}
		}
		return $query_id;
	}

	protected function getLucidQuestion($question_id, $language_id) {
		// Check if we have the question in db
		$this->FedQuestion->primaryKey = 'question_id';
		$this->FedQuestion->bindModel(array('hasMany' => array(
			'FedAnswer' => array(
				'foreignKey' => 'question_id',
				'conditions' => array('FedAnswer.language_id' => $language_id)
			)
		)));
		$fed_question = $this->FedQuestion->find('first', array(
			'conditions' => array(
				'FedQuestion.language_id' => $language_id,
				'FedQuestion.question_id' => $question_id
			)
		));

		if (!$fed_question) {
			$http = new HttpSocket(array(
				'timeout' => 30,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$params = array('key' => $this->settings['lucid.api.key']);
			$url = $this->settings['lucid.host'].'Lookup/v1/QuestionLibrary/QuestionById/'.$language_id.'/'.$question_id;
			$response = $http->get($url, $params);
			$api_question = json_decode($response['body'], true);

			// to prevent updating existing question (if primary key is question_id)
			$fedQuestionSource = $this->FedQuestion->getDataSource();
			$fedQuestionSource->begin();
			$this->FedQuestion->primaryKey = 'id';
			$this->FedQuestion->create();
			$this->FedQuestion->save(array('FedQuestion' => array(
				'language_id' => $language_id,
				'question_id' => $question_id,
				'question' => $api_question['Question']['QuestionText'],
				'type' => $api_question['Question']['QuestionType']
			)));
			$new_question_id = $this->FedQuestion->getLastInsertID();
			$fedQuestionSource->commit();

			if (in_array($api_question['Question']['QuestionType'], array(QUESTION_TYPE_SINGLE, QUESTION_TYPE_MULTIPLE, QUESTION_TYPE_DUMMY))) {
				$fed_answers = $this->FedAnswer->find('all', array(
					'conditions' => array(
						'FedAnswer.language_id' => $language_id,
						'FedAnswer.question_id' => $question_id
					)
				));
				if (!$fed_answers) {
					$url = $this->settings['lucid.host'].'Lookup/v1/QuestionLibrary/AllQuestionOptions/'.$language_id.'/'.$question_id;
					$response = $http->get($url, $params);
					$api_question_options = json_decode($response['body'], true);

					foreach ($api_question_options['QuestionOptions'] as $option) {
						$this->FedAnswer->create();
						$this->FedAnswer->save(array('FedAnswer' => array(
							'question_id' => $question_id,
							'language_id' => $language_id,
							'precode' => $option['Precode'],
							'answer' => $option['OptionText']
						)));
					}
				}
			}

			$this->FedQuestion->primaryKey = 'question_id';
			$this->FedQuestion->bindModel(array('hasMany' => array(
				'FedAnswer' => array(
					'foreignKey' => 'question_id',
					'conditions' => array('FedAnswer.language_id' => $language_id)
				)
			)));
			$fed_question = $this->FedQuestion->findById($new_question_id);
		}

		return $fed_question;
	}

	// performance-based closed rules are handled on the API; this is strictly to handle bad properties
	protected function checkInternalLucidRules($lucid_project, $log_file = null, $log_key = null) {
		$loi = $this->Lucid->loi($lucid_project['SupplierAllocationSurvey'], $lucid_project['SurveyStatistics']);
		$ir = $this->Lucid->ir($lucid_project['SupplierAllocationSurvey'], $lucid_project['SurveyStatistics']);
		$client_link = $this->Lucid->client_link($lucid_project['SupplierAllocationSurvey']);
		$payouts = $this->Lucid->payout($lucid_project['SurveyQuotas']);
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
		// innovate projects
		elseif ($lucid_project['SupplierAllocationSurvey']['StudyTypeID'] == 23) {
			$return = false;
			$message = '[FAILED RULE] StudyType 23';
		}
		// award is less than floor
		elseif ($payouts['award'] < $this->settings['fulcrum.floor.award']) {
			$return = false;
			$message = '[FAILED RULE] Award floor ('.$payouts['award'].' < '.$this->settings['fulcrum.floor.award'].')';
		}
		// check floor EPC
		elseif (!empty($ir) && ($payouts['client_rate'] * $ir / 100) <= ($this->lucid_group['Group']['epc_floor_cents'] / 100)) {
			$return = false;
			$message = '[FAILED RULE] EPC floor '.$payouts['client_rate'].' '.$ir.' ('.($payouts['client_rate'] * $ir / 100).' <= '.($this->lucid_group['Group']['epc_floor_cents'] / 100).')';
		}
		// ratio floor rule
		elseif (!empty($loi) && $loi * $this->settings['fulcrum.floor.loi.ratio'] > $payouts['award']) {
			$return = false;
			$message = '[FAILED RULE] Award ratio ('.($loi * $this->settings['fulcrum.floor.loi.ratio']).' > '.$payouts['award'].')';
		}
		
		// long LOIs are skipped
		elseif (!is_null($this->lucid_group['Group']['max_loi_minutes']) && $loi > ($this->lucid_group['Group']['max_loi_minutes'] * 1.1)) {
			$return = false;
			$message = '[FAILED RULE] Long LOI ('.$loi.' minutes)';
		}
		// unsupported country
		elseif (!in_array($lucid_project['SupplierAllocationSurvey']['CountryLanguageID'], array(6, 8, 9))) {
			$return = false;
			$message = '[FAILED RULE] Unsupported country ('.$lucid_project['SupplierAllocationSurvey']['CountryLanguageID'].')';
		}
		// unsupported study type
		elseif (in_array($lucid_project['SupplierAllocationSurvey']['StudyTypeID'], array(11))) {
			$return = false;
			$message = '[FAILED RULE] Unsupported study type ('.$lucid_project['SupplierAllocationSurvey']['StudyTypeID'].')';
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
	protected function getLucidProject($items_to_return = array('SupplierAllocationSurvey', 'SurveyQuotas')) {
		$return = array();

		$http = new HttpSocket(array(
			'timeout' => 30,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$params = array('key' => $this->settings['lucid.api.key']);

		if (in_array('SupplierAllocationSurvey', $items_to_return)) {
			$url = $this->settings['lucid.host'].'Supply/v1/Surveys/SupplierAllocations/BySurveyNumber/'.$this->params['lucid_project_id'];
			$response = $http->get($url, $params);
			$body = json_decode($response['body'], true);
			$return['SupplierAllocationSurvey'] = $body['SupplierAllocationSurvey'];
		}
		if (in_array('SurveyQuotas', $items_to_return)) {
			$url = $this->settings['lucid.host'].'Supply/v1/SurveyQuotas/BySurveyNumber/'.$this->params['lucid_project_id'].'/'.$this->settings['lucid.supplier.code'];
			$response = $http->get($url, $params);
			$body = json_decode($response['body'], true);
			$return['SurveyQuotas'] = $body['SurveyQuotas'];
			$return['SurveyStillLive'] = $body['SurveyStillLive'];
		}
		if (in_array('SurveyGroups', $items_to_return)) {
			$url = $this->settings['lucid.host'].'Supply/v1/Surveys/SurveyGroups/BySurveyNumber/'.$this->params['lucid_project_id'].'/'.$this->settings['lucid.supplier.code'];
			$response = $http->get($url, $params);
			$body = json_decode($response['body'], true);
			$return['SurveyGroups'] = $body['SurveyGroups'];
		}
		if (in_array('SurveyQualification', $items_to_return)) {
			$url = $this->settings['lucid.host'].'Supply/v1/SurveyQualifications/BySurveyNumberForOfferwall/'.$this->params['lucid_project_id'];
			$response = $http->get($url, $params);
			$body = json_decode($response['body'], true);
			$return['SurveyQualification'] = $body['SurveyQualification'];
		}
		if (in_array('SurveyStatistics', $items_to_return)) {
			$url = $this->settings['lucid.host'].'Supply/v1/SurveyStatistics/BySurveyNumber/'.$this->params['lucid_project_id'].'/'.$this->settings['lucid.supplier.code'];
			$response = $http->get($url, $params);
			$body = json_decode($response['body'], true);
			$return['SurveyStatistics'] = $body['SurveyStatistics'];
		}
		if (in_array('SurveyQualifiedRespondents', $items_to_return)) {
			$url = $this->settings['lucid.host'].'Supply/v1/SurveyQualifiedRespondents/BySurveyNumberSupplierCode/'.$this->params['lucid_project_id'].'/'.$this->settings['lucid.supplier.code'];
			$response = $http->get($url, $params);
			$body = json_decode($response['body'], true);
			$return['SurveyQualifiedRespondents'] = $body['SurveyQualifiedRespondents'];
		}
		return $return;
	}

	private function loadSettings($log_file, $log_key, $time_start) {
		$keys = array(
			// Lucid API credentials
			'lucid.host',
			'lucid.api.key',
			'lucid.supplier.code',

			// various settings
			'fulcrum.floor.loi.ratio',
			'fulcrum.ir_cutoff',
			'fulcrum.floor.award',
			'fulcrum.autolaunch',
			'fulcrum.sample_threshold',
			'lucid.followup.ceiling',
			'lucid.filter.account_name',
			'lucid.queryengine',

			// QE2 API
			'qe.mintvine.username',
			'qe.mintvine.password',
			'hostname.qe',

			// mv API
			'api.mintvine.username',
			'api.mintvine.password',
			'hostname.api',

			// AWS S3
			's3.access',
			's3.secret',
			's3.host',
			's3.bucket',

			// slack channels for alerts
			'slack.qe2.webhook',
			'slack.alerts.webhook',

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
			$this->lecho('FAILED: You are missing required Lucid settings: '.implode(', ', $diff_keys), $log_file, $log_key);
			$this->lecho('Completed loadSettings (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}

		$this->lucid_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'fulcrum'
			)
		));
		$this->lucid_client = $this->Client->find('first', array(
			'conditions' => array(
				'Client.key' => 'fulcrum',
				'Client.deleted' => false
			)
		));
		$this->mv_partner = $this->Partner->find('first', array(
			'conditions' => array(
				'Partner.key' => 'mintvine',
				'Partner.deleted' => false
			)
		));
		if (!$this->lucid_group || !$this->lucid_client || !$this->mv_partner) {
			$this->lecho('FAILED: Missing client, group, or partner', $log_file, $log_key);
			$this->lecho('Completed loadSettings (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		return true;
	}

	public function set_security_group($fed_survey, $fulcrum_survey) {
		// update the security group values
		$survey_group_exists = $fulcrum_survey['SupplierAllocationSurvey']['SurveyGroupExists'] == '1';

		if ($survey_group_exists) {
			if (empty($fulcrum_survey['SurveyGroups'])) {
				$survey_group_exists = false;
			}
		}
		
		$fulcrum_survey_groups = $this->FulcrumSurveyGroup->find('list', array(
			'fields' => array('FulcrumSurveyGroup.id', 'FulcrumSurveyGroup.survey_group_id'),
			'conditions' => array(
				'FulcrumSurveyGroup.project_id' => $fed_survey['FedSurvey']['survey_id'],
				'FulcrumSurveyGroup.deleted is null'
			)
		));
		$survey_groups_api = Hash::extract($fulcrum_survey, 'SurveyGroups.{n}.SurveyGroupID');
		if ($survey_group_exists) {
			$removed_from_groups = array_diff($fulcrum_survey_groups, $survey_groups_api);
			foreach ($removed_from_groups as $fulcrum_survey_group_id => $survey_group_id) {
				$this->FulcrumSurveyGroup->create();
				$this->FulcrumSurveyGroup->save(array('FulcrumSurveyGroup' => array(
					'id' => $fulcrum_survey_group_id,
					'deleted' => date(DB_DATETIME),
					'modified' => false
				)), true, array('deleted'));
			}
			
			foreach ($fulcrum_survey['SurveyGroups'] as $survey_group) {
				$group_fed_surveys = $this->FedSurvey->find('all', array(
					'fields' => array('FedSurvey.id', 'FedSurvey.fed_survey_id', 'FedSurvey.survey_id', 'FedSurvey.survey_group_exists'),
					'conditions' => array(
						'FedSurvey.fed_survey_id' => $survey_group['SurveyGroupSurveys'],
						'FedSurvey.survey_id >' => '0'
					)
				));
				$api_survey_entries = array();
				$fed_survey_lists = array();
				if ($group_fed_surveys) {
					// $survey_group['SurveyGroupSurveys'] this is actual api surveys entries for group- but we ignores those projects not created at mintvine
					$api_survey_entries = Hash::extract($group_fed_surveys, '{n}.FedSurvey.fed_survey_id');
					foreach ($group_fed_surveys as $group_fed_survey) {
						$fed_survey_lists[$group_fed_survey['FedSurvey']['fed_survey_id']] = $group_fed_survey;
					}
				}
				
				$db_survey_entries = $this->FulcrumSurveyGroup->find('list', array(
					'fields' => array('FulcrumSurveyGroup.id', 'FulcrumSurveyGroup.fulcrum_survey_id'),
					'conditions' => array(
						'FulcrumSurveyGroup.survey_group_id' => $survey_group['SurveyGroupID'],
						'FulcrumSurveyGroup.deleted is null'
					)
				));
				
				$missing_surveys_from_db = array_diff($api_survey_entries, $db_survey_entries);
				$missing_surveys_from_api = array_diff($db_survey_entries, $api_survey_entries);
				
				if (!empty($missing_surveys_from_db)) {
					foreach ($missing_surveys_from_db as $fulcrum_survey_id) {
						$this->FulcrumSurveyGroup->create();
						$this->FulcrumSurveyGroup->save(array('FulcrumSurveyGroup' => array(
							'project_id' => $fed_survey_lists[$fulcrum_survey_id]['FedSurvey']['survey_id'],
							'fulcrum_survey_id' => $fulcrum_survey_id,
							'survey_group_id' => $survey_group['SurveyGroupID']
						)));
						
						if (!$fed_survey_lists[$fulcrum_survey_id]['FedSurvey']['survey_group_exists']) {
							$this->FedSurvey->create();
							$this->FedSurvey->save(array('FedSurvey' => array(
								'id' => $fed_survey_lists[$fulcrum_survey_id]['FedSurvey']['id'],
								'survey_group_exists' => true
							)), true, array('survey_group_exists'));
						}
					}
				}
				
				if (!empty($missing_surveys_from_api)) {
					foreach ($missing_surveys_from_api as $fulcrum_survey_id) {
						$fulcrum_survey_group_id = array_search($fulcrum_survey_id, $db_survey_entries);
						if ($fulcrum_survey_group_id !== false) {
							$this->FulcrumSurveyGroup->create();
							$this->FulcrumSurveyGroup->save(array('FulcrumSurveyGroup' => array(
								'id' => $fulcrum_survey_group_id,
								'deleted' => date(DB_DATETIME),
								'modified' => false
							)), true, array('deleted'));
						}
					}
				}
			}
		}
		else {
			if ($fulcrum_survey_groups) {
				foreach ($fulcrum_survey_groups as $fulcrum_survey_group_id => $survey_group_id) {
					$this->FulcrumSurveyGroup->create();
					$this->FulcrumSurveyGroup->save(array('FulcrumSurveyGroup' => array(
						'id' => $fulcrum_survey_group_id,
						'deleted' => date(DB_DATETIME),
						'modified' => false
					)), true, array('deleted'));
				}
			}
			
			if ($fed_survey['FedSurvey']['survey_group_exists']) {
				$this->FedSurvey->create();
				$this->FedSurvey->save(array('FedSurvey' => array(
					'id' => $fed_survey['FedSurvey']['id'],
					'survey_group_exists' => false
				)), true, array('survey_group_exists'));
			}
		}
	}

	public function debug_offerwall() {
		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => array('lucid.host', 'lucid.api.key', 'lucid.supplier.code'),
				'Setting.deleted' => false
			)
		));

		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$params = array('key' => $settings['lucid.api.key']);

		$desired_countries = array(
			6, // Canada
			8, // UK
			9 // US
		);

		// get our total allocation across the board
		$url = $settings['lucid.host'].'Supply/v1/Surveys/SupplierAllocations/All/'.$settings['lucid.supplier.code'];
		$response = $http->get($url, $params);
		print_r($response);
	}

	// output all API calls for a given federated survey
	public function debug() {
		if (!isset($this->params['lucid_project_id'])) {
			echo 'FAILED: You are missing lucid_project_id';
			return false;
		}

		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => array('lucid.host', 'lucid.api.key', 'lucid.supplier.code'),
				'Setting.deleted' => false
			)
		));

		$http = new HttpSocket(array(
			'timeout' => 30,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$params = array('key' => $settings['lucid.api.key']);

		$url = $settings['lucid.host'].'Supply/v1/SurveyQuotas/BySurveyNumber/'.$this->params['lucid_project_id'].'/'.$settings['lucid.supplier.code'];
		$response = $http->get($url, $params);
		$this->out($url);
		$this->out(print_r(json_decode($response['body'], true), true));

		$url = $settings['lucid.host'].'Supply/v1/Surveys/SurveyGroups/BySurveyNumber/'.$this->params['lucid_project_id'].'/'.$settings['lucid.supplier.code'];
		$response = $http->get($url, $params);
		$this->out($url);
		$this->out(print_r(json_decode($response['body'], true), true));

		$url = $settings['lucid.host'].'Supply/v1/SurveyQualifications/BySurveyNumberForOfferwall/'.$this->params['lucid_project_id'];
		$response = $http->get($url, $params);
		$this->out($url);
		$this->out(print_r(json_decode($response['body'], true), true));

		$url = $settings['lucid.host'].'Supply/v1/Surveys/SupplierAllocations/BySurveyNumber/'.$this->params['lucid_project_id'];
		$response = $http->get($url, $params);
		$this->out($url);
		$this->out(print_r(json_decode($response['body'], true), true));

		$url = $settings['lucid.host'].'Supply/v1/SurveyStatistics/BySurveyNumber/'.$this->params['lucid_project_id'].'/'.$settings['lucid.supplier.code'];
		$response = $http->get($url, $params);
		$this->out($url);
		$this->out(print_r(json_decode($response['body'], true), true));

		$url = $settings['lucid.host'].'Supply/v1/SurveyStatistics/BySurveyNumber/'.$this->params['lucid_project_id'].'/'.$settings['lucid.supplier.code'].'/Global/Trailing';
		$response = $http->get($url, $params);
		$this->out($url);
		$this->out(print_r(json_decode($response['body'], true), true));

		$url = $settings['lucid.host'].'Supply/v1/SurveyStatistics/BySurveyNumber/'.$this->params['lucid_project_id'].'/'.$settings['lucid.supplier.code'].'/Supplier/Lifetime';
		$response = $http->get($url, $params);
		$this->out($url);
		$this->out(print_r(json_decode($response['body'], true), true));

		$url = $settings['lucid.host'].'Supply/v1/SurveyStatistics/BySurveyNumber/'.$this->params['lucid_project_id'].'/'.$settings['lucid.supplier.code'].'/Supplier/Trailing';
		$response = $http->get($url, $params);
		$this->out($url);
		$this->out(print_r(json_decode($response['body'], true), true));

		$url = $settings['lucid.host'].'Supply/v1/SurveyQualifiedRespondents/BySurveyNumberSupplierCode/'.$this->params['lucid_project_id'].'/'.$settings['lucid.supplier.code'];
		$response = $http->get($url, $params);
		$this->out($url);
		$this->out(print_r(json_decode($response['body'], true), true));
	}

	public function fix_clients() {
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
		ini_set('memory_limit', '2048M');
		$log_file = 'lucid.clients';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			$this->lecho('Settings not found', $log_file, $log_key);
			return false;
		}

		$groups = $this->Group->find('list', array(
			'fields' => array('id', 'key'),
			'conditions' => array(
				'Group.key' => array('fulcrum')
			)
		));

		if (empty($groups)) {
			echo 'Missing group';
			return;
		}

		$this->Project->bindModel(array('hasOne' => array(
			'FedSurvey' => array(
				'className' => 'FedSurvey',
				'foreignKey' => 'survey_id'
			)
		)));
		$this->Project->unbindModel(array(
			'hasMany' => array('SurveyPartner', 'ProjectOption'),
			'belongsTo' => array('Client', 'Group')
		));
		$projects = $this->Project->find('all', array(
			'fields' => array('FedSurvey.fed_survey_id', 'Project.client_id', 'Project.id'),
			'conditions' => array(
				'Project.group_id' => array_keys($groups),
				'Project.client_id' => 50 // hard code federated sample client
			),
			'order' => 'Project.id desc'
		));
		$this->lecho('Updating '.count($projects).' projects', $log_file, $log_key);
		foreach ($projects as $project) {
			$this->params['lucid_project_id'] = $project['FedSurvey']['fed_survey_id'];
			$lucid_project = $this->getLucidProject(array('SupplierAllocationSurvey'));
			if (empty($lucid_project['SupplierAllocationSurvey']['AccountName'])) {
				$this->lecho('Client not found for Lucid #'.  $project['FedSurvey']['fed_survey_id'] . '(' . $project['Project']['id'] . '). The project may no longer exist.', $log_file, $log_key);
				continue;
			}

			$client_id = $this->Lucid->client($lucid_project['SupplierAllocationSurvey']['AccountName'], $this->lucid_group['Group']['id']);
			if (!$client_id) {
				$client_id = $this->lucid_client['Client']['id'];
			}

			if ($project['Project']['client_id'] == $client_id) {
				$this->lecho('Client is fine. Lucid #' . $project['FedSurvey']['fed_survey_id'] . '(' . $project['Project']['id'] . ')', $log_file, $log_key);
				continue;
			}

			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'client_id' => $client_id,
				'modified' => false
			)), true, array('client_id'));

			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'client.updated',
				'description' => 'Client updated'
			)));

			$this->lecho('Client updated for Lucid #' . $project['FedSurvey']['fed_survey_id'].'('.$project['Project']['id'].')', $log_file, $log_key);
		}
	}

	// Analyze reconciliation report and put the missing and rejected completes in reconciliation_analyses table.
	public function analyze() {
		ini_set('memory_limit', '1024M');
		$log_file = 'reconciliation';
		$log_key = strtoupper(Utils::rand('4'));

		if (!isset($this->args[0]) || empty($this->args[0])) {
			$this->lecho('Provide reconciliation id please.', $log_file, $log_key);
			return;
		}

		$reconciliation = $this->Reconciliation->findById($this->args[0]);
		if (!$reconciliation) {
			$this->lecho('Reconciliation not found reconciliation#'.$this->args[0], $log_file, $log_key);
			return;
		}

		if ($reconciliation['Reconciliation']['status'] != RECONCILIATION_IMPORTED) {
			$this->lecho('[ERROR] Reconciliation #'.$this->args[0] . ' Reconciliation status should be '.RECONCILIATION_IMPORTED . '(status:'.$reconciliation['Reconciliation']['status'].')', $log_file, $log_key);
			$this->update_status($this->args[0], RECONCILIATION_ERROR);
			return;
		}

		$time_start = microtime(true);
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			$this->lecho('[ERROR] Reconciliation #'.$this->args[0] . ' loadSettings() error.', $log_file, $log_key);
			$this->update_status($this->args[0], RECONCILIATION_ERROR);
			return;
		}

		CakePlugin::load('Uploader');
		App::import('Vendor', 'Uploader.S3');
		$S3 = new S3($this->settings['s3.access'], $this->settings['s3.secret'], false, $this->settings['s3.host']);
		$url = $S3->getAuthenticatedURL($this->settings['s3.bucket'], $reconciliation['Reconciliation']['filepath'], 3600, false, false);
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$results = $http->get($url);
		if ($results->code != 200) {
			$this->lecho('[ERROR] Reconciliation #'.$this->args[0].' Cannot find file '.$reconciliation['Reconciliation']['filepath'], $log_file, $log_key);
			$this->update_status($this->args[0], RECONCILIATION_ERROR);
			return;
		}

		$lucid_data = Utils::multiexplode(array("\n", "\r\n", "\r", "\n\r"), $results->body);
		foreach ($lucid_data as &$row) {
			$row = str_getcsv($row); //parse the items in rows
		}

		unset($row);
		$lucid_data = $this->ReconcileLucid->cleanFile($lucid_data);
		if (!$lucid_data) {
			$this->lecho('[ERROR] Reconciliation #'.$this->args[0]. ' File: '.$reconciliation['Reconciliation']['filepath']. ' is either empty, or the headers (first row) are not correct.' , $log_file, $log_key);
			$this->update_status($this->args[0], RECONCILIATION_ERROR);
			return;
		}

		$datetimes = $this->ReconcileLucid->getMinMaxDates($lucid_data);

		// we have some clock drifting; +/ 10 minutes on each side
		$datetimes['min_datetime'] = $datetimes['min_datetime'] - 600;
		$datetimes['max_datetime'] = $datetimes['max_datetime'] + 600;

		// change lucid timezone (Central time) to utc for timestamp sync
		$datetimes['min_datetime'] = Utils::change_tz_to_utc(date(DB_DATETIME, $datetimes['min_datetime']), DB_DATETIME, 'America/Chicago');
		$datetimes['max_datetime'] = Utils::change_tz_to_utc(date(DB_DATETIME, $datetimes['max_datetime']), DB_DATETIME, 'America/Chicago');

		$lucid_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'fulcrum'
			)
		));
		$projects = $this->Project->find('list', array(
			'fields' => array('id'),
			'conditions' => array(
				'Project.group_id' => $lucid_group['Group']['id'],
				'Project.status' => array(PROJECT_STATUS_OPEN, PROJECT_STATUS_SAMPLING, PROJECT_STATUS_CLOSED)
			)
		));
		$survey_completes = $this->SurveyVisit->find('all', array(
			'conditions' => array(
				'SurveyVisit.survey_id' => $projects,
				'SurveyVisit.type' => SURVEY_COMPLETED,
				'SurveyVisit.created >=' => $datetimes['min_datetime'],
				'SurveyVisit.created <=' => $datetimes['max_datetime']
			)
		));
		$mv_ids = $lucid_ids = array();
		if ($survey_completes) {
			foreach ($survey_completes as $survey_complete) {
				$mv_ids[] = $survey_complete['SurveyVisit']['hash'];
			}
		}

		$data = array();
		$data[] = array(
			'Lucid Reported: '.count($lucid_data),
			'MintVine Matched: '.count($survey_completes)
		);
		$data[] = array('---');
		$data[] = array('Hashes Reported by Lucid but missing in MintVine:');

		// first find unmatched values
		foreach ($lucid_data as $key => $row) {
			$lucid_ids[] = $row[$this->ReconcileLucid->indexes['hash']];
			if (in_array($row[$this->ReconcileLucid->indexes['hash']], $mv_ids)) {
				unset($lucid_data[$key]);
			}
			else {
				// Find the survey id
				$fed_survey = $this->FedSurvey->find('first', array(
					'conditions' => array(
						'fed_survey_id' => $row[$this->ReconcileLucid->indexes['survey_id']]
					)
				));

				$user_id = '';
				// Try to find the click in survey_users - if a complete got missed due to some reason, we most probably should have logged the click
				$survey_click = $this->SurveyVisit->find('first', array(
					'conditions' => array(
						'SurveyVisit.survey_id' => ($fed_survey) ? $fed_survey['FedSurvey']['survey_id'] : '',
						'SurveyVisit.type' => SURVEY_CLICK,
						'SurveyVisit.hash' => $row[$this->ReconcileLucid->indexes['hash']]
					)
				));
				if ($survey_click) {
					$user_id = explode('-', $survey_click['SurveyVisit']['partner_user_id']);
					$user_id = $user_id[1];
				}

				// Save the missing complete in ReconciliationAnalysis
				$this->ReconciliationAnalysis->create();
				$this->ReconciliationAnalysis->save(array('ReconciliationAnalysis' => array(
					'reconciliation_id' => $reconciliation['Reconciliation']['id'],
					'user_id' => $user_id,
					'hash' => $row[$this->ReconcileLucid->indexes['hash']],
					'survey_id' => ($fed_survey) ? $fed_survey['FedSurvey']['survey_id'] : '',
					'type' => RECONCILIATION_ANALYSIS_MISSING_COMPLETE,
					'timestamp' => date(DB_DATETIME, strtotime($row[$this->ReconcileLucid->indexes['timestamp']])),
				)));
			}
		}
		$data = $data + $lucid_data;

		$data[] = array('---');
		$data[] = array('Hashes Reported by MintVine but missing in Lucid:');

		if ($survey_completes) {
			foreach ($survey_completes as $survey_complete) {
				if (!in_array($survey_complete['SurveyVisit']['hash'], $lucid_ids)) {
					$data[] = array(
						$survey_complete['SurveyVisit']['hash'],
						$survey_complete['SurveyVisit']['created']
					);

					$user_id = explode('-', $survey_complete['SurveyVisit']['partner_user_id']);
					$user_id = $user_id[1];
					$transaction = $this->Transaction->find('first', array(
						'fields' => array('Transaction.id'),
						'conditions' => array(
							'Transaction.type_id' => TRANSACTION_SURVEY,
							'Transaction.linked_to_id' => $survey_complete['SurveyVisit']['survey_id'],
							'Transaction.user_id' => $user_id,
							'Transaction.deleted' => null,
						)
					));

					// Save the Rejected completes in ReconciliationAnalysis
					$this->ReconciliationAnalysis->create();
					$this->ReconciliationAnalysis->save(array('ReconciliationAnalysis' => array(
						'reconciliation_id' => $reconciliation['Reconciliation']['id'],
						'user_id' => $user_id,
						'transaction_id' => ($transaction) ? $transaction['Transaction']['id'] : '',
						'hash' =>  $survey_complete['SurveyVisit']['hash'],
						'survey_id' => $survey_complete['SurveyVisit']['survey_id'],
						'type' => RECONCILIATION_ANALYSIS_REJECTED_COMPLETE,
						'timestamp' => $survey_complete['SurveyVisit']['created'],
					)));
				}
			}
		}

		$temp_file = tempnam(sys_get_temp_dir(), 'Lucid');
		$fp = fopen($temp_file, 'w');
		// Each iteration of this while loop will be a row in your .csv file where each field corresponds to the heading of the column
		foreach ($data as $row) {
			fputcsv($fp, $row, ',', '"');
		}

		$file_name = 'reconciliations/analyzed/'.$this->args[0].'.csv';
		if ($S3->putObject(S3::inputFile($temp_file), $this->settings['s3.bucket'], $file_name, S3::ACL_PRIVATE)) {
			$this->lecho($file_name. ' generated successfully.', $log_file, $log_key);
		}

		fclose($fp);
		$this->update_status($reconciliation['Reconciliation']['id'], RECONCILIATION_ANALYZED);
	}

	private function update_status($id, $status) {
		$this->Reconciliation->create();
		$this->Reconciliation->save(array('Reconciliation' => array(
			'id' => $id,
			'status' => $status
		)), true, array('status'));
	}

	// this is set on cron...
	public function log_completes() {
		$lucid_settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array(
					'lucid.active',
					'lucid.maintenance',
					'lucid.api.key',
					'lucid.supplier.code',
					'lucid.host'
				),
				'Setting.deleted' => false
			),
			'fields' => array(
				'Setting.name',
				'Setting.value'
			)
		));

		if (count($lucid_settings) < 5) {
			return;
		}

		if ($lucid_settings['lucid.active'] == 'false' || $lucid_settings['lucid.maintenance'] == 'true') {
			return;
		}

		$group = $this->Group->find('first', array(
			'fields' => array(
				'Group.id'
			),
			'conditions' => array(
				'Group.key' => 'fulcrum',
			),
			'recursive' => -1
		));

		ini_set('memory_limit', '1024M');
		$log_file = 'lucid.log_completes';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting', $log_file, $log_key);

		$http = new HttpSocket(array(
			'timeout' => 240,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$params = array('key' => $lucid_settings['lucid.api.key']);

		$desired_countries = array(
			6, // Canada
			8, // UK
			9 // US
		);

		// get the total offerwall
#		$url = $lucid_settings['lucid.host'].'Supply/v1/Surveys/AllOfferwall/'.$lucid_settings['lucid.supplier.code'];
		$url = $lucid_settings['lucid.host'].'Supply/v1/Surveys/SupplierAllocations/All/'.$lucid_settings['lucid.supplier.code'];
		$response = $http->get($url, $params);
		if ($response->code != 200) {
			$this->lecho('FAILED: Offerwall look up failed: '.$url, $log_file, $log_key);
			$this->lecho($response, $log_file, $log_key);
			$this->lecho('Completed (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		$body = json_decode($response->body, true);
		$all_offerwalls = Set::extract('SupplierAllocationSurveys', json_decode($response->body, true));
		$total = count($all_offerwalls);
		$this->lecho('Starting to process '.$total.' projects', $log_file, $log_key);

		$i = 1;
		foreach ($all_offerwalls as $offerwall_survey) {
			$this->out($i.'/'.$total);
			$i++;
			if (!in_array($offerwall_survey['CountryLanguageID'], $desired_countries)) {
				continue;
			}

			$http = new HttpSocket(array(
				'timeout' => 240,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$params = array('key' => $lucid_settings['lucid.api.key']);
			$url = $lucid_settings['lucid.host'].'Supply/v1/Surveys/SupplierAllocations/BySurveyNumber/'.$offerwall_survey['SurveyNumber'].'/'.$lucid_settings['lucid.supplier.code'];
			$response = $http->get($url, $params);
			$body = json_decode($response->body, true);
			if (!isset($body['SupplierAllocationSurvey']['OfferwallAllocations'][0]['OfferwallCompletes'])) {
				continue;
			}
			$complete = Set::extract('/SupplierAllocationSurvey/OfferwallAllocations/0/OfferwallCompletes', $body);
			$complete = (int) $complete[0];

			$date = date('Y-m-d');

			$project_complete_history = $this->ProjectCompleteHistory->find('first', array(
				'conditions' => array(
					'ProjectCompleteHistory.partner' => 'fulcrum',
					'ProjectCompleteHistory.partner_project_id' => $offerwall_survey['SurveyNumber'],
					'ProjectCompleteHistory.date' => $date
				)
			));

			// we are updating an existing value
			if ($project_complete_history) {
				$max_completes = max($project_complete_history['ProjectCompleteHistory']['max_completes'], $complete);
				$min_completes = min($project_complete_history['ProjectCompleteHistory']['min_completes'], $complete);

				$this->ProjectCompleteHistory->create();
				$this->ProjectCompleteHistory->save(array('ProjectCompleteHistory' => array(
					'id' => $project_complete_history['ProjectCompleteHistory']['id'],
					'max_completes' => $max_completes,
					'min_completes' => $min_completes
				)), true, array('max_completes', 'min_completes'));
				$this->out('Updated '.$offerwall_survey['SurveyNumber'].' to '.$max_completes.'/'.$min_completes);

			}
			else {
				// for the first one of today, set the min/max for yesterday just once
				$yesterday = date('Y-m-d', strtotime('yesterday'));

				$yesterday_project_complete_history = $this->ProjectCompleteHistory->find('first', array(
					'conditions' => array(
						'ProjectCompleteHistory.partner' => 'fulcrum',
						'ProjectCompleteHistory.partner_project_id' => $offerwall_survey['SurveyNumber'],
						'ProjectCompleteHistory.date' => $yesterday
					)
				));
				if ($yesterday_project_complete_history) {
					$max_completes = max($yesterday_project_complete_history['ProjectCompleteHistory']['max_completes'], $complete);
					$min_completes = min($yesterday_project_complete_history['ProjectCompleteHistory']['min_completes'], $complete);

					$this->ProjectCompleteHistory->create();
					$this->ProjectCompleteHistory->save(array('ProjectCompleteHistory' => array(
						'id' => $yesterday_project_complete_history['ProjectCompleteHistory']['id'],
						'max_completes' => $max_completes,
						'min_completes' => $min_completes
					)), true, array('max_completes', 'min_completes'));
					$this->out('Finalized '.$offerwall_survey['SurveyNumber'].' to '.$max_completes.'/'.$min_completes);
				}

				$project = $this->Project->find('first', array(
					'fields' => array('Project.id'),
					'conditions' => array(
						'Project.mask' => $offerwall_survey['SurveyNumber'],
						'Project.group_id' => $group['Group']['id']
					),
					'recursive' => -1
				));

				$this->ProjectCompleteHistory->create();
				$this->ProjectCompleteHistory->save(array('ProjectCompleteHistory' => array(
					'partner' => 'fulcrum',
					'project_id' => $project ? $project['Project']['id']: null,
					'partner_project_id' => $offerwall_survey['SurveyNumber'],
					'max_completes' => $complete,
					'min_completes' => $complete,
					'date' => $date
				)));
				$this->out('Created '.$offerwall_survey['SurveyNumber'].' with '.$complete);
			}
		}
	}

	public function qqq() {
		
		// for testing locally; use the mock data; will generate range of user ids 1 - 2000
		if (defined('IS_DEV_INSTANCE') && IS_DEV_INSTANCE === true) {
			App::import('Lib', 'MockQueryEngine');
		}
		
		$lucid_settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array(
					'lucid.maintenance',
					'slack.alerts.webhook'
				),
				'Setting.deleted' => false
			)
		));

		if (count($lucid_settings) < 2) {
			$this->out('ERROR: Missing settings');
			return false;
		}

		if ($lucid_settings['lucid.maintenance'] == 'true') {
			$this->out('ERROR: Lucid is in maintenance mode');
			return false;
		}
		
		ini_set('memory_limit', '2048M');
		$log_file = 'lucid.qe2';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting qe2 qualificaitons', $log_file, $log_key);

		if (!isset($this->params['lucid_project_id'])) {
			$this->lecho('FAILED: You need to define the lucid_project_id', $log_file, $log_key);
			$this->lecho('Completed qualifications (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			// this method logs the errors already
			$this->lecho('Completed qualifications (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}

		// be sure we retrieve the correct fed_survey; an active one!
		$fed_survey = $this->FedSurvey->find('first', array(
			'conditions' => array(
				'FedSurvey.fed_survey_id' => $this->params['lucid_project_id'],
				'FedSurvey.survey_id >' => '0'
			),
			'order' => 'FedSurvey.id DESC'
		));
		$this->Project->bindModel(array(
			'hasOne' => array(
				'FedSurvey' => array(
					'className' => 'FedSurvey',
					'foreignKey' => 'survey_id'
				)
			)
		));

		if ($fed_survey) {
			$project = $this->Project->find('first', array(
				'conditions' => array(
					'Project.id' => $fed_survey['FedSurvey']['survey_id']
				)
			));
		}

		if (!$fed_survey || !$project || empty($project['Project']['country'])) {
			$this->lecho('FAILED: Could not locate the MV project that is associated with #F'.$this->params['lucid_project_id'], $log_file, $log_key);
			$this->lecho('Completed qualifications (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		
		// load data off of the lucid api
		$lucid_project = $this->getLucidProject(array('SupplierAllocationSurvey', 'SurveyQualification', 'SurveyQuotas'));
		$total_qualifications_list = $this->Lucid->qualifications($lucid_project);
		if (empty($total_qualifications_list)) {
			
			// mark the project as public if no quals provided
			if (!$project['Project']['public']) {
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'public' => true,
				)), true, array('public'));
				
				$this->lecho('#F'.$this->params['lucid_project_id']. ' marked as public, because of no qualifications', $log_file, $log_key);
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'project.public',
					'description' => 'public is set to true, because of no qualifications',
				)));
			}
			
			$this->lecho('Could not find any qualifications for #F'.$this->params['lucid_project_id'], $log_file, $log_key);
			$this->lecho('Completed qualifications (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'project.qualifications.missing',
				'description' => 'You are not really operating on a qualification there.',
			)));
			return false;
		}
		// if quals exist and the project is marked as public, unmark it
		elseif ($project['Project']['public']) {
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'public' => false,
			)), true, array('public'));
			$this->lecho('#F'.$this->params['lucid_project_id']. ' qualifications found, and the project is no more public.', $log_file, $log_key);
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'project.public',
				'description' => 'public set to false, because qualifications are added.',
			)));
		}

		// determine: are we running this again and do we need to do something?
		// TODO: backwards compatible this; gotta switch to use $total_qualifications_list
		$qualification_hash = md5(serialize($lucid_project['SurveyQualification']));
		$qualification_hash_option = $this->ProjectOption->find('first', array(
			'fields' => array('ProjectOption.id', 'ProjectOption.name', 'ProjectOption.value'),
			'conditions' => array(
				'ProjectOption.project_id' => $project['Project']['id'],
				'ProjectOption.name' => 'lucid.qe2.qualification',
			)
		));

		if (empty($this->params['force']) && $qualification_hash_option && $qualification_hash_option['ProjectOption']['value'] == $qualification_hash) {
			
			// update the qualification quotas
			if (isset($lucid_project['SurveyQuotas']) && !empty($lucid_project['SurveyQuotas'])) {
				foreach ($lucid_project['SurveyQuotas'] as $survey_quota) {
					$this->Qualification->bindModel(array('hasOne' => array('QualificationStatistic')));
					$qualification = $this->Qualification->find('first', array(
						'conditions' => array(
							'Qualification.project_id' => $project['Project']['id'],
							'Qualification.partner_qualification_id' => $survey_quota['SurveyQuotaID'],
							'Qualification.deleted is null'
						)
					));
					if ($qualification) {
						$original_qualification_data = array(
							'id' => $qualification['Qualification']['id'],
							'cpi' => $qualification['Qualification']['cpi'],
							'award' => $qualification['Qualification']['award'],
							'quota' => $qualification['Qualification']['quota']
						);
						$qualification_data = array(
							'id' => $qualification['Qualification']['id'],
							'cpi' => $survey_quota['QuotaCPI'],
							'award' => $this->convert_cpi_to_award($survey_quota['QuotaCPI']),
							'quota' => $survey_quota['NumberOfRespondents'] + $qualification['QualificationStatistic']['completes']
						);
						$diff = array_diff($qualification_data, $original_qualification_data);
						if (count($diff) > 0) {
							$this->Qualification->create();
							$this->Qualification->save(array('Qualification' => $qualification_data), true, array('quota', 'cpi', 'award'));
							$log = '';
							foreach ($diff as $key => $val) {
								$log .= $key . ' was updated from "' . $original_qualification_data[$key] . '" to "' . $val . '", ';
							}
							
							$log = substr($log, 0, -2);
							$this->ProjectLog->create();
							$this->ProjectLog->save(array('ProjectLog' => array(
								'project_id' => $project['Project']['id'],
								'type' => 'qualification.updated',
								'description' => 'Qualification #' . $qualification['Qualification']['id'] . ' updated: ' . $log,
							)));
						}
					}
				}
			}

			$this->lecho('[Skipped] QE2 Qualifications did not change for #F' . $this->params['lucid_project_id'], $log_file, $log_key);
			return false;
		}

		if (!$qualification_hash_option) {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'project_id' => $project['Project']['id'],
				'name' => 'lucid.qe2.qualification',
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
			'partner' => 'lucid',
			'qualifications' => array(
				'country' => array(!empty($project['Project']['country']) ? $project['Project']['country']: 'US')
			)
		);
		if (!empty($total_qualifications_list)) {
			foreach ($total_qualifications_list as $question_id => $precodes) {
				
				// fix the zipcode with trailing comma
				if ($question_id == 45) {
					foreach ($precodes as $key => $precode) {
						if (strpos($precode, ',') !== false) {
							$precodes[$key] = rtrim($precode, ',');
						}
					}
				}
				
				$query_body['qualifications'][$question_id] = $precodes;
			}
		}
		
		asort($query_body['qualifications']);
		$query_json = $raw_query_json = json_encode($query_body);
		$query_json = QueryEngine::qe2_modify_query($query_json);
		CakeLog::write($log_file, $raw_query_json);
		CakeLog::write($log_file, $query_json);
		$query_hash = md5($query_json);

		$this->Qualification->bindModel(array('hasOne' => array('QualificationStatistic')));
		$total_qualification = $this->Qualification->find('first', array(
			'conditions' => array(
				'Qualification.project_id' => $project['Project']['id'],
				'Qualification.parent_id' => null,
				'Qualification.deleted is null'
			)
		));
		$created_qualification_ids = array();
		$parent_qual_updated = false;
		
		// create parent qualification
		if (!$total_qualification) {
			$qualificationSource = $this->Qualification->getDataSource();
			$qualificationSource->begin();
			$this->Qualification->create();
			$this->Qualification->save(array('Qualification' => array(
				'project_id' => $project['Project']['id'],
				'name' => '',
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
				'description' => 'Qualification #' . $parent_qualification_id,
			)));
		}
		else {
			$parent_qualification_id = $total_qualification['Qualification']['id'];
			
			// update parent qualification if changed
			if ($total_qualification['Qualification']['query_hash'] != $query_hash) {
				$parent_qual_updated = true;
				$qualificationSource = $this->Qualification->getDataSource();
				$qualificationSource->begin();
				$this->Qualification->create();
				$this->Qualification->save(array('Qualification' => array(
					'id' => $total_qualification['Qualification']['id'],
					'query_hash' => $query_hash,
					'query_json' => $query_json,
					'raw_json' => $raw_query_json,
					'active' => false, /* disable this until this process is complete in case notification service kicks off */
				)), true, array('query_hash', 'query_json', 'raw_json'));
				$qualificationSource->commit();

				$this->lecho('Updated qualification ('.$parent_qualification_id.')', $log_file, $log_key);
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'qualification.updated',
					'description' => 'Qualification #' . $parent_qualification_id,
				)));
			}
		}

		// set mobile/tablet flags
		if (!empty($total_qualifications_list)) {
			$desktop = $mobile = $tablet = true;
			
			// Handle qualifications to transform into queries
			foreach ($total_qualifications_list as $question_id => $precodes) {
				
				// Handle mobile flag
				if ($question_id == 8214) {
					if (in_array('true', $precodes)) {
						$desktop = false;
						$tablet = false;
					}
					else {
						$mobile = false;
					}
				}

				// Handle tablets flag
				if ($question_id == 8213) {
					if (in_array('true', $precodes)) {
						
						// explicitly declare as the above mobile question may have made it false
						$tablet = true; 
						$desktop = false;
					}
					else {
						$tablet = false;
					}
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
		}

		// Create project invites
		if (!$project['Client']['do_not_autolaunch']) {
			
			// recontact projects have different behavior
			if ($lucid_project['SupplierAllocationSurvey']['StudyTypeID'] == 22) {
				$survey_respondents = $this->getLucidProject(array('SurveyQualifiedRespondents'));
				$panelist_ids = Hash::extract($survey_respondents, 'SurveyQualifiedRespondents.{n}.PID');
			}
			else {
				if (defined('IS_DEV_INSTANCE') && IS_DEV_INSTANCE === true) {
					$panelist_ids = MockQueryEngine::parent_panelists(1);
				}
				else {
					$panelist_ids = QueryEngine::qe2($this->settings, $query_json);
				}
			}
			
			$qe2_count = count($panelist_ids);
			$this->lecho('Panelists returned by QE2: '.$qe2_count.' panelists', $log_file, $log_key);
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'qualification.count',
				'description' => 'Panelists returned by QE2: '.$qe2_count.' panelists',
			)));
			
			// filter these panelist IDs out here by active in last three weeks
			// also limit them to 10,000 to avoide memory_limit issues.
			if (!empty($panelist_ids)) {
				if ($lucid_project['SupplierAllocationSurvey']['StudyTypeID'] == 22) {
					$panelist_ids = $this->User->find('list', array(
						'fields' => array('User.id', 'User.id'),
						'conditions' => array(
							'User.deleted_on' => null,
							'User.id' => $panelist_ids,
							'User.hellbanned' => false
						),
						'limit' => 10000,
						'recursive' => -1
					));
				}
				else {
					$panelist_ids = $this->User->find('list', array(
						'fields' => array('User.id', 'User.id'),
						'conditions' => array(
							'User.deleted_on' => null,
							'User.id' => $panelist_ids,
							'User.hellbanned' => false,
							'User.last_touched >=' => date(DB_DATETIME, strtotime('-21 days'))
						),
						'limit' => 10000,
						'order' => array(
							'User.last_touched' => 'desc'
						),
						'recursive' => -1
					));
				}
			}
			
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
					'QualificationUser.qualification_id' => $parent_qualification_id,
					'QualificationUser.deleted' => false
				)
			));
			
			$logs = array(
				'Matched from QE2 (after filter): '.$qe2_count,
				'Existing panelists in project: '.count($survey_users).'/'.count($qualification_users),
			);

			// if parent qual updated, we need to rescind invitations
			if ($parent_qual_updated) {
				
				// these panelists no longer are in the query
				$missing_diff = array_diff($survey_users, $panelist_ids);
				if (!empty($missing_diff)) {
					
					// determine which users already accessed this project, then rescind invitations for all other panelists
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
						$logs[] = 'Removed '.count($unclicked_diff). ' old invites.';
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

			$diff = array_diff($panelist_ids, $survey_users);
			if (!empty($diff)) {
				$logs[] = 'Added '.count($diff). ' New invites';
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
				
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'qualification.invited',
					'description' => 'Added survey invitations for '.count($diff).' panelists',
					'internal_description' => count($diff)
				)));

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
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'qualification.stats',
				'description' => implode(', ', $logs),
			)));
			$this->lecho(implode(', ', $logs), $log_file, $log_key);

			// update the total count on this qualification
			$this->set_qualification_invite_count($parent_qualification_id, $qe2_count, $invite_count);
		}

		// Operate on SurveyQuotas
		if (isset($lucid_project['SurveyQuotas']) && !empty($lucid_project['SurveyQuotas'])) {
			$all_quota_ids = array();
			foreach ($lucid_project['SurveyQuotas'] as $survey_quota) {
				$all_quota_ids[] = $survey_quota['SurveyQuotaID'];
				
				// Update Parent qualification here
				if ($survey_quota['SurveyQuotaType'] == 'Total') {

					// because quotas are constantly updated; we need to add our completes to it so we don't exclude ourselves
					$existing_completes = 0;
					if ($total_qualification) {
						$existing_completes = $total_qualification['QualificationStatistic']['completes'];
					}
					
					// write the quota
					$parent_qualification = $this->Qualification->findById($parent_qualification_id);
					$original_qualification_data = array(
						'id' => $parent_qualification_id,
						'name' => $parent_qualification['Qualification']['name'],
						'quota' => $parent_qualification['Qualification']['quota'],
						'cpi' => $parent_qualification['Qualification']['cpi'],
						'award' => $parent_qualification['Qualification']['award'],
						'partner_qualification_id' => $parent_qualification['Qualification']['partner_qualification_id']
					);
					$qualification_data = array(
						'id' => $parent_qualification_id,
						'name' => '#F'.$project['Project']['mask'].' - '.$survey_quota['SurveyQuotaID'],
						'quota' => $survey_quota['NumberOfRespondents'] + $existing_completes,
						'cpi' => $survey_quota['QuotaCPI'],
						'award' => $this->convert_cpi_to_award($survey_quota['QuotaCPI']),
						'partner_qualification_id' => $survey_quota['SurveyQuotaID']
					);
					$diff = array_diff($qualification_data, $original_qualification_data);
					if (count($diff) > 0) {
						$this->Qualification->create();
						$this->Qualification->save(array('Qualification' => $qualification_data), true, array('name', 'award', 'cpi', 'partner_qualification_id', 'quota'));
						$this->lecho('Updated qualification '.$parent_qualification_id.' with new data', $log_file, $log_key);
						
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
				}
				
				if ($survey_quota['SurveyQuotaType'] == 'Client') {
					$query_body_for_quota = $query_body;
					foreach ($survey_quota['Questions'] as $question) {
						
						// overwrite the old values...
						if (isset($query_body['qualifications'][$question['QuestionID']])) {
							$query_body_for_quota['qualifications'][$question['QuestionID']] = $question['PreCodes'];
						}
					}
					
					$query_json = json_encode($query_body_for_quota);
					$query_hash = md5($query_json);

					$this->Qualification->bindModel(array('hasOne' => array('QualificationStatistic')));
					$child_qualification = $this->Qualification->find('first', array(
						'conditions' => array(
							'Qualification.parent_id' => $parent_qualification_id,
							'Qualification.partner_qualification_id' =>  $survey_quota['SurveyQuotaID'],
							'Qualification.deleted is null'
						)
					));

					// two cases where we create a qualification: new one is different, or doesn't exist
					if (!$child_qualification || $query_hash != $child_qualification['Qualification']['query_hash']) {
						
						// if a child qualification changes we delete it, and create a new one
						$existing_completes = 0;
						if ($child_qualification) {
							$existing_completes = $child_qualification['Qualification']['completes'];
							$this->Qualification->delete($child_qualification['Qualification']['id']);
						}
						
						$qualificationSource = $this->Qualification->getDataSource();
						$qualificationSource->begin();
						$this->Qualification->create();
						$this->Qualification->save(array('Qualification' => array(
							'project_id' => $project['Project']['id'],
							'parent_id' => $parent_qualification_id,
							'partner_qualification_id' => $survey_quota['SurveyQuotaID'],
							'name' => '#F'.$project['Project']['mask'].' - '.$survey_quota['SurveyQuotaID'],
							'query_hash' => $query_hash,
							'query_json' => $query_json,
							'quota' => $survey_quota['NumberOfRespondents'] + $existing_completes,
							'cpi' => $survey_quota['QuotaCPI'],
							'award' => $this->convert_cpi_to_award($survey_quota['QuotaCPI'])
						)));
						$child_qualification_id = $this->Qualification->getInsertId();
						$qualificationSource->commit();
						$this->ProjectLog->create();
						$this->ProjectLog->save(array('ProjectLog' => array(
							'project_id' => $project['Project']['id'],
							'type' => 'qualification.created',
							'description' => 'Qualification #' . $child_qualification_id . ' created.',
						)));
						$created_qualification_ids[] = $child_qualification_id;
						$this->lecho('Created child qualification '.$child_qualification_id, $log_file, $log_key);
					} // update CPI if it changes for existing quotas
					elseif ($child_qualification && $child_qualification['Qualification']['cpi'] != $survey_quota['QuotaCPI']) {
						$original_qualification_data = array(
							'id' => $child_qualification['Qualification']['id'],
							'cpi' => $child_qualification['Qualification']['cpi'],
							'award' => $child_qualification['Qualification']['award']
						);
						$award = $this->convert_cpi_to_award($survey_quota['QuotaCPI']);
						$qualification_data = array(
							'id' => $child_qualification['Qualification']['id'],
							'cpi' => $survey_quota['QuotaCPI'],
							'award' => $award
						);
						$diff = array_diff($qualification_data, $original_qualification_data);
						if (count($diff) > 0) {
							$this->Qualification->create();
							$this->Qualification->save(array('Qualification' => $qualification_data), true, array('cpi', 'award'));
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
					if (isset($child_qualifications[$missing_qualification_id])) {
						$this->Qualification->delete($child_qualifications[$missing_qualification_id]);
					}
				}
			}
		}

		// activate all the created qualifications
		if (!empty($created_qualification_ids)) {
			foreach ($created_qualification_ids as $created_qualification_id) {
				$this->Qualification->create();
				$this->Qualification->save(array('Qualification' => array(
					'id' => $created_qualification_id,
					'active' => true
				)), true, array('active'));
			}
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'qualification.activated',
				'description' => 'All created qualificaitons activated.',
			)));
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
		if ($project['Client']['do_not_autolaunch'] && ($project['Project']['status'] != PROJECT_STATUS_STAGING || $project['Project']['active'])) {
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
					'ProjectOption.name' => 'lucid.nolaunch.reason'
				)
			));
			if (!$project_option) {
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'name' => 'lucid.nolaunch.reason',
					'value' => 'client.skipped',
					'project_id' => $project['Project']['id']
				)));
			}
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'qualification.skipped',
				'description' => 'Active set to false by client.',
			)));
		}
		
		$this->lecho('Completed qe2 qualifications (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
		return true;
	}

	public function import_zipcodes() {
		ini_set('memory_limit', '2048M');
		App::import('Model', 'LucidZip');
		$this->LucidZip = new LucidZip;

		if (!isset($this->args[0]) || !is_file($this->args[0])) {
			$file = WWW_ROOT.'files/lucid_zip_table.csv';
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
				$i++;
				/*
     		 	    [ZipCode] => 00605
	    			[Population] => 0
		            [Latitude] => 18.4289
		            [Longitude] => -67.1538
		            [State] => PR
		            [StateFullName] => Puerto Rico
		            [AreaCode] => 787
		            [City] => AGUADILLA
		            [County] => AGUADILLA
		            [CountyFIPS] => 5
		            [StateFIPS] => 72
		            [TimeZone] => 4
		            [DayLightSaving] => N
		            [MSA] => 60
		            [PMSA] =>
		            [CSA] =>
		            [DMA] =>
		            [DMA_Name] =>
		            [DMA_Rank] =>
		            [CBSA] => 10380
		            [CBSAType] => Metro
		            [CBSAName] => Aguadilla-Isabela-San Sebastian, PR
		            [MSAName] => Aguadilla, PR MSA
		            [PMSA_Name] =>
		            [Region] =>
		            [Division] =>
		            [CSAName] =>
		            [CBSA_Div_Name] =>
		            [] =>
				*/
				$lucid_zip = $this->LucidZip->find('first', array(
					'fields' => array('LucidZip.id'),
					'conditions' => array(
						'LucidZip.zipcode' => $row['ZipCode']
					)
				));
				$save_data = array('LucidZip' => array(
					'zipcode' => $row['ZipCode'],
					'population' => $row['Population'],
					'state_abbr' => $row['State'],
					'state_full' => $row['StateFullName'],
					'area_code' => $row['AreaCode'],
					'city' => $row['City'],
					'county' => $row['County'],
					'county_fips' => $row['CountyFIPS'],
					'state_fips' => $row['StateFIPS'],
					'timezone' => $row['TimeZone'],
					'daylight' => $row['DayLightSaving'],
					'msa' => $row['MSA'],
					'pmsa' => $row['PMSA'],
					'csa' => $row['CSA'],
					'dma' => $row['DMA'],
					'dma_name' => $row['DMA_Name'],
					'dma_rank' => $row['DMA_Rank'],
					'cbsa' => $row['CBSA'],
					'cbsa_type' => $row['CBSAType'],
					'cbsa_name' => $row['CBSAName'],
					'msa_name' => $row['MSAName'],
					'pmsa_name' => $row['PMSA_Name'],
					'region' => $row['Region'],
					'division' => $row['Division'],
					'csa_name' => $row['CSAName'],
					'csa_div_name' => $row['CBSA_Div_Name']
				));

				if ($lucid_zip) {
					$save_data['LucidZip']['id'] = $lucid_zip['LucidZip']['id'];
				}
				$this->LucidZip->create();
				$this->LucidZip->save($save_data);
			}
			$this->out('Import complete: '.$i.' records created/updated');
		}
	}

	/* lucid has a 40/60 payout rate */
	private function convert_cpi_to_award($value) {
		$award = $value * 100; /* convert from dollars to points */
		$award = round($award * 4 / 10);
		return $award;
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

	public function track_epc_statistics() {
		$lucid_settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array(
					'lucid.active',
					'lucid.maintenance'
				),
				'Setting.deleted' => false
			),
			'fields' => array(
				'Setting.name',
				'Setting.value'
			)
		));

		if (count($lucid_settings) < 2) {
			$this->out('Missing required settings');
			return;
		}

		if ($lucid_settings['lucid.active'] == 'false' || $lucid_settings['lucid.maintenance'] == 'true') {
			$this->out('Lucid integration disabled');
			return;
		}
		ini_set('memory_limit', '1024M');
		$log_file = 'lucid.epc.statistics';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting process', $log_file, $log_key);

		// load settings, client, group, etc.
		if (!$this->loadSettings($log_file, $log_key, $time_start)) {
			return false;
		}
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$params = array('key' => $this->settings['lucid.api.key']);

		$desired_countries = array(
			6, // Canada
			8, // UK
			9 // US
		);

		// get our total allocation across the board
		$url = $this->settings['lucid.host'].'Supply/v1/Surveys/SupplierAllocations/All/'.$this->settings['lucid.supplier.code'];
		$response = $http->get($url, $params);

		if ($response->code != 200) {
			$this->lecho('FAILED: Allocation look up failed: '.$url, $log_file, $log_key);
			$this->lecho($response, $log_file, $log_key);
			$this->lecho('Completed process (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		$allocated_surveys = Set::extract('SupplierAllocationSurveys', json_decode($response['body'], true));

		// this script takes more than 3 minutes to run; shuffling this will prevent the eventual convergence
		shuffle($allocated_surveys);

		$total = count($allocated_surveys);
		$i = 0;
		$this->lecho('Processing '.$total.' projects', $log_file, $log_key);
		$this->Project->getDatasource()->reconnect(); // reconnect after API call
		foreach ($allocated_surveys as $allocated_survey) {
			$i++;
			// another sanity check we don't import study type 23 or countries
			if (in_array($allocated_survey['StudyTypeID'], array('11', '23'))) {
				continue;
			}

			if (!in_array($allocated_survey['CountryLanguageID'], $desired_countries)) { // only US, UK and CA surveys
				continue;
			}

			$this->Project->bindModel(array(
				'hasOne' => array(
					'FedSurvey' => array(
						'className' => 'FedSurvey',
						'foreignKey' => 'survey_id'
					)
				),
			));
			$project = $this->Project->find('first', array(
				'fields' => array('Project.id', 'Project.client_rate', 'FedSurvey.fed_survey_id', 'SurveyVisitCache.click', 'SurveyVisitCache.complete', 'Project.date_created'),
				'contain' => array('FedSurvey', 'SurveyVisitCache'),
				'conditions' => array(
					'FedSurvey.fed_survey_id' => $allocated_survey['SurveyNumber']
				)
			));
			// tbd: do we store stats on projects we aren't participating in?
			if (!$project) {
				continue;
			}

			// only operate on projects created in the last week
			if (strtotime('-7 days') > strtotime($project['Project']['date_created'])) {
				continue;
			}
			$this->params['lucid_project_id'] = $project['FedSurvey']['fed_survey_id'];
			$lucid_project = $this->getLucidProject(array('SurveyStatistics'));

			$lucid_epc_statistic = $this->LucidEpcStatistic->find('first', array(
				'fields' => array(
					'LucidEpcStatistic.lifetime_epc_cents',
					'LucidEpcStatistic.trailing_epc_cents',
				),
				'conditions' => array(
					'LucidEpcStatistic.project_id' => $project['Project']['id'],
					'LucidEpcStatistic.lucid_project_id' => (string) $project['FedSurvey']['fed_survey_id']
				),
				'order' => 'LucidEpcStatistic.id DESC'
			));
			$trailing_epc_cents = 100 * $lucid_project['SurveyStatistics']['EffectiveEPC'];
			$lifetime_epc_cents = 100 * $lucid_project['SurveyStatistics']['SupplierLifetimeEPC'];

			// write new record if we don't have one; otherwise assume it's the same
			if (!$lucid_epc_statistic || ($lucid_epc_statistic['LucidEpcStatistic']['lifetime_epc_cents'] != $lifetime_epc_cents || $lucid_epc_statistic['LucidEpcStatistic']['trailing_epc_cents'] != $trailing_epc_cents)) {
				$this->out($i.'/'.$total.' #F'.$project['FedSurvey']['fed_survey_id'].' Writing new values: '.$trailing_epc_cents.' and '.$lifetime_epc_cents);

				if ($project['SurveyVisitCache']['click'] > 0) {
					$our_epc_cents = $project['Project']['client_rate'] * 100 * ($project['SurveyVisitCache']['complete'] / $project['SurveyVisitCache']['click']);
				}
				else {
					$our_epc_cents = null;
				}

				$this->LucidEpcStatistic->create();
				$this->LucidEpcStatistic->save(array('LucidEpcStatistic' => array(
					'lifetime_epc_cents' => $lifetime_epc_cents,
					'trailing_epc_cents' => $trailing_epc_cents,
					'project_id' => $project['Project']['id'],
					'lucid_project_id' => (string) $project['FedSurvey']['fed_survey_id'],
					'our_epc_cents' => $our_epc_cents,
					'clicks' => $project['SurveyVisitCache']['click'],
					'completes' => $project['SurveyVisitCache']['complete']
				)));
			}
		}
	}

	// deal with resetting qualification data
	public function delete_qualifications_data() {
		$project_ids = $this->Qualification->find('list', array(
			'fields' => array('Qualification.project_id'),
			'conditions' => array(
				'Qualification.created >=' => '2016-11-04 03:20:00',
				'Qualification.created <=' => '2016-11-04 05:30:00',
			)
		));
		$project_ids = array_unique($project_ids);
		foreach ($project_ids as $project_id) {
			$project = $this->Project->find('first', array(
				'conditions' => array(
					'Project.id' => $project_id
				)
			));

			$count = $this->SurveyUser->find('count', array(
				'conditions' => array(
					'SurveyUser.survey_id' => $project_id
				)
			));
			if ($count > 0) {
				continue;
			}
			$this->out($project_id);

			$this->params['lucid_project_id'] = $project['Project']['mask'];
			$this->qualifications();
		}
	}

	public function check_invite_counts() {
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'fulcrum'
			)
		));
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'Project.status' => PROJECT_STATUS_OPEN
			)
		));
		$invites = array();
		$this->out('Found '.count($projects).' open Lucid projects');
		foreach ($projects as $project) {
			$count = $this->SurveyUser->find('count', array(
				'conditions' => array(
					'SurveyUser.survey_id' => $project['Project']['id']
				)
			));
			$invites[$project['Project']['id']] = $count;
		}
		arsort($invites);
		print_r($invites);
	}

	public function save_study_types() {
		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => array(
					'lucid.api.key',
				),
				'Setting.deleted' => false
			)
		));

		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));

		$url = 'https://api.samplicio.us/Lookup/v1/BasicLookups/BundledLookups/StudyTypes,SupplierLinkTypes?key=' . $settings['lucid.api.key'];
		$response = $http->get($url);
		try {
			$response = json_decode($response->body, true);
		}
		catch (Exception $ex) {
			$this->out('Api error, try again!');
			return;
		}

		foreach ($response['AllStudyTypes'] as $study_type) {
			$lucid_study_type = $this->LucidStudyType->find('first', array(
				'fields' => array('LucidStudyType.id', 'LucidStudyType.key', 'LucidStudyType.name'),
				'conditions' => array(
					'LucidStudyType.key' => $study_type['Id']
				),
			));
			if (!$lucid_study_type) {
				$this->LucidStudyType->create();
				$this->LucidStudyType->save(array('LucidStudyType' => array(
					'code' => $study_type['Code'],
					'key' => $study_type['Id'],
					'name' => $study_type['Name'],
				)));
				$this->out('Created New LucidStudyType: '. $study_type['Name']);
				continue;
			}

			// no need to update if the name is same
			if ($lucid_study_type['LucidStudyType']['name'] == $study_type['Name']) {
				continue;
			}

			$this->LucidStudyType->create();
			$this->LucidStudyType->save(array('LucidStudyType' => array(
				'id' => $lucid_study_type['LucidStudyType']['id'],
				'code' => $study_type['Code'],
				'name' => $study_type['Name']
			)), true, array('code', 'name'));
			$this->out('Updated LucidStudyType: '. $study_type['Name']);
		}

		$this->out('Completed.');
	}

	public function update_question_answer_list() {
		$country_names = array(
			6 => 'CA',
			8 => 'GB',
			9 => 'US'
		);
		$required_settings = array(
			'lucid.api.key',
			'lucid.host',
			'lucid.supplier.code',
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
			$this->out('Missing required settings');
			return;
		}

		$HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$request_data = array(
			'key' => $settings['lucid.api.key']
		);
		foreach ($country_names as $country_code => $country) {
			$this->Question->getDatasource()->reconnect();
			$response = $HttpSocket->get($settings['lucid.host'] . "/Lookup/v1/QuestionLibrary/AllQuestions/" . $country_code, $request_data);
			$body = json_decode($response['body'], true);
			foreach ($body['Questions'] as $question) {
				$this->out("Processing question " . $country . ": " . $question['QuestionID'] . " - " . $question['Name']);
				$mintvine_question = $this->Question->find('first', array(
					'fields' => array('Question.id'),
					'conditions' => array(
						'Question.partner_question_id' => $question['QuestionID'],
						'Question.partner' => 'lucid'
					),
					'recursive' => -1
				));

				// Let's first handle the case where the question does not exist
				if (!$mintvine_question) {
					$questionDataSource = $this->Question->getDataSource();
					$questionDataSource->begin();
					$this->Question->create();
					$saved = $this->Question->save(array('Question' => array(
						'partner_question_id' => $question['QuestionID'],
						'partner' => 'lucid',
						'question' => $question['Name'],
						'question_type' => $question['QuestionType'],
						'logic_group' => null,
						'order' => null,
						'skipped_answer_id' => null,
						'behavior' => null
					)));

					if ($saved) {
						$question_id = $this->Question->getInsertId();
						$mintvine_question = $this->Question->findById($question_id);
						$questionDataSource->commit();
						$msg = "Some people choose to see the ugliness in this world. The disarray. I choose to see this new Lucid question:";
						$msg .= "\nID: *" . $question['QuestionID'] . "*";
						$msg .= "\nName: *" . $question['Name'] . "*";
						Utils::slack_alert($settings['slack.questions.webhook'], $msg);
					}
					else {
						$questionDataSource->commit();
						$msg = "Failed to save following question to MintVine:";
						$msg .= "\nID: *" . $question['QuestionID'] . "*";
						$msg .= "\nName: *" . $question['Name'] . "*";
						Utils::slack_alert($settings['slack.questions.webhook'], $msg);
						
						// Proceed to next question since question text and answers depend on question
						continue;
					}
				}

				// Question texts
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
							'text' => $question['QuestionText']
						)));
						if (!$saved) {
							$msg = "Failed to save following question text to mintvine:";
							$msg .= "\nID: *" . $question['QuestionID'] . "*";
							$msg .= "\nName: *" . $question['Name'] . "*";
							$msg .= "\nCountry: *" . $country . "*";
							$msg .= "\nText: *" . $question['QuestionText'] . "*";
							Utils::slack_alert($settings['slack.questions.webhook'], $msg);
						}
					}

					// Answers
					// HACK: Answer object causes MySQL server to "go away" on staging, so force reconnect
					$this->Answer->getDatasource()->reconnect();
					$response_answers = $HttpSocket->get($settings['lucid.host'] . "/Lookup/v1/QuestionLibrary/AllQuestionOptions/".$country_code."/".$question['QuestionID'], $request_data);
					$body_answers = json_decode($response_answers['body'], true);
					foreach ($body_answers['QuestionOptions'] as $qopt) {
						$mintvine_answer = $this->Answer->find('first', array(
							'fields' => array('Answer.id'),
							'conditions' => array(
								'Answer.partner_answer_id' => $qopt['Precode'],
								'Answer.question_id' =>  $mintvine_question['Question']['id'],
							),
							'recursive' => -1
						));
						if (!$mintvine_answer) {
							$answerDataSource = $this->Answer->getDataSource();
							$answerDataSource->begin();
							$this->Answer->create();
							$saved = $this->Answer->save(array('Answer' => array(
								'answer' => $qopt['OptionText'],
								'partner_answer_id' => $qopt['Precode'],
								'question_id' => $mintvine_question['Question']['id'],
								'country' => $country,
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
								$msg = "Failed to save following answer to mintvine:";
								$msg .= "\nQuestion ID: *" . $question['QuestionID'] . "*";
								$msg .= "\nQuestion Name: *" . $question['Name'] . "*";
								$msg .= "\nPrecode: *" . $qopt['Precode'] . "*";
								$msg .= "\nCountry: *" . $country . "*";
								Utils::slack_alert($settings['slack.questions.webhook'], $msg);
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
									'text' => $qopt['OptionText']
								)));
								
								if (!$saved) {
									$msg = "Failed to save following answer text to mintvine:";
									$msg = "Failed to save following answer to mintvine:";
									$msg .= "\nQuestion ID: *" . $question['QuestionID'] . "*";
									$msg .= "\nQuestion Name: *" . $question['Name'] . "*";
									$msg .= "\nPrecode: *" . $qopt['Precode'] . "*";
									$msg .= "\nCountry: *" . $country . "*";
									$msg .= "\nAnswer Text: *" . $qopt['OptionText'];
									Utils::slack_alert($settings['slack.questions.webhook'], $msg);
								}
							}
						}
					}
				}
			}
		}
	}
	
	// given a date; return # of projects for each client
	public function analyze_clients() {
		if (!isset($this->args[0])) {
			return false; 
		}
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'fulcrum'
			),
			'recursive' => -1
		)); 

		$this->Project->unbindModel(array(
			'hasMany' => array('ProjectOption', 'SurveyPartner', 'ProjectAdmin')
		)); 
		$projects = $this->Project->find('all', array(
			'fields' => array('Client.id', 'Client.client_name'),
			'conditions' => array(
				'Project.date_created >=' => $this->args[0].' 00:00:00',
				'Project.date_created <=' =>$this->args[0].' 23:59:59',
				'Project.group_id' => $group['Group']['id']
			)
		)); 
		$clients_first_date = array();
		if ($projects) {
			foreach ($projects as $project) {
				if (!isset($clients_first_date[$project['Client']['client_name']])) {
					$clients_first_date[$project['Client']['client_name']] = 0; 
				}
				$clients_first_date[$project['Client']['client_name']]++;  
			}
		}
		
		// second date 
		$this->Project->unbindModel(array(
			'hasMany' => array('ProjectOption', 'SurveyPartner', 'ProjectAdmin')
		)); 
		$projects = $this->Project->find('all', array(
			'fields' => array('Client.id', 'Client.client_name'),
			'conditions' => array(
				'Project.date_created >=' => $this->args[1].' 00:00:00',
				'Project.date_created <=' =>$this->args[1].' 23:59:59',
				'Project.group_id' => $group['Group']['id']
			)
		)); 
		$clients_second_date = array();
		if ($projects) {
			foreach ($projects as $project) {
				if (!isset($clients_second_date[$project['Client']['client_name']])) {
					$clients_second_date[$project['Client']['client_name']] = 0; 
				}
				$clients_second_date[$project['Client']['client_name']]++;  
			}
		}
		
		$filename = WWW_ROOT . 'files/lucid_client_analysis.csv';
		$fp = fopen($filename, 'w');
		fputcsv($fp, array(
			'Client Name',
			$this->args[0],
			$this->args[1],
			'Diff',
			'%'
		));
		foreach ($clients_first_date as $client_name => $count) {
			$second_date = isset($clients_second_date[$client_name]) ? $clients_second_date[$client_name]: 0;
			$diff = $count - $second_date;
			fputcsv($fp, array(
				$client_name,
				$count,
				$second_date,
				$diff,
				round($diff / $count * 100)				
			));
		}
		
		fclose($fp);
		$this->out('Wrote '.$filename);
	}
	
	// find projects we had non-performance on, then re-open them
	public function reopen_performing_projects() {
		if (!isset($this->args[0])) {
			$datetime = date(DB_DATETIME, strtotime('-24 hours')); 
		}
		else {
			$datetime = date(DB_DATE, $this->args[0]).' 00:00:00';
		}
		
		$keys = array(
			// Lucid API credentials
			'lucid.host',
			'lucid.api.key',
			'lucid.supplier.code',

			// various settings
			'fulcrum.floor.loi.ratio',
			'fulcrum.ir_cutoff',
			'fulcrum.floor.award',
			'fulcrum.autolaunch',
			'fulcrum.sample_threshold',
			'lucid.followup.ceiling',
			'lucid.filter.account_name',
			'lucid.queryengine',

			// QE2 API
			'qe.mintvine.username',
			'qe.mintvine.password',
			'hostname.qe',

			// mv API
			'api.mintvine.username',
			'api.mintvine.password',
			'hostname.api',

			// AWS S3
			's3.access',
			's3.secret',
			's3.host',
			's3.bucket',

			// slack channels for alerts
			'slack.qe2.webhook',
			'slack.alerts.webhook',

			'qqq.active'
		);
		$this->settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => $keys,
				'Setting.deleted' => false
			)
		));
		
		$this->out('Starting analysis of '.$datetime);
		$group = $this->lucid_group = $this->Group->find('first', array(
			'fields' => array('Group.id', 'Group.epc_floor_cents', 'Group.max_loi_minutes'),
			'conditions' => array(
				'Group.key' => 'fulcrum'
			),
			'recursive' => -1
		)); 
		
		// 125% of floor is considered good
		$epc_threshold = floor($group['Group']['epc_floor_cents'] * 1.25); 
		
		$this->Project->unbindModel(array('hasMany' => array(
			'SurveyPartner',
			'ProjectOption',
			'ProjectAdmin',
		)));
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Project.mask', 'Project.started', 'SurveyVisitCache.click', 'SurveyVisitCache.complete'),
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'Project.ended >=' => $datetime,
				'Project.active' => false
			)
		));

		// only look at performance in last 2 hours
		$epc_datetime = date(DB_DATETIME, strtotime('-2 hours')); 
		
		$this->out('Found '.count($projects).' total closed projects since '.$datetime); 
		foreach ($projects as $key => $project) {
			if ($project['SurveyVisitCache']['complete'] > 0) {
				unset($projects[$key]);
				continue;
			}
			$count = $this->SurveyUser->find('count', array(
				'conditions' => array(
					'SurveyUser.survey_id' => $project['Project']['id']
				),
				'recursive' => -1
			));
			if ($count == 0) {
				unset($projects[$key]); 
				continue;
			}

			// get the highest value
			$lucid_epc_statistics = $this->LucidEpcStatistic->find('count', array(
				'fields' => array('LucidEpcStatistic.trailing_epc_cents'),
				'conditions' => array(
					'LucidEpcStatistic.project_id' => $project['Project']['id'],
					'LucidEpcStatistic.trailing_epc_cents >=' => $epc_threshold,
					'LucidEpcStatistic.created >=' => $epc_datetime
				),
				'recursive' => -1
			)); 
			if (!$lucid_epc_statistics) {
				unset($projects[$key]); 
				continue;
			}
			
			$this->params['lucid_project_id'] = $project['Project']['mask']; 
			$lucid_project = $this->getLucidProject(array('SupplierAllocationSurvey', 'SurveyQuotas', 'SurveyQualification', 'SurveyStatistics', 'SurveyGroups'));
			
			if (!$this->checkInternalLucidRules($lucid_project, null, null)) {
				unset($projects[$key]); 
				continue;
			}
		}
		$this->out('Found '.count($projects).' incompleted projects since '.$datetime); 
		
		// filter out on EPC stats
		foreach ($projects as $key => $project) {
			// open this project again (write in projectlogs)
			
			$project_option = $this->ProjectOption->find('first', array(
				'fields' => array('ProjectOption.value'),
				'conditions' => array(
					'ProjectOption.name' => 'fulcrum.reset.date',
					'ProjectOption.project_id' => $project['Project']['id']
				)
			));
			
			// already been re-opened once; don't try it again
			if ($project_option) {
				unset($projects[$key]); 
				continue;
			}
			
			// write the project option record
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'project_id' => $project['Project']['id'],
				'name' => 'fulcrum.reset.date',
				'value' => date(DB_DATETIME)
			)));
			
			// re-open it
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
				'type' => 'status.opened.epc',
				'description' => 'Reopening as Fulcrum is showing EPC activity w/ other partners'
			)));
			$this->out('Reopening '.$project['Project']['id']); 
			
			$setting = $this->Setting->find('first', array(
				'fields' => array('Setting.value'),
				'conditions' => array(
					'Setting.name' => 'slack.reopen.webhook',
					'Setting.deleted' => false
				)
			));
			if (!empty($setting['Setting']['value'])) {
				$http = new HttpSocket(array(
					'timeout' => '2',
					'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
				));
				$http->post($setting['Setting']['value'], json_encode(array(
					'text' => 'Reopening '.$project['Project']['id'].' <https://cp.mintvine.com/surveys/dashboard/'.$project['Project']['id'].'>',
					'link_names' => 1,
					'username' => 'bernard'
				)));
			}
		}
		$this->out('Found '.count($projects).' that pass EPC filter since '.$datetime);	
	}
	
	// to be used to make sure lucid completes are flowing correctly
	public function find_recent_supply_completes() {
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'fulcrum'
			),
			'recursive' => -1
		)); 
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id'),
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'Project.active' => true
			),
			'recursive' => -1
		)); 
		$survey_visits = $this->SurveyVisit->find('all', array(
			'fields' => array('SurveyVisit.hash', 'SurveyVisit.created'),
			'conditions' => array(
				'SurveyVisit.survey_id' => Hash::extract($projects, '{n}.Project.id'),
				'SurveyVisit.type' => SURVEY_COMPLETED
			),
			'order' => 'SurveyVisit.id DESC',
			'limit' => '10'
		));
		$this->out('Current datetime: '.date(DB_DATETIME));
		print_r($survey_visits);
	}
	
	// to be used to make sure lucid buy completes are flowing correctly
	public function find_recent_buy_completes() {
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'mintvine'
			),
			'recursive' => -1
		)); 
		$partner = $this->Partner->find('first', array(
			'fields' => array('Partner.id'),
			'conditions' => array(
				'Partner.key' => 'fulcrum'
			),
			'recursive' => -1
		)); 
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id'),
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'Project.active' => true
			),
			'recursive' => -1
		)); 
		$survey_visits = $this->SurveyVisit->find('all', array(
			'fields' => array('SurveyVisit.hash', 'SurveyVisit.created', 'SurveyVisit.referrer'),
			'conditions' => array(
				'SurveyVisit.survey_id' => Hash::extract($projects, '{n}.Project.id'),
				'SurveyVisit.partner_id' => $partner['Partner']['id'],
				'SurveyVisit.type' => SURVEY_CLICK
			),
			'order' => 'SurveyVisit.id DESC',
			'limit' => '10'
		));
		$this->out('Current datetime: '.date(DB_DATETIME));
		print_r($survey_visits);
	}

	public function process_live_projects() {
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

		$log_file = 'lucid.update.quota';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting updating quota', $log_file, $log_key);
		
		$lucid_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'fulcrum'
			)
		));

		$last_project_id = 0;
		while (true) {
			$this->Project->getDatasource()->reconnect();
			$this->Project->bindModel(array(
				'hasOne' => array(
					'FedSurvey' => array(
						'className' => 'FedSurvey',
						'foreignKey' => 'survey_id'
					)
				)
			));
			$projects = $this->Project->find('all', array(
				'fields' => array(
					'Project.id', 'Project.mask', 'FedSurvey.fed_survey_id'
				),
				'conditions' => array(
					'Project.group_id' => $lucid_group['Group']['id'],
					'Project.status' => PROJECT_STATUS_OPEN,
					'Project.active' => true,
					'Project.id >' => $last_project_id
				),
				'contain' => array(
					'SurveyVisitCache' => array(
						'fields' => array(
							'SurveyVisitCache.click'
						)
					),
					'FedSurvey'
				),
				'order' => 'Project.id ASC',
				'limit' => 50,
				'recursive' => -1
			));
			if (!$projects) {
				break;
			}
			$project_ids = array();
			foreach ($projects as $project) {
				$last_project_id = $project['Project']['id'];
				if ($project['SurveyVisitCache']['click'] <= 1) {
					continue;
				}
				$this->out('Processing LucidProject#' . $project['FedSurvey']['fed_survey_id']);
				$project_ids[] = $project['FedSurvey']['fed_survey_id'];
			}
			if ($project_ids) {
				$this->lecho('Processing ' . count($project_ids) . ' projects for quota update', $log_file, $log_key);
				$query = ROOT.'/app/Console/cake lucid update_quota --project_ids=' . implode(',', $project_ids);
				exec($query, $output);
			}
		}
		$this->out('Finished.');
	}

	public function update_quota() {
		if (!isset($this->params['project_ids'])) {
			return;
		}
		$log_file = 'lucid.update.quota';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		$this->lecho('Starting updating quota for project_ids (' . $this->params['project_ids'] . ')', $log_file, $log_key);

		$keys = array(
			'lucid.host',
			'lucid.api.key',
			'lucid.supplier.code',
		);
		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => $keys,
				'Setting.deleted' => false
			)
		));
		if (count($settings) != count($keys)) {
			$diff_keys = array_diff($keys, array_keys($settings));
			$this->lecho('FAILED: You are missing required Lucid settings: '.implode(', ', $diff_keys), $log_file, $log_key);
			$this->lecho('Completed loadSettings (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
			return false;
		}
		$lucid_project_ids = explode(',', $this->params['project_ids']);
		foreach ($lucid_project_ids as $lucid_project_id) {
			$this->out('Processing LucidProject#' . $lucid_project_id . ' for updating quota.');
			$this->Project->bindModel(array(
				'hasOne' => array(
					'FedSurvey' => array(
						'className' => 'FedSurvey',
						'foreignKey' => 'survey_id'
					)
				)
			));
			$project = $this->Project->find('first', array(
				'contain' => array('FedSurvey'),
				'conditions' => array(
					'FedSurvey.fed_survey_id' => $lucid_project_id
				)
			));

			if (!$project) {
				continue;
			}

			$http = new HttpSocket(array(
				'timeout' => 15,
				'ssl_verify_host' => false
			));
			
			$url = $settings['lucid.host'] . 'Supply/v1/SurveyQuotas/BySurveyNumber/' . $lucid_project_id . '/' . $settings['lucid.supplier.code'];
			$params = array('key' => $settings['lucid.api.key']);
			$response = $http->get($url, $params);
			$body = json_decode($response['body'], true);
			$survey_quotas = $body['SurveyQuotas'];
			if (empty($survey_quotas)) {
				continue;
			}

			$overall_quota = $this->Lucid->quota($survey_quotas);
			$survey_visit_cache = $this->SurveyVisitCache->find('first', array(
				'fields' => array('complete'),
				'conditions' => array(
					'SurveyVisitCache.survey_id' => $project['Project']['id']
				),
				'recursive' => -1
			));
			if ($overall_quota && $overall_quota > 0 && $survey_visit_cache) {
				$overall_quota = $overall_quota + $survey_visit_cache['SurveyVisitCache']['complete'];
			}
			// update project.quota
			if ($project['Project']['quota'] != $overall_quota) {
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'quota' => $overall_quota,
				)), true, array('quota'));

				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'updated',
					'description' => 'Quota updated from ' . $project['Project']['quota'] . ' to ' . $overall_quota
				)));
				$this->lecho('Quota updated from ' . $project['Project']['quota'] . ' to ' . $overall_quota . ' for project#' . $project['Project']['id'], $log_file, $log_key);
			}
			// update qualification quota
			foreach ($survey_quotas as $survey_quota) {
				$this->Qualification->bindModel(array('hasOne' => array('QualificationStatistic')));
				$qualification = $this->Qualification->find('first', array(
					'conditions' => array(
						'Qualification.project_id' => $project['Project']['id'],
						'Qualification.partner_qualification_id' => $survey_quota['SurveyQuotaID'],
						'Qualification.deleted is null'
					)
				));
				if (!$qualification) {
					continue;
				}

				$original_qualification_data = array(
					'id' => $qualification['Qualification']['id'],
					'cpi' => $qualification['Qualification']['cpi'],
					'award' => $qualification['Qualification']['award'],
					'quota' => $qualification['Qualification']['quota']
				);
				$qualification_data = array(
					'id' => $qualification['Qualification']['id'],
					'cpi' => $survey_quota['QuotaCPI'],
					'award' => $this->convert_cpi_to_award($survey_quota['QuotaCPI']),
					'quota' => $survey_quota['NumberOfRespondents'] + $qualification['QualificationStatistic']['completes']
				);
				$diff = array_diff($qualification_data, $original_qualification_data);
				if (count($diff) > 0) {
					$this->Qualification->create();
					$this->Qualification->save(array('Qualification' => $qualification_data), true, array('quota', 'cpi', 'award'));
					$log = '';
					foreach ($diff as $key => $val) {
						$log .= $key . ' was updated from "' . $original_qualification_data[$key] . '" to "' . $val . '", ';
					}
					
					$log = substr($log, 0, -2);
					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $project['Project']['id'],
						'type' => 'qualification.updated',
						'description' => 'Qualification #' . $qualification['Qualification']['id'] . ' updated: ' . $log,
					)));
				}
			}
		}
		$this->lecho('Completed (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
	}
}
