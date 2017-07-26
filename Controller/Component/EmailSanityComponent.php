<?php

App::uses('Component', 'Controller');
class EmailSanityComponent extends Component {
	/**
	 * Sanity check of resend emails
	 * It limits resend to 3 per day
	 *
	 * @param $email
	 * @return true if it can send resend email
	 */
	public function check_resend($email) {
		App::import('Model', 'MailLog');
		$this->MailLog = new MailLog();

		$yesterday = strtotime('1 day ago');

		$count = $this->MailLog->find('count', array(
			'conditions' => array(
				'MailLog.email' => $email,
				'MailLog.created >' => date(DB_DATETIME, $yesterday)
			)
		));

		return $count < 3;
	}
}