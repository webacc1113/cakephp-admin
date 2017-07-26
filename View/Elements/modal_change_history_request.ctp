<?php echo $this->Form->create('HistoryRequest', array(
	'url' => array('controller' => 'history_requests', 'action' => 'ajax_change_project'),
	'onsubmit' => 'return MintVine.ChangeProject(this)'
)); ?>
<div id="modal-change-history_request" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Change project number</h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<?php 
			echo $this->Form->submit('Submit', array(
				'class' => 'btn btn-primary'
			)); 
		?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>
