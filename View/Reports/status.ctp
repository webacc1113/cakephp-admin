<div class="span6">
	<?php echo $this->Form->create(); ?>
	<div class="box">
		<div class="box-header">
			<span class="title">Project Status Export</span>
		</div>
		<div class="box-content">
			<div class="padded">
				<p>Input a project identifier - one per line - and you will receive a CSV export of the project's status in MintVine.</p>
				<?php echo $this->Form->input('ids', array(
					'type' => 'textarea',
					'value' => isset($this->request->data['Report']['report_id']) ? $this->request->data['Report']['report_id']: null,
					'label' => 'Project ID (or CSV rows)',
					'class' => 'auto'
				)); ?>
				<?php echo $this->Form->input('type', array(
					'type' => 'select',
					'empty' => 'MintVine Project IDs',
					'options' => array(
						'fulcrum' => 'Lucid Project IDs'
					)
				)); ?>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Export Project Status', array(
				'class' => 'btn btn-sm btn-primary',
				'disabled' => false
			)); ?>
		</div>
	</div>
	<?php echo $this->Form->end(null); ?>
</div>
<div class="span6">
	<div class="box">
		<div class="box-header">
			<span class="title">Understanding the Report</span>
		</div>
		<div class="box-content">
			<div class="padded">
				<p>This report is used to quickly generate a status of all given MV projects. It will include the following:</p>
				<ul>
					<li>MintVine Project ID</li>
					<li>Mask Project ID</li>
					<li>Statistics</li>
					<li>Current Status</li>
					<li>IR/LOI Statistics</li>
					<li>Date Created</li>
					<li>Date Closed</li>
					<li>Total Panelists Invited</li>
				</ul>
				<p>For projects that cannot be found in Lucid, the system will return the last attempted action on survey link creation.</p> 
				<p>In the input field, you may also put in CSV values: if you do, each row's data will be appended to each matching project.</p>
				<p>Example would be (using Lucid projects):</p>
				<blockquote>
					181946, $	0.56<br/>
					178468, $	0.04<br/>
					184694, $	0.06
				</blockquote>
			</div>
		</div>
	</div>
</div>