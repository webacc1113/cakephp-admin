<h4>/suppliers/surveys/qualifications-quotas :Qualifiations</h4>
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

<h4>/suppliers/surveys/qualifications-quotas :Quotas</h4>
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