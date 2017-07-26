<?php $currencies = unserialize(CURRENCY);?>
<div class="container">
	<div class="h1-title">
		<h1><b>Invoice</b></h1>
		<p><?php echo Utils::change_tz_from_utc($invoice['Invoice']['date'], 'M d, Y'); ?></p>
		<p>Due <?php echo Utils::change_tz_from_utc($invoice['Invoice']['due_date'], 'M d, Y'); ?> (NET <?php echo $invoice['Invoice']['terms']; ?>)</p>
		<p style="width:350px; word-wrap:break-word; display:inline-block;">Invoice#: <?php echo $invoice['Invoice']['number']; ?></p>
		<?php if (!empty($account_manager)): ?>
			<p>Account Manager#: <?php echo $account_manager; ?></p>
		<?php endif;?>
	</div>

	<div> <img src="https://s3.amazonaws.com/cdn.mintvine.com/brinc.png" width="156" height="45" /> </div>
</div>

<div class="container">
	<div>
		<p><b>To:</b></p>
		<p class="to"><?php echo $invoice['Invoice']['name']; ?></p>
		<?php if ($invoice['Invoice']['address']): ?>
		<p class="address"><?php echo nl2br(htmlspecialchars($invoice['Invoice']['address'])); ?></p>
		<?php endif; ?>
		<p><?php echo $invoice['Invoice']['email']; ?></p>
	</div>
</div>

<div class="container">
	<table class="table table-condensed">
		<th><h5><b>Client Project Reference</b></h5></th>
		<th><h5><b>Project Reference</b></h5></th>
		<th><h5><b>Terms</b></h5></th>

		<tr>
			<td><?php echo $invoice['Invoice']['client_project_reference']; ?></td>
			<td><?php echo $invoice['Invoice']['project_reference']; ?></td>
			<td><?php echo $invoice['Invoice']['terms']; ?></td>
		</tr>
	</table>

	<table class="table table-condensed">
		<th><h5><b>Qty</b></h5></th>
		<th><h5><b>Description</b></h5></th>
		<th class="t-right"><h5><b>Unit Price</b></h5></th>
		<th class="t-right"><h5><b>Line Total</b></h5></th>
		<?php if (isset($invoice['InvoiceRow']) && !empty($invoice['InvoiceRow'])): ?>
			<?php foreach ($invoice['InvoiceRow'] as $invoice_row): ?>
				<?php $subtotal = $invoice_row['quantity'] * $invoice_row['unit_price'];
				$subtotals[] = $subtotal; ?>
				<tr>
					<td><?php echo $invoice_row['quantity']; ?></td>
					<td><?php echo $invoice_row['description']; ?></td>
					<td class="t-right"><?php echo $this->App->dollarize_signed($invoice_row['unit_price'], 2, $currencies[$invoice['Invoice']['currency']]); ?></td>
					<td class="t-right"><?php echo $this->App->dollarize_signed($subtotal, 2, $currencies[$invoice['Invoice']['currency']]); ?></td>
				</tr>
			<?php endforeach; ?>
			<tr>
				<td>&nbsp;</td>
				<td>&nbsp;</td>
				<td class="t-right"><strong>Total</strong></td>
				<td class="t-right"><?php echo $this->App->dollarize_signed(array_sum($subtotals), 2, $currencies[$invoice['Invoice']['currency']]); ?></td>
			</tr>
		<?php endif; ?>
	</table>
	<hr>
</div>

<div class="container">
	<div>
		<p><b>Make checks payable to:</b></p> 
		<p>Branded Research</p>
		<p>343 4th Ave Ste 201</p>
		<p>San Diego, CA 92101</p>
	</div>
</div>

<div class="container">
	<p><b>Wire and ACH info:</b></p> 
	<p>JP Morgan Chase Bank, N.A.</p>
	<p>Account #: 465799372</p>
	<p>ABA/Routing #: 322271627</p>
	<p>Swift Code: CHASUS33</p>
</div>