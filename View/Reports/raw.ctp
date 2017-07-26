<?php $this->Html->script('reports.js', array('inline' => false)); ?>
<div class="span6">
	<div class="box">
		<div class="box-header">
			<span class="title">Generate Raw Report</span>
		</div>
		<div class="box-content">		
			<?php echo $this->Form->create('Report', array()); ?>
			<div class="padded"><?php 
				echo $this->Form->input('project', array(
					'label' => false, 
					'placeholder' => 'Project ID',
					'style' => 'width: auto;',
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
</div>

<div class="span6">
	<div class="box">
		<div class="box-header">
			<span class="title">Understanding the raw reports</span>
		</div>
		<div class="box-content">
			<div class="padded">
				<p>When you generate a standard report, the system does its best to compress each user's activity into a single click and terming action, even if they have multiple click entries.</p>
				<p>While useful for verifying term data, it's not very useful for debugging a "path" a user took to getting that complete for fraud/security checks.</p>
				<p>This report will dump from the database the raw traffic logs for a given survey. This can be very intensive, so please do your best by limiting by a partner.</p>
				<p>An explanation of the fields:</p>
				<ul>
					<li><strong>ID</strong> - the internal ID value for that visit. This is used for "result id" when mapping an exit to an entry</li>
					<li><strong>User ID</strong> - the user ID hash that the partner sent. For MintVine, the second value (separate by the dashes) is the MintVine user ID</li>
					<li><strong>Type</strong> - the data entry type; clicks that finish will have a result and result_id set.</li>
					<li><strong>Note</strong> - returns additional internal debug information about this request</li>
					<li><strong>Link</strong> - this is the link we redirected the user to. Note that for links that have us append data (like Points2Shop router), we will cut out the extra data we append</li>
					<li><strong>Hash</strong> - the internal hash we generate for that user to be sent to client</li>
					<li><strong>Referrer</strong> - the URL that this particular user came from for this entry. For terming events, it should be the client's survey. If an entry event, should be the partner's website. Some browsers will not report referrer information - that is OK.</li>
					<li><strong>Query String</strong> - the query string that is attached to this visit</li>
					<li><strong>Result</strong> - if there was an exit event tied to this entry, mark it here</li>
					<li><strong>Result ID</strong> - the ID that is attached to the entry value. Column 1 has these values</li>
					<li><strong>Created</strong> - the timestamp in Pacific of the creation of this visit</li>
					<li><strong>Modified</strong> - the timestamp in Pacific of any update saves of this visit. For entry events, this will be when the termining event writes back to the row</li>
					<li><strong>Server Info</strong> - a raw dump of the server variables for this visit. Of interest are <code>HTTP_USER_AGENT</code> which tells us the user's browser's user agent</li>
				</ul>
			</div>
		</div>
	</div>
</div>
