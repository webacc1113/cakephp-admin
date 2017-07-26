<?php
App::uses('Shell', 'Console');
App::import('Lib', 'Utilities');
App::uses('HttpSocket', 'Network/Http');

class UserShell extends AppShell {
	var $uses = array('HellbanLog', 'GeoZip', 'UserAcquisition', 'IpProxy', 'UserIp', 'User', 'QueryProfile', 'GeoZip', 'Source', 'UserRestore', 'SurveyUserVisit', 'SurveyVisit', 'SurveyFlag', 'SurveyReport', 'Transaction', 'GeoCountry', 'GeoState', 'PaymentMethod', 'SourceReport', 'SourceMapping', 'UserLog', 'PollUserAnswer', 'CodeRedemption', 'BalanceMismatch', 'UserNotificationReport');
	
	public $tasks = array('UserAnalyzer');
	
	public function main() {
	}
	
	// refire segment data for a panelist
	public function regenerate_segment_data() {
		
		if (isset($this->args[0])) {
			$users = $this->User->find('all', array(
				'conditions' => array(
					'User.id' => $this->args[0]
				)
			));
		}
		else {
			$users = $this->User->find('all', array(
				'conditions' => array(
					'User.last_touched >=' => date(DB_DATETIME, strtotime('-2 weeks'))
				)
			));
		}
		
		$this->out('Found '.count($users).' records');
		
		/* 
			These are all the fields that need to be re-pushed
			'aid',
			'source',
			'medium',
			'campaign',
			'term',
			'referral_userid',
			'referrer_url',
			'acquisition_partner',
			'lander',
			'logins',
			'last_login',
			'surveys_started',
			'surveys_completed',
			'surveys_nq',
			'offers_completed',
			'last_touch',
			'polls_completed',
			'promos_redeemed',
			'unsubscribe_date'
		 */
		
		if ($users) {
			foreach ($users as $user) {
				$traits = array();
				if (!empty($user['QueryProfile']['gender'])) {
					$traits = array(
						'email' => $user['User']['email'],
						'first_name' => $user['User']['firstname'],
						'last_name' => $user['User']['lastname'],
						'gender' => $user['QueryProfile']['gender'],
						'country' => $user['QueryProfile']['country'],
						'created_at' => strtotime($user['User']['created']),
						'registration_date' => date('m/d/y', strtotime($user['User']['created']))
					);
				} 
				
				$user_acquisition = $this->UserAcquisition->find('first', array(
					'conditions' => array(
						'UserAcquisition.user_id' => $user['User']['id']
					)
				));
				
				if ($user_acquisition) {
					$traits['aid'] = (int) $user_acquisition['UserAcquisition']['id'];
					if (!empty($user_acquisition['UserAcquisition']['source'])) {
						$traits['source'] = $user_acquisition['UserAcquisition']['source'];
					}
					
					if (!empty($user_acquisition['UserAcquisition']['params'])) {
						$params = $user_acquisition['UserAcquisition']['params'];
						if (isset($params['utm_medium']) && !empty($params['utm_medium'])) {
							$traits['medium'] = $params['utm_medium'];
						}
						
						if (isset($params['utm_campaign']) && !empty($params['utm_campaign'])) {
							$traits['campaign'] = $params['utm_campaign'];
						}
						
						if (isset($params['utm_term']) && !empty($params['utm_term'])) {
							$traits['term'] = $params['utm_term'];
						}
						
						if (isset($params['lander']) && !empty($params['lander'])) {
							$traits['lander'] = $params['lander'];
						}
						
						if (isset($params['acquisition_partner']) && !empty($params['acquisition_partner'])) {
							$traits['acquisition_partner'] = $params['acquisition_partner'];
						}
					}
				}
				
				$traits['logins'] = $this->UserLog->find('count', array(
					'conditions' => array(
						'UserLog.user_id' => $user['User']['id'],
						'UserLog.type' => 'user.authenticated'
					)
				));
				
				if (!empty($user['User']['login'])) {
					$traits['last_login'] = $user['User']['login'];
				}
				
				$traits['surveys_started'] = $this->SurveyUserVisit->find('count', array(
					'conditions' => array(
						'SurveyUserVisit.user_id' => $user['User']['id']
					),
					'recursive' => -1
				));
				
				$traits['surveys_completed'] = $this->SurveyUserVisit->find('count', array(
					'conditions' => array(
						'SurveyUserVisit.user_id' => $user['User']['id'],
						'SurveyUserVisit.status' => SURVEY_COMPLETED
					),
					'recursive' => -1
				));
				
				$traits['surveys_nq'] = $this->SurveyUserVisit->find('count', array(
					'conditions' => array(
						'SurveyUserVisit.user_id' => $user['User']['id'],
						'SurveyUserVisit.status' => array(SURVEY_NQ, SURVEY_INTERNAL_NQ, SURVEY_NQ_FRAUD, SURVEY_NQ_SPEED, SURVEY_NQ_EXCLUDED)
					),
					'recursive' => -1
				));
				
				$traits['offers_completed'] = $this->Transaction->find('count', array(
					'conditions' => array(
						'Transaction.user_id' => $user['User']['id'],
						'Transaction.type_id' => TRANSACTION_OFFER,
						'Transaction.deleted' => null,
					),
					'recursive' => -1
				));
				
				if (!empty($user['User']['last_touched'])) {
					$traits['last_touch'] = $user['User']['last_touched'];
				}
				
				$traits['polls_completed'] = $this->PollUserAnswer->find('count', array(
					'conditions' => array(
						'PollUserAnswer.user_id' => $user['User']['id']
					),
					'recursive' => -1
				));
				
				$traits['promos_redeemed'] = $this->CodeRedemption->find('count', array(
					'conditions' => array(
						'CodeRedemption.user_id' => $user['User']['id']
					),
					'recursive' => -1
				));
				
				$user_log = $this->UserLog->find('first', array(
					'conditions' => array(
						'UserLog.user_id' => $user['User']['id'],
						'UserLog.type' => 'user.unsubscribed',
					)
				));
				if ($user_log) {
					$traits['unsubscribe_date'] = $user_log['UserLog']['created'];
				}
								
				if (empty($user['User']['segment_identify'])) {
					$identify = array(
						'userId' => $user['User']['id'],
						'traits' => array()
					);
				}
				else {
					$identify = json_decode($user['User']['segment_identify'], true);
				}

				// merge the values into the identify object
				$identify['traits'] = array_merge($identify['traits'], $traits);
				
				$this->User->create();
				$this->User->save(array('User' => array(
					'id' => $user['User']['id'],
					'segment_identify' => json_encode($identify), 
					'modified' => false,
					'segment_refire' => true
				)), true, array('segment_identify', 'segment_refire'));
				
				$this->out('User# '.$user['User']['id']. ' regenerated');
			}
		}
	}
	
