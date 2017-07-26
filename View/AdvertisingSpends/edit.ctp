<?php echo $this->Form->create(); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Edit Advertising Spend</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span4">
				<div class="padded">
					<?php echo $this->Form->input('id', array('type' => 'hidden')); ?>
					<?php echo $this->Form->input('acquisition_partner_id', array(
						'label' => 'Acquisition Partner',
						'empty' => 'Select Partner',
						'options' => $acquisition_partners
					)); ?>
					<?php echo $this->Form->input('country', array(
						'type' => 'select',
						'label' => 'Country', 
						'options' => $countries
					)); ?>
					<?php echo $this->Form->input('date', array(
						'label' => 'Date',
						'class' => 'datepicker',
						'type' => 'text',
						'data-date-autoclose' => false,
						'value' => isset($this->request->data['AdvertisingSpend']['date']) ? date('m/d/Y', strtotime($this->request->data['AdvertisingSpend']['date'])) : date('m/d/Y', mktime(0, 0, 0, date('m'), date('d'), date('Y')))
					)); ?>
					<?php echo $this->Form->input('spend', array(
						'label' => 'Spend', 
						'type' => 'text'
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