<?php
App::uses('AppModel', 'Model');

class GeoState extends AppModel {
	public $displayField = 'name';
	public $actsAs = array('Containable');
}
