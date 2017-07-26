<?php
App::uses('AppModel', 'Model');

class Site extends AppModel {
	var $name = 'Site';
	
	
	function get_quickbook_status() {
		$site = $this->find('first', array(
			'conditions' => array(
				'Site.path_name' => QUICKBOOK_API_PATH_NAME
			)
		));
		if ($site && !empty($site['Site']['oauth_tokens'])) {
			$token_modified_date = $site['Site']['modified'];
			$current_date = date('Y-m-d');
			$current_date = new DateTime($current_date);
			$token_modified_date = new DateTime($token_modified_date);
			$interval = $token_modified_date->diff($current_date);
			$days_diff = $interval->format('%a');
			if ($days_diff > 150 && $days_diff < 180 ) {
				return QUICKBOOK_OAUTH_EXPIRING_SOON;
			}
			elseif($days_diff >= 180) {
				return QUICKBOOK_OAUTH_EXPIRED;
			}
			else {
				return QUICKBOOK_OAUTH_CONNECTED;
			}
		}
		else {
			return QUICKBOOK_OAUTH_NOT_CONNECTED;
		}
	}
}