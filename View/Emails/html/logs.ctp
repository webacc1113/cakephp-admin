<table cellpadding="0" cellspacing="0" style="border-collapse: collapse; border-spacing: 0;background-color: rgba(0, 0, 0, 0); max-width: 100%; margin-bottom: 20px; width: 100%;font-family: 'Lato', Arial, Helvetica; color: #888888; font-size: 14px;">
	<thead>
		<tr>
			<td style="border-bottom: 1px solid #cccccc;padding: 3px;">User Email</td>
			<td style="border-bottom: 1px solid #cccccc;padding: 3px;">Payment Type</td>
			<td style="border-bottom: 1px solid #cccccc;padding: 3px;">Amount</td>
			<td style="border-bottom: 1px solid #cccccc;padding: 3px;">User submission</td>
			<td style="border-bottom: 1px solid #cccccc;padding: 3px;">Approved</td>
			<td style="border-bottom: 1px solid #cccccc;padding: 3px;">Processed</td>
			<td style="border-bottom: 1px solid #cccccc;padding: 3px;">Status</td>
		</tr>
	</thead>
	<tbody>
		<?php foreach ($logs as $log): ?>
		<tr>
			<td style="border-bottom: 1px solid #cccccc;padding: 3px;">
				<?php echo $log['PaymentLog']['user_email']; ?>
			</td>
			<td style="border-bottom: 1px solid #cccccc;padding: 3px;">
				<?php echo $log['PaymentLog']['transaction_name']; ?>
			</td>
			<td style="border-bottom: 1px solid #cccccc;padding: 3px;">
				<?php echo $log['PaymentLog']['transaction_amount']; ?>
			</td>
			<td style="border-bottom: 1px solid #cccccc;padding: 3px;">
				<?php echo $this->Time->format($log['PaymentLog']['transaction_created'], Utils::dateFormatToStrftime('M jS, Y h:i A'), false, 'America/Los_Angeles'); ?>
			</td>
			<td style="border-bottom: 1px solid #cccccc;padding: 3px;">
				<?php echo $this->Time->format($log['PaymentLog']['transaction_executed'], Utils::dateFormatToStrftime('M jS, Y h:i A'), false, 'America/Los_Angeles'); ?>
			</td>
			<td style="border-bottom: 1px solid #cccccc;padding: 3px;">
				<?php echo ($log['PaymentLog']['processed']) ? $this->Time->format($log['PaymentLog']['processed'], Utils::dateFormatToStrftime('M jS, Y h:i A'), false, 'America/Los_Angeles') : '<span class="muted">-</span>'; ?>
			</td>
			<td style="border-bottom: 1px solid #cccccc;padding: 3px;">
				<?php if ($log['PaymentLog']['status'] == PAYMENT_LOG_SUCCESSFUL) : ?>
					<span style="text-transform: uppercase; 
						background-color: #85ca85; 
						border-radius: 0.25em;
						color: #fff;
						display: inline;
						font-size: 75%;
						font-weight: 700;
						line-height: 1;
						padding: 0.2em 0.6em 0.3em;">Successful</span>
				<?php elseif($log['PaymentLog']['status'] == PAYMENT_LOG_FAILED): ?>
					<span style="text-transform: uppercase; 
						background-color: #d9534f; 
						border-radius: 0.25em;
						color: #fff;
						display: inline;
						font-size: 75%;
						font-weight: 700;
						line-height: 1;
						padding: 0.2em 0.6em 0.3em;">Failed</span>
				<?php elseif($log['PaymentLog']['status'] == PAYMENT_LOG_ABORTED): ?>
					<span style="text-transform: uppercase; 
						background-color: #f89406; 
						border-radius: 0.25em;
						color: #fff;
						display: inline;
						font-size: 75%;
						font-weight: 700;
						line-height: 1;
						padding: 0.2em 0.6em 0.3em;">Aborted</span>
				<?php else: ?>
					<span class="muted">-</span>
				<?php endif; ?>	
			</td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>