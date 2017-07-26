<div class="box">
	<div class="box-header">
		<span class="title">Top Rejected Transactions Count</span>
	</div>
	<div class="box-content">
		<table cellpadding="0" cellspacing="0" class="table table-normal">
			<thead>
				<tr>
					<th>User</th>
					<th>Total Points</th>
					<th>Rejected Transactions Count</th>
					<th>Rejected Transactions Points</th>
				</tr>
			</thead>
			<?php foreach ($users as $user): ?>
				<tr>					
					<td>
						<?php echo $this->Element('user_dropdown', array('user' => $user['User'])); ?>
						<small><?php echo $user['User']['email']; ?></small>
					</td>
					<td><?php echo number_format($user['User']['total']); ?></td>
					<td><?php echo number_format($user['User']['rejected_transactions']); ?></td>
					<td><?php 
						echo number_format($user['sum']); 
					?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	</div>
</div>
<?php if ($users) : ?>
	<?php foreach ($users as $user): ?>
		<?php echo $this->Element('modal_user_hellban', array('user' => $user['User'])); ?>
		<?php echo $this->Element('modal_user_remove_hellban', array('user' => $user['User'])); ?>
	<?php endforeach; ?>
<?php endif; ?>
<?php echo $this->Element('modal_user_quickprofile'); ?>
<?php echo $this->Element('modal_user_referrer'); ?>
