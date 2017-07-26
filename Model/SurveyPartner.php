<?php
App::uses('AppModel', 'Model');

class SurveyPartner extends AppModel {
	public $actsAs = array('Containable');
	
	public $validate = array(
		'partner_id' => array(
			'custom' => array(
				'rule' => array('validatePartner'),
				'message' => 'You have already added this partner.',
			)
		),
		'complete_url' => array(
			'notempty' => array(
				'rule' => array('notempty'),
				'message' => 'Your success link must be set to a URL.',
				'required' => true,
				'allowEmpty' => false
			),
		),
		'nq_url' => array(
			'notempty' => array(
				'rule' => array('notempty'),
				'message' => 'Your term link must be set to a URL.',
				'required' => true,
				'allowEmpty' => false
			)
		),
		'oq_url' => array(
			'notempty' => array(
				'rule' => array('notempty'),
				'message' => 'Your overquota link must be set to a URL.',
				'required' => true,
				'allowEmpty' => false
			)
		),
	);
	
	var $belongsTo = array(
		'Partner' => array(
			'className' => 'Partner',
			'foreignKey' => 'partner_id',
			'fields' => array('partner_name')
		)
	);
	
	public function validatePartner() {
		if (!isset($this->data[$this->alias]['id'])) {
			$count = $this->find('count', array(
				'conditions' => array(
					'SurveyPartner.survey_id' => $this->data[$this->alias]['survey_id'],
					'SurveyPartner.partner_id' => $this->data[$this->alias]['partner_id'],
				)
			));
			if ($count > 0) {
				return false;
			}
		}
		return true;
	}
	
	public function beforeSave($options = array()) {
		if (isset($this->data[$this->alias]['pause_url']) && empty($this->data[$this->alias]['pause_url'])) {
			$this->data[$this->alias]['pause_url'] = null;
		}
		if (isset($this->data[$this->alias]['fail_url']) && empty($this->data[$this->alias]['fail_url'])) {
			$this->data[$this->alias]['fail_url'] = null;
		}
		return true;
	}
	
	public function afterSave($created, $options = array()) {
		if ($created) {
			if (isset($this->data[$this->alias]['survey_id']) && $this->data[$this->alias]['survey_id']) {
				$this->updatePartnerCount($this->data[$this->alias]['survey_id']);
			}
		}
	}
	
	public function beforeDelete($cascade = true) {
		$this->info = $this->findById($this->id);
	}

	public function afterDelete() {
		$this->updatePartnerCount($this->info['SurveyPartner']['survey_id']);
	}

	public function updatePartnerCount($survey_id) {
		App::import('Model', 'Project');
		$Project = new Project();
		if (!$Project->exists($survey_id)) {
			throw new NotFoundException(__('Invalid Project ID!'));
		}
		
		$count = $this->find('count', array(
			'recursive' => -1,
			'conditions' => array('survey_id' => $survey_id)
		));
		
		$Project->save(array('Project' => array(
			'id' => $survey_id,
			'partner_count' => $count,
		)), true, array('partner_count'));
	}
}
