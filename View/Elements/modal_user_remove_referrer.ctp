<?php echo $this->Form->create('User', array(
	'url' => array('controller' => 'users', 'action' => 'ajax_remove_referrer'),
	'onsubmit' => 'return MintVine.RemoveReferrer(this)'
)); ?>
<div id="modal-user-remove-referrer" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Remove User Referrer</h6>
	</div>
	<div class="modal-body">
		
	</div>
	<div class="modal-footer">
		<?php 
			echo $this->Form->submit('Remove Referrer', array(
				'class' => 'btn btn-primary'
			)); 
		?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>
