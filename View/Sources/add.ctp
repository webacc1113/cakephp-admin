<?php echo $this->Form->create(null, array('type' => 'file')); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Create Campaign</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span12">
				<div class="padded">
					<div class="alert alert-danger">
						Important: Once sources are created, they should not be edited - just deactivated.
					</div>
					<?php echo $this->Form->input('acquisition_partner_id', array(
						'empty' => 'Select:',
						'label' => 'Affiliate',
						'options' => $acquisition_partners
					)); ?>
					<?php echo $this->Form->input('lander_url_id', array(
						'empty' => 'None',
						'options' => $lander_urls,
						'label' => 'Lander'
					)); ?>
					<?php echo $this->Form->input('name', array(
						'label' => 'Campaign Name',
					)); ?>
					<?php echo $this->Form->input('abbr', array(
						'label' => 'Internal Name (utm_source)',
						'after' => '<small class="muted">This is the value in the lander URL: should only be alphanumeric characters with no spaces</small>'
					)); ?>
					<?php echo $this->Form->input('publisher_id_key', array(
						'label' => 'Publisher ID Key',
						'after' => '<small class="muted">This is the key sent by the campaign that contains the publisher ID</small>'
					)); ?>
					<?php echo $this->Form->input('post_registration_pixel', array(
						'type' => 'textarea',
						'after' => '<small>Supported special variables: <code>{{USER_ID}}</code> or <code>{{GENDER}}</code></small>'
					)); ?>
					<?php echo $this->Form->input('post_registration_postback', array(
						'type' => 'text',
						'after' => '<small>Supported special variables: <code>{{USER_ID}}</code> or <code>{{GENDER}}</code></small>'
					)); ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Create Campaign', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>