<p style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #666666; font-size: 14px; font-weight: 300">Hi <?php echo $user_name; ?></p>

<p style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #666666; font-size: 14px; font-weight: 300">We're happy to report that we've approved your recent request for missing points.</p>

<p style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #666666; font-size: 14px; font-weight: 300; line-height: 22px;">
	<span>Points:</span> <span style="font-size: 16px;"> <?php echo $points; ?></span><br />
	<span>Reported At:</span> <?php echo $this->Time->format(
								strtotime($reported_at), Utils::dateFormatToStrftime('F jS, Y h:i A'), false, ($user_timezone) ? $user_timezone : 'America/Los_Angeles'
							); ?><br />
	<span>Approved At:</span> <?php echo $this->Time->format(
								strtotime($approved_at), Utils::dateFormatToStrftime('F jS, Y h:i A'), false, ($user_timezone) ? $user_timezone : 'America/Los_Angeles'
							); ?><br />
	<span>Approved By:</span> <?php echo $approved_by; ?><br />
</p>

<p style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #666666; font-size: 14px; font-weight: 300">
	<span>From:</span> MintVine
</p>