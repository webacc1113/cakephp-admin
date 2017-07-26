<?php
App::uses('CakeEmail', 'Network/Email');
App::uses('View', 'View');
App::import('Lib', 'Utilities');
App::uses('CakeResponse', 'Network');
App::uses('HttpSocket', 'Network/Http');

class QualificationsShell extends AppShell {
	public $uses = array('Question', 'Answer', 'QuestionText', 'AnswerText', 'Setting');
	
	function main() { 
	}	
	
	// these are generated and should be stored in files/qualifications/lucid
	public function import_lucid() {
		$files = array(
			'countries.csv', 
			'questions.csv',
			'answers.csv', 
			'question_texts.csv',
			'answer_texts.csv',
		); 
		
		$countries = array(); // contains key => val pairs for the lucid code for country and the iso codes
		$questions = array();
		$answers = array();
		$question_texts = array();
		$answer_texts = array();
		
		// csv ids to db ids
		$question_lookups = $answer_lookups = array();
		foreach ($files as $file) {
			$file_to_import = APP.'Console/Command/files/lucid/'.$file;
			$this->out('Importing '.$file_to_import);
			$csv_rows = Utils::csv_to_array($file_to_import);
			
			if ($file == 'countries.csv') {
				// define the columns that contain the header keys for lookup purposes
				$keys = array(
					'code' => array_search('id', $csv_rows[0]),
					'country' => array_search('name', $csv_rows[0])
				);
				unset($csv_rows[0]); // header
				foreach ($csv_rows as $csv_row) {
					$countries[$csv_row[$keys['code']]] = $csv_row[$keys['country']]; 
				}
			}
			elseif ($file == 'questions.csv') {
				$keys = array(
					'csv_id' => array_search('id', $csv_rows[0]),
					'partner_question_id' => array_search('partner_question_id', $csv_rows[0]),
					'question' => array_search('partner_question_name', $csv_rows[0]),
					'question_type' => array_search('question_type', $csv_rows[0]),
					'logic_group' => array_search('logic_group', $csv_rows[0]),
					'order' => array_search('order', $csv_rows[0]),
					'skipped_answer_id' => array_search('skipped_answer_id', $csv_rows[0]),
					'ignore' => array_search('ignore', $csv_rows[0]),
					'behavior' => array_search('behavior', $csv_rows[0]),
				);
				unset($csv_rows[0]); // header
				foreach ($csv_rows as $csv_row) {
					// get rid of literal NULL values
					foreach ($csv_row as $key => $val) {
						if ($val == 'NULL') {
							$csv_row[$key] = null;
						}
					}
					$question = $this->Question->find('first', array(
						'conditions' => array(
							'Question.partner' => 'lucid',
							'Question.partner_question_id' => $csv_row[$keys['partner_question_id']]
						)
					));
					$data = array('Question' => array(
						'partner' => 'lucid',
						'partner_question_id' => $csv_row[$keys['partner_question_id']], 
						'question' => $csv_row[$keys['question']], 
						'question_type' => $csv_row[$keys['question_type']], 
						'logic_group' => isset($csv_row[$keys['logic_group']]) ? $csv_row[$keys['logic_group']]: null, 
						'order' => isset($csv_row[$keys['order']]) ? $csv_row[$keys['order']]: null, 	
						'skipped_answer_id' => isset($csv_row[$keys['skipped_answer_id']]) ? $csv_row[$keys['skipped_answer_id']]: null, 	
						'ignore' => isset($csv_row[$keys['ignore']]) ? $csv_row[$keys['ignore']]: '0',
						'behavior' => isset($csv_row[$keys['behavior']]) ? $csv_row[$keys['behavior']]: null, 	
					)); 
					if ($question) {
						$data['Question']['id'] = $question['Question']['id'];
					}
					$questionSource = $this->Question->getDataSource();
					$questionSource->begin();
					$this->Question->create();
					$this->Question->save($data); 
					if (!$question) {
						$question_id = $this->Question->getInsertId();
						$questionSource->commit();
					}
					else {
						$question_id = $question['Question']['id'];
						$questionSource->commit();
					}
					
					$question_lookups[$csv_row[$keys['csv_id']]] = $question_id;
				}
			}
			elseif ($file == 'answers.csv') {
				$keys = array(
					'csv_id' => array_search('id', $csv_rows[0]),
					'csv_question_id' => array_search('question_id', $csv_rows[0]),
					'partner_answer_id' => array_search('partner_answer_id', $csv_rows[0]),
					'jump_to_question_id' => array_search('jump_to_question_id', $csv_rows[0]),
					'order' => array_search('order', $csv_rows[0])
				);

				unset($csv_rows[0]); // header
				foreach ($csv_rows as $csv_row) {
					$question_id = $question_lookups[$csv_row[$keys['csv_question_id']]]; 
					$answer = $this->Answer->find('first', array(
						'conditions' => array(
							'Answer.question_id' => $question_id,
							'Answer.partner_answer_id' => isset($csv_row[$keys['partner_answer_id']]) ? $csv_row[$keys['partner_answer_id']]: null
						)
					));
					
					$data = array('Answer' => array(
						'question_id' => $question_id,
						'partner_answer_id' => isset($csv_row[$keys['partner_answer_id']]) ? $csv_row[$keys['partner_answer_id']]: null, 
						'jump_to_question_id' => isset($keys['jump_to_question_id']) && isset($csv_row[$keys['jump_to_question_id']]) ? $csv_row[$keys['jump_to_question_id']]: null, 
						'order' => $csv_row[$keys['order']], 
					)); 
					if ($answer) {
						$data['Answer']['id'] = $answer['Answer']['id'];
					}
					
					$answerSource = $this->Answer->getDataSource();
					$answerSource->begin();
					$this->Answer->create();
					$this->Answer->save($data); 
					if (!$answer) {
						$answer_id = $this->Answer->getInsertId();
						$answerSource->commit();
					}
					else {
						$answerSource->commit();
						$answer_id = $answer['Answer']['id'];
					}
					$answer_lookups[$csv_row[$keys['csv_id']]] = $answer_id;
				}
				
			}
			elseif ($file == 'question_texts.csv') {
				$keys = array(
					'csv_question_id' => array_search('question_id', $csv_rows[0]),
					'csv_country_id' => array_search('country_id', $csv_rows[0]),
					'text' => array_search('text', $csv_rows[0])
				);
				unset($csv_rows[0]); // header
				foreach ($csv_rows as $csv_row) {
					$country = $countries[$csv_row[$keys['csv_country_id']]]; 
					$question_id = $question_lookups[$csv_row[$keys['csv_question_id']]]; 

					$question_text = $this->QuestionText->find('first', array(
						'conditions' => array(
							'QuestionText.question_id' => $question_id,
							'QuestionText.country' => $country
						)
					));
					$data = array('QuestionText' => array(
						'question_id' => $question_id,
						'country' => $country, 
						'text' => isset($csv_row[$keys['text']]) ? $csv_row[$keys['text']]: null, 
					)); 
					if ($question_text) {
						$data['QuestionText']['id'] = $question_text['QuestionText']['id'];
					}
					$this->QuestionText->create();
					$this->QuestionText->save($data); 
				}				
			}
			elseif ($file == 'answer_texts.csv') {
				$keys = array(
					'csv_answer_id' => array_search('answer_id', $csv_rows[0]),
					'csv_country_id' => array_search('country_id', $csv_rows[0]),
					'text' => array_search('text', $csv_rows[0])
				);
				unset($csv_rows[0]); // header
				foreach ($csv_rows as $csv_row) {
					$country = $countries[$csv_row[$keys['csv_country_id']]]; 
					$answer_id = $answer_lookups[$csv_row[$keys['csv_answer_id']]]; 

					$answer_text = $this->AnswerText->find('first', array(
						'conditions' => array(
							'AnswerText.answer_id' => $answer_id,
							'AnswerText.country' => $country
						)
					));
					$data = array('AnswerText' => array(
						'answer_id' => $answer_id,
						'country' => $country, 
						'text' => isset($csv_row[$keys['text']]) ? $csv_row[$keys['text']]: '0', // has to do with type errors; 0 is being interpreted wrong 
					)); 
					if ($answer_text) {
						$data['AnswerText']['id'] = $answer_text['AnswerText']['id'];
					}
					$this->AnswerText->create();
					$this->AnswerText->save($data); 
				}
			}
		}
	}
	
