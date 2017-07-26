<?php
App::uses('AppController', 'Controller');

class QuestionsController extends AppController {
	public $uses = array('Question', 'Answer', 'AnswerText', 'QuestionText', 'Group'); 
	
	public function index() {
		$this->Question->bindModel(array(
			'hasMany' => array(
				'QuestionText',
				'Answer'
			)
		));
		$this->Question->Answer->bindModel(array('hasMany' => array('AnswerText')));
		$conditions = array(
		);
		if (!empty($this->request->query['partner'])) {
			$conditions['Question.partner'] = $this->request->query['partner'];
			$this->set('partner', $this->request->query['partner']);
		}
				
		$paginate = array(
			'Question' => array(
				'conditions' => $conditions,
				'contain' => array(
					'QuestionText'
				),
				'limit' => 50,
				'order' => array(
					'Question.core' => 'desc',
					'Question.public' => 'desc',
					'Question.order' => 'asc',
					'Question.question' => 'asc'
				),
			)
		);

		$this->paginate = $paginate;
		$questions = $this->paginate('Question');
		
		$distinct_partners = $this->Question->find('all', array(
			'fields' => array('DISTINCT(Question.partner)')
		));
		if ($distinct_partners){
			foreach ($distinct_partners as $partner) {
				// todo: special case we need to fix eventually
				if ($partner['Question']['partner'] == 'lucid') {
					$group = $this->Group->find('first', array(
						'fields' => array('Group.name'),
						'conditions' => array(
							'Group.key' => 'fulcrum'
						),
						'recursive' => -1
					)); 
				}
				else {
					$group = $this->Group->find('first', array(
						'fields' => array('Group.name'),
						'conditions' => array(
							'Group.key' => $partner['Question']['partner']
						),
						'recursive' => -1
					)); 
				}
				$partners[$partner['Question']['partner']] = $group ? $group['Group']['name']: $partner['Question']['partner']; 
			}
		} 
		$this->set(compact('questions', 'partners'));
	}
	
	public function export() {
		$supported_countries = array_keys(unserialize(SUPPORTED_COUNTRIES)); 
		if ($this->request->is('post')) {
			$country = $supported_countries[$this->request->data['Question']['country']]; 
			$this->Question->bindModel(array(
				'hasOne' => array(
					'QuestionText' => array(
						'conditions' => array(
							'QuestionText.country' => $country
						)
					)
				)
			));
			$questions = $this->Question->find('all', array(
				'fields' => array('Question.id', 'Question.partner_question_id', 'QuestionText.id', 'QuestionText.text', 'Question.partner'),
				'conditions' => array(
				)
			));
				
  			$filename = 'questions-'.$country . '.csv';
	  		$csv_file = fopen('php://output', 'w');

			header('Content-type: application/csv');
			header('Content-Disposition: attachment; filename="' . $filename . '"');
			
			fputcsv($csv_file, array(
				'Partner',
				'Question ID',
				'Question Text',
				'Answer ID', 
				'Answer Text' 
			), ',', '"');
			
			foreach ($questions as $key => $question) {
				if (empty($question['QuestionText']['id'])) {
					unset($questions[$key]);
					continue;
				}
				$this->Answer->bindModel(array('hasOne' => array(
					'AnswerText' => array(
						'conditions' => array(
							'AnswerText.country' => $country
						)
					)
				))); 
				$answers = $this->Answer->find('all', array(
					'fields' => array('Answer.partner_answer_id', 'AnswerText.text'),
					'conditions' => array(
						'Answer.question_id' => $question['Question']['id']
					)
				));
				if (!$answers) {
					continue;
				}
				foreach ($answers as $answer) {
					fputcsv($csv_file, array(
						$question['Question']['partner'],
						$question['Question']['partner_question_id'],
						$question['QuestionText']['text'],
						$answer['Answer']['partner_answer_id'],
						$answer['AnswerText']['text']
					), ',', '"');
				}
				
			}
			fclose($csv_file);
			$this->autoRender = false;
			$this->layout = false;
			$this->render(false);
		}
		$this->set(compact('supported_countries'));
	}
	
