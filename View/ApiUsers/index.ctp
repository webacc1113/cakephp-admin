<h3>Api Users</h3>
<div class="row-fluid">
	<div class="span9">
		<?php echo $this->Html->link('Add new user', array('action' => 'add'), array('class' => 'btn btn-mini btn-success')); ?>
	</div>
	<div class="span3">
		<?php echo $this->Form->create(null, array(
			'class' => 'clearfix form-inline',
			'type' => 'get',
			'url' => array(
				'controller' => 'api_users',
				'action' => 'index',
			)
		));?>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>

<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td><?php echo $this->Paginator->sort('ApiUser.username', 'Username'); ?></td>
				<td>Admin User</td>
				<td>Group</td>
				<td>Client</td>
				<td>Live Mode</td>
<!--				<td>--><?php //echo $this->Paginator->sort('ApiUser.type', 'Type'); ?><!--</td>-->
				<td><?php echo $this->Paginator->sort('ApiUser.active', 'Active'); ?></td>
				<td>Notes</td>
				<td>Modified</td>
				<td class="actions"></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($api_users as $api_user): ?>
				<tr>
					<td><?php echo $api_user['ApiUser']['username']; ?></td>
					<td><?php echo $api_user['Admin']['admin_user']; ?></td>
					<td><?php echo $api_user['Group']['name']; ?></td>
					<td><?php echo $api_user['Client']['client_name']; ?></td>
					<td>
						<?php if ($api_user['ApiUser']['livemode']):?>
							<?php echo $this->Html->link('On', '#', array('class' => 'btn btn-mini btn-success', 'onclick' => 'return MintVine.LivemodeApiUser('.$api_user['ApiUser']['id'].', this)')); ?>
						<?php else:?>
							<?php echo $this->Html->link('Off', '#', array('class' => 'btn btn-mini btn-default', 'onclick' => 'return MintVine.LivemodeApiUser('.$api_user['ApiUser']['id'].', this)')); ?>
						<?php endif;?>
					</td>
					<td>
						<?php if ($api_user['ApiUser']['active']):?>
							<?php echo $this->Html->link('Active', '#', array('class' => 'btn btn-mini btn-success', 'onclick' => 'return MintVine.ActiveApiUser('.$api_user['ApiUser']['id'].', this)')); ?>
						<?php else:?>
							<?php echo $this->Html->link('Inactive', '#', array('class' => 'btn btn-mini btn-default', 'onclick' => 'return MintVine.ActiveApiUser('.$api_user['ApiUser']['id'].', this)')); ?>
						<?php endif;?>
					</td>
					<td><?php echo $api_user['ApiUser']['notes']; ?></td>
					<td><?php echo $this->Time->format($api_user['ApiUser']['modified'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, $timezone); ?></td>
					<td class="nowrap">
						<?php echo $this->Html->link('Edit', array('action' => 'edit', $api_user['ApiUser']['id']), array('class' => 'btn btn-mini btn-primary')); ?>
						<?php echo $this->Html->link('Delete', '#', array('class' => 'btn btn-mini btn-danger', 'onclick' => 'return MintVine.DeleteApiUser('.$api_user['ApiUser']['id'].', this)')); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php echo $this->Form->end(null); ?>
<?php echo $this->Element('pagination'); ?>
