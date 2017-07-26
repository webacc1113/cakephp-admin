<?php
	$request = json_decode($partner_log['PartnerLog']['sent']);
	$response = json_decode($partner_log['PartnerLog']['raw_output']);
?>
<h5>Request</h5>
<textarea rows="6"><?php echo (!empty($request)) ? json_encode($request, JSON_PRETTY_PRINT) : ''; ?></textarea>
<br>
<h5>Response</h5>
<textarea rows="6"><?php echo (!empty($response)) ? json_encode($response, JSON_PRETTY_PRINT) : ''; ?></textarea>
