<?php
if (!empty($data)) {
	foreach ($data as $csv_row) {			
		$this->Csv->addRow(array(key($csv_row), $csv_row[key($csv_row)]));	
	}	
	echo $this->Csv->render(time().'.csv');  
}
?>