<div id="modal-aytm-log-<?php echo $aytm_log_id?>" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Aytm Raw Data</h6>
	</div>
	<div class="modal-body">
		<strong>Headers Data:</strong> <br/><?php echo $headers_raw;?>
		<br/><br/><strong>Post Request Data:</strong> <br/><?php echo $post_raw;?>
		<br/><br/><strong>Get Request Data:</strong> <br/><?php echo $get_raw;?>
	</div>
	<div class="modal-footer">		
	</div>
</div>
