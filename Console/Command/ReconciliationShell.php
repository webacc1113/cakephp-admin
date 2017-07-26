<?php
App::uses('Shell', 'Console');
App::import('Lib', 'Utilities');
App::import('Lib', 'Reporting');
App::uses('HttpSocket', 'Network/Http');

class ReconciliationShell extends AppShell {
	var $uses = array('Reconciliation', 'ReconciliationRow', 'ExtraComplete', 'Group', 'Project', 'Transaction', 'Setting', 'RouterLog', 'SurveyVisit', 'User');
	public $tasks = array('Reconciliations', 'ReconcileSsi', 'ReconcileP2S', 'ReconcilePersonaly', 'ReconcileTrialpay', 'ReconcileAdwall', 'ReconcileOffertoro', 'ReconcilePeanutlabs', 'ReconcileAdgate', 'ReconcileLucid', 'ReconcileToluna', 'ReconcilePrecision', 'ReconcileCint', 'ReconcileSpectrum');
		
	public function getOptionParser() {
		$parser = parent::getOptionParser();
		$parser->addArgument('reconciliation_id', array(
			'help' => 'Reconciliation ID',
			'required' => true
		));
		return $parser;
	}
	
	// some shared validation
	private function pre_checks() {
		$reconciliation_id = $this->args[0];
		$reconciliation = $this->Reconciliation->find('first', array(
			'conditions' => array(
				'Reconciliation.id' => $reconciliation_id
			)
		));
		if (!$reconciliation) {
			return false;
		}
		
		$this->reconciliation = $reconciliation;
		
		// grab S3 information
		$settings = $this->Setting->find('list', array(
			'fields' => array('name', 'value'),
			'conditions' => array(
				'Setting.name' => array(
					's3.access',
					's3.secret',
					's3.bucket',
					's3.host'
				),
				'Setting.deleted' => false
			)
		));
		if (count($settings) != 4) {
			return false;
		}
		$this->settings = $settings;
		$client_parsers_mapping = array(
			RECONCILE_POINTS2SHOP => 'ReconcileP2S',
			RECONCILE_SSI => 'ReconcileSsi',
			RECONCILE_PERSONALY => 'ReconcilePersonaly',
			RECONCILE_ADWALL => 'ReconcileAdwall',
			RECONCILE_OFFERTORO => 'ReconcileOffertoro',
			RECONCILE_PEANUTLABS => 'ReconcilePeanutlabs',
			RECONCILE_ADGATE => 'ReconcileAdgate',
			RECONCILE_LUCID => 'ReconcileLucid',
			RECONCILE_TOLUNA => 'ReconcileToluna',
			RECONCILE_PRECISION => 'ReconcilePrecision',
			RECONCILE_CINT => 'ReconcileCint',
			RECONCILE_SPECTRUM => 'ReconcileSpectrum',
		);
		
		if (!isset($client_parsers_mapping[$reconciliation['Reconciliation']['type']])) {
			return false;
		}
		
		$this->ClientParser = $this->$client_parsers_mapping[$reconciliation['Reconciliation']['type']]; 
		return true;
	}
	
	private function pre_import($type) {
		if (!$this->pre_checks()) {
			$this->Reconciliations->write_log($this->args[0], 'import.failed', 'failed prechecks');
			return false;
		}
		
		if (!is_null($this->reconciliation['Reconciliation']['status'])) {
			$this->Reconciliations->write_log($this->args[0], 'import.failed', 'Status should be NULL. Current status is '.$this->reconciliation['Reconciliation']['status']);
			return false;
		}
		
		$data = $this->Reconciliations->import($this->settings, $this->reconciliation['Reconciliation']['filepath']);
		if (empty($data)) {
			$this->Reconciliations->write_log($this->args[0], 'import.failed', 'File not found');
			$this->Reconciliations->update_status($this->args[0], RECONCILIATION_ERROR);
			return false;
		}
		
		$data = $this->ClientParser->cleanFile($data);
		if (empty($data)) {
			$this->Reconciliations->write_log($this->args[0], 'import.failed', 'File is either empty, or the headers (first row) are not correct');
			$this->Reconciliations->update_status($this->args[0], RECONCILIATION_ERROR);
			return false;
		}
		
		$this->out('Iterating over '.count($data).' rows');
		return $data;
	}

	public function import_cint() {
		ini_set('memory_limit', '1024M');
		$data = $this->pre_import(RECONCILE_CINT);
		if (!$data) {
			return;
		}
		
		$dates = array('min' => '', 'max' => '');
		$completes_count = 0;
		foreach ($data as $key => $row) {
			
			// We only import approved completes from partners
			if ($row[$this->ClientParser->indexes['status']] != 'Complete') {
				continue;
			}
			
			$reconciliation_row = $this->ClientParser->parseCsvRow($row);
			$needs_reconciliation = false;
			$transaction_id = null;
			
			// if we do not find the complete
			if (empty($reconciliation_row['hash'])) {
				$count = $this->User->find('count', array(
					'conditions' => array(
						'User.id' => $reconciliation_row['user_id'],
						'User.deleted_on' => null
					),
					'recursive' => -1
				));
				if ($count == 0) {
					$needs_reconciliation = false;
					$this->Reconciliations->write_log($this->args[0], 'import.missing.user', array(
						'project_id' => $reconciliation_row['survey_id'],
						'user_id' => $reconciliation_row['user_id'],
						'timestamp' => $reconciliation_row['timestamp'],
						'description' => 'User deleted, so reconciliation is not needed.'
					));
				}
				elseif (empty($reconciliation_row['survey_id'])) {
					$this->Reconciliations->write_log($this->args[0], 'import.missing.project', array(
						'user_id' => $reconciliation_row['user_id'],
						'timestamp' => $reconciliation_row['timestamp'],
						'description' => 'Project not found'
					));
				}
				elseif ($transaction_id = $this->ClientParser->find_transaction($reconciliation_row)) {
					$this->Reconciliations->write_log($this->args[0], 'missing.complete.paid', array(
						'user_id' => $reconciliation_row['user_id'],
						'project_id' => $reconciliation_row['survey_id'],
						'timestamp' => $reconciliation_row['timestamp'],
						'description' => 'Missing complete already paid. Transaction #'.$transaction_id
					));
				}
				else {
					$needs_reconciliation = true;
				}
			}
			
			$this->ReconciliationRow->create();
			$this->ReconciliationRow->save(array('ReconciliationRow' => array(
				'reconciliation_id' => $this->reconciliation['Reconciliation']['id'],
				'user_id' => $reconciliation_row['user_id'],
				'hash' => $reconciliation_row['hash'],
				'survey_id' => $reconciliation_row['survey_id'],
				'transaction_id' => $transaction_id,
				'timestamp' => $reconciliation_row['timestamp'],
				'needs_reconciliation' => $needs_reconciliation,
			)));
			
			$completes_count++;
			$dates = $this->ClientParser->getMinMaxDates($dates, $reconciliation_row['timestamp']);
		}
		
		$this->Reconciliation->create();
		$this->Reconciliation->save(array('Reconciliation' => array(
			'id' => $this->reconciliation['Reconciliation']['id'],
			'status' => RECONCILIATION_IMPORTED,
			'max_transaction_date' => date(DB_DATETIME, $dates['max']),
			'min_transaction_date' => date(DB_DATETIME, $dates['min']),
			'total_completes' => $completes_count,
		)), true, array('status', 'max_transaction_date', 'min_transaction_date', 'total_completes'));
		
		$this->out('Import completed');
		
		$query = ROOT.'/app/Console/cake reconciliation find_extra_completes '.$this->reconciliation['Reconciliation']['id'];
		$query.= " > /dev/null &"; 
		exec($query, $output);
	}
	
