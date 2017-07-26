<?php
App::uses('AppController', 'Controller');

class UserNotificationReportsController extends AppController {
	public $helpers = array('Html', 'Time');
	public $uses = array('UserNotificationReport', 'User');
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		if ($this->request->is('put') || $this->request->is('post')) {
			$current_time = date(DB_DATETIME);
			$count = $this->User->find('count', array(
				'conditions' => array(
					'User.last_touched >=' => date(DB_DATETIME, strtotime('-'.$this->request->data['UserNotificationReport']['hours'].' hours', strtotime($current_time))),
				),
				'recursive' => -1
			));

			if ($count > 0) {
				$this->UserNotificationReport->create();
				$this->UserNotificationReport->save(array('UserNotificationReport' => array(					
					'user_id' => $this->current_user['Admin']['id'],
					'hours' => $this->request->data['UserNotificationReport']['hours']
				)));
				
				$exec_query = ROOT.'/app/Console/cake user export_users_notification_report "'.$this->UserNotificationReport->getLastInsertId() . '"';
				$exec_query.= "  > /dev/null 2>&1 &"; 
				CakeLog::write('query_commands', $exec_query); 
				exec($exec_query, $output);
				$this->Session->setFlash('Report being generated - please wait for few minutes to download report.', 'flash_success'); 
				$this->redirect(array('controller' => 'user_notification_reports', 'action' => 'index'));
			}
			else {
				$this->Session->setFlash('No records found in this time period.', 'flash_error'); 
			}
		}
		
		$this->UserNotificationReport->bindModel(array('belongsTo' => array(
			'Admin' => array(
				'foreignKey' => 'user_id',
				'fields' => array('id', 'admin_user')
			)
		)));
		
		$limit = 50;
		$paginate = array(
			'UserNotificationReport' => array(			
				'contain' => array(
					'Admin'
				),
				'limit' => $limit,
				'order' => 'UserNotificationReport.id DESC',
			)
		);
		$this->paginate = $paginate;
		$this->set('user_notification_reports', $this->paginate('UserNotificationReport'));		
		$this->set(compact('user_notification_reports'));
	}
	
	public function report_check($id) {
		$user_notification_report = $this->UserNotificationReport->findById($id);
    	return new CakeResponse(array(
			'body' => json_encode(array(
				'status' => $user_notification_report['UserNotificationReport']['status'],
				'file' => Router::url(array('controller' => 'user_notification_reports', 'action' => 'download', $user_notification_report['UserNotificationReport']['id']))
			)), 
			'type' => 'json',
			'status' => '201'
		));
	}
	
	function download($user_notification_report_id) {
		if(empty($user_notification_report_id)) {
			throw new NotFoundException();
		}
		
		$user_notification_report = $this->UserNotificationReport->find('first', array(
			'conditions' => array(
				'UserNotificationReport.id' => $user_notification_report_id
			),
			'fields' => array(
				'id', 'status', 'path'
			)
		));
		
		if ($user_notification_report) {
			if ($user_notification_report['UserNotificationReport']['status'] == 'complete') {
				$settings = $this->Setting->find('list', array(
					'fields' => array('name', 'value'),
					'conditions' => array(
						'Setting.name' => array(
							's3.access',
							's3.secret',
							's3.bucket',
							's3.host'
						),
						'Setting.deleted' => false
					)
				));
				
				CakePlugin::load('Uploader');
				App::import('Vendor', 'Uploader.S3');
				
				$file = $user_notification_report['UserNotificationReport']['path'];
							
				// we store with first slash; but remove it for S3
				if (substr($file, 0, 1) == '/') {
					$file = substr($file, 1, strlen($file)); 
				}
				
				$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);
				$url = $S3->getAuthenticatedURL($settings['s3.bucket'], $file, 3600, false, false);
				
				$this->redirect($url);
			}
			else {
				$this->Session->setFlash('A report is already being generated - please wait until it is done.', 'flash_error');
				$this->redirect(array(
					'controller' => 'user_notification_reports',
					'action' => 'index'
				));
			}
		}
		else {
			throw new NotFoundException();
		}
	}
}