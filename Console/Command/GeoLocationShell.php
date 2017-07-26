<?php

class GeoLocationShell extends AppShell {
	public $uses = array('Setting');
	
	function get_data() {
		if (!isset($this->args[0])) {
			return;
		}
		$address = $this->args[0];
		
		$settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array('geolocation.key'),
				'Setting.deleted' => false
			),
			'fields' => array('name', 'value')
		));
		
		if (empty($settings) || empty($settings['geolocation.key'])) {
			echo 'Settings not found';
			return;
		}
		
		App::import('Vendor', 'GeoCoder', array('file' => 'GeoCoder' . DS . 'GoogleMapsGeocoder.php'));
		$Geocoder = new GoogleMapsGeocoder($address);
		
		if (!empty($settings['geolocation.key'])) {
			$Geocoder->setApiKey($settings['geolocation.key']);
		}
		$response = $Geocoder->geocode();
		
		print_r ($response);
		
	}
}