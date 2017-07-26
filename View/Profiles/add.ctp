<?php echo $this->Form->create(); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Create User Profile Survey</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span4">
				<div class="padded">
					<?php echo $this->Form->input('name', array('label' => 'User Profile Survey Name')); ?>
					<?php echo $this->Form->input('award', array('type' => 'text', 'label' => 'Award')); ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Create User Profile Survey', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>