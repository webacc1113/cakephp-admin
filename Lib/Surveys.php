<?php

class Surveys {
	
	public static function getSurveys($user_id, $project_id = null) {
		App::import('Model', 'Project');
		$Project = new Project;
		App::import('Model', 'SurveyUser');
		$SurveyUser = new SurveyUser;
		
		if (!is_null($project_id)) {
			$conditions = array(
				'Project.id' => $project_id,
				'Project.active' => true,
			); 
		}
		else {
			$conditions = array(
				'Project.status' => array(PROJECT_STATUS_OPEN, PROJECT_STATUS_SAMPLING),
				'Project.active' => true,
			);
			
			// find all public surveys + add users to them	
			$Project->unbindModel(array(
				'hasOne' => array('SurveyVisitCache'),
				'hasMany' => array('SurveyPartner', 'ProjectOption')
			)); 		
			$public_surveys = $Project->find('all', array(
				'fields' => array('Project.id'),
				'conditions' => array(
					'Project.active' => true,
					'Project.public' => true
				)
			));
			if ($public_surveys) {
				foreach ($public_surveys as $public_survey) {
					$count = $SurveyUser->find('count', array(
						'conditions' => array(
							'SurveyUser.user_id' => $user_id,
							'SurveyUser.survey_id' => $public_survey['Project']['id']
						)
					));
					if ($count == 0) {
						$SurveyUser->create();
						$SurveyUser->save(array('SurveyUser' => array(
							'user_id' => $user_id,
							'survey_id' => $public_survey['Project']['id']
						)));
					}
				}
			}
			
		}
		// on first pass, only grab the project IDs to avoid joining across too many tables
		$Project->unbindModel(array(
			'hasOne' => array('SurveyVisitCache'),
			'hasMany' => array('SurveyPartner', 'ProjectOption')
		)); 
		$all_projects = $Project->find('all', array(
			'fields' => array('Project.id'),
			'conditions' => $conditions,
		)); 
		if (!$all_projects) {
			return false;
		}
		$project_ids = Set::extract('/Project/id', $all_projects); 
		$project_ids = $SurveyUser->find('list', array(
			'fields' => array('id', 'survey_id'),
			'conditions' => array(
				'SurveyUser.user_id' => $user_id,
				'SurveyUser.survey_id' => $project_ids
			),
			'recursive' => -1
		));
		
		$surveys = $Project->find('all', array(
			'fields' => array(
				'SurveyPartner.paused',
				'Project.survey_name', 'Project.description', 'Project.award', 'Project.active', 'Project.public',
				'Project.mobile', 'Project.desktop', 'Project.tablet',
				'Project.id', 'Project.code', 'Project.quota', 'Project.bid_ir', 'Project.router', 'Project.status', 'Project.singleuse', 'Project.client_rate', 'Project.est_length',
				'SurveyUser.*',
				'SurveyUserVisit.id',
				'SurveyUserVisit.status',
				'SurveyUserVisit.redeemed',
				'SurveyVisitCache.complete',
				'Client.*',
				'Group.key'
			),
			'conditions' => array(
				'Project.id' => $project_ids
			),
			'joins' => array(
    		    array(
					'type' => 'LEFT',
					'alias' => 'SurveyUser',
					'table' => 'survey_users',
					'conditions' => array(
						'Project.id = SurveyUser.survey_id',
						'SurveyUser.user_id' => $user_id
					)
		        ),
    		    array(
					'type' => 'INNER',
					'alias' => 'SurveyPartner',
					'table' => 'survey_partners',
		            'conditions' => array(
						'Project.id = SurveyPartner.survey_id',
						'SurveyPartner.partner_id' => 43 // mintvine
					)
		        ),
    		    array(
					'type' => 'LEFT',
					'alias' => 'SurveyUserVisit',
					'table' => 'survey_user_visits',
					'conditions' => array(
						'Project.id = SurveyUserVisit.survey_id',
						'SurveyUserVisit.user_id' => $user_id
					)
		        ),
			)
		));
		return $surveys;
	}
	
