<h2>Toluna Offerwall Pull for #<?php echo $toluna_log['TolunaLog']['user_id']; ?></h2>
<h3><?php echo date(DB_DATETIME, strtotime($toluna_log['TolunaLog']['created'])); ?></h3>

<p><?php if (isset($next_log) && $next_log): ?>
	<?php echo $this->Html->link('< Next', array('action' => 'view', $next_log['TolunaLog']['id'])); ?>
<?php else: ?>
	<span class="muted">&lt; Next</span>
<?php endif; ?>
  | 
<?php if (isset($prev_log) && $prev_log): ?>
	<?php 
		$diff = strtotime($toluna_log['TolunaLog']['created']) - strtotime($prev_log['TolunaLog']['created']); 
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
	<?php echo $this->Html->link('Previous ('.$diff.') >', array('action' => 'view', $prev_log['TolunaLog']['id'])); ?>
<?php else: ?>
	<span class="muted">Previous &gt;</span>
<?php endif; ?></p>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Toluna Name</td>
				<td>Description</td>
				<td>Duration</td>
				<td>Project ID</td>
				<td>Payout</td>
				<td>EPC</td>
				<td>MV Status</td>
				<td>MV IR</td>
				<td>C</td>
				<td>CL</td>
				<td>NQ</td>
				<td>OQ</td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($toluna_logs as $toluna_log): ?>
				<tr class="<?php
					echo in_array($toluna_log['TolunaLog']['status'], array(PROJECT_STATUS_CLOSED, PROJECT_STATUS_INVOICED)) || !$toluna_log['TolunaLog']['status_active'] ? 'muted': ''; 
				?>">
					<td><?php echo $toluna_log['TolunaLog']['name']; ?></td>
					<td><?php echo $toluna_log['TolunaLog']['description']; ?></td>
					<td><?php echo $toluna_log['TolunaLog']['duration']; ?></td>
					<td><?php 
						echo $this->Html->link($toluna_log['TolunaLog']['project_id'], array('controller' => 'surveys', 'action' => 'dashboard', $toluna_log['TolunaLog']['project_id'])); 
					?></td>
					<td>$<?php echo number_format(round($toluna_log['TolunaLog']['partner_amount_cents'] / 100, 2), 2); ?></td>
					<td><?php echo ($toluna_log['TolunaLog']['epc_cents'] > 0) ? '$'. number_format($toluna_log['TolunaLog']['epc_cents'] / 100, 2) : '-'; ?></td>
					<td><?php echo $toluna_log['TolunaLog']['status']; ?></td>
					<td>
						<?php if (!empty($toluna_log['TolunaLog']['status_ir'])): ?>
							<?php echo $toluna_log['TolunaLog']['status_ir']; ?>%
						<?php else: ?>
							-
						<?php endif; ?>
					</td>
					<td><?php echo $toluna_log['TolunaLog']['status_completes']; ?></td>
					<td><?php echo $toluna_log['TolunaLog']['status_clicks']; ?></td>
					<td><?php echo $toluna_log['TolunaLog']['status_nqs']; ?></td>
					<td><?php echo $toluna_log['TolunaLog']['status_oqs']; ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
