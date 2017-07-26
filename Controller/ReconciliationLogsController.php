<?php
App::uses('AppController', 'Controller');

class ReconciliationLogsController extends AppController {
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index($reconciliation_id) {
		if (empty($reconciliation_id)) {
			$this->redirect(array('controller' => 'reconciliations', 'action' => 'index'));
		}
		
		$paginate = array(
			'ReconciliationLog' => array(
				'limit' => '50',
				'order' => 'ReconciliationLog.id DESC'
			)
		);
		$paginate['ReconciliationLog']['conditions']['ReconciliationLog.reconciliation_id'] = $reconciliation_id;
		$this->paginate = $paginate;	
		$this->set('reconciliation_logs', $this->paginate('ReconciliationLog'));
	}	
}
