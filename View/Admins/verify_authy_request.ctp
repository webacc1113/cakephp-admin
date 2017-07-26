<div class="span4 offset4">
	<div class="padded">
		<div class="login box" style="margin-top: 80px;">
		
			<div class="box-header">
				<span class="title">2-Factor Authentication</span>
			</div>
			
			<div class="box-content padded">
				<label> Request has been sent to Authy mobile app. Please verify to proceed further.</label>
				<?php echo $this->Form->create('Admin', array('autocomplete' => 'off', 'class' => 'fill-up separate-sections')); ?>
   				 <fieldset>
				    	<?php
					        echo $this->Form->input('authy_token', array(
					        	'label' => 'Or, Enter Authy Token',
								'autocomplete' => 'off',
								'class' => 'form-control'
							));					       
					    ?>
					<?php echo $this->Form->submit('Verify', array('class' => array('btn btn-primary'))); ?>
				    </fieldset>
				<?php echo $this->Form->end(null); ?>
			</div>
		</div><?php echo $this->Html->link('Login', array('controller' => 'admins', 'action' => 'login')); ?>
	</div>
</div>
<?php $this->Html->script('jquery.timer', array('inline' => false)); ?>
<script type="text/javascript">
	
	var timer = $.timer(function() {
		$.ajax({
			type: "POST",
			url: "/admins/check_authy_request_status/" + <?php echo $admin_id;?>,
			statusCode: {
				201: function(data) {
					setTimeout( function() {
						window.location = data.redirect;
					}, 1000);
				}
			}
		});
	});

	timer.set({ time : 4000, autostart : true });
</script>