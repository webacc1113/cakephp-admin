<h3>Withdrawals Requested</h3>
<div class="box">
	<div class="box-header">
		<span class="title">Filters</span>
		<ul class="box-toolbar">
			<li>
				<?php echo $this->Html->link('<i class="icon-remove-sign"></i> Clear filters', array('action' => 'requested'), array('escape' => false)); ?>
			</li>
		</ul>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create('Withdrawal', array('type' => 'get', 'class' => 'filter')); ?>
			<div class="padded separate-sections">
				<div class="row-fluid">
					<div class="filter date-group">
						<label>Withdrawal date between:</label>
						<?php echo $this->Form->input('date_from', array(
							'label' => false, 
							'class' => 'datepicker',
							'data-date-autoclose' => true,
							'placeholder' => 'Start date',
							'value' => isset($this->data['date_from']) ? $this->data['date_from']: null
						)); ?> 
						<?php echo $this->Form->input('date_to', array(
							'label' => false, 
							'class' => 'datepicker',
							'placeholder' => 'End date',
							'data-date-autoclose' => true,
							'value' => isset($this->data['date_to']) ? $this->data['date_to']: null
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
<div class="row-fluid">
	<div class="span6">
		<?php if (isset($data) && !empty($data)): ?>
			<div class="box">
				<table cellpadding="0" cellspacing="0" class="table table-normal">
					<thead>
						<tr>
							<td>Date</td>
							<td>Paypal</td>
							<td>Gift Card</td>
							<td>Dwolla</td>
							<td><b>Total</b></td>
						</tr>
					</thead>
					<tbody>
						<?php $paypal_total = $gift_total = $dwolla_total = $grand_total = 0; ?>
						<?php foreach ($data as $date => $row): ?>
							<tr>
								<td><?php echo $date; ?></td>
								<td>
									<?php $paypal = (isset($row['paypal'])) ? $row['paypal'] : 0; ?>
									$<?php echo number_format($paypal / 100, 2); ?>
								</td>
								<td>
									<?php $gift = (isset($row['tango'])) ? $row['tango'] : 0; ?>
									$<?php echo number_format($gift / 100, 2); ?>
								</td>
								<td>
									<?php $dwolla = (isset($row['dwolla'])) ? $row['dwolla'] : 0; ?>
									$<?php echo number_format($dwolla / 100, 2); ?>
								</td>
								<td>
									<?php $sub_total = $paypal + $gift + $dwolla; ?>
									$<?php echo number_format($sub_total / 100, 2); ?>
								</td>
							</tr>
							<?php $paypal_total += $paypal; ?>
							<?php $gift_total += $gift; ?>
							<?php $dwolla_total += $dwolla; ?>
							<?php $grand_total += $sub_total; ?>
						<?php endforeach; ?>
						<tr>
							<td><b>Total</b></td>
							<td>$<?php echo number_format($paypal_total / 100, 2); ?></td>
							<td>$<?php echo number_format($gift_total / 100, 2); ?></td>
							<td>$<?php echo number_format($dwolla_total / 100, 2); ?></td>
							<td>$<?php echo number_format($grand_total / 100, 2); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		<?php else: ?>
			<div class="alert alert-danger">Records not found!</div>
		<?php endif; ?>
	</div>
</div>