<?php
App::uses('AppModel', 'Model');

class SourceMapping extends AppModel {
	public $actsAs = array('Containable');
	public $belongsTo = array(
		'AcquisitionPartner'
	); 
	public $validate = array(
		'name' => array(
			'rule' => 'notEmpty',
			'required' => true
		),
		'utm_source' => array(
			'custom' => array(
				'rule' => array('validateUtmSource'),
				'message' => 'This UTM source already exists either in source mappings or in campaigns - you cannot define multiple rules for a single UTM source. '
			)
		)
	);
	
	public function beforeSave($options = array()) {
		return true;
	}
	
	public function validateUtmSource() {
		if (isset($this->data['SourceMapping']['id'])) {
			$count = $this->find('count', array(
				'conditions' => array(
					'SourceMapping.utm_source' => $this->data['SourceMapping']['utm_source'],
					'SourceMapping.id <>' => $this->data['SourceMapping']['id'],
					'SourceMapping.deleted' => null
				)
			));
			if ($count > 0) {
				return false;
			} 
			// block creation of source mappings if it exists in campaigns
			App::import('Model', 'Source');
			$this->Source = new Source;
			$count = $this->Source->find('count', array(
				'conditions' => array(
					'Source.abbr' => $this->data['SourceMapping']['utm_source'],
					'Source.active' => true
				)
			));
			if ($count > 0) {
				return false;
			}
			return true;
		}
		else {
			$count = $this->find('count', array(
				'conditions' => array(
					'SourceMapping.utm_source' => $this->data['SourceMapping']['utm_source'],
					'SourceMapping.deleted' => null
				)
			));
			return $count == 0; 
		}
		return true;
	}
}
