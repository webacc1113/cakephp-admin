<?php echo $this->Form->create('ApiUser', array('autocomplete' => 'off'	));?>
<div class="box">
	<div class="box-header">
		<span class="title">Edit Api User</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span4">
				<div class="padded">
					<?php echo $this->Form->input('id', array('type' => 'hidden', 'value' => $api_user['ApiUser']['id'])); ?>
					<?php
						echo $this->Form->input('username', array(
							'label' => 'Username',
							'required' => true,
							'value' => $api_user['ApiUser']['username']
						));
					?>
					<?php echo $this->Form->input('password', array(
						'type' => 'password',
						'autocomplete' => 'new-password',
						'label' => 'Password', 
						'value' => '',
						'required' => false
					)); ?>
					<?php echo $this->Form->input('admin_user_id', array(
						'options' => $admins,
						'after' => '<br/><small>This is the admin user who will be assigned to all projects created by this API user</small>'
					));?>
					<?php echo $this->Form->input('client_id', array(
						'options' => $clients,
						'after' => '<br/><small>This is the client that will be assigned to all projects created by this API user</small>'
					));?>
					<?php echo $this->Form->input('group_id', array(
						'options' => $groups
					));?>
					<?php echo $this->Form->input('testmode_user_ids', array(
						'label' => 'User IDs for test mode (one per line)',
						'type' => 'textarea',
					)); ?>
					<?php echo $this->Form->input('notes', array(
						'label' => 'Notes',
						'type' => 'textarea',
						'rows' => 3,
						'value' => $api_user['ApiUser']['notes']
					));?>
					<?php echo $this->Form->input('livemode', array(
						'type' => 'checkbox', 
					));?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Save', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>