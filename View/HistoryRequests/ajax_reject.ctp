<?php echo $this->Form->input('request_id', array('id' => 'reject-request_id', 'value' => $request_id, 'type' => 'hidden')); ?>
<?php echo $this->Form->input('submit_to_next', array('value' => $submit_to_next, 'type' => 'hidden')); ?>
<?php echo $this->Form->input('submit_update_row', array('value' => $submit_update_row, 'type' => 'hidden')); ?>
<?php echo $this->Form->input('report_type', array('value' => $report_type, 'type' => 'hidden')); ?>
<?php echo $this->Form->input('reason', array(
	'type' => 'textarea',
	'label' => 'Reason:'
)); ?>
<script type="text/javascript">
    $("#reason").focus();
</script>