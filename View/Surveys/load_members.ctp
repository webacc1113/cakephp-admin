<div class="span6">
	<?php echo $this->Form->create('Survey', array('type' => 'file')); ?>
	<div class="box">
		<div class="box-header">
			<span class="title">Load Members</span>
		</div>
		<div class="box-content">
			<div class="padded">
				<?php echo $this->Form->input('user_ids', array(
					'label' => 'Load User IDS (one per line)',
					'type' => 'textarea',
					'style' => 'height: 104px',
				)); ?>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Load Members', array('class' => 'btn btn-primary')); ?>
			</div>
		</div>
	</div>

	<?php echo $this->Form->end(); ?>
</div>
<div class="span6">
	<div class="box">
		<div class="box-header">
			<span class="title"></span>
		</div>
		<div class="box-content">
			<div class="padded">
			</div>
		</div>
	</div>
</div>
