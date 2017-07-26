<?php echo $this->Form->create('Query', array('url' => array('controller' => 'queries', 'action' => 'send'))); ?>
<div id="modal-query-send" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Send Query for <?php echo $this->App->project_name($project); ?></h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<?php 
			echo $this->Form->submit('Send Query', array(
				'class' => 'btn btn-primary'
			)); 
		?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>

<?php echo $this->Form->create('Query', array('url' => array('controller' => 'queries', 'action' => 'ajax_quick_send'))); ?>
<div id="modal-query-quick-send" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Quick Send for <?php echo $this->App->project_name($project); ?></h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<?php 
			echo $this->Form->submit('Quick Send', array(
				'class' => 'btn btn-primary'
			)); 
		?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>
