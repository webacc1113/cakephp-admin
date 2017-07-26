<style type="text/css">
	span.label {
		font-weight: normal;
		text-transform: uppercase;
	}
	table td.id {
		width: 20px;
	}
	table tr.closed {
		color: #999;
	}
</style>

<h3>Payment Logs</h3>
<div class="row-fluid">
	<div class="span12"><?php
		echo $this->Html->link('All Logs', array(
			'action' => 'index',
			'?' => array(
				'status' => 'all'
			)
		), 
		array(
			'class' => 'btn btn-' . ($status_filter == 'all' ? 'primary' : 'default')
		));
		?> <?php
		echo $this->Html->link('Successful Logs', array(
			'action' => 'index',
			'?' => array(
				'status' => PAYMENT_LOG_SUCCESSFUL
			)
		), 
		array(
			'class' => 'btn btn-' . ($status_filter == PAYMENT_LOG_SUCCESSFUL ? 'primary' : 'default')
		));
		?> <?php
		echo $this->Html->link('Failed Logs', array(
			'action' => 'index',
			'?' => array(
				'status' => PAYMENT_LOG_FAILED
			)
		), 
		array(
			'class' => 'btn btn-' . ($status_filter == PAYMENT_LOG_FAILED ? 'primary' : 'default')
		));
		?> <?php
		echo $this->Html->link('Aborted Logs', array(
			'action' => 'index',
			'?' => array(
				'status' => PAYMENT_LOG_ABORTED
			)
		), 
		array(
			'class' => 'btn btn-' . ($status_filter == PAYMENT_LOG_ABORTED ? 'primary' : 'default')
		));
		?>
	</div>
</div>
<p>&nbsp;</p>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td><?php echo $this->Paginator->sort('username'); ?></td>
				<td><?php echo $this->Paginator->sort('user_email'); ?></td>
				<td><?php echo $this->Paginator->sort('transaction_name', 'Payment Type'); ?></td>
				<td><?php echo $this->Paginator->sort('transaction_amount', 'Amount'); ?></td>
				<td><?php echo $this->Paginator->sort('transaction_created', 'User submission'); ?></td>
				<td><?php echo $this->Paginator->sort('transaction_executed', 'Approved'); ?></td>
				<td><?php echo $this->Paginator->sort('processed'); ?></td>
				<td>Status</td>
				<td>API Response</td>
				<td><?php echo $this->Paginator->sort('created'); ?></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($logs as $log): ?>
				<tr>
					<td>
						<?php echo $this->Html->link($log['PaymentLog']['username'], array(
							'controller' => 'transactions',
							'action' => 'index',
							'?' => array('transaction_id' => $log['PaymentLog']['transaction_id'])
						));	?>
					</td>
					<td>
						<?php echo $log['PaymentLog']['user_email']; ?>
					</td>
					<td>
						<?php echo $log['PaymentLog']['transaction_name']; ?>
					</td>
					<td>
						<?php echo $log['PaymentLog']['transaction_amount']; ?>
					</td>
					<td>
						<?php echo $this->Time->format($log['PaymentLog']['transaction_created'], Utils::dateFormatToStrftime('M jS, Y h:i A'), false, $timezone); ?>
					</td>
					<td>
						<?php echo $this->Time->format($log['PaymentLog']['transaction_executed'], Utils::dateFormatToStrftime('M jS, Y h:i A'), false, $timezone); ?>
					</td>
					<td>
						<?php echo ($log['PaymentLog']['processed']) ? $this->Time->format($log['PaymentLog']['processed'], Utils::dateFormatToStrftime('M jS, Y h:i A'), false, $timezone) : '<span class="muted">-</span>'; ?>
					</td>
					<td>
						<?php if ($log['PaymentLog']['status'] == PAYMENT_LOG_SUCCESSFUL) :?>
							<span class="label label-success">Successful</span>
						<?php elseif($log['PaymentLog']['status'] == PAYMENT_LOG_FAILED): ?>
							<span class="label label-red">Failed</span>
						<?php elseif($log['PaymentLog']['status'] == PAYMENT_LOG_ABORTED): ?>
							<span class="label label-warning">Aborted</span>
						<?php elseif($log['PaymentLog']['status'] == PAYMENT_LOG_STARTED): ?>
							<span class="label label-info">Started</span>
						<?php endif; ?>	
					</td>
					<td>
						<?php if (!empty($log['PaymentLog']['returned_info'])): 
							echo $this->Html->link('View', 
								'#', 
								array(
									'data-target' => '#modal-api-response-'.$log['PaymentLog']['id'],
									'data-toggle' => 'modal',
									'class' => 'btn btn-default'
								)); ?>
								<div id="modal-api-response-<?php echo $log['PaymentLog']['id']; ?>" class="modal hide">
									<div class="modal-header">
										<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
										<h6>API Response</h6>
									</div>
									<div class="modal-body">
										<?php print_r($log['PaymentLog']['returned_info']);?>
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
						<?php echo $this->Time->format($log['PaymentLog']['created'], Utils::dateFormatToStrftime('M jS, Y h:i:s A'), false, $timezone); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<p class="count">Showing <?php
	echo number_format($this->Paginator->counter(array('format' => '{:current}')));
	?> of <?php
	echo number_format($this->Paginator->counter(array('format' => '{:count}')));
	?> matches
</p>
<?php echo $this->Element('pagination'); ?>