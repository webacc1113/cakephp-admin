<?php

class ReportCsvTask extends Shell {
    public $uses = array('Project', 'SurveyVisit', 'SurveyUserVisit', 'Transaction', 'Partner', 'SurveyPartner');

	public function raw($filename, $visits) {
		$mv_partner = $this->Partner->find('first', array(
			'fields' => array('Partner.id'),
			'conditions' => array(
				'Partner.key' => array('mintvine'),
				'Partner.deleted' => false
			),
			'recursive' => -1
		));
		
		$tz = new DateTimeZone('America/Los_Angeles');
		$SURVEY_STATUSES = unserialize(SURVEY_STATUSES);
		$fp = fopen($filename, 'w');
		
		fputcsv($fp, array(
			'Partner', 
			'ID', 
			'User ID',
			'Type',
			'Note',
			'Link',
			'Hash',
			'IP Address',
			'Referrer',
			'Query String',
			'Result', 
			'Result ID',
			'Created', 
			'Modified', 
			'Server Info'
		));
		foreach ($visits as $visit) {
			
			$date = new DateTime($visit['SurveyVisit']['created']);
			$date->setTimeZone($tz);
			$visit['SurveyVisit']['created'] = $date->format('Y-m-d H:i:s');
			$date = new DateTime($visit['SurveyVisit']['modified']);
			$date->setTimeZone($tz);
			$visit['SurveyVisit']['modified'] = $date->format('Y-m-d H:i:s');
			fputcsv($fp, array(
				$visit['Partner']['partner_name'],
				$visit['SurveyVisit']['id'],
				($mv_partner['Partner']['id'] == $visit['SurveyVisit']['partner_id']) ? $visit['SurveyVisit']['user_id'] : $visit['SurveyVisit']['partner_user_id'],
				$SURVEY_STATUSES[$visit['SurveyVisit']['type']],
				$visit['SurveyVisit']['result_note'],
				$visit['SurveyVisit']['link'],
				$visit['SurveyVisit']['hash'],
				$visit['SurveyVisit']['ip'],
				$visit['SurveyVisit']['referrer'],
				$visit['SurveyVisit']['query_string'],
				!empty($visit['SurveyVisit']['result']) ? $SURVEY_STATUSES[$visit['SurveyVisit']['result']]: '',
				!empty($visit['SurveyVisit']['result_id']) ? $visit['SurveyVisit']['result_id']: '',
				$visit['SurveyVisit']['created'],
				$visit['SurveyVisit']['modified'],
				$visit['SurveyVisit']['info']
			));
		}
		fclose($fp);
	}
	