	public function import_points2shop() {
		ini_set('memory_limit', '1024M');
		$data = $this->pre_import(RECONCILE_POINTS2SHOP);
		if (!$data) {
			return;
		}
		
		$completes_count = 0;
		$dates = array('min' => '', 'max' => '');
		foreach ($data as $key => $row) {
			$reconciliation_row = $this->ClientParser->parseCsvRow($row);
			$needs_reconciliation = false;
			$transaction_id = null;
			
			$count = $this->User->find('count', array(
				'conditions' => array(
					'User.id' => $reconciliation_row['user_id'],
					'User.deleted_on' => null
				),
				'recursive' => -1
			));
			if ($count == 0) {
				$this->Reconciliations->write_log($this->args[0], 'import.missing.user', array(
					'hash' => $reconciliation_row['hash'],
					'project_id' => $reconciliation_row['survey_id'],
					'user_id' => $reconciliation_row['user_id'],
					'timestamp' => $reconciliation_row['timestamp'],
					'description' => 'User deleted, so reconciliation is not needed'
				));
			}
			else {
				$needs_reconciliation = $this->ClientParser->needsReconciliationFlag($reconciliation_row);
				
				// if we don't find the complete, find if we have any transaction for this complete
				if ($needs_reconciliation) {
					$transaction_id = $this->ClientParser->find_transaction($reconciliation_row);
					if ($transaction_id) {
						$needs_reconciliation = false;
						$this->Reconciliations->write_log($this->args[0], 'missing.complete.paid', array(
							'user_id' => $reconciliation_row['user_id'],
							'hash' => $reconciliation_row['hash'],
							'project_id' => $reconciliation_row['survey_id'],
							'timestamp' => $reconciliation_row['timestamp'],
							'description' => 'Missing complete already paid. Transaction #'.$transaction_id
						));
					}
				}
			}
			
			$this->ReconciliationRow->create();
			$this->ReconciliationRow->save(array('ReconciliationRow' => array(
				'reconciliation_id' => $this->reconciliation['Reconciliation']['id'],
				'user_id' => $reconciliation_row['user_id'],
				'hash' => $reconciliation_row['hash'],
				'survey_id' => $reconciliation_row['survey_id'],
				'transaction_id' => $transaction_id, // in case the complete is already paid.
				'partner_transaction_id' => $reconciliation_row['partner_transaction_id'],
				'timestamp' => $reconciliation_row['timestamp'],
				'needs_reconciliation' => $needs_reconciliation,
				'processed' => false, 
			)));
			
			$completes_count++;
			$dates = $this->ClientParser->getMinMaxDates($dates, $reconciliation_row['timestamp']);
		}
		
		$this->Reconciliation->create();
		$this->Reconciliation->save(array('Reconciliation' => array(
			'id' => $this->reconciliation['Reconciliation']['id'],
			'status' => RECONCILIATION_IMPORTED,
			'max_transaction_date' => date(DB_DATETIME, $dates['max']),
			'min_transaction_date' => date(DB_DATETIME, $dates['min']),
			'total_completes' => $completes_count
		)), true, array('status', 'max_transaction_date', 'min_transaction_date', 'total_completes'));
		
		$this->out('Import completed');
	}
	
	public function import_personaly() {
		ini_set('memory_limit', '1024M');
		$data = $this->pre_import(RECONCILE_PERSONALY);
		if (!$data) {
			return;
		}
		
		foreach ($data as $key => $row) {
			$reconciliation_row = $this->ClientParser->parseCsvRow($row);
			if (empty($reconciliation_row['user_id'])) {
				unset($data[$key]);
				continue;
			}

			$needs_reconciliation = $this->ClientParser->needsReconciliationFlag($reconciliation_row);
			$count = $this->User->find('count', array(
				'conditions' => array(
					'User.id' => $reconciliation_row['user_id'],
					'User.deleted_on' => null
				),
				'recursive' => -1
			));
			if ($count == 0 || $reconciliation_row['amount'] <= 0) {
				$this->Reconciliations->write_log($this->args[0], 'import', 'Reconciliation row skipped, either because the user is not found, or the offer amount is 0');
				unset($data[$key]);
				continue;
			}
			
			$this->ReconciliationRow->create();
			$this->ReconciliationRow->save(array('ReconciliationRow' => array(
				'reconciliation_id' => $this->reconciliation['Reconciliation']['id'],
				'user_id' => $reconciliation_row['user_id'],
				'offer_id' => isset($reconciliation_row['offer_id']) ? $reconciliation_row['offer_id'] : null,
				'timestamp' => date(DB_DATETIME, $reconciliation_row['timestamp']),
				'offer_amount' => $reconciliation_row['amount'],
				'xoid' => isset($reconciliation_row['xoid']) ? $reconciliation_row['xoid'] : null,
				'xtid' => isset($reconciliation_row['xtid']) ? $reconciliation_row['xtid'] : null,
				'needs_reconciliation' => $needs_reconciliation,
				'processed' => false, 
			)));
		}
		
		$dates = $this->ClientParser->getMinMaxDates($data); 
		$this->Reconciliation->create();
		$this->Reconciliation->save(array('Reconciliation' => array(
			'id' => $this->reconciliation['Reconciliation']['id'],
			'status' => RECONCILIATION_IMPORTED,
			'max_transaction_date' => $dates['max_date'],
			'min_transaction_date' => $dates['min_date']
		)), true, array('status', 'max_transaction_date', 'min_transaction_date'));
		
		$this->out('Import completed');
	}
	
