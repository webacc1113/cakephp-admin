<?php if (isset($token)): ?>
<?php echo $this->Form->input('token', array(
	'type' => 'text', 
	'value' => $token
)); ?>
<?php endif; ?>