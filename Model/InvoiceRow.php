<?php

App::uses('AppModel', 'Model');

class InvoiceRow extends AppModel {

	public $actsAs = array('Containable');
	
	public $belongsTo = array(
		'Invoice' => array(
			'className' => 'Invoice',
			'foreignKey' => 'invoice_id',
		),
	);
}
