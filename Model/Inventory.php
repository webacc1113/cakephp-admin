<?php
App::uses('AppModel', 'Model');

class Inventory extends AppModel {
	
	public $actsAs = array('Containable');
	
}