<?php echo $this->Html->css('/css/application.css'); ?>
	<div class="row-fluid">
	<div class="span8">
		<div class="box">
			<div class="box-header">
				<span class="title">Long NQ / OQs</span>
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
						<?php echo $this->Form->input('threshold', array(
							'value' => '3',
							'label' => 'Set Long Threshold (in minutes)',
							'style' => 'width: 60px;'
						)); ?>
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
					<?php echo $this->Form->submit('See NQ/OQs', array(
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
					<p>This report will find all projects created within a timerange within a given group. 
						From that data, it will then iterate through all NQs and OQs for that project (regardless of when those clicks originated) and report on all long NQs/OQs.</p>
					<p>The threshold for long NQs/OQs is defined by the threshold setting.</p>
					<p><span class="label label-red">IMPORTANT</span> Do not select too wide of a date range; otherwise the data pull will get very long!</p>
				</div>
			</div>
		</div>
	</div>
</div>