<div class="alert alert-error" id="referrer-error" style="display: none;"></div>
<div class="alert alert-success" id="referrer-success" style="display: none;"></div>
<?php echo $this->Form->input('id', array(
	'type' => 'hidden', 
	'value' => $user['User']['id']
)); ?>
<table class="table table-normal">
	<thead>
		<tr>
			<td>Balance</td>
			<td>Pending</td>
			<td>Lifetime</td>
		</tr>
	</thead>
	<tbody>
		<tr>
			<td class = "text-center"><?php echo $this->App->number($user['User']['balance']); ?></td>
			<td><?php echo $this->App->number($user['User']['pending']); ?></td>
			<td><?php echo $this->App->number($user['User']['total']); ?></td>
		</tr>
	</tbody>
</table>
<br />
Are you sure you want to rebuild user(#<?php echo $user['User']['id']?>) balance?