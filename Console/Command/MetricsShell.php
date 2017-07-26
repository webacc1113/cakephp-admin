<?php
App::import('Lib', 'MintVineUser');

class MetricsShell extends AppShell {
	public $uses = array('Project', 'Survey', 'SurveyVisit', 'SurveyVisitCache', 'Group', 'SurveyUser', 'Transaction');
	
	public function completes_over_epc() {
		if (!empty($this->args[0])) {
			$start_date = date($this->args[0].'-1');
			$end_date = date($this->args[0].'-31');
		}

		if (!empty($this->args[1])) {
			$partner = $this->args[1];
		}
		else {
			$partner = 'cint';
		}

		$partner_epc = array();
		$our_epc = array();

		$group = $this->Group->find('first', array(
			'fields' => array('id'),
			'conditions' => array(
				'Group.key' => $partner
			)
		));

		if (!$group) {
			return false;
		}
		
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
		));
			
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Project.client_rate', 'Project.award', 'Project.started', 'Project.ended', 'Project.mask', 'Project.epc', 'SurveyVisitCache.*'),
			'conditions' => $conditions
		));

		if ($projects) {
			foreach ($projects as $project) {
				$survey_visits = $this->SurveyVisit->find('all', array(
					'recursive' => -1,
					'conditions' => array(
						'SurveyVisit.survey_id' => $project['Project']['id'],
						'SurveyVisit.type' => SURVEY_CLICK,
						'SurveyVisit.created >=' => $start_date.' 00:00:00',
						'SurveyVisit.created <=' => $end_date.' 23:59:59'
					)
				));
					
				foreach ($survey_visits as $visit) {
					if ($visit['SurveyVisit']['result'] == SURVEY_COMPLETED) {
						if (empty($partner_epc[$project['Project']['epc']])) {
							$partner_epc[$project['Project']['epc']] = 1;
						} else {
							$partner_epc[$project['Project']['epc']] = $partner_epc[$project['Project']['epc']] + 1;
						}

						if (empty($our_epc[$project['SurveyVisitCache']['epc']])) {
							$our_epc[$project['SurveyVisitCache']['epc']] = 1;
						} else {
							$our_epc[$project['SurveyVisitCache']['epc']] = $our_epc[$project['SurveyVisitCache']['epc']] + 1;
						}
					}
				}
			}
		}
		
		ksort($partner_epc);
		$this->out('Partner EPC by Complete distribution');
		foreach ($partner_epc as $epc=>$value) {
			$this->out($epc.','.$value);
		}

		ksort($our_epc);
		$this->out('Our EPC by Complete distribution');
		foreach ($our_epc as $epc=>$value) {
			$this->out($epc.','.$value);
		}
	}

	public function completes_over_ir() {
		if (!empty($this->args[0])) {
			$start_date = date($this->args[0].'-1');
			$end_date = date($this->args[0].'-31');
		}
		
		if (empty($this->args[1])) {
			$this->out('Please input a group key as your second argument.');
			return false; 
		}
		else {
			$partner = $this->args[1];
		}
		
		$partner_ir = array();
		$our_ir = array();
		$project_ids = array();

		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => $partner
			)
		));

		if (!$group) {
			return false;
		}
		
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
		));
			
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Project.client_rate', 'Project.award', 'Project.started', 'Project.bid_ir', 'Project.ended', 'Project.mask', 'Project.epc', 'SurveyVisitCache.*'),
			'conditions' => $conditions
		));
		
		$this->out('Processing '.count($projects).' projects'); 

		if ($projects) {
			foreach ($projects as $project) {
				$survey_visits = $this->SurveyVisit->find('all', array(
					'fields' => array('SurveyVisit.result'),
					'conditions' => array(
						'SurveyVisit.survey_id' => $project['Project']['id'],
						'SurveyVisit.type' => SURVEY_CLICK,
						'SurveyVisit.created >=' => $start_date.' 00:00:00',
						'SurveyVisit.created <=' => $end_date.' 23:59:59'
					),
					'recursive' => -1,
				));
				if (empty($survey_visits)) {
					continue;
				}
				$this->out('Processing '.count($survey_visits).' clicks for #'.$project['Project']['id']); 
					
				foreach ($survey_visits as $visit) {
					if ($visit['SurveyVisit']['result'] == SURVEY_COMPLETED) {
						// set a safe value for the bid_ir if it doesn't exist; really for the null case
						if (empty($project['Project']['bid_ir'])) {
							$project['Project']['bid_ir'] = 0; 
						}
						
						// incrementing the counts for an IR
						if (!isset($partner_ir[$project['Project']['bid_ir']])) {
							$partner_ir[$project['Project']['bid_ir']] = 1;
						}
						else {
							$partner_ir[$project['Project']['bid_ir']]++;
						}

						// increment the counts for our actual IR; use a fallback in case the IR doesn't exist (it should, but in rare cases it might not)
						$actual_ir = null;
						if (!empty($our_ir[$project['SurveyVisitCache']['ir']])) { 
							$actual_ir = $project['SurveyVisitCache']['ir']; 
						}
						elseif (!empty($project['SurveyVisitCache']['click'])) {
							$actual_ir = ($project['SurveyVisitCache']['complete'] / $project['SurveyVisitCache']['click']) * 100;
						}
						
						// set the actual ir count
						if (!isset($our_ir[$actual_ir])) {
							$our_ir[$actual_ir] = 1; 
						}
						else {
							$our_ir[$actual_ir]++;
						}
						
						// write the project outputs
						if (!isset($project_ids[$actual_ir])) {
							$project_ids[$actual_ir] = array($project['Project']['id']);
						}
						elseif (!in_array($project['Project']['id'], $project_ids[$actual_ir])) {
							$project_ids[$actual_ir][] = $project['Project']['id'];
						}
					}
				}
			}
		}
		
		$filename = WWW_ROOT.'files/completes_over_ir_'.$this->args[1].'.csv';
		if (file_exists($filename)) {
			@unlink($$filename); 
		}
		
		$fp = fopen($filename, 'w');
		fputcsv($fp, array(
			'IR',
			'Partner Count',
			'Our Count',
			'Our Project IDs',
		));
		
		for ($i = 0; $i < 100; $i++) {
			fputcsv($fp, array(
				$i.'%',
				isset($partner_ir[$i]) ? $partner_ir[$i]: '',
				isset($our_ir[$i]) ? $our_ir[$i]: '',
				isset($project_ids[$i]) ? implode(', ', $project_ids[$i]): '',
			));
		}
		fclose($fp);
		$this->out('Completed');
	}

	public function completes_over_loi() {
		if (!empty($this->args[0])) {
			$start_date = date($this->args[0].'-1');
			$end_date = date($this->args[0].'-31');
		}

		if (!empty($this->args[1])) {
			$partner = $this->args[1];
		}
		else {
			$partner = 'cint';
		}

		$partner_loi = array();
		$our_loi = array();

		$group = $this->Group->find('first', array(
			'fields' => array('id'),
			'conditions' => array(
				'Group.key' => $partner
			)
		));

		if (!$group) {
			return false;
		}
		
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
		));
			
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Project.client_rate', 'Project.award', 'Project.started', 'Project.bid_ir', 'Project.ended', 'Project.mask', 'Project.epc', 'SurveyVisitCache.*'),
			'conditions' => $conditions
		));

		if ($projects) {
			foreach ($projects as $project) {
				$survey_visits = $this->SurveyVisit->find('all', array(
					'recursive' => -1,
					'conditions' => array(
						'SurveyVisit.survey_id' => $project['Project']['id'],
						'SurveyVisit.type' => SURVEY_CLICK,
						'SurveyVisit.created >=' => $start_date.' 00:00:00',
						'SurveyVisit.created <=' => $end_date.' 23:59:59'
					)
				));
					
				foreach ($survey_visits as $visit) {
					if ($visit['SurveyVisit']['result'] == SURVEY_COMPLETED) {
						$loi = $project['SurveyVisitCache']['loi_seconds'] / 60;
						if (empty($partner_loi[$loi])) {
							$partner_loi[$loi] = 1;
						} else {
							$partner_loi[$loi] = $partner_loi[$loi] + 1;
						}

						$loi = $project['SurveyVisitCache']['loi'] / 60;
						if (empty($our_loi[$loi])) {
							$our_loi[$loi] = 1;
						} else {
							$our_loi[$loi] = $our_loi[$loi] + 1;
						}
					}
				}
			}
		}
		
		ksort($partner_loi);
		$this->out('Partner LOI by Complete distribution');
		foreach ($partner_loi as $loi=>$value) {
			$this->out($loi.','.$value);
		}

		ksort($our_loi);
		$this->out('Our LOI by Complete distribution');
		foreach ($our_loi as $loi=>$value) {
			$this->out($loi.','.$value);
		}
	}

	public function nqs_over_time() {
		if (!empty($this->args[0])) {
			$start_date = date($this->args[0].'-1');
			$end_date = date($this->args[0].'-31');
		}
		
		

		if (!empty($this->args[1])) {
			$partner = $this->args[1];
		}
		else {
			$partner = 'cint';
		}

		$our_nq = array();
		$sum = 0;

		$group = $this->Group->find('first', array(
			'fields' => array('id'),
			'conditions' => array(
				'Group.key' => $partner
			)
		));

		if (!$group) {
			return false;
		}
		
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
		));
			
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Project.client_rate', 'Project.award', 'Project.started', 'Project.bid_ir', 'Project.ended', 'Project.mask', 'Project.epc', 'SurveyVisitCache.*'),
			'conditions' => $conditions
		));

		if ($projects) {
			foreach ($projects as $project) {
				$survey_visits = $this->SurveyVisit->find('all', array(
					'recursive' => -1,
					'conditions' => array(
						'SurveyVisit.survey_id' => $project['Project']['id'],
						'SurveyVisit.type' => SURVEY_CLICK,
						'SurveyVisit.created >=' => $start_date.' 00:00:00',
						'SurveyVisit.created <=' => $end_date.' 23:59:59'
					)
				));
					
				foreach ($survey_visits as $visit) {
					if ($visit['SurveyVisit']['result'] == SURVEY_NQ) {
						$time_to_nq = (strtotime($visit['SurveyVisit']['modified']) - strtotime($visit['SurveyVisit']['created'])) / 60;
						if (empty($our_nq[$time_to_nq])) {
							$our_nq[$time_to_nq] = 1;
							$sum = $sum + 1;
						}
						else {
							$our_nq[$time_to_nq] = $our_nq[$time_to_nq] + 1;
							$sum = $sum + 1;
						}
					}
				}
			}
		}
		
		ksort($our_nq);
		$this->out('Our NQ by Time distribution - Total: #'.$sum);
		foreach ($our_nq as $time=>$value) {
			$this->out($time.','.$value.','.(($value / $sum) * 100));
		}
	}

	public function monthly_report() {
		ini_set('memory_limit', '4096M');
		if (!empty($this->args[0])) {
			$start_date = date($this->args[0].'-1');
			$end_date = date($this->args[0].'-31');
		}
		
		$user_invites = array();
		$users_active = array();
		$user_ids_with_no_completes = array();
		$return = array();
		
		$project_ids = array();
		$project_earnings = array();
		$total_clicks = 0;
		$total_completes = 0;
		$total_nqs = 0;
		$total_earnings = 0;
		$poll_streak = 0;
		$average_days = 0;
		$users_transactions = array();

		$groups = $this->Group->find('list', array(
			'fields' => array('id'),
			'conditions' => array(
				'Group.key' => array('toluna', 'cint', 'precision', 'ssi', 'usurv', 'fulcrum', 'p2s')
			)
		));
		
		if (!$groups) {
			return false;
		}
		
		$conditions = array(
			'Project.group_id' => $groups,
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
		
		$this->out('Projects Found: '.count($projects));
		
		if ($projects) {
			foreach ($projects as $project) {
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
			
			foreach ($unique_panelists as $unique_panelist) {
				$user_invites[] = $unique_panelist['SurveyUser']['user_id'];
			}

			$this->out('Unique Invites: '.count($user_invites));


			$survey_visits = $this->SurveyVisit->find('all', array(
				'recursive' => -1,
				'fields' => array('id', 'result', 'survey_id', 'created'),
				'conditions' => array(
					'SurveyVisit.survey_id' => $project_ids,
					'SurveyVisit.type' => SURVEY_CLICK,
					'SurveyVisit.created >=' => $start_date.' 00:00:00',
					'SurveyVisit.created <=' => $end_date.' 23:59:59'
				)
			));

			foreach ($survey_visits as $visit) {
				$total_clicks++;
					
				if ($visit['SurveyVisit']['result'] == SURVEY_NQ) {
					$total_nqs++;
				}

				if ($visit['SurveyVisit']['result'] == SURVEY_COMPLETED) {
					$total_completes++;
					$total_earnings = $total_earnings + $project_earnings[$visit['SurveyVisit']['survey_id']];
				}
			}
	
			$transactions = $this->Transaction->find('all', array(
				'fields' => array('DISTINCT(user_id) as user_id'),
				'conditions' => array(
					'Transaction.user_id' => $user_invites,
					'Transaction.created >=' => $start_date.' 00:00:00',
					'Transaction.created <=' => $end_date.' 23:59:59',
					'Transaction.deleted' => null,
				)
			));
			
			foreach ($transactions as $transaction) {
				$users_active[] = $transaction['Transaction']['user_id'];
			}

			$this->out('Active users: '.count($users_active));
			
			$users = $this->User->find('all', array(
				'fields' => array('User.id', 'User.created', 'User.poll_streak', 'User.last_touched'),
				'conditions' => array(
					'User.id' => $users_active,
					'User.hellbanned' => false
				)
			));
			
			foreach ($users as $user) {
				$days_between = (strtotime($end_date) - strtotime($user['User']['created'])) / 86400;
				$average_days = $average_days + $days_between;
				if ($user['User']['poll_streak'] > 1) {
					$poll_streak++;
				}
			}

			$return = MintVineUser::user_level_count($users);
		}


		$user_count = $this->User->find('count', array(
			'conditions' => array(
				'User.created <=' => $end_date.' 23:59:59'
			)
		));

		$this->out('Unique user invites: '.count($user_invites).' of '.$user_count.' total users '.round((count($user_invites)/$user_count)*100, 3).'%');
		$this->out('Unique active users: '.count($users_active).' of '.count($user_invites).' invites '.round((count($users_active)/count($user_invites))*100, 3).'%');
		$this->out('Unique runners: '.$return['runners'].' of '.count($users_active).' active '.round(($return['runners']/count($users_active))*100, 3).'%');
		$this->out('Unique walkers: '.$return['walkers'].' of '.count($users_active).' active '.round(($return['walkers']/count($users_active))*100, 3).'%');
		$this->out('Unique living: '.$return['living'].' of '.count($users_active).' active '.round(($return['living']/count($users_active))*100, 3).'%');
		$this->out('Unique zombies: '.$return['zombies'].' of '.count($users_active).' active '.round(($return['zombies']/count($users_active))*100, 3).'%');
		$this->out('Unique dead: '.$return['dead'].' of '.count($users_active).' active '.round(($return['dead']/count($users_active))*100, 3).'%');
		$this->out('Total Clicks: '.$total_clicks);
		$this->out('Total NQs: '.$total_nqs);
		$this->out('Total Completes: '.$total_completes);
		$this->out('Total Revenue: '.round($total_earnings, 2));
		$this->out('Average User Age: '.round($average_days/count($users_active), 2));
		$this->out('Poll Streak Users: '.$poll_streak.' '.round(($poll_streak/count($users_active))*100, 2).'%');
		$this->out('Completes per user: '.round($total_completes/count($users_active), 2));
		$this->out('NQs per user: '.round($total_nqs/count($users_active), 2));
		$this->out('Clicks per user: '.round($total_clicks/count($users_active), 2));
		$this->out('Revenue per user: '.round($total_earnings/count($users_active), 2));
	}


}