	public function edit($question_id) {
		$this->Question->bindModel(array(
			'hasMany' => array(
				'QuestionText',
				'Answer'
			)
		));
		$question = $this->Question->find('first', array(
			'contain' => array(
				'QuestionText',
			),
			'conditions' => array(
				'Question.id' => $question_id,
			),
			'order' => 'Question.order ASC'
		));
		if ($this->request->is('post') || $this->request->is('put')) {
			if (isset($this->request->data['QuestionText']) && !empty($this->request->data['QuestionText'])) {
				foreach ($this->request->data['QuestionText']['text'] as $question_text_id => $question_text_value) {
					if (empty($question_text_value)) {
						continue;
					}
					if (empty($this->request->data['QuestionText']['cp_text'][$question_text_id])) {
						$this->request->data['QuestionText']['cp_text'][$question_text_id] = null;
					}
					$this->QuestionText->create();
					$this->QuestionText->save(array('QuestionText' => array(
						'id' => $question_text_id,
						'cp_text' => $this->request->data['QuestionText']['cp_text'][$question_text_id],
						'text' => $question_text_value
					)), true, array('text', 'cp_text'));
				}
								
				$this->Session->setFlash('Changes saved', 'flash_success');
				return $this->redirect(array('action' => 'edit', $question_id));
			}
		}
		$this->set(compact('question'));
	}
	
	public function answers($question_id) {
		$this->Answer->bindModel(array('hasMany' => array(
			'AnswerText' => array()
		))); 
		$this->Question->bindModel(array(
			'hasMany' => array(
				'QuestionText',
				'Answer' => array(
					'order' => array(
						'Answer.order ASC',
						'ABS(Answer.partner_answer_id) ASC'
					)
				)
			)
		));
		$question = $this->Question->find('first', array(
			'contain' => array(
				'QuestionText',
				'Answer' => array(
					'AnswerText'
				)
			),
			'conditions' => array(
				'Question.id' => $question_id,
			),
			'order' => 'Question.order ASC'
		));
		if ($this->request->is('post') || $this->request->is('put')) {
			if (isset($this->request->data['AnswerText']) && !empty($this->request->data['AnswerText'])) {
				foreach ($this->request->data['AnswerText'] as $answer_text_id => $answer_text_value) {
					if (empty($answer_text_value)) {
						continue;
					}
					$this->AnswerText->create();
					$this->AnswerText->save(array('AnswerText' => array(
						'id' => $answer_text_id,
						'text' => $answer_text_value
					)), true, array('text'));
				}
			}
			$this->Session->setFlash('Changes saved', 'flash_success');
			return $this->redirect(array('action' => 'answers', $question_id));
		}
		$this->set(compact('question'));	
	}
	
	public function ajax_toggle_answer_status($answer_id, $type) {
		if ($this->request->is('post') || $this->request->is('put')) {
			$answer = $this->Answer->find('first', array(
				'fields' => array('Answer.id', 'Answer.ignore', 'Answer.question_id', 'Answer.hide_from_pms', 'Answer.skip_project'),
				'conditions' => array(
					'Answer.id' => $answer_id
				)
			));
			if ($answer) {
				$ignore = $answer['Answer']['ignore'];
				$hide = $answer['Answer']['hide_from_pms'];
				$skip = $answer['Answer']['skip_project'];
				if ($type == 'ignore') {
					$ignore = !$answer['Answer']['ignore'];
					$this->Answer->create();
					$this->Answer->save(array('Answer' => array(
						'id' => $answer['Answer']['id'],
						'ignore' => $ignore
					)), true, array('ignore'));
				}
				elseif ($type == 'skip') {
					$skip = !$answer['Answer']['skip_project'];
					$this->Answer->create();
					$this->Answer->save(array('Answer' => array(
						'id' => $answer['Answer']['id'],
						'skip_project' => $skip
					)), true, array('skip_project'));
				}
				elseif ($type == 'hide') {
					$hide = !$answer['Answer']['hide_from_pms'];
					$this->Answer->create();
					$this->Answer->save(array('Answer' => array(
						'id' => $answer['Answer']['id'],
						'hide_from_pms' => $hide
					)), true, array('hide_from_pms'));
				}
			}
		}
		return new CakeResponse(array(
			'body' => json_encode(array(
				'ignore' => $ignore,
				'hide' => $hide,
				'skip' => $skip
			)),
			'type' => 'json',
			'status' => '201'
		));
	}
	
