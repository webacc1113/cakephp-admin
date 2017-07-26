<?php

App::uses('CakeEmail', 'Network/Email');
App::uses('HttpSocket', 'Network/Http');
App::import('Lib', 'Utilities');
App::import('Lib', 'MintVine');
App::import('Lib', 'MintVineUser');
App::import('Lib', 'CloudFrontLib');

class MaintenanceShell extends AppShell {
	var $uses = array(
		'Admin', 
		'CintLog', 
		'Group', 
		'Ledger', 
		'Loi',  
		'LucidQueue', 
		'MailLog', 
		'Nonce', 
		'RouterTimeLog', 
		'PartnerLog', 
		'PartnerUser', 
		'PaymentMethod',
		'Poll', 
		'PollUserAnswer', 
		'PrecisionLog', 
		'Project', 
		'ProjectInviteReport', 
		'ProjectLog', 
		'ProjectOption', 
		'QueryProfile', 
		'Setting', 
		'SsiInvite', 
		'SurveyComplete', 
		'SurveyLink', 
		'SurveyPartner', 
		'SurveyUser', 
		'SurveyVisit', 
		'SurveyVisitCache', 
		'Tangocard',
		'TolunaLog',
		'Transaction', 
		'TwilioNumber', 
		'User', 
		'UserAnalytic', 
		'UserExportStatistic', 
		'UserLog', 
		'UserOption', 
		'UserRouterLog'
	);
	public $tasks = array('Maintenance', 'UserAnalyzer');
			