    public function report($visits, $survey_id, $partners = false, $client_hashes = false) {
		$mv_partner = $this->Partner->find('first', array(
			'fields' => array('Partner.id'),
			'conditions' => array(
				'Partner.key' => array('mintvine'),
				'Partner.deleted' => false
			),
			'recursive' => -1
		));
		
		$project = $this->Project->find('first', array(
			'fields' => array('id', 'router'),
			'conditions' => array(
				'Project.id' => $survey_id,
			),
		));
		
		$has_mintvine_partner = $this->SurveyPartner->find('first', array(
			'conditions' => array(
				'SurveyPartner.partner_id' => $mv_partner['Partner']['id'],
				'SurveyPartner.survey_id' => $project['Project']['id']
			),
			'recursive' => -1
		)); 
			
		$tz = new DateTimeZone('America/Los_Angeles');
		$output = "User ID,Partner ID,IP,Result,Note,Link,Hash,Referrer,Click Date,Completed,Time Spent,CPI($),User Payout($)";
		if (!$project['Project']['router'] && $has_mintvine_partner) {
			$output .= ",Missing Points";
			
			$transactions_list = array();
			$transactions = $this->Transaction->find('all', array(
				'fields' => array('Transaction.user_id', 'Transaction.type_id', 'Transaction.amount'),
				'conditions' => array(
					'Transaction.linked_to_id' => $survey_id,
					'Transaction.type_id' => array(
						TRANSACTION_SURVEY_NQ,
						TRANSACTION_MISSING_POINTS
					)
				),
				'recursive' => -1
			));
			foreach ($transactions as $key => $transaction) {
				$transactions_list[$transaction['Transaction']['user_id']] = $transaction['Transaction'];
			}
			
			$transactions = $transactions_list; 
		}
		
		$this->loadModel('ProjectRate');
		$project_rates = $this->ProjectRate->find('list', array(
			'fields' => array('ProjectRate.created', 'ProjectRate.client_rate'),
			'conditions' => array(
				'ProjectRate.project_id' => $survey_id
			),
		));

		$output.= chr(10);
		foreach ($visits as $visit) {
			$user_payout = $missing_points = $cpi = 0;
			$result = '';
			if (!empty($visit['SurveyReport']['started'])) {
				$date = new DateTime($visit['SurveyReport']['started']);
				$date->setTimeZone($tz);
				$visit['SurveyReport']['started'] = $date->format('Y-m-d H:i:s');
			}
			if (!empty($visit['SurveyReport']['completed'])) {
				$original_date = $visit['SurveyReport']['completed']; 
				$date = new DateTime($visit['SurveyReport']['completed']);
				$date->setTimeZone($tz);
				$visit['SurveyReport']['completed'] = $date->format('Y-m-d H:i:s');
			}
			if ($visit['SurveyReport']['result'] == SURVEY_CLICK) {
				$result = 'Pending';
			}
			elseif ($visit['SurveyReport']['result'] == SURVEY_DUPE) {
				$result = 'Dupe';
			}
			elseif ($visit['SurveyReport']['result'] == SURVEY_DUPE_FP) {
				$result = 'Fingerprint Dupe';
			}
			elseif ($visit['SurveyReport']['result'] == SURVEY_COMPLETED) {
				$cpi = ($visit['SurveyReport']['client_rate_cents'] > 0) ? round($visit['SurveyReport']['client_rate_cents'] / 100, 2) : 0;
				$user_payout = ($visit['SurveyReport']['user_payout_cents'] > 0) ? round($visit['SurveyReport']['user_payout_cents'] /100, 2) : 0;
				if ($client_hashes) {
					if (isset($client_hashes[strtolower($visit['SurveyReport']['hash'])])) {
						$result = 'Complete (Accepted)';
					}
					else {
						$result = 'Complete (Rejected)';
					}
				}
				else {
					$result = 'Complete';
				}
			}
			elseif ($visit['SurveyReport']['result'] == SURVEY_OVERQUOTA) {
				$result = 'Overquota';
			}
			elseif ($visit['SurveyReport']['result'] == SURVEY_OQ_INTERNAL) {
				$result = 'OQ (Internal)';
			}
			elseif ($visit['SurveyReport']['result'] == SURVEY_NQ) {
				$result = 'NQ';
			}
			elseif ($visit['SurveyReport']['result'] == SURVEY_INTERNAL_NQ) {
				$result = 'NQ (Internal)';
			}
			elseif ($visit['SurveyReport']['result'] == SURVEY_NQ_FRAUD) {
				$result = 'NQ (Fraud Failure)';
			}
			elseif ($visit['SurveyReport']['result'] == SURVEY_NQ_SPEED) {
				$result = 'NQ (Speeding)';
			}
			elseif ($visit['SurveyReport']['result'] == SURVEY_CUSTOM) {
				$result = 'Custom';
			}
			
			$time = '';
			if (!empty($visit['SurveyReport']['started']) && !empty($visit['SurveyReport']['completed'])) {
				$diff = strtotime($visit['SurveyReport']['completed']) - strtotime($visit['SurveyReport']['started']); 
				$time = round($diff / 60).' minutes';
			}
			
			if (!$project['Project']['router'] && $visit['SurveyReport']['partner_id'] == $mv_partner['Partner']['id'] && $visit['SurveyReport']['result'] != SURVEY_COMPLETED) {
				if (strpos($visit['SurveyReport']['partner_user_id'], '-') !== false) {
					list($nothing, $user_id) = explode('-', $visit['SurveyReport']['partner_user_id']);
				}
				else {
					$user_id = false; 
				}
				
				if ($user_id && isset($transactions[$user_id]) && $visit['SurveyReport']['partner_id'] == $mv_partner['Partner']['id']) {
					if ($transactions[$user_id]['type_id'] == TRANSACTION_SURVEY_NQ) {
						$user_payout = $transactions[$user_id]['amount'];
					}
					elseif ($transactions[$user_id]['type_id'] == TRANSACTION_MISSING_POINTS) {
						$missing_points = $transactions[$user_id]['amount'];
					}
				}
			}
			
			$row = array(
				($visit['SurveyReport']['partner_id'] == $mv_partner['Partner']['id']) ? $visit['SurveyReport']['user_id'] : $visit['SurveyReport']['partner_user_id'],
				$partners !== false ? $partners[$visit['SurveyReport']['partner_id']] : $visit['SurveyReport']['partner_id'],
				$visit['SurveyReport']['ip'],
				$result, 
				$visit['SurveyReport']['result_note'],
				$visit['SurveyReport']['link'],
				$visit['SurveyReport']['hash'],
				$visit['SurveyReport']['referrer'],
				$visit['SurveyReport']['started'],
				$visit['SurveyReport']['completed'],
				$time,
				number_format($cpi, 2),
				$user_payout,
			);
			
			
			if (!$project['Project']['router'] && $visit['SurveyReport']['partner_id'] == $mv_partner['Partner']['id']) {
				$row = array_merge($row, array($missing_points));
			}
			
			$output .= implode(',', $row);
			$output .= chr(10);
		}
		return $output;
    }
}