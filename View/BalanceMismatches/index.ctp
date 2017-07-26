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
		<?php echo $this->Form->create('BalanceMismatch', array('type' => 'get', 'class' => 'filter', 'url' => array('action' => 'index'))); ?>
			<div class="padded separate-sections">
				<div class="row-fluid">
					<div class="filter">
						<?php echo $this->Form->input('user', array(
							'placeholder' => 'Email address or #userid',
							'value' => isset($this->request->query['user']) ? $this->request->query['user']: null
						)); ?>
					</div>
					<div class="filter date-group">
						<label>Logs between:</label>
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
				</div>	
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Search', array(
					'class' => 'btn btn-primary', 
					'onclick' => 'return Chart.searchFilter()'
				)); ?>
			</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>
<div class="box">
	<div class="box-header">
		<span class="title">Balance Mismatch Logs</span>
	</div>
	<div class="box-content">
		<table cellpadding="0" cellspacing="0" class="table table-normal">
			<thead>
				<tr>
					<td style="width: 150px;">Date (GMT)</td>
					<td>User</td>
					<td>Old / New Balance</td>
					<td>Old / New Pending</td>
					<td>Old / New Withdrawal</td>
					<td>Old / New Missing Points</td>
					<td>Last transaction_id</td>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($balance_mismatches as $balance_mismatch): ?>
					<tr>
						<td>
							<?php echo $this->Time->format($balance_mismatch['BalanceMismatch']['created'], Utils::dateFormatToStrftime(DB_DATETIME), false, $timezone); ?>
						</td>
						<td>
							<?php echo $this->Html->link($balance_mismatch['BalanceMismatch']['user_id'], array(
								'controller' => 'transactions',
								'action' => 'index',
								'?' => array('user' => '#'.$balance_mismatch['BalanceMismatch']['user_id'])
							), array(
								'target' => '_blank'
							)); ?>
						</td>
						<td>
							<?php echo $balance_mismatch['BalanceMismatch']['old_balance'].' / '.$balance_mismatch['BalanceMismatch']['new_balance'];?>
						</td>
						<td>
							<?php echo $balance_mismatch['BalanceMismatch']['old_pending'].' / '.$balance_mismatch['BalanceMismatch']['new_pending'];?>
						</td>
						<td>
							<?php echo $balance_mismatch['BalanceMismatch']['old_withdrawal'].' / '.$balance_mismatch['BalanceMismatch']['new_withdrawal'];?>
						</td>
						<td>
							<?php echo $balance_mismatch['BalanceMismatch']['old_missing_points'].' / '.$balance_mismatch['BalanceMismatch']['new_missing_points'];?>
						</td>
						<td>
							<?php echo (!empty($balance_mismatch['BalanceMismatch']['max_transaction_id'])) ? $balance_mismatch['BalanceMismatch']['max_transaction_id'] : '';?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>	
</div>

<?php echo $this->Element('pagination'); ?>