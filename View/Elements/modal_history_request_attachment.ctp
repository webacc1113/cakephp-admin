<?php echo $this->Form->create('HistoryRequestAttachment', array('id' => 'HistoryRequestAttachmentForm')); ?>
<div id="modal-attachment-history_request" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Attached Screenshot</h6>
	</div>
	<div class="modal-body reported-attachment">
	</div>
	<div class="modal-footer">
		<?php echo $this->Html->link('Download', '#', array(
			'onclick' => 'MintVine.DownloadReportedAttachment(this)',
			'class' => 'btn btn-primary'));	?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>
