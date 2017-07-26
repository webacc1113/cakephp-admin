<div class="row-fluid padded">
	<div class="span4">
		<?php echo $this->Form->create(); ?>
		<div class="box">
			<div class="box-header">
				<span class="title">Client Analysis Report</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<?php echo $this->Form->input('group_id', array(
						'type' => 'select',
						'label' => 'Group',
						'options' => $groups,
						'empty' => 'Select a group',
						'value' => isset($this->request->data['Report']['group_id']) ? $this->request->data['Report']['group_id'] : null
					));	?>
					<?php echo $this->Form->input('date_from', array(
						'label' => false, 
						'class' => 'datepicker',
						'data-date-autoclose' => true,
						'label' => 'Start date',
						'value' => isset($this->data['date_from']) ? $this->data['date_from']: date('m/d/Y', strtotime('-1 month'))
					)); ?>
					<?php echo $this->Form->input('date_to', array(
						'label' => false, 
						'class' => 'datepicker',
						'label' => 'End date',
						'data-date-autoclose' => true,
						'value' => isset($this->data['date_to']) ? $this->data['date_to']: date('m/d/Y')
					)); ?>
				</div>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Export Client Analysis', array(
					'class' => 'btn btn-sm btn-primary',
					'disabled' => false
				)); ?>
			</div>
		</div>
		<?php echo $this->Form->end(null); ?>
	</div>
	<div class="span8">
		<div class="box">
			<div class="box-header">
				<span class="title">Understanding the Client Analysis Report</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<p>A client analysis report will collect data about all the clients for a certain group over a desired period of time that way we can see which clients have a reputation with us for performing very well and not so well.</p>
					<p>This report will export the overall statistics for all projects within the selected date range. The date range operates on the projects a client had <strong>open</strong> during that specified time frame.</p>
					<p>In the export, you'll find the following fields:</p>
					<dl>
						<dt>Client Name</dt>
						<dd>Name of the client</dd>
						<dt>Project Count</dt>
						<dd>Total number of projects that client had open during that time frame</dd>
						<dt>EPC</dt>
						<dd>Total Revenue/ Clicks</dd>
						<dt>Total Revenue</dt>
						<dd>Sum of total revenue for every project for that client. (client rate * total completes)</dd>
						<dt>Total Clicks</dt>
						<dd>Sum of total clicks for every project for that client</dd>
						<dt>Total Completes</dt>
						<dd>Sum of total completes for every project for that client</dd>
						<dt>Total NQs</dt>
						<dd>Sum of panelists who were disqualified by the client for each project</dd>
						<dt>Total OQs</dt>
						<dd>Sum of panelists who were unable to access the projects for that client because there was no more quota available for them to take</dd>
						<dt>Total OQ-I</dt>
						<dd>Sum of times we ran into an internal error with MintVine trying to serve up the projects for that client</dd>
						<dt>Total NQ-S</dt>
						<dd>Sum of panelists who were disqualified for speeding through the survey (not reading the questions, just randomly answering questions)</dd>
						<dt>Total NQ-F</dt>
						<dd>Sum of panelists who were disqualified for fraud checks - wrong country, proxy IP addresses, etc.</dd>
						<dt>Total P-CL</dt>
						<dd>Sum of panelists who clicked into the projects for that client and received the prescreener.</dd>
						<dt>Total P-C</dt>
						<dd>Sum of panelists who completed the prescreener and were able to access the projects for that client.</dd>
						<dt>Total P-NQ</dt>
						<dd>Sum of panelists who completed the prescreener and were disqualified - the NQs field also contains all of the P-NQ values.</dd>
						<dt>Avg Bid IR</dt>
						<dd>Average of the expected IR for all the projects for that client. (IR is percentage of completes / clicks)</dd>
						<dt>Avg Bid LOI</dt>
						<dd>Average of the expected Length of Interview for all the projects for that client. (how long it'd take for a panelist to complete the survey)</dd>
						<dt>Avg Client Rate</dt>
						<dd>Average of the amount we receive for each complete for all the projects for that client.</dd>
						<dt>Avg User Payout</dt>
						<dd>Average of the amount we have paid to panelists for each complete for all the projects for that client</dd>
						<dt>Avg Actual IR</dt>
						<dd>Average of the actual IR as a percentage for all the projects for that client.</dd>
						<dt>Avg Actual LOI</dt>
						<dd>Average of the actual LOI from our completes for all the projects for that client. (we throw away the low and top end of these values to create a more accurate measurement)</dd>
						<dt>LOI Diff</dt>
						<dd>Difference between average actual LOI and average bid LOI for all the projects for that client.</dd>
						<dt>IR Diff</dt>
						<dd>Difference between average actual and average IR for all the projects for that client.</dd>
						<dt>Standard Deviation</dt>
						<dd>Standard deviation of EPC across projects for a client in a given time frame.</dd>
					</dl>
				</div>
			</div>
		</div>
	</div>
</div>