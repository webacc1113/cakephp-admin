<div class="pull-right">
	<?php echo $this->Form->create(false, array(
		'type' => 'get',
		'url' => array('controller' => 'transactions', 'action' => 'withdrawals', 'named' => array())
	)); ?>
	<?php echo $this->Form->input('q', array(
		'placeholder' => 'Search',
		'label' => false,
		'value' => isset($this->request->query['q']) ? $this->request->query['q']: null,
		'div' => false,
		'name' => 'q'
	)); ?>
	<?php if (isset($this->request->query['method']) && !empty($this->request->query['method'])): ?>
		<?php echo $this->Form->input('method', array(
			'type' => 'hidden',
			'value' => $this->request->query['method']
		)); ?>
	<?php endif; ?>
	<?php if (isset($this->request->query['type']) && !empty($this->request->query['type'])): ?>
		<?php echo $this->Form->input('type', array(
			'type' => 'hidden',
			'value' => $this->request->query['type']
		)); ?>
	<?php endif; ?>
	<?php echo $this->Form->end(null); ?>
</div>
<div class="row-fluid">
	<div class="span3">
		<?php 
			$base_queries = array();
			if (isset($this->request->query['method']) && !empty($this->request->query['method'])) {
				$base_queries['method'] = $this->request->query['method'];
			}
			if (isset($this->request->query['q']) && !empty($this->request->query['q'])) {
				$base_queries['q'] = $this->request->query['q'];
			}
		?>
		<?php echo $this->Html->link('All', array('?' => array_merge($base_queries, array('type' => 'all'))), array(
			'class' => 'btn '.($type == 'all' ? 'btn-primary': 'btn-default')
		)); ?> 
		<?php echo $this->Html->link('Unprocessed', array('?' => array_merge($base_queries, array('type' => 'unprocessed'))), array(
			'class' => 'btn '.($type == 'unprocessed' ? 'btn-primary': 'btn-default')
		)); ?> 
		<?php echo $this->Html->link('Failed', array('?' => array_merge($base_queries, array('type' => 'failed'))), array(
			'class' => 'btn '.($type == 'failed' ? 'btn-primary': 'btn-default')
		)); ?> 
		<?php echo $this->Html->link('Succeeded', array('?' => array_merge($base_queries, array('type' => 'succeeded'))), array(
			'class' => 'btn '.($type == 'succeeded' ? 'btn-primary': 'btn-default')
		)); ?>
	</div>
	<div class="span3">
		<div class="form-group">
			<?php echo $this->Form->create(false, array(
				'type' => 'get',
				'url' => array('controller' => 'transactions', 'action' => 'withdrawals', 'named' => array()),
				'class' => 'clearfix form-inline'
			)); ?>
			<?php echo $this->Form->input('method', array(
				'type' => 'select',
				'options' => $payment_methods,
				'value' => isset($this->request->query['method']) ? $this->request->query['method']: null,
				'label' => false,
				'empty' => 'Payment Methods (All)',
			)); ?>
			<?php if (isset($this->request->query['q']) && !empty($this->request->query['q'])): ?>
				<?php echo $this->Form->input('q', array(
					'type' => 'hidden',
					'value' => $this->request->query['q']
				)); ?>
			<?php endif; ?>
			<?php if (isset($this->request->query['type']) && !empty($this->request->query['type'])): ?>
				<?php echo $this->Form->input('type', array(
					'type' => 'hidden',
					'value' => $this->request->query['type']
				)); ?>
			<?php endif; ?>
			<?php echo $this->Form->submit('Filter', array('class' => 'btn btn-default')); ?>
		<?php echo $this->Form->end(null); ?>
	</div>
	</div>
