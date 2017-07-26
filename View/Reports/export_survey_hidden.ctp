<h3>Survey Hidden Data</h3>

<div class="box">
	<div class="box-header">
		<span class="title">Filters</span>
		<ul class="box-toolbar">
			<li>
				<?php echo $this->Html->link('<i class="icon-remove-sign"></i> Clear filters', array('action' => 'sampling_percentage'), array('escape' => false)); ?>
			</li>
		</ul>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create('Report', array('type' => 'post', 'class' => 'filter')); ?>
			<div class="padded separate-sections">
				<div class="row-fluid">
					<div class="filter date-group">
						<label>Report date between:</label>
						<?php echo $this->Form->input('date_from', array(
							'label' => false, 
							'class' => 'datepicker',
							'data-date-autoclose' => true,
							'placeholder' => 'Start date',
							'value' => isset($this->data['date_from']) ? $this->data['date_from']: null
						)); ?>
						<?php echo $this->Form->input('date_to', array(
							'label' => false, 
							'class' => 'datepicker',
							'placeholder' => 'End date',
							'data-date-autoclose' => true,
							'value' => isset($this->data['date_to']) ? $this->data['date_to']: null
						)); ?>
					</div>
					<?php echo $this->Form->input('type', array(
						'type' => 'select',
						'empty' => 'Select a reason:',
						'options' => unserialize(SURVEY_HIDDEN)
					)); ?>
				</div>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Search', array('class' => 'btn btn-primary')); ?>
			</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>
