<div class="box">
	<?php echo $this->Form->create('SurveyPartner', array()); ?>
	<div class="box-header"><span class="title">Edit Partner</span></div>
	<div class="box-content padded">
		<?php echo $this->Form->input('survey_id', array(
			'type' => 'hidden'
		)); ?>
		<?php echo $this->Form->input('partner_id', array(
			'type' => 'select', 
			'options' => $partner_list,
			'disabled' => true
		)); ?>		
		<?php echo $this->Form->input('rate', array(
			'type' => 'text',
			'between' => '<div class="input-prepend"><span class="add-on" href="#"><i class="icon-none">$</i></span>',
			'after' => '</div>'
		)); ?>
		
		<?php $url = HOSTNAME_REDIRECT.'/go/'.$project['Project']['id'].'-'.$project['Project']['code'].'?pid='.$this->data['SurveyPartner']['partner_id'].'&uid='; ?>									
		<?php echo $this->Form->input('partner_link', array(
			'type' => 'text', 
			'value' => $url,
			'label' => 'Partner Link (Not Editable)',
			'onclick' => 'return $(this).select()'
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
			'label' => 'Partner Quota/Term Link'
		)); ?>
		<?php echo $this->Form->input('pause_url', array(
			'type' => 'text', 
			'label' => 'Paused Link (Optional)'
		)); ?>
		<?php echo $this->Form->input('fail_url', array(
			'type' => 'text', 
			'label' => 'Security Fail Link (Optional)'
		)); ?>
	</div>
	<div class="form-actions">
		<?php echo $this->Form->submit('Save', array('class' => 'btn btn-primary')); ?> <?php 
			echo $this->Html->link('Cancel', array('action' => 'dashboard', $project['Project']['id']), array('class' => 'btn btn-default'));
		?>
	</div>
	<?php echo $this->Form->end(null); ?>
</div>