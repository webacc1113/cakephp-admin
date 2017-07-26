<?php
App::uses('AppModel', 'Model');

class Project extends AppModel {
	public $actsAs = array('Containable', 'Multivalidatable');
	public $displayField = 'prj_name';
	public $ir = false;
	public $client_rate = false;
	public $award = false;
	
	public $validate = array(
        'prj_name' => array(
            'rule' => 'notEmpty'
        ),
        'client_rate' => array(
            'rule' => 'notEmpty'
        ),
		'recontact_id' => array(
            'rule' => array('validateRecontactId'),
			'allowEmpty' => true,
			'message' => 'You cannot have more than one active recontact project for this original project ID.'
		),
		'survey_name' => array(
            'rule' => 'notEmpty'
        ),
        'award' => array(
            'rule' => array('validateAward'),
			'allowEmpty' => true,
			'message' => 'You input an invalid award amount.'
        ),
    );

	public $hasOne = array(
		'SurveyVisitCache' => array(
			'className' => 'SurveyVisitCache',
			'foreignKey' => 'survey_id'
		),
    );

	public $hasMany = array(
		'SurveyPartner' => array(
			'className' => 'SurveyPartner',
			'foreignKey' => 'survey_id'
		),
		'ProjectOption' => array(
			'className' => 'ProjectOption',
			'foreignKey' => 'project_id',
			'order' => 'ProjectOption.name ASC'
		),
		'ProjectAdmin' => array(
			'className' => 'ProjectAdmin',
			'foreignKey' => 'project_id',
		)
	);
	
	public $belongsTo = array(
		'Client' => array(
			'className' => 'Client',
			'foreignKey' => 'client_id'
		),
		'Group' => array(
			'className' => 'Group',
			'foreignKey' => 'group_id'
		)
    );

	public $validationSets = array(
		'static_list_generator' => array(
			'url' => array(
				'notEmpty' => array(
					'rule' => 'notEmpty',
					'message' => 'Url is required.'
				)
			),
			'hashes' => array(
				'notEmpty' => array(
					'rule' => 'notEmpty',
					'message' => 'Please enter hashes.'
				)
			)
		)
	);
	
	public function validateAward() {
		if (isset($this->data[$this->alias]['award'])) {
			if (strpos($this->data[$this->alias]['award'], '.5') !== false) {
				return false;
			}
			if ($this->data[$this->alias]['award'] < 5) {
				return false;
			}
		}
		return true;
	}
	
	public function validateRecontactId() {
		if (!isset($this->data[$this->alias]['recontact_id']) || empty($this->data[$this->alias]['recontact_id'])) {
			return true;
		}
		$conditions = array(
			'Project.status' => PROJECT_STATUS_OPEN,
			'Project.recontact_id' => $this->data[$this->alias]['recontact_id']
		);
		if (isset($this->data[$this->alias]['id'])) {
			$conditions['Project.id <>'] = $this->data[$this->alias]['id'];
		}
		$count = $this->find('count', array(
			'conditions' => $conditions,
			'recursive' => -1
		));
		return $count == 0;
	}

	public function bindInvoices() {		
		$this->bindModel(array(
			'hasOne' => array(
				'Invoice' => array(
					'className' => 'Invoice',
					'foreignKey' => 'project_id'
				)
			),
		));
	}
	
	public function bindRates() {		
		$this->bindModel(array(
			'hasMany' => array(
				'HistoricalRates' => array(
					'className' => 'ProjectRate',
					'foreignKey' => 'project_id'
				)
			)
		));
	}
	
	public function bindFedSurvey() {		
		$this->bindModel(array(
			'hasOne' => array(
				'FedSurvey' => array(
					'className' => 'FedSurvey',
					'foreignKey' => 'survey_id',
				)
			)
		));
	}
	public function bindCintSurvey() {		
		$this->bindModel(array(
			'hasOne' => array(
				'CintSurvey' => array(
					'className' => 'CintSurvey',
					'foreignKey' => 'survey_id',
				)
			)
		));
	}
	public function bindRfgSurvey() {		
		$this->bindModel(array(
			'hasOne' => array(
				'RfgSurvey' => array(
					'className' => 'RfgSurvey',
					'foreignKey' => 'survey_id',
				)
			)
		));
	}
	public function bindProjectLog() {		
		$this->bindModel(array(
			'hasOne' => array(
				'ProjectLog' => array(
					'className' => 'ProjectLog',
					'foreignKey' => 'project_id',
					'order' => 'ProjectLog.id desc'
				)
			)
		));
	}
	public function bindSpectrumProject() {		
		$this->bindModel(array(
			'hasOne' => array(
				'SpectrumProject' => array(
					'className' => 'SpectrumProject',
					'foreignKey' => 'project_id'
				)
			)
		));
	}
	
