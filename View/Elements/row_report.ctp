<tr data-id="<?php echo $report['Report']['id']; ?>" data-status="<?php echo $report['Report']['status']; ?>">
	<td>
		<?php if ($report['Report']['status'] == 'queued'): ?>
			<span class="label label-gray">Generating</span>
		<?php elseif ($report['Report']['status'] == 'complete'): ?>
			<span class="label label-green">Ready</span>
		<?php endif; ?>
	</td>
	<td><?php 
		if (!empty($report['Project']['group_id'])) {
			echo $this->Html->link('#'.$this->App->project_id($report), array(
				'controller' => 'surveys',
				'action' => 'dashboard', 
				$report['Project']['id']
			));  
			echo ' '.$report['Project']['prj_name'];
		}
	?></td>
	<td>
		<?php if (!empty($report['Report']['partner_id'])): ?>
			<?php echo $report['Partner']['partner_name']; ?>
		<?php else: ?>
			All Partners
		<?php endif; ?>
	</td>
	<td>
		<?php echo $report['Admin']['admin_user']; ?>
	</td>
	<td>
		<?php echo $this->Time->format($report['Report']['modified'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, $timezone); ?>
	</td>
	<td>
		<?php if ($report['Report']['status'] == 'complete'): ?>
			<?php echo $this->Html->link('Download Report', array('controller' => 'reports', 'action' => 'download', $report['Report']['id']), array('class' => 'btn btn-small btn-default', 'target' => '_blank')); ?>
		
			<?php if (!empty($report['Report']['custom_path'])) : ?>
				<?php echo $this->Html->link('Custom Report', array('controller' => 'reports', 'action' => 'download', $report['Report']['id'],true), array('class' => 'btn btn-small btn-default', 'target' => '_blank')); ?>  
			<?php endif; ?>
		<?php else: ?>
			<?php echo '<span class="btn-waiting">'.$this->Html->image('ajax-loader.gif').' Generating... please wait</span>'; ?>
			<?php echo $this->Html->link('Download', '#', array('style' => 'display: none;', 'class' => 'btn btn-small btn-primary btn-download', 'target' => '_blank')); ?>
		<?php endif; ?>
	</td>
</tr>