<?php
App::uses('AppModel', 'Model');

class MbdStale extends AppModel {
	public $displayField = 'dwid';
	public $actsAs = array('Containable');
}
