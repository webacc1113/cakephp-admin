<?php
App::uses('Controller', 'Controller');
App::uses('View', 'View');

class NotifyTask extends AppShell {
	public $uses = array('MailQueue', 'Nonce');

	public function email($project, $user) {
		$survey_subject = empty($project['Project']['description']) ? 'Exciting Survey Opportunity': $project['Project']['description'];
		$survey_award = $project['Project']['award'];
		$survey_length = $project['Project']['est_length'];
		$is_desktop = $project['Project']['desktop'];
		$is_mobile = $project['Project']['mobile'];
		$is_tablet = $project['Project']['tablet'];
		$survey_id = $project['Project']['id'];

		$controller = new Controller();
		$view = new View($controller, false);
		$view->layout = 'Emails/html/default';
		$nonce = '{{nonce}}';
		$survey_url = '{{survey_url}}';
		$unsubscribe_link = '{{unsubscribe_link}}';
		$view->set(compact('nonce', 'survey_url', 'unsubscribe_link', 'survey_award', 'survey_length', 'is_desktop', 'is_mobile', 'is_tablet', 'survey_id'));
		$view->viewPath = 'Emails/html';
		$email_body = $view->render('survey');
		$this->autoRender = true;		

		$nonce = substr($user['User']['ref_id'], 0, 21).'-'.substr(Utils::rand(10), 0, 10);
		$survey_url = HOSTNAME_WWW.'/surveys/pre/'.$project['Project']['id'].'/?nonce='.$nonce.'&from=email&key='.$project['Project']['code'];
		$unsubscribe_link = HOSTNAME_WWW.'/users/emails/'.$user['User']['ref_id'];

		$customized_email_body = str_replace(array(
			'{{nonce}}',
			'{{unsubscribe_link}}', 
			'{{survey_url}}',
			'{{user_id}}'
		), array(
			$nonce,
			$unsubscribe_link, 
			$survey_url,
			$user['User']['id']
		), $email_body);

		// create the one-time nonce
		$this->Nonce->create();
		$this->Nonce->save(array('Nonce' => array(
			'item_id' => $project['Project']['id'],
			'item_type' => 'survey',
			'user_id' => $user['User']['id'],
			'nonce' => $nonce
		)), false);
		
		$this->MailQueue->create();
		$this->MailQueue->save(array('MailQueue' => array(
			'user_id' => $user['User']['id'],
			'email' => $user['User']['email'],
			'subject' => $survey_subject,
			'project_id' => $project['Project']['id'],
			'body' => $customized_email_body,
			'status' => 'Queued'
		)));

		return array(
			'nonce' => $nonce,
			'survey_url' => $survey_url,
		);
	}
}