<?php echo $this->Form->create(); ?>
	<div class="box">
		<div class="box-header">
			<span class="title">Edit Source Mapping</span>
		</div>
		<div class="box-content">
			<div class="row-fluid">
				<div class="span4">
					<div class="padded">
						<?php echo $this->Form->input('id'); ?>
						<?php echo $this->Form->input('utm_source', array('label' => 'UTM Source')); ?>
						<?php echo $this->Form->input('name', array('label' => 'Partner Name')); ?>
						<?php echo $this->Form->input('acquisition_partner_id', array(
							'type' => 'select',
							'options' => $acquisition_partners,
							'label' => 'Partner',
							'selected' => 'adwords'
						)); ?>
						<?php echo $this->Form->input('publisher_id', array(
							'type' => 'text',
							'label' => 'If partner is an affiliate network, please input the UTM parameter that contains publisher ID',
							'placeholder' => 'example: utm_campaign'
						)); ?>
					</div>
				</div>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Save', array('class' => 'btn btn-primary')); ?>
			</div>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>