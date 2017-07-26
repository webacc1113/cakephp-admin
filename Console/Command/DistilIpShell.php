<?php
App::uses('CakeEmail', 'Network/Email');
App::import('Lib', 'Utilities');
App::import('Lib', 'Surveys');
App::uses('HttpSocket', 'Network/Http');

class DistilIpShell extends AppShell {
	public $uses = array('RouterLog', 'SurveyVisit', 'Client', 'Group', 'SurveyUser', 'Project', 'RouterLog', 'Setting', 'ProjectLog', 'UserIp', 'SurveyUserVisit', 'SurveyVisit');
	public $tasks = array('Surveys');

	// sending in project_id will output a CSV of the data to review
	// sending in group_id will output an aggregate of count per-project
	// start/end date should only be applied to groups
	public function main() {
		if (!isset($this->params['group']) && !isset($this->params['project'])) {
			$this->out('Please provide a project or group id');
			return;
		}
				
		// for groups, pull the list of project ids
		if (isset($this->params['group'])) {

			$start_date = (isset($this->params['start_date']) ? $this->params['start_date'] : date('Y-m-d 00:00:00'));
			$end_date = (isset($this->params['end_date']) ? $this->params['end_date'] : date('Y-m-d 23:59:59'));


			$this->Project->unbindModel(array('hasMany' => array('SurveyPartner', 'ProjectOption', 'ProjectAdmin')));
			$project_ids = $this->Project->find('all', array(
				'fields' => array('Project.id'),
				'conditions' => array(
					'Project.group_id' => $this->params['group'],
					'Project.date_created >=' => $start_date,
					'Project.date_created <=' => $end_date,
					'SurveyVisitCache.click >' => '0' // for perf reasons, on large lucid pulls excluding this list will be good
				),
			));
			$project_ids = Hash::extract($project_ids, '{n}.Project.id');
			$this->out('Processing '.count($project_ids).' from '.$start_date.' to '.$end_date);
		}
		elseif (isset($this->params['project'])) {
			$project_ids = array($this->params['project']); 
			$this->out('Processing '.$this->params['project']);
		}
		
		if (empty($project_ids)) {
			$this->out('ERROR: No projects in that range or group'); 
			return; 
		}

		$this->out('Processing '.count($project_ids).' projects');
		$project_summaries = array();
		// iterate over each project and generate the data
		$project_progress = 0; 
		$total = count($project_ids); 
		$total_clicks = array();
		foreach ($project_ids as $project_id) {
			// pull the survey visits related to these project(s)
			$ip_addresses = $this->SurveyVisit->find('list', array(
				'fields' => array('SurveyVisit.ip'),
				'conditions' => array(
					'SurveyVisit.survey_id' => $project_id,
					'SurveyVisit.type' => SURVEY_CLICK
				),
				'recursive' => -1
			));
			$total_clicks[$project_id] = count($ip_addresses); 
			$project_progress++; 
			$this->out($project_progress.'/'.$total.' Processing #'.$project_id.': '.count($ip_addresses));
		
			// possible duplicate ips are set if deduper is off; remove these because they will throw off data
			$ip_addresses = array_unique($ip_addresses); 
			if (empty($ip_addresses)) {
				$this->out('There are no survey_visit records');
				return;
			}
		
			// store all unique parts 
			$ips_by_part = array(
				1 => array(/* xxx. */),
				2 => array(/* xxx.xxx */),
				3 => array(/* xxx.xxx.xxx */),
			);
			foreach ($ip_addresses as $ip_address) {
				$ip_parts = explode('.', $ip_address);
				array_pop($ip_parts);
			
				for ($i = 3; $i > 0; $i--) {
					$ips_by_part[$i][] = implode('.', $ip_parts);
					array_pop($ip_parts);
				}
			}
		
			// count the instances of IP addresses by part
			$counted_ip_parts = array();
			for ($i = 3; $i > 0; $i--) {
				$counted_ip_parts[$i] = array_count_values($ips_by_part[$i]);
			}
			
			// summarize the project-level data
			// here we'll do the group-level summary roll-ups on a per-project basis
			$summary = array();
			for ($i = 3; $i > 0; $i--) {
				if (!isset($summary[$i])) {
					$summary[$i] = 0; 
				}
				foreach ($counted_ip_parts[$i] as $ip => $count) {
					if ($count <= 1) {
						continue;
					}
					$summary[$i] = $summary[$i] + $count; 
				}
			}
			$project_summaries[$project_id] = $summary;
		}

		// generate and write the CSV
		if (isset($this->params['group'])) {
			$file_name = WWW_ROOT.'files/distil_ip_group_'.$this->params['group'].'_'.date(DB_DATE, strtotime($start_date)).'_'.date(DB_DATE, strtotime($end_date)).'.csv'; 
			$fp = fopen($file_name, 'w');
			fputcsv($fp, array(
				'Project ID',
				'Total Clicks', 
				'# Clicks That Matched xxx.xxx.xxx',
				'# Clicks That Matched  xxx.xxx',
				'# Clicks That Matched  xxx',		
			));
			// todo
			foreach ($project_summaries as $project_id => $project_summary) {
				fputcsv($fp, array(
					$project_id,
					$total_clicks[$project_id],
					$project_summary[3],
					$project_summary[2],
					$project_summary[1]
				)); 
			}
			fclose($fp);
		}
		// for projects, output the data to cross-check
		elseif (isset($this->params['project'])) {
			// generate the array we'll use to generate the CSV
			$csv_rows = array();
			foreach ($ip_addresses as $ip_address) {
				$csv_row = array($ip_address);
			
				$ip_parts = explode('.', $ip_address);
				array_pop($ip_parts);
			
				for ($i = 3; $i > 0; $i--) {
					$ip_part = implode('.', $ip_parts);
					array_pop($ip_parts);
					$csv_row[$i] = $counted_ip_parts[$i][$ip_part]; 
				}
				$csv_rows[] = $csv_row; 
			}
			$file_name = WWW_ROOT.'files/distil_ip_project_'.$this->params['project'].'.csv'; 
			$fp = fopen($file_name, 'w');
			fputcsv($fp, array(
				'IP Address',
				'Matched xxx.xxx.xxx',
				'Matched xxx.xxx',
				'Matched xxx',		
			));
			foreach ($csv_rows as $csv_row) {
				fputcsv($fp, array(
					$csv_row[0],
					$csv_row[3],
					$csv_row[2],
					$csv_row[1]
				)); 
			}

			fputcsv($fp, array('', '', '', '')); 
			fputcsv($fp, array(
				'Project ID',
				'# Clicks That Matched xxx.xxx.xxx',
				'# Clicks That Matched  xxx.xxx',
				'# Clicks That Matched  xxx',		
			));

			fputcsv($fp, array(
				$project_id,
				$summary[3],
				$summary[2],
				$summary[1]
			)); 
			
			fclose($fp);
		}
		$this->out('Wrote '.$file_name); 
	}

	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser->addOption('project', array(
			'short' => 'p',
			'help' => 'Project ID'
		));
		$parser->addOption('group', array(
			'short' => 'g',
			'help' => 'group',
			'required' => true
		));
		$parser->addOption('start_date', array(
			'short' => 's',
			'help' => 'start date',
			'required' => true
		));
		$parser->addOption('end_date', array(
			'short' => 'e',
			'help' => 'end date',
			'required' => true
		));
		$parser->addOption('limit', array(
			'short' => 'l',
			'help' => 'limit',
			'required' => true
		));
		return $parser;
	}
}


