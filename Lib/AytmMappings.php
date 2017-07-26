<?php

class AytmMappings {

	static function gender($precode) {
		$return = false;		
		switch ($precode) {
			case 'm':
				$return = 'M';
			break;
			case 'f':
				$return = 'F';
			break;
		}
		return $return;
	}
	
	static function income($val, $inverse = false) {
		$return = null;
		$values = array(
			1 => 1, // "$0 - $25,000"
			2 => 4, // "$25,000 - $50,000"
			3 => 6, // "$50,000 - $75,000"
			4 => 7, // "$75,000 - $100,000"
			5 => 9, // "$100,000 - $200,000"
			6 => 9, // "$200,000 - $500,000"
			7 => 9, // "$500,000+"
			// => 10, // "Prefer not to state"
		);
		if ($inverse) {
			$values = array_flip($values);
		}
		if (isset($values[$val])) {
			$return = $values[$val];
		}
		return $return;
	}
	
	static function career($val, $inverse = false) {
		$return = null;
		$values = array(
			1 => 42, // "Other"
			2 => 6, // "Accounting/Finance/Banking"
			3 => 30, // "Advertising/Graphic design"
			4 => 20, // "Arts and entertainment"
			5 => '', // "Clerical"
			6 => 24, // "Healthcare"
			7 => 25, // "Hospitality"
			8 => '', // "IT"
			9 => 4, // "Legal"
			10 => '', // "Management"
			11 => 32, // "Military"
			12 => '', // "Public safety"
			13 => 28, // "Real estate"
			14 => 38, // "Retail"
			15 => '', // "Small business owner"
			16 => '', // "Student"
		);
		if ($inverse) {
			$values = array_flip($values);
		}
		if (isset($values[$val])) {
			$return = $values[$val];
		}
		return $return;
	}
	
	static function ethnicity($val, $inverse = false) {
		$return = null;
		$values = array(
			'1' => '0', // White American
			'2' => '1', // African-American  
			'3' => '', // Native American 
			'4' => '', // Asian-American 
			'5' => '4', // Hispanic/Latino-American  
			'6' => '', // Multi-racial 
			'7' => '7', // Other
			'8' => '', // Indian-American
		);
		if ($inverse) {
			$values = array_flip($values);
		}
		if (isset($values[$val])) {
			$return = $values[$val];
		}
		return $return;
	}
	
	static function education($val, $inverse = false) {
		$return = null;
		$values = array(
			1 => '', // "Professional degree"
			2 => '', // "No college"
			3 => 2, // "Some college"
			4 => 3, // "2yr degree"
			5 => 4, // "4yr degree"
			6 => '' // "Grad school degree"
		);
		if ($inverse) {
			$values = array_flip($values);
		}
		if (isset($values[$val])) {
			$return = $values[$val];
		}
		return $return;
	}
	
	static function employment($val, $inverse = false) {
		$return = null;
		$values = array(
			1 => 4, // "Unemployed"
			2 => 1, // "Part Time"
			3 => 0, // "Full time"
			4 => 8, // "Retired"
			5 => 5, // "Student"
		);
		if ($inverse) {
			$values = array_flip($values);
		}
		if (isset($values[$val])) {
			$return = $values[$val];
		}
		return $return;
	}
	
	static function relationshipstatus($val, $inverse = false) {
		$return = null;
		$values = array(
			1 => 1, // "Single"
			2 => 4, // "Married"
			5 => 6, // "Widowed"
			6 => '', // "Living with a significant other "
			7 => 3, // "Engaged"
			8 => 5, // "Divorced "
			9 => '', // "It's complicated "
		);
		if ($inverse) {
			$values = array_flip($values);
		}
		if (isset($values[$val])) {
			$return = $values[$val];
		}
		return $return;
	}
	
	static function childern($val, $inverse = false) {
		$return = null;
		$values = array(
			1 => 0, // "Have no children"
			2 => 1, // "Have one child"
			3 => 1, // "Have two children"
			4 => 1, // "Have three children"
			5 => 1, // "Have four or more children"
			6 => 1, // "5+ children "
		);
		if ($inverse) {
			$values = array_flip($values);
		}
		if (isset($values[$val])) {
			$return = $values[$val];
		}
		return $return;
	}
}
