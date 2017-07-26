<div id="modal-user-hellban-<?php echo $user['id']; ?>" class="modal hide">
	<div class="modal-header">
	<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	<h6>Hellban User</h6>
	</div>
	<div class="modal-body">
		<?php echo $this->Form->create('User', array('data-user' => $user['id'], 'onsubmit' => 'return MintVine.HellBan(this)')); ?>
		<div class="alert alert-info" style="display: none;">User has been successfully hellbanned.</div>
		<?php echo $this->Form->input('hellban_reason', array(
			'label' => 'Notes'
		)); ?>
		<?php echo $this->Form->input('id', array('id' => false, 'type' => 'hidden', 'value' => $user['id'])); ?>
		<?php echo $this->Form->submit('Hellban user', array('id' => false, 'class' => 'btn btn-primary')); ?>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>