<?php $this->Html->script('reports.js', array('inline' => false)); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Generate Report</span>
	</div>
	<div class="box-content">		
		<?php echo $this->Form->create('Report', array()); ?>
		<div class="padded"><?php 
			echo $this->Form->input('project', array(
				'label' => false, 
				'placeholder' => 'Project ID',
				'value' => isset($this->request->query['project']) ? $this->request->query['project'] : null
			)); 
			echo '<p>'.$this->Html->link(
				'Find partners', 
				'#', 
				array('class' => 'btn btn-sm btn-default', 'onclick' => 'return partner_list(this)')
			).'</p>'; 
			
			echo $this->Form->input('partner_id', array(
				'label' => false, 
				'empty' => 'All partners',
				'options' => array(),
				'style' => 'display: none;'
			)); 
			
			echo $this->Form->input('hashes', array(
				'type' => 'textarea',
				'after' => '<small class="text-muted">One hash per line</small>'
			));
		?></div>
		
		<div class="form-actions">	
			<?php echo $this->Form->submit('Generate Report', array(
				'class' => 'btn btn-sm btn-primary',
				'disabled' => false
			)); ?>
			<?php echo $this->Form->end(null); ?>
		</div>
	</div>
</div>