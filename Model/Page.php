<?php
App::uses('AppModel', 'Model');

class Page extends AppModel {
	public $actsAs = array('Containable');
    
    public $validate = array(
        'title' => array(
            'rule' => 'notEmpty'
        ),
    );
}