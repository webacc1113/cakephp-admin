<h3>Promo Code Redemptions</h3>
<div class="box">
	<div class="box-header">
		<span class="title">Redeem a code for 
			<em><?php echo $this->App->username($user['User'])?> [#<?php echo $user['User']['id']?>]</em></span>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create('CodeRedemption', array('class' => 'filter')); ?>
			<?php echo $this->Form->input('user_id', array('type' => 'hidden', 'value' => $user['User']['id'])); ?>
			<div class="padded separate-sections">
				<div class="row-fluid">
					<div class="filter">
					<?php echo $this->Form->input('code_id', array(
						'type' => 'select',
						'options' => $codesList,
						'empty' => 'Code:',
					)); ?>
					</div>
				</div>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Redeem', array('class' => 'btn btn-primary')); ?>
				<?php echo $this->Html->link('Back to list', 
					array('action' => 'redeem'), 
					array('class' => 'btn btn-default')); ?>
			</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>

<?php if (!empty($codeRedemptions)): ?>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Code</td>
				<td>Amount</td>
				<td>Date</td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($codeRedemptions as $codeRedemption): ?>
				<tr>
					<td><?php echo $codeRedemption['Code']['code']; ?></td>
					<td><?php echo $codeRedemption['Code']['amount']; ?> pts</td>
					<td><?php echo $this->Time->format($codeRedemption['CodeRedemption']['created'], 
						Utils::dateFormatToStrftime('F jS, Y h:i A'), false, $timezone)
					?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php else: ?>
	<div class="well">No Redemption found</div>
<?php endif; ?>