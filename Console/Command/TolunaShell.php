<?php

App::uses('Shell', 'Console');
App::uses('HttpSocket', 'Network/Http');
App::import('Lib', 'TolunaMappings');
App::import('Lib', 'Utilities');

class TolunaShell extends AppShell {

	public $uses = array('Group', 'PartnerUser', 'Project', 'ProjectLog', 'QueryProfile', 'Setting', 'SurveyUserVisit', 'User', 'TolunaInvite', 'SurveyUser', 'SurveyLink', 'NotificationLog');
	public $tasks = array('Notify');
		
	public function _get_guids() {
		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => array(
					'toluna.api_endpoint',
					'toluna.guid.ca',
					'toluna.guid.us',
					'toluna.guid.gb', 
					'toluna.active',
					'toluna.offerwall_endpoint'
				),
				'Setting.value !=' => '',
				'Setting.deleted' => false
			)
		));
		return $settings;
	}
	
	public function send_new_users() {
		$settings = $this->_get_guids();
		$countries = array();
		foreach ($settings as $key => $value) {
			list($toluna, $guid, $country) = explode('.', $key); 
			$countries[] = strtoupper($country); 
		}
		if (isset($this->args[0])) {
			$countries = array($this->args[0]); 
		}
		if (empty($countries)) {
			return; 
		}
		
		if ($settings['toluna.active'] != 'true') {
			return false;
		}
		
		$this->out('Toluna shell task');
		$this->hr();

		$this->HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$limit = 1000;
		$successful = $total = $skipped = $failed = 0;
		
		$conditions = array(
			'PartnerUser.last_exported is null',
			'User.active' => true,
			'User.deleted_on' => null,
			'User.hellbanned' => false,
			'QueryProfile.country' => $countries, 
			'User.last_touched >=' => date(DB_DATETIME, strtotime('-3 months'))
		);
		if (isset($this->args[1])) {
			$conditions['User.id'] = $this->args[1];
		}
		$this->User->bindModel(array('hasOne' => array(
			'PartnerUser' => array(
				'conditions' => array(
					'PartnerUser.partner' => 'toluna'
				)
			)
		)));
		while (true) {
			// Read users by page
			$users = $this->User->find('all', array(
				'conditions' => $conditions,
				'contain' => array('QueryProfile', 'PartnerUser'),
				'limit' => $limit,
				'fields' => array(
					'id',
					'firstname',
					'lastname',
					'email',
					'last_touched',
					'QueryProfile.birthdate',
					'QueryProfile.postal_code',
					'QueryProfile.country',
					'QueryProfile.gender',
					'QueryProfile.education',
					'QueryProfile.ethnicity',
					'QueryProfile.employment',
					'QueryProfile.hhi',
					'QueryProfile.relationship',
					'PartnerUser.id',
					'PartnerUser.last_exported'
				)
			));			
			if (!$users) {
				break;
			}

			$cutoff = strtotime('-3 months');
			foreach ($users as $user) {
				$active = strtotime($user['User']['last_touched']) < $cutoff ? 'FALSE': 'TRUE';
				$params = array(
					'PartnerGUID' => $settings['toluna.guid.'.strtolower($user['QueryProfile']['country'])],
					'MemberCode' => $user['User']['id'],
					'Active' => $active,
					'Modified' => 'false',
					'FirstName' => $user['User']['firstname'],
					'LastName' => $user['User']['lastname'],
					'Email' => 'user+'.$user['User']['id'].'@mintvine.com',
					'BirthDate' => TolunaMappings::birthdate($user['QueryProfile']['birthdate']),
					'PostalCode' => $user['QueryProfile']['postal_code'],
					'GenderID' => TolunaMappings::gender($user['QueryProfile']['gender']),
					'EducationID' => TolunaMappings::education($user['QueryProfile']['education'], $user['QueryProfile']['country']),
					'EthnicityID' => TolunaMappings::ethnicity($user['QueryProfile']['ethnicity'], $user['QueryProfile']['country']),
					'RaceID' => TolunaMappings::race($user['QueryProfile']['ethnicity'], $user['QueryProfile']['country']),
					'EmploymentID' => TolunaMappings::employment($user['QueryProfile']['employment'], $user['QueryProfile']['country']),
					'IncomeID' => TolunaMappings::income($user['QueryProfile']['hhi'], $user['QueryProfile']['country']),
					'MaritalStatusID' => TolunaMappings::marital_status($user['QueryProfile']['relationship'], $user['QueryProfile']['country'])
				);
				foreach ($params as $key => $param) {
					if (empty($param)) {
						unset($params[$key]);
					}
				}

				// Send user data to toluna
				$this->out('Updating ' . $user['User']['id']);
				$this->out('Last updated '.$user['PartnerUser']['last_exported'].' (Activity '.$user['User']['last_touched'].')'); 
				$this->out(print_r($params, true));
				$result = $this->HttpSocket->post($settings['toluna.api_endpoint'], $params);
				// Print response
				$this->out($result->code);

				// If successful, mark the user as saved
				// Toluna returns 201 when it has accepted the mintvine user
				if ($result->code == 201) {
					CakeLog::write('toluna.users', '['.$user['User']['id'].']'); 
					$this->out('Integration successful!', 2);
					$successful++;

					if (!empty($user['PartnerUser']['id'])) {
						$this->PartnerUser->getDatasource()->reconnect();
						$this->PartnerUser->create();
						$this->PartnerUser->save(array('PartnerUser' => array(
							'id' => $user['PartnerUser']['id'],							
							'last_exported' => date(DB_DATETIME)
						)), true, array('last_exported'));	
					}
					else {
						$this->PartnerUser->getDatasource()->reconnect();
						$this->PartnerUser->create();
						$this->PartnerUser->save(array('PartnerUser' => array(
							'last_exported' => date(DB_DATETIME),
							'user_id' => $user['User']['id'],
							'partner' => 'toluna'
						)));	
					}
				}
				elseif ($result->code == 400) {
					
					if (!empty($user['PartnerUser']['id'])) {
						$this->PartnerUser->getDatasource()->reconnect();
						$this->PartnerUser->create();
						$this->PartnerUser->save(array('PartnerUser' => array(
							'id' => $user['PartnerUser']['id'],							
							'last_exported' => date(DB_DATETIME)
						)), true, array('last_exported'));	
					}
					else {
						$this->PartnerUser->getDatasource()->reconnect();
						$this->PartnerUser->create();
						$this->PartnerUser->save(array('PartnerUser' => array(
							'last_exported' => date(DB_DATETIME),
							'user_id' => $user['User']['id'],
							'partner' => 'toluna'
						)));	
					}
					
					CakeLog::write('toluna.users', '400: '.$result->body); 
					print_r($result->body);
				}
				elseif ($result->code == 409) {
					$skipped++;
					CakeLog::write('toluna.users', '['.$user['User']['id'].'] Conflicted but saved '.print_r($result, true)); 
					
					if (!empty($user['PartnerUser']['id'])) {
						$this->PartnerUser->getDatasource()->reconnect();
						$this->PartnerUser->create();
						$this->PartnerUser->save(array('PartnerUser' => array(
							'id' => $user['PartnerUser']['id'],							
							'last_exported' => date(DB_DATETIME)
						)), true, array('last_exported'));	
					}
					else {
						$this->PartnerUser->getDatasource()->reconnect();
						$this->PartnerUser->create();
						$this->PartnerUser->save(array('PartnerUser' => array(
							'last_exported' => date(DB_DATETIME),
							'user_id' => $user['User']['id'],
							'partner' => 'toluna'
						)));	
					}
				}
				else {
					CakeLog::write('toluna.users', '['.$user['User']['id'].'] Failed '.print_r($result, true)); 
					$this->out('Integration failed!', 2);
					$failed++;
				}
				$total++;
			}
			if (isset($this->args[1])) {
				break;
			}
		}

		$this->out('Toluna integration task ended!');
		$this->hr();
		$this->out('Successful: ' . $successful);
		$this->out('Failed: ' . $failed);
		$this->out('Skipped: ' . $skipped);
		$this->out('Total: ' . $total);
	}
	
	public function get_user() {
		if (!isset($this->args[0])) {
			return false;
		}
		$settings = $this->_get_guids();
		$countries = array();
		foreach ($settings as $key => $value) {
			list($toluna, $guid, $country) = explode('.', $key); 
			$countries[] = strtoupper($country); 
		}
		
		$query_profile = $this->QueryProfile->find('first', array(
			'conditions' => array(
				'QueryProfile.user_id' => $this->args[0]
			),
			'fields' => array('country')
		));
		$guid = $settings['toluna.guid.'.strtolower($query_profile['QueryProfile']['country'])]; 
		$this->HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$result = $this->HttpSocket->get($settings['toluna.api_endpoint'], array(
			'memberCode' => $this->args[0],
			'partnerGuid' => $guid
		));
		print_r(json_decode($result['body']));
		
	}
	public function update_users() {
		ini_set('memory_limit', '2048M');
		$settings = $this->_get_guids();
		if ($settings['toluna.active'] != 'true') {
			return false;
		}
		
		$countries = array();
		foreach ($settings as $key => $value) {
			list($toluna, $guid, $country) = explode('.', $key); 
			$countries[] = strtoupper($country); 
		}		
		if (empty($countries)) {
			return; 
		}
		
		$this->out('Toluna shell task');
		$this->hr();

		$this->HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$pageSize = 1000;
		$page = 1;
		$total = 0;
		$successful = $skipped = $failed = 0;

		if (isset($this->args[0])) {
			if ($this->args[0] == 'all') {
				$conditions = array(
					'PartnerUser.last_exported is not null',
					'User.active' => true,
					'User.deleted_on' => null,
					'User.hellbanned' => false,
					'QueryProfile.country' => $countries, 
				); 
			}
			else {
				$conditions = array(
					'User.id' => $this->args[0]
				); 
			}
		}
		else {
			$conditions = array(
				'PartnerUser.last_exported is not null',
				'PartnerUser.last_exported <' => date(DB_DATETIME, strtotime('-1 week')),
				'QueryProfile.modified >=' => date(DB_DATETIME, strtotime('-1 week')),
				'User.active' => true,
				'User.deleted_on' => null,
				'User.hellbanned' => false,
				'QueryProfile.country' => $countries, 
				'User.last_touched is not null'
			); 
		}
		$this->User->bindModel(array('hasOne' => array(
			'PartnerUser' => array(
				'conditions' => array(
					'PartnerUser.partner' => 'toluna'
				)
			)
		)));
		while (true) {
			// Read users by page -  note we're not concerned with updating against email settings; we'll handle that ourselves
			$users = $this->User->find('all', array(
				'conditions' => $conditions,
				'contain' => array('QueryProfile', 'PartnerUser'),
				'order' => 'User.id ASC',
				'limit' => $pageSize,
				'page' => $page,
				'fields' => array(
					'id',					
					'lastname',
					'email',
					'last_touched',
					'QueryProfile.birthdate',
					'QueryProfile.postal_code',
					'QueryProfile.country',
					'QueryProfile.gender',
					'QueryProfile.education',
					'QueryProfile.ethnicity',
					'QueryProfile.employment',
					'QueryProfile.hhi',
					'QueryProfile.relationship',
					'PartnerUser.id',
					'PartnerUser.last_exported',
				),
				'limit' => $pageSize
			));			
			$cutoff = strtotime('-3 months');
			foreach ($users as $user) {
				print_r($user); 
				$active = strtotime($user['User']['last_touched']) < $cutoff ? 'FALSE': 'TRUE';
				$params = array(
					'PartnerGUID' => $settings['toluna.guid.'.strtolower($user['QueryProfile']['country'])],
					'MemberCode' => $user['User']['id'],
					'Active' => $active,
					'Email' => 'user+'.$user['User']['id'].'@mintvine.com',
					'BirthDate' => TolunaMappings::birthdate($user['QueryProfile']['birthdate']),
					'PostalCode' => $user['QueryProfile']['postal_code'],
					'GenderID' => TolunaMappings::gender($user['QueryProfile']['gender']),
					'EducationID' => TolunaMappings::education($user['QueryProfile']['education'], $user['QueryProfile']['country']),
					'EthnicityID' => TolunaMappings::ethnicity($user['QueryProfile']['ethnicity'], $user['QueryProfile']['country']),
					'RaceID' => TolunaMappings::race($user['QueryProfile']['ethnicity'], $user['QueryProfile']['country']),
					'EmploymentID' => TolunaMappings::employment($user['QueryProfile']['employment'], $user['QueryProfile']['country']),
					'IncomeID' => TolunaMappings::income($user['QueryProfile']['hhi'], $user['QueryProfile']['country']),
					'MaritalStatusID' => TolunaMappings::marital_status($user['QueryProfile']['relationship'], $user['QueryProfile']['country'])
				);
				foreach ($params as $key => $param) {
					if (empty($param)) {
						unset($params[$key]);
					}
				}

				// Send user data to toluna
				$this->out('Integrating ' . $user['User']['id']);
				$this->out(print_r($params, true));
				$result = $this->HttpSocket->put($settings['toluna.api_endpoint'], $params);
				
				// Print response
				$this->out($result->code);
				$this->out('-----------------');

				// If successful, mark the user as saved
				// Toluna returns 201 when it has accepted the mintvine user
				if ($result->code == 200) {
					CakeLog::write('toluna.users', '['.$user['User']['id'].'] Updated'); 
					$this->out('Integration successful!', 2);
					$successful++;					
					if (!empty($user['PartnerUser']['id'])) {
						$this->PartnerUser->getDatasource()->reconnect();
						$this->PartnerUser->create();
						$this->PartnerUser->save(array('PartnerUser' => array(
							'id' => $user['PartnerUser']['id'],							
							'last_exported' => date(DB_DATETIME)
						)), true, array('last_exported'));	
					}
					else {
						$this->PartnerUser->getDatasource()->reconnect();
						$this->PartnerUser->create();
						$this->PartnerUser->save(array('PartnerUser' => array(
							'last_exported' => date(DB_DATETIME),
							'user_id' => $user['User']['id'],
							'partner' => 'toluna'
						)));	
					}
									
				}
				elseif ($result->code == 404) {
					CakeLog::write('toluna.users', '['.$user['User']['id'].'] Updated failed 404 '.print_r($result, true)); 
					if (!empty($user['PartnerUser']['id'])) {
						$this->PartnerUser->getDatasource()->reconnect();
						$this->PartnerUser->create();
						$this->PartnerUser->save(array('PartnerUser' => array(
							'id' => $user['PartnerUser']['id'],							
							'last_exported' => null
						)), true, array('last_exported'));	
					}
					else {
						$this->PartnerUser->getDatasource()->reconnect();
						$this->PartnerUser->create();
						$this->PartnerUser->save(array('PartnerUser' => array(
							'last_exported' => null,
							'partner' => 'toluna',
							'user_id' => $user['User']['id']
						)));	
					}
				}
				else {
					if (!empty($user['PartnerUser']['id'])) {
						$this->PartnerUser->getDatasource()->reconnect();
						$this->PartnerUser->create();
						$this->PartnerUser->save(array('PartnerUser' => array(
							'id' => $user['PartnerUser']['id'],							
							'last_exported' => date(DB_DATETIME)
						)), true, array('last_exported'));	
					}
					else {
						$this->PartnerUser->getDatasource()->reconnect();
						$this->PartnerUser->create();
						$this->PartnerUser->save(array('PartnerUser' => array(
							'last_exported' => date(DB_DATETIME),
							'partner' => 'toluna',
							'user_id' => $user['User']['id']
						)));	
					}
					
					print_r($result);
					CakeLog::write('toluna.users', '['.$user['User']['id'].'] Updated failed '.print_r($result, true)); 
					$this->out('Integration failed!', 2);
					$failed++;
				}
				$total++;
			}

			// prepare to read next page
			$page++;

			// Break if this is the last page
			if (count($users) < $pageSize) {
				break;
			}
		}

		$this->out('Toluna integration task ended!');
		$this->hr();
		$this->out('Successful: ' . $successful);
		$this->out('Failed: ' . $failed);
		$this->out('Skipped: ' . $skipped);
		$this->out('Total: ' . $total);
	}
	
	// args: user
	public function offerwall() {
		if (!isset($this->args[0])) {
			return false;
		}
		$user = $this->User->find('first', array(
			'conditions' => array(
				'User.id' => $this->args[0]
			)
		));
		$settings = $this->_get_guids();
		$this->HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$params = array(
			'partnerGuid' => $settings['toluna.guid.'.strtolower($user['QueryProfile']['country'])],
			'memberCode' => $this->args[0],
			'numberOfSurveys' => 5,
			'mobileCompatible' => 'false'
		);
		$result = $this->HttpSocket->get($settings['toluna.offerwall_endpoint'], $params);
		if ($result->code == '200') {
			$body = json_decode($result['body'], false);
			print_r($body);
		}
	}

	// args[0]: path of csv file relative webroot/files directory (optional). If not set, defaults to "toluna_users.csv"
	public function export_users() {
		ini_set('memory_limit', '1024M');

		$this->out('Export toluna users');
		$this->hr();

		$fileName = "toluna_users.csv";
		if (isset($this->args[0]) && !empty($this->args[0])) {
			$fileName = $this->args[0];
		}
		$path = APP . WEBROOT_DIR . '/files/' . $fileName;

		$file = fopen($path, 'w');

		$delimiter = '|';
		$pageSize = 10000;
		$page = 1;
		$total = 0;

		// Write header
		$header = array(
			'MEMBERCODE',
			'COUNTRYID',
			'LANGUAGEID',
			'BIRTHDATE',
			'GENDERID',
			'FIRSTNAME',
			'LASTNAME',
			'ADDRESS',
			'CITY',
			'STATE',
			'ZIPCODE',
			'EDUCATIONID',
			'INCOMEID',
			'EMPLOYMENTID',
			'ETHNICITYID',
			'RACEID',
			'MARITALSTATUSID',
			'EMAIL'
		);
		fputcsv($file, $header, $delimiter);

		while (1) {
			// Read users by page
			$users = $this->User->find('all', array(
				'conditions' => array(
					'QueryProfile.country' => 'US', // Find only US users,
					'User.active' => true,
					'User.deleted_on' => null,
					'User.hellbanned' => false
				),
				'contain' => array('QueryProfile'),
				'limit' => $pageSize,
				'page' => $page,
				'fields' => array(
					'id',
					'firstname',
					'lastname',
					'email',
					'QueryProfile.birthdate',
					'QueryProfile.postal_code',
					'QueryProfile.country',
					'QueryProfile.gender',
					'QueryProfile.education',
					'QueryProfile.ethnicity',
					'QueryProfile.employment',
					'QueryProfile.hhi',
					'QueryProfile.relationship'
				)
			));

			// Export users
			foreach ($users as $user) {
				/*
				// Check user's name is non-Ascii
				$pattern = '/[^\x20-\x7f]/';
				if (preg_match($pattern, $user['User']['firstname']) || preg_match($pattern, $user['User']['lastname'])) {
					// Name is non-ascii
				}
				*/

				$params = array(
					$user['User']['id'], 										// MEMBERCODE
					TolunaMappings::language('en'), 							// LANGUAGEID
					TolunaMappings::birthdate($user['QueryProfile']['birthdate']),	// BIRTHDATE
					TolunaMappings::gender($user['QueryProfile']['gender']),	// GENDERID
					$user['User']['firstname'],									// FIRSTNAME
					$user['User']['lastname'],									// LASTNAME
					'',															// ADDRESS
					'',															// CITY
					'',															// STATE
					$user['QueryProfile']['postal_code'],						// ZIPCODE
					TolunaMappings::education($user['QueryProfile']['education']),		// EDUCATIONID
					TolunaMappings::income($user['QueryProfile']['hhi']),		// INCOMEID
					TolunaMappings::employment($user['QueryProfile']['employment']),	// EMPLOYMENTID
					TolunaMappings::ethnicity($user['QueryProfile']['ethnicity']),		// ETHNICITYID
					'', 														// RACEID
					TolunaMappings::marital_status($user['QueryProfile']['relationship']),	// MARITALSTATUSID
					$user['User']['email']										// EMAIL
				);

				fputcsv($file, $params, $delimiter);

				$total++;
			}


			// Send user data to toluna
			$this->out('Exported ' . $total . ' users');

			// prepare to read next page
			$page++;

			// Break if this is the last page
			if (count($users) < $pageSize) {
				break;
			}
		}

		fclose($file);

		$this->out('Users have been exported to ' . $path);
		$this->hr();
		$this->out('Total: ' . $total);
	}
	
	public function send_email_invites() {
		$settings = $this->_get_guids();
		$countries = array();
		foreach ($settings as $key => $value) {
			if (strpos($key, 'guid') === false) {
				continue;
			}
			list($toluna, $guid, $country) = explode('.', $key); 
			$countries[] = strtoupper($country); 
		}
		
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'toluna'
			)
		)); 
		
		$user_ids = $this->User->find('list', array(
			'fields' => array('User.id', 'User.id'),
			'conditions' => array(
				'User.last_touched >' => date(DB_DATETIME, strtotime('-2 days'))
			),
			'recursive' => -1
		)); 
		$partner_users = $this->PartnerUser->find('all', array(
			'fields' => array('PartnerUser.user_id', 'PartnerUser.last_exported'),
			'conditions' => array(
				'PartnerUser.partner' => 'toluna',
				'PartnerUser.user_id' => $user_ids
			),
		));
		$total = count($partner_users);
		$this->out('Processing '.$total.' Toluna panelists');
		
		$filename = WWW_ROOT . 'files/toluna_invites.csv';
		$fp = fopen($filename, 'w');
		fputcsv($fp, array(
			'Toluna Survey ID',
			'LOI',
			'MV Survey ID',
			'User ID',
			'MV Status', 
			'MV Active',
			'Clicks',
			'Completes',
			'Taken',
			'Close Reason'
		));
		
		$i = 0; 
		foreach ($partner_users as $partner_user) {	
			$i++; 
			$this->out($i.'/'.$total); 
			$query_profile = $this->QueryProfile->find('first', array(
				'fields' => array('QueryProfile.country'),
				'conditions' => array(
					'QueryProfile.user_id' => $partner_user['PartnerUser']['user_id']
				)
			));
			$params = array(
				'partnerGuid' => $settings['toluna.guid.'.strtolower($query_profile['QueryProfile']['country'])],
				'memberCode' => $partner_user['PartnerUser']['user_id'],
				'numberOfSurveys' => 10,
				'mobileCompatible' => 'false'
			);
			
			$guid = $settings['toluna.guid.'.strtolower($query_profile['QueryProfile']['country'])]; 
			$this->HttpSocket = new HttpSocket(array(
				'timeout' => 15,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$result = $this->HttpSocket->get($settings['toluna.offerwall_endpoint'], $params);
			$surveys = json_decode($result['body'], true);
			if (!is_array($surveys) || empty($surveys)) {
				continue;
			}
			foreach ($surveys as $survey) {
				$this->Project->unbindModel(array('hasMany' => array('SurveyPartner', 'ProjectOption', 'ProjectAdmin')));
				$this->Project->unbindModel(array('belongsTo' => array('Client', 'Group')));
				$project = $this->Project->find('first', array(
					'fields' => array('Project.id', 'Project.status', 'Project.active', 'SurveyVisitCache.click', 'SurveyVisitCache.complete'),
					'conditions' => array(
						'Project.group_id' => $group['Group']['id'],
						'Project.mask' => $survey['SurveyID']
					)
				)); 
				$close_reason = null; 
				if ($project['Project']['status'] == PROJECT_STATUS_CLOSED) {
					$project_log = $this->ProjectLog->find('first', array(
						'fields' => array('ProjectLog.description'),
						'conditions' => array(
							'ProjectLog.project_id' => $project['Project']['id'],
							'ProjectLog.type LIKE' => 'status.closed%'
						),
						'order' => 'ProjectLog.id DESC'
					));
					if ($project_log) {
						$close_reason = $project_log['ProjectLog']['description']; 
					}
				}
				$count = $this->SurveyUserVisit->find('count', array(
					'conditions' => array(
						'SurveyUserVisit.user_id' => $partner_user['PartnerUser']['user_id'],
						'SurveyUserVisit.survey_id' => $project['Project']['id']
					),
					'recursive' => -1
				)); 
				fputcsv($fp, array(
					$survey['SurveyID'],
					$survey['Duration'],
					$project['Project']['id'],
					$partner_user['PartnerUser']['user_id'],
					$project['Project']['status'],
					$project['Project']['active'],
					$project['SurveyVisitCache']['click'],
					$project['SurveyVisitCache']['complete'],
					$close_reason
				));
			}
		}
		fclose($fp);
		$this->out($filename); 
	}
	
	public function send_invites() {
		$log_file = 'toluna.invites';
		$settings = $this->_get_guids();
		if ($settings['toluna.active'] != 'true') {
			$this->lecho('Toluna is not active', $log_file);
			return false;
		}
		
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id', 'Group.max_loi_minutes'),
			'conditions' => array(
				'Group.key' => 'toluna'
			)
		));
		if (isset($this->args[0])) {
			$conditions = array(
				'User.id' => $this->args[0]
			);
		}
		else {
			$conditions = array(
				'User.last_touched <' => date(DB_DATETIME, strtotime('-1 days')),
				'User.last_touched >' => date(DB_DATETIME, strtotime('-2 days'))
			);
		}
		
		$total_count = $this->User->find('count', array(
			'conditions' => $conditions
		));
		if ($total_count == 0) {
			$this->lecho('Active users not found.', $log_file);
			return;
		}
		
		$this->out('Found '.$total_count.' panelists');
		$high_epc_projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'Project.status' => PROJECT_STATUS_OPEN,
				'Project.active' => true,
				'Project.ended is null'
			),
			'contain' => array(
				'SurveyVisitCache' => array(
					'fields' => array(
						'SurveyVisitCache.complete', 'SurveyVisitCache.epc'
					)
				)
			),
			'recursive' => -1
		));
		if (!$high_epc_projects) {
			$this->lecho('Active toluna projects not found.', $log_file);
			return;
		}
		
		foreach ($high_epc_projects as $key => $high_epc_project) {
			unset($high_epc_projects[$key]);
			if ($high_epc_project['SurveyVisitCache']['complete'] < 1) {
				continue;
			}

			$epc = 0;
			if (!empty($high_epc_project['SurveyVisitCache']['epc'])) {
				$epc = $high_epc_project['SurveyVisitCache']['epc'];
			}
			elseif (!empty($high_epc_project['Project']['epc'])) {
				$epc = $high_epc_project['Project']['epc'];
			}

			// Skip if epc is less then 25 cents
			if ($epc < 25) {
				continue;
			}
			
			$mask = $high_epc_project['Project']['mask'];
			$high_epc_projects[$mask] = $high_epc_project;
		}
		
		if (empty($high_epc_projects)) {
			$this->lecho('No open, high epc toluna project found.', $log_file);
			return;
		}
		
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
						'PartnerUser.partner' => 'toluna'
					)
				)
			)
		), false);
		
		$this->HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
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
				$this->lecho('Processing '. $i.'/'.$total_count, $log_file); 
				$last_user_id = $user['User']['id'];
				if (empty($user['PartnerUser']['id'])) {
					$this->lecho('[Skipped] User# '.$user['User']['id'].' not exported to Toluna', $log_file);
					continue;
				}
				
				$invites_sent = 0;
				$email_count = 5; // default max invites that can be sent
				
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
				
				$params = array(
					'partnerGuid' => $settings['toluna.guid.'.strtolower($user['QueryProfile']['country'])],
					'memberCode' => $user['User']['id'],
					'numberOfSurveys' => 10,
					'mobileCompatible' => 'false'
				);
				try {
					$results = $this->HttpSocket->get($settings['toluna.offerwall_endpoint'], $params);
					if ($results->code != '200') {
						$this->lecho('[Skipped] User# '.$user['User']['id'].' API error.', $log_file);
						continue;
					}
				}
				catch (SocketException $e) {
					continue;
				}
				catch (\HttpException $ex) {
					continue;
				}
				catch (\Exception $ex) {
					continue;
				}

				$results = json_decode($results['body'], true);
				if (empty($results)) {
					continue;
				}
				
				$api_invites = array();
				foreach ($results as $result) {
					if (empty($result['WaveId'])) {
						$toluna_id = $result['SurveyID'];
					}
					else {
						$toluna_id = $result['SurveyID'].'-'.$result['WaveId'];
					}

					$api_invites[$toluna_id] = $result;
				}
				
				if (empty($api_invites)) {
					$this->lecho('[Skipped] User# '.$user['User']['id'].' Toluna API invites not found.', $log_file);
					continue;
				}
				
				$this->Project->getDataSource()->reconnect();	
				foreach ($api_invites as $toluna_id => $api_invite) {
					if ($email_count < 1) {
						break;
					}
					
					if (!isset($high_epc_projects[$toluna_id])) {
						continue;
					}
					
					$project = $high_epc_projects[$toluna_id];
					$survey_user_visit = $this->SurveyUserVisit->find('first', array(
						'fields' => array('SurveyUserVisit.id'),
						'conditions' => array(
							'SurveyUserVisit.survey_id' => $project['Project']['id'],
							'SurveyUserVisit.user_id' => $user['User']['id'],
						),
						'recursive' => -1
					));
					if ($survey_user_visit) {
						continue;
					}

					$toluna_invite = $this->TolunaInvite->find('first', array(
						'conditions' => array(
							'TolunaInvite.user_id' => $user['User']['id'],
							'TolunaInvite.toluna_survey_id' => $api_invite['SurveyID'],
							'TolunaInvite.wave_id' => isset($api_invite['WaveId']) ? $api_invite['WaveId']: null
						)
					));
					if ($toluna_invite) {
						
						// if the invite has already been used, skip this
						if (!empty($toluna_invite['TolunaInvite']['used'])) {
							continue;
						}
						
						$this->TolunaInvite->create();
						$this->TolunaInvite->save(array('TolunaInvite' => array(
							'id' => $toluna_invite['TolunaInvite']['id'],
							'name' => $api_invite['Name'],
							'description' => $api_invite['Description'],
							'duration' => $api_invite['Duration'],
							'cpi_cents' => (float) $api_invite['PartnerAmount'] * 100,
							'url' => $api_invite['URL'],
							'seen' => date(DB_DATETIME),
							'used' => date(DB_DATETIME),
						)), true, array('name', 'description', 'duration', 'cpi_cents', 'url', 'seen', 'used'));
					}
					else {
						$this->TolunaInvite->create();
						$this->TolunaInvite->save(array('TolunaInvite' => array(
							'created_date' => date(DB_DATE),
							'toluna_survey_id' => $api_invite['SurveyID'],
							'wave_id' => isset($api_invite['WaveId']) ? $api_invite['WaveId']: null,
							'user_id' => $user['User']['id'],
							'project_id' => '0',
							'name' => $api_invite['Name'],
							'description' => $api_invite['Description'],
							'seen' => date(DB_DATETIME),
							'used' => date(DB_DATETIME),
							'duration' => $api_invite['Duration'],
							'cpi_cents' => (float) $api_invite['PartnerAmount'] * 100,
							'url' => $api_invite['URL']
						)));
					}

					// check survey link
					$survey_link = $this->SurveyLink->find('first', array(
						'recursive' => -1,
						'conditions' => array(
							'SurveyLink.user_id' => $user['User']['id'],
							'SurveyLink.survey_id' => $project['Project']['id']
						),
						'fields' => array('SurveyLink.id', 'SurveyLink.link')
					));
					$create_link = false;
					if ($survey_link) {
						if ($survey_link['SurveyLink']['link'] != $api_invite['URL']) {
							$this->SurveyLink->delete($survey_link['SurveyLink']['id']);
							$create_link = true;
						}
					}
					else {
						$create_link = true;
					}
			
					if ($create_link) {
						$this->SurveyLink->create();
						$this->SurveyLink->save(array('SurveyLink' => array(
							'survey_id' => $project['Project']['id'],
							'link' => $api_invite['URL'].'&uid={{ID}}',
							'user_id' => $user['User']['id'],
							'active' => true
						)));
					}
					
					// check SurveyUser
					$survey_user = $this->SurveyUser->find('first', array(
						'fields' => array('SurveyUser.id', 'SurveyUser.notification'),
						'conditions' => array(
							'SurveyUser.survey_id' => $project['Project']['id'],
							'SurveyUser.user_id' => $user['User']['id'],
						),
						'recursive' => -1
					));
					if (!$survey_user) {
						$this->SurveyUser->create();
						$this->SurveyUser->save(array('SurveyUser' => array(
							'user_id' => $user['User']['id'],
							'survey_id' => $project['Project']['id'],
							'notification' => true
						)));
					}

					$this->Notify->email($project, $user);
					$email_count = $email_count - 1;
					$invites_sent++;
				}
				
				if ($invites_sent > 0) {
					$this->lecho('[Success] User# '.$user['User']['id'].' '. $invites_sent. ' invite(s) sent.', $log_file);
				}
				else {
					$this->lecho('[Failure] User# '.$user['User']['id'].' no invite sent.', $log_file);
				}
			}
		}
		
		$this->lecho('Finished', $log_file);
	}
}
