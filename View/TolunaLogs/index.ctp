<div class="row-fluid">
	<?php echo $this->element('nav_api_logs', array('nav' => 'toluna')); ?>
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
					<div class="filter">
						<?php
						echo $this->Form->input('country', array(
							'class' => 'uniform',
							'type' => 'select',
							'empty' => 'Select:',
							'label' => 'Country',
							'value' => isset($this->request->query['country']) ? $this->request->query['country'] : null,
							'options' => array('US' => 'US', 'CA' => 'CA', 'GB' => 'GB')
						));
						?>
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
				<td>Country</td>
				<td>Project Count</td>
				<td>Active Count</td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($toluna_logs as $toluna_log): ?>
				<tr>
					<td>
						<?php echo $this->Html->link(
							$this->Time->format($toluna_log['TolunaLog']['created'], Utils::dateFormatToStrftime(DB_DATETIME), false, $timezone),
							array('action' => 'view', $toluna_log['TolunaLog']['id'])
						); ?>
					</td>
					<td>
						#<?php echo $this->Html->link($toluna_log['TolunaLog']['user_id'], array('?' => array('user_id' => $toluna_log['TolunaLog']['user_id']))); ; ?>
					</td>
					<td>
						<?php echo $toluna_log['TolunaLog']['country']; ?>
					</td>
					<td>
						<?php echo $toluna_log['TolunaLog']['count']; ?>
					</td>
					<td>
						<?php echo $toluna_log['TolunaLog']['project_active_count']; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<?php echo $this->Element('pagination'); ?>