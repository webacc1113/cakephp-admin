<?php echo $this->Form->create(null, array('type' => 'file', 'class' => 'filter')); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Export Data</span>
	</div>
	<div class="box-content">
		<div class="padded separate-sections">
			<div class="row-fluid">
				<div class="filter date-group">
					<p>Date between:</p>
					<?php echo $this->Form->input('date_from', array(
						'label' => false, 
						'class' => 'datepicker',
						'type' => 'text',
						'data-date-autoclose' => true,
						'placeholder' => 'Start date',
						'value' => isset($this->request->data['date_from']) ? $this->request->data['date_from'] : null
					)); ?> 
					<?php echo $this->Form->input('date_to', array(
						'label' => false, 
						'class' => 'datepicker',
						'type' => 'text',
						'data-date-autoclose' => true,
						'placeholder' => 'End date',
						'value' => isset($this->request->data['date_to']) ? $this->request->data['date_to'] : null
					)); ?>
					<?php echo $this->Form->input('type', array(
						'label' => false, 						
						'type' => 'select',
						'options' => array(
							'polls' => 'Polls'
						),						
						'selected' => isset($this->request->data['type']) ? $this->request->data['type'] : null
					)); ?>
				</div>
				<div class="clearfix"></div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Generate Report', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>