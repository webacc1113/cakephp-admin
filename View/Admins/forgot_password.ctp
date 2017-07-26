<div class="span4 offset4">
	<div class="padded">
		<div class="login box" style="margin-top: 80px;">
		
			<div class="box-header">
				<span class="title">Reset Password</span>
			</div>
			
			<div class="box-content padded">
				<?php echo $this->Form->create('Admin', array('autocomplete' => 'off', 'class' => 'fill-up separate-sections')); ?>
   				 <fieldset>
				    	<?php
					        echo $this->Form->input('admin_user', array(
					        	'label' => 'Username',
								'autocomplete' => 'off',
								'class' => 'form-control'
							));
					    ?>

					<?php echo $this->Form->submit('Send', array('class' => array('btn btn-primary'))); ?>
				    </fieldset>
				<?php echo $this->Form->end(null); ?>
			</div>
		</div>
	</div>
</div>