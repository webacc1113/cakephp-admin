<div class="box">
	<div class="box-header">
		<span class="title">User #<?php echo $user_id; ?> Notification Log</span>
	</div>
	<div class="box-content">
		<table class="table table-normal">
			<thead>
				<tr>
					<th>Status</th>
					<th>Project</th>
					<th>Priority</th>
					<th>Notification Datetime</th>
					<th>Accessed Datetime</th>
					<th>Access</th>
					<th>Project IR</th>
					<th>Project Completes</th>
					<th>Project Clicks</th>
					<th>Project Rate</th>
				</tr>
			</thead>
			<tbody>
				<?php $project_priorities = unserialize(PROJECT_PRIORITY_OPTIONS); ?>
				<?php foreach ($notification_logs as $notification_log): ?>
					<?php
					if (!is_null($notification_log['NotificationLog']['click_timestamp'])) {
						$accessed_datetime = $notification_log['NotificationLog']['click_timestamp']; 
					}
					elseif (isset($survey_user_visits[$notification_log['NotificationLog']['project_id']])) {
						$accessed_datetime = $survey_user_visits[$notification_log['NotificationLog']['project_id']]['SurveyUserVisit']['created'];
					}
								
					$diff = strtotime($accessed_datetime) - strtotime($notification_log['NotificationLog']['created']); 
					?>
					<tr>
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
							<?php
								echo $this->Time->format($accessed_datetime, Utils::dateFormatToStrftime(DB_DATETIME), false, $timezone); 
								if ($diff > 0) {
									echo ' ('.number_format(round($diff / 60)).' minutes)';
								}
							?>
						</td>
						<td>
							<?php if (!is_null($notification_log['NotificationLog']['click_timestamp'])): ?>
								<span class="label label-success">VIA EMAIL</span>
							<?php elseif (isset($survey_user_visits[$notification_log['NotificationLog']['project_id']])): ?>
								<span class="label label-default">VIA ROUTER</span>
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