<?php
App::uses('AppController', 'Controller');
App::import('Lib', 'MintVineUser');

class NotificationSchedulesController extends AppController {
	public $uses = array('NotificationLog', 'NotificationSchedule', 'NotificationTemplate', 'UserActivityHour', 'UserNotification');
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array('RequestHandler');
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function ajax_lock_profile() {
		$notification_shedule_id = $this->request->data['notification_shedule_id'];
		$notification_schedule = $this->NotificationSchedule->find('first', array(
			'fields' => 'NotificationSchedule.locked',
			'conditions' => array(
				'NotificationSchedule.id' => $notification_shedule_id,
			)
		));
		$locked = is_null($notification_schedule['NotificationSchedule']['locked']) ? date(DB_DATETIME): null;
		$this->NotificationSchedule->save(array('NotificationSchedule' => array(
			'id' => $notification_shedule_id,
			'locked' => $locked
		)));
		$status = is_null($locked) ? false: true;
		return new CakeResponse(array(
			'body' => json_encode(array(
				'status' => $status
			)),
			'type' => 'json',
			'status' => '201'
		));
	}
	
	public function index() {
		$notification_templates = $this->NotificationTemplate->find('all', array(
			'order' => 'NotificationTemplate.name ASC'
		)); 
		$this->set(compact('notification_templates'));
	}

	public function add() {
		$dateTimeZoneGmt = new DateTimeZone('UTC');
		$dateTimeZoneUser = new DateTimeZone($this->current_user['Admin']['timezone']);

		// Create two DateTime objects that will contain the same Unix timestamp, but
		// have different timezones attached to them.
		$dateTimeGmt = new DateTime("now", $dateTimeZoneGmt);
		$dateTimeUser = new DateTime("now", $dateTimeZoneUser);

		$offset = $dateTimeZoneUser->getOffset($dateTimeGmt);
		$hour_offset = $offset / 3600;
		$this->set(compact('hour_offset'));
		if ($this->request->is('post')) {
			$this->NotificationTemplate->create();
			$save = $this->NotificationTemplate->save($this->request->data);
			if ($save) {
				$this->Session->setFlash('Your changes have been saved.', 'flash_success');
				return $this->redirect(array('action' => 'index'));
			}
			else {
				$this->Session->setFlash('Please review your changes below.', 'flash_error');
			}
		}
	}

	public function view($notification_template_id) {
		$notification_template = $this->NotificationTemplate->find('first', array(
			'conditions' => array(
				'NotificationTemplate.id' => $notification_template_id
			)
		));
		$this->set(compact('notification_template'));
	}

	public function edit($notification_template_id = null) {
		$dateTimeZoneGmt = new DateTimeZone('UTC');
		$dateTimeZoneUser = new DateTimeZone($this->current_user['Admin']['timezone']);

		// Create two DateTime objects that will contain the same Unix timestamp, but
		// have different timezones attached to them.
		$dateTimeGmt = new DateTime("now", $dateTimeZoneGmt);
		$dateTimeUser = new DateTime("now", $dateTimeZoneUser);

		$offset = $dateTimeZoneUser->getOffset($dateTimeGmt);
		$hour_offset = $offset / 3600;
		$this->set(compact('hour_offset'));	
		$notification_template = $this->NotificationTemplate->find('first', array(
			'conditions' => array(
				'NotificationTemplate.id' => $notification_template_id
			)
		));
		if (!$notification_template) {
        	throw new NotFoundException(__('Invalid notification template'));
    	}
		if ($this->request->is('post') || $this->request->is('put')) {
			$this->request->data['NotificationTemplate']['id'] = $notification_template_id;
			$this->NotificationTemplate->create();
			$save = $this->NotificationTemplate->save($this->request->data, true, array_merge(array('total_emails'), array_keys($this->request->data['NotificationTemplate'])));
        	if ($save) {
        	    $this->Session->setFlash(__('Notification template has been updated.'), 'flash_success');
        	    return $this->redirect(array('action' => 'index'));
        	}
			else {
				$this->Session->setFlash('Please review your changes below.', 'flash_error');
			}
    	}
    	if (!$this->request->data) {
        	$this->request->data = $notification_template;
    	}
	}