	public function import_adwall() {
		ini_set('memory_limit', '1024M');
		$data = $this->pre_import(RECONCILE_ADWALL);
		if (!$data) {
			return;
		}
		
		foreach ($data as $key => $row) {
			$reconciliation_row = $this->ClientParser->parseCsvRow($row);
			if (empty($reconciliation_row['user_id'])) {
				unset($data[$key]);
				continue;
			}

			$needs_reconciliation = $this->ClientParser->needsReconciliationFlag($reconciliation_row);
			$count = $this->User->find('count', array(
				'conditions' => array(
					'User.id' => $reconciliation_row['user_id'],
					'User.deleted_on' => null
				),
				'recursive' => -1
			));
			if ($count == 0 || $reconciliation_row['amount'] <= 0) {
				$this->Reconciliations->write_log($this->args[0], 'import', 'Reconciliation row skipped, either because the user is not found, or the offer amount is 0');
				unset($data[$key]);
				continue;
			}
			
			$this->ReconciliationRow->create();
			$this->ReconciliationRow->save(array('ReconciliationRow' => array(
				'reconciliation_id' => $this->reconciliation['Reconciliation']['id'],
				'user_id' => $reconciliation_row['user_id'],
				'offer_id' => isset($reconciliation_row['offer_id']) ? $reconciliation_row['offer_id'] : null,
				'timestamp' => date(DB_DATETIME, $reconciliation_row['timestamp']),
				'offer_amount' => $reconciliation_row['amount'],
				'xoid' => isset($reconciliation_row['xoid']) ? $reconciliation_row['xoid'] : null,
				'xtid' => isset($reconciliation_row['xtid']) ? $reconciliation_row['xtid'] : null,
				'needs_reconciliation' => $needs_reconciliation,
				'processed' => false, 
			)));
		}
		
		$dates = $this->ClientParser->getMinMaxDates($data); 
		$this->Reconciliation->create();
		$this->Reconciliation->save(array('Reconciliation' => array(
			'id' => $this->reconciliation['Reconciliation']['id'],
			'status' => RECONCILIATION_IMPORTED,
			'max_transaction_date' => $dates['max_date'],
			'min_transaction_date' => $dates['min_date']
		)), true, array('status', 'max_transaction_date', 'min_transaction_date'));
		
		$this->out('Import completed');
	}
	
	public function import_offertoro() {
		ini_set('memory_limit', '1024M');
		$data = $this->pre_import(RECONCILE_OFFERTORO);
		if (!$data) {
			return;
		}
		
		foreach ($data as $key => $row) {
			$reconciliation_row = $this->ClientParser->parseCsvRow($row);
			if (empty($reconciliation_row['user_id'])) {
				unset($data[$key]);
				continue;
			}

			$needs_reconciliation = $this->ClientParser->needsReconciliationFlag($reconciliation_row);
			$count = $this->User->find('count', array(
				'conditions' => array(
					'User.id' => $reconciliation_row['user_id'],
					'User.deleted_on' => null
				),
				'recursive' => -1
			));
			if ($count == 0 || $reconciliation_row['amount'] <= 0) {
				$this->Reconciliations->write_log($this->args[0], 'import', 'Reconciliation row skipped, either because the user is not found, or the offer amount is 0');
				unset($data[$key]);
				continue;
			}
			
			$this->ReconciliationRow->create();
			$this->ReconciliationRow->save(array('ReconciliationRow' => array(
				'reconciliation_id' => $this->reconciliation['Reconciliation']['id'],
				'user_id' => $reconciliation_row['user_id'],
				'offer_id' => isset($reconciliation_row['offer_id']) ? $reconciliation_row['offer_id'] : null,
				'timestamp' => date(DB_DATETIME, $reconciliation_row['timestamp']),
				'offer_amount' => $reconciliation_row['amount'],
				'xoid' => isset($reconciliation_row['xoid']) ? $reconciliation_row['xoid'] : null,
				'xtid' => isset($reconciliation_row['xtid']) ? $reconciliation_row['xtid'] : null,
				'needs_reconciliation' => $needs_reconciliation,
				'processed' => false, 
			)));
		}
		
		$dates = $this->ClientParser->getMinMaxDates($data); 
		$this->Reconciliation->create();
		$this->Reconciliation->save(array('Reconciliation' => array(
			'id' => $this->reconciliation['Reconciliation']['id'],
			'status' => RECONCILIATION_IMPORTED,
			'max_transaction_date' => $dates['max_date'],
			'min_transaction_date' => $dates['min_date']
		)), true, array('status', 'max_transaction_date', 'min_transaction_date'));
		
		$this->out('Import completed');
	}
	
