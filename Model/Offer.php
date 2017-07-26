<?php
App::uses('AppModel', 'Model');

class Offer extends AppModel {
	public $actsAs = array('Containable');
	
	public $validate = array(
        'offer_title' => array(
            'rule' => 'notEmpty'
        ),
        'award' => array(
            'rule' => 'notEmpty'
        ),
        'offer_url' => array(
            'rule' => 'notEmpty'
        ),
        'offer_desc' => array(
            'rule' => 'notEmpty'
        )
    );
}
