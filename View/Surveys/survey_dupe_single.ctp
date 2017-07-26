<h3>Viewing Single Record</h3>
<div class="row-fluid">
	<div class="span8">
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
								<?php echo $system_dupe_log['SystemDupeLog']['ip_dupes']; ?>: <?php
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
							<?php echo $system_dupe_log['SystemDupeLog']['ip_count']; ?>
						</td>
						<td>
							<?php echo $this->Time->format($system_dupe_log['SystemDupeLog']['created'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, $timezone); ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<h3>Previous IP Address Log</h3>
		<div class="box">
			<?php $statuses = unserialize(SURVEY_STATUSES); ?>
			<?php if (!empty($fp_ips)): ?>
				<table class="table table-normal">
					<thead>
						<tr>
							<td>IP Address</td>
							<td>Status</td>
							<td>Date (GMT)</td>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($fp_ips as $fp_ip): ?>
							<tr>
								<td>
									<?php
										$octets = explode('.', $fp_ip['FpIp']['ip']); 
										$display_ips = array(); 
										for ($i = 0; $i < $system_dupe_log['SystemDupeLog']['ip_dupes']; $i++) {
											$display_ips[] = '<strong>'.$octets[$i].'</strong>'; 
										}
										for ($i = count($display_ips); $i <= (6 - count($display_ips)); $i++) {
											$display_ips[] = '<span class="muted">'.$octets[$i].'</span>'; 
										}
										echo implode('.', $display_ips); 
									?>
								</td>
								<td><?php echo $statuses[$fp_ip['FpIp']['status']]; ?></td>
								<td><?php echo $fp_ip['FpIp']['created']; ?></td>
							</tr>
							<?php if ($matched_id == $fp_ip['FpIp']['id']) : ?>
								<tr class="info">
									<td>
										<?php
											$octets = explode('.', $system_dupe_log['SystemDupeLog']['ip_address']); 
											$display_ips = array(); 
											for ($i = 0; $i < $system_dupe_log['SystemDupeLog']['ip_dupes']; $i++) {
												$display_ips[] = '<strong>'.$octets[$i].'</strong>'; 
											}
											for ($i = count($display_ips); $i <= (6 - count($display_ips)); $i++) {
												$display_ips[] = '<span class="muted">'.$octets[$i].'</span>'; 
											}
											echo implode('.', $display_ips); 
										?>
									</td>
									<td>
										<?php if ($system_dupe_log['SystemDupeLog']['type'] == 'exit') : ?>
											<?php echo $statuses[SURVEY_NQ_FRAUD]; ?>
										<?php elseif ($system_dupe_log['SystemDupeLog']['type'] == 'entry'): ?>
											<?php echo $statuses[SURVEY_OQ_INTERNAL]; ?>
										<?php endif; ?>
									</td>
									<td><?php echo $system_dupe_log['SystemDupeLog']['created']; ?></td>
								</tr>
							<?php endif; ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	</div>
	<div class="span4">
		<div class="box">
			<div class="box-header">
				<span class="title">Understanding this log</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<p>This log will show you the previous IP addresses that caused the IP sensitivity dupe check to return this request as a dupe.</p>
					<p>The blue-highlighted row is the IP address you are inspecting; the other records are from previous entrants. The bolded parts of the IP address are the matched counts.</p>
					<p><span class="label info">Note:</span> Records prior to May 3rd, 2017 had a bug that was overaggressive in matching; this bug has been fixed since then. Older records will still show how the incorrect matching was done in this view.</p>
				</div>
			</div>
		</div>
	</div>
</div>