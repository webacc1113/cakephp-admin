<?php
App::uses('CakeEmail', 'Network/Email');
App::uses('ComponentCollection', 'Controller');
App::uses('EmailSanityComponent', 'Controller/Component');
CakePlugin::load('Mailgun');
App::import('Vendor', 'Segment');

class MailShell extends AppShell {
	var $uses = array('MailLog', 'MailQueue', 'NotificationLog', 'NotificationSchedule', 'Project', 'Qualification', 'QualificationUser', 'Setting', 'SurveyUser', 'SystemMailLog', 'User', 'UserNotification', 'UserOption'); 

	public function send() {
		if (!isset($this->args[0])) {
			$this->out('You must defined a shard');
			return;
		}
		ini_set('memory_limit', '1024M');
		ini_set('mysql.connect_timeout', 1200);
		ini_set('default_socket_timeout', 1200);
		ini_set('max_execution_time', 1200); // 10 minutes to send all emails
		
		$this->SystemMailLog->getDataSource()->begin();
		$this->SystemMailLog->create();
		$this->SystemMailLog->save(array('SystemMailLog' => array(
			'shard_id' => $this->args[0],
			'started' => date(DB_DATETIME)
		)));
		$system_mail_log = $this->SystemMailLog->findById($this->SystemMailLog->getInsertId()); 
		$this->SystemMailLog->getDataSource()->commit();
		
		$shard_id = $this->args[0];
		$ts_start = microtime(true);
		
		// clean up all unsent mails from an hour half ago
		$mail_queues_stuck = $this->MailQueue->find('all', array(
			'recursive' => -1,
			'fields' => array('MailQueue.id'),
			'conditions' => array(
				'MailQueue.shard' => $shard_id, 
				'MailQueue.status' => 'Sending', 
				'MailQueue.sent <=' => date(DB_DATETIME, strtotime('-1 hour')) 
			)
		));
		if ($mail_queues_stuck) {
			$stuck_mail_queues = $this->MailQueue->find('all', array(
				'fields' => array('MailQueue.id'),
				'conditions' => array(
					'MailQueue.shard' => $shard_id,
					'MailQueue.status' => 'Sending', 
					'MailQueue.sent <=' => date(DB_DATETIME, strtotime('-1 hour')) 
				),
				'recursive' => -1
			)); 
			if ($stuck_mail_queues) {
				$this->out('Found '.count($stuck_mail_queues).' stuck mails');
				foreach ($stuck_mail_queues as $stuck_mail_queue) {
					$this->MailQueue->create();
					$this->MailQueue->save(array('MailQueue' => array(
						'id' => $stuck_mail_queue['MailQueue']['id'],
						'status' => 'Queued',
						'sent' => null
					)), true, array('status', 'sent'));
				}
				$system_mail_log['SystemMailLog']['stuck_emails'] = count($stuck_mail_queues);
			}
		}
		
		$mail_queues = $this->MailQueue->find('all', array(
			'conditions' => array(
				'MailQueue.status' => 'Queued',
				'MailQueue.shard' => $shard_id
			),
			'limit' => '2000',
			'order' => 'MailQueue.priority DESC'
		));
		if (!$mail_queues) {
			if (is_null($system_mail_log['SystemMailLog']['stuck_emails'])) {
				$this->SystemMailLog->delete($system_mail_log['SystemMailLog']['id']);
			}
			else {
				$this->SystemMailLog->create();
				$this->SystemMailLog->save($system_mail_log, true, array('stuck_emails'));
			}
			return false;
		}
		
		$count = count($mail_queues);
		$system_mail_log['SystemMailLog']['processing_emails'] = $count;
		$this->out('Found '.$count.' mails'); 
		
		$first_id = $last_id = null;
		foreach ($mail_queues as $item) {
			if (is_null($first_id)) {
				$first_id = $item['MailQueue']['id'];
			}
			$last_id = $item['MailQueue']['id'];
			$this->MailQueue->create();
			$this->MailQueue->save(array(
				'id' => $item['MailQueue']['id'],
				'status' => 'Sending',
				'sent' => date(DB_DATETIME)
			), true, array('status', 'sent'));
		}
		
		$yesterday = strtotime('-1 day');
		
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('user.max.mail_log', 'email.max.intro', 'segment.write_key', 'cdn.url'),
				'Setting.deleted' => false
			)
		));
		
		$analytics = array();
		$sent_count = $unsent_count = 0; 
		foreach ($mail_queues as $item) {
			$can_send = true;
			$unset_notification_flag = false; // unset notification flags for users so we can send to them again
			
			if (empty($item['MailQueue']['user_id']) || empty($item['MailQueue']['email'])) {
				$can_send = false;
			}
				
			// forced = true, will bypass the following rules
			if ($can_send && !$item['MailQueue']['forced']) {
				$previous_hour = date('H', strtotime('-1 hour'));
				$current_hour = date('H');
				$next_hour = date('H', strtotime('+1 hour'));
				$user_notification = $this->UserNotification->find('first', array(
					'fields' => array('UserNotification.'.$current_hour, 'UserNotification.'.$next_hour, 'UserNotification.'.$previous_hour),
					'conditions' => array(
						'UserNotification.user_id' => $item['MailQueue']['user_id'],
					)
				));
				if (!$user_notification) {
					$previous_hour_count = 0;
					$current_hour_count = 0;
					$next_hour_count = 0; 
				}
				else {
					$previous_hour_count = $user_notification['UserNotification'][$previous_hour];
					$current_hour_count = $user_notification['UserNotification'][$current_hour];
					$next_hour_count = $user_notification['UserNotification'][$next_hour]; 
				}
				
				$notification_schedule = $this->NotificationSchedule->find('first', array(
					'fields' => array('NotificationSchedule.'.$previous_hour, 'NotificationSchedule.'.$current_hour, 'NotificationSchedule.'.$next_hour),
					'conditions' => array(
						'NotificationSchedule.user_id' => $item['MailQueue']['user_id']
					),
					'recursive' => -1
				));
				if (!$notification_schedule) {
					CakeLog::write('mail', 'WARNING: '.$item['MailQueue']['user_id'].' is missing a notifications schedule'); 
					// the old notification rules utilize a max count per day; keep this around until we can clean this out
					$count = $this->MailLog->find('count', array(
						'conditions' => array(
							'MailLog.project_id >' => '0',
							'MailLog.user_id' => $item['MailQueue']['user_id'],
							'MailLog.created >' => date(DB_DATETIME, $yesterday)
						)
					));
					if ($count >= $user_max_mail_log) {
						$can_send = false;
					}
				}
				else {
					if ($notification_schedule['NotificationSchedule'][$current_hour] <= $current_hour_count) {
						// if we have both the schedules and notification counts, check the per-hour count to see if we can still send
						$can_send = false; 
						$unset_notification_flag = true; // if we unset the notification flags we can email them again soon
												
						// if we're in the last quarter of the hour, and the current hour's count was zero, and next hour has capacity, try sending one
						if (date('i') >= 45 && $current_hour_count == 0 && $notification_schedule['NotificationSchedule'][$next_hour] > 0) {
							$count = $this->MailLog->find('count', array(
								'conditions' => array(
									'MailLog.project_id >' => '0',
									'MailLog.user_id' => $item['MailQueue']['user_id'],
									'MailLog.created >=' => date(DB_DATE).' '.$current_hour.':00:00'
								)
							)); 
							if ($count == 0) {
								$can_send = true;
								$unset_notification_flag = false;
							}
						}
						
						// allow for unused capacity in the past hour
						$currently_exceeded = $current_hour_count - $notification_schedule['NotificationSchedule'][$current_hour];
						$last_hour_remaining = $notification_schedule['NotificationSchedule'][$previous_hour] - $previous_hour_count;
						if (!$can_send && $currently_exceeded < $last_hour_remaining) {
							CakeLog::write('mail', 'Using excess capacity for '.$item['MailQueue']['user_id'].' for '.$previous_hour_count); 
							$can_send = true;
							$unset_notification_flag = false;
						}
						
						// for each click in this hour, allow another email... 
						
					}
				}
			}
			
			if ($can_send) {
				$sent_count++;
				$this->Project->unbindModel(array(
					'hasMany' => array('SurveyPartner', 'ProjectOption', 'ProjectAdmin'),
					'hasOne' => array('SurveyVisitCache')
				));		
				$project = $this->Project->find('first', array(
					'fields' => array('Project.id', 'Client.client_name', 'Group.name'),
					'conditions' => array(
						'Project.id' => $item['MailQueue']['project_id']
					),
					'contain' => array(
						'Client',
						'Group',
					)
				));
				
				if (!empty($settings['cdn.url']) && (!defined('IS_DEV_INSTANCE') || !IS_DEV_INSTANCE)) {
					Configure::write('App.cssBaseUrl', $settings['cdn.url'] . '/');
					Configure::write('App.jsBaseUrl', $settings['cdn.url'] . '/');
					Configure::write('App.imageBaseUrl', $settings['cdn.url'] . '/img/');
				}
				
				$email = new CakeEmail();
				$email->config('mailgun');
				$email->from(array(EMAIL_SENDER => 'MintVine'))
					->replyTo(array(REPLYTO_EMAIL => 'MintVine'))
					->emailFormat('html')
				    ->to($item['MailQueue']['email'])
				    ->subject($item['MailQueue']['subject']);
					
				// add custom variables for email tracking. https://basecamp.com/2045906/projects/1413421/todos/286750036
				if (isset($item['MailQueue']['project_id']) && !empty($item['MailQueue']['project_id'])) {
					$mailgun_custom_variables['user_id'] = $item['MailQueue']['user_id']; 
					$mailgun_custom_variables['survey_id'] = $item['MailQueue']['project_id'];
					$mailgun_custom_variables['subject'] = $item['MailQueue']['subject'];
					$email->addHeaders(array('v:my-custom-data' => json_encode($mailgun_custom_variables)));
				}	
				$response = $email->send($item['MailQueue']['body']);
				
				// Save notification log for invite tracking
				$this->NotificationLog->create();
				$this->NotificationLog->save(array('NotificationLog' => array(
					'email' => $item['MailQueue']['email'],
					'user_id' => $item['MailQueue']['user_id'],
					'project_id' => !empty($item['MailQueue']['project_id']) ? $item['MailQueue']['project_id']: '0'
				)));
				
				if (!defined('IS_DEV_INSTANCE') || IS_DEV_INSTANCE === false) {
					$analytics[] = array(
						'userId' => $item['MailQueue']['user_id'],
						'subject' => $item['MailQueue']['subject'],
						'survey_id' => $item['MailQueue']['project_id'],
						'client' => $project['Client']['client_name'],
						'group' => $project['Group']['name']
					);
				}
								
				// add in the mail log for sanity checks
				$this->MailLog->create();
				$this->MailLog->save(array('MailLog' => array(
					'email' => $item['MailQueue']['email'],
					'user_id' => $item['MailQueue']['user_id'],
					'project_id' => !empty($item['MailQueue']['project_id']) ? $item['MailQueue']['project_id']: '0'
				)));
				
				// here we store the usage statistics
				$current_hour = date('H');
				$user_notification = $this->UserNotification->find('first', array(
					'fields' => array('UserNotification.id', 'UserNotification.'.$current_hour),
					'conditions' => array(
						'UserNotification.user_id' => $item['MailQueue']['user_id']
					)
				)); 
				if (!$user_notification) {
					$this->UserNotification->create();
					$this->UserNotification->save(array('UserNotification' => array(
						'user_id' => $item['MailQueue']['user_id'],
						$current_hour => 1
					))); 
				}
				else {
					// in theory this could be cleaned up with accurate counts; but an approximation is probably enough
					$this->UserNotification->create(); 
					$this->UserNotification->save(array('UserNotification' => array(
						'id' => $user_notification['UserNotification']['id'],
						$current_hour => $user_notification['UserNotification'][$current_hour] + 1
					)), true, array('id', $current_hour)); 
				}
			}
			elseif ($item['MailQueue']['project_id'] > 0 && $item['MailQueue']['user_id'] > 0) {				
				// Store the log with a sent false so we can analyze how many sends we are not making
				$this->NotificationLog->create();
				$this->NotificationLog->save(array('NotificationLog' => array(
					'email' => $item['MailQueue']['email'],
					'user_id' => $item['MailQueue']['user_id'],
					'project_id' => $item['MailQueue']['project_id'],
					'sent' => false
				)));
			
				if ($unset_notification_flag) {
					// unset some data for the user, so they can be notified again
					$survey_user = $this->SurveyUser->find('first', array(
						'fields' => array('SurveyUser.id'),
						'conditions' => array(
							'SurveyUser.user_id' => $item['MailQueue']['user_id'],
							'SurveyUser.survey_id' => $item['MailQueue']['project_id'],
							'SurveyUser.notification' => '1'
						),
						'recursive' => -1
					)); 
					if ($survey_user) {
						$this->SurveyUser->create();
						$this->SurveyUser->save(array('SurveyUser' => array(
							'id' => $survey_user['SurveyUser']['id'],
							'notification' => null
						)), true, array('notification')); 
						
						// get list of qualifications
						$qualification_ids = $this->Qualification->find('list', array(
							'fields' => array('Qualification.id', 'Qualification.id'),
							'conditions' => array(
								'Qualification.project_id' => $item['MailQueue']['project_id']
							),
							'recursive' => -1
						)); 							
						
						// really there should be only one value of this... i think
						// if a panelist is invited into multiple matching quals, qe would 
						// in theory skip the invite since the invite already exists; 
						// this means one qualification_user record per panelist in a project
						$qualification_user_ids = $this->QualificationUser->find('list', array(
							'fields' => array('QualificationUser.id'),
							'conditions' => array(
								'QualificationUser.user_id' => $item['MailQueue']['user_id'],
								'QualificationUser.qualification_id' => $qualification_ids
							)
						)); 
						if (!empty($qualification_user_ids)) {
							foreach ($qualification_user_ids as $qualification_user_id) {
								$this->QualificationUser->create();
								$this->QualificationUser->save(array('QualificationUser' => array(
									'id' => $qualification_user_id,
									'notification' => '0',
									'notification_timestamp' => null,
								)), true, array('notification', 'notification_timestamp'));
							}
						}
					}
				}
			}
			
			if (!$can_send) {
				$unsent_count++;
			}

			// clean up the old mail item
		   	$this->MailQueue->create();
	   		$this->MailQueue->delete($item['MailQueue']['id']);
		}
		
		$system_mail_log['SystemMailLog']['sent_emails'] = $sent_count;
		$system_mail_log['SystemMailLog']['suppressed_emails'] = $unsent_count;
		
		if (!defined('IS_DEV_INSTANCE') || IS_DEV_INSTANCE === false) {
			if (!empty($analytics) && !empty($settings['segment.write_key'])) {
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
							'type' => 'survey',
							'client' => $analytic['client'],
							'group' => $analytic['group']
						)
					));	
				}
			}
		}
		
		$diff = microtime(true) - $ts_start; 
		$system_mail_log['SystemMailLog']['ended'] = date(DB_DATETIME);
		$system_mail_log['SystemMailLog']['execution_time_ms'] = round($diff);
		
		$this->SystemMailLog->create();
		$this->SystemMailLog->save($system_mail_log, true, array('ended', 'execution_time_ms', 'stuck_emails', 'processing_emails', 'sent_emails', 'suppressed_emails')); 
		
		$this->out('[SHARD '.$shard_id.'] Sent '.count($mail_queues).' in '.$diff .' seconds ('.round((count($mail_queues) / $diff), 4).' emails/sec)');
	}
	
	// we need to update the user notification counts for the next hour
	public function reset_user_notification_next_hour() {
		ini_set('memory_limit', '1024M');
		
		$next_hour = date('H', strtotime('+1 hour')); 
		
		$user_notifications = $this->UserNotification->find('all', array(
			'fields' => array('UserNotification.id', 'UserNotification.user_id', 'UserNotification.'.$next_hour),
			'order' => 'UserNotification.id ASC',
			'recursive' => -1
		)); 
		$total = count($user_notifications);
		
		$this->out('Updating '.$total.' records for '.$next_hour.':00'); 
		$i = 0; 
		foreach ($user_notifications as $user_notification) {
			$i++; 
			if (empty($user_notification['UserNotification'][$next_hour])) {
				continue;
			}
			$this->UserNotification->create();
			$this->UserNotification->save(array('UserNotification' => array(
				'id' => $user_notification['UserNotification']['id'],
				$next_hour => '0',
				'modified' => false
			)), true, array($next_hour)); 
			$pct = round($i / $total * 100, 2);
			$this->out($i .' / '.$total.' ('.$pct.'%) '.$user_notification['UserNotification']['user_id']); 
		}
		$this->out('Completed');
	}
	
	public function resend() {
		$collection = new ComponentCollection();
		$this->EmailSanity= new EmailSanityComponent($collection);

				
		$days_to_resend = array(2);
		$emailed = array();
		foreach ($days_to_resend as $day) {
			$conditions = array(
				'User.email <>' => null, 
				'User.active' => false,
				'User.deleted_on' => null,				
				'User.send_email' => 1, 
				'User.verified is null'
			);
			if ($day == 2) {
				$conditions['User.created >='] = date('Y-m-d', mktime() - 86400 * $day). ' 00:00:00';
				$conditions['User.created <='] = date('Y-m-d', mktime() - 86400 * $day). ' 23:59:59';
				$conditions['User.last_emailed_date'] = null;
			} else {
				$conditions['User.last_emailed_date >='] = date('Y-m-d', mktime() - 86400 * ($day - 2)). ' 00:00:00';
				$conditions['User.last_emailed_date <='] = date('Y-m-d', mktime() - 86400 * ($day - 2)). ' 23:59:59';
				$conditions['User.created >='] = date('Y-m-d', mktime() - 86400 * $day);
			}
			$users = $this->User->find('all', array(
				'conditions' => $conditions,
				'order' => 'User.id DESC'
			));
			if (!empty($users)) {
				foreach ($users as $user) {
					$can_send = $this->EmailSanity->check_resend($user['User']['email']);
					if (!$can_send || !$user['User']['email'] || in_array($user['User']['email'], $emailed)) {
						continue;
					}

					if (empty($user['User']['verification_code'])) {
						$verification_code = $user['User']['verification_code'] = sha1($user['User']['email'].random_string('alnum', 16));
						$this->User->save(array(
							'id' => $user['User']['id'],
							'verification_code' => $verification_code
						), true, array('verification_code'));
					} else {
						$verification_code = $user['User']['verification_code'];
					}
					$emailed[] = $user['User']['email'];
					echo $user['User']['email'].' '.$user['User']['created'].' '.$user['User']['last_emailed_date'].' ('.$day.')'."\n";
					$email = new CakeEmail();
					$email->config('mailgun');
					if ($day == 2) {
						$subject = 'Hi, '.$user['User']['username'].'! Remember to Activate Your Account!';
					} else {
						$subject = 'Hi, '.$user['User']['username'].' - Remember to Activate Your Account!';
					}
					$unsubscribe_link = HOSTNAME_WWW.'/users/emails/'.$user['User']['ref_id'];
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
					$result = $email->from(array(EMAIL_SENDER => 'MintVine'))
						->replyTo(array(REPLYTO_EMAIL => 'MintVine'))
						->template('email_confirm_resend')
						->viewVars(array('user' => $user, 'verification_code' => $verification_code, 'unsubscribe_link' => $unsubscribe_link))
						->emailFormat('html')
						->to($user['User']['email'])
						->subject($subject)
		   				->send();

					if ($result) {
						// add in the mail log for sanity checks
						$this->MailLog->create();
						$this->MailLog->save(array('MailLog' => array(
							'email' => $user['User']['email'],
							'type' => EMAIL_TYPE_RESEND,
							'user_id' => $user['User']['id'],
						)));
						
						$this->User->save(array(
							'id' => $user['User']['id'],
							'last_emailed_date' => date('Y-m-d H:i:s', mktime())
						), true, array('last_emailed_date'));
					}
				}
			}
		}
	}
	
	public function find_most_active_clicked_users() {
		ini_set('memory_limit', '1024M');
		$this->loadModel('SurveyUserVisit');
		
		if (!isset($this->args[0])) {
			$this->out('Initial datetime must be defined');
			return false; 
		}
		$date_from = date(DB_DATE, strtotime($this->args[0])).' 00:00:00'; 
		if (!isset($this->args[1])) {
			$date_to = date(DB_DATE, strtotime($this->args[0])).' 23:59:59'; 
		}
		else {
			$date_to = date(DB_DATE, strtotime($this->args[1])).' 23:59:59';
		}
		
		$survey_user_visits = $this->SurveyUserVisit->find('all', array(
			'fields' => array('SurveyUserVisit.user_id', 'SurveyUserVisit.accessed_from', 'SurveyUserVisit.created', 'SurveyUserVisit.status', 'SurveyUserVisit.survey_id'),
			'conditions' => array(
				'SurveyUserVisit.status' => array(SURVEY_CLICK, SURVEY_COMPLETED, SURVEY_NQ),
				'SurveyUserVisit.created >=' => $date_from,
				'SurveyUserVisit.created <=' => $date_to
			),
			'order' => 'SurveyUserVisit.id ASC',
			'recursive' => -1
		)); 
		
		$notification_count = $this->NotificationLog->find('count', array(
			'conditions' => array(
				'NotificationLog.created >=' => $date_from,
				'NotificationLog.created <=' => $date_to,
				'NotificationLog.sent' => true
			),
			'recursive' => -1
		)); 
		$total = count($survey_user_visits); 
		$this->out('Found '.$total.' records'); 
		
		$email_count = 0; 
		$user_ids = array();
		$keyed_survey_user_visits = array(); // store better keys for finding these values
		foreach ($survey_user_visits as $survey_user_visit) {
			$user_id = $survey_user_visit['SurveyUserVisit']['user_id'];
			if ($survey_user_visit['SurveyUserVisit']['accessed_from'] == 'email') {
				$email_count++;
				$user_ids[] = $user_id;  
			}

			$project = $this->Project->find('first', array(
				'fields' => array('Project.client_rate', 'Project.router', 'Project.group_id'),
				'conditions' => array(
					'Project.id' => $survey_user_visit['SurveyUserVisit']['survey_id']
				),
				'recursive' => -1
			)); 
			if ($project['Project']['group_id'] == 14) {
				continue;
			}
			
			if (!isset($keyed_survey_user_visits[$user_id])) {
				$keyed_survey_user_visits[$user_id] = array();
			}
			
			$value = array(
				'accessed_from' => $survey_user_visit['SurveyUserVisit']['accessed_from'],
				'timestamp' => strtotime($survey_user_visit['SurveyUserVisit']['created']),
				'date' => $survey_user_visit['SurveyUserVisit']['created']
			);
			if ($survey_user_visit['SurveyUserVisit']['status'] == SURVEY_COMPLETED) {
				$value['cpi_cents'] = 100 * (float) $project['Project']['client_rate']; 
			}
			else {
				$value['cpi_cents'] = 0;
			}
			$keyed_survey_user_visits[$user_id][] = $value; 
		}
		$this->out('Total Email Clicks: '.$email_count);
		$this->out('Total Unique Panelists: '.count(array_unique($user_ids))); 
		$counted_users = array_count_values($user_ids); 
		
		$unassociated_with_emails = 0;
		$unassociated_rev = 0;
		$just_email = 0;
		$subsequent_router_from_emails = 0; 
		$email_rev = 0;
		$subsequent_rev = 0;
		
		foreach ($keyed_survey_user_visits as $user_id => $entries) {
			$email_path = false;
			$last_ts = 0;
			foreach ($entries as $entry) {
				if ($entry['accessed_from'] != 'email' && !$email_path) {
					$unassociated_rev = $unassociated_rev + $entry['cpi_cents'];
					$unassociated_with_emails++;
					continue;
				}
				elseif ($entry['accessed_from'] == 'email' && !$email_path) {
					$email_path = true;
					$email_rev = $email_rev + $entry['cpi_cents'];
					$last_ts = $entry['timestamp']; 
					$just_email++;
					continue;
				}
				
				if ($email_path && $entry['accessed_from'] != 'email') {
					if (($entry['timestamp'] - $last_ts) < (30 * 60)) {
						$subsequent_router_from_emails++;
						$subsequent_rev = $subsequent_rev + $entry['cpi_cents']; 
					}
					else {
						$email_path = false;
						$unassociated_rev = $unassociated_rev + $entry['cpi_cents'];
						$unassociated_with_emails++;
					}
				}
				else {
					$email_rev = $email_rev + $entry['cpi_cents'];
					$just_email++;
				}
			}
		}
		
		$this->out('total invitations sent: '.$notification_count); 
		$this->out('Just email clicks: '.$just_email); 
		$this->out('Subsequent total router clicks: '.$subsequent_router_from_emails); 
		$this->out('Unassociated clicks: '.$unassociated_with_emails); 
		$this->out('Just email click rev: '.number_format(round($email_rev / 100, 2), 2)); 
		$this->out('Subsequent total router rev: '.number_format(round($subsequent_rev / 100, 2), 2)); 
		$this->out('Unassociated rev: '.number_format(round($unassociated_rev / 100, 2), 2)); 		
	}
	
	public function generate_total_distribution() {
		ini_set('memory_limit', '1024M');
		
		if (!isset($this->args[0])) {
			$this->out('Initial datetime must be defined');
			return false; 
		}
		$date_from = date(DB_DATE, strtotime($this->args[0])).' 00:00:00'; 
		if (!isset($this->args[1])) {
			$date_to = date(DB_DATE, strtotime($this->args[0])).' 23:59:59'; 
		}
		else {
			$date_to = date(DB_DATE, strtotime($this->args[1])).' 23:59:59';
		}
		
		$notification_logs = $this->NotificationLog->find('list', array(
			'fields' => array('NotificationLog.created'),
			'conditions' => array(
				'NotificationLog.created >=' => $date_from,
				'NotificationLog.created <=' => $date_to,
				'NotificationLog.sent' => true
			),
			'recursive' => -1
		)); 
		$this->out(count($notification_logs));
		$distributions = array();
		foreach ($notification_logs as $timestamp) {
			$hour = date('H', strtotime($timestamp)); 
			if (!isset($distributions[$hour])) {
				$distributions[$hour] = 0;
			}
			$distributions[$hour]++;
		}
		
		print_r($distributions);
		$this->out(implode(',',$distributions)); 
	}
	
	public function daily_summary() {
		$this->out('Starting save summed system mail logs');
		if (isset($this->args[0])) {
			$date = date(DB_DATE, strtotime($this->args[0])); 
		}
		else {
			$date = date(DB_DATE, strtotime('yesterday')); 
		}
		
		$start_time = $date.' 00:00:00'; 
		$end_time = $date.' 23:59:59';
			
		$conditions = array(
			'SystemMailLog.shard_id >' => 0,
			'SystemMailLog.started >=' => $start_time, 
			'SystemMailLog.started <=' => $end_time
		);
		$this->out('Calculating summed logs from '.$start_time.' to '.$end_time);
		
		$summed_logs = $this->SystemMailLog->find('first', array(
			'fields' => array(
				'COUNT(started) as counts',
				'MIN(started) as started',
				'ROUND(AVG(SystemMailLog.execution_time_ms)) as execution_time_ms',
				'SUM(SystemMailLog.stuck_emails) as stuck_emails',
				'SUM(SystemMailLog.processing_emails) as processing_emails',
				'SUM(SystemMailLog.sent_emails) as sent_emails',
				'SUM(SystemMailLog.suppressed_emails) as suppressed_emails'
			),
			'conditions' => $conditions
		));
		if (!$summed_logs[0]['counts']) {
			$this->out('Logs not found');
			return false;
		}
		
		$save = array('SystemMailLog' => array(
			'shard_id' => '0',
			'started' => $start_time,
			'ended' => $end_time,
			'execution_time_ms' => $summed_logs[0]['execution_time_ms'],
			'stuck_emails' => $summed_logs[0]['stuck_emails'],
			'processing_emails' => $summed_logs[0]['processing_emails'],
			'sent_emails' => $summed_logs[0]['sent_emails'],
			'suppressed_emails' => $summed_logs[0]['suppressed_emails'],
		)); 
		
		$system_mail_log = $this->SystemMailLog->find('first', array(
			'fields' => array('SystemMailLog.id'),
			'conditions' => array(
				'SystemMailLog.started' => $start_time,
				'SystemMailLog.ended' => $end_time,
				'SystemMailLog.shard_id' => '0'
			)
		)); 
		if ($system_mail_log) {
			$save['SystemMailLog']['id'] = $system_mail_log['SystemMailLog']['id']; 
		}

		$this->SystemMailLog->create();
		$this->SystemMailLog->save($save);
		
		$this->out('Logs have been saved successfully:'.print_r($save, true));
	}
}
