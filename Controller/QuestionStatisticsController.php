<?php
App::uses('AppController', 'Controller');

class QuestionStatisticsController extends AppController {
	public $uses = array('QuestionStatistic', 'Question', 'Group'); 
	
	public function index() {
		$conditions = array();
		if (!empty($this->request->query['partner'])) {
			$conditions['QuestionStatistic.partner'] = $this->request->query['partner'];
			$this->set('partner', $this->request->query['partner']);
		}
		
		if (!empty($this->request->query['country'])) {
			$conditions['QuestionStatistic.country'] = $this->request->query['country'];
			$this->set('country', $this->request->query['country']);
		}

		$this->QuestionStatistic->bindModel(array(
			'belongsTo' => array(
				'Question' => array(
					'className' => 'Question',
					'foreignKey' => 'question_id',
				)
			)
		));

		$this->Question->bindModel(array(
			'hasOne' => array(
				'QuestionText' => array(
					'className' => 'QuestionText',
					'foreignKey' => 'question_id',
				)
			)
		));

		$paginate = array(
			'QuestionStatistic' => array(
				'conditions' => $conditions,
				'limit' => 200,
				'order' => 'QuestionStatistic.frequency DESC',
				'recursive' => 2
			)
		);

		$this->paginate = $paginate;
		$question_statistics = $this->paginate('QuestionStatistic');
		$distinct_partners = $this->Question->find('all', array(
			'fields' => array('DISTINCT(Question.partner)')
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
		
		$countries = array(
			'US' => 'US', 
			'GB' => 'GB', 
			'CA' => 'CA', 
		);
		$this->set(compact('question_statistics', 'partners', 'countries'));
	}
	
	public function export() {
		$this->QuestionStatistic->bindModel(array(
			'belongsTo' => array(
				'Question' => array(
					'className' => 'Question',
					'foreignKey' => 'question_id',
					'fields' => array('id', 'partner_question_id', 'question', 'partner')
				)
			)
		));

		$this->Question->bindModel(array(
			'hasOne' => array(
				'QuestionText' => array(
					'className' => 'QuestionText',
					'foreignKey' => 'question_id',
					'fields' => array('id', 'text'),
				)
			)
		));
		
		$question_statistics = $this->QuestionStatistic->find('all', array(
			'contain' => array(
				'Question' => array(
					'QuestionText'
				)
			),
			'order' => 'QuestionStatistic.frequency DESC',
		));
		if ($question_statistics) {
			$csv_rows = array(array(
				'Question ID',
				'Partner Question ID',
				'Partner',
				'Question (Internal)',
				'Question (Display)',
				'Country',
				'Frequency'
			));
			foreach($question_statistics as $question_statistic) {
				$csv_rows[] = array(
					$question_statistic['Question']['id'],
					$question_statistic['Question']['partner_question_id'],
					$question_statistic['Question']['partner'],
					$question_statistic['Question']['question'],
					isset($question_statistic['Question']['QuestionText']['text']) ? $question_statistic['Question']['QuestionText']['text'] : '-',
					$question_statistic['QuestionStatistic']['country'],
					$question_statistic['QuestionStatistic']['frequency']
				);
			}
			
			$filename = 'questions_statistics-' . date(DB_DATE) . '.csv';
			$csv_file = fopen('php://output', 'w');
			header('Content-type: application/csv');
			header('Content-Disposition: attachment; filename="' . $filename . '"');
			foreach ($csv_rows as $row) {
				fputcsv($csv_file, $row, ',', '"');
			}

			fclose($csv_file);
			$this->autoRender = false;
			$this->layout = false;
			$this->render(false);
			return;
			
		}
		else {
			$this->Session->setFlash('Statistics not found!', 'flash_error');
			$this->redirect(array('action' => 'index'));
		}
	}
	
	public function core_questions() {
		if ($this->request->is('put') || $this->request->is('post')) {
			if (empty($this->request->data['QuestionStatistic']['count']) || is_nan($this->request->data['QuestionStatistic']['count'])) {
				$this->Session->setFlash('You did not input a valid questions count', 'flash_error');
			}
			else {
				$question_count = $this->request->data['QuestionStatistic']['count'];
				$this->QuestionStatistic->bindModel(array('belongsTo' => array('Question')));
				$core_questions = $this->QuestionStatistic->find('all', array(
					'fields' => array('QuestionStatistic.id', 'QuestionStatistic.question_id'),
					'conditions' => array(
						'Question.ignore' => false,
						'Question.staging' => false,
						'Question.deprecated' => false,
					),
					'limit' => $question_count,
					'order' => 'QuestionStatistic.frequency DESC',
				));
				$core_questions = Set::extract('/QuestionStatistic/question_id', $core_questions);
				$locked_core_questions = $this->Question->find('list', array(
					'fields' => array('Question.id', 'Question.id'),
					'conditions' => array(
						'Question.core' => true,
						'Question.locked' => true
					),
				));
				if ($locked_core_questions) {
					$core_questions = array_merge($core_questions, $locked_core_questions);
				}
				
				$current_core_questions = $this->Question->find('list', array(
					'fields' => array('Question.id', 'Question.id'),
					'conditions' => array(
						'Question.core' => true
					)
				));
				$make_core = array_diff($core_questions, $current_core_questions);
				$revert_core = array_diff($current_core_questions, $core_questions);
				if ($make_core) {
					foreach ($make_core as $question_id) {
						$this->Question->create();
						$this->Question->save(array('Question' => array(
							'id' => $question_id,
							'core' => true,
							'public' => false // if its a public question mark it as false
						)), true, array('core', 'public'));
					}
				}

				if ($revert_core) {
					foreach ($revert_core as $question_id) {
						$this->Question->create();
						$this->Question->save(array('Question' => array(
							'id' => $question_id,
							'core' => false
						)), true, array('core'));
					}
				}
				
				// set the sort order
				$i = 1;
				foreach ($core_questions as $question_id) {
					$this->Question->create();
					$this->Question->save(array('Question' => array(
						'id' => $question_id,
						'order' => $i
					)), true, array('order'));
					$i++;
				}

				$this->Session->setFlash(count($make_core). ' questions set as core.'. count($revert_core). ' questions reverted from core.', 'flash_success');
				return $this->redirect(array('action' => 'index')); 
			}
		}
	}
}