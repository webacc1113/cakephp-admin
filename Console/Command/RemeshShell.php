<?php
App::uses('Shell', 'Console');
App::import('Lib', 'Utilities');
App::import('Lib', 'Surveys');
App::uses('ComponentCollection', 'Controller');
App::uses('Controller', 'Controller');
App::uses('View', 'View');
class RemeshShell extends Shell {
	public $uses = array('User', 'Nonce', 'MailQueue', 'Setting', 'SurveyLink', 'ProjectLog', 'RemeshReminder', 'Project', 'ProjectOption', 'SurveyUser', 'Setting', 'Partner', 'Group', 'RemeshReminder', 'RemeshSkippedInvite');
	
	function notify_panelist() {
		
		if (!defined('HOSTNAME_WWW')) {
			$setting = $this->Setting->find('list', array(
				'conditions' => array(
					'Setting.name' => array('hostname.www'),
					'Setting.deleted' => false
				),
				'fields' => array('name', 'value')
			));
			define('HOSTNAME_WWW', $setting['hostname.www']);
		}
		
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id', 'Group.key'),
			'conditions' => array(
				'Group.key' => 'remesh'
			),
			'recursive' => -1
		));

		$mintvine_partner = $this->Partner->find('first', array(
			'fields' => array('Partner.id', 'Partner.key'),
			'conditions' => array(
				'Partner.key' => 'mintvine',
				'Partner.deleted' => false
			),
			'recursive' => -1
		));
		if (!$mintvine_partner) {
			return;
		}

		// set up configurations for email sends
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
		
		// extract all remesh projects
		$all_projects = $this->Project->find('all', array(
			'fields' => array('Project.id'),
			'conditions' => array(
				'Project.status' => array(PROJECT_STATUS_OPEN, PROJECT_STATUS_SAMPLING),
				'Project.active' => true,
				'Project.group_id' => $group['Group']['id']
			),
			'recursive' => -1
		));
		if (!$all_projects) {
			return false;
		}
		$this->out('Found '.count($all_projects).' remesh projects');
		
		foreach ($all_projects as $project) {			
			$project = $this->Project->find('first', array(
				'fields' => array( 
					'Project.id', 'Project.prj_name', 'Project.survey_name', 'Project.description', 'Project.nq_award', 'Project.award', 'Project.active', 'Project.public', 'Project.mobile', 'Project.desktop', 'Project.tablet', 'Project.code', 'Project.quota', 'Project.bid_ir', 'Project.router', 'Project.status', 'Project.singleuse', 'Project.started', 'Project.client_survey_link', 'Project.client_rate', 'Project.est_length', 'Project.group_id',
					'SurveyVisitCache.complete',
					'SurveyVisitCache.click',
					'Client.key',
					'Client.param_type',
					'Client.client_name',
					'Group.name',
					'Group.key'
				),
				'conditions' => array(
					'Project.id' => $project['Project']['id']
				)
			));
			
			// no project options
			if (empty($project['ProjectOption'])) {
				continue; 
			}
			
			// define some settings on the project to review
			$is_interview_project = false; 
			$interview_date = null;
			$email_current = null; 
			$email_upcoming = null; 
			foreach ($project['ProjectOption'] as $project_option) {
				if ($project_option['name'] == 'is_chat_interview' && $project_option['value'] == '1') {
					$is_interview_project = true;
				}
				if ($project_option['name'] == 'interview_date') {
					$interview_date = $project_option['value'];
				}
				if ($project_option['name'] == 'email.current') {
					$email_current = $project_option['value'];
				}
				if ($project_option['name'] == 'email.upcoming') {
					$email_upcoming = $project_option['value'];
				}
			}

			// skip non-interview projects
			if (!$is_interview_project) {
				$this->out($project['Project']['id'].' is not an interview project'); 
				continue;
			}
			
			// project has already launched or has not been set with a valid time
			if (is_null($interview_date) || strtotime($interview_date) < time()) {
				$this->out($project['Project']['id'].' has already been launched; no need to send emails'); 
				continue;
			}
			
			$diff = strtotime($interview_date) - time(); 
			
			$email_type = null; 
			// if it's less than one hour
			if ($diff <= 3600) {
				$email_type = 'current';
			}
			// less than four hours, and upcoming email was never sent
			elseif ($diff <= (3600 * 4) && is_null($email_upcoming)) {
				continue; // skip sending upcoming email; we'll directly sent current email
			}
			// if it's less than a day
			elseif ($diff <= 86400) {
				$email_type = 'upcoming';
			}

			// write the project_options; this is used in the logic above to suppress recently created remesh projects
			if ($email_type == 'upcoming') {
				$project_option = $this->ProjectOption->find('first', array(
					'conditions' => array(
						'ProjectOption.project_id' => $project['Project']['id'],
						'ProjectOption.name' => 'email.upcoming'
					)
				)); 
				if (!$project_option) {
					$this->ProjectOption->create();
					$this->ProjectOption->save(array('ProjectOption' => array(
						'name' => 'email.upcoming',
						'project_id' => $project['Project']['id'],
						'value' => date(DB_DATETIME)
					))); 
				}
				else {
					$this->ProjectOption->create();
					$this->ProjectOption->save(array('ProjectOption' => array(
						'id' => $project_option['ProjectOption']['id'],
						'value' => date(DB_DATETIME)
					)), true, array('value'));
				}
			}
			elseif ($email_type == 'current') {
				$project_option = $this->ProjectOption->find('first', array(
					'conditions' => array(
						'ProjectOption.project_id' => $project['Project']['id'],
						'ProjectOption.name' => 'email.current'
					)
				)); 
				if (!$project_option) {
					$this->ProjectOption->create();
					$this->ProjectOption->save(array('ProjectOption' => array(
						'name' => 'email.current',
						'project_id' => $project['Project']['id'],
						'value' => date(DB_DATETIME)
					))); 
				}
				else {
					$this->ProjectOption->create();
					$this->ProjectOption->save(array('ProjectOption' => array(
						'id' => $project_option['ProjectOption']['id'],
						'value' => date(DB_DATETIME)
					)), true, array('value'));
				}
			}
			
			// start iterating through survey_users
			$survey_users = $this->SurveyUser->find('all', array(
				'fields' => array('SurveyUser.survey_id', 'SurveyUser.user_id'),
				'conditions' => array(
					'SurveyUser.survey_id' => $project['Project']['id']
				),
				'recursive' => -1
			));	
			if (!$survey_users) {
				continue;
			}
			$this->out('Found '.count($survey_users).' users for '.$project['Project']['id']);
			
			// only for current emails; pull survey links
			if ($email_type == 'current') {
				if (empty($project['Project']['client_survey_link'])) {
					$survey_links = $this->SurveyLink->find('list', array(
						'fields' => array('SurveyLink.id', 'SurveyLink.user_id'),
						'conditions' => array(
							'SurveyLink.survey_id' => $project['Project']['id']
						),
						'recursive' => -1
					));
				}
			}
			
			$i = 0; // count for successful sends
			foreach ($survey_users as $survey_user) {				
				// if user has opted out...
				$remesh_skipped_invite = $this->RemeshSkippedInvite->find('first', array(
					'conditions' => array(
						'RemeshSkippedInvite.user_id' => $survey_user['SurveyUser']['user_id'],
						'RemeshSkippedInvite.survey_id' => $survey_user['SurveyUser']['survey_id']
					),
					'recursive' => -1
				));
				if ($remesh_skipped_invite) {				
					continue;
				}
								
				// send emails
				$send = true;	
				$user = $this->User->find('first', array(
					'fields' => array('User.id', 'User.ref_id', 'User.email', 'User.timezone'),
					'conditions' => array(
						'User.id' => $survey_user['SurveyUser']['user_id'],
						'User.deleted_on' => null
					)
				));

				// grab remesh reminder
				$remesh_reminder = $this->RemeshReminder->find('first', array(
					'conditions' => array(
						'RemeshReminder.user_id' => $user['User']['id'],
						'RemeshReminder.survey_id' => $survey_user['SurveyUser']['survey_id']
					),
					'order' => 'RemeshReminder.id DESC'
				));	
				
				if ($email_type == 'upcoming') {
					// any type of reminder sent in the past means we should skip
					if ($remesh_reminder) {
						continue;
					}
					$survey_subject = 'Upcoming Interview Reminder';
					$controller = new Controller();
					$view = new View($controller, false);
					$view->layout = 'Emails/html/default';
					$view->set('survey', $project);
					$view->set('timezone', $user['User']['timezone']);
					$view->set('interview_date', $interview_date);
					$view->viewPath = 'Emails/html';
					$template = 'upcoming-banner-remesh';
					$email_body = $view->render($template);
					$nonce = substr($user['User']['ref_id'], 0, 21).'-'.substr(Utils::rand(10), 0, 10);
					$survey_url = HOSTNAME_WWW.'/surveys/take/'.$project['Project']['id'].'/?nonce='.$nonce . '&from=email'.(!empty($project['Project']['code']) ? '&key='.$project['Project']['code'] : '');
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
						$survey_user['SurveyUser']['user_id']
					), $email_body);
					
					// create the one-time nonce
					$this->Nonce->create();
					$this->Nonce->save(array('Nonce' => array(
						'item_id' => $project['Project']['id'],
						'item_type' => 'survey',
						'user_id' => $survey_user['SurveyUser']['user_id'],
						'nonce' => $nonce
					)), false);	
					
					$this->MailQueue->create();
					$this->MailQueue->save(array('MailQueue' => array(
						'user_id' => $survey_user['SurveyUser']['user_id'],
						'email' => $user['User']['email'],
						'subject' => $survey_subject,
						'project_id' => $project['Project']['id'],
						'body' => $customized_email_body,
						'status' => 'Queued',
						'priority' => '1' // set priority 1 for interview projects
					)));
					
					if (!$remesh_reminder) {
						$this->RemeshReminder->create();									
						$this->RemeshReminder->save(array('RemeshReminder' => array(									
							'user_id' => $survey_user['SurveyUser']['user_id'],
							'survey_id' => $survey_user['SurveyUser']['survey_id'],
							'type' => 'upcoming'
						)));	
					}
					$i++;
					$this->out('Queued Reminder '.$survey_user['SurveyUser']['user_id'].' into '.$survey_user['SurveyUser']['survey_id']);
				}
				elseif ($email_type == 'current') {
				
					// check to see if links exist
					if (empty($project['Project']['client_survey_link']) && !in_array($survey_user['SurveyUser']['user_id'], $survey_links)) {
						$this->out('Could not locate links for '.$survey_user['SurveyUser']['user_id']); 
						continue;
					}
					
					if ($remesh_reminder && $remesh_reminder['RemeshReminder']['type'] == 'current') {
						continue;
					}
					$survey_subject = 'Current Interview Reminder';						
					$controller = new Controller();
					$view = new View($controller, false);
					$view->layout = 'Emails/html/default';
					$view->set('survey', $project);
					$view->set('timezone', $user['User']['timezone']);
					$view->set('interview_date', $interview_date);
					$view->viewPath = 'Emails/html';
					$template = 'current-banner-remesh';
					$email_body = $view->render($template);
					$nonce = substr($user['User']['ref_id'], 0, 21).'-'.substr(Utils::rand(10), 0, 10);
					$survey_url = HOSTNAME_WWW.'/surveys/take/'.$project['Project']['id'].'/?nonce='.$nonce . '&from=email'.(!empty($project['Project']['code']) ? '&key='.$project['Project']['code'] : '');
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
						$survey_user['SurveyUser']['user_id']
					), $email_body);
					
					// create the one-time nonce
					$this->Nonce->create();
					$this->Nonce->save(array('Nonce' => array(
						'item_id' => $project['Project']['id'],
						'item_type' => 'survey',
						'user_id' => $survey_user['SurveyUser']['user_id'],
						'nonce' => $nonce
					)), false);	
					
					$this->MailQueue->create();
					$this->MailQueue->save(array('MailQueue' => array(
						'user_id' => $survey_user['SurveyUser']['user_id'],
						'email' => $user['User']['email'],
						'subject' => $survey_subject,
						'project_id' => $project['Project']['id'],
						'body' => $customized_email_body,
						'status' => 'Queued',
						'priority' => '1' // set priority 1 for interview projects
					)));
					$remesh_reminder = $this->RemeshReminder->find('first', array(
						'conditions' => array(
							'RemeshReminder.user_id' => $user['User']['id'],
							'RemeshReminder.survey_id' => $project['Project']['id']
						),
						'order' => array('RemeshReminder.id' => 'DESC')
					));	
					if ($remesh_reminder) {
						$this->RemeshReminder->create();
						$this->RemeshReminder->save(array('RemeshReminder' => array(
							'id' => $remesh_reminder['RemeshReminder']['id'],
							'user_id' => $user['User']['id'],
							'survey_id' => $project['Project']['id'],
							'type' => 'current'
						)));
					}
					else {
						$this->RemeshReminder->create();
						$this->RemeshReminder->save(array('RemeshReminder' => array(
							'user_id' => $user['User']['id'],
							'survey_id' => $project['Project']['id'],
							'type' => 'current'
						)));
					}
					$i++;
					$this->out('Queued 2 hour reminder '.$user['User']['id'].' into '.$project['Project']['id']);
				}
			}
			
			// write project logs
			if ($i > 0) {
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'email.sent',
					'description' => 'Sent '.$i.' '.$email_type.' reminder emails'
				)));
			}
		}
		$this->out('Completed sending notifications');
	}
}