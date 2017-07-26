<?php echo $this->Form->create('User', array(
	'url' => array('controller' => 'users', 'action' => 'ajax_rebuild_balance'),
	'onsubmit' => 'return MintVine.RebuildBalance(this)'
)); ?>
<div id="modal-user-rebuild-balance" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Rebuild User Balance</h6>
	</div>
	<div class="modal-body">
		
	</div>
	<div class="modal-footer">
		<?php 
			echo $this->Form->submit('Rebuild Balance', array(
				'class' => 'btn btn-primary'
			)); 
		?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>
