<div class="span6">
	<?php echo $this->Form->create(null, array('type' => 'file')); ?>
	<div class="box">
		<div class="box-header">
			<span class="title">Reports</span>
		</div>
		<div class="box-content">
			<div class="padded">
				<?php echo $this->Form->input('file', array(
					'type' => 'file'
				)); ?>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Generate Report', array('class' => 'btn btn-primary')); ?>
			</div>
		</div>
	</div>

	<?php echo $this->Form->end(); ?>
</div>
<div class="span6">
	<div class="box">
		<div class="box-header">
			<span class="title">Understanding the Cint Report</span>
		</div>
		<div class="box-content">
			<div class="padded">
				<p>
					This feature takes a CSV generated report from Cint and compare it with our database. 
					Here is the <a href="https://www.cint.com/cpx3/Login" target="_blank">Cint Panel link</a> to generate the Cint CSV file.
					Use the settings in the image below.<br />
				</p>
				<?php echo $this->Html->image('panel_settings.png', array('class' => 'img-responsive')); ?>
			</div>
		</div>
	</div>
</div>
