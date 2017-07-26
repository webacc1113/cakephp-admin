<?php
App::uses('AppController', 'Controller');

class DailyAnalysisController extends AppController {
	public $helpers = array('Text', 'Html', 'Time');
	public $uses = array('DailyAnalysis', 'DailyAnalysisProperty');
	public $components = array();
	
	public function beforeFilter() {
		parent::beforeFilter();		
	}
	
	public function index() {
		$analysis_list = array();
		$conditions = array();
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		if (!isset($this->data['date_from']) || !isset($this->data['date_to'])) {
			$this->request->data['date_from'] = date("m/01/Y");
			$this->request->data['date_to'] = date("m/d/Y");
		}
		if (!isset($this->data['type'])) {
			$this->request->data['type'] = 'day-of-week';
		}
		if (isset($this->data)) {
			if (isset($this->data['date_from']) && !empty($this->data['date_from'])) {
				if (isset($this->data['date_to']) && !empty($this->data['date_to'])) {
					$conditions['DailyAnalysis.date >='] = date(DB_DATE, strtotime($this->data['date_from']));
					$conditions['DailyAnalysis.date <='] = date(DB_DATE, strtotime($this->data['date_to']) + 86400);
				}
				else {
					$conditions['DailyAnalysis.date >='] = date(DB_DATE, strtotime($this->data['date_from'])).' 00:00:00';
					$conditions['DailyAnalysis.date <='] = date(DB_DATE, strtotime($this->data['date_from'])).' 23:59:59';
				}
			}
		}	
		$conditions['DailyAnalysis.type'] = $this->data['type'];
		$analysis = $this->DailyAnalysis->find('all', array(
			'conditions' => $conditions,
			'order' => 'DailyAnalysis.date DESC'
		));
		
		if (!empty($analysis)) {
			foreach ($analysis as $data) {
				
				// Add blank data for missing date 
				if (isset($analysis_list[$data['DailyAnalysis']['type']])) {
					
					$day_of_week_date = key( array_slice( $analysis_list[$data['DailyAnalysis']['type']], -1, 1, TRUE ) );
					$days_between = ceil(abs(strtotime($day_of_week_date) - strtotime($data['DailyAnalysis']['date'])) / 86400);
					if (!empty($days_between)) {
						for ($d = 1; $d < $days_between; $d++) {
							$add_date = date("Y-m-d",strtotime($day_of_week_date . ' -' . $d . ' days'));
							$analysis_list[$data['DailyAnalysis']['type']][$add_date] =  array();
						}
					}
				}
				
				$analysis_list[$data['DailyAnalysis']['type']][$data['DailyAnalysis']['date']]['common'] = $data['DailyAnalysis'];
				$analysis_list[$data['DailyAnalysis']['type']][$data['DailyAnalysis']['date']][$data['DailyAnalysis']['timeframe']][$data['DailyAnalysis']['daily_analysis_property_id']] = $data['DailyAnalysisProperty']['name'];
			}
		}
		
		$property_list = $this->DailyAnalysisProperty->find('list', array('fields' => array('id', 'name')));
		$this->set(compact('property_list'));
		$this->set(compact('analysis_list'));
	}
	
