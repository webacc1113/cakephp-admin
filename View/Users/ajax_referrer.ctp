<div class="alert alert-error" id="referrer-error" style="display: none;"></div>
<div class="alert alert-success" id="referrer-success" style="display: none;"></div>
<?php echo $this->Form->input('id', array(
	'type' => 'hidden', 
	'value' => $user['User']['id']
)); ?>
<?php echo $this->Form->input('referrer', array(
	'label' => 'Referrer Email',
	'value' => $user['Referrer']['email'],
)); ?>