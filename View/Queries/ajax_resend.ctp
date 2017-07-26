<p>This will resend emails to all users who have not yet completed or disqualified out of this survey.</p>
<p>A new query history item will be created to reflect the new push - and the counts for previous query histories will be updated to reflect the reach.</p>
<?php echo $this->Form->input('id', array(
	'type' => 'hidden', 
	'value' => $query['Query']['id']
)); ?>