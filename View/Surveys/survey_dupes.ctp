<h3>Dupe Logs for <?php echo $this->Html->link('#'.$project_id, array('action' => 'dashboard', $project_id)); ?></h3>
<p>Total dupe record count: <?php echo number_format(count($system_dupe_logs)); ?></p>

<div class="row-fluid">
	<div class="span8">
		<?php if (!empty($system_dupe_logs)): ?>
			<div class="box">				
				<table class="table table-normal">
					<thead>
						<tr>
							<td>Detected on Survey...</td>
							<td>IP address</td>
							<td>Project Sensitivity Setting</td>
							<td>Number of Dupes</td>
							<td>Created (GMT)</td>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($system_dupe_logs as $system_dupe_log): ?>
						<tr>
							<td>
								<?php echo ucfirst($system_dupe_log['SystemDupeLog']['type']); ?>
							</td>
							<td>
								<?php echo $system_dupe_log['SystemDupeLog']['ip_address']; ?>
							</td>
							<td>
								<?php if (is_null($system_dupe_log['SystemDupeLog']['ip_dupes'])): ?>
									Exact IP match
								<?php else: ?>
									<?php
										$display_ips = array(); 
										for ($i = 1; $i <= $system_dupe_log['SystemDupeLog']['ip_dupes']; $i++) {
											$display_ips[] = '<strong>XXX</strong>'; 
										}
										for ($i = 0; $i <= (4 - count($display_ips)); $i++) {
											$display_ips[] = '<span class="muted">XXX</span>'; 
										}
										echo implode('.', $display_ips); 
									?>
								<?php endif; ?>
							</td>
							<td>
								<?php echo $this->Html->link($system_dupe_log['SystemDupeLog']['ip_count'], array($project_id, '?' => array('id' => $system_dupe_log['SystemDupeLog']['id']))); ?>
							</td>
							<td>
								<?php echo $this->Time->format($system_dupe_log['SystemDupeLog']['created'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, $timezone); ?>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else: ?>
			<p>No dupe logs have been found for this project yet.</p>
		<?php endif; ?>
	</div>
	<div class="span4">
		<div class="box">
			<div class="box-header">
				<span class="title">Understanding this log</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<p>This report logs every single dupe for this project. It returns the project sensitivity setting at the time, as well as the number of previous matches that were found.</p>
					<p>Clicking the dupe count will show you the previous IP entries that were considered matches for this attempted entry.</p>
				</div>
			</div>
		</div>
	</div>
</div>
