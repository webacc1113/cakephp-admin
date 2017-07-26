<div class="container">
	<?php
	echo $this->Form->create('Invoice', array(
		'inputDefaults' => array(
			'div' => 'form-group',
			'wrapInput' => false,
			'class' => 'form-control'
	)));
	?>
	<div class="box invoice">
		<div class="box-header">
			<span class="title">Regenerate Invoice</span>
		</div>
		<div class="box-content">
			<div class="padded">
				<?php echo $this->Form->input('invoice'); ?>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Regenerate Invoice', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
	<?php echo $this->Form->end(null); ?>
</div>