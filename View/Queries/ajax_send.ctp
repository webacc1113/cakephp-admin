<?php 
	echo $this->Form->input('query', array(
		'type' => 'select', 
		'options' => $queries,
		'empty' => 'Select query:',
		'onchange' => 'return MintVine.QueryData(this)',
		'after' => ' <span id="waiting" style="display: none;">Loading... please wait</span>'
	)); 
?>

<p class="matched" style="display: none;">This query has matched a total of <span></span> users.</p>
<div class="query_additional" style="display: none;">
	<?php echo $this->Form->input('reach', array(
		'type' => 'text',
		'label' => 'No of users',
		'style' => 'width: 80px',
		'after' => ' <small id="reach-text"></small>',
	)); ?>
</div>

<?php echo $this->Form->input('survey_id', array(
	'type' => 'hidden',
	'value' => $survey_id
)); ?>

<p><small>Note: Each 10,000 users will require about 1 minute of processing time on this page.</small></p>
<div class="alert alert-danger" id="zero" style="display: none;">You have no more users left to query for this survey.</div>
<div class="alert alert-danger" id="killed" style="display: none;">You have completed reaching all active users. No more users will be reached with this query.</div>