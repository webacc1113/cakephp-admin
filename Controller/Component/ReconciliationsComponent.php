<?php

App::uses('Component', 'Controller');
class ReconciliationsComponent extends Component {
	
	public function save($settings, $file, $type) {
		$S3 = new S3($settings['s3.access'], $settings['s3.secret'], '', $settings['s3.host']);
		$file_name = 'reconciliations/'.date('Y').'/'.date('m').'/'.date('d').'/'.date('H').':'.date('i').'-'.$file['name'];
		if (!$S3->putObject(S3::inputFile($file['tmp_name']), $settings['s3.bucket'], $file_name, S3::ACL_PRIVATE)) {
			return false;
		}
		
		App::import('Model', 'Reconciliation');
		$this->Reconciliation = new Reconciliation;
		$reconciliationSource = $this->Reconciliation->getDataSource();
		$reconciliationSource->begin();
		$this->Reconciliation->create();
		$this->Reconciliation->save(array('Reconciliation' => array(
			'type' => $type,
			'filepath' => $file_name
		)));
		$reconciliation_id = $this->Reconciliation->getInsertID();
		$reconciliationSource->commit();
		return $reconciliation_id;
	}
}