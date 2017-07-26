<?php

App::uses('Component', 'Controller');
class ReportDataComponent extends Component {
	
	function generateAgeData($users, $date_field = "created") {
		$count = count($users);
		$return = array(
			'count' => $count,
			'balance' => '0',
			'days' => '0',
			'breakdown' => array(
				'0' => 0,
				'31' => 0,
				'61' => 0,
				'91' => 0,
				'121' => 0,
				'151' => 0,
				'181' => 0,
				'211' => 0,
				'241' => 0,
				'271' => 0,
				'301' => 0,
				'331' => 0,
				'361' => 0,
			),
		);
		$time = time();
		foreach ($users as $user) {
			$timestamp = strtotime($user['User'][$date_field]);
			$diff = round(($time - $timestamp) / 86400);
			$return['days'] = $diff + $return['days'];			
			$return['balance'] = $return['balance'] + $user['User']['total'];
			foreach ($return['breakdown'] as $days => $value) {
				if ($diff > $days && $diff < $days + 30) {
					$return['breakdown'][$days]++;
				}
				elseif ($diff > 361) {
					$return['breakdown'][361]++;
				}
			}
		}
		
		if ($count > 0) {
			$return['days'] = round($return['days'] / $count);
			$return['balance'] = round($return['balance'] / $count);
		}
		return $return;
	}
}