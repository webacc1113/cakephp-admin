<?php

class ReconcilePrecisionTask extends Shell {
	// initial cleanup of file
	function cleanFile($data) {
		App::import('Model', 'Partner');
		$this->Partner = new Partner;

		$partner = $this->Partner->find('first', array(
			'conditions' => array(
				'Partner.key' => array('mintvine'),
				'Partner.deleted' => false
			),
			'fields' => array('Partner.id')
		));
		$this->partner_id = $partner['Partner']['id']; //mintvine partner 
		
		$header = array_shift($data); // remove header
		$indexes = array();
		$indexes['precision_project_id'] = array_search('Project Id', $header);
		$indexes['date'] = array_search('CompleteDate', $header);
		$indexes['user_id'] = array_search('External Memeber Id', $header);
		$this->indexes = $indexes;
		foreach ($indexes as $val) {
			if ($val === false) {
				return false;
			}
		}
		
		foreach ($data as $key => $val) {
			$has_values = false;
			foreach ($val as $k => $v) {
				if (!empty($v)) {
					$has_values = true;
					break;
				}
			}
			if (!$has_values) {
				unset($data[$key]); 
			}
		}
		
		return $data;
	}
	
	public function find_transaction($reconciliation_row) {
		App::import('Model', 'Transaction');
		$this->Transaction = new Transaction;
		$transaction = $this->Transaction->find('first', array(
			'fields' => array('Transaction.id'),
			'conditions' => array(
				'Transaction.type_id' => array(TRANSACTION_SURVEY, TRANSACTION_MISSING_POINTS),
				'Transaction.linked_to_id' => $reconciliation_row['survey_id'],
				'Transaction.user_id' => $reconciliation_row['user_id'],
				'Transaction.deleted' => null,
			)
		));
		if (!$transaction) {
			return false;
		}
		
		return $transaction['Transaction']['id'];
	}
	
	function getMinMaxDates($dates, $timestamp) {
		if (empty($timestamp) || $timestamp == '0000-00-00 00:00:00') {
			return $dates;
		}
		
		$timestamp = strtotime($timestamp);
		if (empty($dates['min'])) {
			$dates['min'] = $timestamp; 
		}
		
		if (empty($dates['max'])) {
			$dates['max'] = $timestamp; 
		}
		
		if ($dates['min'] > $timestamp) {
			$dates['min'] = $timestamp; 
		}
		
		if ($dates['max'] < $timestamp) {
			$dates['max'] = $timestamp; 
		}
			
		return $dates;
	}
	
	function parseCsvRow($row) {
		$project_id = '';
		$hash = '';
		if (empty($row[$this->indexes['date']])) {
			$date = '0000-00-00';
		}
		else {
			$date = date(DB_DATE, strtotime($row[$this->indexes['date']]));
		}
		
		$models_to_load = array('PrecisionProject', 'SurveyVisit', 'QueryProfile');
		foreach ($models_to_load as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		$query_profile = $this->QueryProfile->find('first', array(
			'fields' => array('QueryProfile.country'),
			'conditions' => array(
				'QueryProfile.user_id' => $row[$this->indexes['user_id']]
			)
		));
		if ($query_profile) {

			// we have multiple MV projects per Precsion project (as we create separate MV project for each country)
			$precision_project = $this->PrecisionProject->find('first', array(
				'conditions' => array(
					'PrecisionProject.precision_project_id' => $row[$this->indexes['precision_project_id']],
					'PrecisionProject.country' => $query_profile['QueryProfile']['country'],
				),
				'fields' => array('PrecisionProject.project_id'),
				'recursive' => -1
			));
			if ($precision_project) {
				$project_id = $precision_project['PrecisionProject']['project_id'];
				if ($date != '0000-00-00') {
					$survey_visit_complete = $this->SurveyVisit->find('first', array(
						'fields' => array('SurveyVisit.id', 'SurveyVisit.hash'),
						'conditions' => array(
							'SurveyVisit.partner_user_id LIKE ' => $project_id.'-'.$row[$this->indexes['user_id']].'-%',
							'SurveyVisit.type' => array(SURVEY_COMPLETED, SURVEY_DUPE), // in case of dupe in endController, we still pay the user, this can be a hidden complete
							'SurveyVisit.survey_id' => $project_id,
							'SurveyVisit.partner_id' => $this->partner_id,
							'SurveyVisit.created >=' => date(DB_DATETIME, strtotime($date.' 00:00:00') - 600),
							'SurveyVisit.created <=' => date(DB_DATETIME, strtotime($date) + (60 * 60 * 31)), // since precision is providing only date and its timezone is UTC -7 so we compare on 31 horus interval
						),
						'recursive' => -1
					));

					if ($survey_visit_complete) {
						$hash = $survey_visit_complete['SurveyVisit']['hash'];
					}
				}
			}
		}
		
		return array(
			'survey_id' => $project_id,
			'user_id' => $row[$this->indexes['user_id']],
			'hash' => $hash,
			'timestamp' => $date. ' 00:00:00',
			'precision_project_id' => $row[$this->indexes['precision_project_id']]
		);
	}
}