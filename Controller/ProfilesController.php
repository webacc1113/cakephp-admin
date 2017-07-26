<?php
App::uses('AppController', 'Controller');

class ProfilesController extends AppController {
	public $uses = array('Profile', 'ProfileQuestion', 'ProfileAnswer');
	public $helpers = array('Text', 'Html', 'Time');
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		$limit = 100;
		
		$conditions = array();
		$paginate = array(
			'Profile' => array(
				'limit' => $limit,
				'order' => 'Profile.name ASC'
			)
		);
		$this->paginate = $paginate;
		$this->set('profiles', $this->paginate());
	}
	
	public function ajax_status($profile_id) {
		$profile = $this->Profile->find('first', array(
			'conditions' => array(
				'Profile.id' => $profile_id
			)
		));
		if ($profile['Profile']['status'] == DB_ACTIVE) {
			$this->Profile->create();
			$this->Profile->save(array('Profile' => array(
				'id' => $profile_id,
				'status' => DB_DEACTIVE
			)), true, array('status'));
		}
		else {
			$this->Profile->create();
			$this->Profile->save(array('Profile' => array(
				'id' => $profile_id,
				'status' => DB_ACTIVE
			)), true, array('status'));
		}
		return new CakeResponse(array(
			'body' => json_encode(array(
				'class' => $profile['Profile']['status'] == DB_ACTIVE ? 'btn-default': 'btn-success',
				'text' => $profile['Profile']['status'] == DB_ACTIVE ? 'Inactive': 'Active'
			)),
			'type' => 'json',
			'status' => '201'
		));	
		
	}
	
	public function add() {
		if ($this->request->is('post')) {
            $this->Profile->create();
            if ($this->Profile->save($this->request->data)) {
                $this->Session->setFlash(__('Profile has been created.'), 'flash_success');
                return $this->redirect(array('action' => 'index'));
            }
        }
	}
	
	public function edit($id) {
		$profile = $this->Profile->findById($id);
    	if (!$profile) {
        	throw new NotFoundException(__('Invalid profile'));
    	}
    	if ($this->request->is('post') || $this->request->is('put')) {
        	if ($this->Profile->save($this->request->data)) {
        	    $this->Session->setFlash(__('Profile has been updated.'), 'flash_success');
        	    return $this->redirect(array('action' => 'index'));
        	}
    	}
    	if (!$this->request->data) {
        	$this->request->data = $profile;
		}
	}
	
	public function delete($id) {
		$profile = $this->Profile->findById($id);
    	if (!$profile) {
        	throw new NotFoundException(__('Invalid profile'));
    	}
    	if ($this->request->is('post') || $this->request->is('put')) {
			$this->Profile->delete($profile['Profile']['id']);
			$profile_questions = $this->ProfileQuestion->find('all', array(
				'recursive' => -1,
				'conditions' => array(
					'ProfileQuestion.profile_id' => $profile['Profile']['id']
				),
				'fields' => array('ProfileQuestion.id')
			));
			if ($profile_questions) {
				foreach ($profile_questions as $question) {
					$this->ProfileQuestion->delete($question['ProfileQuestion']['id']); 
				}
			}
			$profile_answers = $this->ProfileAnswer->find('all', array(
				'recursive' => -1,
				'conditions' => array(
					'ProfileAnswer.profile_id' => $profile['Profile']['id']
				),
				'fields' => array('ProfileAnswer.id')
			));
			if ($profile_answers) {
				foreach ($profile_answers as $answer) {
					$this->ProfileAnswer->delete($answer['ProfileAnswer']['id']); 
				}
			}
			$this->Session->setFlash('You have successfully deleted this user profile survey.', 'flash_success');
			$this->redirect(array('action' => 'index'));
		}
		else {
			$this->request->data = $profile;
		}
	}
}