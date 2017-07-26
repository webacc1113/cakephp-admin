<?php echo $this->Form->create('Tango'); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Register credit card</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span8">
				<div class="padded">
					<h3>Credit card details</h3>
					<?php echo $this->Form->input('cc_number', array(
						'label' => 'Credit card number',
						'required' => true
					));?>
					<?php echo $this->Form->input('expiration', array(
						'label' => 'Expiration',
						'after' => '<small>(yyyy-mm)</small>',
					   'required' => true
					));?>
					<?php echo $this->Form->input('cvv', array(
						'label' => 'Security code',
						'after' => '<small>CVV or CV2</small>',
						'required' => true
					));?>
					
					<h3>Billing Address information</h3>
					<?php echo $this->Form->input('first_name'); ?>
					<?php echo $this->Form->input('last_name'); ?>
					<?php echo $this->Form->input('address'); ?>
					<?php echo $this->Form->input('city'); ?>
					<?php echo $this->Form->input('state'); ?>
					<?php echo $this->Form->input('zip', array(
						'label' => 'Zip or postal code'
					)); ?>
					<?php echo $this->Form->input('country'); ?>
					<?php echo $this->Form->input('email'); ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Save', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>