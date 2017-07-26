<?php
App::uses('AppModel', 'Model');

class SocialglimpzRespondent extends AppModel {
	public $displayField = 'user_id';
	public $actsAs = array('Containable');	
}