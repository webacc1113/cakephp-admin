<?php
App::uses('AppController', 'Controller');

class PagesController extends AppController {
	public $uses = array('Page');
	public $helpers = array('Text', 'Html', 'Time');
	
	public function beforeFilter() {
		$this->Auth->allow('home', 'nogroups');
		parent::beforeFilter();
	}
	
	public function index() {
		if ($this->request->is('post') && isset($this->data['Page']) && isset($this->data['delete'])) {
			$deleted = 0;
			foreach ($this->data['Page'] as $id => $value) {
				if ($value == 0 || $id == 'null') {
					continue;
				}

				$this->Page->delete($id);
				$deleted++;
			}
			
			if ($deleted > 0) {
				$this->Session->setFlash('You have deleted ' . $deleted . ' Pages' . '.', 'flash_success');
				$this->redirect(array('action' => 'index'));
			}
		}
		
		$limit = 50;
		$conditions = array();
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}

		$paginate = array(
			'Page' => array(
				'limit' => $limit,
				'order' => 'Page.title ASC',
			)
		);
		if (!empty($conditions)) {
			$paginate['Page']['conditions'] = $conditions;
		}
		$this->paginate = $paginate;
		$this->set('pages', $this->paginate());
	}
	
	public function add() {
		if ($this->request->is('post')) {
            $this->Page->create();
            $this->request->data['Page']['slug'] = Inflector::slug(strtolower($this->request->data['Page']['title']), '-');
            if ($this->Page->save($this->request->data)) {
                $this->Session->setFlash(__('Page has been saved.'), 'flash_success');
                return $this->redirect(array('action' => 'index'));
            }
            $this->Session->setFlash(__('Unable to add the Page.'), 'flash_error');
        }
	}
	
	public function edit($id) {
		$page = $this->Page->findById($id);
    	if (!$page) {
        	throw new NotFoundException(__('Invalid Page'));
    	}
    	if ($this->request->is('post') || $this->request->is('put')) {
			if ($this->Page->save(
				array(
            		'Page' => array(
            			'id' => $id,
            			'title' => $this->request->data['Page']['title'],
            			'body' => $this->request->data['Page']['body'],
            			'slug' => Inflector::slug(strtolower($this->request->data['Page']['title']), '-')
            			),
            		)
			)) {
        	    $this->Session->setFlash(__('Page has been updated.'), 'flash_success');
        	    return $this->redirect(array('action' => 'index'));
        	}
        	$this->Session->setFlash(__('Unable to update the Page.'), 'flash_error');
    	}
    	if (!$this->request->data) {
        	$this->request->data = $page;
    	}
	}
	
	public function home() {
		
	}
	
	public function nogroups() {
		
	}
}
