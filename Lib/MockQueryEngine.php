<?php

class MockQueryEngine {
	public static function parent_panelists($set = 1) {
		if ($set == 1) {
			return range(1, 2000); 
		}
		elseif ($set == 2) {
			return range(400, 2000); 
		}
	}
	
	public static function child_panelists($set = 1) {
		if ($set == 1) {
			$rand = rand(1, 4);
			return range( $rand * 400, ($rand + 1) * 400);
		}
		elseif ($set == 2) {
			$rand = rand(2, 4);
			return range( $rand * 400, ($rand + 1) * 400);
		}
	}
}