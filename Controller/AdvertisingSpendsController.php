<?php
App::uses('AppController', 'Controller');

class AdvertisingSpendsController extends AppController {
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array();
	public $uses = array('AdvertisingSpend', 'AcquisitionPartner', 'GeoCountry');
	
	public function beforeFilter() {
		parent::beforeFilter();	

		if (in_array($this->action, array('add', 'edit', 'index'))) {			
			$acquisition_partners = $this->AcquisitionPartner->find('list', array(
				'fields' => array('id', 'name'),
				'conditions' => array(
					'AcquisitionPartner.active' => true
				),
				'order' => 'AcquisitionPartner.name ASC'
			));
			$supported_countries = array_keys(unserialize(SUPPORTED_COUNTRIES)); 
			$countries = array();
			foreach ($supported_countries as $country) {
				$countries[$country] = $country;
			}
			$this->set(compact('acquisition_partners', 'countries'));
		}
	}
	
	function index() {
		if ($this->request->is('post') && isset($this->data['AdvertisingSpend']) && isset($this->data['delete'])) {
			$deleted = 0;
			foreach ($this->data['AdvertisingSpend'] as $id => $value) {
				if ($value == 0 || $id == 'null') {
					continue;
				}

				$this->AdvertisingSpend->delete($id);
				$deleted++;
			}
			
			if ($deleted > 0) {
				$this->Session->setFlash('You have deleted ' . $deleted . ' records'. '.', 'flash_success');
				$this->redirect(array('action' => 'index'));
			}
		}
		
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		
		$conditions = array();
		$filter_date = $filter_partner = false;
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
				
				$filter_date = true;
				$conditions[] = array(
					'AdvertisingSpend.date >=' => $date_from,
					'AdvertisingSpend.date <=' => $date_to,
				);
			}
			if (isset($this->data['acquisition_partner_id']) && !empty($this->data['acquisition_partner_id'])) {
				$filter_partner = true;
				$conditions[] = array(
					'AdvertisingSpend.acquisition_partner_id' => $this->data['acquisition_partner_id']
				);
			}
            if (isset($this->data['country']) && !empty($this->data['country'])) {
                $conditions[] = array(
                    'AdvertisingSpend.country' => $this->data['country']
                );
            }
		}
		
		$advertising_spends = $this->AdvertisingSpend->find('all', array(
			'conditions' => $conditions,
			'fields' => array('SUM(AdvertisingSpend.spend) AS spend'),
			'recursive' => -1
		));
		
		$limit = 200;
		if ($filter_date && $filter_partner) {
			$count = $this->AdvertisingSpend->find('count', array(
				'conditions' => $conditions
			));
			$limit = ($count > 0) ? $count : $limit; // generally speaking, if the user has specified certain parameters, do not paginate it
		}

		$paginate = array(
			'AdvertisingSpend' => array(
				'conditions' => $conditions,
				'limit' => $limit, 
				'order' => 'AdvertisingSpend.date DESC',
			)
		);
		$this->paginate = $paginate;
		$this->set('advertising_spends', $this->paginate());
		$this->set('spend', $advertising_spends[0][0]['spend']);
	}
	
	function add() {
		if ($this->request->is('post')) {
			if (!empty($this->request->data['AdvertisingSpend']['date'])) {
				$this->request->data['AdvertisingSpend']['date'] = date(DB_DATE, strtotime($this->request->data['AdvertisingSpend']['date'])); 
			}
			$this->AdvertisingSpend->create();
			$save = $this->AdvertisingSpend->save($this->request->data); 
			
            if ($save) {
                $this->Session->setFlash(__('Advertising spend has been saved.'), 'flash_success');
                return $this->redirect(array('action' => 'index'));
            }
			
            $this->Session->setFlash(__('Unable to add the advertising spend.'), 'flash_error');
        }
	}
	
	function edit($id = null) {
		if (empty($id)) {
			throw new NotFoundException();
		}
		$advertising_spend = $this->AdvertisingSpend->findById($id);
		if (!$advertising_spend) {
			throw new NotFoundException();
		}
		if ($this->request->is(array('put', 'post'))) {
			$this->AdvertisingSpend->create();
			$save = $this->AdvertisingSpend->save(array('AdvertisingSpend' => array(
				'id' => $this->request->data['AdvertisingSpend']['id'],
				'acquisition_partner_id' => $this->request->data['AdvertisingSpend']['acquisition_partner_id'],
				'country' => $this->request->data['AdvertisingSpend']['country'],
				'date' => !empty($this->request->data['AdvertisingSpend']['date']) ? date(DB_DATE, strtotime($this->request->data['AdvertisingSpend']['date'])) : null,
				'spend' => $this->request->data['AdvertisingSpend']['spend']
			)), true, array('acquisition_partner_id', 'date', 'spend', 'country'));
			
			if ($save) {
				$this->Session->setFlash(__('Advertising spend has been updated.'), 'flash_success');
        	    return $this->redirect(array('action' => 'index'));
			}
			$this->Session->setFlash(__('Unable to update advertising spend.'), 'flash_error');
		}
		else {
			$this->request->data = $advertising_spend;
		}
	}
}