	public function import_ssi() {
		ini_set('memory_limit', '1024M');
		$data = $this->pre_import(RECONCILE_SSI);
		if (!$data) {
			return;
		}
		
		$completes_count = 0;
		$dates = array('min' => '', 'max' => '');
		foreach ($data as $key => $row) {
			// We only import approved completes from partners
			if ($row[$this->ClientParser->indexes['status']] != 'Complete') {
				continue;
			}
			
			$needs_reconciliation = false;
			$reconciliation_row = $this->ClientParser->parseCsvRow($row);
			$transaction_id = $this->ClientParser->find_transaction($reconciliation_row);
			$user_id = $reconciliation_row['user_id'];
			
			// if we do not find the relevent survey complete in parseCsvRow()
			if ($user_id == '0') {
				
				// try to find the relevent surveyVisit (other then complete) record during that time - that could be the missing complete
				$user_id = $this->ClientParser->missing_user($reconciliation_row);
				if ($user_id) {
					$count = $this->User->find('count', array(
						'conditions' => array(
							'User.id' => $user_id,
							'User.deleted_on' => null
						),
						'recursive' => -1
					));
					if ($count == 0) {
						$this->Reconciliations->write_log($this->args[0], 'import.missing.user', array(
							'hash' => $reconciliation_row['hash'],
							'project_id' => $reconciliation_row['survey_id'],
							'user_id' => $user_id,
							'timestamp' => $reconciliation_row['timestamp'],
							'description' => 'User deleted, so reconciliation is not needed.'
						));
					}
					elseif ($transaction_id) {
						$this->Reconciliations->write_log($this->args[0], 'missing.complete.paid', array(
							'hash' => $reconciliation_row['hash'],
							'user_id' => $user_id,
							'project_id' => $reconciliation_row['survey_id'],
							'timestamp' => $reconciliation_row['timestamp'],
							'description' => 'Missing complete already paid. Transaction #'.$transaction_id
						));
					}
					else {
						$this->Reconciliations->write_log($this->args[0], 'missing.complete.unpaid', array(
							'hash' => $reconciliation_row['hash'],
							'user_id' => $user_id,
							'project_id' => $reconciliation_row['survey_id'],
							'timestamp' => $reconciliation_row['timestamp'],
							'description' => 'Missing complete identified.'
						));
						$needs_reconciliation = true;
					}
				}
				else {
					$this->Reconciliations->write_log($this->args[0], 'import.missing.complete', array(
						'hash' => $reconciliation_row['hash'],
						'project_id' => $reconciliation_row['survey_id'],
						'timestamp' => $reconciliation_row['timestamp'],
						'description' => 'Missing complete could not be identified'
					));
				}
			}
			
			// make sure the panelist get the payout even if the SurveyVist complete is found
			if (!$needs_reconciliation && !$transaction_id && $user_id > 0) {
				$needs_reconciliation = true;
				$this->Reconciliations->write_log($this->args[0], 'missing.complete.unpaid', array(
					'hash' => $reconciliation_row['hash'],
					'user_id' => $user_id,
					'project_id' => $reconciliation_row['survey_id'],
					'timestamp' => $reconciliation_row['timestamp'],
					'description' => 'Complete reported in MintVine, but not paid.'
				));
			}
			
			$this->ReconciliationRow->create();
			$this->ReconciliationRow->save(array('ReconciliationRow' => array(
				'reconciliation_id' => $this->reconciliation['Reconciliation']['id'],
				'user_id' => $user_id,
				'hash' => $reconciliation_row['hash'],
				'survey_id' => $reconciliation_row['survey_id'],
				'timestamp' => $reconciliation_row['timestamp'],
				'needs_reconciliation' => $needs_reconciliation,
				'transaction_id' => $transaction_id, // in case the complete is already paid.
			)));
			
			$completes_count++;
			$dates = $this->ClientParser->getMinMaxDates($dates, $reconciliation_row['timestamp']);
		}
		
		$this->Reconciliation->create();
		$this->Reconciliation->save(array('Reconciliation' => array(
			'id' => $this->reconciliation['Reconciliation']['id'],
			'status' => RECONCILIATION_IMPORTED,
			'max_transaction_date' => date(DB_DATETIME, $dates['max']),
			'min_transaction_date' => date(DB_DATETIME, $dates['min']),
			'total_completes' => $completes_count
		)), true, array('status', 'max_transaction_date', 'min_transaction_date', 'total_completes'));
		
		$this->out('Import completed');
		
		$query = ROOT.'/app/Console/cake reconciliation find_extra_completes '.$this->reconciliation['Reconciliation']['id'];
		$query.= " > /dev/null &"; 
		exec($query, $output);
	}
	
	public function import_peanutlabs() {
		ini_set('memory_limit', '1024M');
		$data = $this->pre_import(RECONCILE_PEANUTLABS);
		if (!$data) {
			return;
		}
		
		foreach ($data as $key => $row) {
			$reconciliation_row = $this->ClientParser->parseCsvRow($row);
			if (empty($reconciliation_row['user_id'])) {
				unset($data[$key]);
				continue;
			}

			$needs_reconciliation = $this->ClientParser->needsReconciliationFlag($reconciliation_row);
			$count = $this->User->find('count', array(
				'conditions' => array(
					'User.id' => $reconciliation_row['user_id'],
					'User.deleted_on' => null
				),
				'recursive' => -1
			));
			if ($count == 0 || $reconciliation_row['amount'] <= 0) {
				$this->Reconciliations->write_log($this->args[0], 'import', 'Reconciliation row skipped, either because the user is not found, or the offer amount is 0');
				unset($data[$key]);
				continue;
			}
			
			$this->ReconciliationRow->create();
			$this->ReconciliationRow->save(array('ReconciliationRow' => array(
				'reconciliation_id' => $this->reconciliation['Reconciliation']['id'],
				'user_id' => $reconciliation_row['user_id'],
				'offer_id' => isset($reconciliation_row['offer_id']) ? $reconciliation_row['offer_id'] : null,
				'timestamp' => date(DB_DATETIME, $reconciliation_row['timestamp']),
				'offer_amount' => $reconciliation_row['amount'],
				'xoid' => isset($reconciliation_row['xoid']) ? $reconciliation_row['xoid'] : null,
				'xtid' => isset($reconciliation_row['xtid']) ? $reconciliation_row['xtid'] : null,
				'needs_reconciliation' => $needs_reconciliation,
				'processed' => false, 
			)));
		}
		
		$dates = $this->ClientParser->getMinMaxDates($data); 
		$this->Reconciliation->create();
		$this->Reconciliation->save(array('Reconciliation' => array(
			'id' => $this->reconciliation['Reconciliation']['id'],
			'status' => RECONCILIATION_IMPORTED,
			'max_transaction_date' => $dates['max_date'],
			'min_transaction_date' => $dates['min_date']
		)), true, array('status', 'max_transaction_date', 'min_transaction_date'));
		
		$this->out('Import completed');
	}
	
