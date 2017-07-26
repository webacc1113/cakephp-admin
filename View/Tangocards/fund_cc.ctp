<?php echo $this->Form->create('Tango'); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Fund credit card</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span8">
				<div class="padded">
					<?php echo $this->Form->input('amount', array(
						'required' => true,
						'after' => '<small>Amount in $</small>'
					));?>
					<?php echo $this->Form->input('cvv', array(
						'label' => 'Security code',
						'after' => '<small>CVV or CV2</small>',
						'required' => true
					));?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Save', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>