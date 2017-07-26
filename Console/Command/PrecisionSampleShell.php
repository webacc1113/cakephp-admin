<?php
App::uses('Shell', 'Console');
App::import('Lib', 'Utilities');
App::import('Lib', 'Reporting');
App::uses('HttpSocket', 'Network/Http');

class PrecisionSampleShell extends AppShell {
	var $uses = array('User', 'Setting', 'ProjectOption', 'Reconciliation', 'ExtraComplete', 'Group', 'Project', 'UserRouterLog', 'MailLog', 'ProjectLog', 'SurveyUserVisit', 'SurveyVisit', 'Transaction', 'PartnerUser', 'PrecisionProject', 'PrecisionInvite', 'SurveyLink', 'SurveyUser', 'Nonce', 'MailQueue', 'PrecisionNotificationLog');
	public $tasks = array('ReconcilePrecision', 'Notify');
	
	public function get_panelist() {
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array(
					'precision_sample.rid', 'precision_sample.host'
				),
				'Setting.deleted' => false
			)
		));
		if (count($settings) < 2) {
			$this->log('Setting not defined.');
			return false;
		}
		$url = $settings['precision_sample.host'] . '/GetProfiles';
		$response = $this->HttpSocket->get($url, array(
			'RID' => $settings['precision_sample.rid'],
			'UserGuid' => $this->args[0]
		));
		
	}	
	
	// args[0] is a flag to print the API response on Console. if it is 1 it will print the API response
	function export() {
		ini_set('memory_limit', '2048M');
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array(
					'precision_sample.rid', 'precision_sample.host'
				),
				'Setting.deleted' => false
			)
		));
		if (count($settings) < 2) {
			$this->log('Setting not defined.');
			return false;
		}
		$precision_sample_last_exported_user_id = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.project_id' => 0,
				'ProjectOption.name' => 'precision_sample_last_exported_user_id'
			)
		));
		if ($precision_sample_last_exported_user_id) {
			$last_user_export_id = $precision_sample_last_exported_user_id['ProjectOption']['value'];
		}
		else {
			$last_user_export_id = 0;
		}
		$this->User->bindModel(array(
			'hasOne' => array(
				'QueryProfile'
			)
		));
		$users = $this->User->find('all', array(
			'contain' => array(
				'QueryProfile'
			),
			'order' => 'User.id ASC',
			'conditions' => array(
				'User.active' => true,				
				'User.deleted_on' => null,		
				'User.hellbanned' => false,				
				'User.id >' => $last_user_export_id,
				'User.last_touched >=' => date(DB_DATETIME, strtotime('-3 months'))
			)
		));
		if ($users) {
			$log_key = strtoupper(Utils::rand('4'));		
			$log_file = 'precision.create';			
			foreach ($users as $user) {
				$existing_partner_user = $this->PartnerUser->find('first', array(
					'conditions' => array(
						'PartnerUser.user_id' => $user['User']['id'],
						'PartnerUser.partner' => 'precision'
					),
					'recursive' => -1
				));
				// don't update existing panelists
				if ($existing_partner_user) {
					continue;
				}
				try {
					$this->HttpSocket = new HttpSocket(array(
						'timeout' => 15,
						'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
					));
					if ($existing_partner_user) {
						$log_file = 'precision.update';
						$url = $settings['precision_sample.host'] . '/Update';
						if (!empty($user['QueryProfile']['postal_code']) && !empty($user['User']['firstname']) && !empty($user['User']['lastname']) && !empty($user['QueryProfile']['gender'])) {
							$response = $this->HttpSocket->post($url, json_encode(array(
								'UserGuid' => $existing_partner_user['PartnerUser']['uid'],
								'RID' => $settings['precision_sample.rid'],
								'TxId' => $user['User']['id'],
								'ExtMemberId' => $user['User']['id'],
								'Country' => $user['QueryProfile']['country'],
								'FirstName' => $user['User']['firstname'],
								'LastName' => $user['User']['lastname'],
								'EmailAddress' => $user['User']['email'],
								'Zip' => $user['QueryProfile']['postal_code'],
								'Gender' => $user['QueryProfile']['gender'],
								'Dob' => date('m/d/Y', strtotime($user['QueryProfile']['birthdate'])),
								'State' => '',
								'Address1' => '',
								'Address2' => '',
								'Ethnicity' => '',
								'City' => '',
							)), array('header' => array(
								'Content-Type' => 'application/json'
							)));
							
							$response = $response['body'];		
							
							$response = json_decode($response);					
							$response = (array)simplexml_load_string($response->d);
							if (isset($this->args[0]) && $this->args[0]) {
								print_r($response);		
							}
							$this->PartnerUser->create();
							$this->PartnerUser->save(array('PartnerUser' => array(
								'id' => $existing_partner_user['PartnerUser']['id'],
								'last_exported' => date(DB_DATETIME),
								'uid' => $response['UserGuid']
							)), false, array('last_exported', 'uid'));
							
							$this->lecho('[SUCCESS] User updated#' . $user['User']['id'] . ' - ' . $response['UserGuid'], $log_file, $log_key);
						}
					}
					else {
						$url = $settings['precision_sample.host'] . '/Create';
						if (!empty($user['QueryProfile']['postal_code']) && !empty($user['User']['firstname']) && !empty($user['User']['lastname']) && !empty($user['QueryProfile']['gender'])) {
							$response = $this->HttpSocket->post($url, array(
								'RID' => $settings['precision_sample.rid'],
								'TxId' => $user['User']['id'],
								'ExtMemberId' => $user['User']['id'],
								'Country' => $user['QueryProfile']['country'],
								'FirstName' => $user['User']['firstname'],
								'LastName' => $user['User']['lastname'],
								'EmailAddress' => $user['User']['email'],
								'Zip' => $user['QueryProfile']['postal_code'],
								'Gender' => $user['QueryProfile']['gender'],
								'Dob' => date('m/d/Y', strtotime($user['QueryProfile']['birthdate'])),
								'State' => '',
								'Address1' => '',
								'Address2' => '',
								'Ethnicity' => '',
								'City' => '',
							));
							
							$response = $response['body'];									
							$response_xml = (array)simplexml_load_string($response);						
							$response_array =json_decode($response_xml[0], true);
							if (isset($this->args[0]) && $this->args[0]) {
								echo '<pre>'; print_r($response_array); echo '</pre>'; 			
							}
							$this->PartnerUser->create();
							$this->PartnerUser->save(array('PartnerUser' => array(							
								'last_exported' => date(DB_DATETIME),
								'uid' => $response_array['result']['UserGuid'],
								'user_id' => $user['User']['id'],
								'partner' => 'precision'
							)));
							$this->lecho('[SUCCESS] User exported #' . $user['User']['id'] . ' - ' . $response_array['result']['UserGuid'], $log_file, $log_key);
						}
					}
				}
				catch (Exception $e) {
					$this->lecho('[FAILED] #' . $user['User']['id'] . ' - ' . $e->getMessage(), $log_file, $log_key);
				}
				
				$last_user = $user['User']['id'];
			}
			if ($precision_sample_last_exported_user_id) {
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'id' => $precision_sample_last_exported_user_id['ProjectOption']['id'],
					'value' => $last_user
				)), false, array('value'));
			}
			else {
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(						
					'value' => $last_user,
					'project_id' => 0,
					'name' => 'precision_sample_last_exported_user_id'
				)));
			}			
		}
	}
	
	public function duplicate_visits() {
		if (!isset($this->args[0]) || empty($this->args[0])) {
			$limit = 10;
		}
		else {
			$limit = $this->args[0];
		}
		
		$count = array();
		
		$projects = $this->PrecisionProject->find('all', array(
			'order' => 'PrecisionProject.id DESC',
			'limit' => $limit
		));

		foreach ($projects as $project) {
			$visits = $this->SurveyVisit->find('all', array(
				'conditions' => array(
					'SurveyVisit.survey_id' => $project['PrecisionProject']['project_id'],
					'SurveyVisit.type' => SURVEY_CLICK
				)
			));
			
			if (!$visits) {
				continue;
			}
			
			foreach ($visits as $visit) {
				$data = explode('-', $visit['SurveyVisit']['partner_user_id']);
				if (empty($count[$data[0]][$data[1]])) {
					$count[$data[0]][$data[1]] = 1;
				}
				else {
					$count[$data[0]][$data[1]] = $count[$data[0]][$data[1]] + 1;
				}
			}
		}

		foreach ($count as $project_id=>$users) {
			foreach ($users as $user_id=>$count) {
				if ($count > 1) {
					$this->out('Project ID: '.$project_id.' User ID: '.$user_id.' Count: '.$count);
				}
			}
		}
	}
	
	public function pull_notifications() {
		$log_file = 'precision_sample.invites';
		$message = '';
		$setting_names = array(
			'precision_sample.invites.host', 
			'precision_sample.secret', 
			'cdn.url', 
			'precision_sample.active'
		); 
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => $setting_names,
				'Setting.deleted' => false
			)
		));
		if (count($settings) < count($setting_names)) {
			$this->out('Settings not found');
			return false;
		}
		
		if ($settings['precision_sample.active'] != 'true') {
			$this->out('Precision Sample is not an active integration');
			return false;
		}
		
		$http = new HttpSocket(array(
			'timeout' => 120,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		try {
			$response = $http->get($settings['precision_sample.invites.host'] . '/api/email', array(), array('header' => array(
				'Content-Type' => 'application/json',
				'X-PSAPPKey' => $settings['precision_sample.secret'],
			)));
		} 
		catch (Exception $ex) {
			$message = 'Api access error, try again please.';
			$this->out($message);
			return;
		}
		
		if ($response->code != 200) {
			$message = 'Api error with response code: '.$response->code. '. Check log please.';
			$this->out($message);
			CakeLog::write($log_file, print_r($response, true));
			return;
		}
		
		/*
		$sample_json = '{  
		      "p_id":64622,
		      "m_reward":0.00,
		      "p_payout":7.30,
		      "s_name":"Study For Professionals",
		      "u_guid":"7557A9C6-340D-4A73-91B6-896C2DABBF03",
		      "ext_id":"128",
		      "s_url":"http://s.opinionetwork.com/partner/sc2.aspx?ug=088037F2-4FBF-42B6-B0C1-100AA8F4F0BE&s=e&sid=FA9593AD-F81D-4F08-8EB2-141607A9B25A",
		      "s_length": 45,
		       “s_sms” : false
		   }'; 
		*/ 
		
		$invites = json_decode($response->body, true);
		if (empty($invites)) {
			$message = 'invites not found.';
			$this->out($message);
			CakeLog::write($log_file, print_r($response, true));
			return;
		}
		
		$project_ids = Set::extract('/p_id', $invites);
		$project_ids = array_unique($project_ids);
		$this->Project->bindModel(array(
			'hasOne' => array(
				'PrecisionProject'
			)
		));
		$projects = $this->Project->find('all', array(
			'contain' => array(
				'PrecisionProject'
			),
			'conditions' => array(
				'PrecisionProject.precision_project_id' => $project_ids,
				'Project.active' => true,
				'Project.status' => PROJECT_STATUS_OPEN
			),
			'fields' => array(
				'Project.id',
				'Project.router',
				'Project.est_length',
				'Project.desktop',
				'Project.mobile',
				'Project.tablet',
				'Project.active',
				'Project.status',
				'Project.client_rate',
				'Project.award',
				'PrecisionProject.precision_project_id',
				'Project.country'
			),
			'recursive' => -1
		));
		if (!$projects) {
			return;
		}
		
		foreach ($projects as $key => $project) {
			$projects[$project['PrecisionProject']['precision_project_id']] = $project;
			unset($projects[$key]);
		}
		
		foreach ($invites as $invite) {
			if (!isset($invite['p_id']) || !isset($invite['u_guid']) || !isset($invite['s_url'])) {
				continue;
			}
			
			// sms projects should only be sent via sms
			if (isset($invite['s_sms']) && $invite['s_sms'] == 'true') {
				$this->write_notification_log($invite, '[Failed] sms projects should only be sent via sms');
				continue;
			}
			
			$partner_user = $this->PartnerUser->find('first', array(
				'fields' => array('PartnerUser.user_id'),
				'conditions' => array(
					'PartnerUser.uid' => $invite['u_guid'],
					'PartnerUser.partner' => 'precision'
				),
				'recursive' => -1
			));
			
			if (!$partner_user) {
				$message = '[Failed] Partner user not found for uid: #'.$invite['u_guid'];
				$this->out($message);
				CakeLog::write($log_file, $message);
				$this->write_notification_log($invite, $message);
				continue;
			}
			
			$mv_user = $this->User->find('first', array(
				'conditions' => array(
					'User.id' => $partner_user['PartnerUser']['user_id'],
					'User.hellbanned' => false,
					'User.deleted_on' => null,
					'User.active' => true, 
					'User.last_touched >=' => date(DB_DATETIME, strtotime('-2 weeks'))
				),
				'contain' => array(
					'QueryProfile' => array(
						'fields' => array('country'),
					)
				)
			));
			
			if (!$mv_user) {
				$this->out('Not sent: inactive user');
				$this->write_notification_log($invite, 'Not sent: inactive user');
				continue;
			}
			
			if (!$mv_user['User']['send_survey_email'] || !$mv_user['User']['send_email']) {
				$message = '[Failed] User #'.$mv_user['User']['id'].' opted out email invitations';
				$this->out($message);
				CakeLog::write($log_file, $message);
				$this->write_notification_log($invite, '[Failed] User #'.$mv_user['User']['id'].' opted out email invitations');
				continue;
			}
			
			// if project is not found, that mean the project is either inactive or not open
			if (!isset($projects[$invite['p_id']])) {
				$this->out('Project '.$invite['p_id'].' does not exist');
				$this->write_notification_log($invite, 'Project '.$invite['p_id'].' does not exist');
				continue;
			}
			
			$project = $projects[$invite['p_id']];
			
			if ($project['Project']['country'] != $mv_user['QueryProfile']['country']) {
				$message = '[Failed] project country do not match user country. User id: '. $mv_user['User']['id']. ', Project id: '. $project['Project']['id'];
				$this->out($message);
				CakeLog::write($log_file, $message);
				$this->write_notification_log($invite, $message);
				continue;
			}

			// Save precision invites
			$precision_invite = $this->PrecisionInvite->find('first', array(
				'conditions' => array(
					'PrecisionInvite.user_id' => $mv_user['User']['id'],
					'PrecisionInvite.precision_project_id' => $invite['p_id']
				)
			));
			
			// don't resend multiple invites
			if ($precision_invite && $precision_invite['PrecisionInvite']['emailed']) {
				$this->out('Already sent invite');
				$this->write_notification_log($invite, 'Already sent invite');
				continue; 
			}
			
			if ($precision_invite) {
				$this->PrecisionInvite->create();
				$this->PrecisionInvite->save(array('PrecisionInvite' => array(
					'id' => $precision_invite['PrecisionInvite']['id'],
					'emailed' => true,
					'last_seen' => date(DB_DATETIME)									
				)), false, array('emailed', 'last_seen'));
			}
			else {
				$this->PrecisionInvite->create();
				$this->PrecisionInvite->save(array('PrecisionInvite' => array(
					'user_id' => $mv_user['User']['id'],
					'first_invited' => date(DB_DATETIME),
					'emailed' => true,
					'last_seen' => date(DB_DATETIME),
					'project_id' => $project['Project']['id'],
					'precision_project_id' => $invite['p_id']
				)));
			}

			// Save user survey links
			$survey_url = $invite['s_url'].'&sub={{ID}}';
			$survey_links = $this->SurveyLink->find('all', array(
				'recursive' => -1,
				'conditions' => array(
					'SurveyLink.user_id' => $mv_user['User']['id'],
					'SurveyLink.survey_id' => $project['Project']['id']
				),
				'fields' => array('SurveyLink.id', 'SurveyLink.link')
			));
			$link_found = false;
			if ($survey_links) {
				foreach ($survey_links as $survey_link) {
					if ($survey_link['SurveyLink']['link'] == $survey_url) {
						$link_found = true;
					}
					else {
						$this->SurveyLink->delete($survey_link['SurveyLink']['id']);
					}
				}
			}

			if (!$link_found) {
				$this->SurveyLink->create();
				$this->SurveyLink->save(array('SurveyLink' => array(
					'survey_id' => $project['Project']['id'],
					'link' => $survey_url,
					'user_id' => $mv_user['User']['id'],
					'active' => true
				)));
			}

			// Save SurveyUser record
			$survey_user = $this->SurveyUser->find('first', array(
				'conditions' => array(
					'SurveyUser.user_id' => $mv_user['User']['id'],
					'SurveyUser.survey_id' => $project['Project']['id'],
				)
			));
			if ($survey_user) {
				if ($survey_user['SurveyUser']['notification'] != '1') {
					$this->SurveyUser->create();
					$this->SurveyUser->save(array('SurveyUser' => array(
						'id' => $survey_user['SurveyUser']['id'],
						'notification' => '1'
					)), true, array('notification'));
				}
			}
			else {
				$this->SurveyUser->create();
				$this->SurveyUser->save(array('SurveyUser' => array(
					'user_id' => $mv_user['User']['id'],
					'survey_id' => $project['Project']['id'],
					'notification' => '1'
				)));
			}

			// for capturing the output of a view into a string
			if ($project['Project']['router']) {
				$template = 'survey-funnel';
			}
			else {
				$template = 'survey';
			}
			
			if (!empty($settings['cdn.url']) && (!defined('IS_DEV_INSTANCE') || !IS_DEV_INSTANCE)) {
				Configure::write('App.cssBaseUrl', $settings['cdn.url'] . '/');
				Configure::write('App.jsBaseUrl', $settings['cdn.url'] . '/');
				Configure::write('App.imageBaseUrl', $settings['cdn.url'] . '/img/');
			}
			
			$nonce = '{{nonce}}';
			$survey_url = '{{survey_url}}';
			$unsubscribe_link = '{{unsubscribe_link}}';
			$survey_subject = empty($project['Project']['description']) ? 'Exciting Survey Opportunity': $project['Project']['description'];
			$survey_award = $project['Project']['award'];
			$survey_length = $project['Project']['est_length'];
			$is_desktop = $project['Project']['desktop'];
			$is_mobile = $project['Project']['mobile'];
			$is_tablet = $project['Project']['tablet'];
			$survey_id = $project['Project']['id'];
			
			$this->autoRender = false;
			App::uses('Controller', 'Controller');
			App::uses('View', 'View');
			$controller = new Controller();
			$view = new View($controller, false);
			$view->layout = 'Emails/html/default';
			$view->set(compact('nonce', 'survey_url', 'unsubscribe_link', 'survey_award', 'survey_length', 'is_desktop', 'is_mobile', 'is_tablet', 'survey_id'));
			$view->viewPath = 'Emails/html';
			$email_body = $view->render($template);
			$this->autoRender = true;
			
			$nonce = substr($mv_user['User']['ref_id'], 0, 21).'-'.substr(Utils::rand(10), 0, 10);
			$survey_url = HOSTNAME_WWW.'/surveys/pre/'.$project['Project']['id'].'/?nonce='.$nonce . '&from=email'.(!empty($project['Project']['code']) ? '&key='.$project['Project']['code'] : '');
			$unsubscribe_link = HOSTNAME_WWW.'/users/emails/'.$mv_user['User']['ref_id'];
			// create the one-time nonce
			$this->Nonce->create();
			$this->Nonce->save(array('Nonce' => array(
				'item_id' => $project['Project']['id'],
				'item_type' => 'survey',
				'user_id' => $mv_user['User']['id'],
				'nonce' => $nonce
			)), false);
			
			$customized_email_body = str_replace(array(
				'{{nonce}}',
				'{{unsubscribe_link}}', 
				'{{survey_url}}',
				'{{user_id}}'
			), array(
				$nonce,
				$unsubscribe_link, 
				$survey_url,
				$mv_user['User']['id']
			), $email_body);

			// queue into mail queue if user has opted into emails
			$this->MailQueue->create();
			$this->MailQueue->save(array('MailQueue' => array(
				'user_id' => $mv_user['User']['id'],
				'email' => $mv_user['User']['email'],
				'subject' => $survey_subject,
				'project_id' => $project['Project']['id'],
				'body' => $customized_email_body,
				'status' => 'Queued'
			)));
			$message = '[Success] invite email queued. User #'.$mv_user['User']['id']. ' Project #'.$project['Project']['id'];
			$this->out($message);
			CakeLog::write($log_file, $message);
			$this->write_notification_log($invite, $message);
		}
		
		$this->out('completed');
	}
	
	// see how the email notifications are performing
	public function check_email_participation_status() {
		$precision_invites = $this->PrecisionInvite->find('all', array(
			'conditions' => array(
				'PrecisionInvite.emailed' => true
			),
			'order' => 'PrecisionInvite.id DESC',
			'limit' => 1000
		));
		
		$filename = WWW_ROOT.'files/precision_email_results.csv';
		$this->out('Writing to '.$filename);
		
		$fp = fopen($filename, 'w');
		fputcsv($fp, array(
			'Project ID',
			'User ID',
			'First Invited',
			'Last Invited',
			'Mailed',
			'Clicked',
			'Result',
			'CPI',
			'Via',
			'Active Since Mail?'
		)); 
		
		$survey_statuses = unserialize(SURVEY_STATUSES); 
		
		foreach ($precision_invites as $precision_invite) {
			$project = $this->Project->find('first', array(
				'fields' => array('Project.client_rate'),
				'conditions' => array(
					'Project.id' => $precision_invite['PrecisionInvite']['project_id']
				),
				'recursive' => -1
			));
			$survey_user_visit = $this->SurveyUserVisit->find('first', array(
				'conditions' => array(
					'SurveyUserVisit.user_id' => $precision_invite['PrecisionInvite']['user_id'],
					'SurveyUserVisit.survey_id' => $precision_invite['PrecisionInvite']['project_id']
				)
			));

			$mail_log = $this->MailLog->find('first', array(
				'fields' => array('MailLog.created'),
				'conditions' => array(
					'MailLog.user_id' => $precision_invite['PrecisionInvite']['user_id'],
					'MailLog.project_id' => $precision_invite['PrecisionInvite']['project_id']
				)
			));
			
			$user_router_log = $this->UserRouterLog->find('first', array(
				'fields' => array('UserRouterLog.created'),
				'conditions' => array(
					'UserRouterLog.user_id' => $precision_invite['PrecisionInvite']['user_id'],
					'UserRouterLog.parent_id' => '0'
				),
				'order' => 'UserRouterLog.id DESC'
			));
			$active_since_mail = false;
			
			if ($user_router_log && $mail_log) {
				if ($user_router_log['UserRouterLog']['created'] > $mail_log['MailLog']['created']) {
					$active_since_mail = true;
				}
			}

			fputcsv($fp, array(
				$precision_invite['PrecisionInvite']['precision_project_id'],
				$precision_invite['PrecisionInvite']['user_id'],
				$precision_invite['PrecisionInvite']['first_invited'],
				$precision_invite['PrecisionInvite']['last_seen'],
				$mail_log ? $mail_log['MailLog']['created']: '',
				$survey_user_visit ? $survey_user_visit['SurveyUserVisit']['created']: '',
				$survey_user_visit ? $survey_statuses[$survey_user_visit['SurveyUserVisit']['status']]: '',
				$project['Project']['client_rate'],
				$survey_user_visit ? $survey_user_visit['SurveyUserVisit']['accessed_from']: '',
				$active_since_mail ? 'Y': ''
			)); 
		}
		fclose($fp);

		$this->out('Wrote '.$filename);
	}
	
	private function write_notification_log($invite, $message) {
		$precision_notification_log = $this->PrecisionNotificationLog->find('first', array(
			'conditions' => array(
				'PrecisionNotificationLog.uid' => $invite['u_guid'],
				'PrecisionNotificationLog.precision_project_id' => $invite['p_id']
			)
		));
		
		if (!$precision_notification_log) {
			$this->PrecisionNotificationLog->create();
			$this->PrecisionNotificationLog->save(array('PrecisionNotificationLog' => array(
				'uid' => $invite['u_guid'],
				'precision_project_id' => $invite['p_id'],
				'status' => $message
			)));
		}	
	}

	public function generate_report() {
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => 'precision_sample.host',
				'Setting.deleted' => false
			)
		));

		if (!$settings) {
			$this->out('Required settings missing');
			return false;
		}

		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'precision'
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
			'fields' => array('PartnerUser.user_id', 'PartnerUser.uid'),
			'conditions' => array(
				'PartnerUser.partner' => 'precision',
				'PartnerUser.user_id' => $user_ids
			),
		));

		$total = count($partner_users);
		$this->out('Processing '. $total .' Precision panelists');

		$filename = WWW_ROOT . 'files/precision_invites.csv';
		$fp = fopen($filename, 'w');
		fputcsv($fp, array(
			'Precision Survey ID',
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
			$this->out($i . '/' . $total);

			$this->HttpSocket = new HttpSocket(array(
				'timeout' => 15,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));

			$response = $this->HttpSocket->post($settings['precision_sample.host'] . '/GetSurveys', json_encode(array(
				'UserGuid' => $partner_user['PartnerUser']['uid']
			)), array('header' => array(
				'Content-Type' => 'application/json'
			)));

			if ($response->code != 200) {
				$this->out('Precision API returned non-200 error');
				return false;
			}
			$body = json_decode($response, true);

			if (!isset($body['d'])) {
				$this->out('Returned unfamiliar data format');
				return false;
			}
			$surveys = json_decode($body['d'], true);

			if (!empty($surveys) && isset($surveys['Surveys']['Survey'])) {
				$precision_surveys = $surveys['Surveys'];
				// if its not a single array
				if (!isset($surveys['Surveys']['Survey']['ProjectId'])) {
					$precision_surveys = $surveys['Surveys']['Survey'];
				}
			}
			else {
				$this->out('There are no precision surveys');
				return false;
			}

			foreach ($precision_surveys as $survey) {
				$this->Project->unbindModel(array('hasMany' => array('SurveyPartner', 'ProjectOption', 'ProjectAdmin')));
				$this->Project->unbindModel(array('belongsTo' => array('Client', 'Group')));				
				$project = $this->Project->find('first', array(
					'fields' => array('Project.id', 'Project.status', 'Project.active', 'SurveyVisitCache.click', 'SurveyVisitCache.complete'),
					'conditions' => array(
						'Project.group_id' => $group['Group']['id'],
						'Project.mask' => $survey['ProjectId']
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
					$survey['ProjectId'],
					$survey['SurveyLength'],
					$project['Project']['id'],
					$partner_user['PartnerUser']['user_id'],
					$project['Project']['status'],
					$project['Project']['active'],
					$project['SurveyVisitCache']['click'],
					$project['SurveyVisitCache']['complete'],
					$count,
					$close_reason
				));
			}
		}
		fclose($fp);
		$this->out($filename);
	}

	public function send_email_invites() {
		$log_file = 'precision.email';
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array(
					'precision_sample.host', 
					'precision_sample.secret'
				),
				'Setting.deleted' => false
			)
		));

		if (!$settings) {
			$this->out('Required settings missing');
			return false;
		}

		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'precision'
			)
		));

		$all_projects = $this->Project->find('all', array(
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

		if (!$all_projects) {
			$this->lecho('Active precision projects not found.', $log_file);
			return;
		}
		$high_epc_projects = array();
		foreach ($all_projects as $project) {
			if ($project['SurveyVisitCache']['complete'] < 1) {
				continue;
			}

			$epc = 0;
			if (!empty($project['SurveyVisitCache']['epc'])) {
				$epc = $project['SurveyVisitCache']['epc'];
			}
			elseif (!empty($project['Project']['epc'])) {
				$epc = $project['Project']['epc'];
			}

			// Skip if epc is less then or equal to 25 cents
			if ($epc <= 25) {
				continue;
			}
			
			$mask = $project['Project']['mask'];
			$high_epc_projects[$mask] = $project;
		}

		if (empty($high_epc_projects)) {
			$this->lecho('No open, high epc precision project found.', $log_file);
			return;
		}

		foreach ($high_epc_projects as $project) {
			$this->out('Processing Project# ' . $project['Project']['id']);
			$this->HttpSocket = new HttpSocket(array(
				'timeout' => 15,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));

			try {
				$response = $this->HttpSocket->post($settings['precision_sample.host'] . '/GetMembers', json_encode(array(
					'ProjectId' => $project['Project']['mask'],
					'ApiKey' => $settings['precision_sample.secret'],
				)), array('header' => array(
					'Content-Type' => 'application/json'
				)));

				if ($response->code != 200) {
					$this->lecho('Precision API returned non-200 error', $log_file);
					continue;
				}
				$body = json_decode($response, true);
				if (!isset($body['d'])) {
					$this->out('Returned unfamiliar data format');
					continue;
				}
				$members = json_decode($body['d'], true);
				if (!$members) {
					$this->lecho('There are no members for Project# ' . $project['Project']['id'], $log_file);
					continue;
				}

				$this->lecho(count($members) . ' members found for Project#' . $project['Project']['id'], $log_file);
				foreach ($members as $member) {
					$partner_user = $this->PartnerUser->find('first', array(
						'conditions' => array(
							'PartnerUser.uid' => $member['ug']
						),
						'recursive' => -1,
					));
					if (!$partner_user) {
						$this->lecho('No user found for uid ' . $member['ug'], $log_file);
						continue;
					}

					$user = $this->User->find('first', array(
						'conditions' => array(
							'User.id' => $partner_user['PartnerUser']['user_id'],
							'User.active' => true,
							'User.deleted_on' => null,
							'User.hellbanned' => false,
							'User.last_touched >' => date(DB_DATETIME, strtotime('-7 days')),
						),
						'fields' => array(
							'User.id',
							'User.ref_id',
							'User.email'
						),
						'recursive' => -1,
					));
					if (!$user) {
						$this->out('User#' . $partner_user['PartnerUser']['user_id'] . ' is not active in last 7 days or does not exist');
						continue;
					}
					
					$survey_user_visit = $this->SurveyUserVisit->find('first', array(
						'fields' => array('SurveyUserVisit.id'),
						'conditions' => array(
							'SurveyUserVisit.survey_id' => $project['Project']['id'],
							'SurveyUserVisit.user_id' => $user['User']['id'],
						),
						'recursive' => -1
					));
					// panelist has already seen this project
					if ($survey_user_visit) {
						continue;
					}

					$precision_invite = $this->PrecisionInvite->find('first', array(
						'conditions' => array(
							'PrecisionInvite.user_id' => $user['User']['id'],
							'PrecisionInvite.project_id' => $project['Project']['id'],
						),
					));

					if (!empty($precision_invite) && $precision_invite['PrecisionInvite']['emailed']) {
						$this->lecho('Already notified User#' . $user['User']['id'] . ' for Project#' . $project['Project']['id'], $log_file);
						continue;
					}

					if (!$precision_invite) {
						$this->PrecisionInvite->create();
						$this->PrecisionInvite->save(array('PrecisionInvite' => array(
							'user_id' => $user['User']['id'],
							'project_id' => $project['Project']['id'],
							'first_invited' => date(DB_DATETIME),
							'emailed' => true
						)));
					}
					else {
						$this->PrecisionInvite->create();
						$this->PrecisionInvite->save(array('PrecisionInvite' => array(
							'id' => $precision_invite['PrecisionInvite']['id'],
							'emailed' => true,
						)), true, array('emailed'));
					}

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
					
					$response = $this->Notify->email($project, $user);
					//Log survey links
					CakeLog::write('precision.email', $response['survey_url']);
					$this->lecho('Email sent to User#' . $user['User']['id'] . ' for Project#' . $project['Project']['id'], $log_file);
				}
			}
			catch (Exception $e) {
				$this->lecho($e->getMessage(), $log_file, $log_key);
			}
		}
		$this->lecho('Finished', $log_file);
	}
}
