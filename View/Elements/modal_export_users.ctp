<div class="modal fade" id="exportUserModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true" style="display: none;">
	<div class="modal-dialog" id="modal-export">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
				<h4 class="modal-title">Export Users</h4>
			</div>
			<div class="modal-body">
				<?php echo $this->Form->create('User', array('type' => 'post', 'url' => array('action' => 'index', '?' => http_build_query($this->request->query) . '&export=1'))); ?>
				<div class="padded">
					<p>Generate a CSV file with these attributes:</p>
					<?php echo $this->Form->input('default_options.', array(
						'label' => 'MintVine ID',
						'type' => 'checkbox',
						'disabled' => true,
						'checked' => true
					)); ?>
					<?php echo $this->Form->input('default_options.', array(
						'label' => 'Facebook ID',
						'type' => 'checkbox',
						'disabled' => true,
						'checked' => true
					)); ?>
					<?php echo $this->Form->input('default_options.', array(
						'label' => 'Email',
						'type' => 'checkbox',
						'disabled' => true,
						'checked' => true
					)); ?>
					<?php echo $this->Form->input('hashed', array(
						'label' => 'Email (Hashed)',
						'type' => 'checkbox',
						'checked' => false
					)); ?>
					<?php echo $this->Form->input('default_options.', array(
						'label' => 'Birthdate',
						'type' => 'checkbox',
						'disabled' => true,
						'checked' => true
					)); ?>
					<?php echo $this->Form->input('default_options.', array(
						'label' => 'Country',
						'type' => 'checkbox',
						'disabled' => true,
						'checked' => true
					)); ?>
					<?php echo $this->Form->input('default_options.', array(
						'label' => 'Postal Code',
						'type' => 'checkbox',
						'disabled' => true,
						'checked' => true
					)); ?>
					<?php echo $this->Form->input('default_options.', array(
						'label' => 'Last Activity',
						'type' => 'checkbox',
						'disabled' => true,
						'checked' => true
					)); ?>
					<?php echo $this->Form->input('options', array(
						'label' => false,
						'multiple' => 'checkbox',
						'options' => $mappings,
						'div' => array('class' => 'select')
					)); ?>
					<?php echo $this->Form->input('twilio_phone', array(
						'label' => 'Phone Number',
						'type' => 'checkbox',
						'checked' => false
					)); ?>
				</div>
			</div>
			<div class="modal-footer">
				<?php echo $this->Form->submit('Export Users', array('class' => 'btn btn-primary', 'onclick' => "$('#exportUserModal').modal('hide'); $('#UserIndexForm').submit();")); ?>
			</div>
		</div>
	</div>
</div>