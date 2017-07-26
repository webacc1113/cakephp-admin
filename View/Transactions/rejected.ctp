<h3>Rejected Withdrawals</h3>

<p><?php echo $this->Html->link('Return to pending withdrawals', array(
	'controller' => 'transactions', 
	'action' => 'index', 
	'?' => array('type' => TRANSACTION_WITHDRAWAL, 'paid' => '0')
), array(
	'class' => 'btn btn-default'
)); ?></p>

<?php echo $this->Form->create('Transaction'); ?>
<div class="box">	
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td><?php echo $this->Paginator->sort('User.email', 'User'); ?></td>
				<td><?php echo $this->Paginator->sort('executed'); ?></td>
				<td>Note</td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($transactions as $transaction): ?>
				<tr class="rejected muted">
					<td>
						<?php echo $this->Element('user_dropdown', array('user' => $transaction['User'])); ?>
						<small><?php echo $transaction['User']['email']; ?></small>
					</td>
					<td><?php echo $transaction['Transaction']['executed']; ?></td>
					<td><textarea name="note[<?php echo $transaction['Transaction']['id'];?>]" style="height: 80px;"><?php echo $transaction['Transaction']['note']; ?></textarea></td>
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
<?php if ($transactions) : ?>
	<?php foreach ($transactions as $transaction): ?>
		<?php echo $this->Element('modal_user_hellban', array('user' => $transaction['User'])); ?>
		<?php echo $this->Element('modal_user_remove_hellban', array('user' => $transaction['User'])); ?>
	<?php endforeach; ?>
<?php endif; ?>
<?php echo $this->Element('pagination'); ?>
<?php echo $this->Element('modal_user_quickprofile'); ?>
<?php echo $this->Element('modal_user_referrer'); ?>
