<?php
App::uses('AppController', 'Controller');
App::import('Lib', 'Reporting');
App::import('Lib', 'MintVineUser');

class ReportsController extends AppController {
	public $uses = array('Report', 'Source', 'User', 'UserRouterLog', 'Client', 'Transaction', 'Group', 'SsiLink', 'LucidEpcStatistic', 'PrecisionOfferwallInvite', 'RouterLog', 'FedSurvey', 'Lander', 'UserAddress', 'Partner', 'SurveyVisit', 'Project', 'UserRevenue', 'SurveyReport', 'QueryProfile', 'SurveyUserVisit', 'Query', 'SurveyUser', 'RouterLog', 'SurveyUserQuery', 'QueryHistory', 'QueryStatistic', 'SourceMapping', 'OfferRedemption', 'ProjectCompleteHistory', 'UserOption', 'Inventory', 'TolunaInvite', 'GroupPerformanceReport', 'OfferRedemptionReport', 'PanelistHistory');
	public $helpers = array('Text', 'Html', 'Time', 'Number', 'Report');
	public $components = array('RequestHandler', 'QueryEngine', 'ReportData');
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function status() {
		if ($this->request->is('put') || $this->request->is('post')) {
			$projects = explode("\n", trim($this->request->data['Report']['ids']));
			array_walk($projects, create_function('&$val', '$val = trim($val);')); 
			if (!empty($projects)) {
				$project_ids = array();
				$not_found_ids = array();
				$extra_data = array();
				foreach ($projects as $project_id) {
					$has_extra_data = strpos($project_id, ',') !== false;
					if ($has_extra_data) {
						$values = explode(',', $project_id);
						array_walk($values, create_function('&$val', '$val = trim($val);'));
						$project_id = array_shift($values);
					}
					$project_id = strtoupper($project_id);
					if ($this->request->data['Report']['type'] == 'fulcrum') {
						if (substr($project_id, 0, 2) != '#F') {
							$project_id = '#F'.$project_id;
						}
					}
					$mv_project_id = MintVine::parse_project_id($project_id);
					if ($mv_project_id) {
						$project_ids[] = $mv_project_id;
						if ($has_extra_data) {
							$extra_data['found'][$mv_project_id] = $values;
						}
					}
					else {
						$not_found_ids[] = $project_id;
						if ($has_extra_data) {
							$extra_data['notfound'][$project_id] = $values;
						}
					}
				}
				
				// permission check
				if (!empty($project_ids)) {
					foreach ($project_ids as $key => &$prj_id) {
						if (!$this->Admins->can_access_project($this->current_user, $prj_id)) {
							unset($project_ids[$key]);
						}
					}
				}
			
				$project_ids = $this->Project->find('list', array(
					'conditions' => array(
						'Project.id' => $project_ids
					),
					'fields' => array('id', 'id')
				));
			}
			else {
				$this->Session->setFlash('You did not input any project IDs', 'flash_error');
			}
			$data = array(array(
				'MintVine ID',
				'Mask ID', 
				'Status',
				'Client',
				'Client Rate',
				'Panelist Payout',
				'LOI',
				'LOI (Actual)',
				'IR',
				'IR (Actual)',
				'Client IR',
				'Drops',
				'EPC',
				'Click', 
				'Complete', 
				'NQ', 
				'OQ', 
				'Created', 
				'Closed',
				'Closed Reason',
				'Panelists Invited',
			));
			if (isset($project_ids) && !empty($project_ids)) {
				$projects = $this->Project->find('all', array(
					'conditions' => array(
						'Project.id' => $project_ids
					)
				));
				if ($projects) {
					foreach ($projects as $project) {
						$project_log = $this->ProjectLog->find('first', array(
							'conditions' => array(
								'ProjectLog.project_id' => $project['Project']['id'],
								'ProjectLog.type LIKE' => 'status.closed%'
							),
							'order' => 'ProjectLog.id DESC'
						)); 
						$count = $this->SurveyUser->find('count', array(
							'conditions' => array(
								'SurveyUser.survey_id' => $project['Project']['id']
							),
							'recursive' => -1,
						));
						if ($project['SurveyVisitCache']['click'] > 0) {
							$epc = round(($project['Project']['client_rate'] * $project['SurveyVisitCache']['complete']) / $project['SurveyVisitCache']['click'], 2);
						}
						else {
							$epc = '-';
						}
						$row = array(
							$project['Project']['id'],
							!empty($project['Project']['mask']) ? $project['Project']['mask']: '-',
							$project['Project']['status'],
							$project['Client']['client_name'],
							number_format($project['Project']['client_rate'], 2),
							$project['Project']['award'],
							$project['Project']['est_length'],
							round($project['SurveyVisitCache']['loi_seconds'] / 60),
							$project['Project']['bid_ir'],
							!empty($project['SurveyVisitCache']['click']) ? (round($project['SurveyVisitCache']['complete'] / $project['SurveyVisitCache']['click'], 2) * 100): '0',
							!is_null($project['SurveyVisitCache']['client_ir']) ? $project['SurveyVisitCache']['client_ir'] : '-',
							$project['SurveyVisitCache']['drops'] > 0 ? $project['SurveyVisitCache']['drops'].'%': '',
							$epc,
							$project['SurveyVisitCache']['click'],
							$project['SurveyVisitCache']['complete'],
							$project['SurveyVisitCache']['nq'],
							$project['SurveyVisitCache']['overquota'],
							$project['Project']['started'],
							$project['Project']['ended'],
							$project_log ? $project_log['ProjectLog']['description'] : '',
							$count
						);
						if (isset($extra_data['found'][$project['Project']['id']])) {
							$row = array_merge($row, $extra_data['found'][$project['Project']['id']]);
						}
						$data[] = $row;
					}
				}
			}
					
			if (!empty($not_found_ids)) {
				foreach ($not_found_ids as $not_found_id) {
					if (substr($not_found_id, 0, 2) == '#F') {
						$fed_survey = $this->FedSurvey->find('first', array(
							'conditions' => array(
								'FedSurvey.fed_survey_id' => substr($not_found_id, 2)
							)
						));
					}
					$row = array(
						'?',
						$not_found_id,
						$fed_survey ? $fed_survey['FedSurvey']['status']: '',
					);
					if (isset($extra_data['notfound'][$project['Project']['id']])) {
						$row = array_merge($row, $extra_data['found'][$project['Project']['id']]);
					}
					$data[] = $row;
				}
			}
				
  			$filename = 'mintvine-status-'.gmdate(DB_DATE, time()) . '.csv';
	  		$csv_file = fopen('php://output', 'w');

			header('Content-type: application/csv');
			header('Content-Disposition: attachment; filename="' . $filename . '"');

			// Each iteration of this while loop will be a row in your .csv file where each field corresponds to the heading of the column
			foreach ($data as $row) {
				fputcsv($csv_file, $row, ',', '"');
			}

			fclose($csv_file);
			$this->autoRender = false;
			$this->layout = false;
			$this->render(false);
		}
	}
	
