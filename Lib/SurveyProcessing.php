<?php

class SurveyProcessing {

	public static function report_to_csv($visits, $extra_fields = array()) {
		$tz = new DateTimeZone('America/Los_Angeles');
		$header = array(
			"User ID",
			"Partner ID",
			"IP",
			"Result",
			"Link",
			"Hash",
			"Referrer",
			"Started",
			"Completed",
			"Time Spent"
		);
		if (!empty($extra_fields)) {
			if (in_array('address', $extra_fields)) {
				$extra_fields['address'] = 'Address';
				$extra_fields['address_line2'] = 'Address (Line 2)';
				$extra_fields['first_name'] = 'First Name (Address)';
				$extra_fields['last_name'] = 'Last Name (Address)';
				$extra_fields['city'] = 'City';
				$extra_fields['postal_code'] = 'Postal Code';
				$extra_fields['state'] = 'State';
				$extra_fields['country'] = 'Country';
				$extra_fields['county'] = 'County';
			}
			if (in_array('name', $extra_fields)) {
				$extra_fields['firstname'] = 'First Name';
				$extra_fields['lastname'] = 'Last Name';
			}
			$output[] = array_merge($header, $extra_fields);
		}

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
				$result = 'Complete';
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
				$visit['SurveyReport']['partner_id'],
				$visit['SurveyReport']['ip'],
				$result, 
				$visit['SurveyReport']['link'],
				$visit['SurveyReport']['hash'],
				$visit['SurveyReport']['referrer'],
				$visit['SurveyReport']['started'],
				$visit['SurveyReport']['completed'],
				$time
			);
			if (!empty($extra_fields)) {
				foreach ($extra_fields as $field_key => $field_val) {
					if ($field_key == 'address') {
						$field_key = 'address_line1';
					}
					$row[] = isset($visit['SurveyReport'][$field_key]) ? $visit['SurveyReport'][$field_key]: '';
				}
			}
			$output[] = $row;
		}
		return $output;
	}
	
	public static function link_users($survey_id) {
		App::import('Model', 'SurveyReport');
		$SurveyReport = new SurveyReport();
		App::import('Model', 'SurveyUserVisit');
		$SurveyUserVisit = new SurveyUserVisit();
		
		$visits = $SurveyReport->find('all', array(
			'conditions' => array(
				'SurveyReport.survey_id' => $survey_id
			), 
			'order' => array(
				'SurveyReport.started DESC'
			)
		)); 
		
		foreach ($visits as $visit) {
			if (!empty($visit['SurveyReport']['user_id'])) {
				continue;
			}
			$user_visit = $SurveyUserVisit->find('first', array(
				'conditions' => array(
					'SurveyUserVisit.survey_id' => $survey_id,
					'SurveyUserVisit.status' => $visit['SurveyReport']['result'],
					'SurveyUserVisit.ip' => $visit['SurveyReport']['ip']
				)
			));
			if ($user_visit) {
				$SurveyReport->create();
				$SurveyReport->save(array('SurveyReport' => array(
					'id' => $visit['SurveyReport']['id'],
					'user_id' => $user_visit['SurveyUserVisit']['user_id']
				)), true, array('user_id'));
			}
		}
	}
}