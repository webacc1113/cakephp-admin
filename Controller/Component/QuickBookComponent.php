<?php

App::uses('Component', 'Controller');
class QuickBookComponent extends Component {
	
	var $data_service;
	
	function init() {
		App::import('Model', 'Site');
		$this->Site = new Site;
		$site = $this->Site->find('first', array(
			'conditions' => array(
				'Site.path_name' => QUICKBOOK_API_PATH_NAME
			)
		));
		if ($site) {
			require_once(APP.'Vendor/Quickbook/config.php');
			App::import('Vendor', 'QuickbookServiceContext', array('file' => 'Quickbook/Core/ServiceContext.php'));
			App::import('Vendor', 'QuickbookDataService', array('file' => 'Quickbook/DataService/DataService.php'));
			App::import('Vendor', 'QuickbookPlatformService', array('file' => 'Quickbook/PlatformService/PlatformService.php'));
			App::import('Vendor', 'QuickbookConfigurationManager', array('file' => 'Quickbook/Utility/Configuration/ConfigurationManager.php'));
			if (empty($site['Site']['oauth_tokens'])) {
				return;
			}
			//Specify QBO or QBD			
			$auth_settings = json_decode($site['Site']['oauth_tokens']);		
			$service_type = IntuitServicesType::QBO;
			// Prep Service Context
			$request_validator = new OAuthRequestValidator(
				$auth_settings->oauth_token,
				$auth_settings->oauth_token_secret,
				$site['Site']['api_key'],
				$site['Site']['api_secret']
			);
			$service_context = new ServiceContext($auth_settings->realmId, $service_type, $request_validator);
			$this->data_service = new DataService($service_context);
		}				
    }
	
