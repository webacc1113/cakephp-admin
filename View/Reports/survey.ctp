<div class="box">
	<div class="box-header">
		<span class="title">Survey Complete History</span>
	</div>
	<div class="box-content">
		<table cellpadding="0" cellspacing="0" class="table table-normal">
			<thead>
				<tr>
					<th>Time Since Launch</th>
					<th>Completes</th>
					<th>Clicks</th>
					<th>Clicks Since Complete</th>
					<th>OQs</th>
					<th>OQs (Internal)</th>
					<th>NQs</th>
					<th>IR</th>
					<th>LOI</th>
					<th>EPC</th>
				</tr>
			</thead>
			<?php $clicks_since_complete = 0; ?>
			<?php foreach ($data_rows as $data_row): ?>
				<tr>					
					<td><?php echo $data_row['time']; ?> minutes</td>
					<td><?php echo $data_row['complete']; ?></td>
					<td><?php echo $data_row['click']; ?></td>
					<td><?php echo ($data_row['click'] > $clicks_since_complete) ? $data_row['click'] - $clicks_since_complete : 0; ?></td>
					<td><?php echo $data_row['oq']; ?></td>
					<td><?php echo $data_row['oq_internal']; ?></td>
					<td><?php echo $data_row['nq']; ?></td>
					<td><?php echo $data_row['ir']; ?></td>
					<td><?php echo $data_row['loi']; ?></td>
					<td>$<?php echo number_format($data_row['epc'], 2); ?></td>
				</tr>
				<?php $clicks_since_complete = $data_row['click']; ?>
			<?php endforeach; ?>
				<tr>					
					<td>Now: <?php echo(round((time() - $start) / 60)); ?> minutes</td>
					<td colspan="5"</td>
				</tr>
		</table>
	</div>
</div>