<div class="box">
	<div class="box-header">
		<span class="title">Recent Notification Logs</span>
	</div>
	<div class="box-content">
		<table class="table table-normal">
			<thead>
				<tr>
					<th>Receipient</th>
					<th>Status</th>
					<th>Project</th>
					<th>Priority</th>
					<th>Notification Datetime</th>
					<th>Project IR</th>
					<th>Project Completes</th>
					<th>Project Clicks</th>
					<th>Project Rate</th>
				</tr>
			</thead>
			<tbody>
				<?php $project_priorities = unserialize(PROJECT_PRIORITY_OPTIONS); ?>
				<?php foreach ($notification_logs as $notification_log): ?>
					<tr>
						<td><?php 
							echo $this->Html->link($notification_log['NotificationLog']['email'], array(
								'controller' => 'notification_logs',
								'action' => 'user',
								$notification_log['NotificationLog']['user_id']
							)); 
						?></td>
						<td>
							<?php if ($notification_log['NotificationLog']['sent']): ?>
								<span class="label label-success">SENT</span>
							<?php else: ?>
								<span class="label label-default">SKIPPED</span>
							<?php endif; ?>
						</td>
						<td><?php 
							echo $this->Html->link('#'.$notification_log['NotificationLog']['project_id'], array(
								'controller' => 'surveys',
								'action' => 'dashbaord',
								$notification_log['NotificationLog']['project_id']
							)); 
						?></td>
						<td><?php echo $project_priorities[$notification_log['Project']['priority']]; ?></td>
						<td><?php 
							echo $this->Time->format($notification_log['NotificationLog']['created'], Utils::dateFormatToStrftime(DB_DATETIME), false, $timezone); 
						?></td>
						<td>
							<?php if (!is_null($notification_log['NotificationLog']['click_timestamp'])): ?>
								<?php
									echo $this->Time->format($notification_log['NotificationLog']['click_timestamp'], Utils::dateFormatToStrftime(DB_DATETIME), false, $timezone); 
								?>
							<?php endif; ?>
						</td>
						<td>
							<?php if (!empty($notification_log['Project']['SurveyVisitCache']['ir'])): ?>
								<?php echo $notification_log['Project']['SurveyVisitCache']['ir']; ?>%
							<?php else: ?>
								<span class="muted">-</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ($notification_log['Project']['SurveyVisitCache']['complete'] > 0): ?>
								<?php echo $notification_log['Project']['SurveyVisitCache']['complete']; ?>
							<?php else: ?>
								<span class="muted">-</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ($notification_log['Project']['SurveyVisitCache']['click'] > 0): ?>
								<?php echo $notification_log['Project']['SurveyVisitCache']['click']; ?>
							<?php else: ?>
								<span class="muted">-</span>
							<?php endif; ?>
						</td>
						<td>
							$<?php echo number_format($notification_log['Project']['client_rate'], 2); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<?php echo $this->Element('pagination'); ?>