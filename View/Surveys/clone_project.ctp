
<?php echo $this->Form->create('Survey'); ?>
<?php echo $this->Form->input('Project.id', array(
	'value' => $survey_record['Project']['id'],
	'type' => 'hidden'
)); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Clone Project</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="padded">
				<div class="msg">Following things will be cloned.</div>
				<ul>
					<li>Project Managers &amp; Account Managers</li>
					<li>Client</li>
					<li>Client Project ID</li>
					<li>Project Name, Survey Name</li>
					<li>Project Description</li>
					<li>Bid IR</li>
					<li>Client Survey Link</li>
					<li>Client Complete Action</li>
					<li>Client Rate, Partner Rate, User Payout</li>
					<li>NQ Award, Pooled Points</li>
					<li>Language, Country</li>
					<li>Misc. Settings</li>
					<li>Survey Length, Minimum Survey Time</li>
					<li>Survey Quota</li>
					<li>Invitation Subject Line</li>
					<li>Prescreeners</li>
				</ul>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Clone Project', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>