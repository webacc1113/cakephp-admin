<?php
App::uses('AppController', 'Controller');

class PartnersController extends AppController {
	public $uses = array('Partner');
	public $helpers = array('Text', 'Html', 'Time');
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		if ($this->request->is('post') && isset($this->data['Partner']) && isset($this->data['delete'])) {
			$deleted = 0;
			foreach ($this->data['Partner'] as $id => $value) {
				if ($value == 0 || $id == 'null') {
					continue;
				}

				$this->Partner->create();
				$this->Partner->save(array('Partner' => array(
					'id' => $id,
					'deleted' => true,
				)), true, array('deleted'));
				$deleted++;
			}
			
			if ($deleted > 0) {
				$this->Session->setFlash('You have deleted ' . $deleted . ' Partners' . '.', 'flash_success');
				$this->redirect(array('action' => 'index'));
			}
		}
		
		$limit = 500;
		$conditions = array('Partner.deleted' => false);
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}

		$paginate = array(
			'Partner' => array(
				'limit' => $limit,
				'order' => 'Partner.partner_name ASC',
			)
		);
		if (!empty($conditions)) {
			$paginate['Partner']['conditions'] = $conditions;
		}
		$this->paginate = $paginate;
		$this->set('partners', $this->paginate());
	}
	
	public function add() {
		if ($this->request->is('post')) {
            $this->Partner->create();
            if ($this->Partner->save($this->request->data)) {
                $this->Session->setFlash(__('Partner has been saved.'), 'flash_success');
                return $this->redirect(array('action' => 'index'));
            }
            $this->Session->setFlash(__('Unable to add the partner.'), 'flash_error');
        }
	}
	
	public function edit($id) {
		$partner = $this->Partner->findById($id);
    	if (!$partner) {
        	throw new NotFoundException(__('Invalid partner'));
    	}
    	if ($this->request->is('post') || $this->request->is('put')) {
    		$partner = array('Partner' => array(
        		'id' => $id,
          		'partner_name' => $this->request->data['Partner']['partner_name'],
          		'notes' => $this->request->data['Partner']['notes'],
				'complete_url' => $this->request->data['Partner']['complete_url'],
				'nq_url' => $this->request->data['Partner']['nq_url'],
				'oq_url' => $this->request->data['Partner']['oq_url']
          	));
        	if ($this->Partner->save($partner, true, array('partner_name', 'notes', 'complete_url', 'oq_url', 'nq_url'))) {
        	    $this->Session->setFlash(__('Partner has been updated.'), 'flash_success');
        	    return $this->redirect(array('action' => 'index'));
        	}
        	$this->Session->setFlash(__('Unable to update the partner.'), 'flash_error');
    	}
    	if (!$this->request->data) {
        	$this->request->data = $partner;
    	}
	}
	
	public function ajax_get_redirects($partner_id = null) {		
		$partner = $this->Partner->find('first', array(
			'conditions' => array(
				'Partner.id' => $partner_id,
				'Partner.deleted' => false
			)
		));
		$redirects = array(
			'complete_url' => $partner['Partner']['complete_url'],
			'nq_url' => $partner['Partner']['nq_url'],
			'oq_url' => $partner['Partner']['oq_url'],
		);
		return new CakeResponse(array(
			'body' => json_encode($redirects), 
			'type' => 'json',
			'status' => 201,
		));
	}
}