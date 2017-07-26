<pre>
	<?php 
		if ($aytm_raw_type == 'post_raw') {
			print_r($aytm_log['AytmLog']['post_raw']);
		}
		else if ($aytm_raw_type == 'get_raw') {
			print_r($aytm_log['AytmLog']['get_raw']);
		}
		else if ($aytm_raw_type == 'headers_raw') {
			print_r($aytm_log['AytmLog']['headers_raw']);
		}
	?>
</pre>