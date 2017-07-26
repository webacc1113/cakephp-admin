<?php

App::uses('CakeEmail', 'Network/Email');
App::uses('Controller', 'Controller');
App::uses('View', 'View');
App::import('Lib', 'Utilities');
App::import('Lib', 'MintVine');
App::uses('CakeResponse', 'Network');
App::uses('ComponentCollection', 'Controller');
App::uses('QueryEngineComponent', 'Controller/Component');
App::uses('HttpSocket', 'Network/Http');

class NotificationsShell extends AppShell {
	public $uses = array('Group', 'Project', 'SurveyUser', 'SurveyUserVisit', 'SurveyVisit', 'SurveyVisitCache', 'Qualification', 'QualificationStatistic', 'FedSurvey', 'ProjectLog', 'QualificationHistory', 'Nonce', 'MailQueue', 'SmsQueue', 'SmsLog', 'User', 'NotificationLog', 'QualificationUser', 'ProjectOption');
	
	/* queue mail notifications for ad-hoc projects */
	public function queue_notifications() {
		$ts_start = microtime(true);
		$groups = $this->Group->find('list', array(
			'fields' => array('Group.key', 'Group.id'),
			'conditions' => array(
				'Group.key' => array('socialglimpz', 'mintvine', 'spectrum', 'fulcrum', 'points2shop')
			)
		));
		if (empty($groups)) {
			$this->out('You must define some groups to notify'); 
			return; 
		}
		
		$this->Project->unbindModel(array('hasMany' => array('SurveyPartner', 'ProjectOption', 'ProjectAdmin')));
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.group_id' => $groups,
				'Project.active' => true,
				'Project.status' => PROJECT_STATUS_OPEN,
				'Project.temp_qualifications' => true
			),
			'order' => 'Project.priority DESC'
		)); 

		if (!$projects) {
			$this->out('No projects found');
			return false;
		}
		
		$total_count = count($projects);
		$this->out('Found '.$total_count.' total projects');
		$urgent_mail_counter = $high_priority_mail_counter = $mail_counter = 0; // count for queued projects
		
		foreach ($projects as $project) {
			$this->out('Starting '.$project['Project']['id']); 
			$last_checked = $this->ProjectOption->find('first', array(
				'conditions' => array(
					'ProjectOption.project_id' => $project['Project']['id'],
					'ProjectOption.name' => 'notifications.last.checked',
				)
			));
			
			// only check to send notifications every 10 minutes per project
			if ($last_checked && strtotime('-10 minutes') < strtotime($last_checked['ProjectOption']['value'])) {
				$this->out("\t".'Skipping because of recent activity'); 
				continue; 
			}
			
			// no active qualifications; skip this project until we have something to look at
			$this->Qualification->bindModel(array('hasOne' => array('QualificationStatistic')));
			$qualifications = $this->Qualification->find('all', array(
				'fields' => array('Qualification.quota', 'QualificationStatistic.completes'),
				'conditions' => array(
					'Qualification.project_id' => $project['Project']['id'],
					'Qualification.deleted' => null,
					'Qualification.active' => true,
					'Qualification.parent_id' => null
				)
			));
			if (!$qualifications) {
				$this->out("\t".'Skipping because of no qualifications'); 
				continue;
			}
			
			// skip this if quota is less than 5
			$completes_remaining = $project['Project']['quota'] - $project['SurveyVisitCache']['complete']; 
			if ($completes_remaining <= 5) {
				$this->out("\t".'Skipping because of low project quota ('.$completes_remaining.')'); 
				continue;
			}
			
			$skip = true;
			$qualifications_quota = array();
			foreach ($qualifications as $qualification) {
				// any qualification with quota greater than 5 ensures a send here
				$qualifications_remaining = $qualification['Qualification']['quota'] - $qualification['QualificationStatistic']['completes']; 
				$qualifications_quota[] = $qualifications_remaining;
				if ($qualifications_remaining >= 5) {
					$skip = false;
					break; 
				}
			}
			if ($skip) {
				$this->out("\t".'Skipping because of low qualifications quota ('.implode(', ', $qualifications_quota).')'); 
				continue;
			}
			
			// is this project new? if so - wait to send email for 10 minutes
			// should this be clicks? testing could throw it out
			if (in_array($project['Project']['priority'], array(PROJECT_PRIORITY_NORMAL, PROJECT_PRIORITY_HIGH)) 
				&& $project['SurveyVisitCache']['complete'] == 0 
				&& strtotime('-10 minutes') < strtotime($project['Project']['started'])) 
			{
				$this->out("\t".'New project created: wait 10 minutes before executing email sends'); 
				continue;
			}
			
			if ($project['Client']['do_not_autolaunch']) {
				$this->out("\t".'Do not autolaunch this client: '.$project['Client']['client_name']); 
				continue;
			}
			
			// need to be smart about when we're setting the last checked value; the rules above should not affect our re-hit ability
			if (!$last_checked) {
				$this->ProjectOption->getDataSource()->begin();
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'project_id' => $project['Project']['id'],
					'name' => 'notifications.last.checked',
					'value' => date(DB_DATETIME)
				))); 
				$last_checked = $this->ProjectOption->find('first', array(
					'conditions' => array(
						'ProjectOption.id' => $this->ProjectOption->getInsertId()
					)
				)); 
				$this->ProjectOption->getDataSource()->commit();
			} 
			else {
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'id' => $last_checked['ProjectOption']['id'],
					'value' => date(DB_DATETIME)
				)), true, array('value'));
			} 
			
			// everything here is considered a "known" error which is fine to be ignored by last_checked
			
			// we need to figure out if this is a "good" project - determined by ... number of completes
			$is_good_project = false; 
			$completes_count = $this->SurveyVisit->find('count', array(
				'conditions' => array(
					'SurveyVisit.survey_id' => $project['Project']['id'],
					'SurveyVisit.type' => SURVEY_COMPLETED
				),
				'recursive' => -1
			)); 
			$is_good_project = $completes_count > 0;
			
			// determine # of sends to make...
			$invited = $this->SurveyUser->find('list', array(
				'fields' => array('SurveyUser.id', 'SurveyUser.user_id'),
				'conditions' => array(
					'SurveyUser.survey_id' => $project['Project']['id']
				),
				'recursive' => -1
			)); 
			$invited = $this->User->find('list', array(
				'fields' => array('User.id', 'User.id'),
				'conditions' => array(
					'User.hellbanned' => false,
					'User.send_email' => true,
					'User.deleted_on' => null,
					'User.send_survey_email' => true,
					'User.id' => $invited
				),
				'recursive' => -1
			)); 
			$emailed = $this->SurveyUser->find('list', array(
				'fields' => array('SurveyUser.id', 'SurveyUser.user_id'),
				'conditions' => array(
					'SurveyUser.survey_id' => $project['Project']['id'],
					'SurveyUser.notification' => '1'
				),
				'recursive' => -1
			)); 
			
			$count_invited = count($invited);
			$count_emailed = count($emailed); 
			if ($count_invited == count($emailed)) {
				$this->out("\t".'Skipping because of maximum invited count ('.$count_invited.')'); 
				continue;
			}
			
			// only send emails once every hour
			$last_emailed = $this->ProjectOption->find('first', array(
				'conditions' => array(
					'ProjectOption.project_id' => $project['Project']['id'],
					'ProjectOption.name' => 'notification.last_emailed'
				)
			)); 
			if ($last_emailed && strtotime('-1 hour') < strtotime($last_emailed['ProjectOption']['value'])) {
				// move click value forward so we don't try to run it again until an hour passes
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'id' => $last_checked['ProjectOption']['id'],
					'value' => date(DB_DATETIME, strtotime('+50 minutes')) // eligible to check for clicks in 50 minutes + 10 minutes
				)), true, array('value'));
				
				$this->out("\t".'Skipping because of last email send was less than an hour ago'); 
				continue;
			}
			
			if ($last_emailed) {
				// count total completes since last visit
				$complete_count_since_last_email_send = $this->SurveyVisit->find('count', array(
					'conditions' => array(
						'SurveyVisit.survey_id' => $project['Project']['id'],
						'SurveyVisit.type' => SURVEY_COMPLETED,
						'SurveyVisit.created >=' => $last_emailed['ProjectOption']['value']
					)
				)); 
				if ($complete_count_since_last_email_send == 0) {
					$this->out("\t".'Skipping because of no completes since last email'); 
					continue;
				}
			}
						
			App::import('Vendor', 'sqs');
			$sqs_settings = $this->Setting->find('list', array(
				'fields' => array('Setting.name', 'Setting.value'),
				'conditions' => array(
					'Setting.name' => array(
						'sqs.access.key', 
						'sqs.access.secret', 
						'sqs.notifications.email.queue', 
						'sqs.notifications.email.priority.queue',
						'sqs.notifications.email.urgent.queue'
					),
					'Setting.deleted' => false
				)
			));

			if ($project['Project']['priority'] == PROJECT_PRIORITY_URGENT) {
				$sqs_queue = $sqs_settings['sqs.notifications.email.urgent.queue'];
				$urgent_mail_counter++;
				$this->out('Scheduled #'.$project['Project']['id'].' for urgent mailing'); 
			}
			elseif ($is_good_project || $project['Project']['priority'] == PROJECT_PRIORITY_HIGH) {
				$sqs_queue = $sqs_settings['sqs.notifications.email.priority.queue'];
				$high_priority_mail_counter++;
				$this->out('Scheduled #'.$project['Project']['id'].' for high priority mailing'); 
			}
			else {
				$sqs_queue = $sqs_settings['sqs.notifications.email.queue'];
				$mail_counter++;
				$this->out('Scheduled #'.$project['Project']['id'].' for mailing'); 
			}
			
			$sqs = new SQS($sqs_settings['sqs.access.key'], $sqs_settings['sqs.access.secret']);
			$response = $sqs->sendMessage($sqs_queue, json_encode(array(
				'type' => 'email',
				'project_id' => $project['Project']['id']
			)));
			
			if (isset($response) && isset($response['MessageId'])) {
				if (!$last_emailed) {
					$this->ProjectOption->create();
					$this->ProjectOption->save(array('ProjectOption' => array(
						'name' => 'notification.last_emailed',
						'value' => date(DB_DATETIME),
						'project_id' => $project['Project']['id']
					))); 
				}
				else {
					$this->ProjectOption->create();
					$this->ProjectOption->save(array('ProjectOption' => array(
						'id' => $last_emailed['ProjectOption']['id'],
						'value' => date(DB_DATETIME)
					)), true, array('value'));
				}
			}
		}
		
		$this->out('Time spent: '.(round(microtime(true) - $ts_start, 4)));
		$this->out('Queued: '.$urgent_mail_counter.' urgent jobs, '.$high_priority_mail_counter.' high priority jobs, '.$mail_counter.' regular jobs');
	}
		
	public function process() {
		$log_file = 'notifications.process';
		if (!isset($this->log_key)) {
			$this->log_key = strtoupper(Utils::rand(4));
		}
		App::import('Vendor', 'sqs');
		$sqs_settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array(
					'sqs.access.key', 
					'sqs.access.secret', 
					'sqs.notifications.email.queue', 
					'sqs.notifications.email.urgent.queue',
					'sqs.notifications.email.priority.queue'
				),
				'Setting.deleted' => false
			)
		));
		$this->lecho('Starting notifications', $log_file, $this->log_key); 
		
		$order = array(
			'urgent' => 'sqs.notifications.email.urgent.queue',
			'priority' => 'sqs.notifications.email.priority.queue',
			'mail' => 'sqs.notifications.email.queue'			
		);
		
		foreach ($order as $priority => $sqs_queue) {
			// URGENT PRIORITY SENDS FIRST!
			while (true) {
				$sqs = new SQS($sqs_settings['sqs.access.key'], $sqs_settings['sqs.access.secret']);
				$results = $sqs->receiveMessage($sqs_settings[$sqs_queue]);
				if (!empty($results['Messages'])) {
					$notification_information = json_decode($results['Messages'][0]['Body'], true);
					$this->lecho('Processing '.$priority.' #'.$notification_information['project_id'].' ('.$notification_information['type'].')', $log_file, $this->log_key); 
				
					if ($notification_information['type'] == 'email' && !empty($notification_information['project_id'])) {
						$sqs->deleteMessage($sqs_settings[$sqs_queue], $results['Messages'][0]['ReceiptHandle']);
						$emailed = $this->email($notification_information['project_id']); 
						if ($emailed !== false) {
							$this->lecho('Sent '.$priority.' #'.$notification_information['project_id'].' ('.$emailed.')', $log_file, $this->log_key); 
						}
						else {
							$this->lecho('Failed to send '.$priority.' #'.$notification_information['project_id'].' try again later', $log_file, $this->log_key); 
						}
					}
				}
				else {
					break;
				}
			}
		}
	}
	
	public function test() {
		if (!isset($this->args[0])) {
			return false;
		}
		$this->manual($this->args[0], true); 
	}
	
	
	// given a project id, this service will make a best approximation for how to email the project 
	// returns false if the send failed; otherwise returns a count of the panelists touched
	private function manual($project_id, $force_send = false) {

		$log_file = 'notifications.manual';
		if (!isset($this->log_key)) {
			$this->log_key = strtoupper(Utils::rand(4));
		}
		
		// get the project and check that it's still active
		$project = $this->Project->find('first', array(
			'fields' => array(
				'Project.id', 
				'Project.code', 
				'Project.started',
				'Project.active', 
				'Project.mask', 
				'Project.status', 
				'Project.award', 
				'Project.est_length', 
				'Project.desktop', 
				'Project.mobile', 
				'Project.soft_launch',
				'Project.tablet', 
				'Project.router', 
				'Project.description', 
				'Project.bid_ir', 
				'Project.temp_qualifications', 
				'Project.priority', 
				'Project.quota', 
				'Group.key',
				'SurveyVisitCache.click', 
				'SurveyVisitCache.complete', 
				'SurveyVisitCache.ir'
			),
			'conditions' => array(
				'Project.id' => $project_id
			)
		));
		
		// if the project isn't open, don't send emails
		if (!$project || !$project['Project']['active'] || in_array($project['Project']['status'], array(PROJECT_STATUS_CLOSED, PROJECT_STATUS_INVOICED))) {
			$this->lecho('Skip: '.$project['Project']['id'].' is not currently active', $log_file, $this->log_key); 
			return false;
		} 

		$unnotified_panelists = $this->SurveyUser->find('count', array(
			'fields' => array('User.id'),
			'conditions' => array(
				'SurveyUser.notification' => '0',	
				'User.hellbanned' => false,
				'User.send_email' => true,
				'User.deleted_on' => null,
				'User.send_survey_email' => true,
			)
		));
				
		// find how many panelists haven't been emailed yet
		$not_notified_panelists = $this->SurveyUser->find('list', array(
			'fields' => array('SurveyUser.id', 'SurveyUser.user_id'),
			'conditions' => array(
				'SurveyUser.survey_id' => $project['Project']['id'],
				'SurveyUser.notification is null'
			),
			'recursive' => -1
		));
		
		// nobody left to email...
		if (count($not_notified_panelists) == 0) {
			$this->lecho('Fail: No panelists left to send to '.$project['Project']['id'], $log_file, $this->log_key); 
			return (int) 0; 
		}
		
		// if there are no completes left, do not send
		$remaining_completes = $project['Project']['quota'] - $project['SurveyVisitCache']['complete']; 
		if ($remaining_completes <= 0) {
			$this->lecho('Fail: Remaining completes too low '.$project['Project']['id'], $log_file, $this->log_key); 
			return (int) 0; 
		}
		
		// secondary rule: less than 10 quota means we probably cannot fulfill it in time
		if ($remaining_completes <= 5 && $project['Project']['priority'] != PROJECT_PRIORITY_URGENT) {
			$this->lecho('Fail: Quota too low '.$project['Project']['id'], $log_file, $this->log_key); 
			return (int) 0; 
		}
		
		// initialize components		
        $collection = new ComponentCollection();
        $this->QueryEngine = new QueryEngineComponent($collection);
        $controller = new Controller();
        $this->QueryEngine->initialize($controller);
		
		$this->lecho('Loaded #'.$project['Project']['id'], $log_file, $this->log_key); 
				
		
		$users = $this->SurveyUser->find('all', array(
			'fields' => array('User.id', 'User.ref_id', 'User.email', 'User.last_touched'),
			'conditions' => array(
				'SurveyUser.survey_id' => $project['Project']['id'],
				'SurveyUser.notification is null',
				'User.hellbanned' => false,
				'User.send_email' => true,
				'User.deleted_on' => null,
				'User.send_survey_email' => true,
			),
			'limit' => '10000', // hard limit; this could be a loop in theory... very expensive though
			'order' => 'User.last_touched DESC'
		));
		$this->lecho('Found '.count($users).' panelists based on qual/email values', $log_file, $this->log_key); 

		$i = 0; 
		if (!empty($users)) {
			// for capturing the output of a view into a string
			$this->autoRender = false;
		
			$survey_subject = empty($project['Project']['description']) ? 'Exciting Survey Opportunity': $project['Project']['description'];
			//  if the project is mobile-only then the subject should prefix with MOBILE ONLY - 
			if ($project['Project']['mobile'] && !$project['Project']['desktop'] && !$project['Project']['tablet']) {
				$survey_subject = 'MOBILE ONLY - '.$survey_subject;
			}

			$survey_award = $project['Project']['award'];
			$survey_length = $project['Project']['est_length'];
		
			$is_desktop = $project['Project']['desktop'];
			$is_mobile = $project['Project']['mobile'];
			$is_tablet = $project['Project']['tablet'];
			$survey_id = $project['Project']['id'];

			if ($project['Project']['router']) {
				$template = 'survey-funnel';
			}
			else {
				$template = 'survey';
			}
			$setting = $this->Setting->find('list', array(
				'fields' => array('Setting.name', 'Setting.value'),
				'conditions' => array(
					'Setting.name' => array('cdn.url'),
					'Setting.deleted' => false
				)
			));
			if (!empty($setting['cdn.url']) && (!defined('IS_DEV_INSTANCE') || !IS_DEV_INSTANCE)) {
				Configure::write('App.cssBaseUrl', $setting['cdn.url'] . '/');
				Configure::write('App.jsBaseUrl', $setting['cdn.url'] . '/');
				Configure::write('App.imageBaseUrl', $setting['cdn.url'] . '/img/');
			}
			$time_start = microtime(true);
			// grab the email template
			$view = new View($controller, false);
			$view->layout = 'Emails/html/default';
			$nonce = '{{nonce}}';
			$survey_url = '{{survey_url}}';
			$unsubscribe_link = '{{unsubscribe_link}}';
			$view->set(compact('nonce', 'survey_url', 'unsubscribe_link', 'survey_award', 'survey_length', 'is_desktop', 'is_mobile', 'is_tablet', 'survey_id'));
			$view->viewPath = 'Emails/html';
			$email_body = $view->render($template);
			$this->autoRender = true;
						
			$added_user_ids = array();
			$this->out('Starting '.count($users).' users.');
			foreach ($users as $user) {				
				$survey_user = $this->SurveyUser->find('first', array(
					'fields' => array('SurveyUser.id', 'SurveyUser.notification'),
					'conditions' => array(
						'SurveyUser.survey_id' => $project['Project']['id'],
						'SurveyUser.user_id' => $user['User']['id'],
					),
					'recursive' => -1
				));
				// somehow wasn't invited? invitation could have been rescinded
				if (!$survey_user) {
					continue;
				}
				// if we've already been notified
				if ($survey_user && !is_null($survey_user['SurveyUser']['notification'])) {
					continue;
				}
				
				$this->SurveyUser->create();
				$this->SurveyUser->save(array('SurveyUser' => array(
					'id' => $survey_user['SurveyUser']['id'],
					'notification' => '1'
				)), true, array('notification')); 
				
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
				$i++;
				$time_start = microtime(true);
				$total_time_start = microtime(true);
				$user_id = $user['User']['id'];
				// generate the email
				$nonce = substr($user['User']['ref_id'], 0, 21).'-'.substr(Utils::rand(10), 0, 10);
				$survey_url = HOSTNAME_WWW.'/surveys/pre/'.$project['Project']['id'].'/?nonce='.$nonce.'&from=email&key='.$project['Project']['code'];
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
					$user_id
				), $email_body);
							
				$time_start = microtime(true);
				// create the one-time nonce
				$this->Nonce->create();
				$this->Nonce->save(array('Nonce' => array(
					'item_id' => $project['Project']['id'],
					'item_type' => 'survey',
					'user_id' => $user_id,
					'nonce' => $nonce
				)), false);
				
				// queue into mail queue if user has opted into emails
				$time_start = microtime(true);
				$this->MailQueue->create();
				$this->MailQueue->save(array('MailQueue' => array(
					'user_id' => $user_id,
					'email' => $user['User']['email'],
					'subject' => $survey_subject,
					'project_id' => $project['Project']['id'],
					'body' => $customized_email_body,
					'status' => 'Queued'
				)));
			}
			
			$write_log = true;
			if ($i == 0) {
				$count = $this->ProjectLog->find('count', array(
					'conditions' => array(
						'ProjectLog.project_id' => $project['Project']['id'],
						'ProjectLog.type' => 'notifications.email',
						'ProjectLog.internal_description' => '0'
					)
				));
				if ($count > 1) {
					// once we've written a 0 notifications; don't need to write again
					// still want to write it at least once so logs show an effort has been made
					$write_log = false;
				}
			}
			
			$email_count = $this->SurveyUser->find('count', array(
				'conditions' => array(
					'SurveyUser.survey_id' => $project['Project']['id'],
					'SurveyUser.notification' => '1'
				)
			)); 
			
			$survey_visit_cache = $this->SurveyVisitCache->find('first', array(
				'fields' => array('SurveyVisitCache.emailed', 'SurveyVisitCache.id'),
				'conditions' => array(
					'SurveyVisitCache.survey_id' => $project['Project']['id']
				)
			)); 
			if ($survey_visit_cache && $survey_visit_cache['SurveyVisitCache']['emailed'] != $email_count) {
				$this->SurveyVisitCache->create();
				$this->SurveyVisitCache->save(array('SurveyVisitCache' => array(
					'id' => $survey_visit_cache['SurveyVisitCache']['id'],
					'emailed' => $email_count
				)), true, array('emailed')); 
				$this->out('Email count set to '.$email_count); 
			}
							
			if ($write_log) {
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'notifications.email',
					'description' => 'Sent emails to '.$i.' panelists',
					'internal_description' => $i
				)));
			}
						
		}
		$this->lecho('Complete: '.$i, $log_file, $this->log_key); 
		return (int) $i; 
	}
	
	// given a project id, this service will make a best approximation for how to email the project 
	// returns false if the send failed; otherwise returns a count of the panelists touched
	private function email($project_id, $force_send = false) {

		$log_file = 'notifications.email';
		if (!isset($this->log_key)) {
			$this->log_key = strtoupper(Utils::rand(4));
		}
		
		// get the project and check that it's still active
		$project = $this->Project->find('first', array(
			'fields' => array(
				'Project.id', 
				'Project.code', 
				'Project.started',
				'Project.active', 
				'Project.mask', 
				'Project.status', 
				'Project.award', 
				'Project.est_length', 
				'Project.desktop', 
				'Project.mobile', 
				'Project.soft_launch',
				'Project.tablet', 
				'Project.router', 
				'Project.description', 
				'Project.bid_ir', 
				'Project.temp_qualifications', 
				'Project.priority', 
				'Project.quota', 
				'Group.key',
				'SurveyVisitCache.click', 
				'SurveyVisitCache.complete', 
				'SurveyVisitCache.ir'
			),
			'conditions' => array(
				'Project.id' => $project_id
			)
		));
		
		// if the project isn't open, don't send emails
		if (!$project || !$project['Project']['active'] || in_array($project['Project']['status'], array(PROJECT_STATUS_CLOSED, PROJECT_STATUS_INVOICED))) {
			$this->lecho('Skip: '.$project['Project']['id'].' is not currently active', $log_file, $this->log_key); 
			return false;
		} 

		// Low priority projects should also not send any emails out. 
		if ($project['Project']['priority'] == PROJECT_PRIORITY_LOW) {
			$this->lecho('Skip: #'.$project['Project']['id'].' is low priority project. Do not send any emails out.', $log_file, $this->log_key); 
			return false;	
		}
		
		// only for the new system; in the old system, survey_users indicates an email delivery so this script is pretty useless
		if (!$project['Project']['temp_qualifications']) {
			$this->lecho('Fail: '.$project['Project']['id'].' is not QQQ', $log_file, $this->log_key); 
			return (int) 0; 
		}
				
		// find out matched panelists from qe2
		$unnotified_panelists = 0;
		$qualification_ids = $this->Qualification->find('list', array(
			'fields' => array('Qualification.id'),
			'conditions' => array(
				'Qualification.project_id' => $project['Project']['id'],
				'Qualification.parent_id is null',
				'Qualification.deleted is null'
			)
		));
		if (empty($qualification_ids)) {
			return false; 
		}
		
		// for now, disable the user join; the right indexes aren't being coerced
		$this->QualificationUser->bindModel(array('belongsTo' => array(
			'User' => array(
				'type' => 'INNER'
			)
		)));
		$unnotified_panelists = $this->QualificationUser->find('count', array(
			'fields' => array('User.id'),
			'conditions' => array(
				'QualificationUser.qualification_id' => $qualification_ids,
				'QualificationUser.notification' => '0',	
				'User.hellbanned' => false,
				'User.send_email' => true,
				'User.deleted_on' => null,
				'User.send_survey_email' => true,
			)
		));
				
		// find how many panelists haven't been emailed yet
		$not_notified_panelists = $this->SurveyUser->find('list', array(
			'fields' => array('SurveyUser.id', 'SurveyUser.user_id'),
			'conditions' => array(
				'SurveyUser.survey_id' => $project['Project']['id'],
				'SurveyUser.notification is null'
			),
			'recursive' => -1
		));
		
		// nobody left to email...
		if (count($not_notified_panelists) == 0) {
			$this->lecho('Fail: No panelists left to send to '.$project['Project']['id'], $log_file, $this->log_key); 
			return (int) 0; 
		}
		
		// if there are no completes left, do not send
		$remaining_completes = $project['Project']['quota'] - $project['SurveyVisitCache']['complete']; 
		if ($remaining_completes <= 0) {
			$this->lecho('Fail: Remaining completes too low '.$project['Project']['id'], $log_file, $this->log_key); 
			return (int) 0; 
		}
		
		// secondary rule: less than 10 quota means we probably cannot fulfill it in time
		if ($remaining_completes <= 5 && $project['Project']['priority'] != PROJECT_PRIORITY_URGENT) {
			$this->lecho('Fail: Quota too low '.$project['Project']['id'], $log_file, $this->log_key); 
			return (int) 0; 
		}
		
		// sanity check to prevent multiple email sends
		$last_email_send = $this->ProjectOption->find('first', array(
			'fields' => array('ProjectOption.id', 'ProjectOption.value'),
			'conditions' => array(
				'ProjectOption.project_id' => $project['Project']['id'],
				'ProjectOption.name' => 'qqq.notifications'
			)
		));
		if (!$force_send && $last_email_send && strtotime($last_email_send['ProjectOption']['value']) > strtotime('-1 hour')) {
			$this->lecho('Skip: Last sent '.$last_email_send['ProjectOption']['value'].' - please wait at least one hour', $log_file, $this->log_key); 
			return false; 
		} 
		if (!$last_email_send) {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'project_id' => $project['Project']['id'],
				'name' => 'qqq.notifications',
				'value' => date(DB_DATETIME)
			)));			
		}
		else {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'id' => $last_email_send['ProjectOption']['id'],
				'value' => date(DB_DATETIME)
			)), true, array('value'));
		} 
		
		// initialize components		
        $collection = new ComponentCollection();
        $this->QueryEngine = new QueryEngineComponent($collection);
        $controller = new Controller();
        $this->QueryEngine->initialize($controller);
		
		$this->lecho('Loaded #'.$project['Project']['id'], $log_file, $this->log_key); 
				
		// we iterate through only the parent ones; the sub-quals (facets) are enforced only at router time
		$this->Qualification->bindModel(array(
			'hasOne' => array('QualificationStatistic')
		));
		$qualifications = $this->Qualification->find('all', array(
			'conditions' => array(
				'Qualification.project_id' => $project['Project']['id'],
				'Qualification.parent_id is null',
				'Qualification.deleted is null',
				'Qualification.active' => true
			)
		));

		if (!$qualifications) {
			$this->lecho('Failed: No qualifications loaded for #'.$project['Project']['id'], $log_file, $this->log_key); 
			// if there are no qualifications, it's an offerwall-pull project so we'd need to handle this as a special case
			return (int) 0; 
		}

		$this->lecho('Iterating through '.count($qualifications).' qualifications for project #'.$project['Project']['id'], $log_file, $this->log_key); 
		
		// iterate through each qualification; we do not worry about sub-qualifiations as the router will deal with them
		foreach ($qualifications as $qualification) {
			$this->lecho('Starting qualification #'.$qualification['Qualification']['id'], $log_file, $this->log_key); 
			if (!is_null($qualification['Qualification']['quota'])) {
				$quota = $qualification['Qualification']['quota']; 
			}
			else {
				$quota = $project['Project']['quota'];
			}
			
			if ($project['SurveyVisitCache']['click'] > 0 && $project['SurveyVisitCache']['complete'] > 0) {
				$ir = round($project['SurveyVisitCache']['complete'] / $project['SurveyVisitCache']['click'] * 100);
			}
			else {
				$ir = $project['Project']['bid_ir'];
			}
			if (empty($ir)) {
				$ir = 15; 
			}
			
			// figure out IR
			if ($qualification['QualificationStatistic']['clicks'] > 0 && $qualification['QualificationStatistic']['completes'] > 0) {
				$ir = round($qualification['QualificationStatistic']['completes'] / $qualification['QualificationStatistic']['clicks'] * 100);
			}
			
			$this->lecho('IR calculated at '.$ir, $log_file, $this->log_key); 
			
			// figure out the quota multiplier on a project; factor in IR of the project along with the overall CTR we have on emails
			$overall_ctr = (18 / 100); // our overall CTR on email invites			
			$multiplier = round(1 / ($ir / 100 * $overall_ctr)); 
			if ($project['Project']['priority'] == PROJECT_PRIORITY_URGENT) {
				$multiplier = $multiplier * 3; 
				$max_emails = 3500;
			}
			elseif ($project['Project']['priority'] == PROJECT_PRIORITY_HIGH || $project['SurveyVisitCache']['complete'] > 0) {
				$multiplier = $multiplier * 2; 
				$max_emails = 2500;
			}
			else {
				$multiplier = round($multiplier/ 2); 
				$max_emails = 1000;
			}
			if ($multiplier < 1) {
				$multiplier = 1; 
			}
			if ($multiplier > 10) {
				$multiplier = 10; 
			}
			
			// soft launch hard overwrites any priority effects
			if ($project['Project']['soft_launch']) {
				$multiplier = round(1 / ($ir / 100)); // set the multipler equivalent 
			}
			
			$this->lecho('Multiplier calculated at '.$multiplier, $log_file, $this->log_key); 
			
			// todo: this is currently supported in the old system; we'll have to find a new way to deal with these
			if (is_null($quota)) {
				$this->lecho("\t".'... SKIPPING (null quota)', $log_file, $this->log_key); 
				continue;
			}
			$available_inventory = (int)$quota - (int)$qualification['QualificationStatistic']['completes']; 			
			$project_available_inventory = $project['Project']['quota'] - $project['SurveyVisitCache']['complete']; 
			if ($project_available_inventory < $available_inventory) {
				$available_inventory = $project_available_inventory;
				// this isn't perfect; we should weight them later
				$available_inventory = floor($available_inventory / count($qualifications)); 
			}

			$this->lecho('Available inventory calculated at '.$available_inventory, $log_file, $this->log_key); 
			
			// projects with less than 10 quota left will not execute in time
			if ($available_inventory <= 10) {
				$this->lecho("\t".'... SKIPPING (< 10 completes left)', $log_file, $this->log_key); 
				continue;
			}
			
			// determine the total count to reach out to
			$total_notification_count = $available_inventory * $multiplier;
			
			if ($total_notification_count == 0) {
				$this->lecho("\t".'... SKIPPING (0 panelists to notify)', $log_file, $this->log_key); 
				continue;
			}
			if ($total_notification_count > $max_emails) {
				$this->lecho("\t".'... RESET count down to '.$max_emails.' (total count went to '.$total_notification_count.')', $log_file, $this->log_key); 
				$total_notification_count = $max_emails;
			}
			$this->lecho("\t".'... NOTIFYING '.$total_notification_count.' panelists', $log_file, $this->log_key); 			
			$this->QualificationUser->bindModel(array('belongsTo' => array(
				'User' => array(
					'type' => 'INNER'
				)
			)));
			$users = $this->QualificationUser->find('all', array(
				'fields' => array('User.id', 'User.ref_id', 'User.email', 'QualificationUser.id', 'User.last_touched'),
				'conditions' => array(
					'QualificationUser.qualification_id' => $qualification['Qualification']['id'],
					'User.hellbanned' => false,
					'User.send_email' => true,
					'User.deleted_on' => null,
					'User.send_survey_email' => true,
				),
				'limit' => '10000', // hard limit; this could be a loop in theory... very expensive though
				'order' => 'User.last_touched DESC'
			));
			$this->lecho('Found '.count($users).' panelists based on qual/email values', $log_file, $this->log_key); 

			$i = 0; 
			if (!empty($users)) {
				// for capturing the output of a view into a string
				$this->autoRender = false;
			
				$survey_subject = empty($project['Project']['description']) ? 'Exciting Survey Opportunity': $project['Project']['description'];
				//  if the project is mobile-only then the subject should prefix with MOBILE ONLY - 
				if ($project['Project']['mobile'] && !$project['Project']['desktop'] && !$project['Project']['tablet']) {
					$survey_subject = 'MOBILE ONLY - '.$survey_subject;
				}

				$survey_award = $project['Project']['award'];
				$survey_length = $project['Project']['est_length'];
			
				$is_desktop = $project['Project']['desktop'];
				$is_mobile = $project['Project']['mobile'];
				$is_tablet = $project['Project']['tablet'];
				$survey_id = $project['Project']['id'];

				if ($project['Project']['router']) {
					$template = 'survey-funnel';
				}
				else {
					$template = 'survey';
				}
				$setting = $this->Setting->find('list', array(
					'fields' => array('Setting.name', 'Setting.value'),
					'conditions' => array(
						'Setting.name' => array('cdn.url'),
						'Setting.deleted' => false
					)
				));
				if (!empty($setting['cdn.url']) && (!defined('IS_DEV_INSTANCE') || !IS_DEV_INSTANCE)) {
					Configure::write('App.cssBaseUrl', $setting['cdn.url'] . '/');
					Configure::write('App.jsBaseUrl', $setting['cdn.url'] . '/');
					Configure::write('App.imageBaseUrl', $setting['cdn.url'] . '/img/');
				}
				$time_start = microtime(true);
				// grab the email template
				$view = new View($controller, false);
				$view->layout = 'Emails/html/default';
				$nonce = '{{nonce}}';
				$survey_url = '{{survey_url}}';
				$unsubscribe_link = '{{unsubscribe_link}}';
				$view->set(compact('nonce', 'survey_url', 'unsubscribe_link', 'survey_award', 'survey_length', 'is_desktop', 'is_mobile', 'is_tablet', 'survey_id'));
				$view->viewPath = 'Emails/html';
				$email_body = $view->render($template);
				$this->autoRender = true;
							
				$added_user_ids = array();
				$this->out('Starting '.count($users).' users.');
				foreach ($users as $user) {
					// do not oversend
					if ($i >= $total_notification_count) {
						break;
					}
					
					$survey_user = $this->SurveyUser->find('first', array(
						'fields' => array('SurveyUser.id', 'SurveyUser.notification'),
						'conditions' => array(
							'SurveyUser.survey_id' => $project['Project']['id'],
							'SurveyUser.user_id' => $user['User']['id'],
						),
						'recursive' => -1
					));
					// somehow wasn't invited? invitation could have been rescinded
					if (!$survey_user) {
						continue;
					}
					// if we've already been notified
					if ($survey_user && !is_null($survey_user['SurveyUser']['notification'])) {
						continue;
					}
					
					$this->SurveyUser->create();
					$this->SurveyUser->save(array('SurveyUser' => array(
						'id' => $survey_user['SurveyUser']['id'],
						'notification' => '1'
					)), true, array('notification')); 
					
					$this->QualificationUser->create();
					$this->QualificationUser->save(array('QualificationUser' => array(
						'id' => $user['QualificationUser']['id'],
						'notification' => '1',
						'notification_timestamp' => date(DB_DATETIME),
					)), true, array('notification', 'notification_timestamp')); 
					
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
					$i++;
					$time_start = microtime(true);
					$total_time_start = microtime(true);
					$user_id = $user['User']['id'];
					// generate the email
					$nonce = substr($user['User']['ref_id'], 0, 21).'-'.substr(Utils::rand(10), 0, 10);
					$survey_url = HOSTNAME_WWW.'/surveys/pre/'.$project['Project']['id'].'/?nonce='.$nonce.'&from=email&key='.$project['Project']['code'];
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
						$user_id
					), $email_body);
								
					$time_start = microtime(true);
					// create the one-time nonce
					$this->Nonce->create();
					$this->Nonce->save(array('Nonce' => array(
						'item_id' => $project['Project']['id'],
						'item_type' => 'survey',
						'user_id' => $user_id,
						'nonce' => $nonce
					)), false);
					
					// queue into mail queue if user has opted into emails
					$time_start = microtime(true);
					$this->MailQueue->create();
					$this->MailQueue->save(array('MailQueue' => array(
						'user_id' => $user_id,
						'email' => $user['User']['email'],
						'subject' => $survey_subject,
						'project_id' => $project['Project']['id'],
						'body' => $customized_email_body,
						'status' => 'Queued'
					)));
				}
				
				$write_log = true;
				if ($i == 0) {
					$count = $this->ProjectLog->find('count', array(
						'conditions' => array(
							'ProjectLog.project_id' => $project['Project']['id'],
							'ProjectLog.type' => 'notifications.email',
							'ProjectLog.internal_description' => '0'
						)
					));
					if ($count > 1) {
						// once we've written a 0 notifications; don't need to write again
						// still want to write it at least once so logs show an effort has been made
						$write_log = false;
					}
				}
				
				$email_count = $this->SurveyUser->find('count', array(
					'conditions' => array(
						'SurveyUser.survey_id' => $project['Project']['id'],
						'SurveyUser.notification' => '1'
					)
				)); 
				
				$survey_visit_cache = $this->SurveyVisitCache->find('first', array(
					'fields' => array('SurveyVisitCache.emailed', 'SurveyVisitCache.id'),
					'conditions' => array(
						'SurveyVisitCache.survey_id' => $project['Project']['id']
					)
				)); 
				if ($survey_visit_cache && $survey_visit_cache['SurveyVisitCache']['emailed'] != $email_count) {
					$this->SurveyVisitCache->create();
					$this->SurveyVisitCache->save(array('SurveyVisitCache' => array(
						'id' => $survey_visit_cache['SurveyVisitCache']['id'],
						'emailed' => $email_count
					)), true, array('emailed')); 
					$this->out('Email count set to '.$email_count); 
				}
								
				if ($write_log) {
					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $project['Project']['id'],
						'type' => 'notifications.email',
						'description' => 'Sent emails to '.$i.' panelists',
						'internal_description' => $i
					)));
				}
							
				$qual_email_count = $this->QualificationUser->find('count', array(
					'conditions' => array(
						'QualificationUser.qualification_id' => $qualification['Qualification']['id'],
						'QualificationUser.notification' => '1'
					)
				)); 
				$this->QualificationStatistic->create();
				$this->QualificationStatistic->save(array('QualificationStatistic' => array(
					'id' => $qualification['QualificationStatistic']['id'],
					'notified_email' => $qual_email_count
				)), true, array('notified_email'));
				$this->out('Qualification #'.$qualification['Qualification']['id'].': '.$qual_email_count); 
			}
		}
		$this->lecho('Complete: '.$i, $log_file, $this->log_key); 
		return (int) $i; 
	}
	
	public function sms() {
		ini_set('memory_limit', '740M');
		$start_time = time();
		
		// clean up all unsent sms from an hour ago
		$count = $this->SmsQueue->find('count', array(
			'recursive' => -1,
			'fields' => array('id'),
			'conditions' => array(
				'SmsQueue.status' => 'Sending', 
				'SmsQueue.sent <=' => date(DB_DATETIME, strtotime('-1 hour')) 
			)
		));
		if ($count > 0) {
			$this->out('Unstick Start');
			$this->SmsQueue->create();
			$this->SmsQueue->updateAll(
				array('SmsQueue.status' => '"Queued"', 'SmsQueue.sent' => null), 
				array(
					'SmsQueue.status' => 'Sending', 
					'SmsQueue.sent <=' => date(DB_DATETIME, strtotime('-1 hour')) 
				)
			);
			CakeLog::write('sms', 'Unstick ['.$this->SmsQueue->getLastQuery().']');
		}
				
		$this->out('Retrieving...');
		$queue = $this->SmsQueue->find('all', array(
			'conditions' => array(
				'SmsQueue.status' => 'Queued',
			),
			'limit' => '2000',
			'order' => 'SmsQueue.id ASC'
		));
		if (!$queue) {
			$this->out('No queued sms found.');
			return false;
		}
		
		$count = count($queue);
		CakeLog::write('sms', 'Go '.number_format($queue[0]['SmsQueue']['id']).' - '.number_format($queue[($count - 1)]['SmsQueue']['id']).' ('.$count.')');
		$this->out('Go '.$queue[0]['SmsQueue']['id'].' - '.$queue[($count - 1)]['SmsQueue']['id'].'('.$count.')');
		$first_id = $last_id = null;
		foreach ($queue as $item) {
			if (is_null($first_id)) {
				$first_id = $item['SmsQueue']['id'];
			}
			
			$last_id = $item['SmsQueue']['id'];
			$this->SmsQueue->create();
			$this->SmsQueue->save(array(
				'id' => $item['SmsQueue']['id'],
				'status' => 'Sending',
				'sent' => date(DB_DATETIME)
			), true, array('status', 'sent'));
		}
		
		CakeLog::write('sms', 'Queued '.number_format($first_id).' - '.number_format($last_id));		
		$this->out("Queue set"); 
		$yesterday = strtotime('-1 day');
		$settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array(
					'twilio.account_sid', 
					'twilio.auth_token', 
					'twilio.phone_number', 
					'twilio.sms.endpoint', 
					'twilio.lookup.endpoint',
					'user.max.sms_log', 
					'segment.write_key'
				),
				'Setting.deleted' => false
			),
			'fields' => array('name', 'value')
		));
		if (empty($settings['user.max.sms_log'])) {
			$settings['user.max.sms_log'] = 2;
		}
		
		$analytics = array();
		foreach ($queue as $item) {
			$can_send = true;
			if (!empty($item['SmsQueue']['user_id'])) {
				$count = $this->SmsLog->find('count', array(
					'conditions' => array(
						'SmsLog.project_id >' => '0',
						'SmsLog.user_id' => $item['SmsQueue']['user_id'],
						'SmsLog.status' => true,
						'SmsLog.created >' => date(DB_DATETIME, $yesterday)
					)
				));
				if ($count >= $settings['user.max.sms_log']) {
					$this->out('[Failed] User max sms limit reached');
					CakeLog::write('sms', '[Failed] User max sms limit reached');
					$can_send = false;
				}
			}
			
			if ($can_send && !empty($item['SmsQueue']['user_id']) && !empty($item['SmsQueue']['mobile_number']) && !empty($settings['twilio.account_sid'])) {
				$HttpSocket = new HttpSocket(array(
					'timeout' => 15,
					'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
				));
				$HttpSocket->configAuth('Basic', $settings['twilio.account_sid'], $settings['twilio.auth_token']);
				$results = $HttpSocket->post(
					str_replace('[SID]', $settings['twilio.account_sid'], $settings['twilio.sms.endpoint']), 
					array(
						'To' => $item['SmsQueue']['mobile_number'], 
						'From' => $settings['twilio.phone_number'], 
						'Body' => $item['SmsQueue']['body']
					)
				);
				$body = json_decode($results->body, true);
				if (!defined('IS_DEV_INSTANCE') || IS_DEV_INSTANCE === false) {
					$analytics[] = array(
						'userId' => $item['SmsQueue']['user_id'],
						'survey_id' => $item['SmsQueue']['project_id'],
						'status' => ($results->code == 201) ? true : false
					);
				}
				
				if ($results->code == 201) {
					$this->out('Sms sent');
					
					// Save notification log for invite tracking
					$this->NotificationLog->create();
					$this->NotificationLog->save(array('NotificationLog' => array(
						'mobile_number' => $item['SmsQueue']['mobile_number'],
						'user_id' => $item['SmsQueue']['user_id'],
						'project_id' => !empty($item['SmsQueue']['project_id']) ? $item['SmsQueue']['project_id']: '0',
						'type' => 'sms'
					)));
				}
				else {
					CakeLog::write('sms', print_r($results, true));
					$this->out('Sms failed - '.$body['message']);
				}
				
				$this->SmsLog->create();
				$this->SmsLog->save(array('SmsLog' => array(
					'mobile_number' => $item['SmsQueue']['mobile_number'],
					'user_id' => $item['SmsQueue']['user_id'],
					'project_id' => !empty($item['SmsQueue']['project_id']) ? $item['SmsQueue']['project_id']: '0',
					'body' => $item['SmsQueue']['body'],
					'status' => ($results->code == 201) ? true : false,
					'response' => $results->body
				)));
			}
			
			// clean up the old mail item
		   	$this->SmsQueue->create();
	   		$this->SmsQueue->delete($item['SmsQueue']['id']);
		}
		
		if (!empty($analytics) && !empty($settings['segment.write_key'])) {
			App::import('Vendor', 'Segment');
			class_alias('Segment', 'Analytics');
			Analytics::init($settings['segment.write_key']);
			foreach ($analytics as $analytic) {
				Analytics::track(array(
					'userId' => $analytic['userId'],
					'event' => 'Sms Sent',
					'properties' => array(
						'survey_id' => $analytic['survey_id'],
						'category' => 'Sms',
						'label' => 'Sms Sent',
						'type' => 'survey'
					)
				));	
			}
		}
		
		$diff = time() - $start_time;
		CakeLog::write('sms', 'Sent '.count($queue).' in '.$diff .' seconds');
	}
}
