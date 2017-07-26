<?php echo $this->Form->create(null, array('type' => 'file')); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Edit Partner</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span4">
				<div class="padded">
					<?php echo $this->Form->input('id'); ?>
					<?php echo $this->Form->input('name', array('label' => 'Display Name')); ?>
					<?php if ($this->data['AcquisitionPartner']['source'] == 'adwords') : ?>
						<?php echo $this->Form->input('source', array(
							'type' => 'hidden',
							'value' => 'adwords'
						)); ?><?php echo $this->Form->input('utm_source', array(
							'type' => 'text',
							'value' => 'adwords',
							'disabled' => true,
							'after' => '<br/><small>You cannot change the adwords source name</small>'
						)); ?>
					<?php else: ?>
						<?php echo $this->Form->input('source', array('label' => 'UTM Source')); ?>
					<?php endif; ?>
					<?php echo $this->Form->input('post_registration_pixel', array('label' => 'Registration Pixel')); ?>
					<?php echo $this->Form->input('affiliate_network', array('label' => 'Affiliate Network', 'type' => 'select', 'options' => array('0' => 'No', '1' => 'Yes'))); ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Save', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>