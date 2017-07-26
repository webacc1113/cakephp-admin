<?php echo $this->Form->create('User', array(
	'url' => array('controller' => 'users', 'action' => 'ajax_referrer'),
	'onsubmit' => 'return MintVine.SetReferrer(this)'
)); ?>
<div id="modal-user-referrer" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Set User Referrer</h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<?php 
			echo $this->Form->submit('Set Referrer', array(
				'class' => 'btn btn-primary'
			)); 
		?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>
