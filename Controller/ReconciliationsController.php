<?php
App::uses('AppController', 'Controller');

class ReconciliationsController extends AppController {

	public $uses = array('Reconciliation', 'ReconciliationRow', 'ReconciliationLog', 'ExtraComplete', 'SurveyVisit', 'RouterLog', 'Project', 'Transaction', 'Offer', 'Redemption', 'OfferRedemption', 'HistoryRequest');
	public $helpers = array('Html', 'Time');
	public $components = array('Reconciliations'); 
	public $settings = array();
	
	public function beforeFilter() {
		parent::beforeFilter();
		
		set_time_limit(600); // 10 minutes execution time
		
		CakePlugin::load('Uploader');
		App::import('Vendor', 'Uploader.S3');
		
		$this->settings = $this->Setting->find('list', array(
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
		$this->set('settings', $this->settings);
		if ($this->params['action'] == 'reconcile' && isset($this->params['pass'][0]) && in_array($this->params['pass'][0], array('trialpay'))) {
			$this->Session->setFlash('Reconciliation work for Trialpay is in development mode, please try later.', 'flash_error');
			$this->redirect(array('action' => 'index'));
		}
		
		if ($this->params['action'] != 'index') {
			if (!isset($this->settings['s3.access']) || !isset($this->settings['s3.secret']) || !isset($this->settings['s3.bucket'])) {
				$this->Session->setFlash('Missing required S3 settings: s3.access, s3.secret, s3.host, or s3.bucket.', 'flash_error');
				$this->redirect(array('controller' => 'reconciliations', 'action' => 'index'));
			}
		}			
	}
	
	public function index() {
		$conditions = array();
		if (isset($this->request->query['type']) && !empty($this->request->query['type'])) {
			$conditions['Reconciliation.type'] = $this->request->query['type'];
		}
		
		$this->paginate = array(
			'Reconciliation' => array(
				'conditions' => $conditions,
				'limit' => 50,
				'order' => 'Reconciliation.id DESC'
			)
		);
		$reconciliations = $this->paginate('Reconciliation');
		$this->set(compact('reconciliations'));
	}
	
	public function reconcile_points2shop() {
		$type = RECONCILE_POINTS2SHOP;
		$this->set('type', $type);
		if ($this->request->is('post')) {
			if (!empty($this->request->data['Reconciliation']['file']['error'])) {
				$this->Session->setFlash('There was an error in file upload!. '. $this->request->data['Reconciliation']['file']['error'], 'flash_error');
				$this->redirect(array('action' => 'reconcile_'.$type));
			}
			
			$reconciliation_id = $this->Reconciliations->save($this->settings, $this->request->data['Reconciliation']['file'], $type);
			if (!$reconciliation_id) {
				$this->Session->setFlash('There was an error uploading file to S3!.', 'flash_error');
				$this->redirect(array('action' => 'reconcile_'.$type));
			}

			$query = ROOT.'/app/Console/cake reconciliation import_'.$type.' '.$reconciliation_id;
			$query.= " > /dev/null &"; 
			exec($query, $output);
			$this->redirect(array('action' => 'index', '?' => array('type' => $type)));
		}
	}

	public function reconcile_cint() {
		$type = RECONCILE_CINT;
		$this->set('type', $type);
		if ($this->request->is('post')) {
			if (!empty($this->request->data['Reconciliation']['file']['error'])) {
				$this->Session->setFlash('There was an error in file upload!. '. $this->request->data['Reconciliation']['file']['error'], 'flash_error');
				$this->redirect(array('action' => 'reconcile_'.$type));
			}
			
			$reconciliation_id = $this->Reconciliations->save($this->settings, $this->request->data['Reconciliation']['file'], $type);
			if (!$reconciliation_id) {
				$this->Session->setFlash('There was an error uploading file to S3!.', 'flash_error');
				$this->redirect(array('action' => 'reconcile_'.$type));
			}

			$query = ROOT.'/app/Console/cake reconciliation import_'.$type.' '.$reconciliation_id;
			$query.= " > /dev/null &"; 
			exec($query, $output);
			$this->redirect(array('action' => 'index', '?' => array('type' => $type)));
		}
	}
	
	public function reconcile_personaly() {
		$type = RECONCILE_PERSONALY;
		$this->set('type', $type);
		if ($this->request->is('post')) {
			if (!empty($this->request->data['Reconciliation']['file']['error'])) {
				$this->Session->setFlash('There was an error in file upload!. '. $this->request->data['Reconciliation']['file']['error'], 'flash_error');
				$this->redirect(array('action' => 'reconcile_'.$type));
			}
			
			$reconciliation_id = $this->Reconciliations->save($this->settings, $this->request->data['Reconciliation']['file'], $type);
			if (!$reconciliation_id) {
				$this->Session->setFlash('There was an error uploading file to S3!.', 'flash_error');
				$this->redirect(array('action' => 'reconcile_'.$type));
			}

			$query = ROOT.'/app/Console/cake reconciliation import_'.$type.' '.$reconciliation_id;
			$query.= " > /dev/null &"; 
			exec($query, $output);
			$this->redirect(array('action' => 'index', '?' => array('type' => $type)));
		}
	}
	
	public function reconcile_adwall() {
		$type = RECONCILE_ADWALL;
		$this->set('type', $type);
		if ($this->request->is('post')) {
			if (!empty($this->request->data['Reconciliation']['file']['error'])) {
				$this->Session->setFlash('There was an error in file upload!. '. $this->request->data['Reconciliation']['file']['error'], 'flash_error');
				$this->redirect(array('action' => 'reconcile_'.$type));
			}
			
			$reconciliation_id = $this->Reconciliations->save($this->settings, $this->request->data['Reconciliation']['file'], $type);
			if (!$reconciliation_id) {
				$this->Session->setFlash('There was an error uploading file to S3!.', 'flash_error');
				$this->redirect(array('action' => 'reconcile_'.$type));
			}

			$query = ROOT.'/app/Console/cake reconciliation import_'.$type.' '.$reconciliation_id;
			$query.= " > /dev/null &"; 
			exec($query, $output);
			$this->redirect(array('action' => 'index', '?' => array('type' => $type)));
		}
	}
	
	public function reconcile_offertoro() {
		$type = RECONCILE_OFFERTORO;
		$this->set('type', $type);
		if ($this->request->is('post')) {
			if (!empty($this->request->data['Reconciliation']['file']['error'])) {
				$this->Session->setFlash('There was an error in file upload!. '. $this->request->data['Reconciliation']['file']['error'], 'flash_error');
				$this->redirect(array('action' => 'reconcile_'.$type));
			}
			
			$reconciliation_id = $this->Reconciliations->save($this->settings, $this->request->data['Reconciliation']['file'], $type);
			if (!$reconciliation_id) {
				$this->Session->setFlash('There was an error uploading file to S3!.', 'flash_error');
				$this->redirect(array('action' => 'reconcile_'.$type));
			}

			$query = ROOT.'/app/Console/cake reconciliation import_'.$type.' '.$reconciliation_id;
			$query.= " > /dev/null &"; 
			exec($query, $output);
			$this->redirect(array('action' => 'index', '?' => array('type' => $type)));
		}
	}
	
	public function reconcile_ssi() {
		$type = RECONCILE_SSI;
		$this->set('type', $type);
		if ($this->request->is('post')) {
			if (!empty($this->request->data['Reconciliation']['file']['error'])) {
				$this->Session->setFlash('There was an error in file upload!. '. $this->request->data['Reconciliation']['file']['error'], 'flash_error');
				$this->redirect(array('action' => 'reconcile_'.$type));
			}
			
			$reconciliation_id = $this->Reconciliations->save($this->settings, $this->request->data['Reconciliation']['file'], $type);
			if (!$reconciliation_id) {
				$this->Session->setFlash('There was an error uploading file to S3!.', 'flash_error');
				$this->redirect(array('action' => 'reconcile_'.$type));
			}

			$query = ROOT.'/app/Console/cake reconciliation import_'.$type.' '.$reconciliation_id;
			$query.= " > /dev/null &"; 
			exec($query, $output);
			$this->redirect(array('action' => 'index', '?' => array('type' => $type)));
		}
	}
	
	public function reconcile_peanutlabs() {
		$type = RECONCILE_PEANUTLABS;
		$this->set('type', $type);
		if ($this->request->is('post')) {
			if (!empty($this->request->data['Reconciliation']['file']['error'])) {
				$this->Session->setFlash('There was an error in file upload!. '. $this->request->data['Reconciliation']['file']['error'], 'flash_error');
				$this->redirect(array('action' => 'reconcile_'.$type));
			}
			
			$reconciliation_id = $this->Reconciliations->save($this->settings, $this->request->data['Reconciliation']['file'], $type);
			if (!$reconciliation_id) {
				$this->Session->setFlash('There was an error uploading file to S3!.', 'flash_error');
				$this->redirect(array('action' => 'reconcile_'.$type));
			}

			$query = ROOT.'/app/Console/cake reconciliation import_'.$type.' '.$reconciliation_id;
			$query.= " > /dev/null &"; 
			exec($query, $output);
			$this->redirect(array('action' => 'index', '?' => array('type' => $type)));
		}
	}
	
	public function reconcile_adgate() {
		$type = RECONCILE_ADGATE;
		$this->set('type', $type);
		if ($this->request->is('post')) {
			if (!empty($this->request->data['Reconciliation']['file']['error'])) {
				$this->Session->setFlash('There was an error in file upload!. '. $this->request->data['Reconciliation']['file']['error'], 'flash_error');
				$this->redirect(array('action' => 'reconcile_'.$type));
			}
			
			$reconciliation_id = $this->Reconciliations->save($this->settings, $this->request->data['Reconciliation']['file'], $type);
			if (!$reconciliation_id) {
				$this->Session->setFlash('There was an error uploading file to S3!.', 'flash_error');
				$this->redirect(array('action' => 'reconcile_'.$type));
			}

			$query = ROOT.'/app/Console/cake reconciliation import_'.$type.' '.$reconciliation_id;
			$query.= " > /dev/null &"; 
			exec($query, $output);
			$this->redirect(array('action' => 'index', '?' => array('type' => $type)));
		}
	}
	
	public function reconcile_lucid() {
		$type = RECONCILE_LUCID;
		$this->set('type', $type);
		if ($this->request->is('post')) {
			if (!empty($this->request->data['Reconciliation']['file']['error'])) {
				$this->Session->setFlash('There was an error in file upload!. '. $this->request->data['Reconciliation']['file']['error'], 'flash_error');
				$this->redirect(array('action' => 'reconcile_'.$type));
			}
			
			$reconciliation_id = $this->Reconciliations->save($this->settings, $this->request->data['Reconciliation']['file'], $type);
			if (!$reconciliation_id) {
				$this->Session->setFlash('There was an error uploading file to S3!.', 'flash_error');
				$this->redirect(array('action' => 'reconcile_'.$type));
			}

			$query = ROOT.'/app/Console/cake reconciliation import_'.$type.' '.$reconciliation_id;
			$query.= " > /dev/null &"; 
			exec($query, $output);
			$this->redirect(array('action' => 'index', '?' => array('type' => $type)));
		}
	}
	
	public function reconcile_toluna() {
		$type = RECONCILE_TOLUNA;
		$this->set('type', $type);
		if ($this->request->is('post')) {
			if (!empty($this->request->data['Reconciliation']['file']['error'])) {
				$this->Session->setFlash('There was an error in file upload!. '. $this->request->data['Reconciliation']['file']['error'], 'flash_error');
				$this->redirect(array('action' => 'reconcile_'.$type));
			}
			
			$reconciliation_id = $this->Reconciliations->save($this->settings, $this->request->data['Reconciliation']['file'], $type);
			if (!$reconciliation_id) {
				$this->Session->setFlash('There was an error uploading file to S3!.', 'flash_error');
				$this->redirect(array('action' => 'reconcile_'.$type));
			}

			$query = ROOT.'/app/Console/cake reconciliation import_'.$type.' '.$reconciliation_id;
			$query.= " > /dev/null &"; 
			exec($query, $output);
			$this->redirect(array('action' => 'index', '?' => array('type' => $type)));
		}
	}
	
	public function reconcile_precision() {
		$type = RECONCILE_PRECISION;
		$this->set('type', $type);
		if ($this->request->is('post')) {
			if (!empty($this->request->data['Reconciliation']['file']['error'])) {
				$this->Session->setFlash('There was an error in file upload!. '. $this->request->data['Reconciliation']['file']['error'], 'flash_error');
				$this->redirect(array('action' => 'reconcile_'.$type));
			}
			
			$reconciliation_id = $this->Reconciliations->save($this->settings, $this->request->data['Reconciliation']['file'], $type);
			if (!$reconciliation_id) {
				$this->Session->setFlash('There was an error uploading file to S3!.', 'flash_error');
				$this->redirect(array('action' => 'reconcile_'.$type));
			}
			
			$query = ROOT.'/app/Console/cake reconciliation import_'.$type.' '.$reconciliation_id;
			$query.= " > /dev/null &"; 
			exec($query, $output);
			$this->redirect(array('action' => 'index', '?' => array('type' => $type)));
		}
	}
	
	public function approve_missing_completes($reconciliation_id) {
		ini_set('memory_limit', '2048M');
		$reconciliation = $this->Reconciliation->find('first', array(
			'conditions' => array(
				'Reconciliation.id' => $reconciliation_id
			)
		));
		if ($this->request->is('put') || $this->request->is('post')) {
			$i = 0;
			if (!empty($this->request->data['Reconciliation']['reconcile'])) {
				$reconciliation_rows = $this->ReconciliationRow->find('all', array(
					'conditions' => array(
						'ReconciliationRow.id' => $this->request->data['Reconciliation']['reconcile']
					)
				));
				
				if ($reconciliation_rows) {
					if (in_array($reconciliation['Reconciliation']['type'], unserialize(RECONCILE_PROJECTS))) {
						$survey_ids = array();
						foreach ($reconciliation_rows as $reconciliation_row) {
							$survey_ids[] = $reconciliation_row['ReconciliationRow']['survey_id'];
						}
						$survey_ids = array_unique($survey_ids);
						$project_awards = $this->Project->find('list', array(
							'fields' => array('Project.id', 'Project.award'),
							'recursive' => -1,
							'conditions' => array(
								'Project.id' => $survey_ids
							)
						));
					}
			
					$approved_history_requests = array();
					foreach ($reconciliation_rows as $reconciliation_row) {
						if (in_array($reconciliation['Reconciliation']['type'], unserialize(RECONCILE_PROJECTS))) {
							$award = $project_awards[$reconciliation_row['ReconciliationRow']['survey_id']];
							
							// check for history requests against this missing complete
							$history_request = $this->HistoryRequest->find('first', array(
								'conditions' => array(
									'HistoryRequest.user_id' => $reconciliation_row['ReconciliationRow']['user_id'],
									'HistoryRequest.project_id' => $reconciliation_row['ReconciliationRow']['survey_id'],
								)
							));
							if ($history_request) {
								$status = MintVine::approve_history_request($history_request['HistoryRequest']['id'], $award, $this->current_user['Admin']['id']);
								if ($status['status'] == false) {
									$approved_history_requests[] = $reconciliation_row['ReconciliationRow']['hash'];
									continue;
								}
								else {
									$transaction_id = $status['transaction_id'];
								}
							}
							else {
								if (in_array($reconciliation['Reconciliation']['type'], array('points2shop', 'ssi'))) {
									$source = $reconciliation['Reconciliation']['type'];
									if ($reconciliation['Reconciliation']['type'] == 'points2shop') {
										$source = 'p2s';
									}

									$this->RouterLog->create();
									$this->RouterLog->save(array('RouterLog' => array(
										'source' => $source,
										'survey_id' => $reconciliation_row['ReconciliationRow']['survey_id'],
										'hash' => $reconciliation_row['ReconciliationRow']['hash'],
										'type' => 'success',
										'user_id' => $reconciliation_row['ReconciliationRow']['user_id'],
										'payout' => $award, 
										'created' => $reconciliation_row['ReconciliationRow']['timestamp']
									)));
								}

								$transactionSource = $this->Transaction->getDataSource();
								$transactionSource->begin();
								$this->Transaction->create();
								$this->Transaction->save(array('Transaction' => array(
									'override' => true, // multiple successes might be allowed
									'type_id' => TRANSACTION_SURVEY,
									'linked_to_id' => $reconciliation_row['ReconciliationRow']['survey_id'],
									'user_id' => $reconciliation_row['ReconciliationRow']['user_id'],
									'amount' => $award,
									'paid' => false,
									'name' => 'Reconciliation for missing Points Place completion (#'.$reconciliation_row['ReconciliationRow']['survey_id'].')',
									'status' => TRANSACTION_PENDING,
									'executed' => date(DB_DATETIME)
								)));
								$transaction_id = $this->Transaction->getInsertId();
								$transaction = $this->Transaction->findById($transaction_id);
								$this->Transaction->approve($transaction);
								$transactionSource->commit();
							}
						}
						else {
							$offer = $this->Offer->find('first', array(
								'conditions' => array(
									'Offer.offer_partner_id' => $reconciliation_row['ReconciliationRow']['offer_id'],
									'Offer.partner' => $reconciliation['Reconciliation']['type']
								)
							));
							
							// if we don't find any offer just create a new one.
							if (!$offer) {
								$offerSource = $this->Offer->getDataSource();
								$offerSource->begin();
								$this->Offer->create();
								$this->Offer->save(array('Offer' => array(
									'offer_partner_id' => $reconciliation_row['ReconciliationRow']['offer_id'],
									'offer_title' => 'Personaly offer #'.$reconciliation_row['ReconciliationRow']['offer_id'],
									'award' => $reconciliation_row['ReconciliationRow']['offer_amount'],
									'partner' => $reconciliation['Reconciliation']['type']
								)));
								$offer = $this->Offer->find('first', array(
									'conditions' => array(
										'Offer.id' => $this->Offer->getLastInsertID(),
									)
								));
								$offerSource->commit();
							}
							
							$transactionSource = $this->Transaction->getDataSource();
							$transactionSource->begin();
							$this->Transaction->create();
							$this->Transaction->save(array('Transaction' => array(
								'type_id' => TRANSACTION_OFFER,
								'linked_to_id' => $offer['Offer']['id'], 
								'linked_to_name' => $offer['Offer']['offer_title'], 
								'user_id' => $reconciliation_row['ReconciliationRow']['user_id'],
								'amount' => $reconciliation_row['ReconciliationRow']['offer_amount'],
								'paid' => false,
								'name' => 'Points for completing offer ' . $offer['Offer']['offer_title'],
								'status' => TRANSACTION_APPROVED,
								'executed' => date(DB_DATETIME)
							)));
							$transaction_id = $this->Transaction->getInsertId();
							$transaction = $this->Transaction->findById($transaction_id);
							$this->Transaction->approve($transaction);
							$transactionSource->commit();
							
							$revenue = $this->__get_total_revenue($reconciliation_row['ReconciliationRow']['offer_amount']);
							$this->OfferRedemption->create();
							$this->OfferRedemption->save(array('OfferRedemption' => array(
								'partner' => $reconciliation['Reconciliation']['type'],
								'user_id' => $reconciliation_row['ReconciliationRow']['user_id'],
								'offer_id' => $offer['Offer']['id'],
								'transaction_id' => $transaction_id,
								'payout' => $reconciliation_row['ReconciliationRow']['offer_amount'],
								'revenue' => $revenue,
								'xtid' => $reconciliation_row['ReconciliationRow']['xtid'],
								'xoid' => $reconciliation_row['ReconciliationRow']['xoid'],
								'status' => OFFER_REDEMPTION_ACCEPTED
							)));
						}
						
						$i++;
						
						$this->ReconciliationRow->create();
						$this->ReconciliationRow->save(array('ReconciliationRow' => array(
							'id' => $reconciliation_row['ReconciliationRow']['id'],
							'processed' => true,
							'transaction_id' => $transaction_id
						)), true, array('processed', 'transaction_id'));
						
						$this->ReconciliationLog->create();
						$this->ReconciliationLog->save(array('ReconciliationLog' => array(
							'reconciliation_id' => $reconciliation['Reconciliation']['id'],
							'type' => 'missing.complete.approved',
							'hash' => !empty($reconciliation_row['ReconciliationRow']['hash']) ? $reconciliation_row['ReconciliationRow']['hash'] : null,
							'project_id' => $reconciliation_row['ReconciliationRow']['survey_id'],
							'user_id' => $reconciliation_row['ReconciliationRow']['user_id'],
							'description' => 'Missing complete approved by '.$this->current_user['Admin']['admin_user'],
						)));
					}
				}
				
				$this->Reconciliation->create();
				$this->Reconciliation->save(array('Reconciliation' => array(
					'id' => $reconciliation_id,
					'total_approved' => $i + $reconciliation['Reconciliation']['total_approved']
				)), true, array('total_approved'));
			}
			
			if (isset($this->data['Reconciliation']['mark']) && $this->data['Reconciliation']['mark']) {
				$this->Reconciliation->create();
				$this->Reconciliation->save(array('Reconciliation' => array(
					'id' => $reconciliation_id,
					'status' => 'completed'
				)), true, array('status'));
			}
			
			$message = '';
			if ($i > 0) {
				$message .= 'You have successfully rewarded '.$i.' panelists.<br />';
			}
			
			if (!empty($approved_history_requests)) {
				$message .= 'The following hashes are already approved in history requests so can not be approved here. <br />'.implode('<br/ >', $approved_history_requests);
			}
			
			if ($message) {
				$this->Session->setFlash($message, 'flash_success');
			}
			
			$this->redirect(array('action' => 'approved_completes', $reconciliation['Reconciliation']['id']));
		}
		
		$reconciliation = $this->Reconciliation->find('first', array(
			'conditions' => array(
				'Reconciliation.id' => $reconciliation_id
			)
		));
		if (!$reconciliation) {
			$this->Session->setFlash('Could not find that reconciliation report', 'flash_error');
			$this->redirect(array('action' => 'index'));
		}
		// todo: could probably show results instead
		elseif (is_null($reconciliation['Reconciliation']['status'])) {
			$this->Session->setFlash('Reconciliation not prepared as yet.', 'flash_error');
			$this->redirect(array('action' => 'index'));
		}
		
		$count = $this->ReconciliationRow->find('count', array(
			'conditions' => array(
				'ReconciliationRow.reconciliation_id' => $reconciliation_id,
			),
			'recursive' => -1
		));
		$this->ReconciliationRow->bindModel(array('belongsTo' => array(
			'User' => array(
				'fields' => array('User.id', 'User.email')
			)
		)));
		$reconciliation_rows = $this->ReconciliationRow->find('all', array(
			'conditions' => array(
				'ReconciliationRow.reconciliation_id' => $reconciliation_id,
				'ReconciliationRow.needs_reconciliation' => true,
				'ReconciliationRow.processed' => false,
			)
		));

		// get unique survey_ids or offer_partner_ids
		$project_ids = array();
		$offer_partner_ids = array();
		$project_awards = array();
		$offers = array();
		foreach ($reconciliation_rows as $reconciliation_row) {
			if (!empty($reconciliation_row['ReconciliationRow']['survey_id'])) {
				$project_ids[] = $reconciliation_row['ReconciliationRow']['survey_id'];
			}
			else {
				$offer_partner_ids[] = $reconciliation_row['ReconciliationRow']['offer_id'];
			}
		}
		
		if ($project_ids) {
			$project_ids = array_unique($project_ids);
			$project_awards = $this->Project->find('list', array(
				'fields' => array('Project.id', 'Project.award'),
				'conditions' => array(
					'Project.id' => $project_ids
				),
				'recursive' => -1,
			));
		}
		elseif ($offer_partner_ids) {
			$offer_partner_ids = array_unique($offer_partner_ids);
			$offers = $this->Offer->find('list', array(
				'recursive' => -1,
				'conditions' => array(
					'Offer.offer_partner_id' => $offer_partner_ids,
					'Offer.partner' => $reconciliation['Reconciliation']['type']
				),
				'fields' => array('offer_partner_id', 'id')
			));
		}
		$this->set(compact('reconciliation', 'reconciliation_rows', 'project_awards', 'offers', 'count'));
	}
	
	public function approved_completes($reconciliation_id) {
		$reconciliation = $this->Reconciliation->find('first', array(
			'conditions' => array(
				'Reconciliation.id' => $reconciliation_id
			)
		));
		$this->paginate = array(
			'ReconciliationRow' => array(
				'conditions' => array(
					'ReconciliationRow.reconciliation_id' => $reconciliation_id,
					'ReconciliationRow.needs_reconciliation' => true,
					'ReconciliationRow.processed' => true,
				),
				'limit' => 100,
				'order' => 'ReconciliationRow.timestamp DESC'
			)
		);
		$reconciliation_rows = $this->paginate('ReconciliationRow');
		$this->set(compact('reconciliation', 'reconciliation_rows'));
	}
	
	public function rejected_completes($reconciliation_id) {
		$reconciliation = $this->Reconciliation->find('first', array(
			'conditions' => array(
				'Reconciliation.id' => $reconciliation_id
			)
		));
		$this->paginate = array(
			'ExtraComplete' => array(
				'conditions' => array(
					'ExtraComplete.reconciliation_id' => $reconciliation_id,
					'ExtraComplete.processed' => true,
				),
				'limit' => 100,
				'order' => 'ExtraComplete.timestamp DESC'
			)
		);
		$extra_completes = $this->paginate('ExtraComplete');
		$this->set(compact('reconciliation', 'extra_completes'));
	}
	
	public function reject_extra_completes($reconciliation_id) {
		$reconciliation = $this->Reconciliation->find('first', array(
			'conditions' => array(
				'Reconciliation.id' => $reconciliation_id
			)
		));
		if (($this->request->is('put') || $this->request->is('post')) && !empty($this->request->data['Reconciliation']['reconcile'])) {
			$extra_completes = $this->ExtraComplete->find('all', array(
				'conditions' => array(
					'ExtraComplete.id' => $this->request->data['Reconciliation']['reconcile']
				)
			));
			if ($extra_completes) {
				$i = 0;
				foreach ($extra_completes as $extra_complete) {
					$transaction = $this->Transaction->find('first', array(
						'conditions' => array(
							'Transaction.id' => $extra_complete['ExtraComplete']['transaction_id'],
							'Transaction.deleted' => null,
						)
					));
					if (!$transaction) {
						continue;
					}

					$this->Transaction->reject($transaction);
					$this->ExtraComplete->create();
					$this->ExtraComplete->save(array('ExtraComplete' => array(
						'id' => $extra_complete['ExtraComplete']['id'],
						'processed' => true,
					)), true, array('processed'));
					
					$this->ReconciliationLog->create();
					$this->ReconciliationLog->save(array('ReconciliationLog' => array(
						'reconciliation_id' => $reconciliation['Reconciliation']['id'],
						'type' => 'extra.complete.rejected',
						'hash' => !empty($extra_complete['ExtraComplete']['hash']) ? $extra_complete['ExtraComplete']['hash'] : null,
						'project_id' => $extra_complete['ExtraComplete']['survey_id'],
						'user_id' => $extra_complete['ExtraComplete']['user_id'],
						'description' => 'Extra complete rejected by '.$this->current_user['Admin']['admin_user'],
					)));
					$i++;
				}

				$this->Reconciliation->create();
				$this->Reconciliation->save(array('Reconciliation' => array(
					'id' => $reconciliation_id,
					'total_rejected' => $i + $reconciliation['Reconciliation']['total_rejected']
				)), true, array('total_rejected'));
				$this->Session->setFlash($i . ' complete(s) have been rejected successfully.', 'flash_success');
				$this->redirect(array('action' => 'rejected_completes', $reconciliation['Reconciliation']['id']));
			}
		}
		
		if (!$reconciliation) {
			$this->Session->setFlash('Could not find that reconciliation report', 'flash_error');
			$this->redirect(array('action' => 'index'));
		}
		elseif ($reconciliation['Reconciliation']['status'] != RECONCILIATION_ANALYZED) {
			$this->Session->setFlash('Analysis not yet completed on this report.', 'flash_error');
			$this->redirect(array('action' => 'index'));
		}
		
		$this->ExtraComplete->bindModel(array('belongsTo' => array(
			'User' => array(
				'fields' => array('User.id', 'User.email')
			)
		)));
		
		$extra_completes = $this->ExtraComplete->find('all', array(
			'conditions' => array(
				'ExtraComplete.reconciliation_id' => $reconciliation_id,
				'ExtraComplete.processed' => false,
			)
		));

		$project_ids = array();
		$projects = array();
		foreach ($extra_completes as $extra_complete) {
			if (!empty($extra_complete['ExtraComplete']['survey_id'])) {
				$project_ids[] = $extra_complete['ExtraComplete']['survey_id'];
			}
		}
		
		if ($project_ids) {
			$project_ids = array_unique($project_ids);
			$projects = $this->Project->find('list', array(
				'recursive' => -1,
				'conditions' => array(
					'Project.id' => $project_ids
				),
				'fields' => array('id', 'award')
			));
		}
		
		$this->set(compact('reconciliation', 'extra_completes', 'projects'));
	}
	
	public function download() {
		if (!isset($this->request->query['path'])) {
			throw new Exception('File path not found.');
		}
		
		$S3 = new S3($this->settings['s3.access'], $this->settings['s3.secret'], false, $this->settings['s3.host']);
		$url = $S3->getAuthenticatedURL($this->settings['s3.bucket'], urldecode($this->request->query['path']), 3600, false, false);
		$this->redirect($url);
	}
	
	public function delete($id = null) {
		set_time_limit(600);
		ini_set('memory_limit', '1024M');
		if (empty($id)) {
			throw new Exception('Id not found.');
		}
		
		$reconciliation = $this->Reconciliation->find('first', array(
			'conditions' => array(
				'Reconciliation.id' => $id
			)
		));
		
		if (!$reconciliation || $reconciliation['Reconciliation']['status'] == RECONCILIATION_COMPLETED) {
			$this->Session->setFlash('Can not delete the reconciliation', 'flash_error');
			$this->redirect(array('action' => 'index'));
		}
		
		$delete_parent = true;
		$processed_count = $this->ReconciliationRow->find('count', array(
			'conditions' => array(
				'ReconciliationRow.reconciliation_id' => $reconciliation['Reconciliation']['id'],
				'ReconciliationRow.processed' => true
			)
		));
		if ($processed_count > 0) {
			$delete_parent = false;
		}
		
		if ($delete_parent) {
			$processed_count = $this->ExtraComplete->find('count', array(
				'conditions' => array(
					'ExtraComplete.reconciliation_id' => $reconciliation['Reconciliation']['id'],
					'ExtraComplete.processed' => true
				)
			));
			
			if ($processed_count > 0) {
				$delete_parent = false;
			}
		}
		
		
		$reconciliation_rows = $this->ReconciliationRow->find('all', array(
			'fields' => array('ReconciliationRow.id', 'ReconciliationRow.processed'),
			'conditions' => array(
				'ReconciliationRow.reconciliation_id' => $reconciliation['Reconciliation']['id'],
				'ReconciliationRow.processed' => false
			)
		));
		
		if ($reconciliation_rows) {
			foreach ($reconciliation_rows as $row) {
				$this->ReconciliationRow->delete($row['ReconciliationRow']['id']);
			}
		}
		
		$extra_completes = $this->ExtraComplete->find('all', array(
			'fields' => array('ExtraComplete.id', 'ExtraComplete.processed'),
			'conditions' => array(
				'ExtraComplete.reconciliation_id' => $reconciliation['Reconciliation']['id'],
				'ExtraComplete.processed' => false
			)
		));
		
		if ($extra_completes) {
			foreach ($extra_completes as $extra_complete) {
				$this->ExtraComplete->delete($extra_complete['ExtraComplete']['id']);
			}
		}
		
		// delete logs
		$this->loadModel('ReconciliationLog');
		$reconciliation_logs = $this->ReconciliationLog->find('all', array(
			'fields' => array('ReconciliationLog.id'),
			'conditions' => array(
				'ReconciliationLog.reconciliation_id' => $reconciliation['Reconciliation']['id'],
			)
		));
		
		if ($reconciliation_logs) {
			foreach ($reconciliation_logs as $log) {
				$this->ReconciliationLog->delete($log['ReconciliationLog']['id']);
			}
		}
		
		if ($delete_parent) {
			$S3 = new S3($this->settings['s3.access'], $this->settings['s3.secret'], false, $this->settings['s3.host']);
			//$url = $S3->getAuthenticatedURL($this->settings['s3.bucket'], $reconciliation['Reconciliation']['filepath'], 3600, false, false);
			$S3->deleteObject($this->settings['s3.bucket'], $reconciliation['Reconciliation']['filepath']);
			if ($reconciliation['Reconciliation']['status'] == RECONCILIATION_ANALYZED) {
				$S3->deleteObject($this->settings['s3.bucket'], 'reconciliations/analyzed/'.$reconciliation['Reconciliation']['id']);
			}
			
			$S3->deleteObject($this->settings['s3.bucket'], $reconciliation['Reconciliation']['filepath']);
			$this->Reconciliation->delete($reconciliation['Reconciliation']['id']);
			$this->Session->setFlash('Reconciliation #'.$reconciliation['Reconciliation']['id']. ' deleted successfully.', 'flash_success');
		}
		else {
			$this->Session->setFlash('We can not delete this reconciliation, because some of the rows has been reconciled (processed) already. However un processed data has been deleted.'
				. '<br />'.count($reconciliation_rows).' Reconciliation rows deleted.'
				. '<br />'.count($extra_completes).' Extra completes rows deleted.', 'flash_error');
		}
		
		$this->redirect(array('action' => 'index'));
	}
		
	private function __get_total_revenue($payout) {
		//converting to dollars
		$user_revenue = $payout / 100;
		
		//mintvine cut is 40% of the total, (user was paid 60% of the total)
		$mintvine_revenue = (40 * $payout) / 60;
		//converting to dollars
		$mintvine_revenue = $mintvine_revenue / 100;
		
		$total_revenue = $user_revenue + $mintvine_revenue;
		return $total_revenue;
	}
	
	public function missing_completes() {
		
	}
	
	public function check($id) {
		$reconciliation = $this->Reconciliation->findById($id);
		if (is_null($reconciliation['Reconciliation']['status'])) {
			$count = $this->ReconciliationRow->find('count', array(
				'conditions' => array(
					'ReconciliationRow.reconciliation_id' => $reconciliation['Reconciliation']['id']
				)
			));
			$data = array(
				'status' => '',
				'count' => $count
			);
		}
		else {
			$data = array(
				'status' => $reconciliation['Reconciliation']['status'],
				'count' => 0
			);
		}
    	return new CakeResponse(array(
			'body' => json_encode($data), 
			'type' => 'json',
			'status' => '201'
		));
	}
	
	public function reconcile_spectrum() {
		$type = RECONCILE_SPECTRUM;
		$this->set('type', $type);
		if ($this->request->is('post')) {
			if (!empty($this->request->data['Reconciliation']['file']['error'])) {
				$this->Session->setFlash('There was an error in file upload!. ' . $this->request->data['Reconciliation']['file']['error'], 'flash_error');
				$this->redirect(array('action' => 'reconcile_' . $type));
			}

			$reconciliation_id = $this->Reconciliations->save($this->settings, $this->request->data['Reconciliation']['file'], $type);
			if (!$reconciliation_id) {
				$this->Session->setFlash('There was an error uploading file to S3!.', 'flash_error');
				$this->redirect(array('action' => 'reconcile_' . $type));
			}
			
			$query = ROOT . '/app/Console/cake reconciliation import_' . $type . ' ' . $reconciliation_id;
			$query.= " > /dev/null &";
			exec($query, $output);
			$this->redirect(array('action' => 'index', '?' => array('type' => $type)));
		}
	}

}