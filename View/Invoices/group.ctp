<div class="box">
	<div class="box-header">
		<span class="title">Generate Invoice by Group + Date</span>
	</div>
	<div class="box-content">
		<div class="padded">
			<?php echo $this->Form->create('Report'); ?>
			<?php echo $this->Form->input('start_date', array(
				'type' => 'date',
				'minYear' => date('Y') - 1,
   				'maxYear' => date('Y'),
				'selected' => date('Y-m-d', strtotime('-1 week'))
			)); ?>
			<?php echo $this->Form->input('end_date', array(
				'type' => 'date',
				'minYear' => date('Y') - 1,
   				'maxYear' => date('Y'),
			)); ?>
			<?php echo $this->Form->input('partner', array(
				'type' => 'select',
				'empty' => 'Select Group:',
				'options' => array(
	//				'cint' => 'Cint',
					'ssi' => 'SSI',
	//				'fulcrum' => 'Fulcrum'
				)
			)); ?>
		</div>
		<div class="form-actions">	
			<?php echo $this->Form->submit('Generate Invoice', array(
				'class' => 'btn btn-sm btn-primary',
				'disabled' => false
			)); ?>
			<?php echo $this->Form->end(null); ?>
		</div>
	</div>
</div>