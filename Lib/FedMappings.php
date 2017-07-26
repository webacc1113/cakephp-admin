<?php

class FedMappings {
	static function gender($precode) {
		$age = false;		
		switch ($precode) {
			case 1:
				$age = 'M';
			break;
			case 2:
				$age = 'F';
			break;
		}
		return $age;
	}
	
	static function hhi($precode) {		
		$hhi = false;
		switch ($precode) {
			case 04:
			case 05:
				$hhi = 0;
			break;
			case 06:
			case 07:
				$hhi = 1;
				break;
			case 08:
			case 9:
			case 10:
				$hhi = 2;
				break;
			case 11:
			case 12:
				$hhi = 3;
				break;
			case 13:
			case 14:
			case 15:
				$hhi = 4;
				break;
			case 16:
			case 17:
			case 18:
			case 19:
			case 20:
				$hhi = 5;
				break;
			case 21:
			case 22:
			case 23:
			case 24:
			case 25:
			case 26:
				$hhi = 6;
			break;
		}
		return $hhi;
	}
	
	static function hhi_v2($precode) {		
		$hhi = false;
		switch ($precode) {
			case 2:
			case 3:
				$hhi = 0;
			break;
			case 4:
			case 5:
				$hhi = 1;
				break;
			case 6:
			case 7:
			case 8:
				$hhi = 2;
				break;
			case 9:
			case 10:
				$hhi = 3;
				break;
			case 11:
			case 12:
			case 13:
				$hhi = 4;
				break;
			case 14:
			case 15:
			case 16:
			case 17:
			case 18:
				$hhi = 5;
				break;
			case 19:
			case 20:
			case 21:
			case 22:
			case 23:
			case 24:
				$hhi = 6;
			break;
		}
		return $hhi;
	}
	
	static function country($country_language_id) {
		if ($country_language_id == 6) {
			return 'CA';
		}
		if ($country_language_id == 8) {
			return 'GB';
		}
		if ($country_language_id == 9) {
			return 'US';
		}
	}
	
	static function language($country_language_id) {
		switch ($country_language_id) {
			case 1:
			case 2:
			case 3:
				return 'zh';
				break;
			case 4:
			case 28:
				return 'nl';
				break;
			case 5:
			case 6:
			case 7:
			case 8:
			case 9:
			case 36:
			case 43:
			case 49:
			case 50:
			case 57:
			case 58:
			case 59:
			case 61:
			case 73:
			case 74:
				return 'en';
				break;
			case 10:
			case 25:
			case 26:
			case 34:
				return 'fr';
				break;
			case 11:
			case 12:
			case 38:
				return 'de';
				break;
			case 13:
			case 35:
				return 'it';
				break;
			case 14:
				return 'ja';
				break;
			case 15:
				return 'pl';
				break;
			case 16:
			case 17:
				return 'pt';
				break;
			case 18:
				return 'ru';
				break;
			case 19:
			case 20:
			case 21:
			case 22:
			case 27:
			case 41:
			case 47:
			case 64:
			case 65:
			case 66:
			case 67:
			case 68:
			case 69:
			case 80:
				return 'es';
				break;
			case 23:
				return 'sv';
				break;
			case 24:
				return 'ko';
				break;
			case 29:
			case 77:
			case 82:
			case 83:
				return 'ar';
				break;
			case 30:
				return 'no';
				break;
			case 31:
				return 'da';
				break;
			case 32:
				return 'fi';
				break;
			case 37:
				return 'tr';
				break;
			case 39:
				return 'cs';
				break;
			case 40:
				return 'el';
				break;
			case 42:
				return 'is';
				break;
			case 45:
				return 'ro';
				break;
			case 46:
				return 'bg';
				break;
			case 51:
				return 'lb';
				break;
			case 52:
				return 'id';
				break;
			case 53:
			case 60:
				return 'ms';
				break;
			case 54:
				return 'th';
				break;
			case 55:
				return 'tl';
				break;
			case 56:
				return 'uk';
				break;
			case 62:
				return 'hu';
				break;
			case 70:
				return 'et';
				break;
			case 71:
				return 'lt';
				break;
			case 72:
				return 'he';
				break;
			case 75:
				return 'zu';
				break;
			case 76:
				return 'hi';
				break;
			case 78:
				return 'sk';
				break;
			case 79:
				return 'sl';
				break;
			case 81:
				return 'vi';
				break;
			default:
				return false;
			break;
		}
	}
	
