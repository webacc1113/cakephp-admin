<table align="center">
	<tr>
		<td style="font-family: 'Dosis', Arial, Helvetica, sans-serif; font-size: 24px; font-weight: 500; color: #68C185; text-align: center; height: 40px">
			You are now able to access the chat room for your interactive research session.
			<?php echo $this->Html->image('current-interview-banner.png', array('alt' => 'MintVine', 'style' => 'border: none; display: block;'));?>
		</td>
	</tr>
	<tr>
		<td style="font-family: 'Lato', Arial, Helvetica; color: #888888; font-size: 13px; font-weight: 300; text-align: center">
			Please connect to the chat room 10 minutes prior to the session start time to confirm that you are able to connect successfully.			
		</td>
	</tr>
	<tr>
		<td style="font-family: 'Lato', Arial, Helvetica; color: #888888; font-size: 13px; font-weight: 300; text-align: center">
			The session will start promptly at <strong><?php echo $this->Time->format(
				strtotime($interview_date), 
				Utils::dateFormatToStrftime('h:i A'), 
				false, 
				$timezone ? $timezone : 'America/Los_Angeles'
			);?></strong>
		</td>
	</tr>	
	<tr>
		<td style="font-family: 'Lato', Arial, Helvetica; color: #888888; font-size: 13px; font-weight: 300; text-align: center">
			<a href="{{survey_url}}"> Chat Entry Point</a>
		</td>
	</tr>
	<tr>
		<td style="font-family: 'Lato', Arial, Helvetica; color: #888888; font-size: 13px; font-weight: 300; text-align: center">
		Rules of conduct <br />
			- Type your own response or select something from the community<br />
			- You must download any required application or software to receive credit<br />
			- You will receive a link from moderators at the end of the chat to be directed  back to MintVine and to receive final credit.
		</td>
	</tr>
	<tr>
		<td style="height: 100px"></td>
	</tr>
</table>	