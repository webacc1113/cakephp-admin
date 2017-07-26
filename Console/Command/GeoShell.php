<?php
App::uses('CakeEmail', 'Network/Email');
App::uses('View', 'View');
App::import('Lib', 'Utilities');
App::uses('CakeResponse', 'Network');
App::uses('HttpSocket', 'Network/Http');

class GeoShell extends AppShell {
	public $uses = array('UsState', 'UsCounty', 'UsCity', 'UsCityCounty', 'UsCbsa', 'UsDma', 'UsZipcode', 'UsCountyZip', 'GeoZip', 'RegionMapping');
	
	function main() { 
	}	
	
	public function import() {
		$this->out('Importing US States...');
		$this->import_states();
		$this->out('Done importing US states'); 
		
		$this->out('Importing US counties...');
		$this->import_counties();
		$this->out('Done importing US counties'); 
		
		$this->out('Importing US CBSAs...');
		$this->import_cbsas();
		$this->out('Done importing US CBSAs'); 
		
		$this->out('Importing DMAs...');
		$this->import_dmas();
		$this->out('Done importing DMAs');
		
	}
	
	// import all US states along with their FIPs codes
	public function import_states() {
		$local_file = WWW_ROOT.'files/national_county.txt'; 
		// this file is from the US Census site: https://www.census.gov/geo/reference/codes/cou.html
		$source_file = 'http://www2.census.gov/geo/docs/reference/codes/files/national_county.txt';
		
		// cache it locally
		if (!is_file($local_file)) {
			$http = new HttpSocket(array(
				'timeout' => 10,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$contents = $http->get($source_file); 			
			$file = new File($local_file, true, 0644);
			$file->write($contents->body);
		}
		
		$csv_rows = Utils::csv_to_array($local_file); 
		$states = array(
			'AE' => 'American Armed Forces',
			'AL' => 'Alabama',
			'AK' => 'Alaska',
			'AS' => 'American Samoa',
			'AZ' => 'Arizona',
			'AR' => 'Arkansas',
			'CA' => 'California',
			'CO' => 'Colorado',
			'CT' => 'Connecticut',
			'DE' => 'Delaware',
			'DC' => 'District of Columbia',
			'FL' => 'Florida',
			'GA' => 'Georgia',
			'GU' => 'Guam',
			'HI' => 'Hawaii',
			'ID' => 'Idaho',
			'IL' => 'Illinois',
			'IN' => 'Indiana',
			'IA' => 'Iowa',
			'KS' => 'Kansas',
			'KY' => 'Kentucky',
			'LA' => 'Louisiana',
			'ME' => 'Maine',
			'MD' => 'Maryland',
			'MA' => 'Massachusetts',
			'MI' => 'Michigan',
			'MN' => 'Minnesota',
			'MP' => 'Northern Mariana Islands',
			'MS' => 'Mississippi',
			'MO' => 'Missouri',
			'MT' => 'Montana',
			'NE' => 'Nebraska',
			'NV' => 'Nevada',
			'NH' => 'New Hampshire',
			'NJ' => 'New Jersey',
			'NM' => 'New Mexico',
			'NY' => 'New York',
			'NC' => 'North Carolina',
			'ND' => 'North Dakota',
			'OH' => 'Ohio',
			'OK' => 'Oklahoma',
			'OR' => 'Oregon',
			'PA' => 'Pennsylvania',
			'PR' => 'Puerto Rico',
			'RI' => 'Rhode Island',
			'SC' => 'South Carolina',
			'SD' => 'South Dakota',
			'TN' => 'Tennessee',
			'TX' => 'Texas',
			'UT' => 'Utah',
			'VI' => 'Virgin Islands',
			'VT' => 'Vermont',
			'UM' => 'United States Minor Outlying Islands', 
			'VA' => 'Virginia',
			'WA' => 'Washington',
			'WV' => 'West Virginia',
			'WI' => 'Wisconsin',
			'WY' => 'Wyoming',
		);
		
		foreach ($csv_rows as $csv_row) {
			$us_state = $this->UsState->find('first', array(
				'conditions' => array(
					'UsState.code' => $csv_row[0]
				)
			));
			if (!$us_state) {
				$this->UsState->create();
				$this->UsState->save(array('UsState' => array(
					'fips' => $csv_row[1],
					'code' => $csv_row[0],
					'name' => $states[$csv_row[0]]
				)));
				$this->out('State '.$states[$csv_row[0]].' added');
			} 
		}
	}
	
	public function import_counties() {
		
		$local_file = WWW_ROOT.'files/national_county.txt'; 
		// this file is from the US Census site: https://www.census.gov/geo/reference/codes/cou.html
		$source_file = 'http://www2.census.gov/geo/docs/reference/codes/files/national_county.txt';
		
		// cache it locally
		if (!is_file($local_file)) {
			$http = new HttpSocket(array(
				'timeout' => 10,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$contents = $http->get($source_file); 			
			$file = new File($local_file, true, 0644);
			$file->write($contents->body);
		}
		
		$csv_rows = Utils::csv_to_array($local_file); 
		foreach ($csv_rows as $csv_row) {
			/* values
				0 => state
				1 => state fip
				2 => county fip
				3 => county name
			 */
			$us_state = $this->UsState->find('first', array(
				'conditions' => array(
					'UsState.fips' => $csv_row[1]
				),
				'fields' => array('UsState.id')
			));
			
			$us_county = $this->UsCounty->find('first', array(
				'conditions' => array(
					'UsCounty.us_state_id' => $us_state['UsState']['id'],
					'UsCounty.fips' => $csv_row[2]
				)
			));
			if (!$us_county) {
				$this->UsCounty->create();
				$this->UsCounty->save(array('UsCounty' => array(
					'fips' => $csv_row[2],
					'name' => $csv_row[3],
					'us_state_id' => $us_state['UsState']['id']
				)));
				$this->out('County '.$csv_row[3].' ('.$csv_row[0].') added');
			} 
		}
	}
	
	// https://www.census.gov/geo/reference/codes/place.html
	public function import_cities() {
		// https://www.census.gov/geo/reference/codes/place.html
		$local_file = WWW_ROOT.'files/national_places.txt'; 
		$source_file = 'http://www2.census.gov/geo/docs/reference/codes/files/national_places.txt';
		
		// cache it locally
		if (!is_file($local_file)) {
			$http = new HttpSocket(array(
				'timeout' => 10,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$contents = $http->get($source_file); 			
			$file = new File($local_file, true, 0644);
			$file->write($contents->body);
		}
		
		$csv_rows = Utils::csv_to_array($local_file, '|'); 
		unset($csv_rows[0]); // header
		foreach ($csv_rows as $csv_row) {
			/* 
				0 => State
				1 => State FIP
				2 => Place FIP (note: these can be duplicated across states)
				3 => Place name (note: this contains some extra junk at the end of the string; pop it off)
				4 => Place type
				5 => Functional status (ignore)
				6 => Counties associated with city
			 */
			$name = $csv_row[3]; 
			$names = explode(' ', $csv_row[3]);
			array_pop($names); 
			$name = implode(' ', $names); 
			
			$us_state = $this->UsState->find('first', array(
				'conditions' => array(
					'UsState.fips' => $csv_row[1]
				),
				'fields' => array('UsState.id')
			));
			
			// does this city exist already?
			$us_city = $this->UsCity->find('first', array(
				'conditions' => array(
					'UsCity.fips' => $csv_row[2],
					'UsCity.us_state_id' => $us_state['UsState']['id']
				)
			));
			if ($us_city) {
				continue;
			}
			
			$us_counties = $this->UsCounty->find('list', array(
				'fields' => array('UsCounty.id', 'UsCounty.name'),
				'conditions' => array(
					'UsCounty.name' => explode(', ', $csv_row[6]),
					'UsCounty.us_state_id' => $us_state['UsState']['id']
				)
			));
			
			$usCitySource = $this->UsCity->getDataSource();
			$usCitySource->begin();
			$this->UsCity->create();
			$this->UsCity->save(array('UsCity' => array(
				'fips' => $csv_row[2],
				'name' => $name, 
				'type' => $csv_row[4],
				'us_state_id' => $us_state['UsState']['id']
			)));
			$us_city_id = $this->UsCity->getInsertId();
			$usCitySource->commit();
			
			if (!empty($us_counties)) {
				foreach ($us_counties as $us_county_id => $us_county_name) {
					$this->UsCityCounty->create();
					$this->UsCityCounty->save(array('UsCityCounty' => array(
						'us_city_id' => $us_city_id,
						'us_county_id' => $us_county_id
					)));
				}
			}
			
			$this->out('City '.$name.' ('.$csv_row[0].') added');
		}
	}
	
	public function import_cbsas() {
		// from http://www.census.gov/population/metro/data/def.html, but checked into SVN because it's an excel file
		$local_file = WWW_ROOT.'files/us-cbsas.csv'; 
		$csv_rows = Utils::csv_to_array($local_file, ','); 
		
		unset($csv_rows[0]); // header
		foreach ($csv_rows as $csv_row) {
			/*
				0 => cbsa code
				1 => metro division code
				2 => csa code
				3 => cbsa title
				4 => msa/microsa
				5 => metropolitan division title
				6 => csa title
				7 => county name
				8 => state name
				9 => state fips
				10 => fips county
			 */ 
			
			$csv_row[10] = str_pad($csv_row[10], 3, '0', STR_PAD_LEFT); // reformat to fips
			$csv_row[9] = str_pad($csv_row[9], 2, '0', STR_PAD_LEFT); // reformat to fips
			$us_cbsa = $this->UsCbsa->find('first', array(
				'conditions' => array(
					'UsCbsa.cbsa_code' => $csv_row[0]
				)
			));
			
			$us_state = $this->UsState->find('first', array(
				'conditions' => array(
					'UsState.fips' => $csv_row[9]
				)
			));
			$us_county = $this->UsCounty->find('first', array(
				'conditions' => array(
					'UsCounty.us_state_id' => $us_state['UsState']['id'],
					'UsCounty.fips' => $csv_row[10]
				)
			));
			if ($us_cbsa) {
				if (empty($us_county['UsCounty']['us_cbsa_id'])) {
					$this->UsCounty->create();
					$this->UsCounty->save(array('UsCounty' => array(
						'id' => $us_county['UsCounty']['id'],
						'us_cbsa_id' => $us_cbsa['UsCbsa']['id']
					)), true, array('us_cbsa_id'));
				}
				continue;
			}
			
			$usCbsaSource = $this->UsCbsa->getDataSource();
			$usCbsaSource->begin();
			$this->UsCbsa->create();
			$this->UsCbsa->save(array('UsCbsa' => array(
				'us_state_id' => $us_state['UsState']['id'],
				'cbsa_code' => $csv_row[0],
				'csa_code' => isset($csv_row[2]) ? $csv_row[2]: null,
				'cbsa_title' => isset($csv_row[3]) ? $csv_row[3]: null,
				'csa_title' => isset($csv_row[6]) ? $csv_row[6]: null,
				'metro_code' => isset($csv_row[1]) ? $csv_row[1]: null,
				'metro_title' => isset($csv_row[5]) ? $csv_row[5]: null, 
				'metropolitan' => $csv_row[4] == 'Metropolitan Statistical Area',
				'micropolitan' => $csv_row[4] == 'Micropolitan Statistical Area'
			)));
			$cbsa_id = $this->UsCbsa->getInsertId();
			$usCbsaSource->commit();
			
			if (empty($us_county['UsCounty']['us_cbsa_id'])) {
				$this->UsCounty->create();
				$this->UsCounty->save(array('UsCounty' => array(
					'id' => $us_county['UsCounty']['id'],
					'us_cbsa_id' => $cbsa_id
				)), true, array('us_cbsa_id'));
			}
			$this->out('Added '.$csv_row[3]); 
		}
	}
	
	// note: this will create some cities
	public function import_dmas() {
		// from https://developers.google.com/adwords/api/docs/appendix/cities-DMAregions; also checked-in to SVN
		$local_file = WWW_ROOT.'files/us-dmas.csv'; 
		$csv_rows = Utils::csv_to_array($local_file, ','); 
		
		unset($csv_rows[0]); // header
		foreach ($csv_rows as $csv_row) {
			/* 
				0 => City
				1 => Criteria ID (Internal Google #, ignore)
				2 => State
				3 => DMA Region
				4 => DMA Code
			 */
			$us_state = $this->UsState->find('first', array(
				'conditions' => array(
					'UsState.name' => $csv_row[2]
				)
			));
			if (!$us_state) {
				$this->out('ERROR: Missing state: '.$csv_row[2]); 
				continue;
			}
			
			$csv_row[4] = str_pad($csv_row[4], 3, '0', STR_PAD_LEFT); 
			$us_dma = $this->UsDma->find('first', array(
				'conditions' => array(
					'UsDma.dma_code' => $csv_row[4]
				)
			));
			
			$us_city = $this->UsCity->find('first', array(
				'conditions' => array(
					'UsCity.name' => $csv_row[0],
					'UsCity.us_state_id' => $us_state['UsState']['id']
				)
			));
			if (!$us_city) {
				$this->UsCity->create();
				$this->UsCity->save(array('UsCity' => array(
					'us_state_id' => $us_state['UsState']['id'],
					'name' => $csv_row[0],
					'type' => 'Unincorporated Place',
					'us_dma_id' => $us_dma['UsDma']['id'],
				)));
				$this->out('Created unincorporated city: '.$csv_row[0]); 
				continue;
			}
			if ($us_dma) {
				if (empty($us_city['UsCity']['us_dma_id'])) {
					$this->UsCity->create();
					$this->UsCity->save(array('UsCity' => array(
						'id' => $us_city['UsCity']['id'],
						'us_dma_id' => $us_dma['UsDma']['id']
					)), true, array('us_dma_id'));
				}
				continue;
			}
			
			$usDmaSource = $this->UsDma->getDataSource();
			$usDmaSource->begin();
			$this->UsDma->create();
			$this->UsDma->save(array('UsDma' => array(
				'us_state_id' => $us_state['UsState']['id'],
				'dma_region' => $csv_row[3],
				'dma_code' => $csv_row[4]
			)));
			$us_dma_id = $this->UsDma->getInsertId();
			$usDmaSource->commit();
			
			$this->UsCity->create();
			$this->UsCity->save(array('UsCity' => array(
				'id' => $us_city['UsCity']['id'],
				'us_dma_id' => $us_dma_id
			)), true, array('us_dma_id'));
			$this->out('DMA '.$csv_row[3].' added'); 
		}
	}
	
	public function import_zips() {
		
		$local_file = WWW_ROOT.'files/us-postal_codes.csv'; 
		// this file is from the US Census site: https://www.census.gov/geo/reference/codes/cou.html
		$source_file = 'http://www.unitedstateszipcodes.org/zip_code_database.csv';
		
		// cache it locally
		if (!is_file($local_file)) {
			$http = new HttpSocket(array(
				'timeout' => 10,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$contents = $http->get($source_file); 			
			$file = new File($local_file, true, 0644);
			$file->write($contents->body);
		}
		
		$csv_rows = Utils::csv_to_array($local_file, ','); 
		
		unset($csv_rows[0]); // header
		foreach ($csv_rows as $csv_row) {
			/* 
				0 => zip code
				1 => type
				2 => primary_city
				3 => acceptable_cities
				4 => unacceptable_cities
				5 => state code
				6 => county
				7 => timezone
				8 => area_codes
				9 => latitude
				10 => longitude
				11 => 
			 */
			$us_zipcode = $this->UsZipcode->find('first', array(
				'conditions' => array(
					'UsZipcode.code' => $csv_row[0]
				)
			));
			$us_state = $this->UsState->find('first', array(
				'conditions' => array(
					'UsState.code' => $csv_row[5]
				)
			));
			if (!$us_state) {
				$usStateSource = $this->UsState->getDataSource();
				$usStateSource->begin();
				$this->UsState->create();
				$this->UsState->save(array('UsState' => array(
					'code' => $csv_row[5]
				)));
				$us_state = $this->UsState->findById($this->UsState->getInsertId()); 
				$usStateSource->commit();
			}
			$us_county = false;
			if (!empty($csv_row[6])) {
				$us_county = $this->UsCounty->find('first', array(
					'conditions' => array(
						'UsCounty.name LIKE' => $csv_row[6].'%',
						'UsCounty.us_state_id' => $us_state['UsState']['id']
					)
				));
				if (!$us_county) {
					$this->out('Could not find '.$csv_row[6].' county in '.$us_state['UsState']['name']); 
				}
			}
			
			if ($us_county) {
				$zip_list = $this->UsCountyZip->find('list', array(
					'conditions' => array(
						'UsCountyZip.us_county_id' => $us_county['UsCounty']['id'],
					),
					'fields' => array('id', 'UsCountyZip.us_zipcode_id')
				));
				if ($us_zipcode && !array_key_exists($us_zipcode['UsZipcode']['id'], $zip_list)) {
					$this->UsCountyZip->create();
					$this->UsCountyZip->save(array('UsCountyZip' => array(
						'us_county_zip.us_zipcode_id' => $us_county['UsCounty']['id'],
						'us_county_zip.us_county_id' => $us_zipcode['UsZipcode']['id'],
					)));
				}
			}
			if ($us_zipcode) {
				continue;
			}
			$usZipcodeSource = $this->UsZipcode->getDataSource();
			$usZipcodeSource->begin();
			$this->UsZipcode->create();
			$this->UsZipcode->save(array('UsZipcode' => array(
				'code' => $csv_row[0],
				'us_state_id' => $us_state['UsState']['id']
			)));
			$us_zipcode_id = $this->UsZipcode->getInsertId();
			$usZipcodeSource->commit();
			
			if ($us_county && !array_key_exists($us_zipcode_id, $zip_list)) {
				$this->UsCountyZip->create();
				$this->UsCountyZip->save(array('UsCountyZip' => array(
					'us_county_zip.us_zipcode_id' => $us_county['UsCounty']['id'],
					'us_county_zip.us_county_id' => $us_zipcode_id,
				)));
			}
			$this->out('Postal code '.$csv_row[0].' created'); 
		}
	}
	
	// Import Canada postal codes data into geo_zips table
	public function import_ca_zips() {
		ini_set('memory_limit', '2048M');
		$file = WWW_ROOT.'files/geo/ca_full_postcodes_by_region.csv'; 
		if (!file_exists($file)) {
			echo 'File not found at '. $file;
			return;
		}
		
		$csv_rows = Utils::csv_to_array($file); 
		$row_count = count($csv_rows);
		$i = 1;
		foreach ($csv_rows as $csv_row) {
			/* values
				0 => ZIP_Postal
				1 => City
				2 => State_Province abbreviation
				4 => Time_Zone
			 */
			$count = $this->GeoZip->find('count', array(
				'conditions' => array(
					'GeoZip.zipcode' => $csv_row[0],
					'country_code' => 'CA'
				)
			));
			if ($count > 0) {
				continue;
			}
			
			$region = '';
			switch ($csv_row[2]) {
				case 'AB':
					$region =  'Alberta';
				break;	
				case 'BC':
					$region =  'British Columbia';
				break;	
				case 'MB':
					$region =  'Manitoba';
				break;	
				case 'NB':
					$region =  'New Brunswick';
				break;
				case 'NL':
					$region =  'Newfoundland and Labrador';
				break;	
				case 'NT':
					$region =  'Northwest Territories';
				break;	
				case 'NS':
					$region =  'Nova Scotia';
				break;	
				case 'NU':
					$region =  'Nunavut';
				break;	
				case 'ON':
					$region =  'Ontario';
				break;	
				case 'PE':
					$region =  'Prince Edward Island';
				break;	
				case 'QC':
					$region =  'Quebec';
				break;	
				case 'SK':
					$region =  'Saskatchewan';
				break;	
				case 'YT':
					$region =  'Yukon';
				break;	
			}
			
			if ($region == '') {
				echo 'Region '.$csv_row[2].' is invalid'. "\n";
				continue;
			}
			
			$this->GeoZip->create();
			$this->GeoZip->save(array('GeoZip' => array(
				'zipcode' => $csv_row[0],
				'city' => $csv_row[1],
				'country_code' => 'CA',
				'region' => $region,
				'timezone' => $csv_row[4]
			)));
			echo $csv_row[0]. ' saved. ('.$i.'/'.$row_count.' rows processed)'. "\n";
			$i++;
		}
	}
	
	// Update UK regions in geo_zips table
	public function update_gb_zips() {
		ini_set('memory_limit', '2048M');
		$file = WWW_ROOT.'files/geo/gb_partial_postcodes_by_region.csv'; 
		if (!file_exists($file)) {
			echo 'File not found at '. $file;
			return;
		}
		
		$csv_rows = Utils::csv_to_array($file);
		foreach ($csv_rows as $csv_row) {
			/* values
				0 => Partial postcode
				1 => City
				2 => region
			 */
			
			$geo_zips = $this->GeoZip->find('list', array(
				'fields' => array('id'),
				'conditions' => array(
					'country_code' => 'GB',
					'zipcode like' => $csv_row[0].'%'
				)
			));
			if (empty($geo_zips)) {
				continue;
			}
			
			$row_count = count($geo_zips);
			$i = 1;
			foreach ($geo_zips as $geo_zip_id) {
				$this->GeoZip->create();
				$this->GeoZip->save(array('GeoZip' => array(
					'id' => $geo_zip_id,
					'city' => $csv_row[1],
					'region' => $csv_row[2],
				)), true, array('city', 'region'));
				echo $i.'/'.$row_count.' rows processed for post codes starting from '.$csv_row[0]. "\n";
				$i++;
			}
		}
	}
	
	// import partial postal codes vs region data for UK and CA in region_mappings table
	public function import_region_mappings() {
		$file_ca = WWW_ROOT.'files/geo/ca_partial_postcodes_by_region.csv'; 
		$file_gb = WWW_ROOT.'files/geo/gb_partial_postcodes_by_region.csv'; 
		if (!file_exists($file_ca) || !file_exists($file_gb)) {
			echo 'Make sure the following files exist: '."\n";
			echo $file_ca. "\n";
			echo $file_gb. "\n";
			return;
		}
		
		$csv_rows = Utils::csv_to_array($file_ca); 
		foreach ($csv_rows as $csv_row) {
			/* values
				0 => partial post code
				1 => City
				2 => region
			 */
			$count = $this->RegionMapping->find('count', array(
				'conditions' => array(
					'RegionMapping.postal_prefix' => $csv_row[0],
					'RegionMapping.country_code' => 'CA'
				)
			));
			if ($count > 0) {
				continue;
			}
			
			$this->RegionMapping->create();
			$this->RegionMapping->save(array('RegionMapping' => array(
				'postal_prefix' => $csv_row[0],
				'city' => $csv_row[1],
				'country_code' => 'CA',
				'region' => $csv_row[2],
			)));
			echo '[CA]'.$csv_row[0]. ' saved.'. "\n";
		}
		
		$csv_rows = Utils::csv_to_array($file_gb); 
		foreach ($csv_rows as $csv_row) {
			/* values
				0 => partial post code
				1 => City
				2 => region
			 */
			$count = $this->RegionMapping->find('count', array(
				'conditions' => array(
					'RegionMapping.postal_prefix' => $csv_row[0],
					'RegionMapping.country_code' => 'GB'
				)
			));
			if ($count > 0) {
				continue;
			}
			
			$this->RegionMapping->create();
			$this->RegionMapping->save(array('RegionMapping' => array(
				'postal_prefix' => $csv_row[0],
				'city' => $csv_row[1],
				'country_code' => 'GB',
				'region' => $csv_row[2],
			)));
			echo '[GB]'.$csv_row[0]. ' saved.'. "\n";
		}
	}
}