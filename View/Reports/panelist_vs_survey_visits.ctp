<?php echo $this->Html->css('/css/application.css'); ?>
	<div class="row-fluid">
	<div class="span8">
		<div class="box">
			<div class="box-header">
				<span class="title">Panelist Vs Survey Visits</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<?php echo $this->Form->create('Report'); ?>
						<?php 
							echo $this->Form->input('start_date', array(
							    'div' => 'input date',
							    'type' => 'date',
							    'minYear' => date('Y') - 1,
							    'maxYear' => date('Y'),
							)); 
						?>
						<?php 
							echo $this->Form->input('end_date', array(
							    'div' => 'input date',
							    'type' => 'date',
							    'minYear' => date('Y') - 1,
							    'maxYear' => date('Y'),
							)); 
						?>
						<?php 
							echo $this->Form->input('threshold', array(
								'value' => '10',
								'label' => 'Set Percent (% assumed)',
								'style' => 'width: 60px;'
							)); 
						?>
						<?php
							echo $this->Form->input('group_key', array(
								'type' => 'select',
								'label' => 'Group',
								'options' => $groups,
								'empty' => 'Select a group',
								'value' => isset($this->request->data['Report']['group_key']) ? $this->request->data['Report']['group_key'] : null)
							);
						?>
				</div>
				<div class="form-actions">	
					<?php echo $this->Form->submit('See Panelist vs Survey Visits', array(
						'class' => 'btn btn-sm btn-primary',
						'disabled' => false
					)); ?>
					<?php echo $this->Form->end(null); ?>
				</div>
			</div>
		</div>
	</div>
	<div class="span4">
		<div class="box">
			<div class="box-header">
				<span class="title">Understanding the Report</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<p>This report will find all users that have mismatched terminating actions between the Survey Visits and Panelist History records created within a time range within a given group.</p>
					<p>The threshold for  percentage is defined by the threshold setting.</p>
					<p><span class="label label-red">IMPORTANT</span> Do not select too wide of a date range; otherwise the data pull will get very long!</p>
				</div>
			</div>
		</div>
	</div>
</div>
