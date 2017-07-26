<?php
App::uses('AppModel', 'Model');
/**
 * ProfileQuestion Model
 *
 * @property Profile $Profile
 * @property ProfileAnswer $ProfileAnswer
 * @property UserProfileAnswer $UserProfileAnswer
 */
class ProfileQuestion extends AppModel {

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
		'type' => array(
			'notempty' => array(
				'rule' => array('notempty'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
		'order' => array(
			'numeric' => array(
				'rule' => array('numeric'),
				//'message' => 'Your custom message here',
				//'allowEmpty' => false,
				//'required' => false,
				//'last' => false, // Stop validation after this rule
				//'on' => 'create', // Limit validation to 'create' or 'update' operations
			),
		),
	);

	//The Associations below have been created with all possible keys, those that are not needed can be removed

/**
 * belongsTo associations
 *
 * @var array
 */
	public $belongsTo = array(
		'Profile' => array(
			'className' => 'Profile',
			'foreignKey' => 'profile_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		)
	);

/**
 * hasMany associations
 *
 * @var array
 */
	public $hasMany = array(
		'ProfileAnswer' => array(
			'className' => 'ProfileAnswer',
			'foreignKey' => 'profile_question_id',
			'dependent' => false,
			'conditions' => '',
			'fields' => array('id', 'name', 'order'),
			'order' => 'ProfileAnswer.order ASC',
			'limit' => '',
			'offset' => '',
			'exclusive' => '',
			'finderQuery' => '',
			'counterQuery' => ''
		)
	);
	
	public function beforeSave($options = array()) {
		if (!isset($this->data[$this->alias]['id'])) {
			
			$max_order = $this->find('first', array(
				'fields' => 'MAX(ProfileQuestion.order) as max_order',
				'conditions' => array(
					'ProfileQuestion.profile_id' => $this->data[$this->alias]['profile_id']
				)
			));
			if ($max_order) {
				$this->data[$this->alias]['order'] = $max_order[0]['max_order'] + 1;
			}
		}
		return true;
	}
	
	public function updateCounter($profile_id) {
		$count = $this->find('count', array(
			'recursive' => -1,
			'conditions' => array(
				'ProfileQuestion.profile_id' => $profile_id
			)
		));
		$this->Profile->save(array(
			'id' => $profile_id,
			'count' => $count
		), array(
			'fieldList' => array('count'),
			'validate' => false,
			'callbacks' => false
		));
	}
	
	public function afterSave($created, $options = array()) {
		if (isset($this->data[$this->alias]['profile_id'])) {
			$this->updateCounter($this->data[$this->alias]['profile_id']);
		}
	}
}
