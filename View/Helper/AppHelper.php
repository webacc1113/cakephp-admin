<?php
/**
 * Application level View Helper
 *
 * This file is application-wide helper file. You can put all
 * application-wide helper-related methods here.
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       app.View.Helper
 * @since         CakePHP(tm) v 0.2.9
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */
App::uses('Helper', 'View');

/**
 * Application helper
 *
 * Add your application-wide methods in the class below, your helpers
 * will inherit them.
 *
 * @package       app.View.Helper
 */
class AppHelper extends Helper {
	
	function clear() {
		return '<div class="clearfix"></div>';
	}
	
	function isDebug($current_user) {
		return $current_user['Admin']['admin_user'] == 'RK';
	}
	
	function ir($project) {
		
		// Bid IR
		if (!empty($project['Project']['bid_ir'])) {
			$return = $project['Project']['bid_ir'].'%';
		}
		else {
			$return = '<span class="muted">-</span>';
		}
				
		// Client IR
		$return .= ' / ';
		$client_ir = '0';
		if (!empty($project['SurveyVisitCache']['client_ir'])) {
			$client_ir = $project['SurveyVisitCache']['client_ir'];
		}
		elseif ($project['SurveyVisitCache']['complete'] > 0) {
			$client_ir = round($project['SurveyVisitCache']['complete'] / ($project['SurveyVisitCache']['complete'] + $project['SurveyVisitCache']['nq']), 2) * 100;
		}
		
		$show_warning_label = false;
		if (!empty($project['Project']['bid_ir']) && $client_ir <= ($project['Project']['bid_ir'] / 2)) {
			$show_warning_label = true;
		}
		
		if ($project['SurveyVisitCache']['click'] == 0) {
			$return .= '<span class="muted">-</span>';
		}
		else {
			if ($show_warning_label) {
				$return .= '<span class="label label-red"><strong>'.$client_ir.'%</strong></span>';
			}
			else {
				$return .= $client_ir.'%';
			}
		}
		
		// Actual IR
		$return .= ' / ';
		$actual_ir = '0';
		if (!empty($project['SurveyVisitCache']['ir'])) {
			$actual_ir = $project['SurveyVisitCache']['ir'];
		}
		elseif ($project['SurveyVisitCache']['complete'] > 0) {
			$actual_ir = round($project['SurveyVisitCache']['complete'] / $project['SurveyVisitCache']['click'], 2) * 100;
		}
		
		$show_warning_label = false;
		if (!empty($project['Project']['bid_ir']) && $actual_ir <= ($project['Project']['bid_ir'] / 2)) {
			$show_warning_label = true;
		}
		
		if ($project['SurveyVisitCache']['click'] == 0) {
			$return .= '<span class="muted">-</span>';
		}
		else {
			if ($show_warning_label) {
				$return .= '<span class="label label-red"><strong>'.$actual_ir.'%</strong></span>';
			}
			else {
				$return .= $actual_ir.'%';
			}
		}
		
		return $return;		
	}
	
	function epc($project) {
		$show_warning_label = false;
		$return = '';
		if (!empty($project['SurveyVisitCache']['epc'])) {
			$actual_epc = $project['SurveyVisitCache']['epc'];
		}
		if (isset($actual_epc) && $actual_epc <= ($project['Project']['epc'] / 2)) {
			$show_warning_label = true;
		}
		if (!empty($project['Project']['epc'])) {
			$return .= '$'.Utils::dollarize_points($project['Project']['epc']);
		}
		else {
			$return .= '<span class="muted">-</span>';
		}
		$return .= ' / '; 
		
		if (is_null($project['SurveyVisitCache']['epc'])) {
			$return .= '<span class="muted">-</span>';
		}
		elseif ($project['SurveyVisitCache']['epc'] == 0) {
			$return .= '<span class="label label-red"><strong>$0</strong></span>';
		}
		elseif ($project['SurveyVisitCache']['epc'] > 0) {
			if ($show_warning_label) {
				$return .= '<span class="label label-red"><strong>$'.Utils::dollarize_points($actual_epc).'</strong></span>';
			}
			else {
				$return .= '$'.Utils::dollarize_points($actual_epc);
			}
		}
		
		return $return;		
	}
	
