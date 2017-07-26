<?php echo $this->Form->create(); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Confirm Deletion</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span12">
				<div class="padded">
					<?php echo $this->Form->input('id'); ?>
					<div class="alert alert-danger">You are about to delete this profile. Deleting this profile will delete all questions associated with it, as well as all user-data associated with this user profile survey.</div>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Delete User Profile Survey', array('class' => 'btn btn-danger')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>