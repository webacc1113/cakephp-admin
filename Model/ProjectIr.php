<?php
App::uses('AppModel', 'Model');

class ProjectIr extends AppModel {
	public $actsAs = array('Containable');
	public $displayField = 'ir';
	public $belongsTo = array('Project');
	
}
