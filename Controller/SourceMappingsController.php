<?php
App::uses('AppController', 'Controller');

class SourceMappingsController extends AppController {
	public $uses = array('SourceMapping', 'AcquisitionPartner', 'SourceReport');
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array();
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		$adword_acquisition_partner = $this->AcquisitionPartner->find('first', array(
			'conditions' => array(
				'AcquisitionPartner.source' => 'adwords'
			)
		));
		if (!$adword_acquisition_partner) {
			$this->Session->setFlash('You are missing the <a href="/acquisition_partners/">Adwords acquisition partner</a>. This feature will not function correctly without it.', 'flash_error'); 
		}
		
		$limit = 50;
		
		$conditions = array();
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		$paginate = array(
			'SourceMapping' => array(
				'limit' => 500,
				'order' => 'SourceMapping.utm_source ASC',
				'conditions' => array(
					'SourceMapping.deleted' => null
				)
			)
		);
		$this->paginate = $paginate;
		$this->set('source_mappings', $this->paginate());
	}
	
	public function add() {
		$acquisition_partners = $this->AcquisitionPartner->find('list', array(
			'fields' => array('source', 'name'),
			'order' => 'AcquisitionPartner.name'
		));
		if ($this->request->is('post') || $this->request->is('put')) {
			$this->request->data['SourceMapping']['automated'] = false;
			$acquisition_partner = $this->AcquisitionPartner->find('first', array(
				'conditions' => array(
					'AcquisitionPartner.source' => $this->request->data['SourceMapping']['acquisition_partner_id']
				)
			));
			if ($acquisition_partner) {
				$this->request->data['SourceMapping']['acquisition_partner_id'] = $acquisition_partner['AcquisitionPartner']['id'];
			}
			$save = $this->SourceMapping->save($this->request->data); 
			if ($save) {
				$this->Session->setFlash('Your mapping rule has been created. All future entries will map correctly into the right bucket.', 'flash_success');
				return $this->redirect(array('action' => 'index'));
			}
		}
		$this->set(compact('acquisition_partners'));
	}
	
	public function edit($source_id = null) {
		$source_mapping = $this->SourceMapping->find('first', array(
			'conditions' => array(
				'SourceMapping.id' => $source_id,
				'SourceMapping.deleted' => null
			)
		));
		if (!$source_mapping) {
			$this->Session->setFlash('This source mapping cannot be found.', 'flash_session');
			return $this->redirect(array('action' => 'index'));
		}
		if ($this->request->is('post') || $this->request->is('put')) {
			$acquisition_partner = $this->AcquisitionPartner->find('first', array(
				'conditions' => array(
					'AcquisitionPartner.source' => $this->request->data['SourceMapping']['acquisition_partner_id']
				)
			));
			if ($acquisition_partner) {
				$this->request->data['SourceMapping']['acquisition_partner_id'] = $acquisition_partner['AcquisitionPartner']['id'];
			}
			$this->SourceMapping->create();
			$save = $this->SourceMapping->save($this->request->data, true, array('utm_source', 'name', 'acquisition_partner_id', 'publisher_id'));
			if ($save) {
				$this->Session->setFlash('Your changes have been saved.', 'flash_success');
				return $this->redirect(array('action' => 'index'));
			}
		}
		else {
			$this->request->data = $source_mapping;
		}
		$acquisition_partners = $this->AcquisitionPartner->find('list', array(
			'fields' => array('source', 'name'),
			'order' => 'AcquisitionPartner.name'
		));
		$this->set(compact('acquisition_partners'));
	}
	
	
	public function delete($source_mapping_id) {
		$source_mapping = $this->SourceMapping->find('first', array(
			'conditions' => array(
				'SourceMapping.id' => $source_mapping_id
			)
		));
		if ($this->request->is('post')) {
			$this->SourceMapping->create();
			$this->SourceMapping->save(array('SourceMapping' => array(
				'id' => $source_mapping,
				'deleted' => date(DB_DATETIME),
				'modified' => false
			)), array(
				'fieldList' => array('deleted'),
				'callbacks' => false,
				'validate' => false
			)); 
			$this->Session->setFlash('Your mapping has been deleted.', 'flash_success');
			return $this->redirect(array('action' => 'index'));
		}
		$this->set(compact('source_mapping'));
	}
	
	function export($source_mapping_id = null) {
		if (empty($source_mapping_id)) {
			throw new NotFoundException();
		}
		
		if ($this->request->is('post') || $this->request->is('put')) {
			$errors = array();
			if (!empty($this->request->data['SourceMapping']['date_to']) && empty($this->request->data['SourceMapping']['date_from'])) {
				$errors[] = 'Please enter start date';
			}
			elseif (!empty($this->request->data['SourceMapping']['date_to']) && !empty($this->request->data['SourceMapping']['date_from'])) {
				if (strtotime($this->request->data['SourceMapping']['date_from']) > strtotime($this->request->data['SourceMapping']['date_to'])) {
					$errors[] = 'Start date should be less than end date';
				}
			}
			
			if (empty($errors)) {
					$sourceReportSource = $this->SourceReport->getDataSource();
					$sourceReportSource->begin();
					$this->SourceReport->create();
					$this->SourceReport->save(array('SourceReport' => array(
						'user_id' => $this->current_user['Admin']['id'],
						'source_mapping_id' => $source_mapping_id,
						'date_from' => !empty($this->request->data['SourceMapping']['date_from']) ? date(DB_DATE, strtotime($this->request->data['SourceMapping']['date_from'])) . ' 00:00:00' . '"' : null,
						'date_to' => !empty($this->request->data['SourceMapping']['date_to']) ? date(DB_DATE, strtotime($this->request->data['SourceMapping']['date_to'])) . ' 23:59:59' . '"' : null,
					)));
					$source_report_id = $this->SourceReport->getInsertId();
					$sourceReportSource->commit();
					
					$query = ROOT.'/app/Console/cake user export_source_data "mapping" '.$source_mapping_id.' "'.$source_report_id.'"';
					if (!empty($this->request->data['SourceMapping']['date_from'])) {
						$query .= ' "' . date(DB_DATE, strtotime($this->request->data['SourceMapping']['date_from'])) . ' 00:00:00' . '"';
					}
					if (!empty($this->request->data['SourceMapping']['date_to'])) {
						$query .= ' "' . date(DB_DATE, strtotime($this->request->data['SourceMapping']['date_to'])) . ' 23:59:59' . '"';
					}				
					$query.= "  > /dev/null 2>&1 &";
					CakeLog::write('query_commands', $query); 
					exec($query);
					
					$this->Session->setFlash('Report being generated - please wait for 10-15 minutes to dowalond report.', 'flash_success');
					$this->redirect(array('controller' => 'source_mappings', 'action' => 'reports', $source_mapping_id));
			}
			else {
				$this->Session->setFlash(implode('<br />', $errors), 'flash_error');
			}
		}
	}
	
	function reports($source_mapping_id = null) {
		if (empty($source_mapping_id)) {
			throw new NotFoundException();
		}
		
		$source_mapping = $this->SourceMapping->findById($source_mapping_id);
		if (!$source_mapping) {
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
					'SourceReport.source_mapping_id' => $source_mapping_id
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
		$this->set('source_mapping', $source_mapping);
	}
	
	public function check($id) {
		$report = $this->SourceReport->findById($id);
    	return new CakeResponse(array(
			'body' => json_encode(array(
				'status' => $report['SourceReport']['status'],
				'file' => Router::url(array('controller' => 'source_mappings', 'action' => 'download', $report['SourceReport']['id']))
			)), 
			'type' => 'json',
			'status' => '201'
		));
	}
	
	function download($source_mapping_report_id) {
		if(empty($source_mapping_report_id)) {
			throw new NotFoundException();
		}
		
		$report = $this->SourceReport->find('first', array(
			'conditions' => array(
				'SourceReport.id' => $source_mapping_report_id
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
					'controller' => 'source_mappings',
					'action' => 'index'
				));
			}
		}
		else {
			throw new NotFoundException();
		}
	}
}