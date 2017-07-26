<?php $tzlist = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
$list = array();
foreach ($tzlist as $tz) {
	$list[$tz] = $tz;
}
?>
<?php echo $this->Form->create('Admin', array(
		'autocomplete' => 'off'
	)); 
?>
<div class="box">
	<div class="box-header">
		<span class="title">Edit Preferences</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span4">
				<div class="padded">
					<?php echo $this->Form->input('id', array('type' => 'hidden')); ?>
					<?php echo $this->Form->input('admin_email', array('label' => 'Email')); ?>
					<?php echo $this->Form->input('admin_pass', array(
						'label' => 'Password (Optional)',
						'autocomplete' => 'new-password',
						'type' => 'password',
						'value' => '', 
						'required' => false
					)); ?>
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

					<?php echo $this->Form->input('slack_username', array('label' => 'Slack Username'));?>
					
					<?php echo $this->Form->input('timezone', array(
						'label' => 'Timezone',
						'options' => $list,
						'type' => 'select',
						'empty' => 'Select'
					)); ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Update', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>
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