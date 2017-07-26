<?php
App::import('Vendor', 'site_profile');

class RfgMappings {

	static function is_mapped($datapoint_name) {
		$mappings = array(
			'Age',
			'DMA (US)',
			'State (US)',
			'List of Zips',
			'Gender',
			'Household Income',
			'Education (US)',
			'Ethnicity (US)',
			'Has Children Under 18',
			'Employment Status',
			'Employment Industry',
			'Job Title',
			'RFG2_Employment Department',
			'Marital Status',
			'Employment Employees',
			'Employment Revenue',
			'Accommodation',
			'RFG2_Mobile Smartphone',
			'MS_is_tablet',
			'RFG2_Travel Flight Destination',
			'RFG2_Hispanic',
		);
		if (in_array($datapoint_name, $mappings)) {
			return true;
		}
		
		return false;
	}

	static function to_mv_query($target) {
		$query_params = array();
		if (empty($target['values'])) { // free answer
			return;
		}
		
		if ($target['name'] == 'Gender') {
			// send gender to query only if it set to whether "M" or "F", not both
			if (count($target['values']) == 1) {
				$query_params['gender'] = self::gender($target['values'][0]['choice']);
				return $query_params;
			}
		}

		switch ($target['name']) {
			case 'Household Income':
				$mappingFunc = 'hhi';
				$key = 'hhi';
				break;
			case 'Education (US)':
				$mappingFunc = 'education';
				$key = 'education';
				break;
			case 'Ethnicity (US)':
				$mappingFunc = 'ethnicity';
				$key = 'ethnicity';
				break;
			case 'Has Children Under 18':
				$mappingFunc = 'children';
				$key = 'children';
				break;
			case 'Employment Status':
				$mappingFunc = 'employment';
				$key = 'employment';
				break;
			case 'Employment Industry':
				$mappingFunc = 'industry';
				$key = 'industry';
				break;
			case 'Job Title':
				$mappingFunc = 'job';
				$key = 'job';
				break;
			case 'RFG2_Employment Department':
				$mappingFunc = 'department';
				$key = 'department';
				break;
			case 'Marital Status':
				$mappingFunc = 'marital';
				$key = 'relationship';
				break;
			case 'Employment Employees':
				$mappingFunc = 'orgSize';
				$key = 'organization_size';
				break;
			case 'Employment Revenue':
				$mappingFunc = 'orgRevenue';
				$key = 'organization_revenue';
				break;
			case 'Accommodation':
				$mappingFunc = 'home';
				$key = 'housing_own';
				break;
			case 'RFG2_Mobile Smartphone':
				$mappingFunc = 'smartphone';
				$key = 'smartphone';
				break;
			case 'MS_is_tablet':
				$mappingFunc = 'tablet';
				$key = 'tablet';
				break;
			case 'RFG2_Travel Flight Destination':
				$mappingFunc = 'travel';
				$key = 'airlines';
				break;
			case 'RFG2_Hispanic':
				$mappingFunc = 'hispanic';
				$key = 'hispanic';
				break;
			default:
				break;
		}

		if (!isset($mappingFunc)) {
			return false;
		}

		$values = array();
		foreach ($target['values'] as $value) {
			$mappedValue = self::$mappingFunc($value['choice']);
			if ($mappedValue !== '') {
				$values[] = $mappedValue;
			}
		}
		if (!empty($values)) {
			$query_params[$key] = array_values(array_unique($values));
		}
		
		return $query_params;
	}

	static function hispanic($key) {
		$mapping = array(
			3 => 1,
			4 => 2,
			2 => 3,
			5 => 4,
			6 => 5,
			7 => 6,
			8 => 7,
			9 => 8,
			10 => 9,
			11 => 10,
			12 => 11,
			13 => 12,
			14 => 13,
		);
		if (isset($mapping[$key])) {
			return $mapping[$key];
		}
		return '';
	}
	
