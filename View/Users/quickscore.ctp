<?php 
	$failed = array(	
		'countries' => 'User has accessed the site outside of the US, GB, or CA', 
		'referral' => 'User was referred by a hellbanned user.', 
		'language' => 'User\'s browser language is not English.', 
		'locations' => 'User has used multiple states to access the site.', 
		'logins' => 'User utilized many different IPs for logins & registrations.', 
		'proxy' => 'User has utilized proxy servers.', 
		'timezone' => 'User has used a timezones that does not match their self-reported postal code.', 
		/* 'profile' => 'User sped through profile questions', */
		'rejected_transactions' => 'User had more than 5 rejected transactions',
		'asian_timezone' => 'Accessed from an Asian timezone',
		'distance' => 'IP addresses that are geographically dispersed utilized.',
		'frequency' => 'User has had a payout or registered in the last 7 days.',
		'offerwall' => 'User has 100% revenue from offerwall completions on revenue-generating activities',
		'poll_revenue' => 'User has 100% revenue from daily poll completions.',
		'payout' => 'Large payout requested',/* ,
		'nonrevenue' => '> 90% non-revenue generating activity' */
		'mobile_verified' => 'User has not verified their phone number.',
		'duplicate_number' => 'User has a duplicate phone number with other accounts.',
		'ip_address' => 'User has utilized an 8.8.8.8 IP address.'
	);
	$success = array(	
		'countries' => 'User has only accessed the site in the US, UK, or CA', 
		'referral' => 'User was not referred by a hellbanned user.', 
		'language' => 'User\'s browser language is set to English.', 
		'logins' => 'User utilized few IPs for logins & registrations.', 
		'locations' => 'User has used the same state to access the site.', 
		'proxy' => 'User has not utilized proxy servers.', 
		'timezone' => 'User has used consistent timezones when accessing the site.',
		/* 'profile' => 'User did not speed through profile questions',	 */
		'rejected_transactions' => 'User has had less than 5 rejected transactions',
		'asian_timezone' => 'User did not access from an Asian timezone',
		'distance' => 'User utilized IP addresses that are geographically consistent.',
		/* 'nonrevenue' => 'Non-revenue activity is < 90%', */
		'mobile_verified' => 'User has verified their phone number.'
	);
?>
<dl>
	<dt>Score</dt>
	<dd>
		Score generated on <?php echo $this->Time->format('F jS, Y h:i:A', $user_analysis['UserAnalysis']['created']); ?> GMT
		(<?php echo $this->Time->format($user_analysis['UserAnalysis']['created'], Utils::dateFormatToStrftime('F jS, Y h:i:A'), false, $timezone) ?> <?php echo $timezone; ?>)
	</dd>
	<dd>
		<div class="label label-info"><?php echo $user_analysis['UserAnalysis']['score']; ?></div> 
		(Raw score: <?php echo $user_analysis['UserAnalysis']['raw']; ?> / Total possible points: <?php echo $user_analysis['UserAnalysis']['total']; ?>)
	</dd>
	<dt>Tests that contributed to fraud score</dt>
	<?php if (!empty($user_analysis['UserAnalysis']['minfraud'])): ?>
		<dd class="text-error">
			<span class="label label-important"><?php echo $user_analysis['UserAnalysis']['minfraud']; ?></span> MinFraud
		</dd>
	<?php endif; ?>
	<dd class="text-error">
	<?php
	$messages = array(); 
	foreach ($user_analysis['UserAnalysis'] as $key => $val) { 
		if (!array_key_exists($key, $failed)) {
			continue;
		}
		if ($val > 0) {
			$messages[] = '<span class="label label-important">'.$val.'</span> '.$failed[$key]; 
		}
	} 
	echo implode('</dd><dd class="text-error">', $messages); 
	?></dd>
	<dt>Positive factors that didn't affect score</dt>
	<dd class="text-success"><?php 
	$messages = array(); 
	foreach ($user_analysis['UserAnalysis'] as $key => $val) { 
		if (!array_key_exists($key, $success)) {
			continue;
		}
		if ($val == '0') {
			$messages[] = $success[$key].' <span class="muted">(Weight: '.$weights[$key].')</span>'; 
		}
	} 
	echo implode('</dd><dd class="text-success">', $messages); 
	?></dd>
	<dt>Unknown Factors (Not enough data to determine yet)</dt>
	<dd class="muted"><?php 
	$messages = array(); 
	foreach ($user_analysis['UserAnalysis'] as $key => $val) { 
		if (!array_key_exists($key, $success)) {
			continue;
		}
		if (is_null($val)) {
			$messages[] = $success[$key].' <span class="muted">(Weight: '.$weights[$key].')</span>'; 
		}
	} 
	echo implode('</dd><dd class="muted">', $messages); 
	?></dd>
</dl>