	public function export_statistics_by_day() {
		$groups = $this->Group->find('list', array(
			'fields' => array('Group.id', 'Group.name'),
			'recursive' => -1,
			'order' => 'Group.name ASC'
		));
		
		// used to determine some special cases in report generation
		$lucid_group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'fulcrum'
			)
		)); 
		if ($this->request->is('post') || $this->request->is('put')) {
			if (empty($this->request->data['Report']['group']) || empty($this->request->data['Report']['date'])) {
				$this->Session->setFlash('You are missing a required field.', 'flash_error'); 
			}
			else { 
				$date = date(DB_DATE, strtotime(trim($this->request->data['Report']['date'])));
				$start_date = $date.' 00:00:00';
				$end_date = $date.' 23:59:59';
				$conditions = array(
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
				if ($this->request->data['Report']['group'] != 'all') {
					$conditions['Project.group_id'] = $this->request->data['Report']['group'];
				}
				if (isset($this->request->data['Report']['filter_ids'])) {
					$this->request->data['Report']['filter_ids'] = trim($this->request->data['Report']['filter_ids']); 
					if (!empty($this->request->data['Report']['filter_ids'])) {
						$filter_ids = explode("\n", $this->request->data['Report']['filter_ids']);
						array_walk($filter_ids, create_function('&$val', '$val = trim($val);')); 
						$conditions['Project.id'] = $filter_ids; 
					}
				}
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
				$header = array(
					'QQQ', 
					'Subquals',
					'Group',
					'Client',
					'Project ID',
					'Mask ID',
					'US',
					'CA',
					'GB',
					'Client Rate',
					'Status', 
					'Started', 
					'Ended',
					'Minutes Live',
					'Bid LOI',
					'LOI',
					'IR',
					'EPC',
					'Total Revenue',
					'Invites',
					'Close Reason',
					'Margin',
					'Profit',
				);
				$statuses = unserialize(SURVEY_STATUSES);
				foreach ($statuses as $status) {
					$header[] = $status;
				} 
				
				// lucid; we can output the highest trailing EPC reported on the exchange
				if ($this->request->data['Report']['group'] == $lucid_group['Group']['id'] || $this->request->data['Report']['group'] == 'all') {
					$header[] = 'Highest Trailing EPC on Lucid';
				}
				
				$csv_rows = array($header);
				if ($projects) {
					foreach ($projects as $project) {
						$survey_visits = $this->SurveyVisit->find('list', array(
							'fields' => array('SurveyVisit.id', 'SurveyVisit.result'),
							'conditions' => array(
								'SurveyVisit.survey_id' => $project['Project']['id'],
								'SurveyVisit.created >=' => $start_date,
								'SurveyVisit.created <=' => $end_date,
								'SurveyVisit.type' => SURVEY_CLICK
							)
						));
						if (!$survey_visits) {
							continue;
						}
						
						$clicks = count($survey_visits);
						$counts = array_count_values($survey_visits);
						
						//invites count for the projects
						if ($project['Group']['key'] == 'ssi') {
							$invite_count = $this->SsiLink->find('count', array(
								'recursive' => -1,
								'conditions' => array(
									'SsiLink.created >=' => $start_date,
									'SsiLink.created <=' => $end_date
								)
							));
						}
						elseif ($project['Group']['key'] == 'toluna') {
							$invite_count = $this->TolunaInvite->find('count', array(
								'recursive' => -1,
								'conditions' => array(
									'TolunaInvite.created >=' => $start_date,
									'TolunaInvite.created <=' => $end_date
								)
							));
						}
						elseif ($project['Group']['key'] == 'precision') {
							$invite_count = $this->PrecisionOfferwallInvite->find('count', array(
								'recursive' => -1,
								'conditions' => array(
									'PrecisionOfferwallInvite.created >=' => $start_date,
									'PrecisionOfferwallInvite.created <=' => $end_date
								)
							));
						}
						elseif ($project['Group']['key'] == 'fulcrum') {
							$invite_count = 0;
						}
						else {
							$invite_count = $this->SurveyUser->find('count', array(
								'recursive' => -1,
								'conditions' => array(
									'SurveyUser.survey_id' => $project['Project']['id'],
									'SurveyUser.created >=' => $start_date,
									'SurveyUser.created <=' => $end_date
								)
							));
						}
						
						if ($project['Project']['status'] == 'Closed') {
							$project_log = $this->ProjectLog->find('first', array(
								'conditions' => array(
									'ProjectLog.project_id' => $project['Project']['id'],
									'ProjectLog.type LIKE' => 'status.closed%'
								),
								'order' => 'ProjectLog.id DESC'
							));
						}
						
						$closed_reason = '';
						if (in_array($project['Project']['status'], array(PROJECT_STATUS_CLOSED, PROJECT_STATUS_INVOICED)) && isset($project_log) && !empty($project_log)) {
							$closed_reason = $project_log['ProjectLog']['description'];
						}
					
						if (!is_null($project['Project']['ended']) && !is_null($project['Project']['started'])) {
							$live_time_in_seconds = strtotime($project['Project']['ended']) - strtotime($project['Project']['started']);
							$live_time_in_minutes = round($live_time_in_seconds / 60); 
						}
						else {
							$live_time_in_minutes = '';
						}
						
						$row = array(
							$project['Project']['temp_qualifications'] ? 'Y': 'N',
							$project['Project']['subquals'] ? 'Y': 'N',
							$project['Group']['name'],
							$project['Client']['client_name'],
							$project['Project']['id'],
							$project['Project']['mask'],
							$project['Project']['country'] == 'US' ? 'Y': '-',
							$project['Project']['country'] == 'CA' ? 'Y': '-',
							$project['Project']['country'] == 'GB' ? 'Y': '-',
							$project['Project']['client_rate'],
							$project['Project']['status'],
							$project['Project']['started'],
							$project['Project']['ended'],
							$live_time_in_minutes,
							$project['Project']['est_length'],
							!empty($project['SurveyVisitCache']['loi_seconds']) ? round($project['SurveyVisitCache']['loi_seconds'] / 60) : '',
							'0', // placeholder for ir - set below
							'0', // placeholder for  epc- set below
							'0', // placeholder for total rev - set below,
							$invite_count,
							$closed_reason,
							!is_null($project['Project']['margin_pct']) ? $project['Project']['margin_pct'].'%': '',
							!is_null($project['Project']['margin_cents']) ? round($project['Project']['margin_cents'] / 100, 2): '',
						);
						
						foreach ($statuses as $key => $status) {
							if ($key == SURVEY_CLICK) {
								$row[] = $clicks;
							}
							else {
								if (isset($counts[$key])) {
									$row[] = $counts[$key];
								}
								else {
									$row[] = 0;
								}
							}
						}
						
						// calculate IR is key 6, EPC is 7
						if ($row[23] > 0) {
							$ir = round($row[24] / $row[23], 2); // completes/clicks
						}
						else {
							$ir = 0;
						}
						$row[16] = $ir * 100;
						$epc = round($ir * $row[9], 2);
						$row[17] = number_format($epc, 2);
						$row[18] = $row[9] * $row[24];
						
						if ($project['Group']['key'] == 'fulcrum') {
							$lucid_epc_statistic = $this->LucidEpcStatistic->find('first', array(
								'fields' => array('LucidEpcStatistic.trailing_epc_cents'),
								'conditions' => array(
									'LucidEpcStatistic.project_id' => $project['Project']['id'],
									'LucidEpcStatistic.created >=' => $start_date,
									'LucidEpcStatistic.created <=' => $end_date,
								),
								'order' => 'LucidEpcStatistic.trailing_epc_cents DESC'
							));
							if ($lucid_epc_statistic) {
								$row[] = number_format($lucid_epc_statistic['LucidEpcStatistic']['trailing_epc_cents'] / 100, 2); 
							}
							else {
								$row[] = '-';
							}
						}
						else if ($this->request->data['Report']['group'] == 'all') {
							$row[] = '-';	
						}
						
						$csv_rows[] = $row; 
					}
					if ($this->request->data['Report']['group'] == 'all') {
						$filename = 'projects_statistics_by_day-'.$date. '.csv';
					} else {
						$filename = str_replace(' ', '_', strtolower($project['Group']['name'])).'_export_statistics_by_day-'.$date. '.csv';
					}
					$csv_file = fopen('php://output', 'w');

					header('Content-type: application/csv');
					header('Content-Disposition: attachment; filename="' . $filename . '"');

					// Each iteration of this while loop will be a row in your .csv file where each field corresponds to the heading of the column
					foreach ($csv_rows as $row) {
						fputcsv($csv_file, $row, ',', '"');
					}

					fclose($csv_file);
					$this->autoRender = false;
					$this->layout = false;
					$this->render(false);
					return;
				}
				else {
					$this->Session->setFlash('Projects not found in the selected date', 'flash_error');
				}
								
				
			}
		}
		$this->set(compact('groups')); 
	}
	
	
	// export all data that shows why users hid surveys
	public function export_survey_hidden() {
		if ($this->request->is('put') || $this->request->is('post')) {
			if (empty($this->request->data['Report']['type'])) {
				$this->Session->setFlash('Please pick a reason', 'flash_error');
			}
			else {
				$start_date = date(DB_DATE, strtotime($this->request->data['Report']['date_from']));
				$end_date = date(DB_DATE, strtotime($this->request->data['Report']['date_to']));
				
				$survey_users = $this->SurveyUser->find('list', array(
					'fields' => array('id', 'survey_id'),
					'conditions' => array(
						'SurveyUser.hidden' => $this->request->data['Report']['type'],
						'SurveyUser.created >=' => $start_date.' 00:00:00',
						'SurveyUser.created <=' => $end_date.' 23:59:59',
					),
					'recursive' => -1,
				));
				
				$survey_ids = array_unique($survey_users);
				$projects = $this->Project->find('all', array(
					'fields' => array('Project.est_length', 'Project.id', 'Project.award', 'Project.bid_ir'),
					'conditions' => array(
						'Project.id' => $survey_ids
					),
				));
				foreach ($projects as $key => $project) {
					unset($projects[$key]);
					$projects[$project['Project']['id']] = $project;
				}
				$csv_rows = array(array(
					'Project ID',
					'Award',
					'LOI',
					'IR',
					'EPC'
				));
				foreach ($survey_users as $survey_id) {
					$csv_rows[] = array(
						$survey_id, 
						$projects[$survey_id]['Project']['award'], 
						$projects[$survey_id]['Project']['est_length'],
						$projects[$survey_id]['Project']['bid_ir'],
						round($projects[$survey_id]['Project']['award'] * $projects[$survey_id]['Project']['bid_ir'] / 10000, 2)
					);
				}
				
				$filename = 'export_survey_hidden-'.$start_date.'-'. $end_date . '.csv';
			  	$csv_file = fopen('php://output', 'w');

				header('Content-type: application/csv');
				header('Content-Disposition: attachment; filename="' . $filename . '"');

				// Each iteration of this while loop will be a row in your .csv file where each field corresponds to the heading of the column
				foreach ($csv_rows as $row) {
					fputcsv($csv_file, $row, ',', '"');
				}

				fclose($csv_file);
				$this->autoRender = false;
				$this->layout = false;
				$this->render(false);
				return;
			}
		}
	}
	
	private function getStatisticData($date, $time, $export_to_csv, $compare_date, $flush_cache_data = false) {
		$request = $this->request->data;
		$time_start = microtime(true);
		$partners = array('cint', 'ssi', 'fulcrum', 'p2s', 'points2shop', 'toluna', 'usurv', 'rfg', 'precision', 'mintvine', 'socialglimpz', 'mbd', 'spectrum');
		$start_date = $date.' 00:00:00';
		$end_date = $date.' '.$time;
		
		if ($flush_cache_data) {
			foreach ($partners as $partner) {
				if (isset($this->data['Report'][$partner]) && $this->data['Report'][$partner] == '1') {
					$cached_statistics = $this->GroupPerformanceReport->find('all', array(
						'conditions' => array(
							'date' => date(DB_DATE, strtotime($date)),
							'partner' => $partner,
						),
						'fields' => array('id'),
						'recursive' => -1
					));	
					if ($cached_statistics) {
						foreach ($cached_statistics as $cached_stat) {
							$this->GroupPerformanceReport->delete($cached_stat['GroupPerformanceReport']['id']);
						}
					}
				}
			}
		}
		if ($export_to_csv) {
			$csv_rows = array(array(
				'Project ID',
				'Mask ID',
				'Date (GMT)',
				'CPI',
				'Session ID',
			));
		}
		// If a "full day" report is being run (i.e. a report for a past day), it should not cache the values unless it is past 12:30am on the next day
		$day_next = date(DB_DATE.' 00:30:00', strtotime( $date . ' +1 day' ));
		$past_report = false;
		if (empty($compare_date)) {
			$past_report = (strtotime($date) < strtotime(date(DB_DATE)) && strtotime(date(DB_DATETIME)) >= strtotime($day_next));
		} 
		elseif (strtotime($compare_date) < strtotime(date(DB_DATE))) {
			$past_report = (strtotime($date) < strtotime(date(DB_DATE)) && strtotime(date(DB_DATETIME)) >= strtotime($day_next));
		}
		$cached_partners = array();
		if ($past_report) {
			$cached_statistics = $this->GroupPerformanceReport->find('all', array(
				'conditions' => array(
					'date' => date(DB_DATE, strtotime($date))
				)
			));
			
			if (!empty($cached_statistics)) {
				foreach ($cached_statistics as $cached) {
					$cached = $cached['GroupPerformanceReport'];
					if (!isset($this->data['Report'][$cached['partner']]) || $this->data['Report'][$cached['partner']] != '1') {
						continue;
					}
					$cached_partners[] = $cached['partner'];
					$cached_rows[$cached['partner']][] = array(
						$cached['total_entries'],
						$cached['total_completes'],
						$cached['total_earnings'],
						$cached['epc'],
						$cached['invite_count'],
						$cached['project_count'],
						$cached['unique_panelists'],
						$cached['total_oqs'],
						$cached['total_nqs'],
						$cached['country']
					);
					if ($cached['projects_imported'] != NULL && $cached['projects_launched'] != NULL) {
						$cached_launched_rows[$cached['partner']] = array(
							$cached['projects_imported'],
							$cached['projects_launched']
						);
					}
					
					if ($export_to_csv) {
						if (!empty($cached['csv'])) {
							$cached_csv_rows = json_decode($cached['csv'], true);
							if (!empty($cached_csv_rows)) {
								foreach ($cached_csv_rows as $cached_csv_row) {
									$csv_rows[] = array(
										$cached_csv_row['project_id'],
										$cached_csv_row['mask_id'],
										$cached_csv_row['date'],
										$cached_csv_row['cpi'],
										$cached_csv_row['session_id']
									);
								}
							}
						}
					}
				}
			}
		}
		// data to show: total clicks, completes, oqs, nqs, epc, # projects
		$rows = array();
		$launched_rows = array();
		$groups = $this->Group->find('list', array(
			'fields' => array('Group.key', 'Group.name'),
			'recursive' => -1
		));

		$this->set(compact('groups'));
		if ($past_report && !empty($cached_partners)) {
			// get reports for un-cached partners only
			$partners = array_diff($partners, $cached_partners);
		}

		foreach ($partners as $partner) {
			if (!isset($this->data['Report'][$partner]) || $this->data['Report'][$partner] != '1') {
				continue;
			}
			$generate_launched_data = false;

			$group = $this->Group->find('first', array(
				'fields' => array('id'),
				'conditions' => array(
					'Group.key' => $partner
				)
			));
			if (!$group) {
				continue;
			}

			$generate_launched_data = true;
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
						'Project.ended LIKE' => trim($date) .'%'
					) 
				)
			);
			$this->Project->unbindModel(array(
				'hasMany' => array('SurveyPartner', 'ProjectOption'),
				'belongsTo' => array('Group', 'Client'),
				'hasOne' => array('SurveyVisitCache')
			));
			$projects = $this->Project->find('all', array(
				'fields' => array('Project.id', 'Project.client_rate', 'Project.award', 'Project.started', 'Project.ended', 'Project.mask', 'Project.country'),
				'conditions' => $conditions
			));

			$rows[$partner][] = array(
				'0',
				'0',
				'0',
				'0',
				'0',
				'0',
				'0',
				'0',
				'0',
				''
			);
			if ($export_to_csv && empty($csv_rows)) {
				$csv_rows = array(array(
					'Project ID',
					'Mask ID',
					'Date (GMT)',
					'CPI',
					'Session ID',
				));
			}
			if ($projects) {
				$project_ids = $project_earnings = $us_project_ids = $gb_project_ids = $ca_project_ids = array();
				foreach ($projects as $key => $project) {
					$project_ids[] = $project['Project']['id'];
					$project_earnings[$project['Project']['id']] = $project['Project']['client_rate'];
					
					if ($project['Project']['country'] == 'US') {
						$us_project_ids[$project['Project']['id']] = $project['Project']['id'];
					}
					elseif ($project['Project']['country'] == 'GB') {
						$gb_project_ids[$project['Project']['id']] = $project['Project']['id'];
					}
					elseif ($project['Project']['country'] == 'CA') {
						$ca_project_ids[$project['Project']['id']] = $project['Project']['id'];
					}
				}
				
				// lucid has too much reach
				if ($partner == 'fulcrum') {
					$unique_panelists = $us_unique_panelists = $gb_unique_panelists = $ca_unique_panelists = 0;
				}
				else {
					$unique_panelists = 0;
					if (!empty($us_project_ids)) {
						$us_unique_panelists = $this->SurveyUser->find('all', array(
							'fields' => array('DISTINCT(user_id) as user_id'),
							'conditions' => array(
								'SurveyUser.survey_id' => $us_project_ids,
								'SurveyUser.created >=' => $start_date,
								'SurveyUser.created <=' => $end_date
							)
						));
						$us_unique_panelists = count($us_unique_panelists);
						$unique_panelists += $us_unique_panelists;
					}
					
					if (!empty($gb_project_ids)) {
						$gb_unique_panelists = $this->SurveyUser->find('all', array(
							'fields' => array('DISTINCT(user_id) as user_id'),
							'conditions' => array(
								'SurveyUser.survey_id' => $gb_project_ids,
								'SurveyUser.created >=' => $start_date,
								'SurveyUser.created <=' => $end_date
							)
						));
						$gb_unique_panelists = count($gb_unique_panelists);
						$unique_panelists += $gb_unique_panelists;
					}

					if (!empty($ca_project_ids)) {
						$ca_unique_panelists = $this->SurveyUser->find('all', array(
							'fields' => array('DISTINCT(user_id) as user_id'),
							'conditions' => array(
								'SurveyUser.survey_id' => $ca_project_ids,
								'SurveyUser.created >=' => $start_date,
								'SurveyUser.created <=' => $end_date
							)
						));
						$ca_unique_panelists = count($ca_unique_panelists);
						$unique_panelists += $ca_unique_panelists;
					}
				}
				
				$fields = array('id', 'result', 'survey_id', 'created', 'hash');
				$survey_visits = $this->SurveyVisit->find('all', array(
					'recursive' => -1,
					'fields' => $fields,
					'conditions' => array(
						'SurveyVisit.survey_id' => $project_ids,
						'SurveyVisit.type' => SURVEY_CLICK,
						'SurveyVisit.created >=' => $start_date,
						'SurveyVisit.created <=' => $end_date
					)
				));
				$us_survey_visits = $this->SurveyVisit->find('count', array(
					'recursive' => -1,
					'fields' => $fields,
					'conditions' => array(
						'SurveyVisit.survey_id' => $us_project_ids,
						'SurveyVisit.type' => SURVEY_CLICK,
						'SurveyVisit.created >=' => $start_date,
						'SurveyVisit.created <=' => $end_date
					)
				));
				$gb_survey_visits = $this->SurveyVisit->find('count', array(
					'recursive' => -1,
					'fields' => $fields,
					'conditions' => array(
						'SurveyVisit.survey_id' => $gb_project_ids,
						'SurveyVisit.type' => SURVEY_CLICK,
						'SurveyVisit.created >=' => $start_date,
						'SurveyVisit.created <=' => $end_date
					)
				));
				$ca_survey_visits = $this->SurveyVisit->find('count', array(
					'recursive' => -1,
					'fields' => $fields,
					'conditions' => array(
						'SurveyVisit.survey_id' => $ca_project_ids,
						'SurveyVisit.type' => SURVEY_CLICK,
						'SurveyVisit.created >=' => $start_date,
						'SurveyVisit.created <=' => $end_date
					)
				));

				if ($partner == 'ssi') {
					$invite_count = $this->SsiLink->find('count', array(
						'recursive' => -1,
						'conditions' => array(
							'SsiLink.created >=' => $date.' 00:00:00',
							'SsiLink.created <=' => $date.' '.$time
						)
					));
					$us_invite_count = $invite_count;
					$ca_invite_count = $gb_invite_count = 0;
				}
				elseif ($partner == 'toluna') {
					$invite_count = 0;
					$us_invite_count = $this->TolunaInvite->find('count', array(
						'recursive' => -1,
						'conditions' => array(
							'TolunaInvite.created >=' => $date.' 00:00:00',
							'TolunaInvite.created <=' => $date.' '.$time,
							'TolunaInvite.project_id' => $us_project_ids
						)
					));
					$invite_count += $us_invite_count;
					$gb_invite_count = $this->TolunaInvite->find('count', array(
						'recursive' => -1,
						'conditions' => array(
							'TolunaInvite.created >=' => $date.' 00:00:00',
							'TolunaInvite.created <=' => $date.' '.$time,
							'TolunaInvite.project_id' => $gb_project_ids
						)
					));
					$invite_count += $gb_invite_count;
					$ca_invite_count = $this->TolunaInvite->find('count', array(
						'recursive' => -1,
						'conditions' => array(
							'TolunaInvite.created >=' => $date.' 00:00:00',
							'TolunaInvite.created <=' => $date.' '.$time,
							'TolunaInvite.project_id' => $ca_project_ids
						)
					));
					$invite_count += $ca_invite_count;
				}
				elseif ($partner == 'precision') {
					$invite_count = 0;
					$us_invite_count = $this->PrecisionOfferwallInvite->find('count', array(
						'recursive' => -1,
						'conditions' => array(
							'PrecisionOfferwallInvite.created >=' => $date.' 00:00:00',
							'PrecisionOfferwallInvite.created <=' => $date.' '.$time,
							'PrecisionOfferwallInvite.project_id' => $us_project_ids
						)
					));
					$invite_count += $us_invite_count;
					$gb_invite_count = $this->PrecisionOfferwallInvite->find('count', array(
						'recursive' => -1,
						'conditions' => array(
							'PrecisionOfferwallInvite.created >=' => $date.' 00:00:00',
							'PrecisionOfferwallInvite.created <=' => $date.' '.$time,
							'PrecisionOfferwallInvite.project_id' => $gb_project_ids
						)
					));
					$invite_count += $gb_invite_count;
					$ca_invite_count = $this->PrecisionOfferwallInvite->find('count', array(
						'recursive' => -1,
						'conditions' => array(
							'PrecisionOfferwallInvite.created >=' => $date.' 00:00:00',
							'PrecisionOfferwallInvite.created <=' => $date.' '.$time,
							'PrecisionOfferwallInvite.project_id' => $ca_project_ids
						)
					));
					$invite_count += $ca_invite_count;
				}
				elseif ($partner == 'fulcrum') {
					$invite_count = $us_invite_count = $gb_invite_count = $ca_invite_count = 0;
				}
				else {
					$invite_count = 0;
					$us_invite_count = $this->SurveyUser->find('count', array(
						'recursive' => -1,
						'conditions' => array(
							'SurveyUser.survey_id' => $us_project_ids,
							'SurveyUser.created >=' => $date.' 00:00:00',
							'SurveyUser.created <=' => $date.' '.$time
						)
					));
					$invite_count += $us_invite_count;
					$gb_invite_count = $this->SurveyUser->find('count', array(
						'recursive' => -1,
						'conditions' => array(
							'SurveyUser.survey_id' => $gb_project_ids,
							'SurveyUser.created >=' => $date.' 00:00:00',
							'SurveyUser.created <=' => $date.' '.$time
						)
					));
					$invite_count += $gb_invite_count;
					$ca_invite_count = $this->SurveyUser->find('count', array(
						'recursive' => -1,
						'conditions' => array(
							'SurveyUser.survey_id' => $ca_project_ids,
							'SurveyUser.created >=' => $date.' 00:00:00',
							'SurveyUser.created <=' => $date.' '.$time
						)
					));
					$invite_count += $ca_invite_count;
				}

				$masks = $this->Project->find('list', array(
					'fields' => array('Project.id', 'Project.mask'),
					'conditions' => array(
						'Project.id' => $project_ids
					),
					'recursive' => -1
				));
				$project_count = count($project_ids);
				$us_project_count = count($us_project_ids);
				$gb_project_count = count($gb_project_ids);
				$ca_project_count = count($ca_project_ids);
				if ($survey_visits) { 
					$total_entries = count($survey_visits);
					$us_total_entries = $us_survey_visits;
					$gb_total_entries = $gb_survey_visits;
					$ca_total_entries = $ca_survey_visits;
					$total_completes = $total_clicks = $total_nqs = $total_oqs = 0;
					$us_total_completes = $us_total_clicks = $us_total_nqs = $us_total_oqs = 0;
					$gb_total_completes = $gb_total_clicks = $gb_total_nqs = $gb_total_oqs = 0;
					$ca_total_completes = $ca_total_clicks = $ca_total_nqs = $ca_total_oqs = 0;
					$total_earnings = $us_total_earnings = $gb_total_earnings = $ca_total_earnings = 0;
					foreach ($survey_visits as $survey_visit) {
						if ($survey_visit['SurveyVisit']['result'] == SURVEY_COMPLETED) {
							$total_completes++;
							$total_earnings = $total_earnings + $project_earnings[$survey_visit['SurveyVisit']['survey_id']];
							
							if (isset($us_project_ids[$survey_visit['SurveyVisit']['survey_id']])) {
								$us_total_completes++; 
								$us_total_earnings = $us_total_earnings + $project_earnings[$survey_visit['SurveyVisit']['survey_id']];
							}
							elseif (isset($gb_project_ids[$survey_visit['SurveyVisit']['survey_id']])) {
								$gb_total_completes++; 
								$gb_total_earnings = $gb_total_earnings + $project_earnings[$survey_visit['SurveyVisit']['survey_id']];
							}
							elseif (isset($ca_project_ids[$survey_visit['SurveyVisit']['survey_id']])) {
								$ca_total_completes++; 
								$ca_total_earnings = $ca_total_earnings + $project_earnings[$survey_visit['SurveyVisit']['survey_id']];
							}
							 
						}
						elseif ($survey_visit['SurveyVisit']['result'] == SURVEY_NQ) {
							$total_nqs++;
							if (isset($us_project_ids[$survey_visit['SurveyVisit']['survey_id']])) {
								$us_total_nqs++;
							}
							elseif (isset($gb_project_ids[$survey_visit['SurveyVisit']['survey_id']])) {
								$gb_total_nqs++;
							}
							elseif (isset($ca_project_ids[$survey_visit['SurveyVisit']['survey_id']])) {
								$ca_total_nqs++;
							}
						}
						elseif ($survey_visit['SurveyVisit']['result'] == SURVEY_OVERQUOTA) {
							$total_oqs++;
							if (isset($us_project_ids[$survey_visit['SurveyVisit']['survey_id']])) {
								$us_total_oqs++;
							}
							elseif (isset($gb_project_ids[$survey_visit['SurveyVisit']['survey_id']])) {
								$gb_total_oqs++;
							}
							elseif (isset($ca_project_ids[$survey_visit['SurveyVisit']['survey_id']])) {
								$ca_total_oqs++;
							}
						}
						if ($export_to_csv) {
							$csv_rows[] = array(
								$survey_visit['SurveyVisit']['survey_id'],
								$masks[$survey_visit['SurveyVisit']['survey_id']],
								$survey_visit['SurveyVisit']['created'],
								$project_earnings[$survey_visit['SurveyVisit']['survey_id']],
								$survey_visit['SurveyVisit']['hash']
							);
						}
						
						$save_csv_rows[$partner][] = array(
							'project_id' => $survey_visit['SurveyVisit']['survey_id'],
							'mask_id' => $masks[$survey_visit['SurveyVisit']['survey_id']],
							'date' => $survey_visit['SurveyVisit']['created'],
							'cpi' => $project_earnings[$survey_visit['SurveyVisit']['survey_id']],
							'session_id' => $survey_visit['SurveyVisit']['hash']
						);
					}
					
					$epc = round($total_earnings / $total_entries, 2);
					$us_epc = ($us_total_earnings > 0) ? round($us_total_earnings / $us_total_entries, 2) : 0;
					$gb_epc = ($gb_total_earnings > 0) ? round($gb_total_earnings / $gb_total_entries, 2) : 0;
					$ca_epc = ($ca_total_earnings > 0) ? round($ca_total_earnings / $ca_total_entries, 2) : 0;
					$rows[$partner][0] = array(
						$total_entries,
						$total_completes,
						$total_earnings,
						$epc,
						$invite_count,
						$project_count,
						$unique_panelists,
						$total_oqs,
						$total_nqs,
						''
					);
					if (empty($compare_date)) {
						if (!empty($us_project_ids) && !empty($us_survey_visits)) {
							$rows[$partner][] = array(
								$us_total_entries,
								$us_total_completes,
								$us_total_earnings,
								$us_epc,
								$us_invite_count,
								$us_project_count,
								$us_unique_panelists,
								$us_total_oqs,
								$us_total_nqs,
								'US'
							);
						}
						if (!empty($gb_project_ids) && !empty($gb_survey_visits)) {
							$rows[$partner][] = array(
								$gb_total_entries,
								$gb_total_completes,
								$gb_total_earnings,
								$gb_epc,
								$gb_invite_count,
								$gb_project_count,
								$gb_unique_panelists,
								$gb_total_oqs,
								$gb_total_nqs,
								'GB'
							);
						}
						if (!empty($ca_project_ids) && !empty($ca_survey_visits)) {
							$rows[$partner][] = array(
								$ca_total_entries,
								$ca_total_completes,
								$ca_total_earnings,
								$ca_epc,
								$ca_invite_count,
								$ca_project_count,
								$ca_unique_panelists,
								$ca_total_oqs,
								$ca_total_nqs,
								'CA'
							);
						}
					}
					
					if ($partner == 'ssi' || $partner == 'p2s') {
						$us_router_log_sum = $this->RouterLog->find('list', array(
							'fields' => array('RouterLog.payout'),
							'conditions' => array(
								'RouterLog.survey_id' => $us_project_ids,
								'RouterLog.created >' => $date . ' 00:00:00',
								'RouterLog.created <' => $date . ' ' . $time,
								'RouterLog.type' => 'success',
								'RouterLog.source' => $partner,
								'RouterLog.duplicate' => false
							)
						));
						$gb_router_log_sum = $this->RouterLog->find('list', array(
							'fields' => array('RouterLog.payout'),
							'conditions' => array(
								'RouterLog.survey_id' => $gb_project_ids,
								'RouterLog.created >' => $date . ' 00:00:00',
								'RouterLog.created <' => $date . ' ' . $time,
								'RouterLog.type' => 'success',
								'RouterLog.source' => $partner,
								'RouterLog.duplicate' => false
							)
						));
						$ca_router_log_sum = $this->RouterLog->find('list', array(
							'fields' => array('RouterLog.payout'),
							'conditions' => array(
								'RouterLog.survey_id' => $ca_project_ids,
								'RouterLog.created >' => $date . ' 00:00:00',
								'RouterLog.created <' => $date . ' ' . $time,
								'RouterLog.type' => 'success',
								'RouterLog.source' => $partner,
								'RouterLog.duplicate' => false
							)
						));

						$router_log_sum = $this->RouterLog->find('list', array(
							'fields' => array('RouterLog.payout'),
							'conditions' => array(
								'RouterLog.created >' => $date.' 00:00:00',
								'RouterLog.created <' => $date.' '.$time,
								'RouterLog.type' => 'success',
								'RouterLog.source' => $partner,
								'RouterLog.duplicate' => false
							)
						));
						foreach ($rows[$partner] as $key => $row) {
							if ($row[9] === '') {
								$rows[$partner][$key][1] = count($router_log_sum); // total completes
								$rows[$partner][$key][2] = round(array_sum($router_log_sum) / 100, 2); // total earnings
								$rows[$partner][$key][3] = round($rows[$partner][$key][2] / $total_entries, 2); // epc
							}
							
							elseif ($row[9] === 'US' && !empty($us_router_log_sum)) {
								$rows[$partner][$key][1] = count($us_router_log_sum); // total completes
								$rows[$partner][$key][2] = round(array_sum($us_router_log_sum) / 100, 2); // total earnings
								$rows[$partner][$key][3] = round($rows[$partner][$key][2] / $us_total_entries, 2); // epc
							}
							
							elseif ($row[9] === 'GB' && !empty($gb_router_log_sum)) {
								$rows[$partner][$key][1] = count($gb_router_log_sum); // total completes
								$rows[$partner][$key][2] = round(array_sum($gb_router_log_sum) / 100, 2); // total earnings
								$rows[$partner][$key][3] = round($rows[$partner][$key][2] / $gb_total_entries, 2); // epc
							}
							
							elseif ($row[9] === 'CA' && !empty($ca_router_log_sum)) {
								$rows[$partner][$key][1] = count($ca_router_log_sum); // total completes
								$rows[$partner][$key][2] = round(array_sum($ca_router_log_sum) / 100, 2); // total earnings
								$rows[$partner][$key][3] = round($rows[$partner][$key][2] / $ca_total_entries, 2); // epc
							}
							if ($rows[$partner][$key][1] == 0) {
								unset($rows[$partner][$key]);
							}
						}
					}
				}
				// launched project data
				if ($generate_launched_data) {
					// find total projects created, find total launched
					$this->Project->unbindModel(array(
						'hasMany' => array('SurveyPartner', 'ProjectOption'),
						'belongsTo' => array('Group', 'Client'),
						'hasOne' => array('SurveyVisitCache')
					));
					if ($partner == 'adhoc') {
						$conditions = array(
							'AND' => array(
								'Project.group_id' => null
							),
							'Project.date_created >' => $start_date.' 00:00:00',
							'Project.date_created <' => $end_date,
						);
					}
					else {
						$conditions = array(
							'Project.group_id' => $group['Group']['id'],
							'Project.date_created >' => $start_date.' 00:00:00',
							'Project.date_created <' => $end_date,
						);
					}
					$projects = $this->Project->find('all', array(
						'fields' => array(
							'Project.id',
							'Project.started',
							'Project.date_created'
						),
						'conditions' => $conditions
					));
					$projects_imported = count($projects);
					$projects_launched = 0;
					if ($projects) {
						foreach ($projects as $project) {
							if (!empty($project['Project']['started'])) {
								$projects_launched++;
							}
						}
					}
					$launched_rows[$partner] = array(
						$projects_imported,
						$projects_launched
					);
				}
			}
		}

		// usurv has a completely different mechanism for calculating rates
		if (isset($rows['usurv']) && !in_array('usurv', $cached_partners)) {
			// update index 2 (total earnings) and 3 (epc)
			App::import('Model', 'UsurvVisit');
			$this->UsurvVisit = new UsurvVisit;

			$usurv_visits = $this->UsurvVisit->find('list', array(
				'fields' => array('UsurvVisit.id', 'UsurvVisit.client_rate'),
				'conditions' => array(
					'UsurvVisit.created >' => $start_date.' 00:00:00',
					'UsurvVisit.created <' => $end_date.' 23:59:59',
					'UsurvVisit.client_rate is not null'
				)
			));
			$earnings = 0; // client_rate is already converted to USD
			if (!empty($usurv_visits)) {
				foreach ($rows['usurv'] as $key => $row) {
					$earnings = array_sum($usurv_visits);
					$rows['usurv'][$key][2] = $earnings;
					$rows['usurv'][$key][3] = $earnings / $rows['usurv'][$key][0];
				}
			}
		}

		if (isset($this->data['Report']['offers']) && $this->data['Report']['offers'] == '1') {
			$offer_partners = $this->__get_partners();
			$this->OfferRedemption->virtualFields = array(
				'created_date' => 'DATE(OfferRedemption.created)',
			);

			$cached_partner_check = $cached_offer_partners = array();
			if ($past_report) {
				$cached_offer_redemptions = $this->OfferRedemptionReport->find('all', array(
					'conditions' => array(
						'OfferRedemptionReport.date' => date(DB_DATE, strtotime($date))
					)
				));
				if ($cached_offer_redemptions) {
					foreach ($cached_offer_redemptions as $cached_offer_redemption) {
						$cached_offer_partners[$cached_offer_redemption['OfferRedemptionReport']['partner']] = $cached_offer_redemption['OfferRedemptionReport']['partner'];
					}
					$cached_partner_check = array('OfferRedemption.partner != ' => $cached_offer_partners);
				}
			}
			$offer_redemptions = $this->OfferRedemption->find('all', array(
				'recursive' => -1,
				'conditions' => array(
					'OfferRedemption.created >=' => $start_date . ' 00:00:00',
					'OfferRedemption.created <=' => $end_date . ' 23:59:59',
					'OfferRedemption.status' => OFFER_REDEMPTION_ACCEPTED,
					$cached_partner_check
				),
				'order' => 'OfferRedemption.id DESC',
				'fields' => array(
					'OfferRedemption.partner',
					'created_date',
					'OfferRedemption.revenue',
				)
			));

			$partner_revenues = array();
			if (!empty($offer_redemptions)) {
				foreach ($offer_redemptions as $offer_redemption) {
					$created_date = $offer_redemption['OfferRedemption']['created_date'];
					$partner = $offer_redemption['OfferRedemption']['partner'];
					$revenue = !empty($offer_redemption['OfferRedemption']['revenue']) ? $offer_redemption['OfferRedemption']['revenue'] : 0;

					if (isset($partner_revenues[$created_date][$partner])) {
						$partner_revenues[$created_date][$partner] += $revenue;
					}
					else {
						$partner_revenues[$created_date][$partner] = $revenue;
					}
				}
			}

			if ($past_report) {
				$report_date = date(DB_DATE, strtotime($date));
				foreach ($partner_revenues as $created_date => $partner_revenue) {
					foreach ($partner_revenue as $partner => $revenue) {
						$offer_redemption_data = array(
							'partner' => $partner,
							'date' => $report_date,
							'revenue' => $revenue
						);

						$this->OfferRedemptionReport->create();
						$this->OfferRedemptionReport->save(array('OfferRedemptionReport' => $offer_redemption_data));
					}
				}
			}
			if ($past_report && !empty($cached_offer_redemptions)) {
				foreach ($cached_offer_redemptions as $cached_offer_redemption) {
					$partner_revenues[$cached_offer_redemption['OfferRedemptionReport']['date']][$cached_offer_redemption['OfferRedemptionReport']['partner']] = $cached_offer_redemption['OfferRedemptionReport']['revenue'];
				}
			}

			$line_totals = array();
			foreach ($partner_revenues as $created_date => $partner_revenue) {
				$line_totals[$created_date] = array_sum($partner_revenue);
			}
			$grand_total = array_sum($line_totals);
			$this->set(compact('offer_partners', 'partner_revenues', 'line_totals', 'grand_total'));
		}
		$time_end = microtime(true);
		$diff = $time_end - $time_start;
		
		if ($past_report) {
			$report_date = date(DB_DATE, strtotime($date));
			foreach ($rows as $partner => $data_rows) {
				foreach ($data_rows as $row) {
					$statistics_data = array(
						'partner' => $partner,
						'date' => $report_date,
						'total_entries' => $row[0],
						'total_completes' => $row[1],
						'total_earnings' => $row[2],
						'epc' => $row[3],
						'invite_count' => $row[4],
						'project_count' => $row[5],
						'unique_panelists' => $row[6],
						'total_oqs' => $row[7],
						'total_nqs' => $row[8],
						'country' => isset($row[9]) ? $row[9] : ''
					);
					if (!empty($launched_rows[$partner])) {
						$statistics_data = array_merge($statistics_data, array(
							'projects_imported' => $launched_rows[$partner][0],
							'projects_launched' => $launched_rows[$partner][1]
						));
					}
					if (isset($save_csv_rows[$partner]) && !empty($save_csv_rows[$partner])) {
						$statistics_data = array_merge($statistics_data, array(
							'csv' => json_encode($save_csv_rows[$partner])
						));
					}

					$this->GroupPerformanceReport->create();
					$this->GroupPerformanceReport->save(array('GroupPerformanceReport' => $statistics_data));
				}
			}
		}
		
		if (!empty($cached_rows)) {
			$rows = $rows + $cached_rows;
		}
		if (!empty($cached_launched_rows)) {
			$launched_rows = $launched_rows + $cached_launched_rows;
		}
		
		return compact('rows', 'launched_rows', 'start_date', 'end_date', 'diff', 'csv_rows');
	}

	public function statistics() {
		if ($this->request->is('post')) {
			$export_to_csv = isset($this->request->data['Report']['export']) && $this->request->data['Report']['export'] == 1;

			$do_compare = (isset($this->data['Report']['use_compare_date']) && $this->data['Report']['use_compare_date'] == 1) ? true : false;
			
			$flush_cache_data = (isset($this->data['Report']['flush_cache_data']) && $this->data['Report']['flush_cache_data'] == 1) ? true : false;

			$report_date = $this->data['Report']['report_date']['year'].'-'. 
				$this->data['Report']['report_date']['month'].'-'.
				$this->data['Report']['report_date']['day'];
			$time = '23:59:59';
			if ($report_date === date('Y-m-d')) {
				$time = date('H:i:s');
			}

			$compare_date = null;
			if ($do_compare) {
				$compare_time = '23:59:59';
				$compare_date = $this->data['Report']['compare_date']['year'].'-'.
					$this->data['Report']['compare_date']['month'].'-'.
					$this->data['Report']['compare_date']['day'];
				
				if ($report_date === date('Y-m-d')) {
					$compare_time = date('H:i:s');
				}
				$compare_data = $this->getStatisticData($compare_date, $compare_time, $export_to_csv, $report_date, $flush_cache_data);
				$compare_data_rows = $compare_data['rows'];
				$compare_launched_rows = $compare_data['launched_rows'];
			}
			
			$report_data = $this->getStatisticData($report_date, $time, $export_to_csv, $compare_date, $flush_cache_data);

			$rows = $report_data['rows'];
			$launched_rows = $report_data['launched_rows'];
			if ($export_to_csv) { 
				$filename = 'perf_group_export-'.$report_date.'.csv';
				$csv_file = fopen('php://output', 'w');
				$csv_rows = $report_data['csv_rows'];
				$start_csv_rows = $report_data['csv_rows'];

				//We should mash the two sets together first
				$csv_rows = array();
				foreach ($start_csv_rows as $partner => $data) {
					if ($partner == 0) {
						$csv_rows[] = $data;
						continue;
					}
					$csv_rows[] = $data; 
				}

				// Let's prep the csv file
				header('Content-type: application/csv');
				header('Content-Disposition: attachment; filename="'.$filename.'"');

				// Each iteration of this while loop will be a row in your .csv file where each field corresponds to the heading of the column
				foreach ($csv_rows as $row) {
					fputcsv($csv_file, $row, ',', '"');
				}

				fclose($csv_file);
				$this->autoRender = false;
				$this->layout = false;
				$this->render(false);
				return false;
			}

			$this->set(compact('do_compare', 'rows', 'launched_rows', 'report_date', 'diff'));
			if ($do_compare) {
				$this->set(compact('do_compare', 'rows', 'compare_data_rows', 'launched_rows', 'compare_launched_rows', 'report_date', 'compare_date', 'diff'));
			}

			$this->render('statistics_data');
		}
	}
	
	public function fulcrum_oqs() {
		if (isset($this->request->query['project'])) {
			$project_id = MintVine::parse_project_id($this->request->query['project']);
			$survey_visits = $this->SurveyVisit->find('all', array(
				'conditions' => array(
					'SurveyVisit.survey_id' => $project_id,
					'SurveyVisit.type' => array(SURVEY_OVERQUOTA, SURVEY_OQ_INTERNAL),
				),
				'fields' => array('SurveyVisit.partner_user_id', 'created'),
			));
			if (!$survey_visits) {
				$this->Session->setFlash('No overquotas on this project.', 'flash_error');
				$this->redirect(array('action' => 'fulcrum_oqs'));
			}
			
			$rows = array();
			foreach ($survey_visits as $survey_visit) {
				list($project_id, $user_id) = explode('-', $survey_visit['SurveyVisit']['partner_user_id']);
				$survey_user = $this->SurveyUser->find('first', array(
					'conditions' => array(
						'SurveyUser.user_id' => $user_id,
						'SurveyUser.survey_id' => $project_id
					),
					'recursive' => -1,
					'fields' => array('id')
				));
				$this->SurveyUserQuery->bindModel(array('belongsTo' => array('QueryHistory')));
				$survey_user_queries = $this->SurveyUserQuery->find('all', array(
					'conditions' => array(
						'SurveyUserQuery.survey_user_id' => $survey_user['SurveyUser']['id'],
					),
					'fields' => array('SurveyUserQuery.*', 'QueryHistory.query_id')
				));
				if ($survey_user_queries) {
					foreach ($survey_user_queries as $survey_user_query) {
						$query_statistic = $this->QueryStatistic->find('first', array(
							'conditions' => array(
								'QueryStatistic.query_id' => $survey_user_query['QueryHistory']['query_id']
							)
						));
						if ($query_statistic) {
							$rows[] = array(
								$user_id, 
								$survey_visit['SurveyVisit']['created'],
								$survey_user_query['QueryHistory']['query_id'], 
								is_null($query_statistic['QueryStatistic']['quota']) ? 'No quota': $query_statistic['QueryStatistic']['quota'],
								$query_statistic['QueryStatistic']['modified']
							);
						}
						else {
							$rows[] = array(
								$user_id, 
								$survey_visit['SurveyVisit']['created'],
								$survey_user_query['QueryHistory']['query_id'], 
								'Master Query',
								'---'
							);
						}
					}
				}
			}
			$this->set(compact('rows'));
		}
	}
	
	public function socialglimpz() {
		App::import('Model', 'SocialglimpzRespondent');
		$this->SocialglimpzRespondent = new SocialglimpzRespondent;
		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'socialglimpz'
			)
		));
		if ($this->request->is('post')) {
			$project_id = $this->request->data['Report']['project_id'];
			if ($project_id{0} == '#') {
				$conditions = array(
					'Project.id' => substr($project_id, 1),
					'Project.group_id' => $group['Group']['id'],
				);
			}
			else {
				$conditions = array(
					'Project.mask' => $project_id,
					'Project.group_id' => $group['Group']['id'],
				);
			}
			$project = $this->Project->find('first', array(
				'conditions' => $conditions
			));
			if ($project) {
				if (!$this->Admins->can_access_project($this->current_user, $project)) {
					$this->Session->setFlash('You are not authorized to access this report.', 'flash_error');
					$this->redirect(array('action' => 'index'));
				}
				
				$this->SocialglimpzRespondent->bindModel(array('belongsTo' => array(
					'Transaction' => array(
						'className' => 'Transaction',
						'foreignKey' => 'transaction_id'
					)
				))); 
				$socialglimpz_respondents = $this->SocialglimpzRespondent->find('all', array(
					'conditions' => array(
						'SocialglimpzRespondent.survey_id' => $project['Project']['id']
					),
				));
				if ($socialglimpz_respondents) {					
					$data = array(array(
						'Respondent ID',
						'IP', 
						'Status', 
						'Reject Timestamp (GMT)', 
						'Transaction ID',
						'User ID',
						'Created (GMT)',
						'Modified (GMT)'
					));
					foreach ($socialglimpz_respondents as $socialglimpz_respondent) {
						$data[] = array(
							$socialglimpz_respondent['SocialglimpzRespondent']['respondent_id'],
							$socialglimpz_respondent['SocialglimpzRespondent']['ip'],
							$socialglimpz_respondent['SocialglimpzRespondent']['status'],
							$socialglimpz_respondent['SocialglimpzRespondent']['reject_timestamp'],
							$socialglimpz_respondent['SocialglimpzRespondent']['transaction_id'],
							$socialglimpz_respondent['Transaction']['user_id'],
							$socialglimpz_respondent['SocialglimpzRespondent']['created'],
							$socialglimpz_respondent['SocialglimpzRespondent']['modified']
						);
					}
   			 		$filename = 'socialglimpz-'.$project['Project']['id'].'-'. gmdate(DB_DATE, time()) . '.csv';
			  		$csv_file = fopen('php://output', 'w');

					header('Content-type: application/csv');
					header('Content-Disposition: attachment; filename="' . $filename . '"');

					// Each iteration of this while loop will be a row in your .csv file where each field corresponds to the heading of the column
					foreach ($data as $row) {
						fputcsv($csv_file, $row, ',', '"');
					}

					fclose($csv_file);
					$this->autoRender = false;
					$this->layout = false;
					$this->render(false);
				}
				else {
					$this->Session->setFlash('No respondent data has been received from SocialGlimpz for this project.', 'flash_error');
				}
			}
			else {
				$this->Session->setFlash('That project could not be found', 'flash_error');
			}
		}
	}
	
	public function check($report_id) {
		$report = $this->Report->find('first', array(
			'fields' => array('Report.id', 'Report.status'),
			'conditions' => array(
				'Report.id' => $report_id
			),
			'recursive' => -1
		)); 
		// sometimes when we wipe reports, people keep their browsers open for hours
		if (!$report) {
	    	return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => 'complete',
					'file' => '#'
				)), 
				'type' => 'json',
				'status' => '201'
			));
		}
    	return new CakeResponse(array(
			'body' => json_encode(array(
				'status' => $report['Report']['status'],
				'file' => Router::url(array('controller' => 'reports', 'action' => 'download', $report['Report']['id']))
			)), 
			'type' => 'json',
			'status' => '201'
		));
	}
	
	public function ajax_partners($project_id) {		
		$project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $project_id
			),
			'contain' => array(
				'SurveyPartner' => array('Partner'),
				'ProjectAdmin'
			)
		));
		if (!$this->Admins->can_access_project($this->current_user, $project)) {
			return new CakeResponse(array('status' => '401'));
		}
		
		$partners = array();
		if ($project) {
			if (!empty($project['SurveyPartner'])) {
				foreach ($project['SurveyPartner'] as $partner) {
					$partners[$partner['partner_id']] = $partner['Partner']['partner_name']; 
				}
			}
		}
		$this->set(compact('partners'));
		$this->RequestHandler->respondAs('application/json'); 
		$this->response->statusCode('200');
		$this->layout = '';
	}
	
	public function router($project_id) {
		
		$logs = $this->RouterLog->find('all', array(
			'conditions' => array(
				'RouterLog.survey_id' => $project_id
			)
		));
		if (!$logs) {
			$this->Session->setFlash('There are no pingbacks yet.', 'flash_error');
			$this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $project_id));
		}
		$data = array(array(
			'Type',
			'Source', 
			'Timestamp', 
			'Hash', 
			'User ID',
			'Commission (in cents)'
		));
		foreach ($logs as $log) {
			$data[] = array(
				$log['RouterLog']['type'],
				$log['RouterLog']['source'],
				$log['RouterLog']['created'],
				$log['RouterLog']['hash'],
				$log['RouterLog']['user_id'],
				$log['RouterLog']['payout']
			);
		}
		
    	$filename = 'router-' . gmdate(DB_DATE, time()) . '.csv';
  		$csv_file = fopen('php://output', 'w');

		header('Content-type: application/csv');
		header('Content-Disposition: attachment; filename="' . $filename . '"');

		// Each iteration of this while loop will be a row in your .csv file where each field corresponds to the heading of the column
		foreach ($data as $row) {
			fputcsv($csv_file, $row, ',', '"');
		}

		fclose($csv_file);
		$this->autoRender = false;
		$this->layout = false;
		$this->render(false);
	}
	
	public function user_revenue() {
		if ($this->request->is('post') || $this->request->is('put')) {
			ini_set('memory_limit', '2048M');
			
			$this->User->bindModel(array('hasOne' => array(
				'UserRevenue' => array(
					'foreignKey' => 'user_id'
				)
			)));
			$users = $this->User->find('all', array(
				'fields' => array('id', 'hellbanned', 'origin', 'created', 'verified', 'last_touched', 'UserRevenue.*'),
				'conditions' => array(
					'User.active' => true,
					'User.deleted_on' => null
				),
				'contain' => array(
					'UserRevenue'
				),
				'order' => 'User.id asc',
			));
			
			$data = array();
			$data[] = array(
				'User ID', 
				'Hellbanned', 
				'Origin',
				'Joined', 
				'Last Activity', 
				'30 Days:', 
				'Revenue', 
				'Cost',
				'Net', 
				'60 Days:', 
				'Revenue', 
				'Cost', 
				'Net', 
				'90 Days:', 
				'Revenue', 
				'Cost', 
				'Net', 
				'Lifetime:', 
				'Revenue', 
				'Cost', 
				'Net', 
				'Referrals Net Sum',
				'Acquisition Cost',
				'',
				'Payouts', 
				'Left to payout'
			);
			$ignore_hellban = isset($this->data['Report']['hellban']) && $this->data['Report']['hellban'] == 1;
			$ignore_net_zero = isset($this->data['Report']['net']) && $this->data['Report']['net'] == 1;
			$verifiedinactive = isset($this->data['Report']['verifiedinactive']) && $this->data['Report']['net'] == 1;
			$payout_filter = isset($this->data['Report']['payout']) && $this->data['Report']['payout'] == 1;
			
			foreach ($users as $user) {
				if ($ignore_hellban && $user['User']['hellbanned']) {
					continue;
				}
				if ($payout_filter && empty($user['UserRevenue']['lifetime_payout'])) {
					continue;
				}
				// figure out how to bucketize the acquisition cost
				if (!empty($user['UserRevenue']['acquisition_cost'])) {
					$registration_diff = (time() - strtotime($user['User']['verified']) / 86400);
					$user['UserRevenue']['lifetime_cost'] = $user['UserRevenue']['lifetime_cost'] + $user['UserRevenue']['acquisition_cost'];
					if ($registration_diff <= 30) {
						$user['UserRevenue']['thirty_cost'] = $user['UserRevenue']['thirty_cost'] + $user['UserRevenue']['acquisition_cost'];
					}
					if ($registration_diff <= 60) {
						$user['UserRevenue']['sixty_cost'] = $user['UserRevenue']['sixty_cost'] + $user['UserRevenue']['acquisition_cost'];
					}
					if ($registration_diff <= 90) {
						$user['UserRevenue']['ninety_cost'] = $user['UserRevenue']['ninety_cost'] + $user['UserRevenue']['acquisition_cost'];
					}
				}
				
				$thirty_revenue = $user['UserRevenue']['thirty_revenue'];
				$thirty_cost = $user['UserRevenue']['thirty_cost'];
				$thirty_diff = $thirty_revenue - $thirty_cost;
				$thirty_net = $thirty_diff != 0 ? $thirty_diff: '';
			
				$sixty_revenue = $user['UserRevenue']['sixty_revenue'];
				$sixty_cost = $user['UserRevenue']['sixty_cost'];
				$sixty_diff = $sixty_revenue - $sixty_cost;
				$sixty_net = $sixty_diff != 0 ? $sixty_diff: '';
			
				$ninety_revenue = $user['UserRevenue']['ninety_revenue'];
				$ninety_cost = $user['UserRevenue']['ninety_cost'];
				$ninety_diff = $ninety_revenue - $ninety_cost;
				$ninety_net = $ninety_diff != 0 ? $ninety_diff: '';
			
				$lifetime_revenue = $user['UserRevenue']['lifetime_revenue'];
				$lifetime_cost = $user['UserRevenue']['lifetime_cost'];
				$lifetime_diff = $lifetime_revenue - $lifetime_cost;
				$lifetime_net = $lifetime_diff != 0 ? $lifetime_diff: '';
			
				$referral_net = $user['UserRevenue']['referral_net'];			
				$lifetime_liability = $user['UserRevenue']['lifetime_cost'] - $user['UserRevenue']['lifetime_payout'];
				
				if ($ignore_net_zero && empty($user['UserRevenue']['acquisition_cost']) && $lifetime_cost < 1000) {
					$diff = round((time() - strtotime($user['User']['last_touched'])) / 86400);
					if ($diff > 30) {
						continue;
					}
				}
				if ($verifiedinactive && ($user['UserRevenue']['acquisition_cost'] == $lifetime_cost || ($user['UserRevenue']['acquisition_cost'] + 200 == $lifetime_cost))) {
					continue;
				}
				
				$data[] = array(
					$user['User']['id'],
					$user['User']['hellbanned'] ? 'Y': '',
					$user['User']['origin'],
					date(DB_DATE, strtotime($user['User']['verified'])),
					!empty($user['User']['last_touched']) ? date(DB_DATE, strtotime($user['User']['last_touched'])): '',
					'', 
					Utils::dollarize_points($thirty_revenue),
					Utils::dollarize_points($thirty_cost),
					Utils::dollarize_points($thirty_net),
					'',
					Utils::dollarize_points($sixty_revenue),
					Utils::dollarize_points($sixty_cost),
					Utils::dollarize_points($sixty_net),
					'',
					Utils::dollarize_points($ninety_revenue),
					Utils::dollarize_points($ninety_cost),
					Utils::dollarize_points($ninety_net),
					'',
					Utils::dollarize_points($lifetime_revenue),
					Utils::dollarize_points($lifetime_cost),
					Utils::dollarize_points($lifetime_net),		
					Utils::dollarize_points($referral_net),
					Utils::dollarize_points($user['UserRevenue']['acquisition_cost']),
					'',
					Utils::dollarize_points($user['UserRevenue']['lifetime_payout']),
					Utils::dollarize_points($lifetime_liability)
				);
			}
				
		
	    	$filename = 'user_revenue_report-' . gmdate(DB_DATE, time()) . '.csv';
	  		$csv_file = fopen('php://output', 'w');

			header('Content-type: application/csv');
			header('Content-Disposition: attachment; filename="' . $filename . '"');

			// Each iteration of this while loop will be a row in your .csv file where each field corresponds to the heading of the column
			foreach ($data as $row) {
				fputcsv($csv_file, $row, ',', '"');
			}

			fclose($csv_file);
			$this->autoRender = false;
			$this->layout = false;
			$this->render(false);
		}
		
		$modified = $this->UserRevenue->find('first', array(
			'fields' => array(
				'MAX(modified) as modified'
			)
		));
		$modified = $modified[0]['modified'];
		$this->set(compact('modified'));
	}
	
	public function generate() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$project_id = MintVine::parse_project_id($this->data['Report']['project']);
			$project = $this->Project->find('first', array(
				'fields' => array('Project.id'),
				'conditions' => array(
					'Project.id' => $project_id
				),
				'recursive' => -1
			));
			if (!$project) {
				$this->Session->setFlash('Project not found!', 'flash_error');
				$this->redirect(array('controller' => 'projects', 'action' => 'index'));
			}
			
			if (!$this->Admins->can_access_project($this->current_user, $project_id)) {
				$this->Session->setFlash('You are not authorized to generate this report.', 'flash_error');
				$this->redirect(array('action' => 'index'));
			}			
			// first see if a report exists
			$conditions = array(
				'Report.survey_id' => $project_id, 
				'Report.type' => 'report'
			);
			if (!empty($this->data['Report']['partner_id'])) {
				$conditions['Report.partner_id'] = $this->data['Report']['partner_id'];
				$partner_id = $this->data['Report']['partner_id']; 
			}
			else {
				$conditions[] = 'Report.partner_id is null';
				$partner_id = 0;
			}
			$report = $this->Report->find('first', array(
				'conditions' => $conditions,
				'recursive' => -1
			));
			$do = true;
					
			if ($report) {
				$report_id = $report['Report']['id'];
			
				// update the custom hashes to use
				if (isset($this->data['Report']['hashes'])) {
					$this->Report->save(array('Report' => array(
						'id' => $report['Report']['id'],
						'hashes' => $this->data['Report']['hashes']
					)), true, array('hashes'));
				}
			
				if ($report['Report']['status'] == 'queued') {
					if (time() - strtotime($report['Report']['created']) <= 600) {
						$do = false;
						$this->Session->setFlash('A report is already being generated - please wait until it is done.', 'flash_error');
						$this->redirect(array('controller' => 'reports', 'action' => 'index'));
					}
				}
				elseif ($report['Report']['status'] == 'complete') {
					$do = false; // completed reports should not be regenerated, except when there is new data				
					$conditions = array(
						'SurveyVisit.survey_id' => $this->data['Report']['project']
					);
					if (!empty($partner_id)) {
						$conditions['SurveyVisit.partner_id'] = $partner_id;
					}
					$last_transaction = $this->SurveyVisit->find('first', array(
						'fields' => array('max(id) as id'), 
						'conditions' => $conditions 
					));
					
					if ($last_transaction) {
						if ($last_transaction[0]['id'] != $report['Report']['last_id']) {
							$do = true;
							$this->Report->create();
							$this->Report->save(array('Report' => array(
								'id' => $report['Report']['id'],
								'status' => 'queued',
								'path' => null,
								'last_transaction' => null,
								'last_id' => null
							)));
						}
					}
					if (!$do) {
						$this->Session->setFlash('The report is ready: <a href="'.Router::url(array('controller' => 'reports', 'action' => 'download', $report['Report']['id'])).'" target="_blank" class="btn btn-sm btn-primary">Download it here</a>', 'flash_success'); 
						$this->redirect(array('action' => 'index')); 
					}
				}
			}
			else {
				$reportSource = $this->Report->getDataSource();
				$reportSource->begin();
				$this->Report->create();
				$this->Report->save(array('Report' => array(
					'user_id' => $this->current_user['Admin']['id'],
					'survey_id' => $this->data['Report']['project'],
					'partner_id' => $partner_id,
					'hashes' => $this->data['Report']['hashes']
				)));
				$report_id = $this->Report->getInsertId();
				$reportSource->commit();
			}
			
			if ($do) {				
				$query = ROOT.'/app/Console/cake report survey '.$this->data['Report']['project'].' '.$partner_id.' '.$report_id;
				$query.= " > /dev/null &"; 
				exec($query, $output);
				CakeLog::write('report_commands', $query);
				
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $project_id,
					'user_id' => $this->current_user['Admin']['id'],
					'type' => 'report.generated',
					'report_id' => $report_id
				)));
			
				$this->Session->setFlash('We are generating your report - check the status below.', 'flash_success');
				$this->redirect(array('controller' => 'reports', 'action' => 'index'));
			}	
		}
		$partners = $this->Partner->find('list', array(
			'fields' => array('id', 'partner_name'),
			'order' => 'Partner.partner_name ASC',
			'conditions' => array(
				'Partner.deleted' => false
			)
		));
		$this->set('partners', $partners);
	}
	
	public function raw() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$project = $this->Project->findById($this->data['Report']['project']);
			if (!$project) {
				$this->Session->setFlash('Project not found!', 'flash_error');
				$this->redirect(array('controller' => 'projects', 'action' => 'index'));
			}
			
			if (!empty($project['Project']['ended']) && $project['Project']['ended'] < date(DB_DATETIME, strtotime('-8 month'))) {
				$this->Session->setFlash('This project has been closed for longer then 8 months, it needs a manual data pull!', 'flash_error');
				$this->redirect(array('action' => 'raw'));
			}
			
			if (!$this->Admins->can_access_project($this->current_user, $project)) {
				$this->Session->setFlash('You are not authorized to access this report.', 'flash_error');
				$this->redirect(array('action' => 'index'));
			}
			
			// first see if a report exists
			$conditions = array('Report.survey_id' => $this->data['Report']['project'], 'Report.type' => 'raw');
			if (!empty($this->data['Report']['partner_id'])) {
				$conditions['Report.partner_id'] = $this->data['Report']['partner_id'];
				$partner_id = $this->data['Report']['partner_id']; 
			}
			else {
				$partner_id = 0;
			}
			
			$report = $this->Report->find('first', array(
				'conditions' => $conditions
			));
			$do = true;
						
			if ($report) {
				$report_id = $report['Report']['id'];
				
				if ($report['Report']['status'] == 'queued') {
					if (time() - strtotime($report['Report']['created']) <= 600) {
						$do = false;
						$this->Session->setFlash('A report is already being generated - please wait until it is done.', 'flash_error');
						$this->redirect(array('controller' => 'reports', 'action' => 'index', '?' => array('type' => 'raw')));
					}
				}
				elseif ($report['Report']['status'] == 'complete') {
					$do = false; // completed reports should not be regenerated, except when there is new data				
					$conditions = array(
						'SurveyVisit.survey_id' => $this->data['Report']['project']
					);
					if (!empty($partner_id)) {
						$conditions['SurveyVisit.partner_id'] = $partner_id;
					}
					$last_transaction = $this->SurveyVisit->find('first', array(
						'fields' => array('max(id) as id'), 
						'conditions' => $conditions
					));
					if ($last_transaction) {
						if ($last_transaction[0]['id'] != $report['Report']['last_id']) {
							$do = true;
							$this->Report->create();
							$this->Report->save(array('Report' => array(
								'id' => $report['Report']['id'],
								'status' => 'queued',
								'path' => null,
								'last_transaction' => null,
								'last_id' => null
							)));
						}
					}
					
					if (!$do) {
						$this->Session->setFlash('The report is ready: <a href="'.$report['Report']['path'].'" target="_blank" class="btn btn-sm btn-primary">Download it here</a>', 'flash_success'); 
						$this->redirect(array('action' => 'index', '?' => array('type' => 'raw'))); 
					}
				}
			}
			else {
				$reportSource = $this->Report->getDataSource();
				$reportSource->begin();
				$this->Report->create();
				$this->Report->save(array('Report' => array(
					'type' => 'raw',
					'user_id' => $this->current_user['Admin']['id'],
					'survey_id' => $this->data['Report']['project'],
					'partner_id' => $this->data['Report']['partner_id']
				)));
				$report_id = $this->Report->getInsertId();
				$reportSource->commit();
			}
				
			if ($do) {				
				$query = ROOT.'/app/Console/cake report raw '.$this->data['Report']['project'].' '.$partner_id.' '.$report_id;
				$query.= " > /dev/null &"; 
				exec($query, $output);
				CakeLog::write('report_commands', $query);
				
				$this->Session->setFlash('We are generating your report - check the status below.', 'flash_success');
				$this->redirect(array('controller' => 'reports', 'action' => 'index', '?' => array('type' => 'raw')));
			}
		}
		
		$partners = $this->Partner->find('list', array(
			'fields' => array('id', 'partner_name'),
			'order' => 'Partner.partner_name ASC',
			'conditions' => array(
				'Partner.deleted' => false
			)
		));
		$this->set('partners', $partners);
	}
	
	public function ajax_check_project_id() {
		$this->layout = '';
		if (empty($this->request->data['project_id'])) {
			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => 'error',
					'error_message' => 'Please enter project ID',
				)), 
				'type' => 'json',
				'status' => '201'
			));
		}
		
		if (!$this->Admins->can_access_project($this->current_user, $this->request->data['project_id'])) {
			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => 'error',
					'error_message' => 'You are not authorized to access this project',
				)), 
				'type' => 'json',
				'status' => '201'
			));
		}
		
		$conditions = array(
			'Report.type' => 'report',
			'Report.survey_id' => $this->request->data['project_id'],
		);
		
		$report = $this->Report->find('first', array(
			'conditions' => $conditions
		));
		
		if ($report && isset($report['Project']['id'])) {
			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => 'success',
					'project_name' => '<strong>Found #' . MintVine::project_id($report) . ' ' . $report['Project']['prj_name'] . '</strong>',
					'report_id' => $report['Report']['id']
				)), 
				'type' => 'json',
				'status' => '201'
			));
		}
		else {
			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => 'error',
					'error_message' => 'Report for this project has not been generated yet',
				)), 
				'type' => 'json',
				'status' => '201'
			));
		}
	}
	
	public function security($project_id = null) {
		App::import('Lib', 'SurveyProcessing');
		App::import('Model', 'Visit');
		$this->Visit = new Visit;
		
		$fields = array(
			'user_agent' => 'User Agent',
			'overall_score' => 'Overall Score',
			'country' => 'Country',
			'timezone' => 'Timezone',
			'language' => 'Language',
			'browser_language' => 'Browser Language',
			'is_proxy' => 'Is Proxy',
			'language_check' => 'Language Check',
			'geo_check' => 'Geo Check',
			'time_check' => 'Time Check',
		);
		if ($this->request->is('post') || $this->request->is('put')) {	
			if (!empty($this->request->data['Report']['report_id'])) {
				$report = $this->Report->findById($this->request->data['Report']['report_id']);	
				if (!$this->Admins->can_access_project($this->current_user, $report['Report']['survey_id'])) {
					$this->Session->setFlash('You are not authorized to access this report.', 'flash_error');
					$this->redirect(array('action' => 'index'));
				}
			
				$survey_visits = $this->SurveyReport->find('all', array(
					'recursive' => -1,
					'conditions' => array(
						'SurveyReport.survey_id' => $report['Report']['survey_id'],
						'SurveyReport.result' => array(SURVEY_NQ_FRAUD, SURVEY_NQ_SPEED, SURVEY_INTERNAL_NQ)
					)
				));
				if (!empty($this->request->data['Report']['hashes'])) {
					$hashes = trim($this->request->data['Report']['hashes']);
					$hashes = explode("\n", $hashes);
					array_walk($hashes, create_function('&$val', '$val = trim($val);')); 
				}
				else {
					$hashes = array();
				}
				$visits = array();
				foreach ($survey_visits as $key => $survey_visit) {
					if (!empty($hashes)) {
						if (!in_array($survey_visit['SurveyReport']['hash'], $hashes)) {
							unset($survey_visits[$key]);
							continue;
						}
					}
					$visit_data = $this->Visit->find('first', array(
						'conditions' => array(
							'Visit.survey_id' => $report['Report']['survey_id'],
							'Visit.survey_visit_id' => $survey_visit['SurveyReport']['start_id']
						)
					));
					if ($visit_data) {
						$survey_visit['SurveyReport'] = $survey_visit['SurveyReport'] + array(
							'user_agent' => $visit_data['Visit']['user_agent'],
							'overall_score' => $visit_data['Visit']['overall_score'],
							'country' => $visit_data['Visit']['country'],
							'timezone' => $visit_data['Visit']['timezone'],
							'language' => $visit_data['Visit']['language'],
							'browser_language' => $visit_data['Visit']['browser_language'],
							'is_proxy' => $visit_data['Visit']['is_proxy'],
							'language_check' => $visit_data['Visit']['language_check'],
							'geo_check' => $visit_data['Visit']['geo_check'],
							'time_check' => $visit_data['Visit']['time_check']
						);
					}
					$visits[] = $survey_visit;
				}
				
				$csv_rows = SurveyProcessing::report_to_csv($visits, $fields);
				
				$filename = 'security_report_'.$report['Report']['survey_id'].'-' . gmdate(DB_DATE, time()) . '.csv';
				$csv_file = fopen('php://output', 'w');
	
				header('Content-type: application/csv');
				header('Content-Disposition: attachment; filename="' . $filename . '"');
				
				foreach ($csv_rows as $row) {
					fputcsv($csv_file, $row, ',', '"');
				}
	
				fclose($csv_file);
				$this->autoRender = false;
				$this->layout = false;
				$this->render(false);
			}
			else {
				$this->Session->setFlash('Please enter project ID', 'flash_error'); 
			}
		}
		
	}
	
	public function extended($project_id = null) {
		App::import('Lib', 'SurveyProcessing');
		App::import('Vendor', 'SiteProfile');
		
		$hhi_keys = unserialize(USER_HHI);
		$ethnicity_keys = unserialize(USER_ETHNICITY);
		$marital_keys = unserialize(USER_MARITAL);
		$edu_keys = unserialize(USER_EDU);
		$children_keys = unserialize(USER_CHILDREN);
		$employment_keys = unserialize(USER_EMPLOYMENT);
		$industry_keys = unserialize(USER_INDUSTRY);
		$home_keys = unserialize(USER_HOME);
		$smartphone_keys = unserialize(USER_SMARTPHONE);
		$tablet_keys = unserialize(USER_TABLET);
		$organization_size_keys = unserialize(USER_ORG_SIZE);
		$organization_revenue_keys = unserialize(USER_ORG_REVENUE);
		$job_keys = unserialize(USER_JOB);
		$department_keys = unserialize(USER_DEPARTMENT);
		$housing_purchase_keys = unserialize(USER_HOME_OWNERSHIP);
		$housing_plans_keys = unserialize(USER_HOME_PLANS);
		$airlines_keys = unserialize(USER_TRAVEL);
		
		if ($this->request->is('post') || $this->request->is('put')) {
			ini_set('memory_limit', '2048M');
			$fields = array();
			foreach ($this->request->data['Report']['options'] as $key => $val) {
				$fields[$val] = $val;
			}
			
			if (isset($fields['address']) && !isset($fields['postal_code'])) {
				$fields['postal_code'] = 'postal_code';
			}
			
			if (!empty($this->request->data['Report']['hashes'])) {
				$hashes = trim($this->request->data['Report']['hashes']);
				$hashes = explode("\n", $hashes);
				array_walk($hashes, create_function('&$val', '$val = trim($val);')); 
			}
			else {
				$hashes = array();
			}
			
			if (!empty($fields)) {
				$report = null;
				$this->request->data['Report']['project_id'] = trim($this->request->data['Report']['project_id']);
				if (!empty($this->request->data['Report']['project_id'])) {
					if ($this->Admins->can_access_project($this->current_user, $this->request->data['Report']['project_id'])) {						
						$conditions = array(
							'Report.type' => 'report',
							'Report.survey_id' => $this->request->data['Report']['project_id'],
						);
						$report = $this->Report->find('first', array(
							'conditions' => $conditions
						));
						if ($report) {
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
							SurveyProcessing::link_users($report['Report']['survey_id']);				
							$visits = $this->SurveyReport->find('all', array(
								'recursive' => -1,
								'conditions' => array(
									'SurveyReport.survey_id' => $report['Report']['survey_id'],
									'SurveyReport.partner_id' => 43
								)
							));
							foreach ($visits as $key => $visit) {
								if (!empty($hashes)) {
									if (!in_array($visit['SurveyReport']['hash'], $hashes)) {
										unset($visits[$key]);
										continue;
									}
								}
								$partner_user_id = $visit['SurveyReport']['partner_user_id'];
								$value = explode('-', $partner_user_id);
								if (!isset($value[1]) || empty($value[1])) {
									continue;
								}
								
								$user_id = $value[1];
								$profile = Utils::qe2_mv_qualifications($user_id, $settings);
								$user = $this->User->find('first', array(
									'fields' => array('id', 'email', 'created', 'firstname', 'lastname', 'fullname'),
									'conditions' => array(
										'User.id' => $user_id
									)
								));

								foreach ($fields as $field) {
									if ($field == 'http_agent') {
										$survey_visit = $this->SurveyVisit->find('first', array(
											'conditions' => array(
												'SurveyVisit.survey_id' => $visit['SurveyReport']['survey_id'],
												'SurveyVisit.hash' => $visit['SurveyReport']['hash'],
												'SurveyVisit.type' => SURVEY_CLICK
											),
											'fields' => array('info')
										));
										$value = '';
										if ($survey_visit) {
											$info = Utils::print_r_reverse($survey_visit['SurveyVisit']['info']);
											if (isset($info) && isset($info['HTTP_USER_AGENT'])) {
												$value = $info['HTTP_USER_AGENT'];
											}
										}
										$visits[$key]['SurveyReport'][$field] = $value;
									}
									elseif ($field == 'email') {
										$visits[$key]['SurveyReport'][$field] = $user ? $user['User']['email']: '';
									}
									elseif ($field == 'created') {
										$visits[$key]['SurveyReport'][$field] = $user ? $user['User']['created']: '';
									}
									elseif ($field == 'name') {
										$visits[$key]['SurveyReport'][$field] = $user ? $user['User']['fullname']: '';
										$visits[$key]['SurveyReport']['firstname'] = $user ? $user['User']['firstname']: '';
										$visits[$key]['SurveyReport']['lastname'] = $user ? $user['User']['lastname']: '';
									}
									elseif ($field == 'age') {
										$visits[$key]['SurveyReport'][$field] = isset($profile['birthdate'][0]) ? Utils::age($profile['birthdate'][0]) : ''; 
									}
									elseif ($field == 'address') {
										$user_address = $this->UserAddress->find('first', array(
											'conditions' => array(
												'UserAddress.user_id' => $user_id,
												'UserAddress.deleted' => false
											)
										));
										
										if ($user_address) {
											$visits[$key]['SurveyReport']['first_name'] = $user_address['UserAddress']['first_name'];
											$visits[$key]['SurveyReport']['last_name'] = $user_address['UserAddress']['last_name'];
											$visits[$key]['SurveyReport']['address_line1'] = $user_address['UserAddress']['address_line1'];
											$visits[$key]['SurveyReport']['address_line2'] = $user_address['UserAddress']['address_line2'];
											$visits[$key]['SurveyReport']['city'] = $user_address['UserAddress']['city'];
											$visits[$key]['SurveyReport']['state'] = $user_address['UserAddress']['state'];
											$visits[$key]['SurveyReport']['country'] = $user_address['UserAddress']['country'];
											$visits[$key]['SurveyReport']['county'] = $user_address['UserAddress']['county'];
										}
										else {
											$visits[$key]['SurveyReport']['first_name'] = '';
											$visits[$key]['SurveyReport']['last_name'] = '';
											$visits[$key]['SurveyReport']['address_line1'] = '';
											$visits[$key]['SurveyReport']['address_line2'] = '';
											$visits[$key]['SurveyReport']['city'] = '';
											$visits[$key]['SurveyReport']['state'] = '';
											$visits[$key]['SurveyReport']['country'] = '';
											$visits[$key]['SurveyReport']['county'] = '';
										}
									}
									elseif (isset($profile[$field])) {
										$value = '';
										if ($field == 'hhi') {
											$value = isset($hhi_keys[$profile[$field][0]]) 
												? $hhi_keys[$profile[$field][0]] : '';
										}
										elseif ($field == 'relationship') {
											$value = isset($marital_keys[$profile[$field][0]]) 
												? $marital_keys[$profile[$field][0]] : '';
										}
										elseif ($field == 'housing_own') {
											$value = isset($home_keys[$profile[$field][0]]) 
												? $home_keys[$profile[$field][0]] : '';
										}
										elseif ($field == 'smartphone') {
											$value = isset($smartphone_keys[$profile[$field][0]]) 
												? $smartphone_keys[$profile[$field][0]]
												: '';
										}
										elseif ($field == 'tablet') {
											$value = isset($tablet_keys[$profile[$field][0]]) 
												? $tablet_keys[$profile[$field][0]]
												: '';
										}
										elseif ($field == 'employment') {
											$value = isset($employment_keys[$profile[$field][0]]) 
												? $employment_keys[$profile[$field][0]]
												: '';
										}
										elseif ($field == 'ethnicity') {
											$value = isset($ethnicity_keys[$profile[$field][0]]) 
												? $ethnicity_keys[$profile[$field][0]]
												: '';								
										}
										elseif ($field == 'industry') {
											$value = isset($industry_keys[$profile['QueryProfile'][$field][0]]) 
												? $industry_keys[$profile[$field][0]]
												: '';								
										}
										elseif ($field == 'education') {
											$value = isset($edu_keys[$profile[$field][0]]) 
												? $edu_keys[$profile[$field][0]]
												: '';					
										}
										elseif ($field == 'children') {
											$value = isset($children_keys[$profile[$field][0]]) 
												? $children_keys[$profile[$field][0]] 
												: '';					
										}
										elseif ($field == 'organization_size') {
											$value = isset($organization_size_keys[$profile[$field][0]]) 
												? $organization_size_keys[$profile[$field][0]] 
												: '';					
										}
										elseif ($field == 'organization_revenue') {
											$value = isset($organization_revenue_keys[$profile[$field][0]]) 
												? $organization_revenue_keys[$profile[$field][0]] 
												: '';					
										}
										elseif ($field == 'job') {
											$value = isset($job_keys[$profile[$field][0]]) 
												? $job_keys[$profile[$field][0]] 
												: '';					
										}
										elseif ($field == 'department') {
											$value = isset($department_keys[$profile[$field][0]]) 
												? $department_keys[$profile[$field][0]] 
												: '';					
										}
										elseif ($field == 'housing_purchased') {
											$value = isset($housing_purchase_keys[$profile[$field][0]]) 
												? $housing_purchase_keys[$profile[$field][0]] 
												: '';					
										}
										elseif ($field == 'housing_plans') {
											$value = isset($housing_plans_keys[$profile[$field][0]]) 
												? $housing_plans_keys[$profile[$field][0]] 
												: '';					
										}
										elseif ($field == 'airlines') {
											$value = isset($airlines_keys[$profile[$field][0]]) 
												? $airlines_keys[$profile[$field][0]] 
												: '';					
										}
										else {
											$value = $profile[$field][0];
										}
										
										$visits[$key]['SurveyReport'][$field] = $value;
									}
									else {
										$visits[$key]['SurveyReport'][$field] = '';
									}
								}
							}
							
							$csv_rows = SurveyProcessing::report_to_csv($visits, $fields);
							$filename = 'extended_report_'.$report['Report']['survey_id'].'-' . gmdate(DB_DATE, time()) . '.csv';
							$csv_file = fopen('php://output', 'w');
							header('Content-type: application/csv');
							header('Content-Disposition: attachment; filename="' . $filename . '"');
							foreach ($csv_rows as $row) {
								fputcsv($csv_file, $row, ',', '"');
							}
							fclose($csv_file);
							$this->autoRender = false;
							$this->layout = false;
							$this->render(false);
						}
						else {
							$this->Session->setFlash('Report for this project has not been generated yet', 'flash_error');
						}
					}
					else {
						$this->Session->setFlash('You are not authorized to access this project', 'flash_error');
					}
				}
				else {
					$this->Session->setFlash('Please input project id.', 'flash_error');	
				}
			}
			else {
				$this->Session->setFlash('Please set some extended data points.', 'flash_error'); 
			}
		}
		
		$this->set(compact('project_id'));
	}
	
	public function index() {
		$paginate = array(
			'Report' => array(
				'fields' => array('*'),
				'limit' => '50',
				'order' => 'Report.modified DESC',
				'contain' => array(
					'Admin', 
					'Partner',
				)	
			)
		);
		
		if ($this->current_user['AdminRole']['admin']) {
			$paginate['Report']['contain'] = array_merge($paginate['Report']['contain'], array('Project' => array('Group')));
		}
		else {
			$paginate['Report']['joins'][] = array(
	            'alias' => 'Project',
	            'table' => 'projects',
	            'type' => 'INNER',
	            'conditions' => array(
					'Report.survey_id = Project.id',
				)
			);
			$paginate['Report']['joins'][] = array(
	            'alias' => 'Group',
	            'table' => 'groups',
	            'type' => 'INNER',
	            'conditions' => array(
					'Project.group_id = Group.id',
					'Group.id' => $this->current_user['AdminGroup']
				)
			);
		}
		
		$this->paginate = $paginate;
		$reports = $this->paginate(); 
		$this->set('reports', $reports);
	}
	
	public function lucid() {
		$fed_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'fulcrum'
			)
		));

		if ($this->request->is('put') || $this->request->is('post')) {
			if (isset($this->data['Report']['file']['tmp_name']) && !empty($this->data['Report']['file']['tmp_name'])) {
				$file = file_get_contents($this->data['Report']['file']['tmp_name']);
				$rows = explode("\n", $file);
				$rows = array_map('trim', $rows);
				$timestamps = array();
				foreach ($rows as $key => $row) {
					$row = str_getcsv($row);
					if (count($row) != 22) {
						continue;
					}
					$rows[$key] = $row;
					$timestamps[] = strtotime($row[13]);
				}
				unset($rows[0]); // drop off header
				
				// we have some clock drifting; +/ 10 minutes on each side
				$min_timestamp = min($timestamps) - 600; 
				$max_timestamp = max($timestamps) + 600;
				
				$projects = $this->Project->find('list', array(
					'fields' => array('id'),
					'conditions' => array(
						'Project.group_id' => $fed_group['Group']['id'],
						'Project.status' => array(PROJECT_STATUS_OPEN, PROJECT_STATUS_CLOSED)
					)
				));
				
				$survey_completes = $this->SurveyVisit->find('all', array(
					'conditions' => array(
						'SurveyVisit.survey_id' => $projects,
						'SurveyVisit.type' => SURVEY_COMPLETED,
						'SurveyVisit.created >=' => date(DB_DATETIME, $min_timestamp),
						'SurveyVisit.created <=' => date(DB_DATETIME, $max_timestamp)
					)
				));
				
				$mv_ids = $fed_ids = array();
				if ($survey_completes) {
					foreach ($survey_completes as $survey_complete) {
						$mv_ids[] = $survey_complete['SurveyVisit']['hash']; 
					}
				}
				
				$data = array();
				$data[] = array(
					'Fed Reported: '.count($rows),
					'MintVine Matched: '.count($survey_completes)
				);
				$data[] = array('---');
				$data[] = array('Hashes Reported by Fed but missing in MintVine:');
				
				// first find unmatched values
				foreach ($rows as $key => $row) {
					$hash = $row[2];
					$fed_ids[] = $hash;
					if (in_array($hash, $mv_ids)) {
						unset($rows[$key]);
					}
				}
				$data = $data + $rows;
				
				$data[] = array('---');
				$data[] = array('Hashes Reported by MintVine but missing in Fed:');
				
				if ($survey_completes) {
					foreach ($survey_completes as $key => $survey_complete) {
						if (!in_array($survey_complete['SurveyVisit']['hash'], $fed_ids)) {
							$data[] = array(
								$survey_complete['SurveyVisit']['hash'],
								$survey_complete['SurveyVisit']['created']
							);
						}
					}
				}
				
				
		    	$filename = 'fed_reconciliation-' . gmdate(DB_DATE, time()) . '.csv';
		  		$csv_file = fopen('php://output', 'w');

				header('Content-type: application/csv');
				header('Content-Disposition: attachment; filename="' . $filename . '"');

				// Each iteration of this while loop will be a row in your .csv file where each field corresponds to the heading of the column
				foreach ($data as $row) {
					fputcsv($csv_file, $row, ',', '"');
				}

				fclose($csv_file);
				$this->autoRender = false;
				$this->layout = false;
				$this->render(false);
			}
		}
	}
	
	public function user_sources($export = false) {
		$show_gender = $show_pubs = false;
		if (isset($this->request->query)) {
			$reporting = array();	
			if (isset($this->request->query['source']) && (!isset($this->request->query['date_from']) || !$this->request->query['date_from'] || !isset($this->request->query['date_to']) || !$this->request->query['date_to'])) {
				$this->Session->setFlash('Please set a date.', 'flash_error');
			}
			else {
				// set default utc values to deal with stackify errors
				if (!isset($this->request->query['timezone']) || empty($this->request->query['timezone'])) {
					$this->request->query['timezone'] = 'UTC';
				}
				$reporting = array();			
				if (isset($this->request->query['source']) && $this->request->query['source']) {
					$show_gender = isset($this->request->query['gender'] ) && $this->request->query['gender'] == 1;
					$show_pubs = isset($this->request->query['pub'] ) && !empty($this->request->query['pub']);
					if ($show_pubs) {
						$pubs = explode("\n", $this->request->query['pub']);
						array_walk($pubs, create_function('&$val', '$val = trim($val);')); 
						$this->set(compact('pubs'));
					}
					if (substr($this->request->query['source'], 0, 8) == 'mapping:') {
						$source_mapping = $this->SourceMapping->findById(substr($this->request->query['source'], 8)); 
						$source_name = $source_mapping['SourceMapping']['utm_source'];
					}
					$this->set(compact('source', 'source_mapping'));
					// when retrieving all sources, simply do a summary view
					if ($this->request->query['source'] == 'all') {
						$user_sources = unserialize(USER_SOURCES);
						foreach ($user_sources as $source => $val) {
							$reporting[] = Reporting::user_sources(
								$source_name, 
								$this->request->query['date_from'], 
								$this->request->query['date_to'],
								$this->request->query['timezone'],
								null,
								$show_gender
							); 
						}
					}
					// when grabbing individual sources, drill down into the publishers 
					else {
						$reporting[] = Reporting::user_sources(
							$source_name, 
							$this->request->query['date_from'], 
							$this->request->query['date_to'],
							$this->request->query['timezone'],
							null,
							$show_gender
						); 
						
						$publishers = Reporting::source_publishers($this->request->query['source']);
						if ($publishers) {
							foreach ($publishers as $pub_id) {
								$reporting[] = Reporting::user_sources(
									$source_name, 
									$this->request->query['date_from'], 
									$this->request->query['date_to'],
									$this->request->query['timezone'],
									$pub_id,
									$show_gender
								); 
							}
						}
					}
				}
			}
			if ($export) {
				$csvs = array();
				if ($show_gender) {
					$csvs[] = array('Campaign', 'Total Registered', 'Registered Males', 'Registered Females', 'Total Verified', 'Verified Males', 'Verified Females', 'Total Survey Starts', 'Survey Start Males', 'Survey Start Females', 'Total Points', 'Average Points', 'Average Points', 'Hell Banned');
				}
				else {
					$csvs[] = array('Campaign', 'Total Registered', 'Total Verified', 'Total Survey Starts', 'Total Points', 'Average Points', 'Average Points', 'Hell Banned');
				}
				if (isset($reporting) && $reporting) {
					$total_registrations = $total_activations = $total_survey_starts = $total_points = $hellbanned = $i = 0;
					$total_males = $total_females = $total_activated_males = $total_activated_females = $total_survey_start_males = $total_survey_start_females = 0;
					$average_points = array(); 
					
					foreach ($reporting as $row) {
						$i++;
						if (isset($row['publisher']) && !empty($row['publisher'])) {
							if (empty($row['total_registrations']) && empty($row['total_activations'])) {
								continue;
							}
							if ($show_pubs && !in_array($row['publisher'], $pubs)) {
								continue;
							}
						}
						if ($show_gender) {
							$csvs[] = array(
								$source_mapping['AcquisitionPartner']['name'] .' '. ((isset($row['publisher']) && !empty($row['publisher'])) ? 'Pub: '.$row['publisher'] : ''),
								number_format($row['total_registrations']),
								number_format($row['males']),
								number_format($row['females']),
								number_format($row['total_activations']),
								number_format($row['activated_males']),
								number_format($row['activated_females']),
								number_format($row['total_survey_starts']),
								number_format($row['total_survey_start_males']),
								number_format($row['total_survey_start_females']),
								($row['total_points']) ? number_format($row['total_points']) : 0,
								($row['average_points']) ? round($row['average_points'], 2) : 0,
								number_format($row['hellbanned']),
							);
						}
						else {
							$csvs[] = array(
								$source_mapping['AcquisitionPartner']['name'] .' '. ((isset($row['publisher']) && !empty($row['publisher'])) ? 'Pub: '.$row['publisher'] : ''),
								number_format($row['total_registrations']),
								number_format($row['total_activations']),
								number_format($row['total_survey_starts']),
								($row['total_points']) ? number_format($row['total_points']) : 0,
								$row['average_points'] ? round($row['average_points'], 2) : 0,
								number_format($row['hellbanned']),
							);
						}
						if ($i > 1) {
							$average_points[] = ($row['average_points']) ? round($row['average_points'], 2) : 0; 
							$total_registrations += $row['total_registrations'];
							$total_activations += $row['total_activations'];
							$total_survey_starts += $row['total_survey_starts'];
							$total_points += $row['total_points'];
							$hellbanned += $row['hellbanned'];
							if ($show_gender) {
								$total_males += $row['males'];
								$total_females += $row['females'];
								$total_activated_males += $row['activated_males'];
								$total_activated_females += $row['activated_females'];
								$total_survey_start_males += $row['total_survey_start_males'];
								$total_survey_start_females += $row['total_survey_start_females'];
							}
						}
					}
				}
				if (isset($this->request->query['source']) && $this->request->query['source'] != 'all') {
					if ($show_gender) {
	    				$csvs[] = array(
							$source_mapping['AcquisitionPartner']['name'] . '(From Publishers)',
							number_format($total_registrations),
							number_format($total_males),
							number_format($total_females),
							number_format($total_activations),
							number_format($total_activated_males),
							number_format($total_activated_females),
							number_format($total_survey_starts),
							number_format($total_survey_start_males),
							number_format($total_survey_start_females),
							($total_points) ? number_format($total_points) : 0,
							!empty($average_points) ? round(array_sum($average_points) / count($average_points), 2) : 0,
							number_format($hellbanned)
	    				);
					}
					else {
	    				$csvs[] = array(
							$source_mapping['AcquisitionPartner']['name'] . '(From Publishers)',
							number_format($total_registrations),
							number_format($total_activations),
							number_format($total_survey_starts),
							($total_points) ? number_format($total_points) : 0,
							!empty($average_points) ? round(array_sum($average_points) / count($average_points), 2) : 0,
							number_format($hellbanned)
	    				);
					}
    			}
    			$filename = 'reports_user_sources_' . gmdate(DB_DATE, time()) . '.csv';
    			$csv_file = fopen('php://output', 'w');

				header('Content-type: application/csv');
				header('Content-Disposition: attachment; filename="' . $filename . '"');

				// Each iteration of this while loop will be a row in your .csv file where each field corresponds to the heading of the column
				foreach ($csvs as $csv) {
					fputcsv($csv_file, $csv, ',', '"');
				}

				fclose($csv_file);
				$this->autoRender = false;
				$this->layout = false;
				$this->render(false);
			} 
			else {
				$this->set(compact('reporting'));
			}
		}
		$source_mappings = $this->SourceMapping->find('list', array(
			'conditions' => array(
				'SourceMapping.deleted' => null
			),
			'order' => 'SourceMapping.name ASC'
		));
		$source_list = array();
		if (!empty($source_mappings)) {
			foreach ($source_mappings as $id => $name) {
				$source_list['mapping:'.$id] = $name;
			}
		}
		array_multisort(array_map('strtolower', $source_list), $source_list);
		$this->set(compact('show_gender', 'show_pubs', 'source_list'));
	}
	
	public function rejected_transactions() {
		$users = $this->User->find('all', array(
			'conditions' => array(
				'User.rejected_transactions >' => '0',
				'User.hellbanned' => false,
				'User.active' => true,
				'User.deleted_on' => null
			),
			'order' => 'User.rejected_transactions DESC'
		));
		if ($users) {
			foreach ($users as $key => $user) {
				$sum = $this->Transaction->find('first', array(
					'fields' => array('SUM(amount) as amount'),
					'recursive' => -1,
					'conditions' => array(
						'Transaction.user_id' => $user['User']['id'],
						'Transaction.type_id' => TRANSACTION_SURVEY,
						'Transaction.status' => TRANSACTION_REJECTED,
						'Transaction.deleted' => null,
					)
				));
				$users[$key]['sum'] = $sum[0]['amount'];
			}
		}
		$this->set(compact('users'));
	}
	
	// to fix
	public function queries() {
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}

		if (!isset($this->data['date_field']) || empty($this->data['date_field'])) {
			$this->data = array_merge($this->data, array('date_field' => 'created'));
		}

		if (isset($this->data['query_id'])) {
			$query = $this->Query->findById($this->data['query_id']);
			if (!$this->Admins->can_access_project($this->current_user, $query['Query']['survey_id'])) {
				$this->Session->setFlash('You are not authorized to access that query.', 'flash_error');
				$this->redirect(array('action' => 'index'));
			}
		
			$query_history_ids = array();
			if (!empty($query['QueryHistory'])) {
				foreach ($query['QueryHistory'] as $history) {
					$query_history_ids[] = $history['id'];
				}
			}
			if (!empty($query['QueryHistory'])) {
				$qs = json_decode($query['Query']['query_string'], true);
			}
			else {
				$qs = array();
			}
			$results = $this->QueryEngine->execute($qs);

			$conditions = array();
			if (!empty($query['Query']['survey_id'])) {
				$conditions['SurveyUser.survey_id'] = $query['Query']['survey_id'];
			}
			if (!empty($query_history_ids)) {
				$conditions['SurveyUser.query_history_id'] = $query_history_ids;
			}
			$survey_users = $this->SurveyUser->find('list', array(
				'fields' => array('id', 'user_id'),
				'recursive' => -1,
				'conditions' => $conditions
			));
			
			$users_all_raw = $this->User->find('all', array(
					'conditions' => array(
					'User.id' => $results['users'],
					'User.send_survey_email' => true
				),
				'fields' => array_unique(array('id', 'total', $this->data['date_field'], 'last_touched')),
				'recursive' => -1,
			));
			$users_all = $this->ReportData->generateAgeData($users_all_raw, $this->data['date_field']);
			$users_matched_raw = $this->User->find('all', array(
					'conditions' => array(
					'User.id' => $survey_users,
				),
				'fields' => array_unique(array('id', 'total', $this->data['date_field'], 'last_touched')),
				'recursive' => -1,
			));
			
			$users_matched = $this->ReportData->generateAgeData($users_matched_raw, $this->data['date_field']);
			$user_levels_all = MintVineUser::user_level_count($users_all_raw);
			$user_levels_matched = MintVineUser::user_level_count($users_matched_raw);
			$this->set(compact('users_all', 'users_matched', 'user_levels_all', 'user_levels_matched'));
		}
	}
	
	public function cint() {
		$cint_group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => 'cint'
			)
		));
		if (($this->request->is('put') || $this->request->is('post')) && isset($this->data['Report']['file']['tmp_name']) && !empty($this->data['Report']['file']['tmp_name'])) {
			ini_set('memory_limit', '1024M');
			ini_set('max_execution_time', 60);
			$handle = fopen($this->data['Report']['file']['tmp_name'], "r");
			$user_id_key = false;
			$project_id_key = false;
			$respondent_status_key = false;
			$header_row = fgetcsv($handle, 0, ';');
			array_pop($header_row);
			if ($header_row) {
				// Handle unwanted charactes in the begining of file
				$header_row[0] = preg_replace("~[^a-z0-9 ]~i", "", $header_row[0]);
				foreach ($header_row as $key => $value) {
					if (trim($value) == 'Member id') {
						$user_id_key = $key;
					}

					if (trim($value) == 'Project id') {
						$project_id_key = $key;
					}
					
					if (trim($value) == 'Respondent status') {
						$respondent_status_key = $key;
					}
				}
			}

			if ($user_id_key === false || $project_id_key === false || $respondent_status_key === false) {
				$this->Session->setFlash('Cint report file must contain columns with heading "Member id", "Project id" & "Respondent status"', 'flash_error');
				$this->redirect(array('action' => 'cint'));
			}
			
			$projects = $this->Project->find('list', array(
				'fields' => array('id'),
				'conditions' => array(
					'Project.group_id' => $cint_group['Group']['id'],
					'Project.status' => array(PROJECT_STATUS_OPEN, PROJECT_STATUS_CLOSED)
				)
			));
			$this->SurveyVisit->primaryKey = 'survey_id';
			$this->SurveyVisit->bindModel(array(
				'hasOne' => array(
					'CintSurvey' => array(
						'className' => 'CintSurvey',
						'foreignKey' => 'survey_id'
					)
				)
			));
			
			$survey_visits = $this->SurveyVisit->find('all', array(
				'fields' => array('SurveyVisit.survey_id', 'SurveyVisit.partner_user_id', 'SurveyVisit.type', 'SurveyVisit.hash'),
				'contain' => array('CintSurvey' => array(
					'fields' => array('CintSurvey.cint_survey_id')
				)),
				'conditions' => array(
					'SurveyVisit.survey_id' => $projects,
					'SurveyVisit.type' => array(SURVEY_COMPLETED, SURVEY_OVERQUOTA, SURVEY_OQ_INTERNAL, SURVEY_NQ)
				)
			));
			
			$visits = array();
			foreach ($survey_visits as $visit) {
				$user = explode('-', $visit['SurveyVisit']['partner_user_id']);
				$visits[$visit['CintSurvey']['cint_survey_id']][$user[1]][$visit['SurveyVisit']['type']] = array(
					'survey_id' => $visit['SurveyVisit']['survey_id'],
					'hash' => $visit['SurveyVisit']['hash']
				);
			}
			
			$filename = 'cint_reconciliation' . '-' . gmdate(DB_DATE, time()) . '.csv';
			$csv_file = fopen('php://output', 'w');
			header('Content-type: application/csv');
			header('Content-Disposition: attachment; filename="' . $filename . '"');
			$header_row[] = 'Hash';
			$header_row[] = 'Status';
			fputcsv($csv_file, $header_row, ',', '"');
			while (($row = fgetcsv($handle, 0, ';')) !== FALSE) {
				array_pop($row);
				$type = '';
				if ($row[$respondent_status_key] == 'Complete') {
					$type = SURVEY_COMPLETED;
				}
				elseif ($row[$respondent_status_key] == 'QuotaFull') {
					$type = SURVEY_OVERQUOTA;
				}
				elseif ($row[$respondent_status_key] == 'EarlyScreenout' || $row[$respondent_status_key] == 'LateScreenout') {
					$type = SURVEY_NQ;
				}
				
				if (isset($visits[$row[$project_id_key]][$row[$user_id_key]][$type])) {
					
					// Note: The order of these statements is importat, as the $row[$project_id_key], changes.
					$row[] = $visits[$row[$project_id_key]][$row[$user_id_key]][$type]['hash'];
					$row[$project_id_key] = $visits[$row[$project_id_key]][$row[$user_id_key]][$type]['survey_id'];
					$row[] = 'Matched';
				}
				
				fputcsv($csv_file, $row, ',', '"');
			}

			fclose($csv_file);
			$this->autoRender = false;
			$this->layout = false;
			$this->render(false);
		}
	}
	
	public function completes_per_user() {
		if ($this->request->is('put') || $this->request->is('post')) {
			$transactions = $this->Transaction->find('all', array(
				'fields' => array('id', 'user_id', 'created'),
				'conditions' => array(
					'Transaction.deleted' => null,
					'Transaction.type_id' => TRANSACTION_SURVEY,
					'Transaction.created >=' => date(DB_DATETIME, strtotime('-'.$this->request->data['Report']['days'].' days'))
				),
				'recursive' => -1
			));
			$user_counts = array();
			if ($transactions) {
				foreach ($transactions as $transaction) {
					$date = date('Y-m-d', strtotime($transaction['Transaction']['created']));
					if (!isset($user_counts[$date])) {
						$user_counts[$date] = array();
					}
					if (!isset($user_counts[$date][$transaction['Transaction']['user_id']])) {
						$user_counts[$date][$transaction['Transaction']['user_id']] = 0;
					}
					$user_counts[$date][$transaction['Transaction']['user_id']]++;
				}
			}
			
			if (isset($this->request->data['Report']['csv'])) {
				
				$data = array(array(
					'Date',
					'UserID', 
					'Count', 
				));
				foreach ($user_counts as $date => $users) {
					foreach ($users as $user_id => $count) {
						$data[] = array(
							$date,
							$user_id,
							$count
						);
					}
				}
		
		    	$filename = 'completes_per_user-' . gmdate(DB_DATE, time()) . '.csv';
		  		$csv_file = fopen('php://output', 'w');

				header('Content-type: application/csv');
				header('Content-Disposition: attachment; filename="' . $filename . '"');

				// Each iteration of this while loop will be a row in your .csv file where each field corresponds to the heading of the column
				foreach ($data as $row) {
					fputcsv($csv_file, $row, ',', '"');
				}

				fclose($csv_file);
				$this->autoRender = false;
				$this->layout = false;
				$this->render(false);
			}
			$this->set(compact('user_counts')); 
		}
		
	}
	
	public function fulcrum_clicks_to_complete() {
		if ($this->request->is('put') || $this->request->is('post')) {			
			$fed_group = $this->Group->find('first', array(
				'conditions' => array(
					'Group.key' => 'fulcrum'
				)
			));
			$conditions = array(
				'Project.group_id' => $fed_group['Group']['id'],
				'Project.status' => array(PROJECT_STATUS_OPEN, PROJECT_STATUS_CLOSED),
				'SurveyVisitCache.complete >' => '0'
			);
			if (isset($this->request->data['Report']['active']) && $this->request->data['Report']['active']) {
				$conditions['Project.status'] = PROJECT_STATUS_OPEN;
			}
			$this->Project->unbindModel(array('hasMany' => array('SurveyPartner', 'ProjectOption')));
			$projects = $this->Project->find('all', array(
				'conditions' => $conditions,
				'fields' => array('Project.id', 'SurveyVisitCache.click', 'SurveyVisitCache.complete', 'SurveyVisitCache.nq', 'SurveyVisitCache.ir'),
				'limit' => $this->request->data['Report']['limit'],
				'order' => 'Project.id DESC'
			));
			$data = $statistics = array();
			foreach ($projects as $project) {
				$complete = $this->SurveyVisit->find('first', array(
					'fields' => array('id'),
					'conditions' => array(
						'SurveyVisit.survey_id' => $project['Project']['id'],
						'SurveyVisit.type' => SURVEY_COMPLETED
					),
					'order' => 'SurveyVisit.id ASC'
				));
				$click = $this->SurveyVisit->find('count', array(
					'conditions' => array(
						'SurveyVisit.survey_id' => $project['Project']['id'],
						'SurveyVisit.type' => SURVEY_CLICK,
						'SurveyVisit.id <' => $complete['SurveyVisit']['id']
					)
				));
				if ($complete && $click) {
					$data[$project['Project']['id']] = $click;
					$statistics[$project['Project']['id']] = $project;
				}
			}
			$this->set(compact('data', 'statistics'));
		}
	}
	
	public function sampling_percentage() {
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		$date_from = '';
		$date_to = '';

		if (isset($this->data) && !empty($this->data)) {
			if (isset($this->data['date_from']) && !empty($this->data['date_from'])) {
				if (isset($this->data['date_to']) && !empty($this->data['date_to'])) {
					$date_from = date(DB_DATE, strtotime($this->data['date_from']));
					$date_to = date(DB_DATE, strtotime($this->data['date_to']));
				}
				else {
					$date_from = date(DB_DATE, strtotime($this->data['date_from']));
					$date_to = date(DB_DATE, strtotime($this->data['date_from']));
				}
			}
		}
		else { // Get the last 7 days' data as default
			$date_from = date(DB_DATE, strtotime("7 days ago"));
			$date_to = date(DB_DATE, strtotime("1 day ago"));
			$this->data = array(
				'date_from' => date('m/d/Y', strtotime($date_from)),
				'date_to' => date('m/d/Y', strtotime($date_to))
			);
		}

		$this->loadModel('ProjectLog');
		
		$project_logs = $this->ProjectLog->find('all', array(
			'conditions' => array(
				'ProjectLog.type like ' => '%status%',
				'ProjectLog.created >=' => $date_from. ' 00:00:00',
				'ProjectLog.created <=' => $date_to . ' 23:59:59',
			),
			'fields' => array('project_id')
		));
		$project_ids = array();
		if (!empty($project_logs)) {
			foreach ($project_logs as $project_log) {
				$project_id = $project_log['ProjectLog']['project_id'];
				if (!isset($project_ids[$project_id])) {
					$project_ids[$project_id] = $project_id;
				}
			}			
		}
		
		$sampled_projects = array();
		$non_sampled_projects = array();
		
		if (!empty($project_ids)) {
			foreach ($project_ids as $project_id) {				
				$project_logs = $this->ProjectLog->find('all', array(
					'conditions' => array(
						'ProjectLog.project_id' => $project_id,
						'ProjectLog.type like ' => '%status%',
						'ProjectLog.created >=' => $date_from. ' 00:00:00',
						'ProjectLog.created <=' => $date_to . ' 23:59:59',
					),
					'fields' => array('type'),
					'order' => 'ProjectLog.id ASC',
				));
		
				if (count($project_logs) > 1) {
					for ($i = 0; $i < count($project_logs) - 1; $i++) {
						$current_log = $project_logs[$i];
						$next_log = $project_logs[$i + 1];
						if ($current_log['ProjectLog']['type'] == 'status.sample' && $next_log['ProjectLog']['type'] == 'status.opened') {
							$sampled_projects[$project_id] = $project_id;
							break;
						}
					}
				}
				if (!isset($sampled_projects[$project_id])) {
					$non_sampled_projects[$project_id] = $project_id;
				}					
			}
		}
		
		if (!empty($sampled_projects)) {
			$projects = $this->Project->find('all', array(
				'conditions' => array(
					'Project.id' => $sampled_projects
				),
				'contain' => array(
					'SurveyVisitCache'
				)
			));		
			
			$this->set('projects', $projects);
		}
		
		$count_sampled_projects = count($sampled_projects);
		$count_non_sampled_projects = count($sampled_projects);
		
		$total = $count_sampled_projects + $count_non_sampled_projects;
		$this->set(compact('count_sampled_projects', 'count_non_sampled_projects', 'total'));
		
	}
	
	public function download($report_id = null, $custom_report = false) {
		if (empty($report_id)) {
			throw new NotFoundException();
		}
		
		$report = $this->Report->find('first', array(
			'conditions' => array(
				'Report.id' => $report_id
			),
			'fields' => array(
				'id', 'status', 'path', 'custom_path', 'user_id', 'survey_id', 'type'
			)
		));
		
		if ($report) {
			if ($report['Report']['type'] != 'poll' && !$this->Admins->can_access_project($this->current_user, $report['Report']['survey_id'])) {
				$this->Session->setFlash('You are not authorized to access this report.', 'flash_error');
				$this->redirect(array('controller' => 'reports', 'action' => 'index'));
			}
			
			if ($report['Report']['status'] == 'complete') {
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
				
				CakePlugin::load('Uploader');
				App::import('Vendor', 'Uploader.S3');
				
				$file = $report['Report']['path'];
				if ($custom_report) {
					$file = $report['Report']['custom_path'];
				}
				
				// we store with first slash; but remove it for S3
				if (substr($file, 0, 1) == '/') {
					$file = substr($file, 1, strlen($file)); 
				}
				
				$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);
				$url = $S3->getAuthenticatedURL($settings['s3.bucket'], $file, 3600, false, false);			
				
				$this->redirect($url);
			}
			else {
				$this->Session->setFlash('A report is already being generated - please wait until it is done.', 'flash_error');
				$this->redirect(array(
					'controller' => 'reports',
					'action' => 'index'
				));
			}
		}
		else {
			throw new NotFoundException();
		}
	}
	
	// given a survey, shows historic performance
	public function survey($survey_id) {
		$project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $survey_id
			)
		));
		if (!$this->Admins->can_access_project($this->current_user, $project)) {
			$this->Session->setFlash('You are not authorized to access this report.', 'flash_error');
			$this->redirect(array('action' => 'index'));
		}
			
		$survey_visits = $this->SurveyVisit->find('all', array(
			'conditions' => array(
				'SurveyVisit.survey_id' => $survey_id
			),
			'recursive' => -1,
			'fields' => array('SurveyVisit.id', 'SurveyVisit.result_id', 'SurveyVisit.type', 'SurveyVisit.created'),
			'order' => 'SurveyVisit.id ASC'
		));
		$start = strtotime($project['Project']['date_created']);
		$data_rows = array(
			// time since creation
			// click count
			// nq count
			// oq count
			// oq_internal count
			// complete count
			// IR
			// LOI
			// EPC
		);
		$click = $complete = $overquota = $nq = $oq = $oq_internal = $epc = $loi = 0;
		$click_time = array();
		$last_type = null;
		foreach ($survey_visits as $survey_visit) {
			$epc = $loi = 0; 
			$last_type = $survey_visit['SurveyVisit']['type'];
			if ($survey_visit['SurveyVisit']['type'] == SURVEY_CLICK) {
				$click++;
				$click_time[$survey_visit['SurveyVisit']['result_id']] = $survey_visit['SurveyVisit']['created'];
			}
			elseif ($survey_visit['SurveyVisit']['type'] == SURVEY_COMPLETED) {
				$complete++;
				if ($click > 0) {
					$epc = round(($project['Project']['client_rate'] * $complete) / $click, 2);
				}
				if (isset($click_time[$survey_visit['SurveyVisit']['id']])) {
					$loi = round((strtotime($survey_visit['SurveyVisit']['created']) - strtotime($click_time[$survey_visit['SurveyVisit']['id']])) / 60);
				}
				$ir = round(($complete / $click) , 2) * 100;
				$data_rows[] = array(
					'time' => round((strtotime($survey_visit['SurveyVisit']['created']) - $start) / 60),
					'complete' => $complete,
					'click' => $click, 
					'nq' => $nq, 
					'oq' => $oq, 
					'oq_internal' => $oq_internal, 
					'ir' => $ir.'%',
					'loi' => $loi,
					'epc' => $epc
				);
			}
			elseif ($survey_visit['SurveyVisit']['type'] == SURVEY_OVERQUOTA) {
				$oq++;
			}
			elseif ($survey_visit['SurveyVisit']['type'] == SURVEY_OQ_INTERNAL) {
				$oq_internal++;
			}
			elseif ($survey_visit['SurveyVisit']['type'] == SURVEY_DUPE) {
				continue;
			}
			// everything else is essentially a NQ
			else {
				$nq++;
			}
		}
		
		if ($last_type != SURVEY_COMPLETED) {
			$ir = round(($complete / $click) , 2) * 100;
			$loi = 0;
			if ($click > 0) {
				$epc = round(($project['Project']['client_rate'] * $complete) / $click, 2);
			}
			$data_rows[] = array(
				'time' => round((strtotime($survey_visit['SurveyVisit']['created']) - $start) / 60),
				'complete' => $complete,
				'click' => $click, 
				'nq' => $nq, 
				'oq' => $oq, 
				'oq_internal' => $oq_internal, 
				'ir' => $ir.'%',
				'loi' => $loi,
				'epc' => $epc
			);
		}
		$this->set(compact('data_rows', 'start'));
	}
	
	public function download_true_sample($country_code = 'US', $num_of_records = 8000) {
				
		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => array(
					'truesample.sourceid'
				),
				'Setting.deleted' => false
			)
		));
		
		$users = $this->User->find('all', array(
			'conditions' => array(
				'User.deleted_on' => null,
				'QueryProfile.country' => $country_code,
				'User.extended_registration' => true,
				'User.active' => true
			),
			'limit' => $num_of_records,
			'fields' => array(
				'User.id',
				'User.firstname',
				'User.lastname',
				'User.email'
			),
			'order' => 'User.last_touched DESC'
		));
		
		$this->set(compact('users', 'settings'));
		$this->layout = null;		 
	}
	
	public function export_projects() {
		ini_set('memory_limit', '2048M');
		ini_set('max_execution_time', 600); // 5 minutes max execution time
		
		if ($this->request->is('post') || $this->request->is('put')) {
			$this->Project->unbindModel(array('hasMany' => array(
				'SurveyPartner',
				'ProjectOption',
				'ProjectAdmin'
			)));

			$conditions = array(
				'Project.date_created >=' => date(DB_DATE, strtotime($this->request->data['Report']['date_from'])).' 00:00:00',
				'Project.date_created <=' => date(DB_DATE, strtotime($this->request->data['Report']['date_to'])).' 23:59:59',
				'Project.router' => false
			); 
			
			if (isset($this->request->data['Report']['group_id']) && !empty($this->request->data['Report']['group_id'])) {
				$conditions['Project.group_id'] = $this->request->data['Report']['group_id'];
			}
			
			if (isset($this->request->data['Report']['filter_ids']) && !empty($this->request->data['Report']['filter_ids'])) {
				$conditions['Project.id'] = explode("\n", trim($this->request->data['Report']['filter_ids']));
			}
			$projects = $this->Project->find('all', array(
				'fields' => array(
					'Project.id',
					'Project.status',
					'Project.prj_name',
					'Client.client_name',
					'Group.key',
					'Project.mask', 
					'Group.name',
					'Project.date_created',
					'Project.started',
					'Project.ended',
					'Project.bid_ir',
					'Project.est_length',
					'Project.client_rate',
					'Project.user_payout',
					'Project.prescreen',
					'Project.language',
					'SurveyVisitCache.ir',
					'SurveyVisitCache.client_ir',
					'Project.epc',
					'Project.country',
					'SurveyVisitCache.complete',
					'SurveyVisitCache.loi_seconds',
					'SurveyVisitCache.click',
					'SurveyVisitCache.nq',
					'SurveyVisitCache.overquota',
					'SurveyVisitCache.oq_internal',
					'SurveyVisitCache.block',
					'SurveyVisitCache.speed',
					'SurveyVisitCache.fraud',
					'SurveyVisitCache.drops',
					'SurveyVisitCache.hidden_no_reason',
					'SurveyVisitCache.hidden_too_long',
					'SurveyVisitCache.hidden_too_small',
					'SurveyVisitCache.hidden_not_working',
					'SurveyVisitCache.hidden_do_not_want',
					'SurveyVisitCache.hidden_other',
					'SurveyVisitCache.prescreen_clicks',
					'SurveyVisitCache.prescreen_completes',
					'SurveyVisitCache.prescreen_nqs'
				),
				'conditions' => $conditions,
				'contain' => array(
					'Client',
					'Group',
					'SurveyVisitCache'
				),
			));
			if (!$projects) {
				$this->Session->setFlash('No projects in that time period.', 'flash_error'); 
			}
			else {
				$csv_rows = array(array(
					'Project ID', 
					'Project Mask',
					'US',
					'CA',
					'GB',
					'Status',
					'Project Name', 
					'Client',
					'Group',
					'Created',
					'Started',
					'Ended',
					'Minutes Live',
					'Bid IR',
					'Bid LOI',
					'Client Rate',
					'User Payout',
					'Prescreener',
					'Language',
					'Actual IR',
					'Client IR',
					'Drops',
					'Actual EPC',
					'Actual LOI',
					'Total Revenue',
					'Invites',
					'Clicks',
					'Completes',
					'NQs',
					'OQS',
					'OQ-I',
					'NQ-S',
					'NQ-F',
					'Dupe',
					'P-CL',
					'P-C',
					'P-NQ',
					'Hidden - No Reason',
					'Hidden - Too Long',
					'Hidden - Payout Too Small',
					'Hidden - Not Working',
					'Hidden - Do Not Want',
					'Hidden - Other'
				));
				foreach ($projects as $project) {
					if (isset($this->request->data['Report']['suppress_empty_clicks']) && $this->request->data['Report']['suppress_empty_clicks'] == 1 && empty($project['SurveyVisitCache']['click'])) {
						continue;
					}
					
					if ($project['Group']['key'] != 'fulcrum') {
						$count = $this->SurveyUser->find('count', array(
							'conditions' => array(
								'SurveyUser.survey_id' => $project['Project']['id']
							),
							'recursive' => -1
						));
					}
					else {
						$count = 0; 
					}
					
					$actual_ir = $actual_epc = $drops = 0;
					if ($project['SurveyVisitCache']['complete'] > 0) {
						$actual_ir = round($project['SurveyVisitCache']['complete'] / $project['SurveyVisitCache']['click'], 2);
						$actual_epc = round($actual_ir * $project['Project']['client_rate'], 2);
					}
					
					if ($project['SurveyVisitCache']['click'] > 0) {
						$drops = MintVine::drop_rate($project); 
					}
					
					if (!is_null($project['Project']['ended']) && !is_null($project['Project']['started'])) {
						$live_time_in_seconds = strtotime($project['Project']['ended']) - strtotime($project['Project']['started']);
						$live_time_in_minutes = round($live_time_in_seconds / 60); 
					}
					else {
						$live_time_in_minutes = '';
					}
					
					$csv_rows[] = array(
						$project['Project']['id'],
						$project['Project']['mask'],
						($project['Project']['country'] == 'US') ? 'Y': '-',
						($project['Project']['country'] == 'CA') ? 'Y': '-',
						($project['Project']['country'] == 'GB') ? 'Y': '-',
						$project['Project']['status'],
						$project['Project']['prj_name'],
						$project['Client']['client_name'],
						$project['Group']['name'],
						$project['Project']['date_created'],
						$project['Project']['started'],
						$project['Project']['ended'],
						$live_time_in_minutes,
						$project['Project']['bid_ir'],
						$project['Project']['est_length'],
						$project['Project']['client_rate'],
						$project['Project']['user_payout'],
						$project['Project']['prescreen'] ? 'Y' : 'N',
						$project['Project']['language'],
						$actual_ir * 100, // ir is in percentage
						!is_null($project['SurveyVisitCache']['client_ir']) ? $project['SurveyVisitCache']['client_ir'] : '',
						$drops > 0 ? $drops . '%': '',
						$actual_epc,
						round($project['SurveyVisitCache']['loi_seconds'] / 60),
						round($project['SurveyVisitCache']['complete'] * $project['Project']['client_rate'], 2),
						$count,
						$project['SurveyVisitCache']['click'],
						$project['SurveyVisitCache']['complete'],
						$project['SurveyVisitCache']['nq'],
						$project['SurveyVisitCache']['overquota'],
						$project['SurveyVisitCache']['oq_internal'],
						$project['SurveyVisitCache']['speed'],
						$project['SurveyVisitCache']['fraud'],
						$project['SurveyVisitCache']['block'],
						$project['SurveyVisitCache']['prescreen_clicks'],
						$project['SurveyVisitCache']['prescreen_completes'],
						$project['SurveyVisitCache']['prescreen_nqs'],
						$project['SurveyVisitCache']['hidden_no_reason'],
						$project['SurveyVisitCache']['hidden_too_long'],
						$project['SurveyVisitCache']['hidden_too_small'],
						$project['SurveyVisitCache']['hidden_not_working'],
						$project['SurveyVisitCache']['hidden_do_not_want'],
						$project['SurveyVisitCache']['hidden_other'],
					);
				}
				
				// temp output for csv
				$fp = fopen('php://temp', 'w+');
				foreach ($csv_rows as $csv_row) {
					fputcsv($fp, $csv_row);
				}
				rewind($fp); // Set the pointer back to the start
				$csv_contents = stream_get_contents($fp); // Fetch the contents of our CSV
				fclose($fp); // Close our pointer and free up memory and /tmp space

				// Handle/Output your final sanitised CSV contents
				if (date(DB_DATE, strtotime($this->request->data['Report']['date_from'])) != date(DB_DATE, strtotime($this->request->data['Report']['date_to']))) {
					$filename = 'project_export-'. date('d-m-Y', strtotime($this->request->data['Report']['date_from'])). '-' . date('d-m-Y', strtotime($this->request->data['Report']['date_to'])) . '.csv';
				}
				else {
					$filename = 'project_export-'. date('d-m-Y', strtotime($this->request->data['Report']['date_from'])) . '.csv';
				}

				$csv_file = fopen('php://output', 'w');
				header('Content-type: application/csv');
				header('Content-Disposition: attachment; filename="' . $filename . '"');

				fputs($csv_file, $csv_contents);

				fclose($csv_file);
				$this->autoRender = false;
				$this->layout = false;
				$this->render(false);
			}
		}
		
		$groups = $this->Group->find('list', array(
			'fields' => array('Group.id', 'Group.name'),
			'recursive' => -1,
			'order' => 'Group.name ASC'
		));
		$this->set(compact('groups'));
	}
	
	private function __get_partners() {
		$partners = $this->OfferRedemption->find('all', array(
			'fields' => array(
				'DISTINCT OfferRedemption.partner'
			),
			'recursive' => -1,
			'order' => 'OfferRedemption.partner ASC'
		));

		$partners_hash = array();
		foreach ($partners as $partner) {
			$partner_name = strtolower($partner['OfferRedemption']['partner']);
			$partners_hash[$partner['OfferRedemption']['partner']] = $partner_name;
		}

		return $partners_hash;
	}
	
	
	public function invites($user_id = null) {
		if (empty($user_id)) {
			$this->Session->setFlash('User not found', 'flash_error');
			$this->redirect(array('action' => 'index'));
		}
		
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
	
		if (isset($this->data['from']) && !empty($this->data['from'])) {
			$this->loadModel('NotificationLog');
			$conditions = array();
			if (isset($this->data['to']) && !empty($this->data['to'])) {
				$conditions['NotificationLog.created >='] = date(DB_DATE, strtotime($this->data['from']));
				$conditions['NotificationLog.created <='] = date(DB_DATE, strtotime($this->data['to']) + 86400);
			}
			else {
				$conditions['NotificationLog.created >='] = date(DB_DATE, strtotime($this->data['from'])).' 00:00:00';
				$conditions['NotificationLog.created <='] = date(DB_DATE, strtotime($this->data['from'])).' 23:59:59';
			}
			
			$conditions['NotificationLog.user_id'] = $user_id;
			$effective_invites_count = $this->NotificationLog->find('count', array(
				'conditions' => array_merge($conditions, array(
					'NotificationLog.status > 0'
				))
			));

			$this->NotificationLog->bindModel(array('belongsTo' => array('Project')));
			$this->paginate = array(
				'NotificationLog' => array(
					'contain' => array(
						'Project' => array(
							'fields' => array('user_payout', 'est_length', 'loi'),
							'SurveyVisitCache'
						)
					),
					'limit' => '50',
					'conditions' => $conditions
				)
			);
			$notification_logs = $this->paginate('NotificationLog');
			$this->set(compact('notification_logs', 'effective_invites_count'));
		}
	}
	
	public function inventory() {
		$distinct_partners = $this->ProjectCompleteHistory->find('all', array(
			'fields' => array('DISTINCT ProjectCompleteHistory.partner'),
			'recursive' => -1,
			'order' => 'ProjectCompleteHistory.partner ASC'
		));
		$partners = array();
		foreach ($distinct_partners as $distinct_partner) {
			$partners[] = $distinct_partner['ProjectCompleteHistory']['partner'];
		}
		
		if (!empty($partners)) {
			$partners = $this->Group->find('list', array(
				'fields' => array('key', 'name'),
				'conditions' => array(
					'key' => $partners
				),
				'order' => 'Group.name asc'
			));
		}

		$this->set(compact('partners'));
		
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		
		if (isset($this->data['date']) && !empty($this->data['date'])) {
			$export_to_csv = isset($this->data['export']) && $this->data['export'] == 1; 
			$save_inventory = strtotime($this->data['date']) < strtotime(date(DB_DATE));
			$date = date(DB_DATE, strtotime($this->data['date']));
			
			$csv_rows = array();
			if (!empty($this->data['partner'])) {
				$completes = array();
				$inventories = $this->Inventory->find('all', array(
					'conditions' => array(
						'Inventory.date' => $date,
						'Inventory.partner' => $this->data['partner']
					)
				));
				if ($inventories) {
					foreach ($inventories as $inventory) {
						$completes[] = array(
							'partner' => $inventory['Inventory']['partner'],
							'date' => $date,
							'min_completes' => $inventory['Inventory']['min_completes'],
							'max_completes' => $inventory['Inventory']['max_completes'],
						);
					}
					
					$cache_partners = Set::extract('/Inventory/partner', $inventories);
					$required_partners = array_diff($this->data['partner'], $cache_partners);
				}
				else {
					$required_partners = $this->data['partner'];
				}
				
				
				if (!empty($required_partners)) {
					$project_complete_histories = $this->ProjectCompleteHistory->find('all', array(
						'fields' => array('sum(min_completes) as min_completes', 'sum(max_completes) as max_completes', 'partner'),
						'conditions' => array(
							'ProjectCompleteHistory.date' => $date,
							'ProjectCompleteHistory.partner' => $required_partners
						),
						'group' => 'ProjectCompleteHistory.partner'
					));
					if ($project_complete_histories) {
						foreach ($project_complete_histories as $complete) {
							$inventory = array(
								'partner' => $complete['ProjectCompleteHistory']['partner'], 
								'date' => $date,
								'min_completes' => $complete[0]['min_completes'],
								'max_completes' => $complete[0]['max_completes'],
							);
							if ($save_inventory) {
								$this->Inventory->create();
								$this->Inventory->save(array('Inventory' => $inventory));
							}
							
							$completes[] = $inventory;
						}
					}
				}
				
				
				if ($export_to_csv) {
					$csv_rows = array(array(
						'Partner',
						'Date',
						'Min completes',
						'Max completes',
						'Difference',
					));
					foreach ($completes as $complete) {
						$csv_rows[] = array(
							(isset($partners[$complete['partner']])) ? $partners[$complete['partner']] : $complete['partner'],
							$complete['date'],
							$complete['min_completes'],
							$complete['max_completes'],
							$complete['max_completes'] - $complete['min_completes'],
						);
					}
				}

				$this->set(compact('completes'));
			}
			
			if (!empty($this->data['mv_router'])) {
				$inventory = $this->Inventory->find('first', array(
					'conditions' => array(
						'Inventory.date' => $date,
						'Inventory.partner' => 'mv_router'
					)
				));
				if ($inventory) {
					$inventory = $inventory['Inventory'];
				}
				else {
					$user_router_log_count = $this->UserRouterLog->find('count', array(
						'conditions' => array(
							'UserRouterLog.parent_id' => '0',
							'UserRouterLog.created >=' => $date.' 00:00:00',
							'UserRouterLog.created <=' => $date.' 23:59:59'
						)
					));
					$inventory = array(
						'partner' => 'mv_router', 
						'date' => $date,
						'total_values' => $user_router_log_count,
					);
					if ($save_inventory) {
						$this->Inventory->create();
						$this->Inventory->save(array('Inventory' => $inventory));
					}
				}
				
				if ($export_to_csv) {
					
					// add empty line
					$csv_rows[] = array();
					$csv_rows[] = array(
						'Partner',
						'Date',
						'Total Values',
					);
					$csv_rows[] = array(
						$inventory['partner'],
						$date,
						$inventory['total_values']
					);
				}
				
				$mv_router_inventory = $inventory;
				$this->set(compact('mv_router_inventory'));
			}
			
			if (!empty($this->data['ssi'])) {
				$inventory = $this->Inventory->find('first', array(
					'conditions' => array(
						'Inventory.date' => $date,
						'Inventory.partner' => 'ssi'
					)
				));
				if ($inventory) {
					$inventory = $inventory['Inventory'];
				}
				else {
					$ssi_link_count = $this->SsiLink->find('count', array(
						'conditions' => array(
							'SsiLink.created >=' => $date.' 00:00:00',
							'SsiLink.created >=' => $date.' 23:59:59'
						)
					));
					$inventory = array(
						'partner' => 'ssi', 
						'date' => $date,
						'total_values' => $ssi_link_count,
					);
					if ($save_inventory) {
						$this->Inventory->create();
						$this->Inventory->save(array('Inventory' => $inventory));
					}
				}
				
				if ($export_to_csv) {
					
					// add empty line
					$csv_rows[] = array();
					$csv_rows[] = array(
						'Partner',
						'Date',
						'Total Values',
					);
					$csv_rows[] = array(
						$inventory['partner'],
						$date,
						$inventory['total_values']
					);
				}

				$ssi_inventory = $inventory;
				$this->set(compact('ssi_inventory'));
			}
			
			if ($export_to_csv) {
				$filename = 'Inventory-' . $date . '.csv';
				$csv_file = fopen('php://output', 'w');
				header('Content-type: application/csv');
				header('Content-Disposition: attachment; filename="' . $filename . '"');

				foreach ($csv_rows as $row) {
					fputcsv($csv_file, $row, ',', '"');
				}

				fclose($csv_file);
				$this->autoRender = false;
				$this->layout = false;
				$this->render(false);
				return;
			}
			
			$this->set(compact('date'));
		}
	}
	
	public function terming_actions() {
		App::import('Model', 'GroupReport');
		$this->GroupReport = new GroupReport;
		if ($this->request->is(array('put', 'post'))) {
			if (empty($this->request->data['Report']['date_from']) || empty($this->request->data['Report']['date_to'])) {
				$this->Session->setFlash('Start date and end date both are required.', 'flash_error');
			}
			elseif (strtotime($this->request->data['Report']['date_from']) > strtotime($this->request->data['Report']['date_to'])) {
				$this->Session->setFlash('Start date must be less than end date.', 'flash_error');
			}
			elseif (empty ($this->request->data['Report']['group_key'])) {
				$this->Session->setFlash('Group is required.', 'flash_error');
			}
			elseif (empty ($this->request->data['Report']['term'])) {
				$this->Session->setFlash('Term is required.', 'flash_error');
			}
			elseif ($this->request->data['Report']['group_key'] == 'usurv' && $this->request->data['Report']['term'] != SURVEY_COMPLETED) {
				$this->Session->setFlash('Only "completes" report can be generated for Usurv', 'flash_error');
			}
			else {
				$date_from = $this->request->data['Report']['date_from'] . ' 00:00:00';
				$date_to = $this->request->data['Report']['date_to'] . ' 23:59:59';

				$start_date = date(DB_DATETIME, strtotime($date_from));
				$end_date = date(DB_DATETIME, strtotime($date_to));

				$group = $this->Group->find('first', array(
					'conditions' => array(
						'Group.key' => $this->request->data['Report']['group_key']
					)
				));

				$conditions = array(
					'Project.group_id' => $group['Group']['id'],
					'OR' => array(
						// projects started before and ended after selected dates
						array(
							'Project.started <=' => $start_date . ' 00:00:00',
							'Project.ended >=' => $start_date . ' 23:59:59'
						),
						// projects started and ended during the duration of the selected date
						array(
							'Project.started >=' => $start_date . ' 00:00:00',
							'Project.ended <=' => $end_date . ' 23:59:59'
						),
						// projects started before the end date but ending much later
						array(
							'Project.started <=' => $end_date . ' 23:59:59',
							'Project.ended >=' => $end_date . ' 23:59:59'
						),
						// projects that are still open
						array(
							'Project.started <=' => $end_date . ' 23:59:59',
							'Project.ended is null'
						),
						// addressing https://basecamp.com/2045906/projects/1413421/todos/206702078
						array(
							'Project.ended LIKE' => $end_date . '%'
						)
					)
				);

				$projects = $this->Project->find('count', array(
					'conditions' => $conditions,
					'recursive' => -1
				));
				if ($projects > 0) {
					$groupReportSource = $this->GroupReport->getDataSource();
					$groupReportSource->begin();
					$this->GroupReport->create();
					$this->GroupReport->save(array('GroupReport' => array(
						'date_from' => $start_date,
						'date_to' => $end_date,
						'group_key' => $this->request->data['Report']['group_key'],
						'term' => $this->request->data['Report']['term'],
						'status' => 'queued',
						'user_id' => $this->current_user['Admin']['id']
					)));
					$group_report_id = $this->GroupReport->getInsertId();
					$groupReportSource->commit();

					$query = ROOT . '/app/Console/cake report terming_actions ' . $group_report_id;
					$query.= " > /dev/null &";
					exec($query, $output);

					$this->Session->setFlash('Report being generated - please wait for 10-15 minutes to download report.', 'flash_success');
					$this->redirect(array('controller' => 'reports', 'action' => 'terming_actions'));
				}
				else {
					$this->Session->setFlash('No active projects found within selected dates.', 'flash_error');
				}
			}
		}

		$this->GroupReport->bindModel(array(
			'belongsTo' => array(
				'Admin' => array(
					'foreignKey' => 'user_id',
					'fields' => array('id', 'admin_user')
				)
			)
		));

		$limit = 50;
		$paginate = array(
			'GroupReport' => array(
				'contain' => array(
					'Admin'
				),
				'limit' => $limit,
				'order' => 'GroupReport.id DESC',
			)
		);
		
		$this->paginate = $paginate;
		$this->set('reports', $this->paginate('GroupReport'));
		
		$groups = $this->Group->find('list', array(
			'fields' => array('key', 'name'),
			'order' => 'Group.name ASC'
		));
		$this->set(compact('groups'));
	}
	
	public function download_group_report($report_id) {
		if (empty($report_id)) {
			throw new NotFoundException();
		}
		App::import('Model', 'GroupReport');
		$this->GroupReport = new GroupReport;
		$report = $this->GroupReport->find('first', array(
			'conditions' => array(
				'GroupReport.id' => $report_id
			),
			'fields' => array(
				'id', 'status', 'path'
			)
		));

		if ($report) {
			if ($report['GroupReport']['status'] == 'complete') {
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

				CakePlugin::load('Uploader');
				App::import('Vendor', 'Uploader.S3');

				$file = $report['GroupReport']['path'];

				// we store with first slash; but remove it for S3
				if (substr($file, 0, 1) == '/') {
					$file = substr($file, 1, strlen($file));
				}

				$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);
				$url = $S3->getAuthenticatedURL($settings['s3.bucket'], $file, 3600, false, false);

				$this->redirect($url);
			}
			else {
				$this->Session->setFlash('A report is already being generated - please wait until it is done.', 'flash_error');
				$this->redirect(array(
					'controller' => 'reports',
					'action' => 'terming_actions'
				));
			}
		}
		else {
			throw new NotFoundException();
		}
	}
	
	public function ajax_check_group_report($report_id) {
		App::import('Model', 'GroupReport');
		$this->GroupReport = new GroupReport;
		$report = $this->GroupReport->findById($report_id);
		return new CakeResponse(array(
			'body' => json_encode(array(
				'status' => $report['GroupReport']['status'],
				'file' => Router::url(array('controller' => 'reports', 'action' => 'download_group_report', $report['GroupReport']['id']))
			)),
			'type' => 'json',
			'status' => '201'
		));
	}
	
	public function user_export_statistics() {
		$this->loadModel('PartnerLog');
		if (isset($this->request->query['date']) && !empty($this->request->query['date'])) {
			$date = date(DB_DATE, strtotime($this->request->query['date']));
		}
		else {
			$date = date(DB_DATE);
		}
		
		$this->loadModel('UserExportStatistic');
		$statistics = $this->UserExportStatistic->find('all', array(
			'conditions' => array(
				'UserExportStatistic.date' => $date,
			),
			'order' => 'UserExportStatistic.days'
		));
		$groups = array(
			'cint',
			'precision',
			'toluna',
			'mintvine_lucid',
			'mintvine_core'
		);
		$this->set(compact('statistics', 'groups'));
	}
	
	public function lucid_epc_statistics() {
		$reported_projects = array();
		
		$lucid_group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'fulcrum'
			),
			'recursive' => -1
		)); 
		
		// determine projects with this EPC or higher
		$epc_threshold = 0.14; 
		
		if (isset($this->request->query['date'])) {
			$date = date(DB_DATE, strtotime(trim($this->request->query['date'])));
					
			$start_date = $date . ' 00:00:00';
			$end_date = $date . ' 23:59:59';
			$conditions = array(
				'Group.key' => 'fulcrum',
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
					array(
						'Project.ended LIKE' => $date . '%'
					)
				)
			);
			$this->Project->unbindModel(array(
				'hasMany' => array('SurveyPartner', 'ProjectOption', 'ProjectAdmin')
			));
			$projects = $this->Project->find('all', array(
				'fields' => array(
					'Project.id', 
					'Project.mask',
					'Project.started', 
					'Project.ended', 
					'Project.client_rate',
					'Client.do_not_autolaunch',
					'Group.key', 
					'SurveyVisitCache.click', 
					'SurveyVisitCache.complete'
				),
				'conditions' => $conditions
			));
		
			if ($projects) {
				$project_ids = array();
				foreach ($projects as $project) {
					// skip projects that we performed on a different day; simply means we exhausted inventory
					if ($project['SurveyVisitCache']['complete'] > 0 && $project['SurveyVisitCache']['click'] > 0) {
						$epc = ($project['SurveyVisitCache']['complete'] / $project['SurveyVisitCache']['click']) * $project['Project']['client_rate']; 
						if ($epc >= $epc_threshold) {
							continue;
						}
					}
					
					// skip projects we are not participating on
					if ($project['Client']['do_not_autolaunch']) {
						continue;
					}
				
					$survey_visits = $this->SurveyVisit->find('list', array(
						'fields' => array('SurveyVisit.id', 'SurveyVisit.result'),
						'conditions' => array(
							'SurveyVisit.survey_id' => $project['Project']['id'],
							'SurveyVisit.created >=' => $start_date,
							'SurveyVisit.created <=' => $end_date,
							'SurveyVisit.type' => SURVEY_CLICK
						)
					));
				
					if (!$survey_visits) {
						continue;
					}
				
					$counts = array_count_values($survey_visits);
					$completes = isset($counts[SURVEY_COMPLETED]) ? $counts[SURVEY_COMPLETED] : 0;
					if ($completes > 0) {
						continue;
					}
				
					// get the highest value
					$lucid_epc_statistics = $this->LucidEpcStatistic->find('first', array(
						'conditions' => array(
							'LucidEpcStatistic.project_id' => $project['Project']['id'],
							'LucidEpcStatistic.trailing_epc_cents >=' => $epc_threshold * 100,
							'LucidEpcStatistic.created >=' => $start_date,
							'LucidEpcStatistic.created <=' => $end_date,
						),
						'order' => 'LucidEpcStatistic.trailing_epc_cents DESC'
					)); 
				
					if (!$lucid_epc_statistics) {
						continue;
					}
					$reported_projects[] = $project + $lucid_epc_statistics; 
					$project_ids[] = $project['Project']['id'];
				}
			}
			else {
				$this->Session->setFlash('Projects not found in the selected date', 'flash_error');
			}
		}
		$this->set(compact('reported_projects', 'epc_threshold', 'lucid_group', 'project_ids', 'date'));
	}
	
	public function long_nq_oq() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$start_date = $this->data['Report']['start_date']['year'].'-'. 
				$this->data['Report']['start_date']['month'].'-'.
				$this->data['Report']['start_date']['day'];
			
			$end_date = $this->data['Report']['end_date']['year'].'-'. 
				$this->data['Report']['end_date']['month'].'-'.
				$this->data['Report']['end_date']['day'];
			
			$group_id = $this->data['Report']['group_key'];

			// Needed to get the project loi for comparison 
			$projects = $this->Project->find('all', array(
				'contain' => array(
					'SurveyVisitCache',
					'Client'
				),
				'fields' => array(
					'Project.id',
					'SurveyVisitCache.click',
					'SurveyVisitCache.nq',
					'SurveyVisitCache.overquota',
					'SurveyVisitCache.complete',
					'SurveyVisitCache.loi_seconds',
					'Client.client_name',
				),
				'conditions' => array(
					'Project.group_id' => $group_id,
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
						)
					)
				),
			));
			
			$mintvine_partner = $this->Partner->find('first', array(
				'fields' => array('Partner.id'),
				'conditions' => array(
					'Partner.key' => 'mintvine'
				)
			));
			
			$long_oq_and_nqs = array();
			if ($projects) {
				foreach ($projects as $key => $project) {
					$projects[$project['Project']['id']] = $project;
					unset($projects[$key]);
				}

				$survey_visits = $this->SurveyVisit->find('all', array(
					'fields' => array('SurveyVisit.id', 'SurveyVisit.survey_id', 'SurveyVisit.partner_user_id', 'SurveyVisit.result_id', 'SurveyVisit.hash', 'SurveyVisit.created', 'SurveyVisit.modified'),
					'conditions' => array(
						'SurveyVisit.survey_id' => Hash::extract($projects, '{n}.Project.id'),
						'SurveyVisit.type' => SURVEY_CLICK,
						'SurveyVisit.result' => array(SURVEY_NQ, SURVEY_OVERQUOTA),
						'SurveyVisit.partner_id' => $mintvine_partner['Partner']['id']
					),
					'recursive' => -1
				));
				if ($survey_visits) {
					$this->loadModel('Loi');
					$lois = $this->Loi->find('all', array(
						'conditions' => array(
							'Loi.survey_id' => Hash::extract($projects, '{n}.Project.id')
						),
						'fields' => array(
							'Loi.survey_id',
							'Loi.loi_seconds',
							'Loi.type',
						)
					));

					foreach ($lois as $key => $loi) {
						$lois[$loi['Loi']['survey_id']][] = $loi;
						unset($lois[$key]);
					}

					foreach ($survey_visits as $survey_visit) {
						$diff_seconds = strtotime($survey_visit['SurveyVisit']['modified']) - strtotime($survey_visit['SurveyVisit']['created']);
						if ($diff_seconds <= ($this->request->data['Report']['threshold'] * 60)) {
							continue;
						}
						
						// there are cases where multiple clicks will not link the correct "last" click entry to the NQ; so find the last real click
						if ($diff_seconds >= 300) {
							$survey_click = $this->SurveyVisit->find('first', array(
								'fields' => array('SurveyVisit.created'),
								'conditions' => array(
									'SurveyVisit.type' => SURVEY_CLICK,
									'SurveyVisit.hash' => $survey_visit['SurveyVisit']['hash'],
									'SurveyVisit.id <=' => $survey_visit['SurveyVisit']['result_id'],
									'SurveyVisit.survey_id' => $survey_visit['SurveyVisit']['survey_id']
								),
								'order' => 'SurveyVisit.created DESC'
							));
							if ($survey_click) {
								$diff_seconds = strtotime($survey_visit['SurveyVisit']['modified']) - strtotime($survey_click['SurveyVisit']['created']);
								if ($diff_seconds <= ($this->request->data['Report']['threshold'] * 60)) {
									continue;
								}
							}
						}

						list($survey_id, $user_id) = explode('-', $survey_visit['SurveyVisit']['partner_user_id']);
						if (!isset($projects[$survey_id]['States']) || empty($projects[$survey_id]['States'])) {
							$states = array(
								'nq_3' => 0,
								'oq_3' => 0,
								'nq_actual_loi' => 0,
								'oq_actual_loi' => 0,
								'nq_oq_100' => 0,
							);
							if (isset($lois[$survey_id])) {
								foreach ($lois[$survey_id] as $loi) {
									if ($loi['Loi']['type'] == SURVEY_NQ) {
										
										//# of NQ's > 3 min 
										if ($loi['Loi']['loi_seconds'] > 180) {
											$states['nq_3']++;
										}
										
										//# of NQ's > actual LOI
										if ($loi['Loi']['loi_seconds'] > $projects[$survey_id]['SurveyVisitCache']['loi_seconds']) {
											$states['nq_actual_loi']++;
										}
									}
									
									if ($loi['Loi']['type'] == SURVEY_OVERQUOTA) {
										if ($loi['Loi']['loi_seconds'] > 180) {
											$states['oq_3']++;
										}
										
										if ($loi['Loi']['loi_seconds'] > $projects[$survey_id]['SurveyVisitCache']['loi_seconds']) {
											$states['oq_actual_loi']++;
										}
									}

									//# of NQ's/OQ's that are recorded > 100 min
									if (in_array($loi['Loi']['type'], array(SURVEY_NQ, SURVEY_OVERQUOTA)) && $loi['Loi']['loi_seconds'] > 6000) {
										$states['nq_oq_100']++;
									}
								}
							}
							
							$projects[$survey_id]['States'] = $states;
						}
						
						$project = $projects[$survey_id];

						$long_oq_and_nqs[] = array(
							$survey_visit['SurveyVisit']['created'],
							$survey_visit['SurveyVisit']['survey_id'],
							$user_id,
							$survey_visit['SurveyVisit']['hash'],
							$diff_seconds,
							round($diff_seconds / 60),
							$project['SurveyVisitCache']['click'],
							$project['SurveyVisitCache']['nq'],
							$project['SurveyVisitCache']['overquota'],
							$project['SurveyVisitCache']['complete'],
							$project['Client']['client_name'],
							round($project['SurveyVisitCache']['loi_seconds'] / 60),
							$project['States']['nq_3'],
							$project['States']['nq_3'] > 0 ? round(($project['States']['nq_3'] / $project['SurveyVisitCache']['nq']), 2) * 100 . '%' : '0%', //Percent of total NQ's that are > 3 min
							$project['States']['oq_3'],
							$project['States']['oq_3'] > 0 ? round(($project['States']['oq_3'] / $project['SurveyVisitCache']['overquota']), 2) * 100 . '%' : '0%', //Percent of total OQ's that are > 3 min
							$project['States']['nq_actual_loi'],
							$project['States']['nq_actual_loi'] > 0 ? round(($project['States']['nq_actual_loi'] / $project['SurveyVisitCache']['nq']), 2) * 100 . '%' : '0%', //Percent of total NQ's that are > actual LOI
							$project['States']['oq_actual_loi'],
							$project['States']['oq_actual_loi'] > 0 ? round(($project['States']['oq_actual_loi'] / $project['SurveyVisitCache']['overquota']), 2) * 100 . '%' : '0%', //Percent of total OQ's that are > actual LOI
							$project['States']['nq_oq_100']
						);
					}
				}
			}
			else {
				$this->Session->setFlash('Projects were not found for your group and or start and end date.', 'flash_error');
			}

			if (!empty($long_oq_and_nqs)) {
				// create the csv now please
				$csv_rows = array_merge(array(array(
					'Timestamp',
					'Project',
					'User',
					'Hash',
					'LOI (seconds)',
					'LOI (minutes)',
					'Clicks',
					'NQs',
					'OQs',
					'Completes',
					'Client',
					'Actual LOI',
					'NQ\'s > 3 min',
					'Percent of total NQ\'s that are > 3 min',
					'OQ\'s > 3 min',
					'Percent of total OQ\'s that are > 3 min',
					'NQ\'s > actual LOI',
					'Percent of total NQ\'s > Actual LOI',
					'OQ\'s > actual LOI',
					'Percent of total OQ\'s > Actual LOI',
					'NQ\'s/OQ\'s that are recorded > 100 min'
				)), $long_oq_and_nqs);

				$group = $this->Group->find('first', array(
					'fields' => array('Group.name'),
					'conditions' => array(
						'Group.id' => $group_id
					)
				));
				$filename = strtolower($group['Group']['name']).'-'.date('Y/m/d', strtotime($start_date)).' - '.date('Y/m/d', strtotime($end_date)).'.csv';
				$csv_file = fopen('php://output', 'w');
				header('Content-type: application/csv');
				header('Content-Disposition: attachment; filename="' . $filename . '"');
				foreach ($csv_rows as $row) {
					fputcsv($csv_file, $row, ',', '"');
				}

				fclose($csv_file);
				$this->autoRender = false;
				$this->layout = false;
				$this->render(false);
				return;
			}
		}

		// Need to return groups every time or else an error occurs
		$groups = $this->Group->find('list', array(
			'fields' => array('Group.id', 'Group.name'),
			'recursive' => -1,
			'order' => 'Group.name ASC'
		));

		$this->set(compact('groups'));
	}

	public function complete_outliers() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$start_date = $this->data['Report']['start_date']['year'].'-'. 
				$this->data['Report']['start_date']['month'].'-'.
				$this->data['Report']['start_date']['day'];
			
			$end_date = $this->data['Report']['end_date']['year'].'-'. 
				$this->data['Report']['end_date']['month'].'-'.
				$this->data['Report']['end_date']['day'];
			
			$group_id = $this->data['Report']['group_key'];

			// Needed to get the project loi for comparison 
			$projects = $this->Project->find('all', array(
				'fields' => array('Project.id'),
				'conditions' => array(
					'Project.group_id' => $group_id,
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
						)
					)
				),
			));
			
			$mintvine_partner = $this->Partner->find('first', array(
				'fields' => array('Partner.id'),
				'conditions' => array(
					'Partner.key' => 'mintvine'
				)
			));
			
			$completes = array();
			$SURVEY_STATUSES = unserialize(SURVEY_STATUSES);
			if ($projects) {
				$survey_visits = $this->SurveyVisit->find('all', array(
					'fields' => array('SurveyVisit.id', 'SurveyVisit.survey_id', 'SurveyVisit.partner_user_id', 'SurveyVisit.result_id', 'SurveyVisit.hash', 'SurveyVisit.created', 'SurveyVisit.modified'),
					'conditions' => array(
						'SurveyVisit.survey_id' => Hash::extract($projects, '{n}.Project.id'),
						'SurveyVisit.type' => SURVEY_CLICK,
						'SurveyVisit.result' => SURVEY_COMPLETED,
						'SurveyVisit.partner_id' => $mintvine_partner['Partner']['id']
					),
					'recursive' => -1
				));
				if ($survey_visits) {
					foreach ($survey_visits as $survey_visit) {
						$loi = strtotime($survey_visit['SurveyVisit']['modified']) - strtotime($survey_visit['SurveyVisit']['created']);
						list($survey_id, $user_id) = explode('-', $survey_visit['SurveyVisit']['partner_user_id']);
						$survey_id = $survey_visit['SurveyVisit']['survey_id'];
						$completes[$survey_id] = array(
							$survey_visit['SurveyVisit']['created'],
							$survey_id,
							$user_id,
							$survey_visit['SurveyVisit']['hash'],
							$loi
						); 
						$projects_lois[$survey_id][] = $loi;
					}
				}

				$average_loi = array();
				foreach ($projects_lois as $survey_id => $lois) {
					$average_loi[$survey_id] = array_sum($lois) / count($lois);
				}

				$complete_outliers = array();
				$percentage_diff = $this->data['Report']['threshold'] /  100;
				foreach ($completes as $survey_id => $complete) {
					if (isset($average_loi[$survey_id])) {
						if ($average_loi[$survey_id] > $complete[4]) {
							$weight = abs($complete[4] - $average_loi[$survey_id]);
						}
						else {
							$weight = abs($average_loi[$survey_id] - $complete[4]);
						}

						$weight /= $average_loi[$survey_id];
						if ($weight >= $percentage_diff) {
							$complete[5] = number_format($average_loi[$survey_id], 2);
							$complete[6] = number_format($complete[4] - $average_loi[$survey_id], 2);
							$complete[4] = number_format($complete[4]);
							$complete_outliers[] = $complete;
						}
					}
				}

			}
			else {
				$this->Session->setFlash('Projects were not found for your group and or start and end date.', 'flash_error');
			}

			if (!empty($complete_outliers)) {
				// create the csv now please
				$csv_rows = array_merge(array(array(
					'Timestamp',
					'Project',
					'User',
					'Hash',
					'LOI (seconds)',
					'Average Project LOI (seconds)',
					'LOI Diff (seconds)'
				)), $complete_outliers);

				$group = $this->Group->find('first', array(
					'fields' => array('Group.name'),
					'conditions' => array(
						'Group.id' => $group_id
					)
				));

				$filename = strtolower($group['Group']['name']).'-completes-percentage_'.$percentage_diff.'-'.date('Y/m/d', strtotime($start_date)).' - '.date('Y/m/d', strtotime($end_date)).'.csv';
				$csv_file = fopen('php://output', 'w');
				header('Content-type: application/csv');
				header('Content-Disposition: attachment; filename="' . $filename . '"');
				foreach ($csv_rows as $row) {
					fputcsv($csv_file, $row, ',', '"');
				}

				fclose($csv_file);
				$this->autoRender = false;
				$this->layout = false;
				$this->render(false);
				return;
			}
		}

		// Need to return groups every time or else an error occurs
		$groups = $this->Group->find('list', array(
			'fields' => array('Group.id', 'Group.name'),
			'recursive' => -1,
			'order' => 'Group.name ASC'
		));

		$this->set(compact('groups'));
	}


	public function panelist_vs_survey_visits() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$start_date = $this->data['Report']['start_date']['year'].'-'. 
				$this->data['Report']['start_date']['month'].'-'.
				$this->data['Report']['start_date']['day'];
			
			$end_date = $this->data['Report']['end_date']['year'].'-'. 
				$this->data['Report']['end_date']['month'].'-'.
				$this->data['Report']['end_date']['day'];
			
			if (strtotime($start_date) > strtotime($end_date)) {
				$this->Session->setFlash('Start date must be less than end date.', 'flash_error');
			}
			elseif (empty($this->request->data['Report']['group_key'])) {
				$this->Session->setFlash('Group is required.', 'flash_error');
			}
			elseif (empty($this->request->data['Report']['threshold'])) {
				$this->Session->setFlash('Threshold is required.', 'flash_error');
			}
			else {
				$group_id = $this->data['Report']['group_key'];
				$threshold = $this->request->data['Report']['threshold'];

				$reportSource = $this->Report->getDataSource();
				$reportSource->begin();
				$this->Report->create();
				$this->Report->save(array('Report' => array(
					'user_id' => $this->current_user['Admin']['id'],
					'status' => 'queued',
					'path' => null
				)));
				$report_id = $this->Report->getInsertId();
				$reportSource->commit();

				$query = ROOT . '/app/Console/cake report panelist_vs_survey_visits ' . $start_date . ' ' . $end_date . ' ' . $group_id . ' ' . $threshold . ' ' . $report_id;
				$query .= " > /dev/null &"; 
				exec($query, $output);
				CakeLog::write('report_commands', $query);

				$this->Session->setFlash('We are generating your report - check the status below.', 'flash_success');
				$this->redirect(array('controller' => 'reports', 'action' => 'index', '?' => array('type' => 'panelist_vs_survey_visits')));
			}
		}

		$groups = $this->Group->find('list', array(
			'fields' => array('Group.id', 'Group.name'),
			'recursive' => -1,
			'order' => 'Group.name ASC'
		));

		$this->set(compact('groups'));
	}

	public function qualification_users($qualification_id) {
		if (!$qualification_id) {
			throw new NotFoundException(__('Invalid Qualification'));
		}
		
		$models_to_load = array('Qualification', 'QualificationUser', 'Question', 'Answer', 'QeUser', 'LucidZip');
		foreach ($models_to_load as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}

		$emailed = isset($this->request->query['emailed']) && $this->request->query['emailed'];
		$qualification = $this->Qualification->find('first', array(
			'fields' => array('Qualification.project_id', 'Qualification.query_json'),
			'conditions' => array(
				'Qualification.id' => $qualification_id
			),
			'recursive' => -1
		));
		if (!$qualification) {
			throw new NotFoundException(__('Invalid Qualification'));
		}
		
		$this->QualificationUser->bindModel(array(
			'belongsTo' => array(
				'User' => array(
					'foreignKey' => 'user_id',
				)
			)
		));
		$conditions = array(
			'QualificationUser.qualification_id' => $qualification_id,
			'QualificationUser.deleted' => false
		);
		$type = 'Invited';
		if ($emailed) {
			$conditions['QualificationUser.notification'] = true;
			$type = 'Emailed';
		}
		
		$this->User->bindModel(array(
			'hasOne' => array('QeUser')
		));
		$qualification_users = $this->QualificationUser->find('all', array(
			'conditions' => $conditions,
			'contain' => array(
				'User' => array(
					'QeUser'
				)
			),
			'fields' => array(
				'QualificationUser.user_id',
				'QualificationUser.notification_timestamp',
				'QualificationUser.created',
				'User.email',
				'User.last_touched'
			),
			'recursive' => -1
		));
		if (!$qualification_users) {
			$this->Session->setFlash('Invited users not found.', 'flash_error');
			$this->redirect($this->referer());
		}
		
		if (!empty($qualification['Qualification']['query_json'])) {
			$json = json_decode($qualification['Qualification']['query_json'], true);
			$partner = $json['partner'];
			$open_ended_questions = $this->Question->find('list', array(
				'conditions' => array(
					'Question.question_type' => QUESTION_TYPE_NUMERIC_OPEN_END,
					'Question.partner' => $partner
				),
				'fields' => array('Question.partner_question_id', 'Question.partner_question_id'),
				'recursive' => -1
			));
			$question_ids = array();
			$questions = array();
			ksort($json['qualifications']);
			foreach ($json['qualifications'] as $question_id => $answer_ids) {
				$conditions = array();
				if (isset($json['qualifications']['country'])) {
					$conditions['QuestionText.country'] = $json['qualifications']['country'];
				}
				
				$this->Question->bindModel(array(
					'hasOne' => array(
						'QuestionText' => array(
							'fields' => array('QuestionText.id', 'QuestionText.text'),
							'conditions' => $conditions
						)
					)
				));

				$question = $this->Question->find('first', array(
					'fields' => array('Question.question', 'Question.partner_question_id'),
					'conditions' => array(
						'Question.partner_question_id' => $question_id,
						'Question.partner' => $partner
					),
					'order' => 'Question.partner_question_id asc',
					'contain' => array(
						'QuestionText',
					)
				));
				if ($question) {
					$questions[$question['Question']['partner_question_id']] = $question['QuestionText']['text'];
				}
				else {
					$questions[$question_id] = $question_id;
				}
			}
		}
		
		$csv_row = array(
			'User ID',
			'Email',
			'Last touched',
			$type
		);
		
		foreach ($questions as $question) {
			$csv_row[] = ucfirst($question); 
		}
	
		$csv_rows = array($csv_row);

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
		
		$http = new HttpSocket(array(
			'timeout' => 30,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		
		foreach ($qualification_users as $qualification_user) {
			$csv_row = array(
				$qualification_user['QualificationUser']['user_id'],
				$qualification_user['User']['email'],
				$qualification_user['User']['last_touched'],
				($emailed) ? $qualification_user['QualificationUser']['notification_timestamp'] : $qualification_user['QualificationUser']['created'],
			);
			
			if (empty($qualification_user['User']['QeUser'])) {
				// get QE2 profile
				$http->configAuth('Basic', $settings['qe.mintvine.username'], $settings['qe.mintvine.password']);
				try {
					$return = $http->get($settings['hostname.qe'] . '/qualifications/' . $qualification_user['User']['id']);
					if ($return->code == 200) {
						$qualifications = json_decode($return->body, true);
						$this->QeUser->create();
						$this->QeUser->save(array('QeUser' => array(
							'user_id' => $qualification_user['User']['id'],
							'value' => json_encode($qualifications)
						)));
						$qualification_user['User']['QeUser']['value'] = json_encode($qualifications);
					}
				} 
				catch (\HttpException $ex) {
					CakeLog::write('qe.user.qualification', $ex->getMessage());
				} 
				catch (\Exception $ex) {
					CakeLog::write('qe.user.qualification', $ex->getMessage());
				}
			}
			
			if ($questions && !empty($qualification_user['User']['QeUser']['value'])) {
				$answers_ids = array();
				$json = json_decode($qualification_user['User']['QeUser']['value'], true);
				if (isset($json['answered'][$partner])) {
					foreach ($json['answered'][$partner] as $question_id => $answers) {
						if (isset($questions[$question_id])) {
							$answers_ids[$question_id] = $answers;
						}
					}
				}
				
				$this->Answer->bindModel(array('hasOne' => array(
					'AnswerText' => array(
						'fields' => array('AnswerText.text')
					)
				)));
				
				$ans_conditions = array();
				if (isset($json['qualifications']['country'])) {
					$ans_conditions['AnswerText.country'] = $json['qualifications']['country'];
				}

				$this->Question->bindModel(array(
					'hasMany' => array(
						'Answer' => array(
							'foreignKey' => 'question_id',
							'conditions' => array(
								'Answer.ignore' => false,
								'Answer.question_id' => 'Question.id'
							)
						)
					),
				));
				
				$answers = array();
				foreach ($questions as $question_id => $question_text) {
					if (!isset($answers_ids[$question_id])) {
						$answers[] = '';
						continue;
					}
					
					// for performance, don't run extra query if this check is met
					if ($partner == 'lucid' && ($question_id == 'country' || isset($open_ended_questions[$question_id]))) {
						$answers[] = implode(', ', $answers_ids[$question_id]);
						continue;
					}

					$answer = $this->Question->find('first', array(
						'fields' => array('Question.question', 'Question.partner_question_id'),
						'conditions' => array(
							'Question.partner_question_id' => $question_id
						),
						'order' => 'Question.partner_question_id asc',
						'contain' => array(
							'Answer' => array(
								'fields' => array('Answer.id'),
								'conditions' => array(
									'Answer.ignore' => false,
									'Answer.partner_answer_id' => $answers_ids[$question_id]
								),
								'AnswerText' => array(
									'fields' => array('AnswerText.text'),
									'conditions' => $ans_conditions
								)
							)
						)
					));
					if ($answer && !empty($answer['Answer'])) {
						$answer_texts = Set::extract('/Answer/AnswerText/text', $answer); 
						$answers[] = implode(', ', $answer_texts);
					}
					else {
						if ($question_id == 98) {
							$lucid_zips = $this->LucidZip->find('all', array(
								'fields' => array(
									'LucidZip.state_fips', 'LucidZip.county_fips', 'LucidZip.county'
								),
								'conditions' => array(
									'CONCAT(LPAD(LucidZip.state_fips, 2, 0), LPAD(LucidZip.county_fips, 3, 0))' => $answers_ids[$question_id]
								),
								'group' => array('LucidZip.state_fips', 'LucidZip.county_fips'),
								'order' => 'LucidZip.county ASC'
							));
							if ($lucid_zips) {
								$counties = array();
								foreach ($lucid_zips as $lucid_zip) {
									$counties[] = $lucid_zip['LucidZip']['county'];
								}	
								$answers[] = implode(', ', $counties);
							}
							else {
								$answers[] = implode(', ', $answers_ids[$question_id]);	
							}
						}
						else {
							$answers[] = implode(', ', $answers_ids[$question_id]);
						}
					}
				}

				foreach ($answers as $answer) {
					$csv_row[] = $answer;
				}
			}
			$csv_rows[] = $csv_row;
		}

		$filename = $qualification['Qualification']['project_id'].'-'.$type.'.csv';
		$csv_file = fopen('php://output', 'w');
		header('Content-type: application/csv');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		foreach ($csv_rows as $row) {
			fputcsv($csv_file, $row, ',', '"');
		}

		fclose($csv_file);
		$this->autoRender = false;
		$this->layout = false;
		$this->render(false);
		return;
	}
	
	public function survey_coverage($qualification_id) {
		if (!$qualification_id) {
			throw new NotFoundException(__('Invalid Qualification'));
		}
		
		$this->loadModel('Qualification');
		$this->loadModel('QualificationUser');
		
		$this->Qualification->bindModel(array('belongsTo' => array(
			'Project' => array(
				'type' => 'INNER'
			)
		))); 
		$qualification = $this->Qualification->find('first', array(
			'fields' => array('Project.id', 'Project.prj_name', 'Qualification.project_id'),
			'conditions' => array(
				'Qualification.id' => $qualification_id
			)
		));
		if (!$qualification) {
			throw new NotFoundException(__('Invalid Qualification'));
		}
		
		$this->QualificationUser->bindModel(array(
			'belongsTo' => array(
				'User' => array(
					'foreignKey' => 'user_id',
				)
			)
		));
		$conditions = array(
			'QualificationUser.qualification_id' => $qualification_id,
			'QualificationUser.deleted' => false
		);
		$this->paginate = array(
			'QualificationUser' => array(
				'contain' => array('User'),
				'fields' => array(
					'QualificationUser.user_id',
					'QualificationUser.notification_timestamp',
					'QualificationUser.created',
					'User.email',
					'User.last_touched'
				),
				'conditions' => $conditions,
				'limit' => '200',
				'order' => 'User.last_touched DESC'
			)
		);
		$qualification_users = $this->paginate('QualificationUser');
		if ($qualification_users) {
			foreach ($qualification_users as $key => $qualification_user) {
				$user_survey_visit = $this->SurveyUserVisit->find('first', array(
					'recursive' => -1,
					'conditions' => array(
						'SurveyUserVisit.user_id' => $qualification_user['QualificationUser']['user_id'],
						'SurveyUserVisit.survey_id' => $qualification['Qualification']['project_id']
					),
					'order' => 'SurveyUserVisit.id DESC'
				)); 
				if ($user_survey_visit) {
					$qualification_users[$key]['SurveyUserVisit'] = $user_survey_visit['SurveyUserVisit']; 
				}				
			
				$user_router_logs = $this->UserRouterLog->find('list', array(
					'fields' => array('UserRouterLog.id', 'order'),
					'conditions' => array(
						'UserRouterLog.user_id' => $qualification_user['QualificationUser']['user_id'],
						'UserRouterLog.survey_id' => $qualification['Qualification']['project_id']
					)
				));
				$min = $max = $count = null; 
				if ($user_router_logs) {
					$min = min($user_router_logs); 
					$max = max($user_router_logs); 
					$count = count($user_router_logs); 
				}
				$qualification_users[$key]['UserRouterLog'] = array(
					'min' => $min,
					'max' => $max,
					'count' => $count
				);
			}
		}
		$this->set(compact('qualification_users', 'qualification'));
	}	
	
	public function client_analysis() {
		if ($this->request->is('post') || $this->request->is('put')) {
			if (empty($this->request->data['Report']['date_from']) || empty($this->request->data['Report']['date_to'])) {
				$this->Session->setFlash('Start date and end date both are required.', 'flash_error');
			}
			elseif (strtotime($this->request->data['Report']['date_from']) > strtotime($this->request->data['Report']['date_to'])) {
				$this->Session->setFlash('Start date must be less than end date.', 'flash_error');
			}
			elseif (empty($this->request->data['Report']['group_id'])) {
				$this->Session->setFlash('Group is required.', 'flash_error');
			}
			else {
				$date_from = $this->request->data['Report']['date_from'] . ' 00:00:00';
				$date_to = $this->request->data['Report']['date_to'] . ' 23:59:59';

				$start_date = date(DB_DATETIME, strtotime($date_from));
				$end_date = date(DB_DATETIME, strtotime($date_to));

				$group_id = $this->data['Report']['group_id'];

				$projects = $this->Project->find('all', array(
					'contain' => array(
						'SurveyVisitCache',
						'Client'
					),
					'fields' => array(
						'Project.id',
						'Project.group_id',
						'Project.started',
						'Project.ended',
						'Project.bid_ir',
						'Project.est_length',
						'Project.client_rate',
						'Project.user_payout',
						'Client.client_name',
						'SurveyVisitCache.client_ir',
						'SurveyVisitCache.complete',
						'SurveyVisitCache.loi_seconds',
						'SurveyVisitCache.click',
						'SurveyVisitCache.nq',
						'SurveyVisitCache.overquota',
						'SurveyVisitCache.oq_internal',
						'SurveyVisitCache.speed',
						'SurveyVisitCache.fraud',
						'SurveyVisitCache.prescreen_clicks',
						'SurveyVisitCache.prescreen_completes',
						'SurveyVisitCache.prescreen_nqs'
					),
					'conditions' => array(
						'Project.group_id' => $group_id,
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
							)
						)
					),
				));

				if (!$projects) {
					$this->Session->setFlash('No projects in that time period.', 'flash_error');
				}
				else {
					$clients = array();
					foreach ($projects as $project) {
						$client_name = $project['Client']['client_name'];
						
						// initialize the array if not already set
						if (!isset($clients[$client_name])) {
							$clients[$client_name] = array(
								'project_count' => 0,
								'client_revenue' => 0,
								'total_completes' => 0,
								'total_clicks' => 0,
								'total_nqs' => 0,
								'total_oqs' => 0,
								'total_internal_oqs' => 0,
								'total_speed_nqs' => 0,
								'total_fraud_nqs' => 0,
								'pre_scnr_clicks' => 0,
								'pre_scnr_completes' => 0,
								'pre_scnr_nqs' => 0,
								'total_bid_ir' => 0,
								'total_bid_loi' => 0,
								'total_user_payout' => 0,
								'total_client_rate' => 0,
								'total_client_ir' => 0,
								'total_actual_ir' => 0,
								'total_actual_loi' => 0,
								'epcs' => array(),
							);
						}

						$clients[$client_name]['project_count']++;
						$clients[$client_name]['client_revenue'] += ($project['Project']['client_rate'] * $project['SurveyVisitCache']['complete']);
						$clients[$client_name]['total_completes'] += $project['SurveyVisitCache']['complete'];
						$clients[$client_name]['total_clicks'] += $project['SurveyVisitCache']['click'];
						$clients[$client_name]['total_nqs'] += $project['SurveyVisitCache']['nq'];
						$clients[$client_name]['total_oqs'] += $project['SurveyVisitCache']['overquota'];
						$clients[$client_name]['total_internal_oqs'] += $project['SurveyVisitCache']['oq_internal'];
						$clients[$client_name]['total_speed_nqs'] += $project['SurveyVisitCache']['speed'];
						$clients[$client_name]['total_fraud_nqs'] += $project['SurveyVisitCache']['fraud'];
						$clients[$client_name]['pre_scnr_clicks'] += !is_null($project['SurveyVisitCache']['prescreen_clicks']) ? $project['SurveyVisitCache']['prescreen_clicks']: 0;
						$clients[$client_name]['pre_scnr_completes'] += !is_null($project['SurveyVisitCache']['prescreen_completes']) ? $project['SurveyVisitCache']['prescreen_completes']: 0;
						$clients[$client_name]['pre_scnr_nqs'] += !is_null($project['SurveyVisitCache']['prescreen_nqs']) ? $project['SurveyVisitCache']['prescreen_nqs']: 0;
						$clients[$client_name]['total_bid_ir'] += $project['Project']['bid_ir'];
						$clients[$client_name]['total_bid_loi'] += $project['Project']['est_length'];
						$clients[$client_name]['total_user_payout'] += $project['Project']['user_payout'];
						$clients[$client_name]['total_client_rate'] += $project['Project']['client_rate'];
						$clients[$client_name]['total_client_ir'] += $project['SurveyVisitCache']['client_ir'];
						$actual_ir = $project['SurveyVisitCache']['complete'] > 0 ? round($project['SurveyVisitCache']['complete'] / $project['SurveyVisitCache']['click'], 2) * 100 : 0; //in percentage
						$clients[$client_name]['total_actual_ir'] += $actual_ir;
						$clients[$client_name]['total_actual_loi'] += round($project['SurveyVisitCache']['loi_seconds'] / 60);
						$clients[$client_name]['epcs'][] = round(($actual_ir * $project['Project']['client_rate']) / 100);
					}
					
					foreach ($clients as $client_name => $client) {
						$total_actual_ir = round($client['total_actual_ir'] / $client['project_count'], 2);
						$total_bid_ir = round($client['total_bid_ir'] / $client['project_count'], 2);
						$total_client_ir = round($client['total_client_ir'] / $client['project_count'], 2);
						$total_actual_loi = round($client['total_actual_loi'] / $client['project_count'], 2);
						$total_bid_loi = round($client['total_bid_loi'] / $client['project_count'], 2);
						
						//EPC standard deviation
						$mean = array_sum($client['epcs']) / $client['project_count'];
						$variance = 0.0;
						foreach ($client['epcs'] as $epc) {
							$variance += pow($epc - $mean, 2);
						}

						$epc_standard_deviation = number_format(sqrt($variance) / sqrt($client['project_count']), 2);
						$client_analysis[] = array(
							$client_name,
							$client['project_count'],
							$client['total_clicks'] > 0 ? '$' .round($client['client_revenue'] / $client['total_clicks'], 2) : '0',
							'$' . $client['client_revenue'],
							$client['total_clicks'],
							$client['total_completes'],
							$client['total_nqs'],
							$client['total_oqs'],
							$client['total_internal_oqs'],
							$client['total_speed_nqs'],
							$client['total_fraud_nqs'],
							$client['pre_scnr_clicks'],
							$client['pre_scnr_completes'],
							$client['pre_scnr_nqs'],
							$total_client_ir . '%',
							$total_bid_ir . '%',
							$total_actual_ir . '%',
							($total_bid_ir > 0) ? round(($total_actual_ir - $total_bid_ir) / $total_bid_ir) . '%' : '0',
							$total_bid_loi,
							$total_actual_loi,
							($total_bid_loi > 0) ? round(($total_actual_loi - $total_bid_loi) / $total_bid_loi) : '0',
							'$' . round($client['total_client_rate'] / $client['project_count'], 2),
							'$' . round($client['total_user_payout'] / $client['project_count'], 2),
							$epc_standard_deviation
						);
					}
					
					if (!empty($client_analysis)) {
						$csv_rows = array_merge(array(array(
							'Client',
							'Project Count',
							'EPC',
							'Project Revenue',
							'Total Clicks',
							'Total Completes',
							'Total NQs',
							'Total OQs',
							'Total OQ-I',
							'Total NQ-S',
							'Total NQ-F',
							'Total P-CL',
							'Total P-C',
							'Total P-NQ',
							'Avg Client IR',
							'Avg Bid IR',
							'Avg Actual IR',
							'IR Diff',
							'Avg Bid LOI',
							'Avg Actual LOI',
							'LOI Diff',
							'Avg Client Rate',
							'Avg User Payout',
							'EPC Standard Deviation'
						)), $client_analysis);
					
						$filename = 'Client-analysis-' . date(DB_DATE, strtotime($date_from)) . '-to-' . date(DB_DATE, strtotime($date_to)) . '.csv';
						$csv_file = fopen('php://output', 'w');
						header('Content-type: application/csv');
						header('Content-Disposition: attachment; filename="' . $filename . '"');
						foreach ($csv_rows as $row) {
							fputcsv($csv_file, $row, ',', '"');
						}

						fclose($csv_file);
						$this->autoRender = false;
						$this->layout = false;
						$this->render(false);
						return;
					}
				}
			}
		}
		
		// Need to return groups every time or else an error occurs
		$groups = $this->Group->find('list', array(
			'fields' => array('Group.id', 'Group.name'),
			'recursive' => -1,
			'order' => 'Group.name ASC'
		));

		$this->set(compact('groups'));
	}
	
	public function history_request_analytics() {
		$models_to_import = array('HistoryRequest', 'HistoryRequestReport');
		foreach ($models_to_import as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}

		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		if (!isset($this->data['period_span']) || !isset($this->data['period'])) {
			$this->request->data['period_span'] = '7';
			$this->request->data['period'] = 'days';
		}
		$export_to_csv = isset($this->request->data['export']) && $this->request->data['export'] == 1;
		if ($this->request->data) {
			$period_span = $this->request->data['period_span'];
			if ($export_to_csv) {
				$csv_rows = array(array(
					'',
					'Reported Issues',
					'Resolved Issues',
					'Time to Resolved Issue (Median)',
					'Total Missing Points Paid Out'
				));
				
				$filename = 'export_history_request_analytics.csv';
				$csv_file = fopen('php://output', 'w');

			}
			if ($this->request->data['period'] == 'days') {
				for ($i = 1; $i <= $period_span; $i++) {
					$start_date = date('Y-m-d', strtotime('-'. $i .'days'));
					$history_request_report = $this->HistoryRequestReport->find('first', array(
						'conditions' => array(
							'HistoryRequestReport.date' => $start_date
						)
					));
					$reports[$start_date] = $history_request_report;
				}
			}
			elseif ($this->request->data['period'] == 'weeks') {
				for ($i = 1; $i <= $period_span; $i++) {
					$start_date = date(DB_DATE, strtotime('this week monday -'. ($i * 7).' days'));
					$end_date = date(DB_DATE, strtotime($start_date . ' +6 days'));
					$history_request_reports = $this->HistoryRequestReport->find('all', array(
						'fields' => array('total_reported_issues', 'total_resolved_issues', 'average_time', 'total_paid_points'),
						'conditions' => array(
							'HistoryRequestReport.date >=' => $start_date,
							'HistoryRequestReport.date <=' => $end_date,
						)
					));
					$report = array();
					if ($history_request_reports) {
						$total_reported_issues = $total_resolved_issues = $average_time = $total_paid_points = 0;
						$time_taken = array();
						foreach ($history_request_reports as $history_request_report) {
							$total_reported_issues += $history_request_report['HistoryRequestReport']['total_reported_issues'];
							$total_resolved_issues += $history_request_report['HistoryRequestReport']['total_resolved_issues'];
							$total_paid_points += $history_request_report['HistoryRequestReport']['total_paid_points'];
							$time_taken[] = $history_request_report['HistoryRequestReport']['average_time'];
						}
						$median = Utils::calculate_median($time_taken);
						$report = array(
							'total_reported_issues' => $total_reported_issues,
							'total_resolved_issues' => $total_resolved_issues,
							'average_time' => $median,
							'total_paid_points' => $total_paid_points,
						);
					}
					$reports[$end_date]['HistoryRequestReport'] = $report;
				}
			}
			elseif ($this->request->data['period'] == 'months') {
				$start = mktime(0, 0 , 0, date('m'), date('d'), date('Y'));
				for ($i = 1; $i <= $period_span; $i++) {
					$start_date = date(DB_DATE, mktime(0, 0, 0, date('m' , $start) - $i , 1 ,date('Y' , $start)));
					$end_date = date(DB_DATE, mktime(0, 0, 0, date('m') - $i + 1, 0, date('Y' , $start)));
					$history_request_reports = $this->HistoryRequestReport->find('all', array(
						'fields' => array('total_reported_issues', 'total_resolved_issues', 'average_time', 'total_paid_points'),
						'conditions' => array(
							'HistoryRequestReport.date >=' => $start_date,
							'HistoryRequestReport.date <=' => $end_date,
						)
					));
					$report = array();
					if ($history_request_reports) {
						$total_reported_issues = $total_resolved_issues = $average_time = $total_paid_points = 0;
						$time_taken = array();
						foreach ($history_request_reports as $history_request_report) {
							$total_reported_issues += $history_request_report['HistoryRequestReport']['total_reported_issues'];
							$total_resolved_issues += $history_request_report['HistoryRequestReport']['total_resolved_issues'];
							$total_paid_points += $history_request_report['HistoryRequestReport']['total_paid_points'];
							$time_taken[] = $history_request_report['HistoryRequestReport']['average_time'];
						}
						$median = Utils::calculate_median($time_taken);
						$report = array(
							'total_reported_issues' => $total_reported_issues,
							'total_resolved_issues' => $total_resolved_issues,
							'average_time' => $median,
							'total_paid_points' => $total_paid_points,
						);
					}
					$index = date("M-Y", strtotime($start_date));
					$reports[$index]['HistoryRequestReport'] = $report;
				}
			}
			if ($export_to_csv) {
				
				if ($reports) {
					foreach ($reports as $key => $report) {
						if (isset($report['HistoryRequestReport']['average_time'])) {
							$average_time = $report['HistoryRequestReport']['average_time'];
							$days    = floor($average_time / (24 * 60 * 60));
							$hours   = floor(($average_time % (24 * 60 * 60)) / (60 * 60));
							$mins = intval(($average_time / 60) % 60);
							$avg_time = '';
							if ($days) {
								$avg_time =  $days . ' day' . ( $days > 1 ? 's' : '' ) . ' ';
							}
							if ($hours) {
								$avg_time .= $hours . ' hour' . ( $hours > 1 ? 's' : '' ) . ' ';
							}	
							if ($mins) {
								$avg_time .= $mins . ' minute' . ( $mins > 1 ? 's' : '' );
							}
							if (!$days && !$hours && !$mins) {
								$avg_time = '0';
							}
						}
						else {
							$avg_time = 'N/A';
						}
						$csv_rows[] = array(
							$key,
							isset($report['HistoryRequestReport']['total_reported_issues']) ? $report['HistoryRequestReport']['total_reported_issues'] : 'N/A',
							isset($report['HistoryRequestReport']['total_resolved_issues']) ? $report['HistoryRequestReport']['total_resolved_issues'] : 'N/A',
							$avg_time,
							isset($report['HistoryRequestReport']['total_paid_points']) ? $report['HistoryRequestReport']['total_paid_points'] : 'N/A'
						);
					}
				}
				header('Content-type: application/csv');
				header('Content-Disposition: attachment; filename="' . $filename . '"');

				// Each iteration of this while loop will be a row in your .csv file where each field corresponds to the heading of the column
				foreach ($csv_rows as $row) {
					fputcsv($csv_file, $row, ',', '"');
				}

				fclose($csv_file);
				$this->autoRender = false;
				$this->layout = false;
				$this->render(false);
				return;
			}	
			$this->set(compact('reports'));
		}
	}
	
	public function history_request_analytics_by_day() {
		App::import('Model', 'HistoryRequest');	
		$this->HistoryRequest = new HistoryRequest;
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		if (!isset($this->data['date']) || !isset($this->data['period'])) {
			$this->request->data['date'] = date(DB_DATE, strtotime('today'));
			$this->request->data['period'] = 'days';
		}
		$export_to_csv = isset($this->request->data['export']) && $this->request->data['export'] == 1;
		if ($this->request->data['period'] == 'days') {
			$start_date = date(DB_DATE, strtotime($this->request->data['date'])) . ' 00:00:00';
			$end_date = date(DB_DATE, strtotime($this->request->data['date'])) . ' 23:59:59';
			$date_range = date(DB_DATE, strtotime($this->request->data['date']));
		}
		elseif ($this->request->data['period'] == 'weeks') {
			$end_date = date(DB_DATE, strtotime($this->request->data['date'])) . ' 23:59:59';
			$start_date = date(DB_DATE, strtotime($this->request->data['date'].' -6 days')) . ' 00:00:00';
			$date_range = date(DB_DATE, strtotime($this->request->data['date'].' -6 days')) . '-' . date(DB_DATE, strtotime($this->request->data['date']));
		}
		elseif ($this->request->data['period'] == 'months') {
			$end_date = date("Y-m-t", strtotime($this->request->data['date'])) . ' 23:59:59';
			$start_date = date("Y-m-01", strtotime($this->request->data['date'])) . ' 00:00:00';
			$date_range = date("Y-m-01", strtotime($this->request->data['date'])) . '-' . date("Y-m-t", strtotime($this->request->data['date']));
		}
		
		if ($export_to_csv) {
			$history_requests = $this->HistoryRequest->find('all', array(
				'conditions' => array(
					'HistoryRequest.created >=' => $start_date,
					'HistoryRequest.created <=' => $end_date	
				),
				'order' => 'HistoryRequest.created asc'
			));
			$csv_rows = array(array(
				'User ID',
				'Project ID',
				'Status',
				'Created',
				'time to Resolved Issue'
			));
			
			$filename = 'export_history_request_'.$date_range.'.csv';
			$csv_file = fopen('php://output', 'w');
			if ($history_requests) {
				foreach ($history_requests as $key => $request) {
					if ($request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_APPROVED || $request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_REJECTED) {
						$time_taken = strtotime($request['HistoryRequest']['modified']) - strtotime($request['HistoryRequest']['created']);
						$days    = floor($time_taken / (24 * 60 * 60));
						$hours   = floor(($time_taken % (24 * 60 * 60)) / (60 * 60));
						$mins = intval(($time_taken / 60) % 60);
						$time_to_resolve = '';
						if ($days) {
							$time_to_resolve = $days . ' day' . ( $days > 1 ? 's' : '' ) . ' ';
						}
						if ($hours) {
							$time_to_resolve .= $hours . ' hour' . ( $hours > 1 ? 's' : '' ) . ' ';
						}	
						if ($mins) {
							$time_to_resolve .= $mins . ' minute' . ( $mins > 1 ? 's' : '' );
						}
						if (!$days && !$hours && !$mins) {
							$time_to_resolve = '0';
						}
					}
					else {
						$time_to_resolve = 'N/A';
					}
					if ($request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_APPROVED) { 
						$status = 'Approved';
					} 
					elseif ($request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_REJECTED) { 
						$status = 'Rejected';
					}
					else {
						$status = 'Pending';
					}
					$csv_rows[] = array(
						$request['User']['id'],
						$request['HistoryRequest']['project_id'],
						$status,
						$request['HistoryRequest']['created'],
						$time_to_resolve
					);
				}
			}
			header('Content-type: application/csv');
			header('Content-Disposition: attachment; filename="' . $filename . '"');

			// Each iteration of this while loop will be a row in your .csv file where each field corresponds to the heading of the column
			foreach ($csv_rows as $row) {
				fputcsv($csv_file, $row, ',', '"');
			}

			fclose($csv_file);
			$this->autoRender = false;
			$this->layout = false;
			$this->render(false);
			return;
		}
		
		$this->paginate = array(
			'HistoryRequest' => array(
				'limit' => '100',
				'conditions' => array(
					'HistoryRequest.created >=' => $start_date,
					'HistoryRequest.created <=' => $end_date	
				),
				'order' => 'HistoryRequest.created asc'
			)
		);
		$history_requests = $this->paginate('HistoryRequest');
		$this->set(compact('history_requests'));
	}
	
	public function poll($poll_id = null) {
		App::import('Vendor', 'SiteProfile');
		$models_to_load = array('Poll', 'PollAnswer', 'PollUserAnswer');
		foreach ($models_to_load as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}

		if ($this->request->is('post') || $this->request->is('put')) {
			$poll = false;
			$this->request->data['Report']['poll_id'] = trim($this->request->data['Report']['poll_id']);
			if (!empty($this->request->data['Report']['poll_id'])) {
				$poll = $this->Poll->find('first', array(
					'fields' => array('Poll.id'),
					'recursive' => -1,
					'conditions' => array(
						'Poll.id' => $this->request->data['Report']['poll_id']
					)
				));
			}
			
			if ($poll) {
				$fields = array();
				if ($this->request->data['Report']['options']) {
					foreach ($this->request->data['Report']['options'] as $key => $val) {
						$fields[] = $val;
					}
				}
				
				if ($fields) {
					$count = $this->PollUserAnswer->find('count', array(
						'conditions' => array(
							'PollUserAnswer.poll_id' => $poll['Poll']['id'],
						)
					));
					if ($count > 0) {
						$report = $this->Report->find('first', array(
							'conditions' => array(
								'type' => 'poll',
								'poll_id' => $poll['Poll']['id']
							)
						));
						if ($report) {
							$data = array(
								'id' => $report['Report']['id'],
								'status' => 'queued'
							);
						}
						else {
							$data = array(
								'poll_id' => $poll['Poll']['id'],
								'type' => 'poll',
								'user_id' => $this->current_user['Admin']['id'],
							);
						}
						
						$this->Report->create();
						$this->Report->save(array('Report' => $data), true, array_keys($data));
						$query = ROOT.'/app/Console/cake report poll '.$poll['Poll']['id'] . ' "'.implode(',', $fields).'"';
						$query.= " > /dev/null &"; 
						exec($query, $output);
						CakeLog::write('report_commands', $query);

						$this->Session->setFlash('We are generating your report', 'flash_success');
						$this->redirect(array('controller' => 'polls', 'action' => 'index'));
					}
					else {
						$this->Session->setFlash('Poll is not taken yet.', 'flash_error');
					}
				}
				else {
					$this->Session->setFlash('Fields not specfied!', 'flash_error');
				}
			}
			else {
				$this->Session->setFlash('Poll not found!', 'flash_error');
			}
		}
		
		$this->set(compact('poll_id'));
	}
	
	public function project_fingerprints($project_id) {
		if (!$project_id) {
			throw new NotFoundException(__('Invalid Project'));
		}
		
		$models_to_load = array('ProjectFingerprint');
		foreach ($models_to_load as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}

		$this->ProjectFingerprint->bindModel(array(
			'belongsTo' => array(
				'SurveyVisit'
			)
		));
		
		$export = isset($this->request->query['export']) && $this->request->query['export'];
		if ($export) {
			$project_fingerprints = $this->ProjectFingerprint->find('all', array(
				'conditions' => array(
					'ProjectFingerprint.project_id' => $project_id
				),
				'contain' => array(
					'SurveyVisit'
				),
				'order' => 'ProjectFingerprint.fingerprint ASC',
			));
			
			// for some reason; partner binding doens't work
			if ($project_fingerprints) {
				$partner_ids = Hash::extract($project_fingerprints, '{n}.SurveyVisit.partner_id'); 
				$partner_ids = array_unique($partner_ids);
				$partners_unkeyed = $this->Partner->find('all', array(
					'fields' => array('Partner.id', 'Partner.partner_name', 'Partner.key'),
					'conditions' => array(
						'Partner.id' => $partner_ids
					)
				)); 
				if ($partners_unkeyed) {
					$partners = array();
					foreach ($partners_unkeyed as $partner) {
						$partners[$partner['Partner']['id']] = $partner; 
					}
				}
			}
			$fingerprints = Set::extract($project_fingerprints, '{n}.ProjectFingerprint.fingerprint');
			$counts = array_count_values($fingerprints);
			
			$csv_rows = array(array(
				'Has Dupe', 
				'Hash',
				'Partner',
				'Partner User ID',
				'IP Address',
				'Fingerprint',
				'User Agent',
				'Created (GMT)',
			));
			foreach ($project_fingerprints as $project_fingerprint) {
				$has_dupe = isset($counts[$project_fingerprint['ProjectFingerprint']['fingerprint']]) && $counts[$project_fingerprint['ProjectFingerprint']['fingerprint']] > 1; 
				$info = Utils::print_r_reverse($project_fingerprint['SurveyVisit']['info']);
				$csv_rows[] = array(
					$has_dupe,
					$project_fingerprint['SurveyVisit']['hash'],
					$partners[$project_fingerprint['SurveyVisit']['partner_id']]['Partner']['partner_name'],
					$project_fingerprint['ProjectFingerprint']['partner_user_id'],
					$project_fingerprint['ProjectFingerprint']['ip_address'],
					$project_fingerprint['ProjectFingerprint']['fingerprint'],
					isset($info) && isset($info['HTTP_USER_AGENT']) ? $info['HTTP_USER_AGENT'] : '',
					$project_fingerprint['ProjectFingerprint']['created']
				);
			}

			$filename = 'project_fingerprints-' . $project_id . '-' . gmdate(DB_DATE, time()) . '.csv';
			$csv_file = fopen('php://output', 'w');

			header('Content-type: application/csv');
			header('Content-Disposition: attachment; filename="' . $filename . '"');

			// Each iteration of this while loop will be a row in your .csv file where each field corresponds to the heading of the column
			foreach ($csv_rows as $row) {
				fputcsv($csv_file, $row, ',', '"');
			}

			fclose($csv_file);
			$this->autoRender = false;
			$this->layout = false;
			$this->render(false);
		}
		else {
			$paginate = array(
				'ProjectFingerprint' => array(
					'conditions' => array(
						'ProjectFingerprint.project_id' => $project_id
					),
					'contain' => array(
						'SurveyVisit'
					),
					'limit' => '500',
					'order' => 'ProjectFingerprint.fingerprint ASC'
				)
			);
			$this->paginate = $paginate;
			$project_fingerprints = $this->paginate('ProjectFingerprint'); 
			
			if ($project_fingerprints) {
				$partner_ids = Hash::extract($project_fingerprints, '{n}.SurveyVisit.partner_id'); 
				$partner_ids = array_unique($partner_ids);
				$partners_unkeyed = $this->Partner->find('all', array(
					'fields' => array('Partner.id', 'Partner.partner_name', 'Partner.key'),
					'conditions' => array(
						'Partner.id' => $partner_ids
					)
				)); 
				if ($partners_unkeyed) {
					$partners = array();
					foreach ($partners_unkeyed as $partner) {
						$partners[$partner['Partner']['id']] = $partner; 
					}
				}
			}
			
			$fingerprints = $this->ProjectFingerprint->find('list', array(
				'fields' => array('ProjectFingerprint.fingerprint'),
				'conditions' => array(
					'ProjectFingerprint.project_id' => $project_id
				),
				'recursive' => -1
			));
			$this->set(compact('fingerprints', 'project_fingerprints', 'partners'));
		}
	}
}