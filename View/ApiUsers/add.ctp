<?php echo $this->Form->create('ApiUser', array('autocomplete' => 'off')); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Create Api User</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span6">
				<div class="padded">
					<?php echo $this->Form->input('username', array(
						'label' => 'Username', 
						'required' => true
					));?>
					<?php echo $this->Form->input('password', array('type' => 'text', 'label' => 'Password', 'required' => true,'autocomplete' => 'new-password')); ?>
					<?php echo $this->Html->link('', array(
							'controller' => 'api_users',
							'action' => 'ajax_create_admin'
						),
						array(
							'id' => 'create_admin_link',
							'data-target' => '#modal-create-admin',
							'data-toggle' => 'modal',
						)); ?>
					<?php
						$admins['create_admin'] = 'Create Administrator';
						echo $this->Form->input('admin_user_id', array(
							'value' => $current_user['Admin']['id'],
							'options' => $admins,
							'id' => 'admin_select',
							'after' => '<br/><small>This is the admin user who will be assigned to all projects created by this API user</small>'
					));?>
					<?php echo $this->Form->input('client_id', array(
						'value' => $mintvine_client['Client']['id'],
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
					<?php echo $this->Form->input('notes', array('rows' => 3, 'label' => 'Notes')); ?>
					<?php echo $this->Form->input('livemode', array(
						'type' => 'checkbox', 
						'label' => 'Live Mode'
					));?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Create API User', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>
<?php echo $this->Form->create('Admin', array('url' => array('controller' => 'api_users', 'action' => 'ajax_create_admin'), 'onsubmit' => 'return create_admin()')); ?>
<div id="modal-create-admin" style="width:40%; left:50%;" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Create Administrator</h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<?php echo $this->Form->submit('Create', array('class' => 'btn btn-primary')); ?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>

<script type="text/javascript">
	$(document).ready(function() {
		var random_string = generate_random_string(15);
		$('#ApiUserUsername').val('tk_' + random_string);
		$('#ApiUserPassword').val(random_string);

		$('#admin_select').change(function() {
			if ($(this).val() == 'create_admin') {
				$('#create_admin_link').trigger('click');
			}
		});
	});

	function create_admin() {
		$('#modal-create-admin .error-message').remove();
		var admin_info = {
			admin_user: $('#modal-create-admin #admin_user').val(),
			admin_email: $('#modal-create-admin #admin_email').val(),
			admin_pass: generate_random_string(15)
		};

		$.ajax({
			type: 'POST',
			url: '/api_users/ajax_create_admin/',
			data: admin_info,
			statusCode: {
				201: function(data) {
					var html_str = '';
					for (var admin_id in data) {
						html_str += '<option value="' + admin_id + '">' + data[admin_id] + '</option>';
					}
					$('#admin_select option:last').before(html_str);
					$('#admin_select').val(admin_id);
					$('#modal-create-admin .close').trigger('click');
				},
				400: function(response) {
					var errors = JSON.parse(response.responseText);
					for (field_name in errors) {
						var html_str = '<div class="error-message text-error">' + errors[field_name] + '</div>';
						$('#' + field_name).after(html_str);
					}
				}
			}
		});
		return false;
	}

	function generate_random_string(length) {
		var string = "";
		var possible = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
		for (var i = 0; i < length; i ++) {
			string += possible.charAt(Math.floor(Math.random() * possible.length));
		}
		return string;
	}
</script>