	// given a campaign source, will export all users into a CSV format
	public function export_source_data() {
		App::import('Model', 'UserAcquisitionData');
		$this->UserAcquisitionData = new UserAcquisitionData; 
		
		ini_set('memory_limit', '1024M');
		if (!isset($this->args[0]) || !isset($this->args[1]) || !isset($this->args[2])) {
			return false; 
		}
		
		$report_id = $this->args[2];
		
		
		$time_start = microtime(true);
		
		if (isset($this->args[0]) && $this->args[0] == 'mapping') {
			$source_mapping = $this->SourceMapping->find('first', array(
				'conditions' => array(
					'SourceMapping.id' => $this->args[1]
				)
			));	
					
			if (!$source_mapping) {
				return false;
			}
		}
		elseif (isset($this->args[0]) && $this->args[0] == 'source') {
			$source = $this->Source->find('first', array(
				'conditions' => array(
					'Source.id' => $this->args[1]
				)
			));	
			if (!$source) {
				return false;
			}
		}
		
		if (isset($this->args[0]) && $this->args[0] == 'source') {
			// write file incrementally so i can see progress
			if (!is_dir(WWW_ROOT . 'files/sources/')) {
				mkdir(WWW_ROOT . 'files/sources/');
			}
			$filename = str_replace(':', '_', $source['Source']['abbr']) .'_'. date('Y-m-d') .'_'. time() .'.csv';
			$file_dir_path = 'files/sources/'.$filename;
			$conditions = array(
				'User.origin' => $source['Source']['abbr']
			);
		}
		elseif(isset($this->args[0]) && $this->args[0] == 'mapping') {
			// write file incrementally so i can see progress
			if (!is_dir(WWW_ROOT . 'files/source_mappings/')) {
				mkdir(WWW_ROOT . 'files/source_mappings/');
			}
			$filename = str_replace(':', '_', $source_mapping['SourceMapping']['utm_source']) .'_'. date('Y-m-d') .'_'. time() .'.csv';
			$file_dir_path = 'files/source_mappings/'.$filename;
			$conditions = array(
				'User.origin' => $source_mapping['SourceMapping']['utm_source']
			);
		}
		
		$file = WWW_ROOT . $file_dir_path;
		$fp = fopen($file, 'w');
		if (isset($this->args[3]) && isset($this->args[4])) {
			$conditions['OR'][] = array(
				'User.created >=' => $this->args[3],
				'User.created <=' => $this->args[4]
			);
			$conditions['OR'][] = array(
				'User.verified >=' => $this->args[3],
				'User.verified <=' => $this->args[4]
			);
			$conditions['OR'][] = array(
				'User.hellbanned_on >=' => $this->args[3],
				'User.hellbanned_on <=' => $this->args[4]
			);
		}
		elseif (isset($this->args[3])) {
			$conditions['OR'] = array(
				'User.created >=' => $this->args[3],
				'User.verified >=' => $this->args[3],
				'User.hellbanned_on >=' => $this->args[3],
			);
		}
		
		$users = $this->User->find('all', array(
			'fields' => array(
				'User.id', 'User.pub_id', 'User.origin', 'User.hellbanned_on', 'User.created', 'User.verified', 'User.first_survey', 'User.send_email', 'User.last_touched', 'QueryProfile.gender'
			),
			'conditions' => $conditions
		));	
		
		$total = count($users);
		echo 'Found '.$total."\n";
		
		fputcsv($fp, array(
			'ID', 
			'Campaign', 
			'Publisher ID',
			'Registered',
			'Verified',
			'Hellbanned',
			'Unsubscribed',
			'First Survey',
			'First Complete',
			'First Withdrawal',
			'Total Survey Points',
			'Total Withdrawals (Points)',
			'Gender',
			'Last Active',
			'Offerwall Points'
		)); 
		
		foreach ($users as $key => $user) {
			$user_acquisition_data = $this->UserAcquisitionData->find('first', array(
				'conditions' => array(
					'UserAcquisitionData.user_id' => $user['User']['id']
				)
			));
			$first_complete = $first_withdrawal = $first_survey = null;
			$total_withdrawal_points = $total_survey_points = 0;
			if (!$user_acquisition_data || strtotime('-1 day') > strtotime($user_acquisition_data['UserAcquisitionData']['modified'])) {
				
				// first withdrawal
				$data = $this->Transaction->find('first', array(
					'conditions' => array(
						'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
						'Transaction.user_id' => $user['User']['id'],
						'Transaction.deleted' => null,
					),
					'order' => 'Transaction.id ASC'
				));
				if ($data) {
					$first_withdrawal = $data['Transaction']['created']; 
				}
				
				// total withdrawal amount
				$data = $this->Transaction->find('first', array(
					'fields' => array('SUM(amount) as sum_amount'),
					'conditions' => array(
						'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
						'Transaction.user_id' => $user['User']['id'],
						'Transaction.deleted' => null,
					)
				));
				if ($data) {
					$total_withdrawal_points = empty($data[0]['sum_amount']) ? 0: $data[0]['sum_amount'] * -1;
				}
				
				// total survey points
				$data = $this->Transaction->find('first', array(
					'fields' => array('SUM(amount) as sum_amount'),
					'conditions' => array(
						'Transaction.type_id' => TRANSACTION_SURVEY,
						'Transaction.user_id' => $user['User']['id'],
						'Transaction.deleted' => null,
					)
				));
				if ($data) {
					$total_survey_points = empty($data[0]['sum_amount']) ? 0: $data[0]['sum_amount'];
				}
				
				// first survey click
				$data = $this->SurveyUserVisit->find('first', array(
					'conditions' => array(
						'SurveyUserVisit.user_id' => $user['User']['id'],
					),
					'fields' => array('SurveyUserVisit.created'),
					'order' => 'SurveyUserVisit.id ASC'
				));
				if ($data) {
					$first_survey = $data['SurveyUserVisit']['created'];
				}
				
				// first complete date
				$data = $this->SurveyUserVisit->find('first', array(
					'conditions' => array(
						'SurveyUserVisit.user_id' => $user['User']['id'],
						'SurveyUserVisit.status' => SURVEY_COMPLETED
					),
					'fields' => array('id', 'created'),
					'order' => 'SurveyUserVisit.id ASC'
				));
				if ($data) {
					$first_complete = $data['SurveyUserVisit']['created'];
				}
			}
			else {
				$first_complete = $user_acquisition_data['UserAcquisitionData']['first_complete'];
				$first_withdrawal = $user_acquisition_data['UserAcquisitionData']['first_withdrawal'];
				$total_withdrawal_points = $user_acquisition_data['UserAcquisitionData']['total_withdrawals'];
				$total_survey_points = $user_acquisition_data['UserAcquisitionData']['total_survey_points'];
				$first_survey = $user_acquisition_data['UserAcquisitionData']['first_survey'];
			}
			$data = $this->Transaction->find('first', array(
				'conditions' => array(
					'Transaction.type_id' => array(TRANSACTION_OFFER, TRANSACTION_GROUPON),
					'Transaction.user_id' => $user['User']['id'],
					'Transaction.deleted' => null,
				),
				'fields' => array('sum(Transaction.amount) as sum_amount')				
			));
			
			echo(($key + 1).' / '.$total."\n");
			
			fputcsv($fp, array(
				$user['User']['id'],
				!empty($source['Source']['name']) ? $source['Source']['name'] : $source_mapping['SourceMapping']['name'],
				$user['User']['pub_id'],
				$user['User']['created'],
				$user['User']['verified'],
				$user['User']['hellbanned_on'],
				!$user['User']['send_email'] ? 'Y': 'N',
				$first_survey,
				$first_complete,
				$first_withdrawal,
				$total_survey_points,
				$total_withdrawal_points,
				$user['QueryProfile']['gender'],
				$user['User']['last_touched'],
				$data[0]['sum_amount']
			));
			
			// doesn't exist, let's write it
			if (!$user_acquisition_data) {
				$this->UserAcquisitionData->create();
				$this->UserAcquisitionData->save(array('UserAcquisitionData' => array(
					'user_id' => $user['User']['id'],
					'source' => !empty($source['Source']['abbr']) ? $source['Source']['abbr'] : $source_mapping['SourceMapping']['utm_source'],
					'publisher' => $user['User']['pub_id'],
					'registered' => $user['User']['created'],
					'verified' => $user['User']['verified'],
					'first_survey' => $first_survey,
					'first_complete' => $first_complete,
					'first_withdrawal' => $first_withdrawal,
					'total_survey_points' => $total_survey_points,
					'total_withdrawals' => $total_withdrawal_points
				)));
			}
		}
		
		$diff = microtime(true) - $time_start; 
		$body = 'Report with '.$total.' records generated - execution time '.round($diff).' seconds';
		
		if (isset($report_id)) {
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
			
			$aws_filename = $file_dir_path;
			echo 'Writing to S3 '.$aws_filename.' from '.$file."\n";
			$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);	
			$headers = array(
				'Content-Disposition' => 'attachment; filename='.$filename.'.csv'
			);
			if ($S3->putObject($S3->inputFile($file), $settings['s3.bucket'] , $aws_filename, S3::ACL_PRIVATE, array(), $headers)) {
					$this->SourceReport->create();
					$this->SourceReport->save(array('SourceReport' => array(
						'id' => $report_id,
						'path' => $aws_filename,
						'status' => 'complete'
					)), true, array('path', 'status'));	
				//unlink($file);
			}
		}
	}
	
	public function analyze() {
		if (isset($this->args[0])) {
			$user = $this->User->findById($this->args[0]);
			$user_analysis = $this->UserAnalyzer->analyze($user, isset($this->args[1]) ? $this->args[1]: null); 
			print_r($user_analysis);
		}
	}
	public function reanalyze() {
		
		$withdrawals = $this->Transaction->find('all', array(
			'conditions' => array(
				'Transaction.status' => TRANSACTION_PENDING,
				'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
				'Transaction.deleted' => null,
			)
		));
		if ($withdrawals) {
			foreach ($withdrawals as $withdrawal) {
				print_r($withdrawal);
				$user = $this->User->findById($withdrawal['User']['id']);
				$user_analysis = $this->UserAnalyzer->analyze($user, $withdrawal['Transaction']['id']);
			}
		}
	}
	
	public function export() {		
		App::import('Vendor', 'SiteProfile');
		ini_set('memory_limit', '2048M');
		
		$users = $this->User->find('all', array(
			'fields' => array(
				'User.id', 'QueryProfile.gender', 'QueryProfile.birthdate', 
				'QueryProfile.country', 'QueryProfile.state', 'QueryProfile.postal_code', 'QueryProfile.dma_code', 
 				'QueryProfile.hhi', 'QueryProfile.education', 'QueryProfile.children', 'QueryProfile.employment', 'QueryProfile.industry', 
				'QueryProfile.organization_size', 'QueryProfile.organization_revenue', 'QueryProfile.job', 'QueryProfile.department', 
				'QueryProfile.relationship', 'QueryProfile.ethnicity', 'QueryProfile.hispanic', 'QueryProfile.housing_own', 'QueryProfile.smartphone', 
				'QueryProfile.tablet'
			),
			'conditions' => array(
				'QueryProfile.country' => 'US', 
				'User.active' => true,
				'User.hellbanned' => false
			)
		));
		$data = array(array(
			'MintVine ID',
			'Birthdate',
			'Gender',
			'Country',
			'Postal Code',
			'hhi',
			'education', 
			'ethnicity', 
			'relationship', 
			'employment',
			'industry', 
			'department', 
			'job', 
			'housing_own',
			'children',
			'organization_size', 
			'organization_revenue'
		));
		$qp_data = array(
			'hhi' => unserialize(USER_HHI),
			'education' => unserialize(USER_EDU),
			'ethnicity' => unserialize(USER_ETHNICITY),
			'relationship' => unserialize(USER_MARITAL),
			'employment' => unserialize(USER_EMPLOYMENT),
			'industry' => unserialize(USER_INDUSTRY),
			'department' => unserialize(USER_DEPARTMENT),
			'job' => unserialize(USER_JOB),
			'housing_own' => unserialize(USER_HOME),
			'children' => unserialize(USER_CHILDREN),
			'organization_size' => unserialize(USER_ORG_SIZE),
			'organization_revenue' => unserialize(USER_ORG_REVENUE),
		);
		$keys = array_keys($qp_data);
		foreach ($users as $user) {
			$qp_fields = array();
			foreach ($keys as $field) {
				switch ($field) {
					case 'hhi':
						$qp_fields[] = (isset($qp_data['hhi'][$user['QueryProfile']['hhi']])) ? $qp_data['hhi'][$user['QueryProfile']['hhi']] : '';
						break;
					case 'education':
						$qp_fields[] = (isset($qp_data['education'][$user['QueryProfile']['education']])) ? $qp_data['education'][$user['QueryProfile']['education']] : '';
						break;
					case 'ethnicity':
						$qp_fields[] = (isset($qp_data['ethnicity'][$user['QueryProfile']['ethnicity']])) ? $qp_data['ethnicity'][$user['QueryProfile']['ethnicity']] : '';
						break;
					case 'relationship':
						$qp_fields[] = (isset($qp_data['relationship'][$user['QueryProfile']['relationship']])) ? $qp_data['relationship'][$user['QueryProfile']['relationship']] : '';
						break;
					case 'employment':
						$qp_fields[] = (isset($qp_data['employment'][$user['QueryProfile']['employment']])) ? $qp_data['employment'][$user['QueryProfile']['employment']] : '';
						break;
					case 'industry':
						$qp_fields[] = (isset($qp_data['industry'][$user['QueryProfile']['industry']])) ? $qp_data['industry'][$user['QueryProfile']['industry']] : '';
						break;
					case 'department':
						$qp_fields[] = (isset($qp_data['department'][$user['QueryProfile']['department']])) ? $qp_data['department'][$user['QueryProfile']['department']] : '';
						break;
					case 'job':
						$qp_fields[] = (isset($qp_data['job'][$user['QueryProfile']['job']])) ? $qp_data['job'][$user['QueryProfile']['job']] : '';
						break;
					case 'housing_own':
						$qp_fields[] = (isset($qp_data['housing_own'][$user['QueryProfile']['housing_own']])) ? $qp_data['housing_own'][$user['QueryProfile']['housing_own']] : '';
						break;
					case 'housing_purchased':
						$qp_fields[] = (isset($qp_data['housing_purchased'][$user['QueryProfile']['housing_purchased']])) ? $qp_data['housing_purchased'][$user['QueryProfile']['housing_purchased']] : '';
						break;
					case 'housing_plans':
						$qp_fields[] = (isset($qp_data['housing_plans'][$user['QueryProfile']['housing_plans']])) ? $qp_data['housing_plans'][$user['QueryProfile']['housing_plans']] : '';
						break;
					case 'children':
						$qp_fields[] = (isset($qp_data['children'][$user['QueryProfile']['children']])) ? $qp_data['children'][$user['QueryProfile']['children']] : '';
						break;
					case 'organization_size':
						$qp_fields[] = (isset($qp_data['organization_size'][$user['QueryProfile']['organization_size']])) ? $qp_data['organization_size'][$user['QueryProfile']['organization_size']] : '';
						break;
					case 'organization_revenue':
						$qp_fields[] = (isset($qp_data['organization_revenue'][$user['QueryProfile']['organization_revenue']])) ? $qp_data['organization_revenue'][$user['QueryProfile']['organization_revenue']] : '';
						break;
					case 'tablet':
						$qp_fields[] = (isset($qp_data['tablet'][$user['QueryProfile']['tablet']])) ? $qp_data['tablet'][$user['QueryProfile']['tablet']] : '';
						break;
					case 'airlines':
						$qp_fields[] = (isset($qp_data['airlines'][$user['QueryProfile']['airlines']])) ? $qp_data['airlines'][$user['QueryProfile']['airlines']] : '';
						break;
					case 'default':
						$qp_fields[] = '';
						break;
				}
			}
			$user_data = array(
				$user['User']['id'],
				$user['QueryProfile']['birthdate'],
				$user['QueryProfile']['gender'],
				$user['QueryProfile']['country'],
				$user['QueryProfile']['postal_code'],
			);
			$data[] = array_merge($user_data, $qp_fields);
		}
		
		$filename = 'user_export-' . gmdate(DB_DATE, time()) . '.csv';
		$fp = fopen($filename, 'w');
		foreach ($data as $field) {
			fputcsv($fp, $field);
		}
		fclose($fp);
	}
	
	public function fill_ip_data() {
		echo "Beginning...\n";
		$user_ips = $this->UserIp->find('all', array(
			'conditions' => array(
				'OR' => array(
					'UserIp.longitude is null',
					'UserIp.country is null'
				),
			), 
			'limit' => '10000'
		)); 
		echo "Starting...\n";
		foreach ($user_ips as $key => $user_ip) {	
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_URL, 'http://freegeoip.net/json/'.$user_ip['UserIp']['ip_address']);
			$result = curl_exec($ch);	
			$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);		
			$data = json_decode($result);
			
			if ($http_status == 403) {
				echo 'HTTP STATUS ERROR: '.$http_status.' on http://freegeoip.net/json/'.$user_ip['UserIp']['ip_address']."\n";
				break;
			}
			
			// rather have no data than bad or incomplete data
			if ($http_status == 404 || empty($data->country_code) || empty($data->region_code)) {	
				if (is_null($user_ip['UserIp']['country'])) {			
					$this->UserIp->create();
					$this->UserIp->save(array(
						'id' => $user_ip['UserIp']['id'],
						'country' => '',
					), true, array('country'));
					echo 'Mapped '.$user_ip['UserIp']['id'].' to nothing'."\n";
				}
				continue;
			}
			
			$this->UserIp->create();
			
			// if we haven't found the country - set additional data
			if (is_null($user_ip['UserIp']['country'])) {
				$this->UserIp->save(array(
					'id' => $user_ip['UserIp']['id'],
					'country' => $data->country_code,
					'state' => $data->region_code,
					'longitude' => $data->longitude,
					'latitude' => $data->latitude
				), true, array('country', 'state', 'longitude', 'latitude'));
			}
			else {
				$this->UserIp->save(array(
					'id' => $user_ip['UserIp']['id'],
					'longitude' => $data->longitude,
					'latitude' => $data->latitude
				), true, array('longitude', 'latitude'));
			}
			echo 'Mapped '.$user_ip['UserIp']['id'].' to '.$data->longitude.', '.$data->latitude."\n";
		}
	}
	
	public function hellban() {
		
		if (isset($this->args[0])) {
			$hellbanned_users = $this->User->find('all', array(
				'conditions' => array(
					'User.id' => $this->args[0]
				)
			));
		}
		else {
			$hellbanned_users = $this->User->find('all', array(
				'conditions' => array(
					'OR' => array(
						'User.hellbanned' => '1',
						'User.checked' => '1',
					),
					'User.hellban_score' => null
				)
			));
		}
		
		if ($hellbanned_users) {
			foreach ($hellbanned_users as $user) {
				$user_analysis = $this->UserAnalyzer->analyze($user);
				if ($user_analysis) {					
					$this->User->create();
					$this->User->save(array('User' => array(
						'id' => $user['User']['id'],
						'hellban_score' => $user_analysis['UserAnalysis']['score']
					)), true, array('hellban_score'));
		
					$hellban_log = $this->HellbanLog->find('first', array(
						'conditions' => array(
							'HellbanLog.user_id' => $user['User']['id'],
							'HellbanLog.type' => 'hellban',
							'HellbanLog.analysis_id' => null
						)
					));
					if ($hellban_log) {
						$this->HellbanLog->create();
						$this->HellbanLog->save(array('HellbanLog' => array(
							'id' => $hellban_log['HellbanLog']['id'],
							'analysis_id' => $user_analysis['UserAnalysis']['id'],
							'score' => $user_analysis['UserAnalysis']['score']
						)), true, array('analysis_id', 'score'));
					}
					echo 'Analyzed '.$user['User']['id'].' with score of '.$user_analysis['UserAnalysis']['score']."\n";		
				}
			}
		}
		
		$this->unhellban();
	}
	
	public function unhellban() {
		// find all users who have been hellbanned automatically
		// and are still hellbanned and unban them if they fall under a threshold
		$logs = $this->HellbanLog->find('all', array(
			'conditions' => array(
				'HellbanLog.type' => 'hellban',
				'HellbanLog.processed' => false, 
				'HellbanLog.automated' => true
			)
		));
		if ($logs) {
			foreach ($logs as $log) {
				if ($log['HellbanLog']['score'] <= 20) {
					$this->HellbanLog->create();
					$this->HellbanLog->save(array('HellbanLog' => array(
						'user_id' => $log['HellbanLog']['user_id'],
						'type' => 'unhellban',
						'automated' => true
					)));
					
					$query_profile = $this->QueryProfile->find('first', array(
						'conditions' => array(
							'QueryProfile.user_id' => $log['HellbanLog']['user_id']
						),
						'recursive' => -1,
						'fields' => array('id')
					));
					if ($query_profile) {
						$this->QueryProfile->create();
						$this->QueryProfile->save(array('QueryProfile' => array(
							'id' => $query_profile['QueryProfile']['id'],
							'ignore' => false
						)), true, array('ignore'));
					}	
					
					$this->User->create();
					$this->User->save(array('User' => array(
						'id' => $log['HellbanLog']['user_id'],
						'hellbanned' => '0',
						'checked' => '1',
						'hellbanned_on' => null
					)), true, array('hellbanned', 'checked', 'hellbanned_on'));
				}
				$this->HellbanLog->create();
				$this->HellbanLog->save(array('HellbanLog' => array(
					'id' => $log['HellbanLog']['id'],
					'processed' => true
				)), true, array('processed'));
			}
		}
	}
	
	public function profile_answers() {
		
		$users = $this->User->find('all', array(
			'conditions' => array(
				'OR' => array(
					'User.hellbanned' => '1',
					'User.checked' => '1',
				)
			)
		));
		
		$models = array('UserAnalysis', 'ProfileAnswer');
		foreach ($models as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		$aggregate = array(
			'hellbanned' => array(),
			'checked' => array()
		);
		foreach ($users as $user) {
		
			// analyze profile questions
			$profile_answers = $this->ProfileAnswer->findAllByUserId($user['User']['id'], array('section_id', 'question_id', 'date_answered'));
			$timestamps = array();
			if ($profile_answers) {
				foreach ($profile_answers as $profile_answer) {
					if (!array_key_exists($profile_answer['ProfileAnswer']['date_answered'], $timestamps)){
						$timestamps[$profile_answer['ProfileAnswer']['date_answered']] = 0;
					}
					$timestamps[$profile_answer['ProfileAnswer']['date_answered']]++;
				}
			}
		
			if (!empty($timestamps)) {
				$last_timestamp = false;
				$total = array(
					'seconds' => '0',
					'questions' => '0'
				);
				foreach ($timestamps as $timestamp => $count) {
					if (!$last_timestamp) {
						$last_timestamp = $timestamp; 
						continue;
					}
					$diff = strtotime($timestamp) - strtotime($last_timestamp);
					$last_timestamp = $timestamp; 
				
					// been five minutes since last survey question push - ignore this data point
					if ($diff > 300) {
						continue;
					}
					$total['seconds'] = $total['seconds'] + $diff; 
					$total['questions'] = $total['questions'] + $count; 
				}
				if ($total['questions'] > 0) {
					$seconds_per_question = round($total['seconds'] / $total['questions'], 2);
					if ($user['User']['hellbanned']) {
						$aggregate['hellbanned'][] = floatval($seconds_per_question);
					}
					else {
						$aggregate['checked'][] = floatval($seconds_per_question);
					}
				}
			}
		}
		
		// throw out some outliers
		$i = count($aggregate['hellbanned']);
		$throw_out = round($i * 0.05);
		echo 'throw out '.$throw_out."\n";
		sort($aggregate['hellbanned']);
		print_r($aggregate['hellbanned']);
		for ($i = 0; $i < $throw_out; $i++) {
			unset($aggregate['hellbanned'][$i]);
		}
		print_r($aggregate['hellbanned']);
		rsort($aggregate['hellbanned']);
		for ($i = 0; $i < $throw_out; $i++) {
			unset($aggregate['hellbanned'][$i]);
		}
		print_r($aggregate['hellbanned']);
		
		echo 'Hellbanned: '.(round(array_sum($aggregate['hellbanned']) / count($aggregate['hellbanned']), 2))."\n";
		echo 'Checked: '.(round(array_sum($aggregate['checked']) / count($aggregate['checked']), 2))."\n";
	}
	
	public function populate_zip_codes() {
		$models = array('QueryProfile');
		foreach ($models as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		$profiles = $this->QueryProfile->find('all', array(
			'conditions' => array(
				'QueryProfile.postal_code is not null',
				'QueryProfile.state is null',
				'QueryProfile.country' => 'US'
			)
		));
		foreach ($profiles as $profile) {
			$this->GeoZip->bindModel(array('belongsTo' => array('GeoState' => array(
				'className' => 'GeoState',
				'foreignKey' => 'state_id'
			))));
			$zip = $this->GeoZip->find('first', array(
				'conditions' => array(
					'GeoZip.zipcode' => $profile['QueryProfile']['postal_code'],
					'GeoZip.country_code' => 'US'
				)
			));
			if (!$zip) {
				continue;
			}
			if (empty($profile['QueryProfile']['gender']) || empty($profile['QueryProfile']['birthdate'])) {
				$user = $this->User->find('first', array(
					'recursive' => -1,
					'fields' => array('date_of_birth', 'gender_id', 'id'),
					'conditions' => array(
						'User.id' => $profile['QueryProfile']['user_id']
					)
				));
				if (empty($user['User']['gender_id']) && $user['User']['date_of_birth'] == $profile['QueryProfile']['birthdate']) {
					continue;
				}
				if ($user['User']['date_of_birth'] == '0000-00-00' && empty($profile['QueryProfile']['birthdate']) ){
					echo 'Birthdate failed to find for user '.$profile['QueryProfile']['user_id']."\n";
					continue;
				}
				print_r($user);
				print_r($profile);
				exit();
			}
			if (isset($zip['GeoState']) && $zip['GeoState']['state_abbr'] == 'N/A') {
				$zip['GeoState']['state_abbr'] = null;
			}
			$this->QueryProfile->create();
			$this->QueryProfile->save(array('QueryProfile' => array(
				'id' => $profile['QueryProfile']['id'],
				'state' => $zip['GeoState']['state_abbr'],
				'modified' => false,
				'dma_code' => $zip['GeoZip']['dma_code']
			)), array(
				'callbacks' => false,
				'validate' => false,
				'fieldList' => array('state', 'dma_code', 'modified')
			));
			echo $this->QueryProfile->getLastQuery()."\n";
			flush();				
		}
	}
	
	// populate master zip code database - run once a month or so
	public function zip_code_master() {
		ini_set('memory_limit', '740M');
		$i = 0;
//		$csv_file = 'http://federalgovernmentzipcodes.us/free-zipcode-database-Primary.csv';
		$csv_file = 'http://www.unitedstateszipcodes.org/zip_code_database.csv';
		$file = file_get_contents($csv_file);
		$lines = explode("\n", $file);
		$data = array();
		foreach ($lines as $line) {
			$data[] = str_getcsv($line);
		}
		
		echo "Total records: ".count($data)."\n";
		
		if (!empty($data)) {
			foreach ($data as $key => $val) {
				if ($key == 0) {
					continue;
				}
				$zipcode = $val[0];
				if (empty($zipcode)) {
					continue;
				}
				$city = $val[2];
				$state = $val[5];	
				$county = $val[6];
				$timezone = $val[7];
				$long = $val[10];
				$lat = $val[9];	
				
				$existing_zip = $this->GeoZip->find('first', array(
					'recursive' => -1,
					'conditions' => array(
						'zipcode' => $zipcode
					)
				)); 
				
				$this->GeoZip->create();
				if (!$existing_zip) {
					$this->GeoZip->save(array('GeoZip' => array(
						'zipcode' => $zipcode,
						'city' => ucwords(strtolower($city)),
						'state_id' => array_search($state, $states),
						'latitude' => $lat,
						'longitude' => $long,
						'county' => $county,
						'timezone' => empty($timezone) ? null: $timezone
					)));
				}
				else {
					$this->GeoZip->save(array('GeoZip' => array(
						'id' => $existing_zip['GeoZip']['id'],
						'latitude' => $lat,
						'longitude' => $long,
						'county' => $county,
						'timezone' => empty($timezone) ? null: $timezone
					)), true, array('longitude', 'latitude', 'timezone', 'county'));
				}
			}
		}
	}
		
	public function link_survey() {
		if (!isset($this->args[0])) {
			return;
		}
		$survey_id = $this->args[0];
		
		App::import('Lib', 'SurveyProcessing');
		SurveyProcessing::link_users($survey_id);
	}
	
	public function surveys() {
		ini_set('memory_limit', '1024M');
		ini_set('mysql.connect_timeout', 1200);
		ini_set('default_socket_timeout', 1200);
		
		$conditions = array(
			'SurveyUserVisit.checked' => null,
			'SurveyUserVisit.status' => SURVEY_COMPLETED
		); 
		
		if (isset($this->args[0]) && !empty($this->args[0])) {
			echo 'Fixing by survey '.$this->args[0]."\n";
			$conditions['SurveyUserVisit.survey_id'] = $this->args[0]; 
		}
		if (isset($this->args[1]) && !empty($this->args[1])) {
			echo 'Fixing by user '.$this->args[1]."\n";
			$conditions['SurveyUserVisit.user_id'] = $this->args[1]; 
			unset($conditions['SurveyUserVisit.checked']);
		}
		
		// force fix everything
		if (isset($this->args[2]) && !empty($this->args[2]) && $this->args[2] == 'all') {
			echo 'Fixing all'."\n";
			$survey_flags = $this->SurveyFlag->find('list', array(
				'conditions' => array(
					'SurveyFlag.passed' => false,
				),
				'recursive' => -1,
				'fields' => array('id', 'survey_user_visit_id'),
				'limit' => 2000,
				'order' => 'SurveyFlag.id desc'
			));
			$conditions['SurveyUserVisit.id'] = $survey_flags; 
			unset($conditions['SurveyUserVisit.checked']);
		}
		
		$user_survey_visits = $this->SurveyUserVisit->find('all', array(
			'fields' => array('id', 'user_id', 'checked', 'survey_id'),
			'conditions' => $conditions,
			'order' => 'SurveyUserVisit.id DESC'
		));
		
		foreach ($user_survey_visits as $user_survey_visit) {
			echo $user_survey_visit['SurveyUserVisit']['id']."\n"; 
			flush();
			if (empty($user_survey_visit['SurveyUserVisit']['user_id'])) {
				continue;
			}
			if (!empty($user_survey_visit['SurveyUserVisit']['checked'])) {
				$this->SurveyUserVisit->create();
				$this->SurveyUserVisit->save(array('SurveyUserVisit' => array(
					'id' => $user_survey_visit['SurveyUserVisit']['id'],
					'checked' => null
				)), true, array('checked'));	
				$survey_flags = $this->SurveyFlag->find('all', array(
					'recursive' => -1,
					'conditions' => array(
						'SurveyFlag.survey_user_visit_id' => $user_survey_visit['SurveyUserVisit']['id']
					),
					'fields' => array('SurveyFlag.id')
				));
				if ($survey_flags) {
					foreach ($survey_flags as $survey_flag) {
						$this->SurveyFlag->delete($survey_flag['SurveyFlag']['id']); 
					}
				}		
			}
			$survey_visits = $this->SurveyVisit->find('all', array(
				'conditions' => array(
					'SurveyVisit.survey_id' => $user_survey_visit['SurveyUserVisit']['survey_id'],
					'SurveyVisit.partner_id' => 43,
					'SurveyVisit.partner_user_id LIKE' => $user_survey_visit['SurveyUserVisit']['survey_id'].'-'.$user_survey_visit['SurveyUserVisit']['user_id'].'%'
				),
				'recursive' => -1,
				'fields' => array('info')
			));
			$flags = array(
				'other-language' => '0'
			);
			if ($survey_visits) {
				foreach ($survey_visits as $survey_visit) {
					if (empty($survey_visit['SurveyVisit']['info'])) {
						continue;
					}
					$info = Utils::print_r_reverse($survey_visit['SurveyVisit']['info']);
					if (!empty($info)) {
						
						// todo: need to handle this later
						if (!isset($info['HTTP_ACCEPT_LANGUAGE']) || empty($info['HTTP_ACCEPT_LANGUAGE'])) {
							continue;
						}
						// check language
						$languages = Utils::http_languages($info['HTTP_ACCEPT_LANGUAGE']);
						if (!empty($languages)) {
							$english = false;
							$other_language = false;
							foreach ($languages as $language => $score) {
								if (strpos($language, 'en') !== false) {
									if ($score == 1) {
										$other_language = false;
										$english = true;
										break;
									}
								}								
								if (strpos($language, 'en') === false) {
									$other_language = true;
								}
							}
							$this->SurveyFlag->create();
							$this->SurveyFlag->save(array('SurveyFlag' => array(
								'survey_user_visit_id' => $user_survey_visit['SurveyUserVisit']['id'],
								'flag' => 'other-language',
								'passed' => $other_language ? '0': '1',
								'description' => $info['HTTP_ACCEPT_LANGUAGE']
							)));
						}
					}
				}
			}
			$this->SurveyUserVisit->create();
			$this->SurveyUserVisit->save(array('SurveyUserVisit' => array(
				'id' => $user_survey_visit['SurveyUserVisit']['id'],
				'checked' => date(DB_DATETIME),
				'modified' => false
			)), false, array('checked', 'modified'));
		}
	}
	
	public function publisher() {
		$acquisitions = $this->UserAcquisition->find('all', array(
			'conditions' => array(
				'user_id >' => '0',
				'pixel_fired >' => '0',
			)
		));
		foreach ($acquisitions as $acquisition) {
			$user = $this->User->fromId($acquisition['UserAcquisition']['user_id']);
			if ($user && !empty($acquisition['UserAcquisition']['params'])) {
				if (strpos($acquisition['UserAcquisition']['source'], ':adp') !== false) {
					if (isset($acquisition['UserAcquisition']['params']['key1']) && !empty($acquisition['UserAcquisition']['params']['key1'])) {
						$this->User->create();
						$this->User->save(array('User' => array(
							'id' => $user['User']['id'],
							'pub_id' => $acquisition['UserAcquisition']['params']['key1']
						)), true, array('pub_id'));
					}
				}
				elseif (strpos($acquisition['UserAcquisition']['source'], ':pt') !== false) {
					if (isset($acquisition['UserAcquisition']['params']['sid']) && !empty($acquisition['UserAcquisition']['params']['sid'])) {
						$this->User->create();
						$this->User->save(array('User' => array(
							'id' => $user['User']['id'],
							'pub_id' => $acquisition['UserAcquisition']['params']['sid']
						)), true, array('pub_id'));
					}
				}
			}
		}
	}
	
	public function balances() {
		$setting = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'slack.balance_mismatches_alert.webhook',
				'Setting.deleted' => false
			)
		));
		if (isset($this->args[0])) {
			if ($this->args[0] == 'all') {
				$users = $this->User->find('all', array(
					'fields' => array('id', 'balance', 'pending', 'withdrawal', 'total', 'missing_points'),
					'conditions' => array(
						'hellbanned' => false,
						'active' => true
					),
					'order' => 'User.id ASC',
					'recursive' => -1
				));
			}
			else {
				$users = $this->User->find('all', array(
					'fields' => array('id', 'balance', 'pending', 'withdrawal', 'total', 'missing_points'),
					'conditions' => array(
						'id ' => $this->args[0]
					),
					'recursive' => -1
				));
			}
		}
		else {
			// within 48 hours touched
			$users = $this->User->find('all', array(
				'fields' => array('id', 'balance', 'pending', 'withdrawal', 'total', 'missing_points'),
				'conditions' => array(
					'last_touched >=' => gmdate(DB_DATE, time() - 86400 * 2),
					'hellbanned' => 0
				),
				'order' => 'User.id ASC',
				'recursive' => -1
			));
		}
		$total = count($users); 
		$this->out('Total: '.$total); 
		$i = 0; 
		foreach ($users as $user) {
			$i++; 
			$this->out($i.'/'.$total.': '.$user['User']['id']);
			$this->User->create();
			$updated = $this->User->rebuildBalances($user);
			if ($updated['User']['balance'] != $user['User']['balance']	|| $updated['User']['pending'] != $user['User']['pending'] || $updated['User']['withdrawal'] != $user['User']['withdrawal'] || $updated['User']['missing_points'] != $user['User']['missing_points']) {
				$transaction = $this->Transaction->find('first', array(
					'conditions' => array(
						'Transaction.user_id' => $user['User']['id'],
						'Transaction.deleted' => null,
					),
					'recursive' => -1,
					'fields' => array('MAX(id) as max_id')
				)); 
				$user_balance_mismatch = 'User balance mismatched:'.print_r($user, true).print_r($updated, true).print_r($transaction, true); 
				CakeLog::write('user_balances', $user_balance_mismatch);
				$this->out($user_balance_mismatch);
				$this->BalanceMismatch->create();
				$this->BalanceMismatch->save(array('BalanceMismatch' => array(
					'user_id' => $user['User']['id'],
					'old_balance' => $user['User']['balance'],
					'old_pending' => $user['User']['pending'],
					'old_withdrawal' => $user['User']['withdrawal'],
					'old_missing_points' => $user['User']['missing_points'],
					'new_balance' => $updated['User']['balance'],
					'new_pending' => $updated['User']['pending'],
					'new_withdrawal' => $updated['User']['withdrawal'],
					'new_missing_points' => $updated['User']['missing_points'],
					'max_transaction_id' => ($transaction) ? $transaction[0]['max_id'] : 0
				)));
				$message = 'User #'.$user['User']['id'];
				if ($updated['User']['balance'] != $user['User']['balance']) {
					$message .= ' Balance: '.$user['User']['balance'].' vs '.$updated['User']['balance'].' (actual);';
				}
				if ($updated['User']['pending'] != $user['User']['pending']) {
					$message .= ' Pending: '.$user['User']['pending'].' vs '.$updated['User']['pending'].' (actual);';
				}
				if ($updated['User']['withdrawal'] != $user['User']['withdrawal']) {
					$message .= ' Withdrawal: '.$user['User']['withdrawal'].' vs '.$updated['User']['withdrawal'].' (actual);';
				}
				if ($updated['User']['missing_points'] != $user['User']['missing_points']) {
					$message .= ' missing_points: '.$user['User']['missing_points'].' vs '.$updated['User']['missing_points'].' (actual);';
				}
				$message .= ' Max transaction_id: #'.$transaction[0]['max_id'];
				if ($setting) {
					$this->out($message);
					$http = new HttpSocket(array(
						'timeout' => '2',
						'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
					));
					$http->post($setting['Setting']['value'], json_encode(array(
						'text' => $message,
						'username' => 'bernard'
					))); 
				}
			}
		}
	}
	
	public function top_users_referral() {
		$this->User->bindModel(array(
			'hasMany' => array(
				'Transaction',
				'Referral' => array(
					'className' => 'User',
					'foreignKey' => 'referred_by',
				)
			)
		));
		$users = $this->User->find('all', array(
			'fields' => array('User.id', 'User.email', 'User.username', 'User.total'),
			'contain' => array(
				'Transaction' => array(
					'conditions' => array(
						'Transaction.type_id' => TRANSACTION_REFERRAL
					),
					'fields' => array('Transaction.id', 'Transaction.amount')
				),
				'Referral' => array(
					'fields' => array('Referral.id')
				)
			),
			'conditions' => array(
				'User.hellbanned' => false,
				'User.deleted_on' => null
			),
			'limit' => 500,
			'order' => 'User.total desc',
			'recursive' => -1
		));
		if (!$users) {
			$this->out('Users not found');
		}
		
		$this->Transaction->bindModel(array('belongsTo' => array(			
			'Offer' => array(
				'className' => 'Offer',
				'foreignKey' => 'linked_to_id',
				'conditions' => array(
					'type_id' => TRANSACTION_OFFER
				),
				'fields' => array('id', 'client_rate', 'award')
			),			
			'Project' => array(
				'className' => 'Project',
				'foreignKey' => 'linked_to_id',
				'conditions' => array(
					'type_id' => TRANSACTION_SURVEY
				),
				'fields' => array('id', 'client_rate', 'award')
			)
		))); 
		
		$data = array();
		$data[] = array(
			'User.id', 
			'User.email', 
			'User.username', 
			'User.total', 
			'No of referred users', 
			'Total referral earned', 
			'Referred users revenue', 
			'Referred users cost', 
			'Referred users payout'
		);
		foreach ($users as $user) {
			$referred_users = Set::extract('/Referral/id', $user);
			$referral_amount = array_sum(Set::extract('/Transaction/amount', $user));
			$referral_transactions = $this->Transaction->find('all', array(
				'conditions' => array(
					'Transaction.user_id' => $referred_users,
					'Transaction.deleted' => null,
				)
			));
			$total_revenue = $total_payout = $total_cost = 0;
			if ($referral_transactions) {
				foreach ($referral_transactions as $transaction) {
					$revenue = $payout = $cost = 0;

					$type = $transaction['Transaction']['type_id'];
					if ($type == TRANSACTION_SURVEY) {
						if ($transaction['Transaction']['amount'] > 5) {
							$revenue = $transaction['Project']['client_rate'] * 100;
						}
						$cost = $transaction['Transaction']['amount'];
					}
					elseif ($type == TRANSACTION_OFFER) {
						if (isset($transaction['Offer']['id']) && !empty($transaction['Offer']['id'])) {
							$revenue = $transaction['Offer']['client_rate'] * 100;
							$cost = $transaction['Transaction']['amount'];
						}
					}
					elseif ($type == TRANSACTION_GOOGLE) {
						$revenue = 50;
						$cost = $transaction['Transaction']['amount'];
					}
					elseif (in_array($type, array(TRANSACTION_REFERRAL, TRANSACTION_POLL, TRANSACTION_POLL_STREAK, TRANSACTION_EMAIL, TRANSACTION_PROFILE, TRANSACTION_OTHER))) {
						$cost = $transaction['Transaction']['amount'];
					}
					elseif ($type == TRANSACTION_WITHDRAWAL && $transaction['Transaction']['paid']) {
						$payout = $transaction['Transaction']['amount'];
					}
					
					$total_revenue = $total_revenue + $revenue;
					$total_cost = $total_cost + $cost;
					$total_payout = $total_payout + $payout;
				}
			}
			
			$data[] = array(
				$user['User']['id'],
				$user['User']['email'],
				$user['User']['username'],
				$user['User']['total'],
				count($referred_users),
				$referral_amount,
				$total_revenue,
				$total_cost,
				$total_payout,
			);
		}
		
		$fp = fopen(WWW_ROOT . 'files/csv/'.date(DB_DATE).'-referrals.csv', 'w');
		foreach ($data as $row) {
			fputcsv($fp, $row, ',', '"');
		}
		
		fclose($fp);
		
		$this->out('CSV file created at /files/csv/'.date(DB_DATE).'-referrals.csv');
	}
	
	public function export_dma_counts() {
		$this->loadModel('LucidZip'); 

		// get QE2 profile
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array(
					'qe.mintvine.username',
					'qe.mintvine.password',
					'hostname.qe'
				),
				'Setting.deleted' => false
			)
		));
		
		$dmas = $this->LucidZip->find('list', array(
			'fields' => array('LucidZip.dma', 'LucidZip.dma_name'),
			'conditions' => array(
				'LucidZip.dma !=' => '',
			),
			'order' => 'LucidZip.dma_name ASC',
			'group' => 'LucidZip.dma'
		));
		
		$original_query_body = array(
			'partner' => 'lucid',
			'qualifications' => array(
				'country' => 'US',
				'active_within_month' => array(
					'true'
				)
			)
		);

		$mv_original_query_body = array(
			'partner' => 'mintvine',
			'qualifications' => array(
				'country' => 'US',
				'active_within_month' => array(
					'true'
				)
			)
		);
		
		
		$filename = 'dma_counts.csv';
		$file_dir_path = 'files/reports/' . $filename;
		$file = WWW_ROOT . $file_dir_path;
		$fp = fopen($file, 'w');
		fputcsv($fp, array(
			'DMA Name', 
			'DMA Code',
			'Total (active within month)',
			'Males (active within month)',
			'Females (active within month)',
		));
		
		$http = new HttpSocket(array(
			'timeout' => 120, 
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$http->configAuth('Basic', $settings['qe.mintvine.username'], $settings['qe.mintvine.password']);
				
		foreach ($dmas as $dma_code => $dma_name) {
			$this->out('Starting '.$dma_code.' ('.$dma_name.')');
			$query_body = $original_query_body; 
			$mv_query_body = $mv_original_query_body; 
			
			$counts = array(
				'total' => 0,
				'm' => 0,
				'f' => 0
			); 
			foreach (array('m' => 1, 'f' => 2) as $gender => $precode) {
				$query_body['qualifications'][43] = array($precode); 
				$query_body['qualifications'][97] = array($dma_code); 
				$json_query = json_encode($query_body); 
				$results = $http->post($settings['hostname.qe'].'/query?count_only=true', $json_query, array(
					'header' => array('Content-Type' => 'application/json')
				));
				$body = json_decode($results, true); 
				$count = $body['count']; 				
				$counts[$gender] = $count;
				$counts['total'] += $count; 
			}
			
			// later we can use this to compare data
			/*$mv_counts = array(
				'total' => 0,
				'm' => 0,
				'f' => 0
			); 
			foreach (array('m' => 1, 'f' => 2) as $gender => $precode) {
				$mv_query_body['qualifications']['gender'] = array(strtoupper($gender)); 
				$mv_query_body['qualifications']['dma'] = array($dma_code); 
				$json_query = json_encode($mv_query_body); 
				$results = $http->post($settings['hostname.qe'].'/query?count_only=true', $json_query);
				$body = json_decode($results, true); 
				$count = $body['count']; 				
				$mv_counts[$gender] = $count;
				$mv_counts['total'] += $count; 
			}*/
			
			fputcsv($fp, array(
				$dma_name, 
				$dma_code, 
				$counts['total'],
				$counts['m'],
				$counts['f']
			)); 
			$this->out('Generated for '.$dma_code.' ('.$dma_name.')');
		}
		fclose($fp);
		$this->out('Completed');
	}
	
	// determine when a user is most active during the day
	public function active_hours() {
		$this->loadModel('SurveyUserVisit');
		$this->loadModel('UserActivityHour');
		$this->loadModel('ProjectOption');
		
		if (!isset($this->args[0])) {
			$this->out('Please set an argument for the frequency: `all` or `recent`'); 
			return false; 
		}
		
		if ($this->args[0] == 'all') {
			$last_user = $this->ProjectOption->find('first', array(
				'conditions' => array(
					'ProjectOption.project_id' => '0',
					'ProjectOption.name' => 'user_active_hours_id'
				)
			)); 
			if (!$last_user) {
				$this->ProjectOption->getDataSource()->begin(); 
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'project_id' => '0',
					'value' => '0',
					'name' => 'user_active_hours_id'
				))); 
				$project_option_id = $this->ProjectOption->getInsertId(); 
				$last_user = $this->ProjectOption->find('first', array(
					'conditions' => array(
						'ProjectOption.id' => $project_option_id
					)
				));				
				$this->ProjectOption->getDataSource()->commit(); 				
				$last_user_id = 0; 
			}
			else {
				$last_user_id =$last_user['ProjectOption']['value']; 
			}
			$total = $this->User->find('count', array(
				'conditions' => array(
					'User.last_touched >=' => date(DB_DATETIME, strtotime('-7 days')),
					'User.id >' => $last_user_id
				)
			));
		}
		elseif ($this->args[0] == 'recent') {
			$total = $this->User->find('count', array(
				'conditions' => array(
					'User.last_touched >=' => date(DB_DATETIME, strtotime('-7 days'))
				)
			));
			$last_user_id = 0; 
		}
		else {
			$total = 1;
			$last_user_id = 0; 
		}
		if ($total > 0) {
			$this->out('Total : '. $total . ' active users found within week to process');
		}		
		
		while (true) {
			if ($this->args[0] == 'all') {
				$conditions = array(
					'User.id >' => $last_user_id
				); 
			}
			elseif ($this->args[0] == 'recent') {
				$conditions = array(
					'User.last_touched >=' => date(DB_DATETIME, strtotime('-7 days')),
					'User.id >' => $last_user_id
				); 
			}
			else {
				$conditions = array(
					'User.id ' => $this->args[0]
				); 
			}
			$users = $this->User->find('all', array(
				'conditions' => $conditions,
				'limit' => '1000',
				'order' => 'User.id asc',
				'recursive' => -1
			));
			if (!$users) {
				$this->out('No users found: quitting'); 
				break;
			}
			foreach ($users as $user) {
				$last_user_id = $user['User']['id'];
				$user_activity_hour_data = array();
				$survey_user_visits = $this->SurveyUserVisit->find('list', array(
					'fields' => array('SurveyUserVisit.id', 'SurveyUserVisit.created'),
					'conditions' => array(
						'SurveyUserVisit.user_id' => $user['User']['id'],
					),
					'recursive' => -1
				));
				$hours = array();
				for ($i = 0; $i < 24; $i++) {
					$hours[str_pad($i, 2, '0', STR_PAD_LEFT)] = 0; 
				}
				foreach ($survey_user_visits as $timestamp) {
					$hour = date('H', strtotime($timestamp)); 
					$hours[$hour]++; 
				}
				$total = array_sum($hours); 
				
				$this->out('Outputting results for: '.$user['User']['id']); 
				foreach ($hours as $hour => $count) {
					$datetime = date(DB_DATE).' '.$hour.':00'; 
					$user_activity_hour_data[$hour] = $count;
				}
				$user_activity_hour_data['user_id'] = $user['User']['id'];
				$user_activity_hour_data['total'] = $total;
				$user_activity_hour = $this->UserActivityHour->find('first', array(
					'conditions' => array(
						'UserActivityHour.user_id' => $user['User']['id'],
					),
					'recursive' => -1
				));
				if ($user_activity_hour) {
					$user_activity_hour_data['id'] = $user_activity_hour['UserActivityHour']['id'];
					unset($user_activity_hour_data['UserActivityHour']['modified']); 
					unset($user_activity_hour_data['UserActivityHour']['created']); 
					unset($user_activity_hour_data['UserActivityHour']['user_id']); 
					$this->UserActivityHour->create();
					$this->UserActivityHour->save(array('UserActivityHour' => $user_activity_hour_data), true, array_keys($user_activity_hour_data));
				}
				else {
					$this->UserActivityHour->create();
					$this->UserActivityHour->save(array('UserActivityHour' => $user_activity_hour_data));
				}
			}
			
			$this->out($last_user_id); 
			
			if ($this->args[0] != 'all' && $this->args[0] != 'recent') {
				$this->out('Finishing user activity for one user: '.$this->args[0]); 
				break;
			}
			
			// set project option value
			if ($this->args[0] == 'all') {
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'id' => $last_user['ProjectOption']['id'],
					'value' => $last_user_id
				)), true, array('value'));
			}
		}	
		
		// set project option value
		if ($this->args[0] == 'all') {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'id' => $last_user['ProjectOption']['id'],
				'value' => $last_user_id
			)), true, array('value'));
		}
	}
	
	public function export_users_notification_report() {
		if (!isset($this->args[0])) {
			$this->out('Please provide report id.'); 
			return false; 
		}
		
		$user_notification_report = $this->UserNotificationReport->find('first', array(
			'conditions' => array(
				'UserNotificationReport.id' => $this->args[0]
			)
		));
		
		if (!$user_notification_report) {
			$this->out('Invalid report.'); 
			return false; 
		}
		
		$users = $this->User->find('all', array(
			'conditions' => array(
				'User.last_touched >=' => date(DB_DATETIME, strtotime('-'.$user_notification_report['UserNotificationReport']['hours'].' hours'))),
			'recursive' => -1,
			'fields' => array('User.id', 'User.email', 'User.last_touched')
		));
		
		if (!$users) {
			$this->out('There is no user who touched website is last ' . $user_notification_report['UserNotificationReport']['hours'] . ' hours.'); 
			return false; 
		}
		App::import('Model', 'NotificationLog');
		$this->NotificationLog = new NotificationLog();
		$csv_rows = array();
		$csv_header = array('User ID', 'Email', 'Project ID', 'Last touched');
		foreach ($users as $user) {
			$notification_logs = $this->NotificationLog->find('first', array(
				'fields' => array(
					'NotificationLog.project_id', 'NotificationLog.email'
				),
				'conditions' => array(
					'NotificationLog.created >=' => date(DB_DATETIME, strtotime('-'. $user_notification_report['UserNotificationReport']['hours'] .' hours')),
					'NotificationLog.sent' => true,
					'NotificationLog.user_id' => $user['User']['id']
				)
			));

			if (!$notification_logs) {
				$notification_logs = $this->NotificationLog->find('first', array(
					'fields' => array(
						'NotificationLog.project_id', 'NotificationLog.created', 'NotificationLog.email'
					),
					'conditions' => array(
						'NotificationLog.created >=' => date(DB_DATETIME, strtotime('-'. $user_notification_report['UserNotificationReport']['hours'] .' hours')),
						'NotificationLog.sent' => false,
						'NotificationLog.user_id' => $user['User']['id']
					)
				));
				if ($notification_logs) {
					$csv_rows[] = array($user['User']['id'], $notification_logs['NotificationLog']['email'], $notification_logs['NotificationLog']['project_id'], $user['User']['last_touched']);
				}
				else {
					$csv_rows[] = array($user['User']['id'], $user['User']['email'], 0, $user['User']['last_touched']);
				}
			}
		}
		
		if (!empty($csv_rows)) {
			$file_name = WWW_ROOT.'files/user_notification_report_'. $this->args[0]  .'.csv'; 
			$fp = fopen($file_name, 'w');
			fputcsv($fp, $csv_header);
			foreach ($csv_rows as $csv_row) {
				fputcsv($fp, $csv_row); 
			}
			
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
			
			$file = $file_name;
			$filename = 'files/reports/user_notification_report_'. $this->args[0]  .'.csv';
			
			$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);			
			$headers = array(
				'Content-Disposition' => 'attachment; filename=user_notification_report_'.$this->args[0].'.csv'
			);
			
			if ($S3->putObject($S3->inputFile($file), $settings['s3.bucket'], $filename, S3::ACL_PRIVATE, array(), $headers)) {				
				$this->UserNotificationReport->create();
				$this->UserNotificationReport->save(array('UserNotificationReport' => array(
					'id' => $this->args[0],
					'path' => $filename,
					'status' => 'complete'
				)), true, array('path', 'status'));
				
				// unlink($file);
			}
		
			$this->out('Wrote '.$file_name);
		}
		else {
			$this->out('No record found.');
		}
	}
}
