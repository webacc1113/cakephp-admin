<h3>Cint Run #<?php echo $cint_log['CintLog']['run']; ?> (<?php echo $cint_log['CintLog']['country']; ?>)</h3>
<p>Run on <?php 
	echo $this->Time->format($cint_log['CintLog']['created'], Utils::dateFormatToStrftime(DB_DATETIME), false, $timezone); 
?> total of <?php 
	echo number_format($cint_log['CintLog']['count']); 
?> rows (Total of <?php echo number_format($open_projects_completes); ?> completes (for open only projects) available). </p>

<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Cint Survey ID</td>
				<td>Cint Quota ID</td>
				<td>Completes Available</td>
				<td>Conversion</td>
				<td>IR</td>
				<td>Completes</td>
				<td>MV Status</td>
				<td>MV IR</td>
				<td>C</td>
				<td>CL</td>
				<td>NQ</td>
				<td>OQ</td>
			</tr>
		</thead>
		<tbody>
			<?php $last_survey_id = 0; ?>
			<?php foreach ($cint_logs as $cint_log): ?>
				<tr class="<?php
					echo in_array($cint_log['CintLog']['status'], array(PROJECT_STATUS_CLOSED, PROJECT_STATUS_INVOICED)) || !$cint_log['CintLog']['status_active'] ? 'muted': ''; 
				?>">
					<td><?php 
						if ($last_survey_id != $cint_log['CintLog']['cint_survey_id']) {
							if (isset($surveys[$cint_log['CintLog']['cint_survey_id']])) {
								$survey_id = $surveys[$cint_log['CintLog']['cint_survey_id']];
								echo $this->Html->link($cint_log['CintLog']['cint_survey_id'], array(
									'controller' => 'surveys',
									'action' => 'dashboard',
									$survey_id
								)); 
							}
							else {
								echo $cint_log['CintLog']['cint_survey_id']; 	
							}
							$last_survey_id = $cint_log['CintLog']['cint_survey_id'];	
						}
					?></td>
					<td><?php echo $cint_log['CintLog']['cint_quota_id']; ?></td>
					<td><?php echo number_format($cint_log['CintLog']['quota']); ?></td>
					<td><?php echo number_format($cint_log['CintLog']['statistic_conversion']); ?>%</td>
					<td><?php echo number_format($cint_log['CintLog']['statistic_ir']); ?>%</td>
					<td><?php echo number_format($cint_log['CintLog']['statistic_completes']); ?></td>
					<td><?php echo $cint_log['CintLog']['status']; ?> / <?php echo $cint_log['CintLog']['status_active'] ? 'Active': 'Inactive'; ?></td>
					<td><?php echo $cint_log['CintLog']['status_ir']; ?>%</td>
					<td><?php echo $cint_log['CintLog']['status_completes']; ?></td>
					<td><?php echo $cint_log['CintLog']['status_clicks']; ?></td>
					<td><?php echo $cint_log['CintLog']['status_nqs']; ?></td>
					<td><?php echo $cint_log['CintLog']['status_oqs']; ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>