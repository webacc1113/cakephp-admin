<?php
App::uses('AppModel', 'Model');

class GeoZip extends AppModel {
	public $displayField = 'name';
	public $actsAs = array('Containable');
	
	public function getDmas() {
		
		return $this->find('list', array(
			'fields' => array('dma_code', 'dma'),
			'conditions' => array(
				'dma_code !=' => '',
			),
			'order' => 'dma asc',
			'group' => 'dma_code'
		));
	}
}
