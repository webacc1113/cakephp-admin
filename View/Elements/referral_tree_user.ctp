<input type="checkbox" name="user[<?php echo $user['User']['id']; ?>]" value="1" class="user-referral-tree <?php echo $user['User']['hellbanned']? 'hellbanned-user' : 'unhellbanned-user'; ?>" />
<?php
	echo $this->Html->link(
		$user['User']['hellbanned'] ? '<span class="label label-red">'.$this->App->username($user['User']).'</span>' : $this->App->username($user['User']), 
		array('action' => 'referral_tree', $user['User']['id']), 
		array('class' => 'underline', 'escape' => false)
	); 
	
?> 
<?php echo $this->Element('user_delete_flag', array('deleted' => $user['User']['deleted_on']));?>
 [Total Points: <?php echo number_format($user['User']['total']); ?>]
 
(<?php
	echo $this->Html->link(
		'Profile', 
		array('controller' => 'users', 'action' => 'quickprofile', $user['User']['id']), 
		array(
			'data-target' => '#modal-user-quickprofile',
			'data-toggle' => 'modal', 
			'class' => 'underline'
		)
	); 
?> | <?php
	echo $this->Html->link(
		'Transactions', 
		array('controller' => 'transactions', 'action' => 'index', '?' => array('user' => '#'.$user['User']['id'])), 
		array(
			'class' => 'underline'
		)
	); 
?> | <?php
	echo $this->Html->link(
		'History', 
		array('controller' => 'panelist_histories', 'action' => 'user', '?' => array('user_id' => $user['User']['id'])), 
		array(
			'class' => 'underline'
		)
	); 
?>
 | Start Date: <?php echo $this->Time->format($user['User']['created'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, $timezone); ?>
 | Country: <?php echo MintVine::country_name($user['QueryProfile']['country']); ?>
 
 <?php if ($user['UserAnalysis'] && !empty($user['UserAnalysis']['score'])): ?>
 | Score:
	<?php 
		echo $this->Html->link($user['UserAnalysis']['score'], 
			array('controller' => 'users', 'action' => 'quickscore', $user['User']['id']), 
			array(
				'class' => ($user['UserAnalysis']['score'] > 30 ? 'label label-important': ''),
				'data-target' => '#modal-user-score',
				'data-toggle' => 'modal', 
			)
		); 
	?>
<?php endif; ?>
<?php if (!empty($user['PaymentMethod']['id'])): ?>
	 | Payment Method:  
	 <?php if ($user['PaymentMethod']['payment_method'] == 'paypal'): ?>
		PayPal
	 <?php elseif (in_array($user['PaymentMethod']['payment_method'], array('dwolla', 'dwolla_id'))): ?>
		Dwolla
	 <?php elseif ($user['PaymentMethod']['payment_method'] == 'tango'): ?>
		Tango card: <?php echo $user['PaymentMethod']['value']; ?>
	<?php endif; ?>	
<?php endif; ?>	
 | Balance: <?php echo $this->App->number($user['User']['balance']);?>
 | Lifetime: <?php echo $this->App->number($user['User']['total']);?>
) 
<?php if ($user['User']['hellbanned'] && !empty($user['User']['hellban_score'])): ?>
 <?php if (!empty($user['User']['hellban_score'])) : ?>
	<?php 
		echo $this->Html->link($user['User']['hellban_score'], 
			array('controller' => 'users', 'action' => 'quickscore', $user['User']['id']), 
			array(
				'data-target' => '#modal-user-score',
				'data-toggle' => 'modal', 
				'class' => 'underline'
			)
		); 
	?>
<?php endif; ?>
 <?php echo $user['User']['hellban_reason']; ?> 
<?php endif; ?>