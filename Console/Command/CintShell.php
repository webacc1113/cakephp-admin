<?php
App::import('Lib', 'QueryEngine');
App::import('Lib', 'CintMappings');
App::import('Lib', 'Utilities');
App::import('Lib', 'MintVine');
App::import('Lib', 'Cint');
App::uses('HttpSocket', 'Network/Http');

class CintShell extends AppShell {
	public $uses = array('ProjectLog', 'Setting', 'User', 'SurveyUser', 'CintLog', 'CintQuestion', 'SurveyPartner', 'CintAnswer', 'QueryProfile', 'GeoZip', 'GeoState', 'Group', 'Partner', 'Client', 'ProjectOption', 'Project', 'CintLog', 'CintSurvey', 'Query', 'QueryHistory', 'QueryStatistic', 'CintRegion', 'SurveyVisitCache', 'Question', 'QuestionText','Answer', 'AnswerText');
	public $tasks = array('Cint');
	private $options = array('header' => array(
		'Accept' => 'application/json',
		'Content-Type' => 'application/json; charset=UTF-8'
	));
	
	private function get_settings() {
		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name LIKE' => 'cint.%',
				'Setting.deleted' => false
			)
		));
		return $settings;
	}
	
	private function parse_countries($settings) {
		$countries = array();
		foreach ($settings as $name => $value) {
			if (strpos($name, '.key') !== false) {
				list($cint, $country, $key) = explode('.', $name);
				$countries[] = strtoupper($country);
			}
		}
		return $countries;
	}
	
	/* info: get xml qualifcation files for the selected language. These files are used by import_qualifications().
	 */
	public function import_xml_qualifications() {
		if (!file_exists(WWW_ROOT . '/files/cint')) {
			echo 'Please create folder /files/cint';
			return;
		}
		$settings = $this->get_settings();
		if (empty($settings)) {
			return false;
		}
		$countries = $this->parse_countries($settings);
		
		foreach ($countries as $country) { 
			if ($country == 'US') {
				$api_key = $settings['cint.us.key'];
				$api_secret = $settings['cint.us.secret'];
			}
			elseif ($country == 'GB') {
				$api_key = $settings['cint.gb.key'];
				$api_secret = $settings['cint.gb.secret'];
			}
			elseif ($country == 'CA') {
				$api_key = $settings['cint.ca.key'];
				$api_secret = $settings['cint.ca.secret'];
			}

			$http = new HttpSocket(array(
				'timeout' => 10,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$http->configAuth('Basic', $api_key, $api_secret);

			/* Questions */
			$url = $this->Cint->api_url($settings['cint.host'], $http, 'panel/questions', $api_key);
			if (!$url) {
				echo 'url not found!' . "\n";
				return;
			}

			try {
				$results = $http->get($url, array(), array('header' => array(
					'Accept' => 'application/xml',
					'Content-Type' => 'application/xml; charset=UTF-8'
				)));
			} catch (Exception $e) {
				echo "Api call failed when getting xml qualifications!" . "\n";
				return;
			}
		
			$file = fopen(WWW_ROOT . '/files/cint/qualifications-' . $country . '.xml', "w");
			fwrite($file, $results['body']);
			fclose($file);
		
			echo WWW_ROOT . '/files/cint/qualifications-' . $country . '.xml imported successfully.'."\n";
		}
		return true;
	}
	
	/* info: Import cint qualifications.
	 */
	public function import_qualifications() {
		if (!$this->import_xml_qualifications()) {
			return;
		}
		$settings = $this->get_settings();
		if (empty($settings)) {
			return false;
		}
		$countries = $this->parse_countries($settings);
		foreach ($countries as $country) { 

			if (!file_exists(WWW_ROOT . '/files/cint/qualifications-' . $country . '.xml')) {
				echo 'Please put the file at '. WWW_ROOT . '/files/cint/qualifications-' . $country . '.xml';
				return;
			}
	
			App::uses('Xml', 'Utility');
			$xml = Xml::toArray(Xml::build(WWW_ROOT . '/files/cint/qualifications-' . $country . '.xml'));
			if (!$xml) {
				echo 'Qualifications not found!';
				return;
			}

			foreach ($xml['surveys']['sss'] as $category) {
				foreach ($category['survey']['record']['variable'] as $question) {
					// If there is only one question in a category, the xml only provide direct question array not an indexed array as normally expected.
					if (is_array($question)) {
						$this->Cint->save_question($question, $country);
					}
					else {
						$this->Cint->save_question($category['survey']['record']['variable'], $country);
						break;
					}
				}
			}
		}
		
		echo 'update successful!' . "\n";
	}
	
	public function export_questions() {
		$this->CintQuestion->primaryKey = 'question_id';
		$this->CintQuestion->bindModel(array(
			'hasMany' => array(
				'CintAnswer' => array(
					'className' => 'CintAnswer',
					'foreignKey' => 'question_id'
				)
			)
		));
		$questions = $this->CintQuestion->find('all', array(
			'fields' => array(
				'CintQuestion.question_id',
				'CintQuestion.question_native_text',
				
			),
			'contain' => array(
				'CintAnswer' => array(
					'fields' => array(
						'CintAnswer.variable_id',
						'CintAnswer.answer',
						'CintAnswer.answer_native_text',
					)
				)
			) 
		));
		$fp = fopen(WWW_ROOT . 'files/cint/qualifications.csv', 'w');
		fputcsv($fp, array(
			'question_id',
			'question',
			'answer variable_id',
			'answer',
			'answer native text',
		));
		foreach ($questions as $question) {
			fputcsv($fp, array(
				$question['CintQuestion']['question_id'],
				$question['CintQuestion']['question_native_text'],
			));
			
			if (empty($question['CintAnswer'])) {
				continue;
			}
			
			foreach ($question['CintAnswer'] as $answer) {
				fputcsv($fp, array(
					'',
					'',
					$answer['variable_id'],
					$answer['answer'],
					$answer['answer_native_text'],
				));
			}
		}
		
		fclose($fp);
		echo "/files/cint/qualifications.csv created.". "\n";
		
	}
	
	public function import_regions() {
		$settings = $this->get_settings();
		if (empty($settings)) {
			return false;
		}
		$countries = $this->parse_countries($settings);
		foreach ($countries as $country) {
			if (isset($this->args[0])) {
				if ($this->args[0] != $country) {
					continue;
				}
			}
			if ($country == 'US') {
				$api_key = $settings['cint.us.key'];
				$api_secret = $settings['cint.us.secret'];
			}
			elseif ($country == 'GB') {
				$api_key = $settings['cint.gb.key'];
				$api_secret = $settings['cint.gb.secret'];
			}
			elseif ($country == 'CA') {
				$api_key = $settings['cint.ca.key'];
				$api_secret = $settings['cint.ca.secret'];
			}

			$http = new HttpSocket(array(
				'timeout' => 2,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$http->configAuth('Basic', $api_key, $api_secret);
			$url = $this->Cint->api_url($settings['cint.host'], $http, 'panel/country', $api_key);
			if (!$url) {
				echo 'url not found!' . "\n";
				return;
			}
		
			try {
				$results = $http->get($url, array(), $this->options);
			} catch (Exception $e) {
				echo "Api call failed." . "\n";
				return;
			}
		
			$results = json_decode($results, true);
			try {
				$results = $http->get($results['links'][1]['href'], array(), $this->options);
			} catch (Exception $e) {
				echo "Api call failed." . "\n";
				return;
			}
		
			$results = json_decode($results, true);

			if (!$results) {
				return;
			}
			foreach ($results['region_types'] as $region_type) {
				try {
					$results = $http->get($region_type['links'][2]['href'], array(), $this->options);
				} catch (Exception $e) {
					echo "Api call failed." . "\n";
					return;
				}
			
				$results = json_decode($results, true);
				if ($region_type['region_type']['name'] == 'Designated Market Areas') {
					$type = 'dma';
				}
				elseif ($region_type['region_type']['name'] == 'States and Territories') {
					$type = 'state';
				}
				else {
					$type = $region_type['region_type']['name'];
				}
			
				if (!$results['regions']) {
					continue;
				}
				
				$this->CintRegion->getDataSource()->reconnect();
				foreach ($results['regions'] as $region) {
					$dma_id = '';
					
					if ($type == 'dma') {
						if (strpos($region['region']['name'], ', ') !== FALSE) {
							$cint_region = explode(', ', $region['region']['name']);
							$dma = str_replace('-', ' - ', $cint_region[0]);
						}
						else {
							$dma = $region['region']['name'];
						}
						
						$geo_zip = $this->GeoZip->find('first', array(
							'conditions' => array(
								'GeoZip.dma LIKE' => '%'.$dma.'%'
							)
						));
						
						if ($geo_zip && !empty($geo_zip['GeoZip']['dma_code'])) {
							$dma_id = $geo_zip['GeoZip']['dma_code'];
							$this->out('Matched: MV '.$geo_zip['GeoZip']['dma_code'].' TO CINT '.$region['region']['id']);
						}
						else {
							// Manual DMA_ID Assignments
							$manual_dma = array(
								'480971' => '670',
								'482284' => '609',
								'482412' => '531',
								'482453' => '526',
								'482279' => '543',
								'480559' => '564',
								'481983' => '521',
								'480333' => '692',
								'481073' => '567',
								'482642' => '536',
								'480970' => '571',
								'481634' => '613',
								'482283' => '638',
								'480550' => '648',
								'480972' => '509',
								'481461' => '722',
								'481952' => '500'
							);
							
							if (array_key_exists($region['region']['id'], $manual_dma)) {
								$dma_id = $manual_dma[$region['region']['id']];
								$this->out('Matched: MV '.$dma_id.' TO CINT '.$region['region']['id']);
							}
							else {
								$this->out('No Match: Cint DMA: '.$region['region']['name'].' ID: '.$region['region']['id']);
							}
						}
					}

					if (strpos($region['region']['name'], ', ') !== FALSE) {
						$region_name = explode(',', $region['region']['name']);
						$name = $region_name[0];
					}
					else {
						$name = $region['region']['name'];
					}
										
					$cint_region = $this->CintRegion->find('first', array(
						'conditions' => array(
							'CintRegion.cint_id' => $region['region']['id'],
						)
					));
					
					if ($cint_region) {
						$this->CintRegion->create();
						$this->CintRegion->save(array('CintRegion' => array(
							'id' => $cint_region['CintRegion']['id'],
							'country' => $country,
							'name' => str_replace('-', ' - ', utf8_decode($name)),
							'raw' => utf8_decode($region['region']['name']),
							'dma_id' => $dma_id,
							'type' => $type 
						)), true, array('country', 'name', 'type', 'raw', 'dma_id'));
					}
					else {
						$this->CintRegion->create();
						$this->CintRegion->save(array('CintRegion' => array(
							'cint_id' => $region['region']['id'],
							'name' => str_replace('-', ' - ', utf8_decode($name)),
							'type' => $type,
							'raw' => utf8_decode($region['region']['name']),
							'dma_id' => $dma_id,
							'country' => $country
						)));
					}
				}
			}
		}
		
		echo "Regions imported!"."\n";
	}

	/* args: $total_count (optional)
	 * args: $cint_survey_id (optional) - if this argument exist, only this cint survey will be imported. 
	 */

	// view offerwall
	// args: $country, $cint_survey_id
	function view_offerwall() {
		if (!isset($this->args[0])) {
			echo 'Please specify a country';
			return false;
		}
		
		$settings = $this->get_settings();
		if ($this->args[0] == 'US') {
			$api_key = $settings['cint.us.key'];
			$api_secret = $settings['cint.us.secret'];
		}
		elseif ($this->args[0] == 'GB') {
			$api_key = $settings['cint.gb.key'];
			$api_secret = $settings['cint.gb.secret'];
		}
		elseif ($this->args[0] == 'CA') {
			$api_key = $settings['cint.ca.key'];
			$api_secret = $settings['cint.ca.secret'];
		}
		
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$http->configAuth('Basic', $api_key, $api_secret);
		$url = $this->Cint->api_url($settings['cint.host'], $http, 'panel/respondent-quotas', $api_key);
		if (!$url) {
			echo 'Failed: Respondent API URL is wrong'; 
			return;
		}
		$cint_response = $http->get($url, array(), $this->options);
		$results = json_decode($cint_response, true);
		
		if (isset($this->args[1])) {
			foreach ($results['respondent_quotas'] as $respondent_quota) {
				if ($respondent_quota['project_id'] == $this->args[1]) {
					print_r($respondent_quota);
				}
			}
		}
		else {
			print_r($results);
		}
	}
	
	// this runs every minute and creates and updates data around each project
	// arguments: 
	// 	country (required) - US/GB/CA
	public function import() {
		ini_set('memory_limit', '2048M');
		set_time_limit(599); // right under 10 minutes
		
		if (!isset($this->args[0])) {
			echo 'Please send a country';
			return false;
		}
		$start_mts = microtime(true);
		
		$log_key = $this->args[0].'-'.strtoupper(Utils::rand(3));
		$settings = $this->get_settings();
		if ($this->args[0] == 'US') {
			$api_key = $settings['cint.us.key'];
			$api_secret = $settings['cint.us.secret'];
		}
		elseif ($this->args[0] == 'GB') {
			$api_key = $settings['cint.gb.key'];
			$api_secret = $settings['cint.gb.secret'];
		}
		elseif ($this->args[0] == 'CA') {
			$api_key = $settings['cint.ca.key'];
			$api_secret = $settings['cint.ca.secret'];
		}
		
		$cint_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'cint'
			)
		));
		$cint_client = $this->Client->find('first', array(
			'conditions' => array(
				'Client.key' => 'cint',
				'Client.deleted' => false
			)
		));
		$mv_partner = $this->Partner->find('first', array(
			'conditions' => array(
				'Partner.key' => 'mintvine',
				'Partner.deleted' => false
			)
		));
		if (!$cint_group || !$cint_client || !$mv_partner) {
			echo "ERROR: You need a client named Cint in the Cint group & MintVine Partner.";
			return;
		}
		
		$this->lecho('Starting', 'cint.import', $log_key); 
		if (defined('IS_DEV_INSTANCE') && IS_DEV_INSTANCE === true) {
			$cint_response = file_get_contents(WWW_ROOT . 'files/cint.response.txt');
		}
		else {
			$http = new HttpSocket(array(
				'timeout' => 15,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$http->configAuth('Basic', $api_key, $api_secret);
			$url = $this->Cint->api_url($settings['cint.host'], $http, 'panel/respondent-quotas', $api_key);
			if (!$url) {
				echo 'Failed: Respondent API URL is wrong'; 
				return;
			}
			$cint_response = $http->get($url, array(), $this->options);
		}
		$this->Project->getDataSource()->reconnect();
		$results = json_decode($cint_response, true);
		print_r($results['respondent_quotas']); 
		
		// we later do some post-processing on $cint_log_ids
		$cint_log_ids = $this->Cint->write_log($http, $results['respondent_quotas'], $this->args[0]);
		
		if (!$results || empty($results) || empty($results['respondent_quotas'])) {
			$this->lecho('Failed to import', 'cint.import', $log_key); 
			$this->lecho(print_r($cint_response, true), 'cint.import', $log_key); 
			print_r($http);
			return false;
		}
		$this->lecho(print_r($results, true), 'cint.import', $log_key); 
		
		$api_cint_ids = Set::extract('/respondent_quotas/project_id', $results); 
		if (empty($api_cint_ids)) {
			echo ' No project IDs';
			return false;
		}
		
		$this->lecho('Total Respondent Quota Count: '.count($results['respondent_quotas']), 'cint.import', $log_key); 
		$all_available_cint_project_ids = array();
		$i = 0; 
		$cint_respondent_quotas = $updated_cint_projects_this_run = array();
		// loop through each quota and do something
		foreach ($results['respondent_quotas'] as $quota) {

			// Save the cint project_ids for later to close projects in MV not found on the wall
			if (!in_array($quota['project_id'], $all_available_cint_project_ids)) {
				$all_available_cint_project_ids[] = $quota['project_id'];
			}			
			
			// because we are no longer storing query-level data: a single update on a project ID is sufficient per pull
			if (in_array($quota['project_id'], $updated_cint_projects_this_run)) {
				continue;
			}
			$updated_cint_projects_this_run[] = $quota['project_id'];
			
			$cint_respondent_quotas[$quota['id']] = $quota['fulfillment']['estimated_remaining_completes'];
			$this->lecho('Starting '.$quota['id']. ' (#C'.$quota['project_id'].')', 'cint.import', $log_key);
			
			// due to the timing of these cronjobs, it's sometime possible that two projects get created at the same time
			// this causes problems when trying to find projects by cint ids
			// do a sanity check here
			$cint_survey = $this->CintSurvey->find('first', array(
				'conditions' => array(
					'CintSurvey.cint_survey_id' => $quota['project_id'],
					'CintSurvey.country' => $this->args[0]
				)
			));
			
			// Create a cint survey if not found
			if (!$cint_survey) {
				$i++;
				if (isset($quota['device_compatibility']['required_capabilities']) && array_search('dc_cam', $quota['device_compatibility']['required_capabilities']) !== false) {
					// we don't import projects that require webcam
					continue;
				}
			
				$this->lecho('Creating #C'.$quota['project_id'], 'cint.import', $log_key); 
			
				// Cint uses different rates for different quotas, for a project, we take the lowest
				$payout = preg_replace("/[^0-9,.]/", "", $quota['pricing']['indicative_cpi']);
				foreach ($results['respondent_quotas'] as $tmp_quota) {
					$tmp_payout = preg_replace("/[^0-9,.]/", "", $tmp_quota['pricing']['indicative_cpi']);
					if ($payout > $tmp_payout && $quota['project_id'] == $tmp_quota['project_id']) {
						$payout = $tmp_payout;
					}
				}
				
				// BR has only 40% share of the total cpi
				$payout = ($payout > 0) ? round($payout * 4 / 10, 2) : 0;
				$payout_to_partners = ($payout > 0) ? round($payout * 4 / 10, 2) : 0;					
				$award = 0;
				if ($payout > 0) {
					$award = intval($payout_to_partners * 100);
					if ($award > 200) {
						$award = 200;
					}
				}

				$save = false;
				$ProjectSource = $this->Project->getDataSource();
				$ProjectSource->reconnect();
				$ProjectSource->begin();
				$this->Project->bindModel(array(
					'hasOne' => array(
						'CintSurvey' => array(
							'className' => 'CintSurvey',
							'foreignKey' => 'survey_id'
						)
					)
				));
				$this->Project->create();
				$save = $this->Project->save(array('Project' => array(
					'prj_name' => 'Cint #' . $quota['project_id'],
					'client_id' => $cint_client['Client']['id'],
					'date_created' => date(DB_DATETIME),
					'bid_ir' => $quota['statistics']['conversion_rate'],
					'client_rate' => $payout,
					'partner_rate' => ($payout > 0 ) ? $payout_to_partners : 0,
					'user_payout' => ($award > 0) ? round($award / 100, 2) : 0,
					'quota' => 10000, // cint is handling the quota
					'est_length' => $quota['statistics']['length_of_interview'],
					'group_id' => $cint_group['Group']['id'],
					'status' => PROJECT_STATUS_OPEN,			
					'country' => $this->args[0],
					'language' => 'en',
					'survey_name' => 'Survey for you!',
					'singleuse' => true,
					'award' => $award,
					'active' => true,
					'dedupe' => 1,
					'description' => 'Survey for you!',
					'started' => date(DB_DATETIME)
				)));
				if ($save) {
					$project_id = $this->Project->getInsertId();
					$ProjectSource->commit();
					
					$this->CintSurvey->create();
					$this->CintSurvey->save(array('CintSurvey' => array(
						'survey_id' => $project_id,
						'cint_survey_id' => $quota['project_id'],
						'country' => $this->args[0]
					)));
					
					// add mintvine as a partner
					$this->SurveyPartner->create();
					$this->SurveyPartner->save(array('SurveyPartner' => array(
						'survey_id' => $project_id,
						'partner_id' => $mv_partner['Partner']['id'],
						'rate' => ($payout > 0 ) ? $payout_to_partners : 0,
						'complete_url' => HOSTNAME_WWW.'/surveys/complete/{{ID}}/',
						'nq_url' => HOSTNAME_WWW.'/surveys/nq/{{ID}}/',
						'oq_url' => HOSTNAME_WWW.'/surveys/oq/{{ID}}/',
						'pause_url' => HOSTNAME_WWW.'/surveys/paused/',
						'fail_url' => HOSTNAME_WWW.'/surveys/sec/{{ID}}/',
					)));
			
					// Save required_capabilities
					if (isset($quota['device_compatibility']['required_capabilities']) && !empty($quota['device_compatibility']['required_capabilities'])) {
						$this->Project->ProjectOption->create();
						$this->Project->ProjectOption->save(array('ProjectOption' => array(
							'project_id' => $project_id,
							'name' => 'cint_required_capabilities',
							'value' => implode(', ', $quota['device_compatibility']['required_capabilities'])
						)));			
					}
				}
				else {
					$ProjectSource->commit();
				}
				
				if ($save) {
					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $project_id,
						'type' => 'created',
						'description' => ''
					)));
					$this->lecho('Created #'.$project_id.' (Cint: '.$quota['project_id'].')', 'cint.import', $log_key); 
				}
				continue;
			}
			
			// Update existing cint surveys
			$this->Project->getDataSource()->reconnect();
			$this->CintSurvey->bindModel(array(
				'belongsTo' => array(
					'Project' => array(
						'className' => 'Project',
						'foreignKey' => 'survey_id'
					)
				)
			));
			$project = $this->CintSurvey->find('first', array(
				'contain' => array(
					'Project'
				),
				'conditions' => array(
					'CintSurvey.cint_survey_id' => $quota['project_id'],
					'CintSurvey.country' => $this->args[0]
				)
			));
			
			if (!$project || empty($project['Project']['id'])) { 
				$this->lecho('Could not find (#C'.$quota['id'], 'cint.import', $log_key);
				continue;
			}
			
			$this->lecho('Found #'.$project['Project']['id'].' (#C'.$quota['id'].')', 'cint.import', $log_key);
			$this->lecho(print_r($project, true), 'cint.import', $log_key); 
			
			// skipped invoice projects: closed projects could still be re-opened
			if (in_array($project['Project']['status'], array(PROJECT_STATUS_INVOICED))) {
				continue;
			}
					
			$this->lecho('Updating #'.$project['Project']['id'], 'cint.import', $log_key);
			// Reopen projects found on the offerwall that are closed in MV
			if ((!$project['Project']['active'] || $project['Project']['status'] == PROJECT_STATUS_CLOSED) && !$project['Project']['ignore_autoclose']) {
				$project_logs = $this->ProjectLog->find('all', array(
					'conditions' => array(
						'ProjectLog.project_id' =>  $project['Project']['id']
					),
					'order' => 'ProjectLog.id ASC'
				));	

				$sampled = 0;
				$last_sample = array();
				$last_close = array();
				$open_project = false;
			
				foreach ($project_logs as $project_log) {
					if ($project_log['ProjectLog']['internal_description'] == 'opened.sample' || $project_log['ProjectLog']['type'] == 'created') {
						$sampled++;
						$last_sample = $project_log;
					}
	
					if ($project_log['ProjectLog']['type'] == 'status.closed.auto') {
						$last_close = $project_log;
					}
				}
				
				// Project was last closed because it wasn't on the offerwall 
				if ($last_close['ProjectLog']['description'] == 'Closed by Cint - not found in offerwall' || $last_close['ProjectLog']['internal_description'] == 'closed.wall') {
					// Project hasn't been permanently shut down for excessive violation of epc cutoffs
					if ($sampled < 3) {
						$open_project = true;
						$internal_description = 'opened.wall';
						$message = '#' . $project['Project']['id'] . ': OPENED project found on offerwall. ';
					}
				}
				
				// Project was last closed because of performance
				if ($last_close['ProjectLog']['description'] != 'Closed by Cint - not found in offerwall' && $last_close['ProjectLog']['internal_description'] != 'closed.wall') {
					// Project hasn't been permanently shut down for excessive violation of epc cutoffs
					if ($sampled < 3) {
						if (strtotime('-'.$settings['cint.sample.hours'].' hours') > strtotime($last_close['ProjectLog']['created'])) {
							$open_project = true;
							$internal_description = 'opened.sample';
							$message = '#' . $project['Project']['id'] . ': OPENED '.$settings['cint.sample.hours'].' hours have passed since last closure. Project has only been sampled '.$sampled.' of 3 times';
						}
					}
				}
				
				if ($open_project) {
					$this->Project->create();
					$this->Project->save(array('Project' => array(
						'id' => $project['Project']['id'],
						'status' => PROJECT_STATUS_OPEN,
						'active' => true,
						'ended' => null
					)), true, array('status', 'active', 'ended'));

					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $project['Project']['id'],
						'type' => 'status.opened.reopen',
						'description' => $message,
						'internal_description' => $internal_description
					)));
				}
			}
			
			$project_quotas = $payout_amounts = array();
			foreach ($results['respondent_quotas'] as $respondent_quota) {
				if ($respondent_quota['project_id'] == $quota['project_id']) {
					$project_quotas[] = $respondent_quota['fulfillment']['estimated_remaining_completes']; 
					$payout_amounts[] = preg_replace("/[^0-9,.]/", "", $respondent_quota['pricing']['indicative_cpi']); 
				}
			}
			$project_quota = max($project_quotas);
			$payout = min($payout_amounts); 
			
			// BR has only 40% share of the total cpi
			$payout = ($payout > 0) ? round($payout * 4 / 10, 2) : 0;
			$payout_to_partners = ($payout > 0) ? round($payout * 4 / 10, 2) : 0;					
			$award = 0;
			if ($payout > 0) {
				$award = intval($payout_to_partners * 100);
				if ($award > 200) {
					$award = 200;
				}
			}

			// if rate changes we update partners
			if ($project['Project']['client_rate'] != $payout) {
				$survey_partners = $this->Project->SurveyPartner->find('all', array(
					'conditions' => array(
						'SurveyPartner.survey_id' => $project['Project']['id'],
						'partner_id' => $mv_partner['Partner']['id'],
					))
				);
				foreach ($survey_partners as $survey_partner) {
					$this->Project->SurveyPartner->create();
					$this->Project->SurveyPartner->save(array('SurveyPartner' => array(
						'id' => $survey_partner['SurveyPartner']['id'],
						'rate' => $payout_to_partners,
					)), true, array('rate'));
				}
			}
			
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'client_rate' => $payout,
				'award' => $award,
				'bid_ir' => $quota['statistics']['conversion_rate'],
				'partner_rate' => ($payout > 0 ) ? $payout_to_partners : 0,
				'user_payout' => ($award > 0) ? round($award / 100, 2) : 0,
				'est_length' => $quota['statistics']['length_of_interview'],
			)), true, array('client_rate', 'award', 'bid_ir', 'partner_rate', 'user_payout', 'est_length'));
		}
		
		// Close any projects in MV not found in Cint.
		$this->Project->bindModel(array(
			'hasOne' => array(
				'CintSurvey' => array(
					'className' => 'CintSurvey',
					'foreignKey' => 'survey_id'
				)
			)
		));
		$this->Project->unbindModel(array('hasMany' => array('ProjectOption', 'SurveyPartner'))); 
		
		$cint_surveys = $this->Project->find('all', array(
			'conditions' => array(
				'CintSurvey.country' => $this->args[0],
				'Project.status' => PROJECT_STATUS_OPEN,
				'Project.group_id' => $cint_group['Group']['id']
			)
		));
		//echo $this->Project->getLastQuery(); exit();

		foreach ($cint_surveys as $local_survey) {
			if (in_array($local_survey['CintSurvey']['cint_survey_id'], $all_available_cint_project_ids)) {
				continue;
			}
			$message = '#' . $local_survey['CintSurvey']['survey_id'] . ': Closed by Cint - not found in offerwall';
			// Close project (It doesn't exist in the array)
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $local_survey['CintSurvey']['survey_id'],
				'status' => PROJECT_STATUS_CLOSED,
				'active' => false,
				'ended' => empty($local_survey['Project']['ended']) ? date(DB_DATETIME) : $local_survey['Project']['ended']
			)), true, array('status', 'active', 'ended'));

			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $local_survey['CintSurvey']['survey_id'],
				'type' => 'status.closed.auto',
				'description' => 'Closed by Cint - not found in offerwall',
				'internal_description' => 'closed.wall'
			)));
			Utils::save_margin($local_survey['CintSurvey']['survey_id']);
			CakeLog::write('auto.close', $message);
		}
	
		// process cint_log_ids and fill in some statistical data; do this at end so it doesn't affect any project processing
		if (!empty($cint_log_ids)) {
			$stored_surveys = array();
			foreach ($cint_log_ids as $cint_log_id) {
				$cint_log = $this->CintLog->find('first', array(
					'conditions' => array(
						'CintLog.id' => $cint_log_id
					),
					'fields' => array('id', 'cint_survey_id', 'cint_quota_id', 'quota')
				));
				if ($cint_log && !empty($cint_log['CintLog']['cint_survey_id'])) {
					if (isset($stored_surveys[$cint_log['CintLog']['cint_survey_id']])) {
						$cint_survey = $stored_surveys[$cint_log['CintLog']['cint_survey_id']];
					}
					else {
						$cint_survey = $this->CintSurvey->find('first', array(
							'fields' => array('Project.id', 'Project.status', 'Project.bid_ir', 'Project.active', 'SurveyVisitCache.click', 'SurveyVisitCache.complete', 'SurveyVisitCache.nq', 'SurveyVisitCache.overquota'),
							'conditions' => array(
								'CintSurvey.cint_survey_id' => $cint_log['CintLog']['cint_survey_id'],
								'CintSurvey.country' => $this->args[0]
							),
							'joins' => array(
				    		    array(
						            'alias' => 'SurveyVisitCache',
						            'table' => 'survey_visit_caches',
						            'conditions' => array(
										'CintSurvey.survey_id = SurveyVisitCache.survey_id'
									),
								),
				    		    array(
						            'alias' => 'Project',
						            'table' => 'projects',
						            'conditions' => array(
										'CintSurvey.survey_id = Project.id'
									),
								)
							),
						));
						$stored_surveys[$cint_log['CintLog']['cint_survey_id']] = $cint_survey;
					}
					
					if ($cint_survey) {
						$this->CintLog->create();
						$this->CintLog->save(array('CintLog' => array(
							'id' => $cint_log_id,
							'modified' => false,
							'status' => $cint_survey['Project']['status'],
							'status_active' => $cint_survey['Project']['active'],
							'status_ir' => $cint_survey['Project']['bid_ir'],
							'status_clicks' => $cint_survey['SurveyVisitCache']['click'],
							'status_completes' => $cint_survey['SurveyVisitCache']['complete'],
							'status_nqs' => $cint_survey['SurveyVisitCache']['nq'],
							'status_oqs' => $cint_survey['SurveyVisitCache']['overquota'],
						)), true, array('status', 'status_active', 'status_ir', 'status_clicks', 'status_completes', 'status_nqs', 'status_oqs'));

						// write the quota statistics
						MintVine::project_quota_statistics('cint', $cint_log['CintLog']['quota'], $cint_survey['Project']['id'], $cint_log['CintLog']['cint_quota_id']);
					}
				}
			}
		}
		
		$end_mts = microtime(true);
		$this->lecho('Finished ('.($end_mts - $start_mts).')', 'cint.import', $log_key); 
	}
	
	public function find_dupes() {
		ini_set('memory_limit', '1024M');
		
		// countries to operate on
		$countries = array('CA', 'GB', 'US');
		
		// stores project ids per country
		$project_ids = array();
		foreach ($countries as $country) {
			$cint_logs = $this->CintLog->find('all', array(
				'fields' => array('DISTINCT(cint_survey_id)'),
				'conditions' => array(
					'CintLog.country' => $country,
					'CintLog.cint_survey_id >' => '0'
				)
			));
			foreach ($cint_logs as $cint_log) {
				$project_ids[$country][] = $cint_log['CintLog']['cint_survey_id'];
			}
		}
		
		// comparison tests to run between countries
		$diff_test = array(
			array('US', 'GB'),
			array('CA', 'GB'),
			array('US', 'CA')
		);
		$total_missed_completes = 0; 
		// test each country for dupes
		foreach ($diff_test as $tests) {
			list($country, $test_country) = $tests;
			$intersection = array_intersect($project_ids[$country], $project_ids[$test_country]);
			echo 'Found '.count($intersection).' overlapping projects between '.$country.' ('.count($project_ids[$country]).' total) and '.$test_country." (".count($project_ids[$test_country])." total)\n";
			
			foreach ($intersection as $cint_survey_id) {
				$this->CintSurvey->bindModel(array(
					'belongsTo' => array(
						'Project' => array(
							'className' => 'Project',
							'foreignKey' => 'survey_id'
						)
					)
				));
				$project = $this->CintSurvey->find('first', array(
					'contain' => array(
						'Project'
					),
					'conditions' => array(
						'CintSurvey.cint_survey_id' => $cint_survey_id
					)
				));
				$exists_country = $project['Project']['country'];
				$diff = array_diff($tests, array($exists_country)); 
				$non_matched_country = current($diff); 
				
				// grab the last cint log value for this
				$last_log = $this->CintLog->find('first', array(
					'conditions' => array(
						'CintLog.cint_survey_id' => $cint_survey_id,
						'CintLog.country' => $non_matched_country
					),
					'order' => 'CintLog.id DESC'
				));
				
				$total_completes = $last_log['CintLog']['statistic_completes'];
				echo 'Cint #'.$cint_survey_id.' exists only in '.$exists_country." - project had ".$total_completes." completes\n"; 
				$total_missed_completes = $total_missed_completes + $total_completes; 
			}
		}
		echo 'Total missed completes: '.$total_missed_completes."\n";
	}
	
	
	public function region_match() {
		ini_set('memory_limit', '8192M');
		if (!isset($this->args[0])) {
			$this->out('File needed to process results');
			return false;
		}
		else {
			$data = Utils::csv_to_array($this->args[0], ';');
		}
		
		$total = count($data);
				
		$regions = $this->CintRegion->find('all', array(
			'conditions' => array(
				'CintRegion.country' => 'GB'
			)
		));
		
		
		foreach ($regions as $region) {
			$st = $region['CintRegion']['raw'];
			$search = array();

			$matches = json_decode($region['CintRegion']['matching_zips'], true);
			if (empty($matches)) {
				$matches = array();
			}

			switch ($region['CintRegion']['type']) {
				case 'Main regions':
					if ($region['CintRegion']['raw'] == 'NORTH EAST YORKSHIRE & THE HUMBER') {
						$search[] = 'NORTH EAST YORKSHIRE';
						$search[] = 'THE HUMBER';
					}
					else {
						$temp = $region['CintRegion']['raw'];
						$search[] = $temp;
					}
				break;
				case 'Local regions':
					if (stripos($region['CintRegion']['raw'], 'and') || stripos($region['CintRegion']['raw'], ',')) {
						if ($region['CintRegion']['raw'] == 'Surrey, East and West Sussex ') {
							$temp = 'Surrey, East Sussex, West Sussex';
						}
						else {
							$temp = strtolower($region['CintRegion']['raw']);
						}
						$temp = str_replace(' and ', ', ', $temp);
						$temp = explode(', ', $temp);
						foreach ($temp as $term) {
							$search[] = $term;
						}
					}
					else {
						$temp = $region['CintRegion']['raw'];
						$search[] = $temp;
					}
				break;
				case 'Counties':
					if (stripos($region['CintRegion']['raw'], 'and') || stripos($region['CintRegion']['raw'], ',')) {
						$temp = strtolower($region['CintRegion']['raw']);
						$temp = str_replace(' and ', ', ', $temp);
						$temp = explode(', ', $temp);
						foreach ($temp as $term) {
							$term = str_replace('County ', '', $term);
							$term = str_replace(' County', '', $term);
							$search[] = $term;
						}
					}
					else {
						$temp = $region['CintRegion']['raw'];
						$search[] = $temp;
					}
				break;
			}
			
			foreach ($search as $term) {
				$term = trim($term);
				foreach ($data as $zip) {
					if ((isset($zip[4]) && stripos($zip[4], $term) !== false) || 
						(isset($zip[5]) && stripos($zip[5], $term) !== false) || 
						(isset($zip[6]) && stripos($zip[6], $term) !== false) || 
						(isset($zip[7]) && stripos($zip[7], $term) !== false) || 
						(isset($zip[8]) && stripos($zip[8], $term) !== false)) 
					{
						if (!empty($zip[9]) && strpos($zip[9], ' ') !== false) {
							list($outcode, $incode) = explode(" ", $zip[9]);
							if (isset($outcode) && !in_array($outcode, $matches)) {
								$matches[] = $outcode;	
							}
						}
					}
				}
			}

			if (count($matches) == 0) {
				$this->out(''.$st.': No matches found!');
			} else {
				$this->out(''.$st.': '.count($matches).' Matches found - LEN: '.strlen(json_encode($matches)).'!');				
				$this->CintRegion->create();
				$this->CintRegion->save(array('CintRegion' => array(
					'id' => $region['CintRegion']['id'],
					'matching_zips' => json_encode($matches)
				)), true, array('matching_zips'));
			}
		}
	}
	
	public function update_question_answer_list() {
		if (!$this->import_xml_qualifications()) {
			return;
		}
		
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => 'slack.questions.webhook',
				'Setting.deleted' => false
			),
			'recursive' => -1
		));
		if (empty($settings)) {
			$this->out('Missing required settings');
			return;
		}
		
		$country_names = array('CA', 'GB', 'US');
		foreach ($country_names as $country) {
			$this->Question->getDatasource()->reconnect();
			if (!file_exists(WWW_ROOT . '/files/cint/qualifications-' . $country . '.xml')) {
				$this->out('Please put the file at '. WWW_ROOT . '/files/cint/qualifications-' . $country . '.xml');
				continue;
			}
	
			App::uses('Xml', 'Utility');
			$xml = Xml::toArray(Xml::build(WWW_ROOT . '/files/cint/qualifications-' . $country . '.xml'));
			if (!$xml) {
				$this->out('Qualifications not found in /files/cint/qualifications-' . $country . '.xml');
				continue;
			}

			foreach ($xml['surveys']['sss'] as $category) {
				foreach ($category['survey']['record']['variable'] as $question) {
					
					// If there is only one question in a category, the xml only provide direct question array not an indexed array as normally expected.
					$break = false;
					if (!is_array($question)) {
						$question = $category['survey']['record']['variable'];
						$break  = true;
					}
					
					$this->out("Processing question " . $country . ": " . $question['@ident']);
					$question_type = $question['@type'];
					if ($question['@type'] == 'single') {
						$question_type = QUESTION_TYPE_SINGLE;
					}
					elseif ($question['@type'] == 'multiple') {
						$question_type = QUESTION_TYPE_MULTIPLE;
					}
					
					$mintvine_question = $this->Question->find('first', array(
						'fields' => array('Question.id', 'Question.question_type'),
						'conditions' => array(
							'Question.partner_question_id' => $question['@ident'],
							'Question.partner' => 'cint'
						),
						'recursive' => -1
					));
					if (!$mintvine_question) {
						$questionDataSource = $this->Question->getDataSource();
						$questionDataSource->begin();
						$this->Question->create();
						$this->Question->save(array('Question' => array(
							'partner_question_id' => $question['@ident'],
							'partner' => 'cint',
							'question' => $question['label']['@'],
							'question_type' => $question_type,
							'logic_group' => null,
							'order' => null,
							'skipped_answer_id' => null,
							'behavior' => null
						)));

						$question_id = $this->Question->getInsertId();
						$questionDataSource->commit();
						$mintvine_question = $this->Question->findById($question_id);
						$message = 'New Cint Question saved';
						$message .= "\nID: " . $question['@ident'];
						$message .= "\nQuestion: " . $question['label']['@'];
						$this->out($message);
						Utils::slack_alert($settings['slack.questions.webhook'], $message);
					}
					elseif ($mintvine_question['Question']['question_type'] != $question_type) {
						$this->Question->create();
						$this->Question->save(array('Question' => array(
							'id' => $mintvine_question['Question']['id'],
							'question_type' => $question_type
						)), true, array('question_type')); 
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
							$this->QuestionText->save(array('QuestionText' => array(
								'question_id' => $mintvine_question['Question']['id'],
								'country' => $country,
								'text' => $question['label']['text']['@']
							)));
						}
					}
					
					// Answers
					if (!isset($question['values']['value'])) {
						$this->out('answers not found for question id: ' . $question['@ident']);
						continue;
					}
					
					$this->Answer->getDatasource()->reconnect();
					foreach ($question['values']['value'] as $answer) {
						$mintvine_answer = $this->Answer->find('first', array(
							'fields' => array('Answer.id'),
							'conditions' => array(
								'Answer.partner_answer_id' => $answer['@cint:variable-id'],
								'Answer.question_id' =>  $mintvine_question['Question']['id'],
							),
							'recursive' => -1
						));

						if (!$mintvine_answer) {
							$answerDataSource = $this->Answer->getDataSource();
							$answerDataSource->begin();
							$this->Answer->create();
							$this->Answer->save(array('Answer' => array(
								'answer' => $answer['@'],
								'partner_answer_id' => $answer['@cint:variable-id'],
								'question_id' => $mintvine_question['Question']['id'],
								'country' => $country,
								'ignore' => false
							)));
							$answer_id = $this->Answer->getInsertId();
							$answerDataSource->commit();
							$mintvine_answer = $this->Answer->findById($answer_id);
							$this->out('New answer saved: variable_id:' . $answer['@cint:variable-id']);
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
								$answer_text = (isset($answer['text']['@'])) ? $answer['text']['@'] : $answer['@'];
								$this->AnswerText->create();
								$this->AnswerText->save(array('AnswerText' => array(
									'answer_id' => $mintvine_answer['Answer']['id'],
									'country' => $country,
									'text' => $answer_text
								)));
							}
						}
					}
					
					// this check is becase of the structure of the xml file. If there is only one question, the array is provided directly.
					if ($break) {
						break;
					}
				}
			}
		}
		
		$this->out('Import completed.');
	}
}