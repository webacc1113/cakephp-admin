<h3>Points2shop Session Log</h3>
<p>
	<strong>Project ID:</strong> <?php echo $points2shop_session_log['Points2shopSessionLog']['project_id']; ?>
</p>
<p>
	<strong>P2s Project ID:</strong> <?php echo $points2shop_session_log['Points2shopSessionLog']['p2s_project_id']; ?>
</p>
<p>
	<strong>Requested URL:</strong> <input type="text" value="<?php echo $points2shop_session_log['Points2shopSessionLog']['requested_url']; ?>" />
</p>
<p>
	<strong>Raw Feed:</strong> <pre><?php var_export(json_decode($points2shop_session_log['Points2shopSessionLog']['raw_feed'], true)); ?></pre>
</p>
<p>
	<strong>Filtered Values:</strong> <pre><?php echo var_export(json_decode($points2shop_session_log['Points2shopSessionLog']['filtered_values'], true)); ?></pre>
</p>