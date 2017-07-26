<div class="box">
	<div class="box-header">
		<span class="title">
			<?php echo __('Update user\'s address')?>
		</span>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create('UserAddress', array(
			'inputDefaults' => array(
				'div' => 'form-group',
				'class' => 'form-control',
				'required' => false
			)
		)); 
		
		echo $this->Form->input('User.id', array(
			'type' => 'hidden'
		));
		
		echo $this->Form->input('UserAddress.id', array(
			'type' => 'hidden'
		));
		
		echo $this->Form->input('QueryProfile.id', array(
			'type' => 'hidden'
		));
		?>			
			<div class="padded separate-sections">												
				<?php echo $this->Session->flash();?>				
				<?php echo $this->Form->input('first_name', array(
					'required' => true,
					'class' => 'span4',
					'label' => 'First Name',
				)); ?>
				<?php echo $this->Form->input('last_name', array(
					'required' => true,
					'class' => 'span4',
					'label' => 'Last Name',
				)); ?>			
				<?php echo $this->Form->input('address_line1', array(
					'required' => true,
					'label' => 'Address (Line 1)',
				)); ?>
				<?php echo $this->Form->input('address_line2', array(
					'required' => false,
					'label' => 'Address Line 2 (Optional)',
				)); ?>
				<?php echo $this->Form->input('city', array(
					'required' => true,
					'label' => 'City',
					'class' => 'span4',
				)); ?>
				<?php echo $this->Form->input('state', array(
					'empty' => 'Select State:',
					'type' => 'select',
					'options' => $states,
					'required' => true,
					'label' => false
				)); ?>
				<?php echo $this->Form->input('postal_code', array(
					'required' => true,
					'label' => 'ZIP Code:',
					'class' => 'span4',
					'style' => 'width: 80px;'
				)); ?>
				<?php echo $this->Form->input('postal_code_extended', array(
					'label' => 'Extented ZIP Code:',
					'maxlength' => 4,
					'style' => 'width: 80px;'
				)); ?>
				<?php echo $this->Form->input('county', array(
					'options' => $counties,
					'type' => 'select',
					'empty' => 'Select:'
				)); ?>
				<?php echo $this->Form->input('verified', array(
					'type' => 'checkbox', 
					'label' => array('text' => 'Is address verified?', 
					'style' => "display: inline;margin-left: 5px;vertical-align: sub;")
				)); ?>
				<div class="clearfix"></div>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Update', array('class' => 'btn btn-success')); ?>
			</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>