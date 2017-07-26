<?php echo $this->Form->input('User.phone_number', array(
	'type' => 'select',
	'options' => $mobile_numbers,
	'label' => 'Select phone number to proceed with:'
));?>