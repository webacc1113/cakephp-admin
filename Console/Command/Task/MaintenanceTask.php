<?php

class MaintenanceTask extends Shell {
    public $uses = array('Project', 'SurveyVisit', 'SurveyUserVisit', 'Transaction');

    public function execute($survey_id, $report_only) {
		$survey = $this->Project->findById($survey_id);
		$award = $survey['Project']['award'];
		$name = $survey['Project']['description'].' #'.$survey['Project']['id'];
		
		$visits = $this->SurveyVisit->find('all', array(
			'conditions' => array(
				'SurveyVisit.survey_id' => $survey_id,
				'SurveyVisit.type' => array(SURVEY_COMPLETED, SURVEY_NQ)
			)
		));
		$i = 0;
		$return = '--------------'."\n".'ANALYZING #'.$survey_id."\n";
		foreach ($visits as $visit) {
			$info = Utils::print_r_reverse($visit['SurveyVisit']['info']);
			$user_visit = $this->SurveyUserVisit->find('first', array(
				'contain' => array(
					'User'
				),
				'conditions' => array(
					'SurveyUserVisit.ip' => $visit['SurveyVisit']['ip'],
					'SurveyUserVisit.survey_id' => $survey_id,
					'SurveyUserVisit.status' => SURVEY_CLICK 
				)
			));
			if (!$user_visit || empty($user_visit['SurveyUserVisit']['user_id']) || empty($user_visit['User']['id'])) {
				continue;
			}
			$user_id = $user_visit['SurveyUserVisit']['user_id']; 
			$count = $this->Transaction->find('count', array(
				'conditions' => array(
					'Transaction.type_id' => TRANSACTION_SURVEY,
					'Transaction.user_id' => $user_id,
					'Transaction.linked_to_id' => $survey_id,
					'Transaction.deleted' => null,
				)
			));
			if ($count == 0) {
				$i++;
				if ($visit['SurveyVisit']['type'] == SURVEY_NQ) {
					$amount = 5;
					$name = 'Sorry - you didn\'t qualify for this survey.';
					$paid = true;
				}
				elseif ($visit['SurveyVisit']['type'] == SURVEY_COMPLETED) {
					$amount = $award;
					$name = 'Survey Completion - '.$name;
					$paid = false;
				}
				$return .= $i.': '."Awarding ".$amount." points to ".$user_id." for survey ".$name;
				if ($report_only) {
					$return .= "\n".$info['HTTP_USER_AGENT']."\n";
				}
				if (!$report_only) {
					$this->Transaction->create();
					$this->Transaction->save(array('Transaction' => array(
						'user_id' => $user_id,
						'type_id' => TRANSACTION_SURVEY,
						'name' => $name,
						'linked_to_id' => $survey_id,
						'linked_to_name' => $survey['Project']['survey_name'],
						'amount' => $amount,
						'status' => TRANSACTION_APPROVED,
						'paid' => $paid,
						'executed' => date(DB_DATETIME, mktime())
					))); 
					
					$this->SurveyUserVisit->create();
					$this->SurveyUserVisit->save(array(
						'id' => $user_visit['SurveyUserVisit']['id'],
						'redeemed' => true,
						'status' => $visit['SurveyVisit']['type']
					), true, array('status', 'redeemed'));
					$return .= '... DONE';
				}
				$return .= "\n";
			}
		}
		$return .= 'affected: '.$i."\n";
		$return .= 'total: '.count($visits)."\n";
		return $return;
    }
}