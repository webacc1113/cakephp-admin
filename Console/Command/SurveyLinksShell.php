<?php
App::uses('CakeEmail', 'Network/Email');
App::import('Vendor', 'sqs');
App::import('Lib', 'Utilities');

// see https://basecamp.com/2045906/projects/1413421/todos/197525397
class SurveyLinksShell extends AppShell {
	public $uses = array('SurveyLink', 'Project', 'ProjectOption', 'Setting');

	public function getOptionParser() {
	    $parser = parent::getOptionParser();
		$parser->addArgument('id', array(
			'help' => 'Project ID',
			'required' => true
		));
		$parser->addArgument('postfix', array(
			'help' => 'Postfix for delete/recreates',
			'required' => false
		));
	    return $parser;
	}
	
	public function view() {
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('sqs.access.key', 'sqs.access.secret'),
				'Setting.deleted' => false
			)
		));
		if (count($settings) < 2) {
			$this->out('Missing required settings');
			return;
		}
		$sqs = new SQS($settings['sqs.access.key'], $settings['sqs.access.secret']);
		$project_option = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.project_id' => $this->args[0],
				'ProjectOption.name' => 'sqs_url'
			)
		));
		if (!$project_option) {
			$this->out('SQS has not been set up for this project.');
			return false;
		}
		$queue_url = $project_option['ProjectOption']['value'];
		$results = $sqs->getQueueAttributes($queue_url);
		print_r($results);
	}
	
	public function test_link() {
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('sqs.access.key', 'sqs.access.secret'),
				'Setting.deleted' => false
			)
		));
		if (count($settings) < 2) {
			$this->out('Missing required settings');
			return;
		}
		$sqs = new SQS($settings['sqs.access.key'], $settings['sqs.access.secret']);
		$project_option = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.project_id' => $this->args[0],
				'ProjectOption.name' => 'sqs_url'
			)
		));
		if (!$project_option) {
			$this->out('SQS has not been set up for this project.');
			return false;
		}
		$queue_url = $project_option['ProjectOption']['value'];
		$results = $sqs->receiveMessage($queue_url);
		if (empty($results['Messages'])) {
			$this->out('Nothing left in the queue');
			return false;
		}
		$survey_link_id = $results['Messages'][0]['Body'];
	}
	
	public function sync_to_sqs() {
		$settings = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('sqs.access.key', 'sqs.access.secret'),
				'Setting.deleted' => false
			)
		));
		if (count($settings) < 2) {
			$this->out('Missing required settings');
			return;
		}
		
		$survey_links = $this->SurveyLink->find('all', array(
			'conditions' => array(
				'SurveyLink.sqs' => false,
				'SurveyLink.survey_id' => $this->args[0],
				'SurveyLink.used' => false,
				'SurveyLink.user_id is null',
			),
			'fields' => array('SurveyLink.id'),
		));
		if (empty($survey_links)) {
			$this->out('No survey links to process');
			return;
		}
		CakeLog::write('aws.sqs', 'Starting SQS Sync for #'.$this->args[0].' ('.count($survey_links).')'); 
		
		$sqs = new SQS($settings['sqs.access.key'], $settings['sqs.access.secret']);
		$project_option = $this->ProjectOption->find('first', array(
			'conditions' => array(
				'ProjectOption.project_id' => $this->args[0],
				'ProjectOption.name' => 'sqs_url'
			)
		));
		if (!$project_option) {
			// we append the time because deleting queues cause the URL to not be accessible; this causes issues 
			// when you select "Delete imported links" options
			$response = $sqs->createQueue($this->args[0].'-'.time());
			$queue_url = $response['QueueUrl'];
			CakeLog::write('aws.sqs', 'Created queue '.$queue_url.' for #'.$this->args[0]); 
			$this->ProjectOption->create();
			$this->ProjectOption->save(array('ProjectOption' => array(
				'name' => 'sqs_url',
				'project_id' => $this->args[0],
				'value' => $queue_url
			)));
			
			// set the max retention to 14 days; we'll refresh the survey links every 10 days
			$result = $sqs->setQueueAttributes($queue_url, array(
				'MessageRetentionPeriod' => 1209600
			)); 
		}
		else {
			$queue_url = $project_option['ProjectOption']['value'];
		}
		
		$i = 0;
		
		$this->Project->create();
		$this->Project->save(array('Project' => array(
			'id' => $this->args[0],
			'sqs' => date(DB_DATETIME)
		)), true, array('sqs'));
		
		foreach ($survey_links as $survey_link) {
			$response = $sqs->sendMessage($queue_url, $survey_link['SurveyLink']['id']);
			$this->SurveyLink->create();
			$this->SurveyLink->save(array('SurveyLink' => array(
				'id' => $survey_link['SurveyLink']['id'],
				'sqs' => true
			)), true, array('sqs'));
			$i++;
		}
		CakeLog::write('amazon.sqs', 'Set SQS to '.date(DB_DATETIME).' for #'.$this->args[0]); 
	}
}
