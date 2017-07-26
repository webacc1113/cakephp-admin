<?php

App::uses('AppController', 'Controller');

class LucidCodesController extends AppController {
	
	public $uses = array('LucidCode');

	public function beforeFilter() {
		parent::beforeFilter();
	}

	function index() {
		if (isset($this->request->query['project_id'])) {
			$conditions = array();
			if (!empty($this->request->query['project_id'])) {
				$conditions['project_id'] = $this->request->query['project_id'];
			}
			
			if (!empty($this->request->query['user_id'])) {
				$conditions['user_id'] = $this->request->query['user_id'];
			}
			
			if (!empty($this->request->query['date_from'])) {
				$date_from = date(DB_DATE, strtotime($this->request->query['date_from']));
				$conditions['created >='] = $date_from.' 00:00:00';
			}
			
			if (!empty($this->request->query['date_from'])) {
				$date_to = date(DB_DATE, strtotime($this->request->query['date_to']));
				$conditions['created <='] = $date_to.' 23:59:59';
			}
			
			
			if (empty($conditions)) {
				$this->Session->setFlash('Please add filters', 'flash_error');
			}
			else {
				$lucid_codes = $this->LucidCode->find('all', array(
					'conditions' => $conditions,
					'recursive' => -1,
				));
				if (empty($lucid_codes)) {
					$this->Session->setFlash('Records not found, please change the filters', 'flash_error');
				}
				else {
					$statuses = unserialize(SURVEY_STATUSES);
					$fields = array(
						'project_id',
						'user_id',
						'result',
						'status',
						'bidincidence',
						'rid',
						'respondentid',
						'rsfn',
						'tsfn',
						'clientip',
						'country',
						'domain',
						'fpf1',
						'fpf2',
						'fpf3',
						'fpf4',
						'fpf5',
						'fpf6',
						'fraudscore',
						'geoip',
						'isnew',
						'oldid',
						'oldiddate',
						'rvid',
						'rvidscore',
						'termedqualificationid',
						'termedquotaid',
						'timeinsurvey',
						'truesamplesourceid',
						'truesamplerid',
						'created',
					);
					$csv_rows = array($fields);
					foreach ($lucid_codes as $lucid_code) {
						$row = array();
						foreach ($fields as $field) {
							if ($field == 'result' && isset($statuses[$lucid_code['LucidCode'][$field]])) {
								$row[] = $statuses[$lucid_code['LucidCode'][$field]];
								continue;
							}
							
							$row[] = $lucid_code['LucidCode'][$field];
						}
						
						$csv_rows[] = $row;
					}

					$filename = 'lucid-codes-'. time() . '.csv';
					$csv_file = fopen('php://output', 'w');

					header('Content-type: application/csv');
					header('Content-Disposition: attachment; filename="' . $filename . '"');

					// Each iteration of this while loop will be a row in your .csv file where each field corresponds to the heading of the column
					foreach ($csv_rows as $row) {
						fputcsv($csv_file, $row, ',', '"');
					}

					fclose($csv_file);
					$this->autoRender = false;
					$this->layout = false;
					$this->render(false);
					return;
				}
			}
		}
	}
	
}
