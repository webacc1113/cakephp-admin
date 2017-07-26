<tr data-id="<?php echo $project_log['ProjectLog']['id']; ?>">
	<td>
		<?php echo $project_log['ProjectLog']['type']; ?>
	</td>
	<td>
		<?php echo $project_log['ProjectLog']['description']; ?>
	</td>
	<td>
		<?php if (!empty($project_log['Admin']['id'])): ?>
			<?php echo $project_log['Admin']['admin_user']; ?>
		<?php else: ?>
			System
		<?php endif; ?>
	</td>
	<td>
		<?php echo $project_log['ProjectLog']['failed_rule']; ?>
	</td>
	<td>
		<?php 
			if (!empty($project_log['ProjectLog']['failed_data'])) {
				echo $this->Html->link('View', array(
					'controller' => 'surveys',
					'action' => 'raw',
					$project_log['ProjectLog']['id']
				), array(
					'data-target' => '#modal-project-log', 
					'data-toggle' => 'modal'
				)); 
			}
		?>
	</td>
	<td>
		<?php echo $this->Time->format($project_log['ProjectLog']['created'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, $timezone); ?>
	</td>
</tr>