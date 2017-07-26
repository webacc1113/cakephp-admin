<h3>User Profiles</h3>

<p><?php echo $this->Html->link('New User Profile Survey', array('action' => 'add'), array('class' => 'btn btn-mini btn-success')); ?></p>

<p class="count">Showing <?php 
	echo number_format($this->Paginator->counter(array('format' => '{:current}')));
?> of <?php
	echo number_format($this->Paginator->counter(array('format' => '{:count}')));
?> matches</p>

<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Name</td>
				<td>Award</td>
				<td>Question Count</td>
				<td>Modified</td>
				<td></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($profiles as $profile): ?>
				<tr data-id="<?php echo $profile['Profile']['id']; ?>">
					<td><?php echo $profile['Profile']['name']; ?></td>
					<td><?php echo $profile['Profile']['award']; ?></td>
					<td>
						<?php if ($profile['Profile']['count'] > 0): ?>
							<?php echo $this->Html->link('Questions: '.$profile['Profile']['count'], array('controller' => 'profile_questions', 'action' => 'index', $profile['Profile']['id']), array('class' => 'btn btn-mini btn-primary')); ?> 
						<?php else: ?>
							<?php echo $this->Html->link('Add Questions', array('controller' => 'profile_questions', 'action' => 'index', $profile['Profile']['id']), array('class' => 'btn btn-mini btn-primary')); ?> 
						<?php endif; ?>
					</td>
					<td><?php 
						echo $this->Time->format($profile['Profile']['modified'], Utils::dateFormatToStrftime('Y-m-d')); 
					?></td>
					<td class="nowrap text-right" style="width: 180px;">
						<?php echo $this->Html->link(
							$profile['Profile']['status'] == 'active' ? 'Active': 'Inactive',
							'#', 
							array(
								'onclick' => 'return MintVine.ToggleProfileStatus(this, '.$profile['Profile']['id'].')',
								'class' => 'btn btn-mini '.($profile['Profile']['status'] == 'active' ? 'btn-success': 'btn-default')
							)
						); ?> 
						<?php echo $this->Html->link('Edit', array('action' => 'edit', $profile['Profile']['id']), array('class' => 'btn btn-mini btn-warning')); ?> 
						<?php echo $this->Html->link('Delete', array('action' => 'delete', $profile['Profile']['id']), array('class' => 'btn btn-mini btn-danger')); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php echo $this->Element('pagination'); ?>