</div>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>ID</td>
				<td>User</td>
				<td>Amount</td>
				<td>Payment By</td>
				<td>Withdrawal Date</td>
				<td>Execution Date</td>
				<td>Partner Response</td>
				<td></td>
			</tr>
		</thead>
		<?php if (!empty($transactions)): ?>
			<tbody>
				<?php foreach ($transactions as $transaction): ?>
					<tr>
						<td>
							<?php if ($transaction['Transaction']['payout_processed'] == PAYOUT_SUCCEEDED): ?>
								<span class="label label-success">#<?php echo $transaction['Transaction']['id']; ?></span>
							<?php elseif ($transaction['Transaction']['payout_processed'] == PAYOUT_FAILED): ?>
								<span class="label label-red">#<?php echo $transaction['Transaction']['id']; ?></span>
							<?php else: ?>
								#<?php echo $transaction['Transaction']['id']; ?>
							<?php endif; ?>
						</td>
						</td>
						<td><?php echo $transaction['User']['email']; ?> (<?php 
							echo $this->Html->link('#'.$transaction['User']['id'], array('controller' => 'users', 'action' => 'history', $transaction['User']['id'])); 
						?>)</td>
						<td><?php echo '$'.number_format(round(-1 * $transaction['Transaction']['amount'] / 100, 2), 2); ?></td>
						<td>
							<?php if (!empty($transaction['Transaction']['linked_to_id'])) : ?>
								<?php if ($transaction['PaymentMethod']['payment_method'] == 'paypal'): ?>
									PayPal: <?php echo $transaction['PaymentMethod']['value']; ?>
								<?php elseif ($transaction['PaymentMethod']['payment_method'] == 'dwolla'): ?>
									Dwolla: <?php echo $transaction['PaymentMethod']['payment_id']; ?>
								<?php elseif ($transaction['PaymentMethod']['payment_method'] == 'dwolla_id'): ?>
									Dwolla: <?php echo $transaction['PaymentMethod']['value']; ?>
								<?php elseif ($transaction['PaymentMethod']['payment_method'] == 'gift'): ?>
									Giftbit Card: <?php echo $transaction['PaymentMethod']['value']; ?>
								<?php elseif ($transaction['PaymentMethod']['payment_method'] == 'tango'): ?>
									Tango Card: <?php echo $transaction['PaymentMethod']['value']; ?>
								<?php elseif ($transaction['PaymentMethod']['payment_method'] == 'mvpay'): ?>
									MVPay: <?php echo $transaction['PaymentMethod']['value']; ?>
								<?php else: ?>
									Unknown
								<?php endif; ?>
							<?php else: ?>
								<?php echo $transaction['Transaction']['name']; ?>
							<?php endif; ?>
						</td>
						<td><?php 
							echo $this->Time->format($transaction['Transaction']['created'], Utils::dateFormatToStrftime('Y-m-d h:i A'), false, $timezone); 
						?></td>
						<td><?php 
							echo $this->Time->format($transaction['Transaction']['executed'], Utils::dateFormatToStrftime('Y-m-d h:i A'), false, $timezone); 
						?></td>
						<td>
							<?php if (!empty($transaction['PaymentLog']['returned_info'])): ?>
								<?php 
									if ($transaction['PaymentLog']['partner_check'] === true) {
										$icon = '<i class="icon-ok"></i> ';
										$text = 'Success';
										$class = 'btn-success';
									}
									elseif ($transaction['PaymentLog']['partner_check'] === false) {
										$icon = '<i class="icon-remove"></i> ';
										$text = 'Failed';
										$class = 'btn-danger';
									}
									else {
										$icon = '';
										$class = 'btn-default';
										$text = 'View';
									}
									echo $this->Html->link($icon.$text, 
										'#', 
										array(
											'data-target' => '#modal-api-response-'.$transaction['PaymentLog']['id'],
											'data-toggle' => 'modal',
											'class' => 'btn btn-small '.$class,
											'escape' => false
										)
									); 
								?>
								<div id="modal-api-response-<?php echo $transaction['PaymentLog']['id']; ?>" class="modal hide">
									<div class="modal-header">
										<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
										<h6>API Response</h6>
									</div>
									<div class="modal-body">
										<?php db($transaction['PaymentLog']['returned_info']);?>
									</div>
									<div class="modal-footer">
										<button class="btn btn-default" data-dismiss="modal">Close</button>
									</div>
								</div>
							<?php else: ?> 	
								<span class="muted">-</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ($transaction['Transaction']['payout_processed'] == PAYOUT_FAILED): ?>
								<?php echo $this->Html->link('Delete', '#', array('onclick' => 'return deleteTransaction(this, '.$transaction['Transaction']['id'].')', 'class' => 'btn btn-small btn-danger')); ?> 
								<?php echo $this->Html->link('Mark as Paid', '#', array('onclick' => 'return markTransaction(this, '.$transaction['Transaction']['id'].')', 'class' => 'btn btn-small btn-success')); ?> 
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		<?php else: ?>
			<tbody>
				<tr>
					<td colspan="8">
						<span class="muted">There were no transactions that matched your query.</span>
					</td>
				</tr>
			</tbody>
		<?php endif; ?>
	</table>
</div>
<p><span class="muted">This view only shows the last 90 days worth of withdrawals</span></p>

<script type="text/javascript">
	function deleteTransaction(node, transaction_id) {
		if (confirm('Are you sure you wish to delete this transaction? It will return the points to the user')) {
			var $node = $(node);
			$.ajax({
				type: 'POST',
				data: 'type=delete&transaction_id=' + transaction_id,
				url: '/transactions/withdrawals/' + transaction_id,
				statusCode: {
					201: function(data) {
						$node.closest('tr').fadeOut();
					}
				}
			});
		}		
		return false;
	}
	
	function markTransaction(node, transaction_id) {
		if (confirm('Are you sure you wish to mark this transaction as paid? Doing so will no longer attempt to pay this user out.')) {
			var $node = $(node);
			$.ajax({
				type: 'POST',
				data: 'type=mark&transaction_id=' + transaction_id,
				url: '/transactions/withdrawals/' + transaction_id,
				statusCode: {
					201: function(data) {
						$node.closest('tr').fadeOut();
					}
				}
			});
		}
		return false;
	}
</script>