<?php
App::uses('AppModel', 'Model');

class CashNotification extends AppModel {
	public $displayField = 'user_id';
	public $actsAs = array('Containable');
}
