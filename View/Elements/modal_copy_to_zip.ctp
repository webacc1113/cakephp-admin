<?php
echo $this->Form->create('LucidZip', array(
	'url' => array(
		'controller' => 'lucidZips',
		'action' => 'add'
	)
));
?>
<div id="modal-copy-to-zip" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Add Zip Code</h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<?php echo $this->Form->submit('Submit', array('class' => 'btn btn-primary', 'div' => false)); ?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>