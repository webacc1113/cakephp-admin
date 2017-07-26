<?php
App::uses('AppController', 'Controller');

class RouterLogsController extends AppController {
	public $uses = array('UserRouterLog', 'Project');
	
	function beforeFilter() {
		parent::beforeFilter();
	}
	
	public function index() {
		
		if (isset($this->request->query['q']) && $this->request->query['q']) {
			
			$project_id = $this->request->query['q'];
			$project_id = MintVine::parse_project_id($project_id);
			
			if (!$project_id) {
				$this->Session->setFlash('Invalid project ID', 'flash_error');
				$this->redirect(array('controller' => 'router_logs', 'action' => 'index'));
			}
			
			$project = $this->Project->find('first', array(
				'conditions' => array(
					'Project.id' => $project_id
				),
				'contain' => array(
					'SurveyVisitCache',
					'ProjectAdmin'
				)
			));
			if (!$this->Admins->can_access_project($this->current_user, $project)) {
				$this->Session->setFlash('You are not authorized to access this report.', 'flash_error');
				$this->redirect(array('action' => 'index'));
			}
			
			$this->set('project', $project);
			
			$user_router_logs = $this->UserRouterLog->find('all', array(
				'conditions' => array(
					'UserRouterLog.survey_id' => $project_id
				),
				'fields' => array('UserRouterLog.id', 'UserRouterLog.user_id', 'UserRouterLog.order'),
				'order' => array(
					'UserRouterLog.id ASC',
				)
			));

			$user_ids = array();			
			if (!empty($user_router_logs)) {
				$logs = array();
				foreach ($user_router_logs as $user_router_log) {					
					if (!isset($user_ids[$user_router_log['UserRouterLog']['user_id']])) {
						$logs[] = $user_router_log['UserRouterLog']['order'] ? $user_router_log['UserRouterLog']['order'] : 0;
						$user_ids[$user_router_log['UserRouterLog']['user_id']] = $user_router_log['UserRouterLog']['user_id'];
					}					
				}
				unset($user_ids);
				sort($logs);
				$count = count($logs);
				//Calculating Mean
				$sum = array_sum($logs);
				$mean = number_format($sum / $count, 2);
				$statistics['mean'] = $mean;
				
				// Calculating Median
				if ($count % 2 == 0) {
					// If array count is an even number, we have to get two middle values of the array					
					
					// Two middle indexes
					$middle_index_1 = round($count / 2);
					$middle_index_2 = $middle_index_1 - 1;
					
					// Two middle values
					$middle_value_1 = $logs[$middle_index_1];
					$middle_value_2 = $logs[$middle_index_2];
					
					// Average of middle values
					$median = ($middle_value_1 + $middle_value_2) / 2;
					$median = number_format($median, 2);
		
					$statistics['median'] = $median;
				}
				else {
					$median_index = (round($count / 2)) - 1;
					$statistics['median'] = $logs[$median_index];
				}
				
				// Calculating Mode
				$mode = '';
				$router_positions = array_count_values($logs);
				arsort($router_positions);
				$mode_array = array();
				if (!empty($router_positions)) {
					//There can be multiple modes
					$first_item = false;
					foreach ($router_positions as $key => $value) {
						if ($first_item == false) {
							$mode_array[$key] = $value;
							$first_item = true;
						}
						else {
							//If value is repeating, we have multiple modes
							if (in_array($value, $mode_array)) {
								$mode_array[$key] = $value;
							}
							else {
								//value is less than previous one based on arsort
								break;
							}
						}
						
					}
				}
				if ($mode_array) {
					$keys = array_keys($mode_array);
					$mod_frequency = $mode_array[$keys[0]];
					$mode = implode(', ', $keys);
					$mode = $mode . ' (' . $mod_frequency . ')';
				}
				$min = $logs[0];
				$max = $logs[$count - 1];
				$statistics['min'] = $min;
				$statistics['max'] = $max;
				
				$sorted_positions = array();
				if (!empty($router_positions)) {
					for ($i = 1; $i <= $max; $i++) {
						if (isset($router_positions[$i])) {
							$sorted_positions[$i] = $router_positions[$i];
						}
						else {
							$sorted_positions[$i] = 0;
						}
					}
				}
				$router_positions = $sorted_positions;
				unset($sorted_positions);
				
				$statistics['router_positions'] = $router_positions;
				$statistics['mode'] = $mode;
				$statistics['count'] = $count;
				
				$this->set('statistics', $statistics);
			}
		}
	}
}