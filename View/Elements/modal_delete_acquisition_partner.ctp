<?php echo $this->Form->create('AcquisitionPartner', array(
	'id' => 'AcquisitionPartnerForm',
	'inputDefaults' => array(
		'div' => false,
		'wrapInput' => false
	),
	'url' => array(
		'action' => 'delete'
	)
)); ?>
<div id="modal-delete-acquisition_partner" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Delete Acquisition Partner</h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<?php echo $this->Html->link('Cancel', 'javascript:void(0)', array(
			'type' => 'button',
			'div' => false,
			'label' => false,
			'class' => 'btn btn-default',
			'data-dismiss' => 'modal'
		));?>
		<?php echo $this->Form->button('Delete', array(
			'type' => 'submit',
			'div' => false,
			'label' => false,
			'class' => 'btn btn-danger'
		));?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>
