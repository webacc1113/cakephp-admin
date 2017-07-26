<div class="span4">
	<?php echo $this->Form->create(); ?>
	<div class="box">
		<div class="box-header">
			<span class="title">Export Project Data</span>
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
					'value' => isset($this->data['date_from']) ? $this->data['date_from']: date('m/d/Y', strtotime('-1 day'))
				)); ?> 
				<?php echo $this->Form->input('date_to', array(
					'label' => false, 
					'class' => 'datepicker',
					'label' => 'End date',
					'data-date-autoclose' => true,
					'value' => isset($this->data['date_to']) ? $this->data['date_to']: date('m/d/Y')
				)); ?>
				<?php echo $this->Form->input('filter_ids', array(
					'type' => 'textarea',
					'label' => 'Filter by these project IDs (optional; one per line)'
				)); ?>
				<?php echo $this->Form->input('suppress_empty_clicks', array(
					'type' => 'checkbox', 
					'label' => 'Suppress projects with no clicks',
					'checked' => true
				)); ?>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Export Projects', array(
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
			<span class="title">Understanding the Project Export</span>
		</div>
		<div class="box-content">
			<div class="padded">
				<p>This project will export the overall statistics for all projects within the selected date range. The date range operates on the <strong>creation</strong> date of the project.</p>
				<p><strong>This report excludes routers as well as projects with 0 clicks</strong>.</p>
				<p>In the export, you'll find the following fields:</p>
				<dl>
					<dt>Project ID</dt>
						<dd>The internal ID we utilize for this project.</dd>
					<dt>Status</dt>
						<dd>This marks the current status of the project. It can be Open, Closed, or Closed &amp; Invoiced</dd>
					<dt>Project Name</dt>
					<dd>This is an internal name for the project; it sometimes gives us hints on how it was classified.</dd>
					<dt>Client</dt>
					<dd>The client for whom we were providing panelists for.</dd>
					<dt>Group</dt>
					<dd>Groups are used to classify projects based on what API they come from. MintVine is a special case, where our PM team bids and creates these projects manually. The other groups are all automated.</dd>
					<dt>Created</dt>
					<dd>Creation date of the project (in GMT)</dd>
					<dt>Started</dt>
					<dd>Start date of the project (in GMT)</dd>
					<dt>Ended</dt>
					<dd>End date of the project (in GMT)</dd>
					<dt>Bid IR</dt>
					<dd>The expected IR of the project (IR is percentage of completes / clicks)</dd>
					<dt>Bid LOI</dt>
					<dd>The expected Length of Interview (how long it'd take for a panelist to complete the survey)</dd>
					<dt>Client Rate</dt>
					<dd>The amount we receive for each complete</dd>
					<dt>User Payout</dt>
					<dd>The amount we pay out to panelists for each complete</dd>
					<dt>Prescreener</dt>
					<dd>Did this project have prescreeners? If so, then we asked a few qualifying questions before the panelist entered the survey (see the P-* columns below)</dd>
					<dt>Language</dt>
					<dd>Language of the survey</dd>
					<dt>Actual IR</dt>
					<dd>The actual IR as a percentage</dd>
					<dt>Actual EPC</dt>
					<dd>The Earnings Per Click - calculated as total revenue / total clicks</dd>
					<dt>Actual LOI</dt>
					<dd>The actual LOI from our completes (we throw away the low and top end of these values to create a more accurate measurement)</dd>
					<dt>Total Revenue</dt>
					<dd>The total amount earned - calculated with client rate * total completes</dd>
					<dt>Invites</dt>
					<dd># of panelists that were invited (Note: Lucid projects do NOT export this data)</dd>
					<dt>Clicks</dt>
					<dd># of clicks</dd>
					<dt>Completes</dt>
					<dd># of completes</dd>
					<dt>NQs</dt>
					<dd># of panelists who were disqualified by the client</dd>
					<dt>OQs</dt>
					<dd># of panelists who were uanble to access the project because there was no more quota available for them to take</dd>
					<dt>OQ-I</dt>
					<dd># of times we ran into an internal error with MintVine trying to serve up the project</dd>
					<dt>NQ-S</dt>
					<dd># of panelists who were disqualified for speeding through the survey (not reading the questions, just randomly answering questions)</dd>
					<dt>NQ-F</dt>
					<dd># of panelists who were disqualified for fraud checks - wrong country, proxy IP addresses, etc.</dd>
					<dt>P-CL</dt>
					<dd># of panelists who clicked into the project and received the prescreener.</dd>
					<dt>P-C</dt>
					<dd># of panelists who completed the prescreener and were able to access the project.</dd>
					<dt>P-NQ</dt>
					<dd># of panelists who completed the prescreener and were disqualified - the NQs field also contains all of the P-NQ values.</dd>
					<dt>Hidden - No Reason</dt>
					<dd># of users who skipped the survey for no reason</dd>
					<dt>Hidden - Too Long</dt>
					<dd># of users who skipped the survey for being too long</dd>
					<dt>Hidden - Payout Too Small</dt>
					<dd># of users who skipped the survey for paying too little</dd>
					<dt>Hidden - Not Working</dt>
					<dd># of users who skipped the survey because it did not work</dd>
					<dt>Hidden - Do Not Want</dt>
					<dd># of users who skipped the survey because they didn't want it</dd>
					<dt>Hidden - Other</dt>
					<dd># of users who skipped the survey for other reasons</dd>
				</dl>
			</div>
		</div>
	</div>
</div>