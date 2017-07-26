<?php
App::uses('AppController', 'Controller');

class TestsController extends AppController {
	public $uses = array('QueryProfile');
	public function index() {

		// try some saves
		$this->QueryProfile->create();
		$this->QueryProfile->save(array('QueryProfile' => array(
			'id' => '400740',
			'country' => 'US'
		)), true, array('country'));
		
		$this->User->create();
		$this->User->save(array('User' => array(
			'id' => '128',
			'last_touched' => date(DB_DATETIME)
		)), true, array('last_touched'));
		
		echo('Starting<br/>');
		$users = $this->QueryProfile->find('all', array(
			'fields' => array('user_id'),
			'conditions' => array(
				'QueryProfile.country' => 'US',
				'QueryProfile.state' => 'CA',
				'QueryProfile.gender' => 'M'
			),
			'joins' => array(
    		    array(
		            'alias' => 'User',
		            'table' => 'users',
		            'conditions' => array(
						'QueryProfile.user_id = User.id',
						'User.active' => true,
						'User.hellbanned' => false
					)
		        )
			)
		));		

		$this->QueryProfile->create();
		$this->QueryProfile->save(array('QueryProfile' => array(
			'id' => '400740',
			'country' => 'US'
		)), true, array('country'));
		
		$this->User->create();
		$this->User->save(array('User' => array(
			'id' => '128',
			'last_touched' => date(DB_DATETIME)
		)), true, array('last_touched'));
		
		echo('Ran master<br/>');
		echo('Switched to slave<br/>');
		$users = $this->QueryProfile->find('all', array(
			'fields' => array('user_id'),
			'conditions' => array(
				'QueryProfile.country' => 'US',
				'QueryProfile.state' => 'CA',
				'QueryProfile.gender' => 'M'
			),
			'joins' => array(
    		    array(
		            'alias' => 'User',
		            'table' => 'users',
		            'conditions' => array(
						'QueryProfile.user_id = User.id',
						'User.active' => true,
						'User.hellbanned' => false
					)
		        )
			)
		));
		echo('Ran slave<br/>');
		$dbquery = $this->QueryProfile->getLastQuery();
		echo($dbquery.'<br/>');
		echo('Switched to master<br/>');
		$users = $this->QueryProfile->find('all', array(
			'fields' => array('user_id'),
			'conditions' => array(
				'QueryProfile.country' => 'US',
				'QueryProfile.state' => 'CA',
				'QueryProfile.gender' => 'M'
			),
			'joins' => array(
    		    array(
		            'alias' => 'User',
		            'table' => 'users',
		            'conditions' => array(
						'QueryProfile.user_id = User.id',
						'User.active' => true,
						'User.hellbanned' => false
					)
		        )
			)
		));
		echo('Finished master<br/>');
		exit();
	}
}