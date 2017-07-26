<?php
App::uses('AppController', 'Controller');

class ChecksController extends AppController {
	
	public $uses = array('User');
	public $helpers = array();
	public $components = array();
	
	public function beforeFilter() {
		parent::beforeFilter();
		$this->Auth->allow('test');
	}
	
	public function test() {
		$user = $this->User->find('first', array(
			'conditions' => array(
				'User.id' => 128
			),
			'fields' => array('id'),
			'recursive' => -1
		));
		if ($user) {
			return new CakeResponse(array(
				'body' => 'MP5q7Suh', // Success
				'status' => 201
			));
		}
	}
}