	public function add() {
		$property_list = array();
		$timeframe_list = array();
		if ($this->request->is('post')) {
			
			if (isset($this->request->data['DailyAnalysis']['60_top_3']) && !empty($this->request->data['DailyAnalysis']['60_top_3'])) {
				$timeframe_list['60_top_3'] = $this->request->data['DailyAnalysis']['60_top_3'];
			}
			if (isset($this->request->data['DailyAnalysis']['60_top_5']) && !empty($this->request->data['DailyAnalysis']['60_top_5'])) {
				$timeframe_list['60_top_5'] = $this->request->data['DailyAnalysis']['60_top_5'];
			}
			if (isset($this->request->data['DailyAnalysis']['30_top_3']) && !empty($this->request->data['DailyAnalysis']['30_top_3'])) {
				$timeframe_list['30_top_3'] = $this->request->data['DailyAnalysis']['30_top_3'];
			}
			if (isset($this->request->data['DailyAnalysis']['30_bottom_3']) && !empty($this->request->data['DailyAnalysis']['30_bottom_3'])) {
				$timeframe_list['30_bottom_3'] = $this->request->data['DailyAnalysis']['30_bottom_3'];
			}
			if (isset($this->request->data['DailyAnalysis']['60_bottom_3']) && !empty($this->request->data['DailyAnalysis']['60_bottom_3'])) {
				$timeframe_list['60_bottom_3'] = $this->request->data['DailyAnalysis']['60_bottom_3'];
			}
			if (isset($this->request->data['DailyAnalysis']['60_bottom_5']) && !empty($this->request->data['DailyAnalysis']['60_bottom_5'])) {
				$timeframe_list['60_bottom_5'] = $this->request->data['DailyAnalysis']['60_bottom_5'];
			}
			if (isset($this->request->data['DailyAnalysis']['strict_high']) && !empty($this->request->data['DailyAnalysis']['strict_high'])) {
				$timeframe_list['strict_high'] = $this->request->data['DailyAnalysis']['strict_high'];
			}
			if (isset($this->request->data['DailyAnalysis']['original_high']) && !empty($this->request->data['DailyAnalysis']['original_high'])) {
				$timeframe_list['original_high'] = $this->request->data['DailyAnalysis']['original_high'];
			}
			if (isset($this->request->data['DailyAnalysis']['mild_high']) && !empty($this->request->data['DailyAnalysis']['mild_high'])) {
				$timeframe_list['mild_high'] = $this->request->data['DailyAnalysis']['mild_high'];
			}
			if (isset($this->request->data['DailyAnalysis']['mild_low']) && !empty($this->request->data['DailyAnalysis']['mild_low'])) {
				$timeframe_list['mild_low'] = $this->request->data['DailyAnalysis']['mild_low'];
			}
			if (isset($this->request->data['DailyAnalysis']['original_low']) && !empty($this->request->data['DailyAnalysis']['original_low'])) {
				$timeframe_list['original_low'] = $this->request->data['DailyAnalysis']['original_low'];
			}
			if (isset($this->request->data['DailyAnalysis']['strict_low']) && !empty($this->request->data['DailyAnalysis']['strict_low'])) {
				$timeframe_list['strict_low'] = $this->request->data['DailyAnalysis']['strict_low'];
			}
			if (isset($timeframe_list) && !empty($timeframe_list)) {
				foreach ($timeframe_list as $key => $timeframe) {
					foreach ($timeframe as $property) {
						if (!is_numeric($property)) {
							$daily_analysis_property_id = $this->DailyAnalysisProperty->find('first', array(
								'fields' => array('id'),
								'conditions' => array(
									'DailyAnalysisProperty.name' => $property
								)
							));
							if (!empty($daily_analysis_property_id)) {
								$property = $daily_analysis_property_id['DailyAnalysisProperty']['id'];
							}
							else {
								$this->DailyAnalysisProperty->create();
								$save = $this->DailyAnalysisProperty->save(array('DailyAnalysisProperty' => array(
									'name' => $property
								)));
								if ($save) {
									$property = $save['DailyAnalysisProperty']['id'];
								}
							}
						}
						$this->DailyAnalysis->create();
						$this->DailyAnalysis->save(array('DailyAnalysis' => array(
							'type' => ($this->request->data['DailyAnalysis']['type'] == 'topbottom') ? $this->request->data['DailyAnalysis']['type'] : $this->request->data['DailyAnalysis']['pattern'],
							'date' => $this->request->data['DailyAnalysis']['date'],
							'timeframe' => $key,
							'daily_analysis_property_id' => $property,
						)));
					}
				}
			}
			if (empty($timeframe_list)) {
				$this->DailyAnalysis->create();
				$this->DailyAnalysis->save(array('DailyAnalysis' => array(
					'type' => ($this->request->data['DailyAnalysis']['type'] == 'topbottom') ? $this->request->data['DailyAnalysis']['type'] : $this->request->data['DailyAnalysis']['pattern'],
					'date' => $this->request->data['DailyAnalysis']['date'],
					'daily_analysis_property_id' => 0,
				)));
			}
			$this->Session->setFlash('Data inserted to Daily Analysis.', 'flash_success');
			return $this->redirect(array('action' => 'index', '?' => array('type' => $this->data['DailyAnalysis']['type'])));
		}
		$property_list = $this->DailyAnalysisProperty->find('list', array('fields' => array('id', 'name')));
		$this->set(compact('property_list'));
	}
	
	public function ajax_delete() {
		if ($this->request->is('post') || $this->request->is('put')) {
			$type = $this->request->data['type'];
			$date = $this->request->data['date'];
			$daily_analysis = $this->DailyAnalysis->find('all', array(
				'fields' => array('id'),
				'conditions' => array(
					'DailyAnalysis.type' => $type,
					'DailyAnalysis.date' => $date
				),
				'recursive' => -1
			));
			if (!empty($daily_analysis)) {
				foreach ($daily_analysis as $analysis) {
					$this->DailyAnalysis->delete($analysis['DailyAnalysis']['id']);
				}
			}
			return new CakeResponse(array(
				'body' => json_encode(array(
					'status' => '1'
				)), 
				'type' => 'json',
				'status' => '201'
			));
		}	
	}
	
