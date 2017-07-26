<?php

class MbdMappings {
	static function gender($val) {
		$code = false;		
		switch ($val) {
			case 'M':
				$code = 1;
			break;
			case 'F':
				$code = 2;
			break;
		}
		
		return $code;
	}
	
	static function hhi($val) {		
		if (is_null($val)) {
			return 99;
		}
		
		$code = false;
		switch ($val) {
			case '0':
				$code = 1;
			break;
			case 1:
				$code = 2;
			break;
			/*case 2:
				$code = 3;
			break;
			case 3:
				$code = 4;
			break;*/
			case 4:
				$code = 5;
			break;
			case 5:
				$code = 6;
			break;
			/*case 6:
				$code = 7;
			break;*/
		}
		
		return $code;
	}
	
	static function race($val) {
		$code = false;
		switch ($val) {
			case '0':
				$code = 1;
			break;
			case 1:
				$code = 2;
			break;
			case 2:
				$code = 3;
			break;
			case 5:
				$code = 5;
			break;
			case 4:
				$code = 4;
			break;
			case ($val == ''):
				$code = 6;
			break;
		}
		
		return $code;
	}
	
	static function childrenunder18($val) {
		$code = false;
		switch ($val) {
			case '0':
				$code = '0';
			break;
			case 1:
				$code = 1;
			break;
		}
		
		return $code;
	}
	
	static function maritalstatus($val) {
		if (is_null($val)) {
			return 99;
		}
		
		$code = false;
		switch ($val) {
			case 1:
				$code = 1;
			break;
			case 4:
				$code = 2;
			break;
			case 5:
			case 6:
				$code = 3;
			break;
		}
		
		return $code;
	}
	
	static function country($val) {
		$code = false;
		switch ($val) {
			case 'US':
				$code = 181;
			break;
			case 'CA':
				$code = 36;
			break;
			case 'GB':
				$code = 180;
			break;
		}
		
		return $code;
	}
	
	static function employmentstatus($val) {
		if (is_null($val)) {
			return 8;
		}
		
		$code = false;
		switch ($val) {
			case '0':
				$code = 1;
			break;
			case 1:
				$code = 2;
			break;
			case 2:
				$code = 3;
			break;
			case 4:
				$code = 4;
			break;
			case 3:
				$code = 5;
			break;
			case 8:
				$code = 6;
			break;
			case 5:
				$code = 7;
			break;
		}
		
		return $code;
	}
	
	static function occupationtype($val) {
		if (is_null($val)) {
			return 12;
		}
		
		$code = false;
		switch ($val) {
			/*case 30:
			case 195:
				$code = 1;
			break; 
			case 83:
				$code = 2;
			break;
			case 149:
				$code = 3;
			break;
			case 9:
			case 10:
			case 11:
			case 12:
			case 13:
			case 23:
			case 31:
			case 32:
			case 44:
			case 49:
			case 65:
			case 68:
			case 79:
			case 95:
			case 105:
			case 114:
			case 178:
			case 182:
			case 182:
			case 184:
			case 185:
			case 186:
			case 187:
			case 188:
			case 189:
			case 192:
			case 194:
			case 196:
			case 166:
			case 173:
			case 162:
			case 169:
			case 172:
			case 165:
				$code = 4;
			break;
			case 24:
			case 155:
			case 29:
			case 66:
			case 75:
			case 80:
			case 81:
			case 82:
			case 88:
			case 89:
			case 90:
			case 102:
			case 103:
			case 109:
			case 140:
				$code = 7;
			break;
			case 124:
			case 193:
			case 197:
				$code = 8;
			break;
			case 33:
			case 72:
			case 73:
			case 133:
			case 142:
			case 145:
			case 151:
				$code = 10;
			break;*/
			case 177:
				$code = 11;
			break; 
		}
		
		return $code;
	}

	static function industrytype($val) {
		$code = false;
		switch ($val) {
			case 30:
				$code = 1;
			break;
			case 6:
				$code = 2;
			break;
			case 10:
			case 11:
			case 12:
			case 51:
				$code = 3;
			break;
			case 14:
				$code = 4;
			break;
			case 18:
				$code = 5;
			break;
			case 45:
				$code = 6;
			break;
			case 20:
				$code = 7;
			break;
			case 21:
				$code = 8;
			break;
			case 24:
				$code = 9;
			break;
			case 25:
				$code = 10;
			break;
			case 26:
				$code = 11;
			break;
			case 4:
			case 27:
				$code = 12;
			break;
			case 23:
				$code = 13;
			break;
			case 47:
			case 36:
				$code = 14;
			break;
			case 28:
				$code = 15;
			break;
			case 38:
				$code = 17;
			break;
			case 49:
				$code = 18;
			break;
			case 40:
			case 41:
				$code = 19;
			break;
			case 42:
				$code = 20;
			break;
		}
		
		return $code;
	}
	
	static function companysize($val) {
		$code = false;
		switch ($val) {
			case '0':
				$code = 1;
			break;
			/*case 9:
				$code = 2;
			break;
			case 8:
				$code = 3;
			break;
			case 7:
				$code = 4;
			break;*/
			case 6:
			case 5:
				$code = 5;
			break;
			case 4:
			case 3:
			case 2:
				$code = 6;
			break;
			case 1:
				$code = 7;
			break;
			/*case 11:
				$code = 8;
			break;*/
		}
		
		return $code;
	}
	
	static function education($val) {
		if (is_null($val)) {
			return 99;
		}
		
		$code = false;
		switch ($val) {
			case '0':
				$code = 1;
			break;
			case 1:
				$code = 2;
			break;
			case 2:
				$code = 3;
			break;
			case 3:
				$code = 4;
			break;
			case 4:
				$code = 5;
			break;
			case 5:
			case 6:
				$code = 6;
			break;
		}
		
		return $code;
	}
	
	static function agerange($val) {
		if (is_null($val)) {
			return false;
		}
		
		$code = false;
		$date = new DateTime($val);
		$now = new DateTime();
		$interval = $now->diff($date);
		$age = $interval->y;
		switch ($age) {
			case ($age < 13):
				$code = 1;
			break;
			case ($age >= 13 && $age <= 17):
				$code = 2;
			break;
			case ($age >= 18 && $age <= 24):
				$code = 3;
			break;
			case ($age >= 25 && $age <= 34):
				$code = 4;
			break;
			case ($age >= 35 && $age <= 44):
				$code = 5;
			break;
			case ($age >= 45 && $age <= 54):
				$code = 6;
			break;
			case ($age >= 55 && $age <= 64):
				$code = 7;
			break;
			case ($age >= 65):
				$code = 8;
			break;
		}
		
		return $code;
	}
}