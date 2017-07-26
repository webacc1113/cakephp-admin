<?php

class TolunaMappings {

	static function birthdate($val) {
		$return = null;

		if (isset($val)) {
			$return = date('n/j/Y', strtotime($val));
		}

		return $return;
	}

	static function gender($val) {
		$return = 0;
		$genders = array(
			'M' => 2000247,
			'F' => 2000246
		);

		if (isset($genders[$val])) {
			$return = $genders[$val];
		}

		return $return;
	}

	static function education($val, $country = 'US') {
		$return = 0;
		
		if ($country == 'US') {
			$values = array(
				0 => 2002269,	// Student => Middle School/Junior High School
				1 => 2002270,	// High School Diploma => High School
				2 => 2002271,	// Some College => Some College/University
				3 => 2002272,	// 2 Year Degree => Graduated 2-year College
				4 => 2002273,	// 4 Year Degree => Graduated 4-year College/University
				5 => 2002274,	// Master's degree => Graduate School
				6 => 2002275	// PhD => Postgraduate
			);
		}
		elseif ($country == 'CA') {
			$values = array(
				0 => 2002269,	// Student => Middle School/Junior High School
				1 => 2002270,	// High School Diploma => High School
				2 => 2002271,	// Some College => Some College/University
				3 => 2002272,	// 2 Year Degree => Graduated 2-year College
				4 => 2002273,	// 4 Year Degree => Graduated 4-year College/University
				5 => 2002274,	// Master's degree => Graduate School
				6 => 2002275	// PhD => Postgraduate
			);
		}
		elseif ($country == 'GB') {
			return null;
		}

		if (isset($values[$val])) {
			$return = $values[$val];
		}

		return $return;
	}

	static function race($val, $country = 'US') {
		$return = 0;
		
		if ($country == 'US') {
			$races = array(
				'0' => 2789290,		// White/Caucasian => White
				'1' => 2789290,		// Black/African American => Black or African-American
				'2' => 2789290,		// Asian => Asian
				'3' => 2789290,		// Pacific Islander => Native Hawaiian or Other Pacific Islander
				'4' => 2000248,		// Hispanic => Latin, Central and South American origins; Hispanic is mapped to race
				'5' => 2789290,		// Other => Other Ethnicity
				'' => 2789290		// Prefer not to say => Don't know/prefer not to answer
			);
		}
		else {
			return null;
		}

		if (isset($races[$val])) {
			$return = $races[$val];
		}

		return $return;
	}
	
	static function ethnicity($val, $country = 'US') {
		$return = 0;
		if ($country == 'US') {
			$ethnicities = array(
				'0' => 2000271,		// White/Caucasian => White
				'1' => 2000265,		// Black/African American => Black or African-American
				'2' => 2000264,		// Asian => Asian
				'3' => 2000267,		// Pacific Islander => Native Hawaiian or Other Pacific Islander
				'4' => 2000269,		// Hispanic => Latin, Central and South American origins; Hispanic is mapped to race
				'5' => 2000269,		// Other => Other Ethnicity
				'' => 2000272		// Prefer not to say => Don't know/prefer not to answer
			);
		}
		else {
			return null;
		}

		if (isset($ethnicities[$val])) {
			$return = $ethnicities[$val];
		}

		return $return;
	}

	static function employment($val, $country = 'US') {
		$return = 0;
		
		if ($country == 'US') {
			$employments = array(
				'0' => 0,				// Full Time Employee
				'1' => 0,				// Part Time Employee
				'2' => 0,				// Self Employed
				'3' => 2796315,			// Homemaker => Housewife/Homemaker
				'4' => 2796318,			// Unemployed => Unemployed
				'5' => 2796317,			// Student => Student
				'6' => 0,				// Prefer not to say
				'7' => 0,				// Military
				'8' => 2796316			// Retired => Retired
			);
		}
		elseif ($country == 'CA') {
			$employments = array(
				'0' => 0,				// Full Time Employee
				'1' => 0,				// Part Time Employee
				'2' => 0,				// Self Employed
				'3' => 2796315,			// Homemaker => Housewife/Homemaker
				'4' => 2796318,			// Unemployed => Unemployed
				'5' => 2796317,			// Student => Student
				'6' => 0,				// Prefer not to say
				'7' => 0,				// Military
				'8' => 2796316			// Retired => Retired
			);
		}
		elseif ($country == 'GB') {
			$employments = array(
				'0' => 0,				// Full Time Employee
				'1' => 0,				// Part Time Employee
				'2' => 0,				// Self Employed
				'3' => 2796315,			// Homemaker => Housewife/Homemaker
				'4' => 2796318,			// Unemployed => Unemployed
				'5' => 2796317,			// Student => Student
				'6' => 0,				// Prefer not to say
				'7' => 0,				// Military
				'8' => 2796316			// Retired => Retired
			);
		}

		if (isset($employments[$val])) {
			$return = $employments[$val];
		}

		return $return;
	}

	static function income($val, $country = 'US') {
		$return = 0;
		
		if ($country == 'US') {
			$incomes = array(
				'0' => 2002315,	// $15,000 to $24,999 => $20,000-$24,999
				'1' => 2002317,	// $25,000 to $34,999 => $30,000-$34,999
				'2' => 2002320,	// $35,000 to $49,999 => $45,000-$49,999
				'3' => 2002322,	// $50,000 to $59,999 => $55,000-$59,999
				'4' => 2002325,	// $60,000 to $74,999 => $70,000-$74,999
				'5' => 2002330,	// $75,000 to $99,999 => $95,000-$99,999
				'6' => 2002331	// $100,000+ => $100,000-$124,999
			);
		}
		elseif ($country == 'CA') {
			return null;
		}
		elseif ($country == 'GB') {
			return null;
		}

		if (isset($incomes[$val])) {
			$return = $incomes[$val];
		}

		return $return;
	}

	static function marital_status($val, $country = 'US') {
		$return = 0;
		$marital_statuses = array(
			'0' => 0,			// Prefer not to say
			'1' => 2002278,		// Single => Single (Never married)
			'2' => 2002279,		// In a relationship => Domestic Partnership/Common Law
			'3' => 0,			// Engaged
			'4' => 2002280,		// Married => Married
			'5' => 2002281,		// Divorced => Separated/Divorced/Widowed
			'6' => 2002281,		// Widowed => Separated/Divorced/Widowed
		);

		if (isset($marital_statuses[$val])) {
			$return = $marital_statuses[$val];
		}

		return $return;
	}

	static function language($val) {
		$return = 0;

		$languages = array(
			'en' => 2000240
		);

		if (isset($languages[$val])) {
			$return = $languages['en'];
		}

		return $return;
	}
}
