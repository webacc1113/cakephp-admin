<?php echo $this->Form->create(); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Gift Points</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span4">
				<div class="padded">
					<?php echo $this->Form->input('email', array(
						'label' => 'User Email'
					)); ?>
					<?php echo $this->Form->input('amount', array('type' => 'text')); ?>
					<?php echo $this->Form->input('description', array()); ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Gift Points', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>