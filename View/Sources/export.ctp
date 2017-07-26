<?php echo $this->Form->create(null, array('type' => 'file', 'class' => 'filter')); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Export Data</span>
	</div>
	<div class="box-content">
		<div class="padded separate-sections">
			<div class="row-fluid">
				<p>This will dump all user data on a given source.</p>
				<div class="alert alert-danger">
					This could take a while - give it about 10-15 minutes. And check your spam folder. And try not to run this during peak load times. 
				</div>
				<div class="filter date-group">
					<p>Date between:</p>
					<?php echo $this->Form->input('date_from', array(
						'label' => false, 
						'class' => 'datepicker',
						'data-date-autoclose' => true,
						'placeholder' => 'Start date',
						'value' => isset($this->request->data['date_from']) ? $this->request->data['date_from'] : null
					)); ?> 
					<?php echo $this->Form->input('date_to', array(
						'label' => false, 
						'class' => 'datepicker',
						'data-date-autoclose' => true,
						'placeholder' => 'End date',
						'value' => isset($this->request->data['date_to']) ? $this->request->data['date_to'] : null
					)); ?>
				</div>
				<div class="clearfix"></div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Generate & Send Report', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>