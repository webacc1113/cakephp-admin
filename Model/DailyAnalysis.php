<?php
App::uses('AppModel', 'Model');

class DailyAnalysis extends AppModel {
	public $actsAs = array('Containable');
	
	public function beforeSave($options = array()) {
		if (!isset($this->data[$this->alias]['id'])) {
			if (!isset($this->data[$this->alias]['daily_analysis_property_id'])) {
				return false;
			}
			$count = $this->find('count', array(
				'conditions' => array(
					'DailyAnalysis.daily_analysis_property_id' => $this->data[$this->alias]['daily_analysis_property_id'],
					'DailyAnalysis.type' => $this->data[$this->alias]['type'],
					'DailyAnalysis.date' => $this->data[$this->alias]['date'],
					'DailyAnalysis.timeframe' => (isset($this->data[$this->alias]['timeframe'])) ? $this->data[$this->alias]['timeframe'] : ''
				)
			));
			if ($count > 0) {
				return false;
			}
		}
		return true;
	}
	
	public $belongsTo = array(
		'DailyAnalysisProperty' => array(
			'className' => 'DailyAnalysisProperty',
			'foreignKey' => 'daily_analysis_property_id',
			'fields' => array('name')
		),
    );
}
