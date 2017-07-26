<?php
App::uses('Shell', 'Console');
App::import('Lib', 'Utilities');

class KeenShell extends AppShell {
	
	var $uses = array('KeenUserTrait', 'User', 'UserAcquisition', 'QueryProfile', 'Source', 'SourceMapping');
	
	public function import() {
		if (!isset($this->args[0])) {
			$this->out('Please povide file name as argument');
			return false;
		}
		
		$handle = fopen(WWW_ROOT . '/files/keen/'.$this->args[0], "r");
		if ($handle === FALSE) {
			$this->out('The following file not found. '.WWW_ROOT . '/files/keen/'.$this->args[0]);
			return false;
		}
		
		$header = fgetcsv($handle);
		if (!$this->file_indexes($header)) {
			$this->out('The file is missing the following header fields. '. implode(', ', $this->missing_indexes));
			return false;
		}
		
		$row = 1;
		while (($data = fgetcsv($handle)) !== FALSE) {
			$row++;
			$user_id = '';
			if (!empty($data[$this->indexes['user.userId']])) {
				$user_id = $data[$this->indexes['user.userId']];
			}
			elseif (!empty($data[$this->indexes['user.userId']])) {
				$user_id = $data[$this->indexes['user.userId']];
			}
			elseif (!empty($data[$this->indexes['user.traits.email']])) {
				$user = $this->User->find('first', array(
					'fields' => array('User.id'),
					'conditions' => array(
						'User.email' => $data[$this->indexes['user.traits.email']]
					)
				));
				if ($user) {
					$user_id = $user['User']['id'];
				}
			}
			
			if ($user_id == '') {
				$this->out('User not identified for row #'. $row);
				continue;
			}
			
			$count = $this->KeenUserTrait->find('count', array(
				'conditions' => array(
					'KeenUserTrait.keen_id' => $data[$this->indexes['keen.id']]
				)
			));
			if ($count > 0) {
				$this->out('Keen id already imported for row#'. $row);
				continue;
			}
			
			$this->KeenUserTrait->create();
			$this->KeenUserTrait->save(array('KeenUserTrait' => array(
				'keen_id' => $data[$this->indexes['keen.id']],
				'user_id' => $user_id,
				'acquisition_partner' => $data[$this->indexes['user.traits.acquisition_partner']],
				'campaign' => $data[$this->indexes['user.traits.campaign']],
				'lander' => $data[$this->indexes['user.traits.lander']],
				'medium' => $data[$this->indexes['user.traits.medium']],
				'source' => $data[$this->indexes['user.traits.source']],
				'term' => $data[$this->indexes['user.traits.term']],
				'country' => $data[$this->indexes['user.traits.country']],
				'gender' => $data[$this->indexes['user.traits.gender']],
			)));
		}
		
		fclose($handle);
		$this->out('Import completed');
	}
	
	function file_indexes($header) {
		$indexes = array();
		$indexes['keen.id'] = array_search('keen.id', $header);
		$indexes['user.traits.email'] = array_search('user.traits.email', $header);
		$indexes['user.userId'] = array_search('user.userId', $header);
		$indexes['userId'] = array_search('userId', $header);
		$indexes['user.traits.acquisition_partner'] = array_search('user.traits.acquisition_partner', $header);
		$indexes['user.traits.source'] = array_search('user.traits.source', $header);
		$indexes['user.traits.medium'] = array_search('user.traits.medium', $header);
		$indexes['user.traits.term'] = array_search('user.traits.term', $header);
		$indexes['user.traits.campaign'] = array_search('user.traits.campaign', $header);
		$indexes['user.traits.lander'] = array_search('user.traits.lander', $header);
		$indexes['user.traits.gender'] = array_search('user.traits.gender', $header);
		$indexes['user.traits.country'] = array_search('user.traits.country', $header);
		$this->indexes = $indexes;
		$this->missing_indexes = array();
		foreach ($indexes as $key => $val) {
			if ($val === false) {
				$this->missing_indexes[] = $key;
			}
		}
		
		if (!empty($this->missing_indexes)) {
			return false;
		}
		
		return true;
	}
	
