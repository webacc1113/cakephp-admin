<?php
App::uses('AppController', 'Controller');

class DailyAnalysisPropertiesController extends AppController {
	public $helpers = array('Text', 'Html', 'Time');
	public $uses = array('DailyAnalysisProperty', 'DailyAnalysis');
	public $components = array();
	
	public function beforeFilter() {
		parent::beforeFilter();		
	}
	
	public function index() {
		$paginate = array(
			'DailyAnalysisProperty' => array(
				'limit' => 500,
				'order' => 'DailyAnalysisProperty.name ASC',
			)
		);
		$this->paginate = $paginate;
		$daily_analysis_properties = $this->paginate('DailyAnalysisProperty');
		$this->set(compact('daily_analysis_properties'));
	}
	
	public function add() {
		if ($this->request->is('post')) {
			$properties = explode("\n", $this->request->data['DailyAnalysisProperty']['properties']);
			if (!empty($properties)) {
				foreach ($properties as $property) {
					$this->DailyAnalysisProperty->create();
					$this->DailyAnalysisProperty->save(array('DailyAnalysisProperty' => array(
						'name' => $property
					)));
				}
			}
			$this->Session->setFlash('Properties created.', 'flash_success');
			return $this->redirect(array('action' => 'index'));
		}
	}
	
	public function edit($id) {
		$property = $this->DailyAnalysisProperty->findById($id);
    	if (!$property) {
        	throw new NotFoundException(__('Invalid Daily Analysis Property'));
    	}
    	if ($this->request->is('post') || $this->request->is('put')) {
			$saved = $this->DailyAnalysisProperty->save(array('DailyAnalysisProperty' => array(
				'id' => $id,
				'name' => $this->request->data['DailyAnalysisProperty']['name']
			)), true, array('name')); 
			if ($saved) {
        	    $this->Session->setFlash(__('Daily Analysis Property has been updated.'), 'flash_success');
        	    return $this->redirect(array('action' => 'index'));
        	}
        	$this->Session->setFlash(__('Unable to update the Daily Analysis Property.'), 'flash_error');
    	}
    	if (!$this->request->data) {
        	$this->request->data = $property;
    	}
	}
	
	public function delete() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$id = $this->request->data['id'];
			$this->DailyAnalysisProperty->delete($id);
			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => '1'
				)), 
				'type' => 'json',
				'status' => '201'
			));
		}	
	}
}