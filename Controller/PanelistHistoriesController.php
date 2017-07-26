<?php
App::uses('AppController', 'Controller');
App::import('Lib', 'MintVineUser');

class PanelistHistoriesController extends AppController {
	public $uses = array('PanelistHistory', 'IpProxy', 'TwilioNumber', 'SurveyVisitCache', 'QueryProfile', 'UserOption');
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array();
	
	public function index() {
		
	}
	public function user($export = null) {
		if (empty($this->request->query['user_id'])) {
			$this->Session->setFlash('No panelist ID specified', 'flash_error');
			return $this->redirect(array('action' => 'index')); 
		}
		
		
		// legend of lookups
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
		$this->set(compact('click_failures', 'term_failures')); 
		
		// top user box
		$this->User->bindModel(array('hasMany' => array('HellbanLog' => array(
			'fields' => array('HellbanLog.automated'),
			'conditions' => array(
				'HellbanLog.type' => 'unhellban'
			),
			'order' => 'HellbanLog.id DESC'
		))));
		$user = $this->User->findById($this->request->query['user_id']);
		if ($user['User']['twilio_number_id'] > 0) {
			$twilio_number = $this->TwilioNumber->find('first', array(
				'conditions' => array(
					'TwilioNumber.id' => $user['User']['twilio_number_id']
				),
				'recursive' => -1
			));
			$this->set(compact('twilio_number'));
		}
		$this->loadModel('UserAnalysis');
		$user_analysis = $this->UserAnalysis->find('first', array(
			'conditions' => array(
				'UserAnalysis.user_id' => $this->request->query['user_id']
			),
			'order' => 'UserAnalysis.id DESC'
		));
		$this->set(compact('user', 'user_analysis')); 
		
		// rest of the code
		
		$this->PanelistHistory->bindModel(array('belongsTo' => array(
			'Group' => array(
				'fields' => array('Group.name')
			),
			'Project' => array(
				'fields' => array('Project.id', 'Project.mask', 'Project.prj_name', 'Project.bid_ir', 'Project.epc', 'Project.client_rate', 'Project.award')
			),
			'Client' => array(
				'fields' => array('Client.client_name')
			),
			'Transaction' => array(
				'fields' => array('Transaction.name', 'Transaction.amount', 'Transaction.status')
			),
			'UserIp' => array(
				'fields' => array('UserIp.id', 'UserIp.user_agent', 'UserIp.user_language', 'UserIp.country', 'UserIp.state', 'UserIp.proxy')
			)
		)));
		if ($export) {
			$STATUSES = unserialize(SURVEY_STATUSES);
			$panelist_histories = $this->PanelistHistory->find('all', array(
				'conditions' => array(
					'PanelistHistory.user_id' => $this->request->query['user_id']
				),
				'order' => 'PanelistHistory.id DESC',
			));

			$csvs = array(
				array('Date', 'IP Address', 'Project', 'Mask', 'Points', 'Group', 'Client', 'Started', 'Click Failure', 'Termed', 'Term Failure', 'LOI (User)', 'LOI (Project)', 'User Agent', 'Language', 'Country', 'State', 'Proxy?')
			);
		}
		else {
			$paginate = array(
				'PanelistHistory' => array(
					'conditions' => array(
						'PanelistHistory.user_id' => $this->request->query['user_id']
					),
					'order' => 'PanelistHistory.id DESC',
					'limit' => '100'
				)
			);
			$this->paginate = $paginate;
			$panelist_histories = $this->paginate('PanelistHistory'); 
		}
		
		$query_profile = $this->QueryProfile->find('first', array(
			'conditions' => array(
				'QueryProfile.user_id' => $this->request->query['user_id']
			),
			'recursive' => -1,
			'fields' => array('id', 'state', 'country')
		));
		
		$panelist_ip_address_entries = $this->PanelistHistory->find('all', array(
			'conditions' => array(
				'PanelistHistory.user_id' => $this->request->query['user_id'],
			),
			'fields' => array('PanelistHistory.ip_address'),
			'group' => 'PanelistHistory.ip_address',
			'order' => 'PanelistHistory.created ASC',
		));
		if ($panelist_ip_address_entries) {
			foreach ($panelist_ip_address_entries as $key => $panelist_ip_address_entry) {
				$panelist_ip_address_group[$panelist_ip_address_entry['PanelistHistory']['ip_address']] = $key + 1;
			}
		}
		$this->set(compact('query_profile', 'panelist_ip_address_group')); 
		
		$last_active_session = '';
		$toggle_session = false;
		// manually join the ipproxy data
		foreach ($panelist_histories as $key => $panelist_history) {	
			if ($panelist_history['UserIp']['proxy']) {
				$ip_proxy = $this->IpProxy->find('first', array(
					'conditions' => array(
						'IpProxy.ip_address' => $panelist_history['PanelistHistory']['ip_address']
					),
					'recursive' => -1
				));
				if ($ip_proxy) {
					$panelist_histories[$key]['IpProxy'] = $ip_proxy['IpProxy']; 
				}
			}
			$survey_visit_cache = $this->SurveyVisitCache->find('first', array(
				'conditions' => array(
					'SurveyVisitCache.survey_id' => $panelist_history['Project']['id']
				)
			));
			if ($survey_visit_cache) {
				$panelist_histories[$key]['SurveyVisitCache'] = $survey_visit_cache['SurveyVisitCache']; 
			}
		}
		// gotta prettify the user agents
		App::import('Model', 'UserAgent');
		$this->UserAgent = new UserAgent;
		
		$user_ip_agents = array();
		if ($panelist_histories) {
			foreach ($panelist_histories as $panelist_history) {
				$user_ip_agents[] = $panelist_history['UserIp']['user_agent'];
			}
		}
		$user_ip_agents = array_unique($user_ip_agents); 
		
		// write missing user agents to DB
		if (!empty($user_ip_agents)) {
			$settings = $this->Setting->find('list', array(
				'conditions' => array(
					'Setting.name' => 'useragent.key',
					'Setting.deleted' => false
				),
				'fields' => array('Setting.name', 'Setting.value')
			));
			if (count($settings) == 1) {
				$user_agents = $this->UserAgent->find('list', array(
					'conditions' => array(
						'UserAgent.user_agent' => $user_ip_agents
					),
					'fields' => array('UserAgent.user_agent', 'UserAgent.id'),
					'recursive' => -1
				));
				
				foreach ($user_ip_agents as $user_ip_agent) {
					// grab and populate data if it exists
					if (!isset($user_agents[$user_ip_agent])) {
						$http = new HttpSocket(array(
							'timeout' => 15,
							'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
						)); 
						$results = $http->get('http://useragentapi.com/api/v2/json/'.$settings['useragent.key'].'/'.urlencode($user_ip_agent));
						
						if ($results->code == 200) {
							$data = json_decode($results->body, true);
							if (isset($data) && !empty($data) && is_array($data)) {
								$userAgentSource = $this->UserAgent->getDataSource();
								$userAgentSource->begin();
								$this->UserAgent->create();
								$this->UserAgent->save(array('UserAgent' => array(
									'user_agent' => $user_ip_agent
								)));
								$user_agent_id = $this->UserAgent->getInsertId();
								$userAgentSource->commit();
								foreach ($data as $key => $val) {
									$this->UserAgent->UserAgentValue->create();
									$this->UserAgent->UserAgentValue->save(array(
										'user_agent_id' => $user_agent_id,
										'name' => $key,
										'value' => $val
									));
								}
								$user_agents[$user_ip_agent] = $user_agent_id;
							}
						}
					}
				}
			}
		}
		
		$agents = array();
		if (isset($user_agents) && $user_agents) {
			$agents_untransformed = $this->UserAgent->find('all', array(
				'conditions' => array(
					'UserAgent.id' => $user_agents
				)
			));
			foreach ($agents_untransformed as $agent) {
				if (isset($agent['UserAgentValue'])) {
					$list = array();
					foreach ($agent['UserAgentValue'] as $agent_value) {
						$list[$agent_value['name']] = $agent_value['value'];
					}
					$agent['UserAgentValue'] = $list;
				}
				$agents[$agent['UserAgent']['id']] = $agent;
			}
		}
		
		if ($export) {
			$tz = new DateTimeZone('America/Los_Angeles');
			foreach ($panelist_histories as $key => $panelist_history) {
				$survey_visit_cache = $this->SurveyVisitCache->find('first', array(
					'conditions' => array(
						'SurveyVisitCache.survey_id' => $panelist_history['Project']['id']
					)
				));
				$date = new DateTime($panelist_history['PanelistHistory']['created']);
				$date->setTimezone($tz);
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
				if (!is_null($panelist_history['PanelistHistory']['panelist_loi'])) {
					$panelist_loi = round($panelist_history['PanelistHistory']['panelist_loi'] / 60);
					if ($panelist_history['PanelistHistory']['term_status'] == SURVEY_COMPLETED) {
						if (isset($survey_visit_cache['SurveyVisitCache']['loi_seconds']) && !empty($survey_visit_cache['SurveyVisitCache']['loi_seconds'])) {
							$survey_loi = round($survey_visit_cache['SurveyVisitCache']['loi_seconds'] / 60);
						}
					}
				}
				if (isset($user_agents[$panelist_history['UserIp']['user_agent']])) {
					$user_agent = $agents[$user_agents[$panelist_history['UserIp']['user_agent']]];
					$agent = $user_agent['UserAgentValue']['platform_type'] . ' . ' . $user_agent['UserAgentValue']['platform_name'] . ' . ' . $user_agent['UserAgentValue']['browser_name'];
				}
				else {
					$agent = $panelist_history['UserIp']['user_agent'];
				}
				if (!is_null($panelist_history['UserIp']['proxy'])) {
					if ($panelist_history['UserIp']['proxy'] == 1) {
						$proxy = $panelist_history['IpProxy']['proxy_score'];
					}
				}
				else {
					$proxy = 'Unchecked';
				}
				$csvs[] = array(
					$date->format('M-d-Y h:i:s'),
					$panelist_history['PanelistHistory']['ip_address'],
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
					$agent,
					$panelist_history['UserIp']['user_language'],
					$panelist_history['UserIp']['country'],
					$panelist_history['UserIp']['state'],
					$proxy,
				);
			}
			
			$filename = 'panelist_history_' . $this->request->query['user_id']. '_' . gmdate(DB_DATE, time()) . '.csv';
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
			$this->set(compact('panelist_histories', 'agents', 'user_agents'));
		}
	}
}