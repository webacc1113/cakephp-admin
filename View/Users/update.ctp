<div class="box">
	<div class="box-header">
		<span class="title">
			<?php echo __('Update user\'s email, DOB, & gender')?>
		</span>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create('User', array(
			'inputDefaults' => array(
				'div' => 'form-group',
				'class' => 'form-control',
				'required' => false
			)
		)); 
		
			echo $this->Form->input('User.id', array(
				'type' => 'hidden'
			));
			
			echo $this->Form->input('QueryProfile.id', array(
				'type' => 'hidden'
			));
		?>			
			<div class="padded separate-sections">												
				<?php echo $this->Session->flash();?>				
				<?php echo $this->Form->input('User.email', array(
					'type' => 'text',
					'class' => 'span4'
				)); ?>	
				<div class="clearfix"></div>				
				<?php 
				$default_ts = time();
				if (!empty($this->request->data['QueryProfile']['birthdate'])) {
					if (is_array($this->request->data['QueryProfile']['birthdate'])) {
						$default_ts = strtotime($this->request->data['QueryProfile']['birthdate']['year'] . '-' . $this->request->data['QueryProfile']['birthdate']['month'] . '-' . $this->request->data['QueryProfile']['birthdate']['day']);
					}
					else {
						$default_ts = strtotime($this->request->data['QueryProfile']['birthdate']);
					}
				}
				
				echo $this->Form->input('QueryProfile.birthdate', array(
					'separator' => ' ',
					'maxYear' => date('Y') - 12,
					'minYear' => date('Y') - 100,
					'selected' => array(
						'year' => date('Y', $default_ts),
						'month' => date('m', $default_ts),
						'day' => date('d', $default_ts),
					),
					'value' => !empty($this->request->data['QueryProfile']['birthdate']) ? $this->request->data['QueryProfile']['birthdate'] : ''
				));
				echo $this->Form->error('birthdate');
				?>
				
				<?php echo $this->Form->input('QueryProfile.gender', array(
					'type' => 'select',
					'empty' => 'Select:',
					'options' => array(
						'M' => 'Male',
						'F' => 'Female'
					)
				)); ?>
				<?php echo $this->Form->input('User.phone_number', array(
					'type' => 'text', 
					'label' => 'Mobile/Landline number', 
					'after' => '<small>Example: 999-999-9999</small>')
				); ?>
				<?php echo $this->Form->input('User.is_mobile_verified', array(
					'type' => 'checkbox', 
					'label' => array('text' => 'Is mobile/landline verified?', 
					'style' => "display: inline;margin-left: 5px;vertical-align: sub;"))); 
				?>
				<?php echo $this->Form->input('send_sms', array(
					'label' => array('text' => __('I would like to receive easy surveys via text message.'), 'style' => "display: inline;margin-left: 5px;vertical-align: sub;"),
					'class' => false,
					'type' => 'checkbox',
					'div' => array(
						'class' => false
					)
				)); ?>
				<div class="clearfix"></div>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Update', array('class' => 'btn btn-success')); ?>
			</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>