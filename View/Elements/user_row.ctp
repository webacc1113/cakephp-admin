<?php $is_us = $user['QueryProfile']['country'] == 'US'; ?>
<tr<?php echo $user['User']['hellbanned'] ? ' class="hellbanned"': ''; ?>>
	<td class="gender"><?php if (!empty($user['User']['fb_id'])) : ?>
		<?php echo $this->Html->link(
			$this->Html->image('balloon-facebook.png'), 
			'https://facebook.com/'.$user['User']['fb_id'],
			array('escape' => false)
		); ?>
	<?php endif; ?><?php 
		if ($user['QueryProfile']['gender'] == 'M') {
			echo $this->Html->image('user.png'); 
		}
		elseif ($user['QueryProfile']['gender'] == 'F') {
			echo $this->Html->image('user-female.png'); 
		}
		
		if (isset($twilio_number) && isset($twilio_number['TwilioNumber']['verified']) && $twilio_number['TwilioNumber']['verified'] == true) {
			echo '&nbsp;&nbsp;<i class="icon-thumbs-up" title="Phone number verified."></i>';
		}
	;?></td>
	<td>
		<?php echo $this->Element('user_dropdown', array('user' => $user['User'])); ?>
		<?php echo $this->Element('user_delete_flag', array('deleted' => $user['User']['deleted_on']));?>
		<small><?php if ($user['User']['hellbanned']): ?>
			<span class="text-error hellban-status">Hellbanned on <?php 
				echo $this->Time->format($user['User']['hellbanned_on'], Utils::dateFormatToStrftime('M d')); 
			?></span> 
		<?php endif; ?>
		<?php echo $user['User']['email']; ?></small>
		<?php if (!empty($user['User']['referred_by'])) : ?>
			<br/><small class="muted">Referred by <?php echo $user['Referrer']['email']; ?> 
				<?php if ($user['Referrer']['hellbanned']): ?>
					<span class="label label-red">HELLBANNED</span>
				<?php endif; ?></small>
		<?php endif; ?>
		
		<?php if (($user['User']['hellbanned'] || $user['User']['checked']) && !empty($user['User']['hellban_score'])): ?>
			<br/><?php echo $this->Html->link($user['User']['hellban_score'], 
					array('controller' => 'users', 'action' => 'quickscore', $user['User']['id']), 
					array(
						'class' => 'label label-'.($user['User']['hellban_score'] > 30 ? 'important': 'info'),
						'data-target' => '#modal-user-score',
						'data-toggle' => 'modal', 
					)
				); 
			?>
		<?php endif; ?>	
		<?php if ($user['User']['hellbanned']): ?>
			<span class="label label-inverse"><?php echo $user['User']['hellban_reason']; ?></span>
		<?php endif; ?>
		<?php if ($user['User']['checked']): ?>
			<div class="label label-default"><?php echo (isset($user['HellbanLog'][0]) && $user['HellbanLog'][0]['automated']) ? 'Automatically un-hellbanned' : 'Manually un-hellbanned' ?></div>
		<?php endif; ?>
	</td>
	<?php if (isset($user_analysis)): ?>
		<td>
			<?php if ($user_analysis && !empty($user_analysis['UserAnalysis']['score'])): ?>
				<?php 
					echo $this->Html->link($user_analysis['UserAnalysis']['score'], 
						array('controller' => 'users', 'action' => 'quickscore', $user['User']['id']), 
						array(
							'class' => ($user_analysis['UserAnalysis']['score'] > 30 ? 'label label-important': ''),
							'data-target' => '#modal-user-score',
							'data-toggle' => 'modal', 
						)
					); 
				?>
			<?php else: ?>
				<span class="text-muted">---</span>
			<?php endif; ?>
		</td>	
	<?php endif; ?>
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
	<?php if (!isset($showing_hellbanned) || !$showing_hellbanned): ?>
		<td><?php echo $this->Time->format('d M, y', $user['User']['created'], null, $timezone);?></td>
	<?php endif; ?>
	<td><?php echo !empty($user['User']['verified']) 
		? $this->Time->format('d M, y', $user['User']['verified'], null, $timezone)
		: '' 
	;?></td>
	<?php if (isset($showing_hellbanned) && $showing_hellbanned): ?>
	<td><?php echo !empty($user['User']['hellbanned_on']) 
		? $this->Time->format('d M, y', $user['User']['hellbanned_on'], null, $timezone)
		: '' 
	;?></td>
	<td><?php echo !empty($user['User']['hellban_score'])
			? number_format($user['User']['hellban_score'], 2)
				: ''; 
	?></td>
	<?php endif; ?>
	<td><?php echo !empty($user['User']['last_touched']) 
		? $this->Time->format('d M, y', $user['User']['last_touched'], null, $timezone)
		: '';?></td>
	<?php if (!isset($showing_hellbanned) || !$showing_hellbanned): ?>
	<td><?php echo $user['User']['active'];?></td>
	<?php endif; ?>
	<td>
		<?php echo $user['User']['origin'];?>
		<?php if (!empty($user['User']['pub_id']) && isset($user['User']['pub_id'])) : ?>
			<br/><small>Publisher ID: <?php echo $user['User']['pub_id']; ?></small>
		<?php endif; ?>
	</td>
	<td><?php echo $this->App->number($user['User']['balance']);?></td>
	<?php if (!isset($showing_hellbanned) || !$showing_hellbanned): ?>
	<td><?php echo $this->App->number($user['User']['pending']);?></td>
	<?php endif; ?>
	<td><?php echo $this->App->number($user['User']['total']);?></td>	
</tr>