<?php
App::uses('CakeEmail', 'Network/Email');

class SsiTask extends Shell {

	public $uses = array('User', 'QueryProfile');
	private $options = array('header' => array(
			'Accept' => 'application/json',
			'Content-Type' => 'application/json; charset=UTF-8'
	));

	public function sanitize($str) {
		return preg_replace("/[^{\\ }a-zA-Z]/", '', $str);
	}
	
	public function get_valid_time_ranges() {
		
		// figure out what day it is in Eastern
		$dt = new DateTime(date(DB_DATETIME), new DateTimeZone('UTC'));
		$dt->setTimeZone(new DateTimeZone('America/New_York'));
		$eastern_date = $dt->format('Y-m-d');
		
		$start_time = $eastern_date.' 07:00:00';
		$end_time = $eastern_date.' 18:00:00';
		
		$dt = new DateTime($start_time, new DateTimeZone('America/New_York'));
		$dt->setTimeZone(new DateTimeZone('UTC'));
		$start_time = $dt->format(DB_DATETIME);
		
		$dt = new DateTime($end_time, new DateTimeZone('America/New_York'));
		$dt->setTimeZone(new DateTimeZone('UTC'));
		$end_time = $dt->format(DB_DATETIME);
		return array(
			'start' => $start_time,
			'end' => $end_time
		);
	}

}
