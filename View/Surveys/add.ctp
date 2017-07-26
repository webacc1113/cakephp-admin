<?php echo $this->Form->create('Project', array('type' => 'file', 'onsubmit' => 'return MintVine.validateProjectSave(this)')); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Create New Survey</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span4">
				<div class="padded">
					<?php echo $this->Form->input('Project.group_id', array(
						'type' => 'select',
						'options' => array($group['Group']['name']),
						'value' => $group['Group']['id'],
						'disabled' => true
					)); ?>

					<div class="row-fluid">
						<div class="span6"><?php 
							echo $this->Form->input('ProjectAdmin.pm_id', array(
								'label' => 'Project Manager(s)',
								'type' => 'select',
								'options' => $project_managers,
								'multiple' => 'checkbox',
								'style' => 'width: 100%;',
								'selected' => $current_user['Admin']['id']
							)); 
						?></div>
						<div class="span6"><?php 
							echo $this->Form->input('ProjectAdmin.am_id', array(
								'label' => 'Account Manager(s)',
								'type' => 'select',
								'options' => $account_managers,
								'multiple' => 'checkbox',
								'style' => 'width: 100%;',
								'selected' => $current_user['Admin']['id']
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
						<div class="hide" id="schedule_block">
						<?php	
						$default_date = mktime('12', '00', '00', date('m'), date('j'), date('Y'));
						echo $this->Form->input('ProjectOption.interview_date', array(						
							'label' => 'Interview Time',
							'type' => 'datetime',
							'div' => 'custom-date-time',
							'selected' => array(
								'year' => date('Y', $default_date),
								'month' => date('m', $default_date),
								'day' => date('d', $default_date),
								'minute' => '00',
								'hour' => '12',
								'meridian' => 'pm'
							),
							'minYear' => date('Y'),							
							'disabled' => true
						)); ?>
						</div>
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
							'label' => 'Survey ID'
						)); ?>
						<?php echo $this->Form->input('ProjectOption.panel_id', array(
							'type' => 'text',
							'label' => 'Panel ID',
							'value' => 'brs'
						)); ?>
					</div>
					
					<div class="options-typeform options-client">
						<h5>Typeform Specific Options</h5>
						<p><strong class="text-error">You must follow these instructions EXACTLY</strong></p>
						<ol>
							<li><a href="/img/hidden_1.png" target="_blank">Create a hidden field called "uid" in your Typeform survey</a>.</li>
							<li>On the "Thank You" page of your survey, set the page to redirect to: <strong><?php echo HOSTNAME_WWW; ?>/surveys/tf/<?php echo $nonce; ?></strong>. 
								<span class="text-error">Warning: do NOT refresh this page, or the special redirect URL will no longer be valid!</span></li>
							<li>Upload the HTML export of the "Full Page" embed found under the "Distribute" tab and the "Embed in a web page" option</li>
						</ol>				
						<?php echo $this->Form->input('typeform_nonce', array(
							'type' => 'hidden',
							'value' => $nonce
						)); ?>
						<?php echo $this->Form->input('typeform_html', array(
							'type' => 'file',
							'label' => 'Typeform HTML file',
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
					<?php
					echo $this->Form->input('pool', array(
						'type' => 'text',
						'label' => 'Pooled Points',
						'between' => '<div class="input-append award"><span class="add-on" href="#"><i class="icon-none">pts</i></span>',
						'after' => '</div>'
					));
					?>
					<?php echo $this->Form->input('language', array(
						'empty' => 'None',
						'type' => 'select'
					)); ?>
					
					<?php echo $this->Form->input('country', array(
						'options' => $countries,
						'value' => 'US',
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
						'checked' => true
					)); ?>
					<?php echo $this->Form->input('Project.router', array(
						'type' => 'checkbox',
						'label' => 'Router',
					)); ?>	
					<?php echo $this->Form->input('Project.singleuse', array(
						'type' => 'checkbox',
						'label' => 'Single Entry Only (MintVine Traffic Only)',
						'checked' => true
					)); ?>
					<?php echo $this->Form->input('desktop', array(
						'type' => 'checkbox', 
						'checked' => true,
						'label' => __('Allow Desktop Access'),
					)); ?>
					<?php echo $this->Form->input('mobile', array(
						'type' => 'checkbox', 
						'checked' => true,
						'label' => __('Allow Mobile Access'),
					)); ?>	
					<?php echo $this->Form->input('tablet', array(
						'type' => 'checkbox', 
						'checked' => true,
						'label' => __('Allow Tablet Access'),
					)); ?>	
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
						'class' => 'short',
						'placeholder' => 'in minutes'
					)); ?>
					<?php echo $this->Form->input('minimum_time', array(
						'type' => 'text', 
						'label' => 'Minimum Survey Time', 
						'class' => 'short',
						'placeholder' => 'in minutes'
					)); ?>
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
						),
						'default' => 4
					)); ?>
					
					<?php echo $this->Form->input('ip_dupes', array(
						'type' => 'select',
						'label' => 'No. of dupes allowed per IP address', 
						'options' => array(
							'1' => '1',
							'2' => '2'
						),
						'default' => 1
					)); ?>
					
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Create Project', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>
<script type="text/javascript">
	MintVine.PopulateNQAward(true);
</script>
<?php echo $this->Element('modal_link_instructions'); ?>
