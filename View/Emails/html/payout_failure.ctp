<p style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #888888; font-size: 14px;">Hey <?php $username; ?></p>
<p style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #888888; font-size: 14px;">
	We're so sorry about this, but there was a problem processing your withdrawal request. 
	<?php if ($type == 'dwolla') : ?>
		It seems there's an unspecified error from Dwolla around your account. 
	<?php elseif ($type == 'paypal'): ?>
		It seems there's an unspecified error from PayPal around your account. 
	<?php elseif ($type == 'tango'): ?>
		It seems there's an unspecified error from our gift card merchant with your request. 
	<?php endif; ?>
</p>
<p style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #888888; font-size: 14px;">
	We've returned the points back to your account - please try setting a new payment method and reprocessing your transaction. For Dwolla and PayPal payouts, <strong>please</strong> make sure 
	your account information is set correctly! 
</p>

<p style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #888888; font-size: 14px;">Here is some information about your failed transaction:</p>

<p style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #888888; font-size: 14px;">
	<strong>Transaction ID</strong>: <?php echo $transaction['Transaction']['id']; ?><br/>
	<strong>Transaction Amount</strong>: <?php echo number_format(-1 * $transaction['Transaction']['amount']); ?> points<br/>
	<strong>Your Email Address</strong>: <?php echo $transaction['User']['email']; ?><br/>
	<strong>Transaction Initiated (GMT)</strong>: <?php echo date('F jS, Y h:i A', strtotime($transaction['Transaction']['created'])); ?><br/>
	<strong>Transaction Approved by MintVine Staff (GMT)</strong>: <?php echo date('F jS, Y h:i A', strtotime($transaction['Transaction']['executed'])); ?><br/>
	<strong>Payment Method</strong>: <?php 
		switch ($type) {
			case 'dwolla': 
				echo 'Dwolla'; 
			break;
			case 'paypal': 
				echo 'PayPal ('.$payment_value.')'; 
			break;
			case 'tango': 
				echo 'Gift Card'; 
			break;
		}
	?>
</p>
<p style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #888888; font-size: 14px;">
	Sorry for the inconvenience.
</p>
<p>&nbsp;</p>
<p style="font-family: 'Lato', Arial, Helvetica; color: #888888; font-size: 14px;">Thank you,</p>
<p style="font-family: 'Lato', Arial, Helvetica; color: #888888; font-size: 14px;">Team MintVine</p>