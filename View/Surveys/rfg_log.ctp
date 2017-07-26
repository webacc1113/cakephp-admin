<?php
$types = array(
	'0' => 'No result',
	'1' => 'Complete',
	'2' => 'Terminate',
	'3' => 'Over-quota',
	'4' => 'Reserved',
	'5' => 'Survey closed',
	'6' => 'Security',
	'7' => 'Early terminate',
	'8' => 'Duplicate',
	'9' => 'Early quota',
	'10' => 'Customer security',
)
?>
<div class="box">
	<div class="box-header">
		<span class="title">RFG Project Logs</span>
	</div>
	<div class="box-content">
		<?php if (isset($logs) && !empty($logs['response']['log'])): ?>
			<table class="table table-normal">
				<tr>
					<th>Type</th>
					<th>Start</th>
					<th>End</th>
					<th>Loi</th>
					<th>SessKey</th>
					<th>rid</th>
				</tr>
				<?php foreach ($logs['response']['log'] as $log): ?>
					<tr>
						<td>
							<?php echo (isset($types[$log['result']]) ? $types[$log['result']] : '-'); ?>
						</td>
						<td>
							<?php echo $log['start']; ?>
						</td>
						<td>
							<?php echo $log['end']; ?>
						</td>
						<td>
							<?php echo $log['loi']; ?>
						</td>
						<td>
							<?php echo $log['sesskey']; ?>
						</td>
						<td>
							<?php echo $log['rid']; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		<?php else: ?>
			<div class="alert alert-danger">Logs not found.</div>
		<?php endif; ?>
	</div>
</div>
