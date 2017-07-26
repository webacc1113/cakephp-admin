<?php
class ReconciliationsTask extends Shell {
	
	public function import($settings, $file) {
		CakePlugin::load('Uploader');
		App::import('Vendor', 'Uploader.S3');
		$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);
		$url = $S3->getAuthenticatedURL($settings['s3.bucket'], $file, 3600, false, false);
		$http = new HttpSocket(array(
			'timeout' => 15,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$results = $http->get($url);
		if ($results->code != 200) {
			return false;
		}
		
		$data = Utils::multiexplode(array("\n", "\r\n", "\r", "\n\r"), $results->body);
		foreach ($data as &$row) {
			$row = str_getcsv($row); //parse the items in rows 
		}
		
		unset($row);
		return $data;
	}
	
	public function update_status($id, $status) {
		App::import('Model', 'Reconciliation');
		$this->Reconciliation = new Reconciliation;
		$this->Reconciliation->create();
		$this->Reconciliation->save(array('Reconciliation' => array(
			'id' => $id,
			'status' => $status
		)), true, array('status'));
	}
	
	public function write_log($reconciliation_id, $type, $data) {
		if (!is_array($data)) {
			$description = $data;
		}
		else {
			$description = isset($data['description']) ? $data['description'] : null;
		}
		
		App::import('Model', 'ReconciliationLog');
		$this->ReconciliationLog = new ReconciliationLog;
		$this->ReconciliationLog->create();
		$this->ReconciliationLog->save(array('ReconciliationLog' => array(
			'reconciliation_id' => $reconciliation_id,
			'type' => $type,
			'hash' => isset($data['hash']) ? $data['hash'] : null,
			'project_id' => isset($data['project_id']) ? $data['project_id'] : null,
			'user_id' => isset($data['user_id']) ? $data['user_id'] : null,
			'description' => $description,
		)));
	}
}