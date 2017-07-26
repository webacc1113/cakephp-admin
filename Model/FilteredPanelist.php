<?php
App::uses('AppModel', 'Model');

class FilteredPanelist extends AppModel {
	
	public $actsAs = array('Containable');
	
	var $belongsTo = array(
		'User' => array(
			'className' => 'User',
			'foreignKey' => 'user_id',
		)
	); 
	
	public $validate = array(
		'user_id' => array(
			'custom1' => array(
				'rule' => array('check_user_id'),
				'message' => 'User id does not exit'
			),
			'custom2' => array(
				'rule' => array('check_existing'),
				'message' => 'User id already in filter list for this partner'
			)
		)
	);
	
	public function check_user_id() {
		$count = $this->User->find('count', array(
			'conditions' => array(
				'User.id' => $this->data['FilteredPanelist']['user_id'],
				'User.deleted_on' => null 
			)
		));
		if ($count == 0) {
			return false;
		}
		
		return true;
	}
	
	public function check_existing() {
		$count = $this->find('count', array(
			'conditions' => array(
				'FilteredPanelist.user_id' => $this->data['FilteredPanelist']['user_id'],
				'FilteredPanelist.partner' => $this->data['FilteredPanelist']['partner'], 
			)
		));
		if ($count > 0) {
			return false;
		}
		
		return true;
	}
	
	public function group_filter_panelists() {
		App::import('Model', 'Group');
		$this->Group = new Group;
		
		$groups = $this->Group->find('all', array(
			'fields' => array('Group.id', 'Group.key', 'Group.filter_panelists')
		));
		
		$filtered_panelists_groups = $this->find('all', array(
			'fields' => array('distinct(FilteredPanelist.partner)')
		));
		$filtered_panelists_groups = Set::extract($filtered_panelists_groups, '/FilteredPanelist/partner');
		foreach ($groups as $group) {
			$save = false;
			if (empty($filtered_panelists_groups) && $group['Group']['filter_panelists']) {
				$save = true;
				$filter_panelists = false;
			}
			elseif (in_array($group['Group']['key'], $filtered_panelists_groups)) {
				if (!$group['Group']['filter_panelists']) {
					$save = $filter_panelists = true;
				}
			}
			elseif ($group['Group']['filter_panelists']) {
				$save = true;
				$filter_panelists = false;
			}
			
			if ($save) {
				$this->Group->create();
				$this->Group->save(array('Group' => array(
					'id' => $group['Group']['id'],
					'filter_panelists' => $filter_panelists
				)), true, array('filter_panelists'));
			}
		}
	}
}
