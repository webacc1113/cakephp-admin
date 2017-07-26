<?php
App::uses('Shell', 'Console');
App::import('Lib', 'Utilities');
App::import('Lib', 'Reporting');
App::import('Lib', 'MintVine');
App::import('Lib', 'MintVineUser');
App::uses('Folder', 'Utility');
App::uses('File', 'Utility');
App::uses('HttpSocket', 'Network/Http');

class ReportShell extends Shell {
	var $uses = array('Report', 'SurveyComplete', 'SurveyVisit', 'SurveyReport', 'User', 'UserRevenue', 'Partner', 'Setting', 'CintLog', 'Project', 'PartnerAnalytics', 'Group', 'SurveyUserVisit', 'UserAnalysis', 'ProjectLog', 'PanelistHistory', 'SurveyVisitCache', 'UserAgent', 'UserRouterLog', 'SocialglimpzRespondent', 'UserAgentValue', 'UserIp');
	public $tasks = array('ReportCsv');
	
	function main() {
		
	}
	
	function user_revenue() {
		ini_set('memory_limit', '2048M');
		set_time_limit(16000);
		
		// force reload data
		if (isset($this->args[0]) && $this->args[0] == 'force') {
			$force = true;
		}
		else {
			$force = false;
		}
		$this->User->bindModel(array('hasOne' => array(
			'UserRevenue' => array(
				'foreignKey' => 'user_id'
			)
		)));
		
		if (isset($this->args[0]) && $this->args[0] != 'force') {
			$users = $this->User->find('all', array(
				'fields' => array('id', 'last_touched', 'UserRevenue.*'),
				'conditions' => array(
					'User.verified is not null',
					'User.id' => $this->args[0]
				),
				'contain' => array(
					'UserRevenue'
				)
			));
		}
		else {
			$users = $this->User->find('all', array(
				'fields' => array('id', 'last_touched', 'origin', 'UserRevenue.*'),
				'conditions' => array(
					'User.verified is not null'
				),
				'contain' => array(
					'UserRevenue'
				)
			));
		}
		foreach ($users as $user) {
			if ($this->User->setUserRevenue($user, $force)) {
				echo "."; 
				flush();
			}
			else {
				echo '-';
			}
		}
		echo "\n";
		
		echo "Running net referral data\n";
		
		// now run the referral calculations
		foreach ($users as $user) {
			$referred_users = $this->User->find('list', array(
				'fields' => array('id', 'id'),
				'conditions' => array(
					'User.referred_by' => $user['User']['id']
				),
				'recursive' => -1
			));
			if ($referred_users) {
				$lifetime_revenue = $this->UserRevenue->find('first', array(
					'fields' => array('SUM(lifetime_revenue) as lifetime_revenue'),
					'recursive' => -1,
					'conditions' => array(
						'UserRevenue.user_id' => $referred_users,
					)
				));
				$lifetime_revenue = $lifetime_revenue[0]['lifetime_revenue'];
				
				$lifetime_cost = $this->UserRevenue->find('first', array(
					'fields' => array('SUM(lifetime_cost) as lifetime_cost'),
					'recursive' => -1,
					'conditions' => array(
						'UserRevenue.user_id' => $referred_users,
					)
				));
				$lifetime_cost = $lifetime_cost[0]['lifetime_cost'];
				
				$this->UserRevenue->updateAll(
					array('referral_net' => $lifetime_revenue - $lifetime_cost), 
					array('UserRevenue.user_id' => $user['User']['id'])
				);
				echo "."; flush();
			}	
		}	
	}
	
