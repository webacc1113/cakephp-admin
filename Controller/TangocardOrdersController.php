<?php
App::uses('AppController', 'Controller');

class TangocardOrdersController extends AppController {
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		$conditions = array();
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		
		if (isset($this->data)) {
			if (isset($this->data['date_from']) && !empty($this->data['date_from'])) {
				$conditions['created > '] = date(DB_DATETIME, strtotime($this->data['date_from'].' 00:00:00')); 
			}
			
			if (isset($this->data['date_to']) && !empty($this->data['date_to'])) {
				$conditions['created <= '] = date(DB_DATETIME, strtotime($this->data['date_to'].' 23:59:59')); 
			}
			
			if (isset($this->data['user_id']) && !empty($this->data['user_id'])) {
				$conditions['user_id'] = $this->data['user_id']; 
			}
			
			if (isset($this->data['email']) && !empty($this->data['email'])) {
				$conditions['recipient_email'] = $this->data['email']; 
			}
			
			if (isset($this->data['transaction_id']) && !empty($this->data['transaction_id'])) {
				$conditions['transaction_id'] = $this->data['transaction_id']; 
			}
		}
		
		$paginate = array(
			'TangocardOrder' => array(
				'limit' => 50,
				'order' => 'TangocardOrder.id DESC',
			)
		);
		if (!empty($conditions)) {
			$paginate['TangocardOrder']['conditions'] = $conditions;
		}
		
		$this->paginate = $paginate;
		$this->set('orders', $this->paginate());
	}
	
	public function resend_reward_email($tangocard_order_id) {
		$this->TangocardOrder->bindModel(array(
			'belongsTo' => array(
				'Transaction' => array(				
					'foreignKey' => 'transaction_id'
				)
			)
		));
		$tangocard_order = $this->TangocardOrder->find('first', array(
			'contain' => array(
				'Transaction' => array(
					'User'
				)
			),
			'conditions' => array(
				'TangocardOrder.order_id' => $tangocard_order_id
			)
		));
		if (!$tangocard_order) {
			$this->Session->setFlash('Tangocard order not found.', 'flash_error');
			$this->redirect($this->referer());
		}
		
		$this->loadModel('Tangocard');
		$tangocard = $this->Tangocard->find('first', array(
			'conditions' => array(
				'Tangocard.sku' => $tangocard_order['TangocardOrder']['sku']
			),
			'order' => 'Tangocard.id DESC'
		));
		if (!$tangocard) {
			$this->Session->setFlash('Tangocard having sku '. $tangocard_order['TangocardOrder']['sku'] .' not found', 'flash_error');
			$this->redirect($this->referer());
		}
		
		$response = json_decode($tangocard_order['TangocardOrder']['response'], true);
		// Send tangocard order email
		$email = new CakeEmail();
		$email->config('mailgun');
		$email->from(array(EMAIL_SENDER => 'MintVine'))
			->replyTo(array(REPLYTO_EMAIL => 'MintVine'))
			->emailFormat('html')
			->template('payout_tango')
			->viewVars(array(
				'user_name' => $tangocard_order['Transaction']['User']['username'],
				'amount' => $tangocard_order['TangocardOrder']['denomination'],
				'reward' => $response['order']['reward'],
				'transaction_name' => (!empty($tangocard['Tangocard']['transaction_name'])) ? $tangocard['Tangocard']['transaction_name'] : 'Gift Certificate',
				'redemption_instructions' => !empty($tangocard['Tangocard']['parent_id']) ? $tangocard['Parent']['redemption_instructions'] : $tangocard['Tangocard']['redemption_instructions'],
				'disclaimer' => !empty($tangocard['Tangocard']['parent_id']) ? $tangocard['Parent']['disclaimer'] : $tangocard['Tangocard']['disclaimer'],
				'unsubscribe_link' => HOSTNAME_WWW.'/users/emails/' . $tangocard_order['Transaction']['User']['ref_id']
			))
			->to(array($tangocard_order['Transaction']['User']['email']))
			->subject('MintVine Payout');
		if ($email->send()) {
			$this->TangocardOrder->create();
			$this->TangocardOrder->save(array('TangocardOrder' => array(
				'id' => $tangocard_order['TangocardOrder']['id'],
				'resend_count' => $tangocard_order['TangocardOrder']['resend_count'] + 1,
				'last_resend' => date(DB_DATETIME)
			)), true, array('resend_count', 'last_resend'));
			$this->Session->setFlash('Reward email has been resent.', 'flash_success');
		}
		else {
			$this->Session->setFlash('Unable to send email.', 'flash_error');
		}
		
		$this->redirect($this->referer());
	}
}
