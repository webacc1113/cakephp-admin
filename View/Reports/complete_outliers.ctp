<?php echo $this->Html->css('/css/application.css'); ?>
	<div class="row-fluid">
	<div class="span8">
		<div class="box">
			<div class="box-header">
				<span class="title">Group Complete Outliers</span>
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
								'value' => '75',
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
								'required' => true,
								'value' => isset($this->request->data['Report']['group_key']) ? $this->request->data['Report']['group_key'] : null)
							);
						?>
				</div>
				<div class="form-actions">	
					<?php echo $this->Form->submit('See Complete Outliers', array(
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
					<p>This report will find all projects created within a time range within a given group. 
						From that data, it will then iterate through all Completes for each project within the selected group (regardless of when those clicks originated) and report on all Completes that are outside of the given average LOI of said project.</p>
					<p>The threshold for Complete percentage is defined by the threshold setting.</p>
					<p><span class="label label-red">IMPORTANT</span> Do not select too wide of a date range; otherwise the data pull will get very long!</p>
				</div>
			</div>
		</div>
	</div>
</div>
