<?php
App::uses('AppModel', 'Model');

class SpectrumProject extends AppModel {
	public $actsAs = array('Containable');
	
	public function beforeSave($options = array()) {		
		if (!isset($this->data[$this->alias]['id'])) {
			if (empty($this->data[$this->alias]['project_id'])) {
				$count = $this->find('count', array(
					'conditions' => array(
						'SpectrumProject.project_id' => '0',
						'SpectrumProject.spectrum_survey_id' => $this->data[$this->alias]['spectrum_survey_id']
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
