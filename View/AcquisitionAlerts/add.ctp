<div class="row-fluid">
	<div class="span8">
		<?php echo $this->Form->create(null); ?>
		<div class="box">
			<div class="box-header">
				<span class="title">Create Alert</span>
			</div>
			<div class="box-content">
				<div class="row-fluid">
					<div class="padded">
						<?php echo $this->Form->input('name', array('label' => 'Name')); ?>
						<?php echo $this->Form->input('source_mapping_id', array(
							'empty' => 'Select Source Mapping:',
							'type' => 'select',
							'label' => 'Select Source Mapping or Campaign:',
							'options' => $source_mappings
						)); ?>
						<?php echo $this->Form->input('source_id', array(
							'empty' => 'Select Campaign:',
							'type' => 'select',
							'label' => false,
							'options' => $sources
						)); ?>
						<?php echo $this->Form->input('event', array(
							'empty' => 'Select Event:',
							'type' => 'select', 
							'label' => 'Trigger Event:',
							'options' => $events
						)); ?>
						<?php echo $this->Form->input('amount', array('type' => 'text', 'label' => 'Current Count', 'style' => 'width: 80px;')); ?>
						<?php echo $this->Form->input('trigger', array('type' => 'text', 'label' => 'Trigger Amount', 'style' => 'width: 80px;')); ?>
						<?php echo $this->Form->input('description', array('label' => 'Alert Text Posted in Slack')); ?>
						<?php echo $this->Form->input('alert_threshold_minutes', array(
							'label' => 'Time between re-alerts (in minutes)',
							'type' => 'text',
							'after' => ' <small>At least 5 minutes, at most 1440 minutes</small>',
							'value' => 240 
						)); ?>
					</div>
				</div>
				<div class="form-actions">
					<?php echo $this->Form->submit('Create Alert', array('class' => 'btn btn-primary')); ?>
				</div>
			</div>
		</div>
		<?php echo $this->Form->end(null); ?>
	</div>

	<div class="span4">
		<div class="box">
			<div class="box-header">
				<span class="title">Acquisition Alerts</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<p>This feature allows you to specify a countdown timer against various user acquisition events and source/campaigns. This allows you to get an advanced notice when certain campaigns or source mappings are exhausting their quotas.</p>
					<p>To set this up, first define either a campaign or source mapping to monitor. You may only monitor one at a time.</p>
					<p>Then, set up the event type to monitor. Currently registrations, verifications, and survey starts are supported.</p>
					<p>You then input the current count you'd like to monitor - this value will be de-incremented every time a user that comes through the campaign/source mapping completes your event.</p>
					<p>The trigger amount is when a Slack notification should be posted - set it at some level above the exhaustion point so you get advanced notice!</p>
					<p>The alert text is what will be posted to the Slack channel that has been set-up for this feature; you should use @ to notify yourself (or other people), as well as description of which campaign/source is running low. No additional information 
						will be posted by the bot - <strong>you must define this alert text yourself</strong>.</p>
					<p>Once you've hit your trigger amount, every subsequent user event will cause an alert: the time between alerts simply allows you to define the amount of time between alerts so you're not flooded.</p>
				</div>
			</div>
		</div>
	</div>
</div>