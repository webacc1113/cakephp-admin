<?php
App::uses('AppModel', 'Model');

class DeletionLog extends AppModel {
	public $displayField = 'name';
	public $actsAs = array('Containable');
}
