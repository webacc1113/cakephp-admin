<dl>
	<?php foreach ($query_string as $key => $value) : ?>
		<?php if ($key == 'user_id'): ?>
			<dt>Users</dt>
			<dd>Matched <strong><?php echo number_format(count($value)); ?></strong> user IDs</dd>
		<?php elseif ($key == 'gender'): ?>
			<dt>Gender</dt>
			<?php foreach ($value as $gender) :?>
				<dd><?php
					if ($gender == 'M') {
						echo 'Males'; 
					}
					elseif ($gender == 'F') {
						echo 'Females';
					}
				?></dd>
			<?php endforeach;?>
		<?php elseif ($key == 'age_ranges'): ?>
			<dt>Ages</dt>
			<?php foreach ($value as $range) : ?>
				<dd><?php echo $range; ?></dd>
			<?php endforeach; ?>
		<?php elseif ($key == 'country'): ?>
			<dt>Country</dt>
			<dd><?php echo implode(', ', $value); ?></dd>
		<?php elseif ($key == 'state'): ?>
			<dt>States</dt>
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
</dl>