<?php
App::uses('AppController', 'Controller');
App::uses('HttpSocket', 'Network/Http');

class InvoicesController extends AppController {
	
	public $uses = array('Invoice', 'Project', 'SurveyComplete', 'SurveyVisit', 'Site', 'GeoCountry', 'GeoState', 'Group', 'SurveyVisit', 'Invoice');
	public $components = array('Invoices', 'QuickBook');
	
	function beforeFilter() {
		parent::beforeFilter();
		$quickbook_connect_status = $this->Site->get_quickbook_status();
		if ($quickbook_connect_status == QUICKBOOK_OAUTH_NOT_CONNECTED) {
			$this->Session->setFlash(__('You are not connected with QuickBook, please connect with QuickBook first to generate invoices.'), 'flash_error');
		}
		elseif ($quickbook_connect_status == QUICKBOOK_OAUTH_EXPIRING_SOON) {
			$this->Session->setFlash(__('Your access to QuickBook is about to expire, please re-connect your account to gain un-intrupted access.'), 'flash_error');
		}
		elseif ($quickbook_connect_status == QUICKBOOK_OAUTH_EXPIRED) {
			$this->Session->setFlash(__('Your access to QuickBook is expired, please re-connect your account to gain un-intrupted access.'), 'flash_error');
		}
	}
	
	function index() {		
		$this->Invoice->bindModel(array(
			'belongsTo' => array(
				'Project' => array(
					'fields' => array('group_id')
				)
			)
		));
		$conditions = array();
		if (isset($this->current_user['AdminGroup'][0])) {
			$conditions['Project.group_id'] = $this->current_user['AdminGroup'];
		}
		
		$paginate = array(
			'Invoice' => array(
				'conditions' => $conditions,
				'limit' => '50',
				'order' => 'Invoice.updated DESC',
			)
		);
		
		$this->paginate = $paginate;
		
		$this->set('invoices', $this->paginate());
	}
	
