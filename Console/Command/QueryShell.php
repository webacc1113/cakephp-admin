<?php

App::import('Lib', 'Utilities');
App::import('Lib', 'MintVine');
App::uses('ComponentCollection', 'Controller');
App::uses('Controller', 'Controller');
App::uses('QueryEngineComponent', 'Controller/Component');
App::uses('View', 'View');

class QueryShell extends AppShell {
	var $uses = array('Query', 'Project', 'SurveyUser', 'Group', 'Nonce', 'MailQueue', 'SurveyUserVisit', 'SurveyUserQuery', 'Setting', 'User', 'ProjectLog');
	public $tasks = array('Maintenance');

	public function mass_send($survey_id = null) {
		ini_set('memory_limit', '2048M');
		if (empty($survey_id)) {
			$survey_id = isset($this->args[0]) ? $this->args[0]: null;
		}
		
		$project = $this->Project->find('first', array(
			'fields' => array('Project.id', 'Project.bid_ir', 'Project.quota', 'SurveyVisitCache.ir', 'SurveyVisitCache.click', 'SurveyVisitCache.complete'),
			'conditions' => array(
				'Project.id' => $survey_id
			)
		));
		$this->Query->bindModel(array('hasOne' => array('QueryStatistic')));
		$this->Query->unbindModel(array('hasMany' => array('QueryHistory')));
		$queries = $this->Query->find('all', array(
			'conditions' => array(
				'Query.survey_id' => $survey_id,
				'Query.parent_id' => '0'
			),
			'order' => 'query_name asc'
		));
		if (empty($queries)) {
			return;
		}
		foreach ($queries as $query) {
			$survey_reach = MintVine::estimate_query_send($query, $project);
			echo $query['Query']['id'].' '.$survey_reach."\n";
			
			$queryHistorySource = $this->QueryHistory->getDataSource();
			$queryHistorySource->begin();
			$this->Query->QueryHistory->create();
			$this->Query->QueryHistory->save(array('QueryHistory' => array(
				'query_id' => $query['Query']['id'],
				'item_id' => $survey_id,
				'item_type' => TYPE_SURVEY,
				'count' => null,
				'total' => null,
				'type' => 'sending'
			)));
			$query_history_id = $this->Query->QueryHistory->getInsertId();
			$queryHistorySource->commit();
			
			$query = ROOT.'/app/Console/cake query create_queries '.$survey_id.' '.$query['Query']['id'].' '.$query_history_id.' '.$survey_reach;
			CakeLog::write('query_commands', $query); 
			exec($query); 
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $project['Project']['id'],
				'type' => 'query.executed',
				'query_id' => $query['Query']['id'],
				'description' => 'Query executed from mass send, total sent : ' . $survey_reach
			)));
		}
		// loop through and set up surveys
	}
	
	// params: $survey_id, $query_id, $query_history_id, $survey_reach, $sample (optional - default null)
	public function create_queries($survey_id = null, $query_id = null, $query_history_id = null, $survey_reach = null, $sample = null) {
		ini_set('memory_limit', '1024M');
		if (empty($survey_id)) {
			$survey_id = isset($this->args[0]) ? $this->args[0]: null;
		}
		if (empty($query_id)) {
			$query_id = isset($this->args[1]) ? $this->args[1]: null;
		}
		if (empty($query_history_id)) {
			$query_history_id = isset($this->args[2]) ? $this->args[2]: null;
		}
		if (empty($survey_reach)) {
			$survey_reach = isset($this->args[3]) ? $this->args[3]: null;
		}
		if (empty($sample)) {
			$sample = isset($this->args[4]) && $this->args[4] == 1;
		}
		if (empty($survey_id) || empty($query_id) || empty($query_history_id) || empty($survey_reach)) {
			return false;
		}
		
		echo 'START: '.date(DB_DATETIME); 
		$log = false;
		
		if ($sample) {
			$setting = $this->Setting->find('first', array(
				'conditions' => array(
					'Setting.name' => 'fulcrum.email_threshold',
					'Setting.deleted' => false
				)
			));
			if (!$setting) { // set the default if not found.
				$setting = array('Setting' => array('value' => 60));
			}
		}
		
		// initialize components		
        $collection = new ComponentCollection();
        $this->QueryEngine = new QueryEngineComponent($collection);
        $controller = new Controller();
        $this->QueryEngine->initialize($controller);
	
		$subquery_list = array();
		$query = $this->Query->findById($query_id);
		if ($query) {
			$qs = (array) json_decode($query['Query']['query_string']);
			
			// this is a parent query, so we gotta check for sub-queries; it triggers different targeting behavior if so
			if (empty($query['Query']['parent_id'])) {
				// todo long-term: we should combine all the subqueries into one master query
				
				// get the subqueries here				
				$this->Query->bindModel(array('hasOne' => array('QueryStatistic')));
				$this->Query->unbindModel(array('hasMany' => array('QueryHistory')));
				$sub_queries = $this->Query->find('all', array(
					'conditions' => array(
						'Query.parent_id' => $query['Query']['id'],
					)
				));
				
				if ($sub_queries) {
					foreach ($sub_queries as $sub_query) {
						$subquery_list[$sub_query['Query']['id']] = (array) json_decode($sub_query['Query']['query_string']);
					}
				}
			}
		
			$results = $this->QueryEngine->execute($qs, $query['Query']['survey_id']);
			echo 'Executed Query'."\n";
			$users = $this->QueryEngine->prioritize_users($results);
			echo 'Prioritized Users ('.count($users).')'."\n";
			$i = 0;
			
			if (!empty($users)) {
				// get survey info 
				$survey = $this->Project->findById($survey_id);
				$survey_users = $this->SurveyUser->find('list', array(
					'fields' => array('user_id', 'id'),
					'recursive' => -1,
					'conditions' => array(
						'SurveyUser.survey_id' => $survey['Project']['id']
					)
				));
				$existing_count = count($survey_users);
			
				// for capturing the output of a view into a string
				$this->autoRender = false;
			
				$survey_subject = empty($survey['Project']['description']) ? 'Exciting Survey Opportunity': $survey['Project']['description'];
				$survey_award = $survey['Project']['award'];
				$survey_length = $survey['Project']['est_length'];
			
				$is_desktop = $survey['Project']['desktop'];
				$is_mobile = $survey['Project']['mobile'];
				$is_tablet = $survey['Project']['tablet'];
				$survey_id = $survey['Project']['id'];

				if ($survey['Project']['router']) {
					$template = 'survey-funnel';
				}
				else {
					$template = 'survey';
				}
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
				$time_start = microtime(true);
				// grab the email template
				$view = new View($controller, false);
				$view->layout = 'Emails/html/default';
				$nonce = '{{nonce}}';
				$survey_url = '{{survey_url}}';
				$unsubscribe_link = '{{unsubscribe_link}}';
				$view->set(compact('nonce', 'survey_url', 'unsubscribe_link', 'survey_award', 'survey_length', 'is_desktop', 'is_mobile', 'is_tablet', 'survey_id'));
				$view->viewPath = 'Emails/html';
				$email_body = $view->render($template);
				$this->autoRender = true;
			
				if ($log) {
					CakeLog::write('send', 'got email '.(microtime(true) - $time_start));
					CakeLog::write('send', 'starting send');
				}
				
				$added_user_ids = array();
				echo 'Starting '.count($users).' users.'."\n";
				$subqueries = array();
				
				foreach ($users as $key => $user) {
					$total_time_start = microtime(true);
					$user_id = $user['User']['id'];
					$in_subqueries = array();
					
					// subquery checks
					if (!empty($subquery_list)) {
						$in_subquery = false;
						foreach ($subquery_list as $subquery_id => $subquery) {
							$subquery['user_id'] = $user_id;
							$results = $this->QueryEngine->execute($subquery, $query['Query']['survey_id']);
							if ($results['count']['total'] > 0) {
								$subqueries[$subquery_id][$user_id] = $user_id; 
								$in_subquery = true;
							}
						}
						
						// if user is being matched via parent query, and subqueries exist, panelist must match against a subquery since parent queries aren't real queries
						// note: actual writes occur later on
						if (!$in_subquery) {
							unset($users[$key]); 
							continue;
						}						
					}
				
					$time_start = microtime(true);
					// create the survey user record
					if ($existing_count > 0 && isset($survey_users[$user_id])) {
						unset($users[$key]); // remove from invite list
						
						// add the survey query history for the added user
						if (!$survey['Project']['router']) {
							$this->SurveyUserQuery->create();
							$this->SurveyUserQuery->save(array('SurveyUserQuery' => array(
								'survey_user_id' => $survey_users[$user_id],
								'query_history_id' => $query_history_id
							)));
						}
						continue;
					}
					
					$survey_user = array('SurveyUser' => array(
						'survey_id' => $survey_id,
						'user_id' => $user_id,
						'query_history_id' => $query_history_id,
						'created' => date(DB_DATETIME, time())
					));
					if ($sample) {
						$survey_user['SurveyUser']['hidden'] = SURVEY_HIDDEN_SAMPLING;
						$email_threshold_setting = $this->Setting->find('first', array(
							'conditions' => array(
								'Setting.name' => 'fulcrum.email_threshold',
								'Setting.deleted' => false
							)
						 ));	
					}
					
					$surveyUserSource = $this->SurveyUser->getDataSource();
					$surveyUserSource->begin();
					$this->SurveyUser->create();
					$this->SurveyUser->save($survey_user, array(
						'callbacks' => $existing_count > 0,
						'validate' => false
					));
					$survey_user_id = $this->SurveyUser->getLastInsertID();
					$surveyUserSource->commit();
					echo 'Invitation created '.$survey_user_id."\n";
					
					$i++; // this keeps count of total users who have been invited; this belongs before email sends
					
					// break execution if we've reached the limit
					if ($i > $survey_reach) {
						echo 'Ended Survey Push'."\n";
						break;
					}

					// create survey user query history for this user
					if (!$survey['Project']['router']) {
						$this->SurveyUserQuery->create();
						$this->SurveyUserQuery->save(array('SurveyUserQuery' => array(
							'survey_user_id' => $survey_user_id,
							'query_history_id' => $query_history_id
						)));
					}
					
					if ($log) {
						CakeLog::write('send', 'wrote survey user '.(microtime(true) - $time_start));
					}
					
					// bypass sending email if no known bid_ir & the last email invite is sent recently.
					if ($sample && !empty($user['User']['fulcrum']) && strtotime($user['User']['fulcrum']) > strtotime('-' . $email_threshold_setting['Setting']['value'] . ' minutes')) {
						echo 'Email send skipped for user id: ' . $user['User']['id'] . ' because last sample invitation was sent at ' . $user['User']['fulcrum'] . "\n";
						continue;
					}
					
					// bypass the sending of email if user has opted out
					if (!$user['User']['send_survey_email'] || !$user['User']['send_email']) {
						echo 'Email not sent to user because opted out'."\n";
						unset($users[$key]); // remove from invite list
						continue;
					}
					
					// we no longer send emails and rely on the router to deliver projects < 25 points
					if ($survey_award < 25) {
						echo 'Email not delivered for projects under 25 points'."\n";
						continue;
					}
					
					// remesh do not get emails sent here
					if ($survey['Group']['key'] == 'remesh') {
						continue;
					}
					
					// break execution if we've reached the limit
					if ($i > $survey_reach) {
						echo 'Ended Survey Push'."\n";
						break;
					}
					// set the fulcrum timestamp if this is a sampling run
					if ($sample) {
						$this->User->create();
						$this->User->save(array('User' => array(
							'id' => $user['User']['id'],
							'fulcrum' => date(DB_DATETIME, time())
						)), array(
							'fieldList' => array('fulcrum'),
							'callbacks' => false,
							'validate' => false
						));
					}
					
					$added_user_ids[] = $user['User']['id'];
				
					$time_start = microtime(true);
					// generate the email
					$nonce = substr($user['User']['ref_id'], 0, 21).'-'.substr(Utils::rand(10), 0, 10);
					$survey_url = HOSTNAME_WWW.'/surveys/pre/'.$survey['Project']['id'].'/?nonce='.$nonce . '&from=email'.(!empty($survey['Project']['code']) ? '&key='.$survey['Project']['code'] : '');
					$unsubscribe_link = HOSTNAME_WWW.'/users/emails/'.$user['User']['ref_id'];
					
					$customized_email_body = str_replace(array(
						'{{nonce}}',
						'{{unsubscribe_link}}', 
						'{{survey_url}}',
						'{{user_id}}'
					), array(
						$nonce,
						$unsubscribe_link, 
						$survey_url,
						$user_id
					), $email_body);
					if ($log) {
						CakeLog::write('send', 'email body '.(microtime(true) - $time_start));
					}
								
					$time_start = microtime(true);
					// create the one-time nonce
					$this->Nonce->create();
					$this->Nonce->save(array('Nonce' => array(
						'item_id' => $survey['Project']['id'],
						'item_type' => 'survey',
						'user_id' => $user_id,
						'nonce' => $nonce
					)), false);
					if ($log) {
						CakeLog::write('send', 'mint_nonce '.(microtime(true) - $time_start));
					}
					
					
					// queue into mail queue if user has opted into emails
					$time_start = microtime(true);
					$this->MailQueue->create();
					$this->MailQueue->save(array('MailQueue' => array(
						'user_id' => $user_id,
						'email' => $user['User']['email'],
						'subject' => $survey_subject,
						'project_id' => $survey['Project']['id'],
						'body' => $customized_email_body,
						'status' => 'Queued'
					)));
					CakeLog::write('send', 'mail_queue '.(microtime(true) - $time_start));
					echo 'Mail to be sent to '.$user_id."\n";
					
					if ($log) {
						CakeLog::write('send', 'total send time '.(microtime(true) - $total_time_start));
						CakeLog::write('send', '...');
					}
					
					unset($users[$key]); // remove from invite list
				}
				
				// create query history for this user
				$this->Query->QueryHistory->create();
				$this->Query->QueryHistory->save(array('QueryHistory' => array(
					'id' => $query_history_id,
					'count' => $i,
					'total' => count($results['users']),
					'type' => 'sent'
				)), true, array('count', 'total', 'type'));
				
				// if we had other users still available 
				if ($existing_count > 0 && !empty($users) && !$survey['Project']['router']) {
					foreach ($users as $user) {	
						if (isset($survey_users[$user['User']['id']])) {
							// create survey user query history for this user
							$this->SurveyUserQuery->create();
							$this->SurveyUserQuery->save(array('SurveyUserQuery' => array(
								'survey_user_id' => $survey_users[$user['User']['id']],
								'query_history_id' => $query_history_id
							)));
						}
					}
				}
				
				// auto-launch subquery and write the records
				if (!empty($subqueries)) {
					echo 'Found sub-queries'."\n";
					foreach ($subqueries as $query_id => $subquery_user_list) {
						$query_histories = $this->Query->QueryHistory->find('list', array(
							'conditions' => array(
								'QueryHistory.query_id' => $query_id,
								'QueryHistory.type' => 'sent'
							)
						));
						// these subqueries have not been sent out yet - easy case: just create and send
						if (empty($query_histories)) {
							$queryHistorySource = $this->Query->QueryHistory->getDataSource();
							$queryHistorySource->begin();
							$this->Query->QueryHistory->create();
							$this->Query->QueryHistory->save(array('QueryHistory' => array(
								'query_id' => $query_id,
								'item_id' => $survey['Project']['id'],
								'type' => 'sent',
								'item_type' => TYPE_SURVEY,
								'count' => count($subquery_user_list),
								'total' => count($subquery_user_list)
							)));
							$query_history_id = $this->Query->QueryHistory->getInsertId();
							$queryHistorySource->commit();
							foreach ($subquery_user_list as $user_id) {
								$survey_user = $this->SurveyUser->find('first', array(
									'conditions' => array(
										'SurveyUser.survey_id' => $survey['Project']['id'],
										'SurveyUser.user_id' => $user_id,
									),
									'recursive' => -1,
									'fields' => array('id')
								));
								$this->SurveyUserQuery->create();
								$this->SurveyUserQuery->save(array('SurveyUserQuery' => array(
									'survey_user_id' => $survey_user['SurveyUser']['id'],
									'query_history_id' => $query_history_id
								)));
							}
						}
						else {
							$first = null;
							foreach ($subquery_user_list as $user_id) {
								$survey_user = $this->SurveyUser->find('first', array(
									'conditions' => array(
										'SurveyUser.survey_id' => $survey['Project']['id'],
										'SurveyUser.user_id' => $user_id,
									),
									'recursive' => -1,
									'fields' => array('id')
								));
								if ($survey_user) {
									$survey_user_query = $this->SurveyUserQuery->find('first', array(
										'conditions' => array(
											'SurveyUserQuery.survey_user_id' => $survey_user['SurveyUser']['id'],
											'SurveyUserQuery.query_history_id' => $query_histories
										)
									));
									if ($survey_user_query) {
										continue;
									}
								}
								if (!is_null($first)) {
									$queryHistorySource = $this->Query->QueryHistory->getDataSource();
									$queryHistorySource->begin();
									$this->Query->QueryHistory->create();
									$this->Query->QueryHistory->save(array('QueryHistory' => array(
										'query_id' => $query_id,
										'item_id' => $survey['Project']['id'],
										'item_type' => TYPE_SURVEY,
										'type' => 'sent',
										'count' => count($subquery_user_list),
										'total' => count($subquery_user_list)
									)));
									$query_history_id = $this->Query->QueryHistory->getInsertId();
									$queryHistorySource->commit();
									$first = false;
								}
								$this->SurveyUserQuery->create();
								$this->SurveyUserQuery->save(array('SurveyUserQuery' => array(
									'survey_user_id' => $survey_user['SurveyUser']['id'],
									'query_history_id' => $query_history_id
								)));
							}
						}
					}
				}
			}
		}
		echo 'END: '.date(DB_DATETIME); 
	}
	
	public function resend() {
		if (!isset($this->args[0])) {
			return false;
		}
		$query_id = $this->args[0];
		$query_histories = $this->Query->QueryHistory->findAllByQueryId($query_id);
		if (!$query_histories) {
			return false;
		}
		foreach ($query_histories as $query_history) {
			if ($query_history['QueryHistory']['item_type'] != TYPE_SURVEY) {
				continue;
			}
			if (!is_null($query_history['QueryHistory']['resent_id'])) {
				
			}
			$survey_users = $this->SurveyUser->find('all', array(
				'conditions' => array(
					'SurveyUser.survey_id' => $query_history['QueryHistory']['item_id'],
					'SurveyUser.query_history_id' => $query_history['QueryHistory']['id']
				)
			));
			$i = 0;
			foreach ($survey_users as $user) {
				$survey_user_visit = $this->SurveyUserVisit->find('first', array(
					'conditions' => array(
						'SurveyUserVisit.user_id' => $user['SurveyUser']['user_id'],
						'SurveyUserVisit.survey_id' => $user['SurveyUser']['survey_id'],
					)
				));
				if ($survey_user_visit && !in_array($survey_user_visit['SurveyUserVisit']['status'], array(SURVEY_CLICK, SURVEY_OVERQUOTA))) {
					continue;
				}
				// clean the user data for this user so they can be invited again
				if ($survey_user_visit) {
					$this->SurveyUserVisit->delete($survey_user_visit['SurveyUserVisit']['id']);
				}
				$this->SurveyUser->delete($user['SurveyUser']['id']);
				$i++;
			}
		
			if ($i > 0) {
				// create new query history	
				$queryHistorySource = $this->Query->QueryHistory->getDataSource();
				$queryHistorySource->begin();
				$this->Query->QueryHistory->create();
				$this->Query->QueryHistory->save(array('QueryHistory' => array(
					'query_id' => $query_history['QueryHistory']['query_id'],
					'item_id' => $query_history['QueryHistory']['item_id'],
					'item_type' => TYPE_SURVEY,
					'count' => null,	
					'total' => null,
					'type' => 'sending'
				)));
				$query_history_id = $this->Query->QueryHistory->getInsertId();
				$queryHistorySource->commit();
			}
			else {
				$query_history_id = 0;
			}
		
		
			// link query
			$this->Query->QueryHistory->create();
			$this->Query->QueryHistory->save(array('QueryHistory' => array(
				'id' => $query_history['QueryHistory']['id'],
				'count' => $query_history['QueryHistory']['count'] - $i,
				'resent_id' => $query_history_id
			)), true, array('resent_id', 'count'));
		
			$this->create_queries($query_history['QueryHistory']['item_id'], $query_history['QueryHistory']['query_id'], $query_history_id, $i);
			
			$this->ProjectLog->create();
			$this->ProjectLog->save(array('ProjectLog' => array(
				'project_id' => $query_history['QueryHistory']['item_id'],
				'type' => 'query.executed',
				'query_id' => $query_history['QueryHistory']['query_id'],
				'description' => 'Resend query executed, total sent : ' . $i
			)));
		}
	}
	
	// args: partner, project_id (optional), query_id (optional)
	public function test_v1_vs_v2() {
		if (!isset($this->args[0])) {
			$this->out('Please input a partner');
			return false;
		}
		
		App::import('Lib', 'QueryEngine');
		App::uses('HttpSocket', 'Network/Http');
		
		$models_to_import = array('Project', 'Query', 'QueryProfile', 'Setting');
		foreach ($models_to_import as $model_to_import) {
			App::import('Model', $model_to_import);
			$this->$model_to_import = new $model_to_import;
		}
		
		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => array(
					'qe.mintvine.username', 
					'qe.mintvine.password', 
					'hostname.qe',
				),
				'Setting.deleted' => false
			)
		));
		
		if (isset($this->args[1])) {
			if (isset($this->args[2])) {
				$queries = $this->Query->find('all', array(
					'conditions' => array(
						'Query.id' => $this->args[2],
						'Query.survey_id' => $this->args[1]
					),
					'recursive' => -1
				));
			}
			else {
				$queries = $this->Query->find('all', array(
					'conditions' => array(
						'Query.survey_id' => $this->args[1]
					),
					'recursive' => -1
				));
			}
		}
		else {
			if ($this->args[0] == 'mintvine') {
				$group = $this->Group->find('first', array(
					'fields' => array('Group.id'),
					'conditions' => array(
						'Group.key' => 'mintvine'
					)
				));
			}
			elseif ($this->args[0] == 'lucid') {
				$group = $this->Group->find('first', array(
					'fields' => array('Group.id'),
					'conditions' => array(
						'Group.key' => 'fulcrum'
					)
				));
			}
			$this->Query->bindModel(array('belongsTo' => array(
				'Project' => array(
					'foreignKey' => 'survey_id',
					'fields' => array('Project.id', 'Project.group_id')
				)
			)));
			$queries = $this->Query->find('all', array(
				'conditions' => array(
					'Project.group_id' => $group['Group']['id']
				),
				'limit' => '100',
				'order' => 'Query.id DESC'
			));	
		}
		
		if (!$queries) {
			$this->out('This project has no queries');
			return false;
		}
		
		foreach ($queries as $query) {
			$query_string = json_decode($query['Query']['query_string'], true);
			if (isset($query_string['regionGB']) || isset($query_string['regionCA']) || isset($query_string['existing_project_id']) || isset($query_string['exclude_user_id'])) {
				continue; // skip these projects for now
			}
			$v2_query_string = QueryEngine::convert_query_to_v2_format($query_string);
						
			if (!empty($query_string['age_from']) || !empty($query_string['age_to'])) {
				if (isset($query_string['age_from']) && !empty($query_string['age_from']) && empty($query_string['age_to'])) {
					$query_string['age_to'] = $query_string['age_from'];
				}
				if (isset($query_string['age_to']) && !empty($query_string['age_to']) && empty($query_string['age_from'])) {
					$query_string['age_from'] = $query_string['age_to'];
				}
		
				$seconds_in_year = 31556940;
				if (!empty($query_string['age_from'])) {
					$query_string['birthdate <='] = date(DB_DATE, time() - $query_string['age_from'] * $seconds_in_year);
					unset($query_string['age_from']);
				}
				if (!empty($query_string['age_to'])) {
					// add a day to make sure you capture the days correctly
					$query_string['birthdate >'] = date(DB_DATE, time() - $query_string['age_to'] * $seconds_in_year - $seconds_in_year + 86400);
					unset($query_string['age_to']);
				}
			}
			
			$this->out('Running query '.$query['Query']['id'].' from '.$query['Query']['survey_id']); 
			if (isset($this->args[1])) { 
				$this->out('v1 Query: '.print_r($query_string, true));
			}
			
			$v1_results = $this->QueryProfile->find('list', array(
				'fields' => array('QueryProfile.id', 'QueryProfile.user_id'),
				'conditions' => array(
					$query_string
				),
				'recursive' => -1
			));
			if (isset($this->args[1])) { 
				$this->out($this->QueryProfile->getLastQuery()); 
			}
			
			$v1_count = count($v1_results);
			$this->out('Found '.$v1_count.' from v1');
			CakeLog::write('core.qe2.test', 'Found '.$v1_count.' from v1'); 
			
			$query_params = array(
				'partner' => $this->args[0], 
				'qualifications' => $v2_query_string
			);
			if (isset($this->args[1])) {
				$this->out('v2 Query: '.json_encode($query_params));
			}
			
			$http = new HttpSocket(array(
				'timeout' => 30,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$http->configAuth('Basic', $settings['qe.mintvine.username'], $settings['qe.mintvine.password']);
			$http_response = $http->post($settings['hostname.qe'].'/query', json_encode($query_params), array(
				'header' => array('Content-Type' => 'application/json')
			));
			CakeLog::write('qe2.test.json', json_encode($query_params)); 
			CakeLog::write('qe2.test.json', json_encode($http_response->body)); 
			CakeLog::write('qe2.test.json', '---'); 
			
			
			if ($http_response->code != 200) {
				$this->out('API inaccessible');
				$this->out(print_r($http_response, true));
				return false;
			}
			$results = json_decode($http_response->body, true);
			$v2_results = $results['panelist_ids'];
			$v2_count = count($v2_results);
			
			$pct = round(($v2_count / $v1_count) * 100, 2);
			$this->out('Found '.$v2_count.' from v2 ('.$pct.'%)');
			CakeLog::write('core.qe2.test', 'Found '.$v2_count.' from v2 ('.$pct.'%)'); 
			CakeLog::write('core.qe2.test', '----------------'); 
			$this->out('----------------');
			
			$diffs = array_diff($v1_results, $v2_results);
			if (!empty($diffs) && isset($this->args[1])) {
			//	$this->out('Missing panelist IDs from v2: '.implode(', ', $diffs));
			}
			$diffs = array_diff($v2_results, $v1_results);
			if (!empty($diffs) && isset($this->args[1])) {
			//	$this->out('Missing panelist IDs from v1: '.implode(', ', $diffs));
			}
		}
	}
}
