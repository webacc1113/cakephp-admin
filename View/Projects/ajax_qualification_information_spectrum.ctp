<table cellpadding="0" cellspacing="0" class="table table-normal">
	<tr>
		<td width="50%" style="vertical-align: top;">
			<h4>"Spectrum API" pull</h4>
			<?php if (!empty($qualifications_and_quotas['qualifications'])): ?>
				<dl>
					<?php foreach($qualifications_and_quotas['qualifications'] as $qualification): ?>
						<dt><?php echo $qualification['name'];?></dt>
						<?php if (!empty($qualification['conditions'])): ?>
							<dd>
								<?php if ($qualification['name'] == 'age'): ?>
									<?php foreach($qualification['conditions'] as $condition): ?>
										<?php echo $condition['from']. ' - ' .$condition['to']; ?><br />
									<?php endforeach; ?>
								<?php elseif ($qualification['name'] == 'houseHoldIncome'): ?>
									<?php foreach($qualification['conditions'] as $condition): ?>
										<?php echo '$'. $condition['from']. ' - $' .$condition['to']; ?><br />
									<?php endforeach; ?>
								<?php elseif ($qualification['name'] == 'zipcodes'): ?>
									<?php foreach($qualification['conditions'] as $condition): ?>
										<?php echo implode('; ', $condition['values']); ?><br />
									<?php endforeach; ?>
								<?php else : ?>
									<?php foreach($qualification['conditions'] as $condition): ?>
										<?php echo $condition['name']; ?><br />
									<?php endforeach; ?>
								<?php endif; ?>
							</dd>
						<?php endif; ?>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>

			<h4>Quotas</h4>
			<?php if (!empty($qualifications_and_quotas['quotas'])): ?>
				<?php foreach($qualifications_and_quotas['quotas'] as $quota): ?>
					<?php if (!empty($quota['associated_qualifications_and_conditions'])): ?>
						<h5>Qualification for Quota id: <?php echo $quota['quota_id']; ?></h5>
						<dl>
							<?php foreach ($quota['associated_qualifications_and_conditions'] as $qualification): ?>
								<dt><?php echo $qualification['qualification_name'];?></dt>
								<?php if (!empty($qualification['conditions'])): ?>
									<dd>
									<?php if ($qualification['qualification_name'] == 'age'): ?>
										<?php foreach($qualification['conditions'] as $condition): ?>
											<?php echo $condition['from']. ' - ' .$condition['to']; ?><br />
										<?php endforeach; ?>
									<?php elseif ($qualification['qualification_name'] == 'houseHoldIncome'): ?>
										<?php foreach($qualification['conditions'] as $condition): ?>
											<?php echo '$'. $condition['from']. ' - $' .$condition['to']; ?><br />
										<?php endforeach; ?>
									<?php elseif ($qualification['qualification_name'] == 'zipcodes'): ?>
										<?php foreach($qualification['conditions'] as $condition): ?>
											<?php echo implode('; ', $condition['values']); ?><br />
										<?php endforeach; ?>
									<?php else : ?>
										<?php foreach($qualification['conditions'] as $condition): ?>
											<?php echo $condition['name']; ?><br />
										<?php endforeach; ?>
									<?php endif; ?>
									</dd>
								<?php endif; ?>
							<?php endforeach; ?>
						</dl>
					<?php endif; ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</td>
		<td width="50%" style="vertical-align: top;">
			<h4>Survey Qualifications</h4>
			<?php if (!empty($qualifications)): ?>
				<dl>
					<?php foreach ($qualifications as $qualification_name => $qualification_value) : ?>
						<?php if ($qualification_name == 'user_id'): ?>
							<dt>Users</dt>
							<dd>Matched <strong><?php echo number_format(count($value)); ?></strong> user IDs</dd>
						<?php elseif ($qualification_name == 'gender'): ?>
							<dt>Gender</dt>
							<?php foreach ($qualification_value as $gender) :?>
								<dd><?php
									if ($gender == 'M') {
										echo 'Males'; 
									}
									elseif ($gender == 'F') {
										echo 'Females';
									}
								?></dd>
							<?php endforeach;?>
						<?php elseif ($qualification_name == 'age_ranges'): ?>
							<dt>Ages</dt>
							<?php foreach ($qualification_value as $range) : ?>
								<dd><?php echo $range; ?></dd>
							<?php endforeach; ?>
						<?php elseif ($qualification_name == 'country'): ?>
							<dt>Country</dt>
							<dd><?php echo implode(', ', $qualification_value); ?></dd>
						<?php elseif ($qualification_name == 'state'): ?>
							<dt>States</dt>
							<dd><?php echo implode(', ', $qualification_value); ?></dd>
						<?php elseif ($qualification_name == 'dma_code'): ?>
							<dt>DMA Regions</dt>
							<dd><?php echo implode(', ', $qualification_value); ?></dd>
						<?php elseif ($qualification_name == 'postal_code'): ?>
							<dt>Postal Codes</dt>
							<dd><?php echo $qualification_value; ?></dd>
						<?php elseif (array_key_exists($qualification_name, $mappings)): ?>
							<dt><?php echo $mappings[$qualification_name]['title']; ?></dt>
							<?php foreach ($qualification_value as $v) : ?>
								<dd><?php echo $v; ?></dd>
							<?php endforeach; ?>
						<?php endif; ?>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>
			<h4>Qualification json</h4>
			<?php if (!$query_json): ?>
				<div class="alert alert-error">This query was misformatted and could not be displayed.</div>
			<?php else: ?>
				<textarea rows="10"><?php echo $query_json; ?></textarea>
			<?php endif; ?>
		</td>
	</tr>
</table>	