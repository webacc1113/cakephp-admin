<?php
App::uses('AppController', 'Controller');

class HistoryRequestsController extends AppController {
	public $helpers = array('Text', 'Html', 'Time');
	public $components = array();
	public $uses = array('HistoryRequest', 'User', 'Project', 'Transaction', 'PanelistHistory', 'Setting', 'Admin', 'SurveyVisit', 'UserIp', 'UserAgent', 'IpProxy', 'SurveyUserVisit', 'SurveyVisitCache');
	
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		$status_filter = SURVEY_REPORT_REQUEST_PENDING;
		$conditions = array();
		if (isset($this->request->query['project_id']) && !empty($this->request->query['project_id'])) {
			$project_id = $this->request->query['project_id'];
			if ($project_id{0} == '#') {
				$project_id = substr($project_id, 1);
			}
			$conditions['HistoryRequest.project_id'] = $project_id;
		}
		if (isset($this->request->query['user_id']) && !empty($this->request->query['user_id'])) {
			$user_id = $this->request->query['user_id'];
			if ($user_id{0} == '#') {
				$user_id = substr($user_id, 1);
			}
			$conditions['HistoryRequest.user_id'] = $user_id;
		}
		if (isset($this->request->query['status'])) {
			$status_filter = $this->request->query['status'];
		}
		if ($status_filter != 'all') {
			$conditions['HistoryRequest.status'] = $status_filter;
		}
		$this->HistoryRequest->bindModel(array('belongsTo' => array(
			'Project' => array(
				'fields' => array('Project.id', 'Project.prj_name', 'Project.bid_ir', 'Project.epc', 'Project.client_rate', 'Project.award')
			),
			'Transaction' => array(
				'fields' => array('Transaction.*')
			),
			'User' => array(
				'fields' => array('User.*')
			),
			'Admin' => array(
				'fields' => array('Admin.admin_user')
			)
		)));
		
