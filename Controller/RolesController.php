<?php
App::uses('AppController', 'Controller');
App::import('Lib', 'MintVineUser');

class RolesController extends AppController {

	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		$this->set('roles', $this->paginate('Role'));
	}
	
	public function add() {
		if ($this->request->is('post')) {
            $this->Role->create();
            if ($this->Role->save($this->request->data)) {
                $this->Session->setFlash(__('Permission group has been saved.'), 'flash_success');
                return $this->redirect(array('action' => 'index'));
            }
			
            $this->Session->setFlash(__('Unable to save permission group.'), 'flash_error');
        }
	}
	
	public function edit($id) {
		$role = $this->Role->findById($id);
    	if (!$role) {
        	throw new NotFoundException(__('Invalid Permission group'));
    	}
		
    	if ($this->request->is('post') || $this->request->is('put')) {
			if ($this->Role->save($this->request->data)) {
        	    $this->Session->setFlash(__('Permission group has been updated.'), 'flash_success');
        	    return $this->redirect(array('action' => 'index'));
        	}
        	$this->Session->setFlash(__('Unable to update the Permission group.'), 'flash_error');
    	}
		
    	if (!$this->request->data) {
        	$this->request->data = $role;
    	}
	}
	
	public function delete() {
		if ($this->request->is('post') || $this->request->is('put')) {
    		$id = $this->request->data['id'];
			$this->Role->delete($id);
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