	public function import_adgate() {
		ini_set('memory_limit', '1024M');
		$data = $this->pre_import(RECONCILE_ADGATE);
		if (!$data) {
			return;
		}
		
		foreach ($data as $key => $row) {
			$reconciliation_row = $this->ClientParser->parseCsvRow($row);
			if (empty($reconciliation_row['user_id'])) {
				unset($data[$key]);
				continue;
			}

			$needs_reconciliation = $this->ClientParser->needsReconciliationFlag($reconciliation_row);
			$count = $this->User->find('count', array(
				'conditions' => array(
					'User.id' => $reconciliation_row['user_id'],
					'User.deleted_on' => null
				),
				'recursive' => -1
			));
			if ($count == 0 || $reconciliation_row['amount'] <= 0) {
				$this->Reconciliations->write_log($this->args[0], 'import', 'Reconciliation row skipped, either because the user is not found, or the offer amount is 0');
				unset($data[$key]);
				continue;
			}
			
			$this->ReconciliationRow->create();
			$this->ReconciliationRow->save(array('ReconciliationRow' => array(
				'reconciliation_id' => $this->reconciliation['Reconciliation']['id'],
				'user_id' => $reconciliation_row['user_id'],
				'offer_id' => isset($reconciliation_row['offer_id']) ? $reconciliation_row['offer_id'] : null,
				'timestamp' => date(DB_DATETIME, $reconciliation_row['timestamp']),
				'offer_amount' => $reconciliation_row['amount'],
				'xoid' => isset($reconciliation_row['xoid']) ? $reconciliation_row['xoid'] : null,
				'xtid' => isset($reconciliation_row['xtid']) ? $reconciliation_row['xtid'] : null,
				'needs_reconciliation' => $needs_reconciliation,
				'processed' => false, 
			)));
		}
		
		$dates = $this->ClientParser->getMinMaxDates($data); 
		$this->Reconciliation->create();
		$this->Reconciliation->save(array('Reconciliation' => array(
			'id' => $this->reconciliation['Reconciliation']['id'],
			'status' => RECONCILIATION_IMPORTED,
			'max_transaction_date' => $dates['max_date'],
			'min_transaction_date' => $dates['min_date']
		)), true, array('status', 'max_transaction_date', 'min_transaction_date'));
		
		$this->out('Import completed');
	}
	
	public function import_lucid() {
		ini_set('memory_limit', '1024M');
		$data = $this->pre_import(RECONCILE_LUCID);
		if (!$data) {
			return;
		}
		
		$completes_count = 0;
		$dates = array('min' => '', 'max' => '');
		foreach ($data as $row) {
			$reconciliation_row = $this->ClientParser->parseCsvRow($row);
			$needs_reconciliation = false;
			$transaction_id = $this->ClientParser->find_transaction($reconciliation_row);
			$user_id = $reconciliation_row['user_id'];
			
			// if we do not find the relevent survey complete in parseCsvRow()
			if ($user_id == '0') {
				
				// try to find the relevent surveyVisit (other then complete) record during that time - that could be the missing complete
				$user_id = $this->ClientParser->missing_user($reconciliation_row);
				if ($user_id) {
					$count = $this->User->find('count', array(
						'conditions' => array(
							'User.id' => $user_id,
							'User.deleted_on' => null
						),
						'recursive' => -1
					));
					if ($count == 0) {
						$this->Reconciliations->write_log($this->args[0], 'import.missing.user', array(
							'hash' => $reconciliation_row['hash'],
							'project_id' => $reconciliation_row['survey_id'],
							'user_id' => $user_id,
							'timestamp' => $reconciliation_row['timestamp'],
							'description' => 'User deleted, so reconciliation is not needed.'
						));
					}
					elseif ($transaction_id) {
						$this->Reconciliations->write_log($this->args[0], 'missing.complete.paid', array(
							'hash' => $reconciliation_row['hash'],
							'user_id' => $user_id,
							'project_id' => $reconciliation_row['survey_id'],
							'timestamp' => $reconciliation_row['timestamp'],
							'description' => 'Missing complete already paid. Transaction #'.$transaction_id
						));
					}
					else {
						$needs_reconciliation = true;
					}
				}
				else {
					$this->Reconciliations->write_log($this->args[0], 'import.missing.complete', array(
						'hash' => $reconciliation_row['hash'],
						'project_id' => $reconciliation_row['survey_id'],
						'timestamp' => $reconciliation_row['timestamp'],
						'description' => 'Missing complete could not be identified'
					));
				}
			}
			
			// make sure the panelist got the payout even if the SurveyVist complete is found
			if (!$needs_reconciliation && !$transaction_id && $user_id > 0) {
				$needs_reconciliation = true;
				$this->Reconciliations->write_log($this->args[0], 'missing.complete.unpaid', array(
					'hash' => $reconciliation_row['hash'],
					'user_id' => $user_id,
					'project_id' => $reconciliation_row['survey_id'],
					'timestamp' => $reconciliation_row['timestamp'],
					'description' => 'Complete reported in MintVine, but not paid.'
				));
			}
			
			$this->ReconciliationRow->create();
			$this->ReconciliationRow->save(array('ReconciliationRow' => array(
				'reconciliation_id' => $this->reconciliation['Reconciliation']['id'],
				'user_id' => $user_id,
				'hash' => $reconciliation_row['hash'],
				'survey_id' => $reconciliation_row['survey_id'],
				'timestamp' => $reconciliation_row['timestamp'],
				'needs_reconciliation' => $needs_reconciliation,
				'transaction_id' => $transaction_id, // in case the complete is already paid.
			)));
			
			$completes_count++;
			$dates = $this->ClientParser->getMinMaxDates($dates, $reconciliation_row['timestamp']);
		}
		
		$this->Reconciliation->create();
		$this->Reconciliation->save(array('Reconciliation' => array(
			'id' => $this->reconciliation['Reconciliation']['id'],
			'status' => RECONCILIATION_IMPORTED,
			'max_transaction_date' => date(DB_DATETIME, $dates['max']),
			'min_transaction_date' => date(DB_DATETIME, $dates['min']),
			'total_completes' => $completes_count
		)), true, array('status', 'max_transaction_date', 'min_transaction_date', 'total_completes'));
		
		$this->out('Import completed');
		
		$query = ROOT.'/app/Console/cake reconciliation find_extra_completes '.$this->reconciliation['Reconciliation']['id'];
		$query.= " > /dev/null &"; 
		exec($query, $output);
	}
	
