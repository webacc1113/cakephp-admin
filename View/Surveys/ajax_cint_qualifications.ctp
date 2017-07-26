<?php if (!empty($quotas)): ?>
	<?php foreach($quotas as $quota): ?>
		<h4>Quota id: <?php echo $quota['id']; ?></h4>
		<dl>
			<dt>Age</dt>
			<dd>
				<?php echo $quota['target_group']['min_age'];?> - 
				<?php echo $quota['target_group']['max_age'];?>
			</dd>
			<dt>Gender</dt>
			<dd><?php echo $quota['target_group']['gender'];?></dd>
			<?php if (isset($quota['Regions'])): ?>
				<dt>Regions</dt>
				<dd>
					<?php foreach($quota['Regions'] as $region): ?>
						<?php echo $region['CintRegion']['name']. ' ('. $region['CintRegion']['type'] .')'; ?><br />
					<?php endforeach;?>
				</dd>
			<?php endif; ?>
			<?php if (isset($quota['Variables'])): ?>
				<dt>Qualifications</dt>
				<dd>
					<?php $question = ''; ?>
					<?php foreach($quota['Variables'] as $variable): ?>
						<?php if ($question != $variable['CintQuestion']['question']): ?>
							<b><?php echo $variable['CintQuestion']['question']; ?></b><br />
							<?php $question = $variable['CintQuestion']['question']; ?>
						<?php endif; ?>
						<?php echo $variable['CintAnswer']['answer']; ?><br />
					<?php endforeach;?>
				</dd>
			<?php endif; ?>
		</dl>
	<?php endforeach; ?>
<?php else: ?>
		Qualifications not found!
<?php endif; ?>