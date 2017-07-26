<script type="text/javascript">
	function toggleChecked(status) {
		$('input[name="data[AdminGroup][id][]"]').each(function () {
			$(this).prop("checked", status);
		})
	}
</script>
<?php echo $this->Form->create('Admin', array('autocomplete' => 'off')); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Create Administrator</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span4">
				<div class="padded">
					<?php echo $this->Form->input('admin_user', array('label' => 'Username'));?>
					<?php echo $this->Form->input('admin_email', array('label' => 'Email', 'autocomplete' => 'new-email'));?>
					<?php echo $this->Form->input('admin_pass', array('type' => 'password', 'label' => 'Password', 'autocomplete' => 'new-password')); ?>
					<?php echo $this->Form->input('slack_username', array('label' => 'Slack Username'));?>
					<div class="row-fluid">
						<div class="span4">
							<?php echo $this->Form->input('country_code', array(
								'type' => 'select', 
								'label' => 'Country Code', 
								'options' => array('US' => 'US (+1)', 'GB' => 'GB (+44)', 'CA' => 'CA (+1)'),
								'style' => 'width: 100%; margin: 0'
							)); ?>
							<?php echo $this->Form->input('phone_country_code', array('type' => 'hidden')); ?>
						</div>
						<div class="span8">
							<?php echo $this->Form->input('phone_number', array('type' => 'text', 'label' => 'Phone Number <small> (Required for 2FA)</small>')); ?>
						</div>
					</div>
					<?php echo $this->Form->input('authenticate_type', array(
						'type' => 'select',
						'label' => '2FA',
						'options' => array('custom_code' => 'App Code', 'sms' => 'SMS'),
						'empty' => 'None',
						'style' => 'width: 100%; margin: 0'
					)); ?>

					<?php echo $this->Form->input('AdminRole.id', array(
						'label' => 'Roles',
						'type' => 'select',
						'options' => $roles,
						'multiple' => 'checkbox',
						'style' => 'width: 100%; height: 200px;'
					)); ?>
					<?php echo $this->Form->input('limit_access',  array(
						'div' => array(
							'id' => 'limit_access'), 
							'rows' => '10', 
							'cols' => '10', 
							'label' => 'Limit Access (for guests only)', 
							'after' => '(each on a separate row)'
					)); ?>
					<?php echo $this->Form->label('Groups'); ?>
					<?php
					echo $this->Form->input('null', array(
						'type' => 'checkbox',
						'label' => 'Check All',
						'id' => 'select_all',
						'onclick' => 'return toggleChecked(this.checked)'
					));
					?>
					<?php echo $this->Form->input('AdminGroup.id', array(
						'label' => false,
						'type' => 'select',
						'options' => $groups,
						'multiple' => 'checkbox',
						'style' => 'width: 100%; height: 200px;'
					)); ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Create Administrator', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>
<script>
	$(document).ready(function() {
		if ($('#AdminRoleId<?php echo $role_keys['guest'];?>').is(':checked')) {
			$("#limit_access").toggle(this.checked);
		}
	}); 
	$('#AdminRoleId<?php echo $role_keys['guest'];?>').click(function() {
		$("#limit_access").toggle(this.checked);
	})
</script>
<?php echo $this->Html->script(array('/js/jquery.mask.min')); ?>
<script>
	$("#AdminPhoneNumber").mask("000-000-0000"); 
	$(document).ready(function() {
		$('#AdminCountryCode').change(function() {
			if ($(this).val() == 'GB') {
				$("#AdminPhoneNumber").mask("00-0000-0000"); 
				$('#AdminPhoneCountryCode').val('+44');	
			} 
			else {
				$("#AdminPhoneNumber").mask("000-000-0000"); 
				$('#AdminPhoneCountryCode').val('+1');		
			}	
		});	
	});
</script>