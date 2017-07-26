<?php
App::uses('AppController', 'Controller');

class LanderUrlsController extends AppController {
	public $uses = array('LanderUrl');
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array();
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		if ($this->request->is('post') && isset($this->data['LanderUrl']) && isset($this->data['delete'])) {
			$deleted = 0;
			foreach ($this->data['LanderUrl'] as $id => $value) {
				if ($value == 0 || $id == 'null') {
					continue;
				}

				$this->LanderUrl->delete($id);
				$deleted++;
			}
			
			if ($deleted > 0) {
				$this->Session->setFlash('You have deleted ' . $deleted . ' Landers URLs' . '.', 'flash_success');
				$this->redirect(array('action' => 'index'));
			}
		}
		
		$limit = 50;
		$conditions = array();
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}

		$paginate = array(
			'LanderUrl' => array(
				'limit' => 500,
				'order' => 'LanderUrl.path ASC',
			)
		);
		$this->paginate = $paginate;
		$this->set('lander_urls', $this->paginate());
	}
	
	public function add() {
		if ($this->request->is('post')) {
            if ($this->LanderUrl->save($this->request->data)) {
                $this->Session->setFlash(__('Lander URL has been created.'), 'flash_success');
                return $this->redirect(array('action' => 'index'));
            }
        }
	}
	
	public function edit($id) {
    	$lander_url = $this->LanderUrl->findById($id);
		if (!$lander_url) {
        	throw new NotFoundException(__('Invalid offer'));
		}
		if ($this->request->is('post') || $this->request->is('put')) {
			$lander_url = array('LanderUrl' => array(
				'id' => $id,
				'path' => $this->request->data['LanderUrl']['path'],
				'name' => $this->request->data['LanderUrl']['name'],
				'description' => $this->request->data['LanderUrl']['description'],
				'browser_title' => $this->request->data['LanderUrl']['browser_title'],
				'heading' => $this->request->data['LanderUrl']['heading'],
				'content' => $this->request->data['LanderUrl']['content'],
				'source_name' => $this->request->data['LanderUrl']['source_name'],
			)); 
        	if ($this->LanderUrl->save($lander_url, true, array('path', 'name', 'description', 'content', 'browser_title', 'heading', 'source_name'))) {
        	    $this->Session->setFlash(__('Lander URL has been updated.'), 'flash_success');
        	    return $this->redirect(array('action' => 'index'));
        	}
        	$this->Session->setFlash(__('Unable to update the offer.'), 'flash_error');
    	}
    	if (!$this->request->data) {
        	$this->request->data = $lander_url;
    	}		
	}
	
}