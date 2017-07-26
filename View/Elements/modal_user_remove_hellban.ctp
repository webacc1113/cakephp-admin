<div id="modal-user-remove-hellban-<?php echo $user['id']; ?>" class="modal hide">
	<div class="modal-header">
	<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	<h6>UnHellban User</h6>
	</div>
	<div class="modal-body">
		<?php echo $this->Form->create('User', array('data-user' => $user['id'], 'onsubmit' => 'return MintVine.UnHellBan(this)')); ?>
		<div class="alert alert-info" style="display: none;">User has been successfully unhellbanned.</div>
		<?php echo $this->Form->input('reason', array(
			'label' => 'Notes'
		)); ?>
		<?php echo $this->Form->input('id', array('id' => false, 'type' => 'hidden', 'value' => $user['id'])); ?>
		<?php echo $this->Form->submit('UnHellban user', array('id' => false, 'class' => 'btn btn-primary')); ?>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>