	static function ethnicity($precode) {
		$return = false;
		switch ($precode) {
			case 1:
				$return = 0;
				break;
			case 2:
				$return = 1;
				break;
			case 4:
			case 5:
			case 6:
			case 7:
			case 8:
			case 9:
			case 10:
				$return = 2;
				break;
			case 11:
			case 12:
			case 13:
			case 14:
				$return = 3;
				break;
			case 15:
				$return = 5;
				break;
		}
		
		return $return;
	}
	
	static function hispanic($precode) {
		$return = false;
		switch ($precode) {
			case 2:
				$return = 3;
				break;
			case 3:
				$return = 1;
				break;
			case 4:
				$return = 2;
				break;
			case 5:
				$return = 4;
				break;
			case 6:
				$return = 5;
				break;
			case 7:
				$return = 6;
				break;
			case 8:
				$return = 7;
				break;
			case 9:
				$return = 8;
				break;
			case 10:
				$return = 9;
				break;
			case 11:
				$return = 10;
				break;
			case 12:
				$return = 11;
				break;
			case 13:
				$return = 12;
				break;
			case 14:
				$return = 13;
				break;
		}
		
		return $return;
	}
	
	static function children($precode) {
		$return = false;
		switch ($precode) {
			case 2:
			case 3:
				$return = 1;
				break;
			case 4:
				$return = '0';
				break;
		}
		return $return;
	}
	
	static function employment($precode) {
		$return = false;
		switch ($precode) {
			case 1:
				$return = 0;
				break;
			case 2:
				$return = 1;
				break;
			case 3:
			case 4:
				$return = 2;
				break;
			case 5:
			case 6:
				$return = 7;
				break;
			case 7:
				$return = 4;
				break;
			case 8:
				$return = 3;
				break;
			case 9:
				$return = 8;
				break;
			case 10:
				$return = 5;
				break;
			case 12:
				$return = 6;
				break;
		}
		return $return;
	}
	
	// Question_id: 15297 is matched.
	static function job($precode) {
		$return = false;
		switch ($precode) {
			case 1:
				$return = 9;
				break;
			case 2:
				$return = 196;
				break;
			case 3:
				$return = 23;
				break;
			case 4:
				$return = 189;
				break;
			case 5:
				$return = 180;
				break;
			case 6:
				$return = 181;
				break;
			case 7:
				$return = 142;
				break;
			case 11:
				$return = 177;
				break;
			
		}
		
		return $return;
	}

	//question_id = 643 & question_id = 5729 both has the same answers set and this function is used for both questions
	static function industry($precode) {
		$return = false;
		switch ($precode) {
			case 1:
				$return = 48;
				break;
			case 2:
				$return = 30;
				break;
			case 3:
				$return = 1;
				break;
			case 4:
				$return = 3;
				break;
			case 5:
				$return = 5;
				break;
			case 6:
				$return = 2;
				break;
			case 7:
				$return = 6;
				break;
			case 8:
				$return = 7;
				break;
			case 11:
				$return = 9;
				break;
			case 12:
				$return = 49;
				break;
			case 13:
			case 14:
				$return = 10;
				break;
			case 15:
				$return = 12;
				break;
			case 16:
				$return = 13;
				break;
			case 17:
				$return = 14;
				break;
			case 18:
				$return = 19;
				break;
			case 19:
				$return = 43;
				break;
			case 20:
				$return = 18;
				break;
			case 21:
				$return = 50;
				break;
			case 22:
				$return = 45;
				break;
			case 23:
				$return = 45;
				break;
			case 25:
				$return = 22;
				break;
			case 26:
				$return = 23;
				break;
			case 27:
				$return = 24;
				break;
			case 28:
				$return = 25;
				break;
			case 30: //IT
				$return = 10; //Computer
				break;
			case 31:
				$return = 26;
				break;
			case 33:
				$return = 4;
				break;
			case 34:
				$return = 29;
				break;
			case 36:
				$return = 30;
				break;
			case 37:
				$return = 47;
				break;
			case 38:
				$return = 32;
				break;
			case 41:
				$return = 31;
				break;
			case 42:
				$return = 36;
				break;
			case 44:
				$return = 28;
				break;
			case 45:
				$return = 38;
				break;
			case 46:
				$return = 35;
				break;
			case 48:
				$return = 49;
				break;
			case 49:
				$return = 40;
				break;
			case 50:
				$return = 42;
				break;
		}
		return $return;
	}
	
