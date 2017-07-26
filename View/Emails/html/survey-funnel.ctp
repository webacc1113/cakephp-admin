<table align="center">
	<tr>
		<td style="font-family: 'Dosis', Arial, Helvetica, sans-serif; font-size: 24px; font-weight: 500; color: #68C185; text-align: center; height: 40px">
			Fresh new surveys available on Rewards Road
		</td>
	</tr>
	<tr>
		<td style="text-align: center">
			<?php echo $this->Html->image('rewards_road.png', array('alt' => 'Rewards Road', 'style' => 'border: none; display: inline-block'));?>			
		</td>
	</tr>
	<tr>
		<td style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #999999; font-size: 12px; font-weight: 300; text-align: center">
			Available survey inventory is currently at maximum. Enter now to earn.
		</td>
	</tr>
	<tr>
		<td style="font-family: 'Lato', Arial, Helvetica; color: #888888; font-size: 18px; font-weight: 300; text-align: center">
			<?php if (!$is_desktop): ?>
				<?php if ($is_mobile && $is_tablet): ?>
					This survey is available for tablet and mobile devices ONLY!<br> You will not be able to take it from your personal computer!
				<?php elseif ($is_mobile): ?>
					This survey is available for mobile devices ONLY!<br> You will not be able to take it from your personal computer!
				<?php endif; ?>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<td style="height: 10px"></td>
	</tr>
	<tr>
		<td style="text-align: center">
			<a style="
			color: #FFFFFF;
			background-image: none;
			background-color: #f0554e;
			border-width: 1px;
			border-style: solid;
			border-color: #a5413f;
			border-radius: 4px;
			cursor: pointer;
			display: inline-block;
			font-family: 'Lato', Arial, Helvetica;
			font-size: 18px;
			font-weight: 400;
			text-decoration: none;
			padding: 6px 40px;
			box-shadow: 0 2px 2px #888888"
			   href="<?php echo $survey_url; ?>">Enter the survey funnel</a>
		</td>
	</tr>
	<tr>
		<td style="height: 10px"></td>
	</tr>
	<tr>
		<td style="font-family: 'Lato', Arial, Helvetica; color: #888888; font-size: 18px; font-weight: 300; text-align: center">
			Points offered: <?php echo $survey_award; ?> per completed survey<br />
			Survey length: <?php echo $survey_length; ?> minutes avg.
		</td>
	</tr>
	<tr>
		<td style="height: 50px"></td>
	</tr>
	<tr>
		<td style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #888888; font-size: 14px; font-weight: 300; text-align: center">
			If you are unable to click on the link above, please copy and paste <br />
			this link in your browser or login to your MintVine account.<br /><br />
			<a href="<?php echo $survey_url;?>" style="color: #428BCA; text-decoration: none; font-family: 'Lato', Arial, Helvetica;"><?php echo $survey_url; ?></a>
		</td>
	</tr>
	<tr>
		<td style="height: 100px"></td>
	</tr>
	<tr>
		<td style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #999999; font-size: 12px; font-weight: 300; text-align: center">
			<b>Please</b> note, this is a link to possibly <b><i>many</i></b>  different surveys that are available to you right now,<br />
			offered and hosted by several of our research partners. This survey <b>"funnel"</b> will continue to redirect you to surveys<br />
			that you may qualify for. You will be credited the advertised points above for <b><i>each</i></b>  successful survey complete.<br />
			<br />
			*You can access this link at all times inside your Surveys tab on MintVine.com.  Please visit <b><i>daily</i></b>  for fresh, <b>new</b><br />
			surveys!  In addition, we will periodically send you reminder email invitations, too.<br />
			<br />
		</td>
	</tr>
</table>