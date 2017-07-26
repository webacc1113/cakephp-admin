<?php
App::uses('HttpSocket', 'Network/Http');
App::import('Lib', 'SsiMappings');
App::import('Lib', 'Utilities');

class SsiShell extends AppShell {

	public $uses = array('ProjectLog', 'User', 'UserOption', 'QueryProfile', 'Setting', 'Project', 'SsiLink', 'Group', 'SsiInvite', 'Prescreener', 'SurveyVisit', 'Client', 'Transaction', 'Partner', 'UserRouterLog', 'PartnerUser', 'ProjectLog', 'SurveyVisitCache');
	public $tasks = array('Ssi', 'Notify');
	public $api_url = 'https://dkr1.ssisurveys.com';
	public $prefix_path = '/partner/saas/importsvc';
	public $ssi_username = 'vnd_brandedresearch';
	public $ssi_password = '!welcome2ssi!';
	public $source_id = '694_3149';
	
	private $ssi_options = array(
		'header' => array(
			'Accept' => 'application/json',
			'Content-Type' => 'application/json; charset=UTF-8'
		)
	);

	public function manage_projects() {
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'ssi'
			)
		));
		if (!$group) {
			$this->out('Failed: Group does not exist'); 
			return;
		}
		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => array('ssi.active', 'ssi.cpi', 'ssi.payout', 'hostname.www', 'ssi.rtv.cpi', 'ssi.rtv.payout'),
				'Setting.deleted' => false
			)
		));
		if (empty($settings) || count($settings) < 6) {
			CakeLog::write('ssi.projects', 'Required settings are missing'); 
			$this->out('Failed: Required settings are missing'); 
			return;
		}
		// stop project creation if SSI is turned off
		if ($settings['ssi.active'] != 'true') {
			$this->out('Failed: SSI is inactive');
			return;
		}
		
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'ssi'
			)
		));
		$client = $this->Client->find('first', array(
			'fields' => array('Client.id'),
			'conditions' => array(
				'Client.key' => 'ssi',
				'Client.deleted' => false
			)
		));
		if (!$group || !$client) {
			CakeLog::write('ssi.projects', 'Missing SSI group and/or client'); 
			return;
		}
		
		// project types
		$ssi_project_types = array(
			'SSI Router',
			'SSI RTV'
		);
		
		$supported_countries = array_keys(unserialize(SUPPORTED_COUNTRIES));
		
		foreach ($ssi_project_types as $ssi_project_type) {
			$projects = $this->Project->find('all', array(
				'conditions' => array(
					'Project.status' => PROJECT_STATUS_OPEN,
					'Project.prj_name LIKE' => $ssi_project_type.'%',
					'Project.group_id' => $group['Group']['id']
				),
				'order' => 'Project.id DESC'
			));
			$countries = array();
			if ($projects) {
				foreach ($projects as $project) {
					$countries[] = $project['Project']['country'];
				}
			}
			$countries = array_unique($countries); // if one is old; it'll keep reduping it
			$missing_countries = array_diff($supported_countries, $countries);
			// create missing countries every night
			if (!empty($missing_countries)) {
				foreach ($missing_countries as $missing_country) {
					// RTV is US-only
					if ($ssi_project_type == 'SSI RTV' && $missing_country != 'US') {
						continue;
					}
					$this->out('Creating '.$ssi_project_type.' for '.$missing_country); 
					$this->create_project($client, $group, $settings, $missing_country, $ssi_project_type);
				}
			}
			
			// update existing projects		
			$projects = $this->Project->find('all', array(
				'conditions' => array(
					'Project.status' => PROJECT_STATUS_OPEN,
					'Project.prj_name LIKE' => $ssi_project_type.'%',
					'Project.group_id' => $group['Group']['id'],
					'Project.active' => true
				),
				'order' => 'Project.id DESC'
			));
			foreach ($projects as $project) {
				$click_count = $project['SurveyVisitCache']['click'];
				// SSI routers close at 20K; RTV projects close whenever this is run (once per day)
				if ($ssi_project_type == 'SSI RTV') {
					
					if ($click_count > 0) {
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
						$this->out('Closed RTV project #'.$project['Project']['id']); 
						$this->create_project($client, $group, $settings, 'US', $ssi_project_type);
					}
				}
				else {
					if ($click_count > 20000) {
						$country = $project['Project']['country'];
						if (empty($country)) {
							continue;
						}
						$this->out('Creating '.$ssi_project_type);
						$this->create_project($client, $group, $settings, $country, $ssi_project_type);
					}
				}
			}
		}
	}
	
	// closes rtv projects
	public function close_rtv_projects() {
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'ssi'
			)
		));
		if (!$group) {
			$this->out('Failed: Group does not exist'); 
			return;
		}
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.status' => PROJECT_STATUS_OPEN,
				'Project.prj_name LIKE' => 'SSI RTV%',
				'Project.group_id' => $group['Group']['id']
			),
			'order' => 'Project.id DESC'
		));
		if (!$projects) {
			return;
		}
		foreach ($projects as $project) {
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
		}
	}
	
	public function close_projects() {
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'ssi'
			)
		));
		if (!$group) {
			return;
		}
		$project_types = array('SSI Router', 'SSI RTV');
		foreach ($project_types as $project_type) {
			$projects = $this->Project->find('all', array(
				'conditions' => array(
					'Project.status' => PROJECT_STATUS_OPEN,
					'Project.prj_name LIKE' => $project_type.'%',
					'Project.group_id' => $group['Group']['id']
				),
				'order' => 'Project.id DESC'
			));
			if (!$projects) {
				return;
			}
		
			$matched_countries = array();
			foreach ($projects as $project) {
				$country = $project['Project']['country'];
				if (empty($country)) {
					continue;
				}

				if (!in_array($country, $matched_countries)) {
					$matched_countries[] = $country;
				}
				else {
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
					CakeLog::write('ssi.projects', 'Closed #'.$project['Project']['id'].' for being a dupe'); 
					$this->out('Closed #'.$project['Project']['id'].' for being a dupe');
					CakeLog::write('auto.close', '#' . $project['Project']['id'] . ' closed 24 hours after creation (SSI)');
					break;
				}
			}
		}
	}
	
	// args[0]: $user_id (optional)
	public function get_attributes() {
		$http = new HttpSocket(array(
			'timeout' => 2,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('ssi.active', 'ssi.source_id')
			)
		));
		//$country_id = (isset($this->args[0]) && !empty($this->args[0])) ? $this->args[0] : 'US';
		$uri = array(
			'host' => $this->api_url,
			'path' => '/offers/offers.json'
		);
		$query = array(
			'country' => 'US',
			'language' => 'en',
			'sourceID' => $settings['ssi.source_id']
		);
		
		$results = $http->get($uri, $query, $this->ssi_options);
		$results = json_decode($results, true);
		pr($results);
		//pr($results);
		/* -----------process attributes--------------
		  1.	List of required features
		  2.	List of optional features
		  3.	Validation rules for features where applicable
		  4.	Name and type of features
		  5.	Definition of values expected for a features. Especially for Enumeration-type features
		  6.	Error definitions
		  7.	Response codes
		 */
	}

	// args[0]: country (required)
	// args[1]: $user_id (optional)
	// args[2]: $limit (optional)
	public function import() {
		if (!isset($this->args[0])) {
			return; 
		}
		
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('ssi.active', 'ssi.source_id')
			)
		));
		
		if (!isset($settings['ssi.active']) || $settings['ssi.active'] == 'false') {
			return;
		}
		
		$country = strtoupper($this->args[0]);
		if (!in_array($country, array_keys(unserialize(SUPPORTED_COUNTRIES)))) {
			return false;
		}
		ini_set('memory_limit', '1024M');

		$log_key = $country.'-'.strtoupper(Utils::rand(3));		
		$this->lecho('Starting', 'ssi.users', $log_key);
		
		$conditions = array(
			'AND' => array(
				'User.lastname !=' => '',
				'User.firstname !=' => ''
			),
			'User.email <>' => null,
			'User.deleted_on' => null,
			'User.last_touched >=' => date(DB_DATETIME, strtotime('-4 weeks')),
			'User.hellbanned' => false,
			'QueryProfile.gender <>' => '',
			'QueryProfile.postal_code <>' => '',
			'QueryProfile.birthdate <' => date(DB_DATETIME, strtotime('-14 years')), // older than 14 years old
			'QueryProfile.birthdate >' => date(DB_DATETIME, strtotime('-100 years')),
			'QueryProfile.country' => $country, 
			'PartnerUser.id is null'
		);

		if (isset($this->args[1]) && !empty($this->args[1])) {
			$conditions['User.id'] = $this->args[1];			
			$this->lecho('Single user import: '.$this->args[1], 'ssi.users', $log_key);
		}

		$limit = isset($this->args[2]) && !empty($this->args[2]) ? $this->args[2] : 100;
		$last_user_id = 0;
		while (true) {
			// reconnect since there can be a lag here and we can get "General error: 2006 MySQL server has gone away"
			$this->User->getDatasource()->reconnect();
			$this->User->bindModel(array('hasOne' => array(
				'PartnerUser' => array(
					'className' => 'PartnerUser',
					'foreignKey' => 'user_id',
					'conditions' => array(
						'PartnerUser.partner' => 'ssi'
					)
				)
			)));
			
			$conditions['User.id >'] = $last_user_id;
			$users = $this->User->find('all', array(
				'fields' => array('User.id', 'User.firstname', 'User.lastname', 'QueryProfile.*', 'PartnerUser.*'),
				'conditions' => $conditions,
				'contain' => array(
					'QueryProfile',
					'PartnerUser'
				),
				'order' => 'User.id ASC',
				'limit' => $limit
			));
			if (!$users) {
				$this->lecho('Completed', 'ssi.users', $log_key);
				break;
			}
			$this->lecho('Exporting '.count($users).' panelists', 'ssi.users', $log_key);
			$http = new HttpSocket(array(
				'timeout' => 2,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$http->configAuth('Basic', $this->ssi_username, $this->ssi_password);
			$query = array(
				'country' => $country,
				'language' => 'en',
				'sourceID' => $settings['ssi.source_id']
			);
			if ($country == 'US') {
				$features = array(
					'first_name',
					'last_name',
					'email_address',
					'gender',
					'daydob',
					'monthdob',
					'yeardob',
					'postalcode',
					'raceUS',
					'ethnicityUS',
					'education',
					'employment',
					'occupation',
					'income'
				);
			}
			elseif ($country == 'GB') {
				$features = array(
					'first_name',
					'last_name',
					'email_address',
					'gender',
					'daydob',
					'monthdob',
					'yeardob',
					'postalcode',
				);
			}
			elseif ($country == 'CA') {
				$features = array(
					'first_name',
					'last_name',
					'email_address',
					'gender',
					'daydob',
					'monthdob',
					'yeardob',
					'postalcode',
				);
			}
			foreach ($users as $user) {
				$last_user_id = $user['User']['id'];
				$start_mts = microtime(true);
				if (empty($user['User']['lastname']) || empty($user['User']['firstname'])) {
					continue;
				}
				
				$user_option = $this->UserOption->find('first', array(
					'conditions' => array(
						'UserOption.user_id' => $user['User']['id'],
						'UserOption.name' => 'ssi.import.fail',
					),
					'recursive' => -1,
				));
				if ($user_option) {
					continue;
				}
				
				if ($country == 'US') {
					$values = array(
						$this->Ssi->sanitize($user['User']['firstname']),
						$this->Ssi->sanitize($user['User']['lastname']),
						'user+' . $user['User']['id'] . '@mintvine.com', // mask emails to partner
						SsiMappings::gender($user['QueryProfile']['gender']),
						date('d', strtotime($user['QueryProfile']['birthdate'])),
						date('m', strtotime($user['QueryProfile']['birthdate'])),
						date('Y', strtotime($user['QueryProfile']['birthdate'])),
						strtoupper(trim($user['QueryProfile']['postal_code'])),
						SsiMappings::race($user['QueryProfile']['ethnicity']),
						SsiMappings::ethnicity($user['QueryProfile']['ethnicity']),
						SsiMappings::education($user['QueryProfile']['education']),
						SsiMappings::employment($user['QueryProfile']['employment']),
						SsiMappings::occupation($user['QueryProfile']['industry']),
						SsiMappings::income($user['QueryProfile']['hhi'])
					);
				}
				elseif ($country == 'GB') {
					$values = array(
						$this->Ssi->sanitize($user['User']['firstname']),
						$this->Ssi->sanitize($user['User']['lastname']),
						'user+' . $user['User']['id'] . '@mintvine.com', // mask emails to partner
						SsiMappings::gender($user['QueryProfile']['gender']),
						date('d', strtotime($user['QueryProfile']['birthdate'])),
						date('m', strtotime($user['QueryProfile']['birthdate'])),
						date('Y', strtotime($user['QueryProfile']['birthdate'])),
						strtoupper(trim($user['QueryProfile']['postal_code'])),
					);
				}
				elseif ($country == 'CA') {
					$values = array(
						$this->Ssi->sanitize($user['User']['firstname']),
						$this->Ssi->sanitize($user['User']['lastname']),
						'user+' . $user['User']['id'] . '@mintvine.com', // mask emails to partner
						SsiMappings::gender($user['QueryProfile']['gender']),
						date('d', strtotime($user['QueryProfile']['birthdate'])),
						date('m', strtotime($user['QueryProfile']['birthdate'])),
						date('Y', strtotime($user['QueryProfile']['birthdate'])),
						strtoupper(trim($user['QueryProfile']['postal_code'])),
					);
				}
				
				$panelist = array(
					'sourceId' => $settings['ssi.source_id'],
					'country' => $country, 
					'language' => 'en',
					'features' => $features,
					'respondent' => array(
						'respondentID' => $user['User']['id'],
						'values' => $values
					)
				); 
				
				$http->configAuth('Basic', $this->ssi_username, $this->ssi_password);
				$results = $http->post(
					$this->api_url.'/partner/saas/importsvc/validate/single', 
					json_encode($panelist), 
					$this->ssi_options
				);				
			
				if ($results->code == 200) {
					$validate_mts = microtime(true);
					$this->lecho('Validated '.$user['User']['id'].' '.($validate_mts - $start_mts), 'ssi.users', $log_key);
					$http->configAuth('Basic', $this->ssi_username, $this->ssi_password);
					
					try {
						$panelist = array(
							'sourceId' => $settings['ssi.source_id'],
							'country' => $country, 
							'language' => 'en',
							'features' => $features,
							'respondents' => array(array(
								'respondentID' => $user['User']['id'],
								'values' => $values
							))
						); 
						$results = $http->post(
							$this->api_url.'/partner/saas/importsvc/submit', 
							json_encode($panelist), 
							$this->ssi_options
						);
						$response = json_decode($results->body);
						$added_mts = microtime(true);
						if (empty($response) && $results->code == 200) {
							$this->PartnerUser->getDataSource()->reconnect();
							$this->PartnerUser->create();
							$this->PartnerUser->save(array('PartnerUser' => array(
								'user_id' => $user['User']['id'],
								'partner' => 'ssi'
							)));
							$this->lecho('Added '.$user['User']['id'].' '.($added_mts - $validate_mts), 'ssi.users', $log_key);
						}
						else {
							$this->UserOption->create();
							$this->UserOption->save(array('UserOption' => array(
								'user_id' => $user['User']['id'],
								'name' => 'ssi.import.fail',
								'value' => date(DB_DATETIME)
							)));

							$added_mts = microtime(true);
							$this->lecho('Failed to add '.$user['User']['id'].' '.($added_mts - $validate_mts), 'ssi.users', $log_key);
							$this->lecho($results, 'ssi.users', $log_key);
						}
					} catch (Exception $e) { }
				}
				else {
					echo "Validation failed for ".$user['User']['id']."\n";
					print_r($results);
				}
			}
		}
	}
	
	function export_inactives() {
		$this->PartnerUser->bindModel(array('belongsTo' => array('User')));
		$parter_users = $this->PartnerUser->find('all', array(
			'conditions' => array(
				'OR' => array(
					'User.last_touched is null', 
					'User.last_touched <' => date(DB_DATETIME, strtotime('-3 months'))
				),
				'PartnerUser.partner' => 'ssi'
			),
			'fields' => array('User.id'),
		));

		$user_ids = Set::extract('/User/id', $parter_users);
		$file = new File(WWW_ROOT.'files/reports/ssi_inactives.csv', true, 0644);
		$file->write(implode("\n", $user_ids));
	}

	function test() {
		//http://ssi.hasoffers.com/offers/offers.json?api_key=AFFqGh1glqF8Yh1TF7EH6ZDumpJKA9
		// Specify API URL
		define('HASOFFERS_API_URL', 'http://api.hasoffers.com/Apiv3/json');

		// Specify method arguments
		$args = array(
			'NetworkId' => null,
			'Target' => 'Affiliate_AffiliateUser',
			'Method' => 'create',
			'api_key' => SSI_AFFILIATE_KEY,
			'data' => array(
				'email' => 'maxsolace@gmail.com',
				'first_name' => 'Max',
				'last_name' => 'Solace',
				'password' => 'max123',
				'password_confirmation' => 'max123',
				'status' => 'active',
				'title' => 'Developer'
			)
		);

		// Initialize cURL
		$curlHandle = curl_init();

		// Configure cURL request
		curl_setopt($curlHandle, CURLOPT_URL, HASOFFERS_API_URL);

		// Configure POST
		curl_setopt($curlHandle, CURLOPT_POST, 1);
		curl_setopt($curlHandle, CURLOPT_POSTFIELDS, http_build_query($args));

		// Make sure we can access the response when we execute the call
		curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);

		// Execute the API call
		$jsonEncodedApiResponse = curl_exec($curlHandle);

		// Ensure HTTP call was successful
		if ($jsonEncodedApiResponse === false) {
			throw new \RuntimeException(
			'API call failed with cURL error: ' . curl_error($curlHandle)
			);
		}

		// Clean up the resource now that we're done with cURL
		curl_close($curlHandle);

		// Decode the response from a JSON string to a PHP associative array
		$apiResponse = json_decode($jsonEncodedApiResponse, true);

		// Make sure we got back a well-formed JSON string and that there were no
		// errors when decoding it
		$jsonErrorCode = json_last_error();
		if ($jsonErrorCode !== JSON_ERROR_NONE) {
			throw new \RuntimeException(
			'API response not well-formed (json error code: ' . $jsonErrorCode . ')'
			);
		}

		// Print out the response details
		if ($apiResponse['response']['status'] === 1) {
			// No errors encountered
			echo 'API call successful';
			echo PHP_EOL;
			echo 'Response Data: ' . print_r($apiResponse['response']['data'], true);
			echo PHP_EOL;
		}
		else {
			// An error occurred
			echo 'API call failed (' . $apiResponse['response']['errorMessage'] . ')';
			echo PHP_EOL;
			echo 'Errors: ' . print_r($apiResponse['response']['errors'], true);
			echo PHP_EOL;
		}
	}
 	
	private function create_project($client, $group, $settings, $country, $ssi_project_type) {
		if ($ssi_project_type == 'SSI RTV') {
			$survey_name = 'TV Survey';
			$cpi = $settings['ssi.rtv.cpi'];
			$payout = $settings['ssi.rtv.payout']; 
			$loi = 8;
			$bid_ir = 33;
			$router = false;
		}
		else {
			$survey_name = 'Rewards Road. A MintVine Funnel Survey';
			$cpi = $settings['ssi.cpi'];
			$payout = $settings['ssi.payout']; 
			$loi = 10;
			$bid_ir = 30;
			$router = true;
		}
		$projectSource = $this->Project->getDataSource();
		$projectSource->begin();
		$this->Project->create();			
		$save = $this->Project->save(array('Project' => array(
			'client_id' => $client['Client']['id'],
			'group_id' => $group['Group']['id'],
			'status' => PROJECT_STATUS_OPEN,
			'bid_ir' => $bid_ir,
			'est_length' => $loi,
			'router' => $router,
			'singleuse' => false, // we check for ssi_links now for access
			'quota' => '10000', 
			'client_rate' => $cpi,
			'partner_rate' => round($payout / 100, 2),
			'prj_name' => $ssi_project_type.' ('. $country .')',
			'user_payout' => round($payout / 100, 2),
			'award' => $payout,
			'mobile' => true,
			'desktop' => true,
			'tablet' => true,
			'started' => date(DB_DATETIME),
			'active' => true,
			'dedupe' => false,
			'survey_name' => $survey_name,
			'country' => $country,
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
		
			if ($ssi_project_type == 'SSI RTV') {
				
				$answer = '[x] Did not watch TV yesterday'."\n"; 
				$answer.= '[x] Only watched TV that was recorded or aired before yesterday (on a DVR for example)'."\n";
				$answer.= '[x] Watched some TV between 1am – noon'."\n";
				$answer.= '[x] Watched some TV between noon – 5pm'."\n";
				$answer.= 'Watched some TV between 5pm – 1am'."\n";
				
				$this->Prescreener->create();
				$this->Prescreener->save(array('Prescreener' => array(
					'survey_id' => $project_id,
					'question' => 'What time did you watch TV yesterday?',
					'answers' => trim($answer)
				)));
				
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project_id,
					'prescreen' => true
				)), true, array('prescreen')); 
			}
			
			CakeLog::write('ssi.projects', 'Created #'.$project_id.' ('. $country .')'); 
			$this->out('Created #'.$project_id.' ('. $country .')'); 
		}
		else {
			$projectSource->commit();
		}
	}
	
	public function rtv_post() {
		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => array('hostname.api', 'hostname.redirect'),
			)
		));
		$http = new HttpSocket(array(
			'timeout' => 2,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$data = array(
			'requestHeader' => array(
				'contactMethodId' => 47,
				'projectId' => 391728,
				'mailBatchId' => 152790504,
			),
			'startUrlHead' => $settings['hostname.redirect'].'/test/?uid={{ID}}&respondentId=',
			'requestAttributeMap' => array(
				'project_subtype' => 3,
				'loi' => 4
			),
			'respondentList' => array(
				array(
					'respondentId' => 128,
					'startUrlId' => '128',
				),
			)
		);
		$result = $http->post($settings['hostname.api'].'/ssi/ping', $data); 
		print_r($result);
	}
	
	// depending on day of week, different prescreeners are required
	public function rtv_prescreeners() {
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'ssi'
			)
		));
		if (!$group) {
			$this->out('Failed: Group does not exist'); 
			return;
		}
		
		$project = $this->Project->find('first', array(
			'fields' => array('Project.id'),
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'Project.status' => PROJECT_STATUS_OPEN,
				'Project.prj_name' => 'SSI RTV (US)'
			),
			'recursive' => -1
		));
		if (!$project) {
			return;
		}
		
		$prescreener = $this->Prescreener->find('first', array(
			'conditions' => array(
				'Prescreener.survey_id' => $project['Project']['id']
			),
			'recursive' => -1
		));
		if (!$prescreener) {
			return false;
		}
		
		$day_of_week = date('D'); 
		
		if (in_array($day_of_week, array('Sun', 'Mon'))) {
			$answer = '[x] Did not watch TV yesterday'."\n"; 
			$answer.= '[x] Only watched TV that was recorded or aired before yesterday (on a DVR for example)'."\n";
			$answer.= '[x] Watched some TV between 1am – noon'."\n";
			$answer.= 'Watched some TV between noon – 5pm'."\n";
			$answer.= 'Watched some TV between 5pm – 1am'."\n";
		}
		else {
			$answer = '[x] Did not watch TV yesterday'."\n"; 
			$answer.= '[x] Only watched TV that was recorded or aired before yesterday (on a DVR for example)'."\n";
			$answer.= '[x] Watched some TV between 1am – noon'."\n";
			$answer.= '[x] Watched some TV between noon – 5pm'."\n";
			$answer.= 'Watched some TV between 5pm – 1am'."\n";
		}
		
		if ($answer != $prescreener['Prescreener']['answers']) {
			$this->Prescreener->create();
			$this->Prescreener->save(array('Prescreener' => array(
				'id' => $prescreener['Prescreener']['id'],
				'answers' => trim($answer)
			)), true, array('answer'));
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'prescreener.updated',
				'description' => 'Updated for day of week ('.date('D').')'
			)));
		}
	}
	
	// input a date to analyze
	public function analyze_usage() {
		if (!isset($this->args[0])) {
			$this->out('Please input a date in YYYY-MM-DD format');
			return false;
		}
		$ssi_links = $this->SsiLink->find('all', array(
			'fields' => array('SsiLink.id', 'SsiLink.created', 'SsiLink.user_id', 'SsiLink.used'),
			'conditions' => array(
				'SsiLink.created >=' => $this->args[0].' 00:00:00',
				'SsiLink.created <=' => $this->args[0].' 23:59:59',
			)
		));
		if (!$ssi_links) {
			$this->out('There are no links to analyze.');
			return false;
		}
		$total = count($ssi_links);
		$this->out('Analyzing '.$total.' links');
		$used = $unused = $could_have_used = 0;
		$user_ids = array();
		if ($ssi_links) {
			foreach ($ssi_links as $ssi_link) {
				if ($ssi_link['SsiLink']['used']) {
					$used++;
				}
				else {
					$unused++;
					$count = $this->UserRouterLog->find('count', array(
						'conditions' => array(
							'UserRouterLog.user_id' => $ssi_link['SsiLink']['user_id'],
							'UserRouterLog.parent_id' => '0',
							'UserRouterLog.created >=' => $this->args[0].' 00:00:00',
							'UserRouterLog.created <=' => $this->args[0].' 23:59:59'
						),
						'recursive' => -1
					));
					if ($count > 0) {
						$could_have_used++;
						$user_ids[] = $ssi_link['SsiLink']['user_id']; 
					}
				}
			}
		}
		$used_pct = round(($used / $total) * 100, 2); 
		$this->out('Used links: '.$used.' ('.$used_pct.'%)'); 
		$unused_pct = round(($unused / $total) * 100, 2); 
		$this->out('Unused links: '.$unused.' ('.$unused_pct.'%)'); 
		
		$could_have_used_pct = round(($could_have_used / $unused) * 100, 2); 
		$this->out('Could have used links: '.$could_have_used.' ('.$could_have_used_pct.'%)'); 
		
		$fp = fopen(WWW_ROOT.'files/ssi_links.csv', 'w');
		foreach ($user_ids as $user_id) {
			fputcsv($fp, array($user_id));
		}
		fclose($fp);
	}
	
	// input a date to analyze
	public function analyze_rtv_usage() {
		if (!isset($this->args[0])) {
			$this->out('Please input a date in YYYY-MM-DD format');
			return false;
		}
		$ssi_links = $this->SsiLink->find('all', array(
			'fields' => array('SsiLink.id', 'SsiLink.created', 'SsiLink.user_id', 'SsiLink.used'),
			'conditions' => array(
				'SsiLink.created >=' => $this->args[0].' 00:00:00',
				'SsiLink.created <=' => $this->args[0].' 23:59:59',
				'SsiLink.project_subtype' => array(3, 4, 'rtv.static') // RTV analysis
			)
		));
		if (!$ssi_links) {
			$this->out('There are no links to analyze.');
			return false;
		}
		$total = count($ssi_links);
		$this->out('Analyzing '.$total.' links');
		$used = $unused = $could_have_used = $active_within_3_days = 0;
		$user_ids = array();
		if ($ssi_links) {
			foreach ($ssi_links as $ssi_link) {
				if ($ssi_link['SsiLink']['used']) {
					$used++;
				}
				else {
					$unused++;
					$count = $this->UserRouterLog->find('count', array(
						'conditions' => array(
							'UserRouterLog.user_id' => $ssi_link['SsiLink']['user_id'],
							'UserRouterLog.parent_id' => '0',
							'UserRouterLog.created >=' => $this->args[0].' 00:00:00',
							'UserRouterLog.created <=' => $this->args[0].' 23:59:59'
						),
						'recursive' => -1
					));
					if ($count > 0) {
						$could_have_used++;
						$user_ids[] = $ssi_link['SsiLink']['user_id']; 
					}
					else {
						$user = $this->User->find('first', array(
							'fields' => array('User.last_touched'),
							'conditions' => array(
								'User.id' => $ssi_link['SsiLink']['user_id']
							),
							'recursive' => -1
						)); 
						if (strtotime('-3 days') <= strtotime($user['User']['last_touched'])) {
							$active_within_3_days++; 
						}
					}
				}
			}
		}
		$used_pct = round(($used / $total) * 100, 2); 
		$this->out('Used links: '.$used.' ('.$used_pct.'%)'); 
		$unused_pct = round(($unused / $total) * 100, 2); 
		$this->out('Unused links: '.$unused.' ('.$unused_pct.'%)'); 
		
		$active_pct = round(($active_within_3_days / $total) * 100, 2); 
		$this->out('Active within 3 days, no take: '.$active_within_3_days.' ('.$active_pct.'%)'); 
		
		$could_have_used_pct = round(($could_have_used / $unused) * 100, 2); 
		$this->out('Could have used links: '.$could_have_used.' ('.$could_have_used_pct.'%)'); 
	}
		
	public function export_logs() {
		if (!isset($this->args[0]) || !in_array($this->args[0], array('router', 'rtv', 'all'))) {
			$this->out('Please set an input variable of "router" or "rtv" or "all"');
			return false; 
		}
		if (!isset($this->args[1]) || !isset($this->args[2])) {
			$start_date = date(DB_DATETIME, strtotime('-1 month')).' 00:00:00';
			$end_date = date(DB_DATETIME); // today
			$date = date(DB_DATE, strtotime($end_date));
		}
		else {
			$start_date = $this->args[1].' 00:00:00';
			$date = $this->args[2]; 
			$end_date = $this->args[2].' 23:59:59';
		}
		
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'ssi'
			)
		)); 
		
		$conditions = array(
			'Project.group_id' => $group['Group']['id'],
			'OR' => array(
				// projects started before and ended after selected dates
				array(
					'Project.started <=' => $start_date,
					'Project.ended >=' => $start_date
				),
				// projects started and ended during the duration of the selected date
				array(
					'Project.started >=' => $start_date,
					'Project.ended <=' => $end_date
				),
				// projects started before the end date but ending much later
				array(
					'Project.started <=' => $end_date,
					'Project.ended >=' => $end_date
				),
				// projects that are still open
				array(
					'Project.started <=' => $end_date,
					'Project.ended is null'
				),
				// addressing https://basecamp.com/2045906/projects/1413421/todos/206702078
				array(
					'Project.ended LIKE' => $date.'%'
				) 
			)
		);
		if ($this->args[0] == 'rtv') {
			$conditions['Project.prj_name LIKE'] = '%RTV%'; 
		}
		elseif ($this->args[0] == 'router') {
			$conditions['Project.prj_name LIKE'] = '%Router%'; 
		}

		$this->Project->unbindModel(array(
			'hasMany' => array('SurveyPartner', 'ProjectOption', 'ProjectAdmin'),
		));
		$projects = $this->Project->find('list', array(
			'fields' => array('Project.id', 'Project.prj_name'),
			'conditions' => $conditions
		));
		$project_ids = array_keys($projects);
		$this->out('Total: '.count($project_ids).' projects');
		
		$survey_statuses = unserialize(SURVEY_STATUSES); 
		
		$filename = WWW_ROOT.'files/ssi_usage_data_'.$this->args[0].'.csv';
		$fp = fopen($filename, 'w');
		fputcsv($fp, array(
			'Project',
			'Panelist ID',
			'Click Time',
			'Link',
			'IP Address',
			'Source Data (Hash)',
			'Subtype',
			'SSI Project ID',
			'Result'
		)); 
		$i = $j = 0; 
		
		foreach ($project_ids as $project_id) {
			$j++;
			$this->out($j.'/'.count($project_ids).': #'.$project_id);
			
			$this->SurveyVisit->getDataSource()->reconnect();
			$survey_visits = $this->SurveyVisit->find('all', array(
				'fields' => array('*'),
				'conditions' => array(
					'SurveyVisit.type' => SURVEY_CLICK,
					'SurveyVisit.survey_id' => $project_id,
					'SurveyVisit.created >=' => $start_date,
					'SurveyVisit.created <=' => $end_date
				)
			));
			if (!$survey_visits) {
				continue;
			}
			$total = count($survey_visits); 
			$this->out('Processing '.$total); 
			$i = 0; 
			
			foreach ($survey_visits as $survey_visit) {
				$i++; 
				$this->out($i.' / '.$total. '('.round(($i / $total) * 100, 2).'%)');
				list($project_id, $user_id) = explode('-', $survey_visit['SurveyVisit']['partner_user_id']);
				$original_link = str_replace('&sourceData='.$survey_visit['SurveyVisit']['hash'], '', $survey_visit['SurveyVisit']['link']); 
				
				$ssi_link = $this->SsiLink->find('first', array(
					'conditions' => array(
						'SsiLink.url' => $original_link,
						'SsiLink.user_id' => $user_id,
						'SsiLink.used' => true
					)
				));
				$result = 'DROP'; 
				if ($survey_visit['SurveyVisit']['result'] > 1) {
					$result = $survey_statuses[$survey_visit['SurveyVisit']['result']]; 
				}
				fputcsv($fp, array(
					$projects[$project_id],
					$user_id, 
					$survey_visit['SurveyVisit']['created'],
					$survey_visit['SurveyVisit']['link'],
					$survey_visit['SurveyVisit']['ip'],
					$survey_visit['SurveyVisit']['hash'],
					$ssi_link ? $ssi_link['SsiLink']['project_subtype']: '',
					$ssi_link ? $ssi_link['SsiLink']['ssi_project_id']: '',
					$result
				));
			}
		}
		
		fclose($fp);
		$this->out('Finished writing '.$filename); 
	}
	
	
	// compare aggregate SSI projects
	public function aggregate_data() {

		if (!isset($this->args[0]) || !in_array($this->args[0], array('router', 'rtv', 'all', 'us', 'ca', 'gb'))) {
			$this->out('Please set an input variable of "router" or "rtv" or "all"');
			return false; 
		}
		if (!isset($this->args[1]) || !isset($this->args[2])) {
			$start_date = date(DB_DATETIME, strtotime('-1 month')).' 00:00:00';
			$end_date = date(DB_DATETIME); // today
			$date = date(DB_DATE, strtotime($end_date));
		}
		else {
			$start_date = $this->args[1].' 00:00:00';
			$date = $this->args[2]; 
			$end_date = $this->args[2].' 23:59:59';
		}
		
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'ssi'
			)
		)); 
		
		$conditions = array(
			'Project.group_id' => $group['Group']['id'],
			'OR' => array(
				// projects started before and ended after selected dates
				array(
					'Project.started <=' => $start_date,
					'Project.ended >=' => $start_date
				),
				// projects started and ended during the duration of the selected date
				array(
					'Project.started >=' => $start_date,
					'Project.ended <=' => $end_date
				),
				// projects started before the end date but ending much later
				array(
					'Project.started <=' => $end_date,
					'Project.ended >=' => $end_date
				),
				// projects that are still open
				array(
					'Project.started <=' => $end_date,
					'Project.ended is null'
				),
				// addressing https://basecamp.com/2045906/projects/1413421/todos/206702078
				array(
					'Project.ended LIKE' => $date.'%'
				) 
			)
		);
		if ($this->args[0] == 'rtv') {
			$conditions['Project.prj_name LIKE'] = '%RTV%'; 
		}
		elseif ($this->args[0] == 'router') {
			$conditions['Project.prj_name LIKE'] = '%Router%'; 
		}
		elseif ($this->args[0] == 'us') {
			$conditions['Project.prj_name LIKE'] = '%Router%'; 
			$conditions['Project.country'] = 'US';
		}
		elseif ($this->args[0] == 'ca') {
			$conditions['Project.prj_name LIKE'] = '%Router%'; 
			$conditions['Project.country'] = 'CA';
		}
		elseif ($this->args[0] == 'gb') {
			$conditions['Project.prj_name LIKE'] = '%Router%'; 
			$conditions['Project.country'] = 'GB';
		}

		$this->Project->unbindModel(array(
			'hasMany' => array('SurveyPartner', 'ProjectOption', 'ProjectAdmin'),
		));
		$projects = $this->Project->find('list', array(
			'fields' => array('Project.id', 'Project.prj_name'),
			'conditions' => $conditions
		));
		$total = count($projects); 
		
		$this->out('Found '.$total.' projects');
		// output total data analysis: 
		// sessions, completes, OQ, NQ
		$i = $clicks = $completes = $oqs = $nqs = 0; 
		
		foreach ($projects as $project_id => $project) {
			$i++;
			if (isset($this->args[3]) && $this->args[3] == 'cache') {
				$survey_visit_cache = $this->SurveyVisitCache->find('first', array(
					'conditions' => array(
						'SurveyVisitCache.survey_id' => $project_id
					)
				));
				$clicks = $clicks + $survey_visit_cache['SurveyVisitCache']['click']; 
				$completes = $completes + $survey_visit_cache['SurveyVisitCache']['complete']; 
				$nqs = $nqs + $survey_visit_cache['SurveyVisitCache']['nq']; 
				$oqs = $oqs + $survey_visit_cache['SurveyVisitCache']['overquota']; 	
			}
			else {
				$count = $this->SurveyVisit->find('count', array(
					'conditions' => array(
						'SurveyVisit.type' => SURVEY_CLICK,
						'SurveyVisit.survey_id' => $project_id,
						'SurveyVisit.created >=' => $start_date,
						'SurveyVisit.created <=' => $end_date
					)
				));
				$clicks = $clicks + $count; 			

				$count = $this->SurveyVisit->find('count', array(
					'conditions' => array(
						'SurveyVisit.type' => SURVEY_COMPLETED,
						'SurveyVisit.survey_id' => $project_id,
						'SurveyVisit.created >=' => $start_date,
						'SurveyVisit.created <=' => $end_date
					)
				));
				$completes = $completes + $count; 
			
				$count = $this->SurveyVisit->find('count', array(
					'conditions' => array(
						'SurveyVisit.type' => SURVEY_NQ,
						'SurveyVisit.survey_id' => $project_id,
						'SurveyVisit.created >=' => $start_date,
						'SurveyVisit.created <=' => $end_date
					)
				));
				$oqs = $oqs + $count; 

				$count = $this->SurveyVisit->find('count', array(
					'conditions' => array(
						'SurveyVisit.type' => SURVEY_OVERQUOTA,
						'SurveyVisit.survey_id' => $project_id,
						'SurveyVisit.created >=' => $start_date,
						'SurveyVisit.created <=' => $end_date
					)
				));
				$nqs = $nqs + $count; 	
			}
			$this->out('Analyzing '.$i.'/'.$total.' projects');
		}
		
		$this->out('-------------------------------------');
		$this->out('Results '.$start_date.' to '.$end_date); 
		$this->out('Clicks: '.$clicks);
		$this->out('Completes: '.$completes); 
		$this->out('NQ: '.$nqs); 
		$this->out('OQ: '.$oqs); 
		
	}
	
	// takes the file generated in the function above and does some analysis on it
	// you must pass the date of the file generation as a param
	public function analyze_user_ids() {
		if (!isset($this->args[0])) {
			$this->out('Please pass in the date of the usage file');
			return false; 
		}
		$filename = WWW_ROOT.'files/ssi_links.csv';
		if (!is_file($filename)) {
			$this->out('Please generate the links file by running ./cake ssi analyze_usage'); 
			return false; 
		}
		$csv_rows = Utils::csv_to_array($filename);
		$user_ids = array();
		foreach ($csv_rows as $csv_row) {
			$user_ids[] = $csv_row[0];
		}

		$unvisited_to_ssi = array(); // find the users who didn't even visit SSI links
		$visited_just_ssi = array(); // find the users who just visited the SSI link
		$mixed_visits = array(); // find the users who visited SSI then went to other projects after
		
		foreach ($user_ids as $user_id) {
			$count = $this->SsiLink->find('count', array(
				'conditions' => array(
					'SsiLink.user_id' => $user_id,
					'SsiLink.used' => true,
					'SsiLink.created >=' => $this->args[0].' 00:00:00',
					'SsiLink.created <=' => $this->args[0].' 23:59:59'
				)
			));
			if ($count == 0) {
				$unvisited_to_ssi[] = $user_id;
			}
			else {
				// see if user visited other projects
				$count = $this->Transaction->find('count', array(
					'conditions' => array(
						'Transaction.user_id' => $user_id,
						'Transaction.type_id' => TRANSACTION_SURVEY,
						'Transaction.created >=' => $this->args[0].' 00:00:00',
						'Transaction.created <=' => $this->args[0].' 23:59:59',
						'Transaction.deleted' => null,
					),
					'recursive' => -1
				));
				if ($count == 1) {
					$visited_just_ssi[] = $user_id;
				}
				else {
					$mixed_visits[] = $user_id;
				}
			}
		}
		$this->out('Did not visit SSI');
		$this->out(implode(', ', $unvisited_to_ssi)); 
		$this->out('----');
		$this->out('Visited just SSI');
		$this->out(implode(', ', $visited_just_ssi)); 
		$this->out('----');
		$this->out('Mixed visits');
		$this->out(implode(', ', $mixed_visits)); 
		$this->out('----');
		
		$this->out('Total count: '.count($user_ids));
		$this->out('Count unvisited: '.count($unvisited_to_ssi)); 
		$this->out('Count just SSI: '.count($visited_just_ssi)); 
		$this->out('Mixed visits: '.count($mixed_visits)); 
	}
	
	/* this will go in, look at all the past ssi invites, see how many were used, how many were sent to active users */
	public function post_invite_usage() {
		
	}
	
	public function output_link_invites() {
		$count = 0; 
		$last_id = 0; 
		$data = array();
		while (true) {
			$ssi_links = $this->SsiLink->find('all', array(
				'fields' => array('SsiLink.id' ,'SsiLink.created'),
				'conditions' => array(
					'SsiLink.id >' => $last_id
				),
				'limit' => '50000',
				'order' => 'SsiLink.id ASC'
			));
			if (!$ssi_links) {
				break;
			}
			$count = $count + count($ssi_links);
			$this->out('Count: '.$count);
			$this->out('Retrieved '.count($ssi_links));
			foreach ($ssi_links as $ssi_link) {
				$date = date(DB_DATE, strtotime($ssi_link['SsiLink']['created'])); 
				if (!isset($data[$date])) {
					$this->out('Added '.$date);
					$data[$date] = 0;
				}
				$data[$date]++;
				$last_id = $ssi_link['SsiLink']['id'];
			}
		}
		print_r($data);
	}

	public function export_active_panelists_with_no_invites() {
		if (!isset($this->args[0])) {
			$this->out('Please supply an argument of a date in YYYY-MM-DD format');
			return false;
		}
		$date = date(DB_DATE, strtotime($this->args[0]));
		$active_panelists = $this->UserRouterLog->find('all', array(
			'fields' => array('DISTINCT UserRouterLog.user_id'),
			'conditions' => array(
				'UserRouterLog.parent_id' => '0',
				'UserRouterLog.created >=' => $date.' 00:00:00',
				'UserRouterLog.created <=' => $date.' 23:59:59'
			),
			'recursive' => -1
		));
		$active_user_ids = Hash::extract($active_panelists, '{n}.UserRouterLog.user_id'); 
		

		$ssi_invites = $this->SsiInvite->find('all', array(
			'fields' => array('SsiInvite.id' , 'SsiInvite.user_id'),
			'conditions' => array(
				'SsiInvite.created >=' => $date.' 00:00:00',
				'SsiInvite.created <=' => $date.' 23:59:59'
			),
			'recursive' => -1
		));
		$invited_user_ids = Hash::extract($ssi_invites, '{n}.SsiInvite.user_id'); 
		$diff = array_diff($active_user_ids, $invited_user_ids);
		
		$this->out('Found '.count($active_user_ids).' active user ids');
		$this->out('Found '.count($invited_user_ids).' invited user ids');
		$this->out('Diff of '.count($diff).' panelists');
		
		// make sure they were exported
		foreach ($diff as $key => $user_id) {
			$partner_user = $this->PartnerUser->find('first', array(
				'conditions' => array(
					'PartnerUser.user_id' => $user_id,
					'PartnerUser.partner' => 'ssi'
				)
			));
			if (!$partner_user || $partner_user['PartnerUser']['created'] > $date) {
				unset($diff[$key]);
			}
		}
		$this->out('Diff of '.count($diff).' panelists (post partner export check)');
		
		$file = fopen(WWW_ROOT . 'files/ssi/export_active_panelists_with_no_invites.csv', "w");
		fwrite($file, implode("\n", array_values($diff)));
		fclose($file);
	}
	
	// Grabs invites done over past X days (default 1)
	public function fresh_invites() {
		$days = 1;
		if (isset($this->args[0]) && is_numeric($this->args[0])) {
			$days = $this->args[0];
		}

		$ssi_links = $this->SsiLink->find('all', array(
			'fields' => array('SsiLink.id' , 'SsiLink.user_id'),
			'conditions' => array(
				'SsiLink.created >' => date('Y-m-d', strtotime("-" . $days . " days"))
			),
			'recursive' => -1
		));

		$this->out("Total SSI links created in past " . $days . " day(s): " . count($ssi_links));

		$urls = $this->UserRouterLog->find('all', array(
			'fields' => array('DISTINCT UserRouterLog.user_id'),
			'conditions' => array(
				'UserRouterLog.parent_id' => '0',
				'UserRouterLog.created >' => date('Y-m-d', strtotime("-" . $days . " days"))
			),
			'recursive' => -1
		));

		$this->out("Total distinct users who entered router in past " . $days . " day(s): " . count($urls)) . "\n";

		$router_users = array();
		foreach ($urls as $url) {
			array_push($router_users, $url['UserRouterLog']['user_id']);
		}

		$fresh_links = 0;
		$ssi_link_users = array();
		foreach ($ssi_links as $link) {
			array_push($ssi_link_users, $link['SsiLink']['user_id']);
			if (in_array($link['SsiLink']['user_id'], $router_users)) {
				$fresh_links += 1;
			}
		}

		// Number of distinct users who entered router who received SSI links
		$users_with_links = 0;
		foreach ($router_users as $ru) {
			if (in_array($ru, $ssi_link_users)) {
				$users_with_links += 1;
			}
		}

		$this->out("Total SSI links created for users who entered router in past " . $days . " day(s): " . $fresh_links);
		$this->out("Ratio: " . $fresh_links . "/" . count($ssi_links) . " (" . number_format(($fresh_links/count($ssi_links))*100, 1) . "%)\n");
		$this->out("Total users who entered router in past  " . $days . " day(s) with at least 1 SSI link: " . $users_with_links);
		$this->out("Ratio: " . $users_with_links . "/" . count($router_users) . " (" . number_format(($users_with_links/count($router_users))*100, 1) . "%)");
	}
	
	public function export_all_active_and_exported_panelists_with_profiling() {
		if (!isset($this->args[0])) {
			$this->out('Please define a country');
			return false; 
		}
		$country = strtoupper($this->args[0]); 
		$rows = array();
		
		$this->User->unbindModel(array(
			'hasOne' => array('QueryProfile'),
			'belongsTo' => array('Referrer')
		));
		$this->User->bindModel(array('hasOne' => array(
			'PartnerUser' => array(
				'className' => 'PartnerUser',
				'foreignKey' => 'user_id',
				'conditions' => array(
					'PartnerUser.partner' => 'ssi'
				)
			),
			'QueryProfile'
		)));
		
		$active_and_exported_panelists = $this->User->find('all', array(
			'fields' => array('User.id', 'User.firstname', 'User.lastname', 'QueryProfile.gender', 'QueryProfile.birthdate', 'QueryProfile.postal_code', 'QueryProfile.ethnicity', 'QueryProfile.education', 'QueryProfile.employment', 'QueryProfile.industry', 'QueryProfile.hhi'),
			'conditions' => array(
				'QueryProfile.country' => strtoupper($country),
				'User.deleted_on' => null,
				'User.hellbanned' => false,
				'User.last_touched >=' => date(DB_DATETIME, strtotime('-3 months')),
				'PartnerUser.id IS NOT NULL'
			)
		));
		if (!$active_and_exported_panelists) {
			$this->out('No panelists found');
			return false;
		}
		foreach ($active_and_exported_panelists as $user) {
			if (empty($user['User']['lastname']) || empty($user['User']['firstname'])) {
				continue;
			}			
			if ($country == 'US') {
				$rows[] = array(
					$user['User']['id'],
					$this->Ssi->sanitize($user['User']['firstname']),
					$this->Ssi->sanitize($user['User']['lastname']),
					'user+' . $user['User']['id'] . '@mintvine.com', // mask emails to partner
					SsiMappings::gender($user['QueryProfile']['gender']),
					date('d', strtotime($user['QueryProfile']['birthdate'])),
					date('m', strtotime($user['QueryProfile']['birthdate'])),
					date('Y', strtotime($user['QueryProfile']['birthdate'])),
					strtoupper(trim($user['QueryProfile']['postal_code'])),
					SsiMappings::race($user['QueryProfile']['ethnicity']),
					SsiMappings::ethnicity($user['QueryProfile']['ethnicity']),
					SsiMappings::education($user['QueryProfile']['education']),
					SsiMappings::employment($user['QueryProfile']['employment']),
					SsiMappings::occupation($user['QueryProfile']['industry']),
					SsiMappings::income($user['QueryProfile']['hhi'])
				);
			}
			elseif ($country == 'GB') {
				$rows[] = array(
					$user['User']['id'],
					$this->Ssi->sanitize($user['User']['firstname']),
					$this->Ssi->sanitize($user['User']['lastname']),
					'user+' . $user['User']['id'] . '@mintvine.com', // mask emails to partner
					SsiMappings::gender($user['QueryProfile']['gender']),
					date('d', strtotime($user['QueryProfile']['birthdate'])),
					date('m', strtotime($user['QueryProfile']['birthdate'])),
					date('Y', strtotime($user['QueryProfile']['birthdate'])),
					strtoupper(trim($user['QueryProfile']['postal_code'])),
				);
			}
			elseif ($country == 'CA') {
				$rows[] = array(
					$user['User']['id'],
					$this->Ssi->sanitize($user['User']['firstname']),
					$this->Ssi->sanitize($user['User']['lastname']),
					'user+' . $user['User']['id'] . '@mintvine.com', // mask emails to partner
					SsiMappings::gender($user['QueryProfile']['gender']),
					date('d', strtotime($user['QueryProfile']['birthdate'])),
					date('m', strtotime($user['QueryProfile']['birthdate'])),
					date('Y', strtotime($user['QueryProfile']['birthdate'])),
					strtoupper(trim($user['QueryProfile']['postal_code'])),
				);
			}
		}
		
		$file = WWW_ROOT . 'files/ssi_export_panelists_'.strtolower($this->args[0]).'.csv';
		$fp = fopen($file, 'w');		

		if ($country == 'US') {
			fputcsv($fp, array(
				'respondent_id', 
				'first_name',
				'last_name',
				'email_address',
				'gender',
				'daydob',
				'monthdob',
				'yeardob',
				'postalcode',
				'raceUS',
				'ethnicityUS',
				'education',
				'employment',
				'occupation',
				'income'
			));
		}
		elseif ($country == 'GB') {
			fputcsv($fp, array(
				'respondent_id', 
				'first_name',
				'last_name',
				'email_address',
				'gender',
				'daydob',
				'monthdob',
				'yeardob',
				'postalcode',
			));
		}
		elseif ($country == 'CA') {
			fputcsv($fp, array(
				'respondent_id', 
				'first_name',
				'last_name',
				'email_address',
				'gender',
				'daydob',
				'monthdob',
				'yeardob',
				'postalcode',
			));
		}
		foreach ($rows as $csv) {
			fputcsv($fp, $csv);
		}

		fclose($fp);
		$this->out('Wrote '.$file);
	}
	
	public function export_all_active_and_exported_panelists() {
		$this->User->unbindModel(array(
			'hasOne' => array('QueryProfile'),
			'belongsTo' => array('Referrer')
		));
		$this->User->bindModel(array('hasOne' => array(
			'PartnerUser' => array(
				'className' => 'PartnerUser',
				'foreignKey' => 'user_id',
				'conditions' => array(
					'PartnerUser.partner' => 'ssi'
				)
			)
		)));
		$active_and_exported_panelists = $this->User->find('all', array(
			'fields' => array('User.id'),
			'conditions' => array(
				'User.deleted_on' => null,
				'User.hellbanned' => false,
				'User.last_touched >=' => date(DB_DATETIME, strtotime('-3 months')),
				'PartnerUser.id IS NOT NULL'
			),
			'order' => 'User.id ASC'
		));
		$active_and_exported_panelists = Hash::extract($active_and_exported_panelists, '{n}.User.id'); 
		
		$this->out('Found total active and exported panelists:'.count($active_and_exported_panelists));
		
		$file = fopen(WWW_ROOT . 'files/ssi/export_all_active_and_exported_panelists.csv', "w");
		fwrite($file, implode("\n", $active_and_exported_panelists));
		fclose($file);
	}
	
	public function export_ssi_usage() {
		$survey_visits = $this->SurveyVisit->find('all', array(
			'fields' => array('SurveyVisit.partner_user_id', 'SurveyVisit.link', 'SurveyVisit.created', 'SurveyVisit.hash', 'SurveyVisit.result'),
			'conditions' => array(
				'SurveyVisit.type' => SURVEY_CLICK,
				'SurveyVisit.survey_id' => $this->args[0],
				'SurveyVisit.created >=' => $this->args[1].' 00:00:00',
				'SurveyVisit.created <=' => $this->args[1].' 23:59:59'
			)
		));
		
		$file = WWW_ROOT . 'files/ssi_link_usage'.$this->args[1].'.csv';
		$fp = fopen($file, 'w');
		fputcsv($fp, array(
			'Panelist ID',
			'Result',
			'Link',
			'Invitation Received',
			'Panelist Used Timestamp',
			'Difference (minutes)',
			'Subtype'
		));
		
		foreach ($survey_visits as $survey_visit) {
			list($survey_id, $user_id, $trash1, $trash2) = explode('-', $survey_visit['SurveyVisit']['partner_user_id']);
			$link = str_replace('&sourceData='.$survey_visit['SurveyVisit']['hash'], '', $survey_visit['SurveyVisit']['link']); 
			$ssi_link = $this->SsiLink->find('first', array(
				'conditions' => array(
					'SsiLink.user_id' => $user_id,
					'SsiLink.url' => $link,
					'SsiLink.created <=' => $survey_visit['SurveyVisit']['created']
				),
				'order' => 'SsiLink.id DESC'
			));
			if (!$ssi_link) {
				$this->out('Could not locate '.$link.' for '.$user_id);
			}
			else {
				$result = '';
				if ($survey_visit['SurveyVisit']['result'] == SURVEY_COMPLETED) {
					$result = 'Complete';
				}
				if ($survey_visit['SurveyVisit']['result'] == SURVEY_NQ) {
					$result = 'NQ';
				}
				if ($survey_visit['SurveyVisit']['result'] == SURVEY_OVERQUOTA) {
					$result = 'OQ';
				}
				$diff = strtotime($survey_visit['SurveyVisit']['created']) - strtotime($ssi_link['SsiLink']['created']); 
				$diff_minutes = round($diff / 60);
				fputcsv($fp, array(
					$user_id, 
					$result,
					$link, 
					$ssi_link['SsiLink']['created'],
					$survey_visit['SurveyVisit']['created'],
					$diff_minutes,
					$ssi_link['SsiLink']['project_subtype']
				));
			}
		}
		fclose($fp);
		$this->out('Wrote '.$file);
	}
	
	public function send_invites() {
		$log_file = 'ssi.invites';
				
		// get valid time ranges
		$times = $this->Ssi->get_valid_time_ranges();
		
		// outside the time range for when these projects can be generated
		if (date(DB_DATETIME) < $times['start'] || date(DB_DATETIME) > $times['end']) {
			return false; 
		}
		
		$models_to_import = array('User', 'Setting', 'PartnerUser', 'SsiLink', 'Project', 'Group', 'NotificationLog', 'SurveyUser');
		foreach ($models_to_import as $model_to_import) {
			App::import('Model', $model_to_import);
			$this->$model_to_import = new $model_to_import;
		}
		
		$required_settings = array('ssi.active', 'ssi.rtv_router', 'ssi.source_id'); 
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => $required_settings,
				'Setting.deleted' => false
			)
		));
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'ssi'
			),
			'recursive' => -1
		));
		
		// missing required settings
		if (count($settings) < count($required_settings)) {
			return false;
		}
		
		if ($settings['ssi.active'] != 'true' || $settings['ssi.rtv_router'] != 'true') {
			return false;
		}
		
		$project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'Project.prj_name' => 'SSI RTV (US)',
				'Project.active' => true,
				'Project.status' => PROJECT_STATUS_OPEN
			)
		));
		if (!$project) {
			return false;
		}
		
		if (isset($this->args[0])) {
			$conditions = array(
				'User.id' => $this->args[0]
			);
		}
		else {
			$conditions = array(
				'User.last_touched >' => date(DB_DATETIME, strtotime('-2 days')),
				'QueryProfile.country' => 'US'
			);
		}
		
		$count = $this->User->find('count', array(
			'conditions' => $conditions
		));
		if ($count == 0) {
			$this->lecho('Active users not found.', $log_file);
			return;
		}
		
		$this->out('Found '.$count.' panelists');
		$this->User->bindModel(array(
			'hasOne' => array(
				'NotificationSchedule' => array(
					'fields' => array('NotificationSchedule.*'),
					'conditions' => array(
						'NotificationSchedule.type' => 'email'
					)
				),
				'PartnerUser' => array(
					'fields' => array('PartnerUser.id'),
					'conditions' => array(
						'PartnerUser.partner' => 'ssi'
					)
				)
			)
		), false);
		
		$i = $last_user_id = 0;
		while (true) {
			$conditions['User.id >'] = $last_user_id;
			$users = $this->User->find('all', array(
				'fields' => array('User.id', 'User.ref_id', 'User.email', 'QueryProfile.country'),
				'conditions' => $conditions,
				'contain' => array('QueryProfile', 'NotificationSchedule', 'PartnerUser'),
				'recursive' => -1,
				'limit' => 10000,
				'order' => 'User.id asc'
			));
			if (!$users) {
				break;
			}

			foreach ($users as $user) {
				$i++; 
				$this->lecho('Processing '. $i.'/'.$count, $log_file); 
				$last_user_id = $user['User']['id'];
				if (empty($user['PartnerUser']['id'])) {
					$this->lecho('[Skipped] User# '.$user['User']['id'].' not exported to SSI', $log_file);
					continue;
				}
				
				// Skip if we have already sent an email today to this panelist.
				$notification_log_count = $this->NotificationLog->find('count', array(
					'conditions' => array(
						'NotificationLog.project_id' => $project['Project']['id'],
						'NotificationLog.user_id' => $user['User']['id'],
						'NotificationLog.created >' => $times['start'],
						'NotificationLog.sent' => true
					)
				));
				if ($notification_log_count > 0) {
					$this->lecho('[Skipped] User# '.$user['User']['id'].' email already sent today.', $log_file);
					continue;
				}
				
				// if user has setup notification schedule, send invites as per the schedule
				if (!empty($user['NotificationSchedule']['id'])) {
					$hour = date('H'); 
					$date = date('Y-m-d'); 
					$date_from = $date.' '.$hour.':00:00'; 
					$date_to = date(DB_DATETIME);
					$notification_log_count = $this->NotificationLog->find('count', array(
						'conditions' => array(
							'NotificationLog.created >=' => $date_from,
							'NotificationLog.created <=' => $date_to,
							'NotificationLog.user_id' => $user['User']['id'],
							'NotificationLog.sent' => true
						)
					));
					$email_count = $user['NotificationSchedule'][$hour] - $notification_log_count;
					if ($email_count < 1) {
						$this->lecho('[Skipped] User# '.$user['User']['id'].' email quota full for this hour.', $log_file);
						continue;
					}
				}

				$ssi_link = $this->SsiLink->find('first', array(
					'fields' => array('SsiLink.id', 'SsiLink.url', 'SsiLink.created', 'SsiLink.used'),
					'conditions' => array(
						'SsiLink.user_id' => $user['User']['id'],
						'SsiLink.project_subtype' => array('3', '4', 'rtv.static'),
						'SsiLink.created >=' => $times['start'],
						'SsiLink.created <=' => $times['end']
					),
					'order' => 'SsiLink.id DESC'
				));
				if ($ssi_link) {
					if ($ssi_link['SsiLink']['used']) {
						continue; 
					}
				}
				else {
					$rtv_static_link = 'http://dkr1.ssisurveys.com/projects/project-offer-wall?source='.$settings['ssi.source_id'].'&sourcePID='.$user['User']['id'].'&offerType=1'; 

					// insert a SSI link
					$this->SsiLink->create();
					$this->SsiLink->save(array('SsiLink' => array(
						'ssi_project_id' => $project['Project']['id'],
						'url' => $rtv_static_link,
						'project_subtype' => 'rtv.static',
						'user_id' => $user['User']['id']
					)));
				}
				
				// check SurveyUser
				$count = $this->SurveyUser->find('count', array(
					'conditions' => array(
						'SurveyUser.survey_id' => $project['Project']['id'],
						'SurveyUser.user_id' => $user['User']['id'],
					),
					'recursive' => -1
				));
				if ($count == 0) {
					$this->SurveyUser->create();
					$this->SurveyUser->save(array('SurveyUser' => array(
						'user_id' => $user['User']['id'],
						'survey_id' => $project['Project']['id'],
						'notification' => true
					)));
				}

				$this->Notify->email($project, $user);
				$this->lecho('[Success] User# '.$user['User']['id'].' invite sent.', $log_file);
			}
		}
	}
}
