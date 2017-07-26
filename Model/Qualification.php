<?php
App::uses('AppModel', 'Model');

class Qualification extends AppModel {
	public $actsAs = array('Containable');
	
	public function beforeDelete($cascade = true) {
		$qualification_id = $this->id; 
		$this->create();
		$this->save(array('Qualification' => array(
			'id' => $qualification_id,
			'deleted' => date(DB_DATETIME),
			'modified' => false
		)), array(
			'fieldList' => array('deleted'),
			'callbacks' => false,
			'validate' => false
		));
		return false;
	}
	
	public function afterSave($created, $options = array()) {
		App::import('Model', 'QualificationCpi');
		$this->QualificationCpi = new QualificationCpi;
		App::import('Model', 'QualificationStatistic');
		$this->QualificationStatistic = new QualificationStatistic;
		
		if ($created) {
			$this->QualificationStatistic->create();
			$this->QualificationStatistic->save(array('QualificationStatistic' => array(
				'qualification_id' => $this->id
			)));
			
			if (isset($this->data[$this->alias]['cpi']) || isset($this->data[$this->alias]['award'])) {
				$this->QualificationCpi->create();
				$this->QualificationCpi->save(array('QualificationCpi' => array(
					'qualification_id' => $this->id,
					'cpi' => $this->data[$this->alias]['cpi'],
					'award' => $this->data[$this->alias]['award']
				)));
			}
		}
		else {
			if (isset($this->data[$this->alias]['cpi']) || isset($this->data[$this->alias]['award'])) {
				$qualification_cpi = $this->QualificationCpi->find('first', array(
					'conditions' => array(
						'QualificationCpi.qualification_id' => $this->id,
					),
					'order' => 'QualificationCpi.id DESC',
					'recursive' => -1
				));
			}
				
			if (isset($this->data[$this->alias]['cpi'])) {
				$save = false;
				if (!$qualification_cpi || ($qualification_cpi && $qualification_cpi['QualificationCpi']['cpi'] != $this->data[$this->alias]['cpi'])) {
					$qualificationCpiSource = $this->QualificationCpi->getDataSource();
					$qualificationCpiSource->begin();
					$this->QualificationCpi->create();
					$this->QualificationCpi->save(array('QualificationCpi' => array(
						'qualification_id' => $this->id,
						'cpi' => $this->data[$this->alias]['cpi']
					)));
					$updated_cpi_id = $this->QualificationCpi->getInsertId();
					$qualificationCpiSource->commit();
				}
			}

			if (isset($this->data[$this->alias]['award'])) {
				$save = false;
				if (!$qualification_cpi || ($qualification_cpi && $qualification_cpi['QualificationCpi']['award'] != $this->data[$this->alias]['award'])) {
					if (isset($updated_cpi_id)) {
						$this->QualificationCpi->create();
						$this->QualificationCpi->save(array('QualificationCpi' => array(
							'id' => $updated_cpi_id,
							'award' => $this->data[$this->alias]['award']
						)));
					}
					else {
						$this->QualificationCpi->create();
						$this->QualificationCpi->save(array('QualificationCpi' => array(
							'qualification_id' => $this->id,
							'award' => $this->data[$this->alias]['award']
						)));
					}
				}
			}
		}
	}
}