		$paginate = array(
			'HistoryRequest' => array(
				'conditions' => $conditions,
				'order' => 'HistoryRequest.id ASC',
				'limit' => '100'
			)
		);
		$this->paginate = $paginate;
		$history_requests = $this->paginate('HistoryRequest');
		$this->set(compact('history_requests', 'status_filter'));
	}
	
	public function info($history_request_id) {

		$this->Project->bindRates();
		$this->HistoryRequest->bindModel(array('belongsTo' => array(
			'Project' => array(
				'fields' => array('Project.id', 'Project.prj_name', 'Project.bid_ir', 'Project.epc', 'Project.est_length', 'Project.client_rate', 'Project.award', 'Project.client_id', 'Project.group_id', 'Project.prescreen')
			),
			'Transaction' => array(
				'fields' => array('Transaction.*')
			),
			'User' => array(
				'fields' => array('User.*')
			),
			'Admin' => array(
				'fields' => array('Admin.admin_user')
			),
			'PanelistHistory'
		)), false);
		$this->PanelistHistory->bindModel(array('belongsTo' => array(
			'Project' => array(
				'fields' => array('Project.id', 'Project.mask', 'Project.prj_name', 'Project.bid_ir', 'Project.epc', 'Project.client_rate', 'Project.award')
			),
			'UserIp' => array(
				'fields' => array('UserIp.id', 'UserIp.user_agent', 'UserIp.user_language', 'UserIp.country', 'UserIp.state', 'UserIp.proxy')
			)
		)));
		
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
		
		$history_request = $this->HistoryRequest->find('first', array(
			'conditions' => array(
				'HistoryRequest.id' => $history_request_id
			),
			'contain' => array(
				'Project' => array(
					'Group' => array('fields' => array('Group.name')),
					'Client' => array('fields' => array('Client.client_name')),
					'SurveyVisitCache',
					'HistoricalRates',
				),
				'Transaction',
				'User',
				'Admin'
			)
		));
		if (!$history_request) {
			$this->Session->setFlash('History request could not be found.', 'flash_error');
			return $this->redirect(array('controller' => 'history_requests', 'action' => 'index'));
		}
		
		$other_requests = $this->HistoryRequest->find('all', array(
			'conditions' => array(
				'HistoryRequest.project_id' => $history_request['HistoryRequest']['project_id'],
				'HistoryRequest.user_id !=' => $history_request['HistoryRequest']['user_id']
			),
			'contain' => array(
				'Project' => array(
					'Group' => array('fields' => array('Group.name')),
					'Client' => array('fields' => array('Client.client_name')),
					'SurveyVisitCache',
					'HistoricalRates',
				),
				'Transaction',
				'User',
				'Admin'
			)
		));
		
		$recent_history_requests = $this->HistoryRequest->find('all', array(
			'conditions' => array(
				'HistoryRequest.id !=' => $history_request_id,
				'HistoryRequest.user_id' => $history_request['HistoryRequest']['user_id'],
			),
			'contain' => array(
				'Project' => array('fields' => array('Project.award')),
				'Transaction' => array('fields' => array('Transaction.*'))
			),
			'order' => 'HistoryRequest.id ASC'
		));
		
		$paid_transaction = array();
		if (!empty($history_request['HistoryRequest']['transaction_id'])) {
			$paid_transaction = $this->Transaction->find('first', array(
				'conditions' => array(
					'Transaction.id' => $history_request['HistoryRequest']['transaction_id'],
					'Transaction.status' => TRANSACTION_APPROVED,
					'Transaction.deleted' => null,
				)
			));
		}
		
		$survey_visits = $this->SurveyVisit->find('all', array(
			'conditions' => array(
				'SurveyVisit.survey_id' => $history_request['HistoryRequest']['project_id'],
				'SurveyVisit.partner_user_id LIKE' => $history_request['HistoryRequest']['project_id'].'-'.$history_request['HistoryRequest']['user_id'].'%'
			),
			'order' => 'SurveyVisit.id ASC'
		));
		
		$user_ip_agents = array();
		foreach ($survey_visits as $key => $survey_visit) {
			$info = Utils::print_r_reverse($survey_visit['SurveyVisit']['info']);
			$survey_visits[$key]['SurveyVisit']['user_agent'] = null;
			if (isset($info) && isset($info['HTTP_USER_AGENT'])) {
				$user_ip_agents[] = $survey_visits[$key]['SurveyVisit']['user_agent'] = $info['HTTP_USER_AGENT'];
			}
		}
		
		$panelist_histories = $this->PanelistHistory->find('all', array(
			'conditions' => array(
				'PanelistHistory.project_id' => $history_request['HistoryRequest']['project_id'],
				'PanelistHistory.user_id' => $history_request['HistoryRequest']['user_id']
			),
			'order' => 'PanelistHistory.id DESC'
		));
		
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
		
		$this->set(compact('survey_visits', 'history_request', 'recent_history_requests', 'other_requests', 'paid_transaction', 'panelist_histories', 'agents', 'user_agents'));
	}
	
	public function download() {
		if ($this->request->is('ajax') && $this->request->is('post')) {
			if (empty($this->data['request_id'])) {
				return new CakeResponse(array('status' => '401'));
			}
			
			$history_request = $this->HistoryRequest->find('first', array(
				'conditions' => array(
					'HistoryRequest.id' => $this->data['request_id']
				),
				'recursive' => -1
			));
			
			if (!$history_request) {
				throw new NotFoundException();
			}
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
			
			$file = $history_request['HistoryRequest']['attachment'];
			
			// we store with first slash; but remove it for S3
			if (substr($file, 0, 1) == '/') {
				$file = substr($file, 1, strlen($file)); 
			}
			
			$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);
			$url = $S3->getAuthenticatedURL($settings['s3.bucket'], $file, 3600, false, false);
			
			return new CakeResponse(array(
				'body' => json_encode(array(
					'screenshot_url' => $url
				)),
				'type' => 'json',
				'status' => '201'
			));
		}
	}
	
	public function ajax_approve($request_id = null) {
		if ($this->request->is('ajax') && $this->request->is('post')) {
			if (empty($this->data['request_id'])) {
				return new CakeResponse(array('status' => '401'));
			}
			$request_id = $this->data['request_id'];
			$submit_to_next = $this->data['submit_to_next'];
			$submit_update_row = $this->data['submit_update_row'];
			$report_type = $this->data['report_type'];
			$amount = (!empty($this->data['amount']) && is_numeric($this->data['amount'])) ? (int)$this->data['amount'] : null;
			
			$status = MintVine::approve_history_request($request_id, $amount, $this->current_user['Admin']['id']);
			if ($status['status'] == false) {
				$error = null;
				if (isset($status['error'])) {
					$error = json_encode(array(
						'error' => $status['error']
					));
				}
				return new CakeResponse(array(
					'body' => $error,
					'type' => 'json',
					'status' => '201'
				));
			}
		
			// write analytics call for Survey Issue Update
			$this->write_analytics($request_id, $amount, 'Approved');
		
			$next_history_url = null;
			if ($submit_to_next) {
				$next_history_url = $this->next_history_request($request_id, $submit_to_next);
			}
			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => '1',
					'next_history_url' => $next_history_url,
					'submit_update_row' => $submit_update_row,
					'report_type' => $report_type
				)), 
				'type' => 'json',
				'status' => '201'
			));
		}
		elseif (empty($request_id)) {
			return new CakeResponse(array('status' => '401'));
		}
		else {
			$history_request = $this->HistoryRequest->find('first', array(
				'conditions' => array(
					'HistoryRequest.id' => $request_id
				),
				'contain' => array('Project' => array('fields' => array('Project.award')))
			));
			if ($history_request) {
				$this->set(array('amount' => $history_request['Project']['award']));
			}
		}
		
		$submit_to_next = isset($this->request->query['submit_to_next']) ? $this->request->query['submit_to_next'] : false;
		$submit_update_row = isset($this->request->query['submit_update_row']) ? $this->request->query['submit_update_row'] : false;
		$report_type = isset($this->request->query['report_type']) ? $this->request->query['report_type'] : null;
		
		$this->set(compact('request_id', 'submit_to_next', 'submit_update_row', 'report_type'));
		$this->layout = '';
	}
	
	public function ajax_reject($request_id = null) {
		if ($this->request->is('ajax') && $this->request->is('post')) {
			if (empty($this->data['request_id'])) {
				return new CakeResponse(array('status' => '401'));
			}
			$request_id = $this->data['request_id'];
			$submit_to_next = $this->data['submit_to_next'];
			$submit_update_row = $this->data['submit_update_row'];
			$report_type = $this->data['report_type'];
			
			$this->HistoryRequest->bindModel(array(
				'belongsTo' => array(
					'Transaction'
				)
			));
			$history_request = $this->HistoryRequest->find('first', array(
				'conditions' => array(
					'HistoryRequest.id' => $request_id
				)
			));
			
			if (!$history_request) {
				return new CakeResponse(array(
					'body' => json_encode(array(
						'status' => '201'
					)), 
					'type' => 'json'
				));
			}
			if ($history_request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_REJECTED) {
				// already rejected
				return new CakeResponse(array(
					'body' => json_encode(array(
						'status' => '201'
					)), 
					'type' => 'json'
				));
			}
			
			// if existing transaction exists; delete it first!
			if ($history_request['Transaction']['id'] > 0) {
				$existing_transaction = array('Transaction' => $history_request['Transaction']); 
				$this->Transaction->soft_delete($existing_transaction);
			}
			
			// create new transaction that will pay out
			$transactionSource = $this->Transaction->getDataSource();
			$transactionSource->begin();
			$this->Transaction->create();
			$this->Transaction->save(array('Transaction' => array(
				'type_id' => TRANSACTION_MISSING_POINTS,
				'linked_to_id' => $history_request['Project']['id'],
				'linked_to_name' => $history_request['Project']['survey_name'],
				'user_id' => $history_request['HistoryRequest']['user_id'],
				'amount' => $history_request['Project']['award'],
				'paid' => false,
				'name' => 'Survey Completion - '.$history_request['Project']['survey_name'],
				'status' => TRANSACTION_PENDING,
				'executed' => date(DB_DATETIME)
			)));
			$transaction_id = $this->Transaction->getInsertId();
			$transaction = $this->Transaction->find('first', array(
				'conditions' => array(
					'Transaction.id' => $transaction_id,
					'Transaction.deleted' => null,
				)
			));
			$transaction_id = $this->Transaction->reject($transaction);
			$transactionSource->commit();
			
			if (!$history_request['Project']['router']) {
				$this->PanelistHistory->create();
				$this->PanelistHistory->save(array('PanelistHistory' => array(
					'id' => $history_request['HistoryRequest']['panelist_history_id'],
					'transaction_id' => $transaction_id,
				)), true, array('transaction_id')); 
			}
			
			// mark the internal survey user click as completed to prevent multiple entries
			$survey_user_visit = $this->SurveyUserVisit->find('first', array(
				'conditions' => array(
					'SurveyUserVisit.user_id' => $history_request['HistoryRequest']['user_id'],
					'SurveyUserVisit.survey_id' => $history_request['HistoryRequest']['project_id']
				)
			));
			if ($survey_user_visit && $survey_user_visit['SurveyUserVisit']['status'] == SURVEY_CLICK) {
				$this->SurveyUserVisit->create();
				$this->SurveyUserVisit->save(array('SurveyUserVisit' => array(
					'id' => $survey_user_visit['SurveyUserVisit']['id'],
					'status' => SURVEY_NQ,
					'redeemed' => true
				)), true, array('status', 'redeemed'));
			}
			
			$this->HistoryRequest->create();
			$this->HistoryRequest->save(array('HistoryRequest' => array(
				'id' => $request_id,
				'transaction_id' => $transaction_id,
				'admin_id' => $this->current_user['Admin']['id'],
				'reason' => $this->data['reason'],
				'status' => SURVEY_REPORT_REQUEST_REJECTED
			)), true, array('transaction_id', 'admin_id', 'reason', 'status'));
			
			// write analytics call for Survey Issue Update
			$this->write_analytics($request_id, 0, 'Rejected');
			
			if ($history_request['HistoryRequest']['send_email']) {
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
				
				// send email notification to user
				$email = new CakeEmail();
				$email->config('mailgun');
				$email->from(array(EMAIL_SENDER => 'MintVine'))
					->replyTo(array(REPLYTO_EMAIL => 'MintVine'))
					->emailFormat('html')
					->template('history_request_rejected')
					->viewVars(array(
						'user_name' => $history_request['User']['username'],
						'user_timezone' => $history_request['User']['timezone'],
						'survey_id' => $history_request['HistoryRequest']['project_id'],
						'reason' => $this->data['reason']
					))
					->to(array($history_request['User']['email']))
					->subject('MintVine Transaction Rejected');
				$email->send();
			}
			
			$next_history_url = null;
			if ($submit_to_next) {
				$next_history_url = $this->next_history_request($request_id, $submit_to_next);
			}
			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => '1',
					'next_history_url' => $next_history_url,
					'submit_update_row' => $submit_update_row,
					'report_type' => $report_type
				)),
				'type' => 'json',
				'status' => '201'
			));
		}
		elseif (empty($request_id)) {
			return new CakeResponse(array('status' => '401'));
		}
		
		$submit_to_next = isset($this->request->query['submit_to_next']) ? $this->request->query['submit_to_next'] : false;
		$submit_update_row = isset($this->request->query['submit_update_row']) ? $this->request->query['submit_update_row'] : false;
		$report_type = isset($this->request->query['report_type']) ? $this->request->query['report_type'] : null;
		
		$this->set(compact('request_id', 'submit_to_next', 'submit_update_row', 'report_type'));
		$this->layout = '';
	}
	
	public function ajax_attachment($request_id = null) {
		$history_request = $this->HistoryRequest->findById($request_id);
		if (empty($history_request)) {
			throw new NotFoundException();
		}
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
		
		$file = $history_request['HistoryRequest']['attachment'];
		
		// we store with first slash; but remove it for S3
		if (substr($file, 0, 1) == '/') {
			$file = substr($file, 1, strlen($file)); 
		}
		
		$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);
		$screenshot_url = $S3->getAuthenticatedURL($settings['s3.bucket'], $file, 3600, false, false);
		
		$this->set(compact('screenshot_url', 'request_id'));
		$this->layout = '';
	}
	
	function ajax_update_request_row($request_id, $report_type = null) {
		$this->Project->bindRates();
		$this->HistoryRequest->bindModel(array('belongsTo' => array(
			'Project' => array(
				'fields' => array('Project.id', 'Project.prj_name', 'Project.bid_ir', 'Project.epc', 'Project.est_length', 'Project.client_rate', 'Project.award', 'Project.client_id', 'Project.group_id', 'Project.prescreen')
			),
			'Transaction' => array(
				'fields' => array('Transaction.*')
			),
			'User' => array(
				'fields' => array('User.*')
			),
			'Admin' => array(
				'fields' => array('Admin.admin_user')
			)
		)));
		$history_request = $this->HistoryRequest->find('first', array(
			'conditions' => array(
				'HistoryRequest.id' => $request_id
			),
			'contain' => array(
				'Project' => array(
					'Group' => array('fields' => array('Group.name')),
					'Client' => array('fields' => array('Client.client_name')),
					'SurveyVisitCache',
					'HistoricalRates',
				),
				'Transaction',
				'User',
				'Admin'
			)
		));
		
		$this->set(compact('history_request', 'report_type'));
		$this->response->statusCode('201');
		$this->layout = '';
	}
	
	public function next_history_request($request_id, $submit_to_next = false) {
		$next_history = $this->HistoryRequest->find('first', array(
			'conditions' => array(
				'HistoryRequest.status' => SURVEY_REPORT_REQUEST_PENDING,
				'HistoryRequest.id >' => $request_id
			),
			'order' => 'HistoryRequest.id ASC',
			'limit' => 1,
			'recursive' => -1
		)); 
		
		if ($next_history) {
			$next_history_url = array('controller' => 'history_requests', 'action' => 'info', $next_history['HistoryRequest']['id']);
		}
		else {
			$next_history_url = array('controller' => 'history_requests', 'action' => 'index', '?' => array('status' => 0));
		}
		
		if ($submit_to_next) {
			$next_history_url = Router::url($next_history_url);
			return $next_history_url;
		}
		else {
			$this->redirect($next_history_url);
		}
	}
	
	public function ajax_change_project($history_request_id = null) {
		if ($this->request->is('ajax') && $this->request->is('post')) {
			$history_request = $this->HistoryRequest->findById($this->request->data['history_request_id']);
			if (!$history_request) {
				return new CakeResponse(array(
					'body' => json_encode(array(
						'message' => 'You are trying to set a project on a invalid history request. Please refresh the page and try again.'
					)), 
					'type' => 'json',
					'status' => '400'
				));	
			}
			
			if (empty($this->request->data['panelist_history_id'])) {
				return new CakeResponse(array(
					'body' => json_encode(array(
						'message' => 'Please select a project.'
					)), 
					'type' => 'json',
					'status' => '400'
				));	
			}
			
			// delete current history_request and transaction with this history_request 
			$this->HistoryRequest->delete($this->request->data['history_request_id']);
			$this->Transaction->delete($history_request['HistoryRequest']['transaction_id']);
			
			// insert copy of this history_request with new panelist_history_id and project_id
			$panelist_history = $this->PanelistHistory->findById($this->request->data['panelist_history_id']);
			$historyRequestSource = $this->HistoryRequest->getDataSource();
			$historyRequestSource->begin();
			$this->HistoryRequest->create();
			$this->HistoryRequest->save(array('HistoryRequest' => array(
					'user_id' => $history_request['HistoryRequest']['user_id'],
					'project_id' => $panelist_history['PanelistHistory']['project_id'],
					'panelist_history_id' => $panelist_history['PanelistHistory']['id'],
					'statement' => $history_request['HistoryRequest']['statement'],
					'link' => $history_request['HistoryRequest']['link'],
					'attachment' => $history_request['HistoryRequest']['attachment'],
					'report' => $history_request['HistoryRequest']['report'],
					'answered' => $history_request['HistoryRequest']['answered'],
					'send_email' => $history_request['HistoryRequest']['send_email']
				)
			));
			$history_request_id = $this->HistoryRequest->getInsertId();
			$historyRequestSource->commit();
			
			return new CakeResponse(array(
				'body' => json_encode(array(
					'message' => 'Project number for this history request has been successfully changed, refreshing search...',
					'history_request_id' => $history_request_id
				)), 
				'type' => 'json',
				'status' => '201'
			));	
		}
		
		$history_request = $this->HistoryRequest->findById($history_request_id);
		$this->PanelistHistory->bindModel(array('belongsTo' => array(
			'Project' => array(
				'fields' => array('Project.id', 'Project.survey_name')
			)
		)));
		$this->PanelistHistory->Project->bindModel(array('hasMany' => array(
			'HistoryRequest'
		)));
		$panelist_histories = $this->PanelistHistory->find('all', array(
			'conditions' => array(
				'PanelistHistory.user_id' => $history_request['User']['id'],
				'PanelistHistory.project_id !=' => $history_request['HistoryRequest']['project_id']
			),
			'contain' => array('Project' => array(
				'HistoryRequest' => array(
					'conditions' => array('HistoryRequest.user_id' => $history_request['User']['id'])
				)
			))
		));
		
		$user_projects = array();
		$valid_reports = array(SURVEY_NQ, SURVEY_OVERQUOTA, SURVEY_INTERNAL_NQ, SURVEY_NQ_FRAUD, SURVEY_NQ_SPEED, SURVEY_NQ_EXCLUDED, SURVEY_OQ_INTERNAL);
		foreach ($panelist_histories as $key => $panelist_history) {
			if ($panelist_history['PanelistHistory']['click_status'] > 0 && empty($panelist_history['PanelistHistory']['term_status']) || in_array($panelist_history['PanelistHistory']['term_status'], $valid_reports)) {
				$transaction_count = $this->Transaction->find('count', array(
					'recursive' => -1,
					'conditions' => array(
						'Transaction.linked_to_id' => $panelist_history['PanelistHistory']['project_id'],
						'Transaction.type_id' => array(TRANSACTION_MISSING_POINTS, TRANSACTION_SURVEY),
						'Transaction.user_id' => $history_request['User']['id'],
						'Transaction.deleted' => null,
					)
				));
				
				if ($transaction_count == 0 && !isset($panelist_history['Project']['HistoryRequest'][0])) {
					$user_projects[$panelist_history['PanelistHistory']['id']] = $panelist_history['PanelistHistory']['project_id'].' - '.$panelist_history['Project']['survey_name'];
				}
			}
		}
		$this->set(compact('user_projects', 'history_request_id'));
		$this->layout = '';
	}
	
	function write_analytics($history_request_id, $amount, $status) {
		if (defined('IS_DEV_INSTANCE') && IS_DEV_INSTANCE === true) {
			return false;
		}
		
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
			return false;
		}
		
		if (!defined('SEGMENT_WRITE_KEY')) {
			define('SEGMENT_WRITE_KEY', $setting['segment.write_key']);
			class_alias('Segment', 'Analytics');
			Analytics::init(SEGMENT_WRITE_KEY);
		}
		
		$history_request = $this->HistoryRequest->find('first', array(
			'conditions' => array(
				'HistoryRequest.id' => $history_request_id
			)
		));
		
		$timestamp = new DateTime(date(DB_DATETIME));
		$timestamp = $timestamp->format(DateTime::ISO8601);
		Analytics::track(array(
			'userId' => $history_request['HistoryRequest']['user_id'],
			'event' => 'Survey Issue Update',
			'timestamp' => strtotime($timestamp),
			'properties' => array(
				'category' => 'User Preferences',
				'label' => 'Survey Issue Update',
				'user_id' => (int) $history_request['HistoryRequest']['user_id'],
				'points' => (int) $amount,
				'status' => $status,
				'survey_id' => (int) $history_request['HistoryRequest']['project_id'],
				'survey_link' => $history_request['HistoryRequest']['link']
			)
		));	
	}
}