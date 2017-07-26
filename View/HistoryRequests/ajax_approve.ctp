<div class="alert" style="display:none;"></div>
<?php echo $this->Form->input('request_id', array('id' => 'approve-request_id', 'value' => $request_id, 'type' => 'hidden')); ?>
<?php echo $this->Form->input('submit_to_next', array('value' => $submit_to_next, 'type' => 'hidden')); ?>
<?php echo $this->Form->input('submit_update_row', array('value' => $submit_update_row, 'type' => 'hidden')); ?>
<?php echo $this->Form->input('report_type', array('value' => $report_type, 'type' => 'hidden')); ?>
<?php echo $this->Form->input('amount', array(
	'type' => 'text',
	'value' => isset($amount) ? $amount : null,
	'label' => 'Project point value'
)); ?>
<script type="text/javascript">
	var el = $("#amount").get(0);
    var elem_len = el.value.length;

    el.selectionStart = elem_len;
    el.selectionEnd = elem_len;
    el.focus();
</script>