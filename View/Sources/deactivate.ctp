<?php echo $this->Form->create(null, array('type' => 'file')); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Deactivate Source</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span12">
				<div class="padded">
					<div class="alert alert-danger">
						You are about to deactive this source. Any users who are captured into these sources will no longer be accurately reported.
					</div>
					<?php echo $this->Form->input('id'); ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Deactivate Source', array('class' => 'btn btn-danger')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>