	public function view($question_id) {
		$this->Answer->bindModel(array('hasMany' => array(
			'AnswerText' => array()
		))); 
		$this->Question->bindModel(array(
			'hasMany' => array(
				'QuestionText',
				'Answer'
			)
		));
		$question = $this->Question->find('first', array(
			'contain' => array(
				'QuestionText',
				'Answer' => array(
					'order' => array(
						'Answer.order ASC',
						'ABS(Answer.partner_answer_id) ASC'
					),
					'AnswerText'
				)
			),
			'conditions' => array(
				'Question.id' => $question_id
			),
			'order' => 'Question.order ASC'
		));
		$this->set(compact('question'));		
	}
	
	public function ignore() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$id = $this->request->data['id'];
			$question = $this->Question->find('first', array(
				'fields' => array('Question.ignore'),
				'conditions' => array(
					'Question.id' => $id,
				),
			));
			$ignore = ($question['Question']['ignore']) ? 0 : 1;
			$this->Question->save(array('Question' => array(
				'id' => $id,
				'ignore' => $ignore,
			)), true, array('ignore'));
			
			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => $ignore
				)),
				'type' => 'json',
				'status' => '201'
			));
		}
	}
	
	public function ajax_deprecate() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$id = $this->request->data['id'];
			$question = $this->Question->find('first', array(
				'fields' => array('Question.deprecated'),
				'conditions' => array(
					'Question.id' => $id,
				),
			));
			$deprecated = ($question['Question']['deprecated']) ? 0 : 1;
			$this->Question->save(array('Question' => array(
				'id' => $id,
				'deprecated' => $deprecated,
			)), true, array('deprecated'));
			
			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => $deprecated
				)),
				'type' => 'json',
				'status' => '201'
			));
		}
	}
	
	public function ajax_staging() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$id = $this->request->data['id'];
			$question = $this->Question->find('first', array(
				'fields' => array('Question.staging'),
				'conditions' => array(
					'Question.id' => $id,
				),
			));
			$staging = ($question['Question']['staging']) ? 0 : 1;
			$this->Question->save(array('Question' => array(
				'id' => $id,
				'staging' => $staging,
			)), true, array('staging'));
			
			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => $staging
				)),
				'type' => 'json',
				'status' => '201'
			));
		}
	}
	
	public function ajax_core() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$id = $this->request->data['id'];
			$question = $this->Question->find('first', array(
				'fields' => array('Question.core'),
				'conditions' => array(
					'Question.id' => $id,
				),
			));
			$core = ($question['Question']['core']) ? 0 : 1;
			$this->Question->save(array('Question' => array(
				'id' => $id,
				'core' => $core,
			)), true, array('core'));
			
			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => $core
				)),
				'type' => 'json',
				'status' => '201'
			));
		}
	}
	
	public function ajax_lock() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$id = $this->request->data['id'];
			$question = $this->Question->find('first', array(
				'fields' => array('Question.locked'),
				'conditions' => array(
					'Question.id' => $id,
				),
			));
			$locked = ($question['Question']['locked']) ? 0 : 1;
			$this->Question->save(array('Question' => array(
				'id' => $id,
				'locked' => $locked,
			)), true, array('locked'));
			
			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => $locked
				)),
				'type' => 'json',
				'status' => '201'
			));
		}
	}

	public function set_high_usage() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$id = $this->request->data['id'];
			$question = $this->Question->find('first', array(
				'conditions' => array(
					'Question.id' => $id,
				),
			));
			$max = $this->Question->find('first', array(
				'conditions' => array(
					'Question.high_usage is not null'
				),
				'fields' => array('max(high_usage) as max_order'),
			));
			$max_order = $max[0]['max_order'];
			$high_usage = ($question['Question']['high_usage']) ? NULL : ++ $max_order;
			$this->Question->save(array('Question' => array(
				'id' => $id,
				'high_usage' => $high_usage,
			)), true, array('high_usage'));

			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => $high_usage
				)),
				'type' => 'json',
				'status' => '201'
			));
		}
	}

	public function add() {
		$partners = $this->Question->find('all', array(
			'fields' => array('distinct(partner)'),
			'recursive' => -1
		));
		foreach ($partners as $key => $partner) {
			$partners[$partner['Question']['partner']] = ucfirst($partner['Question']['partner']);
			unset($partners[$key]);
		}
		
		$this->set(compact('partners'));
		if ($this->request->is('post')) {
			
			// Validate question first
			$this->request->data['Question']['partner_question_id'] = trim($this->request->data['Question']['partner_question_id']);
			if (empty($this->request->data['Question']['partner_question_id'])) {
				$this->Question->validationErrors['partner_question_id'][0] = 'Partner question id missing';
			}
			
			$question = $this->Question->find('first', array(
				'fields' => array('Question.id'),
				'conditions' => array(
					'Question.partner' => $this->request->data['Question']['partner'],
					'Question.partner_question_id' => $this->request->data['Question']['partner_question_id']
				)
			));
			if ($question) {
				$this->Question->validationErrors['partner_question_id'][0] = 'Partner question id already exist';
			}
			
			$this->request->data['Question']['question'] = trim($this->request->data['Question']['question']);
			if (empty($this->request->data['Question']['question'])) {
				$this->Question->validationErrors['question'][0] = 'Question text missing';
			}
			
			$error = '';
			if (empty($this->Question->validationErrors)) {
				$answers = trim($this->request->data['Question']['answers']);
				
				// validate answers if any
				if (!empty($answers)) {
					if (strpos($answers, "\r\n") !== false) {
						$answers = str_replace("\r\n", '|', $answers);
					} 
					elseif (strpos($answers, "\r") !== false) {
						$answers = str_replace("\r", '|', $answers);
					} 
					elseif (strpos($answers, "\n") !== false) {
						$answers = str_replace("\n", '|', $answers);
					}

					$answer_arr = explode('|', $answers);
					$answers = array();
					foreach ($answer_arr as $answer) {
						$answer = trim($answer);
						if (empty($answer)) {
							continue;
						}

						if (strpos($answer, "-") !== false) {
							$answer = explode('-', $answer);
							$answer[0] = trim($answer[0]);
							$answer[1] = trim($answer[1]);

							if (empty($answer[0])) {
								$error = 'An answer "partner_answer_id" is missing.';
								break;
							}
							elseif (empty($answer[1])) {
								$error = 'An answer text is missing.';
								break;
							}
							else {
								$answers[] = array(
									'partner_answer_id' => $answer[0],
									'text' => $answer[1]
								); 
							}
						}
						else {
							$error = 'one of the answers is not correctly formated.';
						}
					}
				}
				
				if (!empty($error)) {
					$this->Question->validationErrors['answers'][0] = $error;
				}
				else {
					$question['Question'] = array(
						'partner' => $this->request->data['Question']['partner'],
						'partner_question_id' => $this->request->data['Question']['partner_question_id'],
						'question' => $this->request->data['Question']['question']
					);
					if (!empty($this->request->data['Question']['question_type'])) {
						$question['Question']['question_type'] = $this->request->data['Question']['question_type'];
					}

					if (!empty($this->request->data['Question']['behavior'])) {
						$question['Question']['behavior'] = $this->request->data['Question']['behavior'];
					}
					
					$this->Question->create();
					$this->Question->save($question);
					$question_id = $this->Question->getInsertId();

					// Save Question Text
					$this->QuestionText->create();
					$this->QuestionText->save(array('QuestionText' => array(
						'question_id' => $question_id,
						'country' => $this->request->data['Question']['country'],
						'text' => $this->request->data['Question']['question'],
					)));

					// Save Answers if any
					if (!empty($answers)) {
						foreach ($answers as $answer) {
							$this->Answer->create();
							$this->Answer->save(array('Answer' => array(
								'question_id' => $question_id,
								'partner_answer_id' => $answer['partner_answer_id'],
							)));
							$answer_id = $this->Answer->getInsertId();

							// Save Answer Text 
							$this->AnswerText->create();
							$this->AnswerText->save(array('AnswerText' => array(
								'answer_id' => $answer_id,
								'country' => $this->request->data['Question']['country'],
								'text' => $answer['text']
							)));
						}
					}
					
					$this->Session->setFlash(__('Question saved successfully!'), 'flash_success');
					$this->redirect(array('action' => 'add'));
				}
			}
        }
	}

	public function set_high_usage_order() {
		$this->Question->bindModel(array(
			'hasMany' => array(
				'QuestionText',
			)
		));
		$questions = $this->Question->find('all', array(
			'conditions' => array(
				'Question.question_type' => array(QUESTION_TYPE_SINGLE, QUESTION_TYPE_MULTIPLE),
				'Question.high_usage is not null'
			),
			'contain' => array(
				'QuestionText',
			),
			'order' => array(
				'Question.high_usage ASC'
			)
		));
		$this->set(compact('questions'));
	}

	public function ajax_save_high_usage_order() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$ids = $this->request->data['sort_order'];
			foreach ($ids as $index => $id) {
				$high_usage = $index + 1;
				$this->Question->save(array('Question' => array(
					'id' => $id,
					'high_usage' => $high_usage,
				)), true, array('high_usage'));
			}
			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => "success"
				)),
				'type' => 'json',
				'status' => '201'
			));
		}
	}
	
	public function ajax_order_answers() {
		if (isset($this->request->data['order'])) {
			$order = explode(',', $this->request->data['order']);
			foreach ($order as $order => $id) {
				$this->Answer->create();
				$this->Answer->save(array('Answer' => array(
					'id' => $id,
					'order' => $order
				)), true, array('order'));
			}
		}
		//db($this->request->data); 
		return new CakeResponse(array(
			'body' => json_encode(array(
				'id' => $question_id
			)),
			'type' => 'json',
			'status' => '201'
		));	
	}
	
	public function export_to_qe($id = null) {
		if (!$id) {
			throw new NotFoundException(__('Invalid question id'));
		}
		
		$required_settings = array(
			'hostname.qe',
			'qe.mintvine.username',
			'qe.mintvine.password',
		);
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => $required_settings,
				'Setting.deleted' => false
			),
			'recursive' => -1
		));
		if (count($settings) != count($required_settings)) {
			$this->Session->setFlash('Missing required settings', 'flash_success');
			return $this->redirect($this->referer());
		}

		$HttpQe = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false
		));
		$HttpQe->configAuth('Basic', $settings['qe.mintvine.username'], $settings['qe.mintvine.password']);
		$this->Question->bindModel(array('hasOne' => array(
			'QuestionText' => array(
				'order' => array(
					'QuestionText.country' => 'desc' // get the US questionText prefereably 
				)
			)
		)));
		$this->Answer->bindModel(array('hasOne' => array(
			'AnswerText' => array(
				'order' => array(
					'AnswerText.country' => 'desc' // get the US answerText prefereably 
				)
			)
		)));
		$question = $this->Question->find('first', array(
			'conditions' => array(
				'Question.id' => $id,
			),
			'contain' => array(
				'QuestionText'
			)
		));
		if (!$question) {
			throw new NotFoundException(__('Invalid question'));
		}
		
		$qe_post_data = array(
			'partner' => $question['Question']['partner'],
			'partner_question_id' => $question['Question']['partner_question_id'],
			'question_type' => $question['Question']['question_type'],
			'behavior' => $question['Question']['behavior'],
			'question_text' => empty($question['QuestionText']['cp_text']) ? $question['QuestionText']['text'] : $question['QuestionText']['cp_text']
		);
		$messages = array();
		$save_answers = false;
		if (!defined('IS_DEV_INSTANCE') || IS_DEV_INSTANCE === false) {
			$info = array(
				"ID: " . $question['Question']['partner_question_id'],
				"Name: " . $question['Question']['question']
			);
			try {
				$response_qe = $HttpQe->post($settings['hostname.qe'] . "/questions", json_encode($qe_post_data), array(
					'header' => array('Content-Type' => 'application/json')
				));
				if ($response_qe->code == 201) {
					$save_answers = true;
					$messages[] = "A new question saved to QE";
					$messages = array_merge($messages, $info);
				}
				elseif ($response_qe->code != 409) {
					$messages[] = "<span class='text-error'>Unexpected response code for " . $question['Question']['partner'] . ": " . $response_qe->code. "</span>";
					$messages = array_merge($messages, $info);
					$messages[] = "Code: " . $response_qe->code;
				}
			}
			catch (Exception $ex) {
				$messages[] = "<span class='text-error'>Failed to save following question to QE for " . $question['Question']['partner']. "</span>";
				$messages = array_merge($messages, $info);
				$messages[] = "Exception: " . $ex->getMessage();
			}
		}

		//Process answers
		if ($save_answers) {
			$answers = $this->Answer->find('all', array(
				'conditions' => array(
					'Answer.question_id' => $question['Question']['id'],
				),
				'contain' => array(
					'AnswerText'
				)
			));
			foreach ($answers as $answer) {
				if (empty($answer['AnswerText'])) {
					continue;
				}

				$qe_post_data = array(
					'partner' => $question['Question']['partner'],
					'partner_question_id' => $question['Question']['partner_question_id'],
					'partner_answer_id' => $answer['Answer']['partner_answer_id'],
					'answer_text' => $answer['AnswerText']['text']
				);

				if (!defined('IS_DEV_INSTANCE') || IS_DEV_INSTANCE === false) {
					$info = array(
						"Answer ID: " . $answer['Answer']['partner_answer_id'],
						"Answer Text: " . $answer['AnswerText']['text']
					);
					try {
						$response_qe = $HttpQe->post($settings['hostname.qe'] . "/answers", json_encode($qe_post_data), array(
							'header' => array('Content-Type' => 'application/json')
						));
						if ($response_qe->code == 201) {
							$messages[] = "New answered saved to QE";
							$messages = array_merge($messages, $info);
						}
						elseif ($response_qe->code != 409) {
							$messages[] = "<span class='text-error'>Unexpected response code for " . $question['Question']['partner'] . ": " . $response_qe->code . "</span>";
							$messages = array_merge($messages, $info);
						}
					}
					catch (Exception $ex) {
						$messages[] = "<span class='text-error'>Failed to save following answer to QE" . "</span>";
						$messages = array_merge($messages, $info);
						$messages[] = "Exception: " . $ex->getMessage();
					}
				}
			}

			$this->Question->create();
			$this->Question->save(array('Question' => array(
				'id' => $question['Question']['id'],
				'last_exported' => date(DB_DATETIME),
			)), true, array('last_exported'));
		}
		
		if ($messages) {
			$this->Session->setFlash(implode('<br />', $messages), 'flash_success');
		}
		else {
			$this->Session->setFlash('This question is already exported to QE', 'flash_success');
		}
		
		return $this->redirect($this->referer());
	}
}