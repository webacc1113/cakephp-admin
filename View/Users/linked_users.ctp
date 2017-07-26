<h3>Linked Users</h3>

<?php if (isset($this->request->query['user_id'])): ?>
	<div class="box">	
		<table cellpadding="0" cellspacing="0" class="table table-normal">
			<thead>
				<tr>
					<td class="gender"></td>
					<td>Name</td>
					<td>Score</td>
					<td>Level</td>
					<td>Age</td>
					<td>Country</td>
					<td>State</td>
					<td>Created</td>
					<td>Verified</td>
					<td>Last Touched</td>
					<td>Active</td>
					<td>Origin</td>
					<td>Balance</td>
					<td>Pending</td>
					<td><div class="tt" data-placement="top" data-toggle="tooltip" title="Calculated once per 24 hours">Lifetime <sup>*</sup></div></td>				
				</tr>
			</thead>
			<tbody>
				<?php echo $this->Element('user_row', array('user' => $user, 'user_analysis' => $user_analysis)); ?>
			</tbody>
		</table>
	</div>
	<?php echo $this->Element('modal_user_hellban', array('user' => $user['User'])); ?>
	<?php echo $this->Element('modal_user_remove_hellban', array('user' => $user['User'])); ?>
	
	<h5>Total linked browser : <?php echo $total_linked_browsers; ?></h5>
	<h5>Total linked users : <?php echo $total_linked_users; ?></h5>
<?php else: ?>
	<h5>Total linked users : <?php echo $total_linked_users; ?></h5>
<?php endif; ?>

<?php $grouped_users = array(); ?>
<?php echo $this->Form->create('LinkedUser'); ?>
<?php foreach ($linked_users as $linked_user): ?>
	<div class="well">
		<p>Unique ID: <b><?php echo $linked_user['UniqueUser']['unique_id']; ?></b></p>
		<p>User Agent: <b><?php echo $linked_user['UniqueUser']['user_agent']; ?></b></p>
		<div class="box">
			<table cellpadding="0" cellspacing="0" class="table table-normal">
				<thead>
					<tr>
						<?php if (!isset($this->request->query['user_id'])): ?>
						<td class="checkbox"><?php echo $this->Form->input('null', array(
							'type' => 'checkbox', 
							'label' => false,
							'hiddenField' => false,
							'onclick' => 'return toggleChecked(this.checked)'
						)); ?></td>
						<?php endif; ?>
						<td>User</td>
						<td>Browser Fingerprint</td>
						<td>Ip Address</td>
						<td>Active</td>
						<td>Last Login</td>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($linked_user['User'] as $user): ?>
						<tr<?php echo $user['hellbanned'] ? ' class="hellbanned"': ''; ?>>
							<?php if (!isset($this->request->query['user_id'])): ?>
								<?php if (!in_array($user['id'], $grouped_users)): ?>
									<?php $grouped_users[] = $user['id']; ?>
									<td class="checkbox"><?php echo $this->Form->input('LinkedUser.'.$user['id'], array(
										'label' => false,
										'hiddenField' => false,
										'type' => 'checkbox'
									)); ?></td>
								<?php else: ?>
									<td></td>
								<?php endif; ?>
							<?php endif; ?>
							<td>
								<?php echo $this->Element('user_dropdown', array('user' => $user)); ?>
								<?php echo $this->Element('user_delete_flag', array('deleted' => $user['deleted_on']));?>
								<small>
									<?php if ($user['hellbanned']): ?>
										<span class="text-error hellban-status">Hellbanned on <?php 
											echo $this->Time->format($user['hellbanned_on'], Utils::dateFormatToStrftime('M d')); 
										?></span> 
									<?php endif; ?>
									<?php echo $user['email']; ?>
								</small>
							</td>
							<td><?php echo $user['UniqueUser']['fingerprint']; ?></td>
							<td><?php echo $user['UniqueUser']['ip_address']; ?></td>
							<td><?php echo $user['active'] ? 'Y' : ''; ?></td>
							<td><?php echo $this->Time->format('d M, y', $user['login'], null, $timezone); ?></td>
						</tr>
						<?php echo $this->Element('modal_user_hellban', array('user' => $user)); ?>
						<?php echo $this->Element('modal_user_remove_hellban', array('user' => $user)); ?>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if (!isset($this->request->query['user_id'])): ?>
			<div class="form-actions">
				<?php echo $this->Form->submit('Hellban', array(
					'class' => 'btn btn-danger'
				)); ?> 
			</div>
			<?php endif; ?>
		</div>
	</div>
<?php endforeach; ?>
<?php echo $this->Form->end(null); ?>
<?php echo $this->Element('modal_user_quickprofile'); ?>
<?php echo $this->Element('modal_user_score'); ?>
<?php echo $this->Element('modal_user_referrer'); ?>