	function create_invoice($invoice) {
		$this->init();
		if (empty($invoice)) {
			return false;
		}
		App::import('Model', 'Project');
		$this->Project = new Project();
		$this->Project->unbindModel(array(
			'hasMany' => array(
				'SurveyPartner',
				'ProjectOption'
			)
		));
		$project = $this->Project->find('first', array(
			'conditions' => array(
				'Project.id' => $invoice['Invoice']['project_id']
			),
			'fields' => array(
				'Project.*',
				'Client.*'
			)
		));
		if (strlen($invoice['Invoice']['number']) > 21) {
			$invoice['Invoice']['number'] = substr($invoice['Invoice']['number'], 0, 21);
		}
		
		try {
			$invoice_object = new IPPInvoice();
			if (empty($project['Client']['quickbook_customer_id'])) {
				$customer_object = new IPPCustomer();
				
				/* Creating billing address object to bind customer address*/
				if (!empty($project['Client']['address_line1'])) {
					$billing_addr = new IPPPhysicalAddress();
					$billing_addr->Line1 = $project['Client']['address_line1'];
					$billing_addr->Line2 = $project['Client']['address_line2'];
					$billing_addr->City = $project['Client']['city'];
					App::import('Model', 'GeoCountry');
					$this->GeoCountry = new GeoCountry();
					$country = $this->GeoCountry->find('first', array(
						'conditions' => array(
							'GeoCountry.id' => $project['Client']['geo_country_id']
						)
					));
					if ($country) {
						$billing_addr->Country = $country['GeoCountry']['country'];
					}
					App::import('Model', 'GeoState');
					$this->GeoState = new GeoState();
					$state = $this->GeoState->find('first', array(
						'conditions' => array(
							'GeoState.id' => $project['Client']['geo_state_id']
						)
					));
					if ($state && $state['GeoState']['state_abbr'] != 'N/A') {
						$billing_addr->CountrySubDivisionCode = $state['GeoState']['state_abbr'];
					}
					else {
						$billing_addr->CountrySubDivisionCode = null;
					}

					$billing_addr->PostalCode = $project['Client']['postal_code'];
					$customer_object->BillAddr = $billing_addr;
				}
				$customer_object->Name = $invoice['Invoice']['name'];
				$customer_object->DisplayName = $invoice['Invoice']['name'] . rand();
				$customer_object->FullyQualifiedName = $invoice['Invoice']['name'];
				$customer_object->CompanyName = $project['Client']['client_name'];
				
				$name = explode(' ', preg_replace('!\s+!', ' ', $invoice['Invoice']['name']));
				if (!empty($name[0])) {
					$customer_object->GivenName = $name[0];
				}
				if (!empty($name[2])) {
					$customer_object->FamilyName = $name[2];
					$customer_object->MiddleName = $name[1];
				}
				if (!empty($name[1]) && empty($name[2])) {
					$customer_object->FamilyName = $name[1];
				}
				/* Creating email address object to bind customer email*/
				$email_address = new IPPEmailAddress();
				$email_address->Address = $project['Client']['billing_email'];
				$customer_object->PrimaryEmailAddr = $email_address;
				
				/* Making api call to add customer info */
				$create_customer_result = $this->data_service->Add($customer_object);
				$invoice_object->CustomerRef = $create_customer_result->Id;
				App::import('Model', 'Client');
				$this->Client = new Client();
				$this->Client->save(array(
					'Client' => array(
						'id' => $project['Client']['id'],
						'quickbook_customer_id' => $create_customer_result->Id,
					)
				), true, array('quickbook_customer_id'));
			}
			else {
				$invoice_object->CustomerRef = $project['Client']['quickbook_customer_id'];
				$customer_object = new IPPCustomer();
				$customer_object->Id = $project['Client']['quickbook_customer_id'];
				
				/* Finding existing quickbook customer data for updating */
				$customer = $this->data_service->FindById($customer_object);
				
				$name = explode(' ', preg_replace('!\s+!', ' ', $invoice['Invoice']['name']));
				if (!empty($name[0])) {
					$customer->GivenName = $name[0];
				}
				if (!empty($name[2])) {
					$customer->FamilyName = $name[2];
					$customer->MiddleName = $name[1];
				}
				if (!empty($name[1]) && empty($name[2])) {
					$customer->FamilyName = $name[1];
				}
				
				$customer->CompanyName = $project['Client']['client_name'];
				$customer->FullyQualifiedName = $invoice['Invoice']['name'];
				
				/* Creating billing address object to bind customer address*/
				if (!empty($project['Client']['address_line1'])) {
					$billing_addr = new IPPPhysicalAddress();
					$billing_addr->Line1 = $project['Client']['address_line1'];
					$billing_addr->Line2 = $project['Client']['address_line2'];
					$billing_addr->City = $project['Client']['city'];
					App::import('Model', 'GeoCountry');
					$this->GeoCountry = new GeoCountry();
					$country = $this->GeoCountry->find('first', array(
						'conditions' => array(
							'GeoCountry.id' => $project['Client']['geo_country_id']
						)
					));
					if ($country) {
						$billing_addr->Country = $country['GeoCountry']['country'];
					}
					App::import('Model', 'GeoState');
					$this->GeoState = new GeoState();
					$state = $this->GeoState->find('first', array(
						'conditions' => array(
							'GeoState.id' => $project['Client']['geo_state_id']
						)
					));
					if ($state && $state['GeoState']['state_abbr'] != 'N/A') {
						$billing_addr->CountrySubDivisionCode = $state['GeoState']['state_abbr'];
					}
					else {
						$billing_addr->CountrySubDivisionCode = null;
					}
					
					$billing_addr->PostalCode = $project['Client']['postal_code'];
					$customer->BillAddr = $billing_addr;
				}
				/* Creating email address object to bind customer email*/
				$email_address = new IPPEmailAddress();
				$email_address->Address = $invoice['Invoice']['email'];
				$customer->PrimaryEmailAddr = $email_address;
				
				/* Making api call to update customer info */
				$customer_update_result = $this->data_service->Update($customer);
			}
			
			
			$invoice_object->TxnDate = date('Y-m-d', strtotime($invoice['Invoice']['date']));
			$invoice_object->DueDate = date('Y-m-d', strtotime($invoice['Invoice']['date']. '+' . $invoice['Invoice']['terms'] . ' days'));
			$invoice_object->DocNumber = $invoice['Invoice']['number'];
			
			/* Creating address object to bind invoice billing address*/
			if (!empty($invoice['Invoice']['address_line1'])) {
				$billing_addr = new IPPPhysicalAddress();
				$billing_addr->Line1 = $invoice['Invoice']['address_line1'];
				$billing_addr->Line2 = $invoice['Invoice']['address_line2'];
				$billing_addr->City = $invoice['Invoice']['city'];
				$billing_addr->Country = $invoice['GeoCountry']['country'];
				if (!empty($invoice['GeoState']['state_abbr']) && $invoice['GeoState']['state_abbr'] != 'N/A') {
					$billing_addr->CountrySubDivisionCode = $invoice['GeoState']['state_abbr'];
					
				}
				else {
					$billing_addr->CountrySubDivisionCode = null;
				}
				$billing_addr->PostalCode = $invoice['Invoice']['postal_code'];
				$invoice_object->BillAddr = $billing_addr;
			}
			
			/* Creating email address object to bind invoice billing email*/
			$email_address = new IPPEmailAddress();
			$email_address->Address = $invoice['Invoice']['email'];
			$invoice_object->BillEmail = $email_address;
			
			$items = array();
			if (!empty($invoice['InvoiceRow'])) {
				foreach ($invoice['InvoiceRow'] as $invoice_row) {
					$line = new IPPline();
					$line->Amount = $invoice_row['unit_price'] * $invoice_row['quantity'];
					$line->DetailType = 'SalesItemLineDetail';
					$line->Description = $invoice_row['description'];
					$sales_item_line_detail = new IPPSalesItemLineDetail();
					$sales_item_line_detail->UnitPrice = $invoice_row['unit_price'];
					$sales_item_line_detail->Qty = $invoice_row['quantity'];
					$line->SalesItemLineDetail = $sales_item_line_detail;
					$invoice_object->Line[] = $line; // Add the line item
					$items[] = $line;
				}
			}
		}
		catch (Exception $e) {
			CakeLog::write('quickbooks', 'Customer creation failed on Quickbook due to some API data error for the client id : '.$project['Client']['id']);
			return false;
		}
		
		try {
			if (empty($invoice['Invoice']['quickbook_invoice_id'])) {
				/* Making API call to create invoice */
				
				$invoice_object->SalesTermRef = $this->add_term($invoice['Invoice']['terms']); // Add the term id				
				$resulting_invoice = $this->data_service->Add($invoice_object);
				App::import('Model', 'Invoice');
				$this->Invoice = new Invoice();
				$this->Invoice->save(array(
					'Invoice' => array(
						'id' => $invoice['Invoice']['id'],
						'quickbook_invoice_id' => $resulting_invoice->Id,
					)
				), true, array('quickbook_invoice_id'));
			}
			else {
				/* Making API call to get existing invoice data */
				$invoices = $this->data_service->Query("SELECT * FROM Invoice WHERE Id = '".$invoice['Invoice']['quickbook_invoice_id'] . "'");

				$invoice_object = $invoices[0];
				$invoice_object->Line = '';
				if (!empty($billing_addr)) {
					$invoice_object->BillAddr = $billing_addr;
				}
				
				$invoice_object->BillEmail = $email_address;
				$invoice_object->SalesTermRef = $this->add_term($invoice['Invoice']['terms']); // Add the term id
				
				$invoice_object->TotalAmt = $invoice['Invoice']['subtotal'];
				$invoice_object->Balance = $invoice['Invoice']['subtotal'];
				$invoice_object->DueDate = date('Y-m-d', strtotime($invoice['Invoice']['date']. '+' . $invoice['Invoice']['terms'] . ' days'));
				$invoice_object->TxnDate = date('Y-m-d', strtotime($invoice['Invoice']['date']));
				$invoice_object->DocNumber = $invoice['Invoice']['number'];
			
				foreach ($items as $item) {
					$invoice_object->Line[] = $item;
				}
				/* Making API call to update existing invoice data */
				$resulting_invoice = $this->data_service->Update($invoice_object);
			}
		}
		catch (Exception $e) {
			if (empty($invoice['Invoice']['quickbook_invoice_id'])) {
				CakeLog::write('quickbooks', '#'.$invoice['Invoice']['project_id'].' Invoice creation failed on Quickbook due to API error for the customer\'s quickbook id : ' . $create_customer_result->Id);
			}
			else {
				CakeLog::write('quickbooks', '#'.$invoice['Invoice']['project_id'].' Invoice updating failed on Quickbook due to API error for the invoice quickbook id : ' . $invoice['Invoice']['quickbook_invoice_id']);
			}
			return false;
		}
		
		return true;
		
	}
	
