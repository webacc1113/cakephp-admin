<dl>
<?php foreach ($query_string as $key => $value) : ?>
	<?php if ($key == 'keyword'): ?>
		<dt>Keyword Search</dt>
		<dd>Matched keyword <code><?php echo $value; ?></code></dd>
	<?php elseif ($key == 'user_id'): ?>
		<dt>Users</dt>
		<dd>Matched <strong><?php echo number_format(count($value)); ?></strong> user IDs</dd>
	<?php elseif ($key == 'gender'): ?>
		<dt>Gender</dt>
		<dd><?php
			if ($value == 'M') {
				echo 'Males'; 
			}
			elseif ($value == 'F') {
				echo 'Females';
			}
		?></dd>
	<?php elseif ($key == 'birthday'): ?>
		<?php if (count($value) == 2) : ?>
			<dt>Age Range</dt>
			<dd><?php echo $value[0].' - '.$value[1]; ?></dd>
		<?php else: ?>
			<dt>Age</dt>
			<dd><?php echo $value[0]; ?></dd>
		<?php endif; ?>
	<?php elseif ($key == 'country'): ?>
		<dt>Country</dt>
		<dd><?php echo $value; ?></dd>
	<?php elseif ($key == 'state'): ?>
		<dt>States</dt>
		<dd><?php echo implode(', ', $value); ?></dd>
	<?php elseif ($key == 'regionCA'): ?>
		<dt>Regions</dt>
		<dd><?php echo implode(', ', $value); ?></dd>
	<?php elseif ($key == 'regionGB'): ?>
		<dt>Regions</dt>
		<dd><?php echo implode(', ', $value); ?></dd>
	<?php elseif ($key == 'dma_code'): ?>
		<dt>DMA Regions</dt>
		<dd><?php echo implode(', ', $value); ?></dd>
	<?php elseif ($key == 'postal_code'): ?>
		<dt>Postal Codes</dt>
		<dd><?php echo $value; ?></dd>
	<?php elseif (array_key_exists($key, $mappings)): ?>
		<dt><?php echo $mappings[$key]['title']; ?></dt>
		<?php foreach ($value as $v) : ?>
			<dd><?php echo $v; ?></dd>
		<?php endforeach; ?>
	<?php endif; ?>
<?php endforeach; ?>

<?php if (isset($query_string['age_to']) && $query_string['age_from']): ?>
	<dt>Ages</dt>
	<dd><?php echo $query_string['age_from']; ?> - <?php echo $query_string['age_to']; ?></dd>
<?php endif; ?>
<?php if (isset($profile_questions) && isset($profile_answers) && $profile_questions && $profile_answers): ?>
	<?php foreach ($profile_questions as $profile_question): ?>
		<dt><span class="label label-default"><?php 
			echo $profile_question['Profile']['name']; 
		?></span> <?php 
			echo $profile_question['ProfileQuestion']['name']; 
		?></dt>
		<?php foreach ($profile_question['ProfileAnswer'] as $answer): ?>
			<?php if (array_key_exists($answer['id'], $profile_answers)): ?>
			<dd><?php echo $answer['name']; ?>
				
			</dd>
			<?php endif; ?>
		<?php endforeach; ?>
	<?php endforeach; ?>
<?php endif; ?>
</dl>