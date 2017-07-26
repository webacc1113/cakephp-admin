<option value="">All partners</option>
<?php
	if (!empty($partners)) {
		foreach ($partners as $id => $partner) {
			echo '<option value="'.$id.'">'.$partner.'</option>';
		}
	}
?>