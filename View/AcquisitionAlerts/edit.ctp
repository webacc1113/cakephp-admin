<?php echo $this->Form->create(null, array('type' => 'file')); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Edit Alert</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span4">
				<div class="padded">
					<?php echo $this->Form->input('id'); ?>
					<?php echo $this->Form->input('name', array('label' => 'Name')); ?>
					<?php echo $this->Form->input('source_mapping_id', array(
						'empty' => 'Select Source Mapping:',
						'type' => 'select',
						'label' => 'Select Source Mapping or Campaign:',
						'options' => $source_mappings
					)); ?>
					<?php echo $this->Form->input('source_id', array(
						'empty' => 'Select Campaign:',
						'type' => 'select',
						'options' => $sources
					)); ?>
					<?php echo $this->Form->input('event', array(
						'empty' => 'Select Event:',
						'label' => 'Trigger Event:',
						'type' => 'select', 
						'options' => $events
					)); ?>
					<?php echo $this->Form->input('amount', array('type' => 'text', 'label' => 'Current Count', 'style' => 'width: 80px;')); ?>
					<?php echo $this->Form->input('trigger', array('type' => 'text', 'label' => 'Trigger Amount', 'style' => 'width: 80px;')); ?>
					<?php echo $this->Form->input('description', array('label' => 'Alert Text Posted in Slack')); ?>
					<?php echo $this->Form->input('alert_threshold_minutes', array(
						'label' => 'Time between re-alerts (in minutes)',
						'type' => 'text',
						'style' => 'width: 80px;',
						'after' => ' <small>At least 5 minutes, at most 1440 minutes</small>'
					)); ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Edit Alert', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>