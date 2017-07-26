<div class="box">
	<div class="box-header">
		<span class="title">
			Reconciliation Logs
		</span>
	</div>
	<div class="box-content">
		<?php if ($reconciliation_logs): ?>
			<table class="table table-normal">
				<tr>
					<th>Type</th>
					<th>Description</th>
					<th>Hash</th>
					<th>Project id</th>
					<th>User id</th>
					<th>Created</th>
				</tr>
				<?php foreach ($reconciliation_logs as $log): ?>
					<tr>
						<td>
							<?php echo $log['ReconciliationLog']['type']; ?>
						</td>
						<td>
							<?php echo $log['ReconciliationLog']['description']; ?>
						</td>
						<td>
							<?php echo $log['ReconciliationLog']['hash']; ?>
						</td>
						<td>
							<?php echo $log['ReconciliationLog']['project_id']; ?>
						</td>
						<td>
							<?php echo $log['ReconciliationLog']['user_id']; ?>
						</td>
						<td>
							<?php echo $this->Time->format($log['ReconciliationLog']['created'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, $timezone); ?>
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