<?php

CakePlugin::load('Mailgun');
App::import('Lib', 'Utilities');
App::uses('CakeEmail', 'Network/Email');
App::uses('HttpSocket', 'Network/Http');
App::uses('Controller', 'Controller');

class CompleteChallengeShell extends AppShell {
	var $uses = array('SurveyUserVisit', 'User', 'Transaction', 'Setting');
	
	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser->addArgument('period', array(
			'help' => 'The period of time to cover',
			'required' => true,
			'choices' => array('daily', 'weekly', 'monthly')
		));
		$parser->addOption('date', array(
			'short' => 'd',
			'help' => 'date to run',
			'default' => 'yesterday',
		));
		return $parser;
	}
	
	function payout() {
		
		$previous_week = strtotime("-1 week +1 day");
		$start_week = strtotime("last sunday midnight", $previous_week);
		$end_week = strtotime("next saturday", $start_week);
		
		$conditions = array(
			'SurveyUserVisit.status' => SURVEY_COMPLETED,
		);
		if ($this->args[0] == 'specific' && !empty($this->args[1])) {
			return false;
		}
		switch ($this->args[0]) {
			case 'specific':
				$points = 100;
				$conditions['SurveyUserVisit.created >='] = date('Y-m-d', strtotime($this->params['date'])).' 00:00:00';
				$conditions['SurveyUserVisit.created <='] = date('Y-m-d', strtotime($this->params['date'])).' 23:59:59';
				break;
			case 'daily':
				$points = 100;
				$conditions['SurveyUserVisit.created >='] = date('Y-m-d', strtotime('yesterday')).' 00:00:00';
				$conditions['SurveyUserVisit.created <='] = date('Y-m-d', strtotime('yesterday')).' 23:59:59';
				break;
			case 'weekly':
				$points = 500;
				$conditions['SurveyUserVisit.created >='] = date('Y-m-d', $start_week).' 00:00:00';
				$conditions['SurveyUserVisit.created <='] = date('Y-m-d', $end_week).' 23:59:59';
				break;
			case 'monthly':
				$points = 2500;
				$date = date('Y-m', strtotime('-1 month'));
				$conditions['SurveyUserVisit.created >'] = $date.'-01 00:00:00';
				$conditions['SurveyUserVisit.created <'] = $date.'-31 23:59:59';
				break;
		}
		$survey_user_visit = $this->SurveyUserVisit->find('first', array(
			'contain' => array(
				'User' => array(
					'fields' => array('User.*'),
					'QueryProfile'
				)
			),
			'conditions' => $conditions,
			'order' => array('rand()'),
			'limit' => 1,
			'recursive' => -1
		));
		if (!$survey_user_visit) {
			return;
		}
		$count = $this->SurveyUserVisit->find('count', array(
			'conditions' => $conditions
		)); 
		
		$setting = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('cdn.url'),
				'Setting.deleted' => false
			)
		));
		if (!empty($setting['cdn.url']) && (!defined('IS_DEV_INSTANCE') || !IS_DEV_INSTANCE)) {
			Configure::write('App.cssBaseUrl', $setting['cdn.url'] . '/');
			Configure::write('App.jsBaseUrl', $setting['cdn.url'] . '/');
			Configure::write('App.imageBaseUrl', $setting['cdn.url'] . '/img/');
		}
		
		$email = new CakeEmail();
		$email->config('mailgun');
		$result = $email->from(array(EMAIL_SENDER => 'MintVine'))
			->replyTo(array(REPLYTO_EMAIL => 'MintVine'))
			->template('complete_challenge')
			->viewVars(array(
				'user' => $survey_user_visit, 
				'amount' => $points,
				'type' => $this->args[0],
				'count' => $count
			))
			->emailFormat('html')
			->to($survey_user_visit['User']['email'])
			->subject("You've Won the ".ucfirst($this->args[0])." MintVine Challenge!")
			->send();
		
		if ($this->args[0] == 'daily') {
			$name = ucfirst($this->args[0]).' Complete Challenge ('.date('F jS', strtotime($this->params['date'])).')';
		}
		if ($this->args[0] == 'specific') {
			$name = ucfirst($this->args[0]).' Complete Challenge ('.date('F jS', strtotime($this->args[1])).')';
		}
		elseif ($this->args[0] == 'weekly') {
			$name = ucfirst($this->args[0]).' Complete Challenge ('.date('F jS', $start_week).' - '.date('F jS', $end_week).')';
		}
		elseif ($this->args[0] == 'monthly') {
			$name = ucfirst($this->args[0]).' Complete Challenge ('.date('F', strtotime('-1 month')).')';
		}
		$transactionSource = $this->Transaction->getDataSource();
		$transactionSource->begin();
		$this->Transaction->create();
		$this->Transaction->save(array('Transaction' => array(
			'type_id' => TRANSACTION_CHALLENGE,
			'linked_to_id' => '0',
			'user_id' => $survey_user_visit['User']['id'],
			'amount' => $points,
			'paid' => false,
			'name' => $name,
			'status' => TRANSACTION_PENDING,
			'executed' => date(DB_DATETIME)
		)));
		$transaction_id = $this->Transaction->getInsertId();
		$transaction = $this->Transaction->findById($transaction_id);
		$this->Transaction->approve($transaction);
		$this->out($transaction_id);		
		$transactionSource->commit();
	}
}