	public static function canViewSurvey($survey, $authed_user, $get_hidden = null) {
		//If survey hidden by user
		if (is_null($get_hidden)) {
			if ($survey['SurveyUser']['hidden'] && $survey['SurveyUser']['hidden'] != SURVEY_HIDDEN_SAMPLING) {
				return false;
			}
		}
		elseif ($get_hidden === true) {
			if (!$survey['SurveyUser']['hidden']) {
				return false;
			}
		}
		
		// do the mobile + desktop checks
		if (!$survey['Project']['mobile'] && $authed_user['User']['is_mobile']) {
			return false;
		}
		if (!$survey['Project']['tablet'] && $authed_user['User']['is_tablet']) {
			return false;
		}
		if (!$survey['Project']['desktop'] && $authed_user['User']['is_desktop']) {
			return false;
		}
		// if you are not invited, sorry
		if ((!$survey['Project']['public']) && empty($survey['SurveyUser']['id'])) {
			return false;
		}
		
		// remove redeemed surveys
		if (!empty($survey['SurveyUserVisit']['id']) && $survey['SurveyUserVisit']['redeemed']) {
			return false;
		}
		// if the user has already qualified out of the survey
		if (!empty($survey['SurveyUserVisit']['id']) && in_array($survey['SurveyUserVisit']['status'], unserialize(SURVEY_TERMINATING_ACTIONS))) {
			return false;
		} 
		
		// check overall project quota - note: this must always be greater than individual quotas
		if (!empty($survey['Project']['quota']) && $survey['Project']['quota'] <= $survey['SurveyVisitCache']['complete']) {
			return false;
		}
		
		// make sure mintvine partner is not paused
		if ($survey['SurveyPartner']['paused']) {
			return false;
		}
	
		// this is a single-entry survey; once a user enters they cannot return
		if ($survey['Project']['singleuse']) {
			App::import('Model', 'SurveyAccess');
			$SurveyAccess = new SurveyAccess;
			$count = $SurveyAccess->find('count', array(
				'conditions' => array(
					'SurveyAccess.survey_id' => $survey['Project']['id'],
					'SurveyAccess.user_id' => $authed_user['User']['id']
				)
			));
			if ($count > 0) {
				return false;
			}
		}
		
		if (isset($authed_user['QueryProfile']['country'])) {
			if ($authed_user['QueryProfile']['country'] != $survey['Project']['country']) {
				return false;
			}
		}
		
		App::import('Model', 'SurveyUserQuery');
		$SurveyUserQuery = new SurveyUserQuery;
		App::import('Model', 'QueryHistory');
		$QueryHistory = new QueryHistory;
		
		// grab the query history
		$QueryHistory->bindModel(array(
			'belongsTo' => array(
				'Query' => array(
					'fields' => array(
						'id', 'parent_id', 'cint_quota_id'
					)
				)
			)
		));
		$QueryHistory->Query->bindModel(array('hasOne' => array('QueryStatistic')));
		$survey_user_queries = $SurveyUserQuery->find('all', array(
			'conditions' => array(
				'SurveyUserQuery.survey_user_id' => $survey['SurveyUser']['id'],
				
			),
			'contain' => array(
				'QueryHistory' => array(
					'fields' => array('id', 'count', 'total', 'active'),
					'Query' => array(
						'QueryStatistic'
					)
				)
			)				
		));
		if ($survey_user_queries) {
			// first pass - simple case: if all master queries are closed, then this project is closed
			$is_closed_query = true;
			foreach ($survey_user_queries as $survey_user_query) {
				// if any of the master queries are active, this project is still available
				if (empty($survey_user_query['QueryHistory']['Query']['parent_id']) && $survey_user_query['QueryHistory']['active']) {
					$is_closed_query = false;
					break;
				}
			}
			if ($is_closed_query) {
				return false;
			}
			
			// master queries only - if ANY of the master queries are open w/ quota checks, continue
			$is_open_query = false;
			
			foreach ($survey_user_queries as $survey_user_query) {
				if (!empty($survey_user_query['QueryHistory']['Query']['parent_id'])) {
					continue;
				}				
				// no quota set on master: inherit from project, which is already checked above
				if (!isset($survey_user_query['QueryHistory']['Query']['QueryStatistic']['id']) || is_null($survey_user_query['QueryHistory']['Query']['QueryStatistic']['quota'])) {
					$is_open_query = true;
					break;
				}
				
				// do the quota check
				if ($survey_user_query['QueryHistory']['Query']['QueryStatistic']['completes'] < $survey_user_query['QueryHistory']['Query']['QueryStatistic']['quota']) {
					$is_open_query = true;
					break;
				}
			}
			if (!$is_open_query) {
				return false;
			}
			
			// sub queries only - if ANY of the subqueries are closed, then the whole project is closed
			$is_open_subquery = null;
			
			foreach ($survey_user_queries as $survey_user_query) {
				if (empty($survey_user_query['QueryHistory']['Query']['parent_id'])) {
					continue;
				}
				
				// find all master query histories
				$query_histories = $QueryHistory->find('all', array(
					'recursive' => -1,
					'conditions' => array(
						'QueryHistory.query_id' => $survey_user_query['QueryHistory']['Query']['parent_id'],
						'QueryHistory.type' => 'sent'
					)
				));
				$has_open_master = true;
				foreach ($query_histories as $query_history) {
					if (!$query_history['QueryHistory']['active']) {
						$has_open_master = false;
						break;
					}
				}
				if (!$has_open_master) {
					return false;
					break;
				}
				
				// set it
				if (is_null($is_open_subquery)) {
					$is_open_subquery = true;
				}
				
				// note: this shouldn't happen: block it from happening
				if (!isset($survey_user_query['QueryHistory']['Query']['QueryStatistic']['id']) || empty($survey_user_query['QueryHistory']['Query']['QueryStatistic']['id'])) {
					$is_open_subquery = false;
					break;
				}
				// do the quota check
				if ($survey_user_query['QueryHistory']['Query']['QueryStatistic']['completes'] >= $survey_user_query['QueryHistory']['Query']['QueryStatistic']['quota']) {
					$is_open_subquery = false;
					break;
				}
				if (!$survey_user_query['QueryHistory']['active']) {
					$is_open_subquery = false;
					break;
				}
			}
			if (!is_null($is_open_subquery) && $is_open_subquery === false) {
				return false;
			}
			
			foreach ($survey_user_queries as $k => $survey_user_query) {
				$survey['SurveyUserQuery'][$k] = $survey_user_query['SurveyUserQuery'];
				$survey['SurveyUserQuery'][$k]['QueryHistory'] = $survey_user_query['QueryHistory'];
			}
		}
		else {
			$survey['SurveyUserQuery'] = array();
		}
		
		// check for cint user SurveyLink
		if ($survey['Client']['key'] == 'cint') {
			App::import('Model', 'CintSurvey');
			$CintSurvey = new CintSurvey;

			$cint_survey = $CintSurvey->find('first', array(
				'conditions' => array(
					'CintSurvey.survey_id' => $survey['Project']['id']
				)
			));
			if ($cint_survey && isset($survey['SurveyUserQuery'][0]['QueryHistory']['Query']['cint_quota_id'])) {
				if (!self::cint_survey_link($authed_user, $survey['Project']['id'], $survey['SurveyUserQuery'][0]['QueryHistory']['Query']['cint_quota_id'])) {
					
					// remove invitation
					App::import('Model', 'SurveyUser');
					$SurveyUser = new SurveyUser;
					$SurveyUser->delete($survey['SurveyUser']['id']);
					return false;
				}
			}
		}
		
		return $survey;
	}
	
	public static function canViewRemeshSurvey($survey, $authed_user, $get_hidden = null) {
		if (!Surveys::canViewSurvey($survey, $authed_user, $get_hidden)) {
			return false;
		}
		
		if ($survey['Group']['key'] == 'remesh') {
			foreach ($survey['ProjectOption'] as $project_option) {
				if ($project_option['name'] == 'is_chat_interview' && $project_option['value'] == false) {
					return false;
				}
				if ($project_option['name'] == 'interview_date' && strtotime($project_option['value']) >= strtotime('+6 days')) {
					return false;				
				}				
				App::import('Model', 'RemeshSkippedInvite');
				$RemeshSkippedInvite = new RemeshSkippedInvite;
				$remesh_skipped_invite = $RemeshSkippedInvite->find('first', array(
					'conditions' => array(
						'RemeshSkippedInvite.user_id' => $authed_user['User']['id'],
						'RemeshSkippedInvite.survey_id' => $survey['Project']['id']
					)
				));
				if ($remesh_skipped_invite) {
					return false;
				}
			}
			
			return $survey;
		}
	}
}