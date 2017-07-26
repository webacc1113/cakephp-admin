<?php
App::uses('AppModel', 'Model');

class UserRestore extends AppModel {
	public $useTable = 'mint_users_restore';
	public $displayField = 'name';
	public $actsAs = array('Containable');
}
