<?php
App::uses('AppController', 'Controller');

class AcquisitionPartnersController extends AppController {
	public $uses = array('AcquisitionPartner');
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array();
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		if ($this->request->is('post') && isset($this->data['AcquisitionPartner']) && isset($this->data['delete'])) {
			$deleted = 0;
			foreach ($this->data['AcquisitionPartner'] as $id => $value) {
				if ($value == 0 || $id == 'null') {
					continue;
				}

				$this->AcquisitionPartner->delete($id);
				$deleted++;
			}
			if ($deleted > 0) {
				$this->Session->setFlash('You have deleted ' . $deleted . ' Acquisition Partners' . '.', 'flash_success');
				$this->redirect(array('action' => 'index'));
			}
		}
		
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		
		$paginate = array('AcquisitionPartner' => array(
			'limit' => 500,
			'order' => 'AcquisitionPartner.name ASC',
		));
		$this->paginate = $paginate;
		$this->set('acquisition_partners', $this->paginate());
	}
	
	public function add() {
		if ($this->request->is('post')) {
            if ($this->AcquisitionPartner->save($this->request->data)) {
                $this->Session->setFlash(__('Partner has been created.'), 'flash_success');
                return $this->redirect(array('action' => 'index'));
            }
        }
	}
	
	public function edit($id) {
    	$acquisition_partner = $this->AcquisitionPartner->findById($id);
    	if (!$acquisition_partner) {
        	throw new NotFoundException(__('Invalid partner'));
    	}
    	if ($this->request->is('post') || $this->request->is('put')) {
    		$source = array('AcquisitionPartner' => array(
        		'id' => $id,
       			'name' => $this->request->data['AcquisitionPartner']['name'],
       			'source' => $this->request->data['AcquisitionPartner']['source'],
       			'post_registration_pixel' => $this->request->data['AcquisitionPartner']['post_registration_pixel'],
       			'affiliate_network' => $this->request->data['AcquisitionPartner']['affiliate_network']
			)); 
        	if ($this->AcquisitionPartner->save($source, true, array('name', 'source', 'post_registration_pixel', 'affiliate_network'))) {
        	    $this->Session->setFlash(__('Partner has been updated.'), 'flash_success');
        	    return $this->redirect(array('action' => 'index'));
        	}
        	$this->Session->setFlash(__('Unable to update the source.'), 'flash_error');
    	}
    	if (!$this->request->data) {
        	$this->request->data = $acquisition_partner;
    	}		
	}
}