<?php
App::uses('AppController', 'Controller');

class FilteredPanelistsController extends AppController {
	
	var $uses = array('FilteredPanelist', 'Group', 'SurveyVisit');

	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	function index() {
		if ($this->request->is('post') && isset($this->data['FilteredPanelist']) && isset($this->data['delete'])) {
			$deleted = 0;
			foreach ($this->data['FilteredPanelist'] as $id => $value) {
				if ($value == 0 || $id == 'null') {
					continue;
				}

				$this->FilteredPanelist->delete($id);
				$deleted++;
			}
			
			if ($deleted > 0) {
				
				// sync Group.filter_panelists
				$this->FilteredPanelist->group_filter_panelists();
				
				$this->Session->setFlash($deleted . ' record(s) deleted.', 'flash_success');
				$this->redirect(array('action' => 'index'));
			}
		}
		$paginate = array(
			'FilteredPanelist' => array(
				'limit' => 200
			)
		);
		$this->paginate = $paginate;		
		$this->set('filtered_panelists', $this->paginate());
	}
	
	public function add() {
		if ($this->request->is(array('put', 'post'))) {
			if (empty($this->request->data['FilteredPanelist']['partner'])) {
				$this->Session->setFlash(__('Please select a partner.'), 'flash_error');
        	    return $this->redirect(array('action' => 'add'));
			}
			
			$user_ids = array();
			if (!empty($this->request->data['FilteredPanelist']['user_ids']) && is_string($this->request->data['FilteredPanelist']['user_ids'])) {
				$user_ids = explode("\n", $this->request->data['FilteredPanelist']['user_ids']);
				array_walk($user_ids, create_function('&$val', '$val = trim($val);'));
			}
			
			if (!empty($user_ids)) {
				$errors = array();
				$saved = 0;
				foreach ($user_ids as $user_id) {
					if (strpos($user_id, 'm') !== false) {
						$survey_visit = $this->SurveyVisit->find('first', array(
							'fields' => array('SurveyVisit.partner_user_id'),
							'conditions' => array(
								'SurveyVisit.hash' => $user_id
							)
						));
						
						if (!$survey_visit) {
							$errors[$user_id] = 'Missing hash in SurveyVisit';
							continue;
						}
						
						$user_id = explode('-', $survey_visit['SurveyVisit']['partner_user_id']);
						$user_id = $user_id[1];
					}
					
					$this->FilteredPanelist->create();
					$save = $this->FilteredPanelist->save(array('FilteredPanelist' => array(
						'partner' => $this->request->data['FilteredPanelist']['partner'],
						'user_id' => $user_id
					)));
					if ($save) {
						$saved++;
					}
					elseif (isset($this->FilteredPanelist->validationErrors['user_id'][0])) {
						$errors[$user_id] = $this->FilteredPanelist->validationErrors['user_id'][0];
					}
				}
				
				$this->set(compact('saved', 'errors'));
				
				// sync Group.filter_panelists
				$this->FilteredPanelist->group_filter_panelists();
			}
			else {
				$this->Session->setFlash(__('User ids not found.'), 'flash_error');
			}
		}
		
		$groups = $this->Group->find('list', array(
			'fields' => array('key', 'name'),
			'order' => 'Group.name ASC'
		));
		$this->set(compact('groups'));
	}
}