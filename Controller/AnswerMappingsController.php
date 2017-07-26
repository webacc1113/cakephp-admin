<?php

App::uses('AppController', 'Controller');

class AnswerMappingsController extends AppController {

	public $uses = array('AnswerMapping', 'Answer', 'AnswerText', 'Question', 'QuestionText');

	public function beforeFilter() {
		parent::beforeFilter();
	}

	function index() {
		$this->AnswerMapping->bindModel(array('belongsTo' => array(
			'Answer' => array(
				'foreignKey' => 'from_answer_id',
			)
		)));

		$answer_mappings = $this->AnswerMapping->find('all', array(
			'fields' => array('Answer.question_id', 'AnswerMapping.from_answer_id'),
		));
		
		$question_ids = array_unique(Set::extract('/Answer/question_id', $answer_mappings));
		$conditions = array();
		$conditions['Question.id'] = $question_ids;
		if (!empty($this->request->query['partner'])) {
			$conditions['Question.partner'] = $this->request->query['partner'];
			$this->set('partner', $this->request->query['partner']);
		}
		
		$this->Question->bindModel(array(
			'hasMany' => array(
				'Answer',
				'QuestionText' => array(
					'order' => array('QuestionText.country' => 'desc')
				)
			)
		));
		$this->Answer->bindModel(array(
			'belongsTo' => array(
				'ToQuestion' => array(
					'className' => 'Question',
					'foreignKey' => 'question_id',
				)
			),
			'hasMany' => array(
				'AnswerMapping' => array(
					'foreignKey' => 'from_answer_id',
				)
			)
		));
		$this->AnswerMapping->bindModel(array('belongsTo' => array(
			'Answer' => array(
				'foreignKey' => 'to_answer_id',
			)
		)));
		
		$paginate = array(
			'Question' => array(
				'fields' => array('Question.id', 'Question.partner', 'Question.partner_question_id'),
				'contain' => array(
					'QuestionText' => array(
						'fields' => array('QuestionText.text')
					),
					'Answer' => array(
						'fields' => array('Answer.id'),
						'AnswerMapping' => array(
							'fields' => array('AnswerMapping.id'),
							'Answer' => array(
								'fields' => array('Answer.id'),
								'ToQuestion' => array(
									'fields' => array('ToQuestion.partner'),
								)
							)
						)
					)
				),
				'conditions' => $conditions,
				'limit' => 50,
				'order' => array(
					'Question.core' => 'desc',
					'Question.public' => 'desc',
					'Question.order' => 'asc',
					'Question.question' => 'asc'
				)
			)
		);
		$this->paginate = $paginate;
		$questions = $this->paginate('Question');
		foreach ($questions as $key => $question) {
			$mapped_partners = array();
			if ($question['Answer']) {
				foreach ($question['Answer'] as $answer) {
					if ($answer['AnswerMapping']) {
						foreach ($answer['AnswerMapping'] as $answer_mapping) {
							$mapped_partners[] = $answer_mapping['Answer']['ToQuestion']['partner'];
						}
					}
				}
			}
			
			if ($mapped_partners) {
				$mapped_partners = array_unique($mapped_partners);
			}
			
			unset($questions[$key]['Answer']);
			$questions[$key]['Partner'] = $mapped_partners;
		}
		
		$distinct_partners = $this->Question->find('all', array(
			'fields' => array('DISTINCT(Question.partner)'),
			'recursive' => -1
		));
		if ($distinct_partners) {
			foreach ($distinct_partners as $partner) {
				// todo: special case we need to fix eventually
				$partner = $partner['Question']['partner'];
				$group = $this->Group->find('first', array(
					'fields' => array('Group.name'),
					'conditions' => array(
						'Group.key' => $partner == 'lucid' ? 'fulcrum' : $partner
					),
					'recursive' => -1
				));
				$partners[$partner] = $group ? $group['Group']['name'] : $partner;
			}
		}
		
		$this->set(compact('partners', 'questions'));
	}

	function add() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$errors = $answer_mappings = array();
			foreach ($this->request->data['AnswerMapping']['from_partner'] as $key => $from_partner) {
				$from_partner_question_id = $this->request->data['AnswerMapping']['from_partner_question_id'][$key];
				$from_partner_answer_id = $this->request->data['AnswerMapping']['from_partner_answer_id'][$key];
				$to_partner = $this->request->data['AnswerMapping']['to_partner'][$key];
				$to_partner_question_id = $this->request->data['AnswerMapping']['to_partner_question_id'][$key];
				$to_partner_answer_id = $this->request->data['AnswerMapping']['to_partner_answer_id'][$key];
				
				if (empty($from_partner) || empty($from_partner_question_id) ||	empty($from_partner_answer_id) || 
					empty($to_partner) || empty($to_partner_question_id) || empty($to_partner_answer_id)) {
					continue;
				}
				
				$this->Question->bindModel(array('hasOne' => array('Answer' => array(
					'conditions' => array(
						'Answer.partner_answer_id' => $from_partner_answer_id
					)
				))));
				$from_question = $this->Question->find('first', array(
					'fields' => array('Question.id'),
					'contain' => array(
						'Answer' => array(
							'fields' => array('Answer.id')
						)
					),
					'conditions' => array(
						'Question.partner' => $from_partner,
						'Question.partner_question_id' => $from_partner_question_id
					),
					'recursive' => -1
				));
				if (!$from_question || empty($from_question['Answer']['id'])) {
					$errors[] = 'Invalid, from mapping: '.$from_partner .' #'. $from_partner_question_id. ' ('.$from_partner_answer_id.')';
					continue;
				}

				$this->Question->bindModel(array('hasOne' => array('Answer' => array(
					'conditions' => array(
						'Answer.partner_answer_id' => $to_partner_answer_id
					)
				))));
				$to_question = $this->Question->find('first', array(
					'fields' => array('Question.id'),
					'contain' => array(
						'Answer' => array(
							'fields' => array('Answer.id')
						)
					),
					'conditions' => array(
						'Question.partner' => $to_partner,
						'Question.partner_question_id' => $to_partner_question_id
					),
				));
				if (!$to_question || empty($to_question['Answer']['id'])) {
					$errors[] = 'Invalid, to mapping: '.$to_partner .' #'. $to_partner_question_id. ' ('.$to_partner_answer_id.')';
					continue;
				}
				
				$count = $this->AnswerMapping->find('count', array(
					'conditions' => array(
						'AnswerMapping.from_answer_id' => $from_question['Answer']['id'],
						'AnswerMapping.to_answer_id' => $to_question['Answer']['id']
					)
				));
				if ($count > 0) {
					$errors[] = 'Mapping already exist: '. $from_partner .' #'. $from_partner_question_id. ' ('.$from_partner_answer_id.') - '. $to_partner .' #'. $to_partner_question_id. ' ('.$to_partner_answer_id.')';
					continue;
				}
				
				$answer_mappings[] = array(
					'from_answer_id' => $from_question['Answer']['id'],
					'to_answer_id' => $to_question['Answer']['id']
				);
			}

