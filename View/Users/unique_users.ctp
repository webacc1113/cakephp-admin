<h3>Unique Users</h3>

<p class="count">Showing <?php 
	echo number_format($this->Paginator->counter(array('format' => '{:current}')));
?> of total <?php
	echo number_format($this->Paginator->counter(array('format' => '{:count}')));
?> browsers</p>

<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Unique Id</td>
				<td>User Agent</td>
				<td>Linked Users</td>
				<td></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($unique_users as $unique_user): ?>
				<tr>
					<td><?php echo $unique_user['UniqueUser']['unique_id']; ?></td>
					<td><?php echo $unique_user['UniqueUser']['user_agent']; ?></td>
					<td><?php echo $unique_user['UniqueUser']['linked_users']; ?></td>
					<td><?php echo $this->Html->link('Search', array(
						'action' => 'linked_users', 
						$unique_user['UniqueUser']['unique_id']
					), array(
						'class' => 'btn btn-small btn-default',
						'target' => '_blank'
					)); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php echo $this->Element('pagination'); ?>