	function group() {
		if ($this->request->is('put') || $this->request->is('post')) {
			$start_date = $this->data['Report']['start_date']['year'].'-'.$this->data['Report']['start_date']['month'].'-'.$this->data['Report']['start_date']['day'];
			$end_date = $this->data['Report']['end_date']['year'].'-'.$this->data['Report']['end_date']['month'].'-'.$this->data['Report']['end_date']['day'];
			
			$group = $this->Group->find('first', array(
				'fields' => array('id'),
				'conditions' => array(
					'Group.key' => $this->request->data['Report']['partner']
				)
			));
			if (!$group) {
				$this->Session->setFlash('Please select a valid partner.', 'flash_error');
			}
			else {
				$conditions = array(
					'Project.group_id' => $group['Group']['id'],
					'OR' => array(
						array(
							'Project.started <=' => $start_date.' 00:00:00',
							'Project.ended >=' => $start_date.' 23:59:59'
						),
						array(
							'Project.started >=' => $start_date.' 00:00:00',
							'Project.ended <=' => $end_date.' 23:59:59'
						),
						array(
							'Project.started <=' => $end_date.' 23:59:59',
							'Project.ended >=' => $end_date.' 23:59:59'
						),
						array(
							'Project.started >=' => $start_date.' 00:00:00',
							'Project.started <=' => $end_date.' 23:59:59',
							'Project.ended is null'
						)
					)
				);
				$this->Project->unbindModel(array('hasMany' => array('SurveyPartner', 'ProjectOption')));
				$projects = $this->Project->find('all', array(
					'conditions' => $conditions,
					'fields' => array('id', 'status')
				));
				
				if ($projects) {
					$project_ids = array();
					$attach_invoice_id = null;
					foreach ($projects as $project) {
						$project_ids[] = $project['Project']['id'];
						if (is_null($attach_invoice_id) && $project['Project']['status'] == PROJECT_STATUS_CLOSED) {
							$invoice = $this->Invoice->find('first', array(
								'conditions' => array(
									'Invoice.project_id' => $project['Project']['id']
								)
							));
							if (!$invoice) {
								$attach_invoice_id = $project['Project']['id'];
							}
						}
					}
					if (is_null($attach_invoice_id)) {
						$this->Session->setFlash('We cannot create an invoice because we have no projects to attach it to.', 'flash_error');						
					}
					else {
						$survey_visits = $this->SurveyVisit->find('list', array(
							'fields' => array(
								'id', 'survey_id'
							),
							'recursive' => -1,
							'conditions' => array(
								'SurveyVisit.survey_id' => $project_ids,
								'SurveyVisit.type' => SURVEY_COMPLETED,
								'SurveyVisit.created >=' => $start_date.' 00:00:00',
								'SurveyVisit.created <=' => $end_date.' 23:59:59'
							)
						));
						$project_rates = array();
						if (!empty($survey_visits)) {
							$project_rates = $this->Project->find('list', array(
								'conditions' => array(
									'Project.id' => array_unique($survey_visits)
								),
								'recursive' => -1,
								'fields' => array('id', 'client_rate')
							));
							$project_names = $this->Project->find('list', array(
								'conditions' => array(
									'Project.id' => array_unique($survey_visits)
								),
								'recursive' => -1,
								'fields' => array('id', 'prj_name')
							));
							$project_counts = array_count_values($survey_visits);
							
							// generate the invoice for the first project
							$project = $this->Project->find('first', array(
								'conditions' => array(
									'Project.id' => $attach_invoice_id
								)
							));
							$start_ts = strtotime($start_date);
							$end_ts = strtotime($end_date);
							$number = date('M j', $start_ts). ' - '.date('M j, Y', $end_ts);
							$invoiceSource = $this->Invoice->getDataSource();
							$invoiceSource->begin();
							$this->Invoice->create();
							$save = $this->Invoice->save(array('Invoice' => array(
								'number' => $number,
								'date' => Utils::change_tz_from_utc(date(DB_DATETIME), DB_DATETIME),
								'name' => $project['Client']['client_name'],
								'address_1' => $project['Client']['address_line1'],
								'address_2' => $project['Client']['address_line2'],
								'geo_country_id' => $project['Client']['geo_country_id'],
								'geo_state_id' => $project['Client']['geo_state_id'],
								'city' => $project['Client']['geo_state_id'],
								'postal_code' => $project['Client']['postal_code'],
								'city' => $project['Client']['city'],
								'project_id' => $attach_invoice_id,
							)));
							if ($save) {
								$invoice_id = $this->Invoice->getInsertId();
								$invoiceSource->commit();
								$total = 0;
								foreach ($project_counts as $project_id => $quantity) {
									$this->Invoice->InvoiceRow->create();
									$this->Invoice->InvoiceRow->save(array('InvoiceRow' => array(
										'invoice_id' => $invoice_id,
										'quantity' => $quantity,
										'description' => $project_names[$project_id],
										'unit_price' => $project_rates[$project_id],
									)));
									$total = $total + ($project_rates[$project_id] * $quantity);
								}
								$total = round($total, 2);
								$this->Invoice->create();
								$this->Invoice->save(array('Invoice' => array(
									'id' => $invoice_id,
									'subtotal' => $total
								)), true, array('subtotal'));
								$this->Session->setFlash('Your invoice has been created. Please edit it and send it.', 'flash_success');
								$this->redirect(array('controller' => 'invoices', 'action' => 'edit', $invoice_id));
							}
							else {
								$invoiceSource->commit();
								$this->Session->setFlash('Client/invoice mismatch - contact tech', 'flash_error');
							}
						}
						else {
							$this->Session->setFlash('There were no completes for projects in that date range.', 'flash_error');
						}
					}
				}
				else {
					$this->Session->setFlash('There were no projects in that date range.', 'flash_error');
				}
			}	
		}
	}
	
