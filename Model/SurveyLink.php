<?php
App::uses('AppModel', 'Model');

class SurveyLink extends AppModel {
	public $actsAs = array('Containable');
	
	public function beforeSave($options = array()) {
		if (isset($this->data[$this->alias]['link'])) {
			$this->data[$this->alias]['link'] = trim($this->data[$this->alias]['link']);
		}
		
		if (!isset($this->data[$this->alias]['id'])) {
			if (!empty($this->data[$this->alias]['user_id']) && !empty($this->data[$this->alias]['link'])) {
				$count = $this->find('count', array(
					'conditions' => array(
						'SurveyLink.survey_id' => $this->data[$this->alias]['survey_id'],
						'SurveyLink.link' => $this->data[$this->alias]['link'],
						'SurveyLink.user_id' => $this->data[$this->alias]['user_id']
					)
				));
				if ($count > 0) {
					return false;
				}
			}
		}
		return true;
	}
}
