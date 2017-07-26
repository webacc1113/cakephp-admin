<?php echo $this->Form->create('HistoryRequest', array('id' => 'HistoryRequestApproveForm', 'onsubmit' => 'return MintVine.ApproveHistoryRequest("#approve-request-submit")')); ?>
<div id="modal-approve-history_request" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Approve panelist history request</h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<?php echo $this->Html->link('Submit', 'javascript:void(0)', array(
			'onclick' => 'MintVine.ApproveHistoryRequest(this)',
			'class' => 'btn btn-primary',
			'id' => 'approve-request-submit'
		)); ?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>
