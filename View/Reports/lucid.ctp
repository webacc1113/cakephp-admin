<div class="span6">
	<?php echo $this->Form->create(null, array('type' => 'file')); ?>
	<div class="box">
		<div class="box-header">
			<span class="title">Generate Lucid Report</span>
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
			<span class="title">Understanding the Lucid Report</span>
		</div>
		<div class="box-content">
			<div class="padded">
				<p>This feature takes a CSV generated report from Lucid and extracts the min and maximum timestamps of completes.</p>
				<p>Using this, it goes through all projects in the "Lucid" group and attempts to align their hashes with ours, as well as ensuring 
					all of our hashes are accounted for.</p>
				<p>This report will spit out a report of total discrepancy counts for both.</p>
			</div>
		</div>
	</div>
</div>
