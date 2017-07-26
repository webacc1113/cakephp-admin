<?php echo $this->Form->create(); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Edit Card</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span8">
				<div class="padded">
					<?php echo $this->Form->input('name'); ?>
					<?php echo $this->Form->input('transaction_name', array(
						'after' => '<small>If left blank, the name field will be used as transaction title</small>'
					)); ?>
					<?php echo $this->Form->input('segment_transaction_name', array(
						'after' => '<small>Refer to Analytics before modifying this field</small>',
						'required' => true
					)); ?>
					<?php if (empty($this->data['Tangocard']['parent_id'])): ?>
						<?php echo $this->Form->input('type', array(
							'placeholder' => 'e.g Gift card',
							'after' => '<small>If left blank, "Gift card" is used by default</small>'
						)); ?>
						<?php echo $this->Form->input('description', array(
							'rows' => '10', 
							'cols' => '10', 
							'label' => 'Short description',
							'after' => '<small>Shown on withdrawal screens</small>'
						)); ?>
						<?php echo $this->Form->input('long_description', array(
							'rows' => '10', 
							'cols' => '10', 
							'label' => 'Long description',
							'after' => '<small>Shown on payment options screen</small>'
						)); ?>
						<?php echo $this->Form->input('disclaimer', array(
							'rows' => '10', 
							'cols' => '10', 
							'label' => 'Disclaimer'
						)); ?>
						<?php echo $this->Form->input('redemption_instructions', array(
							'rows' => '10', 
							'cols' => '10', 
							'label' => 'Redemption Instructions',
							'after' => '<small>This text will be used in redeption email</small>'
						)); ?>
						<?php echo $this->Form->input('conversion', array('type' => 'text')); ?>
						<?php echo $this->Form->input('allowed_us', array('label' => 'Allowed in US')); ?>
						<?php echo $this->Form->input('allowed_ca', array('label' => 'Allowed in CA')); ?>
						<?php echo $this->Form->input('allowed_gb', array('label' => 'Allowed in GB')); ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Save', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>
