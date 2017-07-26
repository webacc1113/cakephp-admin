<?php
App::uses('AppController', 'Controller');

class GroupsController extends AppController {
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array();
	
	public function beforeFilter() {
		parent::beforeFilter();		
	}
	
	function index() {
		$paginate = array(
			'Group' => array(
				'limit' => 200,
				'order' => 'Group.name ASC',
			)
		);
		$this->paginate = $paginate;		
		$this->set('groups', $this->paginate());
	}
	
	function add() {
		if ($this->request->is('post')) {
			$this->Group->create();
			if (isset($this->request->data['Group']['epc_floor_cents']) && !empty($this->request->data['Group']['epc_floor_cents'])) {
				$this->request->data['Group']['epc_floor_cents'] = $this->request->data['Group']['epc_floor_cents'] * 100; 
			}
            if ($this->Group->save($this->request->data)) {
                $this->Session->setFlash(__('Group has been saved.'), 'flash_success');
                return $this->redirect(array('action' => 'index'));
            }
			
            $this->Session->setFlash(__('Unable to add the group.'), 'flash_error');
        }
	}
	
	function edit($id = null) {
		if (empty($id)) {
			throw new NotFoundException();
		}
		$group = $this->Group->findById($id);
		if (!$group) {
			throw new NotFoundException();
		}
		
		if ($this->request->is(array('put', 'post'))) {
			if (isset($this->request->data['Group']['epc_floor_cents']) && !empty($this->request->data['Group']['epc_floor_cents'])) {
				$this->request->data['Group']['epc_floor_cents'] = $this->request->data['Group']['epc_floor_cents'] * 100; 
			}
			$save = $this->Group->save($this->request->data, true, array('name', 'key', 'prefix', 'router_priority', 'calculate_margin', 'max_loi_minutes', 'max_clicks_with_no_completes', 'epc_floor_cents', 'performance_checks', 'code_name', 'filter_panelists', 'use_mask', 'check_links')); 
			if ($save) {
				$this->Session->setFlash(__('Group has been updated.'), 'flash_success');
        	    return $this->redirect(array('action' => 'index'));
			}
			$this->Session->setFlash(__('Unable to update the group.'), 'flash_error');
		}
		else {
			if (!empty($group['Group']['epc_floor_cents'])) {
				$group['Group']['epc_floor_cents'] = number_format(round($group['Group']['epc_floor_cents'] / 100, 2), 2); 
			}
			$this->request->data = $group;
		}
	}
}