	public function import_toluna() {
		ini_set('memory_limit', '1024M');
		$data = $this->pre_import(RECONCILE_TOLUNA);
		if (!$data) {
			return;
		}
		
		$completes_count = 0;
		$dates = array('min' => '', 'max' => '');
		foreach ($data as $key => $row) {
			$reconciliation_row = $this->ClientParser->parseCsvRow($row);
			$needs_reconciliation = false;
			
			// check user first
			$user_count = $this->User->find('count', array(
				'conditions' => array(
					'User.id' => $reconciliation_row['user_id'],
					'User.deleted_on' => null
				),
				'recursive' => -1
			));
			if ($user_count == 0) {
				$needs_reconciliation = false;
				$this->Reconciliations->write_log($this->args[0], 'import.missing.user', array(
					'project_id' => $reconciliation_row['survey_id'],
					'user_id' => $reconciliation_row['user_id'],
					'timestamp' => $reconciliation_row['timestamp'],
					'description' => 'User deleted, so reconciliation is not needed.'
				));
			}
			else {
				$transaction_id = $this->ClientParser->find_transaction($reconciliation_row);
				
				// if we do not find the complete
				if (empty($reconciliation_row['hash'])) {
					if (empty($reconciliation_row['survey_id'])) {
						$this->Reconciliations->write_log($this->args[0], 'import.missing.project', array(
							'user_id' => $reconciliation_row['user_id'],
							'timestamp' => $reconciliation_row['timestamp'],
							'description' => 'Project not found'
						));
					}
					elseif ($transaction_id) {
						$this->Reconciliations->write_log($this->args[0], 'missing.complete.paid', array(
							'user_id' => $reconciliation_row['user_id'],
							'project_id' => $reconciliation_row['survey_id'],
							'timestamp' => $reconciliation_row['timestamp'],
							'description' => 'Missing complete already paid. Transaction #'.$transaction_id
						));
					}
					else {
						$needs_reconciliation = true;
					}
				}
				elseif (!$transaction_id) { // if complete is found, but transaction is not found, mark it as missing complete
					$needs_reconciliation = true;
					$this->Reconciliations->write_log($this->args[0], 'missing.complete.unpaid', array(
						'hash' => $reconciliation_row['hash'],
						'user_id' => $reconciliation_row['user_id'],
						'project_id' => $reconciliation_row['survey_id'],
						'timestamp' => $reconciliation_row['timestamp'],
						'description' => 'Complete reported in MintVine, but not paid.'
					));
				}
			}
			
			$this->ReconciliationRow->create();
			$this->ReconciliationRow->save(array('ReconciliationRow' => array(
				'reconciliation_id' => $this->reconciliation['Reconciliation']['id'],
				'user_id' => $reconciliation_row['user_id'],
				'hash' => $reconciliation_row['hash'],
				'survey_id' => $reconciliation_row['survey_id'],
				'transaction_id' => $transaction_id,
				'timestamp' => $reconciliation_row['timestamp'],
				'needs_reconciliation' => $needs_reconciliation,
			)));
			
			$completes_count++;
			$dates = $this->ClientParser->getMinMaxDates($dates, $reconciliation_row['timestamp']);
		}
		
		$this->Reconciliation->create();
		$this->Reconciliation->save(array('Reconciliation' => array(
			'id' => $this->reconciliation['Reconciliation']['id'],
			'status' => RECONCILIATION_IMPORTED,
			'max_transaction_date' => date(DB_DATETIME, $dates['max']),
			'min_transaction_date' => date(DB_DATETIME, $dates['min']),
			'total_completes' => $completes_count
		)), true, array('status', 'max_transaction_date', 'min_transaction_date', 'total_completes'));
		$this->out('Import completed');
		
		// Activate finding the extra completes for toluna now, as they have the correct timestamp in the completes file now.
		$query = ROOT.'/app/Console/cake reconciliation find_extra_completes '.$this->reconciliation['Reconciliation']['id'];
		$query.= " > /dev/null &"; 
		exec($query, $output);
	}
	
	public function import_precision() {
		ini_set('memory_limit', '1024M');
		$data = $this->pre_import(RECONCILE_PRECISION);
		if (!$data) {
			return;
		}
		
		$completes_count = 0;
		$dates = array('min' => '', 'max' => '');
		foreach ($data as $row) {
			$reconciliation_row = $this->ClientParser->parseCsvRow($row);
			$needs_reconciliation = false;
			$transaction_id = null;
			
			// if survey complete not found in parseCsvRow()
			if (empty($reconciliation_row['hash'])) {
				if (empty($reconciliation_row['survey_id'])) {
					$this->Reconciliations->write_log($this->args[0], 'import.missing.project', array(
						'timestamp' => $reconciliation_row['timestamp'],
						'user_id' => $reconciliation_row['user_id'],
						'description' => 'Precision project #' . $reconciliation_row['precision_project_id'] . ' not found'
					));
				}
				elseif ($transaction_id = $this->ClientParser->find_transaction($reconciliation_row)) {
					$this->Reconciliations->write_log($this->args[0], 'missing.complete.paid', array(
						'user_id' => $reconciliation_row['user_id'],
						'project_id' => $reconciliation_row['survey_id'],
						'timestamp' => $reconciliation_row['timestamp'],
						'description' => 'Missing complete already paid. Transaction #'.$transaction_id
					));
				}
				else {
					$count = $this->User->find('count', array(
						'conditions' => array(
							'User.id' => $reconciliation_row['user_id'],
							'User.deleted_on' => null
						),
						'recursive' => -1
					));
					if ($count == 0) {
						$this->Reconciliations->write_log($this->args[0], 'import.missing.user', array(
							'project_id' => $reconciliation_row['survey_id'],
							'user_id' => $reconciliation_row['user_id'],
							'timestamp' => $reconciliation_row['timestamp'],
							'description' => 'User deleted, so reconciliation is not needed.'
						));
					}
					else {
						$needs_reconciliation = true;
					}
				}
			}
			
			$this->ReconciliationRow->create();
			$this->ReconciliationRow->save(array('ReconciliationRow' => array(
				'reconciliation_id' => $this->reconciliation['Reconciliation']['id'],
				'user_id' => $reconciliation_row['user_id'],
				'transaction_id' => $transaction_id, // in case the complete is already paid.
				'hash' => $reconciliation_row['hash'],
				'survey_id' => $reconciliation_row['survey_id'],
				'timestamp' => $reconciliation_row['timestamp'],
				'needs_reconciliation' => $needs_reconciliation,
			)));
			
			$completes_count++;
			$dates = $this->ClientParser->getMinMaxDates($dates, $reconciliation_row['timestamp']);
		}
		
		$this->Reconciliation->create();
		$this->Reconciliation->save(array('Reconciliation' => array(
			'id' => $this->reconciliation['Reconciliation']['id'],
			'status' => RECONCILIATION_IMPORTED,
			'max_transaction_date' => date(DB_DATETIME, $dates['max']),
			'min_transaction_date' => date(DB_DATETIME, $dates['min']),
			'total_completes' => $completes_count
		)), true, array('status', 'max_transaction_date', 'min_transaction_date', 'total_completes'));
		
		$this->out('Import completed');
		
		$query = ROOT.'/app/Console/cake reconciliation find_extra_completes '.$this->reconciliation['Reconciliation']['id'];
		$query.= " > /dev/null &"; 
		exec($query, $output);
	}
	
