<h3>Hellban Activity Stream</h3>

<div class="box">
	<div class="box-header">
		<span class="title">Filters</span>
	</div>
	<div class="box-content">
		<table cellpadding="0" cellspacing="0" class="table table-normal">
			<thead>
				<tr>
					<td></td>
					<td>User</td>
					<td>Admin Username</td>
					<td>Date</td>
					<td>Automated</td>
					<td>Reason</td>
					<td>User Score</td>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($users as $user): ?>
					<tr>
						<td style="width: 40px;">
							<?php if ($user['HellbanLog']['type'] != 'hellban'): ?>
								<span class="label label-success">UNHELLBAN</span>
							<?php else: ?>
								<span class="label label-red">HELLBAN</span>
							<?php endif; ?>
						</td>
						<td><?php 
							echo $this->Html->link(
								'<strong>'.$this->App->username($user['User']).'</strong>', 
								array('controller' => 'users', 'action' => 'quickprofile', $user['User']['id']), 
								array(
									'data-target' => '#modal-user-quickprofile',
									'data-toggle' => 'modal', 
									'escape' => false,
								)
							); 
						?> <small>(<?php
							echo $this->Html->link('Filter', array('?' => array('user' => $user['User']['id']))); 
						?>)</small> 
						<?php if ($user['User']['hellbanned']): ?>
							 <span class="label label-red">HELLBANNED</span>
						<?php endif; ?>
						</td>
						<td><?php 
							echo $user['Admin']['admin_user']; 
						?></td>
						<td><?php 
							echo $this->Time->format($user['HellbanLog']['created'], Utils::dateFormatToStrftime('M d h:i:s A'), false, $timezone); 
						?></td>
						<td><?php
							echo $user['HellbanLog']['automated'] ? 'Y': 'N';
						?></td>
						<td><?php
							echo $user['HellbanLog']['reason'];
						?></td>
						<td>
							<?php if ($user['HellbanLog']['processed']): ?>
								
								<?php 
									echo $this->Html->link($user['HellbanLog']['score'], 
										array('controller' => 'users', 'action' => 'quickscore', $user['User']['id']), 
										array(
											'class' => 'label label-'.($user['HellbanLog']['score'] > 30 ? 'important': 'info'),
											'data-target' => '#modal-user-score',
											'data-toggle' => 'modal', 
										)
									); 
								?>
							<?php else: ?>
								<span class="text-muted">-</span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>

<?php echo $this->Element('pagination'); ?>
<?php echo $this->Element('modal_user_quickprofile'); ?>
<?php echo $this->Element('modal_user_score'); ?>