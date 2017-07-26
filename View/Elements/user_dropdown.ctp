<div class="btn-group btn-user<?php echo $user['hellbanned'] ? ' hellbanned': ''; ?>" data-userid="<?php echo $user['id']; ?>">	
	<?php
	echo $this->Html->link(
		'<span>'.$this->App->username($user).'</span>', 
		array('controller' => 'users', 'action' => 'quickprofile', $user['id']), 
		array(
			'data-target' => '#modal-user-quickprofile',
			'data-toggle' => 'modal', 
			'escape' => false, 
			'class' => 'modal-user btn btn-default'.($user['hellbanned'] ? ' hellbanned': '')
		)
	); 
	?>
	<button class="btn btn-default dropdown-toggle" data-toggle="dropdown"><span class="caret"></span></button>
	<ul class="dropdown-menu">
		<li><?php 
		echo $this->Html->link('Transactions', array('controller' => 'transactions', 'action' => 'index', '?' => array('user' => urlencode('#'.$user['id'])))); 
		?></li>
		<li><?php 
		echo $this->Html->link('Click History', array('controller' => 'panelist_histories', 'action' => 'user', '?' => array( 'user_id' => $user['id']))); 
		?></li>
		<li><?php 
		echo $this->Html->link('Notification History', array('controller' => 'notification_schedules', 'action' => 'user', '?' => array( 'user_id' => $user['id']))); 
		?></li>
		<li><?php 
		echo $this->Html->link('Notification Log', array('controller' => 'notification_logs', 'action' => 'user', $user['id'])); 
		?></li>
		<li><?php 
		echo $this->Html->link('User Logs', array('controller' => 'user_router_logs', 'action' => 'view', $user['id'])); 
		?></li>		
		<li><?php 
		echo $this->Html->link('User Scores', array('controller' => 'users', 'action' => 'scores', $user['id']), array(
			'data-target' => '#modal-user-score',
			'data-toggle' => 'modal', 
		)); 
		?></li>
		<li><?php 
		echo $this->Html->link('Update', array('controller' => 'users', 'action' => 'update', $user['id'])); 
		?></li>	
		<li><?php 
		echo $this->Html->link('Update Address', array('controller' => 'addresses', 'action' => 'index', $user['id'])); 
		?></li>
		<li><?php 
		echo $this->Html->link('Reported Projects', array('controller' => 'history_requests', 'action' => 'index', '?' => array('user_id' => $user['id']))); 
		?></li>
		<?php if (!empty($user['verified'])) :?>
			<li><?php 
				echo $this->Html->link('Survey Quality', array('controller' => 'users', 'action' => 'survey_quality', $user['id'])); 
			?></li>
		<?php endif; ?>
		<li><?php 
		echo $this->Html->link('Offer History', array('controller' => 'offers', 'action' => 'redemptions', '?' => array('user' => '#'.$user['id']))); 
		?></li>
		<li><?php 
		echo $this->Html->link('Referral Tree', array('controller' => 'users', 'action' => 'referral_tree', $user['id'])); 
		?></li>
		<li><?php 
			echo $this->Html->link('Linked Users', 
				array('controller' => 'users', 'action' => 'linked_users', '?' => array('user_id' => $user['id'])), 
				array(
					'target' => '_blank'
				)
			); 
		?></li>
		<li><?php 
			if (isset($user['referred_by']) && empty($user['referred_by'])) {
				echo $this->Html->link('Set Referrer', 
					array('controller' => 'users', 'action' => 'ajax_referrer', $user['id']),
					array(
						'data-target' => '#modal-user-referrer', 
						'data-toggle' => 'modal'
					)
				); 
			}
			else {
				echo $this->Html->link('Remove Referrer', 
					array('controller' => 'users', 'action' => 'ajax_remove_referrer', $user['id']),
					array(
						'data-target' => '#modal-user-remove-referrer', 
						'data-toggle' => 'modal'
					)
				); 
			}
		?></li>		
		<li class="divider"></li>
		<li><?php 
			echo $this->Html->link('Merge Account', 
				array('controller' => 'users', 'action' => 'merge', '?' => array('from' => $user['id']))
			); 
		?></li>
		<li><?php 
			echo $this->Html->link('Rebuild Balance', 
				array('controller' => 'users', 'action' => 'ajax_rebuild_balance', $user['id']),
				array('data-target' => '#modal-user-rebuild-balance', 'data-toggle' => 'modal')
			); 
		?></li>
		<li class="divider"></li>
		<li><?php 
			echo $this->Html->link('Login as this user', 
				array('controller' => 'users', 'action' => 'login_as_user', $user['id']),
				array(
					'target' => '_blank'
				)
			); 
		?></li>
		<li class="divider"></li>
		<li><?php
			echo $this->Html->link('Hellban Log', array(
				'controller' => 'users', 'action' => 'hellbans', '?' => array('user' => $user['id'])
			)); 
		?></li>
		<li class="banlink"><?php 
		echo $this->Html->link(
			'Hellban', 
			'#modal-user-hellban-'.$user['id'],
			array('class' => 'banlink', 'data-toggle' => 'modal', 'escape' => false)
		); 
		?></li>
		<li class="unbanlink"><?php 
		echo $this->Html->link(
			'Remove Hellban', 
			'#modal-user-remove-hellban-'.$user['id'],
			array('class' => 'unbanlink', 'data-toggle' => 'modal', 'escape' => false)
		); 
		?></li>
		<li class="divider"></li>
		<li><?php echo $this->Html->link('User Activity Logs', array('controller' => 'user_logs', 'action' => 'index', $user['id'])); 
		?></li>
		<li class="divider"></li>
		<li class=""><?php 
		echo $this->Html->link(
			'Delete User', 
			'#',
			array('onclick' => 'return MintVine.DeleteUser('.$user['id'].', this)')
		); 
		?></li>
	</ul>
</div>
<?php echo $this->Element('modal_user_remove_referrer'); ?>
<?php echo $this->Element('modal_user_rebuild_balance'); ?>