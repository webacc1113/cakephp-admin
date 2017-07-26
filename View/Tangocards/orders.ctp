<h3>Tangocard orders (api)</h3>
<div class="box">
	<div class="box-header">
		<span class="title">Filters</span>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create('Tangocard', array('type' => 'get', 'class' => 'filter')); ?>
			<div class="padded separate-sections">					
				<div class="row-fluid">
					<div class="filter date-group">
						<label>Date between</label> 
						<?php echo $this->Form->input('date_from', array(
							'label' => false, 
							'class' => 'datepicker',
							'data-date-autoclose' => true,
							'placeholder' => 'Start date',
							'value' => isset($this->request->query['date_from']) ? $this->request->query['date_from']: date('m/d/Y', mktime(0, 0, 0, date('m'), 1, date('Y')))
						)); ?> 
						<?php echo $this->Form->input('date_to', array(
							'label' => false, 
							'class' => 'datepicker',
							'data-date-autoclose' => true,
							'placeholder' => 'End date',
							'value' => isset($this->request->query['date_to']) ? $this->request->query['date_to']: date('m/d/Y', mktime(0, 0, 0, date('m'), date('d'), date('Y')))
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
<?php if (!empty($orders['orders'])): ?>
	<div class="box">
		<table cellpadding="0" cellspacing="0" class="table table-normal">
			<thead>
				<tr>
					<td>First Sent</td>
					<td>Recipient</td>
					<td>SKU</td>
					<td>Amount(cents)</td>
					<td></td>
					<td></td>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($orders['orders'] as $order): ?>
					<tr>
						<td><?php echo $order['delivered_at']?></td>
						<td><?php echo $order['recipient']['name'] . ' ('.$order['recipient']['email'].')'?></td>
						<td><?php echo $order['sku']?></td>
						<td><?php echo $order['amount_charged']['currency_code'] . ' ' . $order['amount_charged']['value'] ?></td>
						<td>
							<?php echo $this->Html->link('Resend info', array(
								'action' => 'ajax_resend_info',
								$order['order_id']
							),
							array(
								'class' => 'btn btn-default',
								'data-target' => '#modal-resend-info',
								'data-toggle' => 'modal', 
							)); ?>
						</td>
						<td><?php echo $this->Html->link('Resend reward email', array(
								'action' => 'resend_reward',
								$order['order_id']
							),
							array(
								'class' => 'btn btn-danger'
							)); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php if ($total_pages > 1): ?>
		<div class="pagination">
			<ul>
				<?php for ($i = 1; $i <= $total_pages; $i++): ?> 
					<li class="<?php echo (isset($this->request->query['page']) && $this->request->query['page'] == $i) ? 'active' : ''; ?>">
						<?php echo $this->Html->link($i, 
							array(
								'controller' => 'tangocards',
								'action' => 'orders',
								'?' => array(
									'page' => $i,
									'date_from' => isset($this->request->query['date_from']) ? $this->request->query['date_from'] : '',
									'date_to' => isset($this->request->query['date_to']) ? $this->request->query['date_to'] : '',
								)
							)
						); ?>
					</li>
				<?php endfor; ?>
			</ul>
		</div>
	<?php endif; ?>
	<div id="modal-resend-info" class="modal hide">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
			<h6 id="modal-tablesLabel">Resend info</h6>
		</div>
		<div class="modal-body">
		</div>
		<div class="modal-footer">
			<button class="btn btn-default" data-dismiss="modal">Close</button> 
		</div>
	</div>
<?php endif; ?>