	public function bindProjectIr() {	
		$this->bindModel(array(
			'hasMany' => array(
				'ProjectIr' => array(
					'className' => 'ProjectIr',
					'foreignKey' => 'project_id',
					'order' => 'ProjectIr.id DESC'
				)
			)
		));
	}
	
	public function beforeSave($options = array()) {
		if (isset($this->data[$this->alias]['prj_name']) && !empty($this->data[$this->alias]['prj_name'])) {
			$this->data[$this->alias]['prj_name'] = mb_convert_encoding($this->data[$this->alias]['prj_name'],'UTF-8','UTF-8'); 
		}
		
		if (!isset($this->data[$this->alias]['id']) && !isset($this->data[$this->alias]['code'])) {
			$this->data[$this->alias]['code'] = substr(md5(uniqid(rand(), true)), 0, 8); 
		}
		
		if (isset($this->data[$this->alias]['client_survey_link'])) {
			$this->data[$this->alias]['client_survey_link'] = trim($this->data[$this->alias]['client_survey_link']); 
		}
		
		if (!isset($this->data[$this->alias]['id']) && !isset($this->data[$this->alias]['survey_code'])) {
			App::import('Model', 'Dictionary');
			$this->Dictionary = new Dictionary;
			
			$dictionaries = $this->Dictionary->find('all');
			if (!empty($dictionaries)) {
				$colors = $adjectives = $animals = array();
				foreach ($dictionaries as $dictionary) {
					$colors[] = $dictionary['Dictionary']['color'];
					$adjectives[] = $dictionary['Dictionary']['adjective'];
					$animals[] = $dictionary['Dictionary']['animal'];
				}
				
				$created_phrases = array();
				$i = 0; 
				while (true) {
					$i++;
					$color = $colors[mt_rand(0, count($colors) - 1)];
					$adjective = $adjectives[mt_rand(0, count($adjectives) - 1)];
					$animal = $animals[mt_rand(0, count($colors) - 1)];
					
					$this->data[$this->alias]['survey_code'] = $phrase = $color.' '.$adjective.' '.$animal;
					if (in_array($phrase, $created_phrases)) {
						continue;
					}
					else {
						// check survey_code uniqueness within last 90 days
						$conditions = array(
							'survey_code' => $phrase, 
							'date_created >' => date('Y-m-d H:i:s', strtotime('-90 days'))
						);
						if (!$this->hasAny($conditions) || $i == 100) {
							break;
						}
						
						$created_phrases[] = $phrase;
					}
				}
			}
		}
		
		// epc is stored in pennies and stores the effective rate based on rate and ir
		if (isset($this->data[$this->alias]['bid_ir']) || isset($this->data[$this->alias]['client_rate'])) {
			
			// we might just be updating the bid ir or client rate, in which case epc needs to be adjusted
			if (isset($this->data[$this->alias]['id']) && (!isset($this->data[$this->alias]['bid_ir']) || !isset($this->data[$this->alias]['client_rate']))) {
				if (!isset($this->data[$this->alias]['bid_ir'])) {
					$field = 'bid_ir';	
				}
				if (!isset($this->data[$this->alias]['client_rate'])) {
					$field = 'client_rate';
				}
				$existing_project = $this->find('first', array(
					'fields' => array($field),
					'recursive' => -1,
					'conditions' => array(
						'Project.id' => $this->data[$this->alias]['id']
					)
				));
				if ($existing_project) {
					$this->data[$this->alias][$field] = $existing_project['Project'][$field];
				}
			}
			if (empty($this->data[$this->alias]['bid_ir']) || empty($this->data[$this->alias]['client_rate'])) {
				$this->data[$this->alias]['epc'] = null;
			}
			else {
				$this->data[$this->alias]['epc'] = round($this->data[$this->alias]['bid_ir'] * $this->data[$this->alias]['client_rate']); 
			}
		}
		
		if (!isset($this->data[$this->alias]['id'])) {
			$this->data[$this->alias]['created'] = date(DB_DATETIME, time());
			
			if (isset($this->data[$this->alias]['group_id']) && !empty($this->data[$this->alias]['group_id'])) {
				App::import('Model', 'Group');
				$this->Group = new Group;
				
				$group = $this->Group->findById($this->data[$this->alias]['group_id']);
				if (!$group) {
					$this->data[$this->alias]['group_id'] = null; 
				}
				else {
					$increment = $group['Group']['increment'] + 1;
					$this->Group->create();
					$this->Group->save(array('Group' => array(
						'id' => $group['Group']['id'],
						'increment' => $increment
					)), array(
						'fieldList' => array('increment'), 
						'modified' => true,
						'callbacks' => false
					));
					
					if (!isset($this->data[$this->alias]['mask'])) {
						if (strlen($increment) < 4) {
							$increment = str_pad($increment, 4, '0', STR_PAD_LEFT);
						}
						$this->data[$this->alias]['mask'] = $increment;	
					}					
				}
			}
		}
		
		if (!isset($this->data[$this->alias]['modified'])) {
			$this->data[$this->alias]['modified'] = date(DB_DATETIME);
		}
		
		if (((isset($this->data[$this->alias]['nq_award']) && trim($this->data[$this->alias]['nq_award']) == '') || !isset($this->data[$this->alias]['nq_award'])) && !empty($this->data[$this->alias]['user_payout'])) {
			$nq_award = round(($this->data[$this->alias]['user_payout'] * 100 * 5 ) / 100);
			if ($nq_award > 5) {
				$this->data[$this->alias]['nq_award'] = 5;
			}
			else {
				$this->data[$this->alias]['nq_award'] = round(($this->data[$this->alias]['user_payout'] * 100 * 5 ) / 100);
			}
		}
		
		// Save bid_ir historically
		if (isset($this->data[$this->alias]['id']) && isset($this->data[$this->alias]['bid_ir']) && !empty($this->data[$this->alias]['bid_ir'])) {
			$existing_project = $this->find('first', array(
				'fields' => array('Project.bid_ir'),
				'conditions' => array('Project.id' => $this->data[$this->alias]['id']),
				'recursive' => -1
			));
			
			if (!empty($existing_project['Project']['bid_ir']) && $existing_project['Project']['bid_ir'] != $this->data[$this->alias]['bid_ir']) {
				$this->ir = $existing_project['Project']['bid_ir'];
			}
		}
		
		// Save payouts historically
		if (isset($this->data[$this->alias]['client_rate']) || isset($this->data[$this->alias]['award'])) {
			
			// On edit
			if (isset($this->data[$this->alias]['id'])) {
				$existing_project = $this->find('first', array(
					'fields' => array('Project.client_rate', 'Project.award'),
					'conditions' => array('Project.id' => $this->data[$this->alias]['id']),
					'recursive' => -1
				));

				if (isset($this->data[$this->alias]['client_rate'])) {
					
					// comparing floats can be problematic, workaround as per http://php.net/manual/en/language.types.float.php#language.types.float.comparison
					$updated = false;
					if (is_float($this->data[$this->alias]['client_rate'])) {
						if (abs($this->data[$this->alias]['client_rate'] - $existing_project['Project']['client_rate']) > 0.00001) {
							$updated = true;
						}
					}
					elseif ($this->data[$this->alias]['client_rate'] != $existing_project['Project']['client_rate']) {
						$updated = true;
					}
					
					if ($updated) {
						$this->client_rate = $this->data[$this->alias]['client_rate'];
					}
				}

				if (isset($this->data[$this->alias]['award'])) {
					
					// comparing floats can be problematic, workaround as per http://php.net/manual/en/language.types.float.php#language.types.float.comparison
					$updated = false;
					if (is_float($this->data[$this->alias]['award'])) {
						if (abs($this->data[$this->alias]['award'] - $existing_project['Project']['award']) > 0.00001) {
							$updated = true;
						}
					}
					elseif ($this->data[$this->alias]['award'] != $existing_project['Project']['award']) {
						$updated = true;
					}
					
					if ($updated) {
						$this->award = $this->data[$this->alias]['award'];
					}
				}
				
				// If only one payout field changes, keep the default value for the other.
				if (!empty($this->client_rate) && $this->award === false) {
					$this->award = $existing_project['Project']['award'];
				}

				if (!empty($this->award) && $this->client_rate === false) {
					$this->client_rate = $existing_project['Project']['client_rate'];
				}
			}
			else { // on Create
				if (isset($this->data[$this->alias]['client_rate'])) {
					$this->client_rate = $this->data[$this->alias]['client_rate'];
				}

				if (isset($this->data[$this->alias]['award'])) {
					$this->award = $this->data[$this->alias]['award'];
				}
			}
		}
		
		return true;
	}
		
