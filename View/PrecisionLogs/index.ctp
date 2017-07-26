<div class="row-fluid">
	<?php echo $this->element('nav_api_logs', array('nav' => 'precision')); ?>
</div>
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
		<?php echo $this->Form->create(null, array('type' => 'get', 'class' => 'filter')); ?>
			<div class="padded separate-sections">
				<div class="row-fluid">
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
				<?php echo $this->Form->submit('Search', array('class' => 'btn btn-primary')); ?>
			</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td style="width: 150px;">Date (GMT)</td>
				<td>User ID</td>
				<td>Project Count</td>
				<td>Active Count</td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($precision_logs as $precision_log): ?>
				<tr>
					<td>
						<?php echo $this->Html->link(
							$this->Time->format($precision_log['PrecisionLog']['created'], Utils::dateFormatToStrftime(DB_DATETIME), false, $timezone),
							array('action' => 'view', $precision_log['PrecisionLog']['id'])
						); ?>
					</td>
					<td>
						#<?php echo $this->Html->link($precision_log['PrecisionLog']['user_id'], array('?' => array('user_id' => $precision_log['PrecisionLog']['user_id']))); ; ?>
					</td>
					<td>
						<?php echo $precision_log['PrecisionLog']['count']; ?>
					</td>
					<td>
						<?php echo $precision_log['PrecisionLog']['project_active_count']; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<?php echo $this->Element('pagination'); ?>