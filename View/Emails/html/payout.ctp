<?php
if ($payment_method == 'paypal') {
	$account_name = 'Paypal account';
}
elseif ($payment_method == 'dwolla' || $payment_method == 'dwolla_id') {
	$account_name = 'Dwolla account';
}
elseif ($payment_method == 'tango') {
	$account_name = 'Gift card';
}
elseif ($payment_method == 'mvpay') {
	$account_name = 'MVPay account';
}
?>

<table style="margin: 40px 0 0 44px">
	<tr>
		<td style="font-family: 'Dosis', Arial, Helvetica, sans-serif; font-size: 24px; font-weight: 500; color: #68C185; height: 40px">
			Nice work! Cash is on its way  :<span style="vertical-align: middle">)</span>
		</td>
	</tr>
	<tr>
		<td style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #888888; font-size: 14px; font-weight: 300">
			Your withdrawal has been received and is being processed.<br />
			Your <?php echo $account_name; ?> will be credited within 48 hours.<br />
			<br />
			Just so you know, you won't see this deposit in your Earnings list until<br />
			the funds have been released to your account.<br />
			<br />
			Please reply to this message if you have any questions!<br />
			<br />
			-MintVine Support
		</td>
	</tr>
	<tr>
		<td>
			<div style="border: 1px solid #d3d3d3; border-radius: 5px; padding: 10px 15px; float: left; margin-top: 20px;">
				<span style="width: 50%; float: left; margin-right: 3%; font-family: 'Lato', Arial, Helvetica; font-weight: bold; color: #6a6a6a;">
					<?php echo __('See what others are saying about MintVine, and leave a review yourself!') ?>
				</span>
				<span style="float: left; margin-right: 30px;">
					<a href="https://www.surveypolice.com/mintvine">
						<?php echo $this->Html->image('survey-police-small.png', array('alt' => 'Survey Police', 'style' => 'border: none; display: block;'));?>
					</a>
				</span>
				<span style="float: left; margin-right: 30px;">
					<a href="https://www.sitejabber.com/reviews/www.mintvine.com">
						<?php echo $this->Html->image('sitejabber_small.png', array('alt' => 'SiteJabber', 'style' => 'border: none; display: block;'));?>
					</a>
				</span>
			</div>
		</td>
	</tr>
	<tr>
		<td style="height: 50px"></td>
	</tr>
</table>
<?php if ($trustpilot): ?>
<script type="application/json+trustpilot">
{
"recipientName": "<?php echo $user_name; ?>",
"referenceId": "<?php echo $user_id; ?>",
}
</script>
<?php endif; ?>
