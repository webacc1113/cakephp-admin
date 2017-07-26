<h2>Precision Offerwall Pull for #<?php echo $precision_log['PrecisionLog']['user_id']; ?></h2>
<h3><?php echo date(DB_DATETIME, strtotime($precision_log['PrecisionLog']['created'])); ?></h3>

<p><?php if (isset($next_log) && $next_log): ?>
	<?php echo $this->Html->link('< Next', array('action' => 'view', $next_log['PrecisionLog']['id'])); ?>
<?php else: ?>
	<span class="muted">&lt; Next</span>
<?php endif; ?>
  | 
<?php if (isset($prev_log) && $prev_log): ?>
	<?php 
		$diff = strtotime($precision_log['PrecisionLog']['created']) - strtotime($prev_log['PrecisionLog']['created']); 
		if ($diff < 60) {
			$diff = $diff.' seconds';
		}
		elseif ($diff < (60 * 60)) {
			$diff = round($diff / 60).' minutes';
		}
		elseif ($diff < (60 * 60 * 24)) {
			$diff = round($diff / 3600).' hours'; 
		}
		else {
			$diff = round($diff / (60 * 60 * 24)).' days'; 
		}
	?>
	<?php echo $this->Html->link('Previous ('.$diff.') >', array('action' => 'view', $prev_log['PrecisionLog']['id'])); ?>
<?php else: ?>
	<span class="muted">Previous &gt;</span>
<?php endif; ?></p>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Precision Name</td>
				<td>Description</td>
				<td>Duration</td>
				<td>Project ID</td>
				<td>Payout</td>
				<td>MV Status</td>
				<td>MV IR</td>
				<td>MV EPC</td>
				<td>C</td>
				<td>CL</td>
				<td>NQ</td>
				<td>OQ</td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($precision_logs as $precision_log): ?>
				<tr class="<?php
					echo in_array($precision_log['PrecisionLog']['status'], array(PROJECT_STATUS_CLOSED, PROJECT_STATUS_INVOICED)) || !$precision_log['PrecisionLog']['status_active'] ? 'muted': ''; 
				?>">
					<td><?php echo $precision_log['PrecisionLog']['name']; ?></td>
					<td><?php echo $precision_log['PrecisionLog']['description']; ?></td>
					<td><?php echo $precision_log['PrecisionLog']['duration']; ?></td>
					<td><?php 
						echo $this->Html->link($precision_log['PrecisionLog']['project_id'], array('controller' => 'surveys', 'action' => 'dashboard', $precision_log['PrecisionLog']['project_id'])); 
					?></td>
					<td>$<?php echo number_format(round($precision_log['PrecisionLog']['partner_amount_cents'] / 100, 2), 2); ?></td>
					<td><?php echo $precision_log['PrecisionLog']['status']; ?></td>
					<td>
						<?php if (!empty($precision_log['PrecisionLog']['status_ir'])): ?>
							<?php echo $precision_log['PrecisionLog']['status_ir']; ?>%
						<?php else: ?>
							-
						<?php endif; ?>
					</td>
					<td>
						<?php if (!empty($precision_log['PrecisionLog']['status_epc'])): ?>
							<?php echo $precision_log['PrecisionLog']['status_epc']; ?>
						<?php else: ?>
							-
						<?php endif; ?>
					</td>
					<td><?php echo $precision_log['PrecisionLog']['status_completes']; ?></td>
					<td><?php echo $precision_log['PrecisionLog']['status_clicks']; ?></td>
					<td><?php echo $precision_log['PrecisionLog']['status_nqs']; ?></td>
					<td><?php echo $precision_log['PrecisionLog']['status_oqs']; ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
