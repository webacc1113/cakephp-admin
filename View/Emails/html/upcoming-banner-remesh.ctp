<table align="center">
	<tr>
		<td style="font-family: 'Dosis', Arial, Helvetica, sans-serif; font-size: 24px; font-weight: 500; color: #68C185; text-align: center; height: 40px">
			You have an upcoming session
			<?php echo $this->Html->image('upcomming-icon.png', array('alt' => 'MintVine', 'style' => 'border: none; display: block;'));?>
		</td>
	</tr>
	<tr>
		<td style="font-family: 'Lato', Arial, Helvetica; color: #888888; font-size: 13px; font-weight: 300; text-align: center">
			Research Session Date/Time: <strong>
			<?php echo $this->Time->format(
				strtotime($interview_date),
				Utils::dateFormatToStrftime('l F d, Y') . ' at ' . Utils::dateFormatToStrftime('H:m A '), 
				false, 
				$timezone ? $timezone : 'America/Los_Angeles'
			);?>
			</strong>
		</td>
	</tr>	
	<tr>
		<td style="font-family: 'Lato', Arial, Helvetica; color: #888888; font-size: 13px; font-weight: 300; text-align: center">
		Click in now so we can reserve your spot in the session. The session is going to cover music, headphones, and your preferences.		
		</td>
	</tr>
	<tr>
		<td style="font-family: 'Lato', Arial, Helvetica; color: #68C185; font-size: 13px; font-weight: 300; text-align: center">
			<a style="color: #68C185;" href="<?php echo HOSTNAME_WWW; ?>">Select this link to complete your Check-in</a>
		</td>
	</tr>
	<tr>
		<td style="height: 100px"></td>
	</tr>
</table>	