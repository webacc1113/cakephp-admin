<?php

class Utils {
	
	static public function stats_standard_deviation($a, $sample = false) {
		$n = count($a);
		if ($n === 0) {
			trigger_error("The array has zero elements", E_USER_WARNING);
			return false;
		}
		if ($sample && $n === 1) {
			trigger_error("The array has only 1 element", E_USER_WARNING);
			return false;
		}
		$mean = array_sum($a) / $n;
		$carry = 0.0;
		foreach ($a as $val) {
			$d = ((double) $val) - $mean;
			$carry += $d * $d;
		}
		if ($sample) {
			--$n;
		}
		return sqrt($carry / $n);
    }

	static public function ip_address() {
		$ip = Utils::get_user_ip();
		return $ip['ip_address'];
	}
	
	static function get_user_ip() {
		$types = unserialize(IP_ADDRESS_TYPES);
		
		if (isset($_SERVER['HTTP_X_DISTIL'])) {
			if (isset($_SERVER['HTTP_X_FORWARDED_FOR_OLD'])) {
				$ip = Utils::get_last_ip($_SERVER['HTTP_X_FORWARDED_FOR_OLD']);
				if ($ip) {
					return array('ip_address' => $ip, 'ip_address_type' => $types['HTTP_DISTIL_X_FORWARDED_FOR_OLD']); 
				}
			}
			elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				$ip = Utils::get_last_ip($_SERVER['HTTP_X_FORWARDED_FOR']);
				if ($ip) {
					return array('ip_address' => $ip, 'ip_address_type' => $types['HTTP_DISTIL_X_FORWARDED_FOR']); 
				}
			}
		}
		