	public function afterSave($created, $options = array()) {
		if ($created) {
			App::import('Model', 'SurveyVisitCache'); 
			$this->SurveyVisitCache = new SurveyVisitCache;
			
			$survey_visit_cache['SurveyVisitCache']['survey_id'] = $this->id;
			if (isset($this->data[$this->alias]['prescreen']) && $this->data[$this->alias]['prescreen']) {
				$survey_visit_cache['SurveyVisitCache']['prescreen_clicks'] = 0;
				$survey_visit_cache['SurveyVisitCache']['prescreen_completes'] = 0;
				$survey_visit_cache['SurveyVisitCache']['prescreen_nqs'] = 0;
			}
			
			$this->SurveyVisitCache->create();
			$this->SurveyVisitCache->save($survey_visit_cache);
		}
		
		if ($this->ir) {
			App::import('Model', 'ProjectIr');
			$this->ProjectIr = new ProjectIr;
			$this->ProjectIr->create();
			$this->ProjectIr->save(array('ProjectIr' => array(
				'project_id' => $this->id,
				'ir' => $this->ir
			)));
			
			// unset
			$this->ir = false;
		}
		
		if ($this->client_rate || $this->award) {
			App::import('Model', 'ProjectRate');
			$this->ProjectRate = new ProjectRate;
			$this->ProjectRate->create();
			$this->ProjectRate->save(array('ProjectRate' => array(
				'project_id' => $this->id,
				'client_rate' => ($this->client_rate) ? $this->client_rate : '0.00',
				'award' => ($this->award) ? $this->award : '0.00',
			)));
			
			// unset
			$this->client_rate = false;
			$this->award = false;
		}
	}
	
