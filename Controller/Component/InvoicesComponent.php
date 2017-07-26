<?php

App::uses('Component', 'Controller');

class InvoicesComponent extends Component {
	var $components = array('QuickBook');
	
	function save($invoice_id) {
		$models_to_import = array('Invoice', 'ProjectAdmin');
		foreach ($models_to_import as $model_to_import) {
			App::import('Model', $model_to_import);
			$this->$model_to_import = new $model_to_import;
		}
		
		$this->Invoice->bindModel(array(
			'belongsTo' => array(
				'GeoCountry',
				'GeoState',
				'Project' => array(
					'fields' => array(
						'id',
					)
				)
			)
		));
		
		$invoice = $this->Invoice->findById($invoice_id);
		$account_managers = array();
		
		$this->ProjectAdmin->bindModel(array('belongsTo' => array(
			'Admin'
		)));
		$project_admins = $this->ProjectAdmin->find('all', array(
			'fields' => array('Admin.admin_user'),
			'conditions' => array(
				'ProjectAdmin.project_id' => $invoice['Project']['id'],
				'ProjectAdmin.is_am' => true
			)
		));
		if ($project_admins) {
			foreach ($project_admins as $project_admin) {
				$account_managers[] = $project_admin['Admin']['admin_user'];
			}
		}
		
		if (!empty($account_managers)) {
			natcasesort($account_managers);
			$account_manager = implode(', ', $account_managers);
		}
		
		if (empty($invoice['Invoice']['subtotal'])) {
			return false;
		}
		App::import('Model', 'Site');
		$this->Site = new Site;
		$quickbook_connect_status = $this->Site->get_quickbook_status();
		if ($quickbook_connect_status == QUICKBOOK_OAUTH_CONNECTED || $quickbook_connect_status == QUICKBOOK_OAUTH_EXPIRING_SOON) {
			$this->QuickBook->create_invoice($invoice);
		}
		// Save invoice as html
		$view = new View();
		$view->set(compact('invoice', 'account_manager'));
		$viewdata = $view->render('/Invoices/invoice', 'invoice');
		$path = WWW_ROOT . 'files/html/' . $invoice['Invoice']['uuid'] . '.html';
		if (!file_exists(WWW_ROOT.'/files/html')) {
			mkdir(WWW_ROOT.'/files/html', 0775); 
		}
		$file = new File($path, true);
		$file->write($viewdata);
		
		CakePlugin::load('Uploader');
		App::import('Vendor', 'Uploader.S3');
		
		App::import('Model', 'Setting');
		$this->Setting = new Setting;
		
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
		
		$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);
		$aws_filename = 'files/html/' . $invoice['Invoice']['uuid'] . '.html';
		
		$headers = array();
		$S3->putObject($S3->inputFile($path), $settings['s3.bucket'], $aws_filename, S3::ACL_PRIVATE, array(), $headers);
		
		//Save Invoice as PDF
		App::import('Vendor', 'Pdfcrowd');
		try {
			$client = new PdfCrowd(PDFCROWD_USER, PDFCROWD_API_KEY);
			$client->setPageWidth("8.5in");
			$client->setPageHeight("11in");
			$client->setPageMargins("0.2in", "0.2in", "0.2in", "0.2in");
			$client->setInitialPdfZoomType(Pdfcrowd::FIT_PAGE);
			$client->setPageLayout(Pdfcrowd::CONTINUOUS);
			$pdf = $client->convertFile(WWW_ROOT . 'files/html/' . $invoice['Invoice']['uuid'] . '.html');
			//Save file as pdf
			$path = WWW_ROOT . 'files/pdf/Inv_'. $invoice['Invoice']['project_id'] .'_BRInc.pdf';
			if (!file_exists(WWW_ROOT.'/files/pdf')) {
				mkdir(WWW_ROOT.'/files/pdf', 0775); 
			}
			$file = new File($path, true);
			$file->write($pdf);
			
			$S3 = new S3($settings['s3.access'], $settings['s3.secret'], false, $settings['s3.host']);
			$aws_filename = 'files/pdf/Inv_'. $invoice['Invoice']['project_id'] .'_BRInc.pdf';
			
			$headers = array(
				'Content-Disposition' => 'attachment; filename='.$invoice['Invoice']['project_id'] .'_BRInc.pdf'
			);
			
			$S3->putObject($S3->inputFile($path), $settings['s3.bucket'], $aws_filename, S3::ACL_PRIVATE, array(), $headers);
			
			//unlink($path);
		}
		catch (PdfcrowdException $why) {
			CakeLog::write('pdf_crowd', 'Pdf crowd exception : ' . $why->getMessage());
		}
		
		return true;
	}
}