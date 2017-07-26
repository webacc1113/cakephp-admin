<?php
App::uses('AppModel', 'Model');

class Nonce extends AppModel {
	public $actsAs = array('Containable');
}