	function reset_surveylinks_count($project_id) {
		$models_to_load = array('SurveyLink', 'ProjectOption');
		foreach ($models_to_load as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		$survey_links_count = $this->SurveyLink->find('count', array(
			'conditions' => array( 
				'SurveyLink.survey_id' => $project_id
			)
		));
		$survey_links_unused = $this->SurveyLink->find('count', array(
			'conditions' => array(
				'SurveyLink.survey_id' => $project_id,
				'SurveyLink.used' => false,
			)
		));
		$project_option_links = $this->ProjectOption->find('list', array(
			'fields' => array('ProjectOption.name', 'ProjectOption.id'),
			'conditions' => array(
				'ProjectOption.name' => array('links.count', 'links.unused'),
				'ProjectOption.project_id' => $project_id
			)
		));

		if ($survey_links_count == 0 && $project_option_links) { // Case : all survey_links deleted
		    foreach ($project_option_links as $project_option_id) {
				$this->ProjectOption->delete($project_option_id);	
			}
			$this->save(array('Project' => array(
				'id' => $project_id,
				'has_links' => false
			)), true, array('has_links'));
			return true;
		}
		elseif (!$survey_links_count) {
			return true;
		}
		
		if (isset($project_option_links['links.count'])) {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'id' => $project_option_links['links.count'],
				'value' => $survey_links_count
			)), true, array('value'));
		}
		else {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'project_id' => $project_id,
				'name' => 'links.count',
				'value' => $survey_links_count
			)));
			
			$this->save(array('Project' => array(
				'id' => $project_id,
				'has_links' => true
			)), true, array('has_links'));
		}
		
		if (isset($project_option_links['links.unused'])) {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array( 
				'id' => $project_option_links['links.unused'],
				'value' => $survey_links_unused
			)), true, array('value'));
		}
		else {
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'project_id' => $project_id,
				'name' => 'links.unused',
				'value' => $survey_links_unused
			)));
		}
	}
}
