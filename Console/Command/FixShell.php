<?php
App::uses('CakeEmail', 'Network/Email');
App::import('Lib', 'Utilities');
App::import('Lib', 'MintVine');
App::import('Lib', 'MintVineUser');
App::import('Vendor', 'mixpanel/Mixpanel');
App::uses('HttpSocket', 'Network/Http');

class FixShell extends AppShell {
 	var $uses = array('CintSurvey', 'Admin','AdminRole', 'Transaction', 'Poll', 'RecontactHash', 'FedSurvey', 'Question', 'Qualification', 'Group', 'QueryProfile', 'SurveyVisit', 
		'SurveyVisitCache', 'Source', 'SurveyComplete', 'TolunaPostback', 'UserAcquisition', 'UserIp', 'SurveyPartner', 'PaymentMethod', 'SurveyLink', 
		'GeoZip', 'User', 'HellbanLog', 'SurveyPartner', 'UserProfile', 'UserChecklist', 'SurveyUserVisit', 'UserSyncTimestamp', 'LucidZip', 
		'PanelistHistory', 'UserAcquisitionData', 'PartnerUser', 'LucidQueue', 'Project', 'ProfileAnswer', 'ProfileQuestion', 'Contact', 'Client', 
		'QueryHistory', 'SurveyUser', 'ProjectOption', 'Nonce', 'SurveyUserQuery', 'RouterLog', 'Offer', 'ProjectLog', 'Report', 
		'Setting', 'Invoice', 'RfgSurvey', 'TangocardOrder', 'ProjectRate', 'Partner', 'UserAnalysis', 'SourceReport', 'PartnerUser', 
		'TwilioNumber', 'ProjectAdmin', 'Dictionary', 'UserOption', 'UsurvReport', 'GroupReport', 'UserRouterLog', 'UserAddress', 'LucidZip', 
		'HistoryRequest', 'SurveyCountry', 'LucidStudyType', 'CashNotification', 'CintUserQualification', 'NotificationSchedule', 'NotificationTemplate', 'MailgunLog', 'Withdrawal', 'HistoryRequest', 'HistoryRequestReport', 'PrescreenerStatistic');
	public $tasks = array('Maintenance', 'Cint', 'Points2shop', 'UserAnalyzer');
	
	public function spectrum_project_qe2_age_issue() {
		$spectrum_group = $this->Group->find('first', array(
			'fields' => array('Group.id', 'Group.key'),
			'conditions' => array(
				'Group.key' => 'spectrum'
			)
		));
		$project_ids = $this->Project->find('list', array(
			'fields' => array('Project.id', 'Project.id'),
			'conditions' => array(
				'Project.group_id' => $spectrum_group['Group']['id']
			)
		));
		$project_options = $this->ProjectOption->find('all', array(
			'fileds' => array('ProjectOption.id'),
			'conditions' => array(
				'ProjectOption.project_id' => $project_ids,
				'ProjectOption.name' => 'spectrum.qe2.qualification'
			)
		));
		$this->out('Deleting spectrum qualification hash values');
		if ($project_options) {
			foreach ($project_options as $project_option) {
				$this->ProjectOption->delete($project_option['ProjectOption']['id']);
			}
		}
		$this->out('Fixed total: '. count($project_options));
	}
	
	public function find_ssi_completes_after_nq() {
		$survey_visits = $this->SurveyVisit->find('all', array(
			'fields' => array('created', 'type', 'partner_user_id', 'survey_id'),
			'conditions' => array(
				'SurveyVisit.survey_id' => 107043,
				'SurveyVisit.type' => SURVEY_NQ
			),
			'recursive' => -1
		));
		foreach ($survey_visits as $survey_visit) {
			list($survey_id, $user_id, $nada1, $nada2) = explode('-', $survey_visit['SurveyVisit']['partner_user_id']);
			$count = $this->SurveyVisit->find('count', array(
				'conditions' => array(
					'SurveyVisit.type' => SURVEY_COMPLETED,
					'SurveyVisit.survey_id' => $survey_visit['SurveyVisit']['survey_id'],
					'SurveyVisit.created >=' => date(DB_DATETIME, strtotime($survey_visit['SurveyVisit']['created'])),
					'SurveyVisit.partner_user_id LIKE' => '%'.$user_id.'%'
				)
			));
			if ($count > 0) {
				echo $user_id."\n";
			}
		}
	}
		
	public function cleanup_dupe_cint_projects() {
		$this->CintSurvey->bindModel(array(
			'belongsTo' => array(
				'Project' => array(
					'className' => 'Project',
					'foreignKey' => 'survey_id'
				),
				'Survey' => array(
					'className' => 'Survey',
					'foreignKey' => 'survey_id'
				)
			)
		));
		$cint_surveys = $this->CintSurvey->find('all', array(
			'conditions' => array(
				'Project.status' => array(PROJECT_STATUS_OPEN, PROJECT_STATUS_STAGING, PROJECT_STATUS_SAMPLING, PROJECT_STATUS_CLOSED)
			)
		));
		$cint_ids = array();
		foreach ($cint_surveys as $cint_survey) {
			$cint_ids[] = $cint_survey['CintSurvey']['cint_survey_id'];
		}
		$count = array_count_values($cint_ids); 
		foreach ($count as $cint_survey_id => $survey_count) {
			if ($survey_count == 1) {
				continue;
			}
			// this has dupes: clean it up	
			$this->CintSurvey->bindModel(array(
				'belongsTo' => array(
					'Project' => array(
						'className' => 'Project',
						'foreignKey' => 'survey_id'
					),
					'Survey' => array(
						'className' => 'Survey',
						'foreignKey' => 'survey_id'
					)
				)
			));		
			$cint_surveys = $this->CintSurvey->find('all', array(
				'conditions' => array(
					'CintSurvey.cint_survey_id' => $cint_survey_id,
					'Project.status' => array(PROJECT_STATUS_OPEN, PROJECT_STATUS_STAGING, PROJECT_STATUS_SAMPLING, PROJECT_STATUS_CLOSED)
				)
			));
			unset($cint_surveys[0]);
			foreach ($cint_surveys as $dupe_cint_survey) {
				echo 'Cleaning up #C'.$dupe_cint_survey['CintSurvey']['id'].' (#'.$dupe_cint_survey['Project']['id'].')'."\n";
				$this->CintSurvey->delete($dupe_cint_survey['CintSurvey']['id']); 
				
				$this->Project->Survey->create();
				$this->Project->Survey->save(array('Survey' => array(
					'id' => $dupe_cint_survey['Project']['id'],
					'active' => false,
					// update ended if it's blank - otherwise leave the old value
					'ended' => empty($dupe_cint_survey['Survey']['ended']) ? date(DB_DATETIME) : $dupe_cint_survey['Survey']['ended']
				)), true, array('active', 'ended'));

				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $dupe_cint_survey['Project']['id'],
					'ignore_autoclose' => true,
					'status' => PROJECT_STATUS_INVOICED
				)), true, array('status', 'ignore_autoclose'));
				
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $dupe_cint_survey['Project']['id'],
					'type' => 'status.closed.automanual',
					'description' => 'Closed by Roy to deal with dupe project ID issue'
				)));
			}
		}
	}
	
	public function cleanup_dupe_fed_surveys() {
		ini_set('memory_limit', '4096M');
		$fed_surveys = $this->FedSurvey->find('all', array(
			'fields' => array('id', 'fed_survey_id', 'survey_id'),
			'recursive' => -1,
			'conditions' => array(
				'FedSurvey.status' => array('skipped.link.unsupported', 'skipped.country'),
				'FedSurvey.id >' => 2000000
			)
		));
		$fed_survey_ids = array();
		foreach ($fed_surveys as $fed_survey) {
			$key = $fed_survey['FedSurvey']['survey_id'].'-'.$fed_survey['FedSurvey']['fed_survey_id']; 
			if (!in_array($key, $fed_survey_ids)) {
				$fed_survey_ids[] = $key;
			}
			$all_fed_surveys = $this->FedSurvey->find('all', array(
				'conditions' => array(
					'FedSurvey.fed_survey_id' => $fed_survey['FedSurvey']['fed_survey_id'],
					'FedSurvey.survey_id' => $fed_survey['FedSurvey']['survey_id']
				)
			));
			if (!$all_fed_surveys || count($all_fed_surveys) == 1) {
				continue;
			}
			foreach ($all_fed_surveys as $key => $all_fed_survey) {
				if ($key == '0') {
					// skip
					continue;
				}
				$this->FedSurvey->delete($all_fed_survey['FedSurvey']['id']);
				echo $all_fed_survey['FedSurvey']['survey_id'].' - '.$all_fed_survey['FedSurvey']['fed_survey_id']."\n";
			}
		}
	}
	
	public function profile_survey_dupes() {
		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'profiles'
			)
		));
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.group_id' => $group['Group']['id']
			)
		));
		foreach ($projects as $project) {
			echo $project['Project']['id']."\n";
			$survey_visits = $this->SurveyVisit->find('all', array(
				'conditions' => array(
					'SurveyVisit.survey_id' => $project['Project']['id'],
					'SurveyVisit.type' => SURVEY_DUPE
				)
			));
			foreach ($survey_visits as $survey_visit) {
				$visits = $this->SurveyVisit->find('all', array(
					'conditions' => array(
						'SurveyVisit.survey_id' => $survey_visit['SurveyVisit']['survey_id'],
						'SurveyVisit.partner_user_id' => $survey_visit['SurveyVisit']['partner_user_id']
					)
				));
				if ($visits) {
					foreach ($visits as $visit) {
						$this->SurveyVisit->delete($visit['SurveyVisit']['id']); 
						echo '.'; 
					}
				}
				list($project_id, $user_id, $nothing, $nothing2) = explode('-', $survey_visit['SurveyVisit']['partner_user_id']);
				if (empty($user_id)) {
					continue;
				}
				$survey_user_visits = $this->SurveyUserVisit->find('all', array(
					'conditions' => array(
						'SurveyUserVisit.survey_id' => $survey_visit['SurveyVisit']['survey_id'],
						'SurveyUserVisit.user_id' => $user_id
					)
				));;
				if ($survey_user_visits) {
					foreach ($survey_user_visits as $survey_user_visit) {
						$this->SurveyUserVisit->delete($survey_user_visit['SurveyUserVisit']['id']); 
						echo '.'; 
					}
				}
			}
		}
	}
	
	public function hacked_accounts() {
		$start_date = '2015-10-20 04:00:00';
		$affected = array();
		$payment_methods = $this->PaymentMethod->find('all', array(
			'fields' => array('PaymentMethod.*', 'QueryProfile.country'),
			'conditions' => array(
				'PaymentMethod.created >=' => $start_date,
			),
			'joins' => array(
    		    array(
		            'alias' => 'QueryProfile',
		            'table' => 'query_profiles',
		            'conditions' => array(
						'QueryProfile.user_id = PaymentMethod.user_id'
					),
					'fields' => array('QueryProfile.country')
		        )
			),
		));
		foreach ($payment_methods as $payment_method) {
			$count = $this->UserIp->find('count', array(
				'conditions' => array(
					'UserIp.user_id' => $payment_method['PaymentMethod']['user_id'],
					'UserIp.country <>' => $payment_method['QueryProfile']['country'],
					'UserIp.created >=' => $start_date
				)
			));
			if ($count > 0) {
				$affected[] = $payment_method['PaymentMethod']['user_id'];
			}
		}
		$affected = array_unique($affected);
		echo implode("\n", $affected);
	}
	
	public function decode() {
		echo urldecode('http%3a%2f%2fr.mintvine.com%2fquota%2f%3fuid%3d69238d4ae172622994e68e14133f7200e&cancel=http%3a%2f%2fr.mintvine.com%2fquota%2f%3fuid%3d69238d4ae172622994e68e14133f7200e'); 
	}
	
	public function create_recontact_hashes() {
		
		if (!isset($this->args[0])) {
			$this->out('Please set a project ID'); 
			return false;
		}
		$project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $this->args[0]
			)
		));
		$survey_links = $this->SurveyLink->find('all', array(
			'conditions' => array(
				'SurveyLink.survey_id' => $this->args[0]
			)
		));
		$i = 0; 
		foreach ($survey_links as $survey_link) {
			$dupe_recontacts = $this->RecontactHash->find('list', array(
				'conditions' => array(
					'RecontactHash.hash' => $survey_link['SurveyLink']['forced_hash'],
					'RecontactHash.original_project_id' => $project['Project']['recontact_id'],
					'RecontactHash.project_id <>' => $project['Project']['id'],
					'RecontactHash.completed' => false
				),
				'recursive' => -1,
				'fields' => array('RecontactHash.id', 'RecontactHash.project_id')
			));
			if (empty($dupe_recontacts)) {
				$this->RecontactHash->create();
				$this->RecontactHash->save(array('RecontactHash' => array(
					'hash' =>$survey_link['SurveyLink']['forced_hash'],
					'original_project_id' => $project['Project']['recontact_id'],
					'project_id' => $project['Project']['id']
				)));
				$i++; 
			}
		}
		$this->out('Wrote '.$i.' recontact hash records'); 
	}
	
	public function extract_ip() {
		$results = file_get_contents(WWW_ROOT . 'files/ip_results.txt');
		$results = explode("\n", $results);
		array_walk($results, create_function('&$val', '$val = trim($val);')); 
		foreach ($results as $row) {
			$data = explode(' ', $row);
			if ($data[0] > 20 && $data[2] == '/users/login') {
				echo 'deny from '.$data[1]."\n";
			}
		}
	}
	
	public function cleanup_cint_surveys() {
		$cint_surveys = $this->CintSurvey->find('all', array(
			'fields' => array('CintSurvey.survey_id', 'CintSurvey.id')
		));
		foreach ($cint_surveys as $cint_survey) {
			$count = $this->Project->find('count', array(
				'conditions' => array(
					'Project.id' => $cint_survey['CintSurvey']['survey_id']
				)
			));
			if ($count == 0) {
				print_r($cint_survey);
			}
		}
	}
	
	public function cleanup_surveys() {
		// sanity check
		if (!isset($this->args[0]) || $this->args[0] != 'go') {
			return false;
		}
		$projects = $this->Project->find('all', array(
			'recursive' => -1,
			'fields' => array('id', 'group_id', 'date_created', 'prj_name'),
			'conditions' => array(
				'Project.group_id' => 5,
				'Project.id >=' => 211464
			)
		)); 
		foreach ($projects as $project) {
			$this->Project->delete($project['Project']['id']);
			$this->out($project['Project']['id']); 
		}
	}
	
	// https://basecamp.com/2045906/projects/1413421/todos/208609642
	public function unhellban() {
		$this->HellbanLog->bindModel(array('belongsTo' => array(
			'User' => array(
				'fields' => array('User.hellbanned')
			)
		)));
		$hellban_logs = $this->HellbanLog->find('all', array(
			'conditions' => array(
				'HellbanLog.created >=' => date(DB_DATETIME, strtotime('-1 week')),
				'HellbanLog.type' => 'hellban'
			)
		));
		foreach ($hellban_logs as $hellban_log) {
			if (!$hellban_log['User']['hellbanned']) {
				continue;
			}
			$count = $this->HellbanLog->find('count', array(
				'conditions' => array(
					'HellbanLog.user_id' => $hellban_log['HellbanLog']['user_id'],
					'HellbanLog.automated' => false,
					'HellbanLog.type' => 'unhellban'
				)
			));
			if ($count > 0) {
				$this->User->create();
				$this->User->save(array('User' => array(
					'id' => $hellban_log['HellbanLog']['user_id'],
					'hellbanned' => '0',
					'checked' => '1',
					'hellbanned_on' => null
				)), true, array('hellbanned', 'checked', 'hellbanned_on'));
		
				$this->HellbanLog->create();
				$this->HellbanLog->save(array('HellbanLog' => array(
					'user_id' => $hellban_log['HellbanLog']['user_id'],
					'type' => 'unhellban',
					'automated' => false
				)));
				echo $hellban_log['HellbanLog']['user_id']."\n";
			}
		}
	}
	
	public function fix_0_ssi_transactions() {
		$transactions = $this->Transaction->find('all', array(
			'conditions' => array(
				'Transaction.type_id' => TRANSACTION_SURVEY,
				'Transaction.status' => TRANSACTION_PENDING,
				'Transaction.amount' => '1',
				'Transaction.deleted' => null,
			)
		));
		foreach ($transactions as $transaction) {
			$this->Transaction->create();
			$this->Transaction->save(array('Transaction' => array(
				'id' => $transaction['Transaction']['id'],
				'modified' => false,
				'amount' => 60
			)), array(
				'callbacks' => false,
				'validate' => false,
				'fieldList' => array('amount')
			));
			$this->User->rebuildBalances($transaction); 
			$this->out('Fixed '.$transaction['Transaction']['id'].' (#'.$transaction['Transaction']['user_id'].')'); 
		}
	}
	
	public function reopen_ir_0_projects() {
		$project_ids = $this->ProjectLog->find('list', array(
			'fields' => array('ProjectLog.project_id'),
			'recursive' => -1,
			'conditions' => array(
				'ProjectLog.type' => 'status.closed.auto',
				'ProjectLog.description LIKE' => '%IR has been set to 0'
			)
		));
		foreach ($project_ids as $project_id) {
			// reopen project
			$this->Project->Survey->create();
			$this->Project->Survey->save(array('Survey' => array(
				'id' => $project_id,
				'ended' => null,
				'active' => true,
			)), true, array('active', 'ended'));

			$count = $this->SurveyUser->find('count', array(
				'conditions' => array(
					'SurveyUser.survey_id' => $project_id
				),
				'recursive' => -1
			));
			
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project_id,
				'ignore_autoclose' => false,
				'status' => $count == 0 ? PROJECT_STATUS_STAGING : PROJECT_STATUS_OPEN,
			)), true, array('status', 'ignore_autoclose'));
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project_id,
				'type' => 'status.opened',
				'description' => 'Reopened by Roy due to buggy close scripts'
			)));
						
			echo $project_id."\n";
		}
		print_r($project_ids);
	}
	
	public function set_fulcrum_project_ids() {
		$fed_surveys = $this->FedSurvey->find('all');
		print_r($fed_surveys);
	}
	// https://basecamp.com/2045906/projects/1413421/todos/207271924
	public function find_dupe_referrals() {
		// transaction id for june 1st: 6464401
		$transactions = $this->Transaction->find('list', array(
			'conditions' => array(
				'Transaction.id >=' => 8439964,
				'Transaction.type_id' => TRANSACTION_REFERRAL,
				'Transaction.referrer_id' => '0',
				'Transaction.linked_to_id >' => '0',
				'Transaction.deleted' => null,
			),
			'fields' => array('Transaction.id', 'Transaction.linked_to_id'),
			'recursive' => -1,
		));
		$amount_affected = 0;
		$i = 0; 
		foreach ($transactions as $id => $linked_to_id) {
			unset($transactions[$id]);
			if (in_array($linked_to_id, $transactions)) {
				$key = array_search($linked_to_id, $transactions); 
				$transaction = $this->Transaction->find('first', array(
					'fields' => array('amount'),
					'conditions' => array(
						'Transaction.id' => $key,
						'Transaction.status' => TRANSACTION_PENDING,
						'Transaction.deleted' => null,
					),
					'recursive' => -1
				));
				if ($transaction) {
					$i++;
					$this->Transaction->delete($key);
					$amount_affected += $transaction['Transaction']['amount'];
					echo $linked_to_id.':' .$key."\n";
				}
			}
		}
		echo $amount_affected."\n".$i; 
	}
	
	public function delete_invites_from_cint() {
		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'cint'
			)
		));
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'Project.status' => PROJECT_STATUS_OPEN,
			)
		));
		foreach ($projects as $project) {
			echo $project['Project']['id']."\n";
			$survey_users = $this->SurveyUser->find('all', array(
				'conditions' => array(
					'SurveyUser.survey_id' => $project['Project']['id']
				)
			));
			foreach ($survey_users as $survey_user) {
				$this->SurveyUser->delete($survey_user['SurveyUser']['id']);
				echo '.'; 
			}
			echo "\n";
		}
	}
	
	public function migrate_cards() {
		$payment_methods = $this->PaymentMethod->find('all', array(
			'conditions' => array(
				'PaymentMethod.payment_method' => 'gift',
				'PaymentMethod.status' => 'active'
			)
		));
		foreach ($payment_methods as $payment_method) {
			// deactivate
			$payment_method['PaymentMethod']['status'] = 'deactive';
			$this->PaymentMethod->create();
			$this->PaymentMethod->save($payment_method, true, array('status'));
			
			unset($payment_method['PaymentMethod']['id']);
			unset($payment_method['PaymentMethod']['created']);
			unset($payment_method['PaymentMethod']['modified']);
			$payment_method['PaymentMethod']['payment_method'] = 'tango';
			$payment_method['PaymentMethod']['status'] = 'active'; 			
			
			// amazon us : AMZN-E-V-STD : 1
			if ($payment_method['PaymentMethod']['payment_id'] == 73) {
				$payment_method['PaymentMethod']['payment_id'] = 'AMZN-E-V-STD'; 			
			}
			// target us $25 : TRGT-E-V-STD : 2
			elseif ($payment_method['PaymentMethod']['payment_id'] == 629) {
				$payment_method['PaymentMethod']['payment_id'] = 'TRGT-E-V-STD';				
			}
			// target us $10: TRGT-E-V-STD : 2
			elseif ($payment_method['PaymentMethod']['payment_id'] == 628) {
				$payment_method['PaymentMethod']['payment_id'] = 'TRGT-E-V-STD';
			}
			// ebay - NO MAPPING
			elseif ($payment_method['PaymentMethod']['payment_id'] == 679) {
				// cannot map
				continue;
			}
			// banana republic : GAP1-E-V-STD : 44
			elseif ($payment_method['PaymentMethod']['payment_id'] == 764) {
				$payment_method['PaymentMethod']['payment_id'] = 'GAP1-E-V-STD';				
			}
			// $10 starbucks : SBUX-E-1000-STD : 46
			elseif ($payment_method['PaymentMethod']['payment_id'] == 778) {
				$payment_method['PaymentMethod']['payment_id'] = 'SBUX-E-1000-STD';
			}
			// best buy gift card : BSTB1-E-V-STD : 62
			elseif ($payment_method['PaymentMethod']['payment_id'] == 758) {
				$payment_method['PaymentMethod']['payment_id'] = 'BSTB1-E-V-STD';
			}
			// $25 itunes APPL-E-2500-STD: 12
			elseif ($payment_method['PaymentMethod']['payment_id'] == 490) {
				$payment_method['PaymentMethod']['payment_id'] = 'APPL-E-2500-STD';
			}
			// old navy gift card: GAP1-E-V-STD : 44 
			// note: old navy, gap, and banana are all the same company - same gift card works for all
			elseif ($payment_method['PaymentMethod']['payment_id'] == 599) {
				$payment_method['PaymentMethod']['payment_id'] = 'GAP1-E-V-STD';
			}
			// gap gift card: GAP1-E-V-STD : 44
			elseif ($payment_method['PaymentMethod']['payment_id'] == 765) {
				$payment_method['PaymentMethod']['payment_id'] = 'GAP1-E-V-STD';				
			}
			// $10 itunes gift card : APPL-E-1000-STD: 9
			elseif ($payment_method['PaymentMethod']['payment_id'] == 482) {
				$payment_method['PaymentMethod']['payment_id'] = 'APPL-E-1000-STD';	
			}
			// $15 itunes gift card : APPL-E-1500-STD: : 10
			elseif ($payment_method['PaymentMethod']['payment_id'] == 693) {
				$payment_method['PaymentMethod']['payment_id'] = 'APPL-E-1500-STD';	
			}
			// $50 itunes gift card : APPL-E-5000-STD : 13
			elseif ($payment_method['PaymentMethod']['payment_id'] == 491) {
				$payment_method['PaymentMethod']['payment_id'] = 'APPL-E-5000-STD';	
			}
			$this->PaymentMethod->create();
			$this->PaymentMethod->save($payment_method, false); // We skip the validation because PaymentMethod ValidateValue will stop saving some of the records
			echo 'User id: '. $payment_method['PaymentMethod']['user_id']. ' payment method updated.'. "\n";
		}
	}
	
	public function close_mass_projects() {
		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'toluna'
			)
		));
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'Project.status' => PROJECT_STATUS_OPEN,
				'OR' => array(
					'Survey.started is null',
					'Survey.started <=' => date(DB_DATETIME, strtotime('-24 hours'))
				)	
			)
		));
		foreach ($projects as $project) {
			echo $project['Project']['id']."\n";
			$this->Project->Survey->create();
			$this->Project->Survey->save(array('Survey' => array(
				'id' => $project['Project']['id'],
				'active' => false,
				// update ended if it's blank - otherwise leave the old value
				'ended' => empty($project['Survey']['ended']) ? date(DB_DATETIME) : $project['Survey']['ended']
			)), true, array('active', 'ended'));

			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'status' => PROJECT_STATUS_CLOSED
			)), true, array('status'));
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'status.closed',
				'description' => 'Mass closing projects in preparation of new Cint integration'
			)));
		}
	}
	
	public function survey_start() {
		$surveys = $this->Survey->find('all', array(
			'fields' => array('id'),
			'conditions' => array(
				'Survey.started is null',
			),
			'recursive' => -1
		));
		foreach ($surveys as $survey) {
			$survey_visit = $this->SurveyVisit->find('first', array(
				'conditions' => array(
					'SurveyVisit.survey_id' => $survey['Survey']['id']
				),
				'fields' => array('created'),
				'order' => 'SurveyVisit.id ASC'
			));
			if ($survey_visit) {
				$this->Survey->create();
				$this->Survey->save(array('Survey' => array(
					'id' => $survey['Survey']['id'],
					'started' => $survey_visit['SurveyVisit']['created'],
					'modified' => false
				)), true, array('started'));
				echo $survey['Survey']['id']."\n";
			}
		}
	}
	
	public function survey_end() {
		$this->Project->unbindModel(array('hasMany' => array(
			'SurveyPartner', 'ProjectOption'
		)));
		$projects = $this->Project->find('all', array(
			'fields' => array(
				'Survey.ended', 'Project.id', 'Survey.id'
			),
			'conditions' => array(
				'Project.status' => PROJECT_STATUS_OPEN,
				'Survey.active' => true,
				'Survey.ended is not null'
			)
		));
		foreach ($projects as $project) {
			$this->Survey->create();
			$this->Survey->save(array('Survey' => array(
				'id' => $project['Survey']['id'],
				'ended' => null,
				'modified' => false
			)), true, array('ended'));
		}
		print_r($projects);
	}
	
	public function toluna_awards() {
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'toluna'
			)
		));
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.group_id' => $group['Group']['id']
			)
		));
		$client_rate = 1.25; 
		$partner_rate = round(1.25 / 3, 2); 
		$award = $partner_rate * 100; 
		foreach ($projects as $project) {
			if ($project['Project']['client_rate'] != $client_rate) {
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'client_rate' => $client_rate
				)), true, array('client_rate'));
								
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'project.updated',
					'description' => 'Client rate updated from "' . $project['Project']['client_rate'] . '" to "' . $client_rate . '"',
				)));
			}
			if ($project['Project']['partner_rate'] != $partner_rate) {
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'partner_rate' => $partner_rate
				)), true, array('partner_rate'));
				
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'project.updated',
					'description' => 'Partner rate updated from "' . $project['Project']['partner_rate'] . '" to "' . $partner_rate . '"',
				)));
			}
			if ($project['Survey']['award'] != $award) {
				$this->Survey->create();
				$this->Survey->save(array('Survey' => array(
					'id' => $project['Survey']['id'],
					'award' => $award
				)), true, array('award'));
				
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'project.updated',
					'description' => 'Award updated from "' . $project['Survey']['award'] . '" to "' . $award . '"',
				)));
			}
			foreach ($project['SurveyPartner'] as $survey_partner) {
				$this->SurveyPartner->create();
				$this->SurveyPartner->save(array('SurveyPartner' => array(
					'id' => $survey_partner['id'],
					'rate' => $partner_rate
				)), true, array('rate'));
				
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $survey_partner['survey_id'],
					'type' => 'survey_partner.updated',
					'description' => 'Rate updated from "' . $survey_partner['rate'] . '" to "' . $partner_rate . '"',
				)));
			}
			echo $project['Project']['id']."\n";
		}
	}
	public function generate_user_sync_rows() {
		ini_set('memory_limit', '2048M');
		$users = $this->User->find('all', array(
			'recursive' => -1,
			'fields' => array('id')
		));
		foreach ($users as $user) {
			$timestamp = $this->UserSyncTimestamp->find('first', array(
				'conditions' => array(
					'UserSyncTimestamp.user_id' => $user['User']['id']
				)
			));
			if (!$timestamp) {
				$this->UserSyncTimestamp->create();
				$this->UserSyncTimestamp->save(array('UserSyncTimestamp' => array(
					'user_id' => $user['User']['id']
				)));
				echo $user['User']['id']."\n";
			}
		}
	}
	
	public function transition_user_timestamp() {
		ini_set('memory_limit', '2048M');
		if (!isset($this->args[0])) {
			return;
		}
		$users = $this->User->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'User.'.$this->args[0].' is not null',
				'User.deleted' => false
			),
			'fields' => array('id', $this->args[0])
		));
		foreach ($users as $user) {
			$timestamp = $this->UserSyncTimestamp->find('first', array(
				'conditions' => array(
					'UserSyncTimestamp.user_id' => $user['User']['id']
				)
			));
			if (!$timestamp) {
				$this->UserSyncTimestamp->create();
				$this->UserSyncTimestamp->save(array('UserSyncTimestamp' => array(
					'user_id' => $user['User']['id']
				)));
				$id = $this->UserSyncTimestamp->getInsertId();
			}
			else {
				$id = $timestamp['UserSyncTimestamp']['id'];
			}
			
			$this->UserSyncTimestamp->create();
			$this->UserSyncTimestamp->save(array('UserSyncTimestamp' => array(
				'id' => $id,
				$this->args[0] => $user['User'][$this->args[0]]
			)), true, array($this->args[0]));
			echo $user['User']['id']."\n";
		}
	}
	public function adwall_transactions() {
		$transactions = $this->Transaction->find('all', array(
			'conditions' => array(
				'Transaction.linked_to_id' => array(1273, 1625),
				'Transaction.type_id' => TRANSACTION_OFFER,
				'Transaction.name' => 'Points for completing offer Missing Points for Adwall Offers',
				'Transaction.deleted' => null,
			)
		));
		if ($transactions) {
			foreach ($transactions as $transaction) {
				$this->Transaction->create();
				$this->Transaction->save(array('Transaction' => array(
					'id' => $transaction['Transaction']['id'],
					'executed' => '2015-07-05 00:00:00',
					'name' => 'Missing Points for Adwall Offers (2015/04 - 2015/07)',
				)), true, array('executed', 'name'));
			}
		}
	}
	public function checklist_items() {
		$conditions = array(
			'User.active' => true,
			'User.deleted' => false
		);
		if (isset($this->args[0])) {
			$conditions['User.id'] = $this->args[0];
		}
		$users = $this->User->find('list', array(
			'conditions' => $conditions,
			'fields' => array('id', 'id'),
			'fields' => array(
				'User.id'
			),
			'recursive' => -1
		));
		foreach ($users as $user_id) {
			// check profile
			$count = $this->UserChecklist->find('count', array(
				'conditions' => array(
					'UserChecklist.user_id' => $user_id,
					'UserChecklist.name' => 'profile'
				)
			));
			if ($count == 0) {
				$transaction = $this->Transaction->find('first', array(
					'conditions' => array(
						'Transaction.user_id' => $user_id,
						'Transaction.type_id' => TRANSACTION_PROFILE,
						'Transaction.linked_to_id' => '1',
						'Transaction.deleted' => null,
					),
					'recursive' => -1,
					'fields' => array('id', 'created')
				));
				if ($transaction) {
					echo '[P] '.$transaction['Transaction']['created']."\n";
				}
			}
			$count = $this->UserChecklist->find('count', array(
				'conditions' => array(
					'UserChecklist.user_id' => $user_id,
					'UserChecklist.name' => 'tour'
				)
			));
			if ($count == 0) {
				$transaction = $this->Transaction->find('first', array(
					'conditions' => array(
						'Transaction.user_id' => $user_id,
						'Transaction.type_id' => TRANSACTION_OTHER,
						'Transaction.linked_to_id' => '0',
						'Transaction.name' => 'Tour Bonus',
						'Transaction.deleted' => null,
					),
					'recursive' => -1,
					'fields' => array('id', 'created')
				));
				if ($transaction) {
					echo '[T] '.$transaction['Transaction']['created']."\n";
				}
			}
		}
	}
	
	public function set_survey_link_sort_order() {
		ini_set('memory_limit', '2048M');
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.status' => array(PROJECT_STATUS_STAGING, PROJECT_STATUS_SAMPLING, PROJECT_STATUS_OPEN)
			),
			'fields' => array('Project.id'),
			'recursive' => -1
		));
		foreach ($projects as $project) {
			$count = $this->SurveyLink->find('count', array(
				'conditions' => array(
					'SurveyLink.survey_id' => $project['Project']['id']
				)
			));
			$inactive_count = $this->SurveyLink->find('count', array(
				'conditions' => array(
					'SurveyLink.survey_id' => $project['Project']['id'],
					'SurveyLink.active' => false
				)
			));
			if ($count > 150 && $inactive_count == 0) {
				echo $project['Project']['id'].' '.$count.' '.$inactive_count."\n";
				$survey_links = $this->SurveyLink->find('all', array(
					'conditions' => array(
						'SurveyLink.survey_id' => $project['Project']['id']
					)
				));
				$i = 0;
				foreach ($survey_links as $survey_link) {
					$i++;
					$this->SurveyLink->create();
					$this->SurveyLink->save(array('SurveyLink' => array(
						'id' => $survey_link['SurveyLink'],
						'active' => $i < 150
					)), true, array('active'));
					echo '.';
				}
				echo "\n";
			}
		}
	}
	
	public function toluna_masks() {
		$toluna_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'toluna'
			)
		));
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.group_id' => $toluna_group['Group']['id']
			)
		));
		foreach ($projects as $project) {
			list($toluna_id, $nothing) = explode('-', $project['Project']['prj_name']);
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'mask' => $toluna_id,
				'modified' => false
			)), true, array('mask')); 
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'project.updated',
				'description' => 'Mask updated from "' . $project['Project']['mask'] . '" to "' . $toluna_id . '"',
			)));
			
			echo $project['Project']['id']."\n";
		}
	}
	public function set_fulcrum_timestamps() {
		$fed_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'fulcrum'
			)
		));
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.group_id' => $fed_group['Group']['id'],
				'Project.status' => array(PROJECT_STATUS_SAMPLING)
			),
			'fields' => array('id'),
			'recursive' => -1
		));
		
		foreach ($projects as $project) {
			$survey_users = $this->SurveyUser->find('all', array(
				'conditions' => array(
					'SurveyUser.survey_id' => $project['Project']['id']
				),
				'recursive' => -1
			));
			if (!$survey_users) {
				continue;
			}
			foreach ($survey_users as $survey_user) {
				$user = $this->User->find('first', array(
					'recursive' => -1,
					'fields' => array('id', 'fulcrum'),
					'conditions' => array(
						'User.id' => $survey_user['SurveyUser']['user_id'],
						'User.deleted' => false
					)
				));
				if (empty($user['User']['fulcrum']) || $user['User']['fulcrum'] < $survey_user['SurveyUser']['created']) {
					$this->User->create();
					$this->User->save(array('User' => array(
						'id' => $user['User']['id'],
						'fulcrum' => $survey_user['SurveyUser']['created']
					)), array(
						'fieldList' => array('fulcrum'),
						'callbacks' => false,
						'validate' => false
					));
					echo $user['User']['id']."\n";
				}
				echo '.';
			}
		}
	}
	
	public function set_lois_for_fulcrum() {
		$fed_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'fulcrum'
			)
		));
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.group_id' => $fed_group['Group']['id'],
				'Project.status' => array(PROJECT_STATUS_SAMPLING, PROJECT_STATUS_STAGING, PROJECT_STATUS_OPEN)
			)
		));
		foreach ($projects as $project) {
			if (empty($project['Project']['est_length']) && !empty($project['Survey']['id'])) {
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'est_length' => 15
				)), true, array('est_length'));
				
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'project.updated',
					'description' => 'Est length and survey length updated to 15',
				)));
				
				echo $project['Project']['id']."\n";
			}
		}
	}
	
	public function unreject_transactions() {
		if (!isset($this->args[0])) {
			return false;
		}
		$transactions = $this->Transaction->find('all', array(
			'conditions' => array(
				'Transaction.linked_to_id' => $this->args[0],
				'Transaction.status' => TRANSACTION_REJECTED,
				'Transaction.type_id' => TRANSACTION_SURVEY,
				'Transaction.deleted' => null,
			)
		));
		echo "total: ".count($transactions)."\n";
		$i = 0;
		foreach ($transactions as $transaction) {
			$this->Transaction->unreject($transaction);
			echo $i.':' .$transaction['Transaction']['id']."\n";
			$i++;
		}
	}
	
	public function survey_start_date() {
		$surveys = $this->Survey->find('all', array(
			'fields' => array('id', 'created', 'modified'),
			'conditions' => array(
				'Survey.started is null',
				'Survey.active' => true,
			),
			'recursive' => -1
		));
		foreach ($surveys as $survey) {
			$survey_visit = $this->SurveyVisit->find('first', array(
				'conditions' => array(
					'SurveyVisit.survey_id' => $survey['Survey']['id']
				),
				'fields' => array('id', 'created'),
				'order' => 'SurveyVisit.id ASC'
			));
			if (!$survey_visit) {
				continue;
			}
			$this->Survey->create();
			$this->Survey->save(array(
				'id' => $survey['Survey']['id'],
				'started' => $survey_visit['SurveyVisit']['created'],
				'modified' => false
			), true, array('started'));
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $survey['Survey']['id'],
				'type' => 'project.updated',
				'description' => 'Started updated to "' . $survey_visit['SurveyVisit']['created'] . '"',
			)));
			
			echo $survey['Survey']['id']."\n";
		}
	}
	
	public function withdrawal_balances() {
		$transactions = $this->Transaction->find('all', array(
			'conditions' => array(
				'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
				'Transaction.status' => TRANSACTION_PENDING,
				'Transaction.deleted' => null,
			)
		));
		foreach ($transactions as $transaction) {
			$this->User->rebuildBalances($transaction);
		}
		echo count($transactions); 
	}
	
	// 15894 14780
	public function fix_router_projects() {
		if (!isset($this->args[0])) {
			return false; 
		}
		$transactions = $this->Transaction->find('all', array(
			'conditions' => array(
				'Transaction.linked_to_id' => $this->args[0],
				'Transaction.type_id' => TRANSACTION_SURVEY,
				'Transaction.status' => TRANSACTION_REJECTED,
				'Transaction.deleted' => null,
			)
		));
		foreach ($transactions as $transaction) {
			$router_log = $this->RouterLog->find('first', array(
				'conditions' => array(
					'RouterLog.source' => 'p2s',
					'RouterLog.user_id' => $transaction['Transaction']['user_id'],
					'RouterLog.created' => $transaction['Transaction']['created']
				)
			));
			if (true || $router_log) {
				$this->Transaction->create();
				$this->Transaction->save(array('Transaction' => array(
					'id' => $transaction['Transaction']['id'],
					'paid' => true,
					'status' => TRANSACTION_APPROVED
				)), true, array('paid', 'status'));
				
				$count = $this->Transaction->find('count', array(
					'conditions' => array(
						'Transaction.user_id' => $transaction['Transaction']['user_id'],
						'Transaction.status' => TRANSACTION_REJECTED,
						'Transaction.type_id' => TRANSACTION_SURVEY,
						'Transaction.deleted' => null,
					)
				));
				
				$this->User->create();
				$this->User->save(array('User' => array(
					'id' => $transaction['Transaction']['user_id'],
					'rejected_transactions' => $count
				)), array(
					'callbacks' => false,
					'validate' => false,
					'fieldList' => array('rejected_transactions')
				));
			
				$this->User->create();
				$this->User->rebuildBalances($transaction);
				$transaction = $this->Transaction->findById($transaction['Transaction']['id']);
				echo 'Fixed '.$transaction['Transaction']['id']."\n";
			}
		}
	}
	public function canadian_postal_code() {
		$profiles = $this->QueryProfile->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'QueryProfile.country' => 'CA'
			),
			'fields' => array('id', 'postal_code', 'user_id', 'country')
		));
		foreach ($profiles as $profile) {
			if (strpos($profile['QueryProfile']['postal_code'], ' ') === false) {
				$matched = preg_match("/^[ABCEGHJKLMNPRSTVXY]\d[ABCEGHJKLMNPRSTVWXYZ] ?\d[ABCEGHJKLMNPRSTVWXYZ]\d$/", $profile['QueryProfile']['postal_code']);
				if (!$matched) {
					continue;
				}
				$new_postal = substr($profile['QueryProfile']['postal_code'], 0, 3).' '.substr($profile['QueryProfile']['postal_code'], 3, 3);
				echo $profile['QueryProfile']['postal_code'].' '.$new_postal."\n";
				$this->QueryProfile->create();
				$this->QueryProfile->save(array('QueryProfile' => array(
					'id' => $profile['QueryProfile']['id'],
					'postal_code' => $new_postal,
					'modified' => false
				)), true, array('postal_code', 'modified'));
			}
		}
	}
	
	public function missing_registration_payouts() {
		ini_set('memory_limit', '1024M');
		$last_user_id = 0;
		$total = 0;
		$yes = $no = 0;
		while (true) {
			$users = $this->User->find('all', array(
				'conditions' => array(
					'User.active' => true,
					'User.deleted' => false,
					'User.created >=' => '2014-12-01',
					'User.extended_registration' => true,
					'User.id >' => $last_user_id
				),
				'recursive' => -1,
				'fields' => array('id', 'balance', 'created', 'active'),
				'limit' => '2000',
				'order' => 'User.id asc'
			));
			if (!$users) {
				break;
			}
			foreach ($users as $user) {
				$last_user_id = $user['User']['id'];
				$count = $this->Transaction->find('count', array(
					'recursive' => -1,
					'conditions' => array(
						'Transaction.type_id' => TRANSACTION_PROFILE,
						'Transaction.linked_to_id' => '0',
						'Transaction.user_id' => $user['User']['id'],
						'Transaction.amount' => '200',
						'Transaction.paid' => true,
						'Transaction.status' => TRANSACTION_APPROVED,
						'Transaction.deleted' => null,
					)
				));
				if ($count == 0) {					
					$this->Transaction->create();
					$this->Transaction->save(array('Transaction' => array(
						'type_id' => TRANSACTION_PROFILE,
						'linked_to_id' => '0', // distinguishes this from user profile surveys
						'user_id' => $user['User']['id'],
						'amount' => 200,
						'paid' => true,
						'name' => 'Registration Complete!',
						'status' => TRANSACTION_APPROVED,
						'executed' => date(DB_DATETIME)
					)));
				}
				else {
					$yes++;
				}
			}
			$total = $total + count($users);
		}
		echo "---\n";
		echo $total."\n";
		echo 'Y: '.$yes."\n";
		echo 'N: '.$no."\n";
		/*
		$this->Transaction->create();
		$this->Transaction->save(array('Transaction' => array(
			'type_id' => TRANSACTION_PROFILE,
			'linked_to_id' => '0', // distinguishes this from user profile surveys
			'user_id' => $user['User']['id'],
			'amount' => 200,
			'paid' => true,
			'name' => 'Registration Complete!',
			'status' => TRANSACTION_APPROVED,
			'executed' => date(DB_DATETIME)
		))); */
	}
	
	public function query_histories() {
		ini_set('memory_limit', '2048M');
	
		while (true) {
			echo "---\n";
			$survey_users = $this->SurveyUser->find('all', array(
				'recursive' => -1,
				'fields' => array('id', 'query_history_id'),
				'conditions' => array(
					'SurveyUser.query_history_id is not null'
				),
				'limit' => 10000
			));
			if (!$survey_users) {
				break;
			}
			foreach ($survey_users as $survey_user) {
				$this->SurveyUserQuery->create();
				$this->SurveyUserQuery->save(array('SurveyUserQuery' => array(
					'survey_user_id' => $survey_user['SurveyUser']['id'],
					'query_history_id' => $survey_user['SurveyUser']['query_history_id']
				)));
				$this->SurveyUser->create();
				$this->SurveyUser->save(array('SurveyUser' => array(
					'id' => $survey_user['SurveyUser']['id'],
					'query_history_id' => null
				)), true, array('query_history_id'));
				echo '.'; 		
			}
		}
	}
	
	public function set_completed_transactions() {
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.status' => array(PROJECT_STATUS_CLOSED, PROJECT_STATUS_INVOICED),
				'Project.complete_client_report' => false
			),
			'fields' => array('id'),
			'recursive' => -1
		));
		foreach ($projects as $project) {
			$count = $this->SurveyComplete->find('count', array(
				'conditions' => array(
					'SurveyComplete.survey_id' => $project['Project']['id']
				)
			));
			if ($count > 0) {
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'complete_client_report' => true
				)), true, array('complete_client_report'));
				
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'project.updated',
					'description' => 'Complete client report set to true',
				)));
				
		//		echo 'y';
			}
			else {
		//		echo 'n';
			}
		}
		echo "\n";
		
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.status' => array(PROJECT_STATUS_CLOSED, PROJECT_STATUS_INVOICED),
				'Project.complete_client_report' => true,
				'Project.complete_transactions' => false
			),
			'fields' => array('Project.id', 'Survey.ended')
		));
		foreach ($projects as $project) {
			if (strtotime($project['Survey']['ended']) >= time() - (86400 * 14)) {
				continue;
			}
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'complete_transactions' => true
			)), true, array('complete_transactions'));
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'project.updated',
				'description' => 'Complete transactions set to true',
			)));
		}
	}
	
	public function quickbook_clients() {
		
		App::import('Model', 'Site');
		$this->Site = new Site;
		$site = $this->Site->find('first', array(
			'conditions' => array(
				'Site.path_name' => QUICKBOOK_API_PATH_NAME
			)
		));
		if ($site) {
			require_once(APP.'Vendor/Quickbook/config.php');
			App::import('Vendor', 'QuickbookServiceContext', array('file' => 'Quickbook/Core/ServiceContext.php'));
			App::import('Vendor', 'QuickbookDataService', array('file' => 'Quickbook/DataService/DataService.php'));
			App::import('Vendor', 'QuickbookPlatformService', array('file' => 'Quickbook/PlatformService/PlatformService.php'));
			App::import('Vendor', 'QuickbookConfigurationManager', array('file' => 'Quickbook/Utility/Configuration/ConfigurationManager.php'));
			if (empty($site['Site']['oauth_tokens'])) {
				return;
			}
			//Specify QBO or QBD			
			$auth_settings = json_decode($site['Site']['oauth_tokens']);		
			$service_type = IntuitServicesType::QBO;
			// Prep Service Context
			$request_validator = new OAuthRequestValidator(
				$auth_settings->oauth_token,
				$auth_settings->oauth_token_secret,
				$site['Site']['api_key'],
				$site['Site']['api_secret']
			);
			$service_context = new ServiceContext($auth_settings->realmId, $service_type, $request_validator);
			$this->data_service = new DataService($service_context);
		}
		
		$clients = $this->Client->find('list', array(
			'fields' => array('id', 'client_name'),
			'conditions' => array(
				'quickbook_customer_id is null'
			)
		));
		$customers = $this->data_service->FindAll('customer');
		foreach ($customers as $customer) {
			$key = array_search($customer->DisplayName, $clients);
			if ($key !== false) {
				$this->Client->create();
				$this->Client->save(array('Client' => array(
					'id' => $key,
					'quickbook_customer_id' => $customer->Id
				)), true, array('quickbook_customer_id'));
				unset($clients[$key]);
			}
		}
	}
	
	public function epc() {
		$projects = $this->Project->find('all', array(
			'fields' => array('bid_ir', 'client_rate', 'id', 'epc'),
			'recursive' => -1
		)); 
		foreach ($projects as $project) {
			if (empty($project['Project']['bid_ir']) || empty($project['Project']['client_rate'])) {
				continue;
			}
			$epc = round($project['Project']['bid_ir'] / 100 * $project['Project']['client_rate'] * 100);
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'epc' => $epc
			)), true, array('epc')); 
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'project.updated',
				'description' => 'Epc updated from "' . $project['Project']['epc'] . '" to "' . $epc . '"',
			)));
		}
	}
	
	public function populate_sources() {
		$user_sources = unserialize(USER_SOURCES);
		foreach ($user_sources as $source => $name) {
			$source = $this->Source->find('first', array(
				'conditions' => array(
					'Source.source' => $source
				)
			));
			if ($source) {
				continue;
			}
			$this->Source->create();
			$this->Source->save(array('Source' => array(
				'source' => $source,
				'name' => $name
			)));
		}
	}
	
	public function tour_points() {
		$transactions = $this->Transaction->find('all', array(
			'conditions' => array(				
				'Transaction.type_id' => TRANSACTION_OTHER,
				'Transaction.linked_to_id' => '0', 
				'Transaction.amount' => 10,
				'Transaction.paid' => false,
				'Transaction.name' => 'Tour Bonus',
				'status' => TRANSACTION_APPROVED,
				'Transaction.deleted' => null,
			)
		));
		foreach ($transactions as $transaction) {
			$this->Transaction->create();
			$this->Transaction->approve($transaction);
			$this->User->rebuildBalances($transaction);
		}
	}
	
	public function populate_tour_checklist() {
		ini_set('memory_limit', '2048M');
		$users = $this->User->find('all', array(
			'fields' => array('id')
		));
		foreach ($users as $user) {
			$this->UserChecklist->create();
			$this->UserChecklist->save(array('UserChecklist' => array(
				'user_id' => $user['User']['id'],
				'name' => 'tour'
			)));
		}
	}
	
	public function set_extended_registrations() {
		ini_set('memory_limit', '2048M');
		$query_profiles = $this->QueryProfile->find('all', array(
			'fields' => array(
				'QueryProfile.user_id',
				'User.id',
				'User.extended_registration'
			),
			'joins' => array(
    		    array(
		            'alias' => 'User',
		            'table' => 'users',
					'type' => 'INNER',
		            'conditions' => array(
						'QueryProfile.user_id = User.id',
						'User.extended_registration' => '0',
					)
		        )
			),
			'conditions' => array(
				'QueryProfile.postal_code is not null',
			),
			'recursive' => -1
		));
		echo count($query_profiles)."\n";
		sleep(3);
		foreach ($query_profiles as $query_profile) {
			$this->User->create();
			$this->User->save(array('User' => array(
				'id' => $query_profile['User']['id'],
				'extended_registration' => '1'
			)), array(
				'modified' => false,
				'fieldList' => array('extended_registration')
			));
			echo $query_profile['User']['id']."\n";
		}
	}
	
	public function cleanup_bad_cint_projects() {
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'cint'
			),
			'recursive' => -1
		));
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'Project.date_created >=' => '2016-11-15 18:00:00', 
				'Project.date_created <=' => '2016-11-16 22:05:23',
				'SurveyVisitCache.click' => '0',
			)
		));
		foreach ($projects as $project) {
			$cint_survey = $this->CintSurvey->find('count', array(
				'conditions' => array(
					'CintSurvey.survey_id' => $project['Project']['id']
				)
			));
			if (!$cint_survey) {
				$this->Project->delete($project['Project']['id']);
				$this->out($project['Project']['id']); 
			}
		}
	}
	
	
	public function cleanup_bad_fulcrum_projects() {
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'fulcrum'
			),
			'recursive' => -1
		));
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'Project.date_created >=' => '2016-11-15 21:19:50', 
				'Project.date_created <=' => '2016-11-16 12:45:52',
				'SurveyVisitCache.click' => '0',
				'Project.active' => false
			)
		));
		foreach ($projects as $project) {
			$fed_survey = $this->FedSurvey->find('count', array(
				'conditions' => array(
					'FedSurvey.survey_id' => $project['Project']['id']
				)
			));
			if (!$fed_survey) {
				$this->Project->delete($project['Project']['id']);
				$this->out($project['Project']['id']); 
			}
		}
	}
	
	public function cleanup_bad_fed_survey() {
		$fed_client_id = 85;
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.client_id' => $fed_client_id
			)
		));
		
		foreach ($projects as $project) {
			if (empty($project['Survey']['id'])) {
				echo $project['Project']['id']."\n";
				$this->Project->delete($project['Project']['id']);
								
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'project.delete',
					'description' => 'Cleanup bad fed survey',
				)));
				
			}
		}
	}
	// arg 1: date
	// arg 2: filter by source
	public function link_user_acquisitions() {
		$conditions = array(
			'UserAcquisition.checked' => false,
			'UserAcquisition.created like' => $this->args[0].'%',
			'UserAcquisition.pixel_fired' => false,
			'UserAcquisition.user_id' => '0'
		);
		if (isset($this->args[1])) {
			$conditions['UserAcquisition.source'] = $this->args[1];
		}
		$user_acquisitions = $this->UserAcquisition->find('all', array(
			'conditions' => $conditions
		));
		foreach ($user_acquisitions as $user_acquisition) {
			$user_ip = $this->UserIp->find('first', array(
				'conditions' => array(
					'UserIp.ip_address' => $user_acquisition['UserAcquisition']['ip'],
				//	'UserIp.type' => 'registration',
					'UserIp.created >=' => $user_acquisition['UserAcquisition']['created']
				)
			));
			if ($user_ip) {
				$user = $this->User->find('first', array(
					'conditions' => array(
						'User.id' => $user_ip['UserIp']['user_id'],
						'User.origin !=' => $user_acquisition['UserAcquisition']['source'],
						'User.deleted' => false
					),
					'fields' => array('id', 'created', 'origin', 'pub_id')
				));
				if ($user) {
					print_r($user);
					print_r($user_ip);
					print_r($user_acquisition);
					echo "----\n\n\n";
				}
			}
		}
	}
	
	public function populate_fullname() {
		ini_set('memory_limit', '1024M');
		$users = $this->User->find('all', array(
			'fields' => array('id', 'firstname', 'lastname', 'fullname'),
			'conditions' => array(
				'User.fullname is null'
			),
			'recursive' => -1
		));
		foreach ($users as $user) {
			$this->User->create();
			$this->User->save(array('User' => array(
				'id' => $user['User']['id'],
				'fullname' => $user['User']['firstname'].' '.$user['User']['lastname']
			)), true, array('fullname')); 
			echo $user['User']['id']."\n";
		}
	}
	
	public function populate_pubs() { 
		$users = $this->User->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'User.pub_id is null',
				'User.origin' => 'compete:roi'
			),
			'joins' => array(
    		    array(
		            'alias' => 'UserAcquisition',
		            'table' => 'user_acquisitions',
		            'conditions' => array(
						'UserAcquisition.user_id = User.id',
					)
		        )
			),
			'order' => 'User.id DESC',
			'fields' => array('User.id', 'User.pub_id', 'User.origin', 'UserAcquisition.id', 'UserAcquisition.params', 'UserAcquisition.source')
		));
		foreach ($users as $user) {
			if (empty($user['UserAcquisition']['id'])) {
				continue;
			}
			$user_acquisition = $user;
			$user_acquisition['UserAcquisition']['params'] = unserialize($user['UserAcquisition']['params']);
			$pub_id = null;
			if (!empty($user_acquisition['UserAcquisition']['params'])) {
				$params = $user_acquisition['UserAcquisition']['params']; 
				if (!empty($params)) {
					if (strpos($user_acquisition['UserAcquisition']['source'], ':adp') !== false) {
						if (isset($params['key1']) && !empty($params['key1'])) {
							$pub_id = $params['key1'];
						}
					}
					elseif (strpos($user_acquisition['UserAcquisition']['source'], ':pt') !== false) {
						if (isset($params['sid']) && !empty($params['sid'])) {
							$pub_id = $params['sid'];
						}
					}
					elseif (strpos($user_acquisition['UserAcquisition']['source'], ':fbext') !== false || strpos($user_acquisition['UserAcquisition']['source'], ':fbint') !== false || strpos($user_acquisition['UserAcquisition']['source'], '-fb-') !== false) {
						if (isset($params['utm_content']) && !empty($params['utm_content'])) {
							$pub_id = $params['utm_content'];
						}
					}
					elseif (strpos($user_acquisition['UserAcquisition']['source'], ':mvf')) { 
						if (isset($params['did']) && !empty($params['did'])) {
							$pub_id = $params['did'];
						}
					}
					elseif ($user_acquisition['UserAcquisition']['source'] == 'compete:roi') {
						if (isset($params['affid']) && !empty($params['affid'])) {
							$pub_id = $params['affid'];
						}
					}
				}
				if (isset($pub_id) && !empty($pub_id)) {					
					$this->User->create();
					$this->User->save(array('User' => array(
						'id' => $user['User']['id'],
						'pub_id' => $pub_id
					)), true, array('pub_id'));
					echo '.'; flush();
				}
			}
		}
	}
	
	function fix_revenue_items() {
		App::import('Model', 'RevenueItem');
		$this->RevenueItem = new RevenueItem;
		
		$items = $this->RevenueItem->find('all', array(
			'conditions' => array(
				'mixpanel' => true
			)
		));
		foreach ($items as $item) {
			$this->Transaction->create();
			$this->Transaction->save(array('Transaction' => array(
				'id' => $item['RevenueItem']['transaction_id'],
				'mixpanel' => true
			)), true, array('mixpanel'));
		}
	}
	
	// manually pay out all users who took a poll but didn't get it
	function poll_reward() {
		$models_to_import = array('PollUserAnswer', 'Poll');
		foreach ($models_to_import as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		if (isset($this->args[0])) {
			$poll = $this->Poll->find('first', array(
				'conditions' => array(
					'Poll.id' => $this->args[0]
				)
			));
			if ($poll) {
				$users = $this->PollUserAnswer->find('all', array(
					'fields' => array('id', 'user_id'),
					'conditions' => array(
						'PollUserAnswer.poll_id' => $poll['Poll']['id']
					),
					'recursive' => -1
				));
				echo count($users)."\n";
				foreach ($users as $user) {
					$count = $this->Transaction->find('count', array(
						'conditions' => array(
							'Transaction.type_id' => TRANSACTION_POLL,
							'Transaction.linked_to_id' => $poll['Poll']['id'],
							'Transaction.user_id' => $user['PollUserAnswer']['user_id'],
							'Transaction.deleted' => null,
						),
						'recursive' => -1
					));
					if ($count == 0) {		
						$this->Transaction->create();
						$this->Transaction->save(array('Transaction' => array(
							'type_id' => TRANSACTION_POLL,
							'linked_to_id' => $poll['Poll']['id'],
							'linked_to_name' => $poll['Poll']['poll_question'],
							'user_id' => $user['PollUserAnswer']['user_id'],
							'amount' => $poll['Poll']['award'],
							'paid' => true,
							'name' => 'Poll Completion - '.$poll['Poll']['poll_question'],
							'status' => TRANSACTION_APPROVED,
							'executed' => date(DB_DATETIME)
						)));
						echo $user['PollUserAnswer']['user_id']."\n";
						flush();
					}
				}
			}
		}
	}
	
	function poll_204() {
		$transactions = $this->Transaction->find('all', array(
			'recursive' => -1,
			'conditions' => array(				
				'Transaction.type_id' => TRANSACTION_POLL,
				'Transaction.linked_to_id' => 204,
				'Transaction.paid' => true,
				'Transaction.amount' => 5,
				'Transaction.status' => TRANSACTION_APPROVED,
				'Transaction.deleted' => null,
			)
		));
		foreach ($transactions as $transaction) {
			$this->Transaction->create();
			$this->Transaction->save(array('Transaction' => array(
				'id' => $transaction['Transaction']['id'],
				'amount' => 10
			)), true, array('amount'));
			
			$user = $this->User->find('first', array(
				'conditions' => array(
					'User.id' => $transaction['Transaction']['user_id']
				),
				'recursive' => -1
			));
			$this->User->create();
			$this->User->rebuildBalances($user);
			echo 'fixed txn '.$transaction['Transaction']['id'].' and user '.$user['User']['id']."\n";
		}
	}
	
	// convert old transaction types to new ones
	function dwolla_transactions() {
		// handle the 50 point bonus
		$transactions = $this->Transaction->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'Transaction.amount' => '50',
				'Transaction.type_id' => TRANSACTION_OTHER,
				'Transaction.name' => 'Dwolla Bonus',
				'Transaction.deleted' => null,
			)
		));
		if ($transactions) {
			foreach ($transactions as $transaction) {
				$this->Transaction->create();
				$this->Transaction->save(array('Transaction' => array(
					'id' => $transaction['Transaction']['id'],
					'type_id' => TRANSACTION_DWOLLA
				)), array(
					'fieldList' => array('type_id'),
					'callbacks' => false
				));
				echo 'Converted '.$transaction['Transaction']['user_id']."\n";
			}
		}
		
		// find dwolla users w/o transactions yet, since we were waiting for first payout
		$methods = $this->PaymentMethod->find('all', array(
			'conditions' => array(
				'PaymentMethod.payment_method' => 'dwolla_id',
				'PaymentMethod.status' => 'active'
			)
		));
		foreach ($methods as $method) {
			$count = $this->Transaction->find('count', array(
				'recursive' => -1,
				'conditions' => array(
					'Transaction.user_id' => $method['PaymentMethod']['user_id'],
					'Transaction.type_id' => TRANSACTION_DWOLLA,
					'Transaction.deleted' => null,
				)
			));
			if ($count == 0) {				
				$this->Transaction->create();
				$this->Transaction->save(array('Transaction' => array(
					'type_id' => TRANSACTION_DWOLLA,
					'linked_to_id' => '0', 
					'user_id' => $method['PaymentMethod']['user_id'],
					'amount' => 50,
					'paid' => false,
					'name' => 'Dwolla Bonus',
					'status' => TRANSACTION_PENDING,
					'executed' => date(DB_DATETIME)
				)));
				echo 'Created '.$method['PaymentMethod']['user_id']."\n";
			}
		}
	}
	
	function statistics() {
		if (!isset($this->args[0])) {
			return;
		}
		
		$models_to_import = array('SurveyVisit', 'SurveyPartner', 'SurveyVisitCache');
		foreach ($models_to_import as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		$survey_id = $this->args[0];
		if (isset($this->args[1])) {
			$partners = array($this->args[1]);
		}
		else {
			$partners = $this->SurveyPartner->find('list', array(
				'fields' => array('id', 'partner_id'),
				'conditions' => array(
					'SurveyPartner.survey_id' => $survey_id
				)
			));
		}
		
		$click_count = $this->SurveyVisit->find('count', array(
			'conditions' => array(
				'SurveyVisit.survey_id' => $survey_id,
				'SurveyVisit.type' => SURVEY_CLICK
			)
		));
		
		$complete_count = $this->SurveyVisit->find('count', array(
			'conditions' => array(
				'SurveyVisit.survey_id' => $survey_id,
				'SurveyVisit.type' => SURVEY_COMPLETED
			)
		));
		
		$nq_count = $this->SurveyVisit->find('count', array(
			'conditions' => array(
				'SurveyVisit.survey_id' => $survey_id,
				'SurveyVisit.type' => SURVEY_NQ
			)
		));
		
		$oq_count = $this->SurveyVisit->find('count', array(
			'conditions' => array(
				'SurveyVisit.survey_id' => $survey_id,
				'SurveyVisit.type' => SURVEY_OVERQUOTA
			)
		));
		
		$survey_visit_cache = $this->SurveyVisitCache->find('first', array(
			'conditions' => array(
				'SurveyVisitCache.survey_id' => $survey_id
			)
		));
		if ($survey_visit_cache) {
			$this->SurveyVisitCache->create();
			$this->SurveyVisitCache->save(array('SurveyVisitCache' => array(
				'id' => $survey_visit_cache['SurveyVisitCache']['id'],
				'click' => $click_count,
				'complete' => $complete_count,
				'nq' => $nq_count,
				'oq' => $oq_count
			)), array(
				'callbacks' => false,
				'fieldList' => array('click', 'complete', 'nq', 'oq')
			));
			echo $this->SurveyVisitCache->getLastQuery()."\n";
		}
		
		if (!empty($partners)) {
			foreach ($partners as $partner_id) {				
				$click_count = $this->SurveyVisit->find('count', array(
					'conditions' => array(
						'SurveyVisit.survey_id' => $survey_id,
						'SurveyVisit.partner_id' => $partner_id,
						'SurveyVisit.type' => SURVEY_CLICK
					)
				));
		
				$complete_count = $this->SurveyVisit->find('count', array(
					'conditions' => array(
						'SurveyVisit.survey_id' => $survey_id,
						'SurveyVisit.partner_id' => $partner_id,
						'SurveyVisit.type' => SURVEY_COMPLETED
					)
				));
		
				$nq_count = $this->SurveyVisit->find('count', array(
					'conditions' => array(
						'SurveyVisit.survey_id' => $survey_id,
						'SurveyVisit.partner_id' => $partner_id,
						'SurveyVisit.type' => SURVEY_NQ
					)
				));
				
				$oq_count = $this->SurveyVisit->find('count', array(
					'conditions' => array(
						'SurveyVisit.survey_id' => $survey_id,
						'SurveyVisit.partner_id' => $partner_id,
						'SurveyVisit.type' => SURVEY_OVERQUOTA
					)
				));
				
				$survey_partner = $this->SurveyPartner->find('first', array(
					'conditions' => array(
						'SurveyPartner.survey_id' => $survey_id,
						'SurveyPartner.partner_id' => $partner_id
					)
				)); 
				if ($survey_partner) {
					$survey_partner_data = array('SurveyPartner' => array(
						'id' => $survey_partner['SurveyPartner']['id'],
						'clicks' => $click_count,
						'completes' => $complete_count,
						'nqs' => $nq_count,
						'oqs' => $oq_count 
					));
					
					$this->SurveyPartner->create();
					$this->SurveyPartner->save($survey_partner_data, array(
						'callbacks' => false,
						'fieldList' => array('clicks', 'completes', 'nqs', 'oqs')
					));
					
					$survey_partner_logs = Utils::get_field_diffs($survey_partner_data, $survey_partner);
					$this->ProjectLog->create();
					$this->ProjectLog->save(array('ProjectLog' => array(
						'project_id' => $survey_partner['SurveyPartner']['survey_id'],
						'type' => 'survey_partner.updated',
						'description' => $survey_partner_logs,
					)));
					
					echo $this->SurveyPartner->getLastQuery()."\n";
				}
				
			}
		}
		
	}
	
	function completes_7046() {
		App::import('Model', 'SurveyLink');
		$this->SurveyLink = new SurveyLink;
		App::import('Model', 'SurveyUserVisit');
		$this->SurveyUserVisit = new SurveyUserVisit;
		App::import('Model', 'SurveyVisitCache');
		$this->SurveyVisitCache = new SurveyVisitCache;
		
		$survey_links = $this->SurveyLink->find('all', array(
			'fields' => array('link'),
			'conditions' => array(
				'SurveyLink.survey_id' => 7046
			)
		));
		foreach ($survey_links as $link) {
			if (preg_match('/id=(.*)/', $link['SurveyLink']['link'], $match)) {
				$hash = $match[1];
				$survey_id = (int) substr($hash, 0, 5); 
				
				$survey_visit = $this->SurveyVisit->find('first', array(
					'conditions' => array(
						'SurveyVisit.hash' => $hash,
						'SurveyVisit.survey_id <>' => 7046,
						'SurveyVisit.type' => array(SURVEY_COMPLETED, SURVEY_DUPE),
						'SurveyVisit.created >=' => '2014-06-04'
					)
				));
				if ($survey_visit && $survey_visit['SurveyVisit']['survey_id'] != 7406) {
					$original = $this->SurveyVisit->find('first', array(
						'conditions' => array(
							'SurveyVisit.survey_id' => 7046,
							'SurveyVisit.ip' => $survey_visit['SurveyVisit']['ip']
						)
					));
					if ($original && $original['SurveyVisit']['partner_id'] == 43 /* mintvine */) {
						$partner_user_ids = explode('-', $original['SurveyVisit']['partner_user_id']);
						$user_id = $partner_user_ids[1];
						$survey_user_visit = $this->SurveyUserVisit->find('first', array(
							'conditions' => array(
								'SurveyUserVisit.survey_id' => 7046,
								'SurveyUserVisit.user_id' => $user_id
							)
						));						
						if ($survey_user_visit && !$survey_user_visit['SurveyUserVisit']['redeemed'] && $survey_user_visit['SurveyUserVisit']['status'] == SURVEY_CLICK) {						
							// mark the visit as completed
							$this->SurveyUserVisit->create();
							$this->SurveyUserVisit->save(array('SurveyUserVisit' => array(
								'id' => $survey_user_visit['SurveyUserVisit']['id'],
								'redeemed' => true,
								'status' => SURVEY_COMPLETED
							)), true, array('redeemed', 'status'));
			
							$this->Transaction->create();
							$this->Transaction->save(array('Transaction' => array(
								'type_id' => TRANSACTION_SURVEY,
								'linked_to_id' => 7046,
								'user_id' => $user_id,
								'amount' => 100,
								'paid' => false,
								'name' => 'Survey Completion - Follow Up Study ... 100 Points!',
								'status' => TRANSACTION_PENDING,
								'executed' => date(DB_DATETIME)
							)));
						}
						
						// match original entry point to exit
						$this->SurveyVisit->create();
						$this->SurveyVisit->save(array('SurveyVisit' => array(
							'id' => $original['SurveyVisit']['id'],
							'result' => SURVEY_COMPLETED,
							'result_id' => $survey_visit['SurveyVisit']['id']
						)), true, array('result', 'result_id'));
						
						// fix complete result
						$this->SurveyVisit->create();
						$this->SurveyVisit->save(array('SurveyVisit' => array(
							'id' => $survey_visit['SurveyVisit']['id'],
							'survey_id' => 7046,
							'hash' => $original['SurveyVisit']['hash'],
							'type' => SURVEY_COMPLETED
						)), true, array('survey_id', 'hash', 'type'));
						
					}
				}
			}
		}
		
		$count = $this->SurveyVisit->find('count', array(
			'conditions' => array(
				'SurveyVisit.survey_id' => 7046,
				'SurveyVisit.type' => SURVEY_COMPLETED
			)
		));
		$survey_visit_cache = $this->SurveyVisitCache->find('first', array(
			'conditions' => array(
				'SurveyVisitCache.survey_id' => 7046
			)
		));
		$this->SurveyVisitCache->create();
		$this->SurveyVisitCache->save(array('SurveyVisitCache' => array(
			'id' => $survey_visit_cache['SurveyVisitCache']['id'],
			'complete' => $count
		)), true, array('complete'));
	}
	
		
	function project_4807() {
		$transactions = $this->Transaction->find('all', array(
			'conditions' => array(
				'Transaction.type_id' => TRANSACTION_SURVEY,
				'Transaction.linked_to_id' => 4807,
				'Transaction.amount' => 2,
				'Transaction.deleted' => null,
			)
		));
		foreach ($transactions as $transaction) {
			$this->Transaction->create();
			$this->Transaction->save(array('Transaction' => array(
				'id' => $transaction['Transaction']['id'],
				'amount' => '150'
			)), true, array('amount'));
			$this->User->rebuildBalances($transaction);
			echo $transaction['User']['id']."\n";
			flush();
		}
	}
	
	function populate_survey_countries() {
		$projects = $this->Project->find('all', array(
			'recursive' => -1,
		));
		foreach ($projects as $project) {
			$this->Survey->SurveyCountry->create();
			$this->Survey->SurveyCountry->save(array('SurveyCountry' => array(
				'partner_id' => null,
				'survey_id' => $project['Project']['id'],
				'country' => 'US'
			)));
			$this->Survey->SurveyCountry->create();
			$this->Survey->SurveyCountry->save(array('SurveyCountry' => array(
				'partner_id' => null,
				'survey_id' => $project['Project']['id'],
				'country' => 'GB'
			)));
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'project.updated',
				'description' => 'Project country set to US and GB',
			)));
			
		}
	}
	
	function bonus_profiles() {
		ini_set('memory_limit', '1024M');
		
		$user_profiles = $this->UserProfile->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'UserProfile.profile_id' => '0',
				'UserProfile.status' => 'completed'
			)
		));
		foreach ($user_profiles as $user_profile) {
			$count = $this->Transaction->find('count', array(
				'recursive' => -1,
				'conditions' => array(
					'type_id' => TRANSACTION_PROFILE,
					'linked_to_id' => '1', // bonus profile
					'user_id' => $user_profile['UserProfile']['user_id'],
					'amount' => 50,
					'paid' => true,
					'deleted' => null,
				)
			));
			if ($count == 0) {
				print_r($user_profile);
			}
		}
	}
	
	function sids() {
		ini_set('memory_limit', '1024M');
		$models_to_import = array('UserAcquisition');
		foreach ($models_to_import as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		$user_acquisitions = $this->UserAcquisition->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'UserAcquisition.user_id >' => '0',
				'UserAcquisition.pixel_fired' => true,
				'UserAcquisition.source' => array('fb_lander_2_1:pt5', 'fb_lander_2_1:pt6')
			),
			'fields' => array('params', 'source', 'user_id')
		));
		foreach ($user_acquisitions as $user_acquisition) {
			$params = $user_acquisition['UserAcquisition']['params']; 
			$user = $this->User->find('first', array(
				'fields' => array('origin', 'pub_id', 'id'),
				'conditions' => array(
					'User.id' => $user_acquisition['UserAcquisition']['user_id']
				)
			));
			if (!$user) {
				continue;
			}
			$pub_id = null;
			if (strpos($user_acquisition['UserAcquisition']['source'], ':adp') !== false) {
				if (isset($params['key1'])) {
					$pub_id = $params['key1'];
				}
			}
			elseif (strpos($user_acquisition['UserAcquisition']['source'], ':pt') !== false) {
				if (isset($params['sid'])) {
					$pub_id = $params['sid'];
				}
			}
			if (empty($pub_id)) {
				continue;
			}
			if ($user['User']['origin'] != $user_acquisition['UserAcquisition']['source'] || $user['User']['pub_id'] != $pub_id) {
				if (!empty($user['User']['pub_id'])) {
					print_r($user);
					print_R($user_acquisition);
					exit();
				}
				else {
					echo 'update '.$user['User']['id']."\n";
					$this->User->create();
					$this->User->save(array('User' => array(
						'id' => $user['User']['id'],
						'pub_id' => $pub_id
					)), true, array('pub_id'));
				}
			}
		}
	}
	
	function populate_for_bonus_profile() {
		ini_set('memory_limit', '1024M');
		$models_to_import = array('UserProfile');
		foreach ($models_to_import as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		$transactions = $this->Transaction->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'Transaction.type_id' => TRANSACTION_PROFILE,
				'Transaction.linked_to_id' => array('0', '1'),
				'Transaction.amount <' => '200',
				'Transaction.deleted' => null,
			)
		));
		echo count($transactions);
		foreach ($transactions as $transaction) {
			$this->UserProfile->create();
			$this->UserProfile->save(array('UserProfile' => array(
				'profile_id' => '0',
				'user_id' => $transaction['Transaction']['user_id'],
				'status' => 'completed',
				'completed' => $transaction['Transaction']['created']
			)));
		}
		
	}
	
	function add_and_fix_query_profiles() {
		ini_set('memory_limit', '1024M');
		$models_to_import = array('QueryProfile', 'GeoZip');
		foreach ($models_to_import as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		$users = $this->User->find('all', array(
			'conditions' => array(
			),
			'fields' => array('User.id', 'User.date_of_birth', 'User.gender_id', 'User.country_id', 'User.state_id', 'User.zip', 'GeoState.*', 'GeoCountry.*'),
		));
		foreach ($users as $user) {
			if (empty($user['User']['id'])) {
				continue;
			}
			$query_profile = $this->QueryProfile->find('first', array(
				'fields' => array('id', 'gender', 'birthdate', 'country', 'state', 'postal_code'),
				'conditions' => array(
					'QueryProfile.user_id' => $user['User']['id']
				),
				'recursive' => -1
			));
			
			// set gender value
			$gender = null;
			if ($user['User']['gender_id'] == 1) {
				$gender = 'M';
			}
			elseif ($user['User']['gender_id'] == 2) {
				$gender = 'F';
			}
			
			// set birthdate value
			if (!empty($user['User']['date_of_birth']) && $user['User']['date_of_birth'] != '0000-00-00') {
				$birthdate = $user['User']['date_of_birth'];
			}
			else {
				$birthdate = null;
			}
			
			if (!$query_profile) {
				$save = array('QueryProfile' => array(
					'user_id' => $user['User']['id'],
					'gender' => $gender,
					'birthdate' => $birthdate,
					'country' => $user['GeoCountry']['ccode']
				)); 
				if ($user['User']['country_id'] == 230) {
					if (!empty($user['User']['zip'])) {
						$this->GeoZip->bindModel(array('belongsTo' => array('GeoState' => array(
							'className' => 'GeoState',
							'foreignKey' => 'state_id'
						))));
						$zip = $this->GeoZip->find('first', array(
							'conditions' => array(
								'GeoZip.zipcode' => $user['User']['zip'],
								'GeoZip.country_code' => 'US'
							)
						));
						if ($zip) {
							$save['QueryProfile']['postal_code'] = $zip['GeoZip']['zipcode'];
							$save['QueryProfile']['state'] = $zip['GeoState']['state_abbr'];
						}
					}
				}
				else {
					$save['QueryProfile']['state'] = null;
					$save['QueryProfile']['postal_code'] = $user['User']['zip'];
				}
				
				$this->QueryProfile->create();
				$this->QueryProfile->save($save, array(
					'callbacks' => false
				));
				echo $this->QueryProfile->getLastQuery()."\n";
			}
			elseif (empty($query_profile['QueryProfile']['postal_code'])) {
				if (!empty($query_profile['QueryProfile']['birthdate']) && !empty($query_profile['QueryProfile']['gender'])) {
					continue;
				}
				elseif ($user['User']['date_of_birth'] == '0000-00-00' && empty($user['User']['gender_id'])) {
					continue;
				}
				elseif ($query_profile['QueryProfile']['birthdate'] == $user['User']['date_of_birth'] && $gender == $query_profile['QueryProfile']['gender']) {
					continue;
				}
				else {
					if (empty($user['User']['zip'])) {
						continue;
					}
					$save = array('QueryProfile' => array());
					if (empty($query_profile['QueryProfile']['gender'])) {
						$save['QueryProfile']['gender'] = $gender;
					}
					if (empty($query_profile['QueryProfile']['birthdate']) && !empty($birthdate)) {
						$save['QueryProfile']['birthdate'] = $birthdate;
					}
					if (empty($query_profile['QueryProfile']['postal_code'])) {						
						if ($user['User']['country_id'] == 230) {
							if (!empty($user['User']['zip'])) {
								$this->GeoZip->bindModel(array('belongsTo' => array('GeoState' => array(
									'className' => 'GeoState',
									'foreignKey' => 'state_id'
								))));
								$zip = $this->GeoZip->find('first', array(
									'conditions' => array(
										'GeoZip.zipcode' => $user['User']['zip'],
										'GeoZip.country_code' => 'US'
									)
								));
								if ($zip) {
									$save['QueryProfile']['postal_code'] = $zip['GeoZip']['zipcode'];
									$save['QueryProfile']['state'] = $zip['GeoState']['state_abbr'];
								}
							}
						}
						else {
							$save['QueryProfile']['state'] = null;
							$save['QueryProfile']['postal_code'] = $user['User']['zip'];
						}
					}
					if (empty($save['QueryProfile'])) {
						continue;
					}
					$save['QueryProfile']['id'] = $query_profile['QueryProfile']['id'];
					$this->QueryProfile->save($save);
					echo $this->QueryProfile->getLastQuery()."\n";
				}
			}
		}
			
	}
	
	function missing_paypal() {
		$models_to_import = array('PaymentMethod');
		foreach ($models_to_import as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		$users = $this->User->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'User.id' => 101321,
				'User.paypal_email <>' => ''
			),
			'fields' => array('id', 'paypal_email'),
		));
		foreach ($users as $user) {
			$method = $this->PaymentMethod->find('first', array(
				'conditions' => array(
					'PaymentMethod.user_id' => $user['User']['id'],
					'PaymentMethod.payment_method' => 'paypal'
				)
			));
			if (!$method) {
				$this->PaymentMethod->create();
				if ($this->PaymentMethod->save(array('PaymentMethod' => array(
					'user_id' => $user['User']['id'],
					'payment_method' => 'paypal',
					'value' => $user['User']['paypal_email'],
					'status' => DB_ACTIVE
				)))) {
					echo '.'; flush();
				}
				else {
					echo '!'; flush();
				}
			}
		}
	}
	
	function inactive_paypal() {
		
		$models_to_import = array('PaymentMethod');
		foreach ($models_to_import as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		$methods = $this->PaymentMethod->find('all', array(
			'conditions' => array(
				'PaymentMethod.status' => DB_DEACTIVE
			)
		));
		foreach ($methods as $method) {
			$count = $this->PaymentMethod->find('count', array(
				'conditions' => array(
					'PaymentMethod.user_id' => $method['PaymentMethod']['user_id'],
					'PaymentMethod.status' => DB_ACTIVE
				)
			));
			if ($count == 0) {
				$this->PaymentMethod->create();
				$this->PaymentMethod->save(array('PaymentMethod' => array(
					'id' => $method['PaymentMethod']['id'],
					'status' => DB_ACTIVE
				)), array(
					'validate' => false,
					'callbacks' => false,
					'fieldList' => array('status')
				));
				echo '.'; flush();
			}
		}
	}
	
	// create statistic data for prescreeners
	function prescreener_data() {
		$models_to_import = array('Survey', 'PrescreenerStatistic');
		foreach ($models_to_import as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		$surveys = $this->Survey->find('all', array(
			'conditions' => array(
				'prescreen' => true
			),
			'fields' => array('id'),
			'recursive' => -1
		));
		foreach ($surveys as $survey) {
			$this->PrescreenerStatistic->create();
			$statistic = $this->PrescreenerStatistic->find('first', array(
				'conditions' => array(
					'PrescreenerStatistic.survey_id' => $survey['Survey']['id']
				)
			));
			if (!$statistic) {
				$this->PrescreenerStatistic->save(array('PrescreenerStatistic' => array(
					'survey_id' => $survey['Survey']['id']
				)));
			}
		}
	}
	
	// make sure the payout 50 is correct
	function user_checklist_bonus() {
		$users = $this->User->find('all', array(
			'recursive' => -1,
			'fields' => array('User.id'),
			'conditions' => array(
				'User.active' => true,
				'User.deleted' => false
			)
		));
		foreach ($users as $user) {
			$user_checklist = $this->UserChecklist->find('first', array(
				'conditions' => array(
					'UserChecklist.user_id' => $user['User']['id'],
					'UserChecklist.name' => 'profile'
				),
				'recursive' => -1
			));
			if ($user_checklist) {
				$count = $this->Transaction->find('count', array(
					'conditions' => array(
						'Transaction.user_id' => $user['User']['id'],
						'Transaction.type_id' => TRANSACTION_PROFILE,
						'Transaction.amount' => '50',
						'Transaction.name' => 'Extended Registration Completion',
						'Transaction.deleted' => null,
					),
					'recursive' => -1
				));
				if ($count > 0) {
					$this->UserChecklist->delete($user_checklist['UserChecklist']['id']);
					echo '.'; flush();
				}
			}
		}
	}
	
	// 4.1.2013 - multiple user_id - survey_id values detected.
	function multiple_survey_users() {
		$survey_user_visits = $this->SurveyUserVisit->find('all', array(
			'recursive' => -1,
			'fields' => array('id', 'user_id', 'survey_id', 'created', 'status', 'redeemed'),
			'conditions' => array(
				'SurveyUserVisit.id >' => 800000
			),
			'order' => 'SurveyUserVisit.id ASC'
		)); 
		$keys = array();
		foreach ($survey_user_visits as $survey_user_visit) {
			if (!array_key_exists($survey_user_visit['SurveyUserVisit']['user_id'], $keys)) {
				$keys[$survey_user_visit['SurveyUserVisit']['user_id']] = array();
			}
			if (!in_array($survey_user_visit['SurveyUserVisit']['survey_id'], $keys[$survey_user_visit['SurveyUserVisit']['user_id']])) {
				$keys[$survey_user_visit['SurveyUserVisit']['user_id']][$survey_user_visit['SurveyUserVisit']['id']] = $survey_user_visit['SurveyUserVisit']['survey_id'];
			}
			else {
				$key = array_search($survey_user_visit['SurveyUserVisit']['survey_id'], $keys[$survey_user_visit['SurveyUserVisit']['user_id']]);
				$old = $this->SurveyUserVisit->find('first', array(
					'recursive' => -1,
					'fields' => array('id', 'user_id', 'survey_id', 'created', 'status', 'redeemed'),
					'conditions' => array(
						'SurveyUserVisit.id' => $key
					)
				));
				if (!isset($old) || isset($survey_user_visit)) {
					continue;
				}
				if ($old['SurveyUserVisit']['status'] > $survey_user_visit['SurveyUserVisit']['status']) {
					$this->SurveyUserVisit->delete($survey_user_visit['SurveyUserVisit']['id']);
					echo "Deleted ".$survey_user_visit['SurveyUserVisit']['id']."\n";
				}
				else {
					$this->SurveyUserVisit->delete($old['SurveyUserVisit']['id']);
					echo "Deleted ".$old['SurveyUserVisit']['id']."\n";
				}
				db($old);
				db($survey_user_visit);
				echo "---\n";
		//		break;
			}
		}		
	}
	
	function project_11964() {
		$transactions = $this->Transaction->find('all', array(
			'conditions' => array(
				'Transaction.type_id' => TRANSACTION_SURVEY,
				'Transaction.linked_to_id' => 11964,
				'Transaction.amount' => '0',
				'Transaction.deleted' => null,
			)
		));
		foreach ($transactions as $transaction) {
			$this->Transaction->create();
			$this->Transaction->save(array('Transaction' => array(
				'id' => $transaction['Transaction']['id'],
				'amount' => '45'
			)), true, array('amount'));
			$this->User->rebuildBalances($transaction);
			echo $transaction['User']['id']."\n";
			flush();
		}
	}
	
	// we were temporarily not setting the redeemed column to true when users were paid out
	function survey_users_redeemed_column() {
		$survey_user_visits = $this->SurveyUserVisit->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'redeemed' => false, 
				'status' => array(SURVEY_COMPLETED, SURVEY_NQ)
			)
		));
		echo 'found '.count($survey_user_visits)."\n";
		if ($survey_user_visits) {
			foreach ($survey_user_visits as $survey_user_visit) {
				$count = $this->Transaction->find('count', array(
					'recursive' => -1,
					'conditions' => array(
						'Transaction.user_id' => $survey_user_visit['SurveyUserVisit']['user_id'],
						'Transaction.linked_to_id' => $survey_user_visit['SurveyUserVisit']['survey_id'],
						'Transaction.type_id' => TRANSACTION_SURVEY,
						'Transaction.deleted' => null,
					)
				));
				if ($count > 0) {
					$this->SurveyUserVisit->create();
					$this->SurveyUserVisit->save(array('SurveyUserVisit' => array(
						'id' => $survey_user_visit['SurveyUserVisit']['id'],
						'redeemed' => true
					)), true, array('redeemed'));
					echo '.';
				}
			}
		}
		
	}
	
	// mistakenly converted linebreaks to <br />
	function transaction_notes_cleanup() {
		$transactions = $this->Transaction->find('all', array(
			'conditions' => array(
				'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
				'Transaction.status' => 'Rejected',
				'Transaction.note !=' => '',
				'Transaction.deleted' => null,
			)
		));
		foreach ($transactions as $transaction) {
			if (strpos($transaction['Transaction']['note'], '<br') !== false) {
				$note = str_replace('<br />', '', $transaction['Transaction']['note']);
				$this->Transaction->create();
				$this->Transaction->save(array('Transaction' => array(
					'id' => $transaction['Transaction']['id'],
					'note' => $note
				)), true, array('note'));
			}
		}
	}
	
	// migrating profile questions to core questions
	function grab_questions_as_arrays() {
		if (!isset($this->args[0])) {
			return;
		}
		echo "\n";
		$question = $this->ProfileQuestion->findById($this->args[0]);
		$i = 0;
		$array = array();
		$mapping = array();
		foreach ($question['ProfileQuestionAnswer'] as $answer) {
			$array[] = "\t".$i." => '".$answer['answer']."',\n";
			$mapping[] = $answer['id'];
			$i++;
		}
		
		echo implode('', $array)."\n\n";
		echo implode(', ', $mapping)."\n"; 
//		print_r($question);
	}
	
	/***
	 * dest0 was storing origins incorrectly
	 */ 
	function referring_for_panthera() {
		$users = $this->User->find('all', array(
			'fields' => array('User.id', 'User.origin', 'UserAcquisition.source'),
			'conditions' => array(
				'User.active' => true,
				'User.deleted' => false,
				'User.hellbanned' => false,
				'User.origin != UserAcquisition.source'				
			),
			'joins' => array(
    		    array(
		            'alias' => 'UserAcquisition',
		            'table' => 'user_acquisitions',
		            'type' => 'LEFT',
		            'conditions' => array(
						'UserAcquisition.user_id = User.id',
					)
		        )
			)
		));
		foreach ($users as $user) {
			echo $user['User']['id']."\t".$user['User']['origin']."\t".$user['UserAcquisition']['source']."\n";
			$this->User->create();
			$this->User->save(array('User' => array(
				'id' => $user['User']['id'],
				'origin' => $user['UserAcquisition']['source']
			)), true, array('origin'));
			flush();
		}
	}
	
	/* 20/12/2013
	 * Deleted users were not unsetting referrers - causing foreign key failures when referring transactions were added
	 */
	function deleted_referrers() {
		$users = $this->User->find('all', array(
			'fields' => array('id', 'referred_by'),
			'conditions' => array(
				'referred_by >' => '0',
			),
			'recursive' => -1,
		));
		$i = 0;
		foreach ($users as $user) {
			$count = $this->User->find('count', array(
				'recursive' => -1,
				'conditions' => array(
					'User.id' => $user['User']['referred_by']
				)
			));
			if ($count == 0) {
				$this->User->create();
				$this->User->save(array('User' => array(
					'id' => $user['User']['id'],
					'referred_by' => '0'
				)), true, array('referred_by'));
				$i++;
			}
		}
		echo 'fixed '.$i.' users';
	}
	
	/* some users are missing transactions but are marked as completed 
	 */
	function missing_transactions() {
		
		if (!isset($this->args[0])) {
			$survey_visits = $this->SurveyUserVisit->find('all', array(
				'fields' => array('id', 'created', 'user_id', 'survey_id', 'ip'),
				'recursive' => -1,
				'conditions' => array(
					'status' => SURVEY_COMPLETED, 
					'redeemed' => '1',
					'created >=' => '2013-12-01'
				)
			));
		}
		else {
			$survey_visits = $this->SurveyUserVisit->find('all', array(
				'fields' => array('id', 'created', 'user_id', 'survey_id', 'ip'),
				'recursive' => -1,
				'conditions' => array(
					'user_id' => $this->args[0],
					'status' => SURVEY_COMPLETED, 
					'redeemed' => '1'
				)
			));
		}
		$i = 0;
		foreach ($survey_visits as $visit) {
			$count = $this->Transaction->find('count', array(
				'conditions' => array(
					'user_id' => $visit['SurveyUserVisit']['user_id'],
					'type_id' => TRANSACTION_SURVEY,
					'linked_to_id' => $visit['SurveyUserVisit']['survey_id'],
					'Transaction.deleted' => null,
				)
			));
			if ($count == 0) {
				// find survey visit matches
				$count = $this->SurveyVisit->find('count', array(
					'recursive' => -1,
					'conditions' => array(
						'SurveyVisit.survey_id' => $visit['SurveyUserVisit']['survey_id'],
						'SurveyVisit.ip' => $visit['SurveyUserVisit']['ip'],
						'SurveyVisit.type' => SURVEY_COMPLETED
					)
				));
				if ($count > 0) {
					$survey = $this->Project->findById($visit['SurveyUserVisit']['survey_id']);
					
					if (empty($survey['Survey']['award'])) {
						continue;
					}
					
					$count = $this->User->find('count', array(
						'recursive' => -1,
						'conditions' => array(
							'User.id' => $visit['SurveyUserVisit']['user_id']
						)
					));
					if ($count == 0) {
						continue;
					}
					$i++;
					print_r($visit);
					$this->Transaction->create();
					$this->Transaction->save(array('Transaction' => array(
						'user_id' => $visit['SurveyUserVisit']['user_id'],
						'type_id' => TRANSACTION_SURVEY,
						'name' => 'Survey Completion - '.$survey['Survey']['description'],
						'linked_to_id' => $visit['SurveyUserVisit']['survey_id'],
						'amount' => $survey['Survey']['award'],
						'status' => TRANSACTION_APPROVED,
						'executed' => $visit['SurveyUserVisit']['created']
					))); 
				}
			}
		}
		echo $i.' rows'."\n";
	}
	/*** 
	 * Sometimes users are not logged into MintVine - not sure what the root cause is
	 * Compare completes for a given survey and recredit the MV user
	 */
	function credit_missing_users() {		
		if (!isset($this->args[0])) {
			return;
		}
		$survey_id = $this->args[0];
		$report_only = true;
		if (isset($this->args[1]) && $this->args[1] == 'true') {
			$report_only = false;
		}
		
		$this->Maintenance->execute($survey_id, $report_only);
	}
	
	// mint_clients table must contain the following fields
	// billing_name, billing_email, project_name, project_email
	function migrate_contacts() {
		$clients = $this->Client->find('all');
		
		if ($clients) {
			foreach ($clients as $client) {
				if ($client['Contact']) {
					$arr_contact['Client']['billing_name'] = $client['Contact'][0]['contact_name'];
					$arr_contact['Client']['billing_email'] = $client['Contact'][0]['email'];
					
					if (isset($client['Contact'][1])) {
						$arr_contact['Client']['project_name'] = $client['Contact'][1]['contact_name'];
						$arr_contact['Client']['project_email'] = $client['Contact'][1]['email'];
					}
					else {
						$arr_contact['Client']['project_name'] = $client['Contact'][0]['contact_name'];
						$arr_contact['Client']['project_email'] = $client['Contact'][0]['email'];
					}
					
					$arr_contact['Client']['id'] = $client['Client']['id'];
					$this->Client->save($arr_contact);
					echo "Contact info saved for ".$client['Client']['client_name'].  "\n";
				}
			}
		}
	}
	
	function update_project_partners_count() {
		$projects = $this->Project->find('all', array(
			'fields' => array('id'),
			'recursive' => -1
		));
		echo 'Projects retrieved'."\n";
		flush();
		App::import('Model', 'SurveyPartner');
		$this->SurveyPartner = new SurveyPartner;
		
		foreach ($projects as $project) {
			$count = $this->SurveyPartner->find('count', array(
				'recursive' => -1,
				'conditions' => array(
					'SurveyPartner.survey_id' => $project['Project']['id']
				)
			));
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'partner_count' => $count
			)), true, array('partner_count'));
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'project.updated',
				'description' => 'Partner count updated to "' . $count . '"',
			)));
			
			echo $project['Project']['id']  . "\n";
			flush();
		}
		
	}
	
	function partner_codes() {
		App::import('Model', 'Partner');
		$this->Partner = new Partner;

		$partners = $this->Partner->find('all');
		if (!$partners) {
			return;
		}

		foreach ($partners as $partner) {
			if ($partner['Partner']['code']) {
				continue;
			}

			$this->Partner->create();
			$this->Partner->save(array('Partner' => array(
					'id' => $partner['Partner']['id'],
					'code' => strtoupper(substr(md5(uniqid(rand(), true)), 0, 8))
				)), true, array('code'));

			echo "Code generated for Partner " . $partner['Partner']['partner_name'] . "\n";
		}
	}
	
	function transaction_payout_processed() {
		$transactions = $this->Transaction->find('all', array(
			'conditions' => array(
				'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
				'Transaction.status' => TRANSACTION_APPROVED,
				'Transaction.deleted' => null,
			)
		));
		foreach ($transactions as $transaction) {
			$this->Transaction->create();
			$this->Transaction->save(array('Transaction' => array(
				'id' => $transaction['Transaction']['id'],
				'payout_processed' => true
			)), true, array('payout_processed'));
		}
	}
	
	// This fix was written when multiple (more then 2) logs of a failed transaction were being saved.
	function payment_logs_fix_multi() {
		App::import('Model', 'PaymentLog');
		$this->PaymentLog = new PaymentLog;
		
		$transactions = $this->PaymentLog->find('list', array(
			'fields' => array('id', 'transaction_id'),
			'group' => 'transaction_id'
		));
		
		foreach ($transactions as $transaction_id) {
			$count = $this->PaymentLog->find('count', array(
				'conditions' => array(
					'transaction_id' => $transaction_id,
					'OR' => array(
						'status =' => null,
						'status' => '0'
					)
				)
			));
			if ($count >= 2) {
				$this->PaymentLog->deleteAll(array(
					'transaction_id' => $transaction_id,
				));
				echo "Transaction id ".$transaction_id ." log deleted because of duplicates!" . "\n";
			}
		}
	}
	
	// This fix will remove and keep only 1 log per transaction - also fixes the statuses as per our changed constants
	function payment_log_fix_duplicates() {
		App::import('Model', 'PaymentLog');
		$this->PaymentLog = new PaymentLog;
		$this->PaymentLog->deleteAll(array(
			'status' => '0',
			'returned_info' => ''
		));
		
		$this->PaymentLog->updateAll(array('status' => PAYMENT_LOG_FAILED), array('status' => '0'));
		$this->PaymentLog->updateAll(array('status' => PAYMENT_LOG_SUCCESSFUL), array('status' => '1'));
		echo "duplicates & status fixed!" . "\n";
	}
	
	function fix_projects_quota() {
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Project.est_quantity', 'Survey.survey_quota'),
			'contain' => array('Survey'),
			'recursive' => -1
		));
		
		if (!$projects) {
			return;
		}
		
		foreach ($projects as $project) {
			$quota = ($project['Survey']['survey_quota']) ? $project['Survey']['survey_quota'] : $project['Project']['est_quantity'];
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'quota' => $quota,
				'modified' => false
			)), true, array('quota', 'modified'));
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'project.updated',
				'description' => 'Quota updated to "' . $quota . '"',
			)));
			
		}
		
		echo "Survey.survey_quota and/or Project.est_quantity data moved to Project.quota". "\n";
	}
	
	function transaction_survey_nq() {
		ini_set('memory_limit', '2048M');
		$transactions = $this->Transaction->find('all', array(
			'fields' => array('id'),
			'conditions' => array(
				'type_id' => TRANSACTION_SURVEY,
				'amount' => 5,
				'deleted' => null,
			),
			'recursive' => -1
		));
		
		if (!$transactions) {
			return;
		}
		
		echo 'Converting '.count($transactions)."\n";
		
		foreach ($transactions as $transaction) {
			$this->Transaction->create();
			$this->Transaction->save(array('Transaction' => array(
				'id' => $transaction['Transaction']['id'],
				'type_id' => TRANSACTION_SURVEY_NQ,
				'updated' => false
			)), true, array('type_id', 'updated'));
			echo '.'; 
		}
		
		echo "Transactions type id for survey nqs changed.". "\n";
	}
	
	function payment_methods() {
		App::import('Model', 'PaymentMethod');
		$this->PaymentMethod = new PaymentMethod;
		
		$payment_methods = $this->PaymentMethod->find('all', array(
			'fields' => array('PaymentMethod.id', 'PaymentMethod.user_id'),
			'recursive' => -1,
			'conditions' => array(
				'PaymentMethod.user_id >' => '0'
			)
		));
		
		if ($payment_methods) {
			foreach ($payment_methods as $payment_method) {
				$count = $this->User->find('count', array(
					'conditions' => array(
						'User.id' => $payment_method['PaymentMethod']['user_id']
					),
					'recursive' =>  -1
				));
				if ($count == 0) {
					echo '.';
					$this->PaymentMethod->delete($payment_method['PaymentMethod']['id']);
				}
			}
		}
	}
	
	public function fulcrum_mask() {
		$fed_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'fulcrum'
			)
		));
		$this->Project->bindModel(array(
			'hasOne' => array(
				'FedSurvey' => array(
					'className' => 'FedSurvey',
					'foreignKey' => 'survey_id'
				)
			)
		));
		$projects = $this->Project->find('all', array(
			'contain' => array(
				'FedSurvey' => array(
					'fields' => array('FedSurvey.id', 'FedSurvey.fed_survey_id')
				)
			),
			'conditions' => array(
				'Project.group_id' => $fed_group['Group']['id'],
			),
			'fields' => array('id'),
			'recursive' => -1
		));
		
		foreach ($projects as $project) {
			// Update mask field
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'mask' => $project['FedSurvey']['fed_survey_id']
			)), true, array('mask'));
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'project.updated',
				'description' => 'Fulcrum Mask updated to "' . $project['FedSurvey']['fed_survey_id'] . '"',
			)));
			
			echo 'Mask updated project id: '.$project['Project']['id']. "\n";
		}
	}
	
	public function toluna_missing_transactions() {
		$toluna_postbacks = $this->TolunaPostback->find('all', array(
			'conditions' => array(
				'TolunaPostback.toluna_survey_id >' => '0'
			)
		)); 
		foreach ($toluna_postbacks as $toluna_postback) {
			$is_success = $toluna_postback['TolunaPostback']['type'] == 'confirmation';
			$project = $this->Project->find('first', array(
				'conditions' => array(
					'prj_name' => $toluna_postback['TolunaPostback']['toluna_survey_id'],
					'group_id' => 8 // hardcode toluna
				),
				'fields' => array('id', 'survey_name'),
				'recursive' => -1
			));
			if (!$project) {
				echo '_';
			}
			$transaction = $this->Transaction->find('first', array(
				'conditions' => array(
					'Transaction.user_id' => $toluna_postback['TolunaPostback']['user_id'],
					'Transaction.linked_to_id' => $project['Project']['id'],
					'Transaction.type_id' => TRANSACTION_SURVEY,
					'Transaction.deleted' => null,
				)
			));
			if (!$transaction) {
				$this->Transaction->create();
				$this->Transaction->save(array('Transaction' => array(
					'user_id' => $toluna_postback['TolunaPostback']['user_id'],
					'linked_to_id' => $project['Project']['id'],
					'linked_to_name' => $project['Project']['survey_name'],
					'type_id' => $is_success ? TRANSACTION_SURVEY : TRANSACTION_SURVEY_NQ,
					'name' => 'Reconciled missing points for #'.$project['Project']['id'],
					'amount' => $is_success ? 42: 5,
					'status' => TRANSACTION_PENDING,
					'paid' => false, 
					'executed' => $toluna_postback['TolunaPostback']['created']
				))); 
				echo $toluna_postback['TolunaPostback']['user_id']."\n";
			}
			else {
				echo '.';
			}
		}
	}
	
	public function clean_postal_codes() {
		$query_profiles = $this->QueryProfile->find('all', array(
			'conditions' => array(
				'QueryProfile.country' => array('GB', 'CA'),
			),
			'fields' => array('id', 'postal_code', 'country'),
			'recursive' => -1
		));
		
		foreach ($query_profiles as $query_profile) {
			
			if ($query_profile['QueryProfile']['country'] == 'CA') {
				$postcode = Utils::format_uk_postcode($query_profile['QueryProfile']['postal_code']);
			}
			elseif ($query_profile['QueryProfile']['country'] == 'GB') {
				$postcode = Utils::format_ca_postcode($query_profile['QueryProfile']['postal_code']);
			}
			
			$this->QueryProfile->save(array('QueryProfile' => array(
				'id' => $query_profile['QueryProfile']['id'],
				'postal_code' => $postcode
			)), true, array('postal_code'));			
			$this->out('Postal code updated for QueryProfile id: ' . $query_profile['QueryProfile']['id']);
		}
		
	}
	
	

	function move_viper_id_to_project_options() {
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Project.viper_id'),
			'conditions' => array(
				'Project.viper_id >' => 0
			),
			'recursive' => -1
		));
		
		if ($projects) {
			foreach ($projects as $project) {
				$this->ProjectOption->create();
				$this->ProjectOption->save(array(
					'ProjectOption' => array(
						'project_id' => $project['Project']['id'],
						'name' => 'viper_id',
						'value' => $project['Project']['viper_id']
					)
				));
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'project_option.created',
					'description' => 'Viper ID created for project, Viper ID: "' . $project['Project']['viper_id'] . '"',
				)));
				
			}
		}
		echo 'Done';
	}
	function update_user_router_log_order() {
		App::import('Model', 'UserRouterLog');
		$this->UserRouterLog = new UserRouterLog;
		while (true) {
			$user_router_logs = $this->UserRouterLog->find('all', array(
				'fields' => array('id'),
				'conditions' => array(
					'UserRouterLog.order' => NULL,
					'UserRouterLog.parent_id' => 0,
				),
				'limit' => '5000'
			));			
			if (!$user_router_logs) {
				break;
			}
			foreach ($user_router_logs as $user_router_log) {
				$order = 1;					
				$this->UserRouterLog->create();
				$this->UserRouterLog->save(array('UserRouterLog' => array(
					'id' => $user_router_log['UserRouterLog']['id'],
					'order' => 1,
					'modified' => false
				)), true, array('order'));
				
				$children_logs = $this->UserRouterLog->find('all', array(
					'fields' => array('id'),
					'conditions' => array(
						'UserRouterLog.parent_id' => $user_router_log['UserRouterLog']['id'],
					)
				));
				
				$this->out('Parent log updated for ID : ' . $user_router_log['UserRouterLog']['id'] . ', order: 1');

				if (!empty($children_logs)) {
					foreach ($children_logs as $child_log) {
						$order++;
						$this->UserRouterLog->create();
						$this->UserRouterLog->save(array('UserRouterLog' => array(
							'id' => $child_log['UserRouterLog']['id'],
							'order' => $order,
							'modified' => false
						)), true, array('order'));
						$this->out('Child log updated for parent_id : ' . $user_router_log['UserRouterLog']['id'] . ', order : ' . $order);
					}
				}
				$this->out('---');
			}
			
		}
	}
	
	public function set_survey_start_end_times() {
		
		// Task for open & stating projects
		$this->Survey->contain(array(
			'Project'
		));
		
		$surveys = $this->Survey->find('all', array(
			'conditions' => array(
				'Survey.started is null'
			),
			'fields' => array(
				'Survey.id',
				'Survey.ended',
				'Survey.started',
				'Survey.created',
				'Project.status'
			)
		));
		echo 'Found '.count($surveys)."\n";
		if ($surveys) {
			foreach ($surveys as $survey) {
				$save = array('Survey' => array(
					'id' => $survey['Survey']['id'],
					'started' => null,
					'modified' => false
				));
				$survey_visit = $this->SurveyVisit->find('first', array(
					'conditions' => array(
						'SurveyVisit.survey_id' =>  $survey['Survey']['id']
					),
					'order' => array(
						'SurveyVisit.created' => 'ASC'
					),
					'fields' => array(
						'SurveyVisit.created'
					)
				));
				if ($survey_visit) {
					$save['Survey']['started'] = $survey_visit['SurveyVisit']['created'];
				}
				else {
					$save['Survey']['started'] = $survey['Survey']['created'];
				}
				$this->Survey->create();
				$this->Survey->save($save, true, array('started'));
				echo 'Start: '.$survey['Survey']['id']."\n";
			}
		}
		echo "Found : ". count($surveys) . " open & staging projects with null 'surveys.started' time to be fixed." . "\n";
		
		//Task for closed projects		
		$this->Survey->contain(array(
			'Project'			
		));
		
		$surveys = $this->Survey->find('all', array(
			'conditions' => array(
				'Survey.ended is null'
			),
			'fields' => array(
				'Survey.id',
				'Survey.ended',
				'Survey.modified',
				'Survey.created',
				'Project.status'
			)
		));		
		echo 'Found '.count($surveys)."\n";
		if ($surveys) {
			foreach ($surveys as $survey) {
				$save = array('Survey' => array(
					'id' => $survey['Survey']['id'],
					'ended' => null,
					'modified' => false
				));
				$survey_visit = $this->SurveyVisit->find('first', array(
					'conditions' => array(
						'SurveyVisit.survey_id' =>  $survey['Survey']['id']
					),
					'order' => array(
						'SurveyVisit.created' => 'DESC'
					),
					'fields' => array(
						'SurveyVisit.created'
					)
				));				
				if ($survey_visit) {
					$save['Survey']['ended'] = $survey_visit['SurveyVisit']['created'];
				}
				else {
					$save['Survey']['ended'] = $survey['Survey']['modified'];
				}			
				$this->Survey->create();	
				$this->Survey->save($save, true, array('ended'));
				echo 'End: '.$survey['Survey']['id']."\n";
			}
		}
		echo "Found : ". count($surveys) . " closed projects with null 'surveys.ended' time & fixed." . "\n";
	}
	
	function save_surveylinks_count() {
		App::import('Model', 'SurveyLink');
		$this->SurveyLink = new SurveyLink;
		
		$surveys = $this->SurveyLink->find('all', array(
			'fields' => array('DISTINCT(survey_id)')
		));
		
		$total = count($surveys);
		echo 'Found '.$total."\n";
		
		$i = 1;
		foreach ($surveys as $survey) {
			echo $i. ' / '.$total.' '. $survey['SurveyLink']['survey_id']."\n";
			$this->Survey->reset_surveylinks_count($survey['SurveyLink']['survey_id']);
			$i++;
		}
		echo 'All survey_links count added for ' . count($surveys) . ' surveys.';
	}
	
	function populate_cint_counts() {
		App::import('Model', 'CintLog');
		$this->CintLog = new CintLog;
		
		$cint_logs = $this->CintLog->find('all', array(
			'conditions' => array(
				'CintLog.parent_id' => '0',
				'CintLog.count is null'
			),
			'fields' => array('id')
		));
		foreach ($cint_logs as $cint_log) {
			$count = $this->CintLog->find('count', array(
				'conditions' => array(
					'CintLog.parent_id' => $cint_log['CintLog']['id']
				)
			));
			$sum = $this->CintLog->find('first', array(
				'conditions' => array(
					'CintLog.parent_id' => $cint_log['CintLog']['id']
				),
				'fields' => array(
					'SUM(quota) as quota'
				)
			));
			$this->CintLog->create();
			$this->CintLog->save(array('CintLog' => array(
				'id' => $cint_log['CintLog']['id'],
				'count' => $count,
				'quota' => $sum[0]['quota'],
				'modified' => false
			)), true, array('count', 'quota'));
			echo $cint_log['CintLog']['id']."\n";
		}
	}
	
	function update_user_agents() {
		$user_ips = $this->UserIp->find('list', array(
			'fields' => array(
				'UserIp.id', 'UserIp.user_agent'
			)
		));
		
		foreach ($user_ips as $user_ip_id => $user_agent) {
			if (empty($user_agent)) {
				continue;
			}
			$this->UserIp->create();
			$this->UserIp->save(array(
				'UserIp' => array(
					'id' => $user_ip_id,
					'user_agent' => Utils::agent_formattting($user_agent)
				)
			));
		}
	}
	
	// for setting the ignore flag in query_profiles
	function set_ignore_flag() {
		ini_set('memory_limit', '4096M');
		$this->User->bindModel(array(
			'hasOne' => array(
				'QueryProfile'
			)
		));
		$users = $this->User->find('all', array(
			'fields' => array('User.id', 'QueryProfile.id', 'User.active', 'User.deleted', 'User.hellbanned', 'User.last_touched'),
			'conditions' => array(
				'QueryProfile.ignore' => true
			)
		));
		$this->out('Found '.count($users).' ignored records');
		foreach ($users as $user) {
			$is_active = strtotime($user['User']['last_touched']) > strtotime('-2 months'); 
			if (!$user['User']['hellbanned'] && !$user['User']['deleted'] && $user['User']['active'] && $is_active) {
				$this->QueryProfile->create();
				$this->QueryProfile->save(array('QueryProfile' => array(
					'id' => $user['QueryProfile']['id'],
					'ignore' => false,
					'modified' => false
				)), true, array('ignore'));
				$this->out($user['User']['id']);
			}
		}
	}
	
	function export_reports_to_s3() {
		
		$last_report_export_id = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.project_id' => 0,
				'ProjectOption.name' => 'last_report_export_id'
			)
		));
		if ($last_report_export_id) {
			$last_record_id = $last_report_export_id['ProjectOption']['value'];
		}
		else {
			$last_record_id = 0;
		}
		
		echo 'Exporting '.$last_record_id."\n";
		
		$reports = $this->Report->find('all', array(
			'fields' => array(
				'Report.id', 'Report.path', 'Report.custom_path'
			),
			'order' => 'Report.id DESC' 
		));
		
		if ($reports) {
			CakePlugin::load('Uploader');
			App::import('Vendor', 'Uploader.S3');
			
			$settings = $this->Setting->find('list', array(
				'fields' => array('name', 'value'),
				'conditions' => array(
					'Setting.name' => array(
						's3.access',
						's3.secret',
						's3.bucket',
						's3.host',
					),
					'Setting.deleted' => false
				)
			));
			
			echo 'Starting migration of '.count($reports)."\n";
			foreach ($reports as $report) {
				
				echo '#'.$report['Report']['id']."\n";
				if (!empty($report['Report']['path'])) {
					if (substr($report['Report']['path'], 0, 6) != '/files') {
						$report['Report']['path'] = '/files/'.$report['Report']['path'];
						$this->Report->getDatasource()->reconnect();
						$this->Report->create();
						$this->Report->save(array('Report' => array(
							'id' => $report['Report']['id'],
							'path' => $report['Report']['path'],
							'modified' => false
						)), true, array('path'));
					}
					$file = WWW_ROOT.$report['Report']['path'];
					$parts = explode('/', $file); 
					$filename = array_pop($parts);
					$aws_filename = $report['Report']['path'];
					if (substr($aws_filename, 0, 1) == '/') {
						$aws_filename = substr($aws_filename, 1, strlen($aws_filename));
					}
				
					$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);			
					$headers = array(
						'Content-Disposition' => 'attachment; filename='.$filename.'.csv'
					);
					if (file_exists($file)) {
						echo 'Migrated #'.$report['Report']['id']." (main)\n";
						$S3->putObject($S3->inputFile($file), $settings['s3.bucket'] , $aws_filename, S3::ACL_PRIVATE, array(), $headers);
						//unlink($file);
					}
				}
				
				if (!empty($report['Report']['custom_path'])) {
					$file = WWW_ROOT.$report['Report']['custom_path'];
					$parts = explode('/', $file); 
					$filename = array_pop($parts);
					if (substr($report['Report']['custom_path'], 0, 6) != '/files') {
						$report['Report']['custom_path'] = '/files/'.$report['Report']['custom_path'];
						$this->Report->getDatasource()->reconnect();
						$this->Report->create();
						$this->Report->save(array('Report' => array(
							'id' => $report['Report']['id'],
							'custom_path' => $report['Report']['custom_path'],
							'modified' => false
						)), true, array('custom_path'));
					}
					$aws_filename = $report['Report']['custom_path'];
					if (substr($aws_filename, 0, 1) == '/') {
						$aws_filename = substr($aws_filename, 1, strlen($aws_filename));
					}
				
					$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);		
					$headers = array(
						'Content-Disposition' => 'attachment; filename='.$filename.'.csv'
					);
					if (file_exists($file)) {
						echo 'Migrated #'.$report['Report']['id']." (custom)\n";
						$S3->putObject($S3->inputFile($file), $settings['s3.bucket'] , $aws_filename, S3::ACL_PRIVATE, array(), $headers);
						//unlink($file);						
					}
				}
				
				$last_report = $report['Report']['id'];
			}
			
			$this->ProjectOption->getDatasource()->reconnect();
			$this->ProjectOption->create();
			if ($last_report_export_id) {
				$this->ProjectOption->save(array(
					'ProjectOption' => array(
						'id' => $last_report_export_id['ProjectOption']['id'],
						'value' => $last_report
					)
				), false, array('value'));
				
				
			}
			else {	
				$this->ProjectOption->save(array(
					'ProjectOption' => array(						
						'value' => $last_report,
						'project_id' => 0,
						'name' => 'last_report_export_id'
					)
				));
			}	
		}
	}
	
	function populate_precision_user() {
		$csv_users = file(WWW_ROOT . 'mintvine_ps_guids.csv');		
		unset($csv_users[0]);
		if (!empty($csv_users)) {
			foreach ($csv_users as $csv_user) {
				$csv_user = explode(',', $csv_user);		
				if (is_numeric(trim($csv_user[1]))) {
					$this->PartnerUser->create();
					$this->PartnerUser->save(array(
						'PartnerUser' => array(
							'last_exported' => date(DB_DATETIME),
							'uid' => trim($csv_user[0]),
							'user_id' => trim($csv_user[1]),
							'partner' => 'precision'
						)
					));
				}
			}
		}
		echo '...Complete...';
	}
	
	function export_invoices_to_s3() {
		$invoices = $this->Invoice->find('all', array(
			'fields' => array(
				'Invoice.id', 'Invoice.uuid', 'Invoice.project_id'
			),
			'recursive' => -1
		));
		
		if ($invoices) {
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
			echo 'Starting migration of '.count($invoices)."\n";
			foreach ($invoices as $invoice) {
				$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);
				$path = WWW_ROOT . 'files/pdf/Inv_'. $invoice['Invoice']['project_id'] .'_BRInc.pdf';
				if (is_file($path)) {
					$aws_filename = 'files/pdf/Inv_'. $invoice['Invoice']['project_id'] .'_BRInc.pdf';
					$headers = array(
						'Content-Disposition' => 'attachment; filename='.$invoice['Invoice']['project_id'] .'_BRInc.pdf'
					);
					echo 'Migrated pdf#'.$invoice['Invoice']['id']." (main)\n";
					$S3->putObject($S3->inputFile($path), $settings['s3.bucket'], $aws_filename, S3::ACL_PRIVATE, array(), $headers);
				}

				$path = WWW_ROOT . 'files/html/'. $invoice['Invoice']['uuid'] .'.html';
				if (is_file($path)) {
					$aws_filename = 'files/html/'. $invoice['Invoice']['uuid'] .'.html';
					$headers = array();
					echo 'Migrated html#'.$invoice['Invoice']['id']." (main)\n";
					$S3->putObject($S3->inputFile($path), $settings['s3.bucket'], $aws_filename, S3::ACL_PRIVATE, array(), $headers);
				}	
			}			
		}
	}
	
	function data_from_surveys_to_projects() {
		ini_set('memory_limit', '4096M');
		$this->Survey->bindModel(array('belongsTo' => array('Project')));
		$surveys = $this->Survey->find('all', array(
			'fields' => array(
				'Survey.*',
				'Project.id'
			),
			'recursive' => 0
		));
		
		$total_missing_projects = 0;
		if ($surveys) {
			foreach ($surveys as $survey) {
				if (empty($survey['Project']['id'])) {
					$total_missing_projects++;
					continue;
				}
				
				$this->Project->create();
				$this->Project->save(array(
					'Project' => array(
						'id' => $survey['Project']['id'],
						'language' => $survey['Survey']['language'],
						'survey_name' => $survey['Survey']['survey_name'],
						'logo_filepath' => $survey['Survey']['logo_filepath'],
						'description' => $survey['Survey']['description'],
						'minimum_time' => $survey['Survey']['minimum_time'],
						'loi' => $survey['Survey']['loi'],
						'award' => $survey['Survey']['award'],
						'pool' => $survey['Survey']['pool'],
						'active' => $survey['Survey']['active'],
						'dedupe' => $survey['Survey']['dedupe'],
						'client_survey_link' => $survey['Survey']['client_survey_link'],
						'client_end_action' => $survey['Survey']['client_end_action'],
						'public' => $survey['Survey']['public'],
						'prescreen' => $survey['Survey']['prescreen'],
						'desktop' => $survey['Survey']['desktop'],
						'mobile' => $survey['Survey']['mobile'],
						'tablet' => $survey['Survey']['tablet'],
						'paused' => $survey['Survey']['paused'],
						'started' => $survey['Survey']['started'],
						'ended' => $survey['Survey']['ended'],
						'modified' => false
				)), true, array('language', 'survey_name', 'logo_filepath', 'description', 'minimum_time', 'loi', 'award', 'pool', 'active', 'dedupe', 'client_survey_link', 'client_end_action', 'public', 'prescreen', 'desktop', 'mobile', 'tablet', 'paused', 'started', 'ended'));
				echo $survey['Project']['id']."\n";
			}
		}
		
		echo 'Total survey(s) : ' . count($surveys) . ', Missing project(s) : ' . $total_missing_projects . ', Copied over project(s) : ' . (count($surveys) - $total_missing_projects);
	}
	
	public function rfg_surveys() {
		$rfg_surveys = $this->RfgSurvey->find('all');
		foreach ($rfg_surveys as $survey) {
			$count = $this->RfgSurvey->find('count', array(
				'conditions' => array(
					'rfg_survey_id' => $survey['RfgSurvey']['rfg_survey_id']
				)
			));
			if ($count > 1) {
				$this->RfgSurvey->deleteAll(array(
					'RfgSurvey.survey_id' => '0',
					'RfgSurvey.rfg_survey_id' => $survey['RfgSurvey']['rfg_survey_id']
				), false);
				echo 'Dupe of RfgSurvey deleted. rfg_id: '.$survey['RfgSurvey']['rfg_survey_id']. "\n";
			}
		}
	}
	
	function precision_mask() {
		$this->Project->bindModel(array(
			'hasOne' => array(
				'PrecisionProject'
			)
		));
		
		$this->Project->unbindModel(array(
			'hasMany' => array(
				'SurveyPartner',
				'ProjectOption'
			)
		));
		
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'PrecisionProject.precision_project_id >' => 0
			),
			'fields' => array(
				'Project.id',
				'PrecisionProject.precision_project_id'
			)
		));
		
		if ($projects) {
			foreach ($projects as $project) {
				$this->Project->create();
				$this->Project->save(array(
					'Project' => array(
						'id' => $project['Project']['id'],
						'mask' => $project['PrecisionProject']['precision_project_id']
					)
				), false, array('mask'));
			}
		}
		
		
	}
	
	public function fix_ssi_user_payout() {
		$client = $this->Client->find('first', array(
			'fields' => array('Client.id'),
			'conditions' => array(
				'Client.key' => 'ssi'
			)
		));
		if (!$client) {
			return;
		}
		
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Project.user_payout', 'Project.award'),
			'conditions' => array(
				'Project.client_id' => $client['Client']['id']
			),
			'contain' => array(
				'ProjectRate' => array(
					'fields' => array('ProjectRate.id', 'ProjectRate.award')
				)
			)
		));
		
		$fixed_project_count = 0;
		if ($projects) {
			foreach ($projects as $project) {
				$award = '';
				if (!empty($project['ProjectRate']['id'])) {
					$award = $project['ProjectRate']['award'];
				}
				else {
					$award = $project['Project']['award'];
				}
				
				$user_payout = $award / 100;
				
				if ($user_payout != $project['Project']['user_payout']) {
					$this->Project->create();
					$this->Project->save(array(
						'Project' => array(
							'id' => $project['Project']['id'],
							'user_payout' => $user_payout,
							'modified' => false
						)
					), true, array('user_payout'));
					$fixed_project_count ++;
				}
			}
		}
		
		echo 'Total fixed project(s) : ' . $fixed_project_count;
	}
	
	public function tangocard_orders() {
		$tangocard_orders = $this->TangocardOrder->find('all');
		foreach ($tangocard_orders as $tangocard_order) {
			$response = json_decode($tangocard_order['TangocardOrder']['response'], true);
			$user = $this->User->find('first', array(
				'fields' => array('id'),
				'conditions' => array(
					'User.email' => $tangocard_order['TangocardOrder']['recipient_email'] 
				)
			));
			$this->TangocardOrder->create();
			$this->TangocardOrder->save(array('TangocardOrder' => array(
				'id' => $tangocard_order['TangocardOrder']['id'],
				'delivered_at' => $response['order']['delivered_at'],
				'user_id' => ($user) ? $user['User']['id'] : '0'
			)), true, array('delivered_at', 'user_id'));
		}
	}
	
	public function cint_payouts() {
		$cint_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'cint'
			)
		));
		
		$this->CintSurvey->bindModel(array(
			'belongsTo' => array(
				'Project' => array(
					'className' => 'Project',
					'foreignKey' => 'survey_id'
				)
			)
		));
		
		$projects = $this->CintSurvey->find('all', array(
			'contain' => array('Project'),
			'conditions' => array(
				'NOT' => array(
					'Project.status' => PROJECT_STATUS_INVOICED 
				)
			)
		));
		
		foreach ($projects as $project) {
			if ($project['Project']['project_rate_id']) {
				$project_rate = $this->ProjectRate->find('first', array(
					'conditions' => array(
						'id' => $project['Project']['project_rate_id']
					),
					'fields' => array('client_rate')
				));
				$project_rate = $project_rate['ProjectRate']['client_rate'];
			}
			else {
				$project_rate = $project['Project']['client_rate'];
			}
			
			if ($project_rate <= 0) {
				continue;
			}
			
			$payout = round($project_rate * 4 / 10, 2);
			$payout_to_partners = round($payout * 4 / 10, 2);					
			$award = intval($payout_to_partners * 100);
			if ($award > 200) {
				$award = 200;
			}
			
			$project_rate = $this->ProjectRate->find('first', array(
				'conditions' => array(
					'client_rate' => $payout,
					'project_id' => $project['Project']['id']
				),
				'fields' => array('id')
			));

			if ($project_rate) {
				$project_rate_id = $project_rate['ProjectRate']['id'];
			}
			else {
				$this->ProjectRate->create();
				$save = $this->ProjectRate->save(array('ProjectRate' => array(
					'project_id' => $project['Project']['id'],
					'client_rate' => $payout,
					'award' => $award,
				)));
				if ($save) {
					$project_rate_id = $this->ProjectRate->getLastInsertID();
				}
			}
			
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'client_rate' => $payout,
				'partner_rate' => ($payout > 0 ) ? $payout_to_partners : 0,
				'user_payout' => ($award > 0) ? round($award / 100, 2) : 0,
				'project_rate_id' => $project_rate_id,
				'award' => $award
			)), true, array('client_rate', 'partner_rate', 'user_payout', 'project_rate_id', 'award'));
			echo 'Project id:'.$project['Project']['id']. ' rates updated.'. "\n";
		}
	}
	
	public function cint_partner_rate() {
		$cint_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'cint'
			)
		));
		$mv_partner = $this->Partner->find('first', array(
			'conditions' => array(
				'Partner.key' => 'mintvine'
			)
		));
		
		$this->CintSurvey->bindModel(array(
			'belongsTo' => array(
				'Project' => array(
					'className' => 'Project',
					'foreignKey' => 'survey_id'
				)
			)
		));
		
		$projects = $this->CintSurvey->find('all', array(
			'contain' => array('Project'),
			'conditions' => array(
				'NOT' => array(
					'Project.status' => PROJECT_STATUS_INVOICED 
				)
			)
		));
		
		foreach ($projects as $project) {
			$survey_partner = $this->SurveyPartner->find('first', array(
				'conditions' => array(
					'survey_id' => $project['Project']['id'],
					'partner_id' => $mv_partner['Partner']['id'],
				)
			));
			
			if ($survey_partner) {
				$this->SurveyPartner->create();
				$this->SurveyPartner->save(array('SurveyPartner' => array(
					'id' => $survey_partner['SurveyPartner']['id'],
					'rate' => ($project['Project']['partner_rate'] > 0 ) ? $project['Project']['partner_rate'] : 0,
				)), true, array('rate'));
				
				echo 'Project id:'.$project['Project']['id']. ' Survey Partner rate updated.'. "\n";
			}
		}
	}
	
	// back in the day, user_id contained partner userids... clean this up
	public function prune_user_survey_visits() {
		$last_id = 0;
		$max_id = 3077906; 
		while (true) {
			$survey_user_visits = $this->SurveyUserVisit->find('all', array(
				'conditions' => array(
					'SurveyUserVisit.id >' => $last_id,
					'SurveyUserVisit.id <' => $max_id
				),
				'fields' => array('id', 'user_id', 'created'),
				'order' => 'SurveyUserVisit.id ASC',
				'limit' => 15000,
				'recursive' => -1
			)); 
			if (!$survey_user_visits) {
				break;
			}
			foreach ($survey_user_visits as $survey_user_visit) {
				$user = $this->User->find('first', array(
					'conditions' => array(
						'User.id' => $survey_user_visit['SurveyUserVisit']['user_id']
					),
					'recursive' => -1,
					'fields' => array('id', 'created')
				));
				if (!$user || strtotime($user['User']['created']) > strtotime($survey_user_visit['SurveyUserVisit']['created'])) {
					$this->SurveyUserVisit->delete($survey_user_visit['SurveyUserVisit']['id']); 
					echo 'FIXED: '.$survey_user_visit['SurveyUserVisit']['id']."\n";
				}
				$last_id = $survey_user_visit['SurveyUserVisit']['id'];
				echo $survey_user_visit['SurveyUserVisit']['id']."\n";
			}
		}
	}
	
	public function populate_user_acquisition_data() {
		$project_option = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.name' => 'fix.populate_user_acquisition_data'
			)
		));
		if (!$project_option) {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'name' => 'fix.populate_user_acquisition_data',
				'value' => '0',
				'project_id' => '0'
			)));
			$project_option['ProjectOption']['id'] = $this->ProjectOption->getInsertId();
			$last_id = 0;
		}
		else {
			$last_id = $project_option['ProjectOption']['value'];
		}
		while (true) {
			echo '---';
			$user_acquisition_datas = $this->UserAcquisitionData->find('all', array(
				'conditions' => array(
					'UserAcquisitionData.first_survey is null',
					'UserAcquisitionData.id >' => $last_id
				),
				'order' => 'UserAcquisitionData.id ASC',
				'limit' => '1000'
			));
			if ($user_acquisition_datas) {
				foreach ($user_acquisition_datas as $user_acquisition_data) {
					$user = $this->User->find('first', array(
						'recursive' => -1,
						'fields' => array('first_survey'),
						'conditions' => array('User.id' => $user_acquisition_data['UserAcquisitionData']['user_id'])
					));
					if ($user && !empty($user['User']['first_survey'])) {
						$this->UserAcquisitionData->create();
						$this->UserAcquisitionData->save(array('UserAcquisitionData' => array(
							'id' => $user_acquisition_data['UserAcquisitionData']['id'],
							'first_survey' => $user['User']['first_survey'],
							'modified' => false
						)), true, array('first_survey'));
						echo $user_acquisition_data['UserAcquisitionData']['user_id'].': '.$user['User']['first_survey']."\n";
					}
					$last_id = $user_acquisition_data['UserAcquisitionData']['id'];
				}
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'id' => $project_option['ProjectOption']['id'],
					'value' => $last_id
				)), false, array('value'));
			}
			else {
				break;
			}
		}
	}
	
	public function set_first_survey_start() {
		if (!isset($this->args[0])) {
			$project_option = $this->ProjectOption->find('first', array(
				'conditions' => array(
					'ProjectOption.name' => 'fix.set_first_survey_start'
				)
			));
			if (!$project_option) {
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'name' => 'fix.set_first_survey_start',
					'value' => '0',
					'project_id' => '0'
				)));
				$project_option['ProjectOption']['id'] = $this->ProjectOption->getInsertId();
				$last_id = 0;
			}
			else {
				$last_id = $project_option['ProjectOption']['value'];
			}	
		}
		elseif ($this->args[0] == 'repair') {
			$project_option = $this->ProjectOption->find('first', array(
				'conditions' => array(
					'ProjectOption.name' => 'fix.set_first_survey_start.repair'
				)
			));
			if (!$project_option) {
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'name' => 'fix.set_first_survey_start.repair',
					'value' => '0',
					'project_id' => '0'
				)));
				$project_option['ProjectOption']['id'] = $this->ProjectOption->getInsertId();
				$last_id = 0;
			}
			else {
				$last_id = $project_option['ProjectOption']['value'];
			}	
		}
		elseif ($this->args[0] == 'all') {
			$project_option = $this->ProjectOption->find('first', array(
				'conditions' => array(
					'ProjectOption.name' => 'fix.set_first_survey_start.all'
				)
			));
			if (!$project_option) {
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'name' => 'fix.set_first_survey_start.all',
					'value' => '0',
					'project_id' => '0'
				)));
				$project_option['ProjectOption']['id'] = $this->ProjectOption->getInsertId();
				$last_id = 0;
			}
			else {
				$last_id = $project_option['ProjectOption']['value'];
			}	
		}
		while (true) {
			if (!isset($this->args[0])) {
				echo 'Starting '.$last_id."\n";
			}
			elseif ($this->args[0] == 'all') {
				echo 'Updating all updated users starting with '.$last_id."\n";
			}
			elseif ($this->args[0] == 'repair') {
				echo 'Updating all updated users starting with '.$last_id." that have a survey start before account creation\n";
			}
			else {
				echo 'Updating user #'.$this->args[0]."\n";
			}
			if (isset($this->args[0])) {
				if ($this->args[0] == 'all') {
					$users = $this->User->find('list', array(
						'fields' => array('User.id'),
						'conditions' => array(
							'User.first_survey is not null',
							'User.id >' => $last_id
						),
						'order' => 'User.id ASC',
						'limit' => '10000'
					));
				}
				if ($this->args[0] == 'repair') {
					$users = $this->User->find('list', array(
						'fields' => array('User.id', 'User.created'),
						'conditions' => array(
							'User.first_survey is not null',
							'User.first_survey < User.created'
						),
						'order' => 'User.id ASC',
						'limit' => '10000'
					));
				}
				else {
					$users = $this->User->find('list', array(
						'fields' => array('User.id'),
						'conditions' => array(
							'User.id' => $this->args[0]
						)
					));
				}
			}
			else {
				$users = $this->User->find('list', array(
					'fields' => array('User.id'),
					'conditions' => array(
						'User.first_survey is null',
						'User.id >' => $last_id
					),
					'order' => 'User.id ASC',
					'limit' => '10000'
				));
			}
			if (!empty($users)) {
				foreach ($users as $key => $user_id) {
					$last_id = $user_id;
					if (isset($this->args[0]) && $this->args[0] == 'repair') {
						$created = $user_id; 
						$user_id = $key;
						$survey_user_visit = $this->SurveyUserVisit->find('first', array(
							'conditions' => array(
								'SurveyUserVisit.user_id' => $user_id,
								'SurveyUserVisit.created >' => $created, 
							),
							'fields' => array('SurveyUserVisit.created'),
							'order' => 'SurveyUserVisit.id ASC'
						));
					}
					else {
						$survey_user_visit = $this->SurveyUserVisit->find('first', array(
							'conditions' => array(
								'SurveyUserVisit.user_id' => $user_id,
							),
							'fields' => array('SurveyUserVisit.created'),
							'order' => 'SurveyUserVisit.id ASC'
						));
					}
					if ($survey_user_visit) {
						$this->User->create();
						$this->User->save(array('User' => array(
							'id' => $user_id,
							'first_survey' => $survey_user_visit['SurveyUserVisit']['created'],
							'modified' => false
						)), true, array('first_survey'));
						
						$user_acquisition_data = $this->UserAcquisitionData->find('first', array(
							'conditions' => array(
								'UserAcquisitionData.user_id' => $user_id
							)
						));
						if ($user_acquisition_data) {
							if (isset($this->args[0]) && $this->args[0] == 'all') {
								if ($user_acquisition_data['UserAcquisitionData']['first_survey'] != $survey_user_visit['SurveyUserVisit']['created']) {
									$this->UserAcquisitionData->create();
									$this->UserAcquisitionData->save(array('UserAcquisitionData' => array(
										'id' => $user_acquisition_data['UserAcquisitionData']['id'],
										'first_survey' => $survey_user_visit['SurveyUserVisit']['created']
									)), true, array('first_survey'));
									echo $user_id.' '.$survey_user_visit['SurveyUserVisit']['created']."\n";
								}
							}
							elseif (isset($this->args[0]) && $this->args[0] == 'repair') {
								if ($user_acquisition_data['UserAcquisitionData']['first_survey'] != $survey_user_visit['SurveyUserVisit']['created']) {
									$this->UserAcquisitionData->create();
									$this->UserAcquisitionData->save(array('UserAcquisitionData' => array(
										'id' => $user_acquisition_data['UserAcquisitionData']['id'],
										'first_survey' => $survey_user_visit['SurveyUserVisit']['created']
									)), true, array('first_survey'));
									echo $user_id.' '.$survey_user_visit['SurveyUserVisit']['created']."\n";
								}
							}
							else {
								$this->UserAcquisitionData->create();
								$this->UserAcquisitionData->save(array('UserAcquisitionData' => array(
									'id' => $user_acquisition_data['UserAcquisitionData']['id'],
									'first_survey' => $survey_user_visit['SurveyUserVisit']['created']
								)), true, array('first_survey'));
								echo $user_id.' '.$survey_user_visit['SurveyUserVisit']['created']."\n";
							}
						}
					}
				}
				if (isset($this->args[0]) && $this->args[0] != 'all') {
					break;
				}
				else {
					$this->ProjectOption->create();
					$this->ProjectOption->save(array('ProjectOption' => array(
						'id' => $project_option['ProjectOption']['id'],
						'value' => $last_id
					)), false, array('value'));
				}
			}
			else {
				break;
			}
		}
	}
	
	public function cint_survey_country() {
		$this->CintSurvey->bindModel(array(
			'belongsTo' => array(
				'Project' => array(
					'className' => 'Project',
					'foreignKey' => 'survey_id'
				)
			)
		));
		
		$projects = $this->CintSurvey->find('all', array(
			'contain' => array(
				'Project'
			)
		));
		foreach ($projects as $project) {
			if (empty($project['CintSurvey']['country']) && !empty($project['Project']['country'])) {
				$this->CintSurvey->create();
				$this->CintSurvey->save(array('CintSurvey' => array(
					'id' => $project['CintSurvey']['id'],
					'country' => $project['Project']['country'],
				)), true, array('country'));
				
				echo 'Project id:'.$project['Project']['id']. ' cint country updated.'. "\n";
			}
		}
	}
	
	function mobile_user_scoring() {
		
		$weights = unserialize(USER_ANALYSIS_WEIGHTS);
		$user_analysis = $this->UserAnalysis->find('all', array(
			'conditions' => array(
				'UserAnalysis.mobile_verified' => 25
			),
			'recursive' => -1
		));		
		
		echo 'Fixing '.count($user_analysis).' records'."\n";
		if ($user_analysis) {
			foreach ($user_analysis as $user_record) {
				$score = $total = 0;
				foreach ($weights as $key => $weight) {
					if (isset($user_record['UserAnalysis'][$key]) && !is_null($user_record['UserAnalysis'][$key])) {
						$total = $total + $weight;						 
						if ($user_record['UserAnalysis'][$key] !== false && $key != 'mobile_verified') {
							$score = $user_record['UserAnalysis'][$key] + $score; 
						}
					}
				}
				
				$record = array();
				// no data
				if (empty($total)) {
					$record['score'] = '101'; 
					$record['raw'] = $score; 
					$record['total'] = $total; 
				}
				else {
					$record['score'] = round(100 * ($score / $total), 2);
					$record['raw'] = $score; 
					$record['total'] = $total; 
				}
				
				$record['id'] = $user_record['UserAnalysis']['id']; 
				$record['modified'] = false;
				$record['mobile_verified'] = 0;
				$this->UserAnalysis->create();
				$this->UserAnalysis->save(array(
					'UserAnalysis' => $record
				), false, array('total', 'raw', 'score', 'mobile_verified'));
				echo 'Fixed '.$record['id']."\n";
			}
		}
	}
	
	public function rfg_convert_to_router_pulls() {
		$rfg_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'rfg'
			)
		));
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.group_id' => $rfg_group['Group']['id']
			),
			'fields' => array('Project.id', 'Project.status', 'Project.active', 'Project.prescreen')
		));
		echo 'Analyzing '.count($projects).' projects'."\n";
		foreach ($projects as $project) {
			if ($project['Project']['prescreen']) {
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'prescreen' => false,
					'modified' => false
				)), true, array('prescreen'));
				echo 'Turned off prescreener for '.$project['Project']['id']."\n";
			}
			
			// if this is a staging or sampling project, move it to open
			if ($project['Project']['active'] && in_array($project['Project']['status'], array(PROJECT_STATUS_STAGING, PROJECT_STATUS_SAMPLING))) {
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'status' => PROJECT_STATUS_OPEN,
					'modified' => false
				)), true, array('status'));
				
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project['Project']['id'],
					'type' => 'status.opened.offerwall',
					'description' => 'One-time migration from sampling/staging to open with new offerwall implementation'
				)));
				echo 'Moved to open '.$project['Project']['id']."\n";
			}
		}
	}
	
	function sync_mappings_to_source() {
		$this->loadModel('SourceMappingReport');
		$source_mapping_reports = $this->SourceMappingReport->find('all', array(
			'recursive' => -1
		));
		
		if ($source_mapping_reports) {
			foreach ($source_mapping_reports as $source_mapping_report) {				
				$this->SourceReport->create();
				$this->SourceReport->save(array('SourceReport' => array(
					'user_id' => $source_mapping_report['SourceMappingReport']['user_id'],
					'source_mapping_id' => $source_mapping_report['SourceMappingReport']['source_mapping_id'],
					'status' => $source_mapping_report['SourceMappingReport']['status'],
					'date_from' => $source_mapping_report['SourceMappingReport']['date_from'],
					'date_to' => $source_mapping_report['SourceMappingReport']['date_to'],
					'created' => $source_mapping_report['SourceMappingReport']['created'],
					'path' => $source_mapping_report['SourceMappingReport']['path']
				)));
			}
		}
	}
	
	function remove_dupe_fed_surveys() {
		$fed_surveys = $this->FedSurvey->find('all', array(
			'conditions' => array(
				'FedSurvey.survey_id >' => 0
			),
			'fields' => array(
				'id', 'fed_survey_id'
			),
			'group' => array('fed_survey_id having count(id) > 1')
		));	
		
		echo 'Duplicate found ('.count($fed_surveys).')';
		if ($fed_surveys) {
			foreach ($fed_surveys as $fed_survey) {
				$surveys = $this->FedSurvey->find('all', array(
					'conditions' => array(
						'FedSurvey.fed_survey_id' => $fed_survey['FedSurvey']['fed_survey_id']
					),
					'fields' => array('id', 'fed_survey_id'),
					'order' => array('id DESC')					
				));
				foreach ($surveys as $key => $survey) {
					if ($key == 0) {
						continue;
					}
					$this->FedSurvey->delete($survey['FedSurvey']['id']);
					echo 'Deleted FedSurvey entry id = '.$survey['FedSurvey']['id'];
				}
			}
		}		
	}
	
	function sync_cint_users() {
		ini_set('memory_limit', '1024M');
		$settings = $this->Setting->find('list', array(
			'conditions' => array(
				'Setting.name LIKE' => 'cint.%',
				'Setting.deleted' => false
			),
			'fields' => array('name', 'value')
		));
		
		$conditions = array(
			'User.deleted' => false,
			'User.cint !=' => null
		);
		
		if (isset($this->args[0])) {
			$conditions = array('User.id' => $this->args[0]);
		}
		
		$last_id = 0;
		while (true) {
			$users = $this->User->find('all', array(
				'fields' => array('User.id', 'User.firstname', 'User.lastname'),
				'conditions' => $conditions,
				'contain' => array(
					'QueryProfile' => array(
						'fields' => array(
							'id',
							'country',
							'postal_code', 
							'gender', 
							'birthdate', 
							'education', 
							'employment', 
							'ethnicity', 
							'hhi', 
							'housing_own',
							'relationship',
							'smartphone',
							'organization_size',
							'department',
							'industry',
							'job',
						
						),
					)
				),
				'order' => 'User.id ASC',
				'limit' => 10000
			));
				
			if (!$users) {
				break;
			}
			echo '---------------------'."\n";
			echo 'Starting '.count($users)."\n";
			foreach ($users as $user) {
				// repeating
				if ($user['User']['id'] < $last_id) {
					break;
				}
				$last_id = $user['User']['id'];
				if ($user['QueryProfile']['country'] == 'US') {
					$api_key = $settings['cint.us.key'];
					$api_secret = $settings['cint.us.secret'];
				}
				elseif ($user['QueryProfile']['country'] == 'GB') {
					$api_key = $settings['cint.gb.key'];
					$api_secret = $settings['cint.gb.secret'];
				}
				elseif ($user['QueryProfile']['country'] == 'CA') {
					$api_key = $settings['cint.ca.key'];
					$api_secret = $settings['cint.ca.secret'];
				}
				else {
					continue;
				}
			
				$http = new HttpSocket(array(
					'timeout' => 2,
					'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
				));
				$http->configAuth('Basic', $api_key, $api_secret);
				$panelists_url = $this->Cint->api_url($settings['cint.host'], $http, 'panelists', $api_key);
		
				// First get the cint user if that exist
				$cint_user = $this->Cint->user($http, $user['User']['id'], $panelists_url);
				if (defined('CINT_DEBUG') && CINT_DEBUG) {
					CakeLog::write('cint-update', 'Cint User :'. "\n" . print_r($cint_user, true));
				}
			
				// set cint timestamp to null so it can be re-imported
				if (!$cint_user) {
					$this->User->create();
					$this->User->save(array('User' => array(
						'id' =>  $user['User']['id'],
						'cint' => null
					)), false, array('cint'));
				
					$partner_user = $this->PartnerUser->find('first', array(
						'conditions' => array(
							'PartnerUser.user_id' => $user['User']['id'],
							'PartnerUser.partner' => 'cint'
						)
					));
					if ($partner_user) {
						$this->PartnerUser->delete($partner_user['PartnerUser']['id']);
					}
					continue;
				}
			
				$user['QueryProfile']['postal_code'] = trim($user['QueryProfile']['postal_code']); 
				if ($user['QueryProfile']['country'] == 'CA') {
					$user['QueryProfile']['postal_code'] = str_replace(' ', '', $user['QueryProfile']['postal_code']); 
				}
				if ($user['QueryProfile']['country'] == 'GB') {
					list($user['QueryProfile']['postal_code'], $trash) = explode(' ', $user['QueryProfile']['postal_code']); 
				}
				// First update user fields
				$api_user = array(
					'member_id' => $user['User']['id'],
					'first_name' => $this->Cint->sanitize($user['User']['firstname']),
					'last_name' => $this->Cint->sanitize($user['User']['lastname']),
					'email_address' => 'user+' . $user['User']['id'] . '@mintvine.com', // mask emails to partner
					'gender' => $user['QueryProfile']['gender'],
					'postal_code' => $user['QueryProfile']['postal_code'],
					'year_of_birth' => date('Y', strtotime($user['QueryProfile']['birthdate'])),
					'occupation_status' => CintMappings::employment($user['QueryProfile']['employment'], $user['QueryProfile']['country']),
					'education_level' => CintMappings::education($user['QueryProfile']['education'], $user['QueryProfile']['country']),
				);
			
				$user_self_link = '';
				foreach ($cint_user['links'] as $link) {
					if ($link['rel'] == 'self') {
						$user_self_link = $link['href'];
					}
				}
			
				if ($user_self_link) {
					try {
						$results = $http->put($user_self_link, json_encode($api_user), array('header' => array(
							'Accept' => 'application/json',
							'Content-Type' => 'application/json; charset=UTF-8'
						)));
					} catch (Exception $e) {
						echo "Api call failed when updating user_id: " . $user['User']['id'] . " user data!" . "\n";
						continue;
					}
				
					if (defined('CINT_DEBUG') && CINT_DEBUG) {
						CakeLog::write('cint-update', 'User fields Save results :' . "\n" . print_r(json_decode($results, true), true));
					}
				
					if ($results->code == 204) {
						echo "User id: ".$user['User']['id'].", Cint record updated successfully!" . "\n";
					}
					else {
						echo "User id: " . $user['User']['id'] . " not updated. Error code : " . $results->code . "\n";
						print_r($api_user);
						print_r(json_decode($results->body, true));
					}
				}
				else {
					echo "User id: ".$user['User']['id']." self link not found!"."\n";
				}
			
				$this->User->create();
				$this->User->save(array('User' => array(
					'id' =>  $user['User']['id'],
					'cint' => null
				)), false, array('cint'));
				// Update profile fields
				$this->Cint->update_profile($http, $user, $user_self_link, $cint_user);
			}
			if (isset($this->args[0])) {
				break;
			}
		}
	}
	
	public function unset_user_cint_values() {
		$users = $this->User->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'User.cint is not null'
			),
			'fields' => array('User.id')
		));
		$count = count($users);
		$i = 1; 
		foreach ($users as $user) {
			$partner_user = $this->PartnerUser->find('first', array(
				'conditions' => array(
					'PartnerUser.user_id' => $user['User']['id'],
					'PartnerUser.partner' => 'cint',
					'PartnerUser.uid is not null'
				)
			)); 
			if ($partner_user && !empty($partner_user['PartnerUser']['uid'])) {
				$this->User->create();
				$this->User->save(array('User' => array(
					'id' => $user['User']['id'],
					'cint' => null,
					'modified' => false
				)), true, array('cint')); 
				$pct = round(($i / $count), 2) * 100;
				$this->out($user['User']['id'] .'('.$pct.'%)');
				$i++; 
			}
		}
	}
	
	function populate_transaction_cached_names() {
		
		$contain = array(
			'User', 
			'Offer',
			'Project', 
			'Poll',
			'PollStreak',
			'Code',
			'ReferralTransaction' => array(
				'User'
			),
			'PaymentMethod',
			'UserAnalysis'
		);
		$this->Transaction->bindModel(array(
			'hasMany' => array(
				'UserAnalysis' => array(
					'foreignKey' => 'transaction_id',
					'order' => 'UserAnalysis.id DESC'
				)
			)
		));
		$this->Transaction->bindItems(false);
		while (true) {
			$transactions = $this->Transaction->find('all', array(
				'conditions' => array(
					'Transaction.linked_to_id > ' => 0,
					'Transaction.type_id' => array(TRANSACTION_REFERRAL, TRANSACTION_POLL, TRANSACTION_SURVEY, TRANSACTION_OFFER, TRANSACTION_SURVEY_NQ, TRANSACTION_POLL_STREAK, TRANSACTION_CODE),
					'Transaction.linked_to_name' => null,
					'Transaction.deleted' => null,
				),
				'order' => array('Transaction.id' => 'DESC'),
				'contain' => $contain,
				'limit' => 10000,				
			));
			echo 'Records Found('.count($transactions).')';
			echo PHP_EOL;
			if ($transactions) {
				foreach ($transactions as $transaction) {
					echo 'Processing Started...#'.$transaction['Transaction']['id'];
					echo PHP_EOL;
					if ($transaction['Transaction']['type_id'] == TRANSACTION_REFERRAL) {
						if (!empty($transaction['ReferralTransaction']['User']['username']) && empty($transaction['Transaction']['referer_username'])) {
							$this->Transaction->create();
							$this->Transaction->save(array('Transaction' => array(
								'id' => $transaction['Transaction']['id'],
								'linked_to_name' => $transaction['ReferralTransaction']['name'],
								'referer_username' => $transaction['ReferralTransaction']['User']['username'],
								'updated' => false
							)), false, array('linked_to_name', 'referer_username'));
						}
					}
					elseif ($transaction['Transaction']['type_id'] == TRANSACTION_POLL) {
						if (empty($transaction['Transaction']['linked_to_name'])) {
							$this->Transaction->create();
							$this->Transaction->save(array('Transaction' => array(
								'id' => $transaction['Transaction']['id'],
								'linked_to_name' => $transaction['Poll']['poll_question'],
								'updated' => false						
							)), false, array('linked_to_name'));
						}
					}
					elseif ($transaction['Transaction']['type_id'] == TRANSACTION_POLL_STREAK) {
						if (empty($transaction['Transaction']['linked_to_name'])) {
							$this->Transaction->create();
							$this->Transaction->save(array('Transaction' => array(
								'id' => $transaction['Transaction']['id'],
								'linked_to_name' => $transaction['PollStreak']['poll_question'],
								'updated' => false				
							)), false, array('linked_to_name'));
						}
					}
					elseif ($transaction['Transaction']['type_id'] == TRANSACTION_SURVEY_NQ) {
						if (empty($transaction['Transaction']['linked_to_name'])) {
							$project = $this->Project->find('first', array(
								'fields' => array('Project.survey_name'),
								'conditions' => array(
									'Project.id' => $transaction['Transaction']['linked_to_id']
								),
								'recursive' => -1
							)); 
							$this->Transaction->create();
							$this->Transaction->save(array('Transaction' => array(
								'id' => $transaction['Transaction']['id'],
								'linked_to_name' => $project['Project']['survey_name'],
								'updated' => false			
							)), false, array('linked_to_name'));
						}
					}
					elseif ($transaction['Transaction']['type_id'] == TRANSACTION_SURVEY) {
						if (empty($transaction['Transaction']['linked_to_name'])) {
							$this->Transaction->create();
							$this->Transaction->save(array('Transaction' => array(
								'id' => $transaction['Transaction']['id'],
								'linked_to_name' => $transaction['Project']['survey_name'],
								'updated' => false			
							)), false, array('linked_to_name'));
						}
					}
					elseif ($transaction['Transaction']['type_id'] == TRANSACTION_OFFER) {
						if (empty($transaction['Transaction']['linked_to_name'])) {
							$this->Transaction->create();
							$this->Transaction->save(array('Transaction' => array(
								'id' => $transaction['Transaction']['id'],
								'linked_to_name' => $transaction['Offer']['offer_title'],
								'updated' => false			
							)), false, array('linked_to_name'));
						}
					}
					elseif ($transaction['Transaction']['type_id'] == TRANSACTION_CODE) {
						if (empty($transaction['Transaction']['linked_to_name'])) {
							$this->Transaction->create();
							$this->Transaction->save(array('Transaction' => array(
								'id' => $transaction['Transaction']['id'],
								'linked_to_name' => $transaction['Code']['code'],
								'updated' => false	
							)), false, array('linked_to_name'));
						}
					}
					echo 'Processing Completed...#'.$transaction['Transaction']['id'];
					echo PHP_EOL;
				}				
			}
			else {
				echo 'Done.';
				echo PHP_EOL;
				break;
			}
		}
	}
	
	function sync_ssi_users() {
		App::import('Model', 'SsiUser');
		$this->SsiUser = new SsiUser;
		ini_set('memory_limit', '1024M');
		$ssi_users = $this->SsiUser->find('all', array(
			'recursive' => -1
		));
		echo 'found ' . count($ssi_users);
		if ($ssi_users) {
			foreach ($ssi_users as $ssi_user) {
				$this->SsiUser->delete($ssi_user['SsiUser']['id']);
				$this->PartnerUser->create();
				$this->PartnerUser->save(array('PartnerUser' => array(
					'user_id' => $ssi_user['SsiUser']['user_id'],
					'status' => $ssi_user['SsiUser']['status'],
					'partner' => 'ssi'
				)));
				echo 'Synced ' . $ssi_user['SsiUser']['id'];
			}
		}
	}
	
	public function set_recontact_flag() {
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.recontact_id is not null'
			),
			'recursive' => -1,
			'fields' => array('Project.id', 'Project.recontact_id')
		));
		foreach ($projects as $project) {
			
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['recontact_id'],
				'has_recontact_project' => true,
				'modified' => false
			)), array(
				'fieldList' => array('has_recontact_project'),
				'callbacks' => false,
				'validate' => false
			));	
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['recontact_id'],
				'type' => 'survey.updated.recontact',
				'description' => 'Recontact project set-up at #'.$project['Project']['id'].' (This text generated from from a repair script to support this new feature)'
			)));
		}
	}
	
	function fix_mobile_numbers() {
		$users = $this->User->find('all', array(
			'conditions' => array(
				'User.mobile_number !=' => null
			),
			'recursive' => -1,
			'fields' => array(
				'User.mobile_number', 'User.id'
			)
		));
		
		if ($users) {
			$settings = $this->Setting->find('list', array(
				'fields' => array('Setting.name', 'Setting.value'),
				'conditions' => array(
					'Setting.name' => array('twilio.account_sid', 'twilio.auth_token', 'twilio.phone_number', 'twilio.verification_template'),
					'Setting.deleted' => false
				)
			));
					
			App::import('Vendor', 'Twilio', array(
				'file' => 'Twilio' . DS . 'Twilio.php'
			));
			$this->out('Found users with mobile number: ' . count($users));
			foreach ($users as $user) {
				try {
					$twilio_number = $this->TwilioNumber->find('first', array(
						'conditions' => array(
							'TwilioNumber.number' => $user['User']['mobile_number']
						),
						'recursive' => -1
					));
					if (!$twilio_number) {
						$client = new Lookups_Services_Twilio($settings['twilio.account_sid'], $settings['twilio.auth_token']);
						$number = $client->phone_numbers->get($user['User']['mobile_number'], array("CountryCode" => "US", "Type" => "carrier"));			
						$mobile_number = $number->phone_number;	
						if (strtoupper($number->country_code) == 'US') {
							$caller = $client->phone_numbers->get($user['User']['mobile_number'], array( "Type" => "caller-name"));
							$caller_name = $caller->caller_name->caller_name;
							$caller_type = $caller->caller_name->caller_type;
						}
						$this->TwilioNumber->create();
						$this->TwilioNumber->save(array('TwilioNumber' => array(
							'number' => $mobile_number,
							'mobile_country_code' => $number->carrier->mobile_country_code,
							'mobile_network_code' => $number->carrier->mobile_network_code,
							'name' => $number->carrier->name,
							'type' => $number->carrier->type,
							'country_code' => $number->country_code,
							'caller_name' => $caller_name,
							'caller_type' => $caller_type,
							'national_format' => $number->national_format
						)));
					}
					else {						
						$this->out('Already exists: ' . $user['User']['mobile_number']);
					}
				}
				catch (Exception $e) { 					
					$this->out('Twilio error: ' . $e->getMessage());
				}
			}
		}
		
	}
	
	function fix_twilio_mobile_numbers() {
		$twilio_numbers = $this->TwilioNumber->find('all', array(
			'fields' => array('id', 'number'),
			'conditions' => array(
				'name' => ''
			)
		));
		$this->out('Found twilio numbers: ' . count($twilio_numbers));
		if ($twilio_numbers) {
			$settings = $this->Setting->find('list', array(
				'fields' => array('Setting.name', 'Setting.value'),
				'conditions' => array(
					'Setting.name' => array('twilio.account_sid', 'twilio.auth_token', 'twilio.phone_number', 'twilio.verification_template'),
					'Setting.deleted' => false
				)
			));
					
			App::import('Vendor', 'Twilio', array(
				'file' => 'Twilio' . DS . 'Twilio.php'
			));
			foreach ($twilio_numbers as $twilio_number) {
				$this->out('Processing: ' . $twilio_number['TwilioNumber']['number']);
				try {
					$client = new Lookups_Services_Twilio($settings['twilio.account_sid'], $settings['twilio.auth_token']);
					$number = $client->phone_numbers->get($twilio_number['TwilioNumber']['number'], array("CountryCode" => "US", "Type" => "carrier"));			
					$mobile_number = $number->phone_number;	
					if (strtoupper($number->country_code) == 'US') {
						$caller = $client->phone_numbers->get($twilio_number['TwilioNumber']['number'], array( "Type" => "caller-name"));
						$caller_name = $caller->caller_name->caller_name;
						$caller_type = $caller->caller_name->caller_type;
					}
					$this->TwilioNumber->create();					
					$this->TwilioNumber->save(array('TwilioNumber' => array(
						'id' => $twilio_number['TwilioNumber']['id'],						
						'mobile_country_code' => $number->carrier->mobile_country_code,
						'mobile_network_code' => $number->carrier->mobile_network_code,
						'name' => $number->carrier->name,
						'type' => $number->carrier->type,
						'country_code' => $number->country_code,
						'caller_name' => $caller_name,
						'caller_type' => $caller_type,
						'national_format' => $number->national_format
					)), false, array('mobile_country_code', 'mobile_network_code', 'name', 'type', 'country_code', 'national_format', 'caller_name', 'caller_type'));
					$this->out('Processed: ' . $twilio_number['TwilioNumber']['number']);
				}
				catch (Exception $e) {
					$this->out('Twilio Error: ' . $e->getMessage());
				}
			}
		}
	}
	
	function fix_mobiles() {
		$this->User->bindModel(array('hasOne' => array(
			'QueryProfile'
		)));
		$users = $this->User->find('all', array(
			'conditions' => array(
				'User.mobile_number !=' => null
			),
			'recursive' => -1,
			'fields' => array(
				'User.mobile_number', 'User.id', 'QueryProfile.country'
			),
			'contain' => array('QueryProfile')
		));
		
		if ($users) {
			$settings = $this->Setting->find('list', array(
				'fields' => array('Setting.name', 'Setting.value'),
				'conditions' => array(
					'Setting.name' => array('twilio.account_sid', 'twilio.auth_token', 'twilio.phone_number', 'twilio.verification_template', 'twilio.lookup.endpoint'),
					'Setting.deleted' => false
				)
			));
			
			$this->out('Found users with mobile number: ' . count($users));
			foreach ($users as $user) {
				$this->out('Processing: ' . $user['User']['mobile_number']);
				try {	
					$country_code = $user['QueryProfile']['country'];
					if ($user['QueryProfile']['country'] == 'GB') {
						$formatted_mobile_number = preg_replace('~.*(\d{2})[^\d]*(\d{4})[^\d]*(\d{4}).*~', '$1-$2-$3', $user['User']['mobile_number']);
						$country_code = 'GB';
					}
					else {
						$formatted_mobile_number = preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '$1-$2-$3', $user['User']['mobile_number']);
					}					
					$twilio_number = $this->TwilioNumber->find('first', array(
						'conditions' => array(
							'TwilioNumber.number' => $formatted_mobile_number
						),
						'recursive' => -1
					));					
					if (!$twilio_number) {
						$HttpSocket = new HttpSocket(array(
							'timeout' => 15,
							'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
						));
						$HttpSocket->configAuth('Basic', $settings['twilio.account_sid'], $settings['twilio.auth_token']);
						$results = $HttpSocket->get($settings['twilio.lookup.endpoint'] . $user['User']['mobile_number'], array('Type' => 'carrier', 'CountryCode' => $country_code));						
						if ($results->code == '200') {
							$carrier_data = json_decode($results->body, true);
							$mobile_number = $carrier_data['phone_number'];
							$caller_name = null;
							$caller_type = null;
							$type = $carrier_data['carrier']['type'];
							if (strtoupper($carrier_data['country_code']) == 'US') {
								$results = $HttpSocket->get($settings['twilio.lookup.endpoint'] . $user['User']['mobile_number'], array('Type' => 'caller-name', 'CountryCode' => $country_code));
								if ($results->code == 200) {											
									$caller_data = json_decode($results->body, true);
									$caller_name = !empty($caller_data['caller_name']['caller_name']) ? $caller_data['caller_name']['caller_name'] : null;
									$caller_type = !empty($caller_data['caller_name']['caller_type']) ? $caller_data['caller_name']['caller_type'] : null;
								}
							}									
							$twilio_number_to_save = array(
								'number' => $formatted_mobile_number,
								'mobile_country_code' => $carrier_data['carrier']['mobile_country_code'],
								'mobile_network_code' => $carrier_data['carrier']['mobile_network_code'],
								'name' => $carrier_data['carrier']['name'],
								'caller_name' => $caller_name,
								'caller_type' => $caller_type,
								'type' => $carrier_data['carrier']['type'],
								'national_format' => $carrier_data['national_format'],
								'phone_number' => $carrier_data['phone_number'],
								'country_code' => $carrier_data['country_code']
							);
							if ($twilio_number) {
								$twilio_number_to_save['id'] = $twilio_number['TwilioNumber']['id'];							
							}
							$this->TwilioNumber->create();
							$this->TwilioNumber->save(array('TwilioNumber' => $twilio_number_to_save), true, array(array_keys($twilio_number_to_save)));
							$this->out('Processed: ' . $user['User']['mobile_number']);
						}	
					}
					else {
						$this->out('Already Exists: ' . $user['User']['mobile_number']);
					}
				}
				catch (Exception $e) { 					
					$this->out('Twilio error: ' . $e->getMessage());
				}
			}
		}
		
	}
	
	function sync_toluna_users() {
		ini_set('memory_limit', '1024M');
		$conditions = array(
			'User.deleted' => false,
			'User.toluna !=' => null
		);	
		
		$count = $this->User->find('count', array(
			'conditions' => $conditions,
			'recursive' => -1
		));
		
		if (isset($this->args[0])) {
			$conditions = array('User.id' => $this->args[0]);
		}
		
		$this->out('Found '.$count.' users to convert');
		$last_id = 0;
		$i = 0;
		while (true) {
			$users = $this->User->find('all', array(
				'fields' => array('User.id', 'User.firstname', 'User.lastname', 'User.toluna'),
				'conditions' => $conditions,
				'order' => 'User.id ASC',
				'limit' => 10000
			));
			if (!$users) {
				break;
			}
			$this->out('---------------------');
			$this->out('Starting '.count($users));
			foreach ($users as $user) {
				$i++;
				$this->out($i.'/'.$count.': Converting '.$user['User']['id'].'...');
				$partner_user = $this->PartnerUser->find('first', array(
					'conditions' => array(
						'PartnerUser.user_id' => $user['User']['id'],
						'PartnerUser.partner' => 'toluna'
					)
				));
				if ($partner_user) {
					$this->out($i.'/'.$count.': ... Exists');					
					$this->User->create();
					$this->User->save(array('User' => array(
						'id' => $user['User']['id'],
						'toluna' => null
					)), false, array('toluna'));	
					continue;
				}
				$this->out($i.'/'.$count.':  ... Migrated');
				$this->PartnerUser->create();	
				$this->PartnerUser->save(array('PartnerUser' => array(
					'user_id' => $user['User']['id'],
					'last_exported' => $user['User']['toluna'],
					'partner' => 'toluna'
				)));
				$this->User->create();
				$this->User->save(array('User' => array(
					'id' => $user['User']['id'],
					'toluna' => null
				)), false, array('toluna'));		
			}
		}
		$this->out('Completed');
	}
	
	function sync_precision_users() {
		ini_set('memory_limit', '1024M');
		App::import('Model', 'PrecisionUser');
		$this->PrecisionUser = new PrecisionUser;
		$count = $this->PrecisionUser->find('count', array(		
			'recursive' => -1
		));
		
		$this->out('Found '.$count.' users to convert');
		$last_id = 0;
		$i = 0;
		while (true) {
			$precision_users = $this->PrecisionUser->find('all', array(
				'recursive' => -1,
				'limit' => 10000,
				'conditions' => array(
					'PrecisionUser.id > ' => $last_id
				)
			));
			if ($precision_users) {
				$this->out('Operating on chunk of '.count($precision_users)); 
				foreach ($precision_users as $precision_user) {					
					$this->out($i.'/'.$count.': Converting '.$precision_user['PrecisionUser']['user_id'].'...');
					$last_id = $precision_user['PrecisionUser']['id'];
					$partner_user = $this->PartnerUser->find('first', array(
						'conditions' => array(
							'PartnerUser.user_id' => $precision_user['PrecisionUser']['user_id'],
							'PartnerUser.uid' => $precision_user['PrecisionUser']['uid'],
							'PartnerUser.partner' => 'precision'
						)
					));
					if (!$partner_user) {
						$i++;
						$this->out($i.'/'.$count.': ... Do not Exists');						
						$this->PartnerUser->create();
						$this->PartnerUser->save(array('PartnerUser' => array(
							'uid' => $precision_user['PrecisionUser']['uid'],
							'user_id' => $precision_user['PrecisionUser']['user_id'],
							'last_exported' => $precision_user['PrecisionUser']['last_exported'],
							'last_survey_retrieve' => $precision_user['PrecisionUser']['last_survey_retrieve'],
							'partner' => 'precision'
						)));
						$this->out('Migrated...#' .$precision_user['PrecisionUser']['user_id'] );
					}
					else {
						$this->out('Already exists...#' .$precision_user['PrecisionUser']['user_id'] );
					}
				}
				$this->out($i.'/'.$count.': Converted ');
			}
			else {
				break;
			}
		}	
	}
	
	// look in group for open, staging, imported projects and set nq award value
	public function update_nq_awards() {
		if (!isset($this->args[0])) {
			$this->out('Please define a group ID');
			return false;
		}
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Project.nq_award', 'Project.award'),
			'conditions' => array(
				'Project.group_id' => $this->args[0],
				'Project.status' => array(PROJECT_STATUS_OPEN, PROJECT_STATUS_SAMPLING, PROJECT_STATUS_STAGING)
			),
			'recursive' => -1
		));
		if (!$projects) {
			$this->out('No projects found');
			return false;
		}
		foreach ($projects as $project) {
			$nq_award = floor($project['Project']['award'] / 20);
			if ($nq_award < 1) {
				$nq_award = 1; 
			}
			if ($nq_award > 5) {
				$nq_award = 5; 
			}
			if ($nq_award != $project['Project']['nq_award']) {
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'nq_award' => $nq_award
				)), true, array('nq_award'));
				$this->out('Updating #'.$project['Project']['id'].' ('.$project['Project']['award'].') from '.$project['Project']['nq_award'].' to '.$nq_award);
			}
		}
	}
	
	public function migrate_payouts() {
		$this->Project->unbindModel(array(
			'hasMany' => array('SurveyPartner', 'ProjectOption'),
			'hasOne' => array('SurveyVisitCache'),
			'belongsTo' => array('Client', 'Group'),
		));
		$this->Project->bindModel(array(
			'belongsTo' => array('ProjectRate'),
		));
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Project.client_rate', 'Project.award', 'ProjectRate.*'),
			'conditions' => array(
				'Project.project_rate_id is not null'
			)
		));
		
		if (!$projects) {
			$this->out('Projects not found');
			return;
		}
		
		$this->out('Updating '.count($projects).' projects');
		
		foreach ($projects as $project) {
			if (empty($project['ProjectRate']['id'])) {
				$this->out('Project #'. $project['Project']['id']. ' project rate record not found.');
				continue;
			}
			
			if ($project['Project']['client_rate'] == $project['ProjectRate']['client_rate'] && $project['Project']['award'] == $project['ProjectRate']['award']) {
				$this->out('Project #'.$project['Project']['id'].' payouts upto date');
				continue;
			}
			
			CakeLog::write('project.rates', print_r($project, true));
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'client_rate' => $project['ProjectRate']['client_rate'],
				'award' => $project['ProjectRate']['award'],
				'modified' => false
			)), array(
				'callbacks' => false
			), array(
				'client_rate', 
				'award'
			));
			
			$message = 'Project #'.$project['Project']['id']. ' payouts updated as:'."\n".
				'prev client_rate: '. $project['Project']['client_rate']."\n".
				'New client_rate: '. $project['ProjectRate']['client_rate']."\n".
				'prev award: '. $project['Project']['award']."\n".
				'New award: '. $project['ProjectRate']['award']."\n";
			echo $message;
			CakeLog::write('project.rates', $message);
		}
	}
	
	function move_p2s_own_group() {
		$p2s_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'p2s'
			)
		));
		
		if (!$p2s_group) {
			$this->out('Missing P2S Group');
			return false;
		}
		
		$client = $this->Client->find('first', array(
			'conditions' => array(
				'Client.param_type' => 'points2shop'
			)
		));
		
		if (!$client) {
			$this->out('Missing P2S client');
			return false;
		}
		
		$count = $this->Project->find('count', array(
			'conditions' => array(
				'Project.client_id' => $client['Client']['id'],
				'Project.group_id' => null
			)		
		));
		
		$this->out('Found '.$count.' Points2Shop projects to move into its own group');
		$last_id = 0;
		$i = 0;		
		while (true) {
			$p2s_projects = $this->Project->find('all', array(
				'conditions' => array(
					'Project.client_id' => $client['Client']['id'],
					'Project.group_id' => null,
					'Project.id > ' => $last_id
				),
				'recursive' => -1,
				'fields' => array(
					'Project.id', 'Project.client_id', 'Project.group_id'
				),
				'order' => array('Project.id' => 'ASC'),
				'limit' => 10000
			));
			
			if ($p2s_projects) {
				$this->out('Operating on chunk of '.count($p2s_projects)); 
				foreach ($p2s_projects as $p2s_project) {	
					$i++;				
					$this->out($i.'/'.$count.': Moving '.$p2s_project['Project']['id'].'...');
					$last_id = $p2s_project['Project']['id'];
					
					$this->Project->create();
					$this->Project->save(array('Project' => array(
						'id' => $last_id,
						'group_id' => $p2s_group['Group']['id'],
						'modified' => false
					)), array(
						'callbacks' => false,
						'validate' => false,
						'fieldList' => array('group_id')
					));
					
					$this->out('Moved...#' .$p2s_project['Project']['id']);
				}
				$this->out($i.'/'.$count.': Moved.');
			}
			else {
				break;
			}
		}
	}
	
	public function make_all_admins() {
		$admins = $this->Admin->find('list', array(
			'fields' => array('Admin.id', 'Admin.id')
		));
		if (empty($admins)) {
			return false;
		}
		foreach ($admins as $admin_id) {
			$this->AdminRole->create();
			$this->AdminRole->save(array('AdminRole' => array(
				'admin_id' => $admin_id,
				'role_id' => 1
			)));
		}
	}
	
	public function migrate_mintvine_projects() {
		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'mintvine'
			)
		));
		if (!$group) {
			$this->out('You must create a MintVine group');
			return false;
		}
		$this->Project->updateAll(array('Project.group_id' => $group['Group']['id']), array('Project.group_id' => null)); 
		$this->Admin->updateAll(array('Admin.group_id' => $group['Group']['id']), array('Admin.group_id' => null)); 
		$this->Client->updateAll(array('Client.group_id' => $group['Group']['id']), array('Client.group_id' => null)); 
		
	}
	
	public function renumber_points2shop_masks() {
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'p2s'
			)
		));
		
		$projects = $this->Project->find('list', array(
			'fields' => array('Project.id', 'Project.prj_name'),
			'conditions' => array(
				'Project.group_id' => $group['Group']['id']
			)
		));
		foreach ($projects as $project_id => $project_name) {
			if (strpos($project_name, 'extended') !== false) {
				continue;
			}
			$number = str_replace('Points2Shop Router ', '', $project_name);
			$this->out($project_name.' '.$number); 
			
			if (empty($number)) {
				continue;
			}
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project_id,
				'mask' => $number,
				'modified' => false
			)), true, array('mask')); 
		}
	}
	
	public function project_admins() {
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Project.created_by_id', 'Project.account_manager'),
			'conditions' => array(
				'OR' => array(
					'Project.created_by_id <>' => null,
					'Project.account_manager <>' => null
				)
			),
			'recursive' => -1
		));
		foreach ($projects as $project) {
			$project_admin = array();
			if (!empty($project['Project']['created_by_id'])) {
				$this->ProjectAdmin->create();
				$this->ProjectAdmin->save(array('ProjectAdmin' => array(
					'project_id' => $project['Project']['id'],
					'admin_id' => $project['Project']['created_by_id']
				)));
				$this->out('Project #'. $project['Project']['id']. ' data added admin '.$project['Project']['created_by_id']);
			}
			if (!empty($project['Project']['account_manager'])) {
				$this->ProjectAdmin->create();
				$this->ProjectAdmin->save(array('ProjectAdmin' => array(
					'project_id' => $project['Project']['id'],
					'admin_id' => $project['Project']['account_manager']
				)));
				$this->out('Project #'. $project['Project']['id']. ' data added admin '.$project['Project']['account_manager']);
			}
		}
	}
	
	public function add_groups_to_partners() {
		$mintvine_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'mintvine'
			)
		));
		if (!$mintvine_group) {
			$this->out('Could not find MintVine group'); 
			return false;
		}
		
		$partners = $this->Partner->find('all', array(
			'fields' => array('Partner.id', 'Partner.group_id'),
			'conditions' => array(
				'Partner.group_id' => '0'
			)
		));
		if ($partners) {
			foreach ($partners as $partner) {
				$this->Partner->create();
				$this->Partner->save(array('Partner' => array(
					'id' => $partner['Partner']['id'],
					'group_id' => $mintvine_group['Group']['id']
				)), true, array('group_id'));
			}
			$this->out('Updated '.count($partners).' to group '.$mintvine_group['Group']['id']);
		}
		
		$survey_partners = $this->SurveyPartner->find('all'); 
		if ($urvey_partners) {
			foreach ($survey_partners as $survey_partner) {
				$project = $this->Project->find('first', array(
					'fields' => array('Project.group_id'),
					'conditions' => array(
						'Project.id' => $survey_partner['SurveyPartner']['survey_id']
					),
					'recursive' => -1
				));
				// if we're in a different partner
				if ($project && $project['Project']['group_id'] != $survey_partner['Partner']['group_id']) {
					$group_partner = $this->Partner->find('first', array(
						'fields' => array('Partner.id'),
						'conditions' => array(
							'Partner.name' => $survey_partner['Partner']['name'],
							'Partner.group_id' => $project['Project']['group_id']
						)
					));
					if (!$group_partner) {
						$this->Partner->create();
						$this->Partner->save(array('Partner' => array(
							'group_id' => $project['Project']['group_id'],
							'partner_name' => $survey_partner['Partner']['partner_name'],
							'key' => $survey_partner['Partner']['key'],
							'security' => $survey_partner['Partner']['security'],
							'notes' => $survey_partner['Partner']['notes'],
							'date_created' => date(DB_DATETIME)
						)));
						$partner_id = $this->Partner->getInsertId();
						$this->out('Created new group '.$survey_partner['Partner']['partner_name'].' in '.$project['Project']['group_id']); 
					} 
					else {
						$partner_id = $group_partner['Partner']['id'];
					}
					$this->SurveyPartner->create();
					$this->SurveyPartner->save(array('SurveyPartner' => array(
						'id' => $survey_partner['SurveyPartner']['id'],
						'partner_id' => $partner_id,
						'modified' => false
					)), true, array('partner_id'));
					$this->out('Updated '.$survey_partner['SurveyPartner']['id'].' from '.$survey_partner['SurveyPartner']['partner_id'].' to '.$partner_id);
				}
			}
		}
	}
	
	/* am/pm are visual distinctions; they cannot be derived from roles */
	public function am_pm() {
		$this->ProjectAdmin->bindModel(array('belongsTo' => array('Admin')));
		$this->ProjectAdmin->Admin->AdminRole->bindModel(array('belongsTo' => array('Role')));
		$project_admins = $this->ProjectAdmin->find('all', array(
			'conditions' => array(
				'ProjectAdmin.is_am' => false, 
				'ProjectAdmin.is_pm' => false
			),
			'contain' => array(
				'Admin' => array(
					'AdminRole' => array(
						'Role'
					)
				)
			)					
		));
		if (!$project_admins) {
			$this->out('No Project Admins remaining');
			return false;
		}
		foreach ($project_admins as $project_admin) {
			$is_pm = $is_am = false;
			if (empty($project_admin['Admin']['AdminRole']) || !isset($project_admin['Admin']['AdminRole'])) {
				continue;
			}
			foreach ($project_admin['Admin']['AdminRole'] as $admin_role) {
				// admins + project managers
				if ($admin_role['Role']['admin'] || ($admin_role['Role']['projects'] && $admin_role['Role']['reports'])) {
					$is_pm = true;
					$is_am = false;
				}
				else {
					$is_pm = false;
					$is_am = true;
				}
				$this->ProjectAdmin->create();
				$this->ProjectAdmin->save(array('ProjectAdmin' => array(
					'id' => $project_admin['ProjectAdmin']['id'],
					'is_am' => $is_am,
					'is_pm' => $is_pm,
					'modified' => false
				)), true, array('is_am', 'is_pm'));
				$this->out($project_admin['ProjectAdmin']['id'].' am: '.($is_am ? 't': 'f').' pm: '.($is_pm ? 't': 'f')); 
			}
		}
	}
	
	/* This task is used to truncate nonces once a week - because innodb doesn't really clear space on delete, we need to truncate */
	public function truncate_nonces() {
		ini_set('memory_limit', '2048M');
		$nonces = $this->Nonce->find('all', array(
			'fields' => array('Nonce.item_id', 'Nonce.item_type', 'Nonce.user_id', 'Nonce.nonce', 'Nonce.created'),
			'conditions' => array(
				'Nonce.used is null',
				'Nonce.created >' => date(DB_DATETIME, strtotime('-2 days'))
			),
			'recursive' => -1,
			'order' => 'Nonce.id DESC'
		));
		$total = count($nonces);
		$this->out('Migrating '.$total); 
		$this->Nonce->query('truncate table nonces'); 
		$this->out('Truncated table');
		$i = 1; 
		if ($nonces) {
			foreach ($nonces as $nonce) {
				$this->Nonce->create();
				$this->Nonce->save(array('Nonce' => array(
					'item_id' => $nonce['Nonce']['item_id'],
					'item_type' => $nonce['Nonce']['item_type'],
					'user_id' => $nonce['Nonce']['user_id'],
					'nonce' => $nonce['Nonce']['nonce'],
					'created' => $nonce['Nonce']['created'],
				)));
				$this->out($i.' / '.$total); 
				$i++;
			}
		}
		$this->out('Completed');
	}
	
	public function set_poll_publish_date() {
		$polls = $this->Poll->find('all', array(
			'conditions' => array(
				'Poll.id <=' => '808'
			),
			'recursive' => -1
		));
		foreach ($polls as $poll) {
			$date = strtotime($poll['Poll']['publish_date']); 
			$date = $date - 86400;
			$publish_date = date(DB_DATE, $date); 
			$this->Poll->create();
			$this->Poll->save(array('Poll' => array(
				'id' => $poll['Poll']['id'],
				'publish_date' => $publish_date,
				'modified' => false
			)), true, array('publish_date')); 
			$this->out($poll['Poll']['id'].' to '.$publish_date);
		}
	}
	
	public function remove_query_profile_dupes() {
		$query_profiles = $this->QueryProfile->query('select user_id,count(*) from query_profiles group by user_id having count(*) > 1'); 
		if (!empty($query_profiles)) {
			foreach ($query_profiles as $query_profile) {
				$user_id = $query_profile['query_profiles']['user_id'];
				$older = $this->QueryProfile->find('first', array(
					'conditions' => array(
						'QueryProfile.user_id' => $user_id
					),
					'order' => 'QueryProfile.id ASC'
				)); 
				if ($older) {
					$this->QueryProfile->delete($older['QueryProfile']['id']);
					$this->out('Deleted '.$older['QueryProfile']['id']); 
				}
			}
		}
		$query_profiles = $this->QueryProfile->find('all', array(
			'fields' => array('QueryProfile.id'),
			'conditions' => array(
				'QueryProfile.user_id' => '0'
			)
		));
		if ($query_profiles) {
			foreach ($query_profiles as $query_profile) {
				$this->QueryProfile->delete($query_profile['QueryProfile']['id']);
			}
		}
	}
	
	/* This task is used to truncate nonces once a week - because innodb doesn't really clear space on delete, we need to truncate */
	public function truncate_lucid_queues() {
		ini_set('memory_limit', '2048M');
		$lucid_queues = $this->LucidQueue->find('all', array(
			'fields' => array('LucidQueue.worker', 'LucidQueue.amazon_queue_id', 'LucidQueue.fed_survey_id', 'LucidQueue.survey_id', 'LucidQueue.command', 'LucidQueue.created', 'LucidQueue.modified'),
			'conditions' => array(
				'LucidQueue.executed is null',
				'LucidQueue.created >' => date(DB_DATETIME, strtotime('-2 days')),
				'LucidQueue.amazon_queue_id is not null',
			),
			'recursive' => -1,
			'order' => 'LucidQueue.id DESC'
		));
		$total = count($lucid_queues);
		$this->out('Migrating '.$total); 
		$this->LucidQueue->query('truncate table lucid_queues'); 
		$this->out('Truncated table');
		$i = 1; 
		if ($lucid_queues) {
			foreach ($lucid_queues as $lucid_queue) {
				$this->LucidQueue->create();
				$this->LucidQueue->save(array('LucidQueue' => array(
					'worker' => $nonce['LucidQueue']['worker'],
					'amazon_queue_id' => $nonce['LucidQueue']['amazon_queue_id'],
					'fed_survey_id' => $nonce['LucidQueue']['fed_survey_id'],
					'survey_id' => $nonce['LucidQueue']['survey_id'],
					'command' => $nonce['LucidQueue']['command'],
					'created' => $nonce['LucidQueue']['created'],
					'modified' => $nonce['LucidQueue']['modified'],
				)));
				$this->out($i.' / '.$total); 
				$i++;
			}
		}
		$this->out('Completed');
	}
	
	
	public function migrate_verification_flags_on_twilio_numbers() {
		App::import('Model', 'TwilioNumber');
		$this->TwilioNumber = new TwilioNumber; 

		$this->User->bindModel(array('hasOne' => array(
			'QueryProfile'
		)));
		$users = $this->User->find('all', array(
			'fields' => array('User.id', 'User.mobile_number', 'User.is_mobile_verified', 'QueryProfile.country'),
			'conditions' => array(
				'User.mobile_number is not null',
				'User.twilio_number_id is null'
			),
		));

		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('twilio.account_sid', 'twilio.attempts_allowed', 'twilio.auth_token', 'twilio.phone_number', 'twilio.verification_template', 'twilio.sms.endpoint', 'twilio.lookup.endpoint'),
				'Setting.deleted' => false
			)
		));
		
		foreach ($users as $user) {

			$user['User']['mobile_number'] = preg_replace("/[^0-9+]+/", "", $user['User']['mobile_number']);
			if ($user['QueryProfile']['country'] == 'GB') {
				$formatted_mobile_number = preg_replace('~.*(\d{2})[^\d]*(\d{4})[^\d]*(\d{4}).*~', '$1-$2-$3', $user['User']['mobile_number']);
				$country_code = 'GB';
			}
			elseif ($user['QueryProfile']['country'] == 'CA') {
				$formatted_mobile_number = '+1'.preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '$1$2$3', $$user['User']['phone_number']);
				$country_code = $user['QueryProfile']['country'];
			}
			elseif ($user['QueryProfile']['country'] == 'US') {
				$formatted_mobile_number = preg_replace('~.*(\d{3})[^\d]*(\d{3})[^\d]*(\d{4}).*~', '$1-$2-$3', $$user['User']['phone_number']);
				$country_code = $user['QueryProfile']['country'];
			}
			
			$twilio_number = $this->TwilioNumber->find('first', array(
				'conditions' => array(
					'TwilioNumber.number' => $formatted_mobile_number
				)
			));
			
			if (!$twilio_number) {
				$HttpSocket = new HttpSocket(array(
					'timeout' => 15,
					'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
				));
				$HttpSocket->configAuth('Basic', $settings['twilio.account_sid'], $settings['twilio.auth_token']);
				$results = $HttpSocket->get($settings['twilio.lookup.endpoint'] . $formatted_mobile_number, array(
					'Type' => 'carrier', 
					'CountryCode' => $country_code
				));						
				
				if ($results->code == '200') {
					$carrier_data = json_decode($results->body, true);
					$mobile_number = $carrier_data['phone_number'];
					$caller_name = null;
					$caller_type = null;
					$type = $carrier_data['carrier']['type'];
					if (strtoupper($carrier_data['country_code']) == 'US') {
						$results = $HttpSocket->get($settings['twilio.lookup.endpoint'] . $formatted_mobile_number, array(
							'Type' => 'caller-name', 
							'CountryCode' => $country_code
						));
						if ($results->code == 200) {											
							$caller_data = json_decode($results->body, true);
							$caller_name = !empty($caller_data['caller_name']['caller_name']) ? $caller_data['caller_name']['caller_name'] : null;
							$caller_type = !empty($caller_data['caller_name']['caller_type']) ? $caller_data['caller_name']['caller_type'] : null;
						}
					}			
					$twilio_number_to_save = array(
						'number' => $formatted_mobile_number,
						'mobile_country_code' => $carrier_data['carrier']['mobile_country_code'],
						'mobile_network_code' => $carrier_data['carrier']['mobile_network_code'],
						'name' => empty($carrier_data['carrier']['name']) ? '': $carrier_data['carrier']['name'],
						'caller_name' => $caller_name,
						'caller_type' => $caller_type,
						'type' => $carrier_data['carrier']['type'],
						'national_format' => $carrier_data['national_format'],
						'phone_number' => $carrier_data['phone_number'],
						'country_code' => $carrier_data['country_code'],
						'verified' => $carrier_data['carrier']['type'] == 'landline' // auto-verify landline numbers for now
					);
					$this->TwilioNumber->create();
					$this->TwilioNumber->save(array('TwilioNumber' => $twilio_number_to_save), true, array(array_keys($twilio_number_to_save)));
					$twilio_id = $this->TwilioNumber->getInsertId();

					$this->User->create();
					$this->User->save(array('User' => array(
						'id' => $user['User']['id'],
						'twilio_number_id' => $twilio_id
					)), true, array('twilio_number_id'));
					$this->out('Linked '.$user['User']['id'].' '.$user['User']['mobile_number']); 
				}
				elseif ($results->code == '404') {

					$this->User->create();
					$this->User->save(array('User' => array(
						'id' => $user['User']['id'],
						'mobile_number' => null,
						'is_mobile_verified' => false,
						'twilio_number_id' => null
					)), true, array('mobile_number', 'is_mobile_verified', 'twilio_number_id'));
					$this->out('Unset '.$user['User']['id'].' '.$user['User']['mobile_number']); 
				}
				else {
					print_r($results);
					exit();
				}
			}
			else {
				$this->User->create();
				$this->User->save(array('User' => array(
					'id' => $user['User']['id'],
					'twilio_number_id' => $twilio_number['TwilioNumber']['id']
				)), true, array('twilio_number_id'));
				
				$this->TwilioNumber->create();
				$this->TwilioNumber->save(array('TwilioNumber' => array(
					'id' => $twilio_number['TwilioNumber']['id'],
					'verified' => $user['User']['is_mobile_verified'],
					'modified' => false
				)), true, array('verified')); 
				$this->out('Updated '.$user['User']['id'].' with '.$twilio_number['TwilioNumber']['id']); 
			}
		}
	}
	
	public function postal_code_extended() {
		$query_profiles = $this->QueryProfile->find('all', array(
			'fields' => array('QueryProfile.id', 'QueryProfile.postal_code'),
			'conditions' => array(
				'QueryProfile.country' => 'US',
				'QueryProfile.postal_code LIKE' => '%-%'
			),
			'recursive' => -1
		));
		foreach ($query_profiles as $query_profile) {
			list($postal_code, $extended_postal_code) = explode('-', $query_profile['QueryProfile']['postal_code']); 
			$this->QueryProfile->create();
			$this->QueryProfile->save(array('QueryProfile' => array(
				'id' => $query_profile['QueryProfile']['id'],
				'postal_code' => $postal_code,
				'postal_code_extended' => $extended_postal_code,
				'modified' => false
			)), true, array('postal_code', 'postal_code_extended')); 
			$this->out($query_profile['QueryProfile']['id']);
		}
	}
	
	public function repair_missing_poll() {
		App::import('Model', 'PollUserAnswer');
		$this->PollUserAnswer = new PollUserAnswer; 
		
		if (!isset($this->args[0])) {
			$this->out('Please specify a poll ID');
			return false;
		}
		
		$poll = $this->Poll->find('first', array(
			'conditions' => array(
				'Poll.id' => $this->args[0]
			),
			'recursive' => -1
		));

		$poll_user_answers = $this->PollUserAnswer->find('all', array(
			'fields' => array('PollUserAnswer.id', 'PollUserAnswer.user_id'),
			'recursive' => -1,
			'conditions' => array(
				'PollUserAnswer.poll_id' => $poll['Poll']['id'],
			)
		));
		$this->out('Processing '.count($poll_user_answers).' answers');
		foreach ($poll_user_answers as $poll_user_answer) {

			$count = $this->Transaction->find('count', array(
				'recursive' => -1,
				'conditions' => array(
					'Transaction.type_id' => TRANSACTION_POLL,
					'Transaction.linked_to_id' => $poll['Poll']['id'],
					'Transaction.user_id' => $poll_user_answer['PollUserAnswer']['user_id'],
					'Transaction.paid' => true,
					'Transaction.status' => TRANSACTION_APPROVED,
					'Transaction.deleted' => null,
				)
			));
			if ($count == 0) {
				$this->Transaction->create();
				$transaction_saved = $this->Transaction->save(array('Transaction' => array(
					'type_id' => TRANSACTION_POLL,
					'linked_to_id' => $poll['Poll']['id'],
					'linked_to_name' => $poll['Poll']['poll_question'],
					'user_id' => $poll_user_answer['PollUserAnswer']['user_id'],
					'amount' => $poll['Poll']['award'],
					'paid' => true,
					'name' => 'Poll Completion - '.$poll['Poll']['poll_question'],
					'status' => TRANSACTION_APPROVED,
					'executed' => date(DB_DATETIME)
				)));
				$this->out('Paying '.$poll_user_answer['PollUserAnswer']['user_id'].' for missing poll transaction');
			}
		}
	}
	
	// clean up cint dmas from dupes
	public function cleanup_cint_dmas() {
		App::import('Model', 'CintRegion');
		$this->CintRegion = new CintRegion;
		
		$cint_regions = $this->CintRegion->find('all', array(
			'fields' => array(
				'id', 'cint_id'
			),
			'group' => array('cint_id having count(cint_id) > 1')
		));
		$this->out('Duplicate found ('.count($cint_regions).')');
		if (!empty($cint_regions)) {
			foreach ($cint_regions as $key => $cint_region) {
				$cint_del_regions = $this->CintRegion->find('all', array(
					'fields' => array('id'),
					'conditions' => array(
						'cint_id' => $cint_region['CintRegion']['cint_id'],
						'country' => ''
					)
				));
				foreach ($cint_del_regions as $key => $cint_del_region) {
					$this->CintRegion->delete($cint_del_region['CintRegion']['id']);
					$this->out("Deleted CintRegion id = ".$cint_del_region['CintRegion']['id']." because of duplicates!" . "\n"); 
				}	
			}
		}
	}
	
	function create_survey_codes() {
		ini_set('memory_limit', '2048M');
		$projects = $this->Project->find('all', array(
			'fields' => array('id', 'survey_code', 'date_created'),
			'conditions' => array('survey_code IS NULL'),
			'recursive' => -1
		));
		
		if (!$projects) {
			return;
		}
		
		$this->out('Creating survey code '.count($projects));
		
		$dictionaries = $this->Dictionary->find('all');
		if (!empty($dictionaries)) {
			$colors = $adjectives = $animals = array();
			foreach ($dictionaries as $dictionary) {
				$colors[] = $dictionary['Dictionary']['color'];
				$adjectives[] = $dictionary['Dictionary']['adjective'];
				$animals[] = $dictionary['Dictionary']['animal'];
			}

			foreach ($projects as $project) {
				$created_phrases = array();
				while (true) {
					$color = $colors[mt_rand(0, count($colors) - 1)];
					$adjective = $adjectives[mt_rand(0, count($adjectives) - 1)];
					$animal = $animals[mt_rand(0, count($colors) - 1)];
					
					$phrase = $color.' '.$adjective.' '.$animal;
					if (in_array($phrase, $created_phrases)) {
						continue;
					}
					else {
						// check survey_code uniqueness within last 90 days
						$conditions = array(
							'survey_code' => $phrase, 
							'Project.date_created <=' => $project['Project']['date_created'],
							'Project.date_created >' =>  date('Y-m-d H:i:s', strtotime($project['Project']['date_created'].'-90 days'))
						);
						if (!$this->Project->hasAny($conditions)) {
							break;
						}
						
						$created_phrases[] = $phrase;
					}
				}
				
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project['Project']['id'],
					'survey_code' => $phrase,
					'modified' => false
				)), true, array('survey_code'));
				$this->out('Survey code "'.$phrase.'" created for Project #'.$project['Project']['id']);
			}
		}
		else {
			$this->out('No words found in dictionary');
		}
		
		$this->out('Completed');
	}
	
	function resurrect_users() {
		ini_set('memory_limit', '2048M');
		$this->User->bindModel(array(
			'hasMany' => array(
				'UserOption' => array(
					'className' => 'UserOption',
					'foreignKey' => 'user_id'
				)
			)
		));
		
		CakePlugin::load('Mailgun');
		$email = new CakeEmail();
		$email->config('mailgun');
		$email->from(array(EMAIL_SENDER => 'MintVine'))
			->replyTo(array(REPLYTO_EMAIL => 'MintVine'))
			->emailFormat('html');
		
		$users = $this->User->find('all', array(
			'fields' => array('User.id', 'User.username', 'User.email', 'User.ref_id', 'User.last_touched', 'User.created'),
			'conditions' => array(
				'User.id' => array(1, 2),
				'User.active' => true,
				'User.deleted' => false,
				'OR' => array(
					'User.last_touched is null',
					'User.last_touched <' => date(DB_DATE, strtotime('-90 days'))
				)
			),
			'contain' => array(
				'UserOption' => array(
					'fields' => array('id', 'name', 'value'),
					'conditions' => array(
						'UserOption.name' => array('resurrect.email.date', 'resurrect.email.count')
					)
				),
			)
		));
		
		if ($users) {
			$message = count($users) . ' Users found for first resurrect email'. "\n";
			$this->out($message);
			CakeLog::write('resurrect_users', $message);
			foreach ($users as $user) {
				if (!empty($user['UserOption'])) {
					continue;
				}

				$unsubscribe_link = HOSTNAME_WWW . '/users/emails/' . $user['User']['ref_id'];
				$email->template('resurrection_1')
					->viewVars(array(
						'user_name' => $user['User']['username'], 
						'unsubscribe_link' => $unsubscribe_link
					))
					->to($user['User']['email'])
					->subject('Get your payout faster with MintVine');
				$email->send();
				
				$last_touched_date = (isset($user['User']['last_touched'])) ? $user['User']['last_touched'] : $user['User']['created'];
				$diff = date_diff(date_create($last_touched_date), date_create())->format("%y years %m months and %d days");
				$message = 'User: #'.$user['User']['id'] . ' inactive since ' . $diff . "\n" . 'First Resurrection email has been sent to ' . $user['User']['email']. "\n";
				$this->out($message);
				CakeLog::write('resurrect_users', $message);

				$this->UserOption->create();
				$this->UserOption->saveMany(array('UserOption' => array(
						'user_id' => $user['User']['id'],
						'name' => 'resurrect.email.date',
						'value' => date(DB_DATETIME),
					),
					array('UserOption' => array(
						'user_id' => $user['User']['id'],
						'name' => 'resurrect.email.count',
						'value' => 1,
					))
				));
			}
		}
		
		$user_ids = $this->UserOption->find('list', array(
			'fields' => array('id', 'user_id'),
			'conditions' => array(
				'UserOption.name' => array(
					'resurrect.email.count'
				),
				'UserOption.value' => array(1, 2)
			)
		));
		if (empty($user_ids)) {
			$this->out('Users for resurrect email 2 and 3 not found.');
			return;
		}

		$users = $this->User->find('all', array(
			'fields' => array('User.id', 'User.username', 'User.email', 'User.ref_id', 'User.last_touched', 'User.created'),
			'conditions' => array(
				'User.id' => $user_ids
			),
			'contain' => array(
				'UserOption' => array(
					'fields' => array('id', 'name', 'value'),
					'conditions' => array(
						'UserOption.name' => array('resurrect.email.date', 'resurrect.email.count')
					)
				),
			)
		));
		
		foreach ($users as $user) {
			foreach ($user['UserOption'] as $key => $user_option) {
				$user['UserOption'][$user_option['name']] = $user_option['value'];
				unset($user['UserOption'][$key]);
			}

			if (isset($user['UserOption']['resurrect.email.date']) &&
				$user['User']['last_touched'] < $user['UserOption']['resurrect.email.date'] &&
				strtotime($user['UserOption']['resurrect.email.date'] . ' +7 days') < time()) {
				$current_resurrect_email_count = $user['UserOption']['resurrect.email.count'];
				
				if ($current_resurrect_email_count == 1) {
					$subject = 'When will you redeem your next gift card?';
					
				}
				elseif ($current_resurrect_email_count == 2) {
					$subject = 'Get the best matched surveys for faster rewards.';
				}
				else { // only 2 possible values
					continue;
				}
				
				$unsubscribe_link = HOSTNAME_WWW . '/users/emails/' . $user['User']['ref_id'];
				$email->template('resurrection_'.($current_resurrect_email_count + 1))
					->viewVars(array(
						'user_name' => $user['User']['username'], 
						'unsubscribe_link' => $unsubscribe_link
					))
					->to($user['User']['email'])
					->subject($subject);
				$email->send();
				
				$last_touched_date = (isset($user['User']['last_touched'])) ? $user['User']['last_touched'] : $user['User']['created'];
				$diff = date_diff(date_create($last_touched_date), date_create())->format("%y years %m months and %d days");
				$message = 'User# '.$user['User']['id'] . ' inactive since ' . $diff . "\n" . 'Resurrection email no ' . ($current_resurrect_email_count + 1) . ' has been sent to ' . $user['User']['email']. "\n";
				$this->out($message);
				CakeLog::write('resurrect_users', $message);
				
				$count_user_option = $this->UserOption->find('first', array(
					'fields' => array('id'),
					'conditions' => array(
						'UserOption.user_id' => $user['User']['id'],
						'UserOption.name' => 'resurrect.email.count'
					)
				));
				
				$date_user_option = $this->UserOption->find('first', array(
					'fields' => array('id'),
					'conditions' => array(
						'UserOption.user_id' => $user['User']['id'],
						'UserOption.name' => 'resurrect.email.date'
					)
				));
				
				$this->UserOption->create();
				$this->UserOption->save(array('UserOption' => array(
					'id' => $date_user_option['UserOption']['id'],
					'name' => 'resurrect.email.date',
					'value' => date(DB_DATETIME),
				), true, array('value')));
				
				$this->UserOption->create();
				$this->UserOption->save(array('UserOption' => array(
					'id' => $count_user_option['UserOption']['id'],
					'name' => 'resurrect.email.count',
					'value' => $current_resurrect_email_count + 1
				), true, array('value')));
			}
		}
	}
	
	public function usurv_reports_into_group_reports() {
		$usurv_reports = $this->UsurvReport->find('all');
		if (!$usurv_reports) {
			$this->out('Reports not found');
		}
		
		foreach ($usurv_reports as $report) {
			$this->GroupReport->create();
			$this->GroupReport->save(array('GroupReport' => array(
				'user_id' => $report['UsurvReport']['user_id'],
				'group_key' => 'usurv',
				'term' => SURVEY_COMPLETED,
				'date_from' => $report['UsurvReport']['date_from'],
				'date_to' => $report['UsurvReport']['date_to'],
				'path' => $report['UsurvReport']['path'],
				'status' => $report['UsurvReport']['status'],
				'created' => $report['UsurvReport']['created'],
				'modified' => $report['UsurvReport']['modified']
			)));
			
			$this->out('Usurv report #'.$report['UsurvReport']['id'].' migrated to group_reports');
		}
	}
	
	public function update_first_time_completed_profile() {
		ini_set('memory_limit', '1024M');
		
		$user_profiles = $this->UserProfile->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'UserProfile.profile_id' => '0',
				'UserProfile.status' => 'completed'
			)
		));
		foreach ($user_profiles as $user_profile) {
			
			$last_time_paid = $this->UserOption->find('first', array(
				'fields' => array('id'),
				'conditions' => array(
					'UserOption.user_id' => $user_profile['UserProfile']['user_id'],
					'UserOption.name' => 'user_profile.completed'
				)
			));
			
			if (empty($last_time_paid)) {
				$this->UserOption->create();
				$this->UserOption->save(array('UserOption' => array(
					'user_id' => $user_profile['UserProfile']['user_id'],
					'name' => 'user_profile.completed',
					'value' => $user_profile['UserProfile']['completed'],
					'modified' => $user_profile['UserProfile']['completed']
				)));
			}
			
			// set page 1,2,3 modified time
			for ($i = 1; $i <= 3; $i++) {
				$pgae_update = $this->UserOption->find('first', array(
					'fields' => array('id'),
					'conditions' => array(
						'UserOption.user_id' => $user_profile['UserProfile']['user_id'],
						'UserOption.name' => 'user_profile.page.'.$i
					)
				));
				
				if (empty($pgae_update)) {
					$this->UserOption->create();
					$this->UserOption->save(array('UserOption' => array(
						'user_id' => $user_profile['UserProfile']['user_id'],
						'name' => 'user_profile.page.'.$i,
						'value' => $user_profile['UserProfile']['completed'],
						'modified' => $user_profile['UserProfile']['completed']
					)));
				}
			}
		}
	}
	
	/* https://basecamp.com/2045906/projects/1413421/todos/269603281 */
	public function recreate_lucid_links() {
		if (!isset($this->args[0])) {
			$this->out('Please define the scope of changes');
			return false;
		}
		
		CakeLog::write('lucid.recreate.links', 'Starting '.$this->args[0]); 
		
		App::import('Model', 'LucidLinkMigration');
		$this->LucidLinkMigration = new LucidLinkMigration; 

		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => array(
					'lucid.host', 
					'lucid.api.key', 
					'lucid.supplier.code', 
				),
				'Setting.deleted' => false
			)
		));
		
		$group = $this->Group->find('first', array(
			'fields' => array(
				'Group.id'
			),
			'conditions' => array(
				'Group.key' => 'fulcrum',
			),
			'recursive' => -1
		));
		
		// httpsocket for PUTing updating urls
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$data = array(
			'SupplierLinkTypeCode' => 'OWS',
			'TrackingTypeCode' => 'NONE',
			'DefaultLink' => 'https://r.mintvine.com/nq/?uid=[%MID%]',
			'SuccessLink' => 'https://r.mintvine.com/success/?uid=[%MID%]',
			'FailureLink' => 'https://r.mintvine.com/nq/?uid=[%MID%]',
			'OverQuotaLink' => 'https://r.mintvine.com/quota/?uid=[%MID%]',
			'QualityTerminationLink' => 'https://r.mintvine.com/nq/?uid=[%MID%]'
		); 
		
		// get all allocated projects
		if ($this->args[0] == 'offerwall') {		
			// get our total allocation across the board
			$url = $settings['lucid.host'].'Supply/v1/Surveys/SupplierAllocations/All/'.$settings['lucid.supplier.code']; 
			$params = array('key' => $settings['lucid.api.key']);
			$response = $http->get($url, $params);
			$body = json_decode($response->body, true);
		
			CakeLog::write('lucid.recreate.links', 'Found '.count($body['SupplierAllocationSurveys'])); 
			
			foreach ($body['SupplierAllocationSurveys'] as $project) {
				$count = $this->LucidLinkMigration->find('count', array(
					'conditions' => array(
						'LucidLinkMigration.lucid_survey_id' => $project['SurveyNumber']
					)
				));
				if ($count > 0) {
					continue;
				}
				// get the link first to find the type
				$url = 'https://api.samplicio.us/Supply/v1/SupplierLinks/BySurveyNumber/'.$project['SurveyNumber'].'/'.$settings['lucid.supplier.code'].'?key='.$settings['lucid.api.key'];
				$link_response = $http->get($url);
				$link_body = json_decode($link_response->body, true);				
				$data['SupplierLinkTypeCode'] = $link_body['SupplierLink']['SupplierLinkTypeCode']; 
				
				// do the actual PUT to update the link
				$url = 'https://api.samplicio.us/Supply/v1/SupplierLinks/Update/'.$project['SurveyNumber'].'/'.$settings['lucid.supplier.code'].'?key='.$settings['lucid.api.key'];
				$params = array(
					'header' => array('Content-Type' => 'application/json')
				); 
				if (isset($this->args[1]) && $this->args[1] == 'go') {
					$response = $http->put($url, json_encode($data), $params); 
					if ($response->code != '200' && $response->code != '404') {
						CakeLog::write('lucid.recreate.links', 'ERROR:');
						CakeLog::write('lucid.recreate.links', print_r($response, true)); 
						print_r($response);
					}
					else {
						CakeLog::write('lucid.recreate.links', 'SUCCESS: '.$project['SurveyNumber']);
						$this->LucidLinkMigration->create();
						$this->LucidLinkMigration->save(array('LucidLinkMigration' => array(
							'lucid_survey_id' => $project['SurveyNumber']
						)));
					}
				}
				$this->out($project['SurveyNumber']); 
			}
		}
		// get all old un-allocated projects
		elseif ($this->args[0] == 'old') {
			$results = file_get_contents(APP.'Console/Command/files/lucid/project_ids.csv');
			$results = explode("\n", trim($results));
			array_walk($results, create_function('&$val', '$val = trim($val);')); 
			foreach ($results as $row) {
				list($project_id, $count) = explode(',', $row);

				$count = $this->LucidLinkMigration->find('count', array(
					'conditions' => array(
						'LucidLinkMigration.lucid_survey_id' => $project_id
					)
				));
				if ($count > 0) {
					continue;
				}

				// get the link first to find the type
				$url = 'https://api.samplicio.us/Supply/v1/SupplierLinks/BySurveyNumber/'.$project_id.'/'.$settings['lucid.supplier.code'].'?key='.$settings['lucid.api.key'];
				$link_response = $http->get($url);
				$link_body = json_decode($link_response->body, true);				
				$data['SupplierLinkTypeCode'] = $link_body['SupplierLink']['SupplierLinkTypeCode']; 
				
				$url = 'https://api.samplicio.us/Supply/v1/SupplierLinks/Update/'.$project_id.'/'.$settings['lucid.supplier.code'].'?key='.$settings['lucid.api.key'];
				$params = array(
					'header' => array('Content-Type' => 'application/json')
				); 
				if (isset($this->args[1]) && $this->args[1] == 'go') {
					$response = $http->put($url, json_encode($data), $params); 
					if ($response->code != '200') {
						CakeLog::write('lucid.recreate.links', 'ERROR:');
						CakeLog::write('lucid.recreate.links', print_r($response, true)); 
						print_r($response);
					}
					else {
						CakeLog::write('lucid.recreate.links', 'SUCCESS: '.$project_id);
						$this->LucidLinkMigration->create();
						$this->LucidLinkMigration->save(array('LucidLinkMigration' => array(
							'lucid_survey_id' => $project_id
						)));
					}
					$this->out($project_id);
				}
			}
		}
		// target a specific project
		else {
			$project = $this->Project->find('first', array(
				'conditions' => array(
					'Project.group_id' => $group['Group']['id'],
					'Project.mask' => $this->args[0]
				)
			));
			if (!$project) {
				$this->out('Could not find #F'.$this->args[0]); 
				return false;
			}
			
			$url = 'https://api.samplicio.us/Supply/v1/SupplierLinks/Update/'.$this->args[0].'/'.$settings['lucid.supplier.code'].'?key='.$settings['lucid.api.key'];
			$params = array(
				'header' => array('Content-Type' => 'application/json')
			); 
			if (isset($this->args[1]) && $this->args[1] == 'go') {
				$response = $http->put($url, json_encode($data), $params); 
				if ($response->code != '200') {
					CakeLog::write('lucid.recreate.links', 'ERROR:');
					CakeLog::write('lucid.recreate.links', print_r($response, true)); 
					print_r($response);
					exit();
				}
				else {
					CakeLog::write('lucid.recreate.links', 'SUCCESS: '.$this->args[0]);
				}
				$this->out('Finished');
			}
		}
		
	}
	
	public function router_cpis() {
		$project_option = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.name' => 'fix.router_cpis'
			)
		));
		if (!$project_option) {
			$this->out('Please set an option fix.router_cpis');
			return false;
		}
		
		$last_id = $project_option['ProjectOption']['value'];
		while (true) {
			$user_router_logs = $this->UserRouterLog->find('all', array(
				'fields' => array('UserRouterLog.id', 'UserRouterLog.ir', 'UserRouterLog.cpi', 'UserRouterLog.survey_id'),
				'conditions' => array(
					'UserRouterLog.id >=' => $last_id
				),
				'limit' => '10000',
				'order' => 'UserRouterLog.id ASC'
			));
			if (!$user_router_logs) {
				$this->out('Completed');
				return;
			}
			
			// reversing 'cpi' => round($survey['Project']['client_rate'] * $survey['Project']['bid_ir'] / 100, 2),
			foreach ($user_router_logs as $user_router_log) {
				if (empty($user_router_log['UserRouterLog']['ir'])) {
					continue;
				}
				$project = $this->Project->find('first', array(
					'recursive' => -1,
					'fields' => array('Project.client_rate'),
					'conditions' => array(
						'Project.id' => $user_router_log['UserRouterLog']['survey_id']
					)
				));
				$real_cpi = $project['Project']['client_rate'];
				$this->out('#'.$user_router_log['UserRouterLog']['id'].' '.$user_router_log['UserRouterLog']['cpi'].' => '.$real_cpi);
				$last_id = $user_router_log['UserRouterLog']['id'];
				$this->UserRouterLog->create();
				$this->UserRouterLog->save(array('UserRouterLog' => array(
					'id' => $user_router_log['UserRouterLog']['id'],
					'cpi' => $real_cpi,
					'modified' => false
				)), true, array('cpi')); 
			}
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'id' => $project_option['ProjectOption']['id'],
				'value' => $last_id
			)), true, array('value'));
		}
	}
	
	public function ssi_statistics() {
		$models_to_import = array('SurveyVisit', 'SurveyVisitCache');
		foreach ($models_to_import as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		$group = $this->Group->find('first', array(
			'fields' => array(
				'Group.id' 
			),
			'conditions' => array(
				'Group.key' => 'ssi',
			),
			'recursive' => -1
		));
		$this->Project->unbindModel(array(
			'hasMany' => array('ProjectOption', 'ProjectAdmin'),
			'belongsTo' => array('Client', 'Group'),
		));
		$this->Project->bindModel(array('hasOne' => array(
			'PrescreenerStatistic' => array(
				'className' => 'PrescreenerStatistic',
				'foreignKey' => 'survey_id'
			)
		)));
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Project.client_rate', 'Project.prescreen', 'SurveyVisitCache.id', 'PrescreenerStatistic.nqs'),
			'conditions' => array(
				'Project.group_id' => $group['Group']['id']
			)
		));
		$total_projects = count($projects);
		$this->out('Fixing '.$total_projects.' ssi projects');
		$fixed = 0;
		foreach ($projects as $project) {
			$project_id = $project['Project']['id'];
			$click_count = $this->SurveyVisit->find('count', array(
				'conditions' => array(
					'SurveyVisit.survey_id' => $project_id,
					'SurveyVisit.type' => SURVEY_CLICK
				)
			));
			
			$complete_count = $this->SurveyVisit->find('count', array(
				'conditions' => array(
					'SurveyVisit.survey_id' =>$project_id,
					'SurveyVisit.result' => SURVEY_COMPLETED,
					'SurveyVisit.type' => SURVEY_CLICK
				)
			));
			
			$nq_count = $this->SurveyVisit->find('count', array(
				'conditions' => array(
					'SurveyVisit.survey_id' => $project_id,
					'SurveyVisit.result' => array(SURVEY_NQ, SURVEY_INTERNAL_NQ),
					'SurveyVisit.type' => SURVEY_CLICK
				)
			));
			
			$speed_count = $this->SurveyVisit->find('count', array(
				'conditions' => array(
					'SurveyVisit.survey_id' => $project_id,
					'SurveyVisit.result' => SURVEY_NQ_SPEED,
					'SurveyVisit.type' => SURVEY_CLICK,
				)
			));
			
			$fraud_count = $this->SurveyVisit->find('count', array(
				'conditions' => array(
					'SurveyVisit.survey_id' => $project_id,
					'SurveyVisit.result' => SURVEY_NQ_FRAUD,
					'SurveyVisit.type' => SURVEY_CLICK,
				)
			));
			
			$oq_count = $this->SurveyVisit->find('count', array(
				'conditions' => array(
					'SurveyVisit.survey_id' => $project_id,
					'SurveyVisit.result' => SURVEY_OVERQUOTA,
					'SurveyVisit.type' => SURVEY_CLICK,
				)
			));
			
			$oq_internal_count = $this->SurveyVisit->find('count', array(
				'conditions' => array(
					'SurveyVisit.survey_id' => $project_id,
					'SurveyVisit.result' => SURVEY_OQ_INTERNAL,
					'SurveyVisit.type' => SURVEY_CLICK
				)
			));
			
			$block_count = $this->SurveyVisit->find('count', array(
				'conditions' => array(
					'SurveyVisit.survey_id' => $project_id,
					'SurveyVisit.result' => array(SURVEY_DUPE, SURVEY_DUPE_FP),
					'SurveyVisit.type' => SURVEY_CLICK
				)
			));
			
			if (!empty($project['SurveyVisitCache']['id'])) {
				$survey_visit_cache_id = $project['SurveyVisitCache']['id'];
			}
			else {
				$this->SurveyVisitCache = new SurveyVisitCache;
				$this->SurveyVisitCache->save(array('SurveyVisitCache' => array(
					'survey_id' => $project_id,
				)));
				$survey_visit_cache_id = $this->SurveyVisitCache->getLastInsertID();
			}
			
			$actual_ir = null;
			$actual_epc = null;
			$drops = null;
			if ($complete_count > 0) {
				$actual_ir = round($complete_count / $click_count, 2) * 100;
				$actual_epc = round($actual_ir * $project['Project']['client_rate']); // we save epc in cents
			}
			if ($click_count > 0) {
				// because prescreener clicks do not register as project clicks, but prescreener nqs do register as project nqs
				if ($project['Project']['prescreen'] && !empty($project['PrescreenerStatistic']['nqs'])) {
					$actual_nq = $nq_count - $project['PrescreenerStatistic']['nqs'];
				}
				else {
					$actual_nq = $nq_count;
				}
				$drops = 100 - round((($complete_count + $actual_nq + $oq_count + $oq_internal_count + $speed_count + $fraud_count) / $click_count) * 100);
			}
			
			$this->SurveyVisitCache->create();
			$this->SurveyVisitCache->save(array('SurveyVisitCache' => array(
				'id' => $survey_visit_cache_id,
				'click' => $click_count,
				'complete' => $complete_count,
				'nq' => $nq_count,
				'speed' => $speed_count,
				'fraud' => $fraud_count,
				'overquota' => $oq_count,
				'oq_internal' => $oq_internal_count,
				'block' => $block_count,
				'ir' => $actual_ir,
				'epc' => $actual_epc,
				'drops' => $drops,
				'modified' => false
			)), array(
				'callbacks' => false,
				'fieldList' => array('click', 'complete', 'nq', 'speed', 'fraud', 'overquota', 'oq_internal', 'block', 'ir', 'epc', 'drops')
			));
			
			if (!empty($project['SurveyPartner'])) {
				foreach ($project['SurveyPartner'] as $partner) {
					$partner_id = $partner['id'];
					$click_count = $this->SurveyVisit->find('count', array(
						'conditions' => array(
							'SurveyVisit.survey_id' => $project_id,
							'SurveyVisit.partner_id' => $partner_id,
							'SurveyVisit.type' => SURVEY_CLICK
						)
					));
					
					$complete_count = $this->SurveyVisit->find('count', array(
						'conditions' => array(
							'SurveyVisit.survey_id' => $project_id,
							'SurveyVisit.partner_id' => $partner_id,
							'SurveyVisit.result' => SURVEY_COMPLETED,
							'SurveyVisit.type' => SURVEY_CLICK
						)
					));
					
					$nq_count = $this->SurveyVisit->find('count', array(
						'conditions' => array(
							'SurveyVisit.survey_id' => $project_id,
							'SurveyVisit.partner_id' => $partner_id,
							'SurveyVisit.result' => array(SURVEY_NQ, SURVEY_INTERNAL_NQ),
							'SurveyVisit.type' => SURVEY_CLICK
						)
					));
					
					$speed_count = $this->SurveyVisit->find('count', array(
						'conditions' => array(
							'SurveyVisit.survey_id' => $project_id,
							'SurveyVisit.partner_id' => $partner_id,
							'SurveyVisit.result' => SURVEY_NQ_SPEED,
							'SurveyVisit.type' => SURVEY_CLICK,
						)
					));
					
					$fraud_count = $this->SurveyVisit->find('count', array(
						'conditions' => array(
							'SurveyVisit.survey_id' => $project_id,
							'SurveyVisit.partner_id' => $partner_id,
							'SurveyVisit.result' => SURVEY_NQ_FRAUD,
							'SurveyVisit.type' => SURVEY_CLICK,
						)
					));
					
					$oq_count = $this->SurveyVisit->find('count', array(
						'conditions' => array(
							'SurveyVisit.survey_id' => $project_id,
							'SurveyVisit.partner_id' => $partner_id,
							'SurveyVisit.result' => SURVEY_OVERQUOTA,
							'SurveyVisit.type' => SURVEY_CLICK,
						)
					));
					
					$oq_internal_count = $this->SurveyVisit->find('count', array(
						'conditions' => array(
							'SurveyVisit.survey_id' => $project_id,
							'SurveyVisit.partner_id' => $partner_id,
							'SurveyVisit.result' => SURVEY_OQ_INTERNAL,
							'SurveyVisit.type' => SURVEY_CLICK
						)
					));
					$this->SurveyPartner->create();
					$this->SurveyPartner->save(array('SurveyPartner' => array(
						'id' => $partner_id,
						'clicks' => $click_count,
						'completes' => $complete_count,
						'nqs' => $nq_count,
						'speeds' => $speed_count,
						'fails' => $fraud_count,
						'oqs' => $oq_count,
						'oqs_internal' => $oq_internal_count,
						'modified' => false
					)), array(
						'callbacks' => false,
						'fieldList' => array('clicks', 'completes', 'nqs', 'speeds', 'fails', 'oqs', 'oqs_internal')
					));
				}
			}
			$fixed ++;
			$this->out('Fixed '.$fixed.'/'.$total_projects . ' #'. $project_id);
		}
		$this->out('Completed.');
	}
	
	function toluna_precision_epc() {
		$groups = $this->Group->find('all', array(
			'conditions' => array(
				'Group.key' => array('toluna', 'precision')
			)
		));

		foreach ($groups as $group) {
			$projects = $this->Project->find('all', array(
				'conditions' => array(
					'Project.group_id' => $group['Group']['id'],
					'Project.epc is null',
					'Project.bid_ir is not null',
					'Project.client_rate is not null'
				),
				'fields' => array('Project.id', 'Project.bid_ir', 'Project.client_rate'),
				'recursive' => -1
			));
			if (empty($projects)) {
				$this->out($group['Group']['name'] . ' projects with Null epcs not found');
			}
			else {
				$this->out(count($projects) . ' ' . $group['Group']['name'] . ' projects found with Null epcs');
				foreach ($projects as $project) {
					if (!empty($project['Project']['bid_ir']) && !empty($project['Project']['client_rate'])) {
						$epc = round($project['Project']['bid_ir'] * $project['Project']['client_rate']);
						$this->Project->save(array('Project' => array(
							'id' => $project['Project']['id'],
							'epc' => $epc,
							'modified' => false
						)), true, array('epc'));
					}
					$this->out('EPC updated for: ' . $project['Project']['id']);
				}
			}
		}
		$this->out('Completed.');
	}
	
	public function county_fips() {
		if (isset($this->args[0])) {
			$user_addresses = $this->UserAddress->find('all', array(
				'fields' => array('UserAddress.id', 'UserAddress.user_id', 'UserAddress.state', 'UserAddress.county'),
				'conditions' => array(
					'UserAddress.id' => $this->args[0],				)
			));
		}
		else {
			$user_addresses = $this->UserAddress->find('all', array(
				'fields' => array('UserAddress.id', 'UserAddress.user_id', 'UserAddress.state', 'UserAddress.county'),
				'conditions' => array(
					'UserAddress.deleted' => false,
					'UserAddress.country' => 'US',
					'UserAddress.county <>' => '',
					'UserAddress.county_fips is null'
				)
			));
		}
		
		if (!$user_addresses) {
			$this->out('Addresses not found.');
			return;
		}

		$matched_cache = $formated_lucid_zips = array();
		$lucid_zips = $this->LucidZip->find('all', array(
			'fields' => array('distinct (LucidZip.county)', 'LucidZip.state_abbr'),
		));
		foreach ($lucid_zips as $lucid_zip) {
			$formated_lucid_zips[$lucid_zip['LucidZip']['state_abbr']][] = $lucid_zip['LucidZip']['county'];
		}
		
		$this->out('Processing '.count($user_addresses).' records'); 
				
		foreach ($user_addresses as $address) {
			if (!isset($formated_lucid_zips[$address['UserAddress']['state']]) || empty($formated_lucid_zips[$address['UserAddress']['state']])) {
				continue;
			}
			
			$address_county = str_replace(' County', '', $address['UserAddress']['county']);
			if (!isset($matched_cache[$address_county])) {
				$score = 0;
				foreach ($formated_lucid_zips[$address['UserAddress']['state']] as $county) {
					$no_matched_chars = similar_text(strtolower($address_county), strtolower($county));
					//echo $county. ':'. $no_matched_chars. "\n";
					if ($no_matched_chars > $score) {
						$score = $no_matched_chars;
						$matched_cache[$address_county] = $county;
					}
				}
			}
			
			if (!isset($matched_cache[$address_county])) {
				$message = $address['UserAddress']['county']. ' not matched in lucid_zips for User# '. $address['UserAddress']['user_id'];
				$this->out($message);
				cakeLog::write('county_fips', $message);
				continue;
			}
			
			$lucid_zip = $this->LucidZip->find('first', array(
				'conditions' => array(
					'LucidZip.county' => $matched_cache[$address_county]
				)
			));
			if (!$lucid_zip) {
				$message = $matched_cache[$address_county]. ' not found in LucidZip for User# '. $address['UserAddress']['user_id'];
				$this->out($message);
				cakeLog::write('county_fips', $message);
				continue;
			}
			
			if (empty($lucid_zip['LucidZip']['state_fips']) || empty($lucid_zip['LucidZip']['county_fips'])) {
				continue;
			}

			$formatted_county = str_pad($lucid_zip['LucidZip']['state_fips'], 2, '0', STR_PAD_LEFT).str_pad($lucid_zip['LucidZip']['county_fips'], 3, '0', STR_PAD_LEFT);
			$this->UserAddress->create();
			$this->UserAddress->save(array('UserAddress' => array(
				'id' => $address['UserAddress']['id'],
				'county_fips' => $formatted_county,
				'modified' => false
			)), false, array('county_fips'));
			
			$query_profile = $this->QueryProfile->find('first', array(
				'fields' => array('QueryProfile.id'),
				'conditions' => array(
					'QueryProfile.user_id' => $address['UserAddress']['user_id']
				)
			));
			if (!$query_profile) {
				continue;
			}
			
			$this->QueryProfile->create();
			$this->QueryProfile->save(array('QueryProfile' => array(
				'id' => $query_profile['QueryProfile']['id'],
				'county_fips' => $formatted_county,
				'modified' => false
			)), true, array('county_fips'));
			
			$this->out($address['UserAddress']['county'].' matched to '.$lucid_zip['LucidZip']['county']);
		}
	}
	
	// https://basecamp.com/2045906/projects/1413421/todos/274719200
	public function corrupted_ip_addresses() {
		$panelist_histories = $this->PanelistHistory->find('all', array(
			'fields' => array('PanelistHistory.id', 'PanelistHistory.ip_address'),
			'conditions' => array(
				'PanelistHistory.ip_address LIKE' => '%, %'
			)
		));
		$total = count($panelist_histories);
		$i = 0; 
		$this->out('Fixing '.$total);
		foreach ($panelist_histories as $panelist_history) {
			$i++;
			list($address_1, $address_2) = explode(', ', $panelist_history['PanelistHistory']['ip_address']); 
			$this->PanelistHistory->create();
			$this->PanelistHistory->save(array('PanelistHistory' => array(
				'id' => $panelist_history['PanelistHistory']['id'],
				'ip_address' => $address_2,
				'modified' => false
			)), true, array('ip_address')); 
			$this->out($i.' / '.$total.': #'.$panelist_history['PanelistHistory']['id'].' ('.$panelist_history['PanelistHistory']['ip_address'].' => '.$address_2.')');
		}
	}
	
	// https://basecamp.com/2045906/projects/1413421/todos/274719708
	public function unhellbanned_auto() {
		$hellban_logs = $this->HellbanLog->find('all', array(
			'conditions' => array(
				'HellbanLog.reason' => 'Skipped r for completes',
				'HellbanLog.automated' => true,
			)
		));
		
		foreach ($hellban_logs as $hellban_log) {
			$user = $this->User->find('first', array(
				'fields' => array('User.id', 'User.hellbanned'),
				'conditions' => array(
					'User.id' => $hellban_log['HellbanLog']['user_id']
				),
				'recursive' => -1
			));
			if (!$user['User']['hellbanned']) {
				MintVineUser::hellban($user['User']['id'], 'Skipped r for completes'); 
				$this->out('Rebanned '.$user['User']['id']);
			}
		}
	}
	
	// https://basecamp.com/2045906/projects/1413421/todos/274719708
	public function unhellbanned_auto_undo() {
		$hellban_logs = $this->HellbanLog->find('all', array(
			'conditions' => array(
				'HellbanLog.reason' => 'Skipped r for completes',
				'HellbanLog.automated' => false,
			)
		));
		
		foreach ($hellban_logs as $hellban_log) {
			if ($hellban_log['HellbanLog']['user_id'] < 950000) {

				$this->User->create();
				$this->User->save(array('User' => array(
					'id' => $hellban_log['HellbanLog']['user_id'],
					'hellbanned' => '0',
					'checked' => '0',
					'hellbanned_on' => null
				)), true, array('hellbanned', 'checked', 'hellbanned_on'));
		
				$this->HellbanLog->create();
				$this->HellbanLog->save(array('HellbanLog' => array(
					'user_id' => $hellban_log['HellbanLog']['user_id'],
					'type' => 'unhellban',
					'automated' => false
				)));
				$this->out('Unhellbanned '.$hellban_log['HellbanLog']['user_id']);
			}
		}
	}
	
	public function counties() {
		$user_addresses = $this->UserAddress->find('all', array(
			'conditions' => array(
				'length(UserAddress.county)' => 2,
				'deleted' => false
			)
		));
		if (!$user_addresses) {
			return;
		}
		
		foreach ($user_addresses as $address) {
			$this->UserAddress->create();
			$save = $this->UserAddress->save(array('UserAddress' => array(
				'id' => $address['UserAddress']['id'],
				'county' => null,
				'county_fips' => null,
				'modified' => false
			)), false, array('county', 'county_fips'));
			
			$query_profile = $this->QueryProfile->find('first', array(
				'conditions' => array(
					'QueryProfile.user_id' => $address['UserAddress']['user_id']
				)
			));
			if (!$query_profile) {
				continue;
			}
			
			$this->QueryProfile->create();
			$this->QueryProfile->save(array('QueryProfile' => array(
				'id' => $query_profile['QueryProfile']['id'],
				'county_fips' => null,
				'modified' => false
			)), true, array('county_fips'));
			
			$this->out('County '. $address['UserAddress']['county'] . ' cleaned.');
		}
	}
	
	public function cleanup_toluna_dupes() {
		ini_set('memory_limit', '2048M');
		$this->out('Starting Toluna cleanup');
		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'toluna'
			)
		));
		$this->Project->unbindModel(array(
			'hasMany' => array('SurveyPartner', 'ProjectOption', 'ProjectAdmin'),
			'belongsTo' => array('Client', 'Group'),
		));
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Project.mask', 'Project.date_created', 'SurveyVisitCache.click', 'SurveyVisitCache.complete'),
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'Project.id >=' => 245230, // manually found this with DB
				'SurveyVisitCache.click <=' => '1',
				'SurveyVisitCache.complete' => '0'
			),
		)); 
		$total = count($projects);
		$this->out('Found '.$total.' projects');
		$i = 0;
		foreach ($projects as $project) {
			$mask = (int) $project['Project']['mask'];
			if ($mask >= 65000) {
				continue;
			}
			$this->out('#'.$project['Project']['id'].'----------------');
			$i++;
			$survey_partners = $this->SurveyPartner->find('all', array(
				'fields' => array('SurveyPartner.id'),
				'conditions' => array(
					'SurveyPartner.survey_id' => $project['Project']['id']
				)
			));
			if ($survey_partners) {
				foreach ($survey_partners as $survey_partner) {
					$this->SurveyPartner->delete($survey_partner['SurveyPartner']['id']);
					$this->out('Deleted survey_partner #'.$survey_partner['SurveyPartner']['id']);
				}
			}

			$survey_users = $this->SurveyUser->find('all', array(
				'fields' => array('SurveyUser.id'),
				'conditions' => array(
					'SurveyUser.survey_id' => $project['Project']['id']
				)
			));
			if ($survey_users) {
				foreach ($survey_users as $survey_user) {
					$this->SurveyUser->delete($survey_user['SurveyUser']['id']);
					$this->out('Deleted survey_user #'.$survey_user['SurveyUser']['id']);
				}
			}

			$panelist_histories = $this->PanelistHistory->find('all', array(
				'fields' => array('PanelistHistory.id'),
				'conditions' => array(
					'PanelistHistory.project_id' => $project['Project']['id']
				)
			));
			if ($panelist_histories) {
				foreach ($panelist_histories as $panelist_history) {
					$this->PanelistHistory->delete($panelist_history['PanelistHistory']['id']);
					$this->out('Deleted panelist_history #'.$panelist_history['PanelistHistory']['id']);
				}
			}

			$user_ips = $this->UserIp->find('all', array(
				'fields' => array('UserIp.id'),
				'conditions' => array(
					'UserIp.survey_id' => $project['Project']['id']
				)
			));
			if ($user_ips) {
				foreach ($user_ips as $user_ip) {
					$this->UserIp->delete($user_ip['UserIp']['id']);
					$this->out('Deleted user_ip #'.$user_ip['UserIp']['id']);
				}
			}

			$survey_user_visits = $this->SurveyUserVisit->find('all', array(
				'fields' => array('SurveyUserVisit.id'),
				'conditions' => array(
					'SurveyUserVisit.survey_id' => $project['Project']['id']
				)
			));
			if ($survey_user_visits) {
				foreach ($survey_user_visits as $survey_user_visit) {
					$this->SurveyUserVisit->delete($survey_user_visit['SurveyUserVisit']['id']);
					$this->out('Deleted survey_user_visit #'.$survey_user_visit['SurveyUserVisit']['id']);
				}
			}
			
			$survey_visit_caches = $this->SurveyVisitCache->find('all', array(
				'fields' => array('SurveyVisitCache.id'),
				'conditions' => array(
					'SurveyVisitCache.survey_id' => $project['Project']['id']
				)
			));
			if ($survey_visit_caches) {
				foreach ($survey_visit_caches as $survey_visit_cache) {
					$this->SurveyVisitCache->delete($survey_visit_cache['SurveyVisitCache']['id']);
					$this->out('Deleted survey_visit_cache #'.$survey_visit_cache['SurveyVisitCache']['id']);
				}
			}
			
			$project_rates = $this->ProjectRate->find('all', array(
				'fields' => array('ProjectRate.id'),
				'conditions' => array(
					'ProjectRate.project_id' => $project['Project']['id']
				)
			));
			if ($project_rates) {
				foreach ($project_rates as $project_rate) {
					$this->ProjectRate->delete($project_rate['ProjectRate']['id']);
					$this->out('Deleted project_log #'.$project_rate['ProjectRate']['id']);
				}
			}

			$project_logs = $this->ProjectLog->find('all', array(
				'fields' => array('ProjectLog.id'),
				'conditions' => array(
					'ProjectLog.project_id' => $project['Project']['id']
				)
			));
			if ($project_logs) {
				foreach ($project_logs as $project_log) {
					$this->ProjectLog->delete($project_log['ProjectLog']['id']);
					$this->out('Deleted project_log #'.$project_log['ProjectLog']['id']);
				}
			}
			
			// delete all children
			$user_router_logs = $this->UserRouterLog->find('all', array(
				'fields' => array('UserRouterLog.id'),
				'conditions' => array(
					'UserRouterLog.survey_id' => $project['Project']['id'],
					'UserRouterLog.parent_id >' => '0'
				),
				'recursive' => -1
			));
			if ($user_router_logs) {
				foreach ($user_router_logs as $user_router_log) {
					$this->UserRouterLog->delete($user_router_log['UserRouterLog']['id']);
					$this->out('Deleted user_router_log #'.$user_router_log['UserRouterLog']['id']);
				}
			}
			
			// delete all parents
			$user_router_logs = $this->UserRouterLog->find('all', array(
				'fields' => array('UserRouterLog.id'),
				'conditions' => array(
					'UserRouterLog.survey_id' => $project['Project']['id'],
					'UserRouterLog.parent_id' => '0'
				),
				'recursive' => -1
			));
			if ($user_router_logs) {
				foreach ($user_router_logs as $user_router_log) {
					$this->UserRouterLog->delete($user_router_log['UserRouterLog']['id']);
					$this->out('Deleted user_router_log #'.$user_router_log['UserRouterLog']['id']);
					$children_router_logs = $this->UserRouterLog->find('all', array(
						'fields' => array('UserRouterLog.id'),
						'conditions' => array(
							'UserRouterLog.parent_id' => $user_router_log['UserRouterLog']['id']
						),
						'recursive' => -1
					));
					if ($children_router_logs) {
						foreach ($children_router_logs as $children_router_log) {
							$this->UserRouterLog->delete($children_router_log['UserRouterLog']['id']);
							$this->out('Deleted children_router_log #'.$children_router_log['UserRouterLog']['id']);
						}
					}
				}
			}
			
			$this->Project->delete($project['Project']['id']);
			$this->out($i.'/'.$total.' Deleted '.$project['Project']['id']);
		}
	}
	
	public function dupe_survey_users_invites() {
		ini_set('memory_limit', '4096M');
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id'),
			'conditions' => array(
				'Project.temp_qualifications' => true
			),
			'recursive' => -1
		));
		if ($projects) {
			foreach ($projects as $project) {
				$project_option = $this->ProjectOption->find('first', array(
					'conditions' => array(
						'ProjectOption.project_id' => $project['Project']['id'],
						'ProjectOption.name' => 'fix.dupe_survey_users'
					)
				));
				if ($project_option) {
					continue;
				}
				$this->out('Analyzing #'.$project['Project']['id']);
				$survey_users = $this->SurveyUser->find('list', array(
					'fields' => array('SurveyUser.id', 'SurveyUser.user_id'),
					'conditions' => array(
						'SurveyUser.survey_id' => $project['Project']['id']
					),
					'recursive' => -1
				));
				$total_count = count($survey_users); 
				$unique_users = array_unique($survey_users);
				$active_users = $this->User->find('list', array(
					'fields' => array('User.id', 'User.id'),
					'conditions' => array(
						'User.id' => $unique_users,
						'User.hellbanned' => false,
						'User.last_touched >=' => date(DB_DATETIME, strtotime('-30 days'))
					)
				));
				$unique_count = count($unique_users); 
				$active_count = count($active_users); 
				
				if ($total_count != $active_count) {
					$this->out('#'.$project['Project']['id'].' Unique users: '.$unique_count.' vs '.$total_count.' vs '.$active_count); 
					$this->SurveyUser->deleteAll(array('survey_id' => $project['Project']['id']));
					
					foreach ($unique_users as $user_id) {
						$this->SurveyUser->create();
						$this->SurveyUser->save(array('SurveyUser' => array(
							'survey_id' => $project['Project']['id'],
							'user_id' => $user_id	
						)), array(
							'callbacks' => false,
							'validate' => false
						));
					}
				}
				
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'project_id' => $project['Project']['id'],
					'name' => 'fix.dupe_survey_users',
					'value' => 'Unique: '.$unique_count.' vs total: '.$total_count.' vs active/unique: '.$active_count
				)));
			}
		}
	}
	
	public function ssi_export() {
		$file = WWW_ROOT.'files/panelist_ids_not_in_the_ssi_list_12_oct.csv';
		$user_ids = array_map('str_getcsv', file($file));
		$user_ids = Set::extract('/0', $user_ids);
		
		$partner_users = $this->PartnerUser->find('all', array(
			'fields' => array('PartnerUser.id', 'PartnerUser.user_id'),
			'conditions' => array(
				'PartnerUser.user_id' => $user_ids,
				'PartnerUser.partner' => 'ssi',
				'PartnerUser.created <' => date(DB_DATETIME, strtotime('10-10-2016')), // Accidentally rerunning script will not delete re-exported users 
			)
		));
		if (!$partner_users) {
			$this->out('Already Fixed.');
			return true;
		}
		
		$this->out('Started fixing total partner_users for SSI : '. count($partner_users));
		foreach ($partner_users as $partner_user) {
			$this->PartnerUser->delete($partner_user['PartnerUser']['id']);
			$this->out('Deleted for user_id#'. $partner_user['PartnerUser']['user_id']);
		}
		$this->out('Done.');
	}
	
	public function format_ca_and_gb_addresses() {
		$user_addresses = $this->UserAddress->find('all', array(
			'fields' => array('UserAddress.id', 'UserAddress.country', 'UserAddress.postal_code'),
			'conditions' => array(
				'UserAddress.country' => array('GB', 'CA'),
				'UserAddress.deleted' => false
			),
			'recursive' => -1
		));
		$total = count($user_addresses);
		$this->out('Found '.$total.' records');
		$i = 0; 
		foreach ($user_addresses as $user_address) {
			if ($user_address['UserAddress']['country'] == 'GB') {
				$formatted = Utils::format_uk_postcode($user_address['UserAddress']['postal_code']);
			}
			elseif ($user_address['UserAddress']['country'] == 'CA') {
				$formatted = Utils::format_ca_postcode($user_address['UserAddress']['postal_code']);
			}
			$i++;
			if ($formatted == $user_address['UserAddress']['postal_code']) {
				continue;
			}
			$this->UserAddress->create();
			$this->UserAddress->save(array('UserAddress' => array(
				'id' => $user_address['UserAddress']['id'],
				'postal_code' => $formatted,
				'country' => $user_address['UserAddress']['country'],
				'modified' => false
			)), true, array('postal_code', 'country'));
			$this->out($i.'/'.$total.': '.$user_address['UserAddress']['postal_code'].': '.$user_address['UserAddress']['postal_code'].' => '.$formatted);
		}
	}
	
	// fix postal codes that are misformatted in the UK
	// https://basecamp.com/2045906/projects/1413421/todos/276878366
	public function uk_postal_codes() {
		$query_profiles = $this->QueryProfile->find('all', array(
			'fields' => array('QueryProfile.id', 'QueryProfile.user_id', 'QueryProfile.postal_code'),
			'conditions' => array(
				'QueryProfile.country' => 'GB',
				'QueryProfile.postal_code is not null'
			),
			'recursive' => -1
		));
		$total = count($query_profiles);
		$this->out('Found '.$total.' records');
		$i = 0; 
		if ($query_profiles) {
			foreach ($query_profiles as $query_profile) {
				$i++;
				$query_profile['QueryProfile']['postal_code'] = trim($query_profile['QueryProfile']['postal_code']);
				$formatted = Utils::format_uk_postcode($query_profile['QueryProfile']['postal_code']);
				if ($formatted == $query_profile['QueryProfile']['postal_code']) {
					continue;
				}
				$this->QueryProfile->create();
				$this->QueryProfile->save(array('QueryProfile' => array(
					'id' => $query_profile['QueryProfile']['id'],
					'country' => 'GB',
					'postal_code' => $formatted,
					'modified' => false
				)), true, array('country', 'postal_code'));
				$this->out($i.'/'.$total.': '.$query_profile['QueryProfile']['user_id'].' '.$query_profile['QueryProfile']['postal_code'].' => '.$formatted); 
			}
		}
	}
	
	// fix postal codes that are misformatted in the US
	// https://basecamp.com/2045906/projects/1413421/todos/276878366
	public function us_postal_codes() {
		$query_profiles = $this->QueryProfile->find('all', array(
			'fields' => array('QueryProfile.id', 'QueryProfile.postal_code'),
			'conditions' => array(
				'QueryProfile.country' => 'US',
				'CHAR_LENGTH(postal_code) > 5'
			),
			'recursive' => -1
		));
		$total = count($query_profiles);
		$this->out('Found '.$total.' records');
		$i = 0; 
		if ($query_profiles) {
			foreach ($query_profiles as $query_profile) {
				$i++;
				$query_profile['QueryProfile']['postal_code'] = trim($query_profile['QueryProfile']['postal_code']);
				if (strpos($query_profile['QueryProfile']['postal_code'], '-') !== false) {
					list($postal_code, $extended) = explode('-', $query_profile['QueryProfile']['postal_code']); 
					$query_profile['QueryProfile']['postal_code'] = $postal_code; 
				}
				
				$this->QueryProfile->create();
				$save = $this->QueryProfile->save(array('QueryProfile' => array(
					'id' => $query_profile['QueryProfile']['id'],
					'country' => 'US',
					'postal_code' => $query_profile['QueryProfile']['postal_code'],
					'modified' => false
				)), true, array('country', 'postal_code'));
				if ($save) {
					$this->out($i.'/'.$total.': Saved #'.$query_profile['QueryProfile']['id'].' to '.$query_profile['QueryProfile']['postal_code']); 
				}
				else {
					$this->QueryProfile->create();
					$save = $this->QueryProfile->save(array('QueryProfile' => array(
						'id' => $query_profile['QueryProfile']['id'],
						'country' => null,
						'postal_code' => null,
						'modified' => false
					)), false, array('country', 'postal_code'));
					$this->out('Failed on '.$query_profile['QueryProfile']['id'].' ('.$query_profile['QueryProfile']['postal_code'].')'); 
				}
			}
		}
	}
	
	public function export_county_data() {
		$lucid_zips = $this->LucidZip->find('all', array(
			'fields' => array(
				'LucidZip.zipcode', 
				'LucidZip.county',
				'LucidZip.county_fips',
				'LucidZip.state_fips'
			)
		));
		$data = array(array(
			'ZIP Code',
			'County Name',
			'County FIP',
			'County Code',
			'State FIP'
		));
		foreach ($lucid_zips as $lucid_zip) {
			$county_fip = str_pad($lucid_zip['LucidZip']['state_fips'], 2, '0', STR_PAD_LEFT).str_pad($lucid_zip['LucidZip']['county_fips'], 3, '0', STR_PAD_LEFT);
			$data[] = array(
				$lucid_zip['LucidZip']['zipcode'],
				$lucid_zip['LucidZip']['county'],
				$county_fip, 
				$lucid_zip['LucidZip']['county_fips'],
				$lucid_zip['LucidZip']['state_fips']
			);
		}
		$fp = fopen(WWW_ROOT . '/files/counties.csv', "w");
		foreach ($data as $row) {
		    fputcsv($fp, $row);
		}
		fclose($fp);
		$this->out(WWW_ROOT . '/files/counties.csv');
	}
	
	public function delete_survey_issues_from_deleted_users() {
		$history_requests = $this->HistoryRequest->find('all', array(
			'conditions' => array(
				'HistoryRequest.status' => SURVEY_REPORT_REQUEST_PENDING,
				'User.deleted' => true
			),
			'fields' => array('HistoryRequest.id', 'User.id')
		));
		if ($history_requests) {
			$this->out('Found '.count($history_requests).' records');
			foreach ($history_requests as $history_request) {
				$this->HistoryRequest->delete($history_request['HistoryRequest']['id']);
				$this->out('Deleted history_requests #'.$history_request['HistoryRequest']['id']. ' by user #'.$history_request['User']['id']);
			}
		}
	}
	
	// https://basecamp.com/2045906/projects/1413421/todos/279305895
	public function copy_lucid_clients() {
		$client_list = $this->Project->find('all', array(
			'fields' => array('DISTINCT(client_id) as distinct_client_id'),
			'conditions' => array(
				'Project.group_id' => 4
			),
			'recursive' => -1
		));
		foreach ($client_list as $client) {
			$client_id = $client['Project']['distinct_client_id'];
			$client = $this->Client->find('first', array(
				'conditions' => array(
					'Client.id' => $client_id
				),
				'recursive' => -1
			));
			if (!$client) {
				$this->out('Cannot find '.$client_id);
				continue;
			}
			if ($client['Client']['group_id'] != 4) {
				$existing_client_in_group = $this->Client->find('first', array(
					'fields' => array('Client.id'),
					'conditions' => array(
						'Client.deleted' => false,
						'Client.key' => $client['Client']['key'],
						'Client.group_id' => 4
					)
				));
				
				if (!$existing_client_in_group) {
					unset($client['Client']['id']);
					unset($client['Client']['quickbook_customer_id']);
					unset($client['Client']['created']);
					unset($client['Client']['modified']);
					$this->Client->create();
					$this->Client->save($client); 
					$new_client_id = $this->Client->getInsertId();
					$this->out('Created new client ID: '.$new_client_id.' from '.$client_id.' ('.$client['Client']['client_name'].')');
				}
				else {
					$this->out('Copied client exists already: '.$existing_client_in_group['Client']['id']);
					$new_client_id = $existing_client_in_group['Client']['id'];
				}
				$projects = $this->Project->find('list', array(
					'fields' => array('Project.id', 'Project.id'),
					'conditions' => array(
						'Project.group_id' => 4,
						'Project.client_id' => $client_id
					),
					'recursive' => -1
				));
				if (!empty($projects)) {
					foreach ($projects as $project_id) {
						$this->Project->create();
						$this->Project->save(array('Project' => array(
							'id' => $project_id,
							'client_id' => $new_client_id,
							'modified' => false
						)), true, array('client_id'));
						$this->out('Updated '.$project_id.' to '.$new_client_id); 
					} 
				}
			}
		}
	}
	
	// https://basecamp.com/2045906/projects/1413421/todos/279305895
	public function disable_client_projects_in_lucid() {
		$client_list = $this->Client->find('all', array(
			'fields' => array('Client.id', 'Client.client_name'),
			'conditions' => array(
				'Client.deleted' => false,
				'Client.group_id' => 4,
				'Client.do_not_autolaunch' => true
			),
			'recursive' => -1
		));
		
		$client_ids = Set::extract($client_list, '{n}.Client.id');
		if (empty($client_ids)) {
			return;
		}
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Project.ended'),
			'conditions' => array(
				'Project.group_id' => 4,
				'Project.client_id' => $client_ids,
				'Project.status' => array(
					PROJECT_STATUS_STAGING, 
					PROJECT_STATUS_SAMPLING,
					PROJECT_STATUS_OPEN
				)
			),
			'recursive' => -1
		));
		if ($projects) {
			foreach ($projects as $project) {
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
					'type' => 'status.closed',
					'description' => 'Closed by Roy - no longer in active client list'
				)));
				$this->out('Closing '.$project['Project']['id']);
			}
		}
	}
	
	public function project_country() {
		$projects = $this->Project->find('list', array(
			'fields' => array('Project.id'),
			'conditions' => array(
				'Project.country' => ''
			),
			'order' => 'Project.id desc',
			'recursive' => -1,
		));
		if ($projects) {
			foreach ($projects as $project_id) {
				$survey_countries = $this->SurveyCountry->find('list', array(
					'fields' => array(
						 'SurveyCountry.id', 'SurveyCountry.country'
					),
					'conditions' => array(
						'SurveyCountry.survey_id' => $project_id
					)
				));
				$country = 'US';
				if ($survey_countries && !in_array('US', $survey_countries)) {
					$country = array_shift($survey_countries);
				}
				
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $project_id,
					'country' => $country,
					'modified' => false
				)), true, array('country'));
				$this->out('Updated Project ID : ' . $project_id . ', Country : ' . $country);
			}
		}
		
		$this->out('Completed');
	}
	
	public function bad_dates() {
		$data = file_get_contents(WWW_ROOT . 'files/bad_dates.txt');
		$data = json_decode($data);
		$users = array();
		if (!$data) {
			return;
		}
		
		$partners_to_update = array('mintvine', 'lucid');
		$qualifications = array();
		$sync_to_qe2 = false;
		foreach ($partners_to_update as $partner_to_update) {
			$qualifications[$partner_to_update] = array(); 
		}
		
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
		
		foreach ($data as $answer) {
			if (!empty($answer)) {
				foreach ($answer as $user_id) {
					$users[] = $user_id;
				}
			}
		}
		
		$users = $this->User->find('all', array(
			'fields' => array(
				'User.id', 'QueryProfile.id', 'QueryProfile.birthdate'
			),
			'contain' => array('QueryProfile'),
			'conditions' => array(
				'User.id' => $users
			),
			'recursive' => -1,
		));
		
		if (!$users) {
			return;
		}
		
		foreach ($users as $user) {
			$dob = explode('-', $user['QueryProfile']['birthdate']);
			
			// if year is 0000, the user should fix his dob in extended registration modal
			if (checkdate($dob[1], $dob[2], $dob[0]) || $dob[0] == '0000') {
				continue;
			}
			
			$update = false;
			if ($dob[1] == '00') {
				$dob[1] = '01';
				$update = true;
			}
			
			if ($dob[2] == '00') {
				$dob[2] = '01';
				$update = true;
			}
			
			if ($update) {
				$sync_to_qe2 = true;
				foreach ($partners_to_update as $partner_to_update) {
					$qualifications[$partner_to_update][$user['User']['id']] = array(
						'birthdate' => array(implode('-', $dob))
					);
				}
				
				$this->QueryProfile->create();
				$this->QueryProfile->save(array('QueryProfile' => array(
					'id' => $user['QueryProfile']['id'],
					'birthdate' => implode('-', $dob),
					'modified' => false,
				)), true, array('birthdate'));
				$this->out('User# '.$user['User']['id']. ' birthdate modified from '. $user['QueryProfile']['birthdate']. ' to '. implode('-', $dob));
			}
		}
		
		if ($sync_to_qe2) {
			$http = new HttpSocket(array(
				'timeout' => 120,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$http->configAuth('Basic', $settings['qe.mintvine.username'], $settings['qe.mintvine.password']);
			foreach ($partners_to_update as $partner_to_update) {
				$post_data = array(
					'partner' => $partner_to_update,
					'qualifications' => $qualifications[$partner_to_update]
				);
				
				try {
					$results = $http->put($settings['hostname.qe'].'/qualifications', json_encode($post_data), array(
						'header' => array('Content-Type' => 'application/json')
					));
					if ($results->code == 201) {
						$this->out('Updated date of births, synced with qe2 for '. $partner_to_update);
					}
					else {
						$this->out('QE2 api error when putting MintVine qualifications.');
						print_r($results, true);
					}
				} 
				catch (Exception $ex) {
					$this->out('QE2 api error when putting MintVine qualifications.');
					print_r($results, true);
				}
			}
		}
		
		$this->out('Update completed');
	}
	
	public function lucid_study_types() {
		$lucid_settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array(
					'lucid.maintenance',
					'lucid.host',
					'lucid.api.key'
				),
				'Setting.deleted' => false
			)
		));

		if ($lucid_settings['lucid.maintenance'] == 'true') {
			return;
		}

		$fed_surveys = $this->FedSurvey->find('list', array(
			'fields' => array('FedSurvey.id', 'FedSurvey.fed_survey_id'),
			'conditions' => array(
				'FedSurvey.survey_type_id is null'
			)
		));
		
		if (!$fed_surveys) {
			return;
		}
		
		$http = new HttpSocket(array(
			'timeout' => 30,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$params = array('key' => $lucid_settings['lucid.api.key']);
		$lucid_study_types = $this->LucidStudyType->find('list', array(
			'fields' => array(
				'LucidStudyType.key',
				'LucidStudyType.name'
			)
		));

		foreach ($fed_surveys as $id => $fed_survey_id) {
			if (empty($fed_survey_id)) {
				continue;
			}

			$url = $lucid_settings['lucid.host'] . 'Supply/v1/Surveys/SupplierAllocations/BySurveyNumber/'.$fed_survey_id;
			try {
				$response = $http->get($url, $params);
			} 
			catch (Exception $ex) {
				continue;
			}
			
			$response = json_decode($response['body'], true);
			if (!isset($response['SupplierAllocationSurvey']['StudyTypeID']) || empty($response['SupplierAllocationSurvey']['StudyTypeID'])) {
				continue;
			}
			
			$response = $response['SupplierAllocationSurvey'];
			$this->FedSurvey->create();
			$this->FedSurvey->save(array('FedSurvey' => array(
				'id' => $id,
				'survey_type_id' => $response['StudyTypeID'],
				'survey_type' => isset($lucid_study_types[$response['StudyTypeID']]) ? $lucid_study_types[$response['StudyTypeID']] : '',
				'modified' => false
			)), true, array('survey_type_id', 'survey_type'));
			$this->out('Updated fed_survey_id : ' . $fed_survey_id . ' with studyTypeId: ' . $response['StudyTypeID']);
		}
		
		$this->out('completed');
	}
	
	public function migrate_surveyvisitcache_loi() {
		ini_set('memory_limit', '1024M');
		$survey_visit_caches = $this->SurveyVisitCache->find('all', array(
			'fields' => array('SurveyVisitCache.id', 'SurveyVisitCache.loi', 'SurveyVisitCache.survey_id'),
			'conditions' => array(
				'SurveyVisitCache.loi is not null',
				'SurveyVisitCache.loi_seconds is null'
			),
			'recursive' => -1
		));
		$this->out('Processing Survey Visit Cache for '.count($survey_visit_caches).' projects');
		if ($survey_visit_caches) {
			foreach ($survey_visit_caches as $survey_visit_cache) {
				$this->SurveyVisitCache->create();
				$this->SurveyVisitCache->save(array('SurveyVisitCache' => array(
					'id' => $survey_visit_cache['SurveyVisitCache']['id'],
					'loi_seconds' => $survey_visit_cache['SurveyVisitCache']['loi'],
					'modified' => false
				)), true, array('loi_seconds'));
				$this->out('updated loi_seconds for project #'. $survey_visit_cache['SurveyVisitCache']['survey_id']);
			}
		}
	}

	public function payout_missing_completes() {
		$rows = file(WWW_ROOT . 'files/missing_completes.csv');
		if ($rows) {
			foreach ($rows as $row) {
				$row = explode(',', $row);
				$hash = explode('-', $row[1]);
				$project_id = $hash[0];
				$user_id = $hash[1];
				$survey_visit_hash = trim($row[1]);
				
				$project = $this->Project->find('first', array(
					'conditions' => array(
						'Project.id' => $project_id,
						'Project.date_created <=' => '2016-11-12 19:00:00',
					),
					'fields' => array('Project.id', 'Project.award'),
					'recursive' => -1,
				));

				if (!$project) {
					$this->out('Project not found (project may have been created after 2016-11-12 19:00:00) ' . $survey_visit_hash);
					continue;
				}

				$user = $this->User->find('first', array(
					'conditions' => array(
						'User.id' => $user_id
					),
					'fields' => array('User.id'),
					'recursive' => -1,
				));

				if (!$user) {
					continue;
				}

				$transaction = $this->Transaction->find('count', array(
					'conditions' => array(
						'Transaction.linked_to_id' => $project['Project']['id'],
						'Transaction.user_id' => $user_id,
						'Transaction.type_id' => TRANSACTION_SURVEY,
						'Transaction.paid' => true,
						'Transaction.deleted' => null,
					),
					'recursive' => -1
				));
				
				if ($transaction > 0) {
					$this->out('Payout transaction already exist for #'.$project_id.' '.$user_id);
					continue;
				}
				
				$this->Transaction->create();
				$this->Transaction->save(array('Transaction' => array(
					'override' => true,
					'type_id' => TRANSACTION_SURVEY,
					'linked_to_id' => $project['Project']['id'],
					'user_id' => $user_id,
					'amount' => $project['Project']['award'],
					'paid' => true,
					'name' => 'Payout for missing completes on 11/12 (#' . $project['Project']['id'] . ')',
					'status' => TRANSACTION_APPROVED,
					'executed' => date(DB_DATETIME)
				)));

				$this->out('Payout transaction created for UserID: ' . $user_id . ' and ProjectID: ' . $project['Project']['id']);
			}
		}
	}

	public function payout_missing_nq() {
		$rows = file(WWW_ROOT . 'files/missing_nq.csv');
		if ($rows) {
			foreach ($rows as $row) {
				$row = explode(',', $row);
				$hash = explode('-', $row[1]);
				$project_id = $hash[0];
				$user_id = $hash[1];
				$survey_visit_hash = trim($row[1]);
				
				$project = $this->Project->find('first', array(
					'conditions' => array(
						'Project.id' => $project_id,
						'Project.date_created <=' => '2016-11-12 19:00:00',
					),
					'fields' => array('Project.id', 'Project.nq_award'),
					'recursive' => -1,
				));

				if (!$project) {
					$this->out('Project not found (project may have been created after 2016-11-12 19:00:00) ' . $survey_visit_hash);
					continue;
				}

				$user = $this->User->find('first', array(
					'conditions' => array(
						'User.id' => $user_id
					),
					'fields' => array('User.id'),
					'recursive' => -1,
				));

				if (!$user) {
					continue;
				}

				$transaction = $this->Transaction->find('count', array(
					'conditions' => array(
						'Transaction.linked_to_id' => $project['Project']['id'],
						'Transaction.user_id' => $user_id,
						'Transaction.type_id' => TRANSACTION_SURVEY_NQ,
						'Transaction.paid' => true,
						'Transaction.deleted' => null,
					),
					'recursive' => -1
				));
				
				if ($transaction > 0) {
					$this->out('Payout transaction already exist for #'.$project_id.' '.$user_id);
					continue;
				}
				
				$this->Transaction->create();
				$this->Transaction->save(array('Transaction' => array(
					'override' => true,
					'type_id' => TRANSACTION_SURVEY_NQ,
					'linked_to_id' => $project['Project']['id'],
					'user_id' => $user_id,
					'amount' => $project['Project']['nq_award'],
					'paid' => true,
					'name' => 'Payout for missing NQ on 11/12 (#' . $project['Project']['id'] . ')',
					'status' => TRANSACTION_APPROVED,
					'executed' => date(DB_DATETIME)
				)));

				$this->out('Payout transaction created for UserID: ' . $user_id . ' and ProjectID: ' . $project['Project']['id']);
			}
		}
	}
	
	public function reconcile_points2shop() {
		if (!isset($this->args[0])) {
			$this->out('Please set date');
			return false;
		}

		$comparison_date = $this->args[0];
		
		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'p2s'
			)
		));
		$project_ids = $this->Project->find('list', array(
			'fields' => array('Project.id'),
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'Project.status' => PROJECT_STATUS_OPEN
			),
			'recursive' => -1
		));
		
		$transactions = $this->Transaction->find('list', array(
			'fields' => array('Transaction.id', 'Transaction.user_id'),
			'conditions' => array(
				'Transaction.linked_to_id' => $project_ids,
				'Transaction.type_id' => TRANSACTION_SURVEY,
				'Transaction.created LIKE' => $comparison_date.'%',
				'Transaction.deleted' => null,
			),
			'recursive' => -1
		)); 
		$transaction_counts = array_count_values($transactions); 
		
		$completes = explode("\n", file_get_contents(WWW_ROOT . 'files/p2s_completes.csv'));
		unset($completes[0]);
		
		$transaction_payout_counts = array();
		foreach ($completes as $key => $complete_row) {
			$complete_row = trim($complete_row); 
			if (empty($complete_row)) {
				continue;
			}
			
			$row = explode(',', $complete_row);
			/* 
				user@@hash, click date, approval date, 
			 */
			
			$tz = Utils::change_tz_to_utc($row[1], DB_DATETIME, 'America/Chicago'); 
			$date = date(DB_DATE, strtotime($tz)); 
			
			// skip this date
			if ($date != $comparison_date) {
				continue; 
			}
			list($user_id, $hash) = explode('@@', $row[0]); 
			if (!isset($transaction_payout_counts[$user_id])) {
				$transaction_payout_counts[$user_id] = 0; 
			}
			$transaction_payout_counts[$user_id]++;
		}
		
		foreach ($transaction_counts as $user_id => $count) {
			if (!isset($transaction_payout_counts[$user_id])) {
				continue;
			}
			if ($count > $transaction_payout_counts[$user_id]) {
				$diff = $count - $transaction_payout_counts[$user_id]; 
				
				$transactions = $this->Transaction->find('all', array(
					'fields' => array('Transaction.id', 'Transaction.user_id', 'Transaction.amount'),
					'conditions' => array(
						'Transaction.linked_to_id' => $project_ids,
						'Transaction.user_id' => $user_id,
						'Transaction.type_id' => TRANSACTION_SURVEY,
						'Transaction.created LIKE' => $comparison_date.'%',
						'Transaction.deleted' => null,
					),
					'recursive' => -1
				)); 
				$router_logs = $this->RouterLog->find('all', array(
					'conditions' => array(
						'RouterLog.source' => 'p2s',
						'RouterLog.user_id' => $user_id,
						'RouterLog.created LIKE' => $comparison_date.'%'
					),
					'order' => 'RouterLog.id ASC'
				)); 
				
				$i = 0; 
				foreach ($transactions as $transaction) {
					$i++; 
					if ($i <= $diff) {
						$this->Transaction->delete($transaction['Transaction']['id']); 
						$this->out('Deleted: '.$i.' transactions '.$transaction['Transaction']['id']);
					}
				}
				
				$i = 0; 
				foreach ($router_logs as $router_log) {
					$i++; 
					if ($i <= $diff) {
						$this->RouterLog->delete($router_log['RouterLog']['id']); 
						$this->out('Deleted '.$i.' router_log '.$router_log['RouterLog']['id']);
					}
				}

				$this->User->create();
				$this->User->rebuildBalances(array('User' => array('id' => $user_id)));
				
				$this->out($user_id.': '.$count.' vs '.$transaction_payout_counts[$user_id] .' diff: '.$diff);
			}
		}
	}
	
	public function remove_duplicate_router_logs_from_p2s() {
		$router_logs = $this->RouterLog->find('list', array(
			'fields' => array('RouterLog.id', 'RouterLog.partner_transaction_id'), 
			'conditions' => array(
				'RouterLog.partner_transaction_id >' => '0',
			)
		));
		$router_log_values = array_count_values($router_logs); 
		foreach ($router_log_values as $partner_transaction_id => $count) {
			if ($count <= 1) {
				continue;
			}
			$single_router_log_values = $this->RouterLog->find('list', array(
				'fields' => array('RouterLog.id'), 
				'conditions' => array(
					'RouterLog.partner_transaction_id' => $partner_transaction_id,
					'RouterLog.duplicate' => false
				),
				'order' => 'RouterLog.id ASC'
			));
			$first = array_shift($single_router_log_values); 
			
			if (!empty($single_router_log_values)) {
				foreach ($single_router_log_values as $id) {
					$this->RouterLog->create();
					$this->RouterLog->save(array('RouterLog' => array(
						'id' => $id,
						'duplicate' => true,
						'modified' => false
					)), true, array('duplicate')); 
					$this->out($id.' marked as dupe');
				}
			}
		}
	}
	
	public function dwolla_id() {
		$payment_methods = $this->PaymentMethod->find('all', array(
			'conditions' => array(
				'PaymentMethod.payment_method' => 'dwolla',
			)
		));
		foreach ($payment_methods as $payment_method) {
			$id = $payment_method['PaymentMethod']['id'] + 1;
			
			// auto increment id started to increment with an offset of 3 after id = 237898, because we switched the db to Galeria
			if ($payment_method['PaymentMethod']['id'] > 237898) {
				$id = $payment_method['PaymentMethod']['id'] + 3;
			}
			
			$dwolla_id = $this->PaymentMethod->find('first', array(
				'conditions' => array(
					'PaymentMethod.id' => $id,
					'PaymentMethod.user_id' => $payment_method['PaymentMethod']['user_id'],
					'PaymentMethod.payment_method' => 'dwolla_id',
				)
			));
			
			if (!$dwolla_id) {
				// try to get the active payment method dwolla_id
				$dwolla_id = $this->PaymentMethod->find('first', array(
					'conditions' => array(
						'PaymentMethod.user_id' => $payment_method['PaymentMethod']['user_id'],
						'PaymentMethod.payment_method' => 'dwolla_id',
						'PaymentMethod.status' => DB_ACTIVE,
					)
				));
			}
			
			if (!$dwolla_id) {
				
				// if dwolla_id is not found and user payment_id = 'refresh_token', make it null, so that user can reconnect
				if ($payment_method['PaymentMethod']['payment_id'] == 'refresh_token') {
					$this->PaymentMethod->create();
					$this->PaymentMethod->save(array('PaymentMethod' => array(
						'id' => $payment_method['PaymentMethod']['id'],
						'payment_id' => null,
						'modified' => false
					)), false, array('payment_id'));
				}
				
				continue;
			}
			
			$this->PaymentMethod->create();
			$this->PaymentMethod->save(array('PaymentMethod' => array(
				'id' => $payment_method['PaymentMethod']['id'],
				'payment_id' => $dwolla_id['PaymentMethod']['value'],
				'modified' => false
			)), false, array('payment_id'));
			
			// do not delete dwolla_id payment_method, some transactions may have been linked to that payment_id. (maintenance -> dwolla_withdrawals)
			$this->out('Dwolla id: '. $dwolla_id['PaymentMethod']['value']. ' moved to dwolla record id:# '. $payment_method['PaymentMethod']['id']);
		}
	}
	
	function precision_country() {
		$this->Project->bindModel(array(
			'hasOne' => array(
				'PrecisionProject'
			)
		));
		
		$this->Project->unbindModel(array(
			'hasMany' => array(
				'SurveyPartner',
				'ProjectOption'
			)
		));
		
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'PrecisionProject.precision_project_id >' => 0,
				'PrecisionProject.country' => ''
			),
			'fields' => array(
				'Project.id',
				'Project.country',
				'PrecisionProject.id',
			)
		));
		
		if (!$projects) {
			$this->out('All precision_projects.country already uptodate');
		}
		
		foreach ($projects as $project) {
			$this->Project->PrecisionProject->create();
			$this->Project->PrecisionProject->save(array(
				'PrecisionProject' => array(
					'id' => $project['PrecisionProject']['id'],
					'country' => $project['Project']['country']
				)
			), false, array('country'));
			$this->out('Project #'.$project['Project']['id']. ' precision_project.country updated to '. $project['Project']['country']);
		}
	}
	
	public function reset_all_offsets() {
		$transactions = $this->Transaction->find('all', array(
			'conditions' => array(
				'Transaction.id >=' => 31530760,
				'Transaction.status' => TRANSACTION_APPROVED,
				'Transaction.paid' => true,
				'Transaction.type_id' => TRANSACTION_OTHER,
				'Transaction.name' => 'Points offset',
			)
		));
		$total = count($transactions); 
		$i = 0; 
		foreach ($transactions as $transaction) {
			$i++; 
			$this->out($i.' / '.$total);
			$this->Transaction->delete($transaction['Transaction']['id']); 
		}
	}
	
	public function offset_rejected() {

		$last_id = 0;
		while (true) {
			$users = $this->User->find('all', array(
				'fields' => array('User.id', 'User.last_touched'),
				'conditions' => array(
					'User.hellbanned' => false,
					'User.last_touched >' => date(DB_DATETIME, strtotime('-6 weeks')),
					'User.id >=' => $last_id
				),
				'order' => 'User.id ASC',
				'limit' => '20000',
				'recursive' => -1
			));
			if (!$users) {
				break;
			}
			$total = count($users); 
			$i = 0; 
			$this->out('Total: '.$total);
			foreach ($users as $user) {
				$last_id = $user['User']['id'];
				$i++;
				$count = $this->Transaction->find('count', array(
					'conditions' => array(
						'Transaction.user_id' => $user['User']['id'],
						'Transaction.status' => TRANSACTION_APPROVED,
						'Transaction.paid' => true,
						'Transaction.type_id' => TRANSACTION_OTHER,
						'Transaction.name' => 'Points offset',
					)
				));
				if ($count > 0) {
					continue;
				}
			
				$amount = $this->Transaction->find('first', array(
					'fields' => array('SUM(amount) as amount'),
					'recursive' => -1,
					'conditions' => array(
						'Transaction.type_id <>' => TRANSACTION_WITHDRAWAL,
						'Transaction.status' => TRANSACTION_REJECTED,
						'Transaction.paid' => '1',
						'Transaction.user_id' => $user['User']['id'],
						'Transaction.deleted' => null,
						'Transaction.executed <' => '2016-12-14'
					)
				));
				$rejected_points = $amount[0]['amount'];
				if ($rejected_points > 0) {
					$this->Transaction->create();
					$this->Transaction->save(array('Transaction' => array(
						'executed' => date(DB_DATETIME),
						'user_id' => $user['User']['id'],
						'status' => TRANSACTION_APPROVED,
						'paid' => true,
						'type_id' => TRANSACTION_OTHER,
						'name' => 'Points offset',
						'amount' => $rejected_points
					)), array(
						'callbacks' => false,
						'validate' => false
					));
					$this->User->rebuildBalances($user);
					$this->out($i.'/'.$total.': '.$user['User']['id'].': '.$rejected_points); 
				}			
			}
		}
	}
	
	// lucid doesn't use state fips for their precodes; we need this for QEV
	public function populate_lucid_state_precodes() {
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('lucid.host', 'lucid.api.key'),
				'Setting.deleted' => false
			)
		));
		
		$http = new HttpSocket(array(
			'timeout' => 30,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$url = $settings['lucid.host'].'Lookup/v1/QuestionLibrary/AllQuestionOptions/9/96?key='.$settings['lucid.api.key']; 
		$response = $http->get($url);	
		$api_question = json_decode($response['body'], true);
		if (!isset($api_question['QuestionOptions'])) {
			$this->out('API call failed');
			return false;
		}
		foreach ($api_question['QuestionOptions'] as $state) {
			$lucid_zip = $this->LucidZip->find('first', array(
				'conditions' => array(
					'LucidZip.state_full' => $state['OptionText']
				)
			));
			if (!$lucid_zip) {
				$this->out('Could not find '.$state['OptionText']); 
				continue;
			}
			$precode = $state['Precode'];
			$lucid_zips = $this->LucidZip->find('all', array(
				'fields' => array('LucidZip.id', 'LucidZip.state_full'),
				'conditions' => array(
					'LucidZip.state_full' => $state['OptionText'],
					'LucidZip.lucid_precode is null'
				)
			));
			foreach ($lucid_zips as $lucid_zip) {
				$this->LucidZip->create();
				$this->LucidZip->save(array('LucidZip' => array(
					'id' => $lucid_zip['LucidZip']['id'],
					'lucid_precode' => $precode,
					'modified' => false
				)), true, array('lucid_precode'));
				$this->out('Updated '.$lucid_zip['LucidZip']['id'].' ('.$lucid_zip['LucidZip']['state_full'].') to '.$precode);
			}
		}
	}
	
	public function user_missing_points() {
		$this->HistoryRequest->bindModel(array('belongsTo' => array('Transaction')), false);
		$user_history_requests = $this->HistoryRequest->find('all', array(
			'fields' => array('DISTINCT(user_id) as distinct_user_id'),
			'recursive' => -1
		));
		$total = count($user_history_requests);
		$this->out('Total: '.$total.' requests');
		$i = 0; 
		if (!$user_history_requests) {
			$this->out('No history requests to process');
			return false;
		}
		foreach ($user_history_requests as $user_history_request) {
			$total_missing_points = 0;
			$i++;
			$requests_by_user = $this->HistoryRequest->find('all', array(
				'fields' => array('Transaction.amount'),
				'conditions' => array(
					'HistoryRequest.user_id' => $user_history_request['HistoryRequest']['distinct_user_id'],
					'Transaction.status' => TRANSACTION_APPROVED,
				),
				'contain' => array(
					'Transaction',
				)
			));
			if ($requests_by_user) {
				foreach ($requests_by_user as $request_by_user) {
					$total_missing_points = $total_missing_points + $request_by_user['Transaction']['amount'];
				}
			}

			$this->User->create();
			$this->User->save(array('User' => array(
				'id' => $user_history_request['HistoryRequest']['distinct_user_id'],
				'missing_points' => $total_missing_points, 
				'modified' => false
			)), array(
				'fieldList' => array('missing_points'),
				'validate' => false,
				'callbacks' => false
			));
			$this->out($i.'/'.$total.': #'.$user_history_request['HistoryRequest']['distinct_user_id']. ' missing_points updated to '. $total_missing_points);
		}
	}

	public function update_user_transaction_balance() {
		$conditions = array();
		
		if (!isset($this->args[0])) {
			$project_option = $this->ProjectOption->find('first', array(
				'conditions' => array(
					'ProjectOption.project_id' => '0',
					'ProjectOption.name' => 'fix.update_user_transaction_balance'
				)
			)); 
			if (!$project_option) {
				$projectOptionSource = $this->ProjectOption->getDataSource();
				$projectOptionSource->begin();
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'name' => 'fix.update_user_transaction_balance',
					'project_id' => '0',
					'value' => '0'
				)));
				$project_option = $this->ProjectOption->findById($this->ProjectOption->getInsertId()); 
				$projectOptionSource->commit();
				$last_id = 0; 
			}
			else {
				$last_id = $project_option['ProjectOption']['value']; 
			}
			$total = $this->User->find('count', array(
				'conditions' => array(
					'User.id >' => $last_id,
					'User.last_touched >' => date(DB_DATETIME, strtotime('-3 months'))
				),
				'recursive' => -1
			));
		}
		else {
			$total = 1; 
		}
		
		$this->out('Processing '.$total.' users');		
		$i = 0; 		
		
		while (true) {
			if (isset($this->args[0])) {
				$users = $this->User->find('all', array(
					'conditions' => array('User.id' => $this->args[0]),
					'recursive' => -1,
					'fields' => array('User.id')
				));
			}
			else {
				$users = $this->User->find('all', array(
					'conditions' => array(
						'User.id >' => $last_id,
						'User.last_touched >' => date(DB_DATETIME, strtotime('-3 months'))
					),
					'recursive' => -1,
					'fields' => array('User.id'),
					'order' => 'User.id ASC',
					'limit' => '10000'
				));
			}
			if (!$users) {
				break;
			}
			foreach ($users as $user) {
				$user_option = $this->UserOption->find('first', array(
					'conditions' => array(
						'UserOption.user_id' => $user['User']['id'],
						'UserOption.name' => 'last_transaction_with_balance'
					)
				));
				if (!$user_option) {
					$userOptionSource = $this->UserOption->getDataSource();
					$userOptionSource->begin();
					$this->UserOption->create();
					$this->UserOption->save(array('UserOption' => array(
						'user_id' => $user['User']['id'],
						'name' => 'last_transaction_with_balance',
						'value' => '0'
					))); 
					$user_option = $this->UserOption->findById($this->UserOption->getInsertId()); 
					$userOptionSource->commit();
					$last_transaction_id = 0; 
				}
				else {
					$last_transaction_id = $user_option['UserOption']['value'];
				}
				$last_id = $user['User']['id'];
				$i++;
				$transactions = $this->Transaction->find('all', array(
					'conditions' => array(
						'Transaction.user_id' => $user['User']['id'],
						'Transaction.deleted is null',
						'Transaction.id >' => $last_transaction_id
					),
					'order' => 'Transaction.id',
					'recursive' => -1,
					'fields' => array('Transaction.id', 'Transaction.amount', 'Transaction.paid', 'Transaction.status', 'Transaction.type_id')
				));
				
				if (!$transactions) {
					continue; 
				}
				$user_balance = null;
				$j = 0; 
				foreach ($transactions as $transaction) {
					$j++;
					if ($transaction['Transaction']['status'] == TRANSACTION_APPROVED && $transaction['Transaction']['paid']) {
						if (is_null($user_balance)) {
							$user_balance = $transaction['Transaction']['amount'];
						}
						else {
							if ($transaction['Transaction']['type_id'] == TRANSACTION_WITHDRAWAL) {
								$user_balance = $user_balance - ($transaction['Transaction']['amount'] * -1);
							}
							else {
								$user_balance = $user_balance + $transaction['Transaction']['amount'];
							}
						}
					}

					$this->Transaction->create();
					$this->Transaction->save(array('Transaction' => array(
						'id' => $transaction['Transaction']['id'],
						'modified' => false,
						'updated' => false,
						'user_balance' => $user_balance
					)), array(
						'callbacks' => false,
						'validate' => false,
						'fieldList' => array('user_balance')
					));
				}
				
				$this->UserOption->create();
				$this->UserOption->save(array('UserOption' => array(
					'id' => $user_option['UserOption']['id'],
					'value' => $transaction['Transaction']['id']
				)), true, array('value')); 
				
				if (!isset($this->args[0])) {
					$this->ProjectOption->create();
					$this->ProjectOption->save(array('ProjectOption' => array(
						'id' => $project_option['ProjectOption']['id'],
						'value' => $user['User']['id']
					)), true, array('value'));
				}
				$this->out($i.'/'.$total.': User #'.$user['User']['id'] .' ('.$j.' updated; > '.$last_transaction_id.')');
			}
			if (isset($this->args[0])) {
				break; // single user
			}
		}
		$this->out('Finished');
	}
	
	public function find_unpaid_withdrawals() {
		$transactions = $this->Transaction->find('all', array(
			'conditions' => array(
				'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
				'Transaction.original_id >' => '0',
				'Transaction.deleted is null'
			)
		));
		$this->out('Found '.count($transactions).' withdrawals');
		
		foreach ($transactions as $transaction) {
			$this->CashNotification->getDatasource()->reconnect();
			$cash_notification = $this->CashNotification->find('first', array(
				'conditions' => array(
					'CashNotification.user_id' => $transaction['Transaction']['user_id'],
					'CashNotification.amount' => abs($transaction['Transaction']['amount']),
					'CashNotification.created LIKE' => date(DB_DATE).'%'
				)
			));
			if (!$cash_notification) {
				$this->out($transaction['Transaction']['id']);
				$query = ROOT.'/app/Console/cake maintenance payouts '.$transaction['Transaction']['id'];
				passthru($query); 
			}
		}
		$this->out('Completed');
	}
	
	public function update_user_survey_active_session() {
		$project_option = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.project_id' => '0',
				'ProjectOption.name' => 'fix.update_user_survey_active_session'
			)
		)); 
		if (!$project_option) {
			$projectOptionSource = $this->ProjectOption->getDataSource();
			$projectOptionSource->begin();
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'name' => 'fix.update_user_survey_active_session',
				'project_id' => '0',
				'value' => '0'
			)));
			$project_option = $this->ProjectOption->findById($this->ProjectOption->getInsertId()); 
			$projectOptionSource->commit();
			$last_id = 0; 
		}
		else {
			$last_id = $project_option['ProjectOption']['value']; 
		}
		$total = $this->PanelistHistory->find('count', array(
			'conditions' => array(
				'PanelistHistory.id >' => $last_id
			),
			'recursive' => -1
		));
		$this->out('Total: '.$total.' panelist history to process');
		$i = 0; 
		$toggle_session = false;
		while (true) {
			$panelist_histories = $this->PanelistHistory->find('all', array(
				'conditions' => array(
					'PanelistHistory.id >' => $last_id
				),
				'recursive' => -1,
				'fields' => array('PanelistHistory.id', 'PanelistHistory.user_id', 'PanelistHistory.created'),
				'limit' => '10000'
			));
			if (!$panelist_histories) {
				break;
			}
			foreach ($panelist_histories as $key => $panelist_history) {
				$last_id = $panelist_history['PanelistHistory']['id'];
				$i++;
				$last_active_session = $this->PanelistHistory->find('first', array(
					'conditions' => array(
						'PanelistHistory.user_id' => $panelist_history['PanelistHistory']['user_id'],
						'PanelistHistory.created <' => $panelist_history['PanelistHistory']['created']
					),
					'recursive' => -1,
					'order' => 'PanelistHistory.id DESC',
					'fields' => array('PanelistHistory.id', 'PanelistHistory.created')
				));
				
				if (!empty($last_active_session)) {
					$session_length = round(abs((strtotime($panelist_history['PanelistHistory']['created']) - strtotime($last_active_session['PanelistHistory']['created']))) / 60);
					if ($session_length > 30) {
						$toggle_session = !$toggle_session; 
					}
				} 
				$this->PanelistHistory->create();
				$this->PanelistHistory->save(array('PanelistHistory' => array(
					'id' => $panelist_history['PanelistHistory']['id'],
					'is_session_active' => $toggle_session,
					'modified' => false
				)), true, array('is_session_active')); 
				
				$this->ProjectOption->create();
					$this->ProjectOption->save(array('ProjectOption' => array(
						'id' => $project_option['ProjectOption']['id'],
						'value' => $panelist_history['PanelistHistory']['id']
					)), true, array('value'));
				$this->out($i.' / '.$total.': #'.$panelist_history['PanelistHistory']['id'].' is_session_active updated to '.$toggle_session);
			}
		}	
		$this->out('Finished');
	}
	
	public function undelete_parent_qualifications() {
		$fed_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'fulcrum'
			)
		));
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.group_id' => $fed_group['Group']['id'],
				'Project.date_created >=' => date(DB_DATETIME, strtotime('-5 days')),
				'Project.temp_qualifications' => true
			),
			'fields' => array('Project.id'),
			'order' => 'Project.id DESC',
			'recursive' => -1
		));
		$this->out('Found '.count($projects).' projects');
		$i = $j = 0; 
		foreach ($projects as $project) {
			$count = $this->Qualification->find('count', array(
				'conditions' => array(
					'Qualification.deleted is null',
					'Qualification.parent_id is null',
					'Qualification.project_id' => $project['Project']['id']
				)
			));
			if ($count == 0) {
				$i++;
				$qualification = $this->Qualification->find('first', array(
					'conditions' => array(
						'Qualification.deleted is not null',
						'Qualification.parent_id is null',
						'Qualification.project_id' => $project['Project']['id']
					)
				));
				if ($qualification) {
					$this->Qualification->create();
					$this->Qualification->save(array('Qualification' => array(
						'id' => $qualification['Qualification']['id'],
						'deleted' => null,
						'modified' => false
					)), true, array('deleted')); 
					$this->out('#'.$project['Project']['id'].' has no qualifications; undeleted '.$qualification['Qualification']['id']);
				}
				else {
					$this->out('#'.$project['Project']['id'].' has no qualifications');
				}
			}
			else {
				$j++;
			}
		}
		$this->out('Missing: '.$i.'; Not missing: '.$j);
	}
	
	public function user_soft_delete_timestamp_update() {
		$users = $this->User->find('all', array(
			'fields' => array('User.id', 'User.created', 'User.last_touched'),
			'conditions' => array(
				'User.deleted' => true,
				'User.deleted_on is null'
			),
			'recursive' => -1
		));
		$total = count($users);
		$this->out('Found deleted users: ' . $total);
		
		$i = 1;
		foreach ($users as $user) {
			$this->User->create();
			$this->User->save(array('User' => array(
				'id' => $user['User']['id'],
				'deleted_on' => !empty($user['User']['last_touched']) ? $user['User']['last_touched'] : $user['User']['created'],
				'modified' => false
			)), true, array('deleted_on')); 
			$this->out($i.'/'.$total.': Deleted timestamp updated for User id: ' . $user['User']['id']);
			$i++;
		}
	}
	
	public function cint_user_qual_qe2_export() {
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('hostname.qe', 'qe.mintvine.username', 'qe.mintvine.password'),
				'Setting.deleted' => false
			)
		));
		
		if (count($settings) < 3) {
			$this->out('Missing required settings');
			return false;
		}
		
		$project_option = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.project_id' => '0',
				'ProjectOption.name' => 'fix.cint_user_qual_qe2_export'
			)
		)); 
		if (!$project_option) {
			$this->ProjectOption->getDataSource()->begin();
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'project_id' => '0',
				'value' => '0',
				'name' => 'fix.cint_user_qual_qe2_export',
			)));
			$project_option_id = $this->ProjectOption->getInsertId();
			$project_option = $this->ProjectOption->findById($project_option_id); 
			$this->ProjectOption->getDataSource()->commit();
		}
		$last_id = $project_option['ProjectOption']['value']; 
		$i = 0; 
		$count = $this->CintUserQualification->find('count', array(
			'recursive' => -1,
			'conditions' => array(
				'CintUserQualification.id >' => $last_id
			)
		));
		$this->out('Total: '.$count); 
		$exported = true;
		while (true) {
			if ($exported) {
					$cint_user_qualifications = $this->CintUserQualification->find('all', array(
					'conditions' => array(
						'CintUserQualification.id >' => $last_id,
					),
					'recursive' => -1,
					'limit' => '2500',
				));

				if (!$cint_user_qualifications) {
					$this->out('export completed.');
					break;
				}

				$user_qualifications = array();
				foreach ($cint_user_qualifications as $qualification) {
					$last_id = $qualification['CintUserQualification']['id'];
					$user_id = $qualification['CintUserQualification']['user_id'];
					$question_id = $qualification['CintUserQualification']['question_id'];
					$user_qualifications[$user_id][$question_id][] = $qualification['CintUserQualification']['variable_id'];
					$i++;
					$pct = round(($i / $count) * 100, 2);
					$this->out($i.'/ '.$count.' records completed ('.$pct.'%) '.$last_id);
				}
			}
			
			$post_data = array(
				'qualifications' => $user_qualifications,
				'partner' => 'cint'
			);
			
			if (!defined('IS_DEV_INSTANCE') || IS_DEV_INSTANCE === false) {
				try {
					$http = new HttpSocket(array(
						'timeout' => 30,
						'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
					));
					$http->configAuth('Basic', $settings['qe.mintvine.username'], $settings['qe.mintvine.password']);
					$return = $http->post($settings['hostname.qe'].'/qualifications/', json_encode($post_data), array(
						'header' => array('Content-Type' => 'application/json')
					));
					$this->out(count($user_qualifications).' User qualifications has been exported to QE2.');
					$exported = true;
					
					if ($return->code == '201') {
						$this->ProjectOption->create();
						$this->ProjectOption->save(array('ProjectOption' => array(
							'id' => $project_option['ProjectOption']['id'],
							'value' => $last_id
						)), true, array('value'));
					}
					else {
						echo json_encode($post_data); 
						print_r($post_data);
						print_r($return); 
						exit();
					}
				}
				catch (Exception $e) {
					$exported = false;
					$this->out('Failed posting cint user qualifications to QE2, trying again...');
				}
			}
		}
	}
	
	public function fix_qb_invoices_feb_2017() {
		$this->loadModel('Invoice');
		$invoices = $this->Invoice->find('list', array(
			'fields' => array('Invoice.id', 'Invoice.created'),
			'conditions' => array(
				'Invoice.quickbook_invoice_id is null',
				'Invoice.created >=' => '2017-01-15 00:00:00'
			)
		)); 
		if (empty($invoices)) {
			$this->out('No invoices to fix');
			return false;
		}

		App::uses('ComponentCollection', 'Controller');
		App::uses('Controller', 'Controller');
		App::uses('QuickBookComponent', 'Controller/Component');
		
		$collection = new ComponentCollection();
		$this->QuickBookComponent = new QuickBookComponent($collection);
		$controller = new Controller();
		$this->QuickBookComponent->initialize($controller);

		$i = 0; 
		foreach ($invoices as $invoice_id => $invoice_timestamp) {
		
			$this->Invoice->bindModel(array(
				'belongsTo' => array(
					'GeoCountry',
					'GeoState',
					'Project' => array(
						'fields' => array(
							'id',
						)
					)
				)
			));
			$invoice = $this->Invoice->findById($invoice_id);
			$this->out('Invoice '.$invoice_id.': https://cp.mintvine.com/invoices/view/'.$invoice['Invoice']['uuid']);
			$this->QuickBookComponent->create_invoice($invoice);
			$invoice = $this->Invoice->findById($invoice_id);
			$this->out('Synced to QB: https://qbo.intuit.com/app/invoice?txnId='.$invoice['Invoice']['quickbook_invoice_id']);
			$i++; 
		}
		
		$this->out('Total invoices re-synced: '.$i); 
	}
	
	public function reconciliation_total_completes() {
		$models_to_load = array('Reconciliation', 'ReconciliationRow');
		foreach ($models_to_load as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		$reconciliations = $this->Reconciliation->find('list', array(
			'fields' => array('Reconciliation.id'),
			'conditions' => array(
				'Reconciliation.total_completes is null',
				'Reconciliation.status' => array('imported', 'ready', 'completed', 'analyzed')
			),
			'order' => 'Reconciliation.id DESC'
		));
		
		if (!$reconciliations) {
			return;
		}
		
		$this->out(count($reconciliations).' reconciliations found.');
		foreach ($reconciliations as $reconciliation_id) {
			$count = $this->ReconciliationRow->find('count', array(
				'conditions' => array(
					'ReconciliationRow.reconciliation_id' => $reconciliation_id
				)
			));
			if ($count > 0) {
				$this->Reconciliation->create();
				$flag = $this->Reconciliation->save(array('Reconciliation' => array(
					'id' => $reconciliation_id,
					'total_completes' => $count,
					'modified' => false
				)), true, array('total_completes'));
			}
		}

		$this->out('Completed...');
	}

	public function regenerate_p2s_qualifications() {
		App::import('Lib', 'QueryEngine');
		$required_settings = array(
			'points2shop.secret',
			'points2shop.host',
		); 
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => $required_settings,
				'Setting.deleted' => false
			),
			'recursive' => -1
		));

		if (count($settings) < count($required_settings)) {
			$this->out('Missing required settings');
			return;
		}

		$HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));

		$points2shop_header = array(
			'header' => array(
				'X-YourSurveys-Api-Key' => $settings['points2shop.secret']
			),
		);

		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'points2shop',
			),
			'recursive' => -1,
		));

		$supported_countries = array_keys(unserialize(SUPPORTED_COUNTRIES));
		foreach ($supported_countries as $country) {
			$request_data = array(
				'country' => $country,
				'limit' => 10000,
			);
			
			$response = $HttpSocket->get($settings['points2shop.host'] . '/suppliers_api/surveys', $request_data, $points2shop_header);
			$response = json_decode($response, true);
			$p2s_projects = $response['surveys'];

			foreach ($p2s_projects as $p2s_project) {
				$this->Project->getDatasource()->reconnect();
				$project = $this->Project->find('first', array(
					'conditions' => array(
						'Project.mask' => $p2s_project['project_id'],
						'Project.group_id' => $group['Group']['id']
					),
					'recursive' => -1,
				));

				if (!$project) {
					$this->out('Project #' . $p2s_project['project_id']. ' does not exist.');
					continue;
				}

				$total_qualifications_list = $this->Points2shop->qualifications($p2s_project);
				$query_body = array(
					'partner' => 'points2shop',
					'qualifications' => array(
						'country' => array(!empty($project['Project']['country']) ? $project['Project']['country']: 'US')
					)
				);

				if (!empty($total_qualifications_list)) {
					foreach ($total_qualifications_list as $question => $answers) {
						$query_body['qualifications'][$question] = $answers;
					}
				}
				asort($query_body['qualifications']);
				$query_json = $raw_query_json = json_encode($query_body);
				$query_json = QueryEngine::qe2_modify_query($query_json);
				$query_hash = md5($query_json);

				$qualification = $this->Qualification->find('first', array(
					'conditions' => array(
						'Qualification.project_id' => $project['Project']['id'],
						'Qualification.parent_id is null',
					)
				));
				if ($qualification) {
					$this->Qualification->create();
					$this->Qualification->save(array('Qualification' => array(
						'id' => $qualification['Qualification']['id'],
						'query_hash' => $query_hash,
						'query_json' => $query_json,
						'raw_json' => $raw_query_json,
						'quota' => $p2s_project['remaining_completes'],
					)), true, array('query_hash', 'query_json', 'raw_json', 'quota'));
					$this->out('Parent qualifications updated for ' . $project['Project']['mask']);
				}
				else {
					$qualificationSource = $this->Qualification->getDataSource();
					$qualificationSource->begin();
					$this->Qualification->create();
					$this->Qualification->save(array('Qualification' => array(
						'project_id' => $project['Project']['id'],
						'name' => $project['Project']['mask'],
						'query_hash' => $query_hash,
						'query_json' => $query_json,
						'raw_json' => $raw_query_json,
						'quota' => $p2s_project['remaining_completes'],
						'active' => false,
					)));
					$qualificationSource->commit();
					$this->out('Parent qualifications created for ' . $project['Project']['mask']);
				}
			}
		}
		$this->out('Finished.');
	}
	
	public function backfill_users_notification_schedule() {
		$conditions = array();
		
		if (!isset($this->args[0])) {
			$project_option = $this->ProjectOption->find('first', array(
				'conditions' => array(
					'ProjectOption.project_id' => '0',
					'ProjectOption.name' => 'fix.backfill_users_notification_schedule'
				)
			)); 
			if (!$project_option) {
				$projectOptionSource = $this->ProjectOption->getDataSource();
				$projectOptionSource->begin();
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'name' => 'fix.backfill_users_notification_schedule',
					'project_id' => '0',
					'value' => '0'
				)));
				$project_option = $this->ProjectOption->findById($this->ProjectOption->getInsertId()); 
				$projectOptionSource->commit();
				$last_id = 0; 
			}
			else {
				$last_id = $project_option['ProjectOption']['value']; 
			}
			$total = $this->User->find('count', array(
				'conditions' => array(
					'User.id >' => $last_id,
					'User.deleted_on' => null
				),
				'recursive' => -1
			));
		}
		else {
			$total = 1; 
		}
		
		$this->out('Processing '.$total.' users');		
		$c = 0; 		
		
		while (true) {
			if (isset($this->args[0])) {
				$users = $this->User->find('all', array(
					'conditions' => array('User.id' => $this->args[0]),
					'fields' => array('User.id', 'QueryProfile.country')
				));
			}
			else {
				$users = $this->User->find('all', array(
					'conditions' => array(
						'User.id >' => $last_id,
						'User.deleted_on' => null
					),
					'fields' => array('User.id', 'QueryProfile.country'),
					'order' => 'User.id ASC',
					'limit' => '10000'
				));
			}
			if (!$users) {
				break;
			}
			foreach ($users as $user) {
				$last_id = $user['User']['id'];
				$c++;
				$notification_template = $this->NotificationTemplate->find('first', array(
					'conditions' => array(
						'NotificationTemplate.key' => 'new.'.strtolower($user['QueryProfile']['country'])
					)
				));
				$user_notification = $this->NotificationSchedule->find('first', array(
					'conditions' => array(
						'NotificationSchedule.user_id' => $user['User']['id'],
						'NotificationSchedule.type' => 'email'
					)
				));
				if (!$user_notification) {
					$notification_schedule = array();
					if ($notification_template) {
						for ($i = 0; $i < 24; $i++) {
							$hour = str_pad($i, 2, '0', STR_PAD_LEFT);
							$notification_schedule['NotificationSchedule'][$hour] = $notification_template['NotificationTemplate'][$hour];
						}	
						$total_emails = array_sum($notification_schedule['NotificationSchedule']);
						$notification_schedule['NotificationSchedule']['total_emails'] = $total_emails;
						$notification_schedule['NotificationSchedule']['type'] = 'email';
						$notification_schedule['NotificationSchedule']['user_id'] = $user['User']['id'];
						$this->NotificationSchedule->create();
						$this->NotificationSchedule->save($notification_schedule); 
					}
				}
				$this->out($c.'/'.$total.': Notification Schedule added for #'.$user['User']['id']);
			}
			if (!isset($this->args[0])) {
				$this->ProjectOption->create();
				$this->ProjectOption->save(array('ProjectOption' => array(
					'id' => $project_option['ProjectOption']['id'],
					'value' => $user['User']['id']
				)), true, array('value'));
			}
			if (isset($this->args[0])) {
				break; // single user
			}
		}
		$this->out('Finished');
	}

	public function update_p2s_qualifications() {
		App::import('Lib', 'QueryEngine');
		App::import('Model', 'Question');
		$this->Question = new Question;

		$required_settings = array(
			'points2shop.secret',
			'points2shop.host',
		); 
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => $required_settings,
				'Setting.deleted' => false
			),
			'recursive' => -1
		));

		if (count($settings) < count($required_settings)) {
			$this->out('Missing required settings');
			return;
		}

		$HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));

		$points2shop_header = array(
			'header' => array(
				'X-YourSurveys-Api-Key' => $settings['points2shop.secret']
			),
		);

		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'points2shop',
			),
			'recursive' => -1,
		));

		$supported_countries = array_keys(unserialize(SUPPORTED_COUNTRIES));
		foreach ($supported_countries as $country) {
			$request_data = array(
				'country' => $country,
				'limit' => 10000,
			);
			
			$response = $HttpSocket->get($settings['points2shop.host'] . '/suppliers_api/surveys', $request_data, $points2shop_header);
			$response = json_decode($response, true);
			$p2s_projects = $response['surveys'];

			foreach ($p2s_projects as $p2s_project) {
				$this->Project->getDatasource()->reconnect();
				$project = $this->Project->find('first', array(
					'conditions' => array(
						'Project.mask' => $p2s_project['project_id'],
						'Project.group_id' => $group['Group']['id']
					),
					'recursive' => -1,
				));

				if (!$project) {
					$this->out('Project #' . $p2s_project['project_id']. ' does not exist.');
					continue;
				}

				$total_qualifications_list = $this->Points2shop->qualifications($p2s_project);
				$query_body = array(
					'partner' => 'points2shop',
					'qualifications' => array(
						'country' => array(!empty($project['Project']['country']) ? $project['Project']['country']: 'US')
					)
				);

				if (!empty($total_qualifications_list)) {
					foreach ($total_qualifications_list as $question => $answers) {
						$query_body['qualifications'][$question] = $answers;
					}
				}
				asort($query_body['qualifications']);
				$query_json = $raw_query_json = json_encode($query_body);
				$query_json = QueryEngine::qe2_modify_query($query_json);
				$query_hash = md5($query_json);

				$payouts = $this->Points2shop->payout($p2s_project);

				$qualification = $this->Qualification->find('first', array(
					'conditions' => array(
						'Qualification.project_id' => $project['Project']['id'],
						'Qualification.parent_id is null',
					)
				));
				if ($qualification) {
					$this->Qualification->create();
					$this->Qualification->save(array('Qualification' => array(
						'id' => $qualification['Qualification']['id'],
						'query_hash' => $query_hash,
						'query_json' => $query_json,
						'raw_json' => $raw_query_json,
						'cpi' => $payouts['client_rate'],
						'award' => $payouts['award'],
					)), true, array('query_hash', 'query_json', 'raw_json', 'cpi', 'award'));
					$this->out('Parent qualifications updated for ' . $project['Project']['mask']);
					$parent_qualification_id = $qualification['Qualification']['id'];
				}
				else {
					$qualificationSource = $this->Qualification->getDataSource();
					$qualificationSource->begin();
					$this->Qualification->create();
					$this->Qualification->save(array('Qualification' => array(
						'project_id' => $project['Project']['id'],
						'name' => $project['Project']['mask'],
						'query_hash' => $query_hash,
						'query_json' => $query_json,
						'raw_json' => $raw_query_json,
						'quota' => $p2s_project['remaining_completes'],
						'active' => false,
						'cpi' => $payouts['client_rate'],
						'award' => $payouts['award'],
					)));
					$parent_qualification_id = $this->Qualification->getInsertId();
					$qualificationSource->commit();
					$this->out('Parent qualifications created for ' . $project['Project']['mask']);
				}

				//Child qualifications
				if (empty($p2s_project['quotas'])) {
					continue;
				}
				foreach ($p2s_project['quotas'] as $survey_quota) {
					if (!is_array($survey_quota)) {
						continue;
					}
					$query_body_for_quota = array(
						'partner' => 'points2shop',
						'qualifications' => array(
							'country' => array(!empty($project['Project']['country']) ? $project['Project']['country']: 'US')
						)
					);
					foreach ($survey_quota['conditions'] as $question => $answers) {
						if (empty($question) || empty($answers)) {
							continue;
						}
						$mv_question = $this->Question->find('first', array(
							'conditions' => array(
								'Question.question' => $question,
								'Question.partner' => 'points2shop',
							),
							'fields' => array('Question.partner_question_id'),
							'recursive' => -1,
						));
						$query_body_for_quota['qualifications'][$mv_question['Question']['partner_question_id']] = $answers;
					}
					$child_query_json = json_encode($query_body_for_quota);
					$child_query_hash = md5($child_query_json);

					$child_qualification = $this->Qualification->find('first', array(
						'conditions' => array(
							'Qualification.parent_id' => $parent_qualification_id,
							'Qualification.partner_qualification_id' =>  $survey_quota['id'],
							'Qualification.deleted is null'
						)
					));

					if (!$child_qualification) {
						$qualificationSource = $this->Qualification->getDataSource();
						$qualificationSource->begin();
						$this->Qualification->create();
						$this->Qualification->save(array('Qualification' => array(
							'project_id' => $project['Project']['id'],
							'parent_id' => $parent_qualification_id,
							'partner_qualification_id' => $survey_quota['id'],
							'name' => $survey_quota['id'],
							'query_hash' => $child_query_hash,
							'query_json' => $child_query_json,
							'quota' => $survey_quota['remaining_completes'],
							'cpi' => $payouts['client_rate'],
							'award' => $payouts['award'],
						)));
						$child_qualification_id = $this->Qualification->getInsertId();
						
						$this->ProjectLog->create();
						$this->ProjectLog->save(array('ProjectLog' => array(
							'project_id' => $project['Project']['id'],
							'type' => 'qqq.subqual.created',
							'description' => 'Child qualification id: ' . $child_qualification_id . ' created.',
						)));
						
						$qualificationSource->commit();
						$this->out('Child qualification created, '. $child_qualification_id);
					}
					else {
						$this->Qualification->create();
						$this->Qualification->save(array('Qualification' => array(
							'id' => $child_qualification['Qualification']['id'],
							'query_hash' => $child_query_hash,
							'query_json' => $child_query_json,
							'cpi' => $payouts['client_rate'],
							'award' => $payouts['award'],
						)), true, array('query_hash', 'query_json', 'cpi', 'award'));
						$this->out('Child qualification updated, ' . $child_qualification['Qualification']['id']);
					}
				}
			}
		}
		$this->out('Finished.');
	}

	public function update_p2s_questions() {
		App::import('Model', 'Question');
		$this->Question = new Question;
		$questions = $this->Question->find('all', array(
			'conditions' => array(
				'Question.partner' => 'points2shop'
			),
			'fields' => array('Question.id'),
			'recursive' => -1,
		));
		if ($questions) {
			foreach ($questions as $question) {
				$this->Question->create();
				$this->Question->save(array('Question' => array(
					'id' => $question['Question']['id'],
					'ignore' => false,
					'staging' => true,
				)), true, array('ignore', 'staging'));

				$this->out('Question#' . $question['Question']['id'] . ' updated');
			}
		}
		$this->out('Finished.');
	}

	public function update_cint_questions() {
		App::import('Model', 'Question');
		$this->Question = new Question;
		$questions = $this->Question->find('all', array(
			'conditions' => array(
				'Question.partner' => 'cint'
			),
			'fields' => array('Question.id'),
			'recursive' => -1,
		));
		if ($questions) {
			foreach ($questions as $question) {
				$this->Question->create();
				$this->Question->save(array('Question' => array(
					'id' => $question['Question']['id'],
					'ignore' => false,
					'staging' => true,
				)), true, array('ignore', 'staging'));

				$this->out('Question#' . $question['Question']['id'] . ' updated');
			}
		}
		$this->out('Finished.');
	}
	
	public function remove_gb_question_answer_texts() {
		App::import('Model', 'AnswerText');
		$this->AnswerText = new AnswerText;
		App::import('Model', 'QuestionText');
		$this->QuestionText = new QuestionText;

		$question_texts = $this->QuestionText->find('all', array(
			'fields' => array('QuestionText.id'),
			'conditions' => array(
				'QuestionText.country' => 'UK'
			),
			'recursive' => -1
		));
		if ($question_texts) {
			foreach ($question_texts as $question_text) {
				$this->QuestionText->delete($question_text['QuestionText']['id']);
				$this->out('QuestionText #' . $question_text['QuestionText']['id'] . ' deleted');
			}
		}

		$answer_texts = $this->AnswerText->find('all', array(
			'fields' => array('AnswerText.id'),
			'conditions' => array(
				'AnswerText.country' => 'UK'
			),
			'recursive' => -1
		));
		if ($answer_texts) {
			foreach ($answer_texts as $answer_text) {
				$this->AnswerText->delete($answer_text['AnswerText']['id']);
				$this->out('AnswerText #' . $answer_text['AnswerText']['id'] . ' deleted');
			}
		}

		$this->out('Finished');
	}
	
	public function populate_total_counts() {
		$project_option = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.name' => 'fix.populate_total_counts',
				'ProjectOption.project_id' => '0'
			)
		)); 
		if (!$project_option) {
			$this->ProjectOption->getDataSource()->begin();
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'name' => 'fix.populate_total_counts',
				'project_id' => '0',
				'value' => '0'
			)));
			$project_option = $this->ProjectOption->findById($this->ProjectOption->getInsertId()); 
			$this->ProjectOption->getDataSource()->commit();
			$last_id = 0; 
		}
		else {
			$last_id = $project_option['ProjectOption']['value'];
		}
		$i = 0; 
		while (true) {
			$this->Project->unbindModel(array('hasMany' => array('SurveyPartner', 'ProjectOption', 'ProjectAdmin')));
			$projects = $this->Project->find('all', array(
				'fields' => array('Project.id', 'SurveyVisitCache.id'),
				'conditions' => array(
					'Project.id >' => $last_id
				),
				'order' => 'Project.id ASC',
				'limit' => 10000
			));
			if (!$projects) {
				break;
			}
			foreach ($projects as $project) {
				$count_invites = $this->SurveyUser->find('count', array(
					'conditions' => array(
						'SurveyUser.survey_id' => $project['Project']['id'],
					),
					'recursive' => -1
				)); 
				if ($count_invites > 0) {
					$count_email_invites = $this->SurveyUser->find('count', array(
						'conditions' => array(
							'SurveyUser.survey_id' => $project['Project']['id'],
							'SurveyUser.notification' => '1'
						),
						'recursive' => -1
					)); 
				}
				else {
					$count_email_invites = 0; 
				}
				$this->out($project['Project']['id'].': '.$count_invites.'/'.$count_email_invites);
				
				if ($project['SurveyVisitCache']['id'] > 0) {
					$this->SurveyVisitCache->create();
					$this->SurveyVisitCache->save(array('SurveyVisitCache' => array(
						'id' => $project['SurveyVisitCache']['id'],
						'invited' => $count_invites,
						'emailed' => $count_email_invites,
						'modified' => false
					)), array(
						'callbacks' => false,
						'validate' => false,
						'fieldList' => array('emailed', 'invited')
					));
				}
				$last_id = $project['Project']['id']; 
				$i++;
			}
			
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'id' => $project_option['ProjectOption']['id'],
				'value' => $last_id
			)), true, array('value'));
		}
		
		$this->out('Completed '.$i.' projects');
	}

	function mailgun_logs() {
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('mailgun.api.key', 'mailgun.domain'),
				'Setting.deleted' => false
			)
		));
		if (count($settings) != 2) {
			$this->out('Missing settings');
			return false;
		}
		
		$mailgun_log = $this->MailgunLog->find('first', array(
			'fields' => array('MailgunLog.timestamp'),	
			'order' => 'MailgunLog.id DESC'
		));
		if ($mailgun_log) {
			$start = $mailgun_log['MailgunLog']['timestamp']. ' UTC';
		}
		else {
			$start = '2017-03-17 00:00:00 UTC';
		}
		App::uses('HttpSocket', 'Network/Http');
		$HttpSocket = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));

		$queryString = array(
			'begin' => strtotime($start),
			'end' => strtotime('2017-03-17 23:59:59 UTC'),
			'ascending' => 'yes',
			'limit' =>  300,
			'event' => 'delivered'
		);
		$url = 'https://api:'.$settings['mailgun.api.key'].'@api.mailgun.net/v3/'.$settings['mailgun.domain'].'/events'; 
		while (true) {
			$results = $HttpSocket->get($url, $queryString);
			$items = json_decode($results->body, true);
			if (empty($items['items'])) {
				break;
			}
			foreach ($items['items'] as $item) {
				$mailgun_log = $this->MailgunLog->find('first', array(
					'fields' => array('MailgunLog.id'),
					'conditions' => array(
						'MailgunLog.mailgun_id' => $item['id']
					),
					'recursive' => -1
				)); 
				if ($mailgun_log) {
					continue;
				}
				$save = array('MailgunLog' => array(
					'mailgun_id' => $item['id'],
					'target' => $item['envelope']['targets'],
					'subject' => $item['message']['headers']['subject'],
					'event' => $item['event'],
					'timestamp' => date(DB_DATETIME, $item['timestamp'])
				));
				if (isset($item['user-variables']['my-custom-data'])) {
					$data = json_decode($item['user-variables']['my-custom-data'], true);
					if (isset($data['survey_id'])) {
						$save['MailgunLog']['project_id'] = $data['survey_id']; 
					}
					if (isset($data['user_id'])) {
						$save['MailgunLog']['user_id'] = $data['user_id']; 
					}
				}
				
				$this->MailgunLog->create();
				$this->MailgunLog->save($save); 
				$i++;
				$this->out('#' . $i . ' data imported for event id #' . $item['id']);	
			}
			$url = urldecode($items['paging']['next']); 
			$this->out('Loading '.$url); 
		}
	}
	
	public function populate_project_payouts() {
		$this->loadModel('ProjectPayout');
		$project_option = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.project_id' => '0',
				'ProjectOption.name' => 'fix.populate_project_payouts.min_id'
			)			
		)); 
		if (!$project_option) {
			$min_id = $this->SurveyVisit->find('first', array(
				'fields' => array('min(SurveyVisit.id) as min_id')
			));
			$min_id = $min_id[0]['min_id'];
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'project_id' => '0',
				'name' => 'fix.populate_project_payouts.min_id',
				'value' => $min_id
			))); 
		}
		else {
			$min_id = $project_option['ProjectOption']['value']; 
		}
		
		$max_id = $this->SurveyVisit->find('first', array(
			'fields' => array('MAX(SurveyVisit.id) as max_id')
		));
		$max_id = $max_id[0]['max_id'];
		$total = $max_id - $min_id; 
		
		$mintvine_partner = $this->Partner->find('first', array(
			'fields' => array('Partner.id'),
			'conditions' => array(
				'Partner.key' => 'mintvine',
			)
		));
		
		$i = 0; 
		while (true) {
			$survey_visits = $this->SurveyVisit->find('all', array(
				'fields' => array('SurveyVisit.id', 'SurveyVisit.partner_id', 'SurveyVisit.partner_user_id', 'SurveyVisit.survey_id', 'SurveyVisit.created', 'SurveyVisit.type'),
				'conditions' => array(
					'SurveyVisit.id >' => $min_id
				),
				'order' => 'SurveyVisit.id ASC',
				'limit' => '1000',
				'recursive' => -1
			)); 
			if (!$survey_visits) {
				break;
			}
			$this->out('Processing '.count($survey_visits)); 
			foreach ($survey_visits as $survey_visit) {
				$i++;
				$min_id = $survey_visit['SurveyVisit']['id'];
				if (!in_array($survey_visit['SurveyVisit']['type'], array(SURVEY_NQ, SURVEY_COMPLETED))) {
					continue;
				}
				$save = array('SurveyVisit' => array(
					'id' => $survey_visit['SurveyVisit']['id'],
					'user_id' => '0', 
					'client_rate_cents' => null,
					'user_payout_cents' => null,
					'modified' => false
				)); 
			
				if ($survey_visit['SurveyVisit']['partner_id'] == $mintvine_partner['Partner']['id']) {
					list($survey_id, $user_id, $nada1, $nada2) = explode('-', $survey_visit['SurveyVisit']['partner_user_id']);
					$save['SurveyVisit']['user_id'] = $user_id;
				}
			
				if ($survey_visit['SurveyVisit']['type'] == SURVEY_NQ) {
					if ($survey_visit['SurveyVisit']['partner_id'] != $mintvine_partner['Partner']['id']) {
						continue;
					}
					else {
						$project = $this->Project->find('first', array(
							'fields' => array('Project.nq_award'),
							'conditions' => array(
								'Project.id' => $survey_visit['SurveyVisit']['survey_id']
							),
							'recursive' => -1
						));
						$save['SurveyVisit']['user_payout_cents'] = $project['Project']['nq_award']; 
					}
				}
				elseif ($survey_visit['SurveyVisit']['type'] == SURVEY_COMPLETED) {
					$project = $this->Project->find('first', array(
						'fields' => array('Project.client_rate', 'Project.award'),
						'conditions' => array(
							'Project.id' => $survey_visit['SurveyVisit']['survey_id']
						),
						'recursive' => -1
					));
					$project_rates = $this->ProjectRate->find('all', array(
						'fields' => array('ProjectRate.created', 'ProjectRate.client_rate', 'ProjectRate.award'),
						'conditions' => array(
							'ProjectRate.project_id' => $survey_visit['SurveyVisit']['survey_id']
						),
						'order' => 'ProjectRate.id ASC'
					)); 
					$client_rate_cents = $project['Project']['client_rate'] * 100; 
					$user_award_cents = $project['Project']['award']; 
				
					if ($project_rates) {
						foreach ($project_rates as $project_rate) {
							if (strtotime($project_rate['ProjectRate']['created']) > strtotime($survey_visit['SurveyVisit']['created'])) {
								break;
							}
							$client_rate_cents = $project_rate['ProjectRate']['client_rate'] * 100; 
							$user_award_cents = $project_rate['ProjectRate']['award']; 
						}
					}
			
					$save['SurveyVisit']['client_rate_cents'] = $client_rate_cents; 
					$save['SurveyVisit']['user_payout_cents'] = $user_award_cents;	
				}
			
				$this->SurveyVisit->create();
				$this->SurveyVisit->save($save, true, array('user_id', 'client_rate_cents', 'user_payout_cents')); 
			
				// this may create some dupes; but i'll go in and manually cross-check the last 100 projects after this is done
				if ($save['SurveyVisit']['user_payout_cents'] > 0) {
					$this->ProjectPayout->create();
					$this->ProjectPayout->save(array('ProjectPayout' => array(
						'project_id' => $survey_visit['SurveyVisit']['survey_id'],
						'partner_id' => $survey_visit['SurveyVisit']['partner_id'],
						'client_rate_cents' => $save['SurveyVisit']['client_rate_cents'],
						'user_payout_cents' => $save['SurveyVisit']['user_payout_cents'],
						'type' => $survey_visit['SurveyVisit']['type'],
					)));	
				}
			
				$pct = number_format(round(($i / $total) * 100, 8), 8);
				$this->out($i.'/'.$total.' ('.$pct.'%): '.$save['SurveyVisit']['id'].': {'.$save['SurveyVisit']['client_rate_cents'].','.$save['SurveyVisit']['user_payout_cents'].'}'); 
			}
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'id' => $project_option['ProjectOption']['id'],
				'value' => $min_id
			)), true, array('value')); 
		}
		$this->out('Completed');
	}
	
	// TODO: need to re-run this at some point to make sure data is 100%
	public function populate_survey_visit_user_ids() {
		$this->loadModel('ProjectPayout');
		$project_option = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.project_id' => '0',
				'ProjectOption.name' => 'fix.populate_survey_visit_user_ids.min_id'
			)			
		)); 
		if (!$project_option) {
			$min_id = $this->SurveyVisit->find('first', array(
				'fields' => array('min(SurveyVisit.id) as min_id')
			));
			$min_id = $min_id[0]['min_id'];
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'project_id' => '0',
				'name' => 'fix.populate_survey_visit_user_ids.min_id',
				'value' => $min_id
			))); 
		}
		else {
			$min_id = $project_option['ProjectOption']['value']; 
		}
		
		$max_id = $this->SurveyVisit->find('first', array(
			'fields' => array('MAX(SurveyVisit.id) as max_id')
		));
		$max_id = $max_id[0]['max_id'];
		$total = $max_id - $min_id; 
		
		$mintvine_partner = $this->Partner->find('first', array(
			'fields' => array('Partner.id'),
			'conditions' => array(
				'Partner.key' => 'mintvine',
			)
		));
		
		$i = 0; 
		while (true) {
			$survey_visits = $this->SurveyVisit->find('all', array(
				'fields' => array('SurveyVisit.id', 'SurveyVisit.partner_id', 'SurveyVisit.partner_user_id', 'SurveyVisit.survey_id', 'SurveyVisit.created', 'SurveyVisit.type'),
				'conditions' => array(
					'SurveyVisit.id >' => $min_id
				),
				'order' => 'SurveyVisit.id ASC',
				'limit' => '1000',
				'recursive' => -1
			)); 
			if (!$survey_visits) {
				break;
			}
			$this->out('Processing '.count($survey_visits)); 
			foreach ($survey_visits as $survey_visit) {
				$i++;
				$min_id = $survey_visit['SurveyVisit']['id'];
				if ($mintvine_partner['Partner']['id'] != $survey_visit['SurveyVisit']['partner_id']) {
					continue;
				}
				$save = array('SurveyVisit' => array(
					'id' => $survey_visit['SurveyVisit']['id'],
					'user_id' => '0', 
					'modified' => false
				)); 
				list($survey_id, $user_id, $nada1, $nada2) = explode('-', $survey_visit['SurveyVisit']['partner_user_id']);
				$save['SurveyVisit']['user_id'] = $user_id;
			
				$this->SurveyVisit->create();
				$this->SurveyVisit->save($save, true, array('user_id')); 
			
				$pct = number_format(round(($i / $total) * 100, 8), 8);
				$this->out($i.'/'.$total.' ('.$pct.'%): '.$save['SurveyVisit']['id'].': {'.$survey_visit['SurveyVisit']['partner_user_id'].','.$user_id.'}'); 
			}
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'id' => $project_option['ProjectOption']['id'],
				'value' => $min_id
			)), true, array('value')); 
		}
		$this->out('Completed');
	}
	
	public function execute_qualifications_cleanup() {
		$this->Qualification->bindModel(array('hasOne' => array('QualificationStatistic')));
		$qualifications = $this->Qualification->find('all', array(
			'fields' => array('Qualification.id', 'Qualification.processing', 'Qualification.project_id', 'QualificationStatistic.id'),
			'conditions' => array(
				'Qualification.processing <=' => '2017-03-24 22:41:08',
				'Qualification.parent_id is null'
			)
		));
		$this->loadModel('QualificationUser');
		$this->loadModel('QualificationStatistic');
		
		foreach ($qualifications as $qualification) {
			$total = $this->QualificationUser->find('count', array(
				'conditions' => array(
					'QualificationUser.qualification_id' => $qualification['Qualification']['id']
				)
			)); 
					
			$this->Qualification->create();
			$this->Qualification->save(array('Qualification' => array(
				'id' => $qualification['Qualification']['id'],
				'active' => true,
				'processing' => null
			)), true, array('processing', 'active'));
		
			$this->QualificationStatistic->create();
			$this->QualificationStatistic->save(array('QualificationStatistic' => array(
				'id' => $qualification['QualificationStatistic']['id'],
				'invited' => $total
			)), true, array('invited')); 
		
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $qualification['Qualification']['project_id'],
				'type' => 'qqq.qual.activated',
				'description' => 'Qualification #'.$qualification['Qualification']['id'].' activated.',
			)));
			
			$this->out('Fixed up '.$qualification['Qualification']['id'].' in '.$qualification['Qualification']['project_id']); 
		}
	}
	
	// gb short names got wiped somehow
	public function short_names() {
		$this->Question->bindModel(array('hasMany' => array('QuestionText')));
		$questions = $this->Question->find('all', array(
			'conditions' => array(
				'Question.high_usage is not null'
			)
		)); 
		foreach ($questions as $question) {
			$us_value = null;
			$gb_value = null;
			$gb_id = null; 
			foreach ($question['QuestionText'] as $question_text) {
				if ($question_text['country'] == 'GB') {
					$gb_value = $question_text['cp_text']; 
					$gb_id = $question_text['id'];
				}
				if ($question_text['country'] == 'US') {
					$us_value = $question_text['cp_text']; 
				}
			}
			if (!is_null($gb_id) && empty($gb_value) && !empty($us_value)) {
				$this->out('Set '.$gb_id.' to '.$us_value); 
				$this->Question->QuestionText->create();
				$this->Question->QuestionText->save(array('QuestionText' => array(
					'id' => $gb_id,
					'cp_text' => $us_value
				)), true, array('cp_text')); 
			}
		}
	}

	// https://basecamp.com/2045906/projects/1413421/todos/299091431
	public function update_project_logs() {
		$project_logs = $this->ProjectLog->find('list', array(
			'fields' => array('ProjectLog.id', 'ProjectLog.type'),
			'conditions' => array(
				'ProjectLog.type LIKE' => 'qqq%'
			)
		));
		if (!$project_logs) {
			$this->out('There are no logs to be fixed.');
			return false;
		}

		$this->out('Starting update logs.');
		$match_types = array(
			'qqq.qual.activated' => 'qualification.open',
			'qqq.subqual.created' => 'qualification.created',
			'qqq.public' => 'project.public',
			'qqq.missing.qual' => 'project.qualifications.missing',
			'qqq.qual.created' => 'qualification.created',
			'qqq.subqual.updated' => 'qualification.updated',
			'qqq.qual.updated' => 'qualification.updated',
		);
		foreach ($project_logs as $id => $type) {
			$project_log = array(
				'id' => $id
			);
			if (in_array($type, $match_types)) {
				$project_log['type'] = $match_types[$type];
			}
			else {
				$project_log['type'] = str_replace('qqq', 'qualification', $type);
			}

			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => $project_log, true, array('type')));
			$this->out($id . ': Set "' . $type . '" to "' . $project_log['type'] . '".');
		}
		$this->out('Logs have been updated successfully.');
	}
	
	public function rewrite_dupe_values() {
		$project_option = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.name' => 'fix.rewrite_dupe_values',
				'ProjectOption.project_id' => '0'
			)
		));
		if (!$project_option) {
			$this->ProjectOption->getDatasource()->begin();
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'name' => 'fix.rewrite_dupe_values',
				'project_id' => '0',
				'value' => '0'
			))); 
			$project_option_id = $this->ProjectOption->getInsertId();
			$this->ProjectOption->getDatasource()->commit();
			$id = 0;
		}
		else {
			$id = $project_option['ProjectOption']['value'];
			$project_option_id = $project_option['ProjectOption']['id']; 
		}
		
		while (true) {
			$survey_visits = $this->SurveyVisit->find('all', array(
				'fields' => array('SurveyVisit.survey_id', 'SurveyVisit.id'),
				'conditions' => array(
					'SurveyVisit.type' => SURVEY_DUPE,
					'SurveyVisit.id >' => $id
				),
				'order' => 'SurveyVisit.id ASC',
				'limit' => 10000
			));
			if (!$survey_visits) {
				break;
			}
			$this->out('Iterating through '.count($survey_visits). '; starting from '.$id); 
			foreach ($survey_visits as $survey_visit) {
				$click_survey_visit = $this->SurveyVisit->find('first', array(
					'fields' => array('SurveyVisit.id'),
					'conditions' => array(
						'SurveyVisit.survey_id' => $survey_visit['SurveyVisit']['survey_id'],
						'SurveyVisit.result_id' => $survey_visit['SurveyVisit']['id']
					)
				));
				if ($click_survey_visit) {

					$this->SurveyVisit->create();
					$this->SurveyVisit->save(array('SurveyVisit' => array(
						'id' => $survey_visit['SurveyVisit']['id'],
						'modified' => false,
						'type' => SURVEY_NQ_FRAUD,
						'result_note' => 'dupe'
					)), true, array('type', 'result_note'));
					
					$this->SurveyVisit->create();
					$this->SurveyVisit->save(array('SurveyVisit' => array(
						'id' => $click_survey_visit['SurveyVisit']['id'],
						'modified' => false,
						'result' => SURVEY_NQ_FRAUD,
						'result_note' => 'dupe'
					)), true, array('result', 'result_note'));
					
					$this->out($survey_visit['SurveyVisit']['id'].' was a terminating dupe from '.$click_survey_visit['SurveyVisit']['id']);
				}
				else {
					$this->out($survey_visit['SurveyVisit']['id'].' was an entry dupe');
					$this->SurveyVisit->create();
					$this->SurveyVisit->save(array('SurveyVisit' => array(
						'id' => $survey_visit['SurveyVisit']['id'],
						'modified' => false,
						'type' => SURVEY_OQ_INTERNAL,
						'result_note' => 'dupe'
					)), true, array('type', 'result_note'));
				}
				$id = $survey_visit['SurveyVisit']['id']; 
			}
		}
		$this->out('Completed');
	}
	
	public function set_margins() {

		$project_option = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.name' => 'fix.set_margins',
				'ProjectOption.project_id' => '0'
			)
		));
		if (!$project_option) {
		
			$this->loadModel('ProjectPayout');
			// first get the min/max ids of the polls
			$min = $this->ProjectPayout->find('first', array(
				'fields' => array('MIN(project_id) as project_id'),
				'recursive' => -1
			));
			
			$this->ProjectOption->getDatasource()->begin();
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'name' => 'fix.set_margins',
				'project_id' => '0',
				'value' => $min[0]['project_id']
			))); 
			$project_option_id = $this->ProjectOption->getInsertId();
			$this->ProjectOption->getDatasource()->commit();
			$min_project_id = $min[0]['project_id'];
		}
		else {
			$min_project_id = $project_option['ProjectOption']['value'];
			$project_option_id = $project_option['ProjectOption']['id']; 
		}
		
		$this->out('Starting from '.$min_project_id);
		while (true) {			
			$this->Project->unbindModel(array(
				'hasOne' => array('SurveyVisitCache'),
				'hasMany' => array(
					'SurveyPartner', 
					'ProjectOption',
					'ProjectAdmin'
				),
				'belongsTo' => array(
					'Client'
				)
			)); 
			$projects = $this->Project->find('all', array(
				'fields' => array('Group.calculate_margin', 'Project.id'),
				'conditions' => array(
					'Project.id >' => $min_project_id
				),
				'order' => 'Project.id ASC',
				'limit' => '10000'
			));
			if (!$projects) {
				break;
			}
			$this->out('Found '.count($projects).' projects starting from '.$min_project_id); 
			foreach ($projects as $project) {
				if (!$project['Group']['calculate_margin']) {
					$this->Project->create();
					$this->Project->save(array('Project' => array(
						'id' => $project['Project']['id'],
						'margin_cents' => null,
						'margin_pct' => null,
						'modified' => false
					)), true, array('margin_cents', 'margin_pct')); 
					$this->out('Set '.$project['Project']['id'].' to null'); 
					continue;
				}
				$margins = Utils::save_margin($project['Project']['id']);
				if (!$margins) {
					continue;
				}
				$this->out('Set '.$project['Project']['id'].' to '.$margins['margin_cents'].' cents and '.$margins['margin_pct'].'%'); 
				$min_project_id = $project['Project']['id'];	
			}
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'id' => $project_option_id,
				'value' => $min_project_id
			)), true, array('value'));
		}
		$this->out('Completed');
	}
	
	public function socialglimpz_start_times() {
		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'socialglimpz'
			)
		));
		$this->Project->unbindModel(array('hasMany' => array('SurveyPartner', 'ProjectOption', 'ProjectAdmin')));
		$projects = $this->Project->find('all', array(
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'Project.started' => null
			)
		)); 
		if ($projects) {
			foreach ($projects as $project) {
				if ($project['SurveyVisitCache']['click'] > 0) {
					$survey_visit = $this->SurveyVisit->find('first', array(
						'fields' => array('SurveyVisit.created'),
						'conditions' => array(
							'SurveyVisit.survey_id' => $project['Project']['id']
						),
						'order' => 'SurveyVisit.id ASC'
					)); 
					if (!$survey_visit) {
						$start = $project['Project']['date_created']; 
					}
					else {
						$start = $survey_visit['SurveyVisit']['created']; 
					}
					$this->out($project['Project']['id'].': '.$start); 
					$this->Project->create();
					$this->Project->save(array('Project' => array(
						'id' => $project['Project']['id'],
						'modified' => false,
						'started' => $start
					)), true, array('started')); 
				}
			}
		}
	}
	
	public function typo_project_logs() {
		$project_logs = $this->ProjectLog->find('list', array(
			'fields' => array('ProjectLog.id', 'ProjectLog.description'),
			'conditions' => array(
				'ProjectLog.type' => array('qualification.created', 'qualification.open', 'qualification.closed')
			)
		)); 
		$this->out('Found '.count($project_logs).' records'); 
		foreach ($project_logs as $id => $description) {
			$saved_description = str_replace('Qualifiation', 'Qualification', $description); 
			if ($saved_description == $description) {
				continue;
			}
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'id' => $id,
				'description' => $saved_description,
				'modified' => false
			)), true, array('description')); 
			$this->out($id); 
		}
	}
	
	public function reset_invite_email_counts() {
		$this->Project->unbindModel(array('hasMany' => array('SurveyPartner', 'ProjectOption', 'ProjectAdmin')));
		$this->Project->unbindModel(array('belongsTo' => array('Client'))); 
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'SurveyVisitCache.id', 'SurveyVisitCache.invited', 'SurveyVisitCache.emailed'),
			'conditions' => array(
				'Project.temp_qualifications' => true
			),
			'limit' => 5000,
			'order' => 'Project.id DESC'
		)); 
		
		foreach ($projects as $project) {
			$count = $this->SurveyUser->find('count', array(
				'conditions' => array(
					'SurveyUser.survey_id' => $project['Project']['id']
				)
			)); 
			$email_count = $this->SurveyUser->find('count', array(
				'conditions' => array(
					'SurveyUser.survey_id' => $project['Project']['id'],
					'SurveyUser.notification' => '1'
				)
			)); 
			if ($count != $project['SurveyVisitCache']['invited']) {
				$this->SurveyVisitCache->create();
				$this->SurveyVisitCache->save(array('SurveyVisitCache' => array(
					'id' => $project['SurveyVisitCache']['id'],
					'invited' => $count,
					'modified' => false
				)), true, array('invited')); 
				$this->out('Invite: '.$project['Project']['id'].' '.$count.' vs '.$project['SurveyVisitCache']['invited']);
			}
			if ($email_count != $project['SurveyVisitCache']['emailed']) {
				$this->SurveyVisitCache->create();
				$this->SurveyVisitCache->save(array('SurveyVisitCache' => array(
					'id' => $project['SurveyVisitCache']['id'],
					'emailed' => $email_count,
					'modified' => false
				)), true, array('emailed')); 
				$this->out('Emailed: '.$project['Project']['id'].' '.$email_count.' vs '.$project['SurveyVisitCache']['emailed']);
			}
		}
	}

	public function p2s_links() {
		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'points2shop'
			)
		)); 
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Project.client_survey_link'),
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
			),
			'recursive' => -1
		)); 
		foreach ($projects as $project) {
			$client_link = $project['Project']['client_survey_link']; 
			$client_link = str_replace('SUBID{{ID}}', '{{USER}}', $client_link); 
			$client_link .= '&ssi2={{ID}}';
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'client_survey_link' => $client_link,
				'modified' => false
			)), true, array('client_survey_link')); 
			$this->out('Fixed '.$project['Project']['id']); 
		}
		$this->out('Completed'); 
	}
	
	public function find_p2s_for_roy() {
		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'points2shop'
			)
		)); 
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id'),
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'Project.active' => true,
				'Project.status' => PROJECT_STATUS_OPEN
			),
			'recursive' => -1
		)); 
		foreach ($projects as $project) {
			$count = $this->SurveyUser->find('count', array(
				'conditions' => array(
					'SurveyUser.user_id' => 128,
					'SurveyUser.survey_id' => $project['Project']['id']
				)
			));
			if ($count > 0) {
				$this->out($project['Project']['id']); 
			}
		}
		$this->out('Completed'); 
	}
	
	public function populate_withdrawals() {
		$project_option = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.name' => 'fix.populate_withdrawals',
				'ProjectOption.project_id' => '0'
			)
		));
		if (!$project_option) {
			$this->loadModel('ProjectPayout');

			$this->ProjectOption->getDatasource()->begin();
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'name' => 'fix.populate_withdrawals',
				'project_id' => '0',
				'value' => '0'
			))); 
			$project_option_id = $this->ProjectOption->getInsertId();
			$this->ProjectOption->getDatasource()->commit();
			$min_transaction_id = 0;
		}
		else {
			$min_transaction_id = $project_option['ProjectOption']['value'];
			$project_option_id = $project_option['ProjectOption']['id']; 
		}
		
		$this->out('Starting from '.$min_transaction_id);
		$this->Transaction->bindItems(false);
		$this->Transaction->bindModel(array(
			'belongsTo' => array(
				'PaymentMethod' => array(
					'foreignKey' => 'linked_to_id'
				)
			)
		));

		while (true) {
			$transactions = $this->Transaction->find('all', array(
				'conditions' => array(
					'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
					'Transaction.id >' => $min_transaction_id
				),
				'contain' => array('PaymentMethod'),
				'order' => 'Transaction.id ASC',
				'limit' => '10000'
			));
			if (!$transactions) {
				break;
			}
			$this->out('Found '.count($transactions).' transactions starting from '.$min_transaction_id);

			foreach ($transactions as $transaction) {
				$min_transaction_id = $transaction['Transaction']['id'];

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
					'processed' => $processed,
					'created' => $transaction['Transaction']['created'],
					'updated' => $transaction['Transaction']['updated']
				)));

				$this->out('Transferred [transaction_id : ' . $transaction['Transaction']['id'] . ']');
			}

			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'id' => $project_option_id,
				'value' => $min_transaction_id
			)), true, array('value'));
		}

		$this->out('Complete!');
	}

	public function enable_p2s_qualifications() {
		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'points2shop',
			),
			'recursive' => -1,
		));
		$projects = $this->Project->find('all', array(
			'fields' => array(
				'Project.id'
			),
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'Project.status' => PROJECT_STATUS_OPEN
			),
			'recursive' => -1
		));
		if (!$projects) {
			$this->out('There are no points2shop projects to process');
			return;
		}

		foreach ($projects as $project) {
			$qualifications = $this->Qualification->find('all', array(
				'fields' => array('Qualification.id'),
				'conditions' => array(
					'Qualification.project_id' => $project['Project']['id'],
					'Qualification.deleted' => null,
				),
			));
			if (!$qualifications) {
				$this->out('Skipping because of no qualifications'); 
				continue;
			}
			foreach ($qualifications as $qualification) {
				$this->Qualification->create();
				$this->Qualification->save(array('Qualification' => array(
					'id' => $qualification['Qualification']['id'],
					'active' => true,
				)), true, array('active'));

				$this->out('QualificationID: ' . $qualification['Qualification']['id'] . ' activated for ProjectID: ' . $project['Project']['id']);
			}
		}
	}
	

	public function disable_p2s_qualifications() {
		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'points2shop',
			),
			'recursive' => -1,
		));
		$projects = $this->Project->find('all', array(
			'fields' => array(
				'Project.id'
			),
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'Project.status' => PROJECT_STATUS_OPEN
			),
			'recursive' => -1
		));
		if (!$projects) {
			$this->out('There are no points2shop projects to process');
			return;
		}

		foreach ($projects as $project) {
			$qualifications = $this->Qualification->find('all', array(
				'fields' => array('Qualification.id'),
				'conditions' => array(
					'Qualification.project_id' => $project['Project']['id'],
					'Qualification.deleted' => null,
				),
			));
			if (!$qualifications) {
				$this->out('Skipping because of no qualifications'); 
				continue;
			}
			foreach ($qualifications as $qualification) {
				$this->Qualification->create();
				$this->Qualification->save(array('Qualification' => array(
					'id' => $qualification['Qualification']['id'],
					'active' => false,
				)), true, array('active'));

				$this->out('QualificationID: ' . $qualification['Qualification']['id'] . ' deactivated for ProjectID: ' . $project['Project']['id']);
			}
		}
	}

	public function set_p2s_default_ir_loi() {
		$required_settings = array(
			'points2shop.default.loi',
			'points2shop.default.ir',
		);
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => $required_settings,
				'Setting.deleted' => false
			),
			'recursive' => -1
		));

		if (count($settings) != count($required_settings)) {
			$this->out('Missing required settings');
			return false;
		}

		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'points2shop',
			),
			'recursive' => -1,
		));
		$projects = $this->Project->find('all', array(
			'fields' => array(
				'Project.id', 'Project.est_length', 'Project.bid_ir'
			),
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'Project.status' => array(PROJECT_STATUS_OPEN, PROJECT_STATUS_STAGING, PROJECT_STATUS_SAMPLING)
			),
			'recursive' => -1
		));
		if (!$projects) {
			$this->out('There are no points2shop projects to process');
			return;
		}

		foreach ($projects as $project) {
			$project_data = $fields = array();
			if (empty($project['Project']['est_length'])) {
				$project_data['est_length'] = $settings['points2shop.default.loi'];
				$fields[] = 'est_length';
			}

			if (empty($project['Project']['bid_ir'])) {
				$project_data['bid_ir'] = $settings['points2shop.default.ir'];
				$fields[] = 'bid_ir';
			}

			if ($project_data) {
				$project_data['id'] = $project['Project']['id'];
				$this->Project->create();
				$this->Project->save(array('Project' => $project_data), true, $fields);

				$this->out('Updated fields (' . implode(',', $fields) . ') for ProjectID: ' . $project['Project']['id']);
			}
			else {
				$this->out('ProjectID: ' . $project['Project']['id'] . ' skipped');
			}			
		}
		$this->out('Finished.');
	}
	
	public function backfill_users_history_request_report() {
		$last_history_request = $this->HistoryRequest->find('first', array(
			'fields' => array('HistoryRequest.created'),
			'order' => 'HistoryRequest.created desc'
		));
		$first_history_request = $this->HistoryRequest->find('first', array(
			'fields' => array('HistoryRequest.created'),
			'order' => 'HistoryRequest.created asc'
		));
		$fill_start_date = strtotime(date(DB_DATE, strtotime($first_history_request['HistoryRequest']['created'])));
		$fill_end_date = strtotime(date(DB_DATE, strtotime($last_history_request['HistoryRequest']['created'])));
		$diff = ($fill_end_date - $fill_start_date) / 86400; 

		for ($i = 0; $i <= $diff; $i++) {
			$date = $fill_start_date + ($i * 86400); 

			$start_date = date(DB_DATE, $date) . ' 00:00:00';
			$end_date = date(DB_DATE, $date) . ' 23:59:59';
			$save['date'] = date(DB_DATE, $date);
			
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
					'HistoryRequestReport.date' => date(DB_DATE, $date)
				)
			));
			
			if ($history_request_report) {
				$save['id'] = $history_request_report['HistoryRequestReport']['id']; 
			}

			$this->HistoryRequestReport->create();
			$this->HistoryRequestReport->save($save);
			$this->out('History Request Report added for '.date(DB_DATE, $date));
		}	
		$this->out('Process completed successfully.');
	}

	public function rewrite_p2s_si() {
		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'points2shop'
			)
		)); 
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Project.client_survey_link'),
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
			),
			'recursive' => -1
		)); 
		foreach ($projects as $project) {
			$client_link = $project['Project']['client_survey_link']; 
			$client_link = str_replace('si=25', 'si=80', $client_link); 
			$client_link .= '&ssi2={{ID}}';
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'client_survey_link' => $client_link,
				'modified' => false
			)), true, array('client_survey_link')); 
			$this->out('Fixed '.$project['Project']['id']); 
		}
		$this->out('Completed'); 
	}
	
	public function toluna_logs_country() {
		$this->loadModel('TolunaLog');
		$toluna_logs = $this->TolunaLog->find('list', array(
			'fields' => array('TolunaLog.id', 'TolunaLog.user_id'),
			'conditions' => array(
				'TolunaLog.country is null'
			)
		));
		
		if ($toluna_logs) {
			foreach ($toluna_logs as $id => $user_id) {
				$country = $this->QueryProfile->find('first', array(
					'fields' => array('QueryProfile.country'),
					'conditions' => array(
						'QueryProfile.user_id' => $user_id
					),
					'recursive' => -1
				));
				
				$this->TolunaLog->create();
				$this->TolunaLog->save(array('TolunaLog' => array(
					'id' => $id,
					'country' => $country['QueryProfile']['country']
				)), true, array('country')); 
			}
			
			$this->out('Country updated for '.count($toluna_logs).' logs...' );
		}
		$this->out('Process completed successfully.');
	}

	public function project_log_description () {
		$project_logs = $this->ProjectLog->find('list', array(
			'fields' => array('ProjectLog.id', 'ProjectLog.description'),
			'conditions' => array(
				'ProjectLog.type' => array('status.closed.auto', 'status.opened.reopen'),
			)
		));
		if (!$project_logs) {
			$this->out('There are no project logs to process');
			return;
		}
		
		$count = 0;
		$this->out('Processing ' . count($project_logs) . ' logs.');
		foreach ($project_logs as $id => $description) {
			if (preg_match_all('!\$+\d+\.*\d*!', $description, $match) < 1) {
				continue;
			}
			
			$epc = number_format(str_replace('$', '', $match[0][0]), 2);
			$cut_off = isset($match[0][1]) ? number_format(str_replace('$', '', $match[0][1]), 2) : false;
			$fixed = false;
			if (strlen($match[0][0]) - strrpos($match[0][0], '.') == 2) {
				$description = str_replace($match[0][0], '$' . $epc, $description);
				$fixed = true;
			}

			if ($cut_off && strlen($match[0][1]) - strrpos($match[0][1], '.') == 2 && $match[0][0] != $match[0][1]) {
				$description = str_replace($match[0][1], '$'.$cut_off, $description);
				$fixed = true;
			}
			
			if ($fixed) {
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'id' => $id,
					'description' => $description
				)), true, array('description'));
				$count++;
			}
		}
		
		$this->out('Fixed ' . $count . ' logs.');
	}
	
	// argument 1 start date in yyyy-mm-dd format
	// argument 2 end date in yyyy-mm-dd format
	function confirm_survey_visit_user_ids() {
		if (!empty($this->args[0]) && !empty($this->args[1])) {
			$start_date = date($this->args[0].' 00:00:00');
			$end_date = date($this->args[1].' 23:59:59');
		}
		else {
			$start_date = date(DB_DATE, strtotime('yesterday')) . ' 00:00:00';
			$end_date = date(DB_DATE, strtotime('yesterday')) . ' 23:59:59';
		}
		
		$mintvine_partner = $this->Partner->find('first', array(
			'fields' => array('Partner.id'),
			'conditions' => array(
				'Partner.key' => 'mintvine',
			)
		));
		
		$this->out("Processing data between ".$start_date. " to ".$end_date);
		$this->out();
		
		$fill_start_date = strtotime(date(DB_DATE, strtotime($start_date)));
		$fill_end_date = strtotime(date(DB_DATE, strtotime($end_date)));
		$diff = ($fill_end_date - $fill_start_date) / 86400; 
		
		$total_records = $total_matched_ids = $total_unmatched_ids = 0; 
		for ($i = 0; $i <= $diff; $i++) {
			$date = $fill_start_date + ($i * 86400); 

			$start_date = date(DB_DATE, $date) . ' 00:00:00';
			$end_date = date(DB_DATE, $date) . ' 23:59:59';
			
			$this->out('Collecting data for '.date(DB_DATE, $date));
			$survey_visits = $this->SurveyVisit->find('all', array(
				'fields' => array('SurveyVisit.user_id', 'SurveyVisit.partner_user_id'),
				'conditions' => array(
					'SurveyVisit.partner_id' => $mintvine_partner['Partner']['id'],
					'SurveyVisit.created >=' => $start_date,
					'SurveyVisit.created <=' => $end_date,
				)
			));
			
			$matched_ids = $unmatched_ids = 0;
			if ($survey_visits) {
				foreach ($survey_visits as $survey_visit) {
					list($survey_id, $user_id, $nada1, $nada2) = explode('-', $survey_visit['SurveyVisit']['partner_user_id']);	
					if ($user_id == $survey_visit['SurveyVisit']['user_id']) {
						$matched_ids++;
					}
					else {
						$unmatched_ids++;
					}
				}
			}
			$total_records = $total_records + count($survey_visits);
			$total_matched_ids = $total_matched_ids + $matched_ids;
			$total_unmatched_ids = $total_unmatched_ids + $unmatched_ids;
			
			$this->out('Total records: '.count($survey_visits). ', Matched User IDs: '. $matched_ids. ', Un-Matched User IDs: '.$unmatched_ids);
			$this->out('--------------------------------------------------------');
		}
		$pct = number_format(round(($total_matched_ids / $total_records) * 100, 8), 2);
		$this->out('overall accuracy is: '.$pct. '%');
		$this->out("Process Completed.");
	}	

	public function delete_dup_precision_survey_links() {
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'precision'
			)
		));

		$project_id = 0;
		while (true) {
			$projects = $this->Project->find('all', array(
				'conditions' => array(
					'Project.group_id' => $group['Group']['id'],
					'Project.id >' => $project_id,
				),
				'recursive' => -1,
				'limit' => 1000,
			));

			if (!$projects) {
				$this->out('There are no more projects to process');
				break;
			}

			foreach ($projects as $project) {
				$project_id = $project['Project']['id'];
				$this->out('Processing #' . $project['Project']['id']);
				
				$user_survey_links = array();
				$survey_links = $this->SurveyLink->find('all', array(
					'fields' => array('SurveyLink.id', 'SurveyLink.survey_id', 'SurveyLink.user_id'),
					'conditions' => array(
						'SurveyLink.survey_id' => $project['Project']['id'],
					),
					'order' => 'SurveyLink.id DESC',
					'recursive' => -1,
				));
				if (!$survey_links) {
					$this->out('No survey links found for #' . $project['Project']['id']);
					continue;
				}

				foreach ($survey_links as $survey_link) {
					$key = $survey_link['SurveyLink']['user_id'] . '-' . $survey_link['SurveyLink']['survey_id'];
					if (isset($user_survey_links[$key])) {
						$this->SurveyLink->delete($survey_link['SurveyLink']['id']);
						$this->out('Duplicate link deleted, SurveyLink#' . $survey_link['SurveyLink']['id']);
					}
					else {
						$user_survey_links[$key] = $survey_link['SurveyLink']['id'];
					}
				}				
			}
		}
		$this->out('Finished.');
	}

	public function points2shop_qqq_flag() {
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'points2shop'
			)
		));
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id'),
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'Project.temp_qualifications' => true
			),
			'recursive' => -1
		)); 
		foreach ($projects as $project) {
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'temp_qualifications' => false,
				'modified' => false
			)), true, array('temp_qualifications')); 
			$this->out($project['Project']['id']);
		}
	}
	
	public function precision_project_logs() {
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'precision'
			)
		));
		if (!$group) {
			return;
		}
		
		$projects = $this->Project->find('list', array(
			'fields' => array('Project.id'),
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
			),
			'recursive' => -1
		));
		foreach ($projects as $project_id) {
			$project_log_count = $this->ProjectLog->find('count', array(
				'conditions' => array(
					'ProjectLog.project_id' => $project_id,
					'ProjectLog.type' => 'updated'
				)
			));
			$project_rate_count = $this->ProjectRate->find('count', array(
				'conditions' => array(
					'ProjectRate.project_id' => $project_id,
				)
			));			
			if ($project_log_count < 100 && $project_rate_count < 100) {
				continue;
			}

			$this->out('Processing '. $project_log_count. ' ProjectLogs For Project ID: ' . $project_id);
			$last_log_id = $delete_count = 0;
			$log_description = '';
			while (true) {
				$project_logs = $this->ProjectLog->find('all', array(
					'fields' => array('ProjectLog.id', 'ProjectLog.description'),
					'conditions' => array(
						'ProjectLog.project_id' => $project_id,
						'ProjectLog.type' => 'updated',
						'ProjectLog.id >' => $last_log_id
					),
					'order' => 'ProjectLog.created ASC',
					'limit' => 10000,
					'recursive' => -1
				));
				if (!$project_logs) {
					break;
				}
				
				foreach ($project_logs as $project_log) {
					$last_log_id = $project_log['ProjectLog']['id'];
					if (empty($log_description) || $log_description != $project_log['ProjectLog']['description']) {
						$log_description = $project_log['ProjectLog']['description'];
					}
					else {
						$this->ProjectLog->delete($project_log['ProjectLog']['id']);
						$delete_count++;
						$this->out($delete_count . '/'. $project_log_count . ' deleted.');
					}
				}
			}
			
			$this->out('Processing '. $project_rate_count. ' ProjectRates For Project ID: ' . $project_id);
			$last_rate_id = $delete_count = 0;
			$client_rate = '';
			while (true) {
				$project_rates = $this->ProjectRate->find('all', array(
					'fields' => array('ProjectRate.id', 'ProjectRate.client_rate'),
					'conditions' => array(
						'ProjectRate.project_id' => $project_id,
						'ProjectRate.id >' => $last_rate_id,
					),
					'order' => 'ProjectRate.created ASC',
					'limit' => 10000,
					'recursive' => -1
				));
				if (!$project_rates) {
					break;
				}
				
				foreach ($project_rates as $project_rate) {
					$last_rate_id = $project_rate['ProjectRate']['id'];
					if (empty($client_rate) || $client_rate != $project_rate['ProjectRate']['client_rate']) {
						$client_rate = $project_rate['ProjectRate']['client_rate'];
					}
					else {
						$this->ProjectRate->delete($project_rate['ProjectRate']['id']);
						$delete_count++;
						$this->out($delete_count . '/'. $project_rate_count . ' deleted');
					}
				}
			}
		}
		
		$this->out('Finished.');
	}

	public function p2s_set_start_date() {
		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'points2shop',
			),
			'recursive' => -1,
		));

		$project_id = 0;
		$i = 0;
		while (true) {
			$projects = $this->Project->find('all', array(
				'conditions' => array(
					'Project.group_id' => $group['Group']['id'],
					'Project.id >' => $project_id,
				),
				'recursive' => -1,
				'limit' => 1000,
			));

			if (!$projects) {
				$this->out('There are no more projects to process');
				break;
			}

			foreach ($projects as $project) {
				$survey_visit = $this->SurveyVisit->find('first', array(
					'fields' => array('SurveyVisit.created'),
					'conditions' => array(
						'SurveyVisit.survey_id' => $project['Project']['id']
					),
					'order' => 'SurveyVisit.id ASC',
					'recursive' => -1,
				));
				if ($survey_visit) {
					$this->Project->create();
					$this->Project->save(array('Project' => array(
						'id' => $project['Project']['id'],
						'started' => $survey_visit['SurveyVisit']['created'],
						'modified' => false,
					)), true, array('started'));
					$this->out('Start value updated for #' . $project['Project']['id']);
				}
				else {
					$this->out('No SurveyVisist value found for #' . $project['Project']['id']);
				}

				$project_id = $project['Project']['id'];
			}
		}
		$this->out('Finished.');
	}
	
	public function move_prescreener_statistics_to_survey_visit_cache() {
		$prescreener_statistic_id = 0;
		while (true) {
			$prescreener_statistics = $this->PrescreenerStatistic->find('all', array(
				'conditions' => array(
					'PrescreenerStatistic.id >' => $prescreener_statistic_id,
				),
				'recursive' => -1,
				'limit' => 1000
			));

			if (!$prescreener_statistics) {
				$this->out('There are no more prescreener statistics to process');
				break;
			}

			foreach ($prescreener_statistics as $prescreener_statistic) {
				$survey_visit_cache = $this->SurveyVisitCache->find('first', array(
					'fields' => array('SurveyVisitCache.id'),
					'conditions' => array(
						'SurveyVisitCache.survey_id' => $prescreener_statistic['PrescreenerStatistic']['survey_id']
					),
					'order' => 'SurveyVisitCache.id ASC',
					'recursive' => -1,
				));
				if ($survey_visit_cache) {
					$this->SurveyVisitCache->create();
					$this->SurveyVisitCache->save(array('SurveyVisitCache' => array(
						'id' => $survey_visit_cache['SurveyVisitCache']['id'],
						'prescreen_clicks' => $prescreener_statistic['PrescreenerStatistic']['clicks'],
						'prescreen_completes' => $prescreener_statistic['PrescreenerStatistic']['completes'],
						'prescreen_nqs' => $prescreener_statistic['PrescreenerStatistic']['nqs'],
						'modified' => false,
					)), true, array('prescreen_clicks', 'prescreen_completes', 'prescreen_nqs'));
					$this->out('SurveyVisitCache value updated for #' . $prescreener_statistic['PrescreenerStatistic']['survey_id']);
				}
				else {
					$this->out('No SurveyVisitCache value found for #' . $prescreener_statistic['PrescreenerStatistic']['survey_id']);
				}

				$prescreener_statistic_id = $prescreener_statistic['PrescreenerStatistic']['id'];
			}
		}
		$this->out('Finished.');
	}
	
	public function user_score_with_duplicate_numbers() {
		$user_ids = $this->UserAnalysis->find('all', array(
			'fields' => array('DISTINCT(UserAnalysis.user_id)'),
			'conditions' => array(
				'UserAnalysis.duplicate_number is not null'
			)
		));
		if ($user_ids) {
			$this->out('Processing '. count($user_ids). ' users:');
			foreach ($user_ids as $user_id) {
				$this->out('Updating User Score for #'. $user_id['UserAnalysis']['user_id']);
				$user = $this->User->findById($user_id['UserAnalysis']['user_id']);
				$user_analysis = $this->UserAnalyzer->analyze($user);	
			}
		}
		$this->out('Process Completed.');
	}

	public function payout_pending_offers() {
		App::import('Model', 'OfferRedemption');
		$this->OfferRedemption = new OfferRedemption;
		
		$offer_redemptions = $this->OfferRedemption->find('all', array(
			'conditions' => array(
				'OfferRedemption.status' => OFFER_REDEMPTION_ACCEPTED,
				'OfferRedemption.created >=' => '2017-07-07 00:00:00',
				'OfferRedemption.created <=' => '2017-07-14 23:59:59',
			)
		));
		if (!$offer_redemptions) {
			$this->out('There are no offer redemptions to process');
			return;
		}
		foreach ($offer_redemptions as $offer_redemption) {
			$this->Transaction->bindModel(array(
				'belongsTo' => array(
					'Offer' => array(
						'foreignKey' => 'linked_to_id'
					)
				)
			));
			$transaction = $this->Transaction->find('first', array(
				'contain' => array(
					'Offer'
				),
				'conditions' => array(
					'Transaction.id' => $offer_redemption['OfferRedemption']['transaction_id'],
					'Transaction.type_id' => TRANSACTION_OFFER,
					'Transaction.status' => TRANSACTION_APPROVED,
					'Transaction.paid' => false,
					'Transaction.name like' => 'Points for completing offer%',
				),
				'recursive' => -1,
			));
			if ($transaction) {
				$this->Transaction->approve($transaction);
				$this->out('Transaction #' . $transaction['Transaction']['id'] . ' paid out: '.$transaction['Transaction']['amount']);
			}
			else {
#				$this->out('Transaction does not exist or paid, OfferRedemption#' . $offer_redemption['OfferRedemption']['transaction_id']);
			}
		}

		$this->out('Finished.');
	}

	public function find_unpaid_withdrawals_extended() {
		$withdrawals = $this->Withdrawal->find('all', array(
			'conditions' => array(
				'Withdrawal.transaction_id is not null',
				'Withdrawal.status' => WITHDRAWAL_PAYOUT_UNPROCESSED,
				'Withdrawal.deleted' => null
			)
		));
		$this->out('Found '.count($withdrawals).' withdrawals');
		
		foreach ($withdrawals as $withdrawal) {
			$this->CashNotification->getDatasource()->reconnect();
			$cash_notification = $this->CashNotification->find('first', array(
				'conditions' => array(
					'CashNotification.user_id' => $withdrawal['Withdrawal']['user_id'],
					'CashNotification.amount' => abs($withdrawal['Withdrawal']['amount_cents']),
					'CashNotification.created LIKE' => date(DB_DATE).'%'
				)
			));
			if (!$cash_notification) {
				$this->out($withdrawal['Withdrawal']['id']);
				$query = ROOT.'/app/Console/cake withdrawals payouts '.$withdrawal['Withdrawal']['id'];
				passthru($query); 
			}
		}
		$this->out('Completed');
	}
	
	public function unset_bad_postal_codes() {
		ini_set('memory_limit', '1024M');		
		$user_id = 0;
		$i = 0;
		while (true) {
			$users = $this->User->find('all', array(
				'conditions' => array(
					'User.id >' => $user_id,
					'User.extended_registration' => true,
					'QueryProfile.postal_code IS NOT NULL'
				),
				'contain' => array('QueryProfile'),
				'order' => 'User.id ASC',
				'limit' => 1000
			));

			if (!$users) {
				$this->out('There are no more users to process');
				break;
			}

			foreach ($users as $user) {
				$this->QueryProfile->set($user);
				if (!$this->QueryProfile->validateZip()) {
					// unset invalid postal_code
					$this->QueryProfile->create();
					$this->QueryProfile->save(array('QueryProfile' => array(
						'id' => $user['QueryProfile']['id'],
						'postal_code' => null,
						'modified' => false
					)), array(
						'fieldList' => array('postal_code'),
						'callbacks' => false,
						'validate' => false
					));

					// force user to insert new postal code on next login
					$this->User->create();
					$this->User->save(array('User' => array(
						'id' => $user['User']['id'],
						'extended_registration' => false,
						'modified' => false
					)), true, array('extended_registration'));
					
					$this->out('User# '.$user['User']['id'].': Invalid postal code '.$user['QueryProfile']['postal_code'].' for country '.$user['QueryProfile']['country']);
					$i++;
				}

				$user_id = $user['User']['id'];
			}
		}
		
		$this->out('Total invlid postal codes unset : ' . $i);
		$this->out('Finished.');
	}
}
