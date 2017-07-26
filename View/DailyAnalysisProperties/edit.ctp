<?php echo $this->Form->create(); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Edit Daily Analysis Property</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span4">
				<div class="padded">
					<?php echo $this->Form->input('name', array('label' => 'Name'));?>
					<?php echo $this->Form->input('id', array('type' => 'hidden'));?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Save Property', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>