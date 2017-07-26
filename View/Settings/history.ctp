<div class="box">
	<div class="box-header">
		<span class="title">History</span>
	</div>
	<div class="box-content">
		<table class="table table-normal">
			<thead>
				<tr>
					<td><?php echo __('Name'); ?></td>
					<td><?php echo __('Value'); ?></td>
					<td><?php echo __('Note'); ?></td>
					<td><?php echo __('Created'); ?></td>
					<td><?php echo __('Modified'); ?></td>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($settings as $key => $setting): ?>
					<tr class="<?php echo (!$setting['Setting']['deleted']) ? 'success' : ''; ?>">
						<td><?php echo $setting['Setting']['name']; ?> 
							<?php if (!$setting['Setting']['deleted']): ?>
								<span class="label label-green">ACTIVE</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if (strlen($setting['Setting']['value']) > 64): ?>
								<textarea><?php echo $setting['Setting']['value']; ?></textarea>
							<?php else: ?>
								<?php echo $setting['Setting']['value']; ?>
							<?php endif; ?>
						</td>
						<td><?php echo $setting['Setting']['description']?> 
							<?php if (!empty($setting['Admin']['id'])): ?>
								<span class="muted"><?php echo $setting['Admin']['admin_user']; ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php echo $this->Time->format($setting['Setting']['created'], Utils::dateFormatToStrftime(DB_DATETIME), false, $timezone); ?>
						</td>
						<td>
							<?php echo $this->Time->format($setting['Setting']['modified'], Utils::dateFormatToStrftime(DB_DATETIME), false, $timezone); ?>
						</td>
					</tr>
				<?php endforeach; ?>			
			</tbody>
		</table>
	</div>
</div>