	static function gender($key) {
		$mapping = array(
			1 => 'M', 
			2 => 'F'
		);
		if (isset($mapping[$key])) {
			return $mapping[$key];
		}
		return '';
	}

	static function hhi($key) {
		$mapping = array(
			2 => 0, 
			3 => 2, 
			4 => 4, 
			5 => 5,
			6 => 6, 7 => 6, 8 => 6, 9 => 6, 10 => 6, 11 => 6
		);
		if (isset($mapping[$key])) {
			return $mapping[$key];
		}
		return '';
	}

	static function education($key) {
		$mapping = array(
			2 => 0, 
			3 => 1, 4 => 1, 
			5 => 2, 6 => 2,
			7 => 4, 
			8 => 6
		);
		if (isset($mapping[$key])) {
			return $mapping[$key];
		}
		return '';
	}

	static function ethnicity($key) {
		$mapping = array(
			1 => 1, 
			2 => 2, 
			3 => 0,
			4 => 3, 
			5 => 4, 
			6 => 5, 7 => 5
		);
		if (isset($mapping[$key])) {
			return $mapping[$key];
		}
		return '';
	}

	static function children($key) {
		return self::_yesNo($key);
	}

	static function employment($key) {
		$mapping = array(
			1 => 5, 
			2 => 1, 
			3 => 0, 
			4 => 4,
			5 => 8, 
			6 => 2, 
			7 => 3
		);
		if (isset($mapping[$key])) {
			return $mapping[$key];
		}
		return '';
	}

	static function industry($key) {
		$mapping = array(
			 1 => 48,  3 =>  0,  4 =>  1,  5 =>  3,
			 6 => 20,  8 =>  5,  9 =>  2, 10 =>  6, 
			12 =>  7, 16 =>  9, 18 => 10, 11 =>  9,
			21 => 13, 22 => 14, 23 => 19, 24 => 43,
			26 => 18, 28 => 45, 32 => 46, 33 => 22,
			34 => 23, 35 => 24, 36 => 25, 40 => 26, 
			41 => 51, 42 =>  4, 43 => 29, 45 => 39, 
			46 => 47, 47 => 32, 50 => 31, 51 => 36, 
			54 => 38, 58 => 40, 59 => 50, 63 => 42,
			 2 => 30, 17 => 30,
			19 => 12, 20 => 12
		);
		if (isset($mapping[$key])) {
			return $mapping[$key];
		}
		return '';
	}

	static function job($key) {
		$mapping = array(
			 1 => 190,  2 =>   9,  3 =>  23,  4 => 188, 
			25 => 162,  6 => 151,  7 => 193,  8 => 105, 
			 9 => 115, 10 =>  24, 11 => 133, 12 => 166, 
			13 => 128, 17 => 149, 18 => 103, 24 => 173,
			 5 => 177, 21 => 177, 22 => 177, 23 => 177, 16 => 177, 20 => 177, 14 => 177, 15 => 177, 19 => 177
		);
		if (isset($mapping[$key])) {
			return $mapping[$key];
		}
		return '';
	}

	static function department($key) {
		$mapping = array(
			 1 =>  2,  2 => 17,  4 =>  1,  5 => 7,  6 => 9, 
			 7 => 10,  8 => 12, 10 =>  3, 11 => 5, 12 => 8, 
			13 => 18, 14 => 18, 15 => 18, 3 => 18, 9 => 18 
		);
		if (isset($mapping[$key])) {
			return $mapping[$key];
		}
		return '';
	}

	static function marital($key) {
		$mapping = array(
			1 => 1,
			2 => 3,
			3 => 4,
			5 => 5, 6 => 5,
			4 => 2, 8 => 2,
			7 => 6,
			9 => 0
		);
		if (isset($mapping[$key])) {
			return $mapping[$key];
		}
		return '';
	}

