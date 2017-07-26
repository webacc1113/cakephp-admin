<?php
if (!empty($users)) {
	$csv_headers = array(
		'respondentId',
		'sourceId',
		'firstName',
		'lastName',
		'email'
	);
	$this->Csv->addRow($csv_headers);
	foreach ($users as $user) {			
		$this->Csv->addRow(array(
			$user['User']['id'],
			$settings['truesample.sourceid'],
			$user['User']['firstname'],
			$user['User']['lastname'],
			$user['User']['email']
		));
	}	
	echo $this->Csv->render(time().'.csv');  
}
?>