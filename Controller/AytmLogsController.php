<?php
App::uses('AppController', 'Controller');
App::uses('HttpSocket', 'Network/Http');
class AytmLogsController extends AppController {
	public $uses = array('AytmLog');
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array();

	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		$conditions = array();
		$paginate = array(
			'AytmLog' => array(
				'limit' => 50,
				'order' => 'AytmLog.id DESC',
			)
		);
		$this->paginate = $paginate;
		$aytm_logs = $this->paginate();
		
		$this->set(compact('aytm_logs'));		
	}
	
	function raw($aytm_log_id = null, $aytm_raw_type = null) {
		if (empty($aytm_log_id) || empty($aytm_raw_type)) {
			throw new NotFoundException();
		}
		
		$aytm_log = $this->AytmLog->find('first', array(
			'conditions' => array(
				'AytmLog.id' => $aytm_log_id
			),
			'fields' => array('id', $aytm_raw_type),
			'recursive' => -1
		));
		$this->layout = null;
		$this->set(compact('aytm_log', 'aytm_raw_type'));
	}
	
}