<?php
class CintMappings {
	
	static function employment($val, $country = 'US') {
		$return = false;
		switch ($val) {
			case '0':
				$return = 'os_fte'; //Full Time Employee
			break;
			case 1:
				$return = 'os_pte'; //Part-Time Employee
			break;
			case 2:
				$return = 'os_sem'; //Self Employed
			break;
			case 3:
				$return = 'os_hom'; //Home Maker
			break;
			case 4:
				$return = 'os_uem'; //Unemployed
			break;
			case 5:
				$return = 'os_stu'; //Student
			break;
			case 7:
				$return = 'os_mis'; //Military Service
			break;
			case 8:
				$return = 'os_ret'; //Retired
			break;
			
		}
		
		return $return;
	}
	
	static function employment_profile($val, $country = 'US', $inverse = false) {
		$return = false;
		$answers = array();
		switch ($val) {
			case 'US':
				$answers = array(
					'0' => 4790534,
					1 => 4790535,
					2 => 4790536,
					3 => 4790541,
					4 => 4790540,
					5 => 4790533,
					6 => 4790543,
					7 => 4790537,
					8 => 4790539,
				);
				break;
			case 'GB':
				$answers = array(
					'0' => 8270017,
					1 => 8270018,
					2 => 8270019,
					3 => 8270024,
					4 => 8270023,
					5 => 8270016,
					6 => 8270026,
					7 => 8270020,
					8 => 8270022,
				);
				break;
			case 'CA':
				
			break;
		}

		if ($inverse) {
			$answers = array_flip($answers);
		}

		if (isset($answers[$val])) {
			$return = $answers[$val];
		}

		return $return;
	}
	
	static function education($val, $country = 'US') {		
		$return = false;
		switch ($val) {
			case '0':
			case 1:
				$return = 'el_com'; //Compulsory School
			break;
			case 2:
				$return = 'el_ups'; //Upper Secondary School
			break;
			case 3:
			case 4:
			case 5:
			case 6:
				$return = 'el_uni'; //University
			break;
		}
		
		return $return;
	}
	
	// education profile US question
	static function education_profile_277416($val) {
		$return = false;
		$answers = array(
			4790414 => '0',
			4790416 => 1,
			4790417 => 2,
			4790418 => 5,
			4790419 => 6,
		);

		if (isset($answers[$val])) {
			$return = $answers[$val];
		}

		return $return;
	}
	
	// education profile UK question
	static function education_profile_369619($val) {
		$return = false;
		$answers = array(
			8269949 => '0',
			8269950 => '0',
			8269951 => 1,
			8269952 => 1,
			8269953 => 6,
			8269954 => 5,
		);

		if (isset($answers[$val])) {
			$return = $answers[$val];
		}

		return $return;
	}
	
	static function education_profile($val, $country = 'US', $inverse = false) {
		$return = false;
		$answers = array();
		switch ($country) {
			case 'US':
				$answers = array(
					'0' => 4709581,
					1 => 4709581,
					2 => 4709581,
					3 => 4709578,
					4 => 4709578,
					5 => 4709577,
					6 => 4709576,
				);
				break;
			case 'GB':
				$answers = array(
					2 => 8269961,
					4 => 8269958,
					3 => 8269958,
					5 => 8269957,
					5 => 8269959,
					6 => 8269956,
				);
				break;
			case 'CA':

				break;
		}

		if ($inverse) {
			$answers = array_flip($answers);
		}

		if (isset($answers[$val])) {
			$return = $answers[$val];
		}

		return $return;
	}
	
	static function ethnicity($val, $country = 'US', $inverse = false) {
		$return = false;
		$answers = array();
		switch ($country) {
			case 'US':
				$answers = array(
					'' => 4709385,
					'0' => 4709383,
					1 => 4709380,
					2 => 4709379,
					3 => 4709382,
					4 => 4709381,
					5 => 4709384,
				);
				break;
			case 'GB':
				$answers = array(
					'' => 8268364,
					'0' => 8268358,
					1 => 8268361,
					2 => 8268360,
					2 => 8268362,
					5 => 8268363,
					5 => 8268359,
				);
				break;
			case 'CA':

				break;
		}
		
		if ($inverse) {
			$answers = array_flip($answers);
		}
		
		if (isset($answers[$val])) {
			$return = $answers[$val];
		}
		
		return $return;
	}
	
