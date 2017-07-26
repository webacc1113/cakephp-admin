<?php
App::uses('AppController', 'Controller');

class DashboardsController extends AppController {
	public $uses = array('Dashboard', 'Offer', 'Poll', 'Project');
	public $helpers = array('Html', 'Text', 'Time');
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		$offers = $this->Offer->find('all', array(
			'conditions' => array(
				'Offer.active' => 1
			),
			'order' => array(
				'Offer.offer_title ASC'
			),
			'recursive' => -1
		));
		$polls = $this->Poll->find('all', array(
			'order' => array(
				'Poll.poll_question ASC'
			),
			'recursive' => -1
		));
		$projects = $this->Project->find('all', array(
			'fields' => array(
				'Project.id', 'Project.survey_name'
			),
			'conditions' => array(
				'Project.active' => true
			),
			'order' => array(
				'Project.survey_name ASC'
			),
			'recursive' => -1
		));
		$offer_ids = $this->Dashboard->find('list', array(
			'conditions' => array(
				'Dashboard.item_type' => 'offer',
			),
			'fields' => array('Dashboard.item_id')
		));
		$poll_ids = $this->Dashboard->find('list', array(
			'conditions' => array(
				'Dashboard.item_type' => 'poll',
			),
			'fields' => array('Dashboard.item_id')
		));
		$survey_ids = $this->Dashboard->find('list', array(
			'conditions' => array(
				'Dashboard.item_type' => 'survey',
			),
			'fields' => array('Dashboard.item_id')
		));
		$section_ids = $this->Dashboard->find('list', array(
			'conditions' => array(
				'Dashboard.item_type' => 'section',
			),
			'fields' => array('Dashboard.item_id')
		));
		$this->set(compact('offers', 'polls', 'projects', 'sections', 'offer_ids', 'poll_ids', 'survey_ids', 'section_ids'));
	}
	
	public function lst() {
		$this->layout = 'ajax';
		$items = $this->Dashboard->find('all', array(
			'joins' => array(
				array('table' => 'offers',
					'alias' => 'Offer',
					'type' => 'LEFT',
					'conditions' => array(
						"Offer.id = Dashboard.item_id",
						"Dashboard.item_type = 'offer'",
					)
				),
				array('table' => 'polls',
					'alias' => 'Poll',
					'type' => 'LEFT',
					'conditions' => array(
						"Poll.id = Dashboard.item_id",
						"Dashboard.item_type = 'poll'",
					)
				),
				array('table' => 'projects',
					'alias' => 'Project',
					'type' => 'LEFT',
					'conditions' => array(
						"Project.id = Dashboard.item_id",
						"Dashboard.item_type = 'survey'",
					)
				),
				array('table' => 'profile_sections',
					'alias' => 'ProfileSection',
					'type' => 'LEFT',
					'conditions' => array(
						"ProfileSection.id = Dashboard.item_id",
						"Dashboard.item_type = 'section'",
					)
				)
			),
			'order' => array(
				'Dashboard.order ASC'
			)
		));
		$this->set(compact('items'));
	}
	
	public function add() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$item_id = $this->request->data['item_id'];
			$item_title = $this->request->data['item_title'];
			$item_type = $this->request->data['item_type'];
			$checked = $this->request->data['checked'];
			if ($checked) {
				$max_order = $this->Dashboard->find('first', array(
					'fields' => 'MAX(Dashboard.order) as max_order'
				));
				$max_order = $max_order[0]['max_order'];
				$this->Dashboard->create();
				$this->Dashboard->save(array('Dashboard' => array(
    				'item_id' => $item_id,
					'item_title' => $item_title,
					'item_type' => $item_type,
					'item_title' => $item_title,
					'order' => $max_order + 1
				)));
			} else {
				$dashboard_items = $this->Dashboard->find('all', array(
					'recursive' => -1,
					'conditions' => array(
						'Dashboard.item_id' => $item_id,
						'Dashboard.item_type' => $item_type
					),
					'fields' => array('Dashboard.id')
				));
				if ($dashboard_items) {
					foreach ($dashboard_items as $dashboard_item) {
						$this->Dashboard->delete($dashboard_item['Dashboard']['id'], false); 
					}
				}
			}
    		return new CakeResponse(array(
					'body' => json_encode(array(
						'status' => '1'
					)),
					'type' => 'json',
					'status' => '201'
				));
		}
	}
	
	public function save_order() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$items = $this->request->data['items'];
			$i = 0;
			foreach ($items as $id) {
				$i++;
				$this->Dashboard->save(array('Dashboard' => array(
					'id' => $id,
					'order' => $i,
				)), true, array('order'));
			}
			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => '1'
				)),
				'type' => 'json',
				'status' => '201'
			));	
		}
	}
}