	function update_customer($quickbook_customer_id, $client) {
		$this->init();
		$customer_object = new IPPCustomer();
		$customer_object->Id = $quickbook_customer_id;
		try {
			/* Making API call to get existing customer data */
			$customer = $this->data_service->FindById($customer_object);
			
			$customer->CompanyName = $client['Client']['client_name'];
			
			$billing_addr = new IPPPhysicalAddress();
			$billing_addr->Line1 = $client['Client']['address_line1'];
			$billing_addr->Line2 = $client['Client']['address_line2'];
			$billing_addr->City = $client['Client']['city'];
			$billing_addr->Country = $client['GeoCountry']['country'];
			if (!empty($client['GeoState']['state_abbr']) && $client['GeoState']['state_abbr'] != 'N/A') {
				$billing_addr->CountrySubDivisionCode = $client['GeoState']['state_abbr'];
			}
			else {
				$billing_addr->CountrySubDivisionCode = null;
			}
			$billing_addr->PostalCode = $client['Client']['postal_code'];
			$customer->BillAddr = $billing_addr;
			
			$email_address = new IPPEmailAddress();
			$email_address->Address = $client['Client']['billing_email'];
			$customer->PrimaryEmailAddr = $email_address;
			/* Making API call to update existing customer data */
			$this->data_service->Update($customer);
		}
		catch (Exception $e) {
			CakeLog::write('quickbooks', 'Customer updation failed on Quickbook due to some API data error for the customer\'s quickbook id : '.$quickbook_customer_id);
		}
	}
	
