<h3>View Notification Template: <?php echo $notification_template['NotificationTemplate']['name']; ?></h3>
<p><?php echo $notification_template['NotificationTemplate']['description']; ?>.</p>
<p>Total max emails sent with this profile in a day: <strong><?php 
	echo $notification_template['NotificationTemplate']['total_emails']; 
?></strong></p>
	
<div class="row-fluid">
	<div class="box">
		<table cellpadding="0" cellspacing="0" class="table table-normal">
			<thead>
				<tr>
					<td>GMT</td>
					<?php for ($i = 0; $i < 24; $i++): ?>
						<td><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>:00</td>
					<?php endfor; ?>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td></td>
					<?php for ($i = 0; $i < 24; $i++): ?>
						<?php $value = $notification_template['NotificationTemplate'][str_pad($i, 2, '0', STR_PAD_LEFT)]; ?>
						<td>
							<?php if ($value > 0): ?>
								<?php echo $value; ?>
							<?php else: ?>
								<span class="muted">-</span>
							<?php endif; ?>
						</td>
					<?php endfor; ?>
				</tr>
			</tbody>
		</table>
	</div>
</div>