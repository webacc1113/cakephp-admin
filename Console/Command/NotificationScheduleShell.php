<?php
App::uses('HttpSocket', 'Network/Http');

class NotificationScheduleShell extends AppShell {
	public $uses = array('NotificationLog', 'NotificationSchedule', 'NotificationTemplate', 'ProjectOption', 'SurveyUserVisit', 'User', 'UserActivityHour');
	public $max_sends_per_24_hours = 18; 
	
	public function check_users() {
		$last_id = 0; 
		while (true) {
			$users = $this->User->find('list', array(
				'fields' => array('User.id'),
				'conditions' => array(
					'User.id >' => $last_id
				),
				'order' => 'User.id ASC',
				'limit' => '25000'
			));
			if (empty($users)) {
				break;
			}
			$this->out('Starting batch from '.$last_id); 
			foreach ($users as $user_id) {
				$count = $this->NotificationSchedule->find('count', array(
					'conditions' => array(
						'NotificationSchedule.user_id' => $user_id
					)
				));
				if ($count == 0) {
					$this->out('MISSING: '.$user_id);
				}
				$last_id = $user_id;
			}
		}
	}
	
	public function process() {
		$ts_start = microtime(true); 
		if ($this->args[0] == 'all') {
			$project_option = $this->ProjectOption->find('first', array(
				'conditions' => array(
					'ProjectOption.name' => 'notification_schedule.last_user_id',
					'ProjectOption.project_id' => '0'
				)
			)); 
			if (!$project_option) {
				$this->ProjectOption->getDataSource()->begin();
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'project_id' => '0',
					'name' => 'notification_schedule.last_user_id',
					'value' => 0
				)));
				$project_option = $this->ProjectOption->findById($this->ProjectOption->getInsertId()); 
				$this->ProjectOption->getDataSource()->commit();
				$last_user_id = 0; 
			}
			else {
				$last_user_id = $project_option['ProjectOption']['value']; 
			}
			$total = $this->User->find('count', array(
				'conditions' => array(
					'User.id >' => $last_user_id
				)
			));
			$this->out('Starting '.$this->args[0].': '.$total.' users'); 
			while (true) {
				$this->User->unbindModel(array('belongsTo' => array('Referrer'))); 
				$users = $this->User->find('all', array(
					'fields' => array('User.id', 'User.created', 'User.timezone', 'QueryProfile.country'),
					'conditions' => array(
						'User.id >' => $last_user_id
					),
					'limit' => 2000,
					'order' => 'User.id ASC'
				)); 
				if (!$users) {
					$this->out('No more users');
					break;
				}
				foreach ($users as $user) {
					$this->set_user_schedule($user);
					$last_user_id = $user['User']['id']; 
				}
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'id' => $project_option['ProjectOption']['id'],
					'value' => $last_user_id
				)), true, array('value')); 
			}
		}
		elseif ($this->args[0] == 'recent') {
			$last_user_id = 0; 
			$total = $this->User->find('count', array(
				'conditions' => array(
					'User.last_touched >' => date(DB_DATETIME, strtotime('-1 days')),
					'User.id >' => $last_user_id
				)
			));
			$this->out('Starting '.$this->args[0].': '.$total.' users'); 
			while (true) {
				$this->User->unbindModel(array('belongsTo' => array('Referrer'))); 
				$users = $this->User->find('all', array(
					'fields' => array('User.id', 'User.created', 'User.timezone', 'QueryProfile.country'),
					'conditions' => array(
						'User.last_touched >' => date(DB_DATETIME, strtotime('-1 day')),
						'User.id >' => $last_user_id
					),
					'limit' => 2000,
					'order' => 'User.id ASC'
				)); 
				if (!$users) {
					$this->out('No more users');
					break;
				}
				foreach ($users as $user) {
					$this->set_user_schedule($user);
					$last_user_id = $user['User']['id']; 
				}
			}
		}
		elseif (isset($this->args[0])) {
			$this->out('Starting single update: '.$this->args[0]); 
			$this->User->unbindModel(array('belongsTo' => array('Referrer'))); 
			$user = $this->User->find('first', array(
				'fields' => array('User.id', 'User.created', 'User.timezone', 'QueryProfile.country'),
				'conditions' => array(
					'User.id' => $this->args[0]
				)
			)); 
			if ($this->set_user_schedule($user)) {
				$this->out('SET '.$this->args[0]);
			}
			else {
				$this->out('FAILED '.$this->args[0]); 
			}
		}
		$ms_diff = microtime(true) - $ts_start;
		$this->out('Finished: '.$ms_diff.' seconds'); 
	}
	
	private function convertNotificationKey($timezone) {	
		$notification_template_key = str_replace('/', '.', strtolower($timezone)); 
		return $notification_template_key;
	}
	
	public function generate_from_timezone() {
		ini_set('memory_limit', '4096M');
		$this->loadModel('SurveyUserVisit');
		if (!isset($this->args[0])) {
			$timezones = $this->User->find('all', array(
				'fields' => array('DISTINCT(User.timezone)'),
				'conditions' => array(
					'User.timezone is not null',
					'User.last_touched >=' => date(DB_DATETIME, strtotime('-7 days'))
				),
				'recursive' => -1
			)); 
			$timezones = Set::extract($timezones, '{n}.User.timezone');
		}
		else {
			$timezones = array($this->args[0]); 
		}
		
		if (!isset($this->args[1])) {
			$max_count = 18; 
		}
		else {
			$max_count = $this->args[1]; 
		}
		
		foreach ($timezones as $timezone) {
			$user_ids = $this->User->find('list', array(
				'fields' => array('User.id'),
				'conditions' => array(
					'User.timezone' => $timezone,
					'User.last_touched >=' => date(DB_DATETIME, strtotime('-7 days'))
				)
			));
			if (empty($user_ids)) {
				$this->out('There are no users in the timezone '.$timezone); 
				return false; 
			}
			$this->out('Analyzing '.count($user_ids).' from '.$timezone); 

			$survey_user_visits = $this->SurveyUserVisit->find('list', array(
				'fields' => array('SurveyUserVisit.id', 'SurveyUserVisit.created'),
				'conditions' => array(
					'SurveyUserVisit.user_id' => $user_ids,
				),
				'recursive' => -1
			));
			$this->out('Analyzing '.count($survey_user_visits).' visits');
		
			$hours = $notification_template = $pcts = $counts = array();
			for ($i = 0; $i < 24; $i++) {
				$hours[str_pad($i, 2, '0', STR_PAD_LEFT)] = 0; 
			}
			foreach ($survey_user_visits as $timestamp) {
				$hour = date('H', strtotime($timestamp)); 
				$hours[$hour]++; 
			}
			$total = array_sum($hours);
			
			// a data set smaller than 500 is probably not relevant to us 
			if ($total > 500) {
				$this->out('Total data points: '.$total); 
		
				foreach ($hours as $hour => $count) {
					$datetime = date(DB_DATE).' '.$hour.':00'; 
					$notification_template[$hour] = $count;
					$pcts[$hour] = $pct = round($count / $total, 2);
					$counts[$hour] = floor($pct * $max_count);
				}
				$count_diff = $max_count - array_sum($counts); 
				arsort($notification_template); 
				foreach ($notification_template as $key => $val) {
					$count_diff--; 
					$counts[$key] = $counts[$key] + 1; 
					if ($count_diff == 0) {
						break;
					}
				}
			}
			else {
				// reset this existing one to the new.us value
				$default_notification_template = $this->NotificationTemplate->find('first', array(
					'conditions' => array(
						'NotificationTemplate.key' => 'new.us'
					)
				)); 
				$counts = $this->stripOutHourlyData($default_notification_template['NotificationTemplate']); 
			}
			
			// sanity check
			foreach ($counts as $key => $count) {
				if ($count > 5) {
					$counts[$key] = 5; 
				}
			}			
		
			$notification_template_key = $this->convertNotificationKey($timezone);
			 
			if (isset($this->args[1])) {
				$notification_template_key = $notification_template_key.'+'.$max_count;
			}
			$existing_notification_template = $this->NotificationTemplate->find('first', array(
				'conditions' => array(
					'NotificationTemplate.key' => $notification_template_key
				)
			)); 
			$notification_template = array('NotificationTemplate' => $counts); 
			if ($existing_notification_template) {
				$notification_template['NotificationTemplate']['id'] = $existing_notification_template['NotificationTemplate']['id']; 
			}
			$notification_template['NotificationTemplate']['name'] = $timezone;
			if (isset($this->args[1])) {
				$notification_template['NotificationTemplate']['name'] = $max_count.'+'.$notification_template['NotificationTemplate']['name'];
			}
			$notification_template['NotificationTemplate']['data_count'] = count($user_ids); 
			$notification_template['NotificationTemplate']['description'] = 'Generated by system'; 
			$notification_template['NotificationTemplate']['key'] = $notification_template_key;
			$this->NotificationTemplate->create();
			$this->NotificationTemplate->save($notification_template); 
		
			$this->out('Completed '.$timezone); 
		}
	}
	
	private function stripOutHourlyData($data) {
		$hours = array();
		for ($i = 0; $i < 24; $i++) {
			$hour = str_pad($i, 2, '0', STR_PAD_LEFT);
			$hours[$hour] = $data[$hour]; 
		} 
		return $hours;
	}
	
	private function set_user_schedule($user) {
		$this->out('Starting '.$user['User']['id']); 
		
		if (empty($user['User']['timezone']) && empty($user['QueryProfile']['country'])) {
			$this->out('No timezone and country data set for user');
			return false; 
		}
		
		$notification_schedule = $this->NotificationSchedule->find('first', array(
			'conditions' => array(
				'NotificationSchedule.user_id' => $user['User']['id'],
				'NotificationSchedule.type' => 'email'
			)
		)); 
		// locked schedules should not be manually set
		if ($notification_schedule && $notification_schedule['NotificationSchedule']['locked']) {
			return false; 
		}

		$last_key = null; 
		// if this is an older user, check for activity
		$hour_data = false; 
		if (strtotime('-3 days') > strtotime($user['User']['created'])) {
			// todo: at some point; we need to increase the 18 to way more... how to do that?
			$user_activity_hour = $this->UserActivityHour->find('first', array(
				'conditions' => array(
					'UserActivityHour.user_id' => $user['User']['id'],
				)
			));	
			if ($user_activity_hour && $user_activity_hour['UserActivityHour']['total'] > 0) {
				
				// determine the greatest number of emails sent per day
				$survey_user_visits = $this->SurveyUserVisit->find('list', array(
					'fields' => array('SurveyUserVisit.id', 'SurveyUserVisit.created'),
					'conditions' => array(
						'SurveyUserVisit.user_id' => $user['User']['id'],
						'SurveyUserVisit.accessed_from' => 'email',
						'SurveyUserVisit.created >=' => date(DB_DATETIME, strtotime('-7 days'))
					)
				)); 
				if ($survey_user_visits) {
					$counts_by_day = array();
					$counts_by_hour_per_day = array();
					$max_by_hour = array();
					foreach ($survey_user_visits as $datetime) {
						$date = date(DB_DATE, strtotime($datetime)); 
						$hour = date('H', strtotime($datetime)); 
						if (!isset($counts_by_day[$date])) {
							$counts_by_day[$date] = 0; 
						}
						if (!isset($counts_by_hour_per_day[$date][$hour])) {
							$counts_by_hour_per_day[$date][$hour] = 0; 
						}
						$counts_by_day[$date]++;
						$counts_by_hour_per_day[$date][$hour]++;
					}
					$max_email_clicks_per_day = max($counts_by_day);
					
					foreach ($counts_by_hour_per_day as $hours) {
						foreach ($hours as $hour => $value) {
							if (!isset($max_by_hour[$hour])) {
								$max_by_hour[$hour] = $value; 
							}
							else {
								if ($value > $max_by_hour[$hour]) {
									$max_by_hour[$hour] = $value; 
								}
							}
						}
					}
				}
								
				// todo: this area down below can be greatly simplified in the future
				$ratios = array();
				// figure out ratios
				for ($i = 0; $i < 24; $i++) {
					$hour = str_pad($i, 2, '0', STR_PAD_LEFT); 
					$ratio = floor(($user_activity_hour['UserActivityHour'][$hour] / $user_activity_hour['UserActivityHour']['total']) * 100); 
					$ratios[$hour] = $ratio; 
				}
				$diff = 100 - array_sum($ratios); 
		
				if ($diff > 0) {
					arsort($ratios);
					foreach ($ratios as $key => $val) {
						$diff--; 
						$ratios[$key] = $val + 1;
						if ($diff == 0) {
							break;
						}
					}
					ksort($ratios);
				}
		
				$counts = array();
				foreach ($ratios as $hour => $value) {
					$count = round($value / 100 * $this->max_sends_per_24_hours);
					$counts[$hour] = $count; 
				}
				$diff = $this->max_sends_per_24_hours - array_sum($counts);
				if ($diff > 0) {
					arsort($counts);
					foreach ($counts as $key => $val) {
						$diff--; 
						$counts[$key] = $val + 1;
						if ($diff == 0) {
							break;
						}
					}
					ksort($counts);
				}
				$max_value = $counts; 
				
				$hour_data = array();
				foreach ($counts as $hour => $count) {
					$hour_data[$hour] = $count; 
				}
				if (isset($max_by_hour)) {
					foreach ($max_by_hour as $hour => $value) {
						if ($value > $hour_data[$hour]) {
							$hour_data[$hour] = $value + 1; 
						}
					}
				}
			}
		
			// if user generated data is not pre-created, use the defaults for the timezone
			// this should only be executed for non-new users
			if (!$hour_data) {
				$notification_template = $this->NotificationTemplate->find('first', array(
					'conditions' => array(
						'NotificationTemplate.key' => $this->convertNotificationKey($user['User']['timezone'])
					)
				));
				if ($notification_template) {
					$hour_data = $this->stripOutHourlyData($notification_template['NotificationTemplate']); 
				}
				$last_key = $this->convertNotificationKey($user['User']['timezone']); 
			}
		}
		
		if (!empty($user['QueryProfile']['country'])) {
			// if timezone data doesn't exist, default to the country profile; also for new users
			if (!$hour_data) {
				$notification_template = $this->NotificationTemplate->find('first', array(
					'conditions' => array(
						'NotificationTemplate.key' => 'new.'.strtolower($user['QueryProfile']['country'])
					)
				));
				if ($notification_template) {
					$hour_data = $this->stripOutHourlyData($notification_template['NotificationTemplate']); 
				}
				$last_key = $this->convertNotificationKey('new.'.strtolower($user['QueryProfile']['country'])); 
			}
		}
		
		if (!$hour_data) {
			$this->out('No data to update for this panelist');
			return false; 
		}
		
		// if there is no data, then 
		if (!$notification_schedule) {			
			$this->NotificationSchedule->getDataSource()->begin();
			$this->NotificationSchedule->create();
			$this->NotificationSchedule->save(array('NotificationSchedule' => array(
				'type' => 'email', 
				'total_emails' => null,
				'user_id' => $user['User']['id']
			))); 
			$notification_schedule = $this->NotificationSchedule->find('first', array(
				'conditions' => array(
					'NotificationSchedule.id' => $this->NotificationSchedule->getInsertId()
				)
			)); 
			$this->NotificationSchedule->getDataSource()->commit();
		}
		
		$existing_hourly_data = $this->stripOutHourlyData($notification_schedule['NotificationSchedule']); 
		$changed = false;
		foreach ($existing_hourly_data as $hour => $value) {
			if ($hour_data[$hour] != $value) {
				$changed = true;
				break;
			}
		}
		
		if ($changed) {
			foreach ($hour_data as $hour => $value) {
				$notification_schedule['NotificationSchedule'][$hour] = $value; 
			}
			$notification_schedule['NotificationSchedule']['last_key'] = $last_key;
			$notification_schedule['NotificationSchedule']['total_emails'] = array_sum($hour_data);
			unset($notification_schedule['NotificationSchedule']['modified']);
		
			$this->NotificationSchedule->create();
			$this->NotificationSchedule->save($notification_schedule, true, array_merge(array('total_emails', 'last_key'), array_keys($hour_data))); 
		}
				
		return true;
	}
	
	
	// hour, date
	public function statistics() {
		$one_hour_ago = strtotime('-1 hour'); 
		if (!isset($this->args[0])) {
			$hour = date('H', $one_hour_ago); 
		}
		else {
			$hour = $this->args[0]; 
		}
		if (!isset($this->args[1])) {
			$date = date('Y-m-d', $one_hour_ago); 
		}
		else {
			$date = $this->args[1];
		}
		
		$start_ts = $date.' '.$hour.':00:00'; 
		$end_ts = $date.' '.$hour.':59:59'; 
		
		$this->out('Retrieving data from "'.$start_ts.'" to "'.$end_ts.'"');
		$notification_logs = $this->NotificationLog->find('list', array(
			'fields' => array('NotificationLog.sent'),
			'conditions' => array(
				'NotificationLog.created >=' => $start_ts, 
				'NotificationLog.created <=' => $end_ts, 
			)
		)); 
		
		$counts = array(
			'total' => count($notification_logs),
			'sent' => 0, 
			'unsent' => 0
		); 
		foreach ($notification_logs as $value) {
			if ($value) {
				$counts['sent']++; 
			}
			else {
				$counts['unsent']++;
			}
		}
		$message = 'Mail data from "'.$start_ts.'" to "'.$end_ts.'": ';
		foreach ($counts as $key => $value) {
			$message.= $key.': '.$value.' '; 
		}
		
		$date = date(DB_DATE, strtotime('-1 week')); 
		
		$start_ts = $date.' '.$hour.':00:00'; 
		$end_ts = $date.' '.$hour.':59:59'; 
		
		$this->out('Retrieving data from "'.$start_ts.'" to "'.$end_ts.'"');
		$notification_logs = $this->NotificationLog->find('list', array(
			'fields' => array('NotificationLog.sent'),
			'conditions' => array(
				'NotificationLog.created >=' => $start_ts, 
				'NotificationLog.created <=' => $end_ts, 
			)
		)); 
		
		$counts = array(
			'total' => count($notification_logs),
			'sent' => 0, 
			'unsent' => 0
		); 
		foreach ($notification_logs as $value) {
			if ($value) {
				$counts['sent']++; 
			}
			else {
				$counts['unsent']++;
			}
		}

		$message.= "\n".'Mail data from "'.$start_ts.'" to "'.$end_ts.'": ';
		foreach ($counts as $key => $value) {
			$message.= $key.': '.$value.' '; 
		}
		
		$this->out($message);
		
		$http = new HttpSocket(array(
			'timeout' => '2',
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		try {
			$http->post('https://hooks.slack.com/services/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', json_encode(array(
				'text' => $message,
				'link_names' => 1,
				'username' => 'bernard'
			)));
		} 
		catch (Exception $ex) {
			$this->lecho('Slack api error: Slack alert not sent', $log_file, $log_key);
		}
	}
}