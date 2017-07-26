<?php
App::uses('AppController', 'Controller');
App::import('Lib', 'CloudFrontLib');

class CloudFrontController extends AppController {
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array();
	
	public function beforeFilter() {
		parent::beforeFilter();		
	}
	
	function index() {		
		CloudFrontLib::invalidate_cloudfront();
		$this->Session->setFlash(__('js/img/css invalidated successfully.'), 'flash_success');
		$this->redirect('/');
	}
}