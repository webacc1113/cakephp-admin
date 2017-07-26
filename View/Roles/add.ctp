<?php echo $this->Form->create('Role'); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Create Permission Group</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span4">
				<div class="padded">
					<?php echo $this->Form->input('name'); ?>
					<?php echo $this->Form->input('admin', array('label' => 'Allow Admin access')); ?>
					<?php echo $this->Form->input('users', array('label' => 'Allow Users access')); ?>
					<?php echo $this->Form->input('reports', array('label' => 'Allow Reports access')); ?>
					<?php echo $this->Form->input('projects', array('label' => 'Allow Projects access')); ?>
					<?php echo $this->Form->input('transactions', array('label' => 'Allow Transactions access')); ?>
					<?php echo $this->Form->input('campaigns', array('label' => 'Allow Campaigns access')); ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Create Role', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>