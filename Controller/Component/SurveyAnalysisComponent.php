<?php

App::uses('Component', 'Controller');
class SurveyAnalysisComponent extends Component {
	
	
	public function user_from_hash($respondent_id, $mv_partner = false) {
		if (!isset($this->SurveyVisit)) {
			App::import('Model', 'SurveyVisit');
			$this->SurveyVisit = new SurveyVisit;
		}
		
		$user_id = false;
		$partner_id = false;
		$partner_user_id = false;
		
		$survey_id = Utils::parse_project_id_from_hash($respondent_id);
		$row = $this->SurveyVisit->find('first', array(
			'fields' => array('partner_id', 'partner_user_id', 'partner_user_id'), 
			'conditions' => array(
				'SurveyVisit.survey_id' => $survey_id,
				'SurveyVisit.partner_user_id' => $respondent_id
			),
			'recursive' => -1
		));
		if (!$row) {
			$row = $this->SurveyVisit->find('first', array(
				'fields' => array('partner_id', 'partner_user_id', 'partner_user_id'), 
				'conditions' => array(
					'SurveyVisit.survey_id' => $survey_id,
					'SurveyVisit.hash' => $respondent_id
				),
				'recursive' => -1
			));
		}
		if ($row) {
			if ($mv_partner && $row['SurveyVisit']['partner_id'] == $mv_partner['Partner']['id']) {
				$partner_user_ids = explode('-', $row['SurveyVisit']['partner_user_id']); 
				$user_id = $partner_user_ids[1];
			}
			$partner_user_id = $row['SurveyVisit']['partner_user_id'];
			$partner_id = $row['SurveyVisit']['partner_id'];
		}
		if (!$row) {
			return false;
		}
		return array(
			'user_id' => $user_id, 
			'partner_user_id' => $partner_user_id, 
			'partner_id' => $partner_id
		);
	}
	
	public function link_users($project_id) {
		App::import('Model', 'SurveyReport'); 
		$this->SurveyReport = new SurveyReport;
		
		App::import('Model', 'SurveyUserVisit'); 
		$this->SurveyUserVisit = new SurveyUserVisit;
		
		// link users to complete report data		
		$visits = $this->SurveyReport->find('all', array(
			'conditions' => array(
				'SurveyReport.result' => SURVEY_COMPLETED, 
				'SurveyReport.survey_id' => $project_id,
				'SurveyReport.partner_id' => 43 // mintvine only
			), 
			'order' => array(
				'SurveyReport.started DESC'
			)
		));
		
		$changed = false;
		foreach ($visits as $key => $visit) {
			// if it's not a MV partner traffic, don't bother trying
			if ($visit['SurveyReport']['partner_id'] != 43) {
				continue;
			}
			if (!empty($visit['SurveyReport']['user_id'])) {
				continue;
			}
			$user_visit = $this->SurveyUserVisit->find('first', array(
				'conditions' => array(
					'SurveyUserVisit.survey_id' => $project_id,
					'SurveyUserVisit.status' => SURVEY_COMPLETED,
					'SurveyUserVisit.ip' => $visit['SurveyReport']['ip']
				)
			));
			if ($user_visit) {
				$this->SurveyReport->create();
				$this->SurveyReport->save(array('SurveyReport' => array(
					'id' => $visit['SurveyReport']['id'],
					'user_id' => $user_visit['SurveyUserVisit']['user_id']
				)), true, array('user_id'));
				$changed = true;
			}
		}
		return $changed ? $visits: $changed;
	}
	
	public function check_speed($survey, $visits) {
		$est_speed = $survey['Project']['est_length']; 
		$speeds = array();
		
		foreach ($visits as $key => $visit) {
			if (empty($visit['SurveyReport']['completed']) || empty($visit['SurveyReport']['started'])) {
				continue;
			}
			$diff = strtotime($visit['SurveyReport']['completed']) - strtotime($visit['SurveyReport']['started']);
			$minutes = round($diff / 60, 1); 
			if ($minutes <= $est_speed * 2) {
				$speeds[$key] = $minutes;
			}
		}
		
		$mean = array_sum($speeds) / count($speeds);
		$standard_deviation = Utils::sd($speeds); 
		foreach ($speeds as $key => $speed) {
			if ($speed < ($mean - $standard_deviation)) {
				$visits[$key]['SurveyReport']['flag'] = '1';
			}
		}
		return $visits;
	}
}