	function generate($survey_id) {
		$this->Project->bindInvoices();
		$this->Project->bindRates();
		$survey = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $survey_id
			),
			'contain' => array(
				'Invoice',
				'Client' => array(
					'fields' => array('billing_name', 'billing_email', 'address_line1', 'address_line2', 'city', 'postal_code', 'geo_country_id', 'geo_state_id', 'net'),
					'Contact' => array(
						'fields' => array('address', 'email')
					)
				),
				'HistoricalRates' => array(
					'order' => 'HistoricalRates.created desc'
				)
			)
		));
		
		if (!$survey) {
			throw new NotFoundException(__('Invalid survey'));
		}
		
		if (!$this->Admins->can_access_project($this->current_user, $survey['Project']['id'])) {
			$this->Session->setFlash('You are not authorized to generate invoice for this project.', 'flash_error');
			$this->redirect(array('controller' => 'surveys', 'action' => 'index'));
		}
		
		if ($survey['Project']['status'] == PROJECT_STATUS_OPEN) {
			$this->Session->setFlash(__('You cannot generate invoices for open projects - please close this project.'), 'flash_error');
			$this->redirect(array('controller' => 'surveys', 'action' => 'index'));
		}
		
		if (!empty($survey['Invoice']['id'])) {
			$this->Session->setFlash(__('An invoice has already been generated for this project!'), 'flash_error');
			$this->redirect(array('controller' => 'surveys', 'action' => 'index'));
		}
		
		if ($this->request->is('post') || $this->request->is('put')) {
			$invoiceSource = $this->Invoice->getDataSource();
			$invoiceSource->begin();
			$this->Invoice->create();
			$save = $this->Invoice->save($this->request->data);
			if ($save) {
				$invoice_id = $this->Invoice->getLastInsertID();
				$invoiceSource->commit();
				
				$this->ProjectLog->create();
				$this->ProjectLog->save(array('ProjectLog' => array(
					'project_id' => $survey_id,
					'user_id' => $this->current_user['Admin']['id'],
					'type' => 'invoice.generated',
					'description' => 'Invoice ID: ' . $invoice_id,
				)));
				
				// Save invoice first row
				if (intval($this->request->data['InvoiceRow']['quantity']) > 0) {
					$this->Invoice->InvoiceRow->create();
					$this->request->data['InvoiceRow']['invoice_id'] = $invoice_id;
					$this->Invoice->InvoiceRow->save($this->request->data);
				}
				// Save additional rows
				if (isset($this->request->data['new']['quantity'])) {
					foreach ($this->request->data['new']['quantity'] as $key_id => $qty) {
						if (intval($this->request->data['new']['quantity'][$key_id]) < 1) {
							continue;
						}
						
						$this->Invoice->InvoiceRow->create();
						$this->Invoice->InvoiceRow->save(array('InvoiceRow' => array(
							'invoice_id' => $invoice_id,
							'quantity' => $qty,
							'description' => $this->request->data['new']['description'][$key_id],
							'unit_price' => $this->request->data['new']['unit_price'][$key_id],
						)));
					}
					
					if ($this->Invoices->save($invoice_id)) {
						$this->Session->setFlash(__('Invoice generated successfully!'), 'flash_success');
						$this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $survey_id));
					}
					else {
						$this->Session->setFlash(__('Invoice could not be generated. Invoice may be empty.'), 'flash_error');
					}
				}
			}
			else {
				$invoiceSource->commit();
				$this->Session->setFlash(__('There was a problem: please review your errors below and try again.'), 'flash_error');
			}
		}

		$survey_completes = $this->SurveyComplete->find('list', array(
			'fields' => array('id', 'hash'),
			'conditions' => array(
				'SurveyComplete.survey_id' => $survey_id
			)
		));
		$completes = array();
		if (!empty($survey_completes)) {
			$completes = $this->SurveyVisit->find('all', array(
				'fields' => array('created'),
				'conditions' => array(
					'SurveyVisit.survey_id' => $survey_id,
					'SurveyVisit.hash' => $survey_completes,
					'SurveyVisit.type' => SURVEY_COMPLETED
				),
				'recursive' => -1
			));
		}
		
		// In case of variable client rates
		if (!empty($survey['HistoricalRates']) && !empty($completes)) {
			$invoice_rows = array();
			$rates = array_merge($survey['HistoricalRates'], array(
				array(
					'client_rate' => $survey['Project']['client_rate'],
					'created' => $survey['Project']['date_created']
				)
			));
			
			foreach ($completes as $complete) {
				foreach ($rates as $rate) {
					if (strtotime($rate['created']) < strtotime($complete['SurveyVisit']['created'])) {
						$invoice_rows[] = $rate['client_rate'];
						break;
					}
				}
			}
			
			$invoice_rows = array_count_values($invoice_rows);
			$total = 0;
			if (!empty($invoice_rows)) {
				foreach ($invoice_rows as $client_rate => $qty) {
					$total += $client_rate * $qty;
				}
			}
			
			$this->set(compact('invoice_rows'));
		}
		else {
			$count = count($completes);
			$total = round($count * $survey['Project']['client_rate'], 2);
			
		}
		
		$geo_countries = $this->GeoCountry->find('list', array(
			'fields' => array(
				'id', 'country'
			),
			'order' => array(
				'country' => 'ASC'
			)
		));
		
		$geo_states = $this->GeoState->find('list', array(
			'fields' => array(
				'id', 'state'
			),
			'order' => array(
				'state' => 'ASC'
			)
		));
		
		$this->set(compact('geo_countries', 'geo_states', 'survey', 'count', 'total'));
	}
	
	function regenerate() {
		if ($this->request->is('post') || $this->request->is('put')) {
			
			$this->Invoice->bindModel(array(
				'belongsTo' => array(
					'GeoCountry',
					'GeoState'
				)
			));
			
			$invoice = $this->Invoice->find('first', array(
				'conditions' => array(
					'Invoice.project_id' => $this->request->data['Invoice']['invoice']
				)
			));
			if (!$invoice) {
				$this->Session->setFlash('There is no invoice to regenerate; please simply generate it.', 'flash_error'); 
				$this->redirect(array('action' => 'surveys', 'action' => 'dashboard', $this->request->data['Invoice']['invoice']));
			}
			$project = $this->Project->find('first', array(
				'conditions' => array(
					'Project.id' => $invoice['Invoice']['project_id']
				)
			));
			
			$this->Invoice->create();
			$this->Invoice->save(array('Invoice' => array(
				'id' => $invoice['Invoice']['id'],
				'date' => $invoice['Invoice']['date'],
				'address_line1' => trim($project['Client']['address_line1']),
				'address_line2' => trim($project['Client']['address_line2']),
				'geo_country_id' => $project['Client']['geo_country_id'],
				'geo_state_id' => $project['Client']['geo_state_id'],
				'postal_code' => $project['Client']['postal_code'],
				'city' => trim($project['Client']['city']),
				'terms' => 30,
			)), true, array('address_line1', 'address_line2', 'geo_country_id', 'date', 'due_date', 'terms', 'geo_state_id', 'postal_code', 'city', 'address'));
			
			$invoice = $this->Invoice->findById($invoice['Invoice']['id']); 
			$created = $this->QuickBook->create_invoice($invoice); 
			if ($created) {
				$this->Session->setFlash('Regenerated', 'flash_success');
			}
			else {
				$this->Session->setFlash('Failed', 'flash_error');
			}
		}
	}
	
	function edit($invoice_id) {
		$this->Invoice->bindModel(array(
			'belongsTo' => array(
				'Project' => array(
					'className' => 'Project',
					'foreignKey' => 'project_id'
				)
			)
		));
		$invoice = $this->Invoice->find('first',array(
			'contain' => array(
				'Project' => array(
					'Client'
				),
				'InvoiceRow'
			),
			'conditions' => array('Invoice.id' => $invoice_id)
		));
		if (!$invoice) {
			throw new NotFoundException(__('Invalid Invoice'));
		}
		
		if (!$this->Admins->can_access_project($this->current_user, $invoice['Project']['id'])) {
			$this->Session->setFlash('You are not authorized to access that invoice.', 'flash_error');
			$this->redirect(array('controller' => 'invoices', 'action' => 'index'));
		}

		if ($this->request->is('post') || $this->request->is('put')) {
			$save = false;
			//Update Invoice record
			$this->Invoice->create();
			$this->request->data['Invoice']['id'] = $invoice_id;
			if ($this->Invoice->save($this->request->data, true, array(
				'number',
				'date',
				'due_date',
				'name',
				'address',
				'address_line1',
				'address_line2',
				'city',
				'postal_code',
				'geo_country_id',
				'geo_state_id',
				'email',
				'cc',
				'client_project_reference',
				'project_reference',
				'terms',
				'subtotal',
				'currency'
				))) {
				$save = true;
			} 
			
			// Update existing rows of the invoice
			if ($save && !empty($this->request->data['quantity'])) {
				foreach ($this->request->data['quantity'] as $row_id => $qty) {
					$this->Invoice->InvoiceRow->create();
					if (intval($this->request->data['quantity'][$row_id]) < 1) {
						$this->Invoice->InvoiceRow->delete($row_id);
					}
					else {
						$this->Invoice->InvoiceRow->save(array('InvoiceRow' => array(
							'id' => $row_id,
							'quantity' => $qty,
							'description' => $this->request->data['description'][$row_id],
							'unit_price' => $this->request->data['unit_price'][$row_id],
						)), true, array('quantity', 'description', 'unit_price'));
					}
				}
			}

			// Update newly added rows of the invoice
			if ($save && isset($this->request->data['new']['quantity'])) {
				foreach ($this->request->data['new']['quantity'] as $row_id => $qty) {
					if (empty($this->request->data['new']['unit_price'][$row_id])) {
						continue;
					}

					$this->Invoice->InvoiceRow->create();
					$this->Invoice->InvoiceRow->save(array('InvoiceRow' => array(
						'invoice_id' => $this->request->data['Invoice']['id'],
						'quantity' => $qty,
						'description' => $this->request->data['new']['description'][$row_id],
						'unit_price' => $this->request->data['new']['unit_price'][$row_id],
					)));
				}

				if ($this->Invoices->save($this->request->data['Invoice']['id'])) {
					$this->Session->setFlash(__('Invoice saved successfully!'), 'flash_success');
					if ($invoice['Project']['id']) {
						$this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $invoice['Project']['id']));
					}
					else {
						$this->redirect(array('controller' => 'invoices', 'action' => 'index'));
					}
				}
				else {
					$this->Session->setFlash(__('Invoice could not be generated. Invoice may be empty.'), 'flash_error');
				}
			}
			
			if ($save) {
				$this->Session->setFlash('Invoice saved successfully!.', 'flash_success');
				if ($invoice['Project']['id']) {
					$this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $invoice['Project']['id']));
				}
				else {
					$this->redirect(array('controller' => 'invoices', 'action' => 'index'));
				}
				
			}
			else {
				$this->Session->setFlash('There was an error saving your Invoice. Please review it.', 'flash_error');
			}
		}
		else {
			$invoice['Invoice']['date'] = Utils::change_tz_from_utc($invoice['Invoice']['date'], DB_DATETIME);
			$this->data = $invoice;
		}
		
		$geo_countries = $this->GeoCountry->find('list', array(
			'fields' => array(
				'id', 'country'
			),
			'order' => array(
				'country' => 'ASC'
			)
		));
		
		$geo_states = $this->GeoState->find('list', array(
			'fields' => array(
				'id', 'state'
			),
			'order' => array(
				'state' => 'ASC'
			)
		));
		
		$this->set(compact('geo_countries', 'geo_states'));
		$this->set('invoice', $invoice);
	}
	
	function send($invoice_id) {
		$invoice = $this->Invoice->findById($invoice_id);
		if (!$invoice) {
			throw new NotFoundException(__('Invalid Invoice'));
		}
		
		if (!is_file(WWW_ROOT . 'files/pdf/Inv_'.$invoice['Invoice']['project_id'].'_BRInc.pdf')) {
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
			
			CakePlugin::load('Uploader');
			App::import('Vendor', 'Uploader.S3');
			
			$file = 'files/pdf/Inv_'. $invoice['Invoice']['project_id'] .'_BRInc.pdf';
			
			
			$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);
			$url = $S3->getAuthenticatedURL($settings['s3.bucket'], $file, 3600, false, false);
			
			$this->HttpSocket = new HttpSocket(array(
				'timeout' => 15,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$pdf = $this->HttpSocket->get($url);
			if ($pdf->reasonPhrase != 'Not Found') {
				$path = WWW_ROOT . 'files/pdf/Inv_'. $invoice['Invoice']['project_id'] .'_BRInc.pdf';
				if (!file_exists(WWW_ROOT.'/files/pdf')) {
					mkdir(WWW_ROOT.'/files/pdf', 0775); 
				}
				$file = new File($path, true);
				$file->write($pdf);
			}
			else {
				$this->Session->setFlash('The invoice was not correctly generated. Please edit the invoice and save it again, then try to send.', 'flash_error');
				if ($invoice['Invoice']['project_id']) {
					$this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $invoice['Invoice']['project_id']));
				}
				else {
					$this->redirect(array('controller' => 'invoices', 'action' => 'index'));
				}	
			}			
		}		
		$email = new CakeEmail();
		$email->config('mailgun');
		if (defined('IS_DEV_INSTANCE') && IS_DEV_INSTANCE === true) {
			if (defined('DEV_EMAIL')) {
				$email->to(unserialize(DEV_EMAIL));
			}
		}
		else {
			$email->to($invoice['Invoice']['email']);
			$cc = array('support@brandedresearchinc.com', 'accounting@brandedresearchinc.com');
			if (!empty($invoice['Invoice']['cc'])) {
				$cc = array_merge($cc, array_map('trim', explode(',', $invoice['Invoice']['cc'])));
			}
			$email->cc($cc);
		}
		$setting = $this->Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array('cdn.url'),
				'Setting.deleted' => false
			)
		));
		if (!empty($setting['cdn.url']) && (!defined('IS_DEV_INSTANCE') || !IS_DEV_INSTANCE)) {
			Configure::write('App.cssBaseUrl', $setting['cdn.url'] . '/');
			Configure::write('App.jsBaseUrl', $setting['cdn.url'] . '/');
			Configure::write('App.imageBaseUrl', $setting['cdn.url'] . '/img/');
		}
		
		$email->from('accounting@brandedresearchinc.com', 'Stephanie Pac.')
				->replyTo('accounting@brandedresearchinc.com')
				->template('invoice', 'invoice')
				->emailFormat('html')
				->attachments(array(
					'Inv_'.$invoice['Invoice']['project_id'].'_BRInc.pdf' => WWW_ROOT . 'files/pdf/Inv_'.$invoice['Invoice']['project_id'].'_BRInc.pdf'
				))
				->subject('Invoice '. $invoice['Invoice']['number'] .' from Branded Research, Inc.');
		
		if ($email->send()) {
			$this->Invoice->create();
			$this->Invoice->save(array('Invoice' => array(
				'id' => $invoice_id,
				'sent' => date(DB_DATETIME)
			)), true, array('sent'));
			$this->Session->setFlash(__('Email sent successfully!'), 'flash_success');
			if ($invoice['Invoice']['project_id']) {
				$this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $invoice['Invoice']['project_id']));
			}
			else {
				$this->redirect(array('controller' => 'invoices', 'action' => 'index'));
			}
		}
		else {
			$this->Session->setFlash(__('Failed to send invoice. Please double check invoice is formatted correctly and try again.'), 'flash_error');
			if ($invoice['Invoice']['project_id']) {
				$this->redirect(array('controller' => 'surveys', 'action' => 'dashboard', $invoice['Invoice']['project_id']));
			}
			else {
				$this->redirect(array('controller' => 'invoices', 'action' => 'index'));
			}
		}
	}
	
	function download($project_id = null) {
		if(empty($project_id)) {
			throw new NotFoundException();
		}
		
		$invoice = $this->Invoice->find('first', array(
			'conditions' => array(
				'Invoice.project_id' => $project_id
			),
			'fields' => array(
				'id'
			),
			'recursive' => -1
		));
		
		if ($invoice) {
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
			
			CakePlugin::load('Uploader');
			App::import('Vendor', 'Uploader.S3');
			
			$file = 'files/pdf/Inv_'. $project_id .'_BRInc.pdf';
			
			$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);
			$url = $S3->getAuthenticatedURL($settings['s3.bucket'], $file, 3600, false, false);
			
			$this->redirect($url);
		}
		else {
			throw new NotFoundException();
		}
	}
	
	function view ($uuid = null) {
		if(empty($uuid)) {
			throw new NotFoundException();
		}
		
		$invoice = $this->Invoice->find('first', array(
			'conditions' => array(
				'Invoice.uuid' => $uuid
			),
			'fields' => array(
				'id'
			),
			'recursive' => -1
		));
		
		if ($invoice) {
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
			
			CakePlugin::load('Uploader');
			App::import('Vendor', 'Uploader.S3');
			
			$file = 'files/html/' . $uuid . '.html';
			
			$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);
			$url = $S3->getAuthenticatedURL($settings['s3.bucket'], $file, 3600, false, false);
			
			$this->redirect($url);
		}
		else {
			throw new NotFoundException();
		}
	}
}
