<h3>Withdrawal Results</h3>

<div class="pull-right">
	<?php echo $this->Form->create(false, array(
		'type' => 'get',
		'url' => array('controller' => 'withdrawals', 'action' => 'results', 'named' => array())
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
				'url' => array('controller' => 'withdrawals', 'action' => 'results', 'named' => array()),
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
				<td>Requested Date</td>
				<td>Processed Date</td>
				<td>Partner Response</td>
				<td></td>
			</tr>
		</thead>
		<?php if (!empty($withdrawals)): ?>
			<tbody>
				<?php foreach ($withdrawals as $withdrawal): ?>
					<tr>
						<td>
							<?php if ($withdrawal['Withdrawal']['status'] == WITHDRAWAL_PAYOUT_SUCCEEDED): ?>
								<span class="label label-success">#<?php echo $withdrawal['Withdrawal']['id']; ?></span>
							<?php elseif ($withdrawal['Withdrawal']['status'] == WITHDRAWAL_PAYOUT_FAILED): ?>
								<span class="label label-red">#<?php echo $withdrawal['Withdrawal']['id']; ?></span>
							<?php else: ?>
								#<?php echo $withdrawal['Withdrawal']['id']; ?>
							<?php endif; ?>
						</td>
						</td>
						<td><?php echo $withdrawal['User']['email']; ?> (<?php 
							echo $this->Html->link('#'.$withdrawal['User']['id'], array('controller' => 'users', 'action' => 'history', $withdrawal['User']['id'])); 
						?>)</td>
						<td><?php echo '$'.number_format(round(-1 * $withdrawal['Withdrawal']['amount_cents'] / 100, 2), 2); ?></td>
						<td>
							<?php if ($withdrawal['Withdrawal']['payment_type'] == 'paypal'): ?>
								PayPal: <?php echo $withdrawal['Withdrawal']['payment_identifier']; ?>
							<?php elseif ($withdrawal['Withdrawal']['payment_type'] == 'dwolla'): ?>
								Dwolla: <?php echo $withdrawal['Withdrawal']['payment_identifier']; ?>
							<?php elseif ($withdrawal['Withdrawal']['payment_type'] == 'dwolla_id'): ?>
								Dwolla: <?php echo $withdrawal['Withdrawal']['payment_identifier']; ?>
							<?php elseif ($withdrawal['Withdrawal']['payment_type'] == 'gift'): ?>
								Giftbit Card: <?php echo $withdrawal['Withdrawal']['payment_identifier']; ?>
							<?php elseif ($withdrawal['Withdrawal']['payment_type'] == 'tango'): ?>
								Tango Card: <?php echo $withdrawal['Withdrawal']['payment_identifier']; ?>
							<?php else: ?>
								Unknown
							<?php endif; ?>
						</td>
						<td><?php 
							echo $this->Time->format($withdrawal['Withdrawal']['created'], Utils::dateFormatToStrftime('Y-m-d h:i A'), false, $timezone); 
						?></td>
						<td><?php 
							echo $this->Time->format($withdrawal['Withdrawal']['processed'], Utils::dateFormatToStrftime('Y-m-d h:i A'), false, $timezone); 
						?></td>
						<td>
							<?php if (!empty($withdrawal['PaymentLog']['returned_info'])): ?>
								<?php 
									if ($withdrawal['PaymentLog']['partner_check'] === true) {
										$icon = '<i class="icon-ok"></i> ';
										$text = 'Success';
										$class = 'btn-success';
									}
									elseif ($withdrawal['PaymentLog']['partner_check'] === false) {
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
											'data-target' => '#modal-api-response-'.$withdrawal['PaymentLog']['id'],
											'data-toggle' => 'modal',
											'class' => 'btn btn-small '.$class,
											'escape' => false
										)
									); 
								?>
								<div id="modal-api-response-<?php echo $withdrawal['PaymentLog']['id']; ?>" class="modal hide">
									<div class="modal-header">
										<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
										<h6>API Response</h6>
									</div>
									<div class="modal-body">
										<?php db($withdrawal['PaymentLog']['returned_info']);?>
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
							<?php if ($withdrawal['Withdrawal']['status'] != WITHDRAWAL_PAYOUT_SUCCEEDED): ?>
								<?php echo $this->Html->link('Delete', '#', array('onclick' => 'return deleteWithdrawal(this, '.$withdrawal['Withdrawal']['id'].')', 'class' => 'btn btn-small btn-danger')); ?> 
								<?php echo $this->Html->link('Mark as Paid', '#', array('onclick' => 'return markWithdrawal(this, '.$withdrawal['Withdrawal']['id'].')', 'class' => 'btn btn-small btn-success')); ?> 
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
	function deleteWithdrawal(node, withdrawal_id) {
		if (confirm('Are you sure you wish to delete this withdrawal? It will return the points to the user')) {
			var $node = $(node);
			$.ajax({
				type: 'POST',
				data: 'type=delete&withdrawal_id=' + withdrawal_id,
				url: '/withdrawals/withdrawal/' + withdrawal_id,
				statusCode: {
					201: function(data) {
						$node.closest('tr').fadeOut();
					}
				}
			});
		}		
		return false;
	}
	
	function markWithdrawal(node, withdrawal_id) {
		if (confirm('Are you sure you wish to mark this withdrawal as paid? Doing so will no longer attempt to pay this user out.')) {
			var $node = $(node);
			$.ajax({
				type: 'POST',
				data: 'type=mark&withdrawal_id=' + withdrawal_id,
				url: '/withdrawals/withdrawal/' + withdrawal_id,
				statusCode: {
					201: function(data) {
						$node.closest('a').fadeOut();
						$node.closest('tr').children('td:first').html('<span class="label label-success">#' + withdrawal_id + '</span>');
					}
				}
			});
		}
		return false;
	}
</script>