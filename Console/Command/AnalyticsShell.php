<?php
App::uses('CakeEmail', 'Network/Email');
App::uses('View', 'View');
App::import('Lib', 'Utilities');
App::uses('CakeResponse', 'Network');
App::uses('HttpSocket', 'Network/Http');

class AnalyticsShell extends AppShell {
	public $uses = array(); 
	public $tasks = array('AnalyticsCsv');
	
	function main() { 
	}	
	
	// provides data for analysis for polls; 
	function polls() {
		$models_to_import = array('PollUserAnswer', 'Transaction', 'AnalyticPoll', 'SurveyUserVisit', 'AnalyticReport', 'Setting');
		foreach ($models_to_import as $model_to_import) {
			App::import('Model', $model_to_import);
			$this->$model_to_import = new $model_to_import; 
		}
		
		if (!isset($this->args[0]) || !isset($this->args[1])) {
			$this->out('Both arguments are required: start and end date'); 
			return; 
		}
		if (isset($this->args[2])) {
			$report_id = $this->args[2];
		}
		$start_date = min(array($this->args[0], $this->args[1]));
		$end_date = max(array($this->args[0], $this->args[1]));
		$this->out('Ranging between '.$start_date.' to '.$end_date);
		
		// first get the min/max ids of the polls
		$min = $this->PollUserAnswer->find('first', array(
			'fields' => array('MIN(id) as min_id'),
			'conditions' => array(
				'PollUserAnswer.date_taken >=' => $start_date.' 00:00:00'
			)
		));
		if (!empty($min[0]['min_id'])) {
			$min_id = $min[0]['min_id'];
		}
		$max = $this->PollUserAnswer->find('first', array(
			'fields' => array('MAX(id) as min_id'),
			'conditions' => array(
				'PollUserAnswer.date_taken <=' => $end_date.' 23:59:59'
			)
		));
		if (!empty($max[0]['min_id'])) {
			$max_id = $max[0]['min_id'];
		}
		if (!isset($min_id) || !isset($max_id) || empty($min_id) || empty($max_id)) {
			$this->out('Could not find the correct range');
			return false; 
		}
		
		// get all the poll answers in this range for analysis
		$this->PollUserAnswer->bindModel(array(
			'belongsTo' => array(
				'User'
			)
		));
		$poll_user_answers = $this->PollUserAnswer->find('all', array(
			'conditions' => array(
				'PollUserAnswer.id >=' => $min_id,
				'PollUserAnswer.id <=' => $max_id,
				'User.hellbanned' => false
			),
			'fields' => array('PollUserAnswer.id', 'PollUserAnswer.user_id', 'PollUserAnswer.date_taken', 'PollUserAnswer.poll_id'),
			'order' => 'PollUserAnswer.id ASC',
			'contain' => array('User')
		));
		
		if (!$poll_user_answers) {
			$this->out('No data to select in this range');
			return false;
		}
		
		/* 
			For each poll answer, we must find the closest action that happened before the poll and then after the poll. 
			An action can be: 
			 * Survey click
			 * Transaction			
		*/
		$i = 1;
		$total = count($poll_user_answers);
		$this->out('Processing '.$total.' poll answers...');
		foreach ($poll_user_answers as $poll_user_answer) {
			// check to see a poll doesn't already exist for this..
			$analytic_poll = $this->AnalyticPoll->find('first', array(
				'conditions' => array(
					'AnalyticPoll.poll_user_answer_id' => $poll_user_answer['PollUserAnswer']['id']
				)
			));
			// skip writing the data files 
			if ($analytic_poll && !empty($analytic_poll['AnalyticPoll']['action_after'])) {
				continue;
			}
			$poll_timestamp = $poll_user_answer['PollUserAnswer']['date_taken'];
			
			// generate the 'before' action
			if (!$analytic_poll) {
				$survey_user_visit = $this->SurveyUserVisit->find('first', array(
					'conditions' => array(
						'SurveyUserVisit.user_id' => $poll_user_answer['PollUserAnswer']['user_id'],
						'SurveyUserVisit.created <=' => $poll_user_answer['PollUserAnswer']['date_taken']
					),
					'recursive' => -1,
					'fields' => array('SurveyUserVisit.id', 'SurveyUserVisit.created', 'SurveyUserVisit.survey_id'),
					'recursive' => -1,
					'order' => 'SurveyUserVisit.id DESC'
				));
				
				$transaction = $this->Transaction->find('first', array(
					'conditions' => array(
						'Transaction.user_id' => $poll_user_answer['PollUserAnswer']['user_id'],
						'Transaction.created <=' => $poll_user_answer['PollUserAnswer']['date_taken'],
						'Transaction.type_id <>' => TRANSACTION_POLL,
						'Transaction.deleted' => null,
					),
					'fields' => array('Transaction.id', 'Transaction.created', 'Transaction.name', 'Transaction.amount'),
					'order' => 'Transaction.id DESC'
				));
				
				if (!$transaction && !$survey_user_visit) {
					$action_before = null;
					$before_timestamp = null;
				}
				// transaction is closer
				elseif (($transaction && !$survey_user_visit) || ($transaction && $transaction['Transaction']['created'] > $survey_user_visit['SurveyUserVisit']['created'])) {
					$before_timestamp = $transaction['Transaction']['created'];
					$action_before = 'Transaction: '.$transaction['Transaction']['name'].' ('.$transaction['Transaction']['amount'].')';
				}
				//survey visit is more closer
				elseif (($survey_user_visit && !$transaction) || ($survey_user_visit && $survey_user_visit['SurveyUserVisit']['created'] > $transaction['Transaction']['created'])) {
					$before_timestamp = $survey_user_visit['SurveyUserVisit']['created'];
					$action_before = 'Survey Click: #'.$survey_user_visit['SurveyUserVisit']['survey_id'];
				}
			}
			
			// generate the "after" action
			if (!$analytic_poll || empty($analytic_poll['AnalyticPoll']['after_action'])) {
				$survey_user_visit = $this->SurveyUserVisit->find('first', array(
					'conditions' => array(
						'SurveyUserVisit.user_id' => $poll_user_answer['PollUserAnswer']['user_id'],
						'SurveyUserVisit.created >=' => $poll_user_answer['PollUserAnswer']['date_taken']
					),
					'recursive' => -1,
					'fields' => array('SurveyUserVisit.id', 'SurveyUserVisit.created', 'SurveyUserVisit.survey_id'),
					'order' => 'SurveyUserVisit.id ASC'
				));
				
				$poll_transaction = $this->Transaction->find('first', array(
					'conditions' => array(
						'Transaction.type_id' => TRANSACTION_POLL,
						'Transaction.linked_to_id' => $poll_user_answer['PollUserAnswer']['poll_id'],
						'Transaction.user_id' => $poll_user_answer['PollUserAnswer']['user_id'],
						'Transaction.deleted' => null,
					),
					'recursive' => -1,
					'fields' => array('Transaction.id')
				));
				
				$transaction = $this->Transaction->find('first', array(
					'conditions' => array(
						'Transaction.user_id' => $poll_user_answer['PollUserAnswer']['user_id'],
						'Transaction.created >=' => $poll_user_answer['PollUserAnswer']['date_taken'],
						'Transaction.type_id <>' => TRANSACTION_POLL_STREAK,
						'Transaction.id <>' => $poll_transaction['Transaction']['id'],
						'Transaction.deleted' => null,
					),
					'fields' => array('Transaction.id', 'Transaction.created', 'Transaction.name', 'Transaction.amount'),
					'order' => 'Transaction.id ASC'
				));
				
				// transaction is closer
				if (!$transaction && !$survey_user_visit) {
					$after_timestamp = null;
					$action_after = null; 
				}
				elseif (($transaction && !$survey_user_visit) || ($transaction && $transaction['Transaction']['created'] < $survey_user_visit['SurveyUserVisit']['created'])) {
					$after_timestamp = $transaction['Transaction']['created'];
					$action_after = 'Transaction: '.$transaction['Transaction']['name'].' ('.$transaction['Transaction']['amount'].')';
				}
				//survey visit is more closer
				elseif (($survey_user_visit && !$transaction) || ($survey_user_visit && $survey_user_visit['SurveyUserVisit']['created'] < $transaction['Transaction']['created'])) {
					$after_timestamp = $survey_user_visit['SurveyUserVisit']['created'];
					$action_after = 'Survey Click: #'.$survey_user_visit['SurveyUserVisit']['survey_id'];
				}
			}
			
			if ($analytic_poll) {
				if (!isset($action_before)) {
					$action_before = $analytic_poll['AnalyticPoll']['action_before'];
				}
				if (!isset($action_after)) {
					$action_after = $analytic_poll['AnalyticPoll']['action_after'];
				}
				if (!isset($before_timestamp)) {
					$before_timestamp = $analytic_poll['AnalyticPoll']['before_timestamp'];
				}
				if (!isset($after_timestamp)) {
					$after_timestamp = $analytic_poll['AnalyticPoll']['after_timestamp'];
				}
			}
			$save_data = array('AnalyticPoll' => array(
				'user_id' => $poll_user_answer['PollUserAnswer']['user_id'],
				'poll_id' => $poll_user_answer['PollUserAnswer']['poll_id'],
				'poll_user_answer_id' => $poll_user_answer['PollUserAnswer']['id'],
				'action_before' => $action_before,
				'action_after' => $action_after,
				'before_timestamp' => $before_timestamp,
				'after_timestamp' => $after_timestamp,
				'poll_timestamp' => $poll_timestamp,
			));
			if ($analytic_poll) {
				$save_data['AnalyticPoll']['id'] = $analytic_poll['AnalyticPoll']['id']; 
			}
			$analytic_poll = $save_data; 
			
			$this->AnalyticPoll->create();
			$this->AnalyticPoll->save($analytic_poll);
			$pct = round($i / $total * 100);
			$this->out($i.' / ' .$total.' ('.$pct.'%)');
			$i++;
		}
		
		// retrieve the data and create a CSV
		$analytic_polls = $this->AnalyticPoll->find('all', array(
			'conditions' => array(
				'AnalyticPoll.poll_timestamp >=' => $start_date.' 00:00:00',
				'AnalyticPoll.poll_timestamp <=' => $end_date.' 23:59:59'
			)
		));
		
		if (!file_exists(WWW_ROOT.'files/analytics')) {
			mkdir(WWW_ROOT.'files/analytics', 0755); 
		}
		$filename = WWW_ROOT.'files/analytics/polls_'.$this->args[0].'-'.$this->args[1].'.csv';		
		$this->out('Writing '.$filename); 
		$this->AnalyticsCsv->polls($filename, $analytic_polls);
		
		if (isset($report_id)) {
			CakePlugin::load('Uploader');
			App::import('Vendor', 'Uploader.S3');
			$settings = $this->Setting->find('list', array(
				'fields' => array('name', 'value'),
				'conditions' => array(
					'Setting.name' => array(
						's3.access',
						's3.secret',
						's3.bucket',
						's3.host'
					),
					'Setting.deleted' => false
				)
			));
			
			$filename = 'polls_'.$this->args[0].'-'.$this->args[1].'.csv';
			$file_dir_path = 'files/analytics/' . $filename;
			$file = WWW_ROOT . $file_dir_path;
			$aws_filename = $file_dir_path;
			echo 'Writing to S3 '.$aws_filename.' from '.$file."\n";
			$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);	
			$headers = array(
				'Content-Disposition' => 'attachment; filename='.$filename.'.csv'
			);
			if ($S3->putObject($S3->inputFile($file), $settings['s3.bucket'] , $aws_filename, S3::ACL_PRIVATE, array(), $headers)) {
					$this->AnalyticReport->create();
					$this->AnalyticReport->save(array('AnalyticReport' => array(
						'id' => $report_id,
						'path' => 'files/analytics/polls_'.$this->args[0].'-'.$this->args[1].'.csv',
						'status' => 'complete'
					)), false, array('path', 'status'));	
				//unlink($file);
			}
		}		
		
		$this->out('Complete');
	}
}