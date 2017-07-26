<?php
App::uses('AppController', 'Controller');

class OffersController extends AppController {
	public $uses = array('Offer', 'Ledger', 'Transaction', 'OfferRedemption');
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array();

	public function beforeFilter() {
		parent::beforeFilter();
	}

	private function __get_partners() {
		$partners = $this->OfferRedemption->find('all', array(
			'fields' => array(
				'DISTINCT OfferRedemption.partner'
			),
			'recursive' => -1,
			'order' => 'OfferRedemption.partner ASC'
		));

		$partners_hash = array();
		foreach ($partners as $partner) {
			$partner_name = strtolower($partner['OfferRedemption']['partner']);
			$partners_hash[$partner['OfferRedemption']['partner']] = $partner_name;
		}

		return $partners_hash;
	}
	
	public function index() {
		if ($this->request->is('post') && isset($this->data['Offer']) && isset($this->data['delete'])) {
			$deleted = 0;
			foreach ($this->data['Offer'] as $id => $value) {
				if ($value == 0 || $id == 'null') {
					continue;
				}
				
				$this->Offer->delete($id);
				$deleted++;
			}
			
			if ($deleted > 0) {
				$this->Session->setFlash('You have deleted ' . $deleted . ' Offers' . '.', 'flash_success');
				$this->redirect(array('action' => 'index'));
			}
		}
		
		$limit = 50;
		$conditions = array();
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		
		$paginate = array(
			'Offer' => array(
				'limit' => $limit,
				'order' => 'Offer.id DESC',
			)
		);
		if (!empty($conditions)) {
			$paginate['Offer']['conditions'] = $conditions;
		}
		$this->paginate = $paginate;
		$this->set('offers', $this->paginate());
	}

	public function add() {
		if ($this->request->is('post')) {
            $this->Offer->create();
			$this->request->data['Offer']['us'] = 0;
			$this->request->data['Offer']['international'] = 0;
			if ($this->request->data['Offer']['locale']) {
				if (in_array('US', $this->request->data['Offer']['locale'])) {
					$this->request->data['Offer']['us'] = 1;
				}
				if (in_array('International', $this->request->data['Offer']['locale'])) {
					$this->request->data['Offer']['international'] = 1;
				}
			}
            if ($this->Offer->save($this->request->data)) {
                $this->Session->setFlash(__('Offer has been saved.'), 'flash_success');
                return $this->redirect(array('action' => 'index'));
            }
            $this->Session->setFlash(__('Unable to add the offer.'), 'flash_error');
        }
	}

	public function edit($id) {
    	$offer = $this->Offer->findById($id);
    	if (!$offer) {
        	throw new NotFoundException(__('Invalid offer'));
    	}
    	if ($this->request->is('post') || $this->request->is('put')) {
    		$offer = array('Offer' => array(
        		'id' => $id,
       			'partner' => $this->request->data['Offer']['partner'],
       			'offer_title' => $this->request->data['Offer']['offer_title'],
       			'award' => $this->request->data['Offer']['award'],
       			'client_rate' => $this->request->data['Offer']['client_rate'],
       			'offer_url' => $this->request->data['Offer']['offer_url'],
       			'offer_desc' => $this->request->data['Offer']['offer_desc'],
       			'offer_instructions' => $this->request->data['Offer']['offer_instructions'],
       			'paid' => $this->request->data['Offer']['paid'],
       			'us' => 0,
       			'international' => 0,
          	));
			if ($this->request->data['Offer']['locale']) {
				if (in_array('US', $this->request->data['Offer']['locale'])) {
					$offer['Offer']['us'] = 1;
				}
				if (in_array('International', $this->request->data['Offer']['locale'])) {
					$offer['Offer']['international'] = 1;
				}
			}
        	if ($this->Offer->save($offer)) {
        	    $this->Session->setFlash(__('Offer has been updated.'), 'flash_success');
        	    return $this->redirect(array('action' => 'index'));
        	}
        	$this->Session->setFlash(__('Unable to update the offer.'), 'flash_error');
    	}
    	if (!$this->request->data) {
        	$this->request->data = $offer;
    	}
	}

