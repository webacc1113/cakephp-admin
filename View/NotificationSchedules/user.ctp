<h3>Notification Profiles</h3>
<div class="box">
	<div class="box-header">
		<span class="title">Filters</span>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create('Transaction', array('type' => 'get', 'class' => 'filter')); ?>
			<div class="padded separate-sections">
				<div class="row-fluid">
					<label>Find by user:</label><?php 
						echo $this->Form->input('user_id', array(
							'value' => isset($this->request->query['user_id']) ? $this->request->query['user_id']: '',
							'type' => 'text',
							'label' => false
						)); 
					?> 
				</div>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Search', array('class' => 'btn btn-primary')); ?>
			</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>
<div class="row-fluid">
	<?php if (isset($notification_schedule)): ?>
		<div class="pull-right">
			<?php echo $this->Html->link('Edit Email Schedule', array(
				'controller' => 'notification_schedules',
				'action' => 'edit_schedule',
				$notification_schedule['NotificationSchedule']['id']
			)
			, array(
				'class' => 'btn btn-mini btn-default'
			));?>
			<?php echo $this->Html->link('Overwrite Profile', array(
				'controller' => 'notification_schedules',
				'action' => 'ajax_overwrite_profile',
				$notification_schedule['NotificationSchedule']['id']
			)
			, array(
				'class' => 'btn btn-mini btn-default',
				'data-target' => '#modal-overwrite-profile',
				'data-toggle' => 'modal'
			));?>
			<?php echo $this->Html->link((is_null($notification_schedule['NotificationSchedule']['locked']) ? 'Lock Profile' : 'Unlock Profile'),
				'#',
				array(
					'class' => 'btn btn-mini ' . (is_null($notification_schedule['NotificationSchedule']['locked']) ? 'btn-default' : 'btn-danger'),
					'onclick' => 'return MintVine.LockNotificationProfile('. $notification_schedule['NotificationSchedule']['id'] .', this)'
				)
			);?>
		</div>
		<p>This user is set to receive a total of: <strong><?php 
			echo $notification_schedule['NotificationSchedule']['total_emails']; 
		?></strong> emails. 
		<?php if ($user['User']['send_email']): ?>
			This user has <span class="label label-success">enabled</span> email sends.
		<?php else: ?>
			This user has <span class="label label-danger">disabled</span> email sends.
		<?php endif; ?>
		</p>
		<p>Note: the next hour will always show a '0' value for <strong>Current email count</strong> as we pre-emptively wipe the count.</p>
		<div class="box">
			<table cellpadding="0" cellspacing="0" class="table table-normal" id="notification_schedule_table">
				<thead>
					<tr>
						<td>GMT</td>
						<?php for ($i = 0; $i < 24; $i++): ?>
							<td><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>:00</td>
						<?php endfor; ?>
					</tr>
					<tr>
						<td>Local</td>
						<?php for ($i = 0; $i < 24; $i++): ?>
							<?php 
								$local_offset = $i + $hour_offset; 
								if ($local_offset < 0) {
									$local_offset = $local_offset + 24;
								}
								if ($local_offset >= 24) {
									$local_offset = $local_offset - 24;
								}
							?>
							<td><?php echo date("H:i", $local_offset * 3600); ?></td>
						<?php endfor; ?>
					</tr>
				</thead>
				<tbody>
					<tr id="schedule_row">
						<td><strong>Current Profile</strong></td>
						<?php for ($i = 0; $i < 24; $i ++): ?>
							<?php 
								$value = $notification_schedule['NotificationSchedule'][str_pad($i, 2, '0', STR_PAD_LEFT)]; 
								$bg_color = $i == date('H') ? ' style="background-color: #f5ffe5"': ''; 
							?>
							<td<?php echo $bg_color; ?>>
								<?php if ($value > 0): ?>
									<?php echo $value; ?>
								<?php else: ?>
									<span class="muted">-</span>
								<?php endif; ?>
							</td>
						<?php endfor; ?>
					</tr>
					
					<?php if ($user_notification): ?>
						<tr>
							<td><strong>Current email count (past 23 hours)</strong></td>

							<?php for ($i = 0; $i < 24; $i++): ?>
								<?php 
									$value = $user_notification['UserNotification'][str_pad($i, 2, '0', STR_PAD_LEFT)]; 
									$bg_color = $i == date('H') ? ' style="background-color: #f5ffe5"': ''; 
								?>
								<td<?php echo $bg_color; ?>>
									<?php if ($value > 0): ?>
										<?php echo $value; ?>
									<?php else: ?>
										<span class="muted">-</span>
									<?php endif; ?>
								</td>
							<?php endfor; ?>
						</tr>
					<?php else: ?>
						<tr>
							<td><strong>Current email count (past 23 hours)</strong></td>
							<td colspan="24" class="muted" style="text-align: center;">No active email activity stored</td>
						</tr>						
					<?php endif; ?>
					
					<tr>
						<td><strong>Clicked email invites (past 7 days)</strong></td>
						<?php for ($i = 0; $i < 24; $i++): ?>
							<?php 
								if (isset($clicked_notifications[str_pad($i, 2, '0', STR_PAD_LEFT)])) {
									$value = $clicked_notifications[str_pad($i, 2, '0', STR_PAD_LEFT)]; 
								}
								else {
									$value = 0;
								}
								$bg_color = $i == date('H') ? ' style="background-color: #f5ffe5"': ''; 
							?>
							<td<?php echo $bg_color; ?>>
								<?php if ($value > 0): ?>
									<?php echo $value; ?>
								<?php else: ?>
									<span class="muted">-</span>
								<?php endif; ?>
							</td>
						<?php endfor; ?>
					</tr>
					
					<tr>
						<td><strong>Skipped email invites (past 23 hours)</strong></td>

						<?php for ($i = 0; $i < 24; $i++): ?>
							<?php 
								if (isset($skipped_notifications[str_pad($i, 2, '0', STR_PAD_LEFT)])) {
									$value = $skipped_notifications[str_pad($i, 2, '0', STR_PAD_LEFT)]; 
								}
								else {
									$value = 0;
								}
								$bg_color = $i == date('H') ? ' style="background-color: #f5ffe5"': ''; 
							?>
							<td<?php echo $bg_color; ?>>
								<?php if ($value > 0): ?>
									<?php echo $value; ?>
								<?php else: ?>
									<span class="muted">-</span>
								<?php endif; ?>
							</td>
						<?php endfor; ?>
					</tr>
					
					<?php if ($user_activity_hour): ?>
						<tr>
							<td><strong>Total Lifetime Activity (%)</strong></td>
							<?php for ($i = 0; $i < 24; $i++): ?>
								<?php 
									$value = $user_activity_hour['UserActivityHour'][str_pad($i, 2, '0', STR_PAD_LEFT)]; 
									if ($user_activity_hour['UserActivityHour']['total'] > 0) {
										$pct = round($value / $user_activity_hour['UserActivityHour']['total'], 2) * 100; 
									}
									else {
										$pct = 0; 
									}
									$bg_color = $i == date('H') ? ' style="background-color: #f5ffe5"': ''; 
								?>
								<td<?php echo $bg_color; ?>>
									<?php if ($pct > 0): ?>
										<?php echo $pct; ?>%
									<?php else: ?>
										<span class="muted">-</span>
									<?php endif; ?>
								</td>
							<?php endfor; ?>
						</tr>
					<?php else: ?>
						<tr>
							<td><strong>Total Lifetime Activity (%)</strong></td>
							<td colspan="24" class="muted" style="text-align: center;">No lifetime activity calculated</td>
						</tr>						
					<?php endif; ?>
					<tr>
						<td colspan="25">Other Possible Templates</td>
					</tr>
					<?php foreach ($notification_templates as $notification_template): ?>
						<tr class="muted">
							<td><?php echo $notification_template['NotificationTemplate']['name']; ?></td>
							<?php for ($i = 0; $i < 24; $i++): ?>
								<?php 
									$value = $notification_template['NotificationTemplate'][str_pad($i, 2, '0', STR_PAD_LEFT)]; 
									$bg_color = $i == date('H') ? ' style="background-color: #f5ffe5"': ''; 
								?>
								<td<?php echo $bg_color; ?>>
									<?php if ($value > 0): ?>
										<?php echo $value; ?>
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
	<?php else: ?>
		<p class="muted">This panelist does not have a notification schedule set yet.</p>
	<?php endif; ?>
</div>
<div id="modal-overwrite-profile" style="width:80%; left:30%;" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Notification Templates</h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<button class="btn btn-default" id="save_btn" data-miss="modal">Close</button>
	</div>
</div>