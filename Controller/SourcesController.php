<?php
App::uses('AppController', 'Controller');

class SourcesController extends AppController {
	public $uses = array('Source', 'SourceReport');
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array();
	
	public function beforeFilter() {
		parent::beforeFilter();
		
		if (in_array($this->action, array('add', 'edit'))) {			
			$acquisition_partners = $this->Source->AcquisitionPartner->find('list', array(
				'fields' => array('id', 'name'),
				'conditions' => array(
					'AcquisitionPartner.active' => true
				),
				'order' => 'AcquisitionPartner.name ASC'
			));
			$lander_urls_all = $this->Source->LanderUrl->find('all', array(
				'fields' => array('id', 'name', 'path'),
				'conditions' => array(
					'LanderUrl.active' => true
				),
				'order' => 'LanderUrl.name ASC'
			));
			$lander_urls = array();
			foreach ($lander_urls_all as $lander_url) {
				$lander_urls[$lander_url['LanderUrl']['id']] = empty($lander_url['LanderUrl']['name']) ? $lander_url['LanderUrl']['path']: $lander_url['LanderUrl']['name']; 
			}
			
			$this->set(compact('lander_urls', 'acquisition_partners'));
		}
	}
	
	public function export($source_id) {
		if ($this->request->is('post') || $this->request->is('put')) {
			$errors = array();
			if (!empty($this->request->data['Source']['date_to']) && empty($this->request->data['Source']['date_from'])) {
				$errors[] = 'Please enter start date';
			}
			elseif (!empty($this->request->data['Source']['date_to']) && !empty($this->request->data['Source']['date_from'])) {
				if (strtotime($this->request->data['Source']['date_from']) > strtotime($this->request->data['Source']['date_to'])) {
					$errors[] = 'Start date should be less than end date';
				}
			}
			
			if (empty($errors)) {
				$sourceReportSource = $this->SourceReport->getDataSource();
				$sourceReportSource->begin();
				$this->SourceReport->create();
				$this->SourceReport->save(array('SourceReport' => array(
					'user_id' => $this->current_user['Admin']['id'],
					'source_id' => $source_id,
					'date_from' => !empty($this->request->data['Source']['date_from']) ? date(DB_DATE, strtotime($this->request->data['Source']['date_from'])) . ' 00:00:00' . '"' : null,
					'date_to' => !empty($this->request->data['Source']['date_to']) ? date(DB_DATE, strtotime($this->request->data['Source']['date_to'])) . ' 23:59:59' . '"' : null,
				)));
				$source_report_id = $this->SourceReport->getInsertId();
				$sourceReportSource->commit();
				
				$query = ROOT.'/app/Console/cake user export_source_data "source" '.$source_id.' "'.$source_report_id.'"';
				if (!empty($this->request->data['Source']['date_from'])) {
					$query .= ' "' . date(DB_DATE, strtotime($this->request->data['Source']['date_from'])) . ' 00:00:00' . '"';
				}
				if (!empty($this->request->data['Source']['date_to'])) {
					$query .= ' "' . date(DB_DATE, strtotime($this->request->data['Source']['date_to'])) . ' 23:59:59' . '"';
				}				
				$query.= "  > /dev/null 2>&1 &"; 
				CakeLog::write('query_commands', $query); 
				exec($query);
				
				$this->Session->setFlash('Report being generated - please wait for 10-15 minutes to dowalond report.', 'flash_success');
				$this->redirect(array('controller' => 'sources', 'action' => 'reports', $source_id));
			}
			else {
				$this->Session->setFlash(implode('<br />', $errors), 'flash_error');
			}
		}
	}
	
	public function index() {
		$limit = 50;
		
		$conditions = array();
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		if (isset($this->request->query['deactivated']) && $this->request->query['deactivated'] == '1') {
			$active = false;
		}
		else {
			$active = true;
		}
		
		$paginate = array(
			'Source' => array(
				'conditions' => array(
					'Source.active' => $active
				),
				'limit' => $limit,
				'order' => 'Source.name ASC',
			)
		);		
		if (!empty($conditions)) {
			$paginate['Source']['conditions'] = $conditions;
		}		
		$this->paginate = $paginate;
		$this->set('sources', $this->paginate());
		$this->set('active', $active);
	}
	
