<?php
App::uses('AppModel', 'Model');
/**
 * ProfileAnswer Model
 *
 */
class ProfileAnswer extends AppModel {

/**
 * Display field
 *
 * @var string
 */
	public $displayField = 'name';

/**
 * Validation rules
 *
 * @var array
 */
	public $validate = array(
		'profile_id' => array(
			'numeric' => array(
				'rule' => array('numeric'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'profile_question_id' => array(
			'numeric' => array(
				'rule' => array('numeric'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'name' => array(
			'notempty' => array(
				'rule' => array('notempty'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
	);
	
	public function beforeSave($options = array()) {
		if (!isset($this->data[$this->alias]['id'])) {
			
			$max_order = $this->find('first', array(
				'recursive' => -1,
				'fields' => 'MAX(ProfileAnswer.order) as max_order',
				'conditions' => array(
					'profile_question_id' => $this->data[$this->alias]['profile_question_id']
				)
			));
			if ($max_order) {
				$this->data[$this->alias]['order'] = $max_order[0]['max_order'] + 1;
			}
		}
		return true;
	}
}