	// @inverse - optional & if set to true, the function take cint variable as parameter, and return the internal MV mappings value.
	static function hhi($val, $country = 'US', $inverse = false) {
		$return = false;
		$answers = array();
		switch ($country) {
			case 'US':
				$answers = array(
					'0' => 4792368,
					1 => 4792490,
					2 => 4792490,
					3 => 4792599,
					4 => 4792599,
					5 => 4792722,
					6 => 4792807,
				);
				break;
			case 'GB':
				$answers = array(
					'0' => 8268736,
					1 => 8268737,
					2 => 8268737,
					3 => 8268737,
					4 => 8268738,
					5 => 8268739,
					6 => 8268740,
					6 => 8268741,
					6 => 8268742,
				);
				break;
			case 'CA':

				break;
		}

		if ($inverse) {
			$answers = array_flip($answers);
		}

		if (isset($answers[$val])) {
			$return = $answers[$val];
		}

		return $return;
	}
	
	static function industry($val, $country = 'US', $inverse = false) {
		$return = false;
		$answers = array();
		switch ($country) {
			case 'US':
				$answers = array(
					1 => 4709634,
					2 => 4709660,
					3 => 4709658,
					4 => 4709644,
					5 => 4709659,
					6 => 4709636,
					7 => 4709661,
					8 => 4709638,
					9 => 4709663,
					10 => 4709666,
					11 => 4709665,
					12 => 4709665,
					13 => 4709639,
					14 => 4709667,
					15 => 4709665,
					18 => 4709640,
					19 => 4709668,
					20 => 4709647,
					21 => 4709636,
					23 => 4709642,
					24 => 4709643,
					25 => 4709654,
					26 => 4709674,
					27 => 4709644,
					28 => 4709680,
					29 => 4709645,
					30 => 4709657,
					31 => 4709648,
					32 => 4709635,
					35 => 4709651,
					36 => 4709678,
					38 => 4709649,
					40 => 4709653,
					41 => 4709654,
					42 => 4709655,
					43 => 4709669,
					45 => 4709641,
					47 => 4709647,
					48 => 4709656,
					49 => 4709652,
					50 => 4709670,
					51 => 4709665,
				);
				break;
			case 'GB':
				$answers = array(
					1 => 8270027,
					32 => 8270028,
					21 => 8270029,
					10 => 8270030,
					49 => 8270031,
					13 => 8270032,
					18 => 8270033,
					45 => 8270034,
					23 => 8270035,
					24 => 8270036,
					4 => 8270037,
					27 => 8270037,
					29 => 8270038,
					30 => 8270039,
					47 => 8270040,
					31 => 8270041,
					38 => 8270042,
					42 => 8270043,
					42 => 8270044,
					49 => 8270045,
					40 => 8270046,
					41 => 8270047,
					42 => 8270048,
					48 => 8270049,
					30 => 8270050,
					3 => 8270051,
					5 => 8270052,
					2 => 8270053,
					7 => 8270054,
					42 => 8270055,
					9 => 8270056,
					10 => 8270057,
					12 => 8270058,
					10 => 8270059,
					14 => 8270060,
					19 => 8270061,
					43 => 8270062,
					50 => 8270063,
					42 => 8270064,
					42 => 8270065,
					42 => 8270066,
					26 => 8270067,
					42 => 8270068,
					42 => 8270069,
					42 => 8270070,
					36 => 8270071,
					42 => 8270072,
					28 => 8270073,
					42 => 8270074,
					42 => 8270075,
				);
				break;
			case 'CA':

				break;
		}

		if ($inverse) {
			$answers = array_flip($answers);
		}
		
		if (isset($answers[$val])) {
			$return = $answers[$val];
		}

		return $return;

	}
	