	/* question_id 644 & 22467 both have the same answers, both mapped by the function */
	static function organization_size($precode, $question_id) {
		$return = false;
		if ($question_id == 644) {
			switch ($precode) {
				case 1:
					$return = array('0', 10, 9);
					break;
				case 2:
					$return = 8;
					break;
				case 3:
					$return = 7;
					break;
				case 4:
					$return = 5;
					break;
				case 5:
					$return = 4;
					break;
				case 6:
					$return = 3;
					break;
				case 7:
					$return = 2;
					break;
				case 8:
					$return = 11;
					break;
			}
		}
		elseif ($question_id == 22467) {
			switch ($precode) {
				case 1:
					$return = '0';
					break;
				case 2:
					$return = 10;
					break;
				case 3:
					$return = 8;
					break;
				case 4:
					$return = 7;
					break;
				case 5:
					$return = 6;
					break;
				case 6:
					$return = 4;
					break;
				case 7:
					$return = 3;
					break;
				case 8:
					$return = 2;
					break;
				case 9:
					$return = 11;
					break;
			}
		}

		return $return;
	}
	
	static function organization_revenue($precode) {
		$return = false;
		switch ($precode) {
			case 1:
				$return = '0';
				break;
			case 2:
			case 3:
				$return = 12;
				break;
			case 4:
				$return = 11;
				break;
			case 5:
				$return = 10;
				break;
			case 6:
				$return = 9;
				break;
			case 7:
			case 8:
				$return = 8;
				break;
			case 9:
				$return = 7;
				break;
			case 10:
			case 11:
				$return = 6;
				break;
			case 12:
				$return = 5;
				break;
			case 13:
				$return = 4;
				break;
			case 14:
				$return = 1;
				break;
		}

		return $return;
	}
	
	static function department($precode) {
		$return = false;
		switch ($precode) {
			case 1:
				$return = 2;
				break;
			case 2:
				$return = 17;
				break;
			case 3:
				$return = 18;
				break;
			case 4:
				$return = 1;
				break;
			case 5:
				$return = 7;
				break;
			case 6:
				$return = 9;
				break;
			case 7:
				$return = 10;
				break;
			case 8:
				$return = 12;
				break;
			case 9:
				$return = 18;
				break;
			case 10:
				$return = 3;
				break;
			case 11:
			case 12:
			case 13:
				$return = 8;
				break;
			case 14:
				$return = 18;
				break;
		}

		return $return;
	}
	
	static function education($precode) {
		$return = false;
		switch ($precode) {
			case 1:
			case 2:
				$return = '0';
				break;
			case 3:
			case 4:
			case 5:
				$return = 1;
				break;
			case 6:
			case 7:
			case 8:
				$return = 2;
				break;
			case 9:
				$return = 4;
				break;
			case 10:
				$return = 5;
				break;
			case 11:
				$return = 6;
				break;
		}

		return $return;
	}
	
	static function education_v2($precode) {
		$return = false;
		switch ($precode) {
			case 1:
				$return = '0';
				break;
			case 2:
				$return = 1;
				break;
			case 4:
				$return = 2;
				break;
			case 5:
				$return = 3;
				break;
			case 6:
				$return = 4;
				break;
			case 7:
				$return = 5;
				break;
			case 8:
				$return = 6;
				break;
		}

		return $return;
	}

}
