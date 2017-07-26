<?php
App::uses('AppController', 'Controller');

class StatisticsController extends AppController {

	public $uses = array('User', 'Transaction', 'Group', 'SurveyUserVisit', 'SurveyVisit', 'Project', 'Site', 'ProjectOption', 'MailQueue');
	public $helpers = array('Html', 'Time');
	public $components = array(); 
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		$project_options = $this->ProjectOption->find('all', array(
			'conditions' => array(
				'ProjectOption.name' => array(
					'open_projects', 'total_users', 'total_new_users'
				)
			)
		));
		
		$project_count = 0;
		$user_total_count = 0;
		$user_verified = 0;
		if ($project_options) {
			foreach ($project_options as $project_option) {
				if ($project_option['ProjectOption']['name'] == 'open_projects') {
					$project_count = $project_option['ProjectOption']['value'];
				}
				elseif ($project_option['ProjectOption']['name'] == 'total_users') {
					$user_total_count = $project_option['ProjectOption']['value'];
				}
				elseif ($project_option['ProjectOption']['name'] == 'total_new_users') {
					$user_verified = $project_option['ProjectOption']['value'];
				}
			}
		}
		
		$mintvine_group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'mintvine'
			),
			'recursive' => -1
		));
		$quickbook_connect_status = $this->Site->get_quickbook_status();
		$this->set(compact('user_total_count', 'project_count', 'user_verified', 'quickbook_connect_status', 'mintvine_group')); 
	}
	
	
	public function ajax_mail_queue() {
		$this->layout = 'ajax';
		$queued = $this->MailQueue->find('count', array(
			'conditions' => array(
				'MailQueue.status' => 'Queued'
			)
		));
		$sending = $this->MailQueue->find('count', array(
			'conditions' => array(
				'MailQueue.status' => 'Sending'
			)
		));
		$total = $this->MailQueue->find('count');
		$this->set(compact('total', 'sending', 'queued'));
	}
}
