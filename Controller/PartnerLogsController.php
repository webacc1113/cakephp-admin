<?php
App::uses('AppController', 'Controller');

class PartnerLogsController extends AppController {
	public $helpers = array('Html', 'Time');
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	function index() {
		$limit = 200;
		
		$conditions = array();
		if (!empty($this->request->query['partner'])) {
			$conditions['PartnerLog.partner'] = $this->request->query['partner'];
		}
		
		if (isset($this->request->query['date_from']) && !empty($this->request->query['date_from'])) {
			if (isset($this->request->query['date_to']) && !empty($this->request->query['date_to'])) {
				$conditions['PartnerLog.modified >='] = date(DB_DATE, strtotime($this->request->query['date_from']));
				$conditions['PartnerLog.modified <='] = date(DB_DATE, strtotime($this->request->query['date_to']) + 86400);
			}
			else {
				$conditions['PartnerLog.modified >='] = date(DB_DATE, strtotime($this->request->query['date_from'])).' 00:00:00';
				$conditions['PartnerLog.modified <='] = date(DB_DATE, strtotime($this->request->query['date_from'])).' 23:59:59';
			}
		}
		if (isset($this->request->query['status']) && !empty($this->request->query['status'])) {
			if ($this->request->query['status'] == 'error') {
				$conditions['PartnerUser.id'] = null;
			}
			else {
				$conditions['PartnerUser.id !='] = null;
			}
		}	
		if (isset($this->request->query['keyword']) && !empty($this->request->query['keyword'])) {
			$conditions['OR'] = array(
				'PartnerLog.result_code LIKE' => '%'.$this->request->query['keyword'].'%',
				'PartnerLog.sent LIKE' => '%'.$this->request->query['keyword'].'%',
				'PartnerLog.raw_output LIKE' => '%'.$this->request->query['keyword'].'%',
			);
		}	
		$paginate = array(
			'PartnerLog' => array(
				'order' => 'PartnerLog.id DESC',
				'limit' => $limit,
				'fields' => array('PartnerLog.id', 'PartnerLog.user_id', 'PartnerLog.partner', 'PartnerLog.result_code', 'PartnerLog.modified', 'PartnerUser.id'),
				'conditions' => $conditions
			)
		);
		
		$this->PartnerLog->bindModel(array(
			'belongsTo' => array(
				'PartnerUser' => array(
					'foreignKey' => false,
					'conditions' => array(
						'PartnerUser.user_id = PartnerLog.user_id',
						'PartnerUser.partner = PartnerLog.partner'
					)
				)
			)
		), false);
		
		$this->paginate = $paginate;
		$partner_logs = $this->paginate('PartnerLog');
		$this->set(compact('partner_logs'));
	}
	
	function raw($partner_log_id = null) {
		if (empty($partner_log_id)) {
			throw new NotFoundException();
		}
		
		$partner_log = $this->PartnerLog->find('first', array(
			'conditions' => array(
				'PartnerLog.id' => $partner_log_id
			),
			'fields' => array('id', 'raw_output', 'sent'),
			'recursive' => -1
		));
		$this->layout = null;
		$this->set(compact('partner_log'));
	}
	
	public function unsuccessful() {
		$limit = 200;
		
		$conditions = array(
			'NOT' => array(
				'PartnerLog.result_code' => array(200, 201)
			)
		);
		if (isset($this->request->query['date']) && !empty($this->request->query['date'])) {
			$conditions['PartnerLog.created >='] = date(DB_DATE, strtotime($this->request->query['date'])). ' 00:00:00';
			$conditions['PartnerLog.created <='] = date(DB_DATE, strtotime($this->request->query['date'])). ' 23:59:59';
		}
		else {
			$conditions['PartnerLog.created >='] = date(DB_DATE, strtotime('-1 days')). ' 00:00:00';
			$conditions['PartnerLog.created <='] = date(DB_DATE, strtotime('-1 days')). ' 23:59:59';
		}
		
		$paginate = array(
			'PartnerLog' => array(
				'order' => 'PartnerLog.id DESC',
				'limit' => $limit,
				'fields' => array('PartnerLog.id', 'PartnerLog.user_id', 'PartnerLog.result_code', 'PartnerLog.partner', 'PartnerLog.created'),
				'conditions' => $conditions
			)
		);

		$this->paginate = $paginate;
		$partner_logs = $this->paginate('PartnerLog');
		$this->set(compact('partner_logs'));
	}
}
