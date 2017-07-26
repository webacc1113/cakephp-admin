<?php
App::uses('AppModel', 'Model');

class Question extends AppModel {
	
	public $actsAs = array('Containable');
	
	public $validate = array(
		'partner' => array(
			'rule' => array('isUnique', array('partner', 'partner_question_id'), false),
			'required' => true,
			'message' => 'Question ID already exists for specified partner'
		)
	);
}