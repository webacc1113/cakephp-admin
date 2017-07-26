<div class="padded">
	<div class="row-fluid row-admins">
		<?php echo $this->Form->input('Admin.admin_user', array(
			'label' => 'Username',
			'type' => 'text',
			'id' => 'admin_user',
			'maxlength' => 50,
			'required' => true
		)); ?>
		<?php echo $this->Form->input('Admin.admin_email', array(
			'label' => 'Email',
			'type' => 'text',
			'id' => 'admin_email',
			'auto-complete' => 'new-email',
			'maxlength' => 255,
			'required' => true
		)); ?>
	</div>
</div>
