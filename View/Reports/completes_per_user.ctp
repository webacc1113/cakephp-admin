<div class="span12">
	<?php echo $this->Form->create(null); ?>
	<div class="box">
		<div class="box-header">
			<span class="title">Completes Per User</span>
		</div>
		<div class="box-content">
			<div class="padded">
				<?php echo $this->Form->input('days', array(
					'type' => 'text',
					'label' => 'Days since last activity:',
					'value' => isset($this->request->data['Report']['days']) ? $this->request->data['Report']['days'] : '30'
				)); ?>
				<?php echo $this->Form->input('csv', array(
					'type' => 'checkbox',
					'label' => 'As CSV',
				)); ?>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Generate Report', array('class' => 'btn btn-primary')); ?>
			</div>
		</div>
	</div>
	<?php echo $this->Form->end(); ?>

	<?php if (isset($user_counts)) : ?>
	<div class="box">
		<div class="box-header">
			<span class="title">Completes Per User</span>
		</div>
		<table cellpadding="0" cellspacing="0" class="table table-normal">
			<thead>
				<tr>
					<td>Date</td>
					<td>User ID</td>
					<td>Completes</td>
				</tr>
			</thead>
			<tbody>
			<?php foreach ($user_counts as $date => $users): ?>
				<?php foreach ($users as $user_id => $count): ?>
				<tr>
					<td><?php echo $date; ?></td>
					<td><?php echo $user_id; ?></td>
					<td><?php echo $count; ?></td>
				</tr>
				<?php endforeach; ?>
			<?php endforeach; ?>
		</table>
	</div>
	<?php endif; ?>
</div>