<?php
App::uses('AppController', 'Controller');
App::import('Lib', 'MintVineUser');

class NotificationLogsController extends AppController {
	public $uses = array('NotificationLog', 'SurveyUserVisit');
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array('RequestHandler');
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
		
	public function index() {

		$this->NotificationLog->bindModel(array('belongsTo' => array(
			'Project'
		)));
		$this->paginate = array('NotificationLog' => array(
			'contain' => array(
				'Project' => array(
					'fields' => array('Project.id', 'Project.client_rate', 'Project.priority'), 
					'SurveyVisitCache' => array(
						'fields' => array('SurveyVisitCache.ir', 'SurveyVisitCache.click', 'SurveyVisitCache.complete')
					)
				)
			),
			'limit' => 200,
			'order' => 'NotificationLog.id DESC'
		));
		$notification_logs = $this->paginate();
		$this->set(compact('notification_logs'));	
	}
	
	public function user($user_id) {
		$this->NotificationLog->bindModel(array('belongsTo' => array(
			'Project'
		)));
		$notification_logs = $this->NotificationLog->find('all', array(
			'fields' => array(
				'NotificationLog.project_id', 'NotificationLog.click_timestamp', 'NotificationLog.status', 'NotificationLog.sent', 'NotificationLog.created'
			),
			'conditions' => array(
				'NotificationLog.user_id' => $user_id,
			),
			'contain' => array(
				'Project' => array(
					'fields' => array('Project.id', 'Project.client_rate', 'Project.priority'), 
					'SurveyVisitCache' => array(
						'fields' => array('SurveyVisitCache.ir', 'SurveyVisitCache.click', 'SurveyVisitCache.complete')
					)
				)
			),
			'order' => 'NotificationLog.id DESC'
		));
		
		$project_ids = Hash::extract($notification_logs, '{n}.Project.id');
		if (!empty($project_ids)) {
			$project_ids = array_unique($project_ids);
		}
		
		$survey_user_visits_original = $this->SurveyUserVisit->find('all', array(
			'fields' => array('SurveyUserVisit.survey_id', 'SurveyUserVisit.status', 'SurveyUserVisit.accessed_from', 'SurveyUserVisit.created'),
			'conditions' => array(
				'SurveyUserVisit.user_id' => $user_id,
				'SurveyUserVisit.survey_id' => $project_ids
			),
			'recursive' => -1
		)); 
		$survey_user_visits = false;
		if ($survey_user_visits_original) {
			$survey_user_visits = array();
			foreach ($survey_user_visits_original as $survey_user_visit) {
				$survey_user_visits[$survey_user_visit['SurveyUserVisit']['survey_id']] = $survey_user_visit; 
			}
		}
		
		$this->set(compact('user_id', 'survey_user_visits', 'notification_logs')); 
	}
}