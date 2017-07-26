<?php
App::uses('AppController', 'Controller');

class ClicksController extends AppController {
	
	public $uses = array('ClickTemplate', 'ClickTemplateDistribution', 'Question', 'QuestionText', 'Answer', 'AnswerText', 'GeoState', 'LucidZip');
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array('RequestHandler');
	
	public function beforeFilter() {
		parent::beforeFilter();
	}

	public function index() {
		$click_templates = $this->ClickTemplate->find('all', array(
			'order' => 'ClickTemplate.created DESC'
		));
		$this->set(compact('click_templates'));
	}
	
	public function add_distributions($click_template_id) {
		if ($this->request->is('Post')) {
			$request_data = $this->request->data;
			$clickTemplateDistributionSource = $this->ClickTemplateDistribution->getDataSource();
			$clickTemplateDistributionSource->begin();

			// get sumed percentage
			$conditions = array(
				'ClickTemplateDistribution.click_template_id' => $click_template_id,
				'ClickTemplateDistribution.key' => $request_data['key'],
				'ClickTemplateDistribution.other' => false,
			);
			if ($request_data['key'] == 'age_gender') {
				$conditions['ClickTemplateDistribution.gender'] = $request_data['gender'] == 'male' ? 1 : 2;
			}
			$sumed_percentage = $this->ClickTemplateDistribution->find('first', array(
				'fields' => array('SUM(ClickTemplateDistribution.percentage) AS total_percentage'),
				'conditions' => $conditions
			));
			$total_percentage = $sumed_percentage[0]['total_percentage'];

			if ($request_data['key'] == 'age') {
				$age_distributions = $this->ClickTemplateDistribution->find('all', array(
					'conditions' => array(
						'ClickTemplateDistribution.click_template_id' => $click_template_id,
						'ClickTemplateDistribution.key' => 'age',
						'ClickTemplateDistribution.other' => false
					)
				));
				$overlap_flag = false;
				foreach ($age_distributions as $age_distribution) {
					$age_from = $age_distribution['ClickTemplateDistribution']['age_from'];
					$age_to = $age_distribution['ClickTemplateDistribution']['age_to'];
					for ($i = $request_data['age_from']; $i <= $request_data['age_to']; $i ++) {
						if ($age_from <= $i && $i <= $age_to) {
							$overlap_flag = true;
							break 2;
						}
					}
				}
				if ($overlap_flag) {
					$this->Session->setFlash('Age ranges can not overlap.', 'flash_error');
					return $this->redirect(array('controller' => 'clicks', 'action' => 'add_distributions', $click_template_id));
				}

				if (($total_percentage + $request_data['percentage']) > 100) {
					$this->Session->setFlash('The sum of the percentages can not exceed 100%.', 'flash_error');
					return $this->redirect(array('controller' => 'clicks', 'action' => 'add_distributions', $click_template_id));
				}

				$request_data['click_template_id'] = $click_template_id;
				$this->ClickTemplateDistribution->create();
				$this->ClickTemplateDistribution->save(array('ClickTemplateDistribution' => $request_data));
				$other_percentage = 100 - ($total_percentage + $request_data['percentage']);
				$other_distribution = array(
					'click_template_id' => $click_template_id,
					'key' => 'age',
					'other' => true,
					'percentage' => $other_percentage
				);
				$age_other_distribution = $this->ClickTemplateDistribution->find('first', array(
					'conditions' => array(
						'ClickTemplateDistribution.click_template_id' => $click_template_id,
						'ClickTemplateDistribution.key' => 'age',
						'ClickTemplateDistribution.other' => true
					)
				));
				if ($age_other_distribution) {
					$other_distribution['id'] = $age_other_distribution['ClickTemplateDistribution']['id'];
				}
				$this->ClickTemplateDistribution->create();
				$this->ClickTemplateDistribution->save(array('ClickTemplateDistribution' => $other_distribution));
			}
			elseif ($request_data['key'] == 'gender') {
				$total_percentage = $request_data['male'] + $request_data['female'];
				if ($total_percentage != 100) {
					$this->Session->setFlash('The sum of the gender percentages should be 100%.', 'flash_error');
					return $this->redirect(array('controller' => 'clicks', 'action' => 'add_distributions', $click_template_id));
				}
				$gender_distributions = $this->ClickTemplateDistribution->find('all', array(
					'conditions' => array(
						'ClickTemplateDistribution.click_template_id' => $click_template_id,
						'ClickTemplateDistribution.key' => 'gender',
					)
				));
				if ($gender_distributions) {
					foreach ($gender_distributions as $gender_distribution) {
						$this->ClickTemplateDistribution->delete($gender_distribution['ClickTemplateDistribution']['id']);
					}
				}
				foreach ($request_data as $key => $value) {
					if ($key != 'key') {
						$gender_distribution = array(
							'click_template_id' => $click_template_id,
							'key' => 'gender',
							'gender' => ($key == 'male' ? 1 : 2),
							'percentage' => $value
						);
						$this->ClickTemplateDistribution->create();
						$this->ClickTemplateDistribution->save(array('ClickTemplateDistribution' => $gender_distribution));
					}
				}
			}
			elseif ($request_data['key'] == 'age_gender') {
				$request_data['gender'] = $request_data['gender'] == 'male' ? 1 : 2;
				$age_gender_distributions = $this->ClickTemplateDistribution->find('all', array(
					'conditions' => array(
						'ClickTemplateDistribution.click_template_id' => $click_template_id,
						'ClickTemplateDistribution.key' => 'age_gender',
						'ClickTemplateDistribution.gender' => $request_data['gender'],
						'ClickTemplateDistribution.other' => false
					)
				));

				$overlap_flag = false;
				foreach ($age_gender_distributions as $age_gender_distribution) {
					$age_from = $age_gender_distribution['ClickTemplateDistribution']['age_from'];
					$age_to = $age_gender_distribution['ClickTemplateDistribution']['age_to'];
					for ($i = $request_data['age_from']; $i <= $request_data['age_to']; $i ++) {
						if ($age_from <= $i && $i <= $age_to) {
							$overlap_flag = true;
							break 2;
						}
					}
				}
				if ($overlap_flag) {
					$this->Session->setFlash('Age ranges can not overlap.', 'flash_error');
					return $this->redirect(array('controller' => 'clicks', 'action' => 'add_distributions', $click_template_id));
				}

				if (($total_percentage + $request_data['percentage']) > 100) {
					$this->Session->setFlash('The sum of the percentages can not exceed 100%.', 'flash_error');
					return $this->redirect(array('controller' => 'clicks', 'action' => 'add_distributions', $click_template_id));
				}

				$request_data['click_template_id'] = $click_template_id;
				$this->ClickTemplateDistribution->create();
				$this->ClickTemplateDistribution->save(array('ClickTemplateDistribution' => $request_data));
				$other_percentage = 100 - ($total_percentage + $request_data['percentage']);
				$other_distribution = array(
					'click_template_id' => $click_template_id,
					'key' => 'age_gender',
					'other' => true,
					'gender' => $request_data['gender'],
					'percentage' => $other_percentage
				);
				$age_gender_other_distribution = $this->ClickTemplateDistribution->find('first', array(
					'conditions' => array(
						'ClickTemplateDistribution.click_template_id' => $click_template_id,
						'ClickTemplateDistribution.key' => 'age_gender',
						'ClickTemplateDistribution.gender' => $request_data['gender'],
						'ClickTemplateDistribution.other' => true
					)
				));
				if ($age_gender_other_distribution) {
					$other_distribution['id'] = $age_gender_other_distribution['ClickTemplateDistribution']['id'];
				}
				$this->ClickTemplateDistribution->create();
				$this->ClickTemplateDistribution->save(array('ClickTemplateDistribution' => $other_distribution));
			}
			else {
				$total_percentage = 0;
				foreach ($request_data[$request_data['key']] as $answer_id => $value) {
					if (!empty($value)) {
						$total_percentage += $value;
					}
				}
				if ($total_percentage > 100) {
					$this->Session->setFlash('The sum of the percentages can not exceed 100%.', 'flash_error');
					return $this->redirect(array('controller' => 'clicks', 'action' => 'add_distributions', $click_template_id));
				}

				$current_distributions = $this->ClickTemplateDistribution->find('all', array(
					'conditions' => array(
						'ClickTemplateDistribution.click_template_id' => $click_template_id,
						'ClickTemplateDistribution.key' => $request_data['key']
					)
				));
				foreach ($current_distributions as $current_distribution) {
					$this->ClickTemplateDistribution->delete($current_distribution['ClickTemplateDistribution']['id']);
				}

				foreach ($request_data[$request_data['key']] as $answer_id => $value) {
					if (!empty($value)) {
						$this->ClickTemplateDistribution->create();
						$this->ClickTemplateDistribution->save(array('ClickTemplateDistribution' => array(
							'click_template_id' => $click_template_id,
							'key' => $request_data['key'],
							'answer_id' => $answer_id,
							'percentage' => $value
						)));
					}
				}

				$other_percentage = 100 - $total_percentage;
				$other_distribution = array(
					'click_template_id' => $click_template_id,
					'key' => $request_data['key'],
					'other' => true,
					'percentage' => $other_percentage
				);
				$this->ClickTemplateDistribution->create();
				$this->ClickTemplateDistribution->save(array('ClickTemplateDistribution' => $other_distribution));
			}
			$clickTemplateDistributionSource->commit();
			$this->Session->setFlash('Distribution has been added.', 'flash_success');
		}
		$questions = array();
		$geo = array();
		$questions['hhi'] = $this->getQuestion('STANDARD_HHI_US_v2');
		$questions['ethnicity'] = $this->getQuestion('ETHNICITY');
		$questions['hispanic'] = $this->getQuestion('HISPANIC');

		$states = $this->GeoState->find('all', array(
			'fields' => array('GeoState.state_abbr', 'GeoState.state', 'GeoState.region', 'GeoState.sub_region'),
			'conditions' => array(
				'GeoState.id >' => '0'
			),
			'order' => 'GeoState.state ASC'
		));
		$state_regions = $states_list = array();
		foreach ($states as $state) {
			$lucid_zip = $this->LucidZip->find('first', array(
				'fields' => array('LucidZip.lucid_precode'),
				'conditions' => array(
					'LucidZip.state_abbr' => $state['GeoState']['state_abbr']
				)
			));
			$states_list[$lucid_zip['LucidZip']['lucid_precode']] = $state['GeoState']['state_abbr'] . ' - ' . $state['GeoState']['state'];
			$state_regions[] = $state['GeoState']['region'];
			// used for css classes
			$sub_region_list[$state['GeoState']['state_abbr']] = str_replace(' ', '_', $state['GeoState']['sub_region']);
			// get the sub regions for each region
			if (!empty($state['GeoState']['sub_region'])) {
				$sub_regions[$state['GeoState']['region']][] = $state['GeoState']['sub_region'];
			}
		}
		foreach ($sub_regions as $key => $sub_region) {
			$sub_regions[$key] = array_unique($sub_region);
		}
		$geo['region'] = array_keys(array_flip($state_regions));
		$geo['state'] = $states_list;
		$this->ClickTemplateDistribution->bindModel(array(
			'belongsTo' => array(
				'ClickTemplate' => array(
					'fields' => array('ClickTemplate.name', 'ClickTemplate.name')
				)
			)
		));
		$click_template_distributions = $this->ClickTemplateDistribution->find('all', array(
			'conditions' => array(
				'ClickTemplateDistribution.click_template_id' => $click_template_id
			),
			'order' => 'ClickTemplateDistribution.created DESC'
		));
		$this->set(compact('questions', 'geo', 'click_template_id', 'click_template_distributions'));
	}

