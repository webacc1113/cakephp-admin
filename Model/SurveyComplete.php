<?php
App::uses('AppModel', 'Model');

class SurveyComplete extends AppModel {
	public $actsAs = array('Containable');	
    public $validate = array(
        'hash' => array(
            'rule' => 'notEmpty'
        ),
		'survey_id' => array(
			'rule' => 'notEmpty'
		)
    );
}
