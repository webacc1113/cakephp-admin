<?php echo $this->Form->create('QueryStatistic', array(
	'url' => array(
		'controller' => 'queries',
		'action' => 'ajax_update_quota'
	)
)); ?>
<?php
echo $this->Form->input('group_id', array(
	'type' => 'hidden',
	'value' => isset($this->request->query['group_id']) ? $this->request->query['group_id'] : null
));
?>
<div id="modal-query-statistics" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Update Query Quota</h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<?php echo $this->Form->submit('Save Quota', array('class' => 'btn btn-primary', 'div' => false)); ?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>