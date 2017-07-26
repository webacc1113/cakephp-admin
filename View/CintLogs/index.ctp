<div class="row-fluid">
	<?php echo $this->element('nav_api_logs', array('nav' => 'cint')); ?>
</div>
<div class="row-fluid">
	<div class="span6">
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
								<?php echo $this->Form->input('country', array(
									'class' => 'uniform',
									'type' => 'select', 
									'empty' => 'Select:',
									'label' => 'Country',
									'value' => isset($this->request->query['country']) ? $this->request->query['country']: null,
									'options' => array('US' => 'US', 'CA' => 'CA', 'GB' => 'GB')
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
	</div>
	<div class="span6">
		<?php
		echo $this->Form->create(null, array(
			'class' => 'clearfix form-inline',
			'type' => 'get',
			'url' => array(
				'controller' => 'cint_logs',
				'action' => 'compare'
			)
		));
		?>
		<div class="box">
			<div class="box-header">
				<span class="title">Compare</span>
				<ul class="box-toolbar">
					<li>
						<?php echo $this->Html->link('<i class="icon-remove-sign"></i> Clear filters', array('action' => 'index'), array('escape' => false)); ?>
					</li>
				</ul>
			</div>
			<div class="box-content">
				<div class="padded separate-sections">
					<div class="row-fluid">
						<div class="form-group">
							<?php echo $this->Form->input('from', array(
								'label' => false,
								'placeholder' => 'Compare Run #',
								'value' => isset($this->request->query['from']) ? $this->request->query['from']: null
								)); ?> 
								
							<?php echo $this->Form->input('to', array(
								'label' => false,
								'placeholder' => 'to Compare Run #',
								'value' => isset($this->request->query['to']) ? $this->request->query['to']: null
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
	</div>	
</div>

<?php
echo $this->Form->create(null, array(
	'class' => 'clearfix form-inline',
	'url' => array(
		'controller' => 'cint_logs',
		'action' => 'index'
	)
));
?>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td style="width: 10px;"></td>
				<td style="width: 80px;">Run #</td>
				<td>Date (GMT)</td>
				<td>Project Quotas</td>
				<td>Total Completes Available</td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($cint_logs as $cint_log): ?>
				<tr>
					<td><?php echo $this->Form->input('compare.'.$cint_log['CintLog']['run'], array(
						'type' => 'checkbox', 
						'label' => false
					)); ?></td>
					<td><?php echo $cint_log['CintLog']['country']; ?> <?php echo $this->Html->link('#'.$cint_log['CintLog']['run'], array(
						'action' => 'run',
						$cint_log['CintLog']['id']
					)); ?></td>
					<td>
						<?php echo $this->Time->format($cint_log['CintLog']['created'], Utils::dateFormatToStrftime(DB_DATETIME), false, $timezone); ?>
					</td>
					<td><?php echo number_format($cint_log['CintLog']['count']); ?></td>
					<td><?php echo number_format($cint_log['CintLog']['quota']); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php echo $this->Form->submit('Compare (Choose only 2)', array('class' => 'btn btn-primary')); ?>
<?php echo $this->Form->end(null); ?>

<?php echo $this->Element('pagination'); ?>