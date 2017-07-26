<?php echo $this->Form->create(null); ?>
	<div class="box">
		<div class="box-header">
			<span class="title">Users Notification Report</span>
		</div>
		<div class="box-content">
			<div class="padded">
				<?php echo $this->Form->input('hours', array(
					'type' => 'text',
					'label' => 'Report since last hours:',
					'value' => isset($this->request->data['Report']['hours']) ? $this->request->data['Report']['hours'] : '24'
				)); ?>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Generate Report', array('class' => 'btn btn-primary')); ?>
			</div>
		</div>
	</div>
<?php echo $this->Form->end(); ?>
