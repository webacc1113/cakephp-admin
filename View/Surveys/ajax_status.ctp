<?php echo $this->Form->input('id', array('value' => $this->data['Project']['id'], 'type' => 'hidden')); ?>
<?php echo $this->Form->input('status', array(
	'type' => 'select', 
	'value' => $this->data['Project']['status'],
	'options' => unserialize(PROJECT_STATUSES)
)); ?>