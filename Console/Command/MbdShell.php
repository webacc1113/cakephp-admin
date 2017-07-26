<?php
App::import('Lib', 'Utilities');
App::import('Lib', 'MbdMappings');
App::uses('HttpSocket', 'Network/Http');
App::uses('CakeEmail', 'Network/Email');
CakePlugin::load('Mailgun');

class MbdShell extends AppShell {
	public $uses = array('Setting', 'User', 'Nonce', 'MailQueue', 'PartnerUser', 'Group', 'MbdStale', 'Client', 'Project', 'Partner', 'UserOption', 'ProjectLog', 'Transaction');
	public $settings = array();
	private $options = array('header' => array(
		'Accept' => 'application/json',
		'Content-Type' => 'application/json; charset=UTF-8'
	));
	
	
	private function get_settings() {
		$this->settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'OR' => array(
					'Setting.name' => array('hostname.mbd', 'hostname.www'),
					'Setting.name LIKE' => 'mbd.%',
				),
				'Setting.deleted' => false
			)
		));
		
		if (!isset($this->settings['mbd.active']) || $this->settings['mbd.active'] != 'true') {
			$this->out('MBD is not active from settings.');
			return false;
		}
		
		return true;
	}
	
	// add/update panelist demographics to MBD
	// arg: MV user_id [optional], if provided, only this user demographics will be updated. 
	public function process() {
		if (!$this->get_settings()) {
			return;
		}
		
		$http = new HttpSocket(array(
			'timeout' => 240,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$stale_url = $this->settings['hostname.mbd'].'/panelists/stale?X-ApiKey='.$this->settings['mbd.api_key'];
		
		// Add demographics for a single user
		if (isset($this->args[0]) && !empty($this->args[0])) {
			$this->out('Processing user #'.$this->args[0]); 
			
			// Check if the user is exported already?
			$partner_user = $this->PartnerUser->find('first', array(
				'conditions' => array(
					'PartnerUser.user_id' => $this->args[0],
					'PartnerUser.partner' => 'mbd'
				)
			));
			if ($partner_user && !empty($partner_user['PartnerUser']['uid'])) {
				$results = array(
					array(
						'panelistId' => $partner_user['PartnerUser']['user_id'],
						'dwid' => $partner_user['PartnerUser']['uid']
					)
				);
			}
			else { // get the dwid from api
				$results = $http->get($this->settings['hostname.mbd'].'/panelists/'.$this->args[0].'?X-ApiKey='.$this->settings['mbd.api_key'], array(), $this->options);
				$results = json_decode($results['body'], true);
				CakeLog::write('mbd.process', 'User #'.$this->args[0]. ' as on MBD (before update): ' . print_r($results, true));
				$results = array($results);
			}
			
			$this->set_users($http, $results);
		}
		else {
			// Step 1: Call the stale api end point to get a list of panelists that need their demographics updated
			// This method returns up to 1000 rows that have not gotten demos within 30 days and are not marked as Do  
			// Not Contact. This process handles getting information for new panelists as well as updates for existing panelists.
			
			$i = 0;
			while (true) {
				$results = $http->get($stale_url, array(), $this->options);
				print_r($results);
				$i++;
				$this->out('Loop #'.$i.' Started');
				CakeLog::write('mbd.process', 'Loop #'.$i.' Started');
				$body = json_decode($results['body'], true);
				if (empty($body)) {
					CakeLog::write('mbd.process', 'User sync completed. Stale returned an empty result');
					break;
				}
				CakeLog::write('mbd.process.local', print_r($results, true)); 
				
				$return = $this->set_users($http, $body);
				if (!$return) {
					break;
				}
			}
		}
	}
	
	public function see_when_updated() {
		if (!$this->get_settings()) {
			return;
		}
				
		$http = new HttpSocket(array(
			'timeout' => 240,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));		
		$url = $this->settings['hostname.mbd'].'/panelists/stale?X-ApiKey='.$this->settings['mbd.api_key'];
		
		$this->out('Retrieving '.$url); 
		$results = $http->get(
			$url, 
			array(), 
			$this->options
		);

		$results = json_decode($results['body'], true);
		if (empty($results)) {
			CakeLog::write('mbd.process', 'User sync completed. Stale returned an empty result');
			//break;
		}
		foreach ($results as $result) {
			$partner_user = $this->PartnerUser->find('first', array(
				'fields' => array('PartnerUser.last_exported'),
				'conditions' => array(
					'PartnerUser.user_id' => $result['panelistId'],
					'PartnerUser.partner' => 'mbd'
				),
				'recursive' => -1
			));
			
			if (!empty($partner_user['PartnerUser']['uid'])) {
				$this->out($result['panelistId'].'('.$partner_user['PartnerUser']['uid'].'): '.(isset($partner_user['PartnerUser']['last_exported']) ? $partner_user['PartnerUser']['last_exported']: '')); 
			}
			else {
				$this->out($result['panelistId'].': '.(isset($partner_user['PartnerUser']['last_exported']) ? $partner_user['PartnerUser']['last_exported']: '')); 
			}
		}
	}
	
	// Step 2: update MV users demographics on MBD
	private function set_users($http, $results) {
		if (empty($results)) {
			$this->out('Panelists data not found to update.');
			return false;
		}
		
		$panelists = array();
		$mbd_stales = array();
		foreach ($results as $result) {
			if (empty($result['panelistId'])) {
				$this->out('Error: panelistId is empty');
				return false;
			}
			
			$panelists[$result['panelistId']] = $result['dwid'];
			
			// save the data to mbd_stale
			$mbdStaleSource = $this->MbdStale->getDataSource();
			$mbdStaleSource->begin();
			$this->MbdStale->create();
			$this->MbdStale->save(array('MbdStale' => array(
				'dwid' => $result['dwid'],
				'user_id' => $result['panelistId']
			)));
			
			// save the IDs by user_id so that we can mark them as processed when we've sent them
			$mbd_stales[$result['panelistId']] = $this->MbdStale->getInsertId();
			$mbdStaleSource->commit();
		}
		
		$this->out(count($panelists).' panelists requested.');
		$users = $this->User->find('all', array(
			'fields' => array('User.id', 'QueryProfile.*'),
			'contain' => array('QueryProfile'),
			'conditions' => array(
				'User.id' => array_keys($panelists),
				'User.email <>' => null,
				'User.deleted_on' => null,
				'User.hellbanned' => false,
				
				// Only runners, walkers and living users need to be synced with mbd
				'User.last_touched is not null',
				'User.last_touched > ' => date(DB_DATETIME, strtotime('-30 days')),
				'QueryProfile.gender <>' => '',
				'QueryProfile.postal_code <>' => '',
			),
			'recursive' => -1
		));
		
		$user_ids = Set::extract('/User/id', $users);
		$diff = array_diff(array_keys($panelists), $user_ids);
		$this->out(count($users).' panelists are being updated.');
		$this->out('Unmatched panelists: '.implode(', ', $diff));
		
		// Unmatched panelists (e.g deleted, hellbanned, zombies, dead etc) are set to  "do not contact"
		if ($diff) {
			foreach ($diff as $ignored_user_id) {
				$request = array(
					'dwid' => $panelists[$ignored_user_id],
					'panelistId' => $ignored_user_id,
					'partnerId' => $this->settings['mbd.partner_id'],
					'donotcontact' => true
				);
				
				$this->out('#' . $ignored_user_id . ' set to "donotcontact"'); 
				CakeLog::write('mbd.process', '#' . $ignored_user_id . ' set to "donotcontact"');
				try {
					$results = $http->post($this->settings['hostname.mbd'].'/panelists/'.$ignored_user_id.'?X-ApiKey='.$this->settings['mbd.api_key'], json_encode($request), $this->options);
					if ($results['body'] == 'true') {
						// mark this as processed
						if (isset($mbd_stales[$ignored_user_id])) {
							$this->MbdStale->create();
							$this->MbdStale->save(array('MbdStale' => array(
								'id' =>  $mbd_stales[$ignored_user_id],
								'processed' => true
							)), true, array('processed'));
						}
						$mbd_stale_id = $mbd_stales[$ignored_user_id]; 
						$partner_user = $this->PartnerUser->find('first', array(
							'conditions' => array(
								'PartnerUser.user_id' => $ignored_user_id,
								'PartnerUser.partner' => 'mbd'
							)
						));
						if ($partner_user) {
							$this->PartnerUser->save(array('PartnerUser' => array(
								'id' => $partner_user['PartnerUser']['id'],
								'last_exported' => date(DB_DATETIME)
							)), false, array('last_exported'));
						}
						else {
							$this->PartnerUser->create();
							$this->PartnerUser->save(array('PartnerUser' => array(							
								'last_exported' => date(DB_DATETIME),
								'user_id' => $ignored_user_id,
								'uid' => $panelists[$ignored_user_id],
								'partner' => 'mbd'
							)));
						}
					}
				} 
				catch (Exception $e) {
					$this->out('Panelists Api endpoint failed.');
				}
			}
		}

		if (!$users) {
			$this->out('Users not found');
			$this->out('Unmatched panelists: '.implode(', ', array_keys($panelists))); 
			return false;
		}
		
		// Update the active users demographics on mbd
		foreach ($users as $user) {
			$user_id = $user['User']['id'];
			$request = array(
				'dwid' => $panelists[$user_id],
				'panelistId' => $user_id,
				'partnerId' => $this->settings['mbd.partner_id'],
			);
			
			if (!empty($user['QueryProfile']['dma_code'])) {
				$request['dma'] = $user['QueryProfile']['dma_code'];
			}
			
			if (!empty($user['QueryProfile']['birthdate'])) {
				$request['birthdate'] = $user['QueryProfile']['birthdate']. 'T00:00:00';
			}
			
			if (!empty($user['QueryProfile']['postal_code'])) {
				$request['zipcode'] = $user['QueryProfile']['postal_code'];
			}
			
			$mapping_functions = array(
				'birthdate' => 'agerange',
				'country' => 'country',
				'organization_size' => 'companysize',
				'education' => 'education',
				'employment' => 'employmentstatus',
				'gender' => 'gender',
				'hhi' => 'hhi',
				'industry' => 'industrytype',
				'relationship' => 'maritalstatus',
				'job' => 'occupationtype',
				'children' => 'childrenunder18',
				'ethnicity' => 'race',
			);
			foreach ($mapping_functions as $mv_field => $mapping_function) {
				$result = MbdMappings::$mapping_function($user['QueryProfile'][$mv_field]);
				if ($result !== FALSE) {
					$request[$mapping_function] = $result;
				}
			}
			
			CakeLog::write('mbd.process', 'User #'.$user['User']['id']. ' MV data: ' . print_r($user, true));
			CakeLog::write('mbd.process', 'User #'.$user['User']['id']. ' Request data (before posting): ' . print_r($request, true));
			
			try {
				$results = $http->post($this->settings['hostname.mbd'].'/panelists/'.$user_id.'?X-ApiKey='.$this->settings['mbd.api_key'], json_encode($request), $this->options);
			} 
			catch (Exception $e) {
				$this->out('Panelists Api endpoint failed.');
			}
			
			// write local logs
			CakeLog::write('mbd.output', $user['User']['id']); 
			CakeLog::write('mbd.output', print_r($results, true));
			CakeLog::write('mbd.output','---------------'); 

			if ($results['body'] == 'true') {
				
				// mark this as processed
				if (isset($mbd_stales[$user['User']['id']])) {
					$this->MbdStale->create();
					$this->MbdStale->save(array('MbdStale' => array(
						'id' =>  $mbd_stales[$user['User']['id']],
						'processed' => true
					)), true, array('processed'));
				}
				$mbd_stale_id = $mbd_stales[$user['User']['id']]; 
				$partner_user = $this->PartnerUser->find('first', array(
					'conditions' => array(
						'PartnerUser.user_id' => $user['User']['id'],
						'PartnerUser.partner' => 'mbd'
					)
				));
				if ($partner_user) {
					$this->PartnerUser->save(array('PartnerUser' => array(
						'id' => $partner_user['PartnerUser']['id'],
						'uid' => $panelists[$user_id],
						'last_exported' => date(DB_DATETIME)
					)), false, array('last_exported', 'uid'));
				}
				else {
					$this->PartnerUser->create();
					$this->PartnerUser->save(array('PartnerUser' => array(							
						'last_exported' => date(DB_DATETIME),
						'user_id' => $user['User']['id'],
						'uid' => $panelists[$user_id],
						'partner' => 'mbd'
					)));
				}
				
				$msg = '#'.$user['User']['id']. ' (partner_user '.($partner_user && !empty($partner_user['PartnerUser']['uid']) ? 'exists': 'does not exist').')'; 
				$this->out($msg);
				CakeLog::write('mbd.process.local.list', $msg);
				CakeLog::write('mbd.process', 'API response' . print_r($results, true));
			}
			else {
				$this->out('User #'.$user['User']['id']. ' failed to update. check "mbd.process" log file.');
				CakeLog::write('mbd.process', 'User #'.$user['User']['id']. ' failed to update' . print_r($results, true));
			}
		}
	}
	
	// Show User data from MBD
	// arg: MV user_id
	public function get_panelist() {
		if (!isset($this->args[0]) || empty($this->args[0])) {
			$this->out('Provide MV user_id argument.');
			return;
		}
		
		if (!$this->get_settings()) {
			return;
		}
		
		$url = $this->settings['hostname.mbd'].'/panelists/'.$this->args[0].'?X-ApiKey='.$this->settings['mbd.api_key'];
		$this->out('Reaching out to '.$url);
		$http = new HttpSocket(array(
			'timeout' => 60,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		try {
			$results = $http->get($url, 
				array(
					'myDataOnly' => 'true'
				), 
				$this->options
			);
		} catch (Exception $e) {
			$this->out('Get Api endpoint failed.');
			return;
		}
		
		//$results = json_decode($results['body'], true);
		CakeLog::write('mbd.get', print_r($results, true));
		print_r($results);
	}
	
	// Set user data on MBD
	// arg: MV user_id
	public function set_panelist() {
		if (!isset($this->args[0]) || empty($this->args[0])) {
			$this->out('Provide MV user_id argument.');
			return;
		}
		
		if (!$this->get_settings()) {
			return;
		}
		
		$url = $this->settings['hostname.mbd'].'/panelists/'.$this->args[0].'?X-ApiKey='.$this->settings['mbd.api_key'];
		$this->out('Reaching out to '.$url);
		$http = new HttpSocket(array(
			'timeout' => 60,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$request = array(
			'dwid' => '00000000-0000-0001-0379-ef1412100980',
			'panelistId' => $this->args[0],
			'partnerId' => $this->settings['mbd.partner_id'],
			'donotcontact' => true
		);
		try {
			$results = $http->post($url, json_encode($request), $this->options);
		} 
		catch (Exception $e) {
			$this->out('Panelists Api endpoint failed.');
		}
		
		print_r($results);
	}
	
	// Show the current 1k stale users from mbd
	public function stale_users() {
		if (!$this->get_settings()) {
			return;
		}
		
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		try {
			$results = $http->get($this->settings['hostname.mbd'].'/panelists/stale?X-ApiKey='.$this->settings['mbd.api_key'], array(), $this->options);
		} catch (Exception $e) {
			$this->out('Stale Api endpoint failed.');
			return;
		}

		$results = json_decode($results['body'], true);
		CakeLog::write('mbd.stale', print_r($results, true));
		print_r($results);
	}
	
	public function fields() {
		if (!$this->get_settings()) {
			return;
		}
		
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$results = $http->get($this->settings['hostname.mbd'].'/panelists/fields?X-ApiKey='.$this->settings['mbd.api_key'], array(), $this->options);
		$results = json_decode($results['body'], true);
		CakeLog::write('mbd.fields', print_r($results, true));
		print_r($results);
	}
	
	public function manage_projects() {
		if (!$this->get_settings()) {
			return;
		}
		
		if ($this->settings['mbd.active'] != 'true') {
			return;
		}
		
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'mbd'
			)
		));
		$client = $this->Client->find('first', array(
			'fields' => array('Client.id'),
			'conditions' => array(
				'Client.key' => 'mbd',
				'Client.deleted' => false
			)
		));
		if (!$group || !$client) {
			$this->out('Missing mbd group and/or client');
			return;
		}
		
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.status' => PROJECT_STATUS_OPEN,
				'Project.group_id' => $group['Group']['id']
			),
			'order' => 'Project.id DESC'
		));
		
		if (!$projects) {
			$this->create_project($client, $group);
		}
		
		$active_exists = false;		
		foreach ($projects as $key => $project) {
			if ($project['Project']['active']) {
				$active_exists = true; 
			}
			if ($key != 0 || !$project['Project']['active']) {
				
				// we keep only one active mbd project
				$this->close_project($project);
				if (!$project['Project']['active']) {
					$message = 'Closed #'.$project['Project']['id'].' for being inactive (MBD)';
				}
				else {
					$message = 'Closed #'.$project['Project']['id'].' for being a dupe (MBD)';
				}
				CakeLog::write('mbd.projects', $message); 
				CakeLog::write('auto.close', $message);
				$this->out($message);
				continue;
			}
			
			if ($project['SurveyVisitCache']['click'] > 10000) {
				$this->close_project($project);
				$message = 'Closed #'.$project['Project']['id'].' clicks exceeded 10,000 (MBD)';
				CakeLog::write('mbd.projects', $message); 
				CakeLog::write('auto.close', $message);
				$this->out($message);

				$this->create_project($client, $group);
				$active_exists = true; 
			}
		}
		
		if (!$active_exists) {
			$this->create_project($client, $group);
		}
	}
	
	private function close_project($project) {
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
			'description' => 'Clicks exceeded 20,000.'
		)));
		Utils::save_margin($project['Project']['id']);
	}
	
	private function create_project($client, $group) {
		$projectSource = $this->Project->getDataSource();
		$projectSource->begin();
		$this->Project->create();			
		$save = $this->Project->save(array('Project' => array(
			'client_id' => $client['Client']['id'],
			'group_id' => $group['Group']['id'],
			'status' => PROJECT_STATUS_OPEN,
			'bid_ir' => 20,
			'est_length' => '20',
			'quota' => '10000', 
			'client_rate' => $this->settings['mbd.cpi'],
			'partner_rate' => round($this->settings['mbd.payout'] / 100, 2),
			'prj_name' => 'MBD Router',
			'user_payout' => round($this->settings['mbd.payout'] / 100, 2),
			'award' => $this->settings['mbd.payout'],
			'mobile' => true,
			'desktop' => true,
			'tablet' => true,
			'started' => date(DB_DATETIME),
			'active' => true,
			'router' => true,
			'dedupe' => false,
			'survey_name' => 'Rewards Road. A MintVine Funnel Survey',
			'country' => 'US'
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
				'rate' => round($this->settings['mbd.payout'] / 100, 2), // award
				'complete_url' => $this->settings['hostname.www'].'/surveys/complete/{{ID}}/',
				'nq_url' => $this->settings['hostname.www'].'/surveys/nq/{{ID}}/',
				'oq_url' => $this->settings['hostname.www'].'/surveys/oq/{{ID}}/',
				'pause_url' => $this->settings['hostname.www'].'/surveys/paused/',
				'fail_url' => $this->settings['hostname.www'].'/surveys/sec/{{ID}}/',
			)));
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project_id,
				'type' => 'project.created'
			)));
	
			$message = 'Created #'.$project_id. ' (MBD)';
			CakeLog::write('mbd.projects', $message); 
			$this->out($message); 
		}
		else {
			$projectSource->commit();
		}
	}
	
	public function put_exit_urls() {
		if (!$this->get_settings()) {
			return;
		}
		
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$results = $http->put($this->settings['hostname.mbd'].'/ignite/exiturls?X-ApiKey='.$this->settings['mbd.api_key'], json_encode(array(
			'exitUrlComplete' => 'https://r.mintvine.com/success/?', 
			'exitUrlTerm' => 'https://r.mintvine.com/nq/?',
			'exitUrlQuotaFull' => 'https://r.mintvine.com/quota/?',
		)), $this->options);
		print_r($results);
	}
	
	public function get_exit_urls() {
		if (!$this->get_settings()) {
			return;
		}
		
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$results = $http->get($this->settings['hostname.mbd'].'/ignite/exiturls?X-ApiKey='.$this->settings['mbd.api_key'], array(), $this->options);
		$results = json_decode($results['body'], true);
		CakeLog::write('mbd.exiturls', print_r($results, true));
		print_r($results);
	}
	
	// get the last 10K invites
	public function get_invites() {
		if (!$this->get_settings()) {
			return;
		}
		
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		if (isset($this->args[0])) { 
			$results = $http->post($this->settings['hostname.mbd'].'/ignite/getinvites?X-ApiKey='.$this->settings['mbd.api_key'], 
				json_encode(array(
					'panelistIds' => array(
						$this->args[0]
					)
				)), 
				array('header' => array(
					'Accept' => 'application/json',
					'Content-Type' => 'application/json; charset=UTF-8'
				))
			);
		}
		else {
			$results = $http->get($this->settings['hostname.mbd'].'/ignite/getinvites?X-ApiKey='.$this->settings['mbd.api_key'], array(
				'maxResults' => '100'
			), $this->options);
		}
		
		$results = json_decode($results, true);
		CakeLog::write('mbd.invites', print_r($results, true));
		
		$this->out('Total of '.count($results).' results');
		if (!empty($results)) {
			foreach ($results as $result) {
				$results = $http->get($result['url'].'&test=true',
					array(),
					array('header' => array(
						'Accept' => 'application/json',
						'Content-Type' => 'application/json; charset=UTF-8'
					))
				);
				$this->out('#'.$result['panelistId'].' '.$result['url'].': '.$results['body']); 
			}
		}
		$this->out('invite data logged in mbd.invites.log');
	}
	
	// In process - NOT COMPLETED YET -
	public function reconcile() {
		if (!$this->get_settings()) {
			return;
		}
		
		$params = array();
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		try {
			$results = $http->get($this->settings['hostname.mbd'].'/ignite/getexits?X-ApiKey='.$this->settings['mbd.api_key'], $params, $this->options);
		}
		
		catch (Exception $e) {
			$this->out('ignit/getexits api call failed.');
			return;
		}
		
		$results = json_decode($results['body'], true);
		CakeLog::write('mbd.exits', print_r($results, true));
		
		$mbd_data = array();
		foreach ($results as $result) {
			$date = new DateTime($result['exitDate']);
			$mbd_data[$date->format(DB_DATETIME)] = array(
				'status' => $result['status'],
				'user_id' => $result['respondentId']
			);
		}
		
		$group = $Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'mbd'
			)
		));
		if (!$group) {
			CakeLog::write('mbd.reconcile', '[Failed] : MBD group not found.');
			return false;
		}
		
		$project = $Project->find('first', array(
			'fields' => array('Project.id'),
			'conditions' => array(
				'Project.status' => PROJECT_STATUS_OPEN,
				'Project.group_id' => $group['Group']['id']
			),
			'order' => 'Project.id DESC',
			'recursive' => -1
		));
		if (!$project) {
			CakeLog::write('mbd.reconcile', '[Failed] : MBD project not found.');
			return false;
		}
		
		$transactions = $this->Transaction->find('all', array(
			'fields' => array('Transaction.user_id', 'Transaction.created'),
			'conditions' => array(
				'Transaction.type_id' => TRANSACTION_SURVEY,
				'Transaction.linked_to_id' => $project['Project']['id'],
				'Transaction.deleted' => null,
			)
		));
	}
	
	// we will run script every two hours to invite panelists to MBD projects if they exist
	// invite every two hours; but once per panelist per 24 hour period
	public function invite() {
		if (!$this->get_settings()) {
			return;
		}
		
		if ($this->settings['mbd.active'] != 'true') {
			return;
		}
		
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'mbd'
			)
		));
		
		$project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.status' => PROJECT_STATUS_OPEN,
				'Project.active' => true,
				'Project.group_id' => $group['Group']['id']
			),
			'order' => 'Project.id DESC'
		));
		if (!$project) {
			return;
		}

		// so we can capture the output of the view into a string to write
		$this->autoRender = false;
	
		$survey_subject = empty($project['Project']['description']) ? 'Exciting Survey Opportunity - Act now!': $project['Project']['description'];
		$survey_award = $project['Project']['award'];
		$survey_length = $project['Project']['est_length'];
	
		$is_desktop = $project['Project']['desktop'];
		$is_mobile = $project['Project']['mobile'];
		$is_tablet = $project['Project']['tablet'];
		$survey_id = $project['Project']['id'];

		// grab the email template
		App::uses('Controller', 'Controller');
		App::uses('View', 'View');
		
        $controller = new Controller();
		$view = new View($controller, false);
		$view->layout = 'Emails/html/default';
		$nonce = '{{nonce}}';
		$survey_url = '{{survey_url}}';
		$unsubscribe_link = '{{unsubscribe_link}}';
		$view->set(compact('nonce', 'survey_url', 'unsubscribe_link', 'survey_award', 'survey_length', 'is_desktop', 'is_mobile', 'is_tablet', 'survey_id'));
		$view->viewPath = 'Emails/html';
		$email_body = $view->render('survey');
		$this->autoRender = true;
		
		// grab users
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$results = $http->get($this->settings['hostname.mbd'].'/ignite/getinvites?X-ApiKey='.$this->settings['mbd.api_key'], $this->options);		
		$results = json_decode($results, true);
		if (empty($results)) {
			$this->out('API call failed');
			CakeLog::write('mbd.invite', 'API call failed');
			return;
		} 
		
		$this->out('Sending to '.count($results).' panelists');
		CakeLog::write('mbd.invite', 'Sending to '.count($results).' panelists');
		$total = count($results); 
		
		$i = 0; 
		$success = 0;
		$queued_emails = array();
		foreach ($results as $result) {
			$i++;
			$user = $this->User->find('first', array(
				'fields' => array('User.id', 'User.last_touched', 'User.ref_id', 'User.email', 'User.send_survey_email', 'User.send_email'),
				'conditions' => array(
					'User.id' => $result['panelistId'],
					'User.hellbanned' => false
				),
				'recursive' => -1
			));
			if (!$user) {
				continue;
			}
			
			$active = $http->get($result['url'].'&test=true',
				array(),
				array('header' => array(
					'Accept' => 'application/json',
					'Content-Type' => 'application/json; charset=UTF-8'
				))
			);
			if ($active['body'] == 'False') {
				$this->out($i.'/'.$total.' Email not send to '.$user['User']['id'].' because they have opted out of emails'); 
				CakeLog::write('mbd.invite', $i.'/'.$total.' Email not send to '.$user['User']['id'].' because they have opted out of emails');
				continue;
			}
			// bypass the sending of email if user has opted out
			if (!$user['User']['send_survey_email'] || !$user['User']['send_email']) {
				$this->out($i.'/'.$total.' Email not send to '.$user['User']['id'].' because they have opted out of emails'); 
				CakeLog::write('mbd.invite', $i.'/'.$total.' Email not send to '.$user['User']['id'].' because they have opted out of emails');
				continue;
			}
			
			$user_option = $this->UserOption->find('first', array(
				'conditions' => array(
					'UserOption.user_id' => $user['User']['id'],
					'UserOption.name' => 'mbd.last.invited'
				)
			)); 
			if ($user_option && strtotime('-24 hours') <= strtotime($user_option['UserOption']['value'])) {
				$this->out($i.'/'.$total.' Skipped '.$user['User']['id'].' as they were emailed recently ('.$user_option['UserOption']['value'].')');
				CakeLog::write('mbd.invite', $i.'/'.$total.' Skipped '.$user['User']['id'].' as they were emailed recently ('.$user_option['UserOption']['value'].')');
				continue;
			} 
			
			// MBD can send dupe invites
			if (in_array($user['User']['email'], $queued_emails)) {
				continue;
			}
			$queued_emails[] = $user['User']['email']; 
			
			// generate the email
			$nonce = substr($user['User']['ref_id'], 0, 21).'-'.substr(Utils::rand(10), 0, 10);
			$survey_url = HOSTNAME_WWW.'/surveys/pre/'.$project['Project']['id'].'/?nonce='.$nonce . '&from=email'.(!empty($project['Project']['code']) ? '&key='.$project['Project']['code'] : '');
			$unsubscribe_link = HOSTNAME_WWW.'/users/emails/'.$user['User']['ref_id'];
			
			$customized_email_body = str_replace(array(
				'{{nonce}}',
				'{{unsubscribe_link}}', 
				'{{survey_url}}',
				'{{user_id}}'
			), array(
				$nonce,
				$unsubscribe_link, 
				$survey_url,
				$user['User']['id']
			), $email_body);
			
			// create the one-time nonce
			$this->Nonce->create();
			$this->Nonce->save(array('Nonce' => array(
				'item_id' => $project['Project']['id'],
				'item_type' => 'survey',
				'user_id' => $user['User']['id'],
				'nonce' => $nonce
			)), false);
		
			$this->MailQueue->create();
			$this->MailQueue->save(array('MailQueue' => array(
				'user_id' => $user['User']['id'],
				'email' => $user['User']['email'],
				'subject' => $survey_subject,
				'project_id' => $project['Project']['id'],
				'body' => $customized_email_body,
				'status' => 'Queued'
			)));
			
			$success++;
			$this->out($i.'/'.$total.' Queued (#'.$user['User']['id'].': '.$user['User']['email'].')'); 
			CakeLog::write('mbd.invite', $i.'/'.$total.' Queued (#'.$user['User']['id'].': '.$user['User']['email'].')');
			
			if (!$user_option) {
				$this->UserOption->create();
				$this->UserOption->save(array('UserOption' => array(
					'user_id' => $user['User']['id'],
					'name' => 'mbd.last.invited',
					'value' => date(DB_DATETIME)
				)));
			}
			else {
				$this->UserOption->create();
				$this->UserOption->save(array('UserOption' => array(
					'id' => $user_option['UserOption']['id'],
					'value' => date(DB_DATETIME)
				)), true, array('value'));
			}
		}
		$this->out('Emails successfully sent out to '.$success); 
		CakeLog::write('mbd.invite', 'Emails successfully sent out to '.$success);
	}
		
		
	// this will make the analysis easier around synced vs unsynced panelists; the dwid isn't used for anything on our end
	public function sync_mbd_dwids() {
		$partner_users = $this->PartnerUser->find('all', array(
			'conditions' => array(
				'PartnerUser.partner' => 'mbd',
				'PartnerUser.uid is null'
			)
		));
		$this->out('Analyzing '.count($partner_users));
		foreach ($partner_users as $partner_user) {
			$mbd_stale = $this->MbdStale->find('first', array(
				'conditions' => array(
					'MbdStale.user_id' => $partner_user['PartnerUser']['user_id'],
					'MbdStale.processed' => true,
					'MbdStale.dwid is not null'
				),
				'order' => 'MbdStale.id DESC'
			));
			if ($mbd_stale) {
				$this->PartnerUser->create();
				$this->PartnerUser->save(array('PartnerUser' => array(
					'id' => $partner_user['PartnerUser']['id'],
					'modified' => $mbd_stale['MbdStale']['created'],
					'uid' => $mbd_stale['MbdStale']['dwid']
				)), true, array('modified', 'uid')); 
				
				$this->out('Updated #'.$partner_user['PartnerUser']['id']);
			}
		}
	}
		
		
	// how many exported panelists show up on stale? look at only exported panelists from past 14 days
	public function panelist_dwid_availability() {
		$partner_users = $this->PartnerUser->find('all', array(
			'conditions' => array(
				'PartnerUser.partner' => 'mbd',
				'PartnerUser.created >=' => date(DB_DATETIME, strtotime('-14 days'))
			)
		));
		
		$missing_dwids = $missing_from_stales = array();
		$this->out('Analyzing '.count($partner_users).' exported panelists to MBD');
		foreach ($partner_users as $partner_user) {
			$user_id = $partner_user['PartnerUser']['user_id'];
			if (empty($partner_user['PartnerUser']['uid'])) {
				$missing_dwids[] = $user_id;

				// confirm they are missing from stale
				$count = $this->MbdStale->find('count', array(
					'conditions' => array(
						'MbdStale.user_id' => $partner_user['PartnerUser']['user_id']
					)
				));
				if ($count == 0) {
					$missing_from_stales[] = $user_id;
				}
			}
		}
		
		$missing_dwid_pct = round(count($missing_dwids) / count($partner_users) * 100, 2); 
		$missing_stale_pct = round(count($missing_from_stales) / count($partner_users) * 100, 2); 
		$diff = array_diff($missing_dwids, $missing_from_stales);
		
		$this->out(count($missing_dwids).' missing DWIDs ('.$missing_dwid_pct.'%)');
		$this->out(count($missing_from_stales).' missing from stales ('.$missing_stale_pct.'%)');
	}
	
	// find all panelists active in the last day synced over to mbd that did not receive an invite
	public function panelist_uninvited() {
		if (!$this->get_settings()) {
			return;
		}
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$results = $http->get($this->settings['hostname.mbd'].'/ignite/getinvites?X-ApiKey='.$this->settings['mbd.api_key'], $this->options);		
		$results = json_decode($results, true);
		
		$mbd_panelist_ids = array_unique(Set::extract('/panelistId', $results)); 
		
		$this->PartnerUser->bindModel(array('belongsTo' => array('User')));
		$partner_users = $this->PartnerUser->find('all', array(
			'fields' => array('User.id'),
			'conditions' => array(
				'PartnerUser.partner' => 'mbd',
				'PartnerUser.uid is not null',
				'User.last_touched >=' => date(DB_DATETIME, strtotime('-48 hours'))
			)
		));
		
		$active_panelists = Set::extract('/User/id', $partner_users); 
		
		$this->out('Found '.count($mbd_panelist_ids).' MBD invites');
		$this->out('Found '.count($active_panelists).' active MV panelists');
		$diff = array_diff($active_panelists, $mbd_panelist_ids); 
		$this->out('Found '.count($diff).' panelists that were not invited to MBD');
	}
	
	// how many invites are going to active panelists vs inactive panelists?
	public function panelist_dwid_freshness() {

		if (!$this->get_settings()) {
			return;
		}
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$results = $http->get($this->settings['hostname.mbd'].'/ignite/getinvites?X-ApiKey='.$this->settings['mbd.api_key'], $this->options);		
		$results = json_decode($results, true);

		$fp = fopen(WWW_ROOT.'/files/mbd-panelist-analysis.csv', 'w');
  	 	fputcsv($fp, array(
			'Sample ID', 
			'Sample Date',
			'Expire Date',
			'Panelist ID',
			'Active',
			'Panelist Last Active'
		));
		$total = count($results); 
		$this->out('Total of '.$total.' results');
		$i = 0; 
		if (!empty($results)) {
			foreach ($results as $result) {
				$active = $http->get($result['url'].'&test=true',
					array(),
					array('header' => array(
						'Accept' => 'application/json',
						'Content-Type' => 'application/json; charset=UTF-8'
					))
				);
				$user = $this->User->find('first', array(
					'fields' => array('User.last_touched'),
					'conditions' => array(
						'User.id' => $result['panelistId']
					),
					'recursive' => -1
				));
		  	 	fputcsv($fp, array(
					$result['sampleId'],
					$result['sampleDate'],
					$result['expireDate'],
					$result['panelistId'],
					$active['body'],
					$user['User']['last_touched']
				));
				$i++;
				$this->out($i.'/'.$total);
			}
		}
		fclose($fp);
	}
}