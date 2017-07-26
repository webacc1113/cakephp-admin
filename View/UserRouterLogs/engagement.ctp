<div class="span6">
		<div class="box">
		<div class="box-header">
			<span class="title">Router Engagement Report</span>
		</div>
		<div class="box-content">
			<div class="padded">
				<?php echo $this->Form->create(null, array(
					'class' => 'clearfix form-inline',
					'type' => 'post'
				));
				?>
				<div class="form-group">
					<?php echo $this->Form->input('date', array(
						'label' => false, 
						'class' => 'datepicker',
						'data-date-autoclose' => true,
						'placeholder' => 'Date',
						'value' => date('m/d/Y')
					)); ?>
				</div>
				<div class="form-group">
					<?php echo $this->Form->submit('Download CSV', array('class' => 'btn btn-default')); ?>
				</div>
				<?php echo $this->Form->end(null); ?>
			</div>
		</div>
	</div>
</div>
<div class="span6">
	<div class="box">
		<div class="box-header">
			<span class="title">Understanding the Engagement Export</span>
		</div>
		<div class="box-content">
			<div class="padded">
				<p>The engagement CSV contains all the MV router session data broken down per-panelist.</p>
				<dl>
					<dt>Started Sessions</dt>
					<dd>This indicates the number of times the panelist entered the router</dd>
					<dt>Unique Surveys</dt>
					<dd>This counts - across all sessions - the number of surveys the panelist was invited to.</dd>
					<dt>Click/Complete/etc</dt>
					<dd>These are the # of statuses the panelists received.</dd>
				</dl>
			</div>
		</div>
	</div>
</div>