<?php echo $this->Form->create('Admin', array(
		'autocomplete' => 'off'
	)); 
?>
<div class="box">
	<div class="box-header">
		<span class="title">Edit Admin</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span4">
				<div class="padded">
					<?php echo $this->Form->input('id', array('type' => 'hidden')); ?>
					<?php echo $this->Form->input('admin_user', array('label' => 'Username')); ?>
					<?php echo $this->Form->input('admin_email', array('label' => 'Email')); ?>
					<?php echo $this->Form->input('admin_pass', array(
						'type' => 'password',
						'autocomplete' => 'new-password',
						'label' => 'Password', 
						'value' => '', 
						'required' => false
					)); ?>
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
						'style' => 'width: 100%; height: 200px;',
						'selected' => $selected_roles,
					)); ?>
					<?php $urls = $this->data['Admin']['limit_access']; ?>
					<?php if (!empty($urls)): ?>
						<?php $urls = json_decode($urls, true); ?>
						<?php $urls = implode("\r\n", $urls); ?>
					<?php endif; ?>
					<?php echo $this->Form->input('limit_access',  array(
						'div' => array('id' => 'limit_access'), 
						'rows' => '10', 
						'cols' => '10', 
						'label' => 'Limit Access (for guests only)', 
						'value' => $urls, 
						'after' => '(each on a separate row)')
					); ?>
					<?php echo $this->Form->input('AdminGroup.id', array(
						'label' => 'Groups',
						'type' => 'select',
						'options' => $groups,
						'multiple' => 'checkbox',
						'style' => 'width: 100%; height: 200px;',
						'selected' => $selected_groups,
					)); ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Save', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>
<script>
	$(document).ready(function() {
		limitAccess($('#AdminRoleId<?php echo $role_keys['guest'];?>').prop('checked'));
	});
	
	$('#AdminRoleId<?php echo $role_keys['guest'];?>').click(function() {
		limitAccess(this.checked)
	})
	
	function limitAccess(val) {
		$("#limit_access").toggle(val);
	}
</script>
<?php echo $this->Html->script(array('/js/jquery.mask.min')); ?>
<script>
	<?php if (isset($this->request->data['Admin']['country_code']) && $this->request->data['Admin']['country_code'] == 'GB'): ?>
		$("#AdminPhoneNumber").mask("00-0000-0000"); 
	<?php else: ?>
		$("#AdminPhoneNumber").mask("000-000-0000"); 
	<?php endif; ?>
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