			if ($errors) {
				$this->Session->setFlash(implode('<br />', $errors), 'flash_error');
			}
			elseif ($answer_mappings) {
				foreach ($answer_mappings as $answer_mapping) {
					$this->AnswerMapping->create();
					$this->AnswerMapping->save(array('AnswerMapping' => $answer_mapping));
				}
				
				$this->Session->setFlash(__('Mapping(s) saved successfully.'), 'flash_success');
				$this->redirect(array('controller' => 'answer_mappings', 'action' => 'index'));
			}
			else {
				$this->Session->setFlash(__('Mapping not saved.'), 'flash_error');
			}
		}

		if (isset($this->request->query['question_id']) && !empty($this->request->query['question_id'])) {
			$selected_question = $this->Question->find('first', array(
				'conditions' => array(
					'Question.id' => $this->request->query['question_id']
				),
				'recursive' => -1
 			));
			if ($selected_question) {
				$this->set(compact('selected_question'));
			}
		}
		
		$distinct_partners = $this->Question->find('all', array(
			'fields' => array('DISTINCT(Question.partner)')
		));
		$groups = $this->Group->find('list', array(
			'fields' => array('Group.key', 'Group.name'),
			'recursive' => -1
		));
		$partners = array();
		if ($distinct_partners) {
			foreach ($distinct_partners as $partner) {
				$partners[$partner['Question']['partner']] = isset($groups[$partner['Question']['partner']]) ? $groups[$partner['Question']['partner']] : $partner['Question']['partner'];
			}
		}
		
		$this->set(compact('partners'));
	}
	
	function edit($question_id) {
		if (!$question_id) {
			throw new NotFoundException(__('Question id is required!'));
		}
		
		$this->Question->bindModel(array(
			'hasMany' => array(
				'Answer',
				'QuestionText' => array(
					'order' => array('QuestionText.country' => 'desc')
				)
			)
		));
		$this->Answer->bindModel(array(
			'belongsTo' => array(
				'ToQuestion' => array(
					'className' => 'Question',
					'foreignKey' => 'question_id',
				)
			),
			'hasMany' => array(
				'AnswerMapping' => array(
					'foreignKey' => 'from_answer_id',
				),
				'AnswerText' => array(
					'order' => array('AnswerText.country' => 'desc')
				)
			)
		));
		$this->AnswerMapping->bindModel(array('belongsTo' => array(
			'Answer' => array(
				'foreignKey' => 'to_answer_id',
			)
		)));
		$this->Answer->ToQuestion->bindModel(array(
			'hasMany' => array(
				'QuestionText' => array(
					'order' => array('QuestionText.country' => 'desc')
				)
			)
		));
		$question = $this->Question->find('first', array(
			'fields' => array('Question.id', 'Question.partner', 'Question.partner_question_id'),
			'contain' => array(
				'QuestionText' => array(
					'fields' => array('QuestionText.text')
				),
				'Answer' => array(
					'fields' => array('Answer.id', 'Answer.partner_answer_id'),
					'AnswerText' => array(
						'fields' => array('AnswerText.text')
					),
					'AnswerMapping' => array(
						'fields' => array('AnswerMapping.id', 'AnswerMapping.active'),
						'Answer' => array(
							'fields' => array('Answer.id', 'Answer.partner_answer_id'),
							'AnswerText' => array(
								'fields' => array('AnswerText.text')
							),
							'ToQuestion' => array(
								'fields' => array('ToQuestion.partner', 'ToQuestion.partner_question_id'),
								'QuestionText' => array(
									'fields' => array('QuestionText.text')
								),
							)
						)
					)
				)
			),
			'conditions' => array(
				'Question.id' => $question_id
			)
		));
		$this->set(compact('question'));
	}
	
	public function active() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$id = $this->request->data['id'];
			$mapping = $this->AnswerMapping->findById($id);
			$active = ($mapping['AnswerMapping']['active']) ? 0 : 1;
			$this->AnswerMapping->save(array('AnswerMapping' => array(
				'id' => $id,
				'active' => $active,
			)), true, array('active'));

			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => $active
				)),
				'type' => 'json',
				'status' => '201'
			));
		}
	}

}
