<div class="box inventory">
	<div class="box-header">
		<span class="title">Total Inventory Report</span>
	</div>
	<div class="box-content">
		<div class="padded">
			<?php echo $this->Form->create('Report', array('type' => 'get', 'class' => 'filter')); ?>
			<?php echo $this->Form->input('date', array(
				'label' => false, 
				'class' => 'datepicker',
				'data-date-autoclose' => true,
				'placeholder' => 'Date',
				'value' => isset($this->data['date']) ? $this->data['date']: date('m/d/Y')
			)); ?>
			<label>Generate Reports For:</label>
			<?php echo $this->Form->input('partner', array(
				'label' => false,
				'type' => 'select',
				'options' => $partners,
				'multiple' => 'checkbox',
				'div' => array(
					'class' => 'partners'
				),
				'default' => isset($this->data['partner']) ? $this->data['partner']: array()
			)); ?>
			<?php echo $this->Form->input('ssi', array(
				'type' => 'checkbox', 
				'label' => 'SSI',
				'checked' => isset($this->data['ssi']) ? $this->data['ssi'] : false
			)); ?>
			<?php echo $this->Form->input('mv_router', array(
				'type' => 'checkbox', 
				'label' => 'MintVine Router',
				'checked' => isset($this->data['mv_router']) ? $this->data['mv_router'] : false
			)); ?>
			<?php echo $this->Form->input('export', array(
				'type' => 'checkbox', 
				'label' => '<strong>Export data as CSV</strong>',
				'checked' => isset($this->data['export']) ? $this->data['export'] : false
			)); ?>
		</div>
		<div class="form-actions">	
			<?php echo $this->Form->submit('Generate report', array(
				'class' => 'btn btn-sm btn-primary',
				'disabled' => false
			)); ?>
			<?php echo $this->Form->end(null); ?>
		</div>
	</div>
</div>

<div class="row-fluid">
<?php if (isset($completes)): ?>
	<div class="span6">
		<div class="box">
			<table cellpadding="0" cellspacing="0" class="table table-normal table-fixed-header">
				<thead class="header">
					<tr>
						<td>Partner</td>
						<td>Date</td>
						<td>Min completes</td>
						<td>Max completes</td>
						<td>Difference</td>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($completes as $complete): ?>
						<tr>
							<td><?php echo (isset($partners[$complete['partner']])) ? $partners[$complete['partner']] : $complete['partner']; ?></td>
							<td><?php echo $complete['date']; ?></td>
							<td><?php echo $complete['min_completes']; ?></td>
							<td><?php echo $complete['max_completes']; ?></td>
							<td><?php echo $complete['max_completes'] - $complete['min_completes']; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
<?php endif; ?>

<?php if (isset($mv_router_inventory) || isset($ssi_inventory)): ?>
	<div class="span6">
		<div class="box" style="margin-bottom: 0;">
			<table cellpadding="0" cellspacing="0" class="table table-normal table-fixed-header">
				<thead class="header">
					<tr>
						<td>Partner</td>
						<td>Date</td>
						<td>Total Values</td>
					</tr>
				</thead>
				<tbody>
					<?php if (isset($mv_router_inventory)): ?>
						<tr>
							<td><?php echo $mv_router_inventory['partner']?></td>
							<td><?php echo $mv_router_inventory['date']; ?></td>
							<td><?php echo number_format($mv_router_inventory['total_values']); ?></td>
						</tr>
					<?php endif; ?>	
					<?php if (isset($ssi_inventory)): ?>
						<tr>
							<td><?php echo $ssi_inventory['partner']?></td>
							<td><?php echo $ssi_inventory['date']; ?></td>
							<td><?php echo number_format($ssi_inventory['total_values']); ?></td>
						</tr>
					<?php endif; ?>	
				</tbody>
			</table>
		</div>
	</div>
<?php endif; ?>
</div>