	public function edit() {
		
		if ($this->request->is('post') || $this->request->is('put')) {
			if (isset($this->request->data['daily_analyses']) && !empty($this->request->data)) {
				foreach ($this->request->data['daily_analyses'] as $propertkey => $properties) {
					if (!empty($properties)) {
						foreach ($properties as $datekey => $dates) {
							if (!empty($dates)) {
								foreach ($dates as $frame_key => $frames) {
									
									if ($frame_key == 'date') {
										// check if data exist for old date and delete if exists.
										if (strtotime($datekey) != strtotime($frames['year'].'-'.$frames['month'].'-'.$frames['day'])) {
											$daily_analysis_date_data = $this->DailyAnalysis->find('all', array(
												'fields' => array('id'),
												'conditions' => array(
													'DailyAnalysis.type' => $propertkey,
													'DailyAnalysis.date' => $datekey,
												)
											));
											if (!empty($daily_analysis_date_data)) {
												foreach ($daily_analysis_date_data as $daily_analysis_data) {
													$this->DailyAnalysis->delete($daily_analysis_data['DailyAnalysis']['id']);
												}
											}
											$datekey = $frames['year'].'-'.$frames['month'].'-'.$frames['day'];
										}
									}
									if (!empty($frames) && $frame_key != 'date') {
										foreach ($frames as $analysis_key => $daily_analysis) {
											if (!is_numeric($daily_analysis)) {
												$daily_analysis_property_id = $this->DailyAnalysisProperty->find('first', array(
													'fields' => array('id'),
													'conditions' => array(
														'DailyAnalysisProperty.name' => $daily_analysis
													)
												));
												if (!empty($daily_analysis_property_id)) {
													$daily_analysis = $daily_analysis_property_id['DailyAnalysisProperty']['id'];
												}
												else {
													$this->DailyAnalysisProperty->create();
													$save = $this->DailyAnalysisProperty->save(array('DailyAnalysisProperty' => array(
														'name' => $daily_analysis
													)));
													if ($save) {
														$daily_analysis = $save['DailyAnalysisProperty']['id'];
													}
												}
												$frames[$analysis_key] = $daily_analysis;
											}
											$this->DailyAnalysis->create();
											$this->DailyAnalysis->save(array('DailyAnalysis' => array(
												'type' => $propertkey,
												'date' => $datekey,
												'timeframe' => $frame_key,
												'daily_analysis_property_id' => $daily_analysis,
											))); 
										}
										$analysis = $this->DailyAnalysis->find('all', array(
											'fields' => array('id'),
											'conditions' => array(
												'DailyAnalysis.type' => $propertkey,
												'DailyAnalysis.date' => $datekey,
												'DailyAnalysis.timeframe' => $frame_key,
												"NOT" => array("DailyAnalysis.daily_analysis_property_id" => $frames)
											),
											'recursive' => -1
										));

										if (!empty($analysis)) {
											foreach ($analysis as $analyses) {
												$this->DailyAnalysis->delete($analyses['DailyAnalysis']['id']);
											}		
										}
									}
									
									if (empty($frames) && $frame_key != 'date') {
										
										$analysis = $this->DailyAnalysis->find('all', array(
											'fields' => array('id'),
											'conditions' => array(
												'DailyAnalysis.type' => $propertkey,
												'DailyAnalysis.date' => $datekey,
												'DailyAnalysis.timeframe' => $frame_key
											),
											'recursive' => -1
										));

										if (!empty($analysis)) {
											foreach ($analysis as $analyses) {
												$this->DailyAnalysis->delete($analyses['DailyAnalysis']['id']);
											}		
										}
									}
								}	
							}
						}	
					}
				}
			}

			$this->Session->setFlash('Data updated to Daily Analysis.', 'flash_success');
			$this->redirect(array('action' => 'index', '?' => array('type' => $this->data['DailyAnalysis']['type'], 'date_to' => $this->data['DailyAnalysis']['date_to'], 'date_from' => $this->data['DailyAnalysis']['date_from'])));
		}
	}
	
