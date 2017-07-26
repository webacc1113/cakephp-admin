<?php
App::import('Lib', 'Utilities');
class UsurvShell extends AppShell {
	
	public $uses = array(
		'Client', 
		'Group', 
		'Partner', 
		'Project', 
		'ProjectLog',
		'Setting'
	);
	
	public function manage_projects() {
		$required_settings = array(
			'usurv.gb.active', 
			'usurv.us.active',
			'usurv.ca.active'
		); 
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => $required_settings,
				'Setting.deleted' => false
			)
		));
		
		if (count($required_settings) != count($settings)) {
			$this->out('Usurv settings missing');
			return false; 
		}
		
		$group = $this->Group->find('first', array(
			'fields' => array('Group.id'),
			'conditions' => array(
				'Group.key' => 'usurv'
			)
		));
		$client = $this->Client->find('first', array(
			'fields' => array('Client.id'),
			'conditions' => array(
				'Client.key' => 'usurv',
				'Client.deleted' => false
			)
		));
		if (!$group || !$client) {
			$this->out('Missing usurv group and/or client');
			return;
		}
		
		$supported_countries = array('US', 'GB', 'CA'); 
		
		foreach ($supported_countries as $supported_country) {
			
			// first create the project
			$project = $this->Project->find('first', array(
				'conditions' => array(
					'Project.status' => PROJECT_STATUS_OPEN,
					'Project.active' => true,
					'Project.group_id' => $group['Group']['id'],
					'Project.country' => $supported_country
				),
			));
			
			switch ($supported_country) {
				case 'US': 
					$setting_flag = 'usurv.us.active'; 
				break;
				case 'GB': 
					$setting_flag = 'usurv.gb.active'; 
				break;
				case 'CA': 
					$setting_flag = 'usurv.ca.active'; 
				break;
				default: 
					$setting_flag = null; 
			}
			
			if (empty($setting_flag)) {
				continue;
			}
			if (!$project && $settings[$setting_flag] == 'true') {
				$this->create_project($client, $group, $supported_country);
			}
			
			// iterate through the existing project lists and operate on them 
			$projects = $this->Project->find('all', array(
				'conditions' => array(
					'Project.status' => PROJECT_STATUS_OPEN,
					'Project.group_id' => $group['Group']['id'],
					'Project.country' => $supported_country
				),
			));
			if (!$projects) {
				continue;
			}
			foreach ($projects as $project) {
				if (!$project['Project']['active']) {
					$message = 'Closed #'.$project['Project']['id'].' because it was an inactive project (Usurv)';
					$this->close_project($project, $message);
					CakeLog::write('usurv.projects', $message); 
					CakeLog::write('auto.close', $message);
					$this->out($message);
					continue;
				}
			
				if ($project['SurveyVisitCache']['click'] >= 10000) {
					$message = 'Closed #'.$project['Project']['id'].' clicks exceeded 10,000 (Usurv)';
					$this->close_project($project, $message);
					CakeLog::write('usurv.projects', $message); 
					CakeLog::write('auto.close', $message);
					$this->out($message);
					continue;
				}
			}
			
			// sanity check for multiple open projects

			$projects = $this->Project->find('all', array(
				'conditions' => array(
					'Project.status' => PROJECT_STATUS_OPEN,
					'Project.active' => true,
					'Project.group_id' => $group['Group']['id'],
					'Project.country' => $supported_country
				),
				'order' => 'Project.id DESC',
			));
			if (count($projects) > 1) {
				foreach ($projects as $key => $project) {
					
					// Keep the first project open
					if ($key == 0) {
						continue;
					}
					
					// close all the dupes 
					$message = 'Closed #'.$project['Project']['id'].' dupe (Usurv)';
					$this->close_project($project, $message);
					CakeLog::write('usurv.projects', $message); 
					CakeLog::write('auto.close', $message);
					$this->out($message);
				}
			}
		}
		
		// note that the country active flags do NOT automatically close projects; the front-end will stop access to them
		$this->out('Finished.');
	}
	
	private function close_project($project, $message) {
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
			'description' => $message
		)));
		Utils::save_margin($project['Project']['id']);
	}
	
	private function create_project($client, $group, $country) {
		$setting = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => 'hostname.www',
				'Setting.deleted' => false
			)
		));
		if (!isset($setting['hostname.www'])) {
			$this->out('hostname.www setting not found.');
			return false;
		}
		
		$this->Project->validator()->remove('award');
		$projectSource = $this->Project->getDataSource();
		$projectSource->begin();
		
		$this->Project->create();
		$save = $this->Project->save(array('Project' => array(
			'client_id' => $client['Client']['id'],
			'group_id' => $group['Group']['id'],
			'status' => PROJECT_STATUS_OPEN,
			'bid_ir' => 70,
			'est_length' => '3',
			'router' => true,
			'quota' => '10000', 
			'client_rate' => '0',
			'partner_rate' => '0',
			'prj_name' => 'Usurv Router ('.$country.')',
			'user_payout' => '0',
			'award' => '0',
			'mobile' => true,
			'desktop' => true,
			'tablet' => true,
			'started' => date(DB_DATETIME),
			'singleuse' => false,
			'active' => true,
			'dedupe' => false,
			'nq_award' => 0,
			'survey_name' => 'Microsurvey!',
			'country' => $country
		)));
		if ($save) {
			$project_id = $this->Project->getInsertId();
			$projectSource->commit();
				
			// add mintvine as a partner
			$mv_partner = $this->Partner->findByKey('MintVine');
			$this->Project->SurveyPartner->create();
			$this->Project->SurveyPartner->save(array('SurveyPartner' => array(
				'survey_id' => $project_id,
				'partner_id' => $mv_partner['Partner']['id'],
				'rate' => '0', // award
				'complete_url' => $setting['hostname.www'].'/surveys/complete/{{ID}}/',
				'nq_url' => $setting['hostname.www'].'/surveys/nq/{{ID}}/',
				'oq_url' => $setting['hostname.www'].'/surveys/oq/{{ID}}/',
				'pause_url' => $setting['hostname.www'].'/surveys/paused/',
				'fail_url' => $setting['hostname.www'].'/surveys/sec/{{ID}}/',
			)));

			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project_id,
				'type' => 'project.created'
			)));
			
			$message = 'Created #'.$project_id. ' (Usurv)';
			CakeLog::write('usurv.projects', $message); 
			$this->out($message); 
		}
		else {
			$projectSource->commit();
		}
	}
}