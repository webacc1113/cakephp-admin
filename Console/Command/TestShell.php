<?php
App::uses('CakeEmail', 'Network/Email');
App::uses('View', 'View');
App::import('Lib', 'Utilities');
App::import('Lib', 'QueryEngine');
App::uses('CakeResponse', 'Network');
App::uses('HttpSocket', 'Network/Http');

class TestShell extends AppShell {
	public $uses = array(
		'CintLog', 
		'CintSurvey', 
		'FedSurvey', 
		'Group', 
		'Query', 
		'QueryProfile', 
		'IpProxy', 
		'LucidQueue', 
		'Project', 
		'ProjectOption', 
		'ProjectLog', 
		'Setting', 
		'SurveyCountry', 
		'SurveyUser', 
		'SurveyUserVisit', 
		'SurveyLink', 
		'SurveyVisit', 
		'SurveyVisitCache', 
		'Transaction', 
		'TransactionReport', 
		'User', 
		'UserAnalytic', 
		'UserAcquisition', 
		'UserIp', 
		'UserLog', 
		'UserRouterLog', 
		'Withdrawal', 
		'WithdrawalStatusLog'
	);
	
	function main() { 
	}	
	
	function gender_inspect() {
		$genders = explode("\n", file_get_contents(WWW_ROOT . 'files/gender.txt'));
		if (!empty($genders)) {
			$users = $this->User->find('all', array(
				'fields' => array('User.id', 'User.last_touched', 'User.created', 'QueryProfile.country', 'QueryProfile.gender'),
				'conditions' => array(
					'User.id' => $genders,
					'QueryProfile.country' => 'US',
					'QueryProfile.gender is not null'
				)
			));
			print_r($users);

			$this->out(count($users));
		} 
	}
	
