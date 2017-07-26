<?php
App::uses('Shell', 'Console');
App::import('Lib', 'Utilities');

class MigrateShell extends Shell {
	var $uses = array('User', 'HellbanLog', 'Project', 'UserAnalysis', 'SurveyUserVisit', 'SurveyVisit', 'User', 'UserChecklist', 'Transaction', 'PaypalAccount', 'PaymentMethod');
	
	function main() {
	}
	
	// calculate LOI for active surveys
	function loi() {
		App::import('Model', 'Loi');
		$this->Loi = new Loi;
		
		$surveys = $this->Project->find('all', array(
			'conditions' => array(
				array(
					'Project.status' => 'Open'
				)
			),
			'order' => 'Project.id asc'
		));
		
		foreach ($surveys as $survey) {
			echo 'starting '.$survey['Project']['id'];
			$survey_visits = $this->SurveyVisit->find('all', array(
				'fields' => array('survey_id', 'id', 'created'),
				'recursive' => -1,
				'conditions' => array(
					'SurveyVisit.survey_id' => $survey['Project']['id'],
					'SurveyVisit.type' => SURVEY_COMPLETED
				)
			));
			echo '.'; 
			foreach ($survey_visits as $survey_visit) {
				$entry = $this->SurveyVisit->find('first', array(
					'fields' => array('id', 'created'),
					'conditions' => array(
						'SurveyVisit.survey_id' => $survey_visit['SurveyVisit']['survey_id'],
						'SurveyVisit.result_id' => $survey_visit['SurveyVisit']['id'],
						'SurveyVisit.type' => SURVEY_CLICK
					),
					'order' => 'SurveyVisit.id DESC'
				));
				if ($entry && $survey_visit) {
					$diff = strtotime($survey_visit['SurveyVisit']['created']) - strtotime($entry['SurveyVisit']['created']); 
					if ($diff > 0 && $diff < 1801) {
						$this->Loi->create();
						$this->Loi->save(array('Loi' => array(
							'survey_id' => $survey_visit['SurveyVisit']['survey_id'],
							'loi' => $diff
						))); 
						echo '.';
					}
				}
			}
			echo "\n";
		}
	}
	

	function survey_partners() {
		/* 
		ALTER TABLE `projects`
		  DROP `partner_ids`,
		  DROP `partner_success_pages`,
		  DROP `partner_dq_pages`,
		  DROP `partner_quota_pages`,
		  DROP `partner_paused_pages`,
		  DROP `partner_prj_contacts`,
		  DROP `partner_bill_contacts`,
		  DROP `partner_rates_arr`,
		  DROP `partner_clicks`,
		  DROP `partner_completes`,
		  DROP `partner_nqs`,
		  DROP `partners_paused`,
		  DROP `security_paused`;
		*/
		$projects = $this->Project->find('all', array(
			'fields' => array(
				'id',
				'partner_ids', 
				'partner_rate',
				'partner_success_pages', 
				'partner_dq_pages',
				'partner_quota_pages',
				'partner_paused_pages',
				'partner_rates_arr',
				'partner_clicks', 
				'partner_completes',
				'partner_nqs',
				'partners_paused',
				'security_paused'
			)
		));
		foreach ($projects as $project) {
			$partner_ids = explode(',', $project['Project']['partner_ids']);
			$partners_paused = explode(',', $project['Project']['partners_paused']);
			$security_paused = explode(',', $project['Project']['security_paused']);
			$partner_success_pages = json_decode($project['Project']['partner_success_pages'], true);
			$partner_dq_pages = json_decode($project['Project']['partner_dq_pages'], true);
			$partner_quota_pages = json_decode($project['Project']['partner_quota_pages'], true);
			$partner_paused_pages = json_decode($project['Project']['partner_paused_pages'], true);
			$partner_rates_arr = json_decode($project['Project']['partner_rates_arr'], true);
			$partner_clicks = json_decode($project['Project']['partner_clicks'], true);
			$partner_completes = json_decode($project['Project']['partner_completes'], true);
			$partner_nqs = json_decode($project['Project']['partner_nqs'], true);
			if (empty($partner_ids)) {
				continue;
			}
			foreach ($partner_ids as $partner_id) {
				if (empty($partner_id)) {
					continue;
				}
				$save = array('SurveyPartner' => array(
					'survey_id' => $project['Project']['id'],
					'partner_id' => $partner_id,
					'rate' => isset($partner_rates_arr[$partner_id]) ? $partner_rates_arr[$partner_id]: $project['Project']['partner_rate'],
					'paused' => in_array($partner_id, $partners_paused) ? '1': '0',
					'security' => in_array($partner_id, $security_paused) ? '0': '1',
					'clicks' => isset($partner_clicks[$partner_id]) ? $partner_clicks[$partner_id]: '0',
					'nqs' => isset($partner_nqs[$partner_id]) ? $partner_nqs[$partner_id]: '0',
					'completes' => isset($partner_completes[$partner_id]) ? $partner_completes[$partner_id]: '0',
					'complete_url' => isset($partner_success_pages[$partner_id]) ? $partner_success_pages[$partner_id]: '',
					'nq_url' => isset($partner_dq_pages[$partner_id]) ? $partner_dq_pages[$partner_id]: '',
					'oq_url' => isset($partner_quota_pages[$partner_id]) ? $partner_quota_pages[$partner_id]: ''
				));
				$survey_partner = $this->Project->SurveyPartner->find('first', array(
					'conditions' => array(
						'SurveyPartner.survey_id' => $project['Project']['id'],
						'SurveyPartner.partner_id' => $partner_id
					)
				));
				if ($survey_partner) {
					$save['SurveyPartner']['id'] = $survey_partner['SurveyPartner']['id'];
				}
				$this->Project->SurveyPartner->create();
				$this->Project->SurveyPartner->save($save);
			}
		}
	}
	