	public function export_questions_to_qe2() {
		if (!isset($this->args[0])) {
			$this->out('ERROR: Partner missing');
			return false;
		}

		$log_file = 'export.questions.qe2';
		$required_settings = array(
			'hostname.qe',
			'qe.mintvine.username',
			'qe.mintvine.password',
			'slack.questions.webhook',
		);
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => $required_settings,
				'Setting.deleted' => false
			),
			'recursive' => -1
		));
		if (count($settings) != count($required_settings)) {
			$this->out('ERROR: Missing required settings');
			return;
		}

		$HttpQe = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false
		));
		$HttpQe->configAuth('Basic', $settings['qe.mintvine.username'], $settings['qe.mintvine.password']);
		$this->Question->bindModel(array('hasMany' => array(
			'QuestionText' => array(
				'order' => array(
					'QuestionText.country' => 'desc' // get the US questionText at 0 index 
				)
			)
		)));
		$this->Answer->bindModel(array('hasMany' => array(
			'AnswerText' => array(
				'order' => array(
					'AnswerText.country' => 'desc' // get the US answerText at 0 index
				)
			)
		)), false);
		$conditions = array(
			'Question.partner' => $this->args[0],
			'Question.ignore' => false,
			'Question.staging' => false,
			'Question.deprecated' => false,
		);
		if (isset($this->args[1])) {
			$conditions['Question.id'] = $this->args[1];
		}
		
		$questions = $this->Question->find('all', array(
			'conditions' => $conditions,
			'contain' => array(
				'QuestionText'
			)
		));
		if (!$questions) {
			$this->out('There are no questions to process');
			return;
		}
		
		try {
			$qe_response = $HttpQe->get($settings['hostname.qe'] . "/questions?partner=" . $this->args[0] . "&answers=true", array(), array(
				'header' => array('Content-Type' => 'application/json')
			));
		} 
		catch (Exception $ex) {
			$this->out('Unexpected api response, try again please.');
			return;
		}
		
		if ($qe_response->code != 200) {
			$this->out('Problem getting QE questions: code:'. $qe_response->code);
			return;
		}
		
		$this->out('Total Questions: '.count($questions)); 
		$qe_questions = $mv_questions = array();
		foreach ($questions as $question) {
			$mv_questions[$question['Question']['partner_question_id']] = $question;
		}
		
		$qe_response = json_decode($qe_response, true);
		if ($qe_response) {
			foreach ($qe_response as $qe_question) {
				$partner_question_id = $qe_question['partner_question_id'];
				$qe_questions[$partner_question_id] = $qe_question;
				if (!isset($mv_questions[$partner_question_id])) {
					$message = 'Question not found (or is inactive) in Mintvine. Partner: '.$this->args[0].', partner_question_id : ' . $partner_question_id . ' - ' . $qe_question['question'] . "\n";
					CakeLog::write($log_file, $message);
					$this->out($message);
				}
			}
		}
		
		$i = 0;
		foreach ($mv_questions as $question) {
			if (empty($question['QuestionText'])) {
				continue;
			}
				
			$partner_question_id = $question['Question']['partner_question_id'];
			$post_question = (!isset($qe_questions[$partner_question_id]) && is_null($question['Question']['last_exported'])) ? true : false;
			if ($post_question) {
				$this->out('Exporting question ' . $question['Question']['question']. '('. $partner_question_id. ')');
				$qe_post_data = array(
					'partner' => $question['Question']['partner'],
					'partner_question_id' => $question['Question']['partner_question_id'],
					'question_type' => $question['Question']['question_type'],
					'question_text' => empty($question['QuestionText'][0]['cp_text']) ? $question['QuestionText'][0]['text'] : $question['QuestionText'][0]['cp_text'],
					'behavior' => $question['Question']['behavior'],
				);
				if (!defined('IS_DEV_INSTANCE') || IS_DEV_INSTANCE === false) {
					try {
						$response_qe = $HttpQe->post($settings['hostname.qe'] . "/questions", json_encode($qe_post_data), array(
							'header' => array('Content-Type' => 'application/json')
						));
						if ($response_qe->code == 201) {
							$i++;
							$msg = "A new question saved to QE for " . $question['Question']['partner'] . ":";
							$msg .= "\nID: " . $question['Question']['partner_question_id'];
							$msg .= "\nName: " . $question['Question']['question'];
							Utils::slack_alert($settings['slack.questions.webhook'], $msg);
							$this->out($msg);
						}
						elseif ($response_qe->code != 409) {
							$msg = "Unexpected response code for " . $question['Question']['partner'] . ": " . $response_qe->code;
							$msg .= "\nID: " . $question['Question']['partner_question_id'];
							$msg .= "\nName: " . $question['Question']['question'];
							$msg .= "\nCode: " . $response_qe->code;
							Utils::slack_alert($settings['slack.questions.webhook'], $msg);
							$this->out($msg);
						}
					} catch (Exception $ex) {
						$msg = "Failed to save following question to QE for " . $question['Question']['partner'] . "";
						$msg .= "\nID: " . $question['Question']['partner_question_id'];
						$msg .= "\nName: " . $question['Question']['question'];
						$msg .= "\n\nException: " . $ex->getMessage();
						Utils::slack_alert($settings['slack.questions.webhook'], $msg);
						$this->out($msg);
					}
				}
			}
			
			//Process answers
			$answers = $this->Answer->find('all', array(
				'conditions' => array(
					'Answer.question_id' => $question['Question']['id'],
				),
				'contain' => array(
					'AnswerText'
				)
			));
			if (!$answers) {
				$this->Question->create();
				$this->Question->save(array('Question' => array(
					'id' => $question['Question']['id'],
					'last_exported' => date(DB_DATETIME),
				)), true, array('last_exported'));
				continue;
			}
			
			$mv_answers = $qe_answers = array();
			foreach ($answers as $key => $answer) {
				$mv_answers[$answer['Answer']['partner_answer_id']] = $answer;
			}
			
			$total_answers = count($answers); 
			$this->out("\t".'Exporting '.$total_answers.' Answers'); 
			$j = 0; 
			if (!empty($qe_questions[$partner_question_id]['answers'])) {
				foreach ($qe_questions[$partner_question_id]['answers'] as $qe_answer) {
					$partner_answer_id = $qe_answer['partner_answer_id'];
					$qe_answers[$partner_answer_id] = $qe_answer;
					if (!isset($mv_answers[$partner_answer_id])) {
						$message = 'Answer not found in Mintvine. Partner: '.$this->args[0].', partner_answer_id : ' . $partner_answer_id . ' - ' . $qe_answer['answer'] . "\n";
						CakeLog::write($log_file, $message);
					}
				}
			}

			foreach ($answers as $answer) {
				$j++;
				$this->out("\t".$j.'/'.$total_answers); 
				$partner_answer_id = $answer['Answer']['partner_answer_id'];
				if (empty($answer['AnswerText'])) {
					continue;
				}
				
				$post_answer = (!isset($qe_answers[$partner_answer_id])) ? true : false;
				if ($post_answer) {
					$qe_post_data = array(
						'partner' => $question['Question']['partner'],
						'partner_question_id' => $question['Question']['partner_question_id'],
						'partner_answer_id' => $answer['Answer']['partner_answer_id'],
						'answer_text' => $answer['AnswerText'][0]['text']
					);
					if (!defined('IS_DEV_INSTANCE') || IS_DEV_INSTANCE === false) {
						try {
							$response_qe = $HttpQe->post($settings['hostname.qe'] . "/answers", json_encode($qe_post_data), array(
								'header' => array('Content-Type' => 'application/json')
							));
							if ($response_qe->code == 201) {
								$msg = "A new answer is created to QE for " . $question['Question']['partner'] . ":";
								$msg .= "\nQuestion ID: " . $question['Question']['partner_question_id'];
								$msg .= "\nName: " . $question['Question']['question'];
								$msg .= "\nAnswer ID: " . $answer['Answer']['partner_answer_id'];
								$msg .= "\nAnswer Text: " . $answer['AnswerText'][0]['text'];
								$this->out($msg);
							}
							elseif ($response_qe->code != 409) {
								$msg = "Unexpected response code for " . $question['Question']['partner'] . ": " . $response_qe->code;
								$msg .= "\nQuestion ID: " . $question['Question']['partner_question_id'];
								$msg .= "\nName: " . $question['Question']['question'];
								$msg .= "\nAnswer ID: " . $answer['Answer']['partner_answer_id'];
								$msg .= "\nAnswer Text: " . $answer['AnswerText'][0]['text'];
								Utils::slack_alert($settings['slack.questions.webhook'], $msg);
								$this->out($msg);
							}
						} catch (Exception $ex) {
							$msg = "Failed to save following answer to QE for " . $question['Question']['partner'] . ":";
							$msg .= "\nID: " . $question['Question']['partner_question_id'];
							$msg .= "\nName: " . $question['Question']['question'];
							$msg .= "\nAnswer ID: " . $answer['Answer']['partner_answer_id'];
							$msg .= "\nAnswer Text: " . $answer['AnswerText'][0]['text'];
							$msg .= "\n\nException: " . $ex->getMessage();
							Utils::slack_alert($settings['slack.questions.webhook'], $msg);
							$this->out($msg);
						}
					}
				}
			}

			$this->Question->create();
			$this->Question->save(array('Question' => array(
				'id' => $question['Question']['id'],
				'last_exported' => date(DB_DATETIME),
			)), true, array('last_exported'));
		}
		
		if ($i > 0) {
			$this->out($i. ' New questions exported to QE.');
		}
		else {
			$this->out('No new question exported to QE.');
		}
	}
	
	// help identify the core questions
	// arg[0] = past no of days
	// arg[1] = partner (optional)
	public function write_statistics_data() {
		ini_set('memory_limit', '3000M');
		$models_to_import = array('Group', 'Question', 'Answer', 'CintLog', 'Project', 'Qualification', 'QuestionStatistic');
		foreach ($models_to_import as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		if (!isset($this->args[0]) || !is_numeric($this->args[0])) {
			$this->out('Please specify the interval by number of days from today');
			return;
		}
		
		$date = '-' . $this->args[0] . ' days';
		$distinct_partners = $this->Question->find('all', array(
			'fields' => array('DISTINCT(Question.partner)')
		));
		
		foreach ($distinct_partners as $partner) {
			$partner = $partner['Question']['partner'];
			if (isset($this->args[1]) && $partner != $this->args[1]) {
				continue;
			}
			
			$question_statistics = array();
			$questions = $this->Question->find('list', array(
				'fields' => array('Question.partner_question_id', 'Question.id'),
				'conditions' => array(
					'Question.partner' => $partner
				),
			));
			$group = $this->Group->find('first', array(
				'fields' => array('Group.id'),
				'conditions' => array(
					'Group.key' => $partner == 'lucid' ? 'fulcrum' : $partner
				),
				'recursive' => -1
			));
			$this->out('Starting '.$partner.' analysis'); 
			if ($partner == 'cint') {
				$answers = $this->Answer->find('list', array(
					'fields' => array('Answer.partner_answer_id', 'question_id')
				));
				
				$oldest_cint_log = $this->CintLog->find('first', array(
					'fields' => array('CintLog.id'),
					'conditions' => array(
						'CintLog.count >' => 0,
						'CintLog.parent_id' => 0,
						'CintLog.created >=' => date(DB_DATE, strtotime($date)).' 00:00:00',
					),
					'order' => 'CintLog.id ASC'
				)); 
				$newest_cint_log = $this->CintLog->find('first', array(
					'fields' => array('CintLog.id'),
					'conditions' => array(
						'CintLog.count >' => 0,
						'CintLog.parent_id' => 0,
					),
					'order' => 'CintLog.id DESC'
				)); 
				$count = $this->CintLog->find('count', array(
					'conditions' => array(
						'CintLog.id >=' => $oldest_cint_log['CintLog']['id'],
						'CintLog.count >' => 0,
						'CintLog.parent_id' => 0,
					),
				)); 
				$this->out('Found '.$count.' Cint logs to analyze: '.$oldest_cint_log['CintLog']['id'].' to '.$newest_cint_log['CintLog']['id']);
				
				$newest_id = $newest_cint_log['CintLog']['id']; 
				$oldest_id = $oldest_cint_log['CintLog']['id'];
				$i = 0; 
				
				while (true) {
					$cint_logs = $this->CintLog->find('all', array(
						'fields' => array('CintLog.id', 'CintLog.raw', 'country'),
						'conditions' => array(
							'CintLog.id <=' => $newest_id,
							'CintLog.id >' => $oldest_id,
							'CintLog.count >' => 0,
							'CintLog.parent_id' => 0,
						),
						'limit' => 100,
						'order' => 'CintLog.id ASC'
					));
					if (!$cint_logs) {
						break;
					}
					
					$cint_variables = array(); 
					foreach ($cint_logs as $cint_log) {
						$pct = round(($i / $count) * 100, 2); 
						$this->out('Cint: #'.$cint_log['CintLog']['id'].' - '.number_format($pct, 2).'%'); 
						$i++; 
						$oldest_id = $cint_log['CintLog']['id']; 
						$country = $cint_log['CintLog']['country'];
						$cint_raw = json_decode($cint_log['CintLog']['raw'], true); 
						foreach ($cint_raw as $cint_project) {
							if (!isset($cint_project['target_group']['variable_ids']) || empty($cint_project['target_group']['variable_ids'])) {
								continue; 
							}
							
							$project_id = $cint_project['project_id'];
							if (!isset($cint_variables[$country][$project_id])) {
								$cint_variables[$country][$project_id] = array(); 
							}
							
							foreach ($cint_project['target_group']['variable_ids'] as $variable_id) {
								if (isset($answers[$variable_id])) {
									$question_id = $answers[$variable_id];
									
									// to prevent dupe counts on per-project basis
									if (isset($cint_variables[$country][$project_id][$question_id])) {
										continue; 
									}
									
									$cint_variables[$country][$project_id][$question_id] = true; 
									if (!isset($question_statistics[$country][$question_id])) {
										$question_statistics[$country][$question_id] = 1;
										continue;
									}
								
									$question_statistics[$country][$question_id]++;
								}
							}
						}
					}
				}
				
				CakeLog::write('cint-questions', print_r($cint_variables, true));
			}
			else {
				$projects = $this->Project->find('all', array(
					'fields' => array('Project.id', 'country'),
					'conditions' => array(
						'Project.group_id' => $group['Group']['id'],
						'Project.date_created >=' => date(DB_DATE, strtotime($date)).' 00:00:00',
					),
					'recursive' => -1
				));
				$this->out('Found '.count($projects).' to analyze for '.$partner); 
				foreach ($projects as $project) {
					$qualifications = $this->Qualification->find('all', array(
						'fields' => array('Qualification.id', 'Qualification.project_id', 'Qualification.query_json'),
						'conditions' => array(
							'Qualification.project_id' => $project['Project']['id'],
							'Qualification.query_json is not null',
							'Qualification.parent_id is null',
							'Qualification.deleted is null',
						)
					));
					if (!$qualifications) {
						continue;
					}
					
					$country = $project['Project']['country'];
					foreach ($qualifications as $qualification) {
						$qualification = json_decode($qualification['Qualification']['query_json'], true);
						$question_partner_ids = array_keys($qualification['qualifications']);
						foreach ($question_partner_ids as $question_partner_id) {
							if (isset($questions[$question_partner_id])) {
								$question_id = $questions[$question_partner_id];
								if (!isset($question_statistics[$country][$question_id])) {
									$question_statistics[$country][$question_id] = 1;
									continue;
								}
								
								$question_statistics[$country][$question_id]++;
							}
						}
					}
				}
			}
			
			if (empty($question_statistics)) {
				$this->out($partner.' statistics not found.');
				continue;
			}
			
			foreach ($question_statistics as $country => $statistics) {
				foreach ($statistics as $question_id => $frequency) {
					$question_statistic = $this->QuestionStatistic->find('first', array(
						'fields' => array('id', 'frequency'),
						'conditions' => array(
							'QuestionStatistic.question_id' => $question_id,
							'QuestionStatistic.country' => $country
						)
					));
					if ($question_statistic) {

						// frequency = previous count + current count
						$frequency = $question_statistic['QuestionStatistic']['frequency'] + $frequency;
						$this->QuestionStatistic->create();
						$this->QuestionStatistic->save(array('QuestionStatistic' => array(
							'id' => $question_statistic['QuestionStatistic']['id'],
							'frequency' => $frequency
						)), true, array('frequency'));
						continue;
					}

					$this->QuestionStatistic->create();
					$this->QuestionStatistic->save(array('QuestionStatistic' => array(
						'question_id' => $question_id,
						'frequency' => $frequency,
						'partner' => $partner,
						'country' => $country
					)));
				}
			}
			
			$this->out('Completed for ' . $partner);
		}
	}
	
	// help identify the public (less in priority then core) questions.
	// arg[0] = partner (optional)
	public function set_active_quals() {
		ini_set('memory_limit', '3000M');
		$models_to_import = array('ProjectOption', 'Group', 'Question', 'Answer', 'Project', 'Qualification');
		foreach ($models_to_import as $model) {
			App::import('Model', $model);
			$this->$model = new $model;
		}
		
		$distinct_partners = $this->Question->find('all', array(
			'fields' => array('DISTINCT(Question.partner)')
		));
		// this will store any actvie projects quals having Question.core = false;
		$active_project_questions = array();
		foreach ($distinct_partners as $partner) {
			$partner = $partner['Question']['partner'];
			if (isset($this->args[0]) && $partner != $this->args[0]) {
				continue;
			}
			
			$questions = $this->Question->find('list', array(
				'fields' => array('Question.partner_question_id', 'Question.id'),
				'conditions' => array(
					'Question.partner' => $partner,
					'Question.ignore' => false,
					'Question.staging' => false,
					'Question.deprecated' => false,
					'Question.core' => false,
					'Question.question_type' => array(QUESTION_TYPE_SINGLE, QUESTION_TYPE_MULTIPLE)
				),
			));
			
			$this->out('Executing partner: '.$partner); 
			if ($partner == 'cint') {
				App::import('Model', 'CintLog');
				$this->CintLog = new CintLog;
				$answers = $this->Answer->find('list', array(
					'fields' => array('Answer.partner_answer_id', 'question_id')
				));
				$project_options = $this->ProjectOption->find('list', array(
					'fields' => array('ProjectOption.id', 'ProjectOption.value'),
					'conditions' => array(
						'name' => array('cint_log.US.id', 'cint_log.CA.id', 'cint_log.GB.id',),
						'project_id' => 0
					)
				));
				if (!$project_options) {
					continue;
				}
				
				foreach ($project_options as $cint_log_id) {
					$cint_log = $this->CintLog->find('first', array(
						'conditions' => array(
							'CintLog.id' => $cint_log_id
						),
					));
					$cint_raw = json_decode($cint_log['CintLog']['raw'], true);
					if (empty($cint_raw)) {
						continue;
					}

					foreach ($cint_raw as $cint_project) {
						if (!isset($cint_project['target_group']['variable_ids']) || empty($cint_project['target_group']['variable_ids'])) {
							continue; 
						}

						foreach ($cint_project['target_group']['variable_ids'] as $variable_id) {
							if (isset($answers[$variable_id])) {
								$question_id = $answers[$variable_id];

								// prevent dupes
								if (isset($active_project_questions[$question_id])) {
									continue; 
								}

								if (array_search($question_id, $questions) !== false) {
									$active_project_questions[$question_id] = $question_id;
								}
							}
						}
					}
				}
			}
			else {
				$group = $this->Group->find('first', array(
					'fields' => array('Group.id'),
					'conditions' => array(
						'Group.key' => $partner == 'lucid' ? 'fulcrum' : $partner
					),
					'recursive' => -1
				));
				$projects = $this->Project->find('list', array(
					'fields' => array('Project.id', 'Project.id'),
					'conditions' => array(
						'Project.group_id' => $group['Group']['id'],
						'Project.active' => true,
						'Project.status' => array(PROJECT_STATUS_OPEN, PROJECT_STATUS_SAMPLING, PROJECT_STATUS_STAGING)
					),
					'recursive' => -1
				));
				$this->out('Found '.count($projects).' projects for '.$partner); 
				if (empty($projects)) {
					continue;
				}

				foreach ($projects as $project_id) {
					$qualifications = $this->Qualification->find('all', array(
						'fields' => array('Qualification.id', 'Qualification.project_id', 'Qualification.query_json'),
						'conditions' => array(
							'Qualification.project_id' => $project_id,
							'Qualification.query_json is not null',
							'Qualification.parent_id is null',
							'Qualification.deleted is null',
						)
					));
					if (!$qualifications) {
						continue;
					}

					foreach ($qualifications as $qualification) {
						$qualification = json_decode($qualification['Qualification']['query_json'], true);
						if (!isset($qualification['qualifications']) || empty($qualification['qualifications'])) {
							continue;
						}

						$question_partner_ids = array_keys($qualification['qualifications']);
						foreach ($question_partner_ids as $question_partner_id) {
							if (isset($questions[$question_partner_id])) {
								$question_id = $questions[$question_partner_id];
								
								// prevent dupes
								if (isset($active_project_questions[$question_id])) {
									continue; 
								}

								$active_project_questions[$question_id] = $question_id;
							}
						}
					}
				}
			}
		}
			
		
		if (empty($active_project_questions)) {
			$this->out('No active project questions found.');
			return;
		}

		$conditions = array(
			'Question.public' => true
		);
		if (isset($this->args[0]) && !empty($this->args[0])) {
			$conditions['Question.partner'] = $this->args[0]; 
		}
		
		$current_public_questions = $this->Question->find('list', array(
			'fields' => array('Question.id', 'Question.id'),
			'conditions' => $conditions
		));
		$make_public = array_diff($active_project_questions, $current_public_questions);
		$revert_public = array_diff($current_public_questions, $active_project_questions);
		if ($make_public) {
			foreach ($make_public as $question_id) {
				$this->Question->create();
				$this->Question->save(array('Question' => array(
					'id' => $question_id,
					'public' => true
				)), true, array('public'));
			}
		}
		
		$this->out(count($make_public). ' questions made public');
		if ($revert_public) {
			foreach ($revert_public as $question_id) {
				$this->Question->create();
				$this->Question->save(array('Question' => array(
					'id' => $question_id,
					'public' => false
				)), true, array('public'));
			}
		}
		
		$this->out(count($revert_public). ' questions reverted from public');
	}
}