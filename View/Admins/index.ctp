<style>
	td .input {
		margin: 0;
	}
</style>
<h3>Admin Users</h3>
<div class="row-fluid">
	<div class="span6">
		<?php echo $this->Html->link('Add new admin', array('action' => 'add'), array('class' => 'btn btn-mini btn-success')); ?>
		<?php echo $this->Html->link('Permission groups', array('controller' => 'roles', 'action' => 'index'), array('class' => 'btn btn-mini btn-primary')); ?>
		<?php if (!empty($this->request->query)): ?>
			<?php echo $this->Html->link('<i class="icon-remove-sign"></i> Clear filters', array('action' => 'index'), array('class' => 'btn btn-mini btn-default', 'escape' => false)); ?>
		<?php endif; ?>
	</div>
	<div class="span3">
		<?php echo $this->Form->create(null, array(
			'class' => 'clearfix form-inline',
			'type' => 'get',
			'url' => array(
				'controller' => 'admins',
				'action' => 'index',
			)
		));?>
		<div class="form-group">
			<?php echo $this->Form->input('role_id', array(
				'empty' => 'Select Role',
				'label' => false,
				'value' => isset($this->request->query['role_id']) ? $this->request->query['role_id'] : null
			)); ?>
			<?php echo $this->Form->submit('Show', array('class' => 'btn btn-default')); ?>
		</div>
		<?php echo $this->Form->end(null); ?>
	</div>
	<div class="span3">
		<?php echo $this->Form->create(null, array(
			'class' => 'clearfix form-inline',
			'type' => 'get',
			'url' => array(
				'controller' => 'admins',
				'action' => 'index',
			)
		));?>
		<?php echo $this->element('user_groups');?>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>

<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td><?php echo $this->Paginator->sort('Admin.admin_user', 'Username'); ?></td>
				<td><?php echo $this->Paginator->sort('Admin.admin_email', 'Email'); ?></td>
				<td>Project groups</td>
				<td>Permission groups</td>
				<td><?php echo $this->Paginator->sort('Admin.active', 'Active?'); ?></td>
				<td>Authy</td>
				<td>2FA</td>
				<td></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($admins as $admin): ?>
				<tr id="<?php echo $admin['Admin']['id']; ?>">
					<td>
						<?php echo $admin['Admin']['admin_user']; ?>
					</td>
					<td>
						<?php echo $admin['Admin']['admin_email']; ?>
					</td>
					<td>
						<?php if (!empty($admin['AdminGroup'])): ?>
							<?php $groups = array(); ?>
							<?php foreach ($admin['AdminGroup'] as $group): ?>
								<?php $groups[] = $group['Group']['name']; ?>
							<?php endforeach; ?>
							<?php echo implode(', ', $groups); ?>
						<?php endif; ?>
					</td>
					<td>
						<?php if (!empty($admin['AdminRole'])): ?>
							<?php $roles = array(); ?>
							<?php foreach ($admin['AdminRole'] as $role): ?>
								<?php $roles[] = $role['Role']['name']; ?>
							<?php endforeach; ?>
							<?php echo implode(', ', $roles); ?>
						<?php endif; ?>
					</td>
					<td>
						<?php if ($admin['Admin']['active']):?>
							<?php echo $this->Html->link('Active', '#', array('class' => 'btn btn-mini btn-success', 'onclick' => 'return MintVine.ActiveAdmin('.$admin['Admin']['id'].', this)')); ?>
						<?php else:?>
							<?php echo $this->Html->link('Inactive', '#', array('class' => 'btn btn-mini btn-default', 'onclick' => 'return MintVine.ActiveAdmin('.$admin['Admin']['id'].', this)')); ?>
						<?php endif;?>
					</td>
					<td>
						<?php if (!empty($admin['Admin']['phone_country_code'])): ?>
							<?php echo $admin['Admin']['phone_country_code']; ?>
						<?php endif; ?>
						<?php echo $admin['Admin']['phone_number'];?>
						<?php if ($admin['Admin']['phone_number'] && empty($admin['Admin']['authy_user_id'])):?>
							<p><?php echo $this->Html->link('Register', '#', array('class' => 'btn btn-mini btn-default', 'onclick' => 'return MintVine.AuthyRegister('.$admin['Admin']['id'].', this)')); ?></p>
						<?php elseif ($admin['Admin']['phone_number'] && $admin['Admin']['authy_user_id']):?>	
							<p><span class="label label-success">Registered</span></p>
						<?php endif;?>
					</td>
					<td>
						<?php echo $this->Form->input('Admin.authenticate_type', array(
							'type' => 'select',
							'label' => false,
							'options' => array('custom_code' => 'App Code', 'sms' => 'SMS'),
							'empty' => 'None',
							'value' => $admin['Admin']['authenticate_type'],
							'style' => 'width: 100%; margin: 0'
						)); ?>
					</td>
					<td>
						<?php echo $this->Html->link('Edit', array('action' => 'edit', $admin['Admin']['id']), array('class' => 'btn btn-mini btn-primary')); ?> 
						<?php echo $this->Html->link('Delete', '#', array('class' => 'btn btn-mini btn-warning', 'onclick' => 'return MintVine.DeleteAdmin('.$admin['Admin']['id'].', this)')); ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php echo $this->Element('pagination'); ?>
<script>
	$(document).ready(function() {
		$('table tr td select').change(function() {
			var admin_id = $(this).closest('tr').attr('id');
			var authenticate_type = $(this).val();
			$.ajax({
				type: 'POST',
				url: '/admins/set_authenticate_type/',
				data: {id: admin_id, authenticate_type, authenticate_type: authenticate_type},
				statusCode: {
					201: function(data) {

					}
				}
			});
		});
	});
</script>