	static function organization_size($val, $country = 'US', $inverse = false) {
		$return = false;
		$answers = array();
		switch ($country) {
			case 'US':
				$answers = array(
					'0' => 4709685,
					1 => 4709691,
					2 => 4709691,
					3 => 4709690,
					4 => 4709689,
					5 => 4709688,
					6 => 4709688,
					7 => 4709687,
					8 => 4709686,
					9 => 4709685,
					10 => 4709685,
					11 => 4709692,
				);
				break;
			case 'GB':
				$answers = array(
					'0' => 8270078,
					1 => 8270084,
					2 => 8270084,
					3 => 8270083,
					4 => 8270082,
					5 => 8270081,
					6 => 8270081,
					7 => 8270080,
					8 => 8270079,
					9 => 8270078,
					10 => 8270078,
					11 => 8270085
				);
				break;
			case 'CA':

				break;
		}
		
		if ($inverse) {
			$answers = array_flip($answers);
		}

		if (isset($answers[$val])) {
			$return = $answers[$val];
		}

		return $return;
	}
	
	static function department($val, $country = 'US', $inverse = false) {
		$return = false;
		$answers = array();
		switch ($country) {
			case 'US':
				$answers = array(
					1 => 4709697,
					2 => 4709694,
					3 => 4709708,
					5 => 4709702,
					7 => 4709696,
					8 => 4709710,
					9 => 4709703,
					10 => 4709699,
					12 => 4709711,
					16 => 4709708,
					17 => 4709706,
					18 => 4709702,
				);
				break;
			case 'GB':
				$answers = array(
					1 => 8270090,
					2 => 8270087,
					3 => 8270101,
					7 => 8270089,
					8 => 8270093,
					8 => 8270102,
					8 => 8270103,
					9 => 8270096,
					10 => 8270092,
					12 => 8270104,
					17 => 8270099,
					18 => 8270108,
					18 => 8270107,
					18 => 8270106,
					18 => 8270105,
					18 => 8270095,
				);
				break;
			case 'CA':

				break;
		}
		
		if ($inverse) {
			$answers = array_flip($answers);
		}

		if (isset($answers[$val])) {
			$return = $answers[$val];
		}

		return $return;
	}
	
	static function job($val, $country = 'US', $inverse = false) {
		$return = false;
		$answers = array();
		switch ($country) {
			case 'US':
				$answers = array(
					9 => 4709743,
					10 => 4709745,
					23 => 4709748,
					32 => 4709747,
					33 => 4709760,
					44 => 4709760,
					48 => 4709760,
					49 => 4709760,
					72 => 4709760,
					73 => 4709760,
					133 => 4709760,
					142 => 4709760,
					145 => 4709760,
					65 => 4709753,
					114 => 4709756,
					115 => 4709749,
					124 => 4709753,
					177 => 4709756,
					179 => 4709765,
					185 => 4709763,
					187 => 4709756,
					188 => 4709747,
					190 => 4709742,
					191 => 4709743,
					193 => 4709762,
					194 => 4709746,
					196 => 4709746,
					166 => 4709746,
					173 => 4709746,
					162 => 4709746,
					169 => 4709746,
					172 => 4709746,
					165 => 4709746,
					197 => 4709746,
				);
				break;
			case 'GB':
				$answers = array(
					190 => 8270135,
					9 => 8270136,
					188 => 8270137,
					10 => 8270138,
					196 => 8270139,
					166 => 8270139,
					173 => 8270139,
					162 => 8270139,
					169 => 8270139,
					172 => 8270139,
					165 => 8270139,
					197 => 8270139,
					184 => 8270140,
					23 => 8270141,
					105 => 8270144,
					65 => 8270146,
					195 => 8270148,
					177 => 8270149,
					142 => 8270150,
					177 => 8270151,
					177 => 8270152,
					149 => 8270154,
					193 => 8270155,
					124 => 8270155,
					185 => 8270156,
					185 => 8270153,
					177 => 8270157,
					105 => 8270158,
				);
				break;
			case 'CA':

				break;
		}

		if ($inverse) {
			$answers = array_flip($answers);
		}

		if (isset($answers[$val])) {
			$return = $answers[$val];
		}

		return $return;

	}
	
