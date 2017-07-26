<div class="box">
	<div class="box-header">
		<span class="title">Project Group Logs</span>
	</div>
	<div class="box-content">
		<?php if ($project_logs): ?>
			<table class="table table-normal">
				<tr>
					<th>Type</th>
					<th>Project ID</th>
					<th>Description</th>
					<th>User</th>
					<th>Action Taken</th>
				</tr>
				<?php foreach ($project_logs as $project_log): ?>
					<tr data-id="<?php echo $project_log['ProjectLog']['id']; ?>">
						<td>
							<?php echo $project_log['ProjectLog']['type']; ?>
						</td>
						<td>
							<?php echo $this->App->project_id($project_log); ?>
						</td>
						<td>
							<?php echo $project_log['ProjectLog']['description']; ?>
						</td>
						<td>
							<?php if (!empty($project_log['Admin']['id'])): ?>
								<?php echo $project_log['Admin']['admin_user']; ?>
							<?php else: ?>
								System
							<?php endif; ?>
						</td>
						<td>
							<?php echo $this->Time->format($project_log['ProjectLog']['created'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, $timezone); ?>
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
