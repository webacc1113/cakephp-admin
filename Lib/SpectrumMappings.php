<?php

class SpectrumMappings {
	static function is_mapped($precode) {
		$mappings = array(
			'211' => 'Gender',
			'212' => 'Age',
			'213' => 'HouseHoldIncome',
			'214' => 'Race',
			'215' => 'Employments',
			'216' => 'Educations',
			'217' => 'Relationships',
			'218' => 'Children',
			'219' => 'Devices',
			'223' => 'regions',
			'224' => 'divisions',
			'225' => 'state',
			'226' => 'msa',
			'227' => 'csa',
			'228' => 'county', 
			'229' => 'zipcodes',
			'231' => 'dma'
		);
		if (isset($mappings[$precode])) {
			return true;
		}
		return false;
	}
	
	static function gender($precode) {
		$return = false;
		switch ($precode) {
			case 111:
				$return = 'M';
				break;
			case 112:
				$return = 'F';
				break;
		}
		return $return;
	}
	
	static function country($country_code) {
		$return = false;
		if ($country_code == 'CA') {
			$return = 'CA';
		}
		elseif ($country_code == 'GB') {
			$return = 'GB';
		}
		elseif ($country_code == 'US') {
			$return = 'US';
		}
		return $return;
	}
	
	static function language($language_code) {
		$languages = Utils::language_iso1_to_iso2();
		if (isset($languages[$language_code])) {
			return $languages[$language_code];
		}
		return false;
	}
	
	static function ethnicity($precode) {
		$return = false;
		switch ($precode) {
			case 111:
				$return = '0';
				break;
			case 112:
				$return = 4;
				break;
			case 113:
				$return = 1;
				break;
			case 114:
				$return = 2;
				break;
			case 116: // todo: American Indian
			case 115: // Middle Eastern
			case 117:
				$return = 5;
				break;
		}
		return $return;
	}
	
	static function employment($precode) {
		$return = false;
		switch ($precode) {
			case 111:
				$return = 4;
				break;
			case 112:
				$return = 5;
				break;
			case 113:
				$return = 1;
				break;
			case 114:
				$return = '0';
				break;
			case 115:
				$return = 8;
				break;
		}
		return $return;
	}
	
	static function education($precode) {
		$return = false;
		switch ($precode) {
			case 111:
			case 112:
				$return = 1;
				break;
			case 113:
			case 114:
				$return = 2;
				break;
			case 115:
				$return = 5;
				break;
			case 116:
				$return = 6;
				break;
		}
		return $return;
	}
	
	static function relationships($precode) {
		$return = false;
		switch ($precode) {
			case 111:
				$return = 1;
				break;
			case 112:
				$return = 3;
				break;
			case 113:
				$return = 2;
				break;
			case 114:
				$return = 4;
				break;
			case 115:
				$return = 5;
				break;
			case 116:
				$return = 6;
				break;
		}
		return $return;
	}
	
	static function children($precode) {
		$return = false;
		switch ($precode) {
			case 111:
				$return = '0';
				break;
			case 112:
				$return = 1;
				break;
		}
		return $return;
	}
	
	static function hhi($from, $to) {
		$return = array();
		if ($from < 25000) {
			$return[] = 0;
		}
		if ($from < 35000 && $to >= 25000) {
			$return[] = 1;
		}
		if ($from < 50000 && $to >= 35000) {
			$return[] = 2;
		}
		if ($from < 60000 && $to >= 50000) {
			$return[] = 3;
		}
		if ($from < 75000 && $to >= 60000) {
			$return[] = 4;
		}
		if ($from < 100000 && $to >= 75000) {
			$return[] = 5;
		}
		if ($to >= 100000) {
			$return[] = 6;
		}
		return $return;
	}
}