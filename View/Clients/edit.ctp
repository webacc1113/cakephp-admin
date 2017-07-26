<?php echo $this->Form->create(); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Edit Client</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span4">
				<div class="padded">
					<?php echo $this->Form->input('id', array('type' => 'hidden'));?>
					<?php echo $this->Form->input('quickbook_customer_id', array(
						'type' => 'text',
						'label' => 'Quickbook Customer ID'
					)); ?>
					<?php echo $this->Form->input('client_name', array('label' => 'Client Name')); ?>
					<?php echo $this->Form->input('code_name', array('label' => 'Code Name (Seen by Panelists)')); ?>
					<?php //echo $this->Form->input('notes', array('rows' => 3, 'label' => 'Address')); ?>
					<?php echo $this->Form->input('address_line1', array('required' => false)); ?>
					<?php echo $this->Form->input('address_line2', array()); ?>
					<?php echo $this->Form->input('geo_country_id', array(
						'empty' => 'Select:', 
						'label' => 'Country', 
						'options' => $geo_countries)
					); ?>
					<?php echo $this->Form->input('city', array(
					)); ?>
					<?php echo $this->Form->input('geo_state_id', array('label' => 'State', 'options' => $geo_states)); ?>
					<?php echo $this->Form->input('postal_code'); ?>
					<?php echo $this->Form->input('billing_name', array('label' => 'Billing Name')); ?>
					<?php echo $this->Form->input('billing_email', array('label' => 'Billing Email')); ?>
					<?php echo $this->Form->input('net', array('label' => 'Due Days Period', 'maxLength' => 5, 'after' =>  '<span> Days</span>')); ?>
					<?php if (isset($this->data['Contact']['id']) && $this->data['Contact']['id']): ?>
						<?php echo $this->Form->input('Contact.address', array('label' => 'Address')); ?>
						<?php echo $this->Form->input('Contact.id', array('type' => 'hidden')); ?>
					<?php endif; ?>
					<?php echo $this->Form->input('project_name', array('label' => 'Project Name')); ?>
					<?php echo $this->Form->input('project_email', array('label' => 'Project Email')); ?>
					<?php if (isset($groups)): ?>
						<?php echo $this->Form->input('group_id', array('label' => 'Group')); ?>
					<?php endif; ?>
					<?php echo $this->Form->input('param_type', array('label' => 'Param Type', 'empty' => 'None', 'options' => unserialize(PARAM_TYPES))); ?>
					
					<?php echo $this->Form->input('do_not_autolaunch', array(
						'label' => 'Do not autolaunch projects (Only affects projects in the <strong><em>'.$client['Group']['name'].'</em></strong> group)'
					)); ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Save Changes', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>