	public function import_spectrum() {
		ini_set('memory_limit', '1024M');
		$data = $this->pre_import(RECONCILE_SPECTRUM);
		if (!$data) {
			return;
		}
		
		$completes_count = 0;
		$dates = array('min' => '', 'max' => '');
		foreach ($data as $key => $row) {
			
			// We only import approved completes from partners
			if ($row[$this->ClientParser->indexes['status']] != 'Complete') {
				continue;
			}
			
			$reconciliation_row = $this->ClientParser->parseCsvRow($row);
			$needs_reconciliation = false;
			$transaction_id = null;
			
			$count = $this->User->find('count', array(
				'conditions' => array(
					'User.id' => $reconciliation_row['user_id'],
					'User.deleted_on' => null
				),
				'recursive' => -1
			));
			if ($count == 0) {
				$this->Reconciliations->write_log($this->args[0], 'import.missing.user', array(
					'hash' => $reconciliation_row['hash'],
					'project_id' => $reconciliation_row['survey_id'],
					'user_id' => $reconciliation_row['user_id'],
					'timestamp' => $reconciliation_row['timestamp'],
					'description' => 'User deleted, so reconciliation is not needed'
				));
			}
			else {
				$needs_reconciliation = $this->ClientParser->needsReconciliationFlag($reconciliation_row);
				
				// if we don't find the complete, find if we have any transaction for this complete
				if ($needs_reconciliation) {
					$transaction_id = $this->ClientParser->find_transaction($reconciliation_row);
					if ($transaction_id) {
						$needs_reconciliation = false;
						$this->Reconciliations->write_log($this->args[0], 'missing.complete.paid', array(
							'user_id' => $reconciliation_row['user_id'],
							'hash' => $reconciliation_row['hash'],
							'project_id' => $reconciliation_row['survey_id'],
							'timestamp' => $reconciliation_row['timestamp'],
							'description' => 'Missing complete already paid. Transaction #'.$transaction_id
						));
					}
				}
			}
			
			$this->ReconciliationRow->create();
			$this->ReconciliationRow->save(array('ReconciliationRow' => array(
				'reconciliation_id' => $this->reconciliation['Reconciliation']['id'],
				'user_id' => $reconciliation_row['user_id'],
				'hash' => $reconciliation_row['hash'],
				'survey_id' => $reconciliation_row['survey_id'],
				'transaction_id' => $transaction_id, // in case the complete is already paid.
				'timestamp' => $reconciliation_row['timestamp'],
				'needs_reconciliation' => $needs_reconciliation,
				'processed' => false, 
			)));
			
			$completes_count++;
			$dates = $this->ClientParser->getMinMaxDates($dates, $reconciliation_row['timestamp']);
		}
		
		$this->Reconciliation->create();
		$this->Reconciliation->save(array('Reconciliation' => array(
			'id' => $this->reconciliation['Reconciliation']['id'],
			'status' => RECONCILIATION_IMPORTED,
			'max_transaction_date' => date(DB_DATETIME, $dates['max']),
			'min_transaction_date' => date(DB_DATETIME, $dates['min']),
			'total_completes' => $completes_count
		)), true, array('status', 'max_transaction_date', 'min_transaction_date', 'total_completes'));
		
		$this->out('Import completed');
		$query = ROOT . '/app/Console/cake reconciliation find_extra_completes ' . $this->reconciliation['Reconciliation']['id'];
		$query.= " > /dev/null &";
		exec($query, $output);
	}
	
