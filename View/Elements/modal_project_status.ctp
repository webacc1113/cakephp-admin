<?php echo $this->Form->create('Project', array('id' => 'ProjectStatusForm')); ?>
<div id="modal-project" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Edit Status</h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<?php echo $this->Html->link('Change Status', '#', array(
			'onclick' => 'MintVine.SaveProjectStatus(this)',
			'class' => 'btn btn-primary')); ?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>
