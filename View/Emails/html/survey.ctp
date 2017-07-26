<table align="center">
	<tr>
		<td style="font-family: 'Dosis', Arial, Helvetica, sans-serif; font-size: 24px; font-weight: 500; color: #68C185; text-align: center; height: 40px">
			Another Exciting Survey Opportunity
		</td>
	</tr>
	<tr>
		<td style="font-family: 'Lato', Arial, Helvetica; color: #888888; font-size: 18px; font-weight: 300; text-align: center">
			Points offered: <?php echo $survey_award; ?>
			<br />
			Survey length: <?php echo $survey_length; ?> minutes
		</td>
	</tr>
	<tr>
		<td style="height: 20px"></td>
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
			   href="<?php echo $survey_url; ?>">Take the survey</a>
		</td>
	</tr>
	<tr>
		<td style="height: 20px"></td>
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
</table>
