<div id="model_pushed_status" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Select email template &amp; subject</h6>
	</div>
	<div class="modal-body">
		<?php echo $this->Form->create('ProjectOption', array(
			'id' => 'PushedStatusForm'
		)); ?>
		<?Php echo $this->Form->input('project_id', array(
			'type' => 'hidden', 
			'value' => $project['Project']['id']
		)); ?>
		<?php echo $this->Form->input('pushed_email_template', array(
			'type' => 'select', 
			'label' => 'Email template',
			'options' => array(
				'survey-funnel' => 'survey-funnel',
				'survey' => 'survey',
				'survey-router-project' => 'survey-router-project',
			),
			'value' => isset($project['ProjectOption']['pushed_email_template']) ? $project['ProjectOption']['pushed_email_template'] : '',
		)); ?>
		<?php echo $this->Form->input('pushed_email_subject', array(
			'type' => 'text', 
			'label' => 'Email subject',
			'value' => isset($project['ProjectOption']['pushed_email_subject']) ? $project['ProjectOption']['pushed_email_subject'] : ''
		)); ?>
		<?php echo $this->Form->end(null); ?>
	</div>
	<div class="modal-footer">
		<button class="btn btn-default" data-dismiss="modal">Cancel</button>
		<button type="button" class="btn btn-primary <?php echo (!empty($project['ProjectOption']['pushed'])) ? 'disabled' : ''?>"  onclick="return MintVine.SavePushedStatus(this, 1);">Pushed On</button>
		<button type="button" class="btn btn-primary <?php echo (empty($project['ProjectOption']['pushed'])) ? 'disabled' : ''?>" onclick="return MintVine.SavePushedStatus(this, 0);">Pushed Off</button>
	</div>
</div>