	static function orgSize($key) {
		$mapping = array(
			1 => 9, 2 => 8, 3 => 7, 4 =>  5,
			5 => 4, 6 => 3, 7 => 2, 8 => 11
		);
		if (isset($mapping[$key])) {
			return $mapping[$key];
		}
		return '';
	}

	static function orgRevenue($key) {
		$mapping = array(
			 1 => 0,  2 => 12,  3 => 12, 4 => 11,
			 6 => 9,  9 =>  7, 10 =>  6, 5 => 10,
			11 => 6, 12 =>  5, 13 =>  4, 14 => 1,
			7 => 8, 8 => 8
		);
		if (isset($mapping[$key])) {
			return $mapping[$key];
		}
		return '';
	}

	static function home($key) {
		$mapping = array(
			1 => 1, 3 => 1, 5 => 1,
			2 => 0, 4 => 0
		);
		if (isset($mapping[$key])) {
			return $mapping[$key];
		}
		return '';
	}

	static function smartphone($key) {
		return self::_yesNo($key);
	}

	static function tablet($key) {
		return self::_yesNo($key);
	}

	static function travel($key) {
		$mapping = array(
			1 => 0, 
			2 => 1, 
			3 => 2, 
			4 => 3
		);
		if (isset($mapping[$key])) {
			return $mapping[$key];
		}
		return '';
	}

	static private function _yesNo($key) {
		$mapping = array(
			1 => 1,
			2 => 0
		);
		if (isset($mapping[$key])) {
			return $mapping[$key];
		}
		return '';
	}
	
