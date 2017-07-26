<?php
App::uses('AppController', 'Controller');

class FedsController extends AppController {
	public $uses = array('FedQueryProfile');
	public $components = array();

	public function beforeFilter() {
		parent::beforeFilter();
		$this->Auth->allow('result');
	}
	
	function result() {
		$result = json_decode($this->request->data['result'], true);
		if ($this->FedQueryProfile->saveAll($result)) {
			return new CakeResponse(array(
				'body' => json_encode(array('success' => __('Result saved.'))),
				'type' => 'json',
				'status' => '201'
			));
		}
		else {
			return new CakeResponse(array(
				'body' => json_encode(array('error' => __('Failed!'))),
				'type' => 'json',
				'status' => '404'
			));
		}
	}
}