	// Smart phone personsal use?
	static function smartphone($val, $country = 'US', $inverse = false) {
		$return = false;
		$answers = array();
		switch ($country) {
			case 'US':
				$answers = array(
					'0' => 4711251,
					1 => 4711250,
				);
				break;
			case 'GB':
				$answers = array(
					'0' => 8271767,
					1 => 8271766,
				);
				break;
			case 'CA':

				break;
		}
		
		if ($inverse) {
			$answers = array_flip($answers);
		}

		if (isset($answers[$val])) {
			$return = $answers[$val];
		}

		return $return;
	}
	
	// Smart phone business use US.
	static function smartphone_275024($val) {
		$return = false;
		$answers = array(
			4710137 => 1,
			4710138 => '0'
		);

		if (isset($answers[$val])) {
			$return = $answers[$val];
		}

		return $return;
	}
	
	// Smart phone business use UK.
	static function smartphone_369636($val) {
		$return = false;
		$answers = array(
			8270565 => 1,
			8270566 => '0'
		);

		if (isset($answers[$val])) {
			$return = $answers[$val];
		}

		return $return;
	}
	
	static function relationship($val, $country = 'US', $inverse = false) {
		$return = false;
		$answers = array();
		switch ($country) {
			case 'US':
				$answers = array(
					'0' => 4709399,
					1 => 4709393,
					4 => 4709395,
					5 => 4709397,
					6 => 4709398,
				);
				break;
			case 'GB':
				$answers = array(
					'0' => 8268378,
					1 => 8268372,
					2 => 8268373,
					4 => 8268374,
					5 => 8268376,
					6 => 8268377,
				);
				break;
			case 'CA':

				break;
		}

		if ($inverse) {
			$answers = array_flip($answers);
		}

		if (isset($answers[$val])) {
			$return = $answers[$val];
		}

		return $return;
	}
	
	static function housing_own($val, $country = 'US', $inverse = false) {
		$return = false;
		$answers = array();
		switch ($country) {
			case 'US':
				$answers = array(
					'0' => 4709401,
					'0' => 4709402,
					1 => 4709400,
					1 => 4709403,
				);
				break;
			case 'GB':
				$answers = array(
					'0' => 8268379,
					'0' => 8268381,
					1 => 8268380,
					1 => 8268382,
				);
				break;
			case 'CA':

				break;
		}

		if ($inverse) {
			$answers = array_flip($answers);
		}

		if (isset($answers[$val])) {
			$return = $answers[$val];
		}

		return $return;
	}
	
	static function region($val) {
		$regions = array(
			'Albany' => 'Albany, GA',
			'Beaumont - Port Author' => 'Beaumont - Port Arthur',
			'Champaign - Springfield - Decatur' => 'Champaign & Springfield - Decatur',
			'Charleston' => 'Charleston, SC',
			'Charleston - Huntington' => 'Charleston-Huntington',
			'Columbia' => 'Columbia, SC',
			'Ft Myers' => 'Fort Myers - Naples',
			'Ft Smith - Fay - Springfield' => 'Fort Smith - Fayetteville - Springdale - Rogers',
			'Ft Wayne' => 'Fort Wayne',
			'Greenville - Spartenburg' => 'Greenville - Spartansburg - Asheville - Anderson',
			'Lincoln - Hastings' => 'Lincoln & Hastings - Kearney',
			'Minneapolis - St Paul' => 'Minneapolis - Saint Paul',
			'Portland' => 'Portland, OR',
			'Rochester' => 'Rochester, NY',
			'St Joseph' => 'Saint Joseph',
			'St Louis' => 'Saint Louis',
			'Springfield' => 'Springfield, MO',
			'Tri - Cities' => 'Tri-Cities, TN-VA',
			'Utica - Rome' => 'Utica',
			'Wichita Falls' => 'Wichita Falls & Lawton',
			'Youngstown - Warren' => 'Youngstown',
		);
		
		if (isset($regions[$val])) {
			return $regions[$val];
		}
		else {
			return $val;
		}
	}

}
