<h2>Convert Respondent IDs</h2>
<p class="lead">Upload a CSV with respondent IDs in a column and you'll get a CSV with respondent IDs and their correlating MintVine IDs</p>

<?php echo $this->Form->create(null, array('type' => 'file')); ?>
<?php echo $this->Form->input('file', array('type' => 'file')); ?>
<?php echo $this->Form->submit('Convert', array('class' => 'btn btn-primary')); ?>
<?php echo $this->Form->end(null); ?>