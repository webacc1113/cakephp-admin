<h3>Tangocard Orders (local)</h3>
<div class="box">
	<div class="box-header">
		<span class="title">Filters</span>
		<ul class="box-toolbar">
			<li>
				<?php echo $this->Html->link('<i class="icon-remove-sign"></i> Clear filters', array('action' => 'index'), array('escape' => false)); ?>
			</li>
		</ul>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create('TangocardOrder', array('type' => 'get', 'class' => 'filter')); ?>
			<div class="padded separate-sections">					
				<div class="row-fluid">
					<div class="filter date-group">
						<label>Date between</label> 
						<?php echo $this->Form->input('date_from', array(
							'label' => false, 
							'class' => 'datepicker',
							'data-date-autoclose' => true,
							'placeholder' => 'Start date',
							'value' => isset($this->request->query['date_from']) ? $this->request->query['date_from']: null
						)); ?> 
						<?php echo $this->Form->input('date_to', array(
							'label' => false, 
							'class' => 'datepicker',
							'data-date-autoclose' => true,
							'placeholder' => 'End date',
							'value' => isset($this->request->query['date_to']) ? $this->request->query['date_to']: null
						)); ?>
					</div>
					<div class="filter">
						<?php echo $this->Form->input('user_id', array(
							'type' => 'text', 
							'label' => 'User id',
							'value' => isset($this->data['user_id']) ? $this->data['user_id']: null,
						)); ?>
					</div>
					<div class="filter">
						<?php echo $this->Form->input('email', array(
							'label' => 'User email',
							'value' => isset($this->data['email']) ? $this->data['email']: null,
						)); ?>
					</div>
					<div class="filter">
						<?php echo $this->Form->input('transaction_id', array(
							'type' => 'text',
							'label' => 'Transaction id',
							'value' => isset($this->data['transaction_id']) ? $this->data['transaction_id']: null,
						)); ?>
					</div>
				</div>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Search', array('class' => 'btn btn-primary')); ?>
			</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>First sent</td>
				<td><?php echo $this->Paginator->sort('order_id', 'Order id'); ?></td>
				<td><?php echo $this->Paginator->sort('transaction_id', 'Transaction id'); ?></td>
				<td><?php echo $this->Paginator->sort('user_id', 'User id'); ?></td>
				<td><?php echo $this->Paginator->sort('recipient_email', 'Recipient'); ?></td>
				<td><?php echo $this->Paginator->sort('sku'); ?></td>
				<td><?php echo $this->Paginator->sort('amount', 'Amount charged'); ?></td>
				<td><?php echo $this->Paginator->sort('denomination'); ?></td>
				<td>Sent by</td>
				<td><?php echo $this->Paginator->sort('last_resend'); ?></td>
				<td><?php echo $this->Paginator->sort('resend_count'); ?></td>
				<td>Response</td>
				<td></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($orders as $order): ?>
				<tr>
					<td><?php echo ($order['TangocardOrder']['sent_mv']) ? $this->Time->format('F jS, Y h:i A', strtotime($order['TangocardOrder']['first_send']), false, $timezone) : $order['TangocardOrder']['delivered_at'] ;  ?></td>
					<td><?php echo $order['TangocardOrder']['order_id']; ?></td>
					<td><?php echo $order['TangocardOrder']['transaction_id']; ?></td>
					<td><?php echo $order['TangocardOrder']['user_id']; ?></td>
					<td><?php echo $order['TangocardOrder']['recipient_name'] . ' ('.$order['TangocardOrder']['recipient_email'].')'?></td>
					<td><?php echo $order['TangocardOrder']['sku']; ?></td>
					<td>USD <?php echo round($order['TangocardOrder']['amount'] / 100, 2); ?></td>
					<td><?php echo $order['TangocardOrder']['denomination']; ?></td>
					<td><?php echo ($order['TangocardOrder']['sent_mv']) ? 'MV' : 'Tango' ?></td>
					<td><?php echo ($order['TangocardOrder']['last_resend']) ? $this->Time->format('F jS, Y h:i A', strtotime($order['TangocardOrder']['last_resend']), false, $timezone) : '-'; ?></td>
					<td><?php echo $order['TangocardOrder']['resend_count']; ?></td>
					<td>
						<?php echo $this->Html->link('View', 
						'#', 
						array(
							'data-target' => '#modal-response-'.$order['TangocardOrder']['id'],
							'data-toggle' => 'modal',
							'class' => 'btn btn-default'
						)); ?>
						<div id="modal-response-<?php echo $order['TangocardOrder']['id']; ?>" class="modal hide">
							<div class="modal-header">
								<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
								<h6>Response</h6>
							</div>
							<div class="modal-body">
								<?php var_dump(json_decode($order['TangocardOrder']['response'], true));?>
							</div>
							<div class="modal-footer">
								<button class="btn btn-default" data-dismiss="modal">Close</button>
							</div>
						</div>
					</td>
					<td>
						<?php if ($order['TangocardOrder']['sent_mv']): ?> 
							<?php echo $this->Html->link('Resend', array(
								'controller' => 'tangocard_orders',
								'action' => 'resend_reward_email',
								$order['TangocardOrder']['order_id']
							),
							array(
								'class' => 'btn btn-danger'
							)); ?>
						<?php else: ?>
							<?php echo $this->Html->link('Resend', array(
								'controller' => 'tangocards',
								'action' => 'resend_reward',
								$order['TangocardOrder']['order_id']
							),
							array(
								'class' => 'btn btn-danger'
							)); ?>
						<?php endif; ?> 
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php echo $this->Element('pagination'); ?>