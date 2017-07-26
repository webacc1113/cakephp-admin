<?php
App::import('Lib', 'MintVineUser');

class TransactionsShell extends AppShell {
	public $uses = array('Transaction', 'TransactionReport', 'Partner', 'SurveyVisit', 'Project', 'User', 'QueryProfile', 'HellbanLog');
	
	function export_transaction_data() {
		if (!isset($this->args[0])) {
			return false; 
		}
		$transaction_report_id = $this->args[0];
		
		if (!empty($this->args[2])) {
			$date_to = $this->args[2];
		}
		
		if (!empty($this->args[1])) {
			$date_from = $this->args[1];
		}
		
		if (isset($date_from) && !empty($date_from)) {
			if (isset($date_to) && !empty($date_to)) {
				$conditions['Transaction.executed >='] = date(DB_DATE, strtotime($date_from));
				$conditions['Transaction.executed <='] = date(DB_DATE, strtotime($date_to));
			}
			else {
				$conditions['Transaction.executed >='] = date(DB_DATE, strtotime($date_from));
			}
		}
		$this->out('Starting exports from '.$date_from.' to '.$date_to); 
		
		$order = 'Transaction.id DESC';
		$this->Transaction->bindModel(array(
			'hasMany' => array(
				'UserAnalysis' => array(
					'foreignKey' => 'transaction_id',
					'order' => 'UserAnalysis.id DESC'
				)
			)
		));
		$contain = array(
			'User' => array(
				'fields' => array('User.*'),
				'QueryProfile' => array(
					'fields' => array(
						'country'
					)
				)
			),
			'PaymentMethod'
		);
		$this->Transaction->bindItems();
		$conditions['Transaction.type_id'] = TRANSACTION_WITHDRAWAL;
		$conditions['Transaction.deleted'] = null;
		
		$transactions = $this->Transaction->find('all', array(
			'conditions' => $conditions,
			'order' => $order,
			'contain' => $contain
		));
		if (!$transactions) {
			$this->out('There is no transaction available between the selected date range.'); 
			return false; 
		}
		$total = count($transactions); 
		$this->out('Exporting '.$total.' withdrawals'); 
	
		if (!is_dir(WWW_ROOT . 'files/transaction_reports/')) {
			mkdir(WWW_ROOT . 'files/transaction_reports/');
		}
		$filename = 'withdrawals_'.date('Y-m-d') .'_'. time() .'.csv';
		$file_dir_path = 'files/transaction_reports/'.$filename;
		
		$file = WWW_ROOT . $file_dir_path;
		$fp = fopen($file, 'w');
		fputcsv($fp, array(
			'Withdrawal Request At',
			'Withdrawal Approved At',
			'Panelist Id',
			'Payment Method',
			'Amount',
			'Country',
			'Total Withdrawal',
			'Total Transactions Since Last Withdrawal',
			'% Offers',
			'% Surveys',
			'Total Points'
		));
		$i = 0; 
		foreach ($transactions as $transaction) {
			$i++;
			$approved = null;
			if ($transaction['Transaction']['payout_processed'] == true) {
				$approved = $transaction['Transaction']['executed'];
			}
			$country = null;
			if (!empty($transaction['User']['QueryProfile']['country'])) {
				$country = $transaction['User']['QueryProfile']['country'];
			}
			
			$total_withdrawal = $this->Transaction->find('count', array(
				'conditions' => array(
					'Transaction.user_id' => $transaction['Transaction']['user_id'],
					'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
					'Transaction.deleted' => null,
				),
				'recursive' => -1
			));
			$last_withdrawal = $this->Transaction->find('first', array(
				'conditions' => array(
					'Transaction.id <' => $transaction['Transaction']['id'],
					'Transaction.user_id' => $transaction['Transaction']['user_id'],
					'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
					'Transaction.deleted' => null,
				),
				'recursive' => -1
			));
			
			$withdrawal_conditions = array();
			$withdrawal_conditions['Transaction.id <'] = $transaction['Transaction']['id'];
			$withdrawal_conditions['Transaction.user_id'] = $transaction['Transaction']['user_id'];
			$withdrawal_conditions['Transaction.deleted'] = null;
			if (!empty($last_withdrawal)) {
				$withdrawal_conditions['Transaction.id >'] = $last_withdrawal['Transaction']['id'];
			}
			
			$total_since_last = $this->Transaction->find('count', array(
				'conditions' => $withdrawal_conditions,
				'recursive' => -1
			));
			
			$offer_conditions['Transaction.type_id'] = TRANSACTION_OFFER;
			$offer = $this->Transaction->find('all', array(
				'conditions' => $withdrawal_conditions + $offer_conditions,
				'fields' => array('SUM(Transaction.amount) AS total_amount'),
				'recursive' => -1
			));
			
			$survey_conditions['Transaction.type_id'] = TRANSACTION_SURVEY;
			$survey = $this->Transaction->find('all', array(
				'conditions' => $withdrawal_conditions + $survey_conditions,
				'fields' => array('SUM(Transaction.amount) AS total_amount'),
				'recursive' => -1
			));
			
			$offer_amount = $survey_amount = 0;
			if (isset($offer[0][0]['total_amount']) && !empty($offer[0][0]['total_amount'])) {
				$offer_amount = $offer[0][0]['total_amount'];
			}
			if (isset($survey[0][0]['total_amount']) && !empty($survey[0][0]['total_amount'])) {
				$survey_amount = $survey[0][0]['total_amount'];
			} 
			
			$percent_offer = ($offer_amount / $transaction['Transaction']['amount']) * 100;
			$percent_survey = ($survey_amount / $transaction['Transaction']['amount']) * 100;
			$total_points = $transaction['User']['balance'] + $transaction['User']['pending'] + $transaction['User']['withdrawal'];
			fputcsv($fp, array(
				$transaction['Transaction']['created'],
				$approved,
				$transaction['Transaction']['user_id'],
				$transaction['PaymentMethod']['payment_method'],
				'$'.number_format(round(-1 * $transaction['Transaction']['amount'] / 100, 2), 2),
				$country,
				$total_withdrawal,
				$total_since_last + 1, // include current withdrawal in count
				$percent_offer,
				$percent_survey,
				$total_points
			));
			$this->out($i.' / '.$total.': '.$transaction['Transaction']['id'].' written');
		}
		
		if (isset($transaction_report_id)) {
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
			
			$aws_filename = $file_dir_path;
			$this->out('Writing to S3 '.$aws_filename.' from '.$file);
			$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);	
			$headers = array(
				'Content-Disposition' => 'attachment; filename='.$filename
			);
			if ($S3->putObject($S3->inputFile($file), $settings['s3.bucket'] , $aws_filename, S3::ACL_PRIVATE, array(), $headers)) {
				$this->TransactionReport->create();
				$this->TransactionReport->save(array('TransactionReport' => array(
					'id' => $transaction_report_id,
					'path' => $aws_filename,
					'status' => 'complete'
				)), true, array('path', 'status'));	
			}
			$this->out('Completed');
		}
	}
	
	// https://basecamp.com/2045906/projects/1413421/todos/274719708
	public function check_completes_for_skipped_rs() {
		if (!isset($this->args[0])) {
			$timestamp = '-1 week';
		}
		else {
			$timestamp = '-'.$this->args[0];
		}
		
		$partner = $this->Partner->find('first', array(
			'conditions' => array(
				'Partner.key' => 'mintvine'
			)
		));
		
		$timestamp = strtotime($timestamp);
		$datetime = date(DB_DATE, $timestamp).' 00:00:00';
		
		$transactions = $this->Transaction->find('all', array(
			'fields' => array('Transaction.id', 'Transaction.user_id', 'Transaction.amount', 'Transaction.paid', 'Transaction.linked_to_id', 'Transaction.status', 'Transaction.type_id'),
			'conditions' => array(
				'Transaction.deleted' => null,
				'Transaction.created >=' => $datetime,
				'Transaction.status' => array(TRANSACTION_PENDING, TRANSACTION_APPROVED),
				'Transaction.type_id' => TRANSACTION_SURVEY // we need to handle NQ at some point too
			),
			'recursive' => -1
		));
		$this->out('Processing '.count($transactions).' transactions since '.$datetime); 
		
		$i = 0; 
		$bad_user_ids = $hellbanned_ids = array();
		foreach ($transactions as $transaction) {
			$project = $this->Project->find('first', array(
				'fields' => array('Project.router'),
				'conditions' => array(
					'Project.id' => $transaction['Transaction']['linked_to_id']
				),
				'recursive' => -1
			));
			if ($project['Project']['router']) {
				continue;
			}
			$survey_visit = $this->SurveyVisit->find('first', array(
				'conditions' => array(
					'SurveyVisit.survey_id' => $transaction['Transaction']['linked_to_id'],
					'SurveyVisit.type' => array(SURVEY_COMPLETED, SURVEY_DUPE), // for some reason dupes also appear as completes...
					'SurveyVisit.partner_id' => $partner['Partner']['id'],
					'SurveyVisit.partner_user_id LIKE' => $transaction['Transaction']['linked_to_id'].'-'.$transaction['Transaction']['user_id'].'-%'
				)
			));
			
			if (!$survey_visit) {
				$user = $this->User->find('first', array(
					'fields' => array('User.id', 'User.hellbanned', 'User.pending', 'User.balance'),
					'conditions' => array(
						'User.id' => $transaction['Transaction']['user_id']
					),
					'recursive' => -1
				));
				$transaction = array_merge($transaction, $user); 
				$this->Transaction->reject($transaction); 
				if (!$user['User']['hellbanned']) {
					$hellbanned_ids[] = $transaction['Transaction']['user_id'];
					MintVineUser::hellban($transaction['Transaction']['user_id'], 'Skipped r for completes'); 
					$this->out('Rejected #'.$transaction['Transaction']['id'].' and hellbanned '.$transaction['Transaction']['user_id']); 
				}
				else {
					$this->out('Rejected #'.$transaction['Transaction']['id']); 
				}
				$i++;
				$bad_user_ids[] = $transaction['Transaction']['user_id'];
			}
		}
		$bad_user_ids = array_unique($bad_user_ids); 
		$this->out('Found '.$i.' in '.count($bad_user_ids).' users');
		$this->out('Hellbanned users: '.implode("\n", $hellbanned_ids));
	}
	
	
	// https://basecamp.com/2045906/projects/1413421/todos/274719708
	public function check_nqs_for_skipped_rs() {
		if (!isset($this->args[0])) {
			$timestamp = '-1 week';
		}
		else {
			$timestamp = '-'.$this->args[0];
		}
		
		$partner = $this->Partner->find('first', array(
			'conditions' => array(
				'Partner.key' => 'mintvine'
			)
		));
		
		$timestamp = strtotime($timestamp);
		$datetime = date(DB_DATE, $timestamp).' 00:00:00';
		
		$transactions = $this->Transaction->find('all', array(
			'fields' => array('Transaction.id', 'Transaction.user_id', 'Transaction.amount', 'Transaction.paid', 'Transaction.linked_to_id', 'Transaction.status', 'Transaction.type_id'),
			'conditions' => array(
				'Transaction.deleted' => null,
				'Transaction.created >=' => $datetime,
				'Transaction.status' => array(TRANSACTION_PENDING, TRANSACTION_APPROVED),
				'Transaction.type_id' => TRANSACTION_SURVEY_NQ // we need to handle NQ at some point too
			),
			'recursive' => -1
		));
		$this->out('Processing '.count($transactions).' transactions since '.$datetime); 
		
		$i = 0; 
		$bad_user_ids = $hellbanned_ids = array();
		foreach ($transactions as $transaction) {
			$project = $this->Project->find('first', array(
				'fields' => array('Project.router'),
				'conditions' => array(
					'Project.id' => $transaction['Transaction']['linked_to_id']
				),
				'recursive' => -1
			));
			if ($project['Project']['router']) {
				continue;
			}
			$survey_visit = $this->SurveyVisit->find('first', array(
				'conditions' => array(
					'SurveyVisit.survey_id' => $transaction['Transaction']['linked_to_id'],
					'SurveyVisit.type' => array(SURVEY_NQ, SURVEY_INTERNAL_NQ, SURVEY_DUPE_FP, SURVEY_NQ_FRAUD, SURVEY_NQ_SPEED, SURVEY_NQ_EXCLUDED, SURVEY_DUPE),
					'SurveyVisit.partner_id' => $partner['Partner']['id'],
					'SurveyVisit.partner_user_id LIKE' => $transaction['Transaction']['linked_to_id'].'-'.$transaction['Transaction']['user_id'].'-%'
				)
			));
			
			if (!$survey_visit) {
				$this->out('#'.$transaction['Transaction']['id']); 
				$i++;
				$bad_user_ids[] = $transaction['Transaction']['user_id'];
			}
		}
		$bad_user_ids = array_unique($bad_user_ids); 
		$this->out('Found '.$i.' in '.count($bad_user_ids).' users');
	}
}