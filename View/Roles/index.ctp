<h3>Permission Groups</h3>
<p>
	<?php echo $this->Html->link('Add permission group', array('action' => 'add'), array('class' => 'btn btn-mini btn-success')); ?>
</p>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td><?php echo $this->Paginator->sort('Role.name', 'Group'); ?></td>
				<td><?php echo $this->Paginator->sort('Role.admin', 'Admin Access'); ?></td>
				<td><?php echo $this->Paginator->sort('Role.users', 'Access Users'); ?></td>
				<td><?php echo $this->Paginator->sort('Role.reports', 'Access Reports'); ?></td>
				<td><?php echo $this->Paginator->sort('Role.projects', 'Access Projects'); ?></td>
				<td><?php echo $this->Paginator->sort('Role.transactions', 'Access Transactions'); ?></td>
				<td><?php echo $this->Paginator->sort('Role.campaigns', 'Access Campaigns'); ?></td>
				<td></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($roles as $role): ?>
				<tr>
					<td><?php echo $role['Role']['name']; ?></td>
					<td><?php echo ($role['Role']['admin']) ? '<div class="label label-success">Yes</div>' : '<div class="label label-red">No</div>'; ?></td>
					<td><?php echo ($role['Role']['users']) ? '<div class="label label-success">Yes</div>' : '<div class="label label-red">No</div>'; ?></td>
					<td><?php echo ($role['Role']['reports']) ? '<div class="label label-success">Yes</div>' : '<div class="label label-red">No</div>'; ?></td>
					<td><?php echo ($role['Role']['projects']) ? '<div class="label label-success">Yes</div>' : '<div class="label label-red">No</div>'; ?></td>
					<td><?php echo ($role['Role']['transactions']) ? '<div class="label label-success">Yes</div>' : '<div class="label label-red">No</div>'; ?></td>
					<td><?php echo ($role['Role']['campaigns']) ? '<div class="label label-success">Yes</div>' : '<div class="label label-red">No</div>'; ?></td>
					<td>
						<?php echo $this->Html->link('Edit', array('action' => 'edit', $role['Role']['id']), array('class' => 'btn btn-mini btn-primary')); ?> 
						<?php echo $this->Html->link('Delete', '#', array('class' => 'btn btn-mini btn-warning', 'onclick' => 'return MintVine.DeleteRole('.$role['Role']['id'].', this)')); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php echo $this->Element('pagination'); ?>