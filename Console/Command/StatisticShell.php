<?php
App::uses('Shell', 'Console');
App::import('Lib', 'Utilities');
App::import('Lib', 'MintVineUser');

class StatisticShell extends Shell {
	var $uses = array('User', 'Transaction', 'MailQueue', 'Offer', 'Statistic', 'Project', 'ProjectOption');
	
	function main() {
		$date = '2013-09-31';
		while (true) {
			$this->generate($date);
			echo 'Generated '.$date."\n";
			$ts = strtotime($date) + 86400;
			if ($ts > time()) {
				break;
			}
			$date = date('Y-m-d', $ts);
		}
	}
	
	function cp() {		
		$project_count = $this->Project->find('count', array(
			'recursive' => -1, 
			'conditions' => array(
				'Project.status' => PROJECT_STATUS_OPEN
			)
		));
		
		$project_option = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.project_id' => 0,
				'ProjectOption.name' => 'open_projects'
			)
		));
		
		if ($project_option) {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array(
				'ProjectOption' => array(
					'id' => $project_option['ProjectOption']['id'],
					'value' => $project_count
				)
			), true, array('value'));
		}
		else {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array(
				'ProjectOption' => array(
					'project_id' => 0,
					'name' => 'open_projects',
					'value' => $project_count
				)
			));
		}
		
		$user_total_count = $this->User->find('count'); 
		$project_option = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.project_id' => 0,
				'ProjectOption.name' => 'total_users'
			)
		));
		
		if ($project_option) {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array(
				'ProjectOption' => array(
					'id' => $project_option['ProjectOption']['id'],
					'value' => $user_total_count
				)
			), true, array('value'));
		}
		else {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array(
				'ProjectOption' => array(
					'project_id' => 0,
					'name' => 'total_users',
					'value' => $user_total_count
				)
			));
		}
		
		$user_verified = $this->User->find('count', array(
			'recursive' => -1,
			'conditions' => array(
				'User.verified >' => date(DB_DATETIME, strtotime('-24 hours'))
			)
		));
		
		$project_option = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.project_id' => 0,
				'ProjectOption.name' => 'total_new_users'
			)
		));
		
		if ($project_option) {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array(
				'ProjectOption' => array(
					'id' => $project_option['ProjectOption']['id'],
					'value' => $user_verified
				)
			), true, array('value'));
		}
		else {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array(
				'ProjectOption' => array(
					'project_id' => 0,
					'name' => 'total_new_users',
					'value' => $user_verified
				)
			));
		}
	}
	
	function generate($date = null) {
		if (is_null($date)) {
			$date = date('Y-m-d', time() - 86400); 
		}
		
		$statistics = array(
			'date' => $date,
			'users' => null,
			'users_registered' => null,
			'users_verified' => null,
			'users_unverified' => null,
			'users_hellbanned' => null,
			'withdrawal_count' => null,
			'withdrawal_amount' => null,
			'transactions' => null,
			'surveys' => null,
			'offers' => null,
			'completes' => null,
			'nqs' => null,
			'clicks' => null,
			'oqs' => null		
		);
		
		$statistics['users'] = $this->User->find('count', array(
			'recursive' => -1,
			'conditions' => array(
				'User.created <=' => $date
			)
		));
		
		$statistics['users_registered'] = $this->User->find('count', array(
			'recursive' => -1,
			'conditions' => array(
				'User.created >=' => $date.' 00:00:00',
				'User.created <=' => $date.' 23:59:59',
			)
		));
		
		$statistics['users_verified'] = $this->User->find('count', array(
			'recursive' => -1,
			'conditions' => array(
				'User.verified >=' => $date.' 00:00:00',
				'User.verified <=' => $date.' 23:59:59',
			)
		));
		$statistics['users_unverified'] = $statistics['users_registered'] - $statistics['users_verified'];
		
		$statistics['users_hellbanned'] = $this->User->find('count', array(
			'recursive' => -1,
			'conditions' => array(
				'User.hellbanned_on >=' => $date.' 00:00:00',
				'User.hellbanned_on <=' => $date.' 23:59:59',
			)
		));
		
		$statistics['runners'] = $this->User->find('count', array(
			'conditions' => MintVineUser::user_level_date(USER_LEVEL_RUNNER, $date),
			'recursive' -1
		));
		$statistics['walkers'] = $this->User->find('count', array(
			'conditions' => MintVineUser::user_level_date(USER_LEVEL_WALKER, $date),
			'recursive' -1
		));
		$statistics['living'] = $this->User->find('count', array(
			'conditions' => MintVineUser::user_level_date(USER_LEVEL_LIVING, $date),
			'recursive' -1
		));
		$statistics['zombies'] = $this->User->find('count', array(
			'conditions' => MintVineUser::user_level_date(USER_LEVEL_ZOMBIE, $date),
			'recursive' -1
		));
		$statistics['dead'] = $this->User->find('count', array(
			'conditions' => MintVineUser::user_level_date(USER_LEVEL_DEAD, $date),
			'recursive' -1
		));
		
		$statistics['withdrawal_count'] = $this->Transaction->find('count', array(
			'recursive' => -1,
			'conditions' => array(
				'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
				'Transaction.created >=' => $date.' 00:00:00',
				'Transaction.created <=' => $date.' 23:59:59',
				'Transaction.deleted' => null,
			)
		));
		
		$amount = $this->Transaction->find('first', array(
			'recursive' => -1,
			'fields' => array('SUM(amount) as amount'),
			'conditions' => array(
				'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
				'Transaction.status' => TRANSACTION_APPROVED,
				'Transaction.created >=' => $date.' 00:00:00',
				'Transaction.created <=' => $date.' 23:59:59',
				'Transaction.deleted' => null,
			)
		));
		if ($amount) {
			$statistics['withdrawal_amount'] = $amount[0]['amount'] * -1 / 100;
		}
		
		$statistics['transactions'] = $this->Transaction->find('count', array(
			'recursive' => -1,
			'conditions' => array(
				'Transaction.created >=' => $date.' 00:00:00',
				'Transaction.created <=' => $date.' 23:59:59',
				'Transaction.deleted' => null,
			)
		));
		
		$statistics['surveys'] = $this->Project->find('count', array(
			'recursive' => -1,
			'conditions' => array(
				'Project.active' => true,
			)
		));
		
		$statistics['offers'] = $this->Offer->find('count', array(
			'recursive' => -1,
			'conditions' => array(
				'Offer.active' => '1',
			)
		));
				
		$old = $this->Statistic->findByDate($date);
		$this->Statistic->create();
		if ($old) {
			$statistics['id'] = $old['Statistic']['id'];
		}
		$this->Statistic->save(array('Statistic' => $statistics));
	}
}