	public function update_missing_data() {
		while (true) {
			$keen_user_traits = $this->KeenUserTrait->find('all', array(
				'conditions' => array(
					'KeenUserTrait.status' => 'imported'
				),
				'limit' => 500
			));
			
			if (!$keen_user_traits) {
				$this->out('Update completed.');
				break;
			}

			$this->out('Processing '.count($keen_user_traits). ' records.');
			foreach ($keen_user_traits as $trait) {
				$user_acquisition = $this->UserAcquisition->find('first', array(
					'conditions' => array(
						'UserAcquisition.user_id' => $trait['KeenUserTrait']['user_id']
					)
				));
				$query_profile = $this->QueryProfile->find('first', array(
					'fields' => array('QueryProfile.country', 'QueryProfile.gender'),
					'conditions' => array(
						'QueryProfile.user_id' => $trait['KeenUserTrait']['user_id']
					)
				));
				$keen_values = array(
					'source' => $trait['KeenUserTrait']['source'],
					'acquisition_partner' => $trait['KeenUserTrait']['acquisition_partner'],
					'campaign' => $trait['KeenUserTrait']['campaign'],
					'lander' => $trait['KeenUserTrait']['lander'],
					'medium' => $trait['KeenUserTrait']['medium'],
					'term' => $trait['KeenUserTrait']['term'],
					'country' => $trait['KeenUserTrait']['country'],
					'gender' => $trait['KeenUserTrait']['gender']
				);
				
				if ($user_acquisition) {
					$params = $user_acquisition['UserAcquisition']['params'];
					$acquisition_partner = null;
					if (isset($params['acquisition_partner']) && !empty($params['acquisition_partner'])) {
						$acquisition_partner = $params['acquisition_partner'];
					}
					elseif (!empty($trait['KeenUserTrait']['source'])) {
						$acquisition_partner = $this->get_acquisition_partner($trait['KeenUserTrait']['source']);
					}
					// we missed this check on last update and now we need "fix_acquistion_partners" to run
					elseif(!empty($params['source'])) {
						$acquisition_partner = $this->get_acquisition_partner($params['source']);
					}
					
					$user_acquisition_values = array(
						'source' => $user_acquisition['UserAcquisition']['source'],
						'acquisition_partner' => $acquisition_partner,
						'campaign' => isset($params['utm_campaign']) ? $params['utm_campaign'] : null,
						'lander' => isset($params['lander']) ? $params['lander'] : null,
						'medium' => isset($params['utm_medium']) ? $params['utm_medium'] : null,
						'term' => isset($params['utm_term']) ? $params['utm_term'] : null,
						'country' => $query_profile['QueryProfile']['country'],
						'gender' => $query_profile['QueryProfile']['gender']
					);
				}
				// we can also update acquisition partner even if user_acquisition record does not exist
				elseif (empty($trait['KeenUserTrait']['acquisition_partner']) && !empty($trait['KeenUserTrait']['source']) ) {
					$acquisition_partner = $this->get_acquisition_partner($trait['KeenUserTrait']['source']);
					$user_acquisition_values = array(
						'source' => $trait['KeenUserTrait']['source'],
						'acquisition_partner' => $acquisition_partner,
						'campaign' => $trait['KeenUserTrait']['campaign'],
						'lander' => $trait['KeenUserTrait']['lander'],
						'medium' => $trait['KeenUserTrait']['medium'],
						'term' => $trait['KeenUserTrait']['term'],
						'country' => $query_profile['QueryProfile']['country'],
						'gender' => $query_profile['QueryProfile']['gender']
					);
				}
				else {
					$this->KeenUserTrait->create();
					$this->KeenUserTrait->save(array('KeenUserTrait' => array(
						'id' => $trait['KeenUserTrait']['id'],
						'status' => 'ignored'
					)), true, array('status'));
					continue;
				}

				if ($keen_values == $user_acquisition_values) {
					$this->KeenUserTrait->create();
					$this->KeenUserTrait->save(array('KeenUserTrait' => array(
						'id' => $trait['KeenUserTrait']['id'],
						'status' => 'ignored'
					)), true, array('status'));
				}
				else {
					$user_acquisition_values['id'] = $trait['KeenUserTrait']['id'];
					$user_acquisition_values['status'] = 'updated';
					$this->KeenUserTrait->create();
					$this->KeenUserTrait->save(array('KeenUserTrait' => $user_acquisition_values), true, array_keys($user_acquisition_values));
				}
			}
		}
	}
	
