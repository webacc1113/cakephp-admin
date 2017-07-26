<?php echo $this->Form->create('DailyAnalysisProperty', array('novalidate' => true)); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Add Daily Analysis Properties</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span4">
				<div class="padded"><?php 
					echo $this->Form->input('properties', array(
						'label' => 'Properties',
						'type' => 'textarea'
					)); 
				?></div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Add Properties', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>