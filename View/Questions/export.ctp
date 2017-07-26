<?php echo $this->Form->create(); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Export Questions</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span6">
				<div class="padded">
					<?php echo $this->Form->input('country', array(
						'label' => 'Country',
						'type' => 'select',
						'options' => $supported_countries,
					)); ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Export', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>