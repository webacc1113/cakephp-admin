<div class="box" style="margin-bottom: 0;">
	<table cellpadding="0" cellspacing="0" class="table table-normal" id="notification_template_table">
		<thead>
			<tr>
				<td>GMT</td>
				<?php for ($i = 0; $i < 24; $i++): ?>
					<td><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>:00</td>
				<?php endfor; ?>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($notification_templates as $notification_template): ?>
			<tr style="cursor: pointer;" id="<?php echo $notification_template['NotificationTemplate']['id']; ?>">
				<td></td>
				<?php for ($i = 0; $i < 24; $i ++): ?>
					<?php $value = $notification_template['NotificationTemplate'][str_pad($i, 2, '0', STR_PAD_LEFT)]; ?>
					<td>
						<?php if ($value > 0): ?>
							<span><?php echo $value; ?></span>
						<?php else: ?>
							<span class="muted">-</span>
						<?php endif; ?>
					</td>
				<?php endfor; ?>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<script type="text/javascript">
	var notification_schedule_id = <?php echo $notification_schedule_id ;?>;
	$(document).ready(function() {
		$('#notification_template_table tbody tr').click(function() {
			var notification_template_id = $(this).attr('id');
			$(this).addClass('clicked');
			$.ajax({
				type: 'POST',
				url: '/notification_schedules/ajax_overwrite_profile/' + notification_schedule_id,
				data: {notification_template_id: notification_template_id},
				statusCode: {
					201: function (data) {
						var i = 0;
						$('#notification_template_table #' + notification_template_id + ' td').each(function() {
							if (i != 0) {
								var value = $(this).find('span').text();
								$('#notification_schedule_table tbody #schedule_row td').eq(i).text(value);
							}
							i ++;
						});
						$('#modal-overwrite-profile button').trigger('click');
					}
				}
			});
		});
	});
</script>