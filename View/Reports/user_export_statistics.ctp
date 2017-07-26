<p>
	<?php echo $this->Html->link('Unsuccessful User Exports', array('controller' => 'partner_logs', 'action' => 'unsuccessful'), array('class' => 'btn btn-danger')); ?>
</p>
<div class="box">
	<div class="box-header">
		<span class="title">Filters</span>
		<ul class="box-toolbar">
			<li>
				<?php echo $this->Html->link('<i class="icon-remove-sign"></i> Clear filters', array('action' => 'user_export_statistics'), array('escape' => false)); ?>
			</li>
		</ul>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create('Filter', array('type' => 'get', 'class' => 'filter')); ?>
		<div class="padded separate-sections">
			<div class="row-fluid">
				<div class="filter date-group">
					<label>Date</label>
					<?php
					echo $this->Form->input('date', array(
						'label' => false,
						'class' => 'datepicker',
						'data-date-autoclose' => true,
						'value' => isset($this->request->query['date']) ? $this->request->query['date'] : null
					));
					?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Show Statistics', array('class' => 'btn btn-primary')); ?>
		</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>
<?php if (!empty($statistics)): ?>
	<?php foreach ($statistics as $statistic): ?>
		<div class="box">
			<div class="box-header">
				<span class="title">
					Last <?php echo $statistic['UserExportStatistic']['days']; ?> day(s) user export statistics from  
					<?php if (isset($this->request->query['date']) && !empty($this->request->query['date'])): ?>
						<?php echo date('F jS, Y', strtotime($this->request->query['date'])); ?>
					<?php else: ?>
						<?php echo date('F jS, Y'); ?>
					<?php endif; ?>
				</span>
			</div>
			<div class="box-content">
				<table class="table table-normal">
					<tr>
						<th>New Registrations</th>
						<th>Extended Registrations</th>
						<?php foreach($groups as $group): ?>
							<th><?php echo $group; ?> <br /> Succeeded / Failed</th>
						<?php endforeach; ?>
					</tr>
						<tr>
							<td><?php echo  $statistic['UserExportStatistic']['registrations']; ?></td>
							<td><?php echo  $statistic['UserExportStatistic']['extended_registrations']; ?></td>
							<?php foreach($groups as $group): ?>
								<td><?php echo  $statistic['UserExportStatistic'][$group.'_success'] .' / '.$statistic['UserExportStatistic'][$group.'_failure'] ; ?></td>
							<?php endforeach; ?>
						</tr>
				</table>
			</div>
		</div>
	<?php endforeach; ?>
<?php else: ?>
	<div class="alert alert-danger">Statistics not found for the selected date.</div>
<?php endif; ?>