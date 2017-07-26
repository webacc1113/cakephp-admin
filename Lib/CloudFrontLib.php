<?php

class CloudFrontLib {
	public static function invalidate_cloudfront() {
		App::import('Model', 'Setting');
		$Setting = new Setting;
		$settings = $Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array('cloud.front.secret', 'cloud.front.key', 'cloud.front.distribution.id'),
				'Setting.deleted' => false
			),
			'fields' => array('name', 'value')
		));
		
		if (count($settings) < 3) {
			return;
		}
		
		App::import('Vendor', 'CloudFront', array('file' => 'CloudFront' . DS . 'CloudFront.php'));
		$cf = new CloudFront($settings['cloud.front.key'], $settings['cloud.front.secret'], $settings['cloud.front.distribution.id']);
		$cf->invalidate('*');
		return true;
	}
}