	static function dma($answer, $dmas) {
		$return = false;
		switch ($answer) {
			case 'BIRMINGHAM (ANN AND TUSC)':
				$return = $dmas['Birmingham (Anniston and Tuscaloosa)'];
				break;
			case 'CEDAR RAPIDS-WTRLO-IWC&DUB':
				$return = $dmas['Cedar Rapids - Waterloo & Dubuque'];
				break;
			case 'CHAMPAIGN&SPRNGFLD-DECATUR':
				$return = $dmas['Champaign & Springfield - Decatur'];
				break;
			case 'CLEVELAND-AKRON (CANTON)':
				$return = $dmas['Cleveland'];
				break;
			case 'COLUMBUS, GA (OPELIKA, AL)':
				$return = $dmas['Columbus, GA'];
				break;
			case 'COLUMBUS-TUPELO-W PNT-HSTN':
				$return = $dmas['Columbus - Tupelo - West Point'];
				break;
			case 'DALLAS-FT. WORTH':
				$return = $dmas['Dallas - Fort Worth'];
				break;
			case 'DAVENPORT-R.ISLAND-MOLINE':
				$return = $dmas['Davenport - Rock Island - Moline'];
				break;
			case 'EL PASO (LAS CRUCES)':
				$return = $dmas['El Paso'];
				break;
			case 'ELMIRA (CORNING)':
				$return = $dmas['Elmira'];
				break;
			case 'FT. MYERS-NAPLES':
				$return = $dmas['Fort Myers - Naples'];
				break;
			case 'FT. SMITH-FAY-SPRNGDL-RGRS':
				$return = $dmas['Fort Smith - Fayetteville - Springdale - Rogers'];
				break;
			case 'FT. WAYNE':
				$return = $dmas['Fort Wayne'];
				break;
			case 'GRAND RAPIDS-KALMZOO-B.CRK':
				$return = $dmas['Grand Rapids - Kalamazoo - Battle Creek'];
				break;
			case 'GREENSBORO-H.POINT-W.SALEM':
				$return = $dmas['Greensboro - High Point - Winston-Salem'];
				break;
			case 'GREENVILLE-N.BERN-WASHNGTN':
				$return = $dmas['Greenville - New Bern - Washington'];
				break;
			case 'GREENVLL-SPART-ASHEVLL-AND':
				$return = $dmas['Greenville - Spartansburg - Asheville - Anderson'];
				break;
			case 'HARLINGEN-WSLCO-BRNSVL-MCA':
				$return = $dmas['Harlingen - Weslaco - Brownsville - McAllen'];
				break;
			case 'HARRISBURG-LNCSTR-LEB-YORK':
				$return = $dmas['Harrisburg - Lancaster - Lebanon - York'];
				break;
			case 'HUNTSVILLE-DECATUR (FLOR)':
				$return = $dmas['Huntsville - Decatur (Florence)'];
				break;
			case 'IDAHO FALS-POCATLLO(JCKSN)':
				$return = $dmas['Idaho Falls - Pocatello'];
				break;
			case 'JOHNSTOWN-ALTOONA-ST COLGE':
				$return = $dmas['Johnstown - Altoona'];
				break;
			case 'LINCOLN & HASTINGS-KRNY':
				$return = $dmas['Lincoln & Hastings - Kearney'];
				break;
			case 'MIAMI-FT. LAUDERDALE':
				$return = $dmas['Miami - Fort Lauderdale'];
				break;
			case 'MINNEAPOLIS-ST. PAUL':
				$return = $dmas['Minneapolis - Saint Paul'];
				break;
			case 'MINOT-BSMRCK-DCKNSN(WLSTN)':
				$return = $dmas['Minot - Bismarck - Dickinson'];
				break;
			case 'MOBILE-PENSACOLA (FT WALT)':
				$return = $dmas['Mobile  - Pensacola (Fort Walton Beach)'];
				break;
			case 'MONTGOMERY-SELMA':
				$return = $dmas['Montgomery (Selma)'];
				break;
			case 'MYRTLE BEACH-FLORENCE':
				$return = $dmas['Florence - Myrtle Beach'];
				break;
			case 'NORFOLK-PORTSMTH-NEWPT NWS':
				$return = $dmas['Norfolk - Portsmouth - Newport News'];
				break;
			case 'ORLANDO-DAYTONA BCH-MELBRN':
				$return = $dmas['Orlando - Daytona Beach - Melbourne'];
				break;
			case 'PADUCAH-CAPE GIRARD-HARSBG':
				$return = $dmas['Paducah - Cape Girardeau - Harrisburg - Mt Vernon'];
				break;
			case 'PHOENIX (PRESCOTT)':
				$return = $dmas['Phoenix'];
				break;
			case 'SAN FRANCISCO-OAK-SAN JOSE':
				$return = $dmas['San Francisco - Oakland - San Jose'];
				break;
			case 'SANTABARBRA-SANMAR-SANLUOB':
				$return = $dmas['Santa Barbara - Santa Maria - San Luis Obispo'];
				break;
			case 'ST. JOSEPH':
				$return = $dmas['Saint Joseph'];
				break;
			case 'ST. LOUIS':
				$return = $dmas['Saint Louis'];
				break;
			case 'TAMPA-ST. PETE (SARASOTA)':
				$return = $dmas['Tampa - Saint Petersburg (Sarasota)'];
				break;
			case 'TYLER-LONGVIEW(LFKN&NCGD)':
				$return = $dmas['Tyler - Longview (Lufkin & Nacogdoches)'];
				break;
			case 'WASHINGTON, DC (HAGRSTWN)':
				$return = $dmas['Washington DC (Hagerstown)'];
				break;
			case 'WEST PALM BEACH-FT. PIERCE':
				$return = $dmas['West Palm Beach - Fort Pierce'];
				break;
			case 'WICHITA-HUTCHINSON PLUS':
				$return = $dmas['Wichita - Hutchinson'];
				break;
			case 'WILKES BARRE-SCRANTON-HZTN':
				$return = $dmas['Wilkes Barre - Scranton'];
				break;
			case 'YAKIMA-PASCO-RCHLND-KNNWCK':
				$return = $dmas['Yakima - Pasco - Richland - Kennewick'];
				break;
			default:
				break;
		}
		
		return $return;
	}

}