	function raw() {
		if (!isset($this->args[0])) {
			return;
		}
		
		ini_set('memory_limit', '2048M');
		set_time_limit(14400);
			
		$this->SurveyVisit->bindModel(array(
			'belongsTo' => array(
				'Partner' => array(
					'fields' => array('partner_name')
				)
			)
		));
		$conditions =  array(
			'SurveyVisit.survey_id' => $this->args[0]
		);
		if (isset($this->args[1]) && !empty($this->args[1])) {
			$conditions['SurveyVisit.partner_id'] = $this->args[1];
		}
		$visits = $this->SurveyVisit->find('all', array(
			'fields' => array(
				'Partner.partner_name',
				'SurveyVisit.id',
				'SurveyVisit.partner_user_id',
				'SurveyVisit.user_id',
				'SurveyVisit.partner_id',
				'SurveyVisit.type',
				'SurveyVisit.link',
				'SurveyVisit.hash',
				'SurveyVisit.ip',
				'SurveyVisit.referrer',
				'SurveyVisit.query_string',
				'SurveyVisit.result_id',
				'SurveyVisit.result',
				'SurveyVisit.result_note',
				'SurveyVisit.created',
				'SurveyVisit.modified',
				'SurveyVisit.info',
			),
			'conditions' => $conditions
		));
		
		$filename = WWW_ROOT.'files/reports/raw_'.$this->args[0].'.csv';
		
		// since we archive survey_visits, re-generating an old report may wipe out an existing report
		if (file_exists($filename) && ($handle = fopen($filename, 'r')) !== false) {
			$i = 0;
			while (($row = fgetcsv($handle, 1000)) !== false) {
				$i++;
			}
			
			fclose($handle);
			if (count($visits) < ($i - 1)) {
				return false;
			}
		}
		
		$this->ReportCsv->raw($filename, $visits);
		
		if (isset($this->args[2])) {			
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
			
			$file = APP . 'webroot/files/reports/raw_'.$this->args[0].'.csv';
			$filename = 'files/reports/raw_'.$this->args[0].'.csv';
			
			$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);			
			$headers = array(
				'Content-Disposition' => 'attachment; filename=raw_'.$this->args[0].'.csv'
			);
			
			if ($S3->putObject($S3->inputFile($file), $settings['s3.bucket'] , $filename, S3::ACL_PRIVATE, array(), $headers)) {				
				$this->Report->create();
				$this->Report->save(array('Report' => array(
					'id' => $this->args[2],
					'path' => $filename,
					'status' => 'complete'
				)), true, array('path', 'status'));
				
				// unlink($file);
			}			
		}
	}
	
	// passed args; survey_id, partner_id, report_id
	function survey($survey_id = null, $partner_id = null, $report_id = null) {	
		if (!isset($this->args[0]) && is_null($survey_id)) {
			return;
		}
		ini_set('memory_limit', '1024M');
		set_time_limit(14400);
		
		if (is_null($survey_id)) {
			$survey_id = $this->args[0];
		}
		if (is_null($partner_id)) {
			$partner_id = isset($this->args[1]) ? $this->args[1]: null;
		}
		if (is_null($report_id)) {
			$report_id = isset($this->args[2]) ? $this->args[2]: null;
		}
		
		$go = true;
		
		if (false && !empty($report_id)) {
			$report = $this->Report->findById($report_id);
			$conditions = array('SurveyVisit.survey_id' => $survey_id);
			if (!empty($partner_id)) {
				$conditions['SurveyVisit.partner_id'] = $partner_id;
			}
			$last_transaction = $this->SurveyVisit->find('first', array(
				'fields' => array('max(id) as id'), 
				'conditions' => $conditions
			));
			if ($last_transaction) {
				if ($last_transaction[0]['id'] == $report['Report']['last_id']) {
					$go = false;
					$last_ts = $report['Report']['last_transaction'];
					$last_id = $report['Report']['last_id'];
				}
			}
		}
		
		if ($go) {
			$survey_reports = $this->SurveyReport->find('all', array(
				'recursive' => -1,
				'conditions' => array(
					'SurveyReport.survey_id' => $survey_id
				),
				'fields' => array('SurveyReport.id')
			));
			if ($survey_reports) {
				foreach ($survey_reports as $survey_report) {
					$this->SurveyReport->delete($survey_report['SurveyReport']['id']); 
				}
			}
		
			// retrieve the survey visit data
			$conditions = array(
				'SurveyVisit.survey_id' => $survey_id	
			);
			if (!empty($partner_id)) {
				$conditions['SurveyVisit.partner_id'] = $partner_id;
			}
			
			// todo: throw away OQ data?
			echo "Retrieving data...\n"; 
			flush();
			
			$visits = $this->SurveyVisit->find('all', array(
				'fields' => array(
					'SurveyVisit.id', 
					'SurveyVisit.created', 
					'SurveyVisit.type', 
					'SurveyVisit.result_id', 
					'SurveyVisit.result_note',
					'SurveyVisit.user_id', 
					'SurveyVisit.partner_user_id', 
					'SurveyVisit.ip', 
					'SurveyVisit.partner_id', 
					'SurveyVisit.link', 
					'SurveyVisit.hash', 
					'SurveyVisit.referrer', 
					'SurveyVisit.client_rate_cents', 
					'SurveyVisit.user_payout_cents'
				),
				'conditions' => $conditions,
				'order' => 'SurveyVisit.id ASC'
			));
			
			echo "Setting in-memory hashes...\n"; 
			flush();
			// set IDs as key of visits array
			if (!empty($visits)) {
				$keyed = array();
				$end_results = array();
				foreach ($visits as $visit) {
					$keyed[$visit['SurveyVisit']['id']] = $visit;
					$end_results[$visit['SurveyVisit']['result_id']] = $visit['SurveyVisit']['id'];
				}
				$visits = $keyed;
			}
			
			echo "Aligning data...\n"; 
			flush();
			$completed_ids = array(); // completed ids
			if (!empty($visits)) {
				$last_id = null;
				$matched = array();
				foreach ($visits as $id => $visit) {
				
					if ($id > $last_id) {
						$last_id = $id; 
						$last_ts = $visit['SurveyVisit']['created'];
					}
				
					// already found a match in the data
					if (in_array($id, $matched)) {
						continue;
					}
				
					// first see if this value has already been processed
					if (in_array($id, $completed_ids)) {
						continue;
					}
				
					$result = $visit['SurveyVisit']['type'];
					$completed = $started = null;
					$start_id = $end_id = 0;
				
					if ($visit['SurveyVisit']['type'] == SURVEY_CLICK) {
						$started = $visit['SurveyVisit']['created'];
						if (isset($visit['SurveyVisit']['result_id']) && !empty($visit['SurveyVisit']['result_id']) && $visit['SurveyVisit']['result_id'] != $id) {
							$result_id = $visit['SurveyVisit']['result_id'];
							$completed = $keyed[$result_id]['SurveyVisit']['created'];
							$user_payout_cents = $keyed[$result_id]['SurveyVisit']['user_payout_cents'];
							$client_rate_cents = $keyed[$result_id]['SurveyVisit']['client_rate_cents'];
							$result_note = $keyed[$result_id]['SurveyVisit']['result_note'];
							$result = $keyed[$result_id]['SurveyVisit']['type'];
							$matched[] = $result_id;
							$end_id = $result_id;
						}
						$start_id = $visit['SurveyVisit']['id'];
					}
					else {
						if (isset($end_results[$visit['SurveyVisit']['id']])) {
							$original_report_id = $end_results[$visit['SurveyVisit']['id']]; 
							$this->SurveyReport->create();
							$this->SurveyReport->save(array('SurveyReport' => array(
								'id' => $end_results[$visit['SurveyVisit']['id']],
								'end_id' => $visit['SurveyVisit']['id'],
								'result' => $result,
								'client_rate_cents' => $visit['SurveyVisit']['client_rate_cents'],
								'user_payout_cents' => $visit['SurveyVisit']['user_payout_cents'],
								'completed' => $visit['SurveyVisit']['created']
							)), true, array('end_id', 'result', 'completed', 'client_rate_cents', 'user_payout_cents'));
							continue;
						}
						$completed = $visit['SurveyVisit']['created'];
						$end_id = $visit['SurveyVisit']['id'];
						$result_note = $visit['SurveyVisit']['result_note'];
						$client_rate_cents = $visit['SurveyVisit']['client_rate_cents'];
						$user_payout_cents = $visit['SurveyVisit']['user_payout_cents'];
					}
				
					if ($visit['SurveyVisit']['type'] == SURVEY_DUPE) {
						$result = SURVEY_DUPE;
					}
					if ($visit['SurveyVisit']['type'] == SURVEY_DUPE_FP) {
						$result = SURVEY_DUPE_FP;
					}
					
					if (empty($result)) {
						continue;
					}
					// create the report row
					$this->SurveyReport->create();
					$this->SurveyReport->save(array('SurveyReport' => array(
						'start_id' => $start_id,
						'end_id' => $end_id,
						'user_id' => $visit['SurveyVisit']['user_id'],
						'partner_user_id' => $visit['SurveyVisit']['partner_user_id'],
						'ip' => $visit['SurveyVisit']['ip'],
						'result' => $result,
						'result_note' => $result_note,
						'partner_id' => $visit['SurveyVisit']['partner_id'],
						'survey_id' => $survey_id,
						'link' => $visit['SurveyVisit']['link'],
						'hash' => $visit['SurveyVisit']['hash'],
						'referrer' => $visit['SurveyVisit']['referrer'],
						'client_rate_cents' => $client_rate_cents,
						'user_payout_cents' => $user_payout_cents,
						'started' => $started,
						'completed' => $completed,
						'created' => gmdate(DB_DATE, time())
					)));
					if (isset($start_id) && !empty($start_id)) {
						$completed_ids[] = $start_id;
					}
					if (isset($end_id) && !empty($end_id)) {
						$completed_ids[] = $end_id;
					}
				}
			}
			unset($visits); // get rid of that in memory
		}
		
		$hashes = $this->SurveyComplete->find('list', array(
			'fields' => array('hash', 'id'),
			'conditions' => array(
				'SurveyComplete.survey_id' => $survey_id,
			)
		));
		$client_hashes = array();
		if (!empty($hashes)) {
			foreach ($hashes as $key => $val) {
				$client_hashes[strtolower($key)] = $val; 
			}
		}
			
		// generate the report
		$conditions = array(
			'SurveyReport.survey_id' => $survey_id,
			'SurveyReport.result !=' => SURVEY_CLICK
		);
		if (!empty($partner_id)) {
			$conditions['SurveyReport.partner_id'] = $partner_id;
		}
		$visits = $this->SurveyReport->find('all', array(
			'recursive' => -1,
			'conditions' => $conditions
		));
		
		$partners = $this->Partner->find('list', array(
			'conditions' => array(
				'Partner.deleted' => false
			),
			'fields' => array('id', 'partner_name')
		));
		
		// determine filename of file and retrieve the cpi value of survey partners 
		if (empty($partner_id)) {
			$filename = $survey_id;
		}
		else {
			$filename = $survey_id.'_'.$partner_id;
		}
		
		$date = new DateTime();
		$date->setTimeZone(new DateTimeZone('America/Los_Angeles'));
		$filename .= '_'.$date->format('YmdHi');
		
		// write primary file
		$file = new File(WWW_ROOT.'files/reports/'.$filename.'.csv', true, 0644);
		$file->write($this->ReportCsv->report($visits, $survey_id, $partners, $client_hashes));
		echo 'File written'."\n";
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
		
		if (isset($report_id)) {
			
			$file = WWW_ROOT.'files/reports/'.$filename.'.csv';
			$aws_filename = 'files/reports/'.$filename.'.csv';
			echo 'Writing to S3 '.$aws_filename.' from '.$file."\n";
			
			$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);	
			$headers = array(
				'Content-Disposition' => 'attachment; filename='.$filename.'.csv'
			);
			$save = $S3->putObject($S3->inputFile($file), $settings['s3.bucket'] , $aws_filename, S3::ACL_PRIVATE, array(), $headers);
			if ($save) {
				$this->Report->create();
				$this->Report->save(array('Report' => array(
					'id' => $report_id,
					'path' => $aws_filename,
					'status' => 'complete',
					'last_transaction' => $last_ts,
					'last_id' => $last_id
				)));
				
				// unlink($file);
			}
		}
		
		// custom report
		if (!empty($report['Report']['hashes'])) {
			$hashes = explode("\n", $report['Report']['hashes']);
			array_walk($hashes, create_function('&$val', '$val = trim($val);')); 
			
			foreach ($visits as $key => $visit) {
				if (!in_array(trim($visit['SurveyReport']['hash']), $hashes)) {
					unset($visits[$key]);
				}
			}
			$file = new File(WWW_ROOT.'files/reports/'.$filename.'_hashes.csv', true, 0644);
			$file->write($this->ReportCsv->report($visits, $survey_id, false, $client_hashes));
			
			if (isset($report_id)) {				
				$file = WWW_ROOT.'files/reports/'.$filename.'_hashes.csv';
				$aws_filename = 'files/reports/'.$filename.'_hashes.csv';
				$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);	
				$headers = array(
					'Content-Disposition' => 'attachment; filename='.$filename.'.csv'
				);
				if ($S3->putObject($S3->inputFile($file), $settings['s3.bucket'] , $aws_filename, S3::ACL_PRIVATE, array(), $headers)) {
					$this->Report->create();
					$this->Report->save(array('Report' => array(
						'id' => $report_id,
						'hashes' => null,
						'custom_path' => $aws_filename,
					)), true, array('custom_path', 'hashes'));
					
					// unlink($file);
				}
			}
		}
		unset($visits);
	}

	function partner_analytics_generation() {
		///////
		// ---> START CINT REPORT GENERATION <---
		///////
		$cint_partner_id = '38';
		$country = array_keys(unserialize(SUPPORTED_COUNTRIES));
		$all_available_cint_project_ids = $all_available_cint_quota_ids = array();
		$filtered_supply = array(
			'snapshots' => 0, 
			'available_revenue' => array(), 
			'available_erc' => array(), 
			'compiled_revenue' => array(), 
			'compiled_erc' => array(), 
			'average_erc' => 0, 
			'average_revenue' => 0, 
			'available_quotas' => 0, 
			'available_projects' => 0, 
			'actual_clicks' => 0, 
			'actual_completes' => 0, 
			'actual_revenue' => 0, 
			'revenue_metric' => 0, 
			'complete_metric' => 0, 
			'single_metric' => 0
		);
		$total_supply = array(
			'available_revenue' => array(), 
			'available_erc' => array(), 
			'compiled_revenue' => array(), 
			'compiled_erc' => array(), 
			'average_erc' => 0, 
			'average_revenue' => 0, 
			'available_quotas' => 0, 
			'available_projects' => 0
		);
		
		// Parse cint logs to get available revenue and quotas
		$anchor = strtotime('-91 minutes');
		$start = date('Y-m-d H:00:00', $anchor);
		$stop = date('Y-m-d H:59:59', $anchor);
		
		$this->out('Starting report from '.$start.' to '.$stop);
		$cint_logs = $this->CintLog->find('all', array(
			'conditions' => array(
				'CintLog.country' => $country,
				'CintLog.raw !=' => NULL,
				'CintLog.parent_id' => '0', 
				'CintLog.created >=' => $start,
				'CintLog.created <=' => $stop
			)
		));
		$this->out('Analyzing '.count($cint_logs).' records');
		
		// nothing to analyze
		if (!$cint_logs) {
			$this->out('Completed: No logs to analyze');
			return false;
		}

		// Troll every cint log for the specified hour and store the necessary data for determining availability
		foreach ($cint_logs as $cint_log) {
			$cint_quotas = json_decode($cint_log['CintLog']['raw'], true);
			if (empty($cint_quotas)) {
				continue;
			}

			$temp_filtered_project_ids = $temp_filtered_quota_ids = $temp_totalsupply_project_ids = $temp_totalsupply_quota_ids = array();

			foreach ($cint_quotas as $quota) {
				/*
				 $all_available_cint_project_ids and $all_available_cint_quota_ids are later used for pulling revenue
				 information for this run.
				*/
				// Is this project unique to all projects that we have trolled this execution?
				if (!in_array($quota['project_id'], $all_available_cint_project_ids)) {
					$all_available_cint_project_ids[] = $quota['project_id'];
				}			

				// Is this quota unique to all quotas that we have trolled this execution?
				if (!in_array($quota['id'], $all_available_cint_quota_ids)) {
					$all_available_cint_quota_ids[] = $quota['id'];
				}			

				/*
				 $temp_totalsupply_project_ids and $temp_totalsupply_project_ids used to determine total
				 non-filtered project and quota information
				*/
				// Is this project unique for all projects that we have trolled in this cint_log ?
				if (!in_array($quota['project_id'], $temp_totalsupply_project_ids)) {
					$temp_totalsupply_project_ids[] = $quota['project_id'];
				}			

				// Is this quota unique for all quota that we have trolled in this cint_log ?
				if (!in_array($quota['id'], $temp_totalsupply_project_ids)) {
					$temp_totalsupply_quota_ids[] = $quota['id'];
				}			

				// We only get 40% of Cint share -- calculate our payout for completes
				$payout = round($quota['pricing']['indicative_cpi'] * 0.4, 2); 
					
				/*
				 $total_supply['available_erc'] and $total_supply['available_revenue'] store the completes / revenue availability information
				 for every iteration of each quota id to obtain multiple snapshots for the hour on each quota.
				*/
				// Save snap shots over the hour on a per quota basis
				$total_supply['available_erc'][$quota['id']][] = $quota['fulfillment']['estimated_remaining_completes']; 
				$total_supply['available_revenue'][$quota['id']][] = ($payout * ($quota['fulfillment']['estimated_remaining_completes'] * ($quota['statistics']['conversion_rate'] / 100))); 
				
				// If a quota doesn't match our cut offs it isn't 'available' and should not be counted. 
				// Do not count quotas with LOI higher than 20
				if ($quota['statistics']['length_of_interview'] > 20) {
					continue;
				}

				// Do not count quotas with ERC less than 4
				if ($quota['fulfillment']['estimated_remaining_completes'] < 4) {
					continue;
				}

				// Do not count quotas with conversion rate less than 8%
				if ($quota['statistics']['conversion_rate'] < 8) {
					continue;
				}

				// Is this project a unique filtered project for this hour?
				if (!in_array($quota['project_id'], $temp_filtered_project_ids)) {
					$temp_filtered_project_ids[] = $quota['project_id'];
				}		

				// Is this quota unique filtered quota for this hour?
				if (!in_array($quota['id'], $temp_filtered_quota_ids)) {
					$temp_filtered_quota_ids[] = $quota['id'];
				}	
												
				// Save snap shots over the hour on a per quota basis
				$filtered_supply['available_erc'][$quota['id']][] = $quota['fulfillment']['estimated_remaining_completes']; 
				$filtered_supply['available_revenue'][$quota['id']][] = ($payout * ($quota['fulfillment']['estimated_remaining_completes'] * ($quota['statistics']['conversion_rate'] / 100))); 
			}

			$filtered_supply['available_quotas'] = $filtered_supply['available_quotas'] + count($temp_filtered_quota_ids);
			$filtered_supply['available_projects'] = $filtered_supply['available_projects'] + count($temp_filtered_project_ids);
			$total_supply['available_quotas'] = $total_supply['available_quotas'] + count($temp_totalsupply_quota_ids);
			$total_supply['available_projects'] = $total_supply['available_projects'] + count($temp_totalsupply_project_ids);
		}
		// Done
			
		// Average total number of unique quota id's and project id's for the hour
		$count = count($cint_logs);
		$filtered_supply['snapshots'] = $count;
		$filtered_supply['available_quotas'] = $filtered_supply['available_quotas'] / $count;
		$filtered_supply['available_projects'] = $filtered_supply['available_projects'] / $count;
		$total_supply['available_quotas'] = $total_supply['available_quotas'] / $count;
		$total_supply['available_projects'] = $total_supply['available_projects'] / $count;
		// Done
			
		// End get available data

		// ---> START FILTERED SUPPLY BOIL DOWN
		// Boil down partner hourly snap shots
		// Average quotas per quota_id to get an hourly average
		foreach ($filtered_supply['available_erc'] as $quota_id => $available_erc) {
			// How many snapshots do we have for this quota id?
			$count = count($available_erc);
			foreach ($available_erc as $key => $erc) {
				if (empty($filtered_supply['compiled_erc'][$quota_id])) {
					$filtered_supply['compiled_erc'][$quota_id] = $erc;
				}
				else {
					$filtered_supply['compiled_erc'][$quota_id] = $filtered_supply['compiled_erc'][$quota_id] + $erc;
				}
			}
			
			// If there's no compiled quota information for this quota_id skip it
			if (!empty($filtered_supply['compiled_erc'][$quota_id])) {
				$filtered_supply['average_erc'] = $filtered_supply['average_erc'] + ($filtered_supply['compiled_erc'][$quota_id] / $count);
			}
		}
		// Destroy unneeded data in the array as it fills the screen during debugging
		unset($filtered_supply['compiled_erc']);
		unset($filtered_supply['available_erc']);
		// Done averaging available quotas 

		// Average available revenue per quota_id to get an hourly average
		foreach ($filtered_supply['available_revenue'] as $quota_id=>$available_revenue) {
			// How many snapshots do we have for this quota id?
			$count = count($available_revenue);
			foreach ($available_revenue as $key => $revenue) {
				if (empty($filtered_supply['compiled_revenue'][$quota_id])) {
					$filtered_supply['compiled_revenue'][$quota_id] = $revenue;
				}
				else {
					$filtered_supply['compiled_revenue'][$quota_id] = $filtered_supply['compiled_revenue'][$quota_id] + $revenue;
				}
			}
				
			// If there's no compiled revenue information for this quota_id skip it
			if (!empty($filtered_supply['compiled_revenue'][$quota_id])) {
				$filtered_supply['average_revenue'] = $filtered_supply['average_revenue'] + ($filtered_supply['compiled_revenue'][$quota_id] / $count);
			}
		}
		// Destroy unneeded data in the array as it fills the screen during debugging
		unset($filtered_supply['compiled_revenue']);
		unset($filtered_supply['available_revenue']);
		// Done averaging available revenue
		// ---> END FILTERED SUPPLY BOIL DOWN


		// ---> START TOTAL SUPPLY BOIL DOWN
		// Boil down partner total supply snap shots

		// Average quotas per quota_id to get an hourly average of total supply
		foreach ($total_supply['available_erc'] as $quota_id=>$available_erc) {
			// How many snapshots do we have for this quota id?
			$count = count($available_erc);
			foreach ($available_erc as $key=>$erc) {
				if (empty($total_supply['compiled_erc'][$quota_id])) {
					$total_supply['compiled_erc'][$quota_id] = $erc;
				}
				else {
					$total_supply['compiled_erc'][$quota_id] = $total_supply['compiled_erc'][$quota_id] + $erc;
				}
			}
			
			// If there's no compiled quota information for this quota_id skip it
			if (!empty($total_supply['compiled_erc'][$quota_id])) {
				$total_supply['average_erc'] = $total_supply['average_erc'] + ($total_supply['compiled_erc'][$quota_id] / $count);
			}
		}
		// Destroy unneeded data in the array as it fills the screen during debugging
		unset($total_supply['compiled_erc']);
		unset($total_supply['available_erc']);
		// Done averaging available quotas 

		// Average available revenue per quota_id to get an hourly average
		foreach ($total_supply['available_revenue'] as $quota_id=>$available_revenue) {
			// How many snapshots do we have for this quota id?
			$count = count($available_revenue);
			foreach ($available_revenue as $key=>$revenue) {
				if (empty($total_supply['compiled_revenue'][$quota_id])) {
					$total_supply['compiled_revenue'][$quota_id] = $revenue;
				}
				else {
					$total_supply['compiled_revenue'][$quota_id] = $total_supply['compiled_revenue'][$quota_id] + $revenue;
				}
			}

			// If there's no compiled revenue information for this quota_id skip it
			if (!empty($total_supply['compiled_revenue'][$quota_id])) {
				$total_supply['average_revenue'] = $total_supply['average_revenue'] + ($total_supply['compiled_revenue'][$quota_id] / $count);
			}
		}
		// Destroy unneeded data in the array as it fills the screen during debugging
		unset($total_supply['compiled_revenue']);
		unset($total_supply['available_revenue']);
		// Done averaging available revenue
		// ---> END TOTAL SUPPLY BOIL DOWN

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
		$this->out('Retrieved '.count($mv_projects).' projects'); 
		
		// Get our actual revenue / completes / clicks
		foreach ($mv_projects as $project) {
			$current_clicks = $current_completes = 0;
			
			$survey_visits = $this->SurveyVisit->find('list', array(
				'fields' => array('SurveyVisit.id', 'SurveyVisit.result'),
				'conditions' => array(
					'SurveyVisit.survey_id' => $project['Project']['id'],
					'SurveyVisit.created >=' => $start,
					'SurveyVisit.created <=' => $stop,
					'SurveyVisit.type' => SURVEY_CLICK
				),
				'recursive' => -1
			));

			if (empty($survey_visits)) {
				continue;
			}
			$statistics = array_count_values($survey_visits);
			$current_clicks = count($survey_visits);
			$current_completes = isset($statistics[SURVEY_COMPLETED]) && !empty($statistics[SURVEY_COMPLETED]) ? $statistics[SURVEY_COMPLETED]: 0;

			$filtered_supply['actual_completes'] = $filtered_supply['actual_completes'] + $current_completes;
			$filtered_supply['actual_clicks'] = $filtered_supply['actual_clicks'] + $current_clicks;
			$filtered_supply['actual_revenue'] = $filtered_supply['actual_revenue'] + ($current_completes * $project['Project']['client_rate']);
		}
		// End grabbing actual hourly data.

		// Calculate revenue fill rate per hour
		$filtered_supply['revenue_metric'] = ($filtered_supply['actual_revenue'] / ($filtered_supply['average_revenue'] + $filtered_supply['actual_revenue'])) * 100;

		// Calculate complete fill rate per hour
		$filtered_supply['complete_metric'] = ($filtered_supply['actual_completes'] / ($filtered_supply['average_erc'] + $filtered_supply['actual_completes'])) * 100;

		// Calculate single metric fill rate per hour
		$filtered_supply['single_metric'] = ($filtered_supply['revenue_metric'] + $filtered_supply['complete_metric']) / 2;

		// Save reporting data
		$partnerAnalyticsSource = $this->PartnerAnalytics->getDataSource();
		$partnerAnalyticsSource->begin();
		$this->PartnerAnalytics->create();
		$this->PartnerAnalytics->save(array('PartnerAnalytics' => array(
			'partner_id' => $cint_partner_id,
			'created' => $start,
			'snapshots' => $filtered_supply['snapshots'],
			'filtered_projects' => round($filtered_supply['available_projects']),
			'filtered_quotas' => round($filtered_supply['available_quotas']),
			'total_projects' => round($total_supply['available_projects']),
			'total_quotas' => round($total_supply['available_quotas']),
			'filtered_erc' => round($filtered_supply['average_erc']),
			'filtered_revenue' => round($filtered_supply['average_revenue'], 2),
			'total_erc' => round($total_supply['average_erc']),
			'total_revenue' => round($total_supply['average_revenue'], 2),
			'actual_clicks' => $filtered_supply['actual_clicks'],
			'actual_completes' => $filtered_supply['actual_completes'],
			'actual_revenue' => $filtered_supply['actual_revenue'],
			'revenue_fillrate' => round($filtered_supply['revenue_metric'], 4),
			'complete_fillrate' => round($filtered_supply['complete_metric'], 4),
			'single_metric' => round($filtered_supply['single_metric'], 4)
		)));
		
		$partner_analytic = $this->PartnerAnalytics->find('first', array(
			'conditions' => array(
				'PartnerAnalytics.id' => $this->PartnerAnalytics->getInsertId()
			)
		));
		$partnerAnalyticsSource->commit();
		
		$this->out(print_r($partner_analytic, true));
		$this->out('Completed; partner analytics written');
		///////
		// ---> END CINT REPORT GENERATION <---
		///////	
	}

	// Find out what the top projects are for a given day
	function top_projects_report() {
		
		if (!empty($this->args[0])) {
			$start_date = date($this->args[0].' 00:00:00');
			$end_date = date($this->args[0].' 23:59:59');
		}
		else {
			$start_date = date('Y-m-d 00:00:00');
			$end_date = date('Y-m-d 23:59:59');
		}

		$partners = array('cint', 'fulcrum', 'toluna', 'precision', 'spectrum');
		$top_clicks = array();
		$top_completes = array();
		$top_revenue = array();

		$groups = $this->Group->find('list', array(
			'fields' => array('Group.key', 'Group.name'),
			'recursive' => -1
		)); 

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
						// Tabulate clicks
						if (empty($top_clicks[$partner][$project['Project']['id']])) {
							$top_clicks[$partner][$project['Project']['id']] = 1;
						}
						else {
							$top_clicks[$partner][$project['Project']['id']] = $top_clicks[$partner][$project['Project']['id']] + 1;
						}

						if ($visit['SurveyVisit']['result'] == SURVEY_COMPLETED) {
							// Tabulate completes
							if (empty($top_completes[$partner][$project['Project']['id']])) {
								$top_completes[$partner][$project['Project']['id']] = 1;
							}
							else {
								$top_completes[$partner][$project['Project']['id']] = $top_completes[$partner][$project['Project']['id']] + 1;
							}
						
							// Tabulate revenue
							if (empty($top_revenue[$partner][$project['Project']['id']])) {
								$top_revenue[$partner][$project['Project']['id']] = $project['Project']['client_rate'];
							}
							else {
								$top_revenue[$partner][$project['Project']['id']] = $top_revenue[$partner][$project['Project']['id']] + $project['Project']['client_rate'];
							}
						}
					}
				}
			}
			
			arsort($top_revenue[$partner]);
			arsort($top_clicks[$partner]);
			arsort($top_completes[$partner]);
			$this->out($partner.' TOP Projects Data');
			$this->out($partner.' Top 10 Projects by clicks');
			$data = array_slice($top_clicks[$partner], 0, 10, true);
			foreach ($data as $project_id=>$point) {

				if (!empty($top_revenue[$partner][$project_id]) && !empty($point)) {
					$epc = round($top_revenue[$partner][$project_id] / $top_clicks[$partner][$project_id], 3);
				}
				else {
					$epc = 0;
				}

				if (!empty($top_completes[$partner][$project_id]) && !empty($top_clicks[$partner][$project_id])) {
					$ir = round($top_completes[$partner][$project_id] / $top_clicks[$partner][$project_id], 3) * 100;
				}
				else {
					$ir = 0;
				}

				$this->out('Project ID: '.$project_id.' Clicks: '.$point.' EPC: '.$epc.' IR: '.$ir.'%');
			}
			$this->out($partner.' Top 10 Projects by completes');
			$data = array_slice($top_completes[$partner], 0, 10, true);
			foreach ($data as $project_id=>$point) {

				if (!empty($top_revenue[$partner][$project_id]) && !empty($point)) {
					$epc = round($top_revenue[$partner][$project_id] / $top_clicks[$partner][$project_id], 3);
				}
				else {
					$epc = 0;
				}

				if (!empty($top_completes[$partner][$project_id]) && !empty($top_clicks[$partner][$project_id])) {
					$ir = round($top_completes[$partner][$project_id] / $top_clicks[$partner][$project_id], 3) * 100;
				}
				else {
					$ir = 0;
				}

				$this->out('Project ID: '.$project_id.' Completes: '.$point.' EPC: '.$epc.' IR: '.$ir.'%');
			}
			$this->out($partner.' Top 10 Projects by revenue');
			$data = array_slice($top_revenue[$partner], 0, 10, true);
			foreach ($data as $project_id=>$point) {

				if (!empty($top_revenue[$partner][$project_id]) && !empty($point)) {
					$epc = round($top_revenue[$partner][$project_id] / $top_clicks[$partner][$project_id], 3);
				}
				else {
					$epc = 0;
				}

				if (!empty($top_completes[$partner][$project_id]) && !empty($top_clicks[$partner][$project_id])) {
					$ir = round($top_completes[$partner][$project_id] / $top_clicks[$partner][$project_id], 3) * 100;
				}
				else {
					$ir = 0;
				}

				$this->out('Project ID: '.$project_id.' Revenue: '.$point.' EPC: '.$epc.' IR: '.$ir.'%');
			}
			$this->out('');
		}
	}
	
	function get_respondents() {
		if (!isset($this->args[0])) {
			return false; 
		}
		$report_id = $this->args[0];
		
		App::import('Model', 'RespondentReport');
		$this->RespondentReport = new RespondentReport;
		App::import('Model', 'UserAddress');
		$this->UserAddress = new UserAddress;
		
		$time_start = microtime(true);
		
		$report = $this->RespondentReport->find('first', array(
			'conditions' => array(
				'RespondentReport.id' => $this->args[0]
			)
		));
		
		if (!$report) {
			return;
		}
		
		$respondents = $this->SurveyUserVisit->find('all', array(
			'conditions' => array(
				'SurveyUserVisit.survey_id' => $report['RespondentReport']['survey_id'],
				'SurveyUserVisit.status' => json_decode($report['RespondentReport']['filters'])
			)
		));	
		
		if (!$respondents) {
			return;
		}
		if ($respondents) {
			$settings = $this->Setting->find('list', array(
				'fields' => array('name', 'value'),
				'conditions' => array(
					'Setting.name' => array(
						'truesample.sourceid'
					),
					'Setting.deleted' => false
				)
			));
			$batch_respondents = array();				
			foreach ($respondents as $respondent) {
				$user = $this->User->find('first', array(
					'conditions' => array(
						'User.id' => $respondent['User']['id']
					),
					'fields' => array(
						'id',
						'firstname',
						'lastname',
						'email',
						'QueryProfile.state',							
						'QueryProfile.country',
						'QueryProfile.postal_code',
					)					
				));
				
				
				if (!$user) { 
					continue;
				}
				
				$user_address = $this->UserAddress->find('first', array(
					'conditions' => array(
						'UserAddress.user_id' => $respondent['User']['id'],
						'UserAddress.deleted' => false,
						'UserAddress.verified' => true,
					)
				));
				
				if (!$user_address) { 
					continue;
				}					
				$data = array();
				$data['respondentId'] = $user['User']['id'];
				$data['sourceId'] = $settings['truesample.sourceid'];
				$data['firstName'] = $user['User']['firstname'];
				$data['lastName'] = $user['User']['lastname'];
				$data['address1'] = $user_address['UserAddress']['address_line1'];
				$data['address2'] = $user_address['UserAddress']['address_line2'];
				$data['city'] = $user_address['UserAddress']['city'];
				$data['stateProvince'] = !empty($user_address['UserAddress']['state']) ? $user_address['UserAddress']['state'] : $user['QueryProfile']['state'];;
				$data['postalCode'] = !empty($user_address['UserAddress']['postal_code']) ? $user_address['UserAddress']['postal_code'] : $user['QueryProfile']['postal_code'];
				$data['countryCode'] = !empty($user_address['UserAddress']['country']) ? $user_address['UserAddress']['country'] : $user['QueryProfile']['country'];
				$data['email'] = $user['User']['email'];
				$batch_respondents[] = $data;
			}
			if (!is_dir(WWW_ROOT . 'files/truesample/')) {
				mkdir(WWW_ROOT . 'files/truesample/');
			}
			
			$filename = 'truesample_'. date('Y-m-d') .'_'. time();
			$file_dir_path = 'files/truesample/'.$filename;
			$file = WWW_ROOT . $file_dir_path;
			$fp = fopen($file, 'w');
			$total = count($batch_respondents);
			$this->out('Found '.$total."\n");
			
			fputcsv($fp, array(
				'respondentId', 
				'sourceId', 
				'firstName',
				'lastName',
				'address1',
				'address2',
				'city',
				'stateProvince',
				'postalCode',
				'countryCode',
				'email'
			));
			
			foreach ($batch_respondents as $batch_respondent) {
				fputcsv($fp, $batch_respondent);
			}
			$diff = microtime(true) - $time_start; 
			$this->out('Report with '.$total.' records generated - execution time '.round($diff).' seconds');
			
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
					$this->RespondentReport->create();
					$this->RespondentReport->save(array('RespondentReport' => array(
						'id' => $report_id,
						'path' => $aws_filename,
						'status' => 'complete'
					)), true, array('path', 'status'));	
					//unlink($file);
				}
			}
		}
	}
	
	function terming_actions() {
		if (!isset($this->args[0])) {
			return false; 
		}
		
		$report_id = $this->args[0];
		$time_start = microtime(true);
		App::import('Model', 'GroupReport');
		$this->GroupReport = new GroupReport;
		App::import('Model', 'SurveyVisit');
		$this->SurveyVisit = new SurveyVisit;
		App::import('Model', 'SurveyVisitCache');
		$this->SurveyVisitCache = new SurveyVisitCache;
		
		$group_report = $this->GroupReport->find('first', array(
			'conditions' => array(
				'GroupReport.id' => $report_id
			)
		));
		if (!$group_report) {
			$this->out('ERROR: No report by that ID found');
			return;
		}
		
		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => $group_report['GroupReport']['group_key'],
			)
		));
		if (!$group) {
			$this->out('ERROR: Selected group not found.');
			return;
		}
		
		$start_date = date(DB_DATE, strtotime($group_report['GroupReport']['date_from'])); 
		$end_date = date(DB_DATE, strtotime($group_report['GroupReport']['date_to'])); 
		
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
		
		$projects = $this->Project->find('all', array(
			'fields' => array('Project.id', 'Project.client_rate', 'Project.award'),
			'conditions' => $conditions,					
			'recursive' => -1
		));	
		
		if (empty($projects)) {
			$this->out('ERROR: No projects found in this date range');
			return;
		}
		
		$projects_data = array();
		foreach ($projects as $project) {
			$projects_data[$project['Project']['id']] = $project['Project'];
		}
		
		$statuses = unserialize(SURVEY_STATUSES);
		if($group['Group']['key'] == 'usurv') {
			App::import('Model', 'UsurvVisit');
			$this->UsurvVisit = new UsurvVisit;
			$usurv_visits = $this->UsurvVisit->find('all', array(
			'conditions' => array(
				'UsurvVisit.survey_id' => array_keys($projects_data),
				'UsurvVisit.created >=' => $group_report['GroupReport']['date_from'],
				'UsurvVisit.created <=' => $group_report['GroupReport']['date_to'],
				'UsurvVisit.award is not null',
				'UsurvVisit.survey_id is not null',
				)
			));

			if (!$usurv_visits) {
				$this->out('ERROR: No usurv visits found in this range');
				return;
			}

			$group_stats = array();
			foreach ($usurv_visits as $usurv_visit) {
				$group_stats[] = array(
					$usurv_visit['UsurvVisit']['survey_id'],
					$usurv_visit['UsurvVisit']['user_id'],
					$usurv_visit['UsurvVisit']['client_rate'],
					$usurv_visit['UsurvVisit']['award'],
					$usurv_visit['UsurvVisit']['modified'],
				);
			}
		}
		else {
			// get the group visits for this time range for projects in this range
			$survey_visits = $this->SurveyVisit->find('all', array(
				'conditions' => array(
					'SurveyVisit.survey_id' => array_keys($projects_data),
					'SurveyVisit.created >=' => $group_report['GroupReport']['date_from'],
					'SurveyVisit.created <=' => $group_report['GroupReport']['date_to'],
					'SurveyVisit.hash is not null',
					'SurveyVisit.type' => $group_report['GroupReport']['term']
				),
			));
			
			if (!$survey_visits) {
				$this->out('ERROR: No visits found in this range');
				return;
			}
			
			$group_stats = array();
			foreach ($survey_visits as $survey_visit) {
				$id = $survey_visit['SurveyVisit']['survey_id'];
				$survey_visit_cache = $this->SurveyVisitCache->find('first', array(
					'conditions' => array(
						'SurveyVisitCache.survey_id' => $id
					)
				));
				$group_stats[] = array(
					$survey_visit['SurveyVisit']['survey_id'],
					$survey_visit['SurveyVisit']['hash'],
					(isset($projects_data[$id])) ? $projects_data[$id]['client_rate'] : '',
					(isset($projects_data[$id])) ? $projects_data[$id]['award'] : '',
					$statuses[$group_report['GroupReport']['term']],
					(isset($survey_visit_cache['SurveyVisitCache']['loi_seconds'])) ? $survey_visit_cache['SurveyVisitCache']['loi_seconds'] : '',
					$survey_visit['SurveyVisit']['modified'],
				);
			}
		}

		$group_key = $group_report['GroupReport']['group_key'];
		if (!is_dir(WWW_ROOT . 'files/reports')) {
			mkdir(WWW_ROOT . 'files/reports');
		}
		if (!is_dir(WWW_ROOT . 'files/reports/'. $group_key .'/')) {
			mkdir(WWW_ROOT . 'files/reports/' . $group_key . '/');
		}
		
		$filename = $statuses[$group_report['GroupReport']['term']]. '_report_'. date('Y-m-d') .'_'. time().'.csv';
		$file_dir_path = 'files/reports/' . $group_key . '/' .$filename;
		$file = WWW_ROOT . $file_dir_path;
		$fp = fopen($file, 'w');
		$total = count($group_stats);
		$this->out('Found '.$total."\n");
		
		if ($group['Group']['key'] == 'usurv') {
			fputcsv($fp, array(
				'Project ID',
				'User ID',
				'Client Rate',
				'Award',
				'Timestamp ',
			));
		}
		else {
			fputcsv($fp, array(
				'Project ID',
				'Hash',
				'Client Rate',
				'Award',
				'Term Type',
				'LOI (Seconds)',
				'Timestamp',
			));
		}
		
		foreach ($group_stats as $group_stat) {
			fputcsv($fp, $group_stat);
		}
		
		$diff = microtime(true) - $time_start; 
		$this->out('Report with '.$total.' records generated - execution time '.round($diff).' seconds');
		
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
			'Content-Disposition' => 'attachment; filename='.$filename
		);
		if ($S3->putObject($S3->inputFile($file), $settings['s3.bucket'] , $aws_filename, S3::ACL_PRIVATE, array(), $headers)) {
			$this->GroupReport->create();
			$this->GroupReport->save(array('GroupReport' => array(
				'id' => $report_id,
				'path' => $aws_filename,
				'status' => 'complete'
			)), true, array('path', 'status'));	
			unlink($file);
		}
	}
	
	function user_survey_report() {
		ini_set('memory_limit', '2048M');
		set_time_limit(16000);
		
		$target = isset($this->args[0]) ? $this->args[0] : null;
		$targets = explode(',', $target);
		$allowed_targets = array('all', 'users', 'surveys', 'user_surveys', 'user_router_logs');
		
		if (count(array_intersect($targets, $allowed_targets)) != count($targets)) {
			$this->out('Please provide first input for target report. Multiple targets are also accepted (separated with comma without spaces).'."\n");
			$this->out('Allowed report targets are : users, surveys, user_surveys, user_router_logs or all');
			return;
		}
		
		if (!empty($this->args[1]) && !empty($this->args[2])) {
			$start_date = date('Y-m-d', strtotime($this->args[1]));
			$end_date = date('Y-m-d', strtotime($this->args[2]));
		}
		else {
			$start_date = date('Y-m-d', strtotime('-2 month'));
			$end_date = date('Y-m-d');
		}
		
		$time_start = microtime(true);
		$user_export = $survey_export = $user_survey_export = $user_router_log_export = 0;
		
		if (in_array('users', $targets) || $target == 'all') {
			// user-specific information  
			$user_export = $this->user_export($start_date, $end_date);
		}
		
		if (in_array('surveys', $targets) || $target == 'all') {
			// survey information  
			$survey_export = $this->survey_export($start_date, $end_date);
		}
		
		if (in_array('user_surveys', $targets) || $target == 'all') {
			// user-survey result 
			$user_survey_export = $this->user_survey_export($start_date, $end_date);
		}
		
		if (in_array('user_router_logs', $targets) || $target == 'all') {
			// user router logs 
			$user_router_log_export = $this->user_router_log_export($start_date, $end_date);
		}
		
		$diff = microtime(true) - $time_start; 
		
		$count = $user_export + $survey_export + $user_survey_export + $user_router_log_export;
		$this->out($count . ' report' . (($count > 1) ? 's' : '') . ' generated - execution time '.round($diff).' seconds');
	}
	
	private function user_export($start_date, $end_date) {
		$this->User->bindModel(array('hasOne' => array(
			'UserAnalysis' => array(
				'foreignKey' => 'user_id'
			)
		)), false);
		
		if (!is_dir(WWW_ROOT . 'files/reports')) {
			mkdir(WWW_ROOT . 'files/reports');
		}
		
		$filename = 'user_report_'. $start_date . '_' . $end_date .'_'. time().'.csv';
		$file_dir_path = 'files/reports/' . $filename;
		$file = WWW_ROOT . $file_dir_path;
		$fp = fopen($file, 'w');
		
		fputcsv($fp, array(
			'User ID',
			'Name',
			'Email',
			'Score',
			'Level',
			'Age',
			'Country',
			'State',
			'Created',
			'Verified',
			'Last Touched',
			'Active',
			'Origin',
			'Balance',
			'Pending',
			'Lifetime',
		));
		
		$total = $this->User->find('count', array(
			'conditions' => array(
				'User.last_touched >=' => $start_date.' 00:00:00',
				'User.last_touched <=' => $end_date.' 23:59:59'
			)
		));
		if ($total > 0) {
			$this->out('Users export started : ' . $total . ' users found from '.$start_date.' to '.$end_date);
		}
		else {
			$this->out('No users found to be exported from '.$start_date.' to '.$end_date."\n");
			return;
		}
		
		$last_user_id = 0;
		$count = $repeated = 0;
		while (true) {
			$users = $this->User->find('all', array(
				'conditions' => array(
					'User.last_touched >=' => $start_date.' 00:00:00',
					'User.last_touched <=' => $end_date.' 23:59:59',
					'User.id >' => $last_user_id
				),
				'contain' => array(
					'UserAnalysis', 
					'QueryProfile'
				),
				'limit' => '10000',
				'order' => 'User.id asc'
			));
			if (!$users) {
				break;
			}
			foreach ($users as $user) {
				$last_user_id = $user['User']['id'];
				$username = '';
				if (!empty($user['User']['username'])) {
					$username = htmlspecialchars($user['User']['username']);
				}
				elseif (!empty($user['User']['fullname'])) {
					$username = htmlspecialchars($user['User']['fullname']);
				}
				
				$score = (!empty($user['UserAnalysis']['score'])) ? $user['UserAnalysis']['score'] : '';
				$levels = unserialize(USER_LEVELS);
				$level = (!empty($user['User']['last_touched'])) ? $levels[MintVineUser::user_level($user['User']['last_touched'])] : '';
				$age = '';
				$dob = $user['QueryProfile']['birthdate'];
				if ($dob != '0000-00-00') {
					$bd = explode('-', $dob); 
					if (!empty($bd[0]) && !empty($bd[1]) && !empty($bd[2])) {
						$birthDate = array(
							$bd[1],
							$bd[2],
							$bd[0],
						);
						$age = date("md", date("U", mktime(0, 0, 0, $birthDate[0], $birthDate[1], $birthDate[2]))) > date("md") ? ((date("Y")-$birthDate[2])-1):(date("Y")-$birthDate[2]);
					}
				}
				
				$country = MintVine::country_name($user['QueryProfile']['country']);
				$state = ($user['QueryProfile']['country'] == 'US') ? $user['QueryProfile']['state'] : '';
				$active = ($user['User']['active'] == 1) ? 'Y' : 'N';
				
				fputcsv($fp, array(
					$user['User']['id'],
					$username,
					$user['User']['email'],
					$score,
					$level,
					$age,
					$country,
					$state,
					$user['User']['created'],
					$user['User']['verified'],
					$user['User']['last_touched'],
					$active,
					$user['User']['origin'],
					$user['User']['balance'],
					$user['User']['pending'],
					$user['User']['total']
				));
				$count++;
				$percentage = floor((($count / $total) * 100) / 10) * 10;
				if ($percentage % 10 == 0 && $percentage != $repeated || $percentage == 5 && $percentage != $repeated) {
					$repeated = $percentage;
					$this->out('Users exporting : ' . $percentage . '% completed');
				}
			}
		}
		fclose($fp);
		$this->out('Users report generated : ' . $file_dir_path . "\n");
		return true;
	}
	
	private function survey_export($start_date, $end_date) {
		$this->Project->bindModel(array(
			'hasOne' => array(
				'Invoice' => array(
					'className' => 'Invoice',
					'foreignKey' => 'project_id'
				),
				'FedSurvey' => array(
					'className' => 'FedSurvey',
					'foreignKey' => 'survey_id',
				),
				'CintSurvey' => array(
					'className' => 'CintSurvey',
					'foreignKey' => 'survey_id',
				),
				'RfgSurvey' => array(
					'className' => 'RfgSurvey',
					'foreignKey' => 'survey_id',
				),
				'ProjectLog' => array(
					'className' => 'ProjectLog',
					'foreignKey' => 'project_id',
					'order' => 'ProjectLog.id desc'
				),
				'SpectrumProject' => array(
					'className' => 'SpectrumProject',
					'foreignKey' => 'project_id'
				)
			)
		), false);
		$this->Project->bindModel(array(
			'hasMany' => array(
				'HistoricalRates' => array(
					'className' => 'ProjectRate',
					'foreignKey' => 'project_id'
				),
				'ProjectIr' => array(
					'className' => 'ProjectIr',
					'foreignKey' => 'project_id',
					'order' => 'ProjectIr.id DESC'
				)
			)
		), false);
		
		$filename = 'survey_report_'. $start_date . '_' . $end_date .'_'. time().'.csv';
		$file_dir_path = 'files/reports/' . $filename;
		$file = WWW_ROOT . $file_dir_path;
		$fp = fopen($file, 'w');
		
		fputcsv($fp, array(
			'Survey ID',
			'Name',
			'Group',
			'Address Required',
			'From sampling',
			'Direct',
			'Language',
			'Country',
			'Status',
			'Clicks',
			'Completes',
			'Rejects',
			'NQs',
			'OQs',
			'OQ-I',
			'NQ-S',
			'NQ-F',
			'Pre Click',
			'Pre Cpl',
			'Pre NQ'
		));
		
		$conditions = array(
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
		$contain = array(
			'Group', 
			'SurveyVisitCache',
			'FedSurvey',
			'ProjectOption' => array(
				'conditions' => array(
					'name' => array('pushed', 'pushed_email_subject', 'pushed_email_template', 'cint_required_capabilities', 'sqs_url', 'links.count', 'links.unused')
				)
			),
		);
		$total = $this->Project->find('count', array(
			'fields' => array('*'),
			'conditions' => $conditions,
			'contain' => $contain
		));
		
		if ($total > 0) {
			$this->out('Surveys export started : ' . $total . ' surveys found from '.$start_date.' to '.$end_date);
		}
		else {
			$this->out('No surveys found to be exported from '.$start_date.' to '.$end_date."\n");
			return;
		}
		
		$last_project_id = 0;
		$count = $repeated = 0;
		while (true) {
			$conditions['AND'] = array(
				'Project.id >' => $last_project_id
			);
			
			$projects = $this->Project->find('all', array(
				'fields' => array('*'),
				'conditions' => $conditions,
				'contain' => $contain,
				'limit' => '10000',
				'order' => 'Project.id asc'
			));
			if (!$projects) {
				break;
			}
			foreach ($projects as $project) {
				$last_project_id = $project['Project']['id'];
				$address_required = ($project['Project']['address_required'] == 1) ? 'Y' : 'N';
				$project_logs = $this->ProjectLog->find('all', array(
					'conditions' => array(
						'ProjectLog.project_id' => $project['Project']['id'],
						'ProjectLog.type like' => '%status%'
					),
					'fields' => array('type'),
					'order' => 'ProjectLog.id ASC',
				));
				$sampled_to_live = 'N';
				if (count($project_logs) > 1) {
					for ($i = 0; $i < count($project_logs) - 1; $i++) {
						$current_log = $project_logs[$i];
						$next_log = $project_logs[$i + 1];
						if ($current_log['ProjectLog']['type'] == 'status.sample' && $next_log['ProjectLog']['type'] == 'status.opened') {
							$sampled_to_live = 'Y';
							break;
						}
					}
				}
				$direct = ($project['FedSurvey']['direct'] == 1) ? 'Y' : 'N';
				$_STATUSES = unserialize(PROJECT_STATUSES);
				$status = empty($project['Project']['status']) ? PROJECT_STATUS_OPEN : $_STATUSES[$project['Project']['status']];
				
				$group = $this->Group->find('first', array(
					'fields' => array('id'), 
					'conditions' => array(
						'Group.key' => 'socialglimpz'
					)
				));
				
				$socialglimpz_rejects = 0;
				if ($group && ($project['Project']['group_id'] == $group['Group']['id'])) {
					$socialglimpz_rejects = $this->SocialglimpzRespondent->find('count', array(
						'conditions' => array(
							'SocialglimpzRespondent.survey_id' => $project['Project']['id'],
							'SocialglimpzRespondent.status' => 'rejected'
						)
					));
				}
				
				$pre_clicks = $pre_completes = $pre_nqs = 0;
				if ($project['Project']['prescreen']) {
					$pre_clicks = $project['SurveyVisitCache']['prescreen_clicks'];
					$pre_completes = $project['SurveyVisitCache']['prescreen_completes'];
					$pre_nqs = $project['SurveyVisitCache']['prescreen_nqs'];
				}
				fputcsv($fp, array(
					$project['Project']['id'],
					$project['Project']['prj_name'],
					$project['Group']['name'],
					$address_required,
					$sampled_to_live,
					$direct,
					$project['Project']['language'],
					$project['Project']['country'],
					$status,
					$project['SurveyVisitCache']['click'],
					$project['SurveyVisitCache']['complete'],
					$socialglimpz_rejects,
					$project['SurveyVisitCache']['nq'],
					$project['SurveyVisitCache']['overquota'],
					$project['SurveyVisitCache']['oq_internal'],
					$project['SurveyVisitCache']['speed'],
					$project['SurveyVisitCache']['fraud'],
					$pre_clicks,
					$pre_completes,
					$pre_nqs
				));
				
				$count++;
				$percentage = floor((($count / $total) * 100) / 10) * 10;
				if ($percentage % 10 == 0 && $percentage != $repeated || $percentage == 5 && $percentage != $repeated) {
					$repeated = $percentage;
					$this->out('Surveys exporting : ' . $percentage . '% completed');
				}
			}
		}
		fclose($fp);
		$this->out('Surveys report generated : ' . $file_dir_path . "\n");
		return true;
	}
	
	private function user_survey_export($start_date, $end_date) {
		$this->PanelistHistory->bindModel(array('belongsTo' => array(
			'Group' => array(
				'fields' => array('Group.name'),
				'type' => 'INNER'
			),
			'Project' => array(
				'fields' => array('Project.id', 'Project.mask', 'Project.prj_name', 'Project.bid_ir', 'Project.epc', 'Project.client_rate', 'Project.award'),
				'type' => 'INNER'
			),
			'Client' => array(
				'fields' => array('Client.client_name'),
				'type' => 'INNER'
			),
			'UserIp' => array(
				'fields' => array('UserIp.id', 'UserIp.user_agent', 'UserIp.user_language', 'UserIp.country', 'UserIp.state', 'UserIp.proxy'),
				'type' => 'INNER'
			)
		)), false);
		
		$ids = $this->PanelistHistory->find('first', array(
			'fields' => array('MIN(PanelistHistory.id) as min_id', 'MAX(PanelistHistory.id) as max_id'),
			'conditions' => array(
				'PanelistHistory.created >=' => $start_date . ' 00:00:00',
				'PanelistHistory.created <=' => $end_date . ' 23:59:59'
			)
		)); 
		$min_id = $ids[0]['min_id']; 
		$max_id = $ids[0]['max_id']; 
		
		$this->out('Min ID: '.$min_id.'; Max ID: '.$max_id); 
		
		$total = $this->PanelistHistory->find('count', array(
			'conditions' => array(
				'PanelistHistory.id >' => $min_id,
				'PanelistHistory.id <' => $max_id
			)
		)); 
		if ($total > 0) {
			$this->out('User surveys export started : ' . $total . ' user-surveys found from '.$start_date.' to '.$end_date);
		}
		else {
			$this->out('No user surveys found to be exported from '.$start_date.' to '.$end_date."\n");
			return;
		}

		
		$filename = 'user_survey_report_'. $start_date . '_' . $end_date .'_'. time().'.csv';
		$file_dir_path = 'files/reports/' . $filename;
		$file = WWW_ROOT . $file_dir_path;
		$fp = fopen($file, 'w');
		
		fputcsv($fp, array(
			'User ID', 
			'IP Address', 
			'Date', 
			'Project', 
			'Mask', 
			'Points', 
			'Group', 
			'Client', 
			'Started', 
			'Click Failure', 
			'Termed', 
			'Term Failure', 
			'LOI (User)', 
			'LOI (Project)', 
			'User Agent', 
			'Language', 
			'Country', 
			'State', 
		));
		
		
		$STATUSES = unserialize(SURVEY_STATUSES);
		$click_failures = array(
			'survey.invalid.code' => 'Invalid project code in URL',
			'survey.inactive' => 'Inactive project',
			'survey.access.invalid' => 'Accessing with wrong device type (mobile v desktop)',
			'survey.paused' => 'Survey paused',
			'panelist.hellbanned' => 'Hellbanned',
			'panelist.noinvite' => 'Panelist not invited',
			'panelist.excluded' => 'Panelist excluded by PM',
			'panelist.completed' => 'Panelist already completed project',
			'survey.overquota' => 'Project currently OQ',
			'survey.external.check' => 'External permissions check failed', 
			'panelist.noaccess' => 'Permissions failure',
			'panelist.security' => 'Security failure',
			'panelist.address' => 'Missing address',
			'ssi.link' => 'Missing SSI link',
		);
		$term_failures = array(
			'project.closed' => 'Project closed',
			'panelist.noinvite' => 'Panelist not invited',
		);
		
		$last_panelist_history_id = $min_id;
		$repeated = $i = 0;
		while (true) {
			$this->PanelistHistory->getDataSource()->reconnect();
			$panelist_histories = $this->PanelistHistory->find('all', array(
				'conditions' => array(
					'PanelistHistory.id <' => $max_id,
					'PanelistHistory.id >' => $last_panelist_history_id
				),
				'limit' => '10000',
				'order' => 'PanelistHistory.id asc'
			));
			if (!$panelist_histories) {
				break;
			}
			
			foreach ($panelist_histories as $panelist_history) {
				$i++;
				$this->out($i.' / '.$total.': '.(round($i / $total * 100, 4)).'%'); 
				$last_panelist_history_id = $panelist_history['PanelistHistory']['id'];
				$click_status = $click_failure = $term_status = $term_failure = $panelist_loi = $agent = $proxy = '';
				if (is_null($panelist_history['PanelistHistory']['click_status'])) {
					$click_status = 'Skipped';
				}
				elseif ($panelist_history['PanelistHistory']['click_status'] > 0) {
					$click_status = $STATUSES[$panelist_history['PanelistHistory']['click_status']];
				}
				if (!is_null($panelist_history['PanelistHistory']['click_status']) && isset($click_failures[$panelist_history['PanelistHistory']['click_failure']])) {
					$click_failure = $click_failures[$panelist_history['PanelistHistory']['click_failure']];
				}
				if ($panelist_history['PanelistHistory']['term_status'] > 0) {
					$term_status = $STATUSES[$panelist_history['PanelistHistory']['term_status']];
				}
				if ($panelist_history['PanelistHistory']['term_status'] == '0' && isset($term_failures[$panelist_history['PanelistHistory']['term_failure']])) {
					$term_failure = $term_failures[$panelist_history['PanelistHistory']['term_failure']];
				}
				$survey_loi = null; 

				$this->SurveyVisitCache->getDataSource()->reconnect();
				$survey_visit_cache = $this->SurveyVisitCache->find('first', array(
					'conditions' => array(
						'SurveyVisitCache.survey_id' => $panelist_history['Project']['id']
					)
				));
				if (!is_null($panelist_history['PanelistHistory']['panelist_loi'])) {
					$panelist_loi = round($panelist_history['PanelistHistory']['panelist_loi'] / 60);
					if ($panelist_history['PanelistHistory']['term_status'] == SURVEY_COMPLETED) {
						if (isset($survey_visit_cache['SurveyVisitCache']['loi_seconds']) && !empty($survey_visit_cache['SurveyVisitCache']['loi_seconds'])) {
							$survey_loi = round($survey_visit_cache['SurveyVisitCache']['loi_seconds'] / 60);
						}
					}
				}
				fputcsv($fp, array(
					$panelist_history['PanelistHistory']['user_id'],
					$panelist_history['PanelistHistory']['ip_address'],
					$panelist_history['PanelistHistory']['created'],
					$panelist_history['Project']['id'],
					$panelist_history['Project']['mask'],
					$panelist_history['Project']['award'],
					$panelist_history['Group']['name'],
					$panelist_history['Client']['client_name'],
					$click_status,
					$click_failure,
					$term_status,
					$term_failure,
					$panelist_loi,
					$survey_loi,
					$panelist_history['UserIp']['user_agent'],
					$panelist_history['UserIp']['user_language'],
					$panelist_history['UserIp']['country'],
					$panelist_history['UserIp']['state']
				));
			}
		}
		fclose($fp);
		$this->out('User surveys report generated : ' . $file_dir_path . "\n");
		return true;
	}
	
	private function user_router_log_export($start_date, $end_date) {	
		
		$this->out('Starting user router log export'); 
		$ids = $this->UserRouterLog->find('first', array(
			'fields' => array('MIN(UserRouterLog.id) as min_id', 'MAX(UserRouterLog.id) as max_id'),
			'conditions' => array(
				'UserRouterLog.created >=' => $start_date . ' 00:00:00',
				'UserRouterLog.created <=' => $end_date . ' 23:59:59'
			)
		)); 
		$min_id = $ids[0]['min_id']; 
		$max_id = $ids[0]['max_id']; 
		
		$this->out('Min ID: '.$min_id.'; Max ID: '.$max_id); 
		
		$total = $this->UserRouterLog->find('count', array(
			'conditions' => array(
				'UserRouterLog.id >' => $min_id,
				'UserRouterLog.id <' => $max_id
			)
		)); 
		
		if ($total == 0) {
			$this->out('No user router logs found to be exported from '.$start_date.' to '.$end_date."\n");
			return;
		}
		$this->out('User router logs export started : ' . $total . ' user router logs found from '.$start_date.' to '.$end_date);

		$filename = 'user_router_log_report_'. $start_date . '_' . $end_date .'_'. time().'.csv';
		$file_dir_path = 'files/reports/' . $filename;
		$file = WWW_ROOT . $file_dir_path;
		$fp = fopen($file, 'w');
		
		fputcsv($fp, array(
			'User Router Log ID', 
			'Parent ID', 
			'User ID', 
			'Partner User ID', 
			'Survey ID', 
			'Score',
			'CPI',
			'IR',
			'LOI',
			'EPC',
			'EPCM',
			'Quota',
			'Award',
			'Result',
			'Earnings',
			'Created',
			'Modified'
		));
		
		$last_user_router_log_id = $min_id;
		$i = $count = $repeated = 0;
		while (true) {
			$this->UserRouterLog->getDataSource()->reconnect();
			$user_router_logs = $this->UserRouterLog->find('all', array(
				'conditions' => array(
					'UserRouterLog.id <' => $max_id,
					'UserRouterLog.id >' => $last_user_router_log_id
				),
				'limit' => '10000',
				'order' => 'UserRouterLog.id asc'
			));
			if (!$user_router_logs) {
				break;
			}
			
			foreach ($user_router_logs as $user_router_log) {
				$last_user_router_log_id = $user_router_log['UserRouterLog']['id'];
				fputcsv($fp, array(
					$user_router_log['UserRouterLog']['id'], 
					$user_router_log['UserRouterLog']['parent_id'], 
					$user_router_log['UserRouterLog']['user_id'], 
					$user_router_log['UserRouterLog']['partner_user_id'], 
					$user_router_log['UserRouterLog']['survey_id'], 
					$user_router_log['UserRouterLog']['score'], 
					$user_router_log['UserRouterLog']['cpi'], 
					$user_router_log['UserRouterLog']['ir'], 
					$user_router_log['UserRouterLog']['loi'], 
					$user_router_log['UserRouterLog']['epc'], 
					$user_router_log['UserRouterLog']['epcm'], 
					$user_router_log['UserRouterLog']['quota'], 
					$user_router_log['UserRouterLog']['award'], 
					$user_router_log['UserRouterLog']['result'], 
					$user_router_log['UserRouterLog']['earnings'], 
					$user_router_log['UserRouterLog']['created'],
					$user_router_log['UserRouterLog']['modified']
				));
				
				$count++;
				$percentage = floor((($count / $total) * 100) / 10) * 10;
				if ($percentage % 10 == 0 && $percentage != $repeated || $percentage == 5 && $percentage != $repeated) {
					$repeated = $percentage;
					$this->out('User router logs exporting : ' . $percentage . '% completed');
				}
			}
		}
		fclose($fp);
		$this->out('User router logs report generated : ' . $file_dir_path . "\n"); 
		return true;
	}
	
	public function poll() {
		if (empty($this->args[0]) || empty($this->args[1])) {
			$this->out('Poll id or fields missing');
			return;
		}
		
		ini_set('memory_limit', '2048M');
		App::import('Vendor', 'SiteProfile');
		$models_to_load = array('Setting', 'PollAnswer', 'PollUserAnswer', 'UserAddress');
		foreach ($models_to_load as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}

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
		$fields = explode(',', $this->args[1]);
		foreach ($fields as $key => $field) {
			$fields[$field] = ucfirst($field);
			unset($fields[$key]);
		}
		
		$extra_fields = array(
			'user_id' => 'User ID',
			'answer' => 'Answer',
			'date_taken' => 'Poll Taken'
		);
		if (isset($fields['name'])) {
			unset($fields['name']);
			$extra_fields['firstname'] = 'First Name';
			$extra_fields['lastname'] = 'Last Name';
		}

		if (isset($fields['address'])) {
			unset($fields['address']);
			$extra_fields['address'] = 'Address';
			$extra_fields['address_line2'] = 'Address (Line 2)';
			$extra_fields['first_name'] = 'First Name (Address)';
			$extra_fields['last_name'] = 'Last Name (Address)';
			$extra_fields['city'] = 'City';
			$extra_fields['postal_code'] = 'Postal Code';
			$extra_fields['state'] = 'State';
			$extra_fields['country'] = 'Country';
			$extra_fields['county'] = 'County';
		}

		$fields = array_merge($extra_fields, $fields);
		$poll_user_answers = $this->PollUserAnswer->find('all', array(
			'conditions' => array(
				'PollUserAnswer.poll_id' => $this->args[0],
			)
		));
		if (!$poll_user_answers) {
			$this->out('No results found');
			return;
		}
		$total = count($poll_user_answers);
		$this->out('Found '.$total.' results');
		
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array(
					'qe.mintvine.username',
					'qe.mintvine.password',
					'hostname.qe',
					's3.access',
					's3.secret',
					's3.bucket',
					's3.host'
				),
				'Setting.deleted' => false
			)
		));
		$poll_answers = $this->PollAnswer->find('list', array(
			'fields' => array('PollAnswer.id', 'PollAnswer.answer'),
			'conditions' => array(
				'PollAnswer.poll_id' => $this->args[0],
			)
		));

		$i = 0; 
		$rows = array($fields);
		foreach ($poll_user_answers as $poll_user_answer) {
			$i++; 
			$this->out($i.'/'.$total.': '.(round($i / $total * 100, 2)).'%'); 
			$row = array();
			$user_id = $poll_user_answer['PollUserAnswer']['user_id'];
			$answer_id = $poll_user_answer['PollUserAnswer']['answer_id'];
			$profile = Utils::qe2_mv_qualifications($user_id, $settings);
			$user = $this->User->find('first', array(
				'fields' => array('id', 'email', 'created', 'firstname', 'lastname', 'fullname'),
				'conditions' => array(
					'User.id' => $user_id
				)
			));
			if (isset($fields['address']) || isset($fields['postal_code'])) {
				$user_address = $this->UserAddress->find('first', array(
					'conditions' => array(
						'UserAddress.user_id' => $user_id,
						'UserAddress.deleted' => false
					)
				));
			}

			foreach ($fields as $field => $field_value) {
				if ($field == 'email') {
					$row[] = $user ? $user['User']['email'] : '';
				}
				elseif ($field == 'user_id') {
					$row[] = $user['User'] ? $user['User']['id'] : '';
				}
				elseif ($field == 'answer') {
					$row[] = isset($poll_answers[$answer_id]) ? $poll_answers[$answer_id] : '';
				}
				elseif ($field == 'date_taken') {
					$row[] = !empty($poll_user_answer['PollUserAnswer']['date_taken']) ? Utils::change_tz_from_utc($poll_user_answer['PollUserAnswer']['date_taken'], 'Y-m-d H:i:s') : '';
				}
				elseif ($field == 'created') {
					$row[] =  $user ? Utils::change_tz_from_utc($user['User']['created'], 'Y-m-d H:i:s') : '';
				}
				elseif ($field == 'name') {
					$row[] =  $user ? $user['User']['fullname'] : '';
				}
				elseif ($field == 'firstname') {
					$row[] =  $user ? $user['User']['firstname'] : '';
				}
				elseif ($field == 'lastname') {
					$row[] =  $user ? $user['User']['lastname'] : '';
				}
				elseif ($field == 'age') {
					$row[] = (isset($profile['birthdate'])) ? Utils::age($profile['birthdate'][0]) : '';
				}
				elseif ($field == 'address') {
					$row[] = isset($user_address['UserAddress']) ? $user_address['UserAddress']['address_line1'] : '';
				}
				elseif ($field == 'first_name') {
					$row[] = isset($user_address['UserAddress']) ? $user_address['UserAddress']['first_name'] : '';
				}
				elseif ($field == 'last_name') {
					$row[] = isset($user_address['UserAddress']) ? $user_address['UserAddress']['last_name'] : '';
				}
				elseif ($field == 'address_line2') {
					$row[] = isset($user_address['UserAddress']) ? $user_address['UserAddress']['address_line2'] : '';
				}
				elseif ($field == 'city') {
					$row[] = isset($user_address['UserAddress']) ? $user_address['UserAddress']['city'] : '';
				}
				elseif ($field == 'postal_code') {
					$row[] = isset($user_address['UserAddress']) ? $user_address['UserAddress']['postal_code'] : '';
				}
				elseif ($field == 'state') {
					$row[] = isset($user_address['UserAddress']) ? $user_address['UserAddress']['state'] : '';
				}
				elseif ($field == 'country') {
					$row[] = isset($user_address['UserAddress']) ? $user_address['UserAddress']['country'] : '';
				}
				elseif ($field == 'county') {
					$row[] = isset($user_address['UserAddress']) ? $user_address['UserAddress']['county'] : '';
				}
				elseif (isset($profile[$field])) {
					if ($field == 'hhi') {
						$row[] = isset($hhi_keys[$profile[$field][0]]) ? $hhi_keys[$profile[$field][0]] : '';
					}
					elseif ($field == 'relationship') {
						$row[] = isset($marital_keys[$profile[$field][0]]) ? $marital_keys[$profile[$field][0]] : '';
					}
					elseif ($field == 'housing_own') {
						$row[] = isset($home_keys[$profile[$field][0]]) ? $home_keys[$profile[$field][0]] : '';
					}
					elseif ($field == 'smartphone') {
						$row[] = isset($smartphone_keys[$profile[$field][0]]) ? $smartphone_keys[$profile[$field][0]] : '';
					}
					elseif ($field == 'tablet') {
						$row[] = isset($tablet_keys[$profile[$field][0]]) ? $tablet_keys[$profile[$field][0]] : '';
					}
					elseif ($field == 'employment') {
						$row[] = isset($employment_keys[$profile[$field][0]]) ? $employment_keys[$profile[$field][0]] : '';
					}
					elseif ($field == 'ethnicity') {
						$row[] = isset($ethnicity_keys[$profile[$field][0]]) ? $ethnicity_keys[$profile[$field][0]] : '';
					}
					elseif ($field == 'industry') {
						$row[] = isset($industry_keys[$profile[$field][0]]) ? $industry_keys[$profile[$field][0]] : '';
					}
					elseif ($field == 'education') {
						$row[] = isset($edu_keys[$profile[$field][0]]) ? $edu_keys[$profile[$field][0]] : '';
					}
					elseif ($field == 'children') {
						$row[] = isset($children_keys[$profile[$field][0]]) ? $children_keys[$profile[$field][0]] : '';
					}
					elseif ($field == 'organization_size') {
						$row[] = isset($organization_size_keys[$profile[$field][0]])? $organization_size_keys[$profile[$field][0]] : '';
					}
					elseif ($field == 'organization_revenue') {
						$row[] = isset($organization_revenue_keys[$profile[$field][0]]) ? $organization_revenue_keys[$profile[$field][0]] : '';
					}
					elseif ($field == 'job') {
						$row[] = isset($job_keys[$profile[$field][0]]) ? $job_keys[$profile[$field][0]] : '';
					}
					elseif ($field == 'department') {
						$row[] = isset($department_keys[$profile[$field][0]]) ? $department_keys[$profile[$field][0]] : '';
					}
					elseif ($field == 'housing_purchased') {
						$row[] = isset($housing_purchase_keys[$profile[$field][0]]) ? $housing_purchase_keys[$profile[$field][0]] : '';
					}
					elseif ($field == 'housing_plans') {
						$row[] = isset($housing_plans_keys[$profile[$field][0]]) ? $housing_plans_keys[$profile[$field][0]] : '';
					}
					elseif ($field == 'airlines') {
						$row[] = isset($airlines_keys[$profile[$field][0]]) ? $airlines_keys[$profile[$field][0]] : '';
					}
					else {
						$row[] = isset($profile[$field]) ? $profile[$field][0] : '';
					}
				}
				else {
					$row[] = '';
				}
			}
			$rows[] = $row;
		}
		
		$local_file = WWW_ROOT . 'files/reports/poll-' . $this->args[0] . '.csv';
		$fp = fopen($local_file, 'w');
		foreach ($rows as $row) {
			fputcsv($fp, $row);
		}

		CakePlugin::load('Uploader');
		App::import('Vendor', 'Uploader.S3');
		$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);
		$report = $this->Report->find('first', array(
			'conditions' => array(
				'type' => 'poll',
				'poll_id' => $this->args[0]
			)
		));
		if (!empty($report['Report']['path'])) {

			// delete existing file from s3
			$S3->deleteObject($settings['s3.bucket'], $report['Report']['path']);
		}

		$aws_filename = 'files/reports/polls/' . $this->args[0] . '.csv';
		$headers = array('Content-Disposition' => 'attachment; filename=' . $this->args[0] . '.csv');
		$save = $S3->putObject($S3->inputFile($local_file), $settings['s3.bucket'], $aws_filename, S3::ACL_PRIVATE, array(), $headers);
		if ($save) {
			$this->Report->create();
			$this->Report->save(array('Report' => array(
				'id' => $report['Report']['id'],
				'path' => $aws_filename,
				'status' => 'complete'
			)), true, array('status', 'path'));
			$this->out('Report for Poll ID: '.  $this->args[0] .' has been generated successfully!');
		}
		else {
			$this->out('Error saving report to S3');
		}
		
		// remove local file
		@unlink($local_file);
	}

	public function panelist_vs_survey_visits() {
		ini_set('memory_limit', '1024M');
		ini_set('max_execution_time', 1200);
		$group_id = $threshold = $start_date = $end_date = $report_id = null;
		if (isset($this->args[0])) {
			$start_date = $this->args[0];
		}
		if (isset($this->args[1])) {
			$end_date = $this->args[1];
		}
		if (isset($this->args[2])) {
			$group_id = $this->args[2];
		}
		if (isset($this->args[3])) {
			$threshold = $this->args[3];
		}
		if (isset($this->args[4])) {
			$report_id = $this->args[4];
		}

		if (is_null($start_date) || is_null($end_date) || is_null($group_id) || is_null($threshold)) {
			$this->out('Missing required data');
			return;
		}

		$groups = $this->Group->find('list', array(
			'fields' => array('Group.id', 'Group.name'),
			'recursive' => -1,
			'order' => 'Group.name ASC'
		));

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
			'recursive' => -1
		));

		$mintvine_partner = $this->Partner->find('first', array(
			'fields' => array('Partner.id'),
			'conditions' => array(
				'Partner.key' => 'mintvine'
			)
		));

		$mismatched_terms = array();
		if ($projects) {
			$survey_visits = $this->SurveyVisit->find('all', array(
				'fields' => array(
					'SurveyVisit.id', 
					'SurveyVisit.survey_id', 
					'SurveyVisit.partner_user_id', 
					'SurveyVisit.result_id', 
					'SurveyVisit.created', 
					'SurveyVisit.modified'
				),
				'conditions' => array(
					'SurveyVisit.survey_id' => Hash::extract($projects, '{n}.Project.id'),
					'SurveyVisit.type' => SURVEY_CLICK,
					'SurveyVisit.partner_id' => $mintvine_partner['Partner']['id']
				),
				'recursive' => -1
			));
			
			if (!empty($survey_visits)) {
				foreach ($survey_visits as $survey_visit) {
					if (strpos($survey_visit['SurveyVisit']['partner_user_id'], '-') === false) {
						continue;
					}
					list($project_id, $survey_visit_user_id) = explode('-', $survey_visit['SurveyVisit']['partner_user_id']);
					if (empty($survey_visit_user_id)) {
						continue;
					}
					$panelist_histories = $this->PanelistHistory->find('all', array(
						'fields' => array(
							'PanelistHistory.id', 
							'PanelistHistory.user_id',
							'PanelistHistory.group_id',
							'PanelistHistory.project_id',
							'PanelistHistory.term_status',
							'PanelistHistory.panelist_loi',
						),
						'conditions' => array(
							'PanelistHistory.project_id' => $survey_visit['SurveyVisit']['survey_id'],
							'PanelistHistory.user_id' => $survey_visit_user_id,
							'OR' =>  array(
								'PanelistHistory.term_status is null',
								'PanelistHistory.term_status !=' => $survey_visit['SurveyVisit']['result_id']		
							)
						),
						'recursive' => -1
					));
					foreach ($panelist_histories as $panelist_history) {
						$survey_time_recorded = strtotime($survey_visit['SurveyVisit']['modified']) - strtotime($survey_visit['SurveyVisit']['created']);
						$panelist_time_recorded = isset($panelist_history['PanelistHistory']['panelist_loi']) ? $panelist_history['PanelistHistory']['panelist_loi'] : 0;
						$time_recorded_difference = $panelist_time_recorded - $survey_time_recorded;
						$diff = $panelist_time_recorded > 0 ? round(($survey_time_recorded * 100) / $panelist_time_recorded) : 0;
						
						if ($diff > $threshold) {
							$mismatched_terms[] = array(
								$survey_visit_user_id,
								$survey_visit['SurveyVisit']['survey_id'],
								$groups[$group_id],
								$survey_visit['SurveyVisit']['created'],
								$survey_visit['SurveyVisit']['modified'],
								round($survey_time_recorded / 60),
								$panelist_time_recorded / 60,
								$time_recorded_difference / 60,
							);
						}
					}
				}
			}
		}
		$csv_rows = array_merge(array(array(
			'User ID',
			'Project ID',
			'Group',
			'Survey Started',
			'Survey Ended',
			'Survey Time',
			'Panelist Time',
			'Time Difference',
		)), $mismatched_terms);

		$filename = strtolower($group_id.'-panelist_survey_difference-'.$start_date.'-'.$end_date.'.csv');
		$file = WWW_ROOT . 'files/reports/' . $filename;
		
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

		$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);			
		$headers = array(
			'Content-Disposition' => 'attachment; filename=' . $filename
		);

		$csv_file = fopen($file, 'w');
		foreach ($csv_rows as $row) {
			fputcsv($csv_file, $row, ',', '"');
		}
		fclose($csv_file);

		$aws_filename = 'files/reports/' . $filename;
		$this->out('Writing to S3 ' . $aws_filename . ' from ' . $file);

		if ($S3->putObject($S3->inputFile($file), $settings['s3.bucket'] , $filename, S3::ACL_PRIVATE, array(), $headers)) {
			$this->Report->create();
			$this->Report->save(array('Report' => array(
				'id' => $report_id,
				'path' => $filename,
				'status' => 'complete',
			)), true, array('status', 'path'));
			unlink($file);
		}
	}

	public function ip_address() {
		if (!isset($this->args[0]) || empty($this->args[0])) {
			return false;
		}
		
		$fields = array('Project.id', 'Project.description', 'Project.survey_name', 'IpProxy.*', 'UserIp.*', 'User.*');
		$conditions = array('UserIp.ip_address' => $this->args[0]);
		$order = 'UserIp.id DESC';
		$joins = array(
			array(
				'alias' => 'IpProxy',
				'table' => 'ip_proxies',
				'type' => 'LEFT',
				'conditions' => array(
					'UserIp.ip_address = IpProxy.ip_address',
				)
			),
			array(
				'alias' => 'Project',
				'table' => 'projects',
				'type' => 'LEFT',
				'conditions' => array(
					'UserIp.survey_id = Project.id',
				)
			),
			array(
				'alias' => 'User',
				'table' => 'users',
				'type' => 'LEFT',
				'conditions' => array(
					'UserIp.user_id = User.id',
				)
			)
		);

		$user_ips = $this->UserIp->find('all', array(
			'fields' => $fields,
			'order' => $order,
			'joins' => $joins,
			'conditions' => $conditions
		));

		if ($user_ips) {
			$settings = $this->Setting->find('list', array(
				'fields' => array('Setting.name', 'Setting.value'),
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
			
			$SURVEY_STATUSES = unserialize(SURVEY_STATUSES);
			$shown_surveys = array();
			$csv_rows = array(array(
				'User ID',
				'Date',
				'Timezone',
				'Activity',
				'End Result',
				'IP Address',
				'Location',
				'Proxy',
				'User Agent',
				'Languages',
			));

			foreach ($user_ips as $key => $user_ip) {
				if ($user_ip['UserIp']['type'] == 'survey') {
					$user_survey_visits = $this->SurveyUserVisit->find('first', array(
						'recursive' => -1,
						'conditions' => array(
							'SurveyUserVisit.user_id' => $user_ip['UserIp']['user_id'],
							'SurveyUserVisit.survey_id' => $user_ip['Project']['id']
						),
						'order' => 'SurveyUserVisit.id DESC'
					));
					if ($user_survey_visits) {
						$user_ips[$key]['SurveyUserVisit'] = $user_survey_visits['SurveyUserVisit'];
					}
					$survey_visit_cache = $this->SurveyVisitCache->find('first', array(
						'conditions' => array(
							'SurveyVisitCache.survey_id' => $user_ip['Project']['id']
						)
					));
					if ($survey_visit_cache) {
						$user_ips[$key]['SurveyVisitCache'] = $survey_visit_cache['SurveyVisitCache'];
					}
				}
				
				if ($user_ip['UserIp']['type'] == 'survey' && in_array($user_ip['Project']['id'], $shown_surveys)) {
					continue;
				}
				
				$shown_surveys[] = $user_ip['Project']['id'];
				if ($user_ip['UserIp']['type'] == 'survey') {
					$type = '#' . $user_ip['Project']['id'] . ': ' . (!empty($user_ip['Project']['description']) ? $user_ip['Project']['description'] : $user_ip['Project']['survey_name']);
				}
				else {
					$type = $user_ip['UserIp']['type'];
				}
				
				$result = '';
				if ($user_ip['UserIp']['type'] == 'survey' && isset($user_ip['SurveyUserVisit']) && !empty($user_ip['SurveyUserVisit']['status'])) {
					if ($user_ip['SurveyUserVisit']['status'] == SURVEY_COMPLETED) {
						$result = $SURVEY_STATUSES[$user_ip['SurveyUserVisit']['status']];
					}

					if ($user_ip['SurveyUserVisit']['status'] == SURVEY_COMPLETED) {
						$survey_visit_cache = $this->SurveyVisitCache->find('first', array(
							'conditions' => array(
								'SurveyVisitCache.survey_id' => $user_ip['Project']['id']
							)
						));

						$diff = strtotime($user_ip['SurveyUserVisit']['modified']) - strtotime($user_ip['SurveyUserVisit']['created']);
						$minutes = round($diff / 60, 1);
						$result .= ' ' . $minutes . ' minutes';
						if (isset($survey_visit_cache['SurveyVisitCache']['loi_seconds']) && !empty($survey_visit_cache['SurveyVisitCache']['loi_seconds'])) {
							$result .= ' Survey LOI: ' . round($survey_visit_cache['SurveyVisitCache']['loi_seconds'] / 60) . ' minutes';
						}
					}
				}
				$location = array();
				if (!empty($user_ip['UserIp']['state'])) {
					$location[] = $user_ip['UserIp']['state'];
				}
				if (!empty($user_ip['UserIp']['country'])) {
					$location[] = $user_ip['UserIp']['country'];
				}
				
				$csv_rows[] = array(
					$user_ip['User']['id'],
					$user_ip['UserIp']['created'],
					$user_ip['UserIp']['timezone'],
					$type,
					$result,
					$user_ip['UserIp']['ip_address'],
					implode(', ', $location),
					(!is_null($user_ip['UserIp']['proxy'])) ? $user_ip['IpProxy']['proxy_score'] : 'Unchecked',
					$user_ip['UserIp']['user_agent'],
					$user_ip['UserIp']['user_language']
				);
			}
		}

		if (!is_dir(WWW_ROOT . 'files/reports/ip/')) {
			mkdir(WWW_ROOT . 'files/reports/ip/');
		}

		$filename = 'ip-address-' . $this->args[0] . '.csv';
		$file_dir_path = 'files/reports/ip/' . $filename;
		$file = WWW_ROOT . $file_dir_path;
		$csv_file = fopen($file, 'w');
		foreach ($csv_rows as $row) {
			fputcsv($csv_file, $row, ',', '"');
		}
		fclose($csv_file);
		
		CakePlugin::load('Uploader');
		App::import('Vendor', 'Uploader.S3');
		$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);
		$report = $this->Report->find('first', array(
			'conditions' => array(
				'type' => 'ip',
				'ip_address' => $this->args[0]
			)
		));
		if (!empty($report['Report']['path'])) {
			$S3->deleteObject($settings['s3.bucket'], $report['Report']['path']);
		}

		$aws_filename = 'files/reports/ip/' . $filename;
		$headers = array('Content-Disposition' => 'attachment; filename=' . $filename);
		if ($S3->putObject($S3->inputFile($file), $settings['s3.bucket'], $aws_filename, S3::ACL_PRIVATE, array(), $headers)) {
			$this->Report->create();
			$this->Report->save(array('Report' => array(
				'id' => $report['Report']['id'],
				'path' => $aws_filename,
				'status' => 'complete'
			)), true, array('status', 'path'));
			
			unlink($file);
			$this->out('Report for Ip Address: ' . $this->args[0] . ' has been generated successfully!');
		}
		else {
			$this->out('Error saving report to S3');
		}
	}
	
	public function export_panelist_data() {
		if (!isset($this->args[0])) {
			$this->out('Missing required data');
			return; 
		}
		ini_set('memory_limit', '1024M');
		ini_set('max_execution_time', 1200);
		
		$report_id = $this->args[0];
		$models_to_load = array('UserReport', 'SurveyVisit', 'UserAddress', 'TwilioNumber');
		foreach ($models_to_load as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		$user_report = $this->UserReport->find('first', array(
			'conditions' => array(
				'UserReport.id' => $report_id
			)
		));
		if (!$user_report) {
			$this->out('ERROR: No report by that ID found');
			return;
		}	
		
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
		
		$header_values = json_decode($user_report['UserReport']['fields'], true);
		$user_ids = json_decode($user_report['UserReport']['panelist_ids'], true);
		
		if (!empty($user_ids)) {
			if ($user_ids[0] == 'US') {
				$user_ids = $this->User->find('all', array(
					'fields' => array('User.id', 'User.id'),
					'conditions' => array(
						'User.last_touched >=' => date(DB_DATETIME, strtotime('-7 days')),
						'User.active' => true,
						'User.hellbanned' => false,
						'User.deleted' => false,
						'QueryProfile.country' => 'US'
					)
				));
				$user_ids = Hash::extract($user_ids, '{n}.User.id'); 
			}
			elseif ($user_ids[0]{0} == '#') {
				$project_id = str_replace('#', '', $user_ids[0]);
				$user_ids = array();
			
				$survey_visits = $this->SurveyVisit->find('list', array(
					'fields' => array(
						'SurveyVisit.partner_user_id'
					),
					'conditions' => array(
						'SurveyVisit.survey_id' => $project_id,
						'SurveyVisit.type' => SURVEY_COMPLETED
					),
					'recursive' => -1
				));
				if (!empty($survey_visits)) {
					foreach ($survey_visits as $partner_user_id) {
						list($project_id, $user_id, $trash, $nothing) = explode('-', $partner_user_id);
						$user_ids[] = $user_id;
					}
				}
			}
			elseif (strpos($user_ids[0], '-') !== false) {
				$real_user_ids = array();
				foreach ($user_ids as $partner_user_id) {
					list($survey_id, $user_id, $trash) = explode('-', $partner_user_id); 
					$real_user_ids[$partner_user_id] = $user_id;
				}
				$user_ids = array_values($real_user_ids);
				$lookup_by_partner_user_id = array_flip($real_user_ids);
			}
			elseif (strpos($user_ids[0], 'm') !== false) {
				$real_user_ids = array();
				foreach ($user_ids as $survey_hash) {
					$survey_visit = $this->SurveyVisit->find('first', array(
						'fields' => array('SurveyVisit.partner_user_id'),
						'conditions' => array(
							'SurveyVisit.hash' => $survey_hash
						),
						'recursive' => -1
					));
					if ($survey_visit) {
						list($survey_id, $user_id, $trash) = explode('-', $survey_visit['SurveyVisit']['partner_user_id']);
						$real_user_ids[$survey_hash] = $user_id;
					}
				}
				$user_ids = array_values($real_user_ids);
				$lookup_by_hash = array_flip($real_user_ids);
			}
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
			$csv_rows = array(array_values($header_values));
			foreach ($user_ids as $user_id) {
				$profile = Utils::qe2_mv_qualifications($user_id, $settings);
				$user = $this->User->find('first', array(
					'conditions' => array(
						'User.id' => $user_id
					)
				));

				$user_address = $this->UserAddress->find('first', array(
					'conditions' => array(
						'UserAddress.user_id' => $user_id,
						'UserAddress.deleted' => false
					),
					'order' => 'UserAddress.id DESC'
				));
				
				$twilio_number = $this->TwilioNumber->find('first', array(
					'conditions' => array(
						'TwilioNumber.id' => $user['User']['twilio_number_id']
					)
				));
				$row = array();
				foreach ($header_values as $key => $val) {
					list($model, $field) = explode('.', $key);
					
					// see https://basecamp.com/2045906/projects/1413421/todos/314276249
					if (!isset($profile[$field])) {
						$profile[$field] = 0; 
					}
					
					if ($field == 'hhi') {
						$value = isset($hhi_keys[$profile[$field][0]]) 
							? $hhi_keys[$profile[$field][0]]
							: '';
					}
					elseif ($field == 'relationship') {
						$value = isset($marital_keys[$profile[$field][0]])
							? $marital_keys[$profile[$field][0]]
							: '';
					}
					elseif ($field == 'housing_own') {
						$value = isset($home_keys[$profile[$field][0]]) 
							? $home_keys[$profile[$field][0]]
							: '';
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
						$value = isset($industry_keys[$profile[$field][0]]) 
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
					elseif ($field == 'postal_code') {
						$value = isset($profile[$field][0]) ? $profile[$field][0]: '';
					}
					else {
						if ($model == 'UserAddress') {
							$value = isset($user_address[$model][$field]) ? $user_address[$model][$field]: '';
						}
						elseif ($model == 'TwilioNumber') {
							$value = isset($twilio_number[$model][$field]) ? $twilio_number[$model][$field]: '';
						}
						elseif ($model == 'User') {
							$value = isset($user[$model][$field]) ? $user[$model][$field]: '';
						}
						else {
							$value = isset($profile[$field][0]) ? $profile[$field][0]: '';
						}
					}
					$row[] = $value;
				}
				if (isset($lookup_by_partner_user_id)) {
					$row[] = $lookup_by_partner_user_id[$query_profile['QueryProfile']['user_id']];
				}
				$csv_rows[] = $row;
			}

			$filename = 'user_export-'. gmdate(DB_DATE, time()) . '.csv';
			$file = WWW_ROOT . 'files/reports/' . $filename;
			
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

			$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);			
			$headers = array(
				'Content-Disposition' => 'attachment; filename=' . $filename
			);

			$csv_file = fopen($file, 'w');
			foreach ($csv_rows as $row) {
				fputcsv($csv_file, $row, ',', '"');
			}
			fclose($csv_file);

			$aws_filename = 'files/reports/' . $filename;
			$this->out('Writing to S3 ' . $aws_filename . ' from ' . $file);

			if ($S3->putObject($S3->inputFile($file), $settings['s3.bucket'] , $filename, S3::ACL_PRIVATE, array(), $headers)) {
				$this->UserReport->create();
				$this->UserReport->save(array('UserReport' => array(
					'id' => $report_id,
					'path' => $filename,
					'status' => 'complete',
				)), true, array('status', 'path'));
				unlink($file);
			}
		}
	}	

}
