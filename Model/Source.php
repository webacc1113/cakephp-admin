<?php
App::uses('AppModel', 'Model');

class Source extends AppModel {
	public $actsAs = array('Containable');
	
	public $validate = array(
		'name' => array(
			'checkUnique' => array(
				'allowEmpty' => false,
				'required' => true,
				'rule' => 'checkDisplayNameUniqueness',
				'message' => 'This lander name already exists'
			)
		),
		'abbr' => array(
			'checkUnique' => array(
				'allowEmpty' => false,
				'required' => true,
				'rule' => 'checkInternalKeyUniqueness',
				'message' => 'This lander key already exists (either in campaigns or in source mappings) - please pick a new key.'
			)
		),
		'acquisition_partner_id' => array(
			'numeric' => array(
				'allowEmpty' => false,
				'required' => true,
				'rule' => 'numeric',
				'message' => 'Please select a source'
			)
		),
	);
	
	public $belongsTo = array(
		'LanderUrl' => array(
			'className' => 'LanderUrl',
			'foreignKey' => 'lander_url_id',
			'order' => 'LanderUrl.name ASC'
		),
		'AcquisitionPartner' => array(
			'className' => 'AcquisitionPartner',
			'foreignKey' => 'acquisition_partner_id',
			'order' => 'AcquisitionPartner.name ASC'
		),
	);
	
	public function checkInternalKeyUniqueness() {
		if (isset($this->data[$this->alias]['abbr'])) {
			$count = $this->find('count', array(
				'recursive' => -1,
				'conditions' => array(
					'Source.abbr' => $this->data[$this->alias]['abbr'],
					'Source.id <>' => isset($this->data[$this->alias]['id']) ? $this->data[$this->alias]['id']: '0',
				)
			));
			if ($count > 0) {
				return false;
			}
			App::import('Model', 'SourceMapping');
			$this->SourceMapping = new SourceMapping;
			$count = $this->SourceMapping->find('count', array(
				'conditions' => array(
					'SourceMapping.utm_source' => $this->data[$this->alias]['abbr'],
					'SourceMapping.deleted' => null
				)
			));
			if ($count > 0) {
				return false;
			}
		}
		return true;
	}
	
	public function checkDisplayNameUniqueness() {
		if (isset($this->data[$this->alias]['name'])) {
			$count = $this->find('count', array(
				'recursive' => -1,
				'conditions' => array(
					'Source.name' => $this->data[$this->alias]['name'],
					'Source.id <>' => isset($this->data[$this->alias]['id']) ? $this->data[$this->alias]['id']: '0',
				)
			));
			if ($count > 0) {
				return false;
			}
		}
		return true;
	}
}
