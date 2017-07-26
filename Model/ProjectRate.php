<?php
App::uses('AppModel', 'Model');

class ProjectRate extends AppModel {
	public $displayField = 'client_rate';
	public $actsAs = array('Containable');
	
}
