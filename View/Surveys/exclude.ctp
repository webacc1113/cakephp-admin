<div class="span6">
	<?php echo $this->Form->create('Survey', array('type' => 'file')); ?>
	<div class="box">
		<div class="box-header">
			<span class="title">Exclude Users</span>
		</div>
		<div class="box-content">
			<div class="padded">
				<?php echo $this->Form->input('user_ids', array(
					'label' => 'Exclude User IDS (one per line)',
					'type' => 'textarea',
					'style' => 'height: 104px',
				)); ?>
				<?php echo $this->Form->input('project_id', array(
					'label' => 'Exclude completed users from project',
					'type' => 'text',
					'after' => '<br/><small class="text-muted">Separate multiple projects with comma</small>'
				)); ?>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Generate Report', array('class' => 'btn btn-primary')); ?>
			</div>
		</div>
	</div>

	<?php echo $this->Form->end(); ?>
</div>
<div class="span6">
	<div class="box">
		<div class="box-header">
			<span class="title">Excluding Users</span>
		</div>
		<div class="box-content">
			<div class="padded">
				<p>This feature should be used if:</p>
				<ul>
					<li>a query has already been used to send a survey out to a user and you want to prevent those users from accessing this survey</li>
					<li>you have not created any queries and you want to have the same exclusion rules apply to all queries</li>
				</ul>
				<p>This feature works by populating a manual exclusion NQ for the listed users so it seems as if they've taken the survey already.</p>
			</div>
		</div>
	</div>
</div>
