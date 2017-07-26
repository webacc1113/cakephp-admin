
<?php echo $this->Form->create('Survey', array('type' => 'file')); ?>
<?php echo $this->Form->input('Project.id', array(
	'value' => $project['Project']['id'],
	'type' => 'hidden'
)); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Reset Project</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="padded">
				<div class="alert alert-error">Warning: This deletes all traffic data associated with a project and should only be used to reset 
					a project when testing.</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Reset Data', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>