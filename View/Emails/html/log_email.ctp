<p>This is an analysis of the last 100 Quickbook invoices as well as the last week's worth of MintVine invoices</p>
<table cellpadding="0" cellspacing="0" style="border-collapse: collapse; border-spacing: 0;background-color: rgba(0, 0, 0, 0); max-width: 100%; margin-bottom: 20px; width: 100%;font-family: 'Lato', Arial, Helvetica; color: #888888; font-size: 14px;">
	<thead>
		<tr>
			<td style="border-bottom: 1px solid #cccccc;padding: 3px;">Quickbook Invoice Id</td>
			<td style="border-bottom: 1px solid #cccccc;padding: 3px;">MintVine Invoice Id</td>
			<td style="border-bottom: 1px solid #cccccc;padding: 3px;">QB Balance</td>
			<td style="border-bottom: 1px solid #cccccc;padding: 3px;">MV Balance</td>
			<td style="border-bottom: 1px solid #cccccc;padding: 3px;">Description</td>
		</tr>
	</thead>
	<tbody>
		<?php foreach ($email_rows as $email_row):?>
			<tr>
				<td style="border-bottom: 1px solid #cccccc;padding: 3px;">
					<?php if (empty($email_row['quickbook_invoice_id'])): ?>
						Missing from QB
					<?php else: ?>
						<?php echo $this->Html->link('QB #'.$email_row['quickbook_invoice_id'], 'https://qbo.intuit.com/app/invoice?txnId='.$email_row['quickbook_invoice_id']); ?>
					<?php endif; ?>
				</td>
				<td style="border-bottom: 1px solid #cccccc;padding: 3px;">
					<?php if (empty($email_row['invoice_id'])): ?>
						Missing from MV
					<?php elseif (isset($email_row['project_id'])): ?>
						<?php echo $this->Html->link('MV #'.$email_row['project_id'], HOSTNAME_WEB.'/surveys/dashboard/'.$email_row['project_id']); ?>
					<?php else: ?>
						Project not found.
					<?php endif; ?>
				</td>
				<td style="border-bottom: 1px solid #cccccc;padding: 3px;">
					<?php echo $email_row['QB Balance']; ?>
				</td>
				<td style="border-bottom: 1px solid #cccccc;padding: 3px;">
					<?php echo $email_row['MV Balance']; ?>
				</td>
				<td style="border-bottom: 1px solid #cccccc;padding: 3px;">
					<?php echo $email_row['description']; ?>
				</td>
			</tr>
		<?php endforeach;?>
	</tbody>
</table>