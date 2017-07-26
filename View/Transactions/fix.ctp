<div class="span6">
	<?php echo $this->Form->create(); ?>
	<div class="box">
		<div class="box-header">
			<span class="title">Fix Project Points</span>
		</div>
		<div class="box-content">
			<div class="row-fluid">
				<div class="padded">
					<?php echo $this->Form->input('project_id', array(
						'type' => 'text',
						'label' => 'Project ID'
					)); ?>
					<?php echo $this->Form->input('old_amount', array(
						'label' => 'Old Paid Out Amount',
						'type' => 'text'
					)); ?>
					<?php echo $this->Form->input('new_amount', array(
						'label' => 'New Amount',
						'type' => 'text'
					)); ?>
				</div>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Fix Points', array('class' => 'btn btn-primary')); ?>
			</div>
		</div>
	</div>
	<?php echo $this->Form->end(null); ?>
</div>

<div class="span6">
	<div class="box">
		<div class="box-header">
			<span class="title">Fixing Project Points</span>
		</div>
		<div class="box-content">
			<div class="row-fluid">
				<div class="padded">
					<p>From here, you can update all paid-out points on a project from one amount to another. It will only affect pending transactions.</p>
				</div>
			</div>
		</div>
	</div>
</div>