	// move all old profile transactions into the 'other' bucket
	function old_user_profile_transactions() {
		ini_set('memory_limit', '1024M');
		set_time_limit(12000);
		
		$transactions = $this->Transaction->find('all', array(
			'fields' => array('id', 'name', 'linked_to_id', 'type_id'),
			'recursive' => -1,
			'conditions' => array(
				'Transaction.type_id' => TRANSACTION_PROFILE,
				'Transaction.linked_to_id >' => '0',
				'Transaction.deleted' => null,
			)
		));
		foreach ($transactions as $transaction) {
			$this->Transaction->create();
			$this->Transaction->save(array('Transaction' => array(
				'id' => $transaction['Transaction']['id'],
				'name' => 'Profile: '.$transaction['Transaction']['name'],
				'type_id' => TRANSACTION_OTHER,
				'linked_to_id' => '0'
			)), array(
				'validate' => false,
				'callbacks' => false,
				'fieldList' => array('type_id', 'linked_to_id', 'name')
			));
			echo "."; flush();
		}
	}
			
	function checklists() {
		ini_set('memory_limit', '1024M');
		set_time_limit(12000);
		$users = $this->User->find('all', array(
			'fields' => array(
				'id'
			),
		));
		foreach ($users as $user) {
			
			// have not referred
			$count = $this->User->find('count', array(
				'recursive' => -1,
				'conditions' => array(
					'User.referred_by' => $user['User']['id']
				)
			));
			if ($count == 0) {
				$this->UserChecklist->create();
				$this->UserChecklist->save(array(
					'user_id' => $user['User']['id'],
					'name' => 'share'
				));
			}
			
			$count = $this->Transaction->find('count', array(
				'recursive' => -1,
				'conditions' => array(
					'type_id' => TRANSACTION_PROFILE,
					'linked_to_id' => '1', // bonus profile
					'user_id' => $user['User']['id'],
					'amount' => 50,
					'deleted' => null,
				)
			));
			if ($count == 0) {
				$this->UserChecklist->create();
				$this->UserChecklist->save(array(
					'user_id' => $user['User']['id'],
					'name' => 'profile'
				));
			}
		}
	}
	
