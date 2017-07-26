<?php

App::uses('Component', 'Controller');
class SurveyToolsComponent extends Component {
	
	public function set_recontact_flag($original_project_id, $project_id, $current_user) {
		// for recontact projects; write the flag
		
		$models_to_load = array(
			'Project',
			'ProjectLog',
		);
		foreach ($models_to_load as $model) {			
			App::import('Model', $model);
			$this->$model = new $model;
		}		
		
		$recontact_project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $original_project_id
			),
			'fields' => array('Project.has_recontact_project'),
			'recursive' => -1
		)); 
		if ($recontact_project) {
			if (!$recontact_project['Project']['has_recontact_project']) {
				$this->Project->create();
				$this->Project->save(array('Project' => array(
					'id' => $original_project_id,
					'has_recontact_project' => true,
					'modified' => false
				)), array(
					'fieldList' => array('has_recontact_project'),
					'callbacks' => false,
					'validate' => false
				));	
			}
		
			$recontact_update_text = 'Recontact project set-up at #'.$project_id;
			$count = $this->ProjectLog->find('count', array(
				'conditions' => array(
					'ProjectLog.project_id' => $original_project_id,
					'ProjectLog.type' => 'survey.updated.recontact',
					'ProjectLog.description' => $recontact_update_text
				)
			));
			if ($count == 0) {
				// write project log
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $original_project_id,
					'user_id' => $current_user['Admin']['id'],
					'type' => 'survey.updated.recontact',
					'description' => $recontact_update_text
				)));
			}
		}
	}
	
	public function reset($project_id) {
		$models_to_load = array(
			'Project',
			'SurveyVisit', 
			'SurveyUserVisit',
			'SurveyUser',
			'SurveyAccess',
			'SurveyLink',
			'SurveyReport',
			'FpIp',
			'SurveyVisitCache',
			'SurveyFingerprint',
			'SurveyUserQuery',
			'Qualification',
			'QualificationStatistic',
			'QualificationUser',
			'ProjectLog',
			'ProjectFingerprint',
			'Nonce',
			'Visit',
			'Query'
		);
		foreach ($models_to_load as $model) {			
			App::import('Model', $model);
			$this->$model = new $model;
		}		
		
		$project = $this->Project->findById($project_id);
		if (!$project) {
			return false;
		}
		
		$survey_visit_cache = $this->SurveyVisitCache->findBySurveyId($project_id);
		
		// query statistics
		$this->Query->bindModel(array('hasMany' => array('QueryStatistic')));
		$queries = $this->Query->find('all', array(
			'conditions' => array(
				'Query.survey_id' => $project_id
			)
		));
		if ($queries) {
			foreach ($queries as $query) {
				if (isset($query['QueryStatistic']) && !empty($query['QueryStatistic'])) {
					foreach ($query['QueryStatistic'] as $query_statistic) {
						$this->Query->QueryStatistic->create();
						$this->Query->QueryStatistic->save(array('QueryStatistic' => array(
							'id' => $query_statistic['id'],
							'clicks' => '0',
							'completes' => '0',
							'nqs' => '0',
							'oqs' => '0'
						)), true, array('clicks', 'completes', 'nqs', 'oqs')); 
					}
				}
				if (isset($query['QueryHistory']) && !empty($query['QueryHistory'])) {
					foreach ($query['QueryHistory'] as $query_history) {
						if ($query_history['type'] == 'created') {
							continue;
						}
						$this->Query->QueryHistory->delete($query_history['id']); 
					}
				}
			}
		}
		$survey_visits = $this->SurveyVisit->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'SurveyVisit.survey_id' => $project_id
			),
			'fields' => array('SurveyVisit.id')
		));
		if ($survey_visits) {
			foreach ($survey_visits as $survey_visit) {
				$this->SurveyVisit->delete($survey_visit['SurveyVisit']['id']); 
			}
		}
		$survey_reports = $this->SurveyReport->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'SurveyReport.survey_id' => $project_id
			),
			'fields' => array('SurveyReport.id')
		));
		if ($survey_reports) {
			foreach ($survey_reports as $survey_report) {
				$this->SurveyReport->delete($survey_report['SurveyReport']['id']); 
			}
		}
		$survey_user_visits = $this->SurveyUserVisit->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'SurveyUserVisit.survey_id' => $project_id
			),
			'fields' => array('SurveyUserVisit.id')
		));
		if ($survey_user_visits) {
			foreach ($survey_user_visits as $survey_user_visit) {
				$this->SurveyUserVisit->delete($survey_user_visit['SurveyUserVisit']['id']); 
			}
		}
		$survey_accesses = $this->SurveyAccess->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'SurveyAccess.survey_id' => $project_id
			),
			'fields' => array('SurveyAccess.id')
		));
		if ($survey_accesses) {
			foreach ($survey_accesses as $survey_access) {
				$this->SurveyAccess->delete($survey_access['SurveyAccess']['id']); 
			}
		}
		$fp_ips = $this->FpIp->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'FpIp.survey_id' => $project_id
			),
			'fields' => array('FpIp.id')
		));
		if ($fp_ips) {
			foreach ($fp_ips as $fp_ip) {
				$this->FpIp->delete($fp_ip['FpIp']['id']); 
			}
		}
		
		// qualifications do not delete on reset; without a mechanism to re-import them; testing lucid becomes nearly impossible
		$this->Qualification->bindModel(array('hasOne' => array('QualificationStatistic')));
		$qualifications = $this->Qualification->find('all', array(
			'fields' => array('Qualification.id', 'Qualification.parent_id', 'QualificationStatistic.id'),
			'conditions' => array(
				'Qualification.project_id' => $project_id,
				'Qualification.deleted is null'
			)
		));
		if ($qualifications) {
			foreach ($qualifications as $qualification) {
				$this->Qualification->create();
				$this->Qualification->save(array('Qualification' => array(
					'id' => $qualification['Qualification']['id'],
					'refreshed' => null,
					'modified' => false
				)), true, array('refreshed')); 
				
				$this->QualificationStatistic->create();
				$this->QualificationStatistic->save(array('QualificationStatistic' => array(
					'id' => $qualification['QualificationStatistic']['id'],
					'completes' => '0',
					'clicks' => '0',
					'nqs' => '0',
					'oqs' => '0'
				)), true, array('completes', 'clicks', 'nqs', 'oqs'));
				if (empty($qualification['Qualification']['parent_id'])) {
					$qualification_users = $this->QualificationUser->find('all', array(
						'fields' => array('QualificationUser.id'),
						'conditions' => array(
							'QualificationUser.qualification_id' => $qualification['Qualification']['id'],
							'QualificationUser.deleted' => false
						),
						'recursive' => -1,
					));
					if ($qualification_users) {
						$qualification_users_chunks = array_chunk($qualification_users, 12000, false);
						foreach ($qualification_users_chunks as $qualification_users_chunk) {
							foreach ($qualification_users_chunk as $qualification_user) {
								$this->QualificationUser->delete($qualification_user['QualificationUser']['id']);
							}
						}
					}
				}
			}
		}
	
		$survey_users = $this->SurveyUser->find('all', array(
			'conditions' => array(
				'SurveyUser.survey_id' => $project_id,
			),
			'fields' => array('id'),
			'recursive' => -1
		)); 
		if (!empty($survey_users)) {
			foreach ($survey_users as $survey_user) {
				$this->SurveyUser->delete($survey_user['SurveyUser']['id']);
				$survey_user_queries = $this->SurveyUserQuery->find('all', array(
					'recursive' => -1,
					'conditions' => array(
						'SurveyUserQuery.survey_user_id' => $survey_user['SurveyUser']['id']
					),
					'fields' => array('SurveyUserQuery.id')
				));
				if ($survey_user_queries) {
					foreach ($survey_user_queries as $survey_user_query) {
						$this->SurveyUserQuery->delete($survey_user_query['SurveyUserQuery']['id']); 
					}
				}
			}
		}
		
		$survey_fingerprints = $this->SurveyFingerprint->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'SurveyFingerprint.survey_id' => $project_id
			),
			'fields' => array('SurveyFingerprint.id')
		));
		if ($survey_fingerprints) {
			foreach ($survey_fingerprints as $survey_fingerprint) {
				$this->SurveyFingerprint->delete($survey_fingerprint['SurveyFingerprint']['id']); 
			}
		}
		
		$project_fingerprints = $this->ProjectFingerprint->find('all', array(
			'fields' => array('ProjectFingerprint.id'),
			'conditions' => array(
				'ProjectFingerprint.project_id' => $project_id
			),
			'recursive' => -1,
		));
		if ($project_fingerprints) {
			foreach ($project_fingerprints as $project_fingerprint) {
				$this->ProjectFingerprint->delete($project_fingerprint['ProjectFingerprint']['id']); 
			}
		}
		
		$visits = $this->Visit->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'Visit.survey_id' => $project_id
			),
			'fields' => array('Visit.id')
		));
		if ($visits) {
			foreach ($visits as $visit) {
				$this->Visit->delete($visit['Visit']['id']); 
			}
		}
		$this->SurveyLink->updateAll(array('used' => '0'), array('survey_id' => $project_id));
		$this->Project->SurveyPartner->updateAll(
			array(
				'clicks' => '0',
				'completes' => '0',
				'nqs' => '0',
				'oqs' => '0',
				'fails' => '0',
				'speeds' => '0'
			), 
			array('survey_id' => $project_id)
		);
		$nonces = $this->Nonce->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'Nonce.item_id' => $project_id,
				'Nonce.item_type' => 'survey'
			),
			'fields' => array('Nonce.id')
		));
		if ($nonces) {
			foreach ($nonces as $nonce) {
				$this->Nonce->delete($nonce['Nonce']['id']); 
			}
		}
		
		if ($survey_visit_cache) {
			$this->SurveyVisitCache->save(array('SurveyVisitCache' => array(
				'id' => $survey_visit_cache['SurveyVisitCache']['id'],
				'total' => '0',
				'click' => '0',
				'complete' => '0',
				'nq' => '0',
				'overquota' => '0',
				'oq_internal' => '0',
				'block' => '0',
				'speed' => '0',
				'fraud' => '0',
				'prescreen_clicks' => $project['Project']['prescreen'] ? '0' : null,
				'prescreen_completes' => $project['Project']['prescreen'] ? '0' : null,
				'prescreen_nqs' => $project['Project']['prescreen'] ? '0' : null
			)));
		}		
	}
	
	public function check_valid_links($links, $project) {
		$existing = array();
		foreach ($links as $link) {
			if (!empty($project['Project']['recontact_id']) && empty($link['user_id']) && empty($link['partner_user_id'])) {
				return false;
			}
			if (in_array($link['link'], $existing)) {
				return false;
			}
			$existing[] = $link['link'];
		}
		return true;
	}
	
	public function process_links($filename, $survey_id) {
		App::import('Model', 'SurveyLink');
		$this->SurveyLink = new SurveyLink;
		
		$old_links = $this->SurveyLink->find('list', array(
			'fields' => array('id', 'link'),
			'conditions' => array(
				'SurveyLink.survey_id' => $survey_id
			)
		));
		if (!$old_links) {
			$old_links = array();
		}
		$i = 0;
		$contents = file_get_contents($filename);
		$links = preg_split( "/(\\n|\\r)/", $contents);
		$links = array_unique($links); 
		$return = array();
		$rows = 0;
		foreach ($links as $line) {
			$line = trim($line);
			if (empty($line)) {
				continue;
			}
			$arr = str_getcsv($line);
			$link = trim($arr[0]);
			$rows++;
			if (empty($link)) {
				continue;
			}			
			if (in_array($link, $old_links)) {
				continue;
			}			
			$user_id = null;
			if (isset($arr[1]) && !empty($arr[1])) {
				$user_id = trim($arr[1]);
				if (in_array($link.'-'.$user_id, $old_links)) {
					continue;
				}
				$old_links[] = $link.'-'.$user_id;
			} 
			$this->SurveyLink->create();
			$this->SurveyLink->save(array(
				'survey_id' => $survey_id,
				'link' => $link,
				'user_id' => !empty($user_id) ? $user_id: null
			));
			$i++;
			$return['links'][] = array('link' => $link, 'user_id' => $user_id);
		}
		$return['rows'] = $rows;
		return $return;
	}
	
	public function refresh_statistics($survey_id) {
		$return = false;
		$models_to_import = array('SurveyVisit', 'SurveyPartner', 'SurveyVisitCache');
		foreach ($models_to_import as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}

		$partners = $this->SurveyPartner->find('list', array(
			'fields' => array('id', 'partner_id'),
			'conditions' => array(
				'SurveyPartner.survey_id' => $survey_id
			)
		));

		$click_count = $this->SurveyVisit->find('count', array(
			'conditions' => array(
				'SurveyVisit.survey_id' => $survey_id,
				'SurveyVisit.type' => SURVEY_CLICK
			)
		));

		$complete_count = $this->SurveyVisit->find('count', array(
			'conditions' => array(
				'SurveyVisit.survey_id' => $survey_id,
				'SurveyVisit.type' => SURVEY_COMPLETED
			)
		));

		$nq_count = $this->SurveyVisit->find('count', array(
			'conditions' => array(
				'SurveyVisit.survey_id' => $survey_id,
				'SurveyVisit.type' => SURVEY_NQ
			)
		));

		$oq_count = $this->SurveyVisit->find('count', array(
			'conditions' => array(
				'SurveyVisit.survey_id' => $survey_id,
				'SurveyVisit.type' => SURVEY_OVERQUOTA
			)
		));
		
		$oq_internal_count = $this->SurveyVisit->find('count', array(
			'conditions' => array(
				'SurveyVisit.survey_id' => $survey_id,
				'SurveyVisit.type' => SURVEY_OQ_INTERNAL
			)
		));

		$survey_visit_cache = $this->SurveyVisitCache->find('first', array(
			'conditions' => array(
				'SurveyVisitCache.survey_id' => $survey_id
			)
		));
		if ($survey_visit_cache) {
			$this->SurveyVisitCache->create();
			$this->SurveyVisitCache->save(array('SurveyVisitCache' => array(
					'id' => $survey_visit_cache['SurveyVisitCache']['id'],
					'click' => $click_count,
					'complete' => $complete_count,
					'nq' => $nq_count,
					'overquota' => $oq_count,
					'oq_internal' => $oq_internal_count,
				)), array(
				'callbacks' => false,
				'fieldList' => array('click', 'complete', 'nq', 'overquota', 'oq_internal')
			));
			$return = true;
		}

		if (!empty($partners)) {
			foreach ($partners as $partner_id) {
				$click_count = $this->SurveyVisit->find('count', array(
					'conditions' => array(
						'SurveyVisit.survey_id' => $survey_id,
						'SurveyVisit.partner_id' => $partner_id,
						'SurveyVisit.type' => SURVEY_CLICK
					)
				));

				$complete_count = $this->SurveyVisit->find('count', array(
					'conditions' => array(
						'SurveyVisit.survey_id' => $survey_id,
						'SurveyVisit.partner_id' => $partner_id,
						'SurveyVisit.type' => SURVEY_COMPLETED
					)
				));

				$nq_count = $this->SurveyVisit->find('count', array(
					'conditions' => array(
						'SurveyVisit.survey_id' => $survey_id,
						'SurveyVisit.partner_id' => $partner_id,
						'SurveyVisit.type' => SURVEY_NQ
					)
				));

				$oq_count = $this->SurveyVisit->find('count', array(
					'conditions' => array(
						'SurveyVisit.survey_id' => $survey_id,
						'SurveyVisit.partner_id' => $partner_id,
						'SurveyVisit.type' => SURVEY_OVERQUOTA
					)
				));
				
				$oq_internal_count = $this->SurveyVisit->find('count', array(
					'conditions' => array(
						'SurveyVisit.survey_id' => $survey_id,
						'SurveyVisit.partner_id' => $partner_id,
						'SurveyVisit.type' => SURVEY_OQ_INTERNAL
					)
				));

				$survey_partner = $this->SurveyPartner->find('first', array(
					'conditions' => array(
						'SurveyPartner.survey_id' => $survey_id,
						'SurveyPartner.partner_id' => $partner_id
					)
				));
				if ($survey_partner) {
					$this->SurveyPartner->create();
					$this->SurveyPartner->save(array('SurveyPartner' => array(
							'id' => $survey_partner['SurveyPartner']['id'],
							'clicks' => $click_count,
							'completes' => $complete_count,
							'nqs' => $nq_count,
							'oqs' => $oq_count,
							'oqs_internal' => $oq_internal_count,
						)), array(
						'callbacks' => false,
						'fieldList' => array('clicks', 'completes', 'nqs', 'oqs', 'oqs_internal')
					));
				}
			}
		}
		
		return $return;
	}
	
	function execute_rfg_api($command, $settings) {
		$key = hex2bin($settings['rfg.secret']);
		$time = time();
		$hash = hash_hmac('sha1', $time . $command, $key);
		$api_url = $settings['rfg.host'] . "?apid=" . $settings['rfg.apid'] . "&time=" . $time . "&hash=" . $hash;

		$ch = curl_init($api_url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POSTFIELDS, $command);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // should be removed
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: application/json',
			'Content-Length: ' . strlen($command))
		);
		$result = curl_exec($ch);
		curl_close($ch);
		if (empty($result)) {
			return false;
		}

		return json_decode($result, true);
	}

}

if (!function_exists('hex2bin')) {

	function hex2bin($hexstr) {
		$n = strlen($hexstr);
		$sbin = '';
		$i = 0;
		while ($i < $n) {
			$a = substr($hexstr, $i, 2);
			$c = pack("H*", $a);
			if ($i == 0) {
				$sbin = $c;
			}
			else {
				$sbin .= $c;
			}
			$i+=2;
		}
		return $sbin;
	}

}