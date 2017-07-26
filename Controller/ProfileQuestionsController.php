<?php
App::uses('AppController', 'Controller');

class ProfileQuestionsController extends AppController {
	public $uses = array('ProfileQuestion', 'Profile', 'ProfileAnswer');
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array('RequestHandler');
	
	public $question_types = array(
		'select' => 'Dropdown',
		'radio' => 'Multiple Choice (single answer)',
		'checkbox' => 'Multiple Choice (multiple answers)',
	);
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function ajax_search() {
		$conditions = array();
		if (isset($this->request->query['q']) && strlen($this->request->query['q']) > 2) {
			$terms = explode(' ', $this->request->query['q']);
			foreach ($terms as $key => $val) {
				if (strlen($val) < 3) {
					unset($terms[$key]);
				}
			}
			if (count($terms) > 1) {
				foreach ($terms as $term) {
					$conditions[] = array('ProfileQuestion.name like' => '%'.$term.'%');
				}
			}
			else {
				$conditions['ProfileQuestion.name like'] = '%'.$this->request->query['q'].'%';
			}
		}
		elseif (isset($this->request->query['id'])) {
			$conditions = array('ProfileQuestion.profile_id' => $this->request->query['id']);
		}
		$this->ProfileQuestion->bindModel(array('hasMany' => array(
			'ProfileAnswer' => array(
				'fields' => array('id', 'name'),
				'order' => 'ProfileAnswer.order ASC'
			)
		)));
		$profile_questions = $this->ProfileQuestion->find('all', array(
			'fields' => array('ProfileQuestion.id', 'ProfileQuestion.name', 'Profile.name'),
			'conditions' => $conditions,
			'order' => 'ProfileQuestion.order ASC'
		));
		
		$this->set(compact('profile_questions'));
		$this->RequestHandler->respondAs('application/json'); 
		$this->response->statusCode('200');
		$this->layout = '';
	}
	
	public function ajax_add_questions_to_query() {
		
		if ($this->request->is('post') || $this->request->is('put')) {
			if (!empty($this->data['profile_answer'])) {
				$profile_answers = $this->ProfileAnswer->find('list', array(
					'fields' => array('id', 'profile_question_id'),
					'conditions' => array(
						'ProfileAnswer.id' => array_keys($this->data['profile_answer'])
					)
				));
				if ($profile_answers) {					
					$this->ProfileQuestion->bindModel(array('hasMany' => array(
						'ProfileAnswer' => array(
							'fields' => array('id', 'name'),
							'order' => 'ProfileAnswer.order ASC'
						)
					)));
					$profile_questions = $this->ProfileQuestion->find('all', array(
						'fields' => array('ProfileQuestion.id', 'ProfileQuestion.name', 'Profile.name'),
						'conditions' => array(
							'ProfileQuestion.id' => array_unique($profile_answers)
						),
						'order' => 'ProfileQuestion.name ASC'
					));
				}
			}
		}
		$this->set(compact('profile_answers', 'profile_questions'));
		$this->RequestHandler->respondAs('application/json'); 
		$this->response->statusCode('200');
		$this->layout = '';
	}
	
	public function ajax_add() {		
		$profileQuestionSource = $this->ProfileQuestion->getDataSource();
		$profileQuestionSource->begin();
		$this->ProfileQuestion->create();
		if ($this->ProfileQuestion->save($this->request->data)) {
			$question_id = $this->ProfileQuestion->getInsertId();
			$profileQuestionSource->commit();
			if (isset($this->request->data['ProfileQuestion']['answer']) && !empty($this->request->data['ProfileQuestion']['answer'])) {
				foreach ($this->request->data['ProfileQuestion']['answer'] as $answer) {
					$answer = trim($answer);
					if (empty($answer)) {
						continue;
					}
					$this->ProfileAnswer->create();
					$this->ProfileAnswer->save(array('ProfileAnswer' => array(
						'profile_id' => $this->request->data['ProfileQuestion']['profile_id'],
						'profile_question_id' => $question_id,
						'name' => $answer
					)));
				}
			}
			return new CakeResponse(array(
				'body' => json_encode(array(
					'id' => $question_id
				)),
				'type' => 'json',
				'status' => '201'
			));	
		}
		else {
			$profileQuestionSource->commit();
			return new CakeResponse(array(
				'body' => json_encode(array()), 
				'type' => 'json',
				'status' => '400'
			));	
		}
	}
	
