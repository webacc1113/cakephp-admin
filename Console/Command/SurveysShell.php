<?php
App::uses('CakeEmail', 'Network/Email');
App::import('Lib', 'Utilities');
App::import('Lib', 'Surveys');
App::uses('HttpSocket', 'Network/Http');

class SurveysShell extends AppShell {
	public $uses = array('RouterLog', 'SurveyVisit', 'Client', 'Group', 'SurveyUser', 'Project', 'RevenueReport', 'RouterLog', 'Setting', 'ProjectLog', 'UsurvVisit', 'SsiLink', 'TolunaInvite', 'PrecisionOfferwallInvite');
	public $tasks = array('Surveys');

	// use to backfill data
	public function mass_current_revenue() {
		$day = '2016-02-15'; 
		$cur_datetime = date(DB_DATETIME);
		while (true) {
			for ($i = 1; $i < 24; $i++) {
				$start = $day.' 00:00:00'; 
				if ($i == 23) {
					$end = $day.' 23:59:59'; 
				}
				else {
					$end = $day.' '.str_pad($i, 2, '0', STR_PAD_LEFT).':00:00'; 
				}
				if (strtotime($end) >= strtotime('-1 hours')) {
					break;
				}
				$this->out($start.' <> '.$end);
				$this->current_revenue_for_the_day($start, $end);
			}
			// loop through each hour
			$ts = strtotime($day) + 86400; 
			$day = date(DB_DATE, $ts); 
			if ($day > date(DB_DATE)) {
				break;
			}
		}
	}
	
