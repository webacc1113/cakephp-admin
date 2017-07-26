<div class="box">
	<div class="box-header">
		<span class="title">Invoices</span>
	</div>
	<div class="box-content">
		<table class="table table-normal">
			<tr>
				<th>Invoice #</th>
				<th>Project ID</th>
				<th>Sent To</th>
				<th>Sent</th>
				<th>Total</th>
				<th></th>
			</tr>
		<?php if (isset($invoices) && !empty($invoices)): ?>
			<?php foreach ($invoices as $invoice): ?>
				<tr>
					<td><?php echo $invoice['Invoice']['number']; ?></td>
					<td>
						<?php if (!empty($invoice['Invoice']['project_id'])): ?>
							<?php echo $this->Html->link(
								'#'.$invoice['Invoice']['project_id'], 
								array('controller' => 'surveys', 'action' => 'dashboard', $invoice['Invoice']['project_id'])
							); ?>
						<?php elseif (!empty($invoice['Invoice']['project_reference'])): ?>
							<?php echo $invoice['Invoice']['project_reference']; ?>
						<?php endif; ?>
					</td>
					<td>
						<?php echo $invoice['Invoice']['name']; ?> (<?php 
							echo $this->Html->link($invoice['Invoice']['email'], 'mailto:'.$invoice['Invoice']['email']);
						?>)
					</td>
					<td><?php if ($invoice['Invoice']['sent'] == null): ?>
							<span class="muted">-</span>
						<?php else: ?>
							<?php echo Utils::change_tz_from_utc($invoice['Invoice']['sent'], 'M d, Y'); ?>
						<?php endif; ?>
					</td>
					<td><?php echo $this->App->dollarize_signed($invoice['Invoice']['subtotal'], 2); ?></td>
					<td>
						<?php echo $this->Html->link('View', array('controller' => 'invoices', 'action' => 'view', $invoice['Invoice']['uuid']), array('class' => 'btn btn-default')); ?>
						<?php echo $this->Html->link('Download', array('controller' => 'invoices', 'action' => 'download', $invoice['Invoice']['project_id']), array('class' => 'btn btn-default')); ?>
						<?php echo $this->Html->link('Edit', array('controller' => 'invoices', 'action' => 'edit', $invoice['Invoice']['id']), array('class' => 'btn btn-default')); ?>
						<?php if ($invoice['Invoice']['sent'] == null): ?>
							<?php echo $this->Html->link('Send', array('controller' => 'invoices', 'action' => 'send', $invoice['Invoice']['id']), array('class' => 'btn btn-primary')); ?>
						<?php else: ?>
							<?php echo $this->Html->link('Sent', array('controller' => 'invoices', 'action' => 'send', $invoice['Invoice']['id']), array('class' => 'btn btn-default')); ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</table>
		<div class="form-actions">
			<?php echo $this->Element('pagination'); ?>
		</div>
	</div>
</div>