<div class="box">
	<div class="box-header">
		<span class="title">Settings</span>
		<ul class="box-toolbar">
			<li><?php echo $this->Html->link('Add Setting', array('action' => 'add'), array('class' => 'btn btn-small btn-success')); ?></li>
		</ul>
	</div>
	<div class="box-content">
		<table class="table table-normal">
			<thead>
				<tr>
					<td><?php echo __('Name'); ?></td>
					<td><?php echo __('Value'); ?></td>
					<td><?php echo __('Note'); ?></td>
					<td><?php echo __('Last Modified'); ?></td>
 					<td><?php echo __('Action'); ?></td>		
				</tr>
			</thead>
			<tbody>
				<?php foreach ($settings as $key => $setting): ?>
					<tr>
						<td><?php echo $setting['Setting']['name']; ?></td>
						<td>
							<?php if (strlen($setting['Setting']['value']) > 64): ?>
								<span title="<?php echo h($setting['Setting']['value']);?>"><?php echo Utils::truncate($setting['Setting']['value'], 64); ?></span>
							<?php else: ?>
								<?php echo $setting['Setting']['value']; ?>
							<?php endif; ?>
						</td>
						<td><?php echo $setting['Setting']['description']?> 
							<?php if (!empty($setting['Admin']['id'])): ?>
								<span class="muted"><?php echo $setting['Admin']['admin_user']; ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo $setting['Setting']['modified'] != '0000-00-00 00:00:00' ? $setting['Setting']['modified']: ''; ?></td>
 						<td style="white-space: nowrap;">
							<?php echo $this->Html->link('Edit', array(
 								'controller' => 'settings',
 								'action' => 'edit',
 								$setting['Setting']['id']
								), array('class' => 'btn btn-mini btn-default')); ?> 
							<?php echo $this->Html->link('History', array(
								'controller' => 'settings',
								'action' => 'history',
								$setting['Setting']['id']
							), array('class' => 'btn btn-mini btn-default')); ?>
							<?php echo $this->Html->link('Delete', array(
									'controller' => 'settings',
									'action' => 'delete',
									$setting['Setting']['id']
								), array(
									'class' => 'btn btn-mini btn-danger',
								),  __('Are you sure you want to delete this setting?'), true
							)?>						
  						</td>
					</tr>
				<?php endforeach; ?>			
			</tbody>
		</table>
	</div>
</div>