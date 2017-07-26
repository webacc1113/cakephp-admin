<?php echo $this->Form->input('id', array(
	'type' => 'hidden', 
	'value' => $query_statistic['QueryStatistic']['id']
)); ?>
<?php echo $this->Form->input('quota', array(
	'value' => $query_statistic['QueryStatistic']['quota'],
	'after' => '<span class="muted">Leave as an empty string to not set a quota: setting to 0 will close the query quota.</span>'
)); ?>