<?php $user_levels = unserialize(USER_LEVELS); ?>
<dl class="profile-basic">
	<dt>
		<?php if ($user['QueryProfile']['gender'] == 'M'): ?>
			<?php echo $this->Html->image('user.png'); ?>
		<?php elseif ($user['QueryProfile']['gender'] == 'F'): ?>
			<?php echo $this->Html->image('user-female.png'); ?> 
		<?php endif; ?>
		<?php 
			echo $user['User']['username']; 
		?> #<?php echo $user['User']['id']; ?>
	</dt>
	<?php echo $this->Element('user_delete_flag', array('deleted' => $user['User']['deleted_on']));?>
	<?php if (!empty($user['User']['fullname'])): ?>
	<dd><?php echo $user['User']['fullname']; ?></dd>
	<?php endif; ?>
	<dd><?php echo $user['User']['email']; ?></dd>
	<dt>
		<?php if (isset($twilio_number) && $twilio_number['TwilioNumber']['type'] == 'mobile'): ?>
			Mobile Phone
		<?php elseif (isset($twilio_number) && $twilio_number['TwilioNumber']['type'] == 'landline'): ?>
			Landline Phone
		<?php elseif (isset($twilio_number)): ?>
			VOIP Phone
		<?php endif; ?>
		<?php if (!empty($twilio_number['TwilioNumber']['country_code'])): ?>
		  (<?php echo $twilio_number['TwilioNumber']['country_code']; ?>)	
		<?php endif; ?>
	</dt>
	<?php if (isset($twilio_number) && $twilio_number): ?>
		<dd>
			<?php echo $twilio_number['TwilioNumber']['national_format']; ?>
			<?php if ($twilio_number['TwilioNumber']['verified']):?>
				<small class="text-success">(Verified)</small>
				<?php if ($user['User']['send_sms'] == true): ?>	
					<br />
					<small><strong>Opted in</strong> for text-based surveys</small>
				<?php endif; ?>
			<?php else: ?>
				<small class="text-warning">(Unverified)</small>
			<?php endif; ?> 

			<?php if (!empty($twilio_number['TwilioNumber']['name'])): ?>
				<br />
				<small><strong>Carrier:</strong> <?php echo $twilio_number['TwilioNumber']['name']; ?></small>
			<?php endif; ?>
			<?php if (!empty($twilio_number['TwilioNumber']['caller_name'])): ?>
				<br/>
				<small><strong>Caller:</strong> <?php echo $twilio_number['TwilioNumber']['caller_name']; ?></small>
			<?php endif; ?>
		</dd>
	
	<?php endif; ?>
	<dt>Level</dt>
	<dd><?php echo ($user_level = MintVineUser::user_level($user['User']['last_touched'])) ? $user_levels[$user_level] : ''; ?></dd>
	<?php if ($user_analysis) : ?>
		<dt>User Score</dt>
		<dd><?php echo $user_analysis['UserAnalysis']['score']; ?></dd>
	<?php endif; ?>
		
	<?php if (isset($active_payment_method['PaymentMethod'])): ?>
	<dt>Active payment method</dt>
	<dd><?php echo $active_payment_method['PaymentMethod']['payment_method']; ?></dd>
		<?php if ($active_payment_method && $active_payment_method['PaymentMethod']['payment_method'] == 'paypal'): ?>
			<dt>PayPal Email</dt>
			<dd><?php echo $active_payment_method['PaymentMethod']['value']; ?></dd>
		<?php endif; ?>
	<?php endif; ?>
			
	<?php if (!empty($user['QueryProfile']['birthdate'])): ?>
		<dt>Date of Birth</dt>
		<dd><?php echo $user['QueryProfile']['birthdate']; ?> (<?php echo $this->App->age($user['QueryProfile']['birthdate']);?> years old)</dd>
	<?php endif; ?>
		
	<dt>Poll Streak</dt>
	<dd><?php echo $this->App->number($user['User']['poll_streak']);?></dd>
	<dt>Balance</dt>
	<dd><?php echo $this->App->number($user['User']['balance']);?></dd>
	<dt>Pending</dt>
	<dd><?php echo $this->App->number($user['User']['pending']);?></dd>
	<dt>Pending Withdrawal</dt>
	<dd><?php echo $this->App->number($user['User']['withdrawal']);?></dd>
	<dt>Lifetime Points (not realtime)</dt>
	<dd><?php echo $this->App->number($user['User']['total']);?></dd>
	<dt>Account Created</dt>
	<dd><?php echo $this->Time->format($user['User']['created'], Utils::dateFormatToStrftime('F jS, Y h:i:A'), false, $timezone); ?></dd>
	<dt>Account Verified</dt>
	<dd><?php echo !empty($user['User']['verified']) 
		? $this->Time->format($user['User']['verified'], Utils::dateFormatToStrftime('F jS, Y h:i:A'), false, $timezone)
		: '<span class="muted">Never verified</span>' 
	;?></dd>
	
	<?php if ($user['User']['hellbanned']): ?>
		<dt>Hellban Date</dt>
		<dd><?php echo $this->Time->format($user['User']['hellbanned_on'], Utils::dateFormatToStrftime('F jS, Y'), false, $timezone); ?></dd>
		<?php if (!empty($user['User']['hellban_reason'])): ?>
			<dt>Hellban Reason</dt>
			<dd><?php echo $user['User']['hellban_reason']; ?></dd>
		<?php endif; ?>
	<?php endif; ?>
			
	<dt>Last Activity</dt>
	<dd><?php echo !empty($user['User']['last_touched']) 
		? $this->Time->format($user['User']['last_touched'], Utils::dateFormatToStrftime('F jS, Y h:i:A'), false, $timezone)
		: '<span class="muted">Never logged in</span>';?></dd>
		
	<?php if (isset($user_address) && $user_address): ?>
		<dt>Address 
			<?php if ($user_address['UserAddress']['exact']): ?>
				<span class="label label-info">Verified - Exact</span>
			<?php elseif ($user_address['UserAddress']['verified']): ?>
				<span class="label label-info">Verified</span>
			<?php elseif (!$user_address['UserAddress']['verified']): ?>
				<span class="label label-red">Unverified</span>
			<?php endif; ?>
		</dt>
		<dd>
			<?php echo h($user_address['UserAddress']['address_line1']); ?>
			<?php if (!empty($user_address['UserAddress']['address_line2'])): ?>
				<br/><?php echo h($user_address['UserAddress']['address_line2']); ?>
			<?php endif; ?>

			<?php if ($user_address['UserAddress']['country'] == 'US') : ?>
				<?php echo h($user_address['UserAddress']['city']); ?>, <?php echo h($user_address['UserAddress']['state']); ?> <?php 
					echo h($user_address['UserAddress']['postal_code']);?>
			<?php else : ?>
				<br/><?php echo h($user_address['UserAddress']['city']); ?>, <?php echo h($user_address['UserAddress']['postal_code']);?>
			<?php endif; ?>

			<?php echo (!empty($user_address['UserAddress']['postal_code_extended'])) ? '-' . $user_address['UserAddress']['postal_code_extended'] : '' ; ?>
			<br/>
			<?php if ($user_address['UserAddress']['country'] == 'US') : ?>
			(<?php echo h($user_address['UserAddress']['county']); ?>)
			<?php endif; ?>
		</dd>
	<?php endif; ?>
</dl>
<?php if (!empty($qualifications)): ?>
<dl class="profile-basic">
<?php foreach($qualifications as $qualification): ?>
	<dt><?php echo (isset($qualification['QuestionText']['text'])) ? $qualification['QuestionText']['text'] : '';?></dt>
	<?php if (!empty($qualification['Answer'])): ?>
		<dd>
		<?php foreach($qualification['Answer'] as $answer): ?>
			<?php echo (isset($answer['AnswerText']['text'])) ? $answer['AnswerText']['text'] : ''; ?><br />
		<?php endforeach; ?>
		</dd>
	<?php endif; ?>
<?php endforeach; ?>
</dl>
<?php endif; ?>
<div class="clearfix"></div>