	public function notification_template_clone($notification_template_id = null) {
		$notification_template = $this->NotificationTemplate->find('first', array(
			'conditions' => array(
				'NotificationTemplate.id' => $notification_template_id
			)
		));
		if (!$notification_template) {
        	throw new NotFoundException(__('Invalid notification template'));
    	}
		if ($this->request->is('post') || $this->request->is('put')) {
			$this->NotificationTemplate->create();
			$save = $this->NotificationTemplate->save($this->request->data);
        	if ($save) {
        	    $this->Session->setFlash(__('Your changes have been saved.'), 'flash_success');
        	    return $this->redirect(array('action' => 'index'));
        	}
			else {
				$this->Session->setFlash('Please review your changes below.', 'flash_error');
			}
    	}
    	if (!$this->request->data) {
        	$this->request->data = $notification_template;
    	}
	}
	
	public function user() {
		if (isset($this->request->query['user_id']) && $this->request->query['user_id'] > 0) {

			$user_activity_hour = $this->UserActivityHour->find('first', array(
				'conditions' => array(
					'UserActivityHour.user_id' => $this->request->query['user_id']
				)
			));

			$notification_templates = $this->NotificationTemplate->find('all', array(
				'conditions' => array(
				),
				'order' => 'NotificationTemplate.name ASC'
			)); 
			
			$notification_schedule = $this->NotificationSchedule->find('first', array(
				'conditions' => array(
					'NotificationSchedule.user_id' => $this->request->query['user_id'],
					'NotificationSchedule.type' => 'email'
				)
			)); 
			$user_notification = $this->UserNotification->find('first', array(
				'conditions' => array(
					'UserNotification.user_id' => $this->request->query['user_id'],
				)
			)); 
			$user = $this->User->find('first', array(
				'fields' => array('User.send_email', 'User.timezone'),
				'conditions' => array(
					'User.id' => $this->request->query['user_id']
				),
				'recursive' => -1
			)); 
			
			$notification_logs = $this->NotificationLog->find('list', array(
				'fields' => array('NotificationLog.id', 'NotificationLog.created'),
				'conditions' => array(
					'NotificationLog.user_id' => $this->request->query['user_id'],
					'NotificationLog.sent' => false,
					'NotificationLog.created >=' => date(DB_DATETIME, strtotime('-1 day'))
				)
			)); 
			$skipped_notifications = array();
			if (!empty($notification_logs)) {
				foreach ($notification_logs as $timestamp) {
					$hour = date('H', strtotime($timestamp));
					if (!isset($skipped_notifications[$hour])) {
						$skipped_notifications[$hour] = 0;
					}
					$skipped_notifications[$hour]++;
				}
			}
			
			
			$notification_logs = $this->NotificationLog->find('list', array(
				'fields' => array('NotificationLog.id', 'NotificationLog.click_timestamp'),
				'conditions' => array(
					'NotificationLog.user_id' => $this->request->query['user_id'],
					'NotificationLog.click_timestamp is not null',
					'NotificationLog.sent' => true,
					'NotificationLog.created >=' => date(DB_DATETIME, strtotime('-7 days'))
				)
			)); 
			$clicked_notifications = array();
			if (!empty($notification_logs)) {
				foreach ($notification_logs as $timestamp) {
					$hour = date('H', strtotime($timestamp));
					if (!isset($clicked_notifications[$hour])) {
						$clicked_notifications[$hour] = 0;
					}
					$clicked_notifications[$hour]++;
				}
			}
			$dateTimeZoneGmt = new DateTimeZone('UTC');
			$dateTimeZoneUser = new DateTimeZone($user['User']['timezone']);

			// Create two DateTime objects that will contain the same Unix timestamp, but
			// have different timezones attached to them.
			$dateTimeGmt = new DateTime("now", $dateTimeZoneGmt);
			$dateTimeUser = new DateTime("now", $dateTimeZoneUser);

			$offset = $dateTimeZoneUser->getOffset($dateTimeGmt);
			$hour_offset = $offset / 3600;
			$this->set(compact('notification_schedule', 'user', 'hour_offset', 'user_activity_hour', 'skipped_notifications', 'clicked_notifications', 'user_notification', 'notification_templates')); 
		}
	}

