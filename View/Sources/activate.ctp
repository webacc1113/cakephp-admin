<?php echo $this->Form->create(null, array('type' => 'file')); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Reactivate Campaign</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span12">
				<div class="padded">
					<div class="alert alert-danger">
						Are you sure you want to reactivate this campaign?
					</div>
					<?php echo $this->Form->input('id'); ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Reactivate Campaign', array('class' => 'btn btn-success')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>