	public function ajax_edit($question_id = null) {
		$this->layout = 'ajax';
		if ($this->request->is('put') || $this->request->is('post')) {
			$profile_question = $this->ProfileQuestion->findById($this->data['ProfileQuestion']['id']);
			$this->ProfileQuestion->create();
			if ($this->ProfileQuestion->save($this->request->data, true, array('type', 'name'))) {
				
			}
			if (isset($this->data['ProfileAnswer']['existing']) && !empty($this->data['ProfileAnswer']['existing'])) {
				foreach ($this->data['ProfileAnswer']['existing'] as $id => $name) {
					$this->ProfileAnswer->create();
					$this->ProfileAnswer->save(array('ProfileAnswer' => array(
						'id' => $id,
						'name' => $name
					), true, array('name')));
				}
			}
			if (isset($this->data['answer']) && !empty($this->data['answer'])) {
				foreach ($this->data['answer'] as $answer) {
					$answer = trim($answer);
					if (empty($answer)) {
						continue;
					}
					$this->ProfileAnswer->create();
					$this->ProfileAnswer->save(array('ProfileAnswer' => array(
						'profile_id' => $profile_question['Profile']['id'],
						'profile_question_id' => $profile_question['ProfileQuestion']['id'],
						'name' => $answer
					)));
				}
			}
			$this->Session->setFlash('Your changes have been saved.', 'flash_success');
			$this->redirect(array('controller' => 'profile_questions', 'action' => 'index', $profile_question['Profile']['id'])); 
		}
		elseif (isset($question_id)) {
			$profile_question = $this->ProfileQuestion->findById($question_id);
			$this->data = $profile_question;
		}
		$this->set('question_types', $this->question_types);
	}
	
	public function ajax_order($profile_id) {
		if (isset($this->request->data['order'])) {
			$order = explode(',', $this->request->data['order']);
			foreach ($order as $order => $id) {
				$this->ProfileQuestion->create();
				$this->ProfileQuestion->save(array('ProfileQuestion' => array(
					'id' => $id,
					'order' => $order
				)), true, array('order'));
			}
		}
		db($this->request->data); 
		return new CakeResponse(array(
			'body' => json_encode(array(
				'id' => $question_id
			)),
			'type' => 'json',
			'status' => '201'
		));	
	}
	
	public function ajax_remove_answer($answer_id) {
		$this->ProfileAnswer->delete($answer_id);
		return new CakeResponse(array(
			'body' => json_encode(array(
			)),
			'type' => 'json',
			'status' => '201'
		));	
	}
	
	public function ajax_reorder_answers($question_id) {
		$this->layout = 'ajax';
		$question = $this->ProfileQuestion->findById($question_id);
		if ($this->request->is('put') || $this->request->is('post')) {
			if (isset($this->request->data['order'])) {
				$order = explode(',', $this->request->data['order']);
				$i = 0;
				foreach ($order as $answer_id) {
					if (empty($answer_id)) {
						continue;
					}
					$this->ProfileAnswer->create();
					$this->ProfileAnswer->save(array('ProfileAnswer' => array(
						'id' => $answer_id,
						'order' => $i
					)), true, array('order'));
					$i++;
				}
			}
			return new CakeResponse(array(
				'body' => json_encode(array(
				)),
				'type' => 'json',
				'status' => '201'
			));	
		}
		$this->set(compact('question'));
	}
	
	public function ajax_delete($question_id) {
		if ($this->request->is('put') || $this->request->is('post')) {
			$question = $this->ProfileQuestion->findById($question_id);
			$this->ProfileQuestion->delete($question['ProfileQuestion']['id']);
			$this->ProfileQuestion->updateCounter($question['Profile']['id']);
			$profile_answers = $this->ProfileAnswer->find('all', array(
				'recursive' => -1,
				'conditions' => array(
					'ProfileAnswer.profile_question_id' => $question['ProfileQuestion']['id']
				),
				'fields' => array('ProfileAnswer.id')
			));
			if ($profile_answers) {
				foreach ($profile_answers as $answer) {
					$this->ProfileAnswer->delete($answer['ProfileAnswer']['id']); 
				}
			}
		}
		return new CakeResponse(array(
			'body' => json_encode(array(
				'id' => $question_id
			)),
			'type' => 'json',
			'status' => '201'
		));	
	}
	
	public function index($profile_id) {		
		$conditions = array();
		$paginate = array(
			'ProfileQuestion' => array(
				'conditions' => array(
					'ProfileQuestion.profile_id' => $profile_id
				),
				'order' => 'ProfileQuestion.order asc',
				'limit' => 500
			)
		);
		$this->paginate = $paginate;
		$this->set('questions', $this->paginate());
		$profile = $this->Profile->findById($profile_id);
		$this->set(compact('profile'));
		$this->set('question_types', $this->question_types);
	}
		
	public function delete($id) {
		$profile = $this->Profile->findById($id);
    	if (!$profile) {
        	throw new NotFoundException(__('Invalid profile'));
    	}
    	if ($this->request->is('post') || $this->request->is('put')) {
			$this->Profile->delete($profile['Profile']['id']);
			$this->Session->setFlash('You have successfully deleted this user profile survey.', 'flash_success');
			$this->redirect(array('action' => 'index'));
		} else {
			$this->request->data = $profile;
		}
	}
}