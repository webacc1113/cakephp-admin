<?php
App::uses('AppController', 'Controller');

class AnalyticsController extends AppController {
	public $helpers = array('Text', 'Html', 'Time');
	public $uses = array('AnalyticReport');
	public $components = array();
	
	public function beforeFilter() {
		parent::beforeFilter();		
	}
	
	function index() {
		$this->AnalyticReport->bindModel(array('belongsTo' => array(
			'Admin' => array(
				'foreignKey' => 'user_id',
				'fields' => array('id', 'admin_user')
			)
		)));
		
		$limit = 50;
		$paginate = array(
			'AnalyticReport' => array(
				'contain' => array(
					'Admin'
				),
				'limit' => $limit,
				'order' => 'AnalyticReport.id DESC',
			)
		);
		$this->paginate = $paginate;
		$this->set('reports', $this->paginate('AnalyticReport'));		
	}
	
	function export() {
		if ($this->request->is(array('put', 'post'))) {
			$errors = array();
			$queued_report = $this->AnalyticReport->find('first', array(
				'conditions' => array(
					'AnalyticReport.status' => 'queued'
				),
				'recursive' => -1
			));
			if ($queued_report) {
				$errors[] = 'Only one report generation allowed at a time, please wait for a while until previous reports completes.';
			}
			elseif (empty($this->request->data['AnalyticReport']['date_to']) || empty($this->request->data['AnalyticReport']['date_from'])) {
				$errors[] = 'Please enter start date and end date';
			}
			elseif (!empty($this->request->data['AnalyticReport']['date_to']) && !empty($this->request->data['AnalyticReport']['date_from'])) {
				if (strtotime($this->request->data['AnalyticReport']['date_from']) > strtotime($this->request->data['AnalyticReport']['date_to'])) {
					$errors[] = 'Start date should be less than end date';
				}
			}
			
			if (empty($errors)) {
				$analyticReportSource = $this->AnalyticReport->getDataSource();
				$analyticReportSource->begin();
				$this->AnalyticReport->create();
				$this->AnalyticReport->save(array('AnalyticReport' => array(
					'user_id' => $this->current_user['Admin']['id'],
					'type' => $this->request->data['AnalyticReport']['type'],
					'date_from' => !empty($this->request->data['AnalyticReport']['date_from']) ? date(DB_DATE, strtotime($this->request->data['AnalyticReport']['date_from'])) . ' 00:00:00' . '"' : null,
					'date_to' => !empty($this->request->data['AnalyticReport']['date_to']) ? date(DB_DATE, strtotime($this->request->data['AnalyticReport']['date_to'])) . ' 23:59:59' . '"' : null,
				)));
				$analytic_report_id = $this->AnalyticReport->getInsertId();
				$analyticReportSource->commit();
				
				$query = ROOT.'/app/Console/cake analytics ' . $this->request->data['AnalyticReport']['type'];
				if (!empty($this->request->data['AnalyticReport']['date_from'])) {
					$query .= ' "' . date(DB_DATE, strtotime($this->request->data['AnalyticReport']['date_from'])). '"';
				}
				if (!empty($this->request->data['AnalyticReport']['date_to'])) {
					$query .= ' "' . date(DB_DATE, strtotime($this->request->data['AnalyticReport']['date_to'])). '"';
				}	
				$query.= ' '.$analytic_report_id;
				
				$query.= "  > /dev/null 2>&1 &";
				CakeLog::write('query_commands', $query); 
				exec($query);
				
				$this->Session->setFlash('Report being generated - please check back later.', 'flash_success');
				$this->redirect(array('controller' => 'analytics', 'action' => 'index'));
			}
			else {
				$this->Session->setFlash(implode('<br />', $errors), 'flash_error');
			}
		}
	}
	
	public function check($id) {
		$report = $this->AnalyticReport->findById($id);
		if ($report) {
    		return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => $report['AnalyticReport']['status'],
					'file' => Router::url(array('controller' => 'analytics', 'action' => 'download', $report['AnalyticReport']['id']))
				)), 
				'type' => 'json',
				'status' => '201'
			));
		}
		else {
    		return new CakeResponse(array(
				'body' => json_encode(array('')), 
				'type' => 'json',
				'status' => '201'
			));
		}
	}
	
	function download($analytic_report_id) {
		if(empty($analytic_report_id)) {
			throw new NotFoundException();
		}
		
		$report = $this->AnalyticReport->find('first', array(
			'conditions' => array(
				'AnalyticReport.id' => $analytic_report_id
			),
			'fields' => array(
				'id', 'status', 'path'
			)
		));
		
		if ($report) {
			if ($report['AnalyticReport']['status'] == 'complete') {
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
				
				$file = $report['AnalyticReport']['path'];
							
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
					'controller' => 'analytics',
					'action' => 'index'
				));
			}
		}
		else {
			throw new NotFoundException();
		}
	}
}