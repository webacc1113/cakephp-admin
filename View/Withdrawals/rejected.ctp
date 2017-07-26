<h3>Rejected Withdrawals</h3>

<p><?php echo $this->Html->link('Return to pending withdrawals', array(
	'controller' => 'withdrawals', 
	'action' => 'index', 
	'?' => array('status' => WITHDRAWAL_PAYOUT_UNPROCESSED)
), array(
	'class' => 'btn btn-default'
)); ?></p>

<?php echo $this->Form->create('Withdrawal'); ?>
<div class="box">	
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td><?php echo $this->Paginator->sort('User.email', 'User'); ?></td>
				<td><?php echo $this->Paginator->sort('updated'); ?></td>
				<td>Note</td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($withdrawals as $withdrawal): ?>
				<tr class="rejected muted">
					<td>
						<?php echo $this->Element('user_dropdown', array('user' => $withdrawal['User'])); ?>
						<small><?php echo $withdrawal['User']['email']; ?></small>
					</td>
					<td><?php echo $withdrawal['Withdrawal']['updated']; ?></td>
					<td><textarea name="note[<?php echo $withdrawal['Withdrawal']['id'];?>]" style="height: 80px;"><?php echo $withdrawal['Withdrawal']['note']; ?></textarea></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<div class="form-actions">
		<?php echo $this->Form->submit('Save', array(
			'name' => 'save', 
			'rel' => 'tooltip', 
			'data-original-title' => '', 
			'class' => 'btn btn-success'
		)); ?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>

<?php if ($withdrawals) : ?>
	<?php foreach ($withdrawals as $withdrawal): ?>
		<?php echo $this->Element('modal_user_hellban', array('user' => $withdrawal['User'])); ?>
		<?php echo $this->Element('modal_user_remove_hellban', array('user' => $withdrawal['User'])); ?>
	<?php endforeach; ?>
<?php endif; ?>
<?php echo $this->Element('pagination'); ?>
<?php echo $this->Element('modal_user_quickprofile'); ?>
<?php echo $this->Element('modal_user_referrer'); ?>
