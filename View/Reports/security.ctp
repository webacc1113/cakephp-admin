<script type="text/javascript">
	$(document).ready(function() {
		$("#btn_check_id").click(function() {
			$.ajax({
				type: "POST",
				url: "/reports/ajax_check_project_id",
				data: 'project_id=' + $('#ReportProjectId').val(),
				statusCode: {
					201: function(data) {
						if (data.status == 'success') {
							$('#message').html('<div class="alert alert-success">' + data.project_name + '</div>');
							$('#ReportReportId').val(data.report_id);
						}
						else {
							$('#message').html('<div class="alert alert-danger">' + data.error_message + '</div>');
							$('#ReportReportId').val('');
						}
					}
				}
			});
		});
	});
</script>
<div class="span5 reports">
	<?php echo $this->Form->create(); ?>
	<div class="box">
		<div class="box-header">
			<span class="title">Generate Security Report</span>
		</div>
		<div class="box-content">
			<div class="padded">
				<p>This report generator will take security failures on an <strong>existing report</strong>, and append MintVine security information to the data.</p>
				<div class="span4 ml0">
					<?php echo $this->Form->input('project_id', array(
						'label' => 'Project ID',
						'class' => 'auto',
						'type' => 'text',
					)); ?>
				</div>
				<div class="span4 ml0">
					<?php echo $this->Form->button('Check ID', array(
						'class' => 'btn btn-sm btn-primary btn-check-id',
						'type' => 'button',
						'id' => 'btn_check_id',
					)); ?>
				</div>
				<div class="clearfix"></div>
				<div class="span12 ml0" id="message">&nbsp;</div>
				<?php echo $this->Form->input('report_id', array(
					'type' => 'hidden'
				)); ?>
				<div class="clearfix"></div>
				<?php echo $this->Form->input('hashes', array(
					'label' => 'Limit to these respondent IDs',
					'type' => 'textarea'
				)); ?>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Generate Extended Report', array(
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
			<span class="title">Understanding the Security Report</span>
		</div>
		<div class="box-content">
			<div class="padded">
				<p>This security report will generate all NQ fraud &amp; NQ speeding.</p>
				<p>The extra columns in the report are as follows:</p>
				<dl>
					<dt>Is Mobile?</dt>
					<dd>Determines if the user took the survey on a mobile phone</dd>
					<dt>User Agent</dt>
					<dd>Describes the browser used to access the survey. This should be cross-checked against automated bots</dd>
					<dt>Overall Score</dt>
					<dd>Overall score determines the fraud score associated with a user (scored out of 100). 
						A score of <strong>38</strong> or greater indicates a NQ fraud. Various factors are included in determining whether this is a 
						fraudulent user, which are described below</dd>
					<dt>Country</dt>
					<dd>Determines the country of origin based on IP address. If the survey country does not match the IP address's country, <strong>Geo Check</strong> is set to 1.</dd>
					<dt>Timezone</dt>
					<dd>Shows the minute offset from GMT of the user's browser. Can be useful in determining what time zone they are actually in. A timezone that does not match the user's IP address gets scored to 1 for <strong>time check</strong>.</dd>
					<dt>Language</dt>
					<dd>Shows the language of their browser. If it does not match the survey's language, <strong>language_check</strong> is set to greater than zero (it will be a range).</dd>
					<dt>Is Proxy</dt>
					<dd>Determines if the user is coming from a proxy server, based on MaxMind's data. A proxy server access is considered highly suspicious.</dd>
				</dl>
			</div>
		</div>
	</div>
</div>