	function analyses() {
		$hellbans = $this->HellbanLog->find('all', array(
			'conditions' => array(
				'HellbanLog.analysis_id' => null,
				'HellbanLog.type' => 'hellban'
			)
		));
		if ($hellbans) {
			foreach ($hellbans as $hellban) {
				$analysis = $this->UserAnalysis->find('first', array(
					'conditions' => array(
						'UserAnalysis.user_id' => $hellban['HellbanLog']['user_id'],
					)
				));
				if ($analysis) {
					$this->HellbanLog->create();
					$this->HellbanLog->save(array('HellbanLog' => array(
						'id' => $hellban['HellbanLog']['id'],
						'analysis_id' => $analysis['UserAnalysis']['id'],
						'score' => $analysis['UserAnalysis']['score']
					)), true, array('analysis_id', 'score'));
				}
			}
		}
	}
	
	function cleanup() {
		$logs = $this->HellbanLog->find('all');
		$last_value = $last_user_id = $last = null;
		foreach ($logs as $log) {
			if ($last_value == $log['HellbanLog']['type'] && $last_user_id == $log['HellbanLog']['user_id']) {
				$this->HellbanLog->create();
				$this->HellbanLog->delete($log['HellbanLog']['id']);
			}
			$last_value = $log['HellbanLog']['type'];
			$last_user_id = $log['HellbanLog']['user_id'];
			$last = $log;
		}
	}
	
	function hellbanned() {
		// find hellbanned users and generate logs
		// find un-hellbanned users and generate logs
		$users = $this->User->find('all', array(
			'conditions' => array(
				'User.hellbanned' => true
			)
		));
		if ($users) {
			foreach ($users as $user) {
				$count = $this->HellbanLog->find('count', array(
					'conditions' => array(
						'HellbanLog.user_id' => $user['User']['id'],
						'HellbanLog.type' => 'hellban'
					)
				));
				if ($count == 0) {
					$analysis = $this->UserAnalysis->find('first', array(
						'recursive' => -1,
						'conditions' => array(
							'UserAnalysis.user_id' => $user['User']['id']
						)
					));
					$this->HellbanLog->create();
					$this->HellbanLog->save(array('HellbanLog' => array(
						'user_id' => $user['User']['id'],
						'created' => $user['User']['hellbanned_on'],
						'type' => 'hellban',
						'automated' => true,
						'reason' => $user['User']['hellban_reason'],
						'score' => $analysis ? $analysis['UserAnalysis']['score']: null,
						'analysis_id' => $analysis ? $analysis['UserAnalysis']['id']: null,
					)));
				}
			}
		}
		
		$users = $this->User->find('all', array(
			'conditions' => array(
				'User.hellbanned' => false,
				'User.checked' => true
			)
		));
		if ($users) {
			foreach ($users as $user) {		
				$count = $this->HellbanLog->find('count', array(
					'conditions' => array(
						'HellbanLog.user_id' => $user['User']['id'],
						'HellbanLog.type' => 'unhellban'
					)
				));
				if ($count == 0) {		
					$this->HellbanLog->create();
					$this->HellbanLog->save(array('HellbanLog' => array(
						'user_id' => $user['User']['id'],
						'type' => 'unhellban',
						'automated' => false
					)));
				}
			}
		}
	}
	
	function paypal_data() {
		$paypal_data = $this->PaypalAccount->find('all');

		if ($paypal_data) {
			foreach ($paypal_data as $data) {
				$payment_method = array();
				$payment_method['PaymentMethod']['payment_method'] = 'paypal';
				$payment_method['PaymentMethod']['user_id'] = $data['PaypalAccount']['user_id'];
				$payment_method['PaymentMethod']['value'] = $data['PaypalAccount']['paypal_email'];
				$payment_method['PaymentMethod']['status'] = ($data['PaypalAccount']['status']) ? DB_ACTIVE : DB_DEACTIVE;

				$this->PaymentMethod->create();
				if ($this->PaymentMethod->save($payment_method)) {
					echo "Paypal id " . $data['PaypalAccount']['paypal_email'] . " moved to table payment_methods\n";
				}
				else {
					echo "Paypal id " . $data['PaypalAccount']['paypal_email'] . " failed to move\n";
				}
			}
		}
	}

}
