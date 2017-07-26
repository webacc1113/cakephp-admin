<?php echo $this->Form->create('Project', array('type' => 'file', 'onsubmit' => 'return MintVine.validateProjectSave(this)')); ?>
<?php echo $this->Form->input('Project.id'); ?>

<script type="text/javascript">
	$(document).ready(function() {
		MintVine.ClientSurveyOptions($('#ProjectClientId')); 
	});
</script>
<div class="box">
	<div class="box-header">
		<?php if ($this->data['Group']['key'] == 'fulcrum'): ?>
			<div class="pull-right" style="padding: 4px;"><?php 
				echo $this->Html->link('Convert to Adhoc Project', '#', array(
					'onclick' => 'return convert_to_adhoc('.$this->data['Project']['id'].', this)',
					'class' => 'btn btn-default btn-small'
				)); 
			?></div>
			<script type="text/javascript">
				function convert_to_adhoc(project_id, node) {
					var $node = $(node);
					if (confirm("Are you sure you wish to convert this to an ad-hoc project? You cannot bring it back to the Lucid group!")) {
						$.ajax({
							type: 'POST',
							url: '/surveys/ajax_convert_project_to_fulcrum/'+ project_id,
							statusCode: {
								201: function(data) {
									location.reload();
								},
								400: function(data) {
									var message = eval("("+data.responseText+")");
									alert(message.message); 
								}
							}
						});
					}
					return false;
				}
			</script>
		<?php endif; ?>
		<span class="title">Edit Project #<?php echo $this->App->project_id($this->data); ?></span>
	</div>
	<div class="box-content">
		<?php if ($this->data['Group']['key'] == 'ssi'): ?>
			<div class="padded">
				<div class="alert alert-warning">
					If you are editing the client rate, the partner rate, or the payout, be sure you also update the <?php 
						echo $this->Html->link('default values for SSI projects', array('controller' => 'settings'));
					?> or the next time we auto-create these projects they will not carry over.
				</div>
			</div>
		<?php endif; ?>
		<div class="row-fluid">
			<div class="span4">
				<div class="padded">

					<div class="row-fluid">
						<div class="span6"><?php 
							echo $this->Form->input('ProjectAdmin.pm_id', array(
								'label' => 'Project Manager(s)',
								'type' => 'select',
								'options' => $project_managers,
								'multiple' => 'checkbox',
								'style' => 'width: 100%;',
								'selected' => $selected_pms,
							)); 
						?></div>
						<div class="span6"><?php 
							echo $this->Form->input('ProjectAdmin.am_id', array(
								'label' => 'Account Manager(s)',
								'type' => 'select',
								'options' => $account_managers,
								'multiple' => 'checkbox',
								'style' => 'width: 100%;',
								'selected' => $selected_ams,
							)); 
						?></div>
					</div>
					
					<?php echo $this->Form->input('Project.client_id', array(
						'after' => ' <small>'.$this->Html->link('Add client', 
							array('controller' => 'clients', 'action' => 'add'),
							array('class' => 'btn btn-small btn-default')
						).'</small>',
						'onchange' => 'return MintVine.ClientSurveyOptions(this)'
					)); ?>
					<?php echo $this->Form->input('Project.client_project_id', array(
						'type' => 'text',
						'label' => 'Client Project ID',
						'class' => 'short',
					)); ?>
					<?php echo $this->Form->input('Project.prj_name', array(
						'label' => 'Project Name'
					)); ?>
					<?php echo $this->Form->input('Project.prj_description', array(
						'type' => 'textarea',
						'label' => 'Project Description'
					)); ?>
					<?php 
					if (isset($group['Group']['key']) && $group['Group']['key'] == 'remesh') {
						echo $this->Form->input('ProjectOption.is_chat_interview', array(						
							'label' => 'Is this Chat Interview?',
							'type' => 'checkbox'							
						)); ?>
						<div class="hide" id="schedule_block"><?php	
							echo $this->Form->input('ProjectOption.interview_date', array(						
								'label' => 'Interview Time',
								'type' => 'datetime',
								'div' => 'custom-date-time',
								'minYear' => date('Y'),							
								'disabled' => true
							)); 
						?></div>
						<script type="text/javascript">MintVine.IsThisRemeshChatInterview('#ProjectOptionIsChatInterview');</script>
					<?php	
						
					}
					?>
					<?php echo $this->Form->input('Project.recontact_id', array(
						'type' => 'text',
						'label' => 'Original Project ID (Recontact)',
						'class' => 'short',
					)); ?>
					<div class="row-fluid">
						<div class="span6"><?php echo $this->Form->input('Project.bid_ir', array(
							'type' => 'text',
							'label' => 'Bid IR',
							'class' => 'short',
						)); ?></div>
						<div class="span6"><?php echo $this->Form->input('Project.priority', array(
							'type' => 'select',
							'label' => 'Priority', 
							'style' => 'width: 196px;', 
							'options' => unserialize(PROJECT_PRIORITY_OPTIONS)
						)); ?></div>
					</div>
					<div class="options-invoke options-client">
						<h5>INVOKE Specific Options</h5>
						<div class="alert info">
							Note: Invoke surveys may use parameters instead of a URL. When provided with these parameters, <strong>leave the Client Survey Link blank</strong>.
						</div>
						<?php echo $this->Form->input('ProjectOption.survey_id', array(
							'type' => 'text',
							'label' => 'Survey ID',
						)); ?>
						<?php echo $this->Form->input('ProjectOption.panel_id', array(
							'type' => 'text',
							'label' => 'Panel ID',
							'value' => 'brs'
						)); ?>
					</div>
							
					<div class="options-typeform options-client">
						<h5>Typeform Specific Options</h5>
						<ol>
							<li><a href="/img/hidden_1.png" target="_blank">Create a hidden field called "uid" in your Typeform survey</a>.</li>
							<li>On the "Thank You" page of your survey, set the page to redirect to: <strong><?php echo HOSTNAME_WWW; ?>/surveys/tf/<?php echo isset($typeform_nonce) ? $typeform_nonce: ''; ?></strong>. 
								<span class="text-error">Warning: do NOT refresh this page, or the special redirect URL will no longer be valid!</span></li>
							<li>Upload the HTML export of the "Full Page" embed found under the "Distribute" tab and the "Embed in a web page" option</li>
						</ol>	
						<?php echo $this->Form->input('ProjectOption.typeform_nonce', array(
							'type' => 'hidden',
							'value' => (isset($typeform_nonce)) ? $typeform_nonce : ''
						)); ?>
						<?php echo $this->Form->input('typeform_html', array(
							'type' => 'file',
							'label' => 'Replace Typeform HTML file',
						)); ?>
					</div>
					
					<div class="client_url_options">		
						<?php echo $this->Form->input('client_survey_link', array(
							'label' => 'Client Survey Link', 
							'div' => 'input client_survey_link',
							'after' => '<strong><small>'.$this->Html->link('Instructions', '#', array(
								'data-target' => '#modal-link-instructions',
								'data-toggle' => 'modal',
								'escape' => false
							)).'</small></strong>'
						)); ?>
					
						<?php echo $this->Form->input('client_end_action', array(
							'type' => 'select',
							'label' => 'Client Complete Action', 
							'options' => array(
								'redirect' => 'Redirect',
								's2s' => 'Server-to-Server Postback'
							)
						)); ?>
					
						<?php echo $this->Form->input('client_links', array(
							'type' => 'file',
							'label' => 'CSV of survey links (DO NOT UPLOAD MORE THAN 2,500 LINKS)',
							'after' => '<small class="text-muted">These links will take precedence over the single survey link set above</small>'
						)); ?>
					
						<?php if (isset($this->request->data['ProjectOption']['links.count']) &&  $this->request->data['ProjectOption']['links.count'] > 0): ?>
							<?php echo $this->Form->input('delete_old_links', array(
								'type' => 'checkbox', 
								'label' => 'Delete your <strong>'.$this->request->data['ProjectOption']['links.count'].'</strong> imported links'
							)); ?>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<div class="span4">
				<div class="padded">					
					<?php echo $this->Form->input('Project.client_rate', array(
						'type' => 'text',
						'label' => 'Client Rate (CR)',
						'between' => '<div class="input-prepend"><span class="add-on" href="#"><i class="icon-none">$</i></span>',
						'after' => '</div>'
					)); ?>
					<?php echo $this->Form->input('Project.partner_rate', array(
						'type' => 'text',
						'label' => 'Partner Rate (PR)',
						'between' => '<div class="input-prepend"><span class="add-on" href="#"><i class="icon-none">$</i></span>',
						'after' => '</div>'
					)); ?>
					<?php echo $this->Form->input('award', array(
						'type' => 'text',
						'label' => 'User Payout',
						'between' => '<div class="input-append award"><span class="add-on" href="#"><i class="icon-none">pts</i></span>',
						'after' => '</div>',
						'required' => true
					)); ?>
					<?php echo $this->Form->input('nq_award', array(
						'type' => 'text',
						'label' => 'NQ Award',
						'between' => '<div class="input-append award"><span class="add-on" href="#"><i class="icon-none">pts</i></span>',
						'after' => '</div>',
						'required' => true
					)); ?>
					<?php echo $this->Form->input('pool', array(
						'type' => 'text',
						'label' => 'Pooled Points',
						'between' => '<div class="input-append award"><span class="add-on" href="#"><i class="icon-none">pts</i></span>',
						'after' => '</div>'
					)); ?>
					
					<?php echo $this->Form->input('language', array(
						'empty' => 'None',
						'options' => $languages,
						'type' => 'select'
					)); ?>
					
					<?php echo $this->Form->input('country', array(
						'value' => isset($country) ? $country: null,
						'options' => $countries,
						'type' => 'select'
					)); ?>
					
					<?php echo $this->Form->input('public', array(
						'label' => 'Public Survey (no invitation required to take)'
					)); ?>
					<?php echo $this->Form->input('Project.landerable', array(
						'type' => 'checkbox',
						'label' => 'Available for direct FB ads',
					)); ?>
					<?php echo $this->Form->input('prescreen', array(
						'label' => 'Activate prescreener for this survey'
					)); ?>
					<?php echo $this->Form->input('skip_mv_prescreen', array(
						'label' => 'Prescreeners only for non-MintVine partners'
					)); ?>
					<?php echo $this->Form->input('address_required', array(
						'type' => 'checkbox', 
						'label' => __('Require Address (MintVine panel ONLY)'),
					)); ?>
					<?php echo $this->Form->input('dedupe', array(
						'type' => 'checkbox',
						'label' => 'Activate Deduper',
					)); ?>
					<?php echo $this->Form->input('Project.router', array(
						'type' => 'checkbox',
						'label' => 'Router',
					)); ?>
					<?php echo $this->Form->input('Project.singleuse', array(
						'type' => 'checkbox',
						'label' => 'Single Entry Only (MintVine Traffic Only)',
					)); ?>
					<?php echo $this->Form->input('desktop', array(
						'type' => 'checkbox', 
						'label' => __('Allow Desktop Access'),
					)); ?>
					<?php echo $this->Form->input('mobile', array(
						'type' => 'checkbox', 
						'label' => __('Allow Mobile Access'),
					)); ?>
					<?php echo $this->Form->input('tablet', array(
						'type' => 'checkbox', 
						'label' => __('Allow Tablet Access'),
					)); ?>
					<?php if (isset($this->data['Group']['key']) && in_array($this->data['Group']['key'], $groups)): ?>
						<?php echo $this->Form->input('Project.ignore_autoclose', array(
							'type' => 'checkbox',
							'label' => 'Ignore Automated Close/Reopens'
						)); ?>
					<?php endif; ?>
				</div>
			</div>
			<div class="span4">
				<div class="padded">
					<?php echo $this->Form->input('survey_name', array(
						'label' => 'Survey Name for Users'
					)); ?>
					<?php echo $this->Form->input('Project.est_length', array(
						'type' => 'text', 
						'label' => 'Survey Length', 
						'placeholder' => 'in minutes',
						'class' => 'short',
					)); ?>
					<?php echo $this->Form->input('minimum_time', array(
						'type' => 'text',
						'label' => 'Minimum Survey Time',
						'placeholder' => 'in minutes',
						'class' => 'short',
					));
					?>
					<?php echo $this->Form->input('Project.quota', array(
						'type' => 'text', 
						'label' => 'Survey Quota',
						'class' => 'short',
						'required' => true
					)); ?>
					
					<?php echo $this->Form->input('description', array(
						'label' => 'Invitation Subject Line'
					));?>
					
					<?php echo $this->Form->input('ip_sensitivity', array(
						'type' => 'select',
						'label' => 'IP Address sensitivity', 
						'options' => array(
							'2' => '2',
							'3' => '3',
							'4' => '4'
						)
					)); ?>
					
					<?php echo $this->Form->input('ip_dupes', array(
						'type' => 'select',
						'label' => 'No. of dupes allowed per IP address', 
						'options' => array(
							'1' => '1',
							'2' => '2'
						)
					)); ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Save Changes', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>
<script type="text/javascript">
	MintVine.PopulateNQAward(false);
</script>
<?php echo $this->Element('modal_link_instructions'); ?>