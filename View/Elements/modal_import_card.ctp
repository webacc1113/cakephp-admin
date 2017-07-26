<div id="modal_import_card_<?php echo $i; ?>" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Import Tango card</h6>
	</div>
	<div class="modal-body">
		<div class="alert"></div>
		<img src="<?php echo $brand->image_url ?>" class="img-responsive">
		<h5><?php echo $val; ?></h5>
		<?php echo $this->Form->create('Tangocard', array(
			'id' => 'card_'.$i
		)); ?>
		<?php echo $this->Form->input('brand', array(
			'type' => 'hidden', 
			'value' => json_encode($brand)
		)); ?>
		<?php echo $this->Form->input('name', array(
				'value' => $brand->description,
			)
		); ?>
		<?php echo $this->Form->input('transaction_name', array(
			'after' => '<small>If left blank, the name field will be used as transaction title</small>'
		)); ?>
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
		<?php echo $this->Form->input('conversion', array('label' => 'Conversion', 'value' => '1.00', 'type' => 'text')); ?>
		<?php echo $this->Form->input('allowed_us', array('label' => 'Allowed in US')); ?>
		<?php echo $this->Form->input('allowed_ca', array('label' => 'Allowed in CA')); ?>
		<?php echo $this->Form->input('allowed_gb', array('label' => 'Allowed in GB')); ?>
		<?php echo $this->Form->end(null); ?>
	</div>
	<div class="modal-footer">
		<button class="btn btn-default" data-dismiss="modal">Cancel</button>
		<button type="button" class="btn btn-primary" onclick="return MintVine.ImportCard(<?php echo $i;?>);">Import</button>
	</div>
</div>
<style>
	.alert {
		display: none;
	}
</style>