	public function view() {
		$message ='';
		if (isset($this->request->query) && !empty($this->request->query)) {
			$this->data = $this->request->query;
		}
		if (isset($this->data['date']) && !empty($this->data['date'])) {
			$message .= "<b>".$this->data['date']. "</b>:<br/><br/>";
		
			$analysis = $this->DailyAnalysis->find('all', array(
				'conditions' => array(
					'DailyAnalysis.date' => date(DB_DATE, strtotime($this->data['date']))
				)
			));
			
			if (!empty($analysis)) {
				foreach ($analysis as $data) {
					$analysis_list[$data['DailyAnalysis']['type']][$data['DailyAnalysis']['timeframe']] = (isset($analysis_list[$data['DailyAnalysis']['type']][$data['DailyAnalysis']['timeframe']])) ? $analysis_list[$data['DailyAnalysis']['type']][$data['DailyAnalysis']['timeframe']] . ', ' . $data['DailyAnalysisProperty']['name'] : $data['DailyAnalysisProperty']['name'];
				}
			}
		}
		else {
			throw new NotFoundException(__('Daily Analysis Data Not Found.'));
		}
		$this->set(compact('analysis_list'));
	}
	
	public function post_slack() {
		
		// Send messages to slack
		$setting = $this->Setting->find('first', array(
			'conditions' => array(
				'Setting.name' => 'slack.dailyanalysis.webhook',
				'Setting.deleted' => false
			)
		));
		if (empty($setting)) {
			$this->Session->setFlash('Missing Slack channel settings.', 'flash_error');
			return $this->redirect(array('action' => 'index'));
		}
		$analysis_list = array();
		$conditions = array();
		$message = '';
		
		$max_analysis_date = $this->DailyAnalysis->find('first', array(
			'fields' => array('max(date) as maxdate')
		));

		if (!empty($max_analysis_date) && isset($max_analysis_date['0']['maxdate'])) {
			$message .= "*".$max_analysis_date['0']['maxdate']. "*:\n\n";
		
			$analysis = $this->DailyAnalysis->find('all', array(
				'conditions' => array(
					'DailyAnalysis.date' => $max_analysis_date['0']['maxdate']
				)
			));
			
			if (!empty($analysis)) {
				foreach ($analysis as $data) {
					$max_date = $data['DailyAnalysis']['date'];
					$analysis_list[$data['DailyAnalysis']['type']][$data['DailyAnalysis']['timeframe']] = (isset($analysis_list[$data['DailyAnalysis']['type']][$data['DailyAnalysis']['timeframe']])) ? $analysis_list[$data['DailyAnalysis']['type']][$data['DailyAnalysis']['timeframe']] . ', ' . $data['DailyAnalysisProperty']['name'] : $data['DailyAnalysisProperty']['name'];
				}
			}
			
			if (isset($analysis_list) && !empty($analysis_list)) {
				foreach ($analysis_list as $key => $list) {
					$high1 = array();
					$low1 = array();
					$counter_low = 0;
					$counter_high = 0;
					foreach ($list as $framekey => $frame) {
						if (strpos($framekey, 'high') or strpos($framekey, 'top')) {
							$high2 = explode(', ', $frame);
							if (empty($high1) && empty($counter_high)) {
								$high1 = explode(', ', $frame);
							}
							else {
								$high1 = array_intersect($high1, $high2);	
							}
							$counter_high++;
						}
						if (strpos($framekey, 'bottom') or strpos($framekey, 'low')) {
							$low2 = explode(', ', $frame);
							if (empty($low1) && empty($counter_low)) {
								$low1 = explode(', ', $frame);
							}
							else {
								$low1 = array_intersect($low1, $low2);	
							}
							$counter_low++;
						}
					}
					if (!empty($high1)) {
						$message .= "*".$key." Summary Highs:* ". implode(', ',$high1);
						$message .= "\n";
					}
					if (!empty($low1)) {
						$message .= "*".$key." Summary Lows:* ". implode(', ',$low1);
						$message .= "\n";
					}
					$message .= "\n";
				}
			}
			$message .= "<".HOSTNAME_WEB."/daily_analysis/view?date=".$max_analysis_date['0']['maxdate']."|view full data>";
		}
		
		$find = array('day-of-week', 'topbottom', 'monthly');
		$replace = array('Day of Week', 'Top Bottom', 'Monthly');
		$message = str_replace($find, $replace, $message);

		$http = new HttpSocket(array(
			'timeout' => '2',
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$http->post($setting['Setting']['value'], json_encode(array(
			'text' => $message,
			'username' => 'bernard'
		)));
		$this->Session->setFlash('Data posted to Slack Channel.', 'flash_success');
		return $this->redirect(array('action' => 'index'));
	}
}