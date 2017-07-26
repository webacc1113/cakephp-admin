<?php
App::import('Lib', 'MintVineUser');

class ReportPanelistsShell extends AppShell {
	public $uses = array('Transaction', 'User', 'UserAddress');

	public function export_withdrawals() {
		if (!isset($this->args[0])) {
			return false;
		}
		$this->out('Starting withdrawal report');
		$transactions = $this->Transaction->find('all', array(
			'fields' => 'DISTINCT(Transaction.user_id) as user_id',
			'conditions' => array(
				'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
				'Transaction.deleted' => null
			)
		));
		$user_ids = Hash::extract($transactions, '{n}.Transaction.user_id');
		$keys = array(
			'user_id',
			'user_email',
			'first_name',
			'last_name',
			'country',
			'postal_code',
			'postal_code_extended',
			'state',
			'city',
			'address_line1',
			'address_line2',
			'county',
			'county_fips',
			'total_amount'
		);
		$report_panelists = array();
		$total = count($user_ids);
		$this->out('Found '.$total.' users');
		$i = 0; 
		foreach ($user_ids as $user_id) {
			$total_amount = $this->Transaction->find('first', array(
				'fields' => array('SUM(Transaction.amount) AS total_amount'),
				'conditions' => array(
					'Transaction.user_id' => $user_id,
					'Transaction.type_id' => TRANSACTION_WITHDRAWAL,
					'Transaction.deleted' => null,
					'Transaction.executed LIKE' => $this->args[0] . '%'
				),
				'recursive' => -1
			));
			$withdrawn = -1 * $total_amount[0]['total_amount']; // inverse
			if ($withdrawn == 0) {
				continue;
			}

			$user = $this->User->find('first', array(
				'fields' => array('User.email'),
				'conditions' => array(
					'User.id' => $user_id
				),
				'recursive' => -1
			));
			if (!$user) {
				continue;
			}
			$report_panelist = array();
			$report_panelist['user_email'] = $user['User']['email'];
			$report_panelist['total_amount'] = number_format(round($withdrawn / 100, 2), 2);
			
			$user_address = $this->UserAddress->find('first', array(
				'conditions' => array(
					'UserAddress.user_id' => $user_id,
					'UserAddress.deleted' => false,
				)
			));
			if ($user_address) {
				foreach ($user_address['UserAddress'] as $key => $value) {
					if (in_array($key, $keys)) {
						$report_panelist[$key] = $value;
					}
				}
			}
			$report_panelists[] = $report_panelist;
			$i++;
			$this->out($i.'/'.$total); 
		}
		
		if (!is_dir(WWW_ROOT . 'files/panelist_reports/')) {
			mkdir(WWW_ROOT . 'files/panelist_reports/');
		}
		$file_name = 'panelist_report_' . $this->args[0] . '.csv';
		$file = WWW_ROOT . 'files/panelist_reports/' . $file_name;
		$fp = fopen($file, "w");
		fputcsv($fp, $keys);
		foreach ($report_panelists as $report_panelist) {
			$csv_arr = array();
			foreach ($keys as $key) {
				$csv_arr[] = isset($report_panelist[$key]) ? $report_panelist[$key]: '';
			}
			fputcsv($fp, $csv_arr);
		}
		fclose($fp);
		$this->out($file.' has been generated successfully.');
	}
}