	public function ajax_overwrite_profile($notification_schedule_id) {
		if ($this->request->is('post')) {
			$notification_template_id = $this->request->data['notification_template_id'];
			$notification_template = $this->NotificationTemplate->find('first', array(
				'conditions' => array('id' => $notification_template_id)
			));
			$notification_schedule_data = array('id' => $notification_schedule_id);
			$unused_fileds = array('id', 'name', 'description', 'key', 'created', 'modified');
			foreach ($notification_template['NotificationTemplate'] as $key => $value) {
				if (!in_array($key, $unused_fileds)) {
					$notification_schedule_data[$key] = $value;
				}
			}
			$notification_schedule_data['modified'] = date(DB_DATETIME);
			$notification_schedule = $this->NotificationSchedule->save($notification_schedule_data);
			return new CakeResponse(array(
				'body' => json_encode(array(
					'notification_schedule' => $notification_schedule['NotificationSchedule']
				)),
				'type' => 'json',
				'status' => '201'
			));
		}
		$notification_templates = $this->NotificationTemplate->find('all', array(
			'order' => 'NotificationTemplate.name ASC'
		));
		$this->set(compact('notification_templates', 'notification_schedule_id'));
	}
	
	public function edit_schedule($notification_schedule_id) {
		$notification_schedule = $this->NotificationSchedule->find('first', array(
			'conditions' => array(
				'NotificationSchedule.id' => $notification_schedule_id
			)
		)); 
		if (!$notification_schedule) {
			throw new NotFoundException();
		}
		$user = $this->User->find('first', array(
			'fields' => array('User.id', 'User.timezone'),
			'conditions' => array(
				'User.id' => $notification_schedule['NotificationSchedule']['user_id']
			),
			'recursive' => -1
		)); 
		$dateTimeZoneGmt = new DateTimeZone('UTC');
		$dateTimeZoneUser = new DateTimeZone($user['User']['timezone']);

		// Create two DateTime objects that will contain the same Unix timestamp, but
		// have different timezones attached to them.
		$dateTimeGmt = new DateTime("now", $dateTimeZoneGmt);
		$dateTimeUser = new DateTime("now", $dateTimeZoneUser);

		$offset = $dateTimeZoneUser->getOffset($dateTimeGmt);
		$hour_offset = $offset / 3600;
		if ($this->request->is('post') || $this->request->is('put')) {
			$save = true;
			foreach ($this->request->data['NotificationSchedule'] as $hour => $count) {
				if (!empty($count) && ($count < 0 || $count > 10 || !is_numeric($count))) {
					$save = false;
					break;
				}
			}
			$this->request->data['NotificationSchedule']['id'] = $notification_schedule_id;
			if ($save) {
				if ($this->NotificationSchedule->save($this->request->data['NotificationSchedule'], true, array_keys($this->request->data['NotificationSchedule']))) {
					$this->Session->setFlash(__('Notification Schedule has been updated.'), 'flash_success');
					return $this->redirect(array('action' => 'user', '?' => array('user_id' => $notification_schedule['NotificationSchedule']['user_id'])));
				}
				$this->Session->setFlash(__('Unable to update the source.'), 'flash_error');
			}
			else {
				$this->Session->setFlash(__('There was an error saving email schedule - #Emails must be between 1 to 10.'), 'flash_error');
			}
    	}
		if (!$this->request->data) {
        	$this->request->data = $notification_schedule;
    	}
		$this->set(compact('notification_schedule', 'user', 'hour_offset')); 
	}
}