<div class="box">
	<div class="box-header">
		<span class="title">User Activity Logs</span>
	</div>
	<div class="box-content">
		<?php if ($user_logs): ?>
			<table class="table table-normal">
				<tr>
					<th>Type</th>
					<th>Description</th>
					<th>Action Taken</th>
				</tr>
				<?php foreach ($user_logs as $user_log): ?>
					<tr data-id="<?php echo $user_log['UserLog']['id']; ?>">
						<td>
							<?php echo $user_log['UserLog']['type']; ?>
						</td>
						<td>
							<?php echo $user_log['UserLog']['description']; ?>
						</td>
						
						<td>
							<?php echo $this->Time->format($user_log['UserLog']['created'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, $timezone); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		<?php endif; ?>
		<div class="form-actions">
			<?php echo $this->Element('pagination'); ?>
		</div>
	</div>
</div>