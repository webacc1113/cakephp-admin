<?php
App::uses('AppModel', 'Model');

class Query extends AppModel {
	public $actsAs = array('Containable');
	
    public $hasMany = array(
        'QueryHistory' => array(
            'className' => 'QueryHistory',
            'foreignKey' => 'query_id',
        ),
    );

	public function beforeSave($options = array()) {
		if (!isset($this->data[$this->alias]['id']) && isset($this->data[$this->alias]['parent_id']) && !empty($this->data[$this->alias]['parent_id'])) {
			$parent = $this->find('first', array(
				'conditions' => array(
					'Query.id' => $this->data[$this->alias]['parent_id']
				),
				'recursive' => -1,
				'fields' => array('query_string')
			));
			if ($parent) {
				$parent_query = json_decode($parent['Query']['query_string'], true);
				$current_query = json_decode($this->data[$this->alias]['query_string'], true); 
				foreach ($parent_query as $key => $val) {
					if (!isset($current_query[$key])) {
						$current_query[$key] = $parent_query[$key];
					}
				}
				$this->data[$this->alias]['query_string'] = json_encode($current_query); 
			}
		}
		return true;
	}
}