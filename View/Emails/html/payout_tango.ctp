<p style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #666666; font-size: 14px; font-weight: 300">Hi <?php echo $user_name; ?></p>

<p style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #666666; font-size: 14px; font-weight: 300">Here is your <b><?php echo $transaction_name; ?></b> from MintVine.</p>

<p style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #666666; font-size: 14px; font-weight: 300; line-height: 22px;">
	<span style="color:#ff0000;">Amount:</span> <span style="font-size: 16px;"> <?php echo $amount; ?></span><br />
	<?php if (isset($reward['number'])) : ?>
		<span style="color:#ff0000;">Code:</span> <?php echo $reward['number']; ?><br />
	<?php endif; ?>
		
	<?php if (isset($reward['token'])) : ?>
		<span style="color:#ff0000;">Token:</span> <?php echo $reward['token']; ?><br />
	<?php endif; ?>

	<?php if (isset($reward['pin'])) : ?>
		<span style="color:#ff0000;">Pin:</span> <?php echo $reward['pin']; ?><br />
	<?php endif; ?>

	<?php if (isset($reward['redemption_url'])) : ?>
		<span style="color:#ff0000;">Redemption URL:</span> <?php echo $reward['redemption_url']; ?><br />
	<?php endif; ?>

	<?php if (isset($reward['event_number'])) : ?>
		<span style="color:#ff0000;">Event Number:</span> <?php echo $reward['event_number']; ?><br />
	<?php endif; ?>
		
	<?php if (isset($reward['expiration'])) : ?>
		<span style="color:#ff0000;">Expiration Date:</span> <?php echo $reward['expiration']; ?><br />
	<?php endif; ?>
	<span style="color:#ff0000;">From:</span> MintVine<br />
</p>
<p style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #666666; font-size: 14px; font-weight: 300">
	<?php echo nl2br(htmlspecialchars($redemption_instructions)); ?>
</p>

<p style="font-family: 'Lato', Arial, Helvetica, sans-serif; color: #666666; font-size: 12px; font-weight: 300;">
	<?php echo $disclaimer; ?>
</p>