	public function active() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$id = $this->request->data['id'];
			$offer = $this->Offer->findById($id);
    		$active = ($offer['Offer']['active']) ? 0 : 1;
    		$this->Offer->save(array('Offer' => array(
    					'id' => $id,
						'active' => $active,
					)), true, array('active'));
    		return new CakeResponse(array(
					'body' => json_encode(array(
						'status' => $active
					)),
					'type' => 'json',
					'status' => '201'
				));
		}
	}

	public function groupon() {

		$limit = 50;

		$conditions = array();
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		if (isset($this->data['user']) && !empty($this->data['user'])) {
			if ($this->data['user']{0} == '#') {
				$user = $this->User->fromId(substr($this->data['user'], 1));
			}
			else {
				$user = $this->User->fromEmail($this->data['user']);
			}
			if ($user) {
				$conditions['Ledger.user_id'] = $user['User']['id'];
			}
			else {
				$conditions['Ledger.user_id'] = '0';
			}
		}
		if (isset($this->data['order_id']) && !empty($this->data['order_id'])) {
			$conditions['Ledger.order_id'] = $this->data['order_id'];
		}
		$paginate = array(
			'Ledger' => array(
				'limit' => $limit,
				'order' => 'Ledger.id DESC',
			)
		);
		if (!empty($conditions)) {
			$paginate['Ledger']['conditions'] = $conditions;
		}
		$this->paginate = $paginate;
		$this->set('records', $this->paginate('Ledger'));
	}

	public function revenues() {
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}

		$limit = 50;
		$partners = $this->__get_partners();
		
		if (isset($this->data) && !empty($this->data)) {
			if (isset($this->data['date_from']) && !empty($this->data['date_from'])) {
				if (isset($this->data['date_to']) && !empty($this->data['date_to'])) {
					$date_from = date(DB_DATE, strtotime($this->data['date_from']));
					$date_to = date(DB_DATE, strtotime($this->data['date_to']));
				}
				else {
					$date_from = date(DB_DATE, strtotime($this->data['date_from']));
					$date_to = date(DB_DATE, strtotime($this->data['date_from']));
				}
			}
		}

		if (!isset($date_from) && !isset($date_to)) { // Get the last 7 days' data as default
			$this->data = array(
				'date_from' => date('m/d/Y', strtotime("7 days ago")),
				'date_to' => date('m/d/Y', strtotime("1 day ago"))
			);
		}
		else {
			$this->OfferRedemption->virtualFields = array(
				'created_date' => 'DATE(OfferRedemption.created)',
			);

			$offer_redemptions = $this->OfferRedemption->find('all', array(
				'recursive' => -1,
				'conditions' => array(
					'OfferRedemption.created >=' => $date_from . ' 00:00:00',
					'OfferRedemption.created <=' => $date_to . ' 23:59:59',
					'OfferRedemption.status' => OFFER_REDEMPTION_ACCEPTED
				),
				'order' => 'OfferRedemption.id DESC',
				'fields' => array(
					'OfferRedemption.partner',
					'created_date',
					'OfferRedemption.revenue',
				)
			));
		
			$partner_revenues = array();
			foreach ($offer_redemptions as $offer_redemption) {				
				$created_date = $offer_redemption['OfferRedemption']['created_date'];
				$partner = $offer_redemption['OfferRedemption']['partner'];
				$revenue = !empty($offer_redemption['OfferRedemption']['revenue']) ? $offer_redemption['OfferRedemption']['revenue'] : 0;

				if (isset($partner_revenues[$created_date][$partner])) {
					$partner_revenues[$created_date][$partner] += $revenue;
				}
				else {
					$partner_revenues[$created_date][$partner] = $revenue;
				}
			}
			
			$line_totals = array();
			foreach ($partner_revenues as $created_date => $partner_revenue) {
				$line_totals[$created_date] = array_sum($partner_revenue);
			}
			$grand_total = array_sum($line_totals);
			$this->set(compact('partners', 'partner_revenues', 'line_totals', 'grand_total'));
		}
	}
}