	function update_quickbook_customer($id) {
		if (empty($id)) {
			return;
		}
		APP::import('Model', 'Site');
		$this->Site = new Site();
		
		$quickbook_connect_status = $this->Site->get_quickbook_status();
		if ($quickbook_connect_status == QUICKBOOK_OAUTH_CONNECTED || $quickbook_connect_status == QUICKBOOK_OAUTH_EXPIRING_SOON) {
			
			APP::import('Model', 'Client');
			$this->Client = new Client();
		
			$this->Client->unbindModel(array(
				'hasOne' => array(
					'Contact'
				),
				'belongsTo' => array(
					'Group'
				)
			));
			
			$client = $this->Client->find('first', array(
				'conditions' => array(
					'Client.id' => $id,
					'Client.deleted' => false
				)
			));
			if ($client && !empty($client['Client']['quickbook_customer_id'])) {
				$this->update_customer($client['Client']['quickbook_customer_id'], $client);
			}
		}
		
		return;
	}
	
	function add_term($invoice_term) {
		
		if (empty($invoice_term)) {
			return false;
		}

		try {
			$terms = $this->data_service->Query("SELECT * FROM Term");
			if (!empty($terms)) {
				foreach ($terms as $term) {
					if ($invoice_term == $term->DueDays) {
						return $term->Id;
					}
				}
			}

			$term = new IPPTerm();
			$term->Name = 'NET ' . $invoice_term;
			$term->DueDays = $invoice_term;
			$term_response = $this->data_service->Add($term);
			return $term_response->Id;
		}
		catch (Exception $e) {
			CakeLog::write('quickbooks', 'Invoice term creation failed on Quickbook due to some API data error for the term : NET '.$invoice_term);
		}
	}

	function create_purchase_order($invoice, $quickbook_invoice_id, $term) {
		try {
			$purchase_order = new IPPPurchaseOrder();
			$purchase_order->Name = 'PO_' . $quickbook_invoice_id;
			$purchase_order->DocNumber = $invoice['Invoice']['number'];
			$purchase_order->TxnDate =  date('Y-m-d', strtotime($invoice['Invoice']['date']));
			$purchase_order->DueDate = date('Y-m-d', strtotime($invoice['Invoice']['date']. '+' . $invoice['Invoice']['terms'] . ' days'));
			// $purchase_order->SalesTermRef = $term;
			$purchase_order->TotalAmt = $invoice['Invoice']['subtotal'];
			
			$items = array();
			if (!empty($invoice['InvoiceRow'])) {
				foreach ($invoice['InvoiceRow'] as $invoice_row) {
					$line = new IPPline();
					$line->Amount = $invoice_row['unit_price'] * $invoice_row['quantity'];
					$line->DetailType = 'ItemBasedExpenseLineDetail';
					$line->Description = $invoice_row['description'];
					$sales_item_line_detail = new IPPItemBasedExpenseLineDetail();
					$sales_item_line_detail->UnitPrice = $invoice_row['unit_price'];
					$sales_item_line_detail->Qty = $invoice_row['quantity'];
					$sales_item_line_detail->ItemRef = $invoice_row['id'];
					$line->ItemBasedExpenseLineDetail = $sales_item_line_detail;
					$purchase_order->Line[] = $line;
				}
			}

			$this->data_service->Add($purchase_order);
		}
		catch (Exception $e) {
			CakeLog::write('quickbooks', 'Purchase order creation failed on Quickbook due to some API data error for the invoice quickbook id : '.$quickbook_invoice_id);
		}
	}
}