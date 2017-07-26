<?php
App::uses('AppController', 'Controller');

class AcquisitionAlertsController extends AppController {
	public $uses = array('AcquisitionAlert', 'Source', 'SourceMapping');
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array();
	
	public function beforeFilter() {
		parent::beforeFilter();
		$this->events = array(
			'verified' => 'Verified',
			'registration' => 'Registration',
			'start' => 'Survey Start'
		);
		$this->set('events', $this->events);
	}
	
	public function index() {
		if ($this->request->is('post') && isset($this->data['AcquisitionAlert']) && isset($this->data['delete'])) {
			$deleted = 0;
			foreach ($this->data['AcquisitionAlert'] as $id => $value) {
				if ($value == 0 || $id == 'null') {
					continue;
				}

				$this->AcquisitionAlert->create();
				$this->AcquisitionAlert->save(array('AcquisitionAlert' => array(
					'id' => $id,
					'deleted' => date(DB_DATETIME),
					'modified' => false
				)), true, array('deleted'));
				$deleted++;
			}
			
			if ($deleted > 0) {
				$this->Session->setFlash('You have deleted ' . $deleted . ' Acquisition Alerts' . '.', 'flash_success');
				$this->redirect(array('action' => 'index'));
			}
		}

		$this->AcquisitionAlert->bindModel(array('belongsTo' => array(
			'SourceMapping' => array(
				'fields' => array('SourceMapping.id', 'SourceMapping.name'),
			),
			'Source' => array(
				'fields' => array('Source.id', 'Source.name'),
			),
		)));
		$this->paginate = array('AcquisitionAlert' => array(
			'conditions' => array(
				'AcquisitionAlert.deleted' => null
			),
			'limit' => 500,
			'order' => array(
				'AcquisitionAlert.source_id' => 'ASC',
				'AcquisitionAlert.source_mapping_id' => 'ASC'
			),
		));
		$acquisition_alerts = $this->paginate();
		$this->set(compact('acquisition_alerts'));
	}
	
	public function add() {
		$sources = $this->Source->find('list', array(
			'fields' => array('Source.id', 'Source.name'),
			'conditions' => array(
				'Source.active' => true
			),
			'order' => 'Source.name ASC'
		));
		$source_mappings = $this->SourceMapping->find('list', array(
			'fields' => array('SourceMapping.id', 'SourceMapping.name'),
			'conditions' => array(
				'SourceMapping.deleted' => null
			),
			'order' => 'SourceMapping.name ASC'
		));
		$this->set(compact('sources', 'source_mappings'));
		if ($this->request->is('post') || $this->request->is('put')) {
            if ($this->AcquisitionAlert->save($this->request->data)) {
				$this->Session->setFlash(__('Acquisition alert has been created.'), 'flash_success');
                return $this->redirect(array('action' => 'index'));
            }
        }
	}
	
	public function edit($acquisition_alert_id) {
		$sources = $this->Source->find('list', array(
			'fields' => array('Source.id', 'Source.name'),
			'conditions' => array(
				'Source.active' => true
			),
			'order' => 'Source.name ASC'
		));
		$source_mappings = $this->SourceMapping->find('list', array(
			'fields' => array('SourceMapping.id', 'SourceMapping.name'),
			'conditions' => array(
				'SourceMapping.deleted' => null
			),
			'order' => 'SourceMapping.name ASC'
		));
		$this->set(compact('sources', 'source_mappings'));
    	$acquisition_alert = $this->AcquisitionAlert->findById($acquisition_alert_id);
    	if (!$acquisition_alert) {
        	throw new NotFoundException(__('Invalid affiliate'));
    	}
    	if ($this->request->is('post') || $this->request->is('put')) {
        	if ($this->AcquisitionAlert->save($this->request->data, true, array('name', 'event', 'description', 'amount', 'trigger', 'alert_threshold_minutes', 'source_mapping_id', 'source_id'))) {
        	    $this->Session->setFlash(__('Acquisition alert has been updated.'), 'flash_success');
        	    return $this->redirect(array('action' => 'index'));
        	}
        	$this->Session->setFlash(__('Unable to update the acquisition alert.'), 'flash_error');
    	}
    	if (!$this->request->data) {
        	$this->request->data = $acquisition_alert;
    	}		
	}
}