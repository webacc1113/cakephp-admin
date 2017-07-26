<?php

App::import('Lib', 'Utilities');
App::uses('CakeEmail', 'Network/Email');
App::uses('Controller', 'Controller');
App::import('Vendor', 'Segment');

class PushRouterProjectShell extends AppShell {
	var $uses = array('User', 'SurveyUser', 'Project', 'Nonce', 'ProjectOption', 'Setting');
	
	function push() {
		ini_set('memory_limit', '740M');
		ini_set('mysql.connect_timeout', 1200);
		ini_set('default_socket_timeout', 1200);
		ini_set('max_execution_time', 2400); // 20 minutes to send all emails
			
		$log = false;	

		$pushed_projects = $this->ProjectOption->find('list', array(
			'fields' => array(
				'ProjectOption.project_id'
			),
			'conditions' => array(
				'ProjectOption.name' => 'pushed',
				'ProjectOption.value' => true
			)
		));
		
		$surveys = $this->Project->find('all', array(
			'conditions' => array(
				'Project.id' => $pushed_projects,
				'Project.active' => true,
				'Project.status' => PROJECT_STATUS_OPEN,
			),
			'contain' => array(
				'ProjectOption' => array(
					'conditions' => array(
						'name' => array('pushed_user_id', 'pushed_email_subject', 'pushed_email_template')
					)
				),
			)
		));
		if (!$surveys) {
			return;
		}
				
		$analytics = array();
		
		foreach ($surveys as $survey) {
			// no countries
			if (empty($survey['Project']['country'])) {
				continue;
			}
			
			if (!empty($survey['Project']['ProjectOption'])) {
				foreach ($survey['Project']['ProjectOption'] as $key => $project_option) {
					$survey['ProjectOption'][$project_option['name']] = $project_option['value'];
					unset($survey['Project']['ProjectOption'][$key]);
				}
			}

			$this->User->unbindModel(array('belongsTo' => array('Referrer')));
			$active_users = $this->User->find('all', array(
				'fields' => array('User.id', 'User.ref_id', 'User.email', 'QueryProfile.country', 'User.send_survey_email', 'User.send_email'),
				'conditions' => array(
					'User.active' => true,
					'User.deleted_on' => null,
					'User.login > ' => date('Y-m-d H:i:s', strtotime(date('Y-m-d H:i:s').' -4 months')),
					'User.id >' => (!empty($survey['ProjectOption']['pushed_user_id'])) ? $survey['ProjectOption']['pushed_user_id'] : 0,
					'QueryProfile.country' => $survey['Project']['country']
				),
				'order' => 'User.id ASC',
				'limit' => 20000
			));
			if (!$active_users) {
				continue;
			}
			
			// for capturing the output of a view into a string
			$this->autoRender = false;
			$survey_subject = '';
			if (!empty($survey['ProjectOption']['pushed_email_subject'])) {
				$survey_subject = $survey['ProjectOption']['pushed_email_subject'];
			}
			elseif (!empty($survey['Project']['description'])) {
				$survey_subject = $survey['Project']['description'];
			}
			else {
				$survey_subject = 'Exciting Survey Opportunity';
			}
			
			$survey_award = $survey['Project']['award'];
			$survey_length = $survey['Project']['est_length'];
			$is_desktop = $survey['Project']['desktop'];
			$is_mobile = $survey['Project']['mobile'];
			$is_tablet = $survey['Project']['tablet'];
			$survey_id = $survey['Project']['id'];
			
			$template = 'survey';
			if (!empty($survey['ProjectOption']['pushed_email_template'])) {
				$template = $survey['ProjectOption']['pushed_email_template'];
			}
			elseif ($survey['Project']['router']) {
				$template = 'survey-funnel';
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
			$controller = new Controller();
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
			
			echo 'Starting users.'."\n";
			
			foreach ($active_users as $key => $active_user) {
				$user_id = $active_user['User']['id'];
				// create the survey user record
				$check_survey_user = $this->SurveyUser->find('first', array(
					'conditions' => array(
						'SurveyUser.survey_id' => $survey['Project']['id'],
						'SurveyUser.user_id' => $user_id
					),
					'recursive' => -1
				));
				
				if ($check_survey_user) {
					continue;
				}
				
				if (!$check_survey_user) {
					$this->SurveyUser->create();
					$this->SurveyUser->save(array('SurveyUser' => array(
						'survey_id' => $survey['Project']['id'],
						'user_id' => $user_id,
						'query_history_id' => null,
						'created' => date(DB_DATETIME, time())
					)));			
				}
				
				if (!$active_user['User']['send_survey_email'] || !$active_user['User']['send_email']) {
					continue;
				}
				
				// generate the email
				$nonce = substr($active_user['User']['ref_id'], 0, 21).'-'.substr(Utils::rand(10), 0, 10);
				$survey_url = HOSTNAME_WWW.'/surveys/pre/'.$survey['Project']['id'].'/?nonce='.$nonce.'&from=email'.(!empty($survey['Project']['code']) ? '&key='.$survey['Project']['code'] : '');
				$unsubscribe_link = HOSTNAME_WWW.'/users/emails/'.$active_user['User']['ref_id'];
									
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
				
				// create the one-time nonce
				$this->Nonce->create();
				$this->Nonce->save(array('Nonce' => array(
					'item_id' => $survey['Project']['id'],
					'item_type' => 'survey',
					'user_id' => $user_id,
					'nonce' => $nonce
				)), false);
				
				CakePlugin::load('Mailgun');
				$email = new CakeEmail();
				$email->config('mailgun');
				$email->from(array(EMAIL_SENDER => 'MintVine'))
					->replyTo(array(REPLYTO_EMAIL => 'MintVine'))
					->emailFormat('html')
				    ->to($active_user['User']['email'])
				    ->subject($survey_subject);
				$response = $email->send($customized_email_body);
			
				if (!defined('IS_DEV_INSTANCE') || IS_DEV_INSTANCE === false) {
					$analytics[] = array(
						'userId' => $active_user['User']['id'],
						'subject' => $survey_subject,
						'survey_id' => $survey['Project']['id']
					);
				}
				
				echo $active_user['User']['id']."\n";
				
				$last_user_id = $active_user['User']['id'];
			}			
			if (isset($last_user_id)) {
				$project_option = $this->ProjectOption->find('first', array(
					'conditions' => array(
						'ProjectOption.project_id' => $survey['Project']['id'],
						'ProjectOption.name' => 'pushed_user_id'
					)
				));
				
				if (!empty($project_option)) {
					$this->ProjectOption->save(array('ProjectOption' => array(
						'id' => $project_option['ProjectOption']['id'],
						'value' => $last_user_id
					)), true, array('value'));
				}
				else {
					$this->ProjectOption->create();
					$this->ProjectOption->save(array('ProjectOption' => array(
						'project_id' => $survey['Project']['id'],
						'name' => 'pushed_user_id',
						'value' => $last_user_id
					)));
				}
			}
		}
		
		if (!defined('IS_DEV_INSTANCE') || IS_DEV_INSTANCE === false) {
			if (!empty($analytics)) {
				$settings = $this->Setting->find('list', array(
					'conditions' => array(
						'Setting.name' => array('segment.write_key'),
						'Setting.deleted' => false
					),
					'fields' => array('name', 'value')
				));
				if (!empty($settings['segment.write_key'])) {
				
					class_alias('Segment', 'Analytics');
					Analytics::init($settings['segment.write_key']);
					
					foreach ($analytics as $analytic) {
						Analytics::track(array(
							'userId' => $analytic['userId'],
							'event' => 'Survey Invite Sent',
							'properties' => array(
								'survey_id' => $analytic['survey_id'],
								'subject' => $analytic['subject'],
								'category' => 'Email',
								'label' => 'Survey Invite Sent',
								'type' => 'survey'
							)
						));	
					}
				}
			}
		}
	}
}
