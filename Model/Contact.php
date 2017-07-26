<?php
App::uses('AppModel', 'Model');

class Contact extends AppModel {
	public $actsAs = array('Containable');
	
	public $belongsTo = array(
		'Client' => array(
			'className' => 'Client',
			'foreignKey' => 'linked_to_id',
			'conditions' => array('Contact.contact_type' => 'Client')
		),
		'Partner' => array(
			'className' => 'Partner',
			'foreignKey' => 'linked_to_id',
			'conditions' => array('Contact.contact_type' => 'Partner'),
		),
    );
    
    public $validate = array(
        'contact_name' => array(
            'rule' => 'notEmpty'
        ),
    );
}
