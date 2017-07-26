<?php echo $this->Form->create(null, array('type' => 'file')); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Create New Offer</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span4">
				<div class="padded">
					<?php echo $this->Form->input('partner', array('options' => unserialize(OFFER_PARTNERS), 'label' => 'Partner')); ?>
					<?php echo $this->Form->input('offer_title', array('label' => 'Title')); ?>
					<?php echo $this->Form->input('award', array('type' => 'text', 'label' => 'Award', 'default' => '0', 'after' => ' points')); ?>
					<?php echo $this->Form->input('client_rate', array(
						'type' => 'text', 
						'label' => 'Client Rate', 
						'between' => '<div class="input-prepend"><span class="add-on" href="#"><i class="icon-none">$</i></span>',
						'after' => '</div>'
					)); ?>
					<?php echo $this->Form->input('offer_url', array('label' => 'Offer URL')); ?>
					<?php echo $this->Form->input('offer_desc', array('rows' => '10', 'cols' => '10', 'label' => 'Offer Description'));?>
					<?php echo $this->Form->input('offer_instructions', array('rows' => '10', 'cols' => '10', 'label' => 'Offer Instructions'));?>
					<?php echo $this->Form->input('paid', array('options' => array('0' => 'Free', '1' => 'Paid'), 'default' => '1', 'label' => 'Offer Type')); ?>
					<?php echo $this->Form->input('locale', array('multiple' => 'checkbox', 'options' => array('US' => 'US', 'International' => 'International')));?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Save', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>