<?php if (isset($tangocard_order)): ?>
	<?php if ($tangocard_order['TangocardOrder']['resend_count'] == 0): ?>
		<p>Redemption email never resent.</p>
	<?php else: ?>
		<dl>
			<dt>Resend count</dt>
			<dd><?php echo $tangocard_order['TangocardOrder']['resend_count']?></dd>

			<dt>Last Resend</dt>
			<dd><?php echo $this->Time->format($tangocard_order['TangocardOrder']['last_resend'], Utils::dateFormatToStrftime('M jS, Y h:i A'), false, $timezone); ?></dd>
		</dl>
	<?php endif; ?>
	
<?php else: ?>
	Resend info not found for this order.
<?php endif; ?>