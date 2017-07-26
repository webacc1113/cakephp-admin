<?php

class SsiMappings {

	static function gender($val, $inverse = false) {
		$return = null;
		$gender = array(
			'M' => '1',
			'F' => '2'
		);
		if ($inverse) {
			$gender = array_flip($gender);
		}
		if (isset($gender[$val])) {
			$return = $gender[$val];
		}
		return $return;
	}

	static function race($val) {
		$return = null;
		$raceUS = array(
			'0' => '1', // White/Caucasion 
			'1' => '2', // Black/African American 
			'2' => '3', // Asian 
			'3' => '5', // Pacific Islander 
			'4' => '6', // American Indian or Alaska Native (Hispanic?)
			'5' => '6', // Other 
			'' => '7' // Prefer not to say 
		);
		if (isset($raceUS[$val])) {
			$return = $raceUS[$val];
		}
		return $return;
	}
	
	static function ethnicity($val) {
		if ($val == '') {
			return '3'; 
		}
		if ($val == 4) {
			return '1'; 
		}
		return '2'; 
	}

	static function education($val, $inverse = false) {
		$return = null;
		$values = array(
			0 => 1, // "Completed some high school"
			1 => 2, // "High school graduate"
			2 => 3, // "Completed some college"
			3 => 4, // "College degree"
			4 => 5, // "Completed some postgraduate"
			5 => 6, // "Master's degree"
			6 => 7 // "Doctorate, law or professional degree"
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
			2 => 1, // "Self-employed (part-time)"
			// => 2, // "Self-employed (full-time, 30 hours a week or more)"
			1 => 3, // "A paid employee (part-time)"
			0 => 4, // "A paid employee (full-time, 30 hours a week or more)"
			// => 5, // "Temporary or Seasonal Employee"
			4 => 6, // "Unemployed and looking"
			// => 7, // "Unemployed and not looking"
			8 => 8, // "Retired"
			3 => 9, // "Homemaker"
			5 => 10, // "Student"
			// => 11, // "Disabled"
			6 => 999, // "None of the above"
		);
		if ($inverse) {
			$values = array_flip($values);
		}
		if (isset($values[$val])) {
			$return = $values[$val];
		}
		return $return;
	}

	static function occupation($val, $inverse = false) {
		$return = null;
		$values = array(
			// => 1, // "Executive/Upper Management"
			45 => 2, // "IT/MIS Professional"
			46 => 3, // "Doctor/Surgeon/Healthcare professional"
			18 => 4, // "Educator/Educator administrator"
			// => 5, // "Homemaker"
			// => 6, // "Student"
			// => 7, // "Small Business Owner"
			42 => 8, // "None of the above"
			// => 9, // "Manager/Supervisor (non-executive)"
			13 => 10, // "Construction/Tradesman/Contractor"
			// => 11, // "Business Professional (non-manager/non-executive)"
			27 => 12, // "Lawyer/Attorney"
		);
		if ($inverse) {
			$values = array_flip($values);
		}
		if (isset($values[$val])) {
			$return = $values[$val];
		}
		return $return;
	}

	static function income($val, $inverse = false) {
		$return = null;
		$values = array(
			// => 1, // "Less than $20,000"
			0 => 2, // "$20,000 - $29,000"
			1 => 3, // "$30,000 - $39,000"
			2 => 4, // "$40,000 - $49,000"
			3 => 5, // "$50,000 - $59,000"
			4 => 6, // "$60,000 - $74,999"
			5 => 7, // "$75,000 - $99,999"
			6 => 8, // "$100,000 - $149,000"
			// => 9, // "$150,000+"
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

}
