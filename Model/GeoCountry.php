<?php
App::uses('AppModel', 'Model');

class GeoCountry extends AppModel {
	public $displayField = 'name';
	public $actsAs = array('Containable');
	
	function returnAsList() {
		$countries = $this->find('list', array(
			'fields' => array('ccode', 'country'),
			'order' => 'GeoCountry.country ASC'
		));
		
		$supported_countries = array_reverse(unserialize(SUPPORTED_COUNTRIES));
		$supported_countries = array_keys($supported_countries);
		foreach ($supported_countries as $id) {
			$temp = $countries[$id];
			unset($countries[$id]);
			$countries = array($id => $temp) + $countries;
		}
		return $countries;
	}
}
