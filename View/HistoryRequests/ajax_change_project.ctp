<div class="alert alert-error" id="change-error" style="display: none;"></div>
<div class="alert alert-success" id="change-success" style="display: none;"></div>
<?php echo $this->Form->input('history_request_id', array('id' => 'change-request_id', 'value' => $history_request_id, 'type' => 'hidden')); ?>
<?php echo $this->Form->input('panelist_history_id', array(
	'type' => 'select',
	'empty' => 'Select new project',
	'options' => $user_projects,
	'label' => 'Change project',
	'style' => 'width: 100%'
)); ?>