	public function find_extra_completes() {
		ini_set('memory_limit', '1024M');
		$reconciliation = $this->Reconciliation->find('first', array(
			'conditions' => array(
				'Reconciliation.id' => $this->args[0]
			)
		));
		if (!$reconciliation) {
			$this->out('[ERROR] Reconciliation #'.$this->args[0]. ' not found.');
			return;
		}
		
		// Sanity check to avoid any big comparison, that can timeout
		if ((strtotime($reconciliation['Reconciliation']['max_transaction_date']) - strtotime($reconciliation['Reconciliation']['min_transaction_date'])) > 7776000) {
			$this->out('Reconciliation interval is more then 90 days.');
			$this->Reconciliations->write_log($this->args[0], 'extra.completes', 'Failed: Min and max transaction date difference is more then 90 days. We can reconcile with a maximum of 90 days interval.');
			return;
		}
		
		$group_mappings = array(
			RECONCILE_POINTS2SHOP => 'p2s',
			RECONCILE_SSI => 'ssi',
			RECONCILE_LUCID => 'fulcrum',
			RECONCILE_TOLUNA => 'toluna',
			RECONCILE_PRECISION => 'precision',
			RECONCILE_CINT => 'cint',
			RECONCILE_SPECTRUM => 'spectrum',
		);
		
		if ($reconciliation['Reconciliation']['status'] != RECONCILIATION_IMPORTED) {
			$this->Reconciliations->write_log($this->args[0], 'extra.completes', 'Reconciliation status should be '.RECONCILIATION_IMPORTED . '(status:'.$reconciliation['Reconciliation']['status'].')');
			return;
		}
		
		$reconciliation_rows = $this->ReconciliationRow->find('list', array(
			'fields' => array('ReconciliationRow.hash', 'ReconciliationRow.id'),
			'conditions' => array(
				'ReconciliationRow.reconciliation_id' => $reconciliation['Reconciliation']['id']
			),
			'recursive' => -1
		));
		if (!$reconciliation_rows) {
			$this->out('Reconciliation rows not found.');
			$this->Reconciliations->write_log($this->args[0], 'extra.completes', 'Reconciliation rows not found.');
			return;
		}
		
		$group = $this->Group->find('first', array(
			'conditions' => array(
				'Group.key' => $group_mappings[$reconciliation['Reconciliation']['type']]
			)
		));
		$start_date = date(DB_DATETIME, strtotime($reconciliation['Reconciliation']['min_transaction_date']) - 3600);
		$end_date = date(DB_DATETIME, strtotime($reconciliation['Reconciliation']['max_transaction_date']) + 3600);
		$projects = $this->Project->find('list', array(
			'fields' => array('id'),
			'conditions' => array(
				'Project.group_id' => $group['Group']['id'],
				'OR' => array(
					// projects started before and ended after selected dates
					array(
						'Project.started <=' => $start_date,
						'Project.ended >=' => $start_date
					),
					// projects started and ended during the duration of the selected date
					array(
						'Project.started >=' => $start_date,
						'Project.ended <=' => $end_date
					),
					// projects started before the end date but ending much later
					array(
						'Project.started <=' => $end_date,
						'Project.ended >=' => $end_date
					),
					// projects that are still open
					array(
						'Project.started <=' => $end_date,
						'Project.ended is null'
					)
				)
			)
		));
		if (!$projects) {
			$this->out('projects not found.');
			$this->Reconciliations->write_log($this->args[0], 'extra.completes', 'MV projects not found btw '. $reconciliation['Reconciliation']['min_transaction_date']. ' and '.$reconciliation['Reconciliation']['max_transaction_date']);
			return;
		}
		
		$this->loadModel('Partner');
		$partner = $this->Partner->find('first', array(
			'conditions' => array(
				'Partner.key' => array('mintvine'),
				'Partner.deleted' => false
			),
			'fields' => array('Partner.id')
		));
		
		$survey_completes = $this->SurveyVisit->find('all', array(
			'conditions' => array(
				'SurveyVisit.survey_id' => $projects,
				'SurveyVisit.partner_id' => $partner['Partner']['id'],
				'SurveyVisit.type' => array(SURVEY_COMPLETED, SURVEY_DUPE),
				'SurveyVisit.created >=' => $reconciliation['Reconciliation']['min_transaction_date'],
				'SurveyVisit.created <=' => $reconciliation['Reconciliation']['max_transaction_date']
			)
		));
		
		if (!$survey_completes) {
			$this->out('Survey visits not found.');
			$this->Reconciliations->write_log($this->args[0], 'extra.completes', 'MV survey visits not found btw '. $reconciliation['Reconciliation']['min_transaction_date']. ' and '.$reconciliation['Reconciliation']['max_transaction_date']);
			return;
		}
		
		$i = 0;
		$survey_users = array();
		foreach ($survey_completes as $survey_complete) {
			$user_id = explode('-', $survey_complete['SurveyVisit']['partner_user_id']);
			$user_id = isset($user_id[1]) ? $user_id[1] : false;
			if (!$user_id) {
				continue;
			}
			
			if ($survey_complete['SurveyVisit']['type'] == SURVEY_DUPE) {
				$survey_user = $survey_complete['SurveyVisit']['survey_id'].'-'.$user_id;
				
				// make sure we execute on survey user once only
				if (in_array($survey_user, $survey_users)) {
					continue;
				}
				
				$survey_users[] = $survey_user;
				$dupe_transactions = $this->Transaction->find('list', array(
					'fields' => array('Transaction.id'),
					'conditions' => array(
						'Transaction.type_id' => TRANSACTION_SURVEY,
						'Transaction.linked_to_id' => $survey_complete['SurveyVisit']['survey_id'],
						'Transaction.user_id' => $user_id,
						'Transaction.deleted' => null,
					)
				));
				if (count($dupe_transactions) < 1) {
					continue;
				}
				
				// if partner have provided the complete hash, we keep one transaction and mark other as dupes
				if (isset($reconciliation_rows[$survey_complete['SurveyVisit']['hash']])) {
					array_shift($dupe_transactions);
				}
				
				if (empty($dupe_transactions)) {
					continue;
				}

				foreach ($dupe_transactions as $transaction_id) {
					$this->ExtraComplete->create();
					$this->ExtraComplete->save(array('ExtraComplete' => array(
						'reconciliation_id' => $reconciliation['Reconciliation']['id'],
						'user_id' => $user_id,
						'transaction_id' => $transaction_id,
						'hash' =>  $survey_complete['SurveyVisit']['hash'],
						'survey_id' => $survey_complete['SurveyVisit']['survey_id'],
						'description' => 'Dupe complete transaction found',
						'timestamp' => $survey_complete['SurveyVisit']['created'],
					)));

					$this->Reconciliations->write_log($reconciliation['Reconciliation']['id'], 'extra.complete.dupe', array(
						'hash' => $survey_complete['SurveyVisit']['hash'],
						'project_id' => $survey_complete['SurveyVisit']['survey_id'],
						'user_id' => $user_id,
						'timestamp' => $survey_complete['SurveyVisit']['created'],
						'description' => 'Dupe complete transaction found'
					));
					$i++;
				}
				
				continue;
			}
			
			if (isset($reconciliation_rows[$survey_complete['SurveyVisit']['hash']])) {
				continue;
			}
			
			$transaction = $this->Transaction->find('first', array(
				'fields' => array('Transaction.id'),
				'conditions' => array(
					'Transaction.type_id' => TRANSACTION_SURVEY,
					'Transaction.linked_to_id' => $survey_complete['SurveyVisit']['survey_id'],
					'Transaction.user_id' => $user_id,
					'Transaction.deleted' => null,
				)
			));
			if (!$transaction) {
				continue;
			}
			
			$i++;
			$this->ExtraComplete->create();
			$this->ExtraComplete->save(array('ExtraComplete' => array(
				'reconciliation_id' => $reconciliation['Reconciliation']['id'],
				'user_id' => $user_id,
				'transaction_id' => $transaction['Transaction']['id'],
				'hash' =>  $survey_complete['SurveyVisit']['hash'],
				'survey_id' => $survey_complete['SurveyVisit']['survey_id'],
				'description' => 'Complete not found in partner provided data',
				'timestamp' => $survey_complete['SurveyVisit']['created'],
			)));
			
			$this->Reconciliations->write_log($reconciliation['Reconciliation']['id'], 'extra.complete', array(
				'hash' => $survey_complete['SurveyVisit']['hash'],
				'project_id' => $survey_complete['SurveyVisit']['survey_id'],
				'user_id' => $user_id,
				'timestamp' => $survey_complete['SurveyVisit']['created'],
				'description' => 'Extra complete found'
			));
		}
		
		$this->Reconciliations->update_status($reconciliation['Reconciliation']['id'], RECONCILIATION_ANALYZED);
		$this->out($i. ' extra completes found!');
		$this->Reconciliations->write_log($this->args[0], 'extra.completes', $i.' extra completes found.');
	}
}