	public function export_check() {
		
		// Send messages to slack
		$setting = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'slack.systemchecks.webhook',
				'Setting.deleted' => false
			)
		));
		if (!$setting) {
			$this->out('ERROR: Slack channel missing');
			return; 
		}
		
		$this->out('Starting...');
		// set the time ranges
		$days = array(1, 7, 15, 30); 
		
		$this->out('Retrieving partners...');
		// determine the partners we need to work with 
		$partners = $this->PartnerUser->find('all', array(
			'fields' => array('DISTINCT(partner) as distinct_partner'
		))); 
		$partner_list = Set::extract('/PartnerUser/distinct_partner', $partners);
		$this->out('Retrieved ('.count($partner_list).') partners');
		
		// store multi-dimensional array of our total counts for each date range's partners; precreate this so no errors are thrown when we try to store data
		$counts = array(); 
		foreach ($days as $day) {
			$counts[$day] = array(
				'total' => 0,
			); 
			foreach ($partner_list as $partner) {
				$counts[$day][$partner] = 0;
			}
		}
		
		// internal timer
		$start_ts = microtime(true);
		
		// iterate over every day
		foreach ($days as $day) {
			$this->out('Iterating over day '.$day);
			// grab all users in this date range
			$users = $this->User->find('all', array(
				'recursive' => -1, 
				'fields' => array('User.id'),
				'conditions' => array(
					'User.deleted_on' => null,
					'User.created >=' => date(DB_DATETIME, strtotime('-'.$day.' days')),
				)
			));
			$counts[$day]['total'] = count($users);
			$this->out('Found '.$counts[$day]['total'].' users');
			
			foreach ($users as $user) {
				$partner_users = $this->PartnerUser->find('all', array(
					'fields' => array('PartnerUser.id', 'PartnerUser.partner', 'PartnerUser.uid'),
					'conditions' => array(
						'PartnerUser.user_id' => $user['User']['id']
					),
					'recursive' => -1
				));
				if (!empty($partner_users)) {
					foreach ($partner_users as $partner) {
						if ($partner['PartnerUser']['partner'] == 'precision' && empty($partner['PartnerUser']['uid'])) {
							continue;
						}
						$counts[$day][$partner['PartnerUser']['partner']]++;
					}
				}
			}
		}
		$end_ts = microtime(true);
		
		$this->out('Counting took '.($end_ts - $start_ts).' seconds');
				
		$message = '*User Export Verification Report for '.date('F jS, Y').'*'; 
		
		$http = new HttpSocket(array(
			'timeout' => '2',
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$http->post($setting['Setting']['value'], json_encode(array(
			'text' => $message,
			'username' => 'pequeño'
		))); 
		
		foreach ($counts as $day => $data) {
			$message = array('*'.$day.' day verification (Total Users: '.number_format($data['total']).')*'); 
			foreach ($data as $key => $val) {
				if ($key == 'total') {
					continue;
				}
				$pct = round($val / $data['total'], 2) * 100; 
				$message[] = $key.' ('.$pct.'%): '.number_format($val).' users exported';
			}
			
			$http = new HttpSocket(array(
				'timeout' => '2',
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$http->post($setting['Setting']['value'], json_encode(array(
				'text' => implode("\n", $message),
				'username' => 'pequeño'
			))); 
		}
	}
	
	// grab average partner times for past 1000
	public function average_api_call_times() {
		$setting = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'slack.partnerapis.webhook',
				'Setting.deleted' => false
			)
		));
		if (!$setting) {
			return false;
		}
		$distinct_partners = $this->RouterTimeLog->find('all', array(
			'fields' => array('DISTINCT(partner)'),
			'order' => 'RouterTimeLog.id DESC',
			'limit' => 3000
		)); 
		$partners = Set::extract('/RouterTimeLog/partner', $distinct_partners); 
		if (empty($partners)) {
			return false;
		}
		$messages = array();
		foreach ($partners as $partner) {
			if ($partner == 'toluna') { 
				$distinct_countries = $this->RouterTimeLog->find('all', array(
					'fields' => array('DISTINCT(country) as distinct_country'),
					'conditions' => array(
						'RouterTimeLog.partner' => 'toluna',
						'RouterTimeLog.country is not null'
					)
				));
				$countries = Set::extract('/RouterTimeLog/distinct_country', $distinct_countries); 
				if (empty($countries)) {
					continue;
				}
				foreach ($countries as $country) {
					$timers = $this->RouterTimeLog->find('list', array(
						'fields' => array('RouterTimeLog.id', 'RouterTimeLog.time'),
						'conditions' => array(
							'RouterTimeLog.partner' => 'toluna',
							'RouterTimeLog.country' => $country
						),
						'order' => 'RouterTimeLog.id DESC',
						'limit' => 1000
					));
					$average = round(array_sum($timers) / count($timers), 4); 
					$std_dev = round(Utils::stats_standard_deviation($timers), 4); 
					$message = 'Toluna '.$country.': ';
					$message.= 'Average: '.$average.' seconds (std dev: '.$std_dev.') (sample size: '.count($timers).')';
					$messages[] = $message; 
				} 
			}
			else {
				$timers = $this->RouterTimeLog->find('list', array(
					'fields' => array('RouterTimeLog.id', 'RouterTimeLog.time'),
					'conditions' => array(
						'RouterTimeLog.partner' => $partner,
					),
					'order' => 'RouterTimeLog.id DESC',
					'limit' => 1000
				));
				$average = round(array_sum($timers) / count($timers), 4); 
				$std_dev = round(Utils::stats_standard_deviation($timers), 4); 
				$message = ucfirst($partner).': ';
				$message.= 'Average: '.$average.' seconds (std dev: '.$std_dev.') (sample size: '.count($timers).')';
				$messages[] = $message; 
			}
		}
		
		foreach ($messages as $message) {
			if (empty($message)) {
				continue;
			}
			$http = new HttpSocket(array(
				'timeout' => '2',
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$http->post($setting['Setting']['value'], json_encode(array(
				'text' => $message,
				'link_names' => 1,
				'username' => 'bernard'
			))); 
		}
	}
	
	public function survey_links() {
		ini_set('memory_limit', '1024M');
		App::import('Vendor', 'sqs');
		
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('sqs.access.key', 'sqs.access.secret'),
				'Setting.deleted' => false
			)
		));
		if (count($settings) < 2) {
			return false;
		}
		$sqs = new SQS($settings['sqs.access.key'], $settings['sqs.access.secret']);
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.status' => PROJECT_STATUS_OPEN,
				'Project.sqs is not null',
				'Project.sqs <' => date(DB_DATETIME, strtotime('-10 days'))
			),
			'fields' => array('Project.id'),
			'recursive' => -1
		));
		foreach ($projects as $project) {
			$project_option = $this->ProjectOption->find('first', array(
				'conditions' => array(
					'ProjectOption.project_id' => $project['Project']['id'],
					'ProjectOption.name' => 'sqs_url'
				)
			));
			if ($project_option) {
				$queue_url = $project_option['ProjectOption']['value'];
				$survey_link_ids = array();
				while (true) {
					$results = $sqs->receiveMessage($queue_url);
					if (!empty($results['Messages'])) {
						$survey_link_ids[] = $results['Messages'][0]['Body'];
						$sqs->deleteMessage($queue_url, $results['Messages'][0]['ReceiptHandle']);
						echo 'Added '.$results['Messages'][0]['Body']."\n";
					}
					else {
						break;
					}
				}
				if (!empty($survey_link_ids)) {
					foreach ($survey_link_ids as $survey_link_id) {
						$response = $sqs->sendMessage($queue_url, $survey_link_id);
						print_r($response);
					}
				}
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'sqs' => date(DB_DATETIME)
				)), true, array('sqs'));
			}
		}
	}
	
	public function user_inactive() {
		ini_set('memory_limit', '1024M');
		$users = $this->User->find('all', array(
			'conditions' => array(
				'User.active' => true,
				'User.deleted_on' => null,
				'User.verified is not null',
				'OR' => array(
					'User.last_touched is null',
					'User.last_touched <' => date(DB_DATETIME, strtotime('-3 months'))
				)
			),
			'recursive' => -1,
			'fields' => array('id', 'last_touched', 'active', 'verified')
		));
		foreach ($users as $user) {
			$this->User->create();
			$this->User->save(array('User' => array(
				'id' => $user['User']['id'],
				'active' => false,
				'modified' => false
			)), true, array('active'));
			echo $user['User']['id']."\n";
		}
		echo 'Inactivated '.count($users).' users'; 
	}
	
	// DO NOT RUN THIS ON THE GALERA CLUSTER
	public function cleanup_db_data() {
		// this is to fix a bug where amazon queue ids are not returned correctly
		
		$count = $this->LucidQueue->find('count', array(
			'conditions' => array(
				'LucidQueue.amazon_queue_id is null',
				'LucidQueue.executed is null',
				'LucidQueue.created <' => date(DB_DATETIME, strtotime('-12 hours'))
			)
		));
		CakeLog::write('lucid.missing_queue_ids', $count); 
		// DELETE FROM lucid_queues where amazon_queue_id is null and executed is null and created < (NOW() - INTERVAL 12 HOUR)
		$this->LucidQueue->query("DELETE FROM lucid_queues where amazon_queue_id is null and executed is null and created < '".date(DB_DATETIME, strtotime('-12 hours'))."'");
		$this->out('Cleaned up Lucid Queues ('.$count.')');

		// DELETE FROM ssi_invites where created < (NOW() - INTERVAL 8 DAY)
		$ssi_invite = $this->SsiInvite->find('first', array(
			'conditions' => array(
				'SsiInvite.created <=' => date(DB_DATETIME, strtotime('-8 days'))
			),
			'order' => 'SsiInvite.id DESC'
		));
		if ($ssi_invite) {
			$this->SsiInvite->query("DELETE FROM ssi_invites where id < ".$ssi_invite['SsiInvite']['id']);
			$this->out('Deleted SSI Invites');
		}
		
		// DELETE FROM mail_logs where created < (NOW() - INTERVAL 2 DAY)
		$maillog = $this->MailLog->find('first', array(
			'conditions' => array(
				'MailLog.created <=' => date(DB_DATETIME, strtotime('-2 days'))
			),
			'order' => 'MailLog.id DESC'
		));
		if ($maillog) {
			$this->MailLog->query("DELETE FROM mail_logs where id < ".$maillog['MailLog']['id']);
			$this->out('Deleted Mail Logs');
		}
		
		// DELETE FROM cint_logs where created < (NOW() - INTERVAL 7 DAY)
		$cint_log = $this->CintLog->find('first', array(
			'conditions' => array(
				'CintLog.parent_id' => '0',
				'CintLog.created <=' => date(DB_DATETIME, strtotime('-15 days'))
			),
			'order' => 'CintLog.id DESC'
		));
		if ($cint_log) {
			$this->CintLog->query("DELETE FROM cint_logs where id < ".$cint_log['CintLog']['id']);
			$this->out('Deleted Cint Logs');
		}
		
		// DELETE FROM toluna_logs where created < (NOW() - INTERVAL 7 DAY)
		$toluna_log = $this->TolunaLog->find('first', array(
			'conditions' => array(
				'TolunaLog.parent_id' => '0',
				'TolunaLog.created <=' => date(DB_DATETIME, strtotime('-8 days'))
			),
			'order' => 'TolunaLog.id DESC'
		));
		if ($toluna_log) {
			$this->TolunaLog->query("DELETE FROM toluna_logs where id < ".$toluna_log['TolunaLog']['id']);
			$this->out('Deleted Toluna Logs');
		}
		// DELETE FROM precision_logs where created < (NOW() - INTERVAL 7 DAY)
		$precision_log = $this->PrecisionLog->find('first', array(
			'conditions' => array(
				'PrecisionLog.parent_id' => '0',
				'PrecisionLog.created <=' => date(DB_DATETIME, strtotime('-8 days'))
			),
			'order' => 'PrecisionLog.id DESC'
		));
		if ($precision_log) {
			$this->PrecisionLog->query("DELETE FROM precision_logs where id < ".$precision_log['PrecisionLog']['id']);
			$this->out('Deleted Precision Logs');
		}
			
	}
	/* this function checks for the existence of fake completes - completes that the system stores but the client never reports */
	public function check_completes() {
		
		if (isset($this->args[0])) {
			if ($this->args[0] == 'missing') {
				$missing_projects = $this->SurveyComplete->find('all', array(
					'conditions' => array(
						'SurveyComplete.status' => 'imported',
						'SurveyComplete.created >=' => date(DB_DATETIME, strtotime('-30 days'))
					),
					'fields' => array('DISTINCT(survey_id) as survey_id')
				)); 
				$project_ids = array();
				foreach ($missing_projects as $missing_project) {
					$project_ids[] = $missing_project['SurveyComplete']['survey_id'];
				}
				$projects = $this->Project->find('all', array(
					'conditions' => array(
						'Project.id' => $project_ids,
					),
					'recursive' => -1,
					'fields' => array('Project.id', 'Project.router')
				));
				
			}
			else {
				$projects = $this->Project->find('all', array(
					'conditions' => array(
						'Project.id' => $this->args[0],
					),
					'recursive' => -1,
					'fields' => array('Project.id', 'Project.router')
				));
			}
		}
		else {
			$projects = $this->Project->find('all', array(
				'conditions' => array(
					'Project.status' => array(PROJECT_STATUS_CLOSED, PROJECT_STATUS_INVOICED),
					'Project.complete_client_report' => true,
					'Project.complete_transactions' => false,
					'Project.id >' => '10962',
				),
				'recursive' => -1,
				'fields' => array('Project.id', 'Project.router')
			));
		}
		if (!$projects) {
			return;
		}
		// user id
		$transaction_ids = array();
		foreach ($projects as $project) {
			echo $project['Project']['id']."\n";
			$survey_completes = $this->SurveyComplete->find('list', array(
				'fields' => array('id', 'hash'),
				'conditions' => array(
					'SurveyComplete.survey_id' => $project['Project']['id'],
					'SurveyComplete.status' => 'imported'
				)
			));
			
			if (!empty($survey_completes)) {				
				$survey_visits = $this->SurveyVisit->find('all', array(
					'fields' => array('id', 'partner_user_id', 'hash', 'partner_id'),
					'conditions' => array(
						'SurveyVisit.survey_id' => $project['Project']['id'],
						'SurveyVisit.hash' => $survey_completes,
						'SurveyVisit.type' => SURVEY_COMPLETED
					)
				));
				if (!$survey_visits) {
					$this->Project->create();
					$this->Project->save(array('Project' => array(
						'id' => $project['Project']['id'],
						'complete_transactions' => true
					)), true, array('complete_transactions'));
					
					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $project['Project']['id'],
						'type' => 'reporting.validation',
						'description' => 'true'
					)));
					continue;
				}
	
				// match against surveys
				foreach ($survey_visits as $survey_visit) {
					if ($survey_visit['SurveyVisit']['partner_id'] == 43) {
						if (strpos($survey_visit['SurveyVisit']['partner_user_id'], '-') === false) {
							continue;
						}
						list($project_id, $user_id, $hash) = explode('-', $survey_visit['SurveyVisit']['partner_user_id']);
						if (!isset($user_id) || empty($user_id)) {
							continue;
						}
						if (!isset($transaction_ids[$user_id])) {
							$transaction_ids[$user_id] = array();
						}
						$conditions = array(
							'Transaction.user_id' => $user_id,
							'Transaction.linked_to_id' => $project_id,
							'Transaction.amount >' => '5',
							'Transaction.deleted' => null,
						);
						if (!empty($transaction_ids)) {
							$conditions['NOT'] = array('Transaction.id' => $transaction_ids[$user_id]);
						}
						$transaction = $this->Transaction->find('first', array(
							'conditions' => $conditions
						));
						if (!$transaction) {
							continue;
						}
						$transaction_ids[$transaction['Transaction']['user_id']][] = $transaction['Transaction']['id'];
					
						$survey_complete = $this->SurveyComplete->find('first', array(
							'conditions' => array(
								'SurveyComplete.survey_id' => $project_id,
								'SurveyComplete.hash' => $survey_visit['SurveyVisit']['hash'],
								'SurveyComplete.user_id is null'
							)
						));
						if (!$survey_complete) {
							continue;
						}
						$this->SurveyComplete->create();
						$this->SurveyComplete->save(array('SurveyComplete' => array(
							'id' => $survey_complete['SurveyComplete']['id'],
							'status' => 'matched',
							'user_id' => $user_id,
							'transaction_id' => $transaction['Transaction']['id']
						)), true, array('status', 'user_id', 'transaction_id'));
						echo 'Matched '.$transaction['Transaction']['id']."\n";
					}
					else {
						// for non-mintvine partners, just mark it as 0 to say it's completed
						$survey_complete = $this->SurveyComplete->find('first', array(
							'conditions' => array(
								'SurveyComplete.survey_id' => $project['Project']['id'],
								'SurveyComplete.hash' => $survey_visit['SurveyVisit']['hash'],
								'SurveyComplete.status' => 'imported',
								'SurveyComplete.user_id is null'
							)
						));
						if (!$survey_complete) {
							continue;
						}
						$this->SurveyComplete->create();
						$this->SurveyComplete->save(array('SurveyComplete' => array(
							'id' => $survey_complete['SurveyComplete']['id'],
							'status' => 'matched',
							'user_id' => '0',
						)), true, array('status', 'user_id'));
					}
				}
			}
	
			// get all survey completes that are matched and with a user
			$survey_completes = $this->SurveyComplete->find('list', array(
				'conditions' => array(
					'SurveyComplete.survey_id' => $project['Project']['id'],
					'SurveyComplete.status' => 'matched',
					'SurveyComplete.transaction_id is not null'
				),
				'fields' => array('id', 'transaction_id')
			));
			if (empty($survey_completes)) {
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'complete_transactions' => true
				)), true, array('complete_transactions'));
				
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'reporting.validation',
					'description' => 'true'
				)));
				continue; // all external partners
			}

			// reject transactions and report on the data
			$transactions = $this->Transaction->find('all', array(
				'fields' => array('Transaction.*', 'User.pending', 'User.id', 'User.balance'),
				'conditions' => array(
					'Transaction.type_id' => TRANSACTION_SURVEY,
					'Transaction.linked_to_id' => $project['Project']['id'],
					'Transaction.status' => array(TRANSACTION_PENDING, TRANSACTION_APPROVED),
					'Transaction.deleted' => null,
				)
			));
			
			if (empty($transactions)) {				
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'complete_transactions' => true
				)), true, array('complete_transactions'));
				
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'reporting.validation',
					'description' => 'true'
				)));
				continue; // no bad transactions
			}
			if (!$project['Project']['router']) {
				foreach ($transactions as $transaction) {
					if (in_array($transaction['Transaction']['id'], $survey_completes)) {
						$this->Transaction->create();
						$this->Transaction->approve($transaction);
						continue;
					}
					$this->Transaction->create();
					$this->Transaction->reject($transaction); 
					echo 'Rejected '.$transaction['Transaction']['id'].' for user '.$transaction['Transaction']['user_id']."\n";
				}
			}
			
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'complete_transactions' => true
			)), true, array('complete_transactions'));
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'reporting.validation',
				'description' => 'true'
			)));
		}
	}
	
	// calculate IR for active surveys
	public function ir() {
		if (!isset($this->args[0])) {
			$conditions =  array('Project.status' => 'Open');
		}
		elseif ($this->args[0] != 'all') {
			$conditions = array('Project.id' => $this->args[0]);
		}
		else {
			$conditions = array('Project.date_created >=' => date(DB_DATETIME, strtotime('-1 month')));
		}
		
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'SurveyVisitCache.*', 'Project.client_rate', 'Project.prescreen'),
			'contain' => array(
				'SurveyVisitCache',
			),
			'conditions' => $conditions,
			'order' => 'Project.id asc',
			'recursive' => -1
		));
		
		$this->out('Processing '.count($projects).' projects');
		foreach ($projects as $project) {
			if (empty($project['SurveyVisitCache']['id'])) {
				continue;
			}
			
			$survey_visit_cache = array('id' => $project['SurveyVisitCache']['id']);
			if (!empty($project['SurveyVisitCache']['complete'])) {
				$actual_ir = round($project['SurveyVisitCache']['complete'] / $project['SurveyVisitCache']['click'], 2) * 100;
				$actual_epc = round($actual_ir * $project['Project']['client_rate']); // we save epc in cents
				$survey_visit_cache['ir'] = $actual_ir;
				$survey_visit_cache['epc'] = $actual_epc;
				
				// find absolute low and high ir and epc
				if (is_null($project['SurveyVisitCache']['low_ir']) || $actual_ir < $project['SurveyVisitCache']['low_ir']) {
					$survey_visit_cache['low_ir'] = $actual_ir;
				}

				if ($actual_ir > $project['SurveyVisitCache']['high_ir']) {
					$survey_visit_cache['high_ir'] = $actual_ir;
				}

				if (is_null($project['SurveyVisitCache']['low_epc']) || $actual_epc < $project['SurveyVisitCache']['low_epc']) {
					$survey_visit_cache['low_epc'] = $actual_epc;
				}

				if ($actual_epc > $project['SurveyVisitCache']['high_epc']) {
					$survey_visit_cache['high_epc'] = $actual_epc;
				}
			}
			
			if ($project['SurveyVisitCache']['click'] > 0) {
				$drops = MintVine::drop_rate($project); 
				$survey_visit_cache['drops'] = $drops;
			}
			
			$this->SurveyVisitCache->create();
			$this->SurveyVisitCache->save(array('SurveyVisitCache' => $survey_visit_cache), true, array_keys($survey_visit_cache));
			$this->out('Updated #'.$project['Project']['id']);
		}
	}
	
	// calculate LOI for active surveys
	function loi() {
		if (!isset($this->args[0])) {
			$surveys = $this->Project->find('all', array(
				'conditions' => array(
					array(
						'Project.status' => 'Open',
						'Project.active' => true
					)
				),
				'order' => 'Project.id asc'
			));
		}
		else {
			$surveys = $this->Project->find('all', array(
				'conditions' => array(
					array(
						'Project.id' => $this->args[0]
					)
				),
				'order' => 'Project.id asc'
			));
		}
		foreach ($surveys as $survey) {
			$lois = $this->Loi->find('all', array(
				'fields' => array('Loi.partner_id', 'Loi.loi_seconds'),
				'conditions' => array(
					'Loi.survey_id' => $survey['Project']['id'],
					'Loi.type' => SURVEY_COMPLETED
				)
			));
			if (!$lois) {
				continue;
			}
			$loi_list = array();
			$partners = array();
			foreach ($lois as $loi) {
				$loi_list[] = $loi['Loi']['loi_seconds'];
				$partners[$loi['Loi']['partner_id']][] = $loi['Loi']['loi_seconds'];
			}

			$old_avg = round(array_sum($loi_list) / count($loi_list));
			if (count($loi_list) > 10) {
				$median = Utils::calculate_median($loi_list); 
				
				$modifier = $median / 2; 
				foreach ($loi_list as $key => $loi) {
					if ($loi >= ($median + $modifier)) {
						unset($loi_list[$key]); 
					}
					if ($loi <= ($median - $modifier)) {
						unset($loi_list[$key]);
					}
				}
			}
			
			if (!empty($partners)) {
				foreach ($partners as $partner_id => $partner_lois) {
					$survey_partner = $this->SurveyPartner->find('first', array(
						'conditions' => array(
							'SurveyPartner.survey_id' => $survey['Project']['id'],
							'SurveyPartner.partner_id' => $partner_id
						),
						'recursive' => -1
					));
					if ($survey_partner) {
						$partner_avg = round(array_sum($partner_lois) / count($partner_lois));
						$this->SurveyPartner->create();
						$this->SurveyPartner->save(array('SurveyPartner' => array(
							'id' => $survey_partner['SurveyPartner']['id'],
							'loi_seconds' => $partner_avg
						)), true, array('loi_seconds'));
						$this->out('Updated survey '.$survey['Project']['id'].' partner '.$partner_id.' with '.$partner_avg);
					}
				}
			}
			$avg = round(array_sum($loi_list) / count($loi_list));
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $survey['Project']['id'],
				'loi' => $avg
			)), true, array('loi'));
			
			$survey_visit_cache = $this->SurveyVisitCache->find('first', array(
				'conditions' => array(
					'SurveyVisitCache.survey_id' => $survey['Project']['id']
				)
			));
			if ($survey_visit_cache) {
				$this->SurveyVisitCache->create();
				$this->SurveyVisitCache->save(array('SurveyVisitCache' => array(
					'id' => $survey_visit_cache['SurveyVisitCache']['id'],
					'loi' => $avg,
					'loi_seconds' => $avg
				)), true, array('loi', 'loi_seconds'));
			}
			$this->out('Updated survey '.$survey['Project']['id'].' with '.$avg .' (pure avg: '.$old_avg.')');
		}
	}
	
	function withdrawals() {
		$models_to_load = array('SurveyUserVisit', 'IpProxy', 'UserIp', 'UserAnalysis');
		foreach ($models_to_load as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		App::import('Vendor', 'geoip/geoipcity'); 	
		App::import('Vendor', 'geoip/geoipregionvars'); 
		$gi = geoip_open(APP."Vendor/geoip/GeoIPRegion-115.dat", GEOIP_STANDARD);
		
		if (isset($this->args[0])) {
			$withdrawals = $this->Transaction->find('all', array(
				'conditions' => array(
					'Transaction.status' => TRANSACTION_PENDING,
					'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
					'Transaction.id' => $this->args[0],
					'Transaction.deleted' => null,
				)
			));
		}
		else {
			$withdrawals = $this->Transaction->find('all', array(
				'conditions' => array(
					'Transaction.status' => TRANSACTION_PENDING,
					'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
					'Transaction.deleted' => null,
				)
			));
		}
		if (!$withdrawals) {
			return;
		}
		
		$hellbanned_users = array();
		foreach ($withdrawals as $key => $withdrawal) {
			if (in_array($withdrawal['Transaction']['user_id'], $hellbanned_users)) {
				continue;
			}
			
			// verify balance
			$this->User->rebuildBalances($withdrawal);
			
			// verify there are no duplicate transactions
			$dupe_transactions = $this->Transaction->find('count', array(
				'conditions' => array(
					'Transaction.status' => TRANSACTION_PENDING,
					'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
					'Transaction.user_id' => $withdrawal['Transaction']['user_id'],
					'Transaction.id <>' => $withdrawal['Transaction']['id'],
					'Transaction.deleted' => null,
				),
				'recursive' => -1
			));
			if ($dupe_transactions > 0) {
				MintVineUser::hellban($withdrawal['Transaction']['user_id']);
				$hellbanned_users[] = $withdrawal['Transaction']['user_id'];
				echo 'Hellbanned '.$withdrawal['Transaction']['user_id']; 
				continue;
			}

			$user = $this->User->find('first', array(
				'conditions' => array(
					'User.id' => $withdrawal['User']['id']
				),
				'fields' => array('id', 'balance', 'pending', 'withdrawal'),
				'recursive' => -1
			)); 
			if (($user['User']['balance'] + $user['User']['withdrawal'] + $user['User']['pending']) < 0) {
				$this->Transaction->reject($withdrawal);
				$this->Transaction->save(array('Transaction' => array(
					'id' => $withdrawal['Transaction']['id'],
					'note' => 'Balance exceeded for withdrawal'
				)), true, array('note')); 
				echo 'balance exceeded';
				continue;
			}
			
			$user_analysis = $this->UserAnalysis->find('first', array(
				'conditions' => array(
					'UserAnalysis.user_id' => $withdrawal['User']['id']
				),
				'order' => 'UserAnalysis.id DESC'
			));
			$withdrawal_message = explode("\n", $withdrawal['Transaction']['note']);
			if ((isset($this->args[1]) && $this->args[1] == 'force' && isset($this->args[0])) || !$user_analysis || ($user_analysis['UserAnalysis']['created'] < $withdrawal['Transaction']['created'])) {
				echo 'analyzing '.$withdrawal['User']['id']."\n";
				$user = $this->User->findById($withdrawal['User']['id']);
				$user_analysis = $this->UserAnalyzer->analyze($user, $withdrawal['Transaction']['id']);
				
				$last_withdrawal = $this->Transaction->find('first', array(
					'fields' => array(
						'Transaction.created'
					),
					'conditions' => array(
						'Transaction.user_id' => $withdrawal['User']['id'],
						'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
						'Transaction.status' => TRANSACTION_APPROVED,
						'Transaction.id <' => $withdrawal['Transaction']['id'],
						'Transaction.deleted' => null,
					),
					'order' => 'Transaction.id DESC'
				));
				if ($last_withdrawal) {
					echo 'Since '.$last_withdrawal['Transaction']['created']."\n";
				}
				// set the transactional notes
				$failed = array(	
					'countries' => 'User has accessed the site outside of the US, GB, or CA', 
					'referral' => 'User was referred by a hellbanned user.', 
					'language' => 'User\'s browser language is not English.', 
					'locations' => 'User has used multiple states to access the site.', 
					'logins' => 'User utilized many different IPs for logins & registrations.', 
					'proxy' => 'User has utilized proxy servers.', 
					'timezone' => 'User has used a timezones that does not match their self-reported ZIP code.', 
					/* 'profile' => 'User sped through profile questions', */
					'rejected_transactions' => 'User had more than 5 rejected transactions',
					'frequency' => 'User has had a payout or registered in the last 7 days.',
					'asian_timezone' => 'User accessed from an Asian timezone',
					'distance' => 'User utilized IP addresses that are geographically dispersed.',
					'payout' => 'Large payout requested'/* ,
					'nonrevenue' => '> 90% non-revenue generating activity' */
				);
				foreach ($user_analysis['UserAnalysis'] as $key => $val) {
					if (!isset($failed[$key])) {
						continue;
					}
					if (!empty($val)) {
						$message = $failed[$key];
						echo $message."\n";
						if ($key == 'countries') {
							if ($last_withdrawal) {
								echo 'Since '.$last_withdrawal['Transaction']['created']."\n";
								$countries = $this->UserIp->find('all', array(
									'fields' => array('distinct(country)'),
									'conditions' => array(
										'UserIp.user_id' => $user_analysis['UserAnalysis']['user_id'],
										'UserIp.created >' => $last_withdrawal['Transaction']['created']
									)
								));
							}
							else {
								$countries = $this->UserIp->find('all', array(
									'fields' => array('DISTINCT(country)'),
									'conditions' => array(
										'UserIp.user_id' => $user_analysis['UserAnalysis']['user_id']
									)
								));
							}
							if ($countries) {
								$list = array();
								foreach ($countries as $country) {
									if (!empty($country['UserIp']['country'])) {
										$list[] = $country['UserIp']['country'];
									}
								}
								$message .= ' ('.implode(', ', $list).')';
							}
						}
						elseif ($key == 'language') {
							if ($last_withdrawal) {
								echo 'Since '.$last_withdrawal['Transaction']['created']."\n";
								$language_codes = $this->UserIp->find('all', array(
									'fields' => array('distinct(user_language)'),
									'conditions' => array(
										'UserIp.user_id' => $user_analysis['UserAnalysis']['user_id'],
										'UserIp.created >' => $last_withdrawal['Transaction']['created']
									)
								));
							}
							else {
								$language_codes = $this->UserIp->find('all', array(
									'fields' => array('DISTINCT(user_language)'),
									'conditions' => array(
										'UserIp.user_id' => $user_analysis['UserAnalysis']['user_id']
									)
								));
							}
							if ($language_codes) {
								$list = array();
								foreach ($language_codes as $language_code) {
									$http_languages = Utils::http_languages($language_code['UserIp']['user_language']);
									if (!empty($http_languages)) {
										foreach ($http_languages as $key => $score) {
											if ($score != 1) {
												continue;
											}
											$lang = strtolower($key);
											if (strpos($lang, '-') !== false) {
												list($lang, $whatever) = explode('-', $lang);
											}
											if (strtolower($lang) == 'en') {
												continue;
											}
											$list[] = strtolower($lang);
										}
									}
								}
								$message .= ' ('.implode(', ', $list).')';
							}
						}
						elseif ($key == 'locations') {
							if ($last_withdrawal) {
								echo 'Since '.$last_withdrawal['Transaction']['created']."\n";
								$states = $this->UserIp->find('all', array(
									'fields' => array('distinct(state)'),
									'conditions' => array(
										'UserIp.user_id' => $user_analysis['UserAnalysis']['user_id'],
										'UserIp.created >' => $last_withdrawal['Transaction']['created']
									)
								));
							}
							else {
								$states = $this->UserIp->find('all', array(
									'fields' => array('DISTINCT(state)'),
									'conditions' => array(
										'UserIp.user_id' => $user_analysis['UserAnalysis']['user_id']
									)
								));
							}
							if ($states) {
								$list = array();
								foreach ($states as $state) {
									if (!empty($state['UserIp']['state'])) {
										$list[] = $state['UserIp']['state'];
									}
								}
								$message .= ' ('.implode(', ', $list).')';
							}
						}
						elseif ($key == 'proxy') {
							
						}
						if (!in_array($message, $withdrawal_message)) {
							$withdrawal_message[] = $message; 
						}
					}
				}
			}
			
			$this->Transaction->create();
			$this->Transaction->save(array('Transaction' => array(
				'id' => $withdrawal['Transaction']['id'],
				'note' => implode("\n", $withdrawal_message)
			)), true, array('note')); 
		}
	}
	
	function inactive_projects() {
		$inactive_projects = array();
		$surveys = $this->Project->find('all', array(
			'conditions' => array(
				array(
					'Project.status' => 'Open',
					'Project.date_created <' => date(DB_DATE, time() - 86400 * 7)
				)
			),
			'order' => 'Project.id asc'
		));
		foreach ($surveys as $survey) {
			$survey_visit = $this->SurveyVisit->find('first', array(
				'conditions' => array(
					'SurveyVisit.survey_id' => $survey['Project']['id']
				),
				'order' => 'SurveyVisit.id DESC'
			));
			if ($survey_visit) {
				if (((time() - strtotime($survey_visit['SurveyVisit']['created'])) / 86400) < 7) {
					continue;
				}
			}
			$inactive_projects[] = $survey;
		}
		
		if (!empty($inactive_projects)) {
			$return = array();
			foreach ($inactive_projects as $project) {
				$return[] = '#'.$project['Project']['id'].' '.$project['Project']['prj_name']
					."\n"
					.HOSTNAME_WEB.'/surveys/dashboard/'.$project['Project']['id'];
			}
			CakePlugin::load('Mailgun');
			$email = new CakeEmail();
			$email->config('mailgun');
			$email->from(array(EMAIL_SENDER => 'MintVine'))
				->replyTo(array(REPLYTO_EMAIL => 'MintVine'))
				->emailFormat('html')
			 	->to(array('support@brandedresearchinc.com'))
			    ->subject(count($inactive_projects).' - Inactive Project Report');
			$result = $email->send(nl2br(implode("\n\n", $return)));
			echo 'Mail sent'."\n";
		}
	}
	
	// create groupon transactions
	function groupon() {
		$orders = $this->Ledger->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'Ledger.transaction_id is null'
			),
			'order' => 'Ledger.id DESC'
		));
		foreach ($orders as $order) {
			if (empty($order['Ledger']['user_id']) || empty($order['Ledger']['commission'])) {
		CakeLog::write('Groupon', 'Error (Missing data)'.print_r($order, true));
				continue;
			}
			if ($order['Ledger']['country'] != 'US') {
				CakeLog::write('Groupon', 'Error (NOT US)'.print_r($order, true));
				continue;
			}
			$transaction = $this->Transaction->find('first', array(
				'recursive' => -1,
				'conditions' => array(
					'Transaction.type_id' => TRANSACTION_GROUPON,
					'Transaction.user_id' => $order['Ledger']['user_id'],
					'Transaction.linked_to_id' => $order['Ledger']['order_id'],
					'Transaction.deleted' => null,
				)
			));
			if (!$transaction) {
				$points = ceil($order['Ledger']['commission'] / 2);
				$transactionSource = $this->Transaction->getDataSource();
				$transactionSource->begin();
				$this->Transaction->create();
				$this->Transaction->save(array('Transaction' => array(
					'type_id' => TRANSACTION_GROUPON,
					'linked_to_id' => $order['Ledger']['order_id'],
					'user_id' => $order['Ledger']['user_id'],
					'amount' => $points,
					'paid' => false,
					'name' => 'Local Deals - Order # '.$order['Ledger']['order_id'],
					'status' => TRANSACTION_PENDING,
					'executed' => date(DB_DATETIME)
				)));
				$transaction_id = $this->Transaction->getInsertId();
				$transactionSource->commit();
				$this->Ledger->create();
				$this->Ledger->save(array('Ledger' => array(
    				'id' => $order['Ledger']['id'],
					'transaction_id' => $transaction_id,
				)), true, array('transaction_id'));
			}
			else {
				$this->Ledger->create();
				$this->Ledger->save(array('Ledger' => array(
    				'id' => $order['Ledger']['id'],
					'transaction_id' => $transaction['Transaction']['id'],
				)), true, array('transaction_id'));
			}
		} 
	}
	
	/*** 
	 *  args: user_id, dryrun
	 */
	function payout_pending_transactions() {
		if (isset($this->args[0]) && $this->args[0] != 'dryrun') {
			$pending_transactions = $this->Transaction->find('all', array(
				'conditions' => array(
					'OR' => array(
						array('Transaction.status' => TRANSACTION_PENDING),
						array(
							'Transaction.status' => TRANSACTION_APPROVED,
							'Transaction.paid' => false
						)
					),
					'Transaction.deleted' => null,
					'Transaction.executed <=' => date(DB_DATETIME, time() - 86400 * 14),
					'Transaction.user_id' => $this->args[0],
				),
				'order' => 'Transaction.id ASC'
			));
		}
		else {
			$pending_transactions = $this->Transaction->find('all', array(
				'conditions' => array(
					'OR' => array(
						array('Transaction.status' => TRANSACTION_PENDING),
						array(
							'Transaction.status' => TRANSACTION_APPROVED,
							'Transaction.paid' => false
						)
					),
					'Transaction.deleted' => null,
					'Transaction.executed <=' => date(DB_DATETIME, time() - 86400 * 14),
					'NOT' => array(
						'Transaction.type_id' => TRANSACTION_DWOLLA
					)
				),
				'order' => 'Transaction.id ASC'
			));
		}
		$i = $amount = 0;
		foreach ($pending_transactions as $transaction) {
			if ($transaction['User']['hellbanned']) {
				continue;
			}
			if (isset($this->args[1]) || (isset($this->args[0]) && $this->args[0] == 'dryrun')) {
				print_r($transaction);
			}
			else {
				// groupon is handled differently
				if ($transaction['Transaction']['type_id'] == TRANSACTION_GROUPON) {
					if (date(DB_DATE, strtotime($transaction['Transaction']['executed'])) > date(DB_DATE, time() - 86400 * 45)) {
						continue;
					}
					$ledger = $this->Ledger->find('first', array(
						'conditions' => array(
							'Ledger.transaction_id' => $transaction['Transaction']['id']
						)
					));
					if (!$ledger || $ledger['Ledger']['status'] != 'LOCKED') {
						continue;
					}
				}
			
				// Dwolla pays out manually
				if ($transaction['Transaction']['type_id'] == TRANSACTION_DWOLLA) {
					continue;
				}
				// ok, pay it out
				$this->Transaction->create();
				$this->Transaction->approve($transaction);
				echo $transaction['Transaction']['id']."\n";
			}
			$i++;
			$amount = $amount + $transaction['Transaction']['amount'];
		}
	
		echo 'Paid out '.$i.' transactions for '.$amount;
	}
	
	// because referrals may not be correctly handled at real-time, we retroactively go back and find users
	// arguments: user_id (optional)
	function referral() {
		$logging_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		ini_set('memory_limit', '740M');
				
		if (isset($this->args[0])) {
			$conditions = array(
				'User.id' => $this->args[0]
			);
			$this->lecho('Starting referral bonuses for '.$this->args[0], 'referrals.account', $logging_key);
		}
		else {
			$conditions = array(
				'User.referred_by >' => '0',
				'User.active' => true,
				'User.deleted_on' => null,
				'User.hellbanned' => false,
				'User.last_touched >=' => date(DB_DATE, time() - 86400 * 5) // last 5 days of activity
			);
			$this->lecho('Starting referral bonuses', 'referrals.account', $logging_key);
		}
		
		$i = 0; // paid users
		$users = $this->User->find('all', array(
			'fields' => array('id', 'referred_by', 'username'),
			'recursive' => -1,
			'conditions' => $conditions
		));
		$this->lecho('Processing '.count($users).' users', 'referrals.account', $logging_key);
		
		foreach ($users as $user) {
			$referring_user = $this->User->find('first', array(
				'fields' => array('id'),
				'conditions' => array(
					'User.id' => $user['User']['referred_by'],
				),
				'recursive' => -1
			));
			if (!$referring_user) {
				// don't unset this value: may need it for historical reasons
				$this->lecho('[FAILED] Cannot find referring user #'.$user['User']['referred_by'].' for #'.$user['User']['id'], 'referrals.account', $logging_key);
				continue;
			}
			$referral_count = $this->Transaction->find('count', array(
				'recursive' => -1,
				'conditions' => array(
					'Transaction.type_id' => TRANSACTION_REFERRAL,
					'Transaction.user_id' => $referring_user['User']['id'],
					'Transaction.referrer_id' => $user['User']['id'],
					'Transaction.amount' => '50',
					'Transaction.name' => 'Referral Bonus (Registration)',
					'Transaction.deleted' => null,
				)
			));
			// if we find no referral payout for the referring user
			if ($referral_count == 0) {
				$survey = $this->Transaction->find('first', array(
					'recursive' => -1,
					'conditions' => array(
						'Transaction.user_id' => $user['User']['id'],
						'Transaction.status' => array(TRANSACTION_PENDING, TRANSACTION_APPROVED),
						'Transaction.type_id' => TRANSACTION_SURVEY,
						'Transaction.deleted' => null,
					)
				));
				if ($survey) {
					$i++;
					$transactionSource = $this->Transaction->getDataSource();
					$transactionSource->begin();
					$this->Transaction->create();
					$save = $this->Transaction->save(array('Transaction' => array(
						'type_id' => TRANSACTION_REFERRAL,
						'user_id' => $user['User']['referred_by'],
						'name' => 'Referral Bonus (Registration)',
						'linked_to_id' => $survey['Transaction']['id'],
						'linked_to_name' => 'Referral Bonus (Registration)',
						'referer_username' => $user['User']['username'],
						'referrer_id' => $user['User']['id'],
						'amount' => 50,
						'paid' => false,
						'status' => TRANSACTION_PENDING,
						'executed' => date(DB_DATETIME)
					)));
					$transaction_id = $this->Transaction->getInsertId();
					$transaction = $this->Transaction->findById($transaction_id);
					$this->Transaction->approve($transaction);
					$transactionSource->commit();
					$this->lecho('[SUCCESS] Paid #'.$user['User']['referred_by'].' for #'.$user['User']['id'], 'referrals.account', $logging_key);
				}
				else {
					$this->lecho('[SKIPPED] No survey responses found on #'.$user['User']['referred_by'].' for #'.$user['User']['id'], 'referrals.account', $logging_key);
				}
			}
			else {
				$this->lecho('[SKIPPED] Referral bonus already paid to #'.$user['User']['referred_by'].' for #'.$user['User']['id'], 'referrals.account', $logging_key);
			}
		}
		$this->lecho('Completed paying '.$i.' users (Execution time: '.(microtime(true) - $time_start).')', 'referrals.account', $logging_key);
	}
	
	// find all survey referrals and pay those users out
	function survey_referrals() {
		$logging_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);
		ini_set('memory_limit', '740M');
		$this->lecho('Starting referral transactions', 'referrals.transaction', $logging_key);
		
		$this->Transaction->bindModel(array(
			'belongsTo' => array(
				'Project' => array(				
					'foreignKey' => 'linked_to_id'
				)
			)
		));
		$transactions = $this->Transaction->find('all', array(
			'fields' => array('Transaction.id', 'User.id', 'Transaction.amount', 'User.referred_by', 'Project.description', 'User.username', 'Project.id'),
			'conditions' => array(
				'Transaction.status' => array(TRANSACTION_PENDING, TRANSACTION_APPROVED),
				'Transaction.type_id' => array(TRANSACTION_SURVEY),
				'Transaction.created >=' => date(DB_DATETIME, time() -2 * 86400), 
				'User.referred_by >' => '0',
				'Transaction.deleted' => null,
			)
		));
		$this->lecho('Found '.count($transactions).' transactions', 'referrals.transaction', $logging_key);
		$award_ratio = 0.15;
		$i = 0;
		foreach ($transactions as $transaction) {
			// find a referral survey
			$referral = $this->Transaction->find('first', array(
				'recursive' => -1,
				'conditions' => array(
					'Transaction.type_id' => TRANSACTION_REFERRAL,
					'Transaction.user_id' => $transaction['User']['referred_by'],
					'Transaction.linked_to_id' => $transaction['Transaction']['id'], 
					'Transaction.amount' => round($award_ratio * $transaction['Transaction']['amount']),
					'Transaction.deleted' => null,
				)
			));
			// create a missing referral transaction
			if (!$referral) {
				$i++;
				$this->Transaction->create();
				$this->Transaction->save(array('Transaction' => array(
					'type_id' => TRANSACTION_REFERRAL,
					'user_id' => $transaction['User']['referred_by'],
					'linked_to_id' => $transaction['Transaction']['id'],
					'linked_to_name' => 'Bonus from '.$transaction['User']['username'].' for "'.$transaction['Project']['description'].'" (#'.$transaction['Project']['id'].')',
					'amount' => round($award_ratio * $transaction['Transaction']['amount']),
					'name' => 'Bonus from '.$transaction['User']['username'].' for "'.$transaction['Project']['description'].'" (#'.$transaction['Project']['id'].')',
					'status' => TRANSACTION_PENDING,
					'paid' => false,
					'executed' => date(DB_DATETIME)
				)));
				$this->lecho('Created referral transaction for #'.$transaction['Transaction']['id'].' ('.round($award_ratio * $transaction['Transaction']['amount']).' points)', 'referrals.transaction', $logging_key);
			}
		}
		$this->lecho('Completed paying '.$i.' transactions (Execution time: '.(microtime(true) - $time_start).')', 'referrals.transaction', $logging_key);
	}
	
	function bounced() {
		App::uses('HttpSocket', 'Network/Http');

		$HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));

		$i = 0;
		// string query
		$results = $HttpSocket->get('https://api:key-9qy3jxazer-pfjbfvxi1ba3vtw4r0o52@api.mailgun.net/v2/mintvine.com/bounces', 'limit=1000');
		$items = json_decode($results->body);
		foreach ($items->items as $item) {
			$user = $this->User->find('first', array(
				'fields' => array('id', 'email', 'send_email', 'send_survey_email', 'created', 'login'),
				'conditions' => array(
					'User.email' => $item->address,
					'User.send_email' => true
				),
				'recursive' => -1
			));
			if ($user) {
				$this->User->create();
				$this->User->save(array('User' => array(
					'id' => $user['User']['id'],
					'send_email' => false,
					'send_survey_email' => false
				), true, array('send_email', 'send_survey_email')));
				$HttpSocket->delete('https://api:key-9qy3jxazer-pfjbfvxi1ba3vtw4r0o52@api.mailgun.net/v2/mintvine.com/bounces/'.$item->address);
				$i++;
				echo $user['User']['id']."\n";
			}
		}
		echo "Removed ".$i."\n";
	}
		
	function ledger() {
		$access_url = 'https://partner-api.groupon.com/v1/ledger.json?column=0&ascending=true&clientId='.GROUPON_CLIENT_ID;
		$json = file_get_contents($access_url);
		$ledgers = json_decode($json, true);
		$total = $ledgers['data']['total'];
		$limit = 50;
		$pages = ceil($total / $limit);
		for ($i = 0; $i < $pages; $i++) {
			$start = $i * $limit;
			$end = ($i + 1) * $limit;
			$page_access_url = 'https://partner-api.groupon.com/v1/ledger.json?column=0&ascending=true&clientId='.GROUPON_CLIENT_ID.'&start='.$start.'&end='.$end;
			$page_json = file_get_contents($page_access_url);
			$page_ledgers = json_decode($page_json, true);
			$page_records = $page_ledgers['data']['records'];
			foreach ($page_records as $record) {
				$ledger = $this->Ledger->findByOrderId($record['orderId']);
				$id = 0;
				$locked = null;
				if ($ledger) {
					$id = $ledger['Ledger']['id'];
					if ($ledger['Ledger']['status'] == 'OPEN' && $record['orderStatus'] == 'LOCKED') {
						$locked = date(DB_DATETIME);
					}
				}
				$this->Ledger->create();
				$this->Ledger->save(array('Ledger' => array(
					'id' => $id,						
					'order_id' => $record['orderId'],
					'gross' => $record['grossMinorUnits'],
					'commission' => $record['commissionMinorUnits'],
					'user_id' => $record['sid'],
					'status' => $record['orderStatus'],
					'country' => $record['country']['isoCode'],
					'order' => date(DB_DATETIME, strtotime($record['transactionDate'])),
					'locked' => $locked,
				)));
			}
		}
		echo 'Ledger imported'."\n";
	}
	
	function user_reminder() {
		CakePlugin::load('Mailgun');
		$days_to_resend = array(2, 5);
		$emailed = array();
				
		foreach ($days_to_resend as $key => $day) {
			$conditions = array(
				'User.email <>' => null, 
				'User.active' => false,
				'User.deleted_on' => null,
				'User.send_email' => true,
				'User.verified is null'
			);
			if ($day == 2) {
				$conditions['User.created >='] = date('Y-m-d', time() - 86400 * $day) . ' 00:00:00';
				$conditions['User.created <='] = date('Y-m-d', time() - 86400 * $day) . ' 23:59:59';
				$conditions['User.last_emailed_date'] = null;
			}
			else {
				$conditions['User.created >='] = date('Y-m-d', time() - 86400 * $day). ' 00:00:00';
				$conditions['User.created <='] = date('Y-m-d', time() - 86400 * $day). ' 23:59:59';
				$conditions['User.last_emailed_date >='] = date('Y-m-d', time() - 86400 * ($day  - $days_to_resend[$key - 1])) . ' 00:00:00';
				$conditions['User.last_emailed_date <='] = date('Y-m-d', time() - 86400 * ($day  - $days_to_resend[$key - 1])) . ' 23:59:59';
			}
			
			$users = $this->User->find('all', array(
				'recursive' => -1,
				'conditions' => $conditions,
				'order' => 'User.id DESC'
			));
			
			if (!empty($users)) {
				foreach ($users as $user) {
					if (!$user['User']['email'] || in_array($user['User']['email'], $emailed)) {
						continue;
					}
					
					// grab a registration nonce
					$nonce = $this->Nonce->find('first', array(
						'conditions' => array(
							'Nonce.item_type' => 'registration',
							'Nonce.user_id' => $user['User']['id'],
							'Nonce.item_id' => '0',
							'Nonce.used' => null
						)
					));
					if (!$nonce) {
						$nonce = String::uuid();
						$this->Nonce->create();
						$this->Nonce->save(array('Nonce' => array(
							'item_type' => 'registration',
							'nonce' => $nonce,
							'user_id' => $user['User']['id'],
							'item_id' => '0'
						)));
					}
					else {
						$nonce = $nonce['Nonce']['nonce'];
					}
					
					$emailed[] = $user['User']['email'];
					CakeLog::write('reminders', $user['User']['email'].' '.$user['User']['created'].' '.$user['User']['last_emailed_date'].' ('.$day.')');
					
					$unsubscribe_link = HOSTNAME_WWW.'/users/emails/' . $user['User']['ref_id'];
					$setting = $this->Setting->find('list', array(
						'fields' => array('Setting.name', 'Setting.value'),
						'conditions' => array(
							'Setting.name' => array('cdn.url'),
							'Setting.deleted' => false
						)
					));
					if (!empty($setting['cdn.url']) && (!defined('IS_DEV_INSTANCE') || !IS_DEV_INSTANCE)) {
						Configure::write('App.cssBaseUrl', $setting['cdn.url'] . '/');
						Configure::write('App.jsBaseUrl', $setting['cdn.url'] . '/');
						Configure::write('App.imageBaseUrl', $setting['cdn.url'] . '/img/');
					}
					$email = new CakeEmail();
					$email->config('mailgun');
					$email->from(array(EMAIL_SENDER => 'MintVine'))
						->replyTo(array(REPLYTO_EMAIL => 'MintVine'))
						->template('email_confirm_resend')
						->viewVars(array(
							'nonce' => $nonce,
							'user' => $user, 
							'unsubscribe_link' => $unsubscribe_link, 
						))
						->emailFormat('html')
		    			->to($user['User']['email'])
		    			->subject('Hi, '.$user['User']['username'].' - Remember to Activate Your Account!')
		   				->send();
		
		   			$this->User->save(array(
						'id' => $user['User']['id'],
						'last_emailed_date' => date(DB_DATETIME, time())
					), true, array('last_emailed_date'));
				}
			}
		}
	}
	
	// Make a withdrawal transaction for every user, with active dwolla payment method, having more than 1,000 points.
	function dwolla_withdrawals() {
		$logging_key = strtoupper(Utils::rand('4'));
		$this->lecho('Starting', 'dwolla.withdrawals', $logging_key); 
		
		$this->PaymentMethod->bindModel(array('belongsTo' => array(
			'User' => array(
				'fields' => array('id', 'hellbanned', 'active', 'last_touched', 'balance')
			)
		)));
		$payment_methods = $this->PaymentMethod->find('all', array(
			'conditions' => array(
				'PaymentMethod.status' => DB_ACTIVE,
				'PaymentMethod.payment_method' => 'dwolla',
				'User.last_touched >=' => date(DB_DATETIME, strtotime('-5 days')),
				'User.balance >=' => '1000',
				'User.active' => true,
				'User.hellbanned' => false
			)
		));
		$this->lecho('Found '.count($payment_methods).' users to pay out', 'dwolla.withdrawals', $logging_key); 
		if ($payment_methods) {
			foreach ($payment_methods as $payment_method) {
				$count = $this->Transaction->find('count', array(
					'conditions' => array(
						'Transaction.status' => TRANSACTION_PENDING,
						'Transaction.user_id' => $payment_method['User']['id'],
						'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
						'Transaction.deleted' => null,
					),
					'recursive' => -1
				));
				if ($count > 0) {
					$this->lecho('Skipped #'.$payment_method['User']['id'].' due to a pending withdrawal', 'dwolla.withdrawals', $logging_key); 
					continue;
				}

				$save = true;
				//todo: IP check. I think we should check the last login ip of the user logged in UserIP.

				$prj_invite_reports_dataSource = $this->User->getDataSource();
				$prj_invite_reports_dataSource->begin();
				if ($save) {
					// regenerate user balance for sanity check
					$this->User->rebuildBalances($payment_method);
					$user_record = $this->User->find('first', array(
						'conditions' => array(
							'User.id' => $payment_method['User']['id']
						),
						'recursive' => -1,
						'fields' => array('User.id', 'User.balance', 'User.withdrawal')
					));
					if (($user_record['User']['balance'] + $user_record['User']['withdrawal']) < 1000) {
						$save = false;
						$this->lecho('Skipped #'.$payment_method['User']['id'].' - balance was under 1,000 points after a manual recalculation of balance', 'dwolla.withdrawals', $logging_key); 
					}
				}

				if ($save) {
					$this->Transaction->create();
					$this->Transaction->save(array('Transaction' => array(
						'amount' => $user_record['User']['balance'] * -1, // withdrawals are negative
						'user_id' => $payment_method['User']['id'],
						'linked_to_id' => $payment_method['PaymentMethod']['id'],
						'linked_to_name' => $payment_method['PaymentMethod']['payment_method'],
						'paid' => true,
						'name' => 'Payout via Dwolla',
						'status' => TRANSACTION_PENDING,
						'executed' => date(DB_DATETIME),
						'type_id' => TRANSACTION_WITHDRAWAL
					)));
					$user = $this->User->find('first', array(
						'fields' => array('id', 'balance', 'withdrawal', 'pending'),
						'conditions' => array(
							'User.id' => $payment_method['User']['id']
						),
						'recursive' => -1
					));
					$this->lecho('Processed #'.$payment_method['User']['id'].' for '.$user_record['User']['balance'].' points', 'dwolla.withdrawals', $logging_key); 
				}
				$prj_invite_reports_dataSource->commit();
			}
		}
	}
	
	function import_survey_hide_data() {
		$models_to_import = array('SurveyUser', 'Project', 'SurveyVisitCache');
		foreach ($models_to_import as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		// only work with active projects
		$projects = $this->Project->find('list', array(
			'fields' => array('id', 'id'),
			'conditions' => array(
				'Project.status' => PROJECT_STATUS_OPEN
			),
			'recursive' => -1
		));		
		foreach ($projects as $project_id) {
			$records = $this->SurveyUser->find('all', array(
				'fields' => array('SurveyUser.survey_id', 'count(hidden) as count', 'SurveyUser.hidden', 'SurveyVisitCache.id'),
				'group' => 'hidden',
				'joins' => array(
					array(
						'alias' => 'SurveyVisitCache',
						'table' => 'survey_visit_caches',
						'type' => 'INNER',
						'conditions' => array(
							'SurveyVisitCache.survey_id = SurveyUser.survey_id',
						)
					)
				),
				'conditions' => array(
					'SurveyUser.survey_id' => $project_id
				)
			));
			if (!$records) {
				echo "data not found!". "\n";
			}
		
			$prj_invite_reports_dataset = array();
			foreach ($records as $record) {
				if ($record['SurveyUser']['hidden'] == 1) {
					$prj_invite_reports_dataset[$record['SurveyVisitCache']['id']]['hidden_no_reason'] = $record[0]['count'];
				}
				elseif ($record['SurveyUser']['hidden'] == 2) {
					$prj_invite_reports_dataset[$record['SurveyVisitCache']['id']]['hidden_too_long'] = $record[0]['count'];
				}
				elseif ($record['SurveyUser']['hidden'] == 3) {
					$prj_invite_reports_dataset[$record['SurveyVisitCache']['id']]['hidden_too_small'] = $record[0]['count'];
				}
				elseif ($record['SurveyUser']['hidden'] == 4) {
					$prj_invite_reports_dataset[$record['SurveyVisitCache']['id']]['hidden_not_working'] = $record[0]['count'];
				}
				elseif ($record['SurveyUser']['hidden'] == 5) {
					$prj_invite_reports_dataset[$record['SurveyVisitCache']['id']]['hidden_do_not_want'] = $record[0]['count'];
				}
				elseif ($record['SurveyUser']['hidden'] == 6) {
					$prj_invite_reports_dataset[$record['SurveyVisitCache']['id']]['hidden_other'] = $record[0]['count'];
				}
			}
		
			foreach ($prj_invite_reports_dataset as $survey_visit_cache_id => $prj_invite_reports_data) {
				$prj_invite_reports_data['id'] = $survey_visit_cache_id;
				$this->SurveyVisitCache->create();
				$this->SurveyVisitCache->save(array(
					'SurveyVisitCache' => $prj_invite_reports_data
				), true, array_keys($prj_invite_reports_data));
				echo "Data saved for SurveyVisitCache id=".$prj_invite_reports_data['id']."\n";
			}
		}
	}
	
	// new method that uses SQS	
	// args: $transaction_id
	function payouts() {
		$logging_key = strtoupper(Utils::rand('4'));
		// initialize email
		
		$bcc_setting = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'payouts.bcc',
				'Setting.deleted' => false
			)
		));
		if ($bcc_setting) {
			$bcc = explode(',', $bcc_setting['Setting']['value']);
			array_walk($bcc, create_function('&$val', '$val = trim($val);')); 
		}
		else {
			$bcc = array();
		}
		
		CakePlugin::load('Mailgun');
		$setting = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('cdn.url'),
				'Setting.deleted' => false
			)
		));
		if (!empty($setting['cdn.url']) && (!defined('IS_DEV_INSTANCE') || !IS_DEV_INSTANCE)) {
			Configure::write('App.cssBaseUrl', $setting['cdn.url'] . '/');
			Configure::write('App.jsBaseUrl', $setting['cdn.url'] . '/');
			Configure::write('App.imageBaseUrl', $setting['cdn.url'] . '/img/');
		}
		$email = new CakeEmail();
		$email->config('mailgun');
		$email->from(array(EMAIL_SENDER => 'MintVine'))
			->replyTo(array(REPLYTO_EMAIL => 'MintVine'))
			->emailFormat('html');
			
		// initialize all the payout components
		// todo these should be moved into tasks or libs
		
		$models_to_import = array('PaymentMethod', 'Transaction', 'User', 'CashNotification', 'PaymentLog');
		foreach ($models_to_import as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		$i = 0; 
		
		// set up amazon SQS
		App::import('Vendor', 'sqs');
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('sqs.access.key', 'sqs.access.secret', 'sqs.payout.queue'),
				'Setting.deleted' => false
			)
		));
		if (count($settings) < 3) {
			return false;
		}
		$this->lecho('Starting', 'payouts', $logging_key); 
		$sqs = new SQS($settings['sqs.access.key'], $settings['sqs.access.secret']);
		$processed_ids = array();
		$i = 0;
		while (true) {
			if (!isset($this->args[0])) {
				$results = $sqs->receiveMessage($settings['sqs.payout.queue']);
				print_r($results);
				$transaction_id = false;
				if (!empty($results['Messages'])) {
					$transaction_id = $results['Messages'][0]['Body'];
				}
				if (empty($results['Messages'])) {
					$this->lecho('Completed processing '.$i.' payouts', 'payouts', $logging_key); 
					break;
				}
			}
			else {
				$transaction_id = $this->args[0];
			}
			
			if ($transaction_id) {
				// sanity check; the moment we see the same transaction ID, kill the loop
				if (in_array($transaction_id, $processed_ids)) {
					$this->lecho('Completed processing '.$i.' payouts', 'payouts', $logging_key); 
					break;
				}
				$this->lecho('Processing #'.$transaction_id, 'payouts', $logging_key); 
				$processed_ids[] = $transaction_id;
				$transaction = $this->Transaction->find('first', array(
					'conditions' => array(
						'Transaction.id' => $transaction_id,
						'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
						'Transaction.deleted' => null,
					)
				));
				if (!$transaction) {
					if (!isset($this->args[0])) {
						$sqs->deleteMessage($settings['sqs.payout.queue'], $results['Messages'][0]['ReceiptHandle']);
					}
					$this->lecho('Could not find #'.$transaction_id, 'payouts', $logging_key);
					continue;
				}
				
				// if transaction is more than 48 hours... then do something else
				
				$this->PaymentLog->log($transaction);
				
				// this was manually processed
				if ($transaction['Transaction']['payout_processed'] == PAYOUT_SUCCEEDED) {
					$this->lecho('#'.$transaction_id.' has been marked as paid already.', 'payouts', $logging_key); 
					if (!isset($this->args[0])) {
						$sqs->deleteMessage($settings['sqs.payout.queue'], $results['Messages'][0]['ReceiptHandle']);
					}
					$this->PaymentLog->log($transaction, PAYMENT_LOG_ABORTED, array('MV Error' => 'Transaction has already been paid.'));
					continue;
				}
				// Sanity check: make sure there is not an existing payment made in same amount to same user id within the past 12 hours
				$notification = $this->CashNotification->find('first', array(
					'conditions' => array(
						'user_id' => $transaction['Transaction']['user_id'],
						'amount' => (-1 * $transaction['Transaction']['amount']),
						'created >=' => date(DB_DATETIME, time() - 43200)
					)
				));
				if ($notification) {
					$this->lecho('#'.$transaction_id.' has been marked as paid already via cash notifications.', 'payouts', $logging_key); 
					$this->PaymentLog->log($transaction, PAYMENT_LOG_ABORTED, array('MV Error' => 'Cashout notification exists for this transaction, within the past 12 hours.'));
					continue;
				}
			
				// If payment_method id is linked to transaction, we use that payment method, else we use the active payment_mehtod.
				if ($transaction['Transaction']['linked_to_id']) {
					$conditions = array('id' => $transaction['Transaction']['linked_to_id']);
				}
				else {
					$conditions = array(
						'user_id' => $transaction['User']['id'],
						'status' => DB_ACTIVE,
						'payment_method' => array('paypal', 'dwolla', 'tango', 'mvpay')
					);
				}

				$payment_method = $this->PaymentMethod->find('first', array('conditions' => $conditions));
				if ($transaction['Transaction']['payout_processed'] == PAYOUT_FAILED) {
					// two business days to resolve issues before automatically returning the cash
					if (Utils::business_days($transaction['Transaction']['executed'], 2) < date(DB_DATETIME)) {
						
						if (!empty($transaction['User']['email'])) {
							$email = new CakeEmail();
							$email->config('mailgun');
							$email->from(array(EMAIL_SENDER => 'MintVine'))
								->replyTo(array(REPLYTO_EMAIL => 'MintVine'))
								->emailFormat('html');
							
							$email->template('payout_failure')
								->viewVars(array(
									'type' => $payment_method['PaymentMethod']['payment_method'],
									'payment_value' => $payment_method['PaymentMethod']['value'],
									'transaction' => $transaction,
									'username' => $transaction['User']['username']
								))
								->to(array($transaction['User']['email']))
								->subject('MintVine Payout Failure');
							
							if (!empty($bcc)) {
								$email->bcc($bcc);
							}
							$email->send();
						}
						
						$this->Transaction->create();
						$this->Transaction->delete($transaction['Transaction']['id']);
						
						// After deleting the transaction we need to rebuild the user balance
						$this->User->rebuildBalances($transaction);
			
						CakeLog::write('payouts.returned', 'Returned '.(-1 * $transaction['Transaction']['amount']).' points to '.$transaction['User']['email']);
						if (!isset($this->args[0])) {
							// mark this as received and add it back into the queue until it processes
							$sqs->deleteMessage($settings['sqs.payout.queue'], $results['Messages'][0]['ReceiptHandle']);
							$this->lecho('Deleted #'.$transaction['Transaction']['id'].' due to repeated failure attempts', 'payouts', $logging_key); 
						}
						continue;
					}
				}
				
				$this->lecho('#'.$transaction_id.' linked to payment method #'.$payment_method['PaymentMethod']['id'], 'payouts', $logging_key); 
				$this->lecho($payment_method, 'payouts', $logging_key); 
				$this->lecho('#'.$transaction_id.' processing via '.$payment_method['PaymentMethod']['payment_method'], 'payouts', $logging_key); 
				$save = false;
				if ($payment_method && $payment_method['PaymentMethod']['payment_method'] == 'paypal') {
					$this->PaymentPaypal = $this->Tasks->load('PaymentPaypal');
					if ($this->PaymentPaypal->execute($transaction, $payment_method['PaymentMethod']['value'])) {
						$save = true;
					}
					else {
						$this->lecho('[FAILED] #'.$transaction_id.' - please view payouts.paypal logs', 'payouts', $logging_key); 
					}
				}
				elseif ($payment_method && $payment_method['PaymentMethod']['payment_method'] == 'dwolla') {
					$this->PaymentDwolla = $this->Tasks->load('PaymentDwolla');
					if ($this->PaymentDwolla->execute($transaction, $payment_method['PaymentMethod']['payment_id'])) {
						$save = true;
					}
					else {
						$this->lecho('[FAILED] #'.$transaction_id.' - please view payouts.dwolla logs', 'payouts', $logging_key); 
					}
				}
				// todo : deprecate this check after some time, this is because the auto dwolla payout used dwolla_id record id in Transaction.linked_to_id 
				// temporary band-aid: this should not be happening; either the transaction is linked to dwolla or the dwolla_id
				elseif ($payment_method && $payment_method['PaymentMethod']['payment_method'] == 'dwolla_id') {
					$this->PaymentDwolla = $this->Tasks->load('PaymentDwolla');
					if ($this->PaymentDwolla->execute($transaction, $payment_method['PaymentMethod']['value'])) {
						$save = true;
					}
					else {
						$this->lecho('[FAILED] #'.$transaction_id.' - please view payouts.dwolla logs', 'payouts', $logging_key); 
					}
				}
				elseif ($payment_method && $payment_method['PaymentMethod']['payment_method'] == 'tango') {
					$this->PaymentTango = $this->Tasks->load('PaymentTango');
					if ($this->PaymentTango->execute($transaction, $payment_method['PaymentMethod']['payment_id'])) {
						$save = true;
					}
					else {
						$this->lecho('[FAILED] #'.$transaction_id.' - please view payouts.tango logs', 'payouts', $logging_key); 
					}
				}
				elseif ($payment_method && $payment_method['PaymentMethod']['payment_method'] == 'mvpay') {
					$this->PaymentMvpay = $this->Tasks->load('PaymentMvpay');
					if ($this->PaymentMvpay->execute($transaction, $payment_method['PaymentMethod']['payment_id'])) {
						$save = true;
					}
					else {
						$this->lecho('[FAILED] #'.$transaction_id.' - please view payouts.mvpay logs', 'payouts', $logging_key); 
					}
				}
				else {
					print_r($payment_method); exit();
					$this->lecho('#'.$transaction_id.' using invalid payment type', 'payouts', $logging_key); 
				}
				
				if ($save) {
					$this->lecho('#'.$transaction_id.' PAID', 'payouts', $logging_key); 
					if (!isset($this->args[0])) {
						$sqs->deleteMessage($settings['sqs.payout.queue'], $results['Messages'][0]['ReceiptHandle']);
					}
					
					$i++;
					$this->Transaction->getDatasource()->reconnect();
					// mark transaction as processed
					$this->Transaction->save(array('Transaction' => array(
						'id' => $transaction['Transaction']['id'],
						'payout_processed' => PAYOUT_SUCCEEDED
					)), true, array('payout_processed'));

					// update user balance
					$this->User->rebuildBalances($transaction);

					// cash out notification
					$this->CashNotification->create();
					$this->CashNotification->save(array('CashNotification' => array(
						'user_id' => $transaction['Transaction']['user_id'],
						'type' => $payment_method['PaymentMethod']['payment_method'],
						'amount' => (-1 * $transaction['Transaction']['amount'])
					)));

					// send cash out email
					$user = $this->User->find('first', array(
						'conditions' => array(
							'User.id' => $transaction['Transaction']['user_id']
						),
						'fields' => array('id', 'username', 'ref_id', 'email', 'medvine'),
						'recursive' => -1
					));
					
					// skip medvine
					if ($user['User']['medvine']) {
						continue;
					}
				
					$trustpilot_count = $this->UserOption->find('count', array(
						'conditions' => array(
							'UserOption.user_id' => $user['User']['id'],
							'UserOption.name' => 'trustpilot.invite',
						)
					));
					
					$email->template('payout')
						->viewVars(array(
							'user_name' => $user['User']['username'],
							'user_id' => $user['User']['id'],
							'payment_method' => $payment_method['PaymentMethod']['payment_method'],
							'payment_id' => $payment_method['PaymentMethod']['payment_id'],
							'amount' => (-1 * $transaction['Transaction']['amount']) / 100,
							'unsubscribe_link' => HOSTNAME_WWW.'/users/emails/' . $user['User']['ref_id'],
							'trustpilot' => ($trustpilot_count == 0) ? true : false
						))
						->to(array($user['User']['email']));
					if ($trustpilot_count == 0) {
						$trustpilot_setting = $this->Setting->find('first', array(
							'fields' => array('Setting.value'),
							'conditions' => array(
								'Setting.name' => 'trustpilot.email',
								'Setting.deleted' => false
							)
						));
						if ($trustpilot_setting && !empty($trustpilot_setting['Setting']['value'])) {
							$email->bcc($trustpilot_setting['Setting']['value']);
						}
					}
					
					$email->subject('MintVine Payout Complete')
						->send();
					if ($trustpilot_count == 0) {
						$this->UserOption->create();
						$this->UserOption->save(array('UserOption' => array(
							'user_id' => $user['User']['id'],
							'name' => 'trustpilot.invite',
							'value' => date(DB_DATETIME)
						)));
					}
				}
				else { // payout processed failed! 
					$this->Transaction->getDatasource()->reconnect();
					// mark transaction as processed
					$this->Transaction->save(array('Transaction' => array(
						'id' => $transaction['Transaction']['id'],
						'payout_processed' => PAYOUT_FAILED
					)), true, array('payout_processed'));
					
					if (!isset($this->args[0])) {
						// mark this as received and add it back into the queue until it processes
						$sqs->deleteMessage($settings['sqs.payout.queue'], $results['Messages'][0]['ReceiptHandle']);
						$response = $sqs->sendMessage($settings['sqs.payout.queue'], $transaction['Transaction']['id']);
						$this->lecho('Requeued #'.$transaction_id.' for processing', 'payouts', $logging_key); 
					}
				}
			}
		}	
	}

	public function refresh_dwolla_tokens() {
		$models_to_import = array('PaymentMethod');
		foreach ($models_to_import as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		// Dwolla accounts that are refreshed 55 days earlier
		$dwolla_accounts = $this->PaymentMethod->find('all', array(
			'conditions' => array(
				'PaymentMethod.payment_method' => 'dwolla',
				'PaymentMethod.value is not null',
				'PaymentMethod.modified <=' => date(DB_DATETIME, time() - 4752000)
			)
		));
		
		if (!$dwolla_accounts) {
			echo 'any account does not need to refresh today!'. "\n";
			return;
		}
		
		App::import('Vendor', 'autoload', array(
			'file' => 'DwollaSDK' . DS . 'autoload.php'
		));
		$OAuth = new Dwolla\OAuth();
		foreach ($dwolla_accounts as $account) {
			$result = $OAuth->refresh($account['PaymentMethod']['value']);
			if (!$result || !isset($result['access_token'])) {
				
				// unset refresh token
				$this->PaymentMethod->create();
				$this->PaymentMethod->save(array('PaymentMethod' => array(
					'id' => $account['PaymentMethod']['id'],
					'value' => null,
				)), false, array('value'));
			
				$this->out('Tokens not returned for payment_method: '.  $account['PaymentMethod']['id'] . "\n". print_r($result, true));
				continue;
			}
			
			$this->PaymentMethod->create();
			$this->PaymentMethod->save(array('PaymentMethod' => array(
				'id' => $account['PaymentMethod']['id'],
				'value' => $result['refresh_token'],
			)), false, array('value'));
			
			$this->out('User #'.$account['PaymentMethod']['user_id']. ' dwolla refresh token updated.');
		}
	}
	
	// accepts a user id target - calculates poll streak and pays out if matched
	public function poll_streak() {
		
		$this->out("Started updating poll streak of users");
		$this->hr();
		
		// target a user id for poll streak updating
		if (isset($this->args[0])) {
			$this->out('Operating on '.$this->args[0]);
			$users = $this->User->find('all', array(
				'recursive' => -1,
				'conditions' => array(
					'User.id' => $this->args[0],
				),
				'fields' => array('id', 'poll_streak')
			));
		}
		else {
			// Finds the users whose poll streak is greater than 0
			$users = $this->User->find('all', array(
				'recursive' => -1,
				'conditions' => array(
					'User.active' => true,
					'User.deleted_on' => null,
					'User.poll_streak >' => 0
				),
				'fields' => array('User.id', 'User.poll_streak')
			));
		}
		
		$this->out('Operating on '.count($users).' panelists');
		
		$poll_publish_time_setting = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'poll.publish_time',
				'Setting.deleted' => false
			)
		));
		if (date('H:00') > $poll_publish_time_setting['Setting']['value']) {
			$date = date(DB_DATE); 
		}
		else {
			$date = date(DB_DATE, strtotime('yesterday'));
		}
		
		// get the last ten polls to calculate streak in ascending order	
		$last_polls = $this->Poll->find('list', array(
			'fields' => array('Poll.id', 'Poll.publish_date'),
			'conditions' => array(
				'Poll.publish_date <=' => $date
			),
			'recursive' => -1,
			'order' => 'Poll.publish_date DESC',
			'limit' => 10
		));
		
		$last_polls = array_reverse($last_polls, true /* preserve keys */);
		
		if ($users) {
			foreach ($users as $user) {
				// get last poll streak transaction as an anchor
				$poll_streak_transaction = $this->Transaction->find('first', array(
					'fields' => array('Transaction.id', 'Transaction.linked_to_id'),
					'conditions' => array(
						'Transaction.user_id' => $user['User']['id'],
						'Transaction.type_id' => TRANSACTION_POLL_STREAK,
						'Transaction.deleted' => null,
					),
					'recursive' => -1,
					'order' => 'Transaction.id DESC',
				));

				$poll_user_answers = $this->PollUserAnswer->find('list', array(
					'fields' => array('PollUserAnswer.id', 'PollUserAnswer.poll_id'),
					'recursive' => -1,
					'conditions' => array(
						'PollUserAnswer.poll_id' => array_keys($last_polls),
						'PollUserAnswer.user_id' => $user['User']['id']
					)
				));
				
				$poll_streak = 0;
				
				if (isset($this->args[0])) {
					$this->out('Last polls');
					$this->out(print_r($last_polls, true));
					$this->out('Panelist\'s answers');
					$this->out(print_r($poll_user_answers, true));
					$this->out('Last poll transaction');
					$this->out(print_r($poll_streak_transaction, true));
				}
				
				foreach ($last_polls as $poll_id => $date) {
					if (isset($this->args[0])) {	
						$this->out($poll_id.':' .$poll_streak);
					}
					// need to only start looking at the poll after the last payout
					if ($poll_streak_transaction && $poll_id == $poll_streak_transaction['Transaction']['linked_to_id']) {
						$poll_streak = 0;
						continue;
					}
					if (in_array($poll_id, $poll_user_answers)) {
						$poll_streak++;
					}
					// don't necessarily reset if you haven't taken it
					elseif (date('Y-m-d') != $date) {
						$poll_streak = 0;
					}
				}
				
				if ($poll_streak == POLL_STREAK_COUNT) {
					$poll = $this->Poll->find('first', array(
						'conditions' => array(
							'Poll.id' => $poll_id
						)
					));
					$transactionSource = $this->Transaction->getDataSource();
					$transactionSource->begin();
					$this->Transaction->create();
					$this->Transaction->save(array('Transaction' => array(
						'type_id' => TRANSACTION_POLL_STREAK,
						'linked_to_id' => $poll['Poll']['id'],
						'linked_to_name' => $poll['Poll']['poll_question'],
						'user_id' => $user['User']['id'],
						'amount' => 25,
						'paid' => false,
						'name' => 'Completed poll streak',
						'status' => TRANSACTION_PENDING,
						'executed' => date(DB_DATETIME)
					)), true, array('type_id', 'linked_to_id', 'user_id', 'amount', 'paid', 'name', 'status', 'executed'));
					
					$transaction = $this->Transaction->findById($this->Transaction->getInsertId());
					$this->Transaction->approve($transaction);
					$transactionSource->commit();
					$this->out($user['User']['id'].' PAID: '.$poll_streak.' #'.$transaction['Transaction']['id']); 
					$poll_streak = 0;
				}
				
				if ($poll_streak != $user['User']['poll_streak']) {
					$this->User->save(array('User' => array(
						'id' => $user['User']['id'],
						'poll_streak' => $poll_streak,
						'modified' => false
					)), true, array('poll_streak')); 
					$this->out($user['User']['id'].': '.$poll_streak.' from '.$user['User']['poll_streak']);
				}
			}
		}
	}
		
	// args[0]:
	//		$date in YYYY-MM-DD format: make project invite report for the date specified
	//		'all': make project invite reports for all dates possible so far
	//		if not specified, yesterday is selected in default
	public function make_project_invite_reports() {
		ini_set('memory_limit', '2048M');
		$this->SurveyUser->virtualFields = array(
			'created_date' => 'DATE(SurveyUser.created)',
			'total_invites_sent' => 'COUNT(SurveyUser.id)'
		);

		if (isset($this->args[0]) && !empty($this->args[0])) {
			if (strtolower($this->args[0]) == 'all') {
				$first_invite = $this->SurveyUser->find('first', array(
					'conditions' => array(
						'SurveyUser.created_date >' => '0000-00-00'
					),
					'fields' => array(
						'SurveyUser.created_date'
					),
					'order' => array(
						'SurveyUser.created_date'
					)
				));

				if (!$first_invite) {
					$this->out("There is no project invites data");
					return;
				}

				$start_date_timestamp = strtotime($first_invite['SurveyUser']['created_date']);
				$end_date_timestamp = strtotime("1 day ago midnight");
			}
			elseif (strtolower($this->args[0]) == 'yesterday') {
				$start_date_timestamp = strtotime('yesterday');
				$end_date_timestamp = $start_date_timestamp;
			}
			else {
				$timestamp = strtotime($this->args[0]);
				if (!$timestamp) {
					$this->out('Invalid date');
					return;
				}

				if ($timestamp >= strtotime("today midnight")) {
					$this->out('You should enter date that is before yesterday');
					return;
				}

				$start_date_timestamp = strtotime(date(DB_DATE, $timestamp));
				$end_date_timestamp = $start_date_timestamp;
			}
		}
		else {
			$start_date_timestamp = strtotime("1 day ago midnight");
			$end_date_timestamp = $start_date_timestamp;
		}

		$current_date_timestamp = $start_date_timestamp;
		while ($current_date_timestamp <= $end_date_timestamp) {
			$current_date = date(DB_DATE, $current_date_timestamp);
			$survey_user_stats = $this->SurveyUser->find('all', array(
				'contain' => array(
					'User'
				),
				'conditions' => array(
					'SurveyUser.created_date' => $current_date
				),
				'fields' => array(
					'SurveyUser.user_id',
					'SurveyUser.total_invites_sent',
					'User.last_touched'
				),
				'order' => array(
					'SurveyUser.total_invites_sent DESC'
				),
				'group' => array(
					'SurveyUser.user_id'
				)
			));

			$prj_invite_report_data = array();
			$prj_invite_report_data['date'] = $current_date;
			$user_level_count = array(
				'runners' => 0,
				'walkers' => 0,
				'living' => 0,
				'zombies' => 0,
				'dead' => 0
			);
			if (count($survey_user_stats) == 0) {
				$prj_invite_report_data['total_invites_sent'] = 0;
				$prj_invite_report_data['total_users_received'] = 0;
				$prj_invite_report_data['max_invites_received_by_user'] = 0;
				$prj_invite_report_data['median_invites_received_by_user'] = 0;
				$prj_invite_report_data['mean_invites_received_by_user'] = 0;
			}
			else {
				
				# Total invites sent
				$total_invites_sent = 0;
				foreach ($survey_user_stats as $survey_user_stat) {
					$total_invites_sent += $survey_user_stat['SurveyUser']['total_invites_sent'];
				}
				
				$user_level_count = MintVineUser::user_level_count($survey_user_stats);
				$prj_invite_report_data['total_invites_sent'] = $total_invites_sent;

				# Total users received
				$prj_invite_report_data['total_users_received'] = count($survey_user_stats);

				# Max invites received by user
				$prj_invite_report_data['max_invites_received_by_user'] = $survey_user_stats[0]['SurveyUser']['total_invites_sent'];

				# Median invites per user
				$user_count = count($survey_user_stats);
				$prj_invite_report_data['median_invites_received_by_user'] = ($survey_user_stats[intval(($user_count - 1) / 2)]['SurveyUser']['total_invites_sent']
						+ $survey_user_stats[intval($user_count / 2)]['SurveyUser']['total_invites_sent']) / 2;

				# Mean invites per user
				$prj_invite_report_data['mean_invites_received_by_user'] = $prj_invite_report_data['total_invites_sent'] / $prj_invite_report_data['total_users_received'];
			}

			$prj_invite_report_data = array_merge($prj_invite_report_data, $user_level_count);

			// Save
			$existing_prj_invite_report = $this->ProjectInviteReport->find('first', array(
				'conditions' => array(
					'ProjectInviteReport.date' => $current_date
				),
				'fields' => array('id')
			));
			if ($existing_prj_invite_report) {
				$prj_invite_report_data['id'] = $existing_prj_invite_report['ProjectInviteReport']['id'];
			}

			$this->ProjectInviteReport->create();
			if ($this->ProjectInviteReport->save(array(
				'ProjectInviteReport' => $prj_invite_report_data
			), true)) {
				$this->out(sprintf("Saved project invite report of %s", $current_date));
			}

			$current_date_timestamp += 60 * 60 * 24;
		}
	}
	
	public function refresh_dwolla_master_token() {
		$models_to_import = array('PaymentMethod', 'Setting');
		foreach ($models_to_import as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		// Get refresh token
		$refresh_token = $this->PaymentMethod->find('first', array(
			'recursive' => -1,
			'conditions' => array(
				'user_id' => 0,
				'payment_id' => 'master_refresh_token'
			)
		));
		CakeLog::write('dwolla_refresh', 'Master refresh token retrieved.'.$refresh_token['PaymentMethod']['value']);
		
		if (!$refresh_token) {
			$message = 'Dwolla master token not found. Please add the master refresh token to payment_methods';
			echo $message . "\n";
			CakeLog::write('dwolla_refresh', $message);
			return false;
		}
		
		// Get access token
		$access_token = $this->PaymentMethod->find('first', array(
			'recursive' => -1,
			'conditions' => array(
				'user_id' => 0,
				'payment_id' => 'master_access_token'
			)
		));
		
		// Create empty access token record if not already exist.
		if (!$access_token) {
			$this->PaymentMethod->create();
			$access_token = $this->PaymentMethod->save(array('PaymentMethod' => array(
				'value' => '',
				'user_id' => 0,
				'payment_method' => '',
				'payment_id' => 'master_access_token',
			)), false);
		}
		
		App::import('Vendor', 'autoload', array(
			'file' => 'DwollaSDK' . DS . 'autoload.php'
		));
		$OAuth = new Dwolla\OAuth();
		$result = $OAuth->refresh($refresh_token['PaymentMethod']['value']);
		if (!$result || !isset($result['access_token'])) {
			$message = 'FAILED';
			echo $message."\n";
			CakeLog::write('dwolla_refresh', $message. print_r($result, true));
			
			// post to slack
			$setting = $this->Setting->find('list', array(
				'fields' => array('Setting.name', 'Setting.value'),
				'conditions' => array(
					'Setting.name' => 'slack.alerts.webhook',
					'Setting.deleted' => false
				)
			));
			if (isset($setting['slack.alerts.webhook'])) {
				$msg = 'Dwolla master refresh token failed to update. Please check dwolla_refresh.log file, and generate a new master refresh token.';
				Utils::slack_alert($setting['slack.alerts.webhook'], $msg);
			}
			
			return false;
		}
		
		// Update master access token
		$this->PaymentMethod->create();
		$this->PaymentMethod->save(array('PaymentMethod' => array(
			'id' => $access_token['PaymentMethod']['id'],
			'value' => $result['access_token'],
			'user_id' => $access_token['PaymentMethod']['user_id'],
			'payment_method' => $access_token['PaymentMethod']['payment_method'],
		)), true, array('value', 'user_id', 'payment_method'));
		
		// Update master refresh token
		$this->PaymentMethod->create();
		$this->PaymentMethod->save(array('PaymentMethod' => array(
			'id' => $refresh_token['PaymentMethod']['id'],
			'value' => $result['refresh_token'],
			'user_id' => $refresh_token['PaymentMethod']['user_id'],
			'payment_method' => $refresh_token['PaymentMethod']['payment_method'],
		)), true, array('value', 'user_id', 'payment_method'));
		$message = 'success';
		echo $message . "\n";
		CakeLog::write('dwolla_refresh',  $message);
		CakeLog::write('dwolla_refresh',  $result);
	}
	
	public function refresh_dwolla_master_token_v2() {
		App::import('Model', 'Setting');
		$this->Setting = new Setting;
		
		// Get refresh token
		$refresh_token = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'dwolla_v2.master.refresh_token',
				'Setting.deleted' => false
			)
		));
		
		if (!$refresh_token || empty($refresh_token['Setting']['value'])) {
			$message = 'Dwolla master token not found. Please add the master refresh token to settings';
			echo $message . "\n";
			CakeLog::write('dwolla_refresh_v2', $message);
			return false;
		}
		
		CakeLog::write('dwolla_refresh_v2', 'Master refresh token retrieved.'.$refresh_token['Setting']['value']);
		
		// Get access token
		$access_token = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'dwolla_v2.master.access_token',
				'Setting.deleted' => false
			)
		));
		
		// Create empty access token record if not already exist.
		if (!$access_token) {
			$settingSource = $this->Setting->getDataSource();
			$settingSource->begin();
			$this->Setting->create();
			$this->Setting->save(array('Setting' => array(
				'name' => 'dwolla_v2.master.access_token',
				'value' => ''
			)));
			$access_token['Setting']['id'] = $this->Setting->getInsertId();
			$settingSource->commit();
		}
		
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array(
					'hostname.dwolla',
					'dwolla_v2.master.key', 
					'dwolla_v2.master.secret', 
					'slack.alerts.webhook'
				),
				'Setting.deleted' => false
			)
		));
		
		if (!isset($settings['dwolla_v2.master.key']) || empty($settings['dwolla_v2.master.key'])) {
			$message = 'Dwolla master key not found. Please add dwolla_v2.master.key to settings';
			echo $message . "\n";
			CakeLog::write('dwolla_refresh_v2', $message);
			return false;
		}
		
		if (!isset($settings['dwolla_v2.master.secret']) || empty($settings['dwolla_v2.master.secret'])) {
			$message = 'Dwolla master secret not found. Please add dwolla_v2.master.secret to settings';
			echo $message . "\n";
			CakeLog::write('dwolla_refresh_v2', $message);
			return false;
		}
		
		if (!isset($settings['hostname.dwolla']) || empty($settings['hostname.dwolla'])) {
			$message = 'Dwolla api url not found. Please add hostname.dwolla to settings';
			echo $message . "\n";
			CakeLog::write('dwolla_refresh_v2', $message);
			return false;
		}
		
		$http = new HttpSocket(array(
			'timeout' => '2',
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		
		$result = $http->post($settings['hostname.dwolla'] . 'oauth/v2/token', array(
			'client_id' => $settings['dwolla_v2.master.key'],
			'client_secret' => $settings['dwolla_v2.master.secret'],
			'refresh_token' => $refresh_token['Setting']['value'],
			'grant_type' => 'refresh_token'
		));
		
		$result_body = json_decode($result['body'], true);
		if (!$result_body || !isset($result_body['access_token'])) {
			$message = 'FAILED';
			echo $message."\n";
			CakeLog::write('dwolla_refresh_v2', $message. print_r($result, true));
			
			// post to slack
			if (isset($settings['slack.alerts.webhook'])) {
				$msg = 'Dwolla master refresh token failed to update. Please check dwolla_refresh_v2.log file, and generate a new master refresh token.';
				Utils::slack_alert($settings['slack.alerts.webhook'], $msg);
			}
			
			return false;
		}
		
		// Update master access token
		$this->Setting->create();
		$this->Setting->save(array('Setting' => array(
			'id' => $access_token['Setting']['id'],
			'value' => $result_body['access_token'],
		)), true, array('value'));
		
		// Update master refresh token
		$this->Setting->create();
		$this->Setting->save(array('Setting' => array(
			'id' => $refresh_token['Setting']['id'],
			'value' => $result_body['refresh_token']
		)), true, array('value'));
		$message = 'success';
		echo $message . "\n";
		CakeLog::write('dwolla_refresh_v2',  $message);
		CakeLog::write('dwolla_refresh_v2',  $result);
	}
	
	function remove_active_users_bounce() {
		
		$log_file = 'maintenance.mailgun.list';
		$log_key = strtoupper(Utils::rand('4'));
		$time_start = microtime(true);		
		$this->lecho('Starting Mailgun list maintenance', $log_file, $log_key); 
		
		$settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array('mailgun.api.key', 'mailgun.domain'),
				'Setting.deleted' => false
			),
			'fields' => array('name', 'value')
		));
		if (count($settings) != 2) {
			echo 'Missing settings';
			return false;
		}
		App::uses('HttpSocket', 'Network/Http');
		$HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));

		$bounce_emails = array();
		$results = $HttpSocket->get('https://api:'.$settings['mailgun.api.key'].'@api.mailgun.net/v3/'.$settings['mailgun.domain'].'/bounces', 'limit=10000');
		$items = json_decode($results->body, true);
		
		if (!empty($items['items'])) {
			foreach ($items['items'] as $item) {
				if (strpos($item['address'], 'craigslist') !== false) {
					continue;
				}
				if (!Validation::email($item['address'])) {
					continue;
				}
				$bounce_emails[] = $item['address'];
			}
			
			while (true) {
				$results = $HttpSocket->get(urldecode($items['paging']['next']));
				$items = json_decode($results->body, true);
				if (!empty($items['items'])) {
					foreach ($items['items'] as $item) {
						if (strpos($item['address'], 'craigslist') !== false) {
							continue;
						}
						if (!Validation::email($item['address'])) {
							continue;
						}
						$bounce_emails[] = $item['address'];
					}
				}
				else {
					break;
				}
			}
		}
		$this->lecho('Found '.count($bounce_emails).' in total', $log_file, $log_key); 
		$i = 0;
		$five_days_ago = date(DB_DATETIME, strtotime('-5 days')); 
		foreach ($bounce_emails as $bounce_email) {
			$user = $this->User->find('first', array(
				'fields' => array('id', 'last_touched'),
				'conditions' => array(
					'User.email' => $bounce_email,
					'User.deleted_on' => null
				),
				'recursive' => -1
			));
			
			if ($user && $user['User']['last_touched'] >= $five_days_ago) {
				$this->lecho('Removing #'.$user['User']['id'].' '.$bounce_email.' from Mailgun bounce list', $log_file, $log_key);
				$response = $HttpSocket->delete('https://api:'.$settings['mailgun.api.key'].'@api.mailgun.net/v3/'.$settings['mailgun.domain'].'/bounces/' . $bounce_email);
				$i++;
			}
			else {
				$this->lecho('Skipped '.$bounce_email, $log_file, $log_key);
			}
		}
		
		$this->lecho('Completed - removed '.$i.' users (Execution time: '.(microtime(true) - $time_start).')', $log_file, $log_key);
	}
	
	// args[0] = group key e.g cint, rfg etc
	// args[1] = days of inactivity e.g 7, 10 etc
	public function close_inactive() {
		if (!isset($this->args[0]) || empty($this->args[0])) {
			echo 'Please provide the group key as first argument'. "\n";
			return;
		}
		
		if (!isset($this->args[1]) || empty($this->args[1])) {
			echo 'Please provide the no of days of inactivity as second argument'. "\n";
			return;
		}
		
		$group = $this->Group->find('first', array(
			'fields' => array('id'),
			'conditions' => array(
				'Group.key' => $this->args[0]
			)
		));
		if (!$group) {
			return;
		}
		
		$conditions = array(
			'Project.status' => array(PROJECT_STATUS_OPEN, PROJECT_STATUS_SAMPLING, PROJECT_STATUS_STAGING),
			'Project.group_id' => $group['Group']['id'],
			'Project.ignore_autoclose' => false,
			'Project.date_created <' => date(DB_DATE, strtotime('-'.$this->args[1].' days'))
		);
		
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'date_created'),
			'conditions' => $conditions,
			'order' => 'Project.id DESC',
			'recursive' => -1
		));
		if (!$projects) {
			echo 'No open projects found.' ."\n";
			return false;
		}
		
		$message = 'Processing '.count($projects).' projects';
		echo $message."\n";
		CakeLog::write('maintenance.close_inactive', $message);
		foreach ($projects as $project) {
			$survey_visit = $this->SurveyVisit->find('first', array(
				'conditions' => array(
					'SurveyVisit.survey_id' => $project['Project']['id'],
					'SurveyVisit.type' => SURVEY_CLICK,
				),
				'order' => 'SurveyVisit.id DESC'
			));
			
			if ($survey_visit && ((time() - strtotime($survey_visit['SurveyVisit']['created'])) / 86400) < $this->args[1]) {
				continue;
			}
			
			// close project 
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'status' => PROJECT_STATUS_CLOSED,
				'active' => false,
				// update ended if it's blank - otherwise leave the old value
				'ended' => empty($project['Project']['ended']) ? date(DB_DATETIME) : $project['Project']['ended']
			)), true, array('status', 'active', 'ended'));

			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'status.closed.'.$this->args[0],
				'internal_description' => 'closed.wall',
				'description' => 'closed because project is inactive for last '.$this->args[1].' days.'
			)));
			Utils::save_margin($project['Project']['id']);
			$message = "Project #".$project['Project']['id']. " closed, because of ".$this->args[1]." days inactivity.";
			echo $message."\n";
			CakeLog::write('maintenance.close_inactive', $message);
		}
	}
	
	function sync_user_analytics() {		
		$setting = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array('segment.write_key'),
				'Setting.deleted' => false
			),
			'fields' => array(
				'name', 'value'
			)
		));	
		if (empty($setting)) {
			return;
		}
		if (!defined('SEGMENT_WRITE_KEY')) {
			define('SEGMENT_WRITE_KEY', $setting['segment.write_key']);
			class_alias('Segment', 'Analytics');
			Analytics::init(SEGMENT_WRITE_KEY);
		}
		
		$four_hour_less_date_time = date(DB_DATETIME, strtotime(date(DB_DATETIME)) - 60 * 60 * 4);
		$analytics = $this->UserAnalytic->find('all', array(
			'conditions' => array(
				'UserAnalytic.created <=' => $four_hour_less_date_time,
				'UserAnalytic.fired' => false
			)
		));
		echo 'Firing: ' . count($analytics);
		if (!empty($analytics)) {			
			foreach ($analytics as $analytic) {
				$properties = json_decode($analytic['UserAnalytic']['json_body'], true);
				Analytics::track(array(
					'userId' => $properties['userId'],
					'event' => $properties['event'],
					'timestamp' => strtotime($properties['timestamp']),
					'properties' => $properties['properties']
				));	

				$this->UserAnalytic->create();
				$this->UserAnalytic->save(array('UserAnalytic' => array(
					'id' => $analytic['UserAnalytic']['id'],
					'fired' => true
				)), false, array('fired'));
				
				echo 'Fired: ' . $analytic['UserAnalytic']['id']."\n";
			}	
		}					
	}
	
	function invalidate_cloudfront() {		
		CloudFrontLib::invalidate_cloudfront();
		echo 'js/img/css invalidated successfully.'."\n";
	}
	
	// calculate project drop rate and alert on slack if its greater than certain number
	public function project_drop_rate_alert() {
		
		// Send messages to slack
		$setting = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'slack.drop_rate.webhook',
				'Setting.deleted' => false
			)
		));
		if (!$setting) {
			$this->out('ERROR: Slack channel missing');
			return; 
		}
		
		$drop_rate = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'project.drop_rate_threshold',
				'Setting.deleted' => false
			)
		));
		if (!$drop_rate) {
			$this->out('ERROR: Drop rate missing');
			return; 
		}
		
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Group.name', 'SurveyVisitCache.*', 'Project.est_length', 'Project.prescreen'),
			'contain' => array(
				'Group', 
				'SurveyVisitCache'
			),
			'conditions' => array(
				'Project.status' => PROJECT_STATUS_OPEN, 
				'Project.active' => true,
				'Group.key !=' => 'mintvine',
				'Project.router' => false
			),
			'order' => 'Project.id DESC', // prioritize alerts on newer projects first
			'recursive' => -1
		));
		
		$this->out('Processing '.count($projects).' projects');
		$message = '';
		foreach ($projects as $project) {
			
			// no data
			if (empty($project['SurveyVisitCache']['id'])) {
				continue;
			}
			$loi = $survey_recent_entrants = '';
			if (!empty($project['SurveyVisitCache']['loi_seconds'])) {
				$loi = round($project['SurveyVisitCache']['loi_seconds'] / 60);
			}
			elseif (!empty($project['Project']['est_length'])) {
				$loi = $project['Project']['est_length'];
			}
			if (!empty($loi)) {
				$loi = $loi * 1.2; // give it some leeway
				$survey_recent_entrants = $this->SurveyVisit->find('count', array(
					'conditions' => array(
						'SurveyVisit.survey_id' => $project['Project']['id'],
						'SurveyVisit.type' => SURVEY_CLICK,
						'SurveyVisit.result' => 0,
						'SurveyVisit.created >=' => date(DB_DATETIME, strtotime('- '.$loi.'minutes'))
					)
				));
			}
			$project['SurveyVisitCache']['click'] = $project['SurveyVisitCache']['click'] - $survey_recent_entrants;

			$drop_timestamp = $this->ProjectOption->find('first', array(
				'conditions' => array(
					'ProjectOption.project_id' => $project['Project']['id'],
					'ProjectOption.name' => 'drop.alert.timestamp',
				)
			));
			
			
			// minimum threshold of 5 clicks before we alert
			if ($project['SurveyVisitCache']['click'] > 5) {
				$drops = MintVine::drop_rate($project); 
				
				if ($drops > str_replace('%', '', $drop_rate['Setting']['value'])) {

					$drop_count = $this->ProjectOption->find('first', array(
						'conditions' => array(
							'ProjectOption.project_id' => $project['Project']['id'],
							'ProjectOption.name' => 'drop.alert.count',
						)
					));
					
					// only alert once per hour per project
					if ($drop_timestamp) {
						if (strtotime($drop_timestamp['ProjectOption']['value']) > strtotime('-1 hour')) {
							continue;
						}
						$count = $this->SurveyVisit->find('count', array(
							'conditions' => array(
								'SurveyVisit.survey_id' => $project['Project']['id'],
								'SurveyVisit.type' => SURVEY_CLICK,
								'SurveyVisit.created >=' => date(DB_DATETIME, strtotime($drop_timestamp['ProjectOption']['value']))
							),
							'recursive' => -1
						));
						if ($count == 0) {
							continue;
						}
					}
					$send_channel_notification = '';
					if ($drop_count) {
						$count = (int) $drop_count['ProjectOption']['value'];
						$count++;
					}
					else {
						$count = 1; 
						$send_channel_notification = "[FIRST] ";
					}
					$message = $send_channel_notification.'HIGH DROP RATE (*'.$drops.'%*): #'.$project['Project']['id']. ' ('.$project['Group']['name'].') ';
					if ($count > 1) {
						$message.= ' Alert #'.$count.' ';
					}
					$message .= '('.$project['SurveyVisitCache']['complete'].'/'.$project['SurveyVisitCache']['click'].'/'.$project['SurveyVisitCache']['overquota'].'/'.$project['SurveyVisitCache']['nq'].') - ';
					$message .= ' https://cp.mintvine.com/surveys/dashboard/'.$project['Project']['id']; 
					$this->out($message);
					
					if ($drop_count) {
						$this->ProjectOption->create();
						$this->ProjectOption->save(array('ProjectOption' => array(
							'id' => $drop_count['ProjectOption']['id'],
							'value' => $count
						)), true, array('value'));
					}
					else {
						$this->ProjectOption->create();
						$this->ProjectOption->save(array('ProjectOption' => array(
							'name' => 'drop.alert.count',
							'value' => $count,
							'project_id' => $project['Project']['id']
						)));
					}
					if ($drop_timestamp) {
						$this->ProjectOption->create();
						$this->ProjectOption->save(array('ProjectOption' => array(
							'id' => $drop_timestamp['ProjectOption']['id'],
							'value' => date(DB_DATETIME)
						)), true, array('value'));
						
					}
					else {
						$this->ProjectOption->create();
						$this->ProjectOption->save(array('ProjectOption' => array(
							'name' => 'drop.alert.timestamp',
							'value' => date(DB_DATETIME),
							'project_id' => $project['Project']['id']
						)));
					}

					if (!empty($message)) {
						$http = new HttpSocket(array(
							'timeout' => '2',
							'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
						));
						$http->post($setting['Setting']['value'], json_encode(array(
							'text' => $message,
							'link_names' => 1,
							'username' => 'bernard'
						))); 
					}
				}
			}
		}
	}
	
	// calculate project drop rate for mintvine projects and alert on slack if its greater than certain number
	public function mintvine_project_drop_rate_alert() {
		
		// Send messages to slack
		$setting = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'slack.mintvine.drop_rate.webhook',
				'Setting.deleted' => false
			)
		));
		if (!$setting) {
			$this->out('ERROR: Slack channel missing');
			return; 
		}
		
		$drop_rate = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'project.mintvine.drop_rate_threshold',
				'Setting.deleted' => false
			)
		));
		if (!$drop_rate) {
			$this->out('ERROR: Drop rate missing');
			return; 
		}
		
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Group.name', 'SurveyVisitCache.*', 'Project.est_length', 'Project.prescreen'),
			'contain' => array(
				'Group', 
				'SurveyVisitCache'
			),
			'conditions' => array(
				'Project.status' => PROJECT_STATUS_OPEN, 
				'Project.active' => true,
				'Group.key' => 'mintvine'
			),
			'order' => 'Project.id DESC', // prioritize alerts on newer projects first
			'recursive' => -1
		));
		
		$this->out('Processing '.count($projects).' projects');
		$message = '';
		foreach ($projects as $project) {

			// no data
			if (empty($project['SurveyVisitCache']['id'])) {
				continue;
			}
			$loi = $survey_recent_entrants = '';
			if (!empty($project['SurveyVisitCache']['loi_seconds'])) {
				$loi = round($project['SurveyVisitCache']['loi_seconds'] / 60);
			}
			elseif (!empty($project['Project']['est_length'])) {
				$loi = $project['Project']['est_length'];
			}
			if (!empty($loi)) {
				$loi = $loi * 1.2; // give it some leeway
				$survey_recent_entrants = $this->SurveyVisit->find('count', array(
					'conditions' => array(
						'SurveyVisit.survey_id' => $project['Project']['id'],
						'SurveyVisit.type' => SURVEY_CLICK,
						'SurveyVisit.result' => 0,
						'SurveyVisit.created >=' => date(DB_DATETIME, strtotime('- '.$loi.'minutes'))
					)
				));
			}
			$project['SurveyVisitCache']['click'] = $project['SurveyVisitCache']['click'] - $survey_recent_entrants;

			$drop_timestamp = $this->ProjectOption->find('first', array(
				'conditions' => array(
					'ProjectOption.project_id' => $project['Project']['id'],
					'ProjectOption.name' => 'drop.alert.timestamp',
				)
			));
			
			
			// minimum threshold of 5 clicks before we alert
			if ($project['SurveyVisitCache']['click'] > 5) {
				$drops = MintVine::drop_rate($project); 
				if ($drops > str_replace('%', '', $drop_rate['Setting']['value'])) {

					$drop_count = $this->ProjectOption->find('first', array(
						'conditions' => array(
							'ProjectOption.project_id' => $project['Project']['id'],
							'ProjectOption.name' => 'drop.alert.count',
						)
					));
					
					// only alert once per hour per project
					if ($drop_timestamp) {
						if (strtotime($drop_timestamp['ProjectOption']['value']) > strtotime('-1 hour')) {
							continue;
						}
						// do not show alert if there are no new clicks
						$count = $this->SurveyVisit->find('count', array(
							'conditions' => array(
								'SurveyVisit.survey_id' => $project['Project']['id'],
								'SurveyVisit.type' => SURVEY_CLICK,
								'SurveyVisit.created >=' => date(DB_DATETIME, strtotime($drop_timestamp['ProjectOption']['value']))
							),
							'recursive' => -1
						));
						if ($count == 0) {
							continue;
						}
					}
					$send_channel_notification = '';
					if ($drop_count) {
						$count = (int) $drop_count['ProjectOption']['value'];
						$count++;
					}
					else {
						$count = 1; 
						$send_channel_notification = "@channel ";
					}
					$message = $send_channel_notification.'HIGH DROP RATE (*'.$drops.'%*): #'.$project['Project']['id']. ' ('.$project['Group']['name'].') ';
					if ($count > 1) {
						$message.= ' Alert #'.$count.' ';
					}
					$message .= '('.$project['SurveyVisitCache']['complete'].'/'.$project['SurveyVisitCache']['click'].'/'.$project['SurveyVisitCache']['overquota'].'/'.$project['SurveyVisitCache']['nq'].') - ';
					$message .= ' https://cp.mintvine.com/surveys/dashboard/'.$project['Project']['id']; 
					$this->out($message);
					
					if ($drop_count) {
						$this->ProjectOption->create();
						$this->ProjectOption->save(array('ProjectOption' => array(
							'id' => $drop_count['ProjectOption']['id'],
							'value' => $count
						)), true, array('value'));
					}
					else {
						$this->ProjectOption->create();
						$this->ProjectOption->save(array('ProjectOption' => array(
							'name' => 'drop.alert.count',
							'value' => $count,
							'project_id' => $project['Project']['id']
						)));
					}
					if ($drop_timestamp) {
						$this->ProjectOption->create();
						$this->ProjectOption->save(array('ProjectOption' => array(
							'id' => $drop_timestamp['ProjectOption']['id'],
							'value' => date(DB_DATETIME)
						)), true, array('value'));
						
					}
					else {
						$this->ProjectOption->create();
						$this->ProjectOption->save(array('ProjectOption' => array(
							'name' => 'drop.alert.timestamp',
							'value' => date(DB_DATETIME),
							'project_id' => $project['Project']['id']
						)));
					}

					if (!empty($message)) {
						$http = new HttpSocket(array(
							'timeout' => '2',
							'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
						));
						$http->post($setting['Setting']['value'], json_encode(array(
							'text' => $message,
							'link_names' => 1,
							'username' => 'bernard'
						))); 
					}
				}
			}
		}
	}
	
	public function user_export_statistics() {
		$date = date(DB_DATE);
		$export_status = true;
		$count = $this->UserExportStatistic->find('count', array(
			'conditions' => array(
				'UserExportStatistic.date' => $date
			)
		));
		if ($count > 0) {
			$this->out('User export statistics already saved for '.$date);
			return;
		}
		
		$user_condition[1] = array(
			'User.created >=' => $date . ' 00:00:00',
			'User.created <=' => $date. ' 23:59:59'
		);
		$user_condition[5] = array(
			'User.created >=' => date(DB_DATE, strtotime('-5 days')) . ' 00:00:00',
			'User.created <=' => $date. ' 23:59:59'
		);
		$user_condition[14] = array(
			'User.created >=' => date(DB_DATE, strtotime('-14 days')) . ' 00:00:00',
			'User.created <=' => $date. ' 23:59:59'
		);
		$partner_condition[1] = array(
			'PartnerLog.created >=' => $date . ' 00:00:00',
			'PartnerLog.created <=' => $date. ' 23:59:59'
		);
		$partner_condition[5] = array(
			'PartnerLog.created >=' => date(DB_DATE, strtotime('-5 days')) . ' 00:00:00',
			'PartnerLog.created <=' => $date. ' 23:59:59'
		);
		$partner_condition[14] = array(
			'PartnerLog.created >=' => date(DB_DATE, strtotime('-14 days')) . ' 00:00:00',
			'PartnerLog.created <=' => $date. ' 23:59:59'
		);
		
		$groups = array(
			'cint',
			'precision',
			'toluna',
			'mintvine_lucid',
			'mintvine_core'
		);
		
		foreach ($user_condition as $days => $condition) {
			$statistics = array(
				'date' => $date,
				'days' => $days
			);
			$users = $this->User->find('list', array(
				'conditions' => $condition,
				'fields' => array('User.id', 'User.id')
			));
			$statistics['registrations'] = count($users);
			$statistics['extended_registrations'] = $this->QueryProfile->find('count', array(
				'conditions' => array(
					'QueryProfile.user_id' => $users,
					'QueryProfile.postal_code is not null'
				)
			));

			foreach ($groups as $group) {
				$statistics[$group.'_failure'] = $this->PartnerLog->find('count', array(
					'conditions' => array_merge(
						array(
							'NOT' => array(
								'PartnerLog.result_code' => array(200, 201),
							),
							'PartnerLog.partner' => $group
						),
						$partner_condition[$days]
					)
				));
				if ($days == 1 && $statistics[$group.'_failure'] > 0) {
					$export_status = false;
				}
				
				$statistics[$group.'_success'] = $this->PartnerLog->find('count', array(
					'conditions' => array_merge(
						array(
							'PartnerLog.result_code' => array(200, 201),
							'PartnerLog.partner' => $group
						),
						$partner_condition[$days]
					)
				));
			}
			
			$this->UserExportStatistic->create();
			$this->UserExportStatistic->save(array('UserExportStatistic' => $statistics));
		}
		
		$this->out('User export statistics imported for '.$date);
		if (!$export_status) {
			$setting = $this->Setting->find('first', array(
				'conditions' => array(
					'Setting.name' => 'slack.userexport.webhook',
					'Setting.deleted' => false
				),
				'fields' => array('Setting.value')
			));
			$http = new HttpSocket(array(
				'timeout' => '2',
				'ssl_verify_host' => false
			));
			$response = $http->post($setting['Setting']['value'], json_encode(array(
				'text' => 'We have issues with user exports, please see <https://cp.mintvine.com/partner_logs/unsuccessful?date='.$date.'|unsuccessful exports> and <https://cp.mintvine.com/reports/user_export_statistics?date='.$date.'|export statistics>', 
				'link_names' => 1,
				'username' => 'bernard'
			)));
		}
	}
	
	// args[0]: scope, possible values are 'week' and 'month'
	public function sync_qe2_active() {
		if (!isset($this->args[0])) {
			$this->out('Please specify the scope. Possible values are "week", "month", "60_days", or "90_days"');
			return;
		}
		
		if (!in_array($this->args[0], array('week', 'month', '60_days', '90_days'))) {
			$this->out('Possible scope values are "week" and "month"');
			return;
		}
		
		$partners_to_update = array('mintvine', 'lucid');
		$qualifications = array();
		foreach ($partners_to_update as $partner_to_update) {
			$qualifications[$partner_to_update] = array(); 
		}
		
		if ($this->args[0] == 'week') {
			$db_date_condition = '-1 week';
			$log_file = 'qe2.active_within_week';
			$key = 'active_within_week';
		}
		elseif ($this->args[0] == 'month') {
			$db_date_condition = '-1 month';
			$log_file = 'qe2.active_within_month';
			$key = 'active_within_month';
		}
		elseif ($this->args[0] == '60_days') {
			$db_date_condition = '-60 days';
			$log_file = 'qe2.active_within_60_days';
			$key = 'active_within_60_days';
		}
		elseif ($this->args[0] == '90_days') {
			$db_date_condition = '-90 days';
			$log_file = 'qe2.active_within_90_days';
			$key = 'active_within_90_days';
		}
		
		$message = '';
		$settings = $this->Setting->find('list', array(
			'fields' => array(
				'Setting.name', 'Setting.value'
			),
			'conditions' => array(
				'Setting.name' => array('hostname.qe', 'qe.mintvine.username', 'qe.mintvine.password'),
				'Setting.deleted' => false
			)
		));
		
		if (count($settings) != 3) {
			$this->out('Missing required settings');
			return;
		}
		
		$existing_panelists_index = array(); 
		foreach ($partners_to_update as $partner_to_update) {
			$query = array(
				'partner' => $partner_to_update,
				'qualifications' => array(
					$key => array('true')
				)
			);		
			$http = new HttpSocket(array(
				'timeout' => 120,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$http->configAuth('Basic', $settings['qe.mintvine.username'], $settings['qe.mintvine.password']);
			try {
				$results = $http->post($settings['hostname.qe'].'/query', json_encode($query), array(
					'header' => array('Content-Type' => 'application/json')
				));
			}
			catch (Exception $ex) {
				$this->lecho('QE2 api error when getting active panelists.', $log_file); 
				CakeLog::write($log_file, $message);
				return;
			}

			if ($results->code != 200) {
				$this->lecho('BAD API response (Response code: '.$results->code.'), view logs', $log_file); 
				CakeLog::write($log_file, print_r($results, true));
				return;
			}
			$body = json_decode($results['body'], true);
			$existing_panelists_index[$partner_to_update] = array_flip($body['panelist_ids']); 			
			$this->lecho(count($body['panelist_ids']). ' active panelists found in QE2 for partner '.$partner_to_update, $log_file); 
		}
		
		$this->lecho('Finding users since '.date(DB_DATETIME, strtotime($db_date_condition)), $log_file); 
		$last_user_id = 0; 
		$total_user_count = 0; 
		while (true) {
			$users = $this->User->find('all', array(
				'fields' => array('User.id', 'User.last_touched'),
				'conditions' => array(
					'User.id >' => $last_user_id,
					'User.deleted_on' => null,
					'User.last_touched >' => date(DB_DATETIME, strtotime($db_date_condition))
				),
				'recursive' => -1,
				'limit' => 10000,
				'order' => 'User.id ASC'
			));
			if (!$users) {
				break;
			}
			$total_user_count = $total_user_count + count($users);
			foreach ($users as $user) {
				$last_user_id = $user['User']['id'];
				foreach ($partners_to_update as $partner_to_update) {
					if (isset($existing_panelists_index[$partner_to_update][$user['User']['id']])) {
						unset($existing_panelists_index[$partner_to_update][$user['User']['id']]); 
						continue;
					}
					$qualifications[$partner_to_update][$user['User']['id']] = array(
						$key => array('true')
					);
				}
			}
		}
		
		// the remainder of users need to be set to false
		$inactive_user_ids = array();
		foreach ($partners_to_update as $partner_to_update) {
			$inactive_user_ids = $inactive_user_ids + array_keys($existing_panelists_index[$partner_to_update]); 
		}
		
		if (!empty($inactive_user_ids)) {
			$inactive_user_ids = array_unique($inactive_user_ids); 
			foreach ($inactive_user_ids as $inactive_user_id) {
				foreach ($partners_to_update as $partner_to_update) {
					$qualifications[$partner_to_update][$inactive_user_id] = array(
						$key => array('false')
					);
				}
			}
		}

		foreach ($partners_to_update as $partner_to_update) {
			$this->lecho('Starting active users sync ('. count($qualifications[$partner_to_update]). ' panelists)', $log_file);
			$qualification_chunks = array_chunk($qualifications[$partner_to_update], 10000, true);

			foreach ($qualification_chunks as $qualification_chunk) {
				$post_data = array(
					'partner' => $partner_to_update,
					'qualifications' => $qualification_chunk
				);
				try {
					$results = $http->put($settings['hostname.qe'].'/qualifications', json_encode($post_data), array(
						'header' => array('Content-Type' => 'application/json')
					));
					if ($results->code != 201) {
						$this->lecho('QE2 api error when putting MintVine qualifications, check log please.', $log_file); 
						CakeLog::write($log_file, 'Response: '. print_r($results, true));
					}
				} 
				catch (Exception $ex) {
					$this->lecho('QE2 api error when putting MintVine qualifications', $log_file); 
					CakeLog::write($log_file, $message);
				}
			}
		}
	}
	
	public function slow_router_times_alert() {
		$setting = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'slack.router_times_alert.webhook',
				'Setting.deleted' => false
			)
		));
		if (!$setting) {
			$this->out('ERROR: Slack channel missing');
			return; 
		}
		
		$user_router_logs = $this->UserRouterLog->find('list', array(
			'fields' => array('UserRouterLog.id', 'UserRouterLog.time_milliseconds'),
			'conditions' => array(
				'UserRouterLog.parent_id' => '0'
			),
			'order' => 'UserRouterLog.id desc',
			'limit' => 500,
			'recursive' => -1
		));	
		if ($user_router_logs) {
			$router_max_time = max($user_router_logs);
			$router_min_time = min($user_router_logs);
			$router_avg_time = round(array_sum($user_router_logs) / count($user_router_logs));
			$router_std_dev_time = round(Utils::stats_standard_deviation($user_router_logs));
			$message = $router_min_time.'ms (min) / '.$router_max_time.'ms (max) / '.$router_avg_time.'ms (avg) / '.$router_std_dev_time.'ms (stddev)';
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
	
	public function set_user_count_defaults() {

		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array(
					'qe.mintvine.username', 
					'qe.mintvine.password', 
					'hostname.qe',	
				),
				'Setting.deleted' => false
			)
		));
		
		$countries = array('US', 'GB', 'CA');
		foreach ($countries as $country) {
			$query_body = array(
				'partner' => 'lucid',
				'qualifications' => array(
					'country' => array(strtoupper($country)),
					'active_within_month' => array('true')
				)
			);
			App::uses('HttpSocket', 'Network/Http');
			$http = new HttpSocket(array(
				'timeout' => 30,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$http->configAuth('Basic', $settings['qe.mintvine.username'], $settings['qe.mintvine.password']);
			$results = $http->post($settings['hostname.qe'].'/query?count_only=true', json_encode($query_body), array(
				'header' => array('Content-Type' => 'application/json')
			));
			$body = json_decode($results['body'], true);
			
			$project_option = $this->ProjectOption->find('first', array(
				'conditions' => array(
					'ProjectOption.name' => 'qev.'.strtolower($country).'.count',
					'ProjectOption.project_id' => '0'
				)
			));
			if (!$project_option) {
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'name' => 'qev.'.strtolower($country).'.count',
					'project_id' => '0',
					'value' => $body['count']
				)));
			}
			else {
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'id' => $project_option['ProjectOption']['id'],
					'value' => $body['count']
				)), true, array('value'));
			}
			$this->out('Updated '.$country.' to '.$body['count']);
		}
	}
	
	public function missing_completes() {
		$models_to_import = array('BadUidLog', 'BadUidMatch', 'User');
		foreach ($models_to_import as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		$bad_uid_logs = $this->BadUidLog->find('all', array(
			'conditions' => array(
				'BadUidLog.processed' => false,
				'BadUidLog.end_action' => 'success',
			),
		));
		if (!$bad_uid_logs) {
			$this->out('BadUidLogs not found');
			return;
		}
		
		$this->out('Processing on '.count($bad_uid_logs).' BadUidLogs!');
		foreach ($bad_uid_logs as $bad_uid_log) {
			$this->out('Processing BadUid #'. $bad_uid_log['BadUidLog']['id']);
			
			// if referrer is not set we skip this log
			if (!isset($bad_uid_log['BadUidLog']['referrer']) || empty($bad_uid_log['BadUidLog']['referrer'])) {
				$this->BadUidLog->create();
				$this->BadUidLog->save(array('BadUidLog' => array(
					'id' => $bad_uid_log['BadUidLog']['id'],
					'processed' => true,
				)), true, array('processed'));
				
				continue;
			}
			
			$hash_matchable = (!empty($bad_uid_log['BadUidLog']['hash']) && strlen($bad_uid_log['BadUidLog']['hash']) > 25) ? true : false; 
			$server = Utils::print_r_reverse($bad_uid_log['BadUidLog']['server_info']);
			$bad_uid_matches = $this->BadUidMatch->find('all', array(
				'conditions' => array(
					'BadUidMatch.bad_uid_log_id' => $bad_uid_log['BadUidLog']['id']
				),
			));
			if (!$bad_uid_matches) {
				continue;
			}
			
			foreach ($bad_uid_matches as $bad_uid_match) {
				$survey_visit = $this->SurveyVisit->find('first', array(
					'conditions' => array(
						'SurveyVisit.id' => $bad_uid_match['BadUidMatch']['survey_visit_id']
					)
				));
				
				if ($bad_uid_match['BadUidMatch']['type'] != 'ip_address') {
					continue;
				}

				// check if the user agent also match
				$info = Utils::print_r_reverse($survey_visit['SurveyVisit']['info']);
				if (isset($info['HTTP_USER_AGENT']) && isset($server['HTTP_USER_AGENT']) && $info['HTTP_USER_AGENT'] != $server['HTTP_USER_AGENT']) {
					continue;
				}
					
				// check if the referrer match
				if ($bad_uid_log['BadUidLog']['referrer'] != $survey_visit['SurveyVisit']['referrer']) {
					continue;
				}
				
				// check if the user is set and is active
				$user_id = explode('-', $survey_visit['SurveyVisit']['partner_user_id']);
				if (!isset($user_id[1])) {
					continue;
				}
				
				$count = $this->User->find('count', array(
					'conditions' => array(
						'User.id' => $user_id[1],
						'User.deleted' => false
					)
				));
				if ($count == 0) {
					continue;
				}
				
				// check if the hash matches, only if the hash seem to be a valid hash
				if ($hash_matchable && $survey_visit['SurveyVisit']['hash'] != $bad_uid_log['BadUidLog']['hash']) {
					continue;
				}
				
				// check if the time diff is less then 30 minutes
				$log_time = strtotime($bad_uid_log['BadUidLog']['created']);
				$survey_visit_time = strtotime($survey_visit['SurveyVisit']['created']);
				if (($log_time - $survey_visit_time) > 1800) {
					continue;
				}
				
				$this->BadUidMatch->create();
				$this->BadUidMatch->save(array('BadUidMatch' => array(
					'id' => $bad_uid_match['BadUidMatch']['id'],
					'matched' => true,
				)), true, array('matched'));
				
				$this->out('Missing complete found!');
			}

			$this->BadUidLog->create();
			$this->BadUidLog->save(array('BadUidLog' => array(
				'id' => $bad_uid_log['BadUidLog']['id'],
				'processed' => true,
			)), true, array('processed'));
		}
		
		$this->out('Completed');
	}
	
	public function check_inactive_tangocards() {
		
		$required_settings = array('tango.platform', 'tango.key', 'tango.api_host', 'slack.alerts.inactive_tangocards'); 
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => $required_settings,
				'Setting.deleted' => false
			)
		));

		if (count($settings) != count($required_settings)) {
			return false;
		}
		
		if (empty($settings['slack.alerts.inactive_tangocards'])) {
			return false; 
		}
		$HttpSocket = new HttpSocket(array(
			'timeout' => 5,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$HttpSocket->configAuth('Basic', $settings['tango.platform'], $settings['tango.key']);
		try {
			$response = $HttpSocket->get($settings['tango.api_host'] . 'rewards');
		} 
		catch (Exception $e) {
			$this->out('Api call failed, please try again.', 'flash_error');
		}
		
		$response = json_decode($response, true);
		if (!isset($response['success']) || !$response['success']) {
			$this->out('Api call failed to retrieve gift cards.', 'flash_error');
		}
		
		$api_skus = Set::extract('/brands/rewards/sku', $response); 
		$tangocards = $this->Tangocard->find('list', array(
			'fields' => array('Tangocard.id', 'Tangocard.sku'),
			'conditions' => array(
				'Tangocard.sku <>' => '',
				'Tangocard.deleted' => false,
			)
		));
		$diff = array_diff($tangocards, $api_skus);
		if (!$diff) {
			$this->out('Check Complete');
			return;
		}
		
		$http = new HttpSocket(array(
			'timeout' => '5',
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$http->post($settings['slack.alerts.inactive_tangocards'], json_encode(array(
			'text' => '[Tangocards] The following skus are not found in api, and need to be deactivated. '.  implode(', ', $diff), 
			'username' => 'bernard'
		)));
		$this->out('Inactive tangocards found. '. implode(', ', $diff));
	}
	
	// arg[0] : (optional) partner
	public function misconfigured_questions() {
		$models_to_import = array('Setting', 'Answer', 'Question');
		foreach ($models_to_import as $model_to_import) {
			App::import('Model', $model_to_import);
			$$model_to_import = new $model_to_import;
		}
		
		$alerts = array();
		$Answer->bindModel(array('hasMany' => array(
			'AnswerText' => array(
				'fields' => array('AnswerText.id', 'AnswerText.text', 'AnswerText.country'),
			)
		)), false); 
		$Question->bindModel(array(
			'hasMany' => array(
				'QuestionText' => array(
					'fields' => array('QuestionText.id', 'QuestionText.text', 'QuestionText.country'),
				),
				'Answer' => array(
					'conditions' => array(
						'Answer.ignore' => false
					)
				)
			)
		), false);
		$conditions = array(
			'Question.question_type' => array(QUESTION_TYPE_MULTIPLE, QUESTION_TYPE_SINGLE),
			'Question.ignore' => false,
			'Question.deprecated' => false,
			'Question.deprecated' => false,
		);
		if (isset($this->args[0]) && !empty($this->args[0])) {
			$conditions['Question.partner'] = $this->args[0];
		}
		
		$count = $Question->find('count', array(
			'conditions' => $conditions
		));
		$question_id = 0;
		$i = 0;
		while (true) {
			$conditions['Question.id >'] = $question_id;
			$questions = $Question->find('all', array(
				'contain' => array(
					'QuestionText',
					'Answer' => array(
						'AnswerText'
					)
				),
				'conditions' => $conditions,
				'limit' => 20,
				'recursive' => -1,
				'order' => 'Question.id ASC'
			));
			
			if (!$questions) {
				break;
			}
			
			foreach ($questions as $question) {
				$i++;
				$question_id = $question['Question']['id'];
				
				$this->out('Processing '.$i. ' / '. $count. ' questions');
				if (empty($question['QuestionText'])) {
					$alerts[$question_id][] = 'QuestionText not found.';
					continue;
				}

				$question_countries = array();
				foreach ($question['QuestionText'] as $question_text) {
					$question_countries[] = $question_text['country'];
					if (empty($question_text['text'])) {
						$alerts[$question_id][] = 'QuestionText has empty text';
					}
				}

				if (empty($question['Answer'])) {
					$alerts[$question_id][] = 'Answers not found';
					continue;
				}

				$answer_countries = array();
				foreach ($question['Answer'] as $answer) {
					if (empty($answer['AnswerText'])) {
						$alerts[$question_id][] = 'Answer.id:'.$answer['id']. ' - AnswerText not found';
						continue;
					}

					foreach ($answer['AnswerText'] as $answer_text) {
						if (!in_array($answer_text['country'], $answer_countries)) {
							$answer_countries[] = $answer_text['country'];
						}

						if (trim($answer_text['text']) == '') {
							$alerts[$question_id][] = 'Answer.id:'.$answer['id']. ': AnswerText has empty text';
						}
					}
				}

				if ($diff = array_diff($question_countries, $answer_countries)) {
					$alerts[$question_id][] = 'Answers not found '. implode(', ', $diff). '. Though QuestionText exist for '. implode(', ', $diff);
				}

				if ($diff = array_diff($answer_countries, $question_countries)) {
					$alerts[$question_id][] = 'QuestionText not found '. implode(', ', $diff). '. Though answers exist for '. implode(', ', $diff);
				}
			}
		}
		
		if ($alerts) {
			$setting = $this->Setting->find('list', array(
				'fields' => array('Setting.name', 'Setting.value'),
				'conditions' => array(
					'Setting.name' => 'slack.questions.webhook',
					'Setting.deleted' => false
				),
				'recursive' => -1
			));
		
			$message = "TESTING misconfigured questions.....\n";
			foreach ($alerts as $question_id => $messages) {
				$message .= 'Question# <https://cp.mintvine.com/questions/view/'.$question_id.'|'. $question_id .'> '. implode(', ', $messages). "\n";
			}
			
			Utils::slack_alert($setting['slack.questions.webhook'], $message);
			$this->out(count($alerts). ' misconfigured questions found, please view detail in slack channel #zquestions');
		}
	}
	
	public function daily_history_request_report() {
		$models_to_import = array('HistoryRequest', 'HistoryRequestReport');
		foreach ($models_to_import as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}

		$start_date = date(DB_DATE, strtotime('yesterday')) . ' 00:00:00';
		$end_date = date(DB_DATE, strtotime('yesterday')) . ' 23:59:59';
		$save['date'] = date(DB_DATE, strtotime('yesterday'));
		
		$total_reported_issues = $this->HistoryRequest->find('count', array(
			'conditions' => array(
				'HistoryRequest.created >=' => $start_date,
				'HistoryRequest.created <=' => $end_date		
			),
			'recursive' => -1
		));
		$save['total_reported_issues'] = $total_reported_issues;
		
		$resolved_issues = $this->HistoryRequest->find('all', array(
			'fields' => array('HistoryRequest.modified', 'HistoryRequest.created'),
			'conditions' => array(
				'HistoryRequest.created >=' => $start_date,
				'HistoryRequest.created <=' => $end_date,
				'HistoryRequest.status' => array(SURVEY_REPORT_REQUEST_APPROVED, SURVEY_REPORT_REQUEST_REJECTED) 
			),
			'recursive' => -1
 		));
		$total_resolved_issues = count($resolved_issues);
		$save['total_resolved_issues'] = $total_resolved_issues;
		
		$median = 0;
		$time_taken = array();
		if ($total_resolved_issues) {
			foreach ($resolved_issues as $issue) {
				$time_taken[] = strtotime($issue['HistoryRequest']['modified']) - strtotime($issue['HistoryRequest']['created']);
			}
			$median = Utils::calculate_median($time_taken);
		}
		$save['average_time'] = $median;
		
		$this->HistoryRequest->bindModel(array('belongsTo' => array(
			'Transaction' => array(
				'fields' => array('Transaction.*')
			)
		)));
		$total_points = $this->HistoryRequest->find('first', array(
			'fields' => array('SUM(Transaction.amount) as amount'),
			'conditions' => array(
				'HistoryRequest.created >=' => $start_date,
				'HistoryRequest.created <=' => $end_date,
				'HistoryRequest.status' => SURVEY_REPORT_REQUEST_APPROVED
			)
		));	
		$save['total_paid_points'] = ($total_points['0']['amount']) ? $total_points['0']['amount'] : 0;
		
		$history_request_report = $this->HistoryRequestReport->find('first', array(
			'conditions' => array(
				'date' => date(DB_DATE, strtotime('yesterday'))
			)
		));
		
		if ($history_request_report) {
			$save['id'] = $history_request_report['HistoryRequestReport']['id']; 
		}

		$this->HistoryRequestReport->create();
		$this->HistoryRequestReport->save($save);
		
		$this->out('History Request Report added for '.date(DB_DATE, strtotime('yesterday')));
	}	
	
	public function write_analytics_w9_threshold() {
		$settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name' => array('segment.write_key', 'segment.w9_threshold'),
				'Setting.deleted' => false
			),
			'fields' => array(
				'name', 'value'
			)
		));	
		if (count($settings) < 2) {
			return false;
		}
		
		if (!defined('SEGMENT_WRITE_KEY')) {
			define('SEGMENT_WRITE_KEY', $settings['segment.write_key']);
			class_alias('Segment', 'Analytics');
			Analytics::init(SEGMENT_WRITE_KEY);
		}
		
		$transactions = $this->Transaction->find('all', array(
			'fields' => 'DISTINCT(Transaction.user_id) as user_id',
			'conditions' => array(
				'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
				'Transaction.created >=' =>  date('Y-01-01') . ' 00:00:00',
				'Transaction.deleted' => null
			)
		));
		$user_ids = Hash::extract($transactions, '{n}.Transaction.user_id');
		if (!$user_ids) {
			$this->out('No user withdrawal found for current year.');
			return false;
		}
		
		$i = 0;
		$this->out('Processing ' . count($user_ids) . ' users');
		foreach ($user_ids as $user_id) {
			// check if W9 Threshold event has already been fired to Segment.io for current calendar year
			$user_option = $this->UserOption->find('first', array(
				'conditions' => array(
					'UserOption.user_id' => $user_id,
					'UserOption.name' => 'segment.w9_threshold',
					'UserOption.value' => date('Y', time())
				),
				'recursive' => -1
			));
			if ($user_option) {
				$this->out('User #' . $user_id . ' w9 threshold event has already been fired for current year');
				continue;
			}
			
			// get panelist withdrawals in the current calendar year
			$total_amount = $this->Transaction->find('first', array(
				'fields' => array('SUM(Transaction.amount) AS total_amount'),
				'conditions' => array(
					'Transaction.user_id' => $user_id,
					'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
					'Transaction.status' => TRANSACTION_APPROVED,
					'Transaction.created >=' =>  date('Y-01-01') . ' 00:00:00',
					'Transaction.deleted' => null
				),
				'recursive' => -1
			));
			$withdrawn = -1 * $total_amount[0]['total_amount']; // inverse
			$total_withdrawn_amount = number_format(round($withdrawn / 100, 2), 2);
			
			if ($total_withdrawn_amount < $settings['segment.w9_threshold']) {
				continue;
			}
			
			if (!defined('IS_DEV_INSTANCE') || IS_DEV_INSTANCE === false) {
				// write analytics
				$timestamp = new DateTime(date(DB_DATETIME));
				$timestamp = $timestamp->format(DateTime::ISO8601);
				Analytics::track(array(
					'userId' => $user_id,
					'event' => 'W9 Threshold',
					'timestamp' => strtotime($timestamp),
					'properties' => array(
						'category' => 'Engagement Activity',
						'label' => 'W9 Threshold'
					)
				));	
			}
			
			// W9 Threshold event has been fired to Segment.io for current calendar year for this user
			$this->UserOption->create();
			$this->UserOption->save(array('UserOption' => array(
				'user_id' => $user_id,
				'name' => 'segment.w9_threshold',
				'value' => date('Y', time())
			)));
			$i++;
			
			$this->out('User #' . $user_id . ' ($' . $total_withdrawn_amount . ') w9 threshold event has been fired');
		}
		
		$this->out($i.' w9 threshold events has been fired to segment.io successfully.');
	}
	
	public function survey_links_alert() {
		
		// Send messages to slack
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('project.mintvine.exhaust_rate_threshold', 'slack.mintvine.survey_links_alert.webhook'),
				'Setting.deleted' => false
			)
		));
		if (!isset($settings['slack.mintvine.survey_links_alert.webhook']) || empty($settings['slack.mintvine.survey_links_alert.webhook'])) {
			$this->out('ERROR: Slack channel missing');
			return; 
		} 
			
		if (!isset($settings['project.mintvine.exhaust_rate_threshold']) || empty($settings['project.mintvine.exhaust_rate_threshold'])) {
			$this->out('ERROR: Exhaust rate missing');
			return; 
		}
		$groups = $this->Group->find('list', array(
			'fields' => array('Group.id', 'Group.key'),
			'conditions' => array(
				'Group.check_links' => true
			),
			'recursive' => -1
		));
		if (!$groups) {
			$this->out('NOOP: There are no groups with check links on');
			return; 
		}

		$exhaust_rate = (int) str_replace('%', '', $settings['project.mintvine.exhaust_rate_threshold']); 
		$this->out('Exhaust rate set to '.$exhaust_rate);
		
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Group.name'),
			'contain' => array(
				'Group',
				'ProjectAdmin'
			),
			'conditions' => array(
				'Project.router' => false, // skip routers
				'Project.recontact_id is null', // skip recontact projects
				'Project.status' => PROJECT_STATUS_OPEN, 
				'Project.active' => true,
				'Group.key' => $groups
			),
			'order' => 'Project.id DESC', // prioritize alerts on newer projects first
			'recursive' => -1
		));
		$this->out('Processing '.count($projects).' projects');
		
		if (!$projects) {
			$this->out('NOOP: There are no open projects');
			return; 
		}
	
		foreach ($projects as $key => $project) {
			// first check to see if project is using custom links; not all projects do
			$total_links = $this->SurveyLink->find('count', array(
				'conditions' => array(
					'SurveyLink.survey_id' => $project['Project']['id']
				)
			));
			if ($total_links == 0) {
				continue;
			}
			
			$remaining_survey_links = $this->SurveyLink->find('count', array(
				'conditions' => array(
					'SurveyLink.survey_id' => $project['Project']['id'],
					'SurveyLink.used' => false
				)
			));
			if ($remaining_survey_links == 0) {
				continue;
			}
						
			// don't alert if we're not hitting the threshold
			$exhausted = 100 - round($remaining_survey_links / $total_links * 100); 
			$this->out($project['Project']['id'].' has exhaust rate of '.$exhausted.'%'); 
			
			if ($exhausted < $exhaust_rate) {
				continue; 
			}
						
			$recent_survey_visits = $this->SurveyVisit->find('count', array(
				'conditions' => array(
					'SurveyVisit.survey_id' => $project['Project']['id'],
					'SurveyVisit.type' => SURVEY_CLICK,
					'SurveyVisit.created >=' => date(DB_DATETIME, strtotime('-4 hours'))
				)
			));
			
			$this->out($project['Project']['id'].' has '.$recent_survey_visits.' visits and '.$remaining_survey_links.' links remaining'); 
			
			if ((3 * $recent_survey_visits) < $remaining_survey_links) {
				continue;
			}
			
			$last_notification = $this->ProjectOption->find('first', array(
				'fields' => array('ProjectOption.id', 'ProjectOption.value'),
				'conditions' => array(
					'ProjectOption.name' => 'survey_links_alert', 
					'ProjectOption.project_id' => $project['Project']['id']
				),
				'recursive' => -1
			)); 
			if ($last_notification && strtotime($last_notification['ProjectOption']['value']) > strtotime('-1 hour')) {
				// alert already sent within the last hour 
				continue;
			}
			
			if (!$last_notification) {
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'name' => 'survey_links_alert',
					'project_id' => $project['Project']['id'],
					'value' => date(DB_DATETIME)
				)));
			}
			else {
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'id' => $last_notification['ProjectOption']['id'],
					'value' => date(DB_DATETIME)
				)), true, array('value'));
			}
			
			$send_pm_notification = '';
			if (!empty($project['ProjectAdmin'])) {
				foreach ($project['ProjectAdmin'] as $project_admins) {
					$admin = $this->Admin->find('first', array(
						'fields' => array('Admin.slack_username'),
						'conditions' => array(
							'Admin.id' => $project_admins['admin_id']
						),
						'recursive' => -1
					));
					if (!empty($admin['Admin']['slack_username'])) {
						$send_pm_notification .= '@'.$admin['Admin']['slack_username'].' ';
					}
				}
			}

			$message = $send_pm_notification.'HIGH LINK EXHAUST RATE (*'.$exhausted.'%*): ('.$recent_survey_visits.' clicks in last 4 hours) ('.$remaining_survey_links.' links remaining) #'.$project['Project']['id']. ' ('.$project['Group']['name'].') ';
			$message .= '- <https://cp.mintvine.com/surveys/dashboard/'.$project['Project']['id'].'>'; 
			Utils::slack_alert($settings['slack.mintvine.survey_links_alert.webhook'], $message);
			$this->out('SLACK: '.$message);		
		}
	}
	
	// args[0]: 
	// start date in YYYY-MM-DD format, to process users created after the date specified
	// if not specified, all users will be processed
	public function export_bad_postal_codes() {
		ini_set('memory_limit', '1024M');
		if (!empty($this->args[0])) {
			$timestamp = strtotime($this->args[0]);
			if (!$timestamp) {
				$this->out('Invalid date');
				return;
			}
			$start_date = date(DB_DATETIME, $timestamp);
			$date_check = array('User.created >=' => $start_date);
		}
		$total = $this->User->find('count', array(
			'conditions' => array(
				'User.extended_registration' => true,
				'QueryProfile.postal_code IS NOT NULL',
				isset($date_check) ? $date_check: ''
			),
			'contain' => array('QueryProfile')
		));
		
		if ($total > 0) {
			$this->out('Bad postal code check started : ' . $total . ' total users found having postal code'. (isset($start_date) ? ' created after ' . date(DB_DATE, $timestamp) : '.'));
		}
		else {
			$this->out('No users found having postal code provided'. (isset($start_date) ? ' after ' . date(DB_DATE, $timestamp) : '.'));
			$this->out('Finished.');
			return;
		}
		
		$data = array(array(
			'User ID',
			'Email',
			'Country',
			'State',
			'Postal Code',
			'Created',
			'Verified',
			'Last Touched',
			'Last Login',
			'Timezone',
			'Active',
			'Deleted',
			'Hellbanned'
		));
		
		$user_id = $count = $repeated = $i = 0;
		while (true) {
			$users = $this->User->find('all', array(
				'conditions' => array(
					'User.id >' => $user_id,
					'User.extended_registration' => true,
					'QueryProfile.postal_code IS NOT NULL',
					isset($date_check) ? $date_check: ''
				),
				'contain' => array('QueryProfile'),
				'order' => 'User.id ASC',
				'limit' => 10000
			));

			if (!$users) {
				break;
			}

			foreach ($users as $user) {
				$this->QueryProfile->set($user);
				if (!$this->QueryProfile->validateZip()) {
					$data[] = array(
						$user['User']['id'],
						$user['User']['email'],
						$user['QueryProfile']['country'],
						$user['QueryProfile']['state'],
						$user['QueryProfile']['postal_code'],
						date(DB_DATE, strtotime($user['User']['created'])),
						!empty($user['User']['verified']) ? date(DB_DATE, strtotime($user['User']['verified'])): '',
						!empty($user['User']['last_touched']) ? date(DB_DATE, strtotime($user['User']['last_touched'])): '',
						date(DB_DATE, strtotime($user['User']['login'])),
						$user['User']['timezone'],
						($user['User']['active'] == 1) ? 'Y' : 'N',
						!empty($user['User']['deleted_on']) ? 'Y': 'N',
						!empty($user['User']['hellbanned_on']) ? 'Y': 'N'
					);
					$i++;
				}

				$user_id = $user['User']['id'];
				
				$count++;
				$percentage = floor((($count / $total) * 100) / 10) * 10;
				if ($percentage % 10 == 0 && $percentage != $repeated || $percentage == 5 && $percentage != $repeated) {
					$repeated = $percentage;
					$this->out('Bad postal code check : ' . $percentage . '% completed');
				}
			}
		}
		
		$this->out('Total invalid postal codes found : ' . $i);
		if ($i > 0) {
			$this->out('Exporting...');
			$csv = 'invalid_zip_codes' .'_'. (isset($timestamp) ? date(DB_DATE, $timestamp).'_' : '') . time() .'.csv';
			$fp = fopen(WWW_ROOT . 'files/' . $csv, "w");
			foreach ($data as $row) {
				fputcsv($fp, $row);
			}
			fclose($fp);
			$this->out(WWW_ROOT . 'files/' . $csv);
		}
		$this->out('Finished.');
	}
}