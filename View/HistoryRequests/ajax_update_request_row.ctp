<?php if ($report_type == 'other') { ?>
	<td>
		<?php echo $this->Element('user_dropdown', array('user' => $history_request['User'])); ?>
		<small><?php echo $history_request['User']['email']; ?></small>
	</td>
<?php } ?>
<?php if ($report_type == 'redeemed') { ?>
	<td><?php echo $this->Html->link('#'.$history_request['HistoryRequest']['project_id'], array('controller' => 'surveys', 'action' => 'dashboard', $history_request['HistoryRequest']['project_id'])); ?></td>
	<td><?php echo $history_request['Project']['award']; ?></td>
<?php } ?>
<td><?php echo ($history_request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_REJECTED) ? '0' : $history_request['Transaction']['amount']; ?></td>
<td><?php 
	$REPORTS = unserialize(SURVEY_REPORT_TYPES);
	echo $REPORTS[$history_request['HistoryRequest']['report']]; 
	if ($history_request['HistoryRequest']['report'] == SURVEY_REPORT_LATE_NQ_OQ) {
		echo '<br />(Answered questions : <b>'. $history_request['HistoryRequest']['answered'].'</b>)';
	}
?></td>
<td><?php echo $history_request['HistoryRequest']['statement']; ?></td>
<td>
	<?php echo $this->Time->format($history_request['HistoryRequest']['created'], Utils::dateFormatToStrftime('Y-m-d h:i A'), false, $timezone); ?>
</td>
<td><?php 
	if ($history_request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_APPROVED) { ?>
		<b class="label label-green label-transaction">Approved</b><?php 
	} 
	elseif ($history_request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_REJECTED) { ?>
		<b class="label label-red label-transaction">Rejected</b><?php 
	}
	else { ?>
		<b class="label label-transaction">Pending</b><?php
	} ?>
</td>
<td><?php 
	echo $this->Html->link('Search', array(
		'controller' => 'history_requests',
		'action' => 'info', 
		$history_request['HistoryRequest']['id']
	), array(
		'class' => 'btn btn-default btn-mini',
		'target' => '_blank'
	)); ?> <?php 
	if ($history_request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_REJECTED) {
		echo $this->Html->link('Approve', array(
			'action' => 'ajax_approve',
			$history_request['HistoryRequest']['id'], '?' => array(
				'submit_update_row' => true,
				'report_type' => $report_type
			)
		), array(
			'escape' => false,
			'div' => false,
			'class' => 'btn btn-mini btn-success',
			'data-toggle' => 'modal',
			'data-target' => '#modal-approve-history_request',
			'id' => 'approve-history-'.$history_request['HistoryRequest']['id']
		));
	} 
	elseif ($history_request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_APPROVED) { 
		echo $this->Html->link('Reject', array(
			'action' => 'ajax_reject',
			$history_request['HistoryRequest']['id'], '?' => array(
				'submit_update_row' => true,
				'report_type' => $report_type
			)
		), array(
			'div' => false,
			'class' => 'btn btn-mini btn-danger',
			'data-toggle' => 'modal',
			'data-target' => '#modal-reject-history_request',
			'id' => 'reject-history-'.$history_request['HistoryRequest']['id']
		));
	} ?>
</td>