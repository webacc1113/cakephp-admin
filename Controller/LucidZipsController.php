<?php

App::uses('AppController', 'Controller');

class LucidZipsController extends AppController {
	public $uses = array('LucidZip', 'GeoZip', 'GeoState');
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array();

	public function beforeFilter() {
		parent::beforeFilter();
	}

	function index() {
		$paginate = array(
			'LucidZip' => array(
				'fields' => array('LucidZip.id', 'LucidZip.dma_name', 'LucidZip.modified'),
				'limit' => 200,
				'order' => 'LucidZip.dma_name ASC',
				'group' => 'LucidZip.dma',
				'conditions' => array(
					'LucidZip.dma_name <>' => ''
				),
				'recursive' => -1
			)
		);
		$this->paginate = $paginate;
		$this->set('lucid_zips', $this->paginate());
	}

	function edit($id = null) {
		if (empty($id)) {
			throw new NotFoundException();
		}
		
		$lucid_zip = $this->LucidZip->findById($id);
		if (!$lucid_zip) {
			throw new NotFoundException();
		}

		if ($this->request->is(array('put', 'post'))) {
			if ($lucid_zip['LucidZip']['dma_name'] == $this->request->data['LucidZip']['dma_name']) {
				$this->Session->setFlash(__('Dma name is same as in db.'), 'flash_error');
			}
			elseif (empty($this->request->data['LucidZip']['dma_name'])) {
				$this->Session->setFlash(__('You cannot set an empty DMA'), 'flash_error');
			}
			else {
				$lucid_zips = $this->LucidZip->find('list', array(
					'fields' => array('LucidZip.id'),
					'conditions' => array(
						'LucidZip.dma' => $lucid_zip['LucidZip']['dma']
					),
				));
				foreach ($lucid_zips as $id) {
					$this->LucidZip->create();
					$this->LucidZip->save(array('LucidZip' => array(
						'id' => $id,
						'dma_name' => $this->request->data['LucidZip']['dma_name'],
						'modified' => false
					)), true, array('dma_name'));
				}

				$this->Session->setFlash(__('DMA name has been updated. ('.count($lucid_zips).' ZIP codes updated)'), 'flash_success');
				return $this->redirect(array('action' => 'index'));
			}
		}
		
		if (!$this->request->data) {
			$this->request->data = $lucid_zip;
		}
	}
	
	function search() {
		if (isset($this->request->query) && !empty($this->request->query)) {
			$conditions = array();
			
			if (isset($this->request->query['zipcode']) && !empty($this->request->query['zipcode'])) {
				$conditions['LucidZip.zipcode'] = $this->request->query['zipcode'];
			}
			if (isset($this->request->query['state_abbr']) && !empty($this->request->query['state_abbr'])) {
				$conditions['LucidZip.state_abbr'] = $this->request->query['state_abbr'];
			}
			if (isset($this->request->query['city']) && !empty($this->request->query['city'])) {
				$conditions['LucidZip.city'] = $this->request->query['city'];
			}
			
			$zip_codes = $this->LucidZip->find('all', array(
				'conditions' => $conditions,
				'fields' => array(
					'LucidZip.id',
					'LucidZip.zipcode',
					'LucidZip.state_abbr',
					'LucidZip.dma',
					'LucidZip.dma_name',
					'LucidZip.msa',
					'LucidZip.city'
				),
				'order' => 'LucidZip.dma_name ASC',
				'group' => 'LucidZip.dma',
			));
			
			$this->set(compact('zip_codes'));
		}
	}
	
	function add($lucid_zip_id = null) {
		if ($this->request->is('put') || $this->request->is('post')) {
			$save_lucid_zip = $save_geo_zip = false;
			$count = $this->LucidZip->find('count', array(
				'conditions' => array(
					'LucidZip.zipcode' => $this->request->data['zipcode']
				)
			));
			if ($count == 0) {
				$this->LucidZip->create();
				$this->LucidZip->save($this->request->data);
				$save_lucid_zip = true;
			}
			
			// Add/update GeoZip
			$geo_state = $this->GeoState->find('first', array(
				'fields' => array('GeoState.id'),
				'conditions' => array(
					'GeoState.state_abbr' => $this->request->data['state_abbr']
				)
			));
			if ($geo_state) {
				$this->GeoZip->bindModel(array(
					'belongsTo' => array(
						'GeoState' => array(
							'className' => 'GeoState',
							'foreignKey' => 'state_id',
						)
					)
				));
				$geo_zip = $this->GeoZip->find('first', array(
					'conditions' => array(
						'GeoZip.zipcode' => $this->request->data['zipcode']
					)
				));
				if ($geo_zip && (empty($geo_zip['GeoZip']['state_id']) || $geo_zip['GeoState']['state_abbr'] != $this->request->data['state_abbr'])) {
					$this->GeoZip->create();
					$this->GeoZip->save(array('GeoZip' => array(
						'id' => $geo_zip['GeoZip']['id'],
						'state_id' => $geo_state['GeoState']['id']
					)), true, array('state_id'));
					$save_geo_zip = true;
				}
				else {
					$this->GeoZip->create();
					$this->GeoZip->save(array('GeoZip' => array(
						'zipcode' => $this->request->data['zipcode'],
						'city' => $this->request->data['city'],
						'state_id' => $geo_state['GeoState']['id'],
						'country_code' => 'US',
						'dma_code' => $this->request->data['dma'],
						'dma' => $this->request->data['dma_name'],
						'county' => $this->request->data['county'],
						'region' => $this->request->data['region'],
						'msa' => $this->request->data['msa'],
					)));
					$save_geo_zip = true;
				}
			}
			
			if ($save_geo_zip && $save_lucid_zip) {
				$message = 'GeoZip and LucidZip records saved successfully.';
			}
			elseif ($save_geo_zip) {
				$message = 'GeoZip saved successfully.';
			}
			elseif ($save_lucid_zip) {
				$message = 'LucidZip saved successfully.';
			}
			
			$this->Session->setFlash(__($message), 'flash_success');
			return $this->redirect($this->referer());
		}
		else {
			$zip_code = $this->LucidZip->find('first', array(
				'conditions' => array(
					'LucidZip.id' => $lucid_zip_id
				),
			));
			$this->set(compact('zip_code'));
		}

		$this->layout = 'ajax';
	}
}
