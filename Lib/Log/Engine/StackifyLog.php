<?php
App::uses('BaseLog', 'Log/Engine');

class StackifyLog extends BaseLog {
	public function write($type, $message) {
		App::uses('StackifyComponent', 'Controller/Component');
		$Stackify = new StackifyComponent();
		return $Stackify->write_log($type, $message);
	}
}