	// reopen all projects in a group 
	public function reopen_past_hours() {
		if (!isset($this->args[0])) {
			return false;
		}
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Project.ended', 'Project.started', 'SurveyVisitCache.click', 'SurveyVisitCache.complete'),
			'conditions' => array(
				'Project.group_id' => $this->args[0],
				'Project.ended >=' => date(DB_DATETIME, strtotime('-36 hours')),
				'Project.status' => PROJECT_STATUS_CLOSED,
			),
		));
		
		foreach ($projects as $project) {
			$this->Project->create();
			$this->Project->save(array('Project' => array(
				'id' => $project['Project']['id'],
				'status' => PROJECT_STATUS_OPEN,
				'ended' => null,
				'active' => true
			)), true, array('status', 'active', 'ended'));
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'status.opened.manual',
				'description' => 'Reopening all projects closed in group in last 36 hours',
			)));
			$this->out('Reopened '.$project['Project']['id']);
		}
	}
	
	// set to run at the beginning; we need to offset time one hour to account
	public function current_revenue_for_the_day($start_datetime = null, $end_datetime = null) {
		if (!empty($start_datetime) && !empty($end_datetime)) {
			// already set
		}
		elseif (isset($this->args[0]) && isset($this->args[1])) {
			$start_datetime = $this->args[0]; 
			$end_datetime = $this->args[1]; 
		}
		else {
			$ts = strtotime('-1 hour');
			$start_datetime = date(DB_DATE, $ts).' 00:00:00'; 
			$end_datetime = date(DB_DATE, $ts) .' '. date('H', $ts).':59:59'; 
		}
		$start_date = date(DB_DATE, strtotime($start_datetime)); 
		$end_date = date(DB_DATE, strtotime($end_datetime)); 
		$time = date('H:00:00', strtotime($end_datetime)); // only used for storing the report once an hour
				
		$this->out('Starting '.$start_datetime.' to '.$end_datetime.' ('.$time.')');
		$revenue_report = $this->RevenueReport->find('first', array(
			'conditions' => array(
				'RevenueReport.date' => date(DB_DATE, strtotime($start_date)),
				'RevenueReport.time' => $time
			)
		));
		if ($revenue_report) {
			return false;
		}
		
		$sum_revenue = $sum_projects = $sum_clicks = $sum_completes = $sum_invites = 0; 
		$partners = array('cint', 'ssi', 'fulcrum', 'p2s', 'points2shop', 'toluna', 'precision', 'usurv', 'mbd');
		foreach ($partners as $partner) {			
			$group = $this->Group->find('first', array(
				'fields' => array('id'),
				'conditions' => array(
					'Group.key' => $partner
				)
			));
			if (!$group) {
				continue;
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
				'hasOne' => array('SurveyVisitCache')
			));
			$projects = $this->Project->find('all', array(
				'fields' => array('Project.id', 'Project.client_rate', 'Project.award', 'Project.started', 'Project.ended', 'Project.mask'),
				'conditions' => $conditions
			));
			
			// import to reset in case a partner hasn't done anything yet
			$project_ids = $project_earnings = array();
			$project_count = 0;
			$total_earnings = 0;
			$total_entries = 0;
			$total_completes = 0;
			$invite_count = 0;
			
			$this->out('Analyzing '.count($projects).' projects from '.$partner);
			if ($projects) {
				foreach ($projects as $key => $project) {
					$project_ids[] = $project['Project']['id'];
					$project_earnings[$project['Project']['id']] = $project['Project']['client_rate'];
				}
				$fields = array('id', 'result', 'survey_id', 'created');
				$survey_visits = $this->SurveyVisit->find('all', array(
					'recursive' => -1,
					'fields' => $fields,
					'conditions' => array(
						'SurveyVisit.survey_id' => $project_ids,
						'SurveyVisit.type' => SURVEY_CLICK,
						'SurveyVisit.created >=' => $start_datetime,
						'SurveyVisit.created <=' => $end_datetime
					)
				));
				if ($partner == 'ssi') {
					$invite_count = $this->SsiLink->find('count', array(
						'recursive' => -1,
						'conditions' => array(
							'SsiLink.created >=' => $start_datetime,
							'SsiLink.created <=' => $end_datetime
						)
					));
				}
				elseif ($partner == 'toluna') {
					$invite_count = $this->TolunaInvite->find('count', array(
						'recursive' => -1,
						'conditions' => array(
							'TolunaInvite.created >=' => $start_datetime,
							'TolunaInvite.created <=' => $end_datetime
						)
					));
				}
				elseif ($partner == 'precision') {
					$invite_count = $this->PrecisionOfferwallInvite->find('count', array(
						'recursive' => -1,
						'conditions' => array(
							'PrecisionOfferwallInvite.created >=' => $start_datetime,
							'PrecisionOfferwallInvite.created <=' => $end_datetime
						)
					));
				}
				elseif ($partner == 'fulcrum') {
					$invite_count = 0; 
				}
				else {
					$invite_count = $this->SurveyUser->find('count', array(
						'recursive' => -1,
						'conditions' => array(
							'SurveyUser.survey_id' => $project_ids,
							'SurveyUser.created >=' => $start_datetime,
							'SurveyUser.created <=' => $end_datetime
						)
					));
				}
				$masks = $this->Project->find('list', array(
					'fields' => array('id', 'mask'),
					'conditions' => array(
						'Project.id' => $project_ids
					)
				));
				$project_count = count($project_ids);
				if ($survey_visits) {
					$total_entries = count($survey_visits);
					$total_completes = $total_nqs = $total_oqs = 0;
					$total_earnings = 0;
					foreach ($survey_visits as $survey_visit) {
						if ($survey_visit['SurveyVisit']['result'] == SURVEY_COMPLETED) {
							$total_completes++;			
							$total_earnings = $total_earnings + $project_earnings[$survey_visit['SurveyVisit']['survey_id']];
						}
					}
				}
			}
			
			// calculate p2s; it's stored differently
			if ($partner == 'p2s') {
				// points2shop has a different way of calculating 
				// update index 2 (total earnings) and 3 (epc)
	
				$router_log_sum = $this->RouterLog->find('list', array(
					'fields' => array('RouterLog.payout'),
					'conditions' => array(
						'RouterLog.created >' => $start_date.' 00:00:00',
						'RouterLog.created <' => $end_date.' 23:59:59',
						'RouterLog.type' => 'success',
						'RouterLog.source' => 'p2s',
						'RouterLog.duplicate' => false
					)
				));
				$total_completes = count($router_log_sum); 
				$total_earnings = round(array_sum($router_log_sum) / 100, 2);
			}
			
			// calculate ssi; it's stored differently
			if ($partner == 'ssi') {
				// points2shop has a different way of calculating 
				// update index 2 (total earnings) and 3 (epc)
	
				$router_log_sum = $this->RouterLog->find('list', array(
					'fields' => array('RouterLog.payout'),
					'conditions' => array(
						'RouterLog.created >' => $start_date.' 00:00:00',
						'RouterLog.created <' => $end_date.' 23:59:59',
						'RouterLog.type' => 'success',
						'RouterLog.source' => 'ssi',
						'RouterLog.duplicate' => false
					)
				));
				$total_completes = count($router_log_sum); 
				$total_earnings = round(array_sum($router_log_sum) / 100, 2);
			}
			
			// calculate usurv; it's stored differently
			if ($partner == 'usurv') {
				if ($projects) {
					$project_ids = Set::extract('/Project/id', $projects); 
					$usurv_visits = $this->UsurvVisit->find('list', array(
						'fields' => array('UsurvVisit.id', 'UsurvVisit.client_rate'),
						'conditions' => array(
							'UsurvVisit.created >=' => $start_date.' 00:00:00',
							'UsurvVisit.created <=' => $end_date.' 23:59:59',
							'UsurvVisit.survey_id' => $project_ids,
							'UsurvVisit.client_rate is not null'
						)
					));
					$total_completes = count($usurv_visits);
					$total_earnings = array_sum($usurv_visits);
				}
			}
			
			// sum all the revenues
			$sum_projects = $sum_projects + $project_count; 
			$sum_revenue = $sum_revenue + $total_earnings; 
			$sum_clicks = $sum_clicks + $total_entries; 
			$sum_completes = $sum_completes + $total_completes; 
			$sum_invites = $sum_invites + $invite_count; 
		
		}
		
		$revenueReportSource = $this->RevenueReport->getDataSource();
		$revenueReportSource->begin();
		$this->RevenueReport->create();
		$this->RevenueReport->save(array('RevenueReport' => array(
			'date' => $start_date,
			'time' => $time,
			'revenue_cents' => $sum_revenue * 100,
			'invites' => $sum_invites,
			'clicks' => $sum_clicks,
			'completes' => $sum_completes,
		))); 
		$revenue_report_id = $this->RevenueReport->getInsertId();
		$revenueReportSource->commit();

		$setting = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'slack.revenue.webhook',
				'Setting.deleted' => false
			)
		));
		if ($setting) {
			// find the last three weeks worth of data
			$dates_to_examine = array(
				date(DB_DATE, strtotime($end_date.' -1 week')),
				date(DB_DATE, strtotime($end_date.' -2 week')),
				date(DB_DATE, strtotime($end_date.' -3 week')),
				date(DB_DATE, strtotime($end_date.' -4 week')),
			);
			$progress_amounts = $total_amounts = $current_amounts = 0;
			foreach ($dates_to_examine as $date_to_examine) {
				$final_value = $this->RevenueReport->find('first', array(
					'conditions' => array(
						'RevenueReport.date' => $date_to_examine,
						'RevenueReport.time' => '23:00:00'
					)
				));
				$progress_value = $this->RevenueReport->find('first', array(
					'conditions' => array(
						'RevenueReport.date' => $date_to_examine,
						'RevenueReport.time' => $time
					)
				));
				if ($final_value && $progress_value) {
					$current_value = $final_value['RevenueReport']['revenue_cents'] - $progress_value['RevenueReport']['revenue_cents'];
					$current_amounts = $current_amounts + $current_value;
					$progress_amounts = $progress_value['RevenueReport']['revenue_cents'] + $progress_amounts; 
					$total_amounts = $final_value['RevenueReport']['revenue_cents'] + $total_amounts; 
				}
			}
			$avg_current_amount = $current_amounts / count($dates_to_examine);
			$total_current_amount = round($avg_current_amount / 100, 2) + $sum_revenue;

			if ($time == '23:00:00') {
				$date_text = 'Final tally for '.date('m/d', strtotime($end_datetime)); 
			}
			else {
				$date_text = date('m/d H:00', strtotime($end_datetime)); 
			}
			$text = $date_text.' *$'.number_format(round($sum_revenue, 2)).'* '
				.'Invites: '.number_format($sum_invites).'; '
				.'Clicks: '.number_format($sum_clicks).'; '
				.'Completes: '.number_format($sum_completes); 
			
			// weighting logic as described https://basecamp.com/2045906/projects/1413421/todos/312507670
			$weight_logic = array(
				'02' => array('method_1' => '.1' , 'method_2' => '.9'),
				'03' => array('method_1' => '.1' , 'method_2' => '.9'),
				'04' => array('method_1' => '.2' , 'method_2' => '.8'),
				'05' => array('method_1' => '.2' , 'method_2' => '.8'),
				'06' => array('method_1' => '.3' , 'method_2' => '.7'),
				'07' => array('method_1' => '.3' , 'method_2' => '.7'),
				'08' => array('method_1' => '.4' , 'method_2' => '.6'),
				'09' => array('method_1' => '.4' , 'method_2' => '.6'),
				'10' => array('method_1' => '.5' , 'method_2' => '.5'),
				'11' => array('method_1' => '.5' , 'method_2' => '.5'),
				'12' => array('method_1' => '.6' , 'method_2' => '.4'),
				'13' => array('method_1' => '.6' , 'method_2' => '.4'),
				'14' => array('method_1' => '.7' , 'method_2' => '.3'),
				'15' => array('method_1' => '.8' , 'method_2' => '.2'),
				'16' => array('method_1' => '.9' , 'method_2' => '.1'),
				'17' => array('method_1' => '1' , 'method_2' => '0'),
				'18' => array('method_1' => '1' , 'method_2' => '0'),
				'19' => array('method_1' => '1' , 'method_2' => '0'),
				'20' => array('method_1' => '1' , 'method_2' => '0'),
				'21' => array('method_1' => '1' , 'method_2' => '0'),
				'22' => array('method_1' => '1' , 'method_2' => '0'),
				'23' => array('method_1' => '1' , 'method_2' => '0'),
			);

			// skip the last hour
			if ($progress_amounts > 0 && $total_amounts > 0 && $time != '23:00:00') {
				$ratio = round($progress_amounts / $total_amounts, 4);
				$multiplier = 1 / $ratio; 
				$expected = $sum_revenue * $multiplier; 
				if (!in_array($time, array('00:00:00', '01:00:00', '23:00:00'))) {
					$h_index = date('H', strtotime($time));
					$expected = $expected * $weight_logic[$h_index]['method_1'] + $total_current_amount * $weight_logic[$h_index]['method_2'];
					$text .= " _Projected: $".number_format(round($expected, 2))."_";
				}
				$this->RevenueReport->create();
				$this->RevenueReport->save(array('RevenueReport' => array(
					'id' => $revenue_report_id,
					'projected' => round($expected, 2)
				)), true, array('projected'));
			}
			
			$http = new HttpSocket(array(
				'timeout' => '2',
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$http->post($setting['Setting']['value'], json_encode(array(
				'text' => $text,
				'link_names' => 1,
				'username' => 'bernard'
			))); 
			$this->out($text); 
		}
	}
	
	public function determine_router_position() {
			
	}
}