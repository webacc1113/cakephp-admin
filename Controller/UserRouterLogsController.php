<?php
App::uses('AppController', 'Controller');

class UserRouterLogsController extends AppController {
	public $uses = array('UserRouterLog', 'User', 'SurveyUserVisit');
	
	function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		$user_router_logs = $this->UserRouterLog->find('all', array(
			'conditions' => array(
				'UserRouterLog.parent_id' => '0'
			),
			'order' => 'UserRouterLog.id DESC',
			'limit' => 100
		));
		if ($user_router_logs) {
			foreach ($user_router_logs as $key => $user_router_log) {
				$count = $this->UserRouterLog->find('count', array(
					'recursive' => -1,
					'conditions' => array(
						'UserRouterLog.parent_id' => $user_router_log['UserRouterLog']['id']
					)
				));
				$user_router_logs[$key]['UserRouterLog']['count'] = $count + 1; // include parent
			}
		}
		$this->set(compact('user_router_logs'));
	}
		
	public function view($user_id = null, $log_id = null) {
		if (empty($user_id)) {
			throw new NotFoundException();
		}
		$this->UserRouterLog->bindModel(array(
			'belongsTo' => array(
				'Project' => array(
					'foreignKey' => 'survey_id'
				)
			),
			'hasMany' => array(
				'ChildLog' => array(
					'className' => 'UserRouterLog',
					'foreignKey' => 'parent_id'
				)
			)
		), false);
		$contain = array(
			'Project' => array(
				'fields' => array('Project.prj_name'),
				'Client' => array(
					'fields' => array('Client.client_name')
				)
			),
			'ChildLog' => array(
				'fields' => array(
					'ChildLog.id',
					'ChildLog.survey_id',
					'ChildLog.score',
					'ChildLog.ir',
					'ChildLog.loi',
					'ChildLog.epc',
					'ChildLog.epcm',
				)
			)
		);
		$this->UserRouterLog->contain($contain);
		$conditions = array(
			'UserRouterLog.user_id' => $user_id,
			'UserRouterLog.parent_id' => '0'
		);
		
		$current_conditions = array(
			'UserRouterLog.user_id' => $user_id,
			'UserRouterLog.parent_id' => '0'
		);
		
		if (!empty($log_id)) {
			$current_conditions['UserRouterLog.id'] = $log_id;
		}
		
		$current_log = $this->UserRouterLog->find('first', array(
			'conditions' => $current_conditions,
			'order' => array(
				'UserRouterLog.id' => 'DESC'
			)
		));
		if (!empty($current_log['ChildLog'])) {
			$current_log['ChildLog'] = $this->childlog_client_name($current_log['ChildLog']);
		}
		if (!empty($current_log)) {
			$log_id = $current_log['UserRouterLog']['id'];
		}
		
		$this->UserRouterLog->contain($contain);
		$prev_log = $this->UserRouterLog->find('first', array(
			'conditions' => array(
				$conditions,
				'UserRouterLog.id <' => $log_id
			),
			'order' => array(
				'UserRouterLog.id' => 'DESC'
			)
		));
		if (!empty($prev_log['ChildLog'])) {
			$prev_log['ChildLog'] = $this->childlog_client_name($prev_log['ChildLog']);
		}
		
		$this->UserRouterLog->contain($contain);
		$next_log = $this->UserRouterLog->find('first', array(
			'conditions' => array(
				$conditions,
				'UserRouterLog.id >' => $log_id
			),
			'order' => array(
				'UserRouterLog.id' => 'ASC'
			)
		));
		if (!empty($next_log['ChildLog'])) {
			$next_log['ChildLog'] = $this->childlog_client_name($next_log['ChildLog']);
		}
		
		$user = $this->User->findById($user_id);
		$title_for_layout = sprintf('User Logs - %s', $user['User']['email']);
		$this->set(compact('current_log', 'prev_log', 'next_log', 'user', 'title_for_layout'));
	}
	
	private function childlog_client_name($child_logs) {
		App::import('Model', 'Project');
		$this->Project = new Project;
		
		if (!empty($child_logs)) {
			foreach ($child_logs as $key => $child_log) {
				$project = $this->Project->find('first', array(
					'fields' => array(
						'Project.id'
					),
					'conditions' => array(
						'Project.id' => $child_log['survey_id']
					),
					'contain' => array(
						'Client' => array(
							'fields' => array(
								'Client.client_name'
							)
						),
						'Group' => array(
							'fields' => array(
								'Group.name'
							)
						)
					)
				));
				if (!empty($project['Client'])) {
					$child_logs[$key]['client_name'] = $project['Client']['client_name'];
				}
				
				//childlog group
				if (!empty($project['Group'])) {
					$child_logs[$key]['group_name'] = $project['Group']['name'];
				}
			}
		}
		return $child_logs;
	}

	
	public function engagement() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$date = date(DB_DATE, strtotime($this->request->data['UserRouterLog']['date']));
			$min_max = $this->UserRouterLog->find('first', array(
				'fields' => array('MIN(id) as min_id', 'MAX(id) as max_id'),
				'conditions' => array(
					'UserRouterLog.created >=' =>  $date.' 00:00:00',
					'UserRouterLog.created <=' =>  $date.' 23:59:59',
					'UserRouterLog.parent_id' => '0'					
				)
			));
			$min_id = $min_max[0]['min_id'];
			$max_id = $min_max[0]['max_id'];
			
			$distinct_users = $this->UserRouterLog->find('all', array(
				'fields' => array('DISTINCT(user_id) as user_id'),
				'conditions' => array(
					'UserRouterLog.id >=' => $min_id,
					'UserRouterLog.id <=' => $max_id,
					'UserRouterLog.parent_id' => '0'
				)
			));
			
			$user_ids = Set::extract('/UserRouterLog/user_id', $distinct_users);
			
			$survey_statuses = unserialize(SURVEY_STATUSES);
			$header = array(
				'User ID',
				'Started Sessions',
				'Unique Surveys'
			);
			foreach ($survey_statuses as $status => $name) {
				$header[] = $name;
			}
			$rows = array($header);
			if (!empty($user_ids)) {
				foreach ($user_ids as $user_id) {
					$started_sessions = $this->UserRouterLog->find('count', array(
						'conditions' => array(
							'UserRouterLog.user_id' => $user_id,
							'UserRouterLog.parent_id' => '0',
							'UserRouterLog.id >=' => $min_id,
							'UserRouterLog.id <=' => $max_id,
						)
					));
					
					$unique_surveys = $this->UserRouterLog->find('all', array(
						'fields' => array('DISTINCT(survey_id) as survey_id'),
						'conditions' => array(
							'UserRouterLog.user_id' => $user_id,
							'UserRouterLog.id >=' => $min_id,
							'UserRouterLog.id <=' => $max_id,
						)
					));
					$unique_surveys_through_router = count($unique_surveys);
					$survey_ids = Set::extract('/UserRouterLog/survey_id', $unique_surveys);
					
					$survey_user_visits = $this->SurveyUserVisit->find('list', array(
						'fields' => array('SurveyUserVisit.survey_id', 'SurveyUserVisit.status'),
						'conditions' => array(
							'SurveyUserVisit.user_id' => $user_id,
							'SurveyUserVisit.survey_id' => $survey_ids
						)
					));
					$status_counts = array_count_values($survey_user_visits); 
					$status_counts[SURVEY_CLICK] = array_sum($status_counts);
					$row = array(
						$user_id,
						$started_sessions,
						$unique_surveys_through_router
					);

					foreach ($survey_statuses as $status => $name) {
						if (isset($status_counts[$status])) {
							$row[] = $status_counts[$status]; 
						}
						else {
							$row[] = 0;
						}
					}
					$rows[] = $row;
				}
			}
				
  			$filename = 'router_engagement-'.$date . '.csv';
	  		$csv_file = fopen('php://output', 'w');

			header('Content-type: application/csv');
			header('Content-Disposition: attachment; filename="' . $filename . '"');

			// Each iteration of this while loop will be a row in your .csv file where each field corresponds to the heading of the column
			foreach ($rows as $row) {
				fputcsv($csv_file, $row, ',', '"');
			}

			fclose($csv_file);
			$this->autoRender = false;
			$this->layout = false;
			$this->render(false);
		}
	}
}