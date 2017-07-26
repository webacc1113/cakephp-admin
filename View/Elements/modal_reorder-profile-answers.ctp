<?php echo $this->Form->create('ProfileQuestion', array(
	'url' => array(
		'controller' => 'profile_questions', 
		'action' => 'ajax_edit'
	)
)); ?>
<div id="modal-reorder-answers" class="modal hide">
	<div class="modal-header">
	<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	<h6 id="modal-tablesLabel">Reorder Answers</h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
	<button class="btn btn-default" data-dismiss="modal">Close</button>
	</div>
</div>
<?php echo $this->Form->end(null); ?>