	private function getQuestion($key, $country = 'US') {
		$this->Question->bindModel(array(
			'hasOne' => array(
				'QuestionText' => array(
					'conditions' => array(
						'QuestionText.country' => $country
					)
				)
			)
		));
		$question = $this->Question->find('first', array(
			'fields' => array(
				'Question.id', 'Question.question', 'QuestionText.text', 'QuestionText.cp_text', 'Question.partner_question_id'
			),
			'conditions' => array(
				'Question.question' => $key,
				'Question.partner' => 'lucid'
			),
		));
		$this->Answer->bindModel(array(
			'hasOne' => array(
				'AnswerText' => array(
					'conditions' => array(
						'AnswerText.country' => $country
					)
				)
			)
		));
		$answers = $this->Answer->find('all', array(
			'fields' => array(
				'Answer.partner_answer_id', 'AnswerText.text'
			),
			'conditions' => array(
				'Answer.ignore' => false,
				'Answer.hide_from_pms' => false,
				'Answer.question_id' => $question['Question']['id']
			)
		));
		if ($answers) {
			$answer_return = array();
			foreach ($answers as $answer) {
				$answer_return[$answer['Answer']['partner_answer_id']] = $answer['AnswerText']['text'];
			}
		}
		return $question + array('Answers' => $answer_return);
	}