	// Due to missing a check in the previous update script we need to run this fix script to populate acquistion partners.
	public function fix_acquisition_partners() {
		while (true) {
			$keen_user_traits = $this->KeenUserTrait->find('all', array(
				'conditions' => array(
					'OR' => array(
						'KeenUserTrait.acquisition_partner is null',
						'KeenUserTrait.acquisition_partner' => ''
					),
					'KeenUserTrait.source <>' => '',
					'KeenUserTrait.status' => 'updated'
				),
				'limit' => 500
			));
			
			if (!$keen_user_traits) {
				$this->out('fix completed.');
				break;
			}
			
			$this->out('Processing '.count($keen_user_traits). ' records.');
			foreach ($keen_user_traits as $trait) {
				$acquisition_partner = $this->get_acquisition_partner($trait['KeenUserTrait']['source']);
				if ($acquisition_partner) {
					$this->KeenUserTrait->create();
					$this->KeenUserTrait->save(array('KeenUserTrait' => array(
						'id' => $trait['KeenUserTrait']['id'],
						'acquisition_partner' => $acquisition_partner,
					)), true, array('acquisition_partner'));
					$this->out('Success: Acquisition partner: '.$acquisition_partner. ' updated for source: '.$trait['KeenUserTrait']['source']);
				}
				else {
					$this->KeenUserTrait->create();
					$this->KeenUserTrait->save(array('KeenUserTrait' => array(
						'id' => $trait['KeenUserTrait']['id'],
						'status' => 'updated_without_ap', // updated other fields but not acquisition_partner. We need this status to avoid this script run indefinately
					)), true, array('status'));
					$message = 'Error: Acquisition partner not found for source: '.$trait['KeenUserTrait']['source'];
					cakeLog::write('keen_import', $message);
					$this->out($message);
				}
			}
		}
	}
	
	private function get_acquisition_partner($source) {
		$acquisition_partner = null;
		$source_lookup = $this->Source->find('first', array(
			'conditions' => array(
				'Source.abbr' => $source
			)
		)); 
		if ($source_lookup) {
			$acquisition_partner = $source_lookup['AcquisitionPartner']['name']; 
		}
		else {
			$source_mapping = $this->SourceMapping->find('first', array(
				'conditions' => array(
					'SourceMapping.utm_source' => $source,
					'SourceMapping.deleted' => null
				)
			));
			if ($source_mapping) {
				$acquisition_partner = $source_mapping['AcquisitionPartner']['name'];
			}
		}
		
		return $acquisition_partner;
	}
	
	// arg0: file name
	// arg1: event
	public function update_event() {
		if (!isset($this->args[0])) {
			$this->out('Please povide file name as argument 1');
			return false;
		}
		
		if (!isset($this->args[1])) {
			$this->out('Please povide event name as argument 2');
			return false;
		}
		
		$handle = fopen(WWW_ROOT . '/files/keen/'.$this->args[0], "r");
		if ($handle === FALSE) {
			$this->out('The following file not found. '.WWW_ROOT . '/files/keen/'.$this->args[0]);
			return false;
		}
		
		$header = fgetcsv($handle);
		if (!$this->file_indexes($header)) {
			$this->out('The file is missing the following header fields. '. implode(', ', $this->missing_indexes));
			return false;
		}
		
		$row = 0;
		while (($data = fgetcsv($handle)) !== FALSE) {
			$row++;
			if ($row % 5000 == 0) {
				$this->out($row. ' rows processed.');
			}
			
			$keen_user_trait = $this->KeenUserTrait->find('first', array(
				'fields' => array('KeenUserTrait.id', 'KeenUserTrait.keen_id'),
				'conditions' => array(
					'KeenUserTrait.keen_id' => $data[$this->indexes['keen.id']],
					'KeenUserTrait.status' => array('updated', 'updated_without_ap'),
					'KeenUserTrait.event is null',
				)
			));
			if (!$keen_user_trait) {
				continue;
			}
			
			$this->KeenUserTrait->create();
			$this->KeenUserTrait->save(array('KeenUserTrait' => array(
				'id' => $keen_user_trait['KeenUserTrait']['id'],
				'event' => $this->args[1]
			)), true, array('event'));
			
			$this->out('Keen id: '. $keen_user_trait['KeenUserTrait']['keen_id']. ' event updated.');
		}
		
		fclose($handle);
		$this->out('Update completed');
	}
}