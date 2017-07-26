<?php echo $this->Form->create(null); ?>
	<div class="box">
		<div class="box-header">
			<span class="title">Generate User Notification Reports</span>
		</div>
		<div class="box-content">
			<div class="padded">
				<?php echo $this->Form->input('hours', array(
					'type' => 'text',
					'label' => 'Report since last',
					'value' => isset($this->request->data['UserNotificationReport']['hours']) ? $this->request->data['UserNotificationReport']['hours'] : '24',
					'after' => '<label>hours:</label>',
					'required' => true
				)); ?>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Generate Report', array('class' => 'btn btn-primary')); ?>
			</div>
		</div>
	</div>
<?php echo $this->Form->end(); ?>



<?php $this->Html->script('jquery.timer', array('inline' => false)); ?>
<script type="text/javascript">

	var timer = $.timer(function() {
		$('tr[data-status="queued"]').each(function() {
			var $node = $(this);
			var id = $node.data('id');
			$.ajax({
				type: "GET",
				url: "/user_notification_reports/report_check/" + id,
				statusCode: {
					201: function(data) {
						if (data.status == 'complete') {
							$node.data('status', 'complete');
							$('tr[data-id="'+id+'"]').data('status', 'complete');
							$('span.label', $node).removeClass('label-gray').addClass('label-green').text('Ready');
							$('a.btn-download', $node).attr('href', data.file).show();
							$('span.btn-waiting', $node).hide();
						}
					}
				}
			});
		});
	});

	timer.set({ time : 4000, autostart : true });
</script>
<div class="box">
	<div class="box-header">
		<span class="title">User Notification Reports</span>	
	</div>
	<div class="box-content">
		<table class="table table-normal">
			<tr>				
				<th>Created At</th>
				<th>Report Generated for Last</th>
				<th>Generated By</th>
				<th></th>
			</tr>
			<?php foreach ($user_notification_reports as $user_notification_report) : ?>
				<tr  data-id="<?php echo $user_notification_report['UserNotificationReport']['id']; ?>" data-status="<?php echo $user_notification_report['UserNotificationReport']['status']; ?>">					
					<td><?php echo $this->Time->format($user_notification_report['UserNotificationReport']['created'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, $timezone); ?></td>
					<td>
						<?php echo $user_notification_report['UserNotificationReport']['hours']. ' Hours'; ?>
					</td>
					<td class="muted"><?php echo $user_notification_report['Admin']['admin_user']; ?></td>
					<td><?php 
						if ($user_notification_report['UserNotificationReport']['status'] == 'complete') {
							echo $this->Html->link('Download', array('controller' => 'user_notification_reports', 'action' => 'download', $user_notification_report['UserNotificationReport']['id']), array('class' => 'btn btn-small btn-primary', 'title' => 'Download')); 
						}
						else {
							echo '<span class="btn-waiting">'.$this->Html->image('ajax-loader.gif').' Generating... please wait</span>';
							echo $this->Html->link('Download', '#', array('style' => 'display: none;', 'class' => 'btn btn-small btn-primary btn-download', 'target' => '_blank'));
						} ?>	
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<div class="form-actions">
			<?php echo $this->Element('pagination'); ?>
		</div>
	</div>
</div>