<div class="box">
	<div class="box-header">
		<span class="title">Mass Hellban</span>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create('User', array()); ?>
		<?php if ($this->request->is(array('get'))): ?>
		<div class="padded"><?php
			echo $this->Form->input('user_id', array(
				'label' => 'Enter user ids one id per line.',
				'type' => 'textarea',
				'required' => true
			)); 
			echo $this->Form->input('reason', array(
				'type' => 'text',
				'required' => true
			)); 
		?></div>
		<div class="form-actions">	
			<?php echo $this->Form->submit('Next', array(
				'class' => 'btn btn-sm btn-primary',
				'disabled' => false
			)); ?>
		</div>
		<?php else: ?>
			<div><?php
				if (isset($users) && !empty($users)): ?>
				<table cellpadding="0" cellspacing="0" class="table table-normal">
					<thead>
						<tr>
							<td class="gender">Name</td>
							<td>Email</td>
							<td>Level</td>
							<td>Age</td>
							<td>Country</td>
							<td>State</td>
							<td>created</td>
							<td>Verified</td>
							<td>Last Touched</td>
							<td>Active</td>
							<td>Origin</td>
							<td>Balance</td>
							<td>Pending</td>
							<td>Lifetime</td>					
						</tr>
					</thead>
					<tbody>
						<?php foreach ($users as $user): ?>
							<?php $is_us = $user['QueryProfile']['country'] == 'US'; ?>
								<tr<?php echo $user['User']['hellbanned'] ? ' class="hellbanned"': ''; ?>>
									<td class="gender"><?php
										echo $this->Form->input('user_id][', array('type' => 'checkbox', 'value' => $user['User']['id'], 'label' => $user['User']['firstname'] . ' ' . $user['User']['lastname'], 'checked' => true, 'hiddenField' => false));
									?>
									<small><?php if ($user['User']['hellbanned']): ?>
										<span class="text-error hellban-status">Hellbanned on <?php 
											echo $this->Time->format($user['User']['hellbanned_on'], Utils::dateFormatToStrftime('M d')); 
										?></span> 
									<?php endif; ?>
										</small>
										<?php if (!empty($user['User']['referred_by'])) : ?>
											<br/><small class="muted">Referred by <?php echo $user['Referrer']['email']; ?> 
												<?php if ($user['Referrer']['hellbanned']): ?>
													<span class="label label-red">HELLBANNED</span>
												<?php endif; ?></small>
										<?php endif; ?>
										<?php if ($user['User']['hellbanned'] && !empty($user['User']['hellban_score'])): ?>
											<br/><?php if (!empty($user['User']['hellban_score'])) : ?>
												<?php 
													echo $this->Html->link($user['User']['hellban_score'], 
														array('controller' => 'users', 'action' => 'quickscore', $user['User']['id']), 
														array(
															'class' => 'label label-'.($user['User']['hellban_score'] > 30 ? 'important': 'info'),
															'data-target' => '#modal-user-score',
															'data-toggle' => 'modal', 
														)
													); 
												?>
											<?php endif; ?>
											<span class="label label-inverse"><?php echo $user['User']['hellban_reason']; ?></span>
										<?php endif; ?>
										<?php if (isset($user['User']['checked']) && $user['User']['checked'] && !empty($user['User']['hellban_score'])): ?>
											<?php if (!empty($user['User']['hellban_score'])) : ?>
												 <br/><?php 
													echo $this->Html->link($user['User']['hellban_score'],
														array('controller' => 'users', 'action' => 'quickscore', $user['User']['id']),
														array(
															'class' => 'label label-'.($user['User']['hellban_score'] > 30 ? 'important': 'info'),
															'data-target' => '#modal-user-score',
															'data-toggle' => 'modal', 
														)
													); 
												?> <div class="label label-default">Manually un-hellbanned</div>
											<?php endif; ?>
										<?php endif; ?></td>
									<td>
										<?php echo $user['User']['email'];?><br />
									</td>
									<td><?php
										if (!empty($user['User']['last_touched'])) {
											$levels = unserialize(USER_LEVELS);
											echo $levels[MintVineUser::user_level($user['User']['last_touched'])];
										}
										else {
											echo '-';
										}
									?></td>
									<td><?php echo $this->App->age($user['QueryProfile']['birthdate']);?></td>
									<td><span class="tt">
										<?php echo MintVine::country_name($user['QueryProfile']['country']); ?>
									</span></td>
									<td><?php echo $is_us ? $user['QueryProfile']['state'] : '';?></td>	
									<td><?php echo $this->Time->format('d M, y', $user['User']['created'], null, $timezone);?></td>
									<td><?php echo !empty($user['User']['verified']) 
										? $this->Time->format('d M, y', $user['User']['verified'], null, $timezone)
										: '' 
									;?></td>
									<td><?php echo !empty($user['User']['last_touched']) 
										? $this->Time->format('d M, y', $user['User']['last_touched'], null, $timezone)
										: '';?></td>
									<td><?php echo $user['User']['active'];?></td>
									<td>
										<?php echo $user['User']['origin'];?>
										<?php if (!empty($user['User']['pub_id']) && isset($user['User']['pub_id'])) : ?>
											<br/><small>Publisher ID: <?php echo $user['User']['pub_id']; ?></small>
										<?php endif; ?>
									</td>
									<td><?php echo $this->App->number($user['User']['balance']);?></td>
									<td><?php echo $this->App->number($user['User']['pending']);?></td>
									<td><?php echo $this->App->number($user['User']['total']);?></td>	
								</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif;?></div>
			<div class="form-actions">	
				<?php echo $this->Form->input('reason', array(
					'type' => 'hidden'
				)); ?>
				<?php echo $this->Form->input('action', array(
					'type' => 'hidden',
					'value' => 'hellban'
				)); ?>
				<?php echo $this->Form->submit('Mass Hellban', array(
					'class' => 'btn btn-sm btn-primary',
					'disabled' => false,
					'onclick' => 'return MintVine.CheckMassHellban();'
				)); ?>
			</div>
		<?php endif; ?>
		<?php echo $this->Form->end(); ?>
	</div>
</div>