<?php

class AnalyticsCsvTask extends Shell {
    public $uses = array('Project', 'SurveyVisit', 'SurveyUserVisit', 'Transaction');

	public function polls($filename, $analytic_polls) {
		$fp = fopen($filename, 'w');
		
		fputcsv($fp, array(
			'User ID', 
			'Poll ID', 
			'Poll Timestamp', 
			'Before Action',
			'Before Timestamp',
			'After Action',
			'After Timestamp',
			'Diff in Minutes (Before)',
			'Diff in Minutes (After)',
		));
		foreach ($analytic_polls as $analytic_poll) {
			$diff_before = $diff_after = null;
			
			if (!empty($analytic_poll['AnalyticPoll']['action_after'])) {
				$diff_after = round(((strtotime($analytic_poll['AnalyticPoll']['after_timestamp']) - strtotime($analytic_poll['AnalyticPoll']['poll_timestamp'])) / 60));
			}
			if (!empty($analytic_poll['AnalyticPoll']['action_before'])) {
				$diff_before = round(((strtotime($analytic_poll['AnalyticPoll']['poll_timestamp']) - strtotime($analytic_poll['AnalyticPoll']['before_timestamp'])) / 60));
			}
			fputcsv($fp, array(
				$analytic_poll['AnalyticPoll']['user_id'],
				$analytic_poll['AnalyticPoll']['poll_id'],
				$analytic_poll['AnalyticPoll']['poll_timestamp'],
				$analytic_poll['AnalyticPoll']['action_before'],
				$analytic_poll['AnalyticPoll']['before_timestamp'],
				$analytic_poll['AnalyticPoll']['action_after'],
				$analytic_poll['AnalyticPoll']['after_timestamp'],
				$diff_before,
				$diff_after
			));
		}
		fclose($fp);
	}
	
    public function report($visits, $partners = false, $client_hashes = false) {
		$tz = new DateTimeZone('America/Los_Angeles');
		$output = "User ID,Partner ID,IP,Result,Link,Hash,Referrer,Started,Completed,Time Spent";		
		$output.= chr(10);
		foreach ($visits as $visit) {
			$result = '';
			if (!empty($visit['SurveyReport']['started'])) {
				$date = new DateTime($visit['SurveyReport']['started']);
				$date->setTimeZone($tz);
				$visit['SurveyReport']['started'] = $date->format('Y-m-d H:i:s');
			}
			if (!empty($visit['SurveyReport']['completed'])) {
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
				if ($client_hashes) {
					if (isset($client_hashes[$visit['SurveyReport']['hash']])) {
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
			
			$time = '';
			if (!empty($visit['SurveyReport']['started']) && !empty($visit['SurveyReport']['completed'])) {
				$diff = strtotime($visit['SurveyReport']['completed']) - strtotime($visit['SurveyReport']['started']); 
				$time = round($diff / 60).' minutes';
			}
			$row = array(
				$visit['SurveyReport']['partner_user_id'],
				$partners !== false ? $partners[$visit['SurveyReport']['partner_id']] : $visit['SurveyReport']['partner_id'],
				$visit['SurveyReport']['ip'],
				$result, 
				$visit['SurveyReport']['link'],
				$visit['SurveyReport']['hash'],
				$visit['SurveyReport']['referrer'],
				$visit['SurveyReport']['started'],
				$visit['SurveyReport']['completed'],
				$time
			);
			$output .= implode(',', $row);
			$output .= chr(10);
		}
		return $output;
    }
}