<?php

class ReportHelper extends NumberHelper {
	
	public function calculatePercentage($original = null, $new = null) {
		$percentage = 0;
		if (!isset($original, $new) || $original == 0) {
			return '-';
		}
		$percentage = number_format(round((($original - $new) /  $original) * -100, 2), 2);
		return $percentage.'%';
	}

	public function getPercentageClass($value = 0) {
		$class = 'muted pull-right';
		if ($value > 0) {
			$class = 'text-success pull-right';
		} 
		elseif ($value < 0) {
			$class = 'text-error pull-right';
		}
		return $class;
	}
}
