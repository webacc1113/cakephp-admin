<?php

class MintVineUser {
	public static function hellban($user_id, $reason = null, $admin_id = null) {
		$models = array('User', 'Transaction', 'HellbanLog', 'QueryProfile');
		foreach ($models as $model) {
			App::import('Model', $model);
			$$model = new $model;
		}
		
		$User->create();
		$User->save(array('User' => array(
			'id' => $user_id,
			'hellbanned' => '1', 
			'hellbanned_on' => date(DB_DATETIME),
			'hellban_reason' => trim(str_replace('auto:', '', $reason)),
			'hellban_score' => null,
			'checked' => '0'
		)), true, array('hellbanned', 'hellbanned_on', 'hellban_reason', 'hellban_score', 'checked'));
		
		$query_profile = $QueryProfile->find('first', array(
			'conditions' => array(
				'QueryProfile.user_id' => $user_id
			),
			'recursive' => -1,
			'fields' => array('id')
		));
		if ($query_profile) {
			$QueryProfile->create();
			$QueryProfile->save(array('QueryProfile' => array(
				'id' => $query_profile['QueryProfile']['id'],
				'ignore' => true
			)), true, array('ignore'));
		}
		
		// reject pending withdrawals
		$transactions = $Transaction->find('all', array(
			'conditions' => array(
				'Transaction.user_id' => $user_id,
				'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
				'Transaction.status' => TRANSACTION_PENDING,
				'Transaction.deleted' => null,
			)
		));
		if ($transactions) {
			foreach ($transactions as $transaction) {
				$Transaction->reject($transaction); 
			}
		}
		
		// log this information
		if (substr($reason, 0, 5) == 'auto:') {
			$automated = true;
			$reason = trim(str_replace('auto:', '', $reason));
		}
		else {
			$automated = false;
		}
		$HellbanLog->create();
		$HellbanLog->save(array('HellbanLog' => array(
			'user_id' => $user_id,
			'admin_id' => $admin_id,
			'type' => 'hellban',
			'automated' => $automated,
			'reason' => $reason
		)));
	}
	
	public static function user_level($date) {
		if (empty($date)) {
			return USER_LEVEL_DEAD;
		}
		
		$date = strtotime($date);
		if ($date > strtotime('-1 day')) {
			return USER_LEVEL_RUNNER;
		}
		
		if ($date > strtotime('-7 days') && $date < strtotime('-1 day')) {
			return USER_LEVEL_WALKER;
		}
		
		if ($date > strtotime('-30 days') && $date < strtotime('-7 days')) {
			return USER_LEVEL_LIVING;
		}
		
		if ($date > strtotime('-6 months') && $date < strtotime('-30 days')) {
			return USER_LEVEL_ZOMBIE;
		}
		
		if ($date < strtotime('-6 months')) {
			return USER_LEVEL_DEAD;
		}
		
		return false;
	}
	
	public static function user_level_date($level, $date = '') {
		if ($date) {
			$date = $date.' ';
		}
		
		$return = array('User.last_touched > ' => date(DB_DATETIME, strtotime($date . '-1 day')));
		switch ($level) {
			case USER_LEVEL_RUNNER:
				$return = array('User.last_touched > ' => date(DB_DATETIME, strtotime($date . '-1 day')));
			break;
			case USER_LEVEL_WALKER:
				$return = array(
					'User.last_touched > ' => date(DB_DATETIME, strtotime($date . '-7 days')),
					'User.last_touched < ' => date(DB_DATETIME, strtotime($date . '-1 day')),
				);
			break;
			case USER_LEVEL_LIVING:
				$return = array(
					'User.last_touched > ' => date(DB_DATETIME, strtotime($date . '-30 days')),
					'User.last_touched < ' => date(DB_DATETIME, strtotime($date . '-7 days')),
				);
			break;
			case USER_LEVEL_ZOMBIE:
				$return = array(
					'User.last_touched > ' => date(DB_DATETIME, strtotime($date . '-6 months')),
					'User.last_touched < ' => date(DB_DATETIME, strtotime($date . '-30 days')),
				);
			break;
			case USER_LEVEL_DEAD:
				$return = array(
					'OR' => array(
						'User.last_touched < ' => date(DB_DATETIME, strtotime($date . '-6 months')),
						'User.last_touched is null'
					)
				);
			break;
		}
		
		return $return;
	}
	
	public static function user_level_count($users) {
		$return = array(
			'runners' => 0,
			'walkers' => 0,
			'living' => 0,
			'zombies' => 0,
			'dead' => 0
		);
		foreach ($users as $user) {
			switch (self::user_level($user['User']['last_touched'])) {
				case USER_LEVEL_RUNNER:
					$return['runners']++;
				break;
				case USER_LEVEL_WALKER:
					$return['walkers']++;
				break;
				case USER_LEVEL_LIVING:
					$return['living']++;
				break;
				case USER_LEVEL_ZOMBIE:
					$return['zombies']++;
				break;
				case USER_LEVEL_DEAD:
					$return['dead']++;
				break;
			}
		}
		
		return $return;
	}
}