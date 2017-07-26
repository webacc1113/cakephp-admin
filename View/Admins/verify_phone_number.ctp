<div class="span4 offset4">
	<div class="padded">
		<div class="login box" style="margin-top: 80px;">
		
			<div class="box-header">
				<span class="title">2-Factor Authentication</span>
			</div>
			
			<div class="box-content padded">
				<?php echo $this->Form->create('Admin', array('autocomplete' => 'off', 'class' => 'fill-up separate-sections')); ?>
   				 <fieldset>
				    	<?php
					        echo $this->Form->input('mobile_verification_code', array(
					        	'label' => 'Authentication Code',
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