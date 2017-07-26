<?php
App::uses('AppModel', 'Model');

class FulcrumSurveyGroup extends AppModel {
	
	public function beforeSave($options = array()) {		
		if (!isset($this->data[$this->alias]['id'])) {
			if (isset($this->data[$this->alias]['project_id']) && isset($this->data[$this->alias]['survey_group_id'])) {
				$count = $this->find('count', array(
					'conditions' => array(
						'FulcrumSurveyGroup.project_id' => $this->data[$this->alias]['project_id'],
						'FulcrumSurveyGroup.survey_group_id' =>$this->data[$this->alias]['survey_group_id'],
						'FulcrumSurveyGroup.deleted is null'
					),
					'recursive' => -1
				));
				if ($count > 0) {
					return false;
				}
			}
		}
		return true;
	}
}