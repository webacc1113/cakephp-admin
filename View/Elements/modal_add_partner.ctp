<div id="modal-add-partner" class="modal hide">
	<div class="modal-header">
	<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	<h6 id="modal-tablesLabel">Add Partner to this Project</h6>
	</div>
	<div class="modal-body">
		<?php echo $this->Form->create('SurveyPartner', array(
			'id' => 'PartnerAddForm'
		)); ?>
		<?Php echo $this->Form->input('survey_id', array(
			'type' => 'hidden', 
			'value' => $project['Project']['id']
		)); ?>
		<?php echo $this->Form->input('partner_id', array(
			'type' => 'select', 
			'options' => $partner_list
		)); ?>
		<?php echo $this->Form->input('rate', array(
			'type' => 'text', 
			'value' => $project['Project']['partner_rate'],
			'between' => '<div class="input-prepend"><span class="add-on" href="#"><i class="icon-none">$</i></span>',
			'after' => '</div>'
		)); ?>
		<p>Available variables: {{ID}}, {{TIMESTAMP}}, {{HASH}}</p>
		<?php echo $this->Form->input('complete_url', array(
			'type' => 'text', 
			'label' => 'Partner Success Link'
		)); ?>
		<?php echo $this->Form->input('nq_url', array(
			'type' => 'text', 
			'label' => 'Partner Disqualification Link'
		)); ?>
		<?php echo $this->Form->input('oq_url', array(
			'type' => 'text', 
			'label' => 'Partner Quota Link'
		)); ?>
		<?php echo $this->Form->input('pause_url', array(
			'type' => 'text', 
			'label' => 'Paused Link (Optional)'
		)); ?>
		<?php echo $this->Form->input('fail_url', array(
			'type' => 'text', 
			'label' => 'Security Fail Link (Optional)'
		)); ?>
		<?php echo $this->Form->end(null); ?>
	</div>
	<div class="modal-footer">
	<button class="btn btn-default" data-dismiss="modal">Cancel</button>
	<button type="button" class="btn btn-primary" onclick="return MintVine.SaveSurveyPartner(this);">Add Partner to Project</button>
	</div>
</div>