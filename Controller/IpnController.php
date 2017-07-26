<?php
App::uses('AppController', 'Controller');

class IpnController extends AppController {
	
	public function beforeFilter() {
		parent::beforeFilter();
		$this->Auth->allow('index');
	}
	
	public function index() {
		if ($this->request->is('post') || $this->request->is('put')) {
			CakeLog::write('ipn', print_r($_POST, true));
		}
		
		// 
    	return new CakeResponse(array(
			'body' => '', 
			'type' => 'html',
			'status' => '201'
		));
	}
	
	public function view() {
		$file = new File(ROOT.'/app/tmp/logs/ipn.log');
		$log = $file->read(true, 'r');
		$this->set(compact('log'));
	}	
}