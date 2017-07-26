<?php
App::uses('AppModel', 'Model');

class AcquisitionAlert extends AppModel {
	public $actsAs = array('Containable');

	public $validate = array(
		'amount' => array(
			'notempty' => array(
				'rule' => array('notempty'),
				'message' => 'Your custom message here',
				'allowEmpty' => false,
				'required' => true,
			),
		),
		'event' => array(
			'rule' => 'notEmpty',
			'required' => true,
			'message' => 'Please choose an event'
		),
		'trigger' => array(
			'rule' => 'notEmpty',
			'required' => true,
			'message' => 'Your trigger must be greater than 0'
		),
		'source_id' => array(
			'validateSourceId' => array(
				'rule' => array('validateSourceId'),
				'allowEmpty' => true,
				'message' => 'You already had an alert sent against that campaign/source mapping.'
			),
			'validateNonDuplicates' => array(
				'rule' => array('validateNonDuplicates'),
				'allowEmpty' => true,
				'message' => 'You cannot select both a campaign and a source mapping: please choose one.'
			)
		),
		'alert_threshold_minutes' => array(
			'rule' => array('range', 4, 1441),
			'allowEmpty' => true,
			'required' => true,
			'message' => 'You must set an alert threshold of at least 5 minutes and at most 1440 minutes'
		),
		'source_mapping_id' => array(
			'rule' => array('validateSourceMappingId'),
			'allowEmpty' => true,
			'message' => 'You already had an alert sent against that campaign/source mapping.'
		),
		'name' => array(
			'rule' => 'notEmpty',
			'required' => true,
		),
		'description' => array(
			'rule' => 'notEmpty',
			'required' => true,
			'message' => 'Please set a description'
		)
	);
	
	public function validateExistenceSource() {
		if (empty($this->data[$this->alias]['source_mapping_id']) && empty($this->data[$this->alias]['source_id'])) {
			return false;
		}
		return true;
	}
	
	public function validateNonDuplicates() {
		if (isset($this->data[$this->alias]['source_mapping_id']) && isset($this->data[$this->alias]['source_id'])) {
			if ($this->data[$this->alias]['source_mapping_id'] > 0 && $this->data[$this->alias]['source_id'] > 0) {
				return false;
			}
		}
		return true;
	}
	public function validateSourceMappingId() {
		if (isset($this->data[$this->alias]['source_mapping_id']) && $this->data[$this->alias]['source_mapping_id'] > 0) {
			if (isset($this->data[$this->alias]['id'])) {
				$count = $this->find('count', array(
					'conditions' => array(
						'AcquisitionAlert.source_mapping_id' => $this->data[$this->alias]['source_mapping_id'],
						'AcquisitionAlert.event' => $this->data[$this->alias]['event'],
						'AcquisitionAlert.deleted' => null,
						'AcquisitionAlert.id <>' => $this->data[$this->alias]['id']
					)
				));
			}
			else {
				$count = $this->find('count', array(
					'conditions' => array(
						'AcquisitionAlert.source_mapping_id' => $this->data[$this->alias]['source_mapping_id'],
						'AcquisitionAlert.event' => $this->data[$this->alias]['event'],
						'AcquisitionAlert.deleted' => null
					)
				));
			}
			if ($count > 0) {
				return false;
			}
		}
		return true;
	}
	public function validateSourceId() {
		if (isset($this->data[$this->alias]['source_id']) && $this->data[$this->alias]['source_id'] > 0) {
			if (isset($this->data[$this->alias]['id'])) {
				$count = $this->find('count', array(
					'conditions' => array(
						'AcquisitionAlert.source_id' => $this->data[$this->alias]['source_id'],
						'AcquisitionAlert.event' => $this->data[$this->alias]['event'],
						'AcquisitionAlert.deleted' => null,
						'AcquisitionAlert.id <>' => $this->data[$this->alias]['id']
					)
				));
			}
			else {
				$count = $this->find('count', array(
					'conditions' => array(
						'AcquisitionAlert.source_id' => $this->data[$this->alias]['source_id'],
						'AcquisitionAlert.event' => $this->data[$this->alias]['event'],
						'AcquisitionAlert.deleted' => null
					)
				));
			}
			if ($count > 0) {
				return false;
			}
		}
		return true;
	}
}
