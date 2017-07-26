<h3>Percentage of Sampling</h3>

<div class="box">
	<div class="box-header">
		<span class="title">Filters</span>
		<ul class="box-toolbar">
			<li>
				<?php echo $this->Html->link('<i class="icon-remove-sign"></i> Clear filters', array('action' => 'sampling_percentage'), array('escape' => false)); ?>
			</li>
		</ul>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create('Filter', array('type' => 'get', 'class' => 'filter')); ?>
			<div class="padded separate-sections">
				<div class="row-fluid">
					<div class="filter date-group">
						<label>Report date between:</label>
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

<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Open projects from sampling</td>
				<td>Open projects (non sampling)</td>
			</tr>
		</thead>
		<tbody>
			<?php if ($total): ?>
				<tr>
					<td><?php echo ($count_sampled_projects) ? $count_sampled_projects : '0'; ?> out of <?php echo $total; ?> (<?php echo ($count_sampled_projects * 100) / $total; ?>%)</td>
					<td><?php echo $count_non_sampled_projects; ?> out of <?php echo $total; ?> (<?php echo ($count_non_sampled_projects * 100) / $total; ?>%)</td>
				</tr>
			<?php else: ?>
				<tr>
					<td colspan="2">Open projects not found in the selected date range.</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>
</div>
<?php if (!empty($projects)) : ?>
	<h3>Sampled Projects</h3>
	<div class="box">
		<table cellpadding="0" cellspacing="0" class="table table-normal">
			<thead>
				<tr>
					<td><?php echo __('Project Name');?></td>
					<td><?php echo __('Clicks');?></td>
					<td><?php echo __('Completes');?></td>
					<td><?php echo __('NQs');?></td>
					<td><?php echo __('OQs');?></td>
					<td><?php echo __('NQ-S');?></td>
					<td><?php echo __('NQ-F');?></td>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($projects as $project) : ?>
					<tr>
						<td><?php echo $project['Project']['survey_name']; ?></td>
						<td><?php echo number_format($project['SurveyVisitCache']['click']); ?></td>
						<td><?php echo number_format($project['SurveyVisitCache']['complete']); ?></td>
						<td><?php echo number_format($project['SurveyVisitCache']['nq']); ?></td>
						<td><?php echo number_format($project['SurveyVisitCache']['overquota']); ?></td>
						<td><?php echo number_format($project['SurveyVisitCache']['speed']); ?></td>
						<td><?php echo number_format($project['SurveyVisitCache']['fraud']); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>