	public function ajax_delete_distribution() {
		$id = $this->request->data['id'];
		$distribution = $this->ClickTemplateDistribution->find('first', array(
			'conditions' => array(
				'ClickTemplateDistribution.id' => $id
			)
		));
		$key = $distribution['ClickTemplateDistribution']['key'];
		$conditions = array(
			'ClickTemplateDistribution.click_template_id' => $distribution['ClickTemplateDistribution']['click_template_id'],
			'ClickTemplateDistribution.key' => $distribution['ClickTemplateDistribution']['key'],
			'ClickTemplateDistribution.other' => true,
		);
		if ($key == 'age_gender') {
			$conditions['ClickTemplateDistribution.gender'] = $distribution['ClickTemplateDistribution']['gender'];
		}
		$other_distribution = $this->ClickTemplateDistribution->find('first', array(
			'conditions' => $conditions
		));
		$new_other_percentage = $other_distribution['ClickTemplateDistribution']['percentage'] + $distribution['ClickTemplateDistribution']['percentage'];
		if ($new_other_percentage == 100) {
			$this->ClickTemplateDistribution->delete($id);
			$this->ClickTemplateDistribution->delete($other_distribution['ClickTemplateDistribution']['id']);
			$return_data = array(
				$id => 0,
				$other_distribution['ClickTemplateDistribution']['id'] => 0
			);
		}
		else {
			$this->ClickTemplateDistribution->create();
			$this->ClickTemplateDistribution->save(array('ClickTemplateDistribution' => array(
				'id' => $other_distribution['ClickTemplateDistribution']['id'],
				'click_template_id' => $distribution['ClickTemplateDistribution']['click_template_id'],
				'other' => true,
				'percentage' => $new_other_percentage
			)));

			$this->ClickTemplateDistribution->delete($id);
			$return_data = array(
				$id => 0,
				$other_distribution['ClickTemplateDistribution']['id'] => $new_other_percentage
			);
		}
		return new CakeResponse(array(
			'body' => json_encode($return_data),
			'type' => 'json',
			'status' => '201'
		));
	}
}