	function s3() {
		CakePlugin::load('Uploader');
		App::import('Vendor', 'Uploader.S3');
		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => array(
					's3.access',
					's3.secret',
					's3.bucket',
					's3.host'
				),
				'Setting.deleted' => false
			)
		));
		$filename = 'cint.response.txt';
		$file = WWW_ROOT.'files/'.$filename; 
		$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);	
		$headers = array(
			'Content-Disposition' => 'attachment; filename='.$filename
		);
		print_r($settings);
		$response = $S3->putObject($S3->inputFile($file), $settings['s3.bucket'] , $filename, S3::ACL_PRIVATE, array(), $headers); 
		print_r($response);
	}
	
	function verified_timestamp() {
		$users = $this->User->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'User.created >' => '2015-11-21 00:00:00'
			),
			'fields' => array('User.id', 'User.created', 'User.active')
		));
		$unverified_count = 0;
		$timestamps = array();
		echo 'TOTAL: '.count($users)."\n";
		foreach ($users as $user) {
			if ($user['User']['active']) {
				$user_log = $this->UserLog->find('first', array(
					'conditions' => array(
						'UserLog.user_id' => $user['User']['id'],
						'UserLog.type' => 'user.activated',
					),
					'fields' => array('created')
				));
				if ($user_log) {
					$diff = strtotime($user_log['UserLog']['created']) - strtotime($user['User']['created']);
					$timestamps[] = $diff;
				}
			}
			else {
				$unverified_count++;
			}
		}
		echo 'Unverified count: '.$unverified_count."\n";
		$average = array_sum($timestamps) / count($timestamps); 
		echo 'Average: '.$average."\n";
		
		// 
		sort($timestamps);
		$count = count($timestamps); //total numbers in array
		$middleval = floor(($count-1)/2); // find the middle value, or the lowest middle value
		if($count % 2) { // odd number, middle is the median
			$median = $timestamps[$middleval];
		} 
		else { // even number, calculate avg of 2 medians
			$low = $timestamps[$middleval];
			$high = $timestamps[$middleval+1];
			$median = (($low+$high)/2);
		}
		echo 'Median: '.$median;
		
		echo implode("\n", $timestamps); 
	}
	
	function points2shop() {
		$this->User->bindModel(array('hasOne' => array('QueryProfile')));
		$user = $this->User->findById($this->args[0]);
		$setting = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'points2shop.secret',
				'Setting.deleted' => false
			)
		));
		$params = array(
			'user_id'=> $user['User']['id'],
			'date_of_birth'=> $user['QueryProfile']['birthdate'],
			'email'=> $user['User']['email'],
			'gender'=> strtolower($user['QueryProfile']['gender']),
			'zip'=> $user['QueryProfile']['postal_code'],
			'ip_address'=> $this->args[1],
			'basic' => 1
		);
		App::uses('HttpSocket', 'Network/Http');
		$http = new HttpSocket(array(
			'timeout' => 2,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$options = array('header' => array(
			'X-YourSurveys-Api-Key' => $setting['Setting']['value']
		));
		$time_start = microtime(true);
		try {
			$response = $http->get('https://www.your-surveys.com/suppliers_api/surveys/user', $params, $options);
		} catch (Exception $e) {
			return false;
		}
		print_r($response);
	}
	
	function user_agent() {
		$settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => 'useragent.key',
				'Setting.deleted' => false
			),
			'fields' => array('Setting.name', 'Setting.value')
		));
		$string = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2490.80 Safari/537.36';
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		)); 
		$results = $http->get('http://useragentapi.com/api/v2/json/'.$settings['useragent.key'].'/'.urlencode($string));
		$data = json_decode($results->body, false);
		print_r($data);
	}
	
	function timeout() {
		$CakeResponse = new CakeResponse;
		$CakeResponse->httpCodes(array(
			381 => 'Unicorn Moved',
			555 => 'Unexpected Minotaur'
		));
		$http = new HttpSocket(array(
			'timeout' => 1,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$options = array('header' => array(
			'Accept' => 'application/json',
			'Content-Type' => 'application/json; charset=UTF-8'
		));
		
		
		try {
			$results = $http->get('http://www.google.com:81', array(), $options);	
			print_r($results);
		} catch (Exception $e) {
			print_r($e);
		}
		
	}
	function fed_date() {
		preg_match('/(\d{10})(\d{3})([\+\-]\d{4})/', $this->args[0], $matches);
		$dt = DateTime::createFromFormat("U.u.O", vsprintf('%2$s.%3$s.%4$s', $matches));
		echo $dt->format('r');
	}
	
	function find_large_link_studies() {
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.status' => PROJECT_STATUS_OPEN,
				'Project.sqs is null'
			),
			'fields' => array('Project.id'),
			'recursive' => -1
		));
		foreach ($projects as $project) {
			
			$this->SurveyLink->getDatasource()->reconnect();
			$survey_links = $this->SurveyLink->find('count', array(
				'conditions' => array(
					'SurveyLink.survey_id' => $project['Project']['id'],
					'SurveyLink.user_id is null',
				)
			));
			if (!empty($survey_links) && $survey_links > 1000) {
				echo $project['Project']['id'].' '.$survey_links."\n";
				exec('/var/www/html/cp.mintvine.com/web/app/Console/cake survey_links sync_to_sqs '.$project['Project']['id']); 
			}
		}
	}
	function cint_statistics() {
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.group_id' => 5
			),
			'fields' => array('Project.id', 'Project.prj_name', 'Project.active', 'Project.date_created'),
		));
		
		$project_option = $this->ProjectOption->find('first', array(
			'fields' => array('id', 'value'),
			'conditions' => array(
				'project_id' => 0,
				'name' => 'cint_last_event_id'
			)
		));
		$fp = fopen('cint_statistics_'.$project_option['ProjectOption']['value'].'.csv', 'w');
		
		fputcsv($fp, array(
			'MintVine Project ID', 
			'Cint Project Name', 
			'Created', 
			'Total Invites',
			'Total Invites (Active Users)',
			'Clicks',
			'Completes',
			'NQ',
			'OQ'
		));
		foreach ($projects as $project) {
			$statistics = $this->SurveyVisitCache->find('first', array(
				'conditions' => array(
					'SurveyVisitCache.survey_id' => $project['Project']['id']
				)
			));
			$user_ids = $this->SurveyUser->find('list', array(
				'fields' => array('id', 'user_id'),
				'conditions' => array(
					'SurveyUser.survey_id' => $project['Project']['id']
				)
			));
			$users_count = $this->User->find('count', array(
				'conditions' => array(
					'User.id' => $user_ids,
					'User.hellbanned' => false,
					'User.last_touched >=' => date(DB_DATETIME, strtotime('-1 month'))
				)
			));
			fputcsv($fp, array(
				$project['Project']['id'],
				$project['Project']['prj_name'],
				$project['Project']['date_created'],
				count($user_ids),
				$users_count,
				$statistics['SurveyVisitCache']['click'],
				$statistics['SurveyVisitCache']['complete'],
				$statistics['SurveyVisitCache']['nq'],
				$statistics['SurveyVisitCache']['overquota']
			));
		}
		fclose($fp);
	}
	
	

	// arg: email type
	// Available names are:
	// 		survey
	//		survey-funnel
	//		survey-router-project
	//		payout
	function test_email() {
		if (!$this->args[0]) {
			$this->out('Please enter email type name');
			return;
		}

		$unsubscribe_link = HOSTNAME_WWW.'/users/emails/';
		$nonce = substr(Utils::rand(10), 0, 10);
		$vars = array(
			'unsubscribe_link' => $unsubscribe_link,
			'survey_url' => 'http://www.test.com',
			'nonce' => $nonce,
			'survey_award' => 50,
			'survey_length' => 7,
			'is_desktop' => 1,
			'is_mobile' => 1,
			'is_tablet' => 1
		);

		$email_types = array(
			'survey' => array(
				'subject' => 'Survey for you',
				'template' => 'survey',
				'vars' => $vars
			),
			'survey-funnel' => array(
				'subject' => 'Funnel Survey',
				'template' => 'survey-funnel',
				'vars' => $vars
			),
			'survey-router-project' => array(
				'subject' => 'Survey router project',
				'template' => 'survey-router-project',
				'vars' => $vars
			),
			'payout' => array(
				'subject' => 'Withdrawl',
				'template' => 'payout',
				'vars' => array(
					'unsubscribe_link' => $unsubscribe_link,
					'user_name' => 'Username',
					'amount' => 100,
					'payment_method' => 'paypal',
				)
			)
		);

		if (!in_array($this->args[0], array_keys($email_types))) {
			$this->out('Template not found');
			return;
		}

		$type= $this->args[0];

		CakePlugin::load('Mailgun');
		$email = new CakeEmail();
		$email->config('mailgun');
		$email->from(array(EMAIL_SENDER => 'MintVine'));
		$email->replyTo(array(REPLYTO_EMAIL => 'MintVine'));
		$email->template($email_types[$type]['template']);
		$email->emailFormat('html');
		$email->viewVars($email_types[$type]['vars']);
		$email->to(unserialize(DEV_EMAIL))
			->subject($email_types[$type]['subject']);

		// Output the rendered email template into log
		$view = new View();
		$view->layout = 'Emails/html/default';
		$view->viewPath = 'Emails/html';
		$view->set($email_types[$type]['vars']);
		$this->out($view->render($email_types[$type]['template']));

		if ($email->send()) {
			$this->out('Test email sent');
		}
	}
	
	public function mixpanel_verify() {
		echo 'Matching in MV: '.Utils::change_tz_from_utc('2014-11-08 00:00:00', 'Y-m-d H:i:s').' to '.Utils::change_tz_from_utc('2014-11-08 23:59:59', 'Y-m-d H:i:s')."\n";
		$file = WWW_ROOT.'files/segment.csv';
		$csv_datas = explode("\n", file_get_contents($file));
		
		$ids = array();
		$final_ids = array();
		foreach ($csv_datas as $key => $rows) {
			$rows = explode(',', $rows);
			if ($key == 0) {
				unset($rows[0]);
				$ids = $rows; 
				continue;
			}
			if (in_array($rows[0], array('2014-11-08'))) {
				unset($rows[0]);
				foreach ($rows as $key => $val) {
					if ($val > 0 && $ids[$key] != 'undefined') {
						$final_ids[] = $ids[$key]; 
					}
				}
			}
		}
		echo 'all ids: '.count($ids)."\n";
		echo 'from csv: '.count($final_ids)."\n";
		
		$user_acquisitions = $this->UserAcquisition->find('all', array(
			'conditions' => array(
				'UserAcquisition.modified >=' => Utils::change_tz_from_utc('2014-11-08 00:00:00', 'Y-m-d H:i:s'),
				'UserAcquisition.modified <=' => Utils::change_tz_from_utc('2014-11-08 23:59:59', 'Y-m-d H:i:s'),
				'UserAcquisition.source' => array('fb_lander_2_1:pt5', 'fb_lander_2_1:pt6'),
				'UserAcquisition.pixel_fired' => true,
				'UserAcquisition.user_id >' => '0'
			)
		));
		$list_acquisition_ids = array();
		$matched_acquisition_ids = $unmatched_acquisition_ids = array();
		foreach ($user_acquisitions as $user_acquisition) {
			$list_acquisition_ids[$user_acquisition['UserAcquisition']['id']] = $user_acquisition['UserAcquisition']['user_id'];
			if (in_array($user_acquisition['UserAcquisition']['id'], $ids)) {
				$matched_acquisition_ids[$user_acquisition['UserAcquisition']['id']] = $user_acquisition['UserAcquisition']['user_id'];
			}
			else {
				$unmatched_acquisition_ids[$user_acquisition['UserAcquisition']['id']] = $user_acquisition['UserAcquisition']['user_id'];
			}
		}
		echo 'internal mv acquisitions count: '.count($user_acquisitions)."\n";
		echo 'matched mv acquisitions count: '.count($matched_acquisition_ids)."\n";
		echo 'unmatched mv acquisitions count: '.count($unmatched_acquisition_ids)."\n";
		
		echo 'Unmatched in Mixpanel:'."\n";
		$fp = fopen(WWW_ROOT.'files/segment_missing.csv', 'w');
		fputcsv($fp, array(
			'AID',
			'UID',
			'IP Address',
			'Acquisition Started (GMT)',
			'Acquisition Last Touch (GMT)',
			'User Record Created (GMT)',
			'User Record Verified (GMT)',			
		));
		foreach ($unmatched_acquisition_ids as $acquisition_id => $user_id) {
			$this->UserAcquisition->bindModel(array('belongsTo' => array('User')));
			$user_acquisition = $this->UserAcquisition->find('first', array(
				'fields' => array('*'),
				'conditions' => array(
					'UserAcquisition.id' => $acquisition_id
				)
			));
			fputcsv($fp, array(
				$user_acquisition['UserAcquisition']['id'],
				$user_acquisition['UserAcquisition']['user_id'],
				$user_acquisition['UserAcquisition']['ip'],
				$user_acquisition['UserAcquisition']['created'],
				$user_acquisition['UserAcquisition']['modified'],
				$user_acquisition['User']['created'],
				$user_acquisition['User']['verified']
			));
			echo $user_acquisition['UserAcquisition']['id']."\t"
				.$user_acquisition['UserAcquisition']['user_id']."\t"
				.$user_acquisition['UserAcquisition']['ip']."\t"
				.(empty($user_acquisition['User']['fb_id']) ? '            ': $user_acquisition['User']['fb_id'])."\t"
				.$user_acquisition['UserAcquisition']['created']."\t"
				.$user_acquisition['UserAcquisition']['modified']."\t"
				.$user_acquisition['User']['created']."\t"
				.$user_acquisition['User']['verified']."\n";				
		}
		fclose($fp);
		
		/*
		echo 'Unmatched from MixPanel:'."\n";
		foreach ($final_ids as $final_id) {
			if (!array_key_exists($final_id, $list_acquisition_ids)) {
				echo $final_id."\n";
			}
		}
		echo "------\n";
		*/
		$users = $this->User->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'User.origin' => array('fb_lander_2_1:pt5', 'fb_lander_2_1:pt6'),
				'User.verified >=' => Utils::change_tz_from_utc('2014-11-08 00:00:00', 'Y-m-d H:i:s'),
				'User.verified <=' => Utils::change_tz_from_utc('2014-11-08 23:59:59', 'Y-m-d H:i:s')
			)
		));
		$matched_user_ids = array();
		foreach ($users as $user) {
			
		}
		echo 'total users count: '.count($users)."\n";
		
		/* user acquisition gets written afterCreate(); 
		   user.verified should be roughly close to the fire time
		*/
	}
	
	// arg: old oauth_token of some existing user
	function dwolla_tokens() {
		if (!$this->args[0]) {
			echo 'Please enter dwolla oauth_token';
			return;
		}
		
		App::import('Vendor', 'autoload', array(
			'file' => 'DwollaSDK' . DS . 'autoload.php'
		));
		$OAuth = new Dwolla\OAuth();
		$OAuth->settings->sandbox = true;
		$OAuth->settings->debug = true;
		$response = $OAuth->get($this->args[0]);
		CakeLog::write('dwolla-tokens', 'Dwolla tokens for  : ' . $this->args[0] . "\n" . print_r($response, true));
	}
	
	function dwolla_send() {
		if (!$this->args[0]) {
			echo 'Please enter dwolla oauth_token';
			return;
		}
		
		App::import('Vendor', 'autoload', array(
			'file' => 'DwollaSDK' . DS . 'autoload.php'
		));
		$Transactions = new Dwolla\Transactions();
		$Transactions->settings->sandbox = true;
		$Transactions->settings->oauth_token = $this->args[0];

		$result = $Transactions->send('812-742-2808', 5.50);
		CakeLog::write('dwolla-send', 'Dwolla Send  : ' . $this->args[0] . "\n" . print_r($result, true));
	}
	
	public function process_tangocard() {
		$models_to_import = array('PaymentMethod', 'Transaction', 'User', 'CashNotification', 'PaymentLog');
		foreach ($models_to_import as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}

		$i = 0;
		// Load PaymentComponent 
		App::uses('ComponentCollection', 'Controller');
		App::uses('Controller', 'Controller');
		App::uses('PaymentComponent', 'Controller/Component');
		$collection = new ComponentCollection();
		$this->Payment = new PaymentComponent($collection);
		$controller = new Controller();
		$this->Payment->initialize($controller);
		
		if (!isset($this->args[0])) {
			echo 'Transaction id is required as first argument.' . "\n";
			return;
		}
		
		$transaction = $this->Transaction->find('first', array(
			'conditions' => array(
				'Transaction.id' => $this->args[0],
				'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
				'Transaction.status' => TRANSACTION_APPROVED,
				'Transaction.payout_processed' => false,
				'Transaction.deleted' => null,
			),
			'recursive' => -1
		));
		
		if (!$transaction) {
			echo 'Approved, unprocessed withdrawal transaction not found.'. "\n";
			return;
		}
		
		$payment_method = $this->PaymentMethod->find('first', array(
			'conditions' => array(
				'id' => $transaction['Transaction']['linked_to_id']
			)
		));
		
		if ($this->Payment->tangocard_payout($transaction, $payment_method['PaymentMethod']['payment_id'])) {
			echo "Tango card has been successfully processed. To verify check your account balance please.". "\n";
		}
	}
	
	public function api_survey_status() {
		if (!isset($this->args[0]) || empty($this->args[0])) {
			return;
		}
		
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('hostname.api', 'api.mintvine.username', 'api.mintvine.password'),
				'Setting.deleted' => false
			)
		));
		App::uses('HttpSocket', 'Network/Http');
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$http->configAuth('Basic', $settings['api.mintvine.username'], $settings['api.mintvine.password']);
		$results = $http->post($settings['hostname.api'].'/surveys/test_survey_status/'.$this->args[0]);
		$results = json_decode($results['body'], true);
		print_r($results);
	}
	
	public function qe() {
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('hostname.qe', 'qe.mintvine.username', 'qe.mintvine.password'),
				'Setting.deleted' => false
			)
		));
		$options = array(
			'qualifications' => array(
				42 => array(30, 31)
			),
			'partner' => 'lucid'
		);
		App::uses('HttpSocket', 'Network/Http');
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$http->configAuth('Basic', $settings['qe.mintvine.username'], $settings['qe.mintvine.password']);
		$results = $http->post($settings['hostname.qe'].'/query', json_encode($options), array(
			'header' => array('Content-Type' => 'application/json')
		));
		$results = json_decode($results['body'], true);
		print_r($results);
	}
	
	public function lucid_encrypt() {
		//$hash = hash_hmac('sha1', 'http://www.samplicio.us/router/default.aspx?SID=12345dca-a691-4496-a2fa-12345b99ec29&PID=1234&', '1234567890ABC', true);
		$hash = hash_hmac('sha1', 'https://www.abc.com/ex.aspx?abc=def&vid=123&', '1234567890ABC', true);
		$token = base64_encode($hash);
		$token = str_replace(array('+', '/', '='), array('-', '_', ''), $token);
		echo $token;
	}

	public function tab_only_users() {
		$d0 = new DateTime($this->args[0].' 00:00:00');
		$d1 = new DateTime($this->args[0].' 00:00:00');
		$d1->add(new DateInterval('P1D'));

		$users = $this->SurveyUserVisit->find('list', array(
			'fields' => array('user_id'),
			'conditions' => array(
				'created >=' => $d0->format('Y-m-d H:i:s'),
				'created <' => $d1->format('Y-m-d H:i:s'),
				'accessed_from' => 'tab'
			)
		));
		
		$stats['total_users'] = 0;
		$stats['no_router'] = 0;
		
		foreach ($users as $user) {
			$access = $this->SurveyUserVisit->find('list', array(
				'fields' => array('id'),
				'conditions' => array(
					'user_id' => $user,
					'created >=' => $d0->format('Y-m-d H:i:s'),
					'created <' => $d1->format('Y-m-d H:i:s'),
					'accessed_from' => 'router.entry'
				)
			));

			if (count($access) == 0) {
				// No Router Entry for User
				$stats['no_router']++;
			}
			$stats['total_users']++;
		}

		$hits = $this->SurveyUserVisit->find('list', array(
			'fields' => array('user_id', 'accessed_from'),
			'conditions' => array(
				'created >' => $d0->format('Y-m-d H:i:s'),
				'created <' => $d1->format('Y-m-d H:i:s'),
				'accessed_from' => 'router.entry'
			)
		));
		
		$hits = count($hits);
		
		$pct = $stats['no_router'] / $stats['total_users'];
		$pct = round((float)$pct*100).'%';
		$pot = $stats['no_router'] / $hits;
		$pot = round((float)$pot*100).'%';
		echo 'Date Range: '.$d0->format('Y-m-d H:i:s').' -> '.$d1->format('Y-m-d H:i:s')."\r\n";
		echo '# of Tab Hits: '.$stats['total_users']."\r\n";
		echo '# of Tab Hits with no user router.entry on same range: '.$stats['no_router'].' -> '.$pct."\r\n";
		echo '# of Router entries: '.$hits.' Potential Increase: '.$pot."\r\n";
	}
	
	public function nqs_from_withdrawals() {
		$withdrawals = $this->Transaction->find('all', array(
			'fields' => array('Transaction.id', 'Transaction.user_id', 'Transaction.executed', 'Transaction.user_id', 'Transaction.amount'),
			'conditions' => array(
				'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
				'Transaction.status' => TRANSACTION_PENDING,
				'Transaction.deleted' => null,
			),
			'recursive' => -1
		));
		
		$totals = array(
			'withdrawal_values' => 0,
			'revenue_generating_points' => 0,
			'non_revenue_generating_points' => 0,
			'nq_points' => 0,
		);
		foreach ($withdrawals as $withdrawal) {
			$total_withdrawal_amount = abs($withdrawal['Transaction']['amount']);
			
			// grab all transactions less than the execution date that are approved
			$transactions = $this->Transaction->find('all', array(
				'recursive' => -1,
				'fields' => array('Transaction.id', 'Transaction.type_id', 'Transaction.status'),
				'conditions' => array(
					'Transaction.status' => TRANSACTION_APPROVED,
					'Transaction.user_id' => $withdrawal['Transaction']['user_id'],
					'Transaction.created <' => $withdrawal['Transaction']['executed'],
					'Transaction.deleted' => null,
				),
				'fields' => array('Transaction.type_id', 'Transaction.amount'),
				'order' => 'Transaction.id DESC'
			));
			$sum_so_far = 0;
			$revenue_generating_points = 0;
			$non_revenue_generating_points = 0;
			$nq_points = 0; 
			
			foreach ($transactions as $transaction) {
				if ($transaction['Transaction']['type_id'] == TRANSACTION_WITHDRAWAL) {
					continue;
				}
				$sum_so_far = $sum_so_far + $transaction['Transaction']['amount'];
				
				if (in_array($transaction['Transaction']['type_id'], array(TRANSACTION_OFFER, TRANSACTION_GROUPON, TRANSACTION_SURVEY))) {
					$revenue_generating_points = $revenue_generating_points + $transaction['Transaction']['amount'];
				}
				else {
					$non_revenue_generating_points = $non_revenue_generating_points + $transaction['Transaction']['amount'];
					if ($transaction['Transaction']['type_id'] == TRANSACTION_SURVEY_NQ) {
						$nq_points = $nq_points + $transaction['Transaction']['amount'];
					}
				}
				
				if ($sum_so_far > $total_withdrawal_amount) {
					// analysis complete
					break;
				}
			}
			$totals['withdrawal_values'] += $total_withdrawal_amount; 
			$totals['revenue_generating_points'] += $revenue_generating_points; 
			$totals['non_revenue_generating_points'] += $non_revenue_generating_points; 
			$totals['nq_points'] += $nq_points; 
			
			$this->out('Withdrawal #'.$withdrawal['Transaction']['id'].' (#'.$withdrawal['Transaction']['user_id'].') ('.$total_withdrawal_amount.') has '.$revenue_generating_points.' revenue points; '
				.$non_revenue_generating_points.' non-revenue points; '.$nq_points.' nq points');
		}
		$this->out('Withdrawals count: '.count($withdrawals));
		$this->out('Total withdrawal values: '.$totals['withdrawal_values']); 
		$this->out('Total revenue generating points: '.$totals['revenue_generating_points']); 
		$this->out('Total non-revenue generating points: '.$totals['non_revenue_generating_points']); 
		$this->out('Total nq points: '.$totals['nq_points']); 
	}
	
	public function single_metric_calc() {
		if (!isset($this->args[0]) || empty($this->args[0])) {
			$this->out('Country on ARG0 not defined, defaulting to USA');
			$country = 'US';
		}
		else {
			$country = $this->args[0];
		}

		if (!isset($this->args[1]) || empty($this->args[1])) {
			$this->out('Specify date in form "YYYY-MM-DD" for ARG1');
			return FALSE;
		}
		else {
			$date = $this->args[1];
		}
		
		$all_available_cint_project_ids = array();
		$all_available_cint_quota_ids = array();

		$totals = array();
		$day_total = array();
		
		$total_periods = 24;
		$hourly_periods = 20;

		// Parse cint logs to get available revenue and quotas
		$a = 0;
		while ($a < $total_periods) {
			$b = 0;
			$minutes = 0;
			while ($b < $hourly_periods) {
				$start = date(DB_DATETIME, strtotime($date.' 00:00:00 +'.$a.' hours'));
				$start = date(DB_DATETIME, strtotime($start.' +'.$minutes.' minutes'));

				$minutes = $minutes + ((1 / $hourly_periods) * 60);
				$stop = date(DB_DATETIME, strtotime($date.' 00:00:00 +'.$a.' hours'));
				$stop = date(DB_DATETIME, strtotime($stop.' +'.$minutes.' minutes'));

				$cint_log = $this->CintLog->find('first', array(
					'conditions' => array(
						'CintLog.country' => $country,
						'CintLog.raw !=' => NULL,
						'CintLog.parent_id' => '0', 
						'CintLog.created >=' => $start,
						'CintLog.created <=' => $stop
					)
				));

				$cint_quotas = json_decode($cint_log['CintLog']['raw'], true);
				if (!empty($cint_quotas)) {
					$temp_available_project_ids = array();
					$temp_available_quota_ids = array();
					foreach ($cint_quotas as $quota) {
						if (!in_array($quota['project_id'], $all_available_cint_project_ids)) {
							$all_available_cint_project_ids[] = $quota['project_id'];
						}			

						if (!in_array($quota['id'], $all_available_cint_quota_ids)) {
							$all_available_cint_quota_ids[] = $quota['id'];
						}			
						
						if ($quota['statistics']['length_of_interview'] > 21) {
							continue;
						}

						if ($quota['fulfillment']['estimated_remaining_completes'] < 3) {
							continue;
						}

						if ($quota['statistics']['conversion_rate'] < 7) {
							continue;
						}
													
						$payout = round($quota['pricing']['indicative_cpi'] * 0.4, 2); 

						if (empty($totals[$a][$b]['available_quota'])) {
							$totals[$a][$b]['available_quota'] = $quota['fulfillment']['estimated_remaining_completes']; 
						}
						else {
							$totals[$a][$b]['available_quota'] = $totals[$a][$b]['available_quota'] + $quota['fulfillment']['estimated_remaining_completes'];
						}

						if (empty($totals[$a][$b]['available_revenue'])) {
							$totals[$a][$b]['available_revenue'] = ($payout * ($quota['fulfillment']['estimated_remaining_completes'] * ($quota['statistics']['conversion_rate'] / 100))); 
						}
						else {
							$totals[$a][$b]['available_revenue'] = $totals[$a][$b]['available_revenue'] + ($payout * ($quota['fulfillment']['estimated_remaining_completes'] * ($quota['statistics']['conversion_rate'] / 100)));
						}

						if (!in_array($quota['project_id'], $temp_available_project_ids)) {
							$temp_available_project_ids[] = $quota['project_id'];
						}			

						if (!in_array($quota['id'], $temp_available_quota_ids)) {
							$temp_available_quota_ids[] = $quota['id'];
						}			
					}

					if (count($temp_available_quota_ids) > 0) {
						$totals[$a][$b]['available_quotas'] = count($temp_available_quota_ids);
					}
					
					if (count($temp_available_project_ids) > 0) {
						$totals[$a][$b]['available_projects'] = count($temp_available_project_ids);
					}
				}
				$b++;
			}
			$a++;
		}
		// End get available data

		ksort($totals);

		$this->out('Unique Projects: '.count($all_available_cint_project_ids).' Unique Quotas: '.count($all_available_cint_quota_ids));

		// Boil down partner hourly snap shots
		foreach ($totals as $hour=>$snap_shots) {
			$intervals = count($snap_shots);
			foreach ($snap_shots as $key=>$snap_shot) {

				if (empty($totals[$hour]['available_revenue'])) {
					$totals[$hour]['available_revenue'] = $snap_shot['available_revenue'];
				}
				else {
					$totals[$hour]['available_revenue'] = $totals[$hour]['available_revenue'] + $snap_shot['available_revenue'];
				}

				if (empty($totals[$hour]['available_quota'])) {
					$totals[$hour]['available_quota'] = $snap_shot['available_quota'];
				}
				else {
					$totals[$hour]['available_quota'] = $totals[$hour]['available_quota'] + $snap_shot['available_quota'];
				}

				if (empty($totals[$hour]['available_projects'])) {
					$totals[$hour]['available_projects'] = $snap_shot['available_projects'];
				}
				else {
					$totals[$hour]['available_projects'] = $totals[$hour]['available_projects'] + $snap_shot['available_projects'];
				}
				
				if (empty($totals[$hour]['available_quotas'])) {
					$totals[$hour]['available_quotas'] = $snap_shot['available_quotas'];
				}
				else {
					$totals[$hour]['available_quotas'] = $totals[$hour]['available_quotas'] + $snap_shot['available_quotas'];
				}
			}

			$totals[$hour]['snapshots'] = $intervals;
			$totals[$hour]['available_quota'] = round($totals[$hour]['available_quota'] / $intervals);
			$totals[$hour]['available_revenue'] = round($totals[$hour]['available_revenue'] / $intervals);
			$totals[$hour]['available_quotas'] = round($totals[$hour]['available_quotas'] / $intervals);
			$totals[$hour]['available_projects'] = round($totals[$hour]['available_projects'] / $intervals);
		}
		// End boil down


		// Convert list of cint project ids to mv project ids
		$this->Project->bindModel(array(
			'hasOne' => array(
				'CintSurvey' => array(
					'className' => 'CintSurvey',
					'foreignKey' => 'survey_id'
				)
			)
		));
			
		$mv_projects = $this->Project->find('all', array(
			'conditions' => array(
				'CintSurvey.country' => $country,
				'CintSurvey.cint_survey_id' => $all_available_cint_project_ids
			)
		));
		
		$this->out('MV Project IDs: '.count($mv_projects));

		// Get our actual revenue / completes / clicks
		$a = 0;
		while ($a < $total_periods) {
			foreach ($mv_projects as $project) {
				$start = date(DB_DATETIME, strtotime($date.' 00:00:00 +'.$a.' hours'));
				
				$stop = $a + 1;
				$stop = date(DB_DATETIME, strtotime($date.' 00:00:00 +'.$stop.' hours'));
			
				$survey_visits = $this->SurveyVisit->find('list', array(
					'fields' => array('SurveyVisit.id', 'SurveyVisit.type'),
					'conditions' => array(
						'SurveyVisit.survey_id' => $project['Project']['id'],
						'SurveyVisit.created >=' => $start,
						'SurveyVisit.created <=' => $stop
					),
					'order' => 'SurveyVisit.id DESC',
					'recursive' => -1
				));

				if (!empty($survey_visits)) {
					$statistics = array_count_values($survey_visits);
					$current_clicks = isset($statistics[SURVEY_CLICK]) && !empty($statistics[SURVEY_CLICK]) ? $statistics[SURVEY_CLICK]: 0;
					$current_completes = isset($statistics[SURVEY_COMPLETED]) && !empty($statistics[SURVEY_COMPLETED]) ? $statistics[SURVEY_COMPLETED]: 0;

					if (empty($totals[$a]['actual_completes'])) {
						$totals[$a]['actual_completes'] = $current_completes;
					}
					else {
						$totals[$a]['actual_completes'] = $totals[$a]['actual_completes'] + $current_completes;
					}

					if (empty($totals[$hour]['actual_clicks'])) {
						$totals[$a]['actual_clicks'] = $current_clicks;
					}
					else {
						$totals[$a]['actual_clicks'] = $totals[$a]['actual_clicks'] + $current_clicks;
					}

					if (empty($totals[$a]['actual_revenue'])) {
						if ($current_completes > 0) {
							$totals[$a]['actual_revenue'] = $current_completes * $project['Project']['client_rate'];
						}
					}
					else {
						if ($current_completes > 0) {
							$totals[$a]['actual_revenue'] = $totals[$a]['actual_revenue'] + ($current_completes * $project['Project']['client_rate']);
						}
					}
				}
			}
			
			$a++;
		}
		// End grabbing actual hourly data.

		// Boil down daily totals / averages
		foreach ($totals as $hour=>$total) {
			
				// Calculate revenue fill rate per hour
				if (!empty($total['actual_revenue']) && !empty($total['available_revenue'])) {
					$totals[$hour]['revenue_metric'] = $total['actual_revenue'] / ($total['available_revenue'] + $total['actual_revenue']);
					if (empty($day_total['revenue_metric'])) {
						$day_total['revenue_metric'] = $total['actual_revenue'] / $total['available_revenue'];
					}
					else {
						$day_total['revenue_metric'] = $day_total['revenue_metric'] + ($total['actual_revenue'] / $total['available_revenue']);
					}
				}

				// Calculate complete fill rate per hour
				if (!empty($total['actual_completes']) && !empty($total['available_quota'])) {
					$totals[$hour]['complete_metric'] = $total['actual_completes'] / ($total['available_quota'] + $total['actual_completes']);
					if (empty($day_total['complete_metric'])) {
						$day_total['complete_metric'] = $total['actual_completes'] / $total['available_quota'];
					}
					else {
						$day_total['complete_metric'] = $day_total['complete_metric'] + ($total['actual_completes'] / $total['available_quota']);
					}
				}

				// Calculate revenue fill rate per hour
				if (!empty($total[$hour]['revenue_metric']) && !empty($totals[$hour]['complete_metric'])) {
					$totals[$hour]['single_metric'] = ($totals[$hour]['revenue_metric'] + $totals[$hour]['complete_metric']) / 2;
				}
				
				if (empty($day_total['actual_revenue'])) {
					if (!empty($total['actual_revenue'])) {
						$day_total['actual_revenue'] = $total['actual_revenue'];
					}
				}
				else {
					if (!empty($total['actual_revenue'])) {
						$day_total['actual_revenue'] = $day_total['actual_revenue'] + $total['actual_revenue'];
					}
				}
				
				if (empty($day_total['actual_completes'])) {
					if (!empty($total['actual_completes'])) {
						$day_total['actual_completes'] = $total['actual_completes'];
					}
				}
				else {
					if (!empty($total['actual_completes'])) {
						$day_total['actual_completes'] = $day_total['actual_completes'] + $total['actual_completes'];
					}
				}

				if (empty($day_total['actual_clicks'])) {
					if (!empty($total['actual_clicks'])) {
						$day_total['actual_clicks'] = $total['actual_clicks'];
					}
				}
				else {
					if (!empty($total['actual_clicks'])) {
						$day_total['actual_clicks'] = $day_total['actual_clicks'] + $total['actual_clicks'];
					}
				}

				if (empty($day_total['available_revenue'])) {
					$day_total['available_revenue'] = $total['available_revenue'];
				}
				else {
					$day_total['available_revenue'] = $day_total['available_revenue'] + $total['available_revenue'];
				}

				if (empty($day_total['available_quota'])) {
					$day_total['available_quota'] = $total['available_quota'];
				}
				else {
					$day_total['available_quota'] = $day_total['available_quota'] + $total['available_quota'];
				}

				if (empty($day_total['available_quotas'])) {
					$day_total['available_quotas'] = $total['available_quotas'];
				}
				else {
					$day_total['available_quotas'] = $day_total['available_quotas'] + $total['available_quotas'];
				}

				if (empty($day_total['available_projects'])) {
					$day_total['available_projects'] = $total['available_projects'];
				}
				else {
					$day_total['available_projects'] = $day_total['available_projects'] + $total['available_projects'];
				}

		}
		// End boil
		
		// Generate daily averages
		$day_total['revenue_metric'] = round($day_total['revenue_metric'] / $total_periods, 4);
		$day_total['complete_metric'] = round($day_total['complete_metric'] / $total_periods, 4);
		$day_total['available_quota'] = round($day_total['available_quota'] / $total_periods);
		$day_total['available_revenue'] = round($day_total['available_revenue'] / $total_periods);
		$day_total['average_cpi'] = round($day_total['available_revenue'] / $day_total['available_quota'], 4);
		$day_total['available_quotas'] = round($day_total['available_quotas'] / $total_periods);
		$day_total['available_projects'] = round($day_total['available_projects'] / $total_periods);
		$day_total['single_metric'] = ($day_total['revenue_metric'] + $day_total['complete_metric']) / 2;
		// End

		// Output results
		$this->out('Average projects: '.$day_total['available_projects'].' Average Quotas: '.$day_total['available_quotas'].' Note: These are projects that match our criteria');
		$this->out('Average ERC: '.$day_total['available_quota'].' Average Revenue: $'.$day_total['available_revenue'].' Average CPI: $'.$day_total['average_cpi']);
		$this->out('Our Revenue: $'.$day_total['actual_revenue'].' Our Clicks: '.$day_total['actual_clicks'].' Our Completes: '.$day_total['actual_completes'].' Our Average CPI: $'.($day_total['actual_revenue'] / $day_total['actual_completes']));
		$this->out('Average Rev Fill: '.$day_total['revenue_metric'].' Average Complete Fill: '.$day_total['complete_metric']);
		$this->out('Average Single Metric: '.$day_total['single_metric']);

	}
	
	// arg $limit = number of projects
	// arg $project_id 
	public function verify_qe2() {
		ini_set('memory_limit', '2048M');
		$limit = 1;
		if (isset($this->args[0])) {
			$limit = $this->args[0];
		}
		
		$keys = array(
			// Lucid API credentials
			'lucid.host', 
			'lucid.api.key', 
			'lucid.supplier.code', 
			'lucid.queryengine',
			
			// QE2 API
			'qe.mintvine.username', 
			'qe.mintvine.password', 
			'hostname.qe',
		);
		$this->settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => $keys,
				'Setting.deleted' => false
			)
		));
		if (count($this->settings) != count($keys)) {
			$this->lecho('FAILED: You are missing required Lucid settings: '.implode(', ', $diff_keys), $log_file, $log_key);
			return false;
		}
		
		$lucid_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'fulcrum'
			)
		));
		
		if (!$lucid_group) {
			return;
		}
		
		$conditions = array('Project.group_id' => $lucid_group['Group']['id']);
		$project_id = '';
		if (isset($this->args[1])) {
			$project_id = $this->args[1];
			$conditions['Project.id'] = $this->args[1];
		}
		
		$projects = $this->Project->find('all', array(
			'conditions' => $conditions,
			'limit' => $limit,
			'order' => 'Project.date_created desc'
		));
		
		if (!$projects) {
			echo 'projects not found.'."\n";
			return;
		}
		
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$params = array('key' => $this->settings['lucid.api.key']);
		foreach ($projects as $project) {
			
			// get lucid qualifications
			$url = $this->settings['lucid.host'].'Supply/v1/SurveyQualifications/BySurveyNumberForOfferwall/'.$project['Project']['client_project_id']; 
			$lucid_qualifications = $http->get($url, $params);
			$lucid_qualifications = json_decode($lucid_qualifications['body'], true);
			
			// get lucid quotas
			$url = $this->settings['lucid.host'].'Supply/v1/SurveyQuotas/BySurveyNumber/'.$project['Project']['client_project_id'].'/'.$this->settings['lucid.supplier.code']; 
			$lucid_quotas = $http->get($url, $params);
			$lucid_quotas = json_decode($lucid_quotas['body'], true);
			
			$parent_query_params = array();
			foreach ($lucid_qualifications['SurveyQualification']['Questions'] as $question) {
				$parent_query_params['qualifications'][$question['QuestionID']] = $question['PreCodes'];
			}
			
			$this->out('Running parent query');
			$parent_v1_results = $this->get_qe1_query_results($project);
			$parent_v1_count = count($parent_v1_results);
			CakeLog::write('qe-'.$project['Project']['id'], 'Parent v1 Count: '.$parent_v1_count);
			
			$parent_v2_results = $this->get_qe2_query_results($parent_query_params);
			$parent_v2_count = count($parent_v2_results);
			CakeLog::write('qe-'.$project['Project']['id'], 'Parent v2 Count: '.$parent_v2_count);
			
			$p_pct = round(($parent_v2_count / $parent_v1_count) * 100, 2);
			CakeLog::write('qe-'.$project['Project']['id'], 'Found '.$parent_v2_count.' from v2 ('.$p_pct.'%)');

			$p_diffs = array_diff($parent_v1_results, $parent_v2_results);
			if (!empty($p_diffs)) {
				CakeLog::write('qe-'.$project['Project']['id'], 'Missing panelist IDs from parent v2: '.implode(', ', $p_diffs));
			}
			$p_diffs = array_diff($parent_v2_results, $parent_v1_results);
			if (!empty($diffs)) {
				CakeLog::write('qe-'.$project['Project']['id'], 'Missing panelist IDs from parent v1: '.implode(', ', $p_diffs));
			}
				
			CakeLog::write('qe-'.$project['Project']['id'], 'Starting filter queries');
			
			foreach ($lucid_quotas['SurveyQuotas'] as $quota) {
				// Skip Overall quota
				if ($quota['SurveyQuotaType'] == 'Total') {
					continue;
				}

				$query_params = array(); // to be sent to query engine
				foreach ($quota['Questions'] as $question) {
					$query_params['qualifications'][$question['QuestionID']] = $question['PreCodes'];
				}
				
				$v1_results = $this->get_qe1_query_results($project, $quota);
				$v1_count = count($v1_results);
				CakeLog::write('qe-'.$project['Project']['id'], 'v1 Count: '.$v1_count);
				// add parent query params
				foreach ($parent_query_params['qualifications'] as $key => $value) {
					if (!isset($query_params['qualifications'][$key])) {
						$query_params['qualifications'][$key] = $value;
					}
				}
				
				$this->out('Running v2 query');
				CakeLog::write('qe-'.$project['Project']['id'], 'v2 Query: '.print_r($query_params, true));
				$v2_results = $this->get_qe2_query_results($query_params);
				$v2_count = count($v2_results);
				CakeLog::write('qe-'.$project['Project']['id'], 'v2 Count: '.$v2_count);
				
				$pct = round(($v2_count / $v1_count) * 100, 2);
				CakeLog::write('qe-'.$project['Project']['id'], 'Found '.$v2_count.' from v2 ('.$pct.'%)');

				$diffs = array_diff($v1_results, $v2_results);
				if (!empty($diffs)) {
					CakeLog::write('qe-'.$project['Project']['id'], 'Missing panelist IDs from v2: '.implode(', ', $diffs));
				}
				$diffs = array_diff($v2_results, $v1_results);
				if (!empty($diffs)) {
					CakeLog::write('qe-'.$project['Project']['id'], 'Missing panelist IDs from v1: '.implode(', ', $diffs));
				}
				
				$this->out('Query analysis complete for quota #'.$quota['SurveyQuotaID'] );
			}
		}
	}
	
	public function get_qe2_query_results($query_params) {
		//$country = $query_params['country'];
		//unset($query_params['country']);
		$query_params['partner'] = 'lucid';
		App::uses('HttpSocket', 'Network/Http');
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$http->configAuth('Basic', $this->settings['qe.mintvine.username'], $this->settings['qe.mintvine.password']);
		try {
			$results = $http->post($this->settings['hostname.qe'].'/query', json_encode($query_params), array(
				'header' => array('Content-Type' => 'application/json')
			));
		} catch (Exception $ex) {
			echo 'QE2 api call failed.'. "\n";
			return false;
		}

		$results = json_decode($results['body'], true);
		if (!isset($results['panelist_ids'])) {
			return false;
		}

		return $results['panelist_ids'];
	}
	
	public function get_qe1_query_results($project, $quota = null) {
		if (!is_null($quota)) {
			$query_name = 'Lucid #' . $project['Project']['client_project_id'] . ' Quota #' . $quota['SurveyQuotaID'];
			$query_old_name = 'Fulcrum #' . $project['Project']['client_project_id'] . ' Quota #' . $quota['SurveyQuotaID'];
		}
		else {
			$query_name = 'Lucid #' . $project['Project']['client_project_id'] . ' Qualifications';
			$query_old_name = 'Fulcrum #' . $project['Project']['client_project_id'] . ' Qualifications';
		}
		
		$query = $this->Query->find('first', array(
			'conditions' => array(
				'Query.query_name' => array($query_name, $query_old_name),
				'Query.survey_id' => $project['Project']['id']
			),
			'order' => 'Query.id DESC' // multiple queries can exist with same name: retrieve the last one
		));

		if ($query) {
			$query_string = json_decode($query['Query']['query_string'], true);
			$query_string['ignore'] = false;
			//$query_string[] = 'qe2_exported is not null';

			// convert date to correct format
			if (!empty($query_string['age_from']) || !empty($query_string['age_to'])) {
				if (isset($query_string['age_from']) && !empty($query_string['age_from']) && empty($query_string['age_to'])) {
					$query_string['age_to'] = $query_string['age_from'];
				}
				if (isset($query_string['age_to']) && !empty($query_string['age_to']) && empty($query_string['age_from'])) {
					$query_string['age_from'] = $query_string['age_to'];
				}

				$seconds_in_year = 31556940;
				if (!empty($query_string['age_from'])) {
					$query_string['birthdate <='] = date(DB_DATE, time() - $query_string['age_from'] * $seconds_in_year);
					unset($query_string['age_from']);
				}
				if (!empty($query_string['age_to'])) {
					// add a day to make sure you capture the days correctly
					$query_string['birthdate >'] = date(DB_DATE, time() - $query_string['age_to'] * $seconds_in_year - $seconds_in_year + 86400);
					unset($query_string['age_to']);
				}
			}
			
			$this->out('Running v1 query #'.$query['Query']['id']);
			CakeLog::write('qe-'.$project['Project']['id'], 'Running v1 query #'.$query['Query']['id'].print_r($query_string, true));
			return $this->QueryProfile->find('list', array(
				'fields' => array('QueryProfile.id', 'QueryProfile.user_id'),
				'conditions' => array(
					$query_string
				),
				'recursive' => -1
			));
		}
		else {
			echo 'Query ('.$query_name.') not found for survey #'.$project['Project']['id']. "\n";
			return false;
		}
	}
	
	// exporting mintvine profiles
	public function export_mintvine_profiles() {
		
		App::uses('HttpSocket', 'Network/Http');
				
		$models_to_import = array('QueryProfile', 'Setting');
		foreach ($models_to_import as $model_to_import) {
			App::import('Model', $model_to_import);
			$this->$model_to_import = new $model_to_import;
		}
		
		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => array(
					'qe.mintvine.username', 
					'qe.mintvine.password', 
					'hostname.qe',
				),
				'Setting.deleted' => false
			)
		));
		
		$total = $this->QueryProfile->find('count', array(
			'recursive' => -1,
			'conditions' => array(
				'QueryProfile.ignore' => false,
				'QueryProfile.qe2_exported is null'
			)
		));
		
		$this->out('Exporting '.$total.' records');
		$i = 0; 
		while (true) {
			$query_profiles = $this->QueryProfile->find('all', array(
				'recursive' => -1,
				'fields' => array(
					'QueryProfile.id',
					'QueryProfile.user_id', 
					'QueryProfile.gender', 
					'QueryProfile.birthdate',
					'QueryProfile.country',
					'QueryProfile.state',
					'QueryProfile.postal_code',
					'QueryProfile.dma_code',
					'QueryProfile.hhi',
					'QueryProfile.education',
					'QueryProfile.children',
					'QueryProfile.employment',
					'QueryProfile.industry',
					'QueryProfile.organization_size',
					'QueryProfile.organization_revenue',
					'QueryProfile.job',
					'QueryProfile.department',
					'QueryProfile.relationship',
					'QueryProfile.ethnicity',
					'QueryProfile.hispanic',
					'QueryProfile.housing_own',
					'QueryProfile.housing_purchased',
					'QueryProfile.housing_plans',
					'QueryProfile.smartphone',
					'QueryProfile.tablet',
					'QueryProfile.airlines',
				),
				'conditions' => array(
					'QueryProfile.ignore' => false,
					'QueryProfile.qe2_exported is null',
				),
				'order' => 'QueryProfile.id ASC',
				'limit' => '500'
			));
			if (!$query_profiles) {
				break;
			}
			$query_params = array(
				'partner' => 'mintvine',
				'qualifications' => array(
					// each user_id
				)
			);
			
			$updated_ids = array();
			foreach ($query_profiles as $query_profile) {
				$updated_ids[] = $query_profile['QueryProfile']['id'];	
				
				$i++;
				$pct = ($i / $total) * 100; 
				$this->out($i.'/'.$total.' ('.round($pct, 3).'%) : '.$query_profile['QueryProfile']['user_id']);
				
				$user_id = $query_profile['QueryProfile']['user_id'];
				if (!isset($query_params['qualifications'][$user_id])) {
					$query_params['qualifications'][$user_id] = array();
				}
				foreach ($query_profile['QueryProfile'] as $key => $val) {
					if (is_bool($val)) {
						$query_profile['QueryProfile'][$key] = $val ? '1': '0';
					}
					if (is_null($val) || $val == '') {
						unset($query_profile['QueryProfile'][$key]);
						continue;
					}
					$query_profile['QueryProfile'][$key] = array($query_profile['QueryProfile'][$key]);
				}
				unset($query_profile['QueryProfile']['id']);
				unset($query_profile['QueryProfile']['user_id']);
				$query_params['qualifications'][$user_id] = $query_profile['QueryProfile'];	
			}
			
			$http = new HttpSocket(array(
				'timeout' => 15,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$http->configAuth('Basic', $settings['qe.mintvine.username'], $settings['qe.mintvine.password']);
			$results = $http->post($settings['hostname.qe'].'/qualifications', json_encode($query_params), array(
				'header' => array('Content-Type' => 'application/json')
			));
			
			foreach ($updated_ids as $updated_id) {
				$this->QueryProfile->create();
				$this->QueryProfile->save(array('QueryProfile' => array(
					'id' => $updated_id,
					'modified' => false,
					'qe2_exported' => date(DB_DATETIME)
				)), true, array('qe2_exported'));
			}
		}
		
		$this->out('Finished');
	}
	
	// this is to test a certain SSL cert which is causing API problems
	public function ssl() {
		$setting = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'hostname.api',
				'Setting.deleted' => false
			),
			'fields' => array('Setting.value')
		));
		$http = new HttpSocket(array(
			'timeout' => '2',
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$response = $http->get($setting['Setting']['value']);
		print_r($response);
	}
	
	public function slack() {
		$setting = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'slack.glimpzit.webhook',
				'Setting.deleted' => false
			),
			'fields' => array('Setting.value')
		));
		$http = new HttpSocket(array(
			'timeout' => '2',
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$response = $http->post($setting['Setting']['value'], json_encode(array(
			'text' => 'Testing slack api posting to #glimpzit', 
			'link_names' => 1,
			'username' => 'bernard'
		)));
		print_r($response);
	}
	
	public function compare_proxy_results() {
				
		// debug one
		if (isset($this->args[0])) {
			$visits = $this->UserIp->find('all', array(
				'conditions' => array(
					'UserIp.user_id' => $this->args[0]
				),
				'limit' => '100',
				'order' => 'UserIp.id DESC'
			));
		}
		// versus last 100 trailing
		else {
			$visits = $this->UserIp->find('all', array(
				'conditions' => array(
					'UserIp.fraud_score is not null'
				),
				'limit' => '200',
				'order' => 'UserIp.id DESC'
			));
		}
		
		if (!$visits) {
			$this->out('No IPs found.');
			return false;
		}
		
		$message = 'Found ' . count($visits).' IPs'; 
		$this->out($message);
		CakeLog::write('ip_proxy', $message);
		
		$http = new HttpSocket(array(
			'timeout' => 5,
			'ssl_verify_host' => false
		));

		$output = fopen(WWW_ROOT . '/files/ipinteltest.csv', 'w');

		fputcsv($output, array(
			'IP Address', 
			'MaxMind Score', 
			'IPIntel (m)', 
			'IPIntel (b)',
		));
		
		foreach ($visits as $visit) {

			try {
		
				$http = new HttpSocket(array(
					'timeout' => 5,
					'ssl_verify_host' => false
				));
				
				$results_m = $http->get('http://royka62sz7uswbsv7nke88fzkf.getipintel.net/check.php?flags=m&ip='.$visit['UserIp']['ip_address'].'&contact=royikim@gmail.com');
				if ($results_m->code != 200) {
					print_r($results_m); 
					return;
				}
		
				$http = new HttpSocket(array(
					'timeout' => 5,
					'ssl_verify_host' => false
				));

				$results_b = $http->get('http://royka62sz7uswbsv7nke88fzkf.getipintel.net/check.php?flags=b&ip='.$visit['UserIp']['ip_address'].'&contact=royikim@gmail.com');
				if ($results_b->code != 200) {
					print_r($results_b); 
					return;
				}
			} 
			catch (Exception $e) {
				print_r($e);
				$this->out('API call failed');
				return false;
			}

		
			$http = new HttpSocket(array(
				'timeout' => 5,
				'ssl_verify_host' => false
			));

			$settings = $this->Setting->find('list', array(
				'fields' => array('Setting.name', 'Setting.value'),
				'conditions' => array(
					'Setting.name' => 'maxmind.license',
					'Setting.deleted' => false
				)
			));
			
			$results = $http->get('https://minfraud.maxmind.com/app/ipauth_http?l='.$settings['maxmind.license'].'&i=' . $visit['UserIp']['ip_address']);
			$return = $results->body;						
			preg_match("/proxyScore=([0-9\.]+)/", $return, $match);	
			$proxyScore = null;
			if (isset($match[1]) && !empty($match[1])) {
				$proxyScore = floatval($match[1]);
			}
			
			fputcsv($output, array(
				$visit['UserIp']['ip_address'], 
				$proxyScore, 
				$results_m->body, 
				$results_b->body
			));
			$this->out('Finished for '.$visit['UserIp']['ip_address']);
		}

		fclose($output);
		$this->out('Completed');
		
	}

	
	public function fingerprint_data_check() {
		App::import('Model', 'SurveyFingerprint');
		$this->SurveyFingerprint = new SurveyFingerprint; 
		App::import('Model', 'SurveyVisit');
		$this->SurveyVisit = new SurveyVisit; 
		
		if (!isset($this->args[0])) {
			$this->out('Please input a survey ID');
			return false;
		}
		$survey_visits = $this->SurveyVisit->find('all', array(
			'conditions' => array(
				'SurveyVisit.survey_id' => $this->args[0],
				'SurveyVisit.type' => SURVEY_CLICK,
				'SurveyVisit.result_id' => '0', 
			)
		));
		$no_fp = $fp = 0;
		foreach ($survey_visits as $survey_visit) {
			$count = $this->SurveyFingerprint->find('count', array(
				'conditions' => array(
					'SurveyFingerprint.survey_id' => $this->args[0],
					'SurveyFingerprint.hash' => $survey_visit['SurveyVisit']['hash'],
				)
			)); 
			if ($count == 0) {
				$info = Utils::print_r_reverse($survey_visit['SurveyVisit']['info']); 
				$this->out('#'.$survey_visit['SurveyVisit']['id'].':' .$info['HTTP_USER_AGENT']); 
				$no_fp++;
			}
			else {
				$fp++;
			}
		}
		$this->out('FP: '.$fp);
		$this->out('No FP: '.$no_fp);
	}
	
	public function qa_segment_identify() {
		if (isset($this->args[0])) {
			$users = $this->User->find('all', array(
				'fields' => array('User.id'),
				'conditions' => array(
					'User.id' => $this->args[0]
				),
				'recursive' => -1
			));
		}
		else {
			$users = $this->User->find('all', array(
				'fields' => array('User.id'),
				'conditions' => array(
					'User.last_touched >=' => date(DB_DATETIME, strtotime('-6 weeks'))
				),
				'recursive' => -1
			));
		}
		
		$this->out('Analyzing '.count($users).' panelists');
		if (!$users) {
			$this->out('User not found');
			return false;
		}
		
		foreach ($users as $user) {
			$user_analytics = $this->UserAnalytic->find('all', array(
				'conditions' => array(
					'UserAnalytic.user_id' => $user['User']['id'],
					'UserAnalytic.created >=' => date(DB_DATETIME, strtotime('-2 days'))
				),
				'order' => array('UserAnalytic.id asc')
			));
			if (!$user_analytics) {
				continue;
			}
		
			$previous_identify = array();
			$i = 0;
			$success = true;
			foreach ($user_analytics as $analytic) {
				if (empty($analytic['UserAnalytic']['json_identify']) && empty($previous_identify)) {
					continue;
				}
				// skip the first record
				if ($i == 0) {
					$previous_identify = json_decode($analytic['UserAnalytic']['json_identify'], true);
					$i++;
					continue;
				}
			
				$current_identify = json_decode($analytic['UserAnalytic']['json_identify'], true);
				if (!isset($current_identify['traits']) || empty($current_identify['traits'])) {
					continue;
				}
				if (count($current_identify['traits']) < count($previous_identify['traits'])) {
					$diff_keys = array_values(array_diff(array_keys($previous_identify['traits']), array_keys($current_identify['traits'])));
					$this->out('ERROR: #'.$analytic['UserAnalytic']['user_id'].': '.$analytic['UserAnalytic']['id'].' '.json_encode($diff_keys)); 
				}
				$previous_identify = $current_identify;
			}
		}
		$this->out('Finished');	
	}
	
	public function analyze_user_router_reports() {
		if (!isset($this->args[0])) {
			$this->out('Input date');
		}
		$user_router_logs = $this->UserRouterLog->find('all', array(
			'conditions' => array(
				'UserRouterLog.created LIKE' => $this->args[0].'%',
				'UserRouterLog.parent_id' => '0'
			)
		));
		$this->out(count($user_router_logs)); 
		$toluna_count = $precision_count = $others_count = 0; 
		foreach ($user_router_logs as $user_router_log) {
			$project = $this->Project->find('first', array(
				'fields' => array('Project.group_id'),
				'recursive' => -1,
				'conditions' => array(
					'Project.id' => $user_router_log['UserRouterLog']['survey_id']
				)
			));
			
			$is_sampled = false;
			if (in_array($project['Project']['group_id'], array(8, 10))) {
				if ($user_router_log['UserRouterLog']['ir'] == '99') {
					$is_sampled = true;
					if ($project['Project']['group_id'] == 8) {
						$toluna_count++;
					}
					else {
						$precision_count++;
					}
				}
				elseif ($user_router_log['UserRouterLog']['ir'] == '66') {
					$is_sampled = true;
					if ($project['Project']['group_id'] == 8) {
						$toluna_count++;
					}
					else {
						$precision_count++;
					}
				}
			}
			if (!$is_sampled) {
				$others_count++;	
			}
		}
		$this->out('toluna: '.$toluna_count);
		$this->out('precision: '.$precision_count);
		$this->out('other: '.$others_count);
	}
	
	
	public function cint_50_terms() {
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.group_id' => 5,
				'SurveyVisitCache.complete >' => '10'
			)
		));
		$totals = array();
		$this->out('Analyzing '.count($projects).' Cint projects with more than 10 completes'); 
		foreach ($projects as $project) {
			$first_complete = $this->SurveyVisit->find('first', array(
				'fields' => array('SurveyVisit.id'),
				'conditions' => array(
					'SurveyVisit.survey_id' => $project['Project']['id'],
					'SurveyVisit.type' => SURVEY_COMPLETED
				),
				'order' => 'SurveyVisit.id ASC'
			));
			$count = $this->SurveyVisit->find('count', array(
				'conditions' => array(
					'SurveyVisit.type' => SURVEY_CLICK,
					'SurveyVisit.survey_id' => $project['Project']['id'],
					'SurveyVisit.id <=' => $first_complete['SurveyVisit']['id']
				)
			));
			$totals[] = $count;
			$this->out($project['Project']['id'].' (final epc: $0.'.$project['Project']['epc'].') had '.$count.' clicks before the first complete (total completes: '.$project['SurveyVisitCache']['complete'].')');
		}
		$this->out('Average: '.(array_sum($totals) / count($totals)));
	}
	
	public function lucid_non_performant_projects() {
		
		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => array('lucid.host', 'lucid.api.key', 'lucid.supplier.code'),
				'Setting.deleted' => false
			)
		));
		
		$http = new HttpSocket(array(
			'timeout' => 30,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$params = array('key' => $settings['lucid.api.key']);
		
		// $list = '214091,213813,213947,214182,214155,214175,214177,214191,214136,214092,214104,214079,214113,214126,214002,213914,214003,214093,214059,214035,213996,213886,214086,214081,214089,213946,214061,214017,214058,213985,214069,214072,214043,213874,214046,214045,214007,214005,213986,213900,213536,213733,214024,214038,214040,214015,213768,213431,213970,213984,213727,213987,213766,213795,213961,213930,213938,213889,213960,213955,213851,213911,213764,213937,213865,213922,213769,213875,213847,213904,213895,213885,213919,213898,213788,213789,213848,213859,213802,213822,213835,213793,213863,213796,213744,213817,213688,213816,213464,213721,213728,213779,213778,213765,213724,213699,213706,213693,213535,213702,213564,213692,213683,213685,213641,213537,213430,213590,213530,213500,213495,213459,213429';
		$list = '214091,213813,213947,214182,214155,214175,214177,214191,214136,214092,214104,214079,214113,214126,214002,213914,214003,214093,214059,214035,213996,213886,214086,214081,214089,213946,214061,214017,214058,213985,214069,214072,214043,213874,214046,214045,214007,214005,213986,213900,213536,213733,214024,214038,214040,214015,213768,213431,213970,213984,213727,213987,213766,213795,213961,213930,213938,213889,213960,213955,213851,213911,213764,213937,213865,213922,213769,213875,213847,213904,213895,213885,213919,213898,213788,213789,213848,213859,213802,213822,213835,213793,213863,213796,213744,213817,213688,213816,213464,213721,213728,213779,213778,213765,213724,213699,213706,213693,213535,213702,213564,213692,213683,213685,213641,213537,213430,213590,213530,213500,213495,213459,213429,214022,214116,214120,214121,213905,213918,214030,213968,213657,213709,213710,213714,213888,213890,213893,214051,214196,214118,214206,214124,213850,214008,214167,214123,213860,214023,213806,214044,214122,213655,214172,214169,214174,213988,214013,214062,214096,214057,213956,213958,213962,214160,213639,214156,213538,214029,214073,214151,214107,213935,213827,213980,213973,214205,213798,213861,213931,213944,213949,213976,214133,214176,213746,213748,213749,213751,213752,213754,213757,213610,214159,214179,214102,214144,213844,213995,213929,213687,214131,213942,213726,214098,214099,214055,213858,214127,214194,214163,214054,214067,214186,214215,213824,214100,213939,213945,214076,214211,214106,214214,213760,213807,213808,213800,213681,213458,213907,214197,214063,214128,214152,213842,214210,214162,214193,213950,214108,214117,214060,214016,213971,213840,214178,213638,214198,213696,214213,213811,213941,213999,213948,214143,214195,214134,214166,213695,214203,213591,214158,213879,213814,213794,214071,213697,213771,213773,213767,213774,213772,213894,213797,213770,213763,214039,213781';
		$project_ids = explode(',', $list); 
		$fed_survey_ids = $this->FedSurvey->find('list', array(
			'fields' => array('FedSurvey.survey_id', 'FedSurvey.fed_survey_id'),
			'conditions' => array(
				'FedSurvey.survey_id' => $project_ids
			)
		));
		
		$performing = $unperforming = $unperforming_fed_ids = array();
		foreach ($fed_survey_ids as $survey_id => $fed_survey_id) {
			$url = $settings['lucid.host'].'Supply/v1/SurveyStatistics/BySurveyNumber/'.$fed_survey_id.'/'.$settings['lucid.supplier.code']; 
			$response = $http->get($url, $params);	
			$response = json_decode($response, true);
			
			$response['SurveyStatistics']['GlobalTrailingSystemConversion'] = (float) $response['SurveyStatistics']['GlobalTrailingSystemConversion']; 
			$this->out($survey_id.' '.$fed_survey_id.' '.$response['SurveyStatistics']['GlobalTrailingSystemConversion']);
			if ($response['SurveyStatistics']['GlobalTrailingSystemConversion'] > 0) {
				$performing[] = $survey_id;
			}
			else {
				$unperforming[] = $survey_id;
				$unperforming_fed_ids[] = $fed_survey_id;
			}
		}
		$this->out(count($unperforming).' underperformed projects');
		$this->out(count($performing).' performant projects');
//		print_r($performing);
		print_r($unperforming_fed_ids);
	}
	
	public function repro_multiple_data_sources_long() {
		$start_timer = microtime(true);
		$this->out('Starting');
		$group = $this->Group->find('first', array(
			'fields' => array('id'),
			'conditions' => array(
				'Group.key' => 'fulcrum'
			)
		));
		
		$start_date = '2016-08-10';
		$end_date = '2016-08-10';
		
		$conditions = array(
			'Project.group_id' => $group['Group']['id'],
			'OR' => array(
				// projects started before and ended after selected dates
				array(
					'Project.started <=' => $start_date.' 00:00:00',
					'Project.ended >=' => $start_date.' 23:59:59'
				),
				// projects started and ended during the duration of the selected date
				array(
					'Project.started >=' => $start_date.' 00:00:00',
					'Project.ended <=' => $end_date.' 23:59:59'
				),
				// projects started before the end date but ending much later
				array(
					'Project.started <=' => $end_date.' 23:59:59',
					'Project.ended >=' => $end_date.' 23:59:59'
				),
				// projects that are still open
				array(
					'Project.started <=' => $end_date.' 23:59:59',
					'Project.ended is null'
				),
				// addressing https://basecamp.com/2045906/projects/1413421/todos/206702078
				array(
					'Project.ended LIKE' => $end_date.'%'
				) 
			)
		);
		
		$this->Project->unbindModel(array(
			'hasMany' => array('SurveyPartner', 'ProjectOption'),
			'belongsTo' => array('Group', 'Client'),
			'hasOne' => array('SurveyVisitCache')
		));
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Project.client_rate', 'Project.award', 'Project.started', 'Project.ended', 'Project.mask'),
			'conditions' => $conditions
		));
		if ($projects) {
			$project_ids = $project_earnings = array();
			foreach ($projects as $key => $project) {
				$project_ids[] = $project['Project']['id'];
				$project_earnings[$project['Project']['id']] = $project['Project']['client_rate'];
			}
			$unique_panelists = $this->SurveyUser->find('all', array(
				'fields' => array('DISTINCT(user_id) as user_id'),
				'conditions' => array(
					'SurveyUser.survey_id' => $project_ids,
					'SurveyUser.created >=' => $start_date.' 00:00:00',
					'SurveyUser.created <=' => $end_date.' 23:59:59'
				)
			)); 
			$unique_panelists = count($unique_panelists);
			$fields = array('id', 'result', 'survey_id', 'created');

			$survey_visits = $this->SurveyVisit->find('all', array(
				'recursive' => -1,
				'fields' => $fields,
				'conditions' => array(
					'SurveyVisit.survey_id' => $project_ids,
					'SurveyVisit.type' => SURVEY_CLICK,
					'SurveyVisit.created >=' => $start_date.' 00:00:00',
					'SurveyVisit.created <=' => $end_date.' 23:59:59'
				)
			));
			
			$invite_count = $this->SurveyUser->find('count', array(
				'recursive' => -1,
				'conditions' => array(
					'SurveyUser.survey_id' => $project_ids,
					'SurveyUser.created >=' => $start_date.' 00:00:00',
					'SurveyUser.created <=' => $end_date.' 23:59:59'
				)
			));
			
			$masks = $this->Project->find('list', array(
				'fields' => array('id', 'mask'),
				'conditions' => array(
					'Project.id' => $project_ids
				)
			));
			
			// find total projects created, find total launched
			$this->Project->unbindModel(array(
				'hasMany' => array('SurveyPartner', 'ProjectOption'),
				'belongsTo' => array('Group', 'Client'),
				'hasOne' => array('SurveyVisitCache')
			));
				$conditions = array(
					'Project.group_id' => $group['Group']['id'],
					'Project.date_created >' => $start_date.' 00:00:00',
					'Project.date_created <' => $end_date.' 23:59:59',
				);
			$projects = $this->Project->find('all', array(
				'fields' => array(
					'Project.id',
					'Project.started', 
					'Project.date_created'
				), 
				'conditions' => $conditions
			));
		}
		$this->out(microtime(true) - $start_timer);
	}
	
	public function find_missed_lucid_projects() {
		// 12 hours, as lucid only looks at data past this point
		$datetime = strtotime('-12 hours');
		$this->out('Reviewing data since '.date(DB_DATETIME, $datetime)); 
		$sampled_projects = $this->ProjectLog->find('list', array(
			'fields' => array('ProjectLog.id', 'ProjectLog.project_id'),
			'conditions' => array(
				'ProjectLog.created >=' => date(DB_DATETIME, $datetime),
				'ProjectLog.type' => 'status.sample'
			)
		));
		$launched_projects = $this->ProjectLog->find('list', array(
			'fields' => array('ProjectLog.id', 'ProjectLog.project_id'),
			'conditions' => array(
				'ProjectLog.created >=' => date(DB_DATETIME, $datetime),
				'ProjectLog.type' => 'status.opened.sample'
			)
		));
		$this->out('Found '.count($sampled_projects).' sampled projects since '.$datetime); 
		$this->out('Found '.count($launched_projects).' launched projects since '.$datetime); 
		$non_launched_projects = array_diff($sampled_projects, $launched_projects);
		$this->out('Found '.count($non_launched_projects).' launched projects since '.$datetime); 
		
		
		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => array('lucid.host', 'lucid.api.key', 'lucid.supplier.code'),
				'Setting.deleted' => false
			)
		));
		
		$http = new HttpSocket(array(
			'timeout' => 30,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$params = array('key' => $settings['lucid.api.key']);
		foreach ($non_launched_projects as $project_id) {
			$fed_survey = $this->FedSurvey->find('first', array(
				'fields' => array('FedSurvey.fed_survey_id'),
				'conditions' => array(
					'FedSurvey.survey_id' => $project_id
				)
			));
			$url = $settings['lucid.host'].'Supply/v1/SurveyStatistics/BySurveyNumber/'.$fed_survey['FedSurvey']['fed_survey_id'].'/'.$settings['lucid.supplier.code'].'/Global/Trailing'; 
			$response = $http->get($url, $params);	
			$response = json_decode($response, true);
			if ($response['SurveyStatistics']['EffectiveEPC'] > 0.15) {
				$check_why_skipped[] = $project_id; 
			}
		}
		
		$this->out('Found '.count($check_why_skipped).' projects');
		$this->out(implode("\n", $check_why_skipped)); 
	}
	
	// for lucid; they need sha256 emails and pids for proof
	public function export_hashed_emails() {
		$this->User->bindModel(array(
			'hasOne' => array(
				'QueryProfile'
			)
		));
		$users = $this->User->find('all', array(
			'fields' => array('User.id', 'User.email'),
			'conditions' => array(
				'QueryProfile.country' => 'GB',
				'QueryProfile.birthdate <=' => date(DB_DATE, strtotime('-18 years'))
			)
		));
		$this->out('Outputting '.count($users)); 

		$fp = fopen('gb_18+.csv', 'w');
		
		fputcsv($fp, array(
			'PID', 
			'Email (SHA-256)', 
		));
		
		// control field
		fputcsv($fp, array(
			'0', 
			hash('sha256', 'roy@brandedresearchinc.com')
		));
		
		foreach ($users as $user) {
			fputcsv($fp, array(
				$user['User']['id'],
				hash('sha256', $user['User']['email'])
			));
		}
		$this->out('completed');
		fclose($fp);
	}
	
	public function find_adhoc_lucid_projects() {
		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'mintvine'
			)
		));
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Project.client_survey_link'),
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'Project.client_survey_link LIKE' => '%samplicio.us%'
			),
			'recursive' => -1
		));
		$this->out('Found '.count($projects)); 
		print_r($projects);
	}
	
	public function compare_partner_sets() {
		$lucid = file_get_contents('Command/files/lucid.missing.json');
		$mintvine = file_get_contents('Command/files/mintvine.missing.json');
		$lucid_user_ids = json_decode($lucid, true);
		$mintvine_user_ids = json_decode($mintvine, true);
		
		$diff_ids = array_diff($lucid_user_ids['panelist_ids'], $mintvine_user_ids['panelist_ids']);
		foreach ($diff_ids as $diff_id) {
			
		}
#		print_r($diff_ids);
		
		$diff_ids = array_diff($mintvine_user_ids['panelist_ids'], $lucid_user_ids['panelist_ids']);
		print_r($diff_ids);
	}
	
	public function bulk_writes_for_maxscale() {
		App::import('Model', 'TestWrite');
		$this->TestWrite = new TestWrite; 
		
		$this->out('Writing 100,000 records');
		$j = 0; 
		for ($i = 0; $i < 10000; $i++) {
			$testWriteSource = $this->TestWrite->getDataSource();
			$testWriteSource->begin();
			$this->TestWrite->create();
			$this->TestWrite->save(array('TestWrite' => array(
				'value' => Utils::rand(12)
			)));
			$test_write_id = $this->TestWrite->getInsertId();
			$test_write = $this->TestWrite->find('first', array(
				'fields' => array('TestWrite.id'),
				'conditions' => array(
					'TestWrite.id' => $test_write_id
				)
			));
			$testWriteSource->commit();
			if (!$test_write) {
				$this->out('FAILED WRITING '.$i);
				$j++;
			}
		}
		$this->out('Completed; failed '.$j);
	}
	
	public function check_data_integrity_lucid() {
		$lucid_survey_ids = $this->LucidQueue->find('list', array(
			'fields' => array('LucidQueue.fed_survey_id'),
			'conditions' => array(
				'LucidQueue.created >=' => '2016-11-04 03:20:00', 
				'LucidQueue.created <=' => '2016-11-04 04:35:00',
				'LucidQueue.command LIKE' => '%create%'
			)
		));
		$lucid_survey_ids = array_unique($lucid_survey_ids);
		
		$fed_survey_ids = $this->FedSurvey->find('all', array(
			'conditions' => array(
				'FedSurvey.fed_survey_id' => $lucid_survey_ids
			)
		));
		print_r($fed_survey_ids);
	}
	
	// 
	public function adhoc_query_usage() {
		$mintvine_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'mintvine'
			)
		));
		$this->Query->unbindModel(array('hasMany' => array('QueryHistory')));
		$this->Query->bindModel(array('belongsTo' => array(
			'Project' => array(
				'foreignKey' => 'survey_id'
			)
		)));
		
		$queries = $this->Query->find('all', array(
			'fields' => array('Query.query_string'),
			'conditions' => array(
				'Project.group_id' => $mintvine_group['Group']['id'],
				'Project.started >=' => date(DB_DATETIME, strtotime('-1 year'))
			)
		));
		$count = array();
		foreach ($queries as $query) {
			$query = json_decode($query['Query']['query_string'], true);
			if (empty($query)) {
				continue;
			} 
			foreach ($query as $key => $val) {
				if (!isset($count[$key])) {
					$count[$key] = 0; 
				}
				$count[$key]++;
			}
		}
		arsort($count);
		print_r($count);
	}
	
	public function survey_countries() {
		$this->Project->unbindModel(array(
			'hasMany' => array(
				'ProjectOption',
				'ProjectAdmin',
				'SurveyPartner'
			),
			'hasOne' => array(
				'SurveyVisitCache'
			),
			'belongsTo' => array(
				'Client',
				'Group'
			),
		));
		$this->Project->bindModel(array(
			'hasMany' => array(
				'SurveyCountry' => array(
					'foreignKey' => 'survey_id'
				)
			)
		));
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Project.country'),
			'conditions' => array(
				'Project.active' => true,
				'Project.status' => PROJECT_STATUS_OPEN
			)
		));
		foreach ($projects as $project) {
			if (count($project['SurveyCountry']) > 1) {
				$this->out('REVIEW '.$project['Project']['id'].': '.count($project['SurveyCountry']).' legacy countries detected');
			}
			$countries = Hash::extract($project, 'SurveyCountry.{n}.country');
			$countries = array_unique($countries);
			if (count($countries) > 1) {
				$this->out('REVIEW '.$project['Project']['id'].': '.count($countries).' unique legacy countries detected');
			}
			elseif (count($countries) == 1) {
				$legacy_country = current($countries);
				if ($legacy_country != $project['Project']['country']) {
					$this->out('REVIEW '.$project['Project']['id'].': legacy country ('.$legacy_country.') does not match project country ('.$project['Project']['country'].')');
				}
			}
		}
	}
	
	// LOI data migration between projects to survey_visit_cache should be 1:1
	public function check_loi_data() {
		
	}
	
	// just check the country data prior to pushing it
	public function double_check_country_data() {
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Project.country'),
			'conditions' => array(
				'Project.country' => 'US',
				'Project.id >=' => '330000'
			),
			'recursive' => -1
		));
		foreach ($projects as $project) {
			if ($project['Project']['country'] != 'US') {
				$this->out($project['Project']['id']);
			}
		}
	}
	
	public function transactions() {
		if (!isset($this->args[0]) || !isset($this->args[1])) {
			$this->out('Please set TRANSACTION_ID and TYPE (accept, reject, view)');
			return false;
		} 
		

		$transactionSource = $this->Transaction->getDataSource();
		$transactionSource->begin();
		$transaction = $this->Transaction->find('first', array(
			'conditions' => array(
				'Transaction.id' => $this->args[0],
				'Transaction.deleted' => null
			)
		));
		print_r($transaction); 
		if ($this->args[1] == 'accept') {
			$this->Transaction->approve($transaction);
			$this->out('Transaction approved');
		}
		elseif ($this->args[1] == 'reject') {
			$this->Transaction->reject($transaction);
			$this->out('Transaction rejected');
		}
		$transactionSource->commit();
	}
	
	// demonstrates how updating a transaction works with delete+create mechanism
	public function update_transaction() {
		$transaction = $this->Transaction->find('first', array(
			'conditions' => array(
				'Transaction.id' => 611
			),
			'recursive' => -1
		));
		
		// note: this will be the new beforeDelete() method in Transaction model
		$this->Transaction->delete($transaction['Transaction']['id']); 
		
		// unset values that will not be carried over to new transaction record
		unset($transaction['Transaction']['id']);
		unset($transaction['Transaction']['deleted']);
		unset($transaction['Transaction']['user_balance']);
		$transaction['Transaction']['original_id'] = $transaction['Transaction']['id']; // for linking transactions
		
		// unset this so the modified timestamp is updated upon save; created should retain original value
		unset($transaction['Transaction']['modified']); 
		
		$this->Transaction->create();
		$this->Transaction->save($transaction); 
	}

	// try generating a withdrawal report between 01/01/2016 - 12/31/2017
	public function generate_withdrawal_report() {
		/* -- getting new report id */
		$transactionReportSource = $this->TransactionReport->getDataSource();
		$transactionReportSource->begin();
		$this->TransactionReport->create();
		$this->TransactionReport->save(array('TransactionReport' => array(
			'user_id' => 1,
			'date_from' => '2016-01-01 00:00:00',
			'date_to' => '2017-12-31 23:59:59'
		)));
		$withdrawal_report_id = $this->TransactionReport->getInsertId();
		$transactionReportSource->commit();
		$this->out('Withdrawal report (id: '. $withdrawal_report_id . ') generation started.');
		/* getting new report id -- */

		// fabricating bash query for running withdrawal report generation command
		$query = ROOT . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Console' . DIRECTORY_SEPARATOR . 'cake withdrawals export_withdrawal_data ' . $withdrawal_report_id . ' "2016-01-01 00:00:00" "2017-12-31 23:59:59"';
		$query.= " > /dev/null 2>&1 &";

		// execution begins
		$this->out('Executing command ...');
		$this->out($query);
		exec($query);
		$this->out('Generation Complete !');
	}

	/*
	 * demonstrates how the history of withdrawal `status` change gets logged
	 */
	public function update_withdrawal_status() {
		$withdrawal = $this->Withdrawal->find('first', array(
			'order' => array('id' => 'DESC'),
			'recursive' => -1
		));
		$original_status = $withdrawal['Withdrawal']['status'];
		$this->out('Testing with withdrawal record (id: ' . $withdrawal['Withdrawal']['id'] . ') ...');
		$this->out('Original status: `' . $original_status . '`');

		/* -- 1. change withdrawal status to N/A */
		$this->Withdrawal->create();
		$this->Withdrawal->save(array('Withdrawal' => array(
			'id' => $withdrawal['Withdrawal']['id'],
			'status' => WITHDRAWAL_NA
		)), true, array('status'));
		$this->out('Changed to: `' . WITHDRAWAL_NA . '`');
		/* 1. change withdrawal status to N/A -- */

		/* -- 2. change withdrawal status to `Payout Unprocessed` */
		$this->Withdrawal->create();
		$this->Withdrawal->save(array('Withdrawal' => array(
			'id' => $withdrawal['Withdrawal']['id'],
			'status' => WITHDRAWAL_PAYOUT_UNPROCESSED
		)), true, array('status'));
		$this->out('Changed to: `' . WITHDRAWAL_PAYOUT_UNPROCESSED . '`');
		/* 2. change withdrawal status to `Payout Unprocessed` -- */

		/* -- 3. change withdrawal status to `Payout Succeeded` */
		$this->Withdrawal->create();
		$this->Withdrawal->save(array('Withdrawal' => array(
			'id' => $withdrawal['Withdrawal']['id'],
			'status' => WITHDRAWAL_PAYOUT_SUCCEEDED
		)), true, array('status'));
		$this->out('Changed to: `' . WITHDRAWAL_PAYOUT_SUCCEEDED . '`');
		/* 3. change withdrawal status to `Payout Succeeded` -- */

		/* -- 4. revert back to original status */
		$this->Withdrawal->create();
		$this->Withdrawal->save(array('Withdrawal' => array(
			'id' => $withdrawal['Withdrawal']['id'],
			'status' => $original_status
		)), true, array('status'));
		$this->out('Reverted back to original status.');
		/* 4. revert back to original status -- */

		/* -- validating withdrawal_status_log records */
		$withdrawal_status_logs = $this->WithdrawalStatusLog->find('all', array(
			'conditions' => array(
				'withdrawal_id' => $withdrawal['Withdrawal']['id']
			),
			'limit' => 4,
			'order' => array('id' => 'DESC'),
			'recursive' => -1
		));
		$this->out('--- Withdrawal status logs ---');
		$this->out('...');
		for ($i = 3; $i >= 0; $i --) {
			$this->out($withdrawal_status_logs[$i]['WithdrawalStatusLog']['created'] . ": `" . 
				$withdrawal_status_logs[$i]['WithdrawalStatusLog']['old_status'] . "` -> `" . $withdrawal_status_logs[$i]['WithdrawalStatusLog']['new_status'] . "`");
		}
		$this->out('--- log ends ---');
		/* validating withdrawal_status_log records -- */

		$this->out('Testing complete !');
	}

	/*
	 * demonstrates the beforeSave(), afterSave() callback of `Withdrawal` model
	 */
	public function update_withdrawal_record() {
		$old_withdrawal = $this->Withdrawal->find('first', array(
			'order' => array('id' => 'DESC'),
			'recursive' => -1
		));
		
		$old_transaction = $this->Transaction->find('first', array(
			'conditions' => array('id' => $old_withdrawal['Withdrawal']['transaction_id']),
			'order' => array('id' => 'DESC'),
			'recursive' => -1
		));
		if (!$old_transaction) {
			$this->out('Transaction record not found!');
			return;
		}

		$this->out('- Original withdrawal record: ');
		print_r($old_withdrawal);
		$this->out('- Original transaction record: ');
		print_r($old_transaction);

		/* -- trying changing `withdrawal` record */
		$this->Withdrawal->create();
		$this->Withdrawal->save(array('Withdrawal' => array(
			'id' => $old_withdrawal['Withdrawal']['id'],
			'status' => WITHDRAWAL_PAYOUT_SUCCEEDED,
			'amount_cents' => -100,
			'note' => 'Test note',
			'approved' => '2017-06-28'
		)), true, array('status', 'amount_cents', 'note', 'approved'));
		/* trying changing `withdrawal` record -- */

		/* -- testing result */
		$this->out('Changes applied to `withdrawal` record !');
		$this->out('- Updated withdrawal record: ');
		$new_withdrawal = $this->Withdrawal->find('first', array(
			'conditions' => array('id' => $old_withdrawal['Withdrawal']['id']),
			'order' => array('id' => 'DESC'),
			'recursive' => -1
		));
		print_r($new_withdrawal);
		
		$this->out('- Updated transaction record: ');
		$new_transaction = $this->Transaction->find('first', array(
			'conditions' => array('id' => $old_withdrawal['Withdrawal']['transaction_id']),
			'order' => array('id' => 'DESC'),
			'recursive' => -1
		));
		print_r($new_transaction);

		if ($new_transaction['Transaction']['status'] == TRANSACTION_APPROVED && $new_transaction['Transaction']['paid'] == PAYOUT_SUCCEEDED) {
			$this->out('Status changed successfully.');
		}
		else {
			$this->out('Err: Status not changed.');
		}
		if ($new_transaction['Transaction']['name'] == 'Test note') {
			$this->out('Note changed successfully.');
		}
		else {
			$this->out('Err: Name not changed.');
		}
		if ($new_transaction['Transaction']['amount'] == -100) {
			$this->out('Amount changed successfully.');
		}
		else {
			$this->out('Err: Amount not changed.');
		}
		/* testing result -- */
		
		/* -- reverting back */
		$this->Withdrawal->create();
		$this->Withdrawal->save($old_withdrawal);
		/* reverting back -- */

		$this->out('Testing complete !');
	}

	public function send_payout_sqs_message() {
		if (!isset($this->args[0])) {
			return false; 
		}
		$withdrawal_id = $this->args[0];

		/* -- SQS setting */
		App::import('Vendor', 'sqs');
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('sqs.access.key', 'sqs.access.secret', 'sqs.payout.queue'),
				'Setting.deleted' => false
			)
		));
		if (count($settings) < 3) {
			$this->Session->setFlash('Your withdrawals will not process due to missing SQS keys. Do NOT attempt to process payouts!', 'flash_error');
		}
		$sqs = new SQS($settings['sqs.access.key'], $settings['sqs.access.secret']);
		/* SQS setting -- */

		$sqs->sendMessage($settings['sqs.payout.queue'], $withdrawal_id);
		$this->out('Sent payout request for withdrawal (id: ' . $withdrawal_id . ').');
	}
	
	/* 
	http://api.thepanelstation.com/api/Vendor/GetSampleStatus
	http://api.thepanelstation.com/api/Vendor/GetSurveyTopic
	http://api.thepanelstation.com/api/Vendor/GetProjectdetails?Projectstatus=live
	http://api.thepanelstation.com/api/Vendor/GetProjectSampleQuota?projectID=<#ProjectID#>&sampleID=<#SampleID#>
	http://api.thepanelstation.com/api/Vendor/GetVendorQuestionAnswer
	http://api.thepanelstation.com/api/Vendor/GetProjectStatus
	*/
	public function borderless() {
		App::uses('HttpSocket', 'Network/Http');
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$http->configAuth('Basic', 'Branded API', 'Ptfnd&8$hj');
		$results = $http->get('http://api.thepanelstation.com/api/Vendor/GetProjectdetails?Projectstatus=Live', array(
			'Key' => 'FA25E24F-5DD6-44A8-92D8-9088DD0A4B5E'
		), array(
			'header' => array('Content-Type' => 'application/json')
		));
		//$results = json_decode($results['body'], true);
		print_r($results);
	}
	
	public function socialglimpz_data() {
		if (!isset($this->args[0])) {
			$this->out('Input a date: YYYY-MM-DD');
			return false; 
		}
		
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'socialglimpz'
			)
		)); 
		
		$date = date(DB_DATE, strtotime(trim($this->args[0])));
		$start_date = $date.' 00:00:00';
		$end_date = $date.' 23:59:59';
		$conditions = array(
			'Project.group_id' => $group['Group']['id'],
			'OR' => array(
				// projects started before and ended after selected dates
				array(
					'Project.started <=' => $start_date,
					'Project.ended >=' => $start_date
				),
				// projects started and ended during the duration of the selected date
				array(
					'Project.started >=' => $start_date,
					'Project.ended <=' => $end_date
				),
				// projects started before the end date but ending much later
				array(
					'Project.started <=' => $end_date,
					'Project.ended >=' => $end_date
				),
				// projects that are still open
				array(
					'Project.started <=' => $end_date,
					'Project.ended is null'
				),
				// addressing https://basecamp.com/2045906/projects/1413421/todos/206702078
				array(
					'Project.ended LIKE' => $date.'%'
				) 
			)
		); 

		$this->Project->unbindModel(array(
			'hasMany' => array('SurveyPartner', 'ProjectOption', 'ProjectAdmin'),
		));
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Project.subquals', 'Project.margin_cents', 'Project.margin_pct', 'Client.client_name', 'Project.temp_qualifications', 'Project.status', 'Project.active', 'Project.client_rate', 'Project.award', 'Project.started', 'Project.ended', 'Project.mask', 'Project.country', 'Project.est_length', 'Group.name', 'Group.key', 'SurveyVisitCache.loi_seconds'),
			'conditions' => $conditions,
			'contain' => array(
				'Client',
				'Group',
				'SurveyVisitCache'
			)
		));
		
		$survey_users = $this->SurveyUser->find('all', array(
			'conditions' => array(
				'SurveyUser.survey_id' => Hash::extract($projects, '{n}.Project.id'),
				'SurveyUser.created >=' => $start_date,
				'SurveyUser.created <=' => $end_date
			),
			'recursive' => -1
		));

		$file = WWW_ROOT . 'files/socialglimpz_'.$start_date.'.csv'; 
		$fp = fopen($file, 'w');
		fputcsv($fp, array(
			'Invite Issued',
			'User ID',
			'Project ID',
			'Next Router Session',
			'Next Matched Project ID',
			'Last Touched',
			'Router Project Entry',
			'User Agent',
			'Router ID',
		));
		foreach ($survey_users as $survey_user) {
			$user_router_log = $this->UserRouterLog->find('first', array(
				'conditions' => array(
					'UserRouterLog.parent_id' => '0',
					'UserRouterLog.created >=' => $survey_user['SurveyUser']['created'],
					'UserRouterLog.user_id' => $survey_user['SurveyUser']['user_id']
				),
				'order' => 'UserRouterLog.created ASC'
			)); 
			$user = $this->User->find('first', array(
				'fields' => array('User.last_touched'),
				'conditions' => array(
					'User.id' => $survey_user['SurveyUser']['user_id']
				),
				'recursive' => -1
			)); 
			
			$survey_visit = $info = false;
			if ($user_router_log) {
				$survey_visit = $this->SurveyVisit->find('first', array(
					'fields' => array('SurveyVisit.info', 'SurveyVisit.created'),
					'conditions' => array(
						'SurveyVisit.user_id' => $survey_user['SurveyUser']['user_id'],
						'SurveyVisit.survey_id' => $user_router_log['UserRouterLog']['survey_id'], 
						'SurveyVisit.type' => SURVEY_CLICK
					)
				)); 
				if ($survey_visit) {
					$info = Utils::print_r_reverse($survey_visit['SurveyVisit']['info']);
				}
			}
			fputcsv($fp, array(
				$survey_user['SurveyUser']['created'],
				$survey_user['SurveyUser']['user_id'],
				$survey_user['SurveyUser']['survey_id'],
				$user_router_log ? $user_router_log['UserRouterLog']['created']: '-',
				$user_router_log ? $user_router_log['UserRouterLog']['survey_id']: '-',
				$user['User']['last_touched'],
				$survey_visit ? $survey_visit['SurveyVisit']['created']: '-',
				$survey_visit ? $info['HTTP_USER_AGENT']: '-',	
				$user_router_log ? $user_router_log['UserRouterLog']['id']: '-'			
			));
		}
		
		fclose($fp);
		
		$this->out('Wrote '.$file); 
	}
	
	public function mobile_detect() {
		App::import('Vendor', 'Mobile_Detect', array('file' => 'MobileDetect' .DS. 'Mobile_Detect.php'));

		$detect = new Mobile_Detect();

		$user_agents = array(
			'Mozilla/5.0 (iPhone; CPU iPhone OS 10_3_2 like Mac OS X) AppleWebKit/603.2.4 (KHTML, like Gecko) Version/10.0 Mobile/14F89 Safari/602.1'
		);

		foreach ($user_agents as $user_agent) {
			$detect->setUserAgent($user_agent);
			$this->out($user_agent.': '.($detect->isMobile() ? 'mobile': 'not mobile')); 
		}	
	}

	public function populate_test_withdrawals() {
		$this->Transaction->bindItems(false);
		$this->Transaction->bindModel(array(
			'belongsTo' => array(
				'PaymentMethod' => array(
					'foreignKey' => 'linked_to_id'
				)
			)
		));
		
		$transactions = $this->Transaction->find('all', array(
			'conditions' => array(
				'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
				'Transaction.deleted' => null
			),
			'contain' => array('PaymentMethod'),
			'order' => 'Transaction.id ASC',
			'limit' => '5'
		));
		if (!$transactions) {
			return;
		}
		$this->out('Found '.count($transactions).' transactions.');

		foreach ($transactions as $transaction) {
			$withdrawal = $this->Withdrawal->find('first', array(
				'conditions' => array(
					'Withdrawal.transaction_id' => $transaction['Transaction']['id']
				)
			));

			if ($withdrawal) {
				continue; // Highly unlikely to go thorugh this statement, We should skip it, as we keep ProjectOption value to avoid redundency
			}

			/* -- determine the status of withdrawal record */
			$status = WITHDRAWAL_NA;
			$approved = null;
			$processed = null;
			$paid_amount_cents = null;
			if ($transaction['Transaction']['status'] == TRANSACTION_PENDING) {
				$status = WITHDRAWAL_PENDING;
			}
			elseif ($transaction['Transaction']['status'] == TRANSACTION_REJECTED) {
				$status = WITHDRAWAL_REJECTED;
			}
			elseif ($transaction['Transaction']['status'] == TRANSACTION_APPROVED) {
				$approved = $transaction['Transaction']['executed'];

				if ($transaction['Transaction']['payout_processed'] == PAYOUT_UNPROCESSED) {
					$status = WITHDRAWAL_PAYOUT_UNPROCESSED;
				}
				elseif ($transaction['Transaction']['payout_processed'] == PAYOUT_SUCCEEDED) {
					$status = WITHDRAWAL_PAYOUT_SUCCEEDED;
					$processed = $transaction['Transaction']['executed'];
					$paid_amount_cents = $transaction['Transaction']['amount'];
				}
				elseif ($transaction['Transaction']['payout_processed'] == PAYOUT_FAILED) {
					$status = WITHDRAWAL_PAYOUT_FAILED;
				}
			}
			/* determine the status of withdrawal record -- */

			$this->Withdrawal->create();
			$this->Withdrawal->save(array('Withdrawal' => array(
				'user_id' => $transaction['Transaction']['user_id'],
				'transaction_id' => $transaction['Transaction']['id'],
				'payment_identifier' => $transaction['PaymentMethod']['id'], // for tango, we are referring 2 values from the table, name & sku
				'payment_type' => $transaction['PaymentMethod']['payment_method'],
				'amount_cents' => $transaction['Transaction']['amount'],
				'paid_amount_cents' => $paid_amount_cents, // for `WITHDRAWAL_PAYOUT_SUCCEEDED` status, set paid_amount_cents equal to amount_cents
				'status' => $status,
				'note' => $transaction['Transaction']['name'],
				'deleted' => $transaction['Transaction']['deleted'],
				'scheduled' => null,
				'approved' => $approved,
				'processed' => $processed					
			)));

			$this->out('Transferred [transaction_id : ' . $transaction['Transaction']['id'] . ']');
		}

		$this->out('Complete!');
	}

}
