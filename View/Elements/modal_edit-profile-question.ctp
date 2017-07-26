<?php echo $this->Form->create('ProfileQuestion', array(
	'url' => array(
		'controller' => 'profile_questions', 
		'action' => 'ajax_edit'
	)
)); ?>
<div id="modal-edit-question" class="modal hide">
	<div class="modal-header">
	<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	<h6 id="modal-tablesLabel">Edit Question</h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
	<button class="btn btn-default" data-dismiss="modal">Close</button>
	<?php echo $this->Form->submit('Save Changes', array('class' => 'btn btn-primary', 'div' => false)); ?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>