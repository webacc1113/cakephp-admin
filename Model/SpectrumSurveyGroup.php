<?php
App::uses('AppModel', 'Model');

class SpectrumSurveyGroup extends AppModel {
	
	public function beforeSave($options = array()) {		
		if (!isset($this->data[$this->alias]['id']) && isset($this->data[$this->alias]['project_id']) && isset($this->data[$this->alias]['group_project_id'])) {
			$count = $this->find('count', array(
				'conditions' => array(
					'SpectrumSurveyGroup.spectrum_survey_id' => $this->data[$this->alias]['spectrum_survey_id'],
					'SpectrumSurveyGroup.group_spectrum_survey_id' =>$this->data[$this->alias]['group_spectrum_survey_id'],
					'SpectrumSurveyGroup.deleted is null'
				)
			));
			if ($count > 0) {
				return false;
			}
		}
		return true;
	}
}