		foreach ($types as $ip_address_type => $ip_address_value) {
			if (array_key_exists($ip_address_type, $_SERVER) === true) {
				$ip = Utils::get_last_ip($_SERVER[$ip_address_type]);
				if ($ip) {
					return array('ip_address' => $ip, 'ip_address_type' => $ip_address_value); 
				}
			}
		}
	}
	
	static function get_last_ip($ips) {
		$ips = explode(',', $ips);
		// x-forwarded for should contain the real IP address in first position http://www.openinfo.co.uk/apache/index.html
		// do not reverse this array
		foreach ($ips as $ip) {
			$ip = trim($ip);
			if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
				return $ip;
			}
		}
		return false;
	}
	
	static public function parse_project_id_from_hash($hash) {
		$hash = strtolower($hash);
		if (strpos($hash, 'm') !== false) {
			list($project_id, $nothing) = explode('m', $hash);
			return (int) $project_id;
		}
		return (int) substr($hash, 0, 5);
	}
	
	static function multiexplode($delimiters, $string) {
		$ready = str_replace($delimiters, $delimiters[0], $string);
		$launch = explode($delimiters[0], $ready);
		return  $launch;
	}
	
	static function truncate($string, $num, $snipFront = false) {
		if (strlen($string) > $num) {
			if ($snipFront) {
				$string = '...'.substr($string, strlen($string) - $num + 3, strlen($string));	
			}
			else {
				$string = substr($string, 0, $num - 3).'...';	
			}
		}
		return $string;
	}
	
	// filters both arrays for the same fields, then sees if any values changed
	static function array_values_changed($original, $comparison) {
		$fields_changed = array();
		foreach ($original as $key => $val) {
			if (isset($comparison[$key]) && $val != $comparison[$key]) {
				$fields_changed[] = $key;
			}
		}
		return !empty($fields_changed) ? $fields_changed: false;
	}
	
	static function business_days($date, $offset) {
		list($date, $hour) = explode(' ', $date);
		return date(DB_DATE, strtotime($date.' +'.$offset.' weekday')).' '.$hour;
	}
	
	static function csv_to_array($filename = '', $delimiter = ',') {
		if (!file_exists($filename) || !is_readable($filename)) {
			return false;
		}

    	$data = array();
		ini_set('auto_detect_line_endings', '1');
    	if (($handle = fopen($filename, 'r')) !== false) {
			while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
				foreach ($row as $key => $val) {
					if (empty($val)) {
						unset($row[$key]);
					}
				}
				if (!empty($row)) {
					$data[] = $row;
				}
	        }
	        fclose($handle);
	    }
	    return $data;
	}

	static function survey_status($project, $type = 'icon') {
		if ($project['Project']['active']) {
			if ($type == 'icon') {
				return 'icon-play';
			}
			elseif ($type == 'button') {
				return 'btn-success';
			}
		}
		else {
			if ($type == 'icon') {
				return 'icon-stop';
			}
			elseif ($type == 'button') {
				return 'btn-danger';
			}
		}
	}
	
	// birthdate: YYYY-mm-dd
	static function age($birthdate) {
		if (is_null($birthdate)) {
			return '0';
		}
		list($year, $month, $day) = explode('-', $birthdate); 
		$birthdate_ts = gmmktime(0, 0, 0, $month, $day, $year); 
		$today_ts = time(); 
		$age = floor(($today_ts - $birthdate_ts) / (86400 * 365.25)); 
		return $age;
	}
	
	static function emailize($text) {
	    $regex = '/(\S+@\S+\.\S+)/';
	    $replace = '<a href="mailto:$1">$1</a>';

	    return preg_replace($regex, $replace, $text);
	}
	
	static function prettify($json) {
   	 	$result      = '';
	    $pos         = 0;
	    $strLen      = strlen($json);
	    $indentStr   = '  ';
	    $newLine     = "\n";
	    $prevChar    = '';
	    $outOfQuotes = true;

	    for ($i=0; $i<=$strLen; $i++) {

	        // Grab the next character in the string.
	        $char = substr($json, $i, 1);

	        // Are we inside a quoted string?
	        if ($char == '"' && $prevChar != '\\') {
	            $outOfQuotes = !$outOfQuotes;

	        // If this character is the end of an element,
	        // output a new line and indent the next line.
	        } else if(($char == '}' || $char == ']') && $outOfQuotes) {
	            $result .= $newLine;
	            $pos --;
	            for ($j=0; $j<$pos; $j++) {
	                $result .= $indentStr;
	            }
	        }

	        // Add the character to the result string.
	        $result .= $char;

	        // If the last character was the beginning of an element,
	        // output a new line and indent the next line.
	        if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
	            $result .= $newLine;
	            if ($char == '{' || $char == '[') {
	                $pos ++;
	            }

	            for ($j = 0; $j < $pos; $j++) {
	                $result .= $indentStr;
	            }
	        }

	        $prevChar = $char;
	    }

	    return $result;
	}
	
	static function rand($length = 8, $possible = '123456789abcdefghjkmnpqrstuvwxyz') {
		$string = "";
		$i = 0;

		while ($i < $length) {
			$char = substr($possible, mt_rand(0, strlen($possible)-1), 1);
			if (!strstr($string, $char)) { 
				$string .= $char;
				$i++;
			}
		}
		return $string;
	}
	
	static function fileinfo($file) {
		$return = array(
			'exif' => @exif_read_data($file),
			'checksum' => sha1_file($file),
			'size' => filesize($file)
		);
		list($return['width'], $return['height']) = getimagesize($file);
		
		$finfo = new finfo(FILEINFO_MIME);
		$type = $finfo->file($file);
		$return['type'] = substr($type, 0, strpos($type, ';'));
		return $return;
	}
	
	static function filename($file, $retina = false) {
		if ($retina) {
			$info = pathinfo($file);	
			$filename = $info['filename'].'@2x.'.$info['extension'];
		}
		else {
			$filename = basename($file);
		}
		return $filename;	
	}

	function unparse_url($parsed) {
		if (!is_array($parsed)) {
			return false;
		}

		$uri = isset($parsed['scheme']) ? $parsed['scheme'].':'.((strtolower($parsed['scheme']) == 'mailto') ? '' : '//') : '';
		$uri .= isset($parsed['user']) ? $parsed['user'].(isset($parsed['pass']) ? ':'.$parsed['pass'] : '').'@' : '';
		$uri .= isset($parsed['host']) ? $parsed['host'] : '';
		$uri .= isset($parsed['port']) ? ':'.$parsed['port'] : '';

		if (isset($parsed['path'])) {
			$uri .= (substr($parsed['path'], 0, 1) == '/') 
				? $parsed['path'] 
				: ((!empty($uri) ? '/' : '' ) . $parsed['path']);
		}

		$uri .= isset($parsed['query']) ? '?'.$parsed['query'] : '';
		$uri .= isset($parsed['fragment']) ? '#'.$parsed['fragment'] : '';

		return $uri;
	}

	function validate_url($url, $return = false) {
		$regexp = "^(https?://)?(([0-9a-z_!~*'().&=+$%-]+:)?[0-9a-z_!~*'().&=+$%-]+@)?((([12]?[0-9]{1,2}\.){3}[12]?[0-9]{1,2})|(([0-9a-z_!~*'()-]+\.)*([0-9a-z][0-9a-z-]{0,61})?[0-9a-z]\.(com|net|org|edu|mil|gov|int|aero|coop|museum|name|info|biz|pro|[a-z]{2})))(:[1-6]?[0-9]{1,4})?((/?)|(/[0-9a-z_!~*'().;?:@&=+$,%#-]+)+/?)$";
	
		if (empty($url)) {
			return $return;
		}
		$urls = parse_url($url);
		$query = isset($urls['query']) ? $urls['query']: null;
		$fragment = isset($urls['fragment']) ? $urls['fragment']: null;
		unset($urls['query']);
		unset($urls['fragment']);
		$url = self::unparse_url($urls);
	
		if (eregi( $regexp, $url )){
			if (!empty($query)) {
				$url = $url.'?'.$query;
			}
			if (!empty($fragment)) {
				$url = $url.'#'.$url;
			}
		    // No http:// at the front? lets add it.
		    if (!eregi( "^https?://", $url )) $url = "http://" . $url;

		    // If it's a plain domain or IP there should be a / on the end
		    if (!eregi( "^https?://.+/", $url )) $url .= "";

		    // If it's a directory on the end we should add the proper slash
		    // We should first make sure it isn't a file, query, or fragment
		    if ((eregi( "/[0-9a-z~_-]+$", $url)) && (!eregi( "[\?;&=+\$,#]", $url))) $url .= "";
		    return $url;
		}
		return $return;
	}

	static function auto_link_text($text) {
	    $pattern  = '#\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))#';
	    return preg_replace_callback($pattern, 'Utils::auto_link_text_callback', $text);
	}

	static function auto_link_text_callback($matches) {
	    $max_url_length = 50;
	    $max_depth_if_over_length = 2;
	    $ellipsis = '&hellip;';

	    $url_full = $matches[0];
	    $url_short = '';

	    if (strlen($url_full) > $max_url_length) {
	        $parts = parse_url($url_full);
	        $url_short = $parts['scheme'] . '://' . preg_replace('/^www\./', '', $parts['host']) . '/';

	        $path_components = explode('/', trim($parts['path'], '/'));
	        foreach ($path_components as $dir) {
	            $url_string_components[] = $dir . '/';
	        }

	        if (!empty($parts['query'])) {
	            $url_string_components[] = '?' . $parts['query'];
	        }

	        if (!empty($parts['fragment'])) {
	            $url_string_components[] = '#' . $parts['fragment'];
	        }

	        for ($k = 0; $k < count($url_string_components); $k++) {
	            $curr_component = $url_string_components[$k];
	            if ($k >= $max_depth_if_over_length || strlen($url_short) + strlen($curr_component) > $max_url_length) {
	                if ($k == 0 && strlen($url_short) < $max_url_length) {
	                    // Always show a portion of first directory
	                    $url_short .= substr($curr_component, 0, $max_url_length - strlen($url_short));
	                }
	                $url_short .= $ellipsis;
	                break;
	            }
	            $url_short .= $curr_component;
	        }

	    } else {
	        $url_short = $url_full;
	    }

	    return "<a rel=\"nofollow\" href=\"$url_full\">$url_short</a>";
	}
	
	static function dateFormatToStrftime($dateFormat) {     
 	   $caracs = array( 
	        // Day - no strf eq : S 
	        'd' => '%d', 'D' => '%a', 'j' => '%e', 'l' => '%A', 'N' => '%u', 'w' => '%w', 'z' => '%j', 
	        // Week - no date eq : %U, %W 
	        'W' => '%V',  
	        // Month - no strf eq : n, t 
	        'F' => '%B', 'm' => '%m', 'M' => '%b', 
	        // Year - no strf eq : L; no date eq : %C, %g 
	        'o' => '%G', 'Y' => '%Y', 'y' => '%y', 
	        // Time - no strf eq : B, G, u; no date eq : %r, %R, %T, %X 
	        'a' => '%P', 'A' => '%p', 'g' => '%l', 'h' => '%I', 'H' => '%H', 'i' => '%M', 's' => '%S', 
	        // Timezone - no strf eq : e, I, P, Z 
	        'O' => '%z', 'T' => '%Z', 
	        // Full Date / Time - no strf eq : c, r; no date eq : %c, %D, %F, %x  
	        'U' => '%s' 
	    ); 
    
	    return strtr((string)$dateFormat, $caracs); 
	} 
	
	static function dollarize_points($value) {
		if (empty($value)) {
			return '';
		}
		return number_format(round($value / 100, 2), 2);
	}
	
	static function number_unformat($value) {
		return (float) str_replace(',', '', $value);
	}
	
	static function print_r_reverse($in) { 
 	   $lines = explode("\n", trim($in)); 
	    if (trim($lines[0]) != 'Array') { 
	        // bottomed out to something that isn't an array 
	        return $in; 
	    } else { 
	        // this is an array, lets parse it 
	        if (preg_match("/(\s{5,})\(/", $lines[1], $match)) { 
	            // this is a tested array/recursive call to this function 
	            // take a set of spaces off the beginning 
	            $spaces = $match[1]; 
	            $spaces_length = strlen($spaces); 
	            $lines_total = count($lines); 
	            for ($i = 0; $i < $lines_total; $i++) { 
	                if (substr($lines[$i], 0, $spaces_length) == $spaces) { 
	                    $lines[$i] = substr($lines[$i], $spaces_length); 
	                } 
	            } 
	        } 
	        array_shift($lines); // Array 
	        array_shift($lines); // ( 
	        array_pop($lines); // ) 
	        $in = implode("\n", $lines); 
	        // make sure we only match stuff with 4 preceding spaces (stuff for this array and not a nested one) 
	        preg_match_all("/^\s{4}\[(.+?)\] \=\> /m", $in, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER); 
	        $pos = array(); 
	        $previous_key = ''; 
	        $in_length = strlen($in); 
	        // store the following in $pos: 
	        // array with key = key of the parsed array's item 
	        // value = array(start position in $in, $end position in $in) 
	        foreach ($matches as $match) { 
	            $key = $match[1][0]; 
	            $start = $match[0][1] + strlen($match[0][0]); 
	            $pos[$key] = array($start, $in_length); 
	            if ($previous_key != '') $pos[$previous_key][1] = $match[0][1] - 1; 
	            $previous_key = $key; 
	        } 
	        $ret = array(); 
	        foreach ($pos as $key => $where) { 
	            // recursively see if the parsed out value is an array too 
	            $ret[$key] = Utils::print_r_reverse(substr($in, $where[0], $where[1] - $where[0])); 
	        } 
	        return $ret; 
	    } 
	} 
	
	static function http_languages($input) {
		$langs = array();

		if (isset($input)) {
			// break up string into pieces (languages and q factors)
			preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $input, $lang_parse);
			if (count($lang_parse[1])) {
				// create a list like "en" => 0.8
				$langs = array_combine($lang_parse[1], $lang_parse[4]);

				// set default to 1 for any without q factor
				foreach ($langs as $lang => $val) {
					if ($lang == 'q') { // sometimes non-standard http languages cause the quality factor to be returned as a lang
						unset($langs[$lang]); 
						continue;
					}
					if ($val === '') {
						$langs[$lang] = 1;
					}
				}

				// sort list based on value	
				arsort($langs, SORT_NUMERIC);
			}
		}
		return $langs;	
	}
	
	static function sd_square($x, $mean) { return pow($x - $mean,2); }

	// Function to calculate standard deviation (uses sd_square)    
	static function sd($array) {
	    // square root of sum of squares devided by N-1
	    return sqrt(array_sum(array_map(array('Utils', 'sd_square'), $array, array_fill(0,count($array), (array_sum($array) / count($array)) ) ) ) / (count($array)-1) );
	}
	
	static function language_codes() {
		$language_codes = array(
			'af' => 'Afrikaans',
			'ar' => 'Arabic',
			'be' => 'Belarusian',
			'bg' => 'Bulgarian',
			'bh' => 'Bihari',
			'bn' => 'Bengali',
			'br' => 'Breton',
			'bs' => 'Bosnian',
			'ca' => 'Catalan; Valencian',
			'co' => 'Corsican',
			'cs' => 'Czech',
			'cy' => 'Welsh',
			'da' => 'Danish',
			'de' => 'German',
			'el' => 'Greek, Modern',
			'eo' => 'Esperanto',
			'es' => 'Spanish; Castilian',
			'et' => 'Estonian',
			'eu' => 'Basque',
			'fa' => 'Persian',
			'fi' => 'Finnish',
			'fr' => 'French',
			'ga' => 'Irish',
			'gd' => 'Scottish Gaelic; Gaelic',
			'gl' => 'Galician',
			'he' => 'Hebrew (modern)',
			'hi' => 'Hindi',
			'hr' => 'Croatian',
			'hu' => 'Hungarian',
			'hy' => 'Armenian',
			'id' => 'Indonesian',
			'ig' => 'Igbo',
			'is' => 'Icelandic',
			'it' => 'Italian',
			'ja' => 'Japanese',
			'ka' => 'Georgian',
			'kg' => 'Kongo',
			'km' => 'Khmer',
			'ko' => 'Korean',
			'kr' => 'Kanuri',
			'ks' => 'Kashmiri',
			'ku' => 'Kurdish',
			'kv' => 'Komi',
			'la' => 'Latin',
			'lb' => 'Luxembourgish, Letzeburgesch',
			'lo' => 'Lao',
			'lt' => 'Lithuanian',
			'mg' => 'Malagasy',
			'mh' => 'Marshallese',
			'mi' => 'Maori',
			'mk' => 'Macedonian',
			'ml' => 'Malayalam',
			'mn' => 'Mongolian',
			'mr' => 'Marathi (Mara?hi)',
			'ms' => 'Malay',
			'mt' => 'Maltese',
			'my' => 'Burmese',
			'na' => 'Nauru',
			'ne' => 'Nepali',
			'ng' => 'Ndonga',
			'nl' => 'Dutch',
			'no' => 'Norwegian',
			'pa' => 'Panjabi, Punjabi',
			'pl' => 'Polish',
			'pt' => 'Portuguese',
			'ro' => 'Romanian, Moldavian, Moldovan',
			'ru' => 'Russian',
			'sk' => 'Slovak',
			'sl' => 'Slovene',
			'sm' => 'Samoan',
			'so' => 'Somali',
			'sq' => 'Albanian',
			'sr' => 'Serbian',
			'ss' => 'Swati',
			'st' => 'Southern Sotho',
			'su' => 'Sundanese',
			'sv' => 'Swedish',
			'sw' => 'Swahili',
			'ta' => 'Tamil',
			'th' => 'Thai',
			'tl' => 'Tagalog',
			'tr' => 'Turkish',
			'uk' => 'Ukrainian',
			'ur' => 'Urdu',
			'uz' => 'Uzbek',
			've' => 'Venda',
			'vi' => 'Vietnamese',
			'wa' => 'Walloon',
			'wo' => 'Wolof',
			'xh' => 'Xhosa',
			'yi' => 'Yiddish',
			'yo' => 'Yoruba',
			'za' => 'Zhuang, Chuang',
			'zh' => 'Chinese',
			'zu' => 'Zulu'
		);
		asort($language_codes);
		return array('en' => 'English') + $language_codes;
	}
	
	static function language_iso1_to_iso2() {
		$language_codes = array(
			'ab' => 'abk',
			'aa' => 'aar',
			'af' => 'afr',
			'ak' => 'aka',
			'sq' => 'sqi',
			'am' => 'amh',
			'ar' => 'ara',
			'an' => 'arg',
			'hy' => 'hye',
			'as' => 'asm',
			'av' => 'ava',
			'ae' => 'ave',
			'ay' => 'aym',
			'az' => 'aze',
			'bm' => 'bam',
			'ba' => 'bak',
			'eu' => 'eus',
			'be' => 'bel',
			'bn' => 'ben',
			'bh' => 'bih',
			'bi' => 'bis',
			'bs' => 'bos',
			'br' => 'bre',
			'bg' => 'bul',
			'my' => 'mya',
			'ca' => 'cat',
			'ch' => 'cha',
			'ce' => 'che',
			'ny' => 'nya',
			'zh' => 'zho',
			'cv' => 'chv',
			'kw' => 'cor',
			'co' => 'cos',
			'cr' => 'cre',
			'hr' => 'hrv',
			'cs' => 'ces',
			'da' => 'dan',
			'dv' => 'div',
			'nl' => 'nld',
			'dz' => 'dzo',
			'eo' => 'epo',
			'et' => 'est',
			'ee' => 'ewe',
			'fo' => 'fao',
			'fj' => 'fij',
			'fi' => 'fin',
			'fr' => 'fra',
			'ff' => 'ful',
			'gl' => 'glg',
			'ka' => 'kat',
			'de' => 'deu',
			'el' => 'ell',
			'gn' => 'grn',
			'gu' => 'guj',
			'ht' => 'hat',
			'ha' => 'hau',
			'he' => 'heb',
			'hz' => 'her',
			'hi' => 'hin',
			'ho' => 'hmo',
			'hu' => 'hun',
			'ia' => 'ina',
			'id' => 'ind',
			'ie' => 'ile',
			'ga' => 'gle',
			'ig' => 'ibo',
			'ik' => 'ipk',
			'io' => 'ido',
			'is' => 'isl',
			'it' => 'ita',
			'iu' => 'iku',
			'ja' => 'jpn',
			'jv' => 'jav',
			'kl' => 'kal',
			'kn' => 'kan',
			'kr' => 'kau',
			'ks' => 'kas',
			'kk' => 'kaz',
			'km' => 'khm',
			'ki' => 'kik',
			'rw' => 'kin',
			'ky' => 'kir',
			'kv' => 'kom',
			'kg' => 'kon',
			'ko' => 'kor',
			'ku' => 'kur',
			'kj' => 'kua',
			'la' => 'lat',
			'lb' => 'ltz',
			'lg' => 'lug',
			'li' => 'lim',
			'ln' => 'lin',
			'lo' => 'lao',
			'lt' => 'lit',
			'lu' => 'lub',
			'lv' => 'lav',
			'gv' => 'glv',
			'mk' => 'mkd',
			'mg' => 'mlg',
			'ms' => 'msa',
			'ml' => 'mal',
			'mt' => 'mlt',
			'mi' => 'mri',
			'mr' => 'mar',
			'mh' => 'mah',
			'mn' => 'mon',
			'na' => 'nau',
			'nv' => 'nav',
			'nd' => 'nde',
			'ne' => 'nep',
			'ng' => 'ndo',
			'nb' => 'nob',
			'nn' => 'nno',
			'no' => 'nor',
			'ii' => 'iii',
			'nr' => 'nbl',
			'oc' => 'oci',
			'oj' => 'oji',
			'cu' => 'chu',
			'om' => 'orm',
			'or' => 'ori',
			'os' => 'oss',
			'pa' => 'pan',
			'pi' => 'pli',
			'fa' => 'fas',
			'pl' => 'pol',
			'ps' => 'pus',
			'pt' => 'por',
			'qu' => 'que',
			'rm' => 'roh',
			'rn' => 'run',
			'ro' => 'ron',
			'ru' => 'rus',
			'sa' => 'san',
			'sc' => 'srd',
			'sd' => 'snd',
			'se' => 'sme',
			'sm' => 'smo',
			'sg' => 'sag',
			'sr' => 'srp',
			'gd' => 'gla',
			'sn' => 'sna',
			'si' => 'sin',
			'sk' => 'slk',
			'sl' => 'slv',
			'so' => 'som',
			'st' => 'sot',
			'es' => 'spa',
			'su' => 'sun',
			'sw' => 'swa',
			'ss' => 'ssw',
			'sv' => 'swe',
			'ta' => 'tam',
			'te' => 'tel',
			'tg' => 'tgk',
			'th' => 'tha',
			'ti' => 'tir',
			'bo' => 'bod',
			'tk' => 'tuk',
			'tl' => 'tgl',
			'tn' => 'tsn',
			'to' => 'ton',
			'tr' => 'tur',
			'ts' => 'tso',
			'tt' => 'tat',
			'tw' => 'twi',
			'ty' => 'tah',
			'ug' => 'uig',
			'uk' => 'ukr',
			'ur' => 'urd',
			'uz' => 'uzb',
			've' => 'ven',
			'vi' => 'vie',
			'vo' => 'vol',
			'wa' => 'wln',
			'cy' => 'cym',
			'wo' => 'wol',
			'fy' => 'fry',
			'xh' => 'xho',
			'yi' => 'yid',
			'yo' => 'yor',
			'za' => 'zha',
			'zu' => 'zul'
		);
		asort($language_codes);
		return array('eng' => 'en') + $language_codes;
	}
		
	//Change Timezone To UTC
	static function change_tz_to_utc($date_time, $format, $timezone = 'America/Los_Angeles') {
		$date = new DateTime($date_time, new DateTimeZone($timezone));
		$date->setTimezone(new DateTimeZone('UTC'));
		return $date->format($format);
	}

	//Change Timezone From UTC
	static function change_tz_from_utc($date_time, $format, $timezone = 'America/Los_Angeles') {
		$date = new DateTime($date_time, new DateTimeZone('UTC'));
		$date->setTimezone(new DateTimeZone($timezone));
		return $date->format($format);
	}
	
	static function format_uk_postcode($postcode) {
		$postcode = strtoupper(preg_replace("/[^A-Za-z0-9]/", '', $postcode));
		$postcode_length = strlen($postcode);
		$postcode_outward = substr($postcode, 0, $postcode_length - 3); 
		$postcode_inward = substr($postcode, -3, $postcode_length); 
		return $postcode_outward.' '.$postcode_inward; 
	}
	
	static function format_ca_postcode($postcode) {
		$postcode = strtoupper(preg_replace("/[^A-Za-z0-9]/", '', $postcode));
		$postcode = substr($postcode, 0, 3) . ' ' . substr($postcode, 3);
		return $postcode;
	}
	
	static function get_field_diffs($updated_project, $existing_project) {
		$project_fields = array_keys($updated_project);
		$project_logs = array();
		foreach ($project_fields as $field) {
			if (in_array($field, array('modified', 'created'))) {
				continue;
			}
			
			if (is_array($updated_project[$field]) || !isset($existing_project[$field]) || is_array($existing_project[$field])) {
				continue;
			}
			
			$updated = false;
			
			// this is to deal with https://basecamp.com/2045906/projects/1413421/todos/312357648
			// comparing floats can be problematic, workaround as per http://php.net/manual/en/language.types.float.php#language.types.float.comparison
			if (is_float($updated_project[$field])) {
				if (abs($updated_project[$field] - $existing_project[$field]) > 0.00001) {
					$updated = true;
				}
			}
			elseif ($updated_project[$field] != $existing_project[$field]) {
				$updated = true;
			}
			
			if ($updated) {
				$project_logs[$field] = $field . ' updated from "' . $existing_project[$field] . '" to "' . $updated_project[$field] . '"';
			}
		}
		
		return $project_logs;
	}
	
	static function agent_formattting($user_agent) {
		if (empty($user_agent)) {
			return '';
		}
		$user_agent = urlencode($user_agent);
		$request = file_get_contents('http://useragentapi.com/api/v2/json/' . USER_AGENT_API_KEY . '/' .$user_agent);
		$result = json_decode($request, true);
		if (empty($result['browser_name'])) {
			return '';
		}
		else {
			return 'Browser: ' . $result['browser_name'] . ', V ' . $result['browser_version'] . '<br>OS: ' . $result['platform_name'] . ', V ' . $result['platform_version'];
		}
	}
	
	public static function checkUkPostcode($toCheck) {
 		// Permitted letters depend upon their position in the postcode.
		$alpha1 = "[abcdefghijklmnoprstuwyz]";                          // Character 1
		$alpha2 = "[abcdefghklmnopqrstuvwxy]";                          // Character 2
		$alpha3 = "[abcdefghjkpmnrstuvwxy]";                            // Character 3
		$alpha4 = "[abehmnprvwxy]";                                     // Character 4
		$alpha5 = "[abdefghjlnpqrstuwxyz]";                             // Character 5
		$BFPOa5 = "[abdefghjlnpqrst]{1}";                               // BFPO character 5
		$BFPOa6 = "[abdefghjlnpqrstuwzyz]{1}";                          // BFPO character 6

		// Expression for BF1 type postcodes 
		$pcexp[0] =  '/^(bf1)([[:space:]]{0,})([0-9]{1}' . $BFPOa5 . $BFPOa6 .')$/';

		// Expression for postcodes: AN NAA, ANN NAA, AAN NAA, and AANN NAA with a space
		$pcexp[1] = '/^('.$alpha1.'{1}'.$alpha2.'{0,1}[0-9]{1,2})([[:space:]]{0,})([0-9]{1}'.$alpha5.'{2})$/';

		// Expression for postcodes: ANA NAA
		$pcexp[2] =  '/^('.$alpha1.'{1}[0-9]{1}'.$alpha3.'{1})([[:space:]]{0,})([0-9]{1}'.$alpha5.'{2})$/';

		// Expression for postcodes: AANA NAA
		$pcexp[3] =  '/^('.$alpha1.'{1}'.$alpha2.'{1}[0-9]{1}'.$alpha4.')([[:space:]]{0,})([0-9]{1}'.$alpha5.'{2})$/';

		// Exception for the special postcode GIR 0AA
		$pcexp[4] =  '/^(gir)([[:space:]]{0,})(0aa)$/';

		// Standard BFPO numbers
		$pcexp[5] = '/^(bfpo)([[:space:]]{0,})([0-9]{1,4})$/';

		// c/o BFPO numbers
		$pcexp[6] = '/^(bfpo)([[:space:]]{0,})(c\/o([[:space:]]{0,})[0-9]{1,3})$/';

		// Overseas Territories
		$pcexp[7] = '/^([a-z]{4})([[:space:]]{0,})(1zz)$/';

		// Anquilla
		$pcexp[8] = '/^ai-2640$/';

		// Load up the string to check, converting into lowercase
		$postcode = strtolower($toCheck);

		// Assume we are not going to find a valid postcode
		$valid = false;
		
		// Check the string against the six types of postcodes
		foreach ($pcexp as $regexp) {

			if (preg_match($regexp,$postcode, $matches)) {

				// Load new postcode back into the form element  
				$postcode = strtoupper ($matches[1] . ' ' . $matches [3]);

				// Take account of the special BFPO c/o format
				$postcode = preg_replace ('/C\/O([[:space:]]{0,})/', 'c/o ', $postcode);

				// Take acount of special Anquilla postcode format (a pain, but that's the way it is)
				if (preg_match($pcexp[7], strtolower($toCheck), $matches)) $postcode = 'AI-2640';      

				// Remember that we have found that the code is valid and break from loop
				$valid = true;
				break;
			}
		}

		// Return with the reformatted valid postcode in uppercase if the postcode was valid
		if ($valid) {
			$toCheck = $postcode; 
			return true;
		} 
		else {
			return false;
		}
	}
	
	static function calculate_median($arr) {
		sort($arr);
		$count = count($arr); //total numbers in array
		$middleval = floor(($count-1)/2); // find the middle value, or the lowest middle value
		if($count % 2) { // odd number, middle is the median
			$median = $arr[$middleval];
		} 
		else { // even number, calculate avg of 2 medians
			$low = $arr[$middleval];
			$high = $arr[$middleval+1];
			$median = (($low+$high)/2);
		}
		return $median;
	}
	
	public static function save_margin($project_id) {
		$models_to_load = array('Group', 'Project', 'ProjectPayout', 'ProjectLog');
		foreach ($models_to_load as $model) {
			App::import('Model', $model);
			$$model = new $model;
		}
			
		$project = $Project->find('first', array(
			'fields' => array('Project.id', 'Project.group_id'),
			'conditions' => array(
				'Project.id' => $project_id
			),
			'recursive' => -1
		));
		if (!$project) {
			return false;
		}
		$group = $Group->find('first', array(
			'fields' => array('Group.calculate_margin'),
			'conditions' => array(
				'Group.id' => $project['Project']['group_id']
			)
		));
		if (!$group || !$group['Group']['calculate_margin']) {
			return false; 
		}
		
		$project_payouts = $ProjectPayout->find('all', array(
			'fields' => array('ProjectPayout.type', 'ProjectPayout.client_rate_cents', 'ProjectPayout.user_payout_cents'),
			'conditions' => array(
				'ProjectPayout.project_id' => $project['Project']['id']
			),
			'recursive' => -1
		)); 
		
		if (!$project_payouts) {
			return false; 
		}
		
		$client_payout = $user_payout = 0;
		foreach ($project_payouts as $project_payout) {
			if ($project_payout['ProjectPayout']['type'] == SURVEY_COMPLETED) {
				$client_payout += $project_payout['ProjectPayout']['client_rate_cents'];
				$user_payout += $project_payout['ProjectPayout']['user_payout_cents'];
			}
			elseif ($project_payout['ProjectPayout']['type'] == SURVEY_NQ) {
				$user_payout += $project_payout['ProjectPayout']['user_payout_cents'];
			}
		}
		if ($client_payout == 0) {
			$margin_cents = 0; 
			$margin_pct = 0; 
		}
		else {
			$margin_cents = $client_payout - $user_payout;
			$margin_pct = round(100 * (1 - ($user_payout / $client_payout))); 
		}
		
		$Project->create();
		$Project->save(array('Project' => array(
			'id' => $project['Project']['id'],
			'margin_cents' => $margin_cents,
			'margin_pct' => $margin_pct,
			'modified' => false
		)), true, array('margin_cents', 'margin_pct'));
		
		$ProjectLog->create();
		$ProjectLog->save(array('ProjectLog' => array(
			'project_id' => $project['Project']['id'],
			'type' => 'margin.calculated',
			'description' => 'Client earnings: $' . round($client_payout / 100, 2) . ', User payout: $' . round($user_payout/ 100, 2) . ', Margin: $' . round($margin_cents/ 100, 2),
		)));
		return array(
			'margin_cents' => $margin_cents,
			'margin_pct' => $margin_pct
		);
	}
	
	public static function slack_alert($webhook, $message) {
		if ((!defined('IS_DEV_INSTANCE') || IS_DEV_INSTANCE === false) && !empty($webhook)) {
			App::uses('HttpSocket', 'Network/Http');
			$HttpSocket = new HttpSocket(array(
				'timeout' => 15,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$HttpSocket->post($webhook, json_encode(array(
				'text' => $message,
				'link_names' => 1,
				'username' => 'bernard'
			)));
		}
	}
	
	// $force = false (default) will show the data from cache
	// $force = true will bring the fresh data from qe2
	static function qe2_mv_qualifications($user_id, $settings, $force = false) {
		if (empty($user_id)) {
			return false;
		}
		
		$qualification = false;
		if (!$force) {
			App::import('Model', 'QeUser');
			$QeUser = new QeUser;
			$qe_user = $QeUser->find('first', array(
				'fields' => array('QeUser.value'),
				'conditions' => array(
					'QeUser.user_id' => $user_id
				)
			));
			if ($qe_user) {
				$data = json_decode($qe_user['QeUser']['value'], true);
				if (isset($data['answered']['mintvine']) && !empty($data['answered']['mintvine'])) {
					$qualification = $data['answered']['mintvine'];
				}
			}
		}
		
		if (!$qualification) {
			App::uses('HttpSocket', 'Network/Http');
			$http = new HttpSocket(array(
				'timeout' => 30,
				'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
			));
			$http->configAuth('Basic', $settings['qe.mintvine.username'], $settings['qe.mintvine.password']);
			try {
				$return = $http->get($settings['hostname.qe'] . '/qualifications/' . $user_id);
				if ($return->code == 200) {
					$data = json_decode($return->body, true);
					if (isset($data['answered']['mintvine']) && !empty($data['answered']['mintvine'])) {
						$qualification = $data['answered']['mintvine'];
					}
				}
			}
			catch (\HttpException $ex) {
				
			}
			catch (\Exception $ex) {
				
			}
		}
		
		return $qualification;
	}
	
	static function qe2_query($query_json) {
		App::import('Model', 'Setting');
		$Setting = new Setting;
			
		// load settings
		$settings = $Setting->find('list', array(
			'fields' => array('Setting.name', 'Setting.value'),
			'conditions' => array(
				'Setting.name' => array(
					'qe.mintvine.username', 
					'qe.mintvine.password', 
					'hostname.qe'
				),
				'Setting.deleted' => false
			)
		));
		if (!$settings || count($settings) != 3) {
			return false; 
		}

		App::uses('HttpSocket', 'Network/Http');
		$http = new HttpSocket(array(
			'timeout' => 30,
			'ssl_verify_host' => false // PHP does not seem to check SANs for CNs
		));
		$http->configAuth('Basic', $settings['qe.mintvine.username'], $settings['qe.mintvine.password']);
		try {
			$results = $http->post(
				$settings['hostname.qe'].'/query', $query_json, array(
					'header' => array('Content-Type' => 'application/json')
				)
			);
			return $results;
		} 
		catch (SocketException $e) {
		}
		catch (\HttpException $ex) {
		}
		catch (\Exception $ex) {
		}
		
		return false;
	}
	
}

function db($value) {
	echo '<pre>'.print_r($value, true).'</pre>';
}