	public function add() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$this->Source->create();
			$save = $this->Source->save($this->request->data); 
			if ($save) {
				$this->Session->setFlash('Your source has been created.', 'flash_success');
				$this->redirect(array('action' => 'index')); 
			}
            $this->Session->setFlash(__('Unable to add the source.'), 'flash_error');
        }
	}
	
	public function instructions() {
		
	}
	
	public function edit($source_id) {
		$source = $this->Source->findById($source_id); 
		if (!$source) {
			$this->Session->setFlash('That source does not exist', 'flash_error');
			$this->redirect(array('action' => 'index')); 
		}
		if ($this->request->is('post') || $this->request->is('put')) {
			$this->Source->create();
			$save = $this->Source->save($this->request->data, true, array('acquisition_partner_id', 'publisher_id_key', 'lander_url_id', 'abbr', 'name', 'post_registration_pixel', 'post_registration_postback')); 
			if ($save) {
				$this->Session->setFlash('Your source has been created.', 'flash_success');
				$this->redirect(array('action' => 'index')); 
			}
            $this->Session->setFlash(__('Unable to add the source.'), 'flash_error');
		}
		else {
			$this->data = $source;
		}
	}
	
	public function deactivate($source_id) {
		$source = $this->Source->findById($source_id); 
		if (!$source) {
			$this->Session->setFlash('That source does not exist', 'flash_error');
			$this->redirect(array('action' => 'index')); 
		}
		if ($this->request->is('post') || $this->request->is('put')) {
			$this->Source->create();
			$save = $this->Source->save(array('Source' => array(
				'id' => $source_id,
				'active' => false
			)), true, array('active')); 
			if ($save) {
				$this->Session->setFlash('Your source has been deactivated.', 'flash_success');
				$this->redirect(array('action' => 'index')); 
			}
            $this->Session->setFlash(__('Unable to add the source.'), 'flash_error');
		}
	}
	
	public function activate($source_id) {
		$source = $this->Source->findById($source_id); 
		if (!$source) {
			$this->Session->setFlash('That campaign does not exist', 'flash_error');
			$this->redirect(array('action' => 'index')); 
		}
		if ($this->request->is('post') || $this->request->is('put')) {
			$this->Source->create();
			$save = $this->Source->save(array('Source' => array(
				'id' => $source_id,
				'active' => true
			)), true, array('active')); 
			if ($save) {
				$this->Session->setFlash('Your campaign has been reactivated.', 'flash_success');
				$this->redirect(array('action' => 'index', '?' => array('deactivated' => 1))); 
			}
            $this->Session->setFlash(__('Unable to add the campaign.'), 'flash_error');
		}
	}
	
	function download($source_report_id) {
		if(empty($source_report_id)) {
			throw new NotFoundException();
		}
		
		$report = $this->SourceReport->find('first', array(
			'conditions' => array(
				'SourceReport.id' => $source_report_id
			),
			'fields' => array(
				'id', 'status', 'path'
			)
		));
		
		if ($report) {
			if ($report['SourceReport']['status'] == 'complete') {
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
				
				$file = $report['SourceReport']['path'];
							
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
					'controller' => 'sources',
					'action' => 'index'
				));
			}
		}
		else {
			throw new NotFoundException();
		}
	}
	
	function reports($source_id) {
		if (empty($source_id)) {
			throw new NotFoundException();
		}
		
		$source = $this->Source->findById($source_id);
		if (!$source) {
			$this->Session->setFlash('That campaign does not exist', 'flash_error');
			$this->redirect(array('action' => 'index'));
		}
		
		$this->SourceReport->bindModel(array('belongsTo' => array(
			'Admin' => array(
				'foreignKey' => 'user_id',
				'fields' => array('id', 'admin_user')
			)
		)));
		
		$limit = 50;
		$paginate = array(
			'SourceReport' => array(
				'conditions' => array(
					'SourceReport.source_id' => $source_id
				),
				'contain' => array(
					'Admin'
				),
				'limit' => $limit,
				'order' => 'SourceReport.id DESC',
			)
		);
		$this->paginate = $paginate;
		$this->set('reports', $this->paginate('SourceReport'));
		$this->set('source', $source);
	}
	
	public function check($id) {
		$report = $this->SourceReport->findById($id);
    	return new CakeResponse(array(
			'body' => json_encode(array(
				'status' => $report['SourceReport']['status'],
				'file' => Router::url(array('controller' => 'sources', 'action' => 'download', $report['SourceReport']['id']))
			)), 
			'type' => 'json',
			'status' => '201'
		));
	}
}