	function quota_number($project) {	
		$show_warning_label = false;
		if ($project['Project']['quota'] > 0) {
			$pct = round($project['SurveyVisitCache']['complete'] / $project['Project']['quota'] * 100);
			if ($pct >= 90 && $project['Project']['active'] && $project['Project']['status'] == PROJECT_STATUS_OPEN) {
				$show_warning_label = true;
			}
		}
		if ($show_warning_label) {
			return '<span class="label label-red"><strong>'.number_format($project['Project']['quota']).'</strong></span>';
		}
		else {
			if (empty($project['Project']['quota'])) {
				return '-';
			}
			return number_format($project['Project']['quota']); 
		}	
	}
	
	function project_name($project) {
		return '#'.$this->project_id($project).' '.$project['Project']['prj_name'];
	}
	
	function age($dob, $return = '<span class="muted">-</span>') {
		if ($dob == '0000-00-00') {
			return $return;
		}
		$bd = explode('-', $dob); 
		if (empty($bd[0]) || empty($bd[1]) || empty($bd[1])) {
			return $return;
		}
		$birthDate = array(
			$bd[1],
			$bd[2],
			$bd[0],
		);
		return date("md", date("U", mktime(0, 0, 0, $birthDate[0], $birthDate[1], $birthDate[2]))) > date("md") ? ((date("Y")-$birthDate[2])-1):(date("Y")-$birthDate[2]);
	}
	
	// deprecate this abstraction in the future
	function project_id($project) {
		return MintVine::project_id($project); 
	}
	
	function shortened_statistics($survey_visit_cache) {
		return implode(' / ', array(
			$survey_visit_cache['click'],
			$survey_visit_cache['complete'],
			$survey_visit_cache['overquota'],
			$survey_visit_cache['nq'],
			$survey_visit_cache['speed'],
			$survey_visit_cache['fraud']
		)); 
	}
	
	function balance($user) {
		return number_format($user['balance'] + $user['pending']);
	}
	
	function number($number) {
		if (empty($number)) {
			return '<span class="muted">-</span>';
		}
		return number_format($number);
	}
	
	public static function dollarize($value, $decimals = 0, $currency = '$') {
		if ($value == '') {
			$value = 0;
		}
		if ($value < 0) {
			$value = $value * -1;
		}
		return $currency.number_format($value, $decimals);
	}	
	
	public static function dollarize_signed($value, $decimals = 0, $currency = '$') {
		if ($value == '') {
			$value = 0;
		}
		return $currency.number_format($value, $decimals);
	}
	
	public static function negatize($value, $int) {
		if ($int >= 0) {
			return $value;
		}
		return '<span class="text-error">('.$value.')</span>';
	}
	
	public static function domain_host($url) {
		$parse = parse_url($url);
		return $parse['host'];
	}
	
	public function format_points($points) {
		return number_format($points);
	}

	public static function username($user) {
		if (!empty($user['username'])) {
			return htmlspecialchars($user['username']);
		}
		elseif (!empty($user['fullname'])) {
			return htmlspecialchars($user['fullname']);
		}
	}

	function pretty_number($num) {
		if ($num > 999) {
			$x = round($num);
			$x_number_format = number_format($x);
			$x_array = explode(',', $x_number_format);
			$x_parts = array('k', 'm', 'b', 't');
			$x_count_parts = count($x_array) - 1;
			$x_display = $x;
			$x_display = $x_array[0] . ((int) $x_array[1][0] !== 0 ? '.' . $x_array[1][0] : '');
			$x_display .= $x_parts[$x_count_parts - 1];
		}
		else {
			$x_display = $num;
		}
		return $x_display;
    }
	
	function drops($project) {
		$drop_rate = $project['SurveyVisitCache']['drops']; 
		// possible it hasn't been calculated yet from backend script, so calculate it live
		if (empty($drop_rate)) {
			if ($project['SurveyVisitCache']['click'] > 0) {
				$drop_rate = 100 - round((($project['SurveyVisitCache']['complete'] + $project['SurveyVisitCache']['nq'] + $project['SurveyVisitCache']['overquota'] + $project['SurveyVisitCache']['speed'] + $project['SurveyVisitCache']['fraud']) / $project['SurveyVisitCache']['click']) * 100);
			} 
		}
		if (is_null($drop_rate)) {
			return '<span class="muted">-</span>';
		}
		// weird edge case that needs further inspection later
		if ($drop_rate < 0) {
			$drop_rate = 0; 
		}
		return $drop_rate.'%';
	}
}
