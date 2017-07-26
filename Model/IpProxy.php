<?php
App::uses('AppModel', 'Model');

class IpProxy extends AppModel {
	public $actsAs = array('Containable');
	
	
	public function getProxyScore($ip_address, $proxy_service = 'maxmind') {
		
		App::import('Model', 'Setting');
		$this->Setting = new Setting; 

		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => 'maxmind.license',
				'Setting.deleted' => false
			)
		));
		
		$proxy = $this->findByIpAddress($ip_address);
		if ($proxy) {
			if ($proxy_service == 'maxmind' && !empty($proxy['IpProxy']['proxy_score'])) {
				return $proxy['IpProxy']['proxy_score'];
			}
			elseif ($proxy_service == 'ipintel' && !empty($proxy['IpProxy']['ipintel_proxy_score'])) {
				return $proxy['IpProxy']['ipintel_proxy_score'];
			}
		}
		
		$http = new HttpSocket(array(
			'timeout' => 2,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		try {
			if ($proxy_service == 'ipintel') {
				$results = $http->get('http://royka62sz7uswbsv7nke88fzkf.getipintel.net/check.php?flags=b&ip=' . $ip_address . '&contact=royikim@gmail.com');
			}
			else {
				$results = $http->get('https://minfraud.maxmind.com/app/ipauth_http?l='.$settings['maxmind.license'].'&i=' . $ip_address);
			}
	    }
		catch (Exception $e) {
			return false;
		}
		
		if ($proxy_service == 'ipintel') {
			if ($results->code == 200) {
				$ip_proxy = array('IpProxy' => array(
					'ip_address' => $ip_address, 
					'ipintel_proxy_score' => $results->body
				));
				if ($proxy) {
					$ip_proxy['IpProxy']['id'] = $proxy['IpProxy']['id'];
				}
				
				$this->create();
				$this->save($ip_proxy);
				return $results->body;
			}
			else {
				CakeLog::write('proxy', '[Error] Proxy service: '.$proxy_service. ', IP address: '.$ip_address. ', Error code:'. $results->code. ', Value: '. $results->body);
			}
		}
		else {
			preg_match("/proxyScore=([0-9\.]+)/", $results->body, $match);	
			if (isset($match[1]) && !empty($match[1])) {
				$proxyScore = floatval($match[1]);
			}
			else {
				CakeLog::write('proxy', '[Error] Proxy service: '.$proxy_service. ', IP address: '.$ip_address. ', Value: '. $results->body);
			}
			
			if (!is_null($proxyScore)) {
				$ip_proxy = array('IpProxy' => array(
					'ip_address' => $ip_address, 
					'proxy_score' => $proxyScore
				));
				if ($proxy) {
					$ip_proxy['IpProxy']['id'] = $proxy['IpProxy']['id'];
				}
				
				$this->create();
				$this->save($ip_proxy);
				return $proxyScore;
			}
		}
		
		return false;
	}
}
