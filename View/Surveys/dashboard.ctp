<?php $this->Html->script('/js/clipboard/jquery.zclip.min.js', array('inline' => false)); ?>
<?php $_STATUSES = unserialize(PROJECT_STATUSES); ?>
<script type="text/javascript">
	<?php if (isset($qualifications) && !empty($qualifications)): ?>
		var project = <?php echo json_encode($project); ?>;
		var qualifications = <?php echo json_encode($qualifications)?>;
		var processingTimer = null;
		$(document).ready(function() {
			setTimeout(function() {
			    $("#copy_addr").zclip({
			    	path: "/js/clipboard/ZeroClipboard.swf",
			    	copy: function() {
			    		return $("#url_to_copy").val();
			    	},
			    	afterCopy: function() {}
				});
			}, 800);
			flag = true;
			for (var i = 0; i < qualifications.length; i ++) {
				var id = qualifications[i]['Qualification']['id'];
				if (qualifications[i]['Qualification']['processing'] != null) {
					$('.detail_group_' + id).hide();
					$('#invited_processing_' + id).show();
					flag = false;
				}
				else {
					$('.detail_group_' + id).show();
					$('#invited_processing_' + id).hide();
				}
			}
			if (!flag) {
				timer();
			}
			$('#modal-edit-quotas #save_btn').click(function() {
				var qualification_info =  {};
				qualification_info.id = $('#modal-edit-quotas #qualification_id').val();
				qualification_info.name = $('#modal-edit-quotas #qualification_name').val();
				qualification_info.quota = $('#modal-edit-quotas #qualification_quota').val();
				qualification_info.cpi = $('#modal-edit-quotas #qualification_cpi').val();
				qualification_info.award = $('#modal-edit-quotas #qualification_award').val();
				$.ajax({
					type: 'POST',
					url: '/surveys/ajax_edit_quotas/' + qualification_info.id,
					data: qualification_info,
					statusCode: {
						201: function(data) {
							for (c in data) {
								if (c != 'id' && c != 'parent_id') {
									if (c == 'cpi') {
										var cpi = '$' + parseFloat(data[c]).toFixed(2).replace(/(\d)(?=(\d\d\d)+(?!\d))/g, "$1,");
										$('#qualifications_table #qualification_' + data['id']).find('.' + c + ' a').text(cpi);
									}
									else if (c == 'name') {
										if (data['parent_id'] == null) {
											$('#qualifications_table .' + data['id'] + '_children').each(function() {
												if ($(this).find('.name span').length > 0) {
													$(this).find('.name span').text(data[c]);
												}
											});
										}
										$('#qualifications_table #qualification_' + data['id'] + ' .name a').text(data[c]);
									}
									else {
										$('#qualifications_table #qualification_' + data['id']).find('.' + c + ' a').text(data[c]);
									}
								}
							}
						}
					}
				});
			});
			
			$('#qualifications_table .parent').each(function() {
				var qualification_id = $(this).attr('id').split('_')[1];
				if ($(this).find('.action a').hasClass('btn-danger')) {
					$('#qualifications_table .' + qualification_id + '_children .action a').attr('disabled', 'disabled');
				}
			});
		});
	function timer() {
		if (processingTimer) {
			clearTimeout(processingTimer);
			processingTimer = null;
		}
		processingTimer = setTimeout(function() {
			if (!flag) {
				timer();
			}
			$.post('/surveys/ajax_get_processing', {project_id: project['Project']['id']}, function(data) {
				var processings = data['processings'];
				flag = true;
				for (var i = 0; i < processings.length; i ++) {
					var id = processings[i]['id'];
					if (processings[i]['processing'] != null) {
						$('.detail_group_' + id).hide();
						$('#invited_processing_' + id).show();
						flag = false;
					}
					else {
						$('.detail_group_' + id).show();
						$('#invited_processing_' + id).hide();
						var j = 0;
						$('.detail_group_' + id).each(function () {
							if (j == 0) {
								var invited = parseInt(processings[i]['invited']);
								if (invited > 0) {
									var format_invited = invited.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
									$(this).find('a').remove();
									var link = '<a href="/reports/qualification_users/' + id + '">' + format_invited + '</a>';
									$(this).append(link);
								}
								else {
									$(this).text(invited);
								}
							}
							else if (j == 1) {
								var emailed = parseInt(processings[i]['notified_email']);
								if (emailed > 0) {
									var format_emailed = emailed.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
									$(this).find('a').remove();
									var link = '<a href="/reports/qualification_users/' + id + '?emailed=1">' + format_emailed + '</a>';
									$(this).append(link);
								}
								else {
									$(this).text(emailed);
								}
							}
							else if (j == 2){
								var completes = parseInt(processings[i]['completes']);
								var format_number = completes.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
								$(this).text(format_number);
							}
							else if (j == 3){
								var clicks = parseInt(processings[i]['clicks']);
								var format_number = clicks.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
								$(this).text(format_number);
							}
							else if (j == 4){
								var nqs = parseInt(processings[i]['nqs']);
								var format_number = nqs.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
								$(this).text(format_number);
							}
							else if (j == 5){
								var oqs = parseInt(processings[i]['oqs']);
								var format_number = oqs.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
								$(this).text(format_number);
							}
							j ++;
						});
					}
				}
			});

		}, 8000);
	}
	<?php endif; ?>
</script>
<div class="box">
	<div class="area-top clearfix">
		<div class="pull-left header">
			<h3 class="title">
				<?php if (!empty($project['Project']['recontact_id'])): ?>
					<i class="icon-screenshot"></i>
				<?php endif; ?>
            	<strong>                    
                    <?php echo $this->Html->link(
						'#' . $this->App->project_id($project), array('action' => 'project_logs', $project['Project']['id'])
					); ?>
					<?php if ($project['Group']['key'] == 'points2shop') : ?>
						<?php echo $this->Html->link(
							$project['Project']['prj_name'], array('controller' => 'points2shop_logs', 'action' => 'search', '?' => array('project_id' =>  $project['Project']['mask']))
						); ?>
					<?php else : ?>
                    	<?php echo ' ' . $project['Project']['prj_name']; ?>
                	<?php endif; ?>
                </strong> 
				<?php if (!empty($project['Project']['recontact_id'])): ?>
					(Recontact #<?php echo $project['Project']['recontact_id']; ?>)
				<?php endif; ?>
            </h3>
			<h5><?php 
				echo $this->Html->link($project['Group']['name'], array('controller' => 'projects', 'action' => 'index', '?' => array('group_id' => $project['Group']['id']))); 
			?> | <?php echo $project['Project']['survey_name']; ?> | 
			<?php if (!empty($project['Project']['survey_code'])): ?>
				<span class="muted"><?php echo $project['Project']['survey_code']; ?> | </span>
			<?php endif; ?>
			<?php if ($project['Project']['address_required']): ?>
				<span class="label label-info">Address Required</span> 
			<?php endif; ?>
			<?php if ($sampled_to_live): ?>
				<span class="label label-info">From sampling</span>
			<?php endif; ?>
			<?php if ($project['FedSurvey']['direct']): ?>
				<span class="label label-green">Direct</span>
			<?php endif; ?>
			<?php if (!empty($project['Project']['language'])) : ?>
				<span class="label label-blue">Language: <?php echo $project['Project']['language']; ?></span>
			<?php endif; ?>
			<?php if (!empty($project['Project']['country'])): ?>			
				<?php echo $this->Html->image('/img/flags/'.strtolower($project['Project']['country']).'.png'); ?> 
			<?php endif; ?>
			<br/>
				<?php if (!empty($project['ProjectOption']['links.count']) && $project['ProjectOption']['links.count'] > 0): ?>
					<?php echo $this->Html->link('Imported Links: '.number_format($project['ProjectOption']['links.count']), 
						array('controller' => 'surveys', 'action' => 'ajax_survey_links', $project['Project']['id']), 
						array(
							'class' => 'underline',										
							'data-target' => '#modal-survey-links',
							'data-toggle' => 'modal', 
						)
					); ?> 
					<?php if (isset($sqs_number)): ?>
						 <span class="muted">Available for panelists: <?php echo number_format($sqs_number); ?></span>
					<?php else: ?>
						 <span class="muted">Available for panelists: <?php echo number_format($project['ProjectOption']['links.unused']) ?></span>
					<?php endif; ?>
				<?php else: ?>
					<?php echo $project['Project']['client_survey_link']; ?>
				<?php endif; ?></h5>
		</div>
		
		<ul class="inline pull-right sparkline-box">

          <li class="sparkline-row">
            <h4><span>Project Status</span> <?php 
				echo $this->Html->link(
					empty($project['Project']['status']) ? PROJECT_STATUS_OPEN : $_STATUSES[$project['Project']['status']], 
					array('action' => 'ajax_status', $project['Project']['id']),
					array(
						'class' => 'underline',
						'data-toggle' => 'modal',
						'data-target' => '#modal-project',
						'id' => 'status-link-'.$project['Project']['id']
					)
				); 
				?></h4>
          </li>

			<li class="sparkline-row">
				<h4 class="blue"><span>Clicks</span> <?php echo number_format($project['SurveyVisitCache']['click']); ?></h4>
			</li>

			<li class="sparkline-row">
				<h4 class="green"><span>Completes</span> <?php echo number_format($project['SurveyVisitCache']['complete']); ?></h4>
			</li>
			
			<?php if (isset($socialglimpz_rejects)) :?>
				<li class="sparkline-row">
					<h4 class="red"><span>Rejects</span> <?php echo number_format($socialglimpz_rejects); ?></h4>
				</li>
			<?php endif;?>
			
			<li class="sparkline-row">
				<h4 class="red"><span>NQs</span> <?php echo number_format($project['SurveyVisitCache']['nq']); ?></h4>
			</li>

			<li class="sparkline-row">
				<h4 class="orange"><span>OQs</span> <?php echo number_format($project['SurveyVisitCache']['overquota']); ?></h4>
			</li>
			
			<?php if ($project['SurveyVisitCache']['oq_internal'] > 0) : ?>
				<li class="sparkline-row">
					<h4 class="orange"><span>OQ-I</span> <?php echo number_format($project['SurveyVisitCache']['oq_internal']); ?></h4>
				</li>
			<?php endif; ?>
			
			<?php if ($project['SurveyVisitCache']['nq_internal'] > 0) : ?>
				<li class="sparkline-row">
					<h4 class="red"><span>NQ-I</span> <?php echo number_format($project['SurveyVisitCache']['nq_internal']); ?></h4>
				</li>
			<?php endif; ?>
			
			<?php if ($project['SurveyVisitCache']['speed'] > 0) : ?>
				<li class="sparkline-row">
					<h4 class="red"><span>NQ-S</span> <?php echo number_format($project['SurveyVisitCache']['speed']); ?></h4>
				</li>
			<?php endif; ?>
			
			<?php if ($project['SurveyVisitCache']['fraud'] > 0) : ?>
				<li class="sparkline-row">
					<h4 class="red"><span>NQ-F</span> <?php echo number_format($project['SurveyVisitCache']['fraud']); ?></h4>
				</li>
			<?php endif; ?>
			
			<?php if ($project['Project']['prescreen']): ?>
				<li class="sparkline-row">
					<h4 class="blue"><span>P-Click</span> <?php echo number_format($project['SurveyVisitCache']['prescreen_clicks']); ?></h4>
				</li>
				<li class="sparkline-row">
					<h4 class="green"><span>P-Complete</span> <?php echo number_format($project['SurveyVisitCache']['prescreen_completes']); ?></h4>
				</li>
				<li class="sparkline-row">
					<h4 class="orange"><span>P-NQ</span> <?php echo number_format($project['SurveyVisitCache']['prescreen_nqs']); ?></h4>
				</li>
			<?php endif; ?>
		</ul>
	</div>
	
	<div class="padded">
		<div class="row-fluid">
			<div class="span9">
				<?php if (isset($duplicate_fulcrum_found) && $duplicate_fulcrum_found): ?>
					<div class="alert alert-warning">
						A duplicate Lucid project has been found: <?php 
							echo $this->Html->link('#'.$duplicate_fulcrum_found['FedSurvey']['survey_id'], array(
								'controller' => 'surveys',
								'action' => 'dashboard',
								$duplicate_fulcrum_found['FedSurvey']['survey_id']
							)); 
						?>
					</div>
				<?php endif; ?>
				<?php if ($project['Project']['router'] && $project['Project']['dedupe']) : ?>
					<div class="alert alert-warning">
						You've set a router project but have left the deduper on. It is recommended you turn off deduping for router projects, or users will not be able to access the survey multiple times.
					</div>
				<?php endif; ?>
				<p class="pull-right">
					<?php echo $this->Html->link(
						'Soft Launch', 
						'#', 
						array(
							'class' => 'btn '.($project['Project']['soft_launch'] == 1 ? 'btn-success': 'btn-default'),
							'escape' => false,
							'onclick' => 'return MintVine.ToggleSoftLaunch(this, '.$project['Project']['id'].')'
						)
					); ?>
					<?php echo $this->Html->link(
						'Reset', 
						array('action' => 'reset', $project['Project']['id']), 
						array('class' => 'btn btn-default')
					); ?> 
					<?php echo $this->Html->link(
						'Clone', 
						array('action' => 'clone_project', $project['Project']['id']), 
						array('class' => 'btn btn-default')
					); ?> 
					<?php echo $this->Html->link(
						'<i class="icon-refresh"></i> Statistics', 
						array('action' => 'refresh_statistics', $project['Project']['id']), 
						array('class' => 'btn btn-default', 'escape' => false)
					); ?> 
					<?php echo $this->Html->link(
						'<span class="icon-'.($project['Project']['test_mode'] == 1 ? 'pause': 'play').'"></span> Test Mode', 
						'#', 
						array(
							'class' => 'btn '.($project['Project']['test_mode'] == 1 ? 'btn-danger': 'btn-default'),
							'escape' => false,
							'onclick' => 'return MintVine.ToggleTestMode(this, '.$project['Project']['id'].')'
						)
					); ?>
				</p>
				<p>
					<?php 
					$disable_launch = '';
					if ($project['Project']['status'] == PROJECT_STATUS_CLOSED || $project['Project']['status'] == PROJECT_STATUS_INVOICED) {
						$disable_launch = ' disabled';
					}
					echo $this->Html->link(
						'<span class="'.Utils::survey_status($project, 'icon').'"></span> Project', 
						'#', 
						array(
							'class' => 'btn '.Utils::survey_status($project, 'button').$disable_launch,
							'escape' => false,
							'onclick' => ($project['Project']['status'] == PROJECT_STATUS_CLOSED || $project['Project']['status'] == PROJECT_STATUS_INVOICED) ? '' : 'return MintVine.PauseProject(this, '.$project['Project']['id'].')'
						)
					); ?> 
					<?php echo $this->Html->link(
						'<span class="icon-'.($project['Project']['dedupe'] != 1 ? 'pause': 'play').'"></span> Deduper', 
						'#', 
						array(
							'class' => 'btn '.($project['Project']['dedupe'] != 1 ? 'btn-danger': 'btn-success'),
							'escape' => false,
							'onclick' => 'return MintVine.ToggleDeduper(this, '.$project['Project']['id'].')'
						)
					); ?>
					<?php if ($project['Project']['router']): ?>
						<?php echo $this->Html->link(
							'<span class="icon-'.(empty($project['ProjectOption']['pushed']) ? 'pause': 'play').'"></span> Pushed', 
							'#', 
							array(
								'class' => 'btn '.(empty($project['ProjectOption']['pushed']) ? 'btn-danger': 'btn-success'),
								'escape' => false,
								'data-target' => '#model_pushed_status',
								'data-backdrop' => 'static',
								'data-toggle' => 'modal', 
							)
						); ?>
					<?php endif; ?>
					<?php echo $this->Html->link(
						'Edit', 
						array('action' => 'edit', $project['Project']['id']), 
						array('class' => 'btn btn-default')
					); ?> 
					
					<?php if ($project['Project']['router']): ?>
						<?php echo $this->Html->link(
							'Postback Report', 
							array('controller' => 'reports', 'action' => 'router', $project['Project']['id']), 
							array('class' => 'btn btn-default')
						); ?>
					<?php endif; ?>
					
					<?php if ($project['Project']['prescreen']): ?>
						<?php echo $this->Html->link(
							'Prescreeners', 
							array('action' => 'prescreeners', $project['Project']['id']), 
							array('class' => 'btn btn-default')
						); ?>
					<?php endif; ?>
					
					<?php if ($project['Project']['prescreen']): ?>
						<?php echo $this->Html->link(
							'Prescreeners (Viper)', array(
								'controller' => 'surveys',
								'action' => 'ss_redirect', $project['Project']['id'] . '-' . base64_encode($project['Project']['prj_name'])
							),						
							array(
								'class' => 'btn btn-default',
								'target' => '_blank'
							)
						); ?>
					<?php endif; ?>
					
					<?php if ($project['Project']['status'] != PROJECT_STATUS_OPEN): ?>
						<?php echo $this->Html->link(
							'Set Hashes from Client',
							array('action' => 'complete', $project['Project']['id']),
							array('class' => 'btn btn-default')
						); ?> 
						<?php echo $this->Html->link(
							'Generate Client Complete Report',
							array('action' => 'complete_analysis', $project['Project']['id']),
							array('class' => 'btn btn-default')
						); ?>
					<?php endif; ?>
				</p>
				<div class="box">
					<table cellpadding="0" cellspacing="0" class="table table-normal">
						<thead>
							<tr>
								<td>Client</td>
								<?php if($project['Group']['key'] == 'fulcrum'): ?>
									<td>Study Type</td>
								<?php endif; ?>
								<?php if($project['Group']['calculate_margin']): ?>	
									<td>Margin</td>
									<td>Profit</td>
								<?php endif; ?>
								<td>IR</td>
								<td>Drops</td>
								<td>LOI</td>
								<td>Client Rate (CR)</td>
								<td>User Payout</td>
								<td>EPC</td>
								<td>Quota</td>
								<td>Created</td>
								<?php if ($project['Project']['status'] == PROJECT_STATUS_OPEN && $project['Project']['active']): ?>
									<td>Launched</td>
								<?php endif; ?>
								<?php if ($project['Project']['status'] == PROJECT_STATUS_CLOSED || $project['Project']['status'] == PROJECT_STATUS_INVOICED): ?>
									<td>Closed</td>
								<?php endif; ?>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td><?php echo $project['Client']['client_name'];?><br/>
								<span class="muted"><?php echo $project['Client']['project_name'];?></span></td>
								<?php if ($project['Group']['key'] == 'fulcrum'): ?>
									<td>
										<?php echo !empty($project['FedSurvey']['survey_type']) ? $project['FedSurvey']['survey_type'] : '<span class="muted">-</span>'; ?>
									</td>
								<?php endif; ?>
								<?php if($project['Group']['calculate_margin']): ?>	
									<td>
										<?php if (!is_null($project['Project']['margin_pct'])): ?>
											<?php echo $project['Project']['margin_pct']; ?>%
										<?php else: ?>
											<span class="muted">-</span>
										<?php endif; ?>
									</td>
									<td>
										<?php if (!is_null($project['Project']['margin_cents'])): ?>
											$<?php echo number_format(round($project['Project']['margin_cents'] / 100, 2), 2); ?>
										<?php else: ?>
											<span class="muted">-</span>
										<?php endif; ?>
									</td>
								<?php endif; ?>
								<td>
									<?php if (!empty($project['ProjectIr'])): ?>
										<?php echo $this->Html->link($this->App->ir($project), '#', array(
											'escape' => false,
											'data-target' => '#modal-project-irs',
											'data-toggle' => 'modal'
										)); ?>
									<?php else: ?>
										<?php echo $this->App->ir($project); ?>
									<?php endif; ?>
								</td>
								<td><?php echo $this->App->drops($project); ?></td>
								<td><?php echo $project['Project']['est_length']; ?>  / 
									<?php if (!empty($project['SurveyVisitCache']['loi_seconds'])) : ?>
										<?php echo round($project['SurveyVisitCache']['loi_seconds'] / 60); ?>
									<?php else: ?>
										<span class="muted">-</span>
									<?php endif; ?></td>
								<td>
									<?php if (count($project['HistoricalRates']) > 1): ?>
										<?php echo $this->Html->link($this->App->dollarize($project['Project']['client_rate'], 2), '#', array(
											'escape' => false,
											'data-target' => '#modal-rates',
											'data-toggle' => 'modal',
										)); ?>
									<?php else: ?>
										<?php echo $this->App->dollarize($project['Project']['client_rate'], 2);?>
									<?php endif; ?>
								</td>
								<td><?php echo $this->App->dollarize($project['Project']['user_payout'], 2);?></td>
								<td><?php echo $this->App->epc($project); ?></td>
								<td>
									<?php echo $this->App->quota_number($project); ?>
									<?php $quota_types = unserialize(QUOTA_TYPES); ?>
									(<?php echo $quota_types[$project['Project']['quota_type']]; ?>)
								</td>
								<td>
									<?php echo $this->Time->format($project['Project']['date_created'], Utils::dateFormatToStrftime('M j'), false, $timezone); ?>
								</td>
								<?php if ($project['Project']['status'] == PROJECT_STATUS_OPEN && $project['Project']['active']): ?>
								<td><?php echo $this->Time->format($project['Project']['started'], Utils::dateFormatToStrftime('M j<br />h:i A'), false, $timezone); ?></td>
								<?php endif; ?>
								<?php if ($project['Project']['status'] == PROJECT_STATUS_CLOSED || $project['Project']['status'] == PROJECT_STATUS_INVOICED): ?>
									<td><?php echo $this->Time->format($project['Project']['ended'], Utils::dateFormatToStrftime('M j, Y<br />h:i A'), false, $timezone); ?></td>
								<?php endif; ?>
							</tr>
						</tbody>
					</table>
				</div>
				<div class="box">
					<table cellpadding="0" cellspacing="0" class="table table-normal">
						<thead>
							<tr>
								<td>Invited</td>
								<td>Emailed</td>
								<td>Low IR</td>
								<td>High IR</td>
								<td>Low EPC</td>
								<td>High EPC</td>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td>
									<?php if ($project['SurveyVisitCache']['invited'] > 0): ?>
										<?php echo number_format($project['SurveyVisitCache']['invited']); ?>
									<?php else: ?>
										<span class="muted">-</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ($project['SurveyVisitCache']['emailed'] > 0): ?>
										<?php echo number_format($project['SurveyVisitCache']['emailed']); ?>
									<?php else: ?>
										<span class="muted">-</span>
									<?php endif; ?>
								</td>
								<td><?php echo !empty($project['SurveyVisitCache']['low_ir']) ? $project['SurveyVisitCache']['low_ir'].'%' : '<span class="muted">-</span>'; ?></td>
								<td><?php echo !empty($project['SurveyVisitCache']['high_ir']) ? $project['SurveyVisitCache']['high_ir'].'%' : '<span class="muted">-</span>'; ?></td>
								<td><?php echo !empty($project['SurveyVisitCache']['low_epc']) ? '$'.Utils::dollarize_points($project['SurveyVisitCache']['low_epc']) : '<span class="muted">-</span>'; ?></td>
								<td><?php echo !empty($project['SurveyVisitCache']['high_epc']) ? '$'.Utils::dollarize_points($project['SurveyVisitCache']['high_epc']) : '<span class="muted">-</span>'; ?></td>
							</tr>
						</tbody>
					</table>
				</div>
				<?php if (isset($lucid_survey_statistics) && $lucid_survey_statistics): ?>
				<h3>Global Live Trailing Statistics from Fulcrum (12 hours)</h3>
				<div class="box" style="width: 400px">
					<table cellpadding="0" cellspacing="0" class="table table-normal">
						<thead>
							<tr>
								<td>Effective EPC</td>
								<td>LOI</td>
								<td>System Conversion</td>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td>
									<?php if (!empty($lucid_epc_statistics)): ?>
										<?php echo $this->Html->link($this->App->dollarize($lucid_survey_statistics['SurveyStatistics']['EffectiveEPC'], 2), '#', array(
											'escape' => false,
											'data-target' => '#modal-lucid-epc-statistics',
											'data-toggle' => 'modal'
										)); ?>
										<?php echo $this->Element('modal_lucid_epc_statistics'); ?>
									<?php else: ?>
										<?php echo $this->App->dollarize($lucid_survey_statistics['SurveyStatistics']['EffectiveEPC'], 2); ?>
									<?php endif; ?>
								</td>
								<td><?php echo $lucid_survey_statistics['SurveyStatistics']['LengthOfInterview']; ?></td>
								<td><?php echo $this->App->dollarize($lucid_survey_statistics['SurveyStatistics']['SystemConversion'], 2); ?></td>
							</tr>
						</tbody>
					</table>
				</div>
				<?php endif; ?>
				<?php if (isset($client_reports) && $client_reports): ?>
					<h3>Client Complete Report</h3>
					<div class="box">
						<table cellpadding="0" cellspacing="0" class="table table-normal">
							<thead>
								<tr>
									<td>Partner</td>
									<td>Partner Completes Tracked</td>
									<td>Client-Reported Completes</td>
									<td>Completion Rate</td>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($client_reports as $client_report): ?>
									<tr>
										<td><?php echo $client_report['Partner']['partner_name']; ?></td>
										<td><?php echo number_format($client_report['ClientReport']['reported']); ?></td>
										<td><?php echo number_format($client_report['ClientReport']['confirmed']); ?></td>
										<td><?php
											echo round($client_report['ClientReport']['confirmed'] / $client_report['ClientReport']['reported'] * 100, 2);
										?>% </td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
				
				<?php if (!empty($project['SpectrumProject']['id'])): ?>
					<?php echo $this->Html->link('See Spectrum API Json', array(
							'action' => 'ajax_spectrum_api_json',
							$project['SpectrumProject']['spectrum_survey_id']
						), array(
							'class' => 'btn btn-default',
							'data-target' => '#modal-spectrum-api-json',
							'data-toggle' => 'modal', 
					)); ?> 
				<?php endif; ?>
		
				<h3>Partners</h3>
				<p><?php echo $this->Html->link('Add Partner', '#', array(
					'class' => 'btn btn-default btn-sm',
					'data-target' => '#modal-add-partner',
					'data-backdrop' => 'static',
					'data-toggle' => 'modal', 
				)); ?></p>
	
				<?php if (!empty($project['SurveyPartner'])): ?>
					<div class="box">				
						<table class="table table-normal">
							<thead>
								<tr>
									<td>Partner</td>
									<td>Rate</td>
									<td>LOI</td>
									<td>Statistics</td>
									<td style="width: 380px;">Link</td>
									<td class="actions"></td>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($project['SurveyPartner'] as $partner): ?>
									<?php $url = HOSTNAME_REDIRECT.'/go/'.$project['Project']['id'].'-'.$project['Project']['code'].'?pid='.$partner['partner_id'].'&uid='; ?>
																		
									<tr>
										<td><?php echo $partner['Partner']['partner_name']; ?></td>
										<td><?php echo $this->App->dollarize($partner['rate'], 2); ?></td>
										<td><?php if (!empty($project['SurveyPartner']['loi_seconds'])): ?>
											<?php echo round($project['SurveyPartner']['loi_seconds'] / 60); ?>
										<?php else: ?>
											<span class="muted">-</span>
										<?php endif; ?></td>
										<td><?php 
											echo $partner['clicks'].' / '.$partner['completes'].' / '.$partner['nqs'].' / '.$partner['oqs'].' / '.$partner['speeds'].' / '.$partner['fails']; 
										?></td>
										<td><input type="text" style="width: 80%; margin: 0;" onclick="$(this).select();" value="<?php echo $url; ?>" /></td>
										<td class="actions nowrap"> <?php
											echo $this->Html->link('Test', $url.'MVTESTID', array(
												'target' => '_blank',
												'class' => 'btn btn-sm btn-default'
											)); 
										?> 
										<?php if (!$partner['paused']) : ?>
											<?php echo $this->Html->link('<span class="icon-play"></span>', '#', array(
												'class' => 'btn btn-sm btn-success',
												'escape' => false,
												'onclick' => 'return MintVine.PausePartner(this, '.$partner['id'].');'
											)); ?>
										<?php else: ?>
											<?php echo $this->Html->link('<span class="icon-pause"></span>', '#', array(
												'class' => 'btn btn-sm btn-danger',
												'escape' => false,
												'onclick' => 'return MintVine.PausePartner(this, '.$partner['id'].');'
											)); ?>
										<?php endif; ?>
											
										<?php if ($partner['security']): ?>
											<?php echo $this->Html->link('<span class="icon-play"></span> Security', '#', array(
												'class' => 'btn btn-sm btn-success',
												'escape' => false,
												'onclick' => 'return MintVine.ToggleSecurity(this, '.$partner['id'].');'
											)); ?>
										<?php else: ?>
											<?php echo $this->Html->link('<span class="icon-pause"></span> Security', '#', array(
												'class' => 'btn btn-sm btn-danger',
												'escape' => false,
												'onclick' => 'return MintVine.ToggleSecurity(this, '.$partner['id'].');'
											)); ?>
										<?php endif; ?>
										<?php echo $this->Html->link('Edit', array('action' => 'partner_edit', $project['Project']['id'], $partner['partner_id']), array('class' => 'btn btn-sm btn-default')); ?> 
										<?php
											echo $this->Html->link('<span class="icon-trash"></span>', '#', array(
												'escape' => false,
												'class' => 'btn btn-sm btn-default',
												'onclick' => 'return MintVine.DeleteSurveyPartner(this, '.$project['Project']['id'].', '.$partner['partner_id'].');'
											)); 
										?> 
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
			<div class="span3">
				<?php if (!in_array($project['Project']['status'], array(PROJECT_STATUS_OPEN, PROJECT_STATUS_STAGING))): ?>
					<div class="box">
							<div class="box-header">
								<span class="title">Invoices</span>
							</div>
							<div class="padded">
								<?php if (!empty($project['Invoice']['id'])): ?>
										<?php echo $this->Html->link('View', array('controller' => 'invoices', 'action' => 'view', $project['Invoice']['uuid']), array('class' => 'btn btn-default')); ?>
										<?php echo $this->Html->link('Download', array('controller' => 'invoices', 'action' => 'download', $project['Invoice']['project_id']), array('class' => 'btn btn-default')); ?>
										<?php echo $this->Html->link('Edit', array('controller' => 'invoices', 'action' => 'edit', $project['Invoice']['id']), array('class' => 'btn btn-default')); ?>
										<?php echo $this->Html->link('Send', array('controller' => 'invoices', 'action' => 'send', $project['Invoice']['id']), array('class' => 'btn btn-default')); ?>
								<?php else: ?>
									<?php if (isset($client_reports) && !empty($client_reports)): ?>
										<?php echo $this->Html->link('Generate Invoice', array('controller' => 'invoices', 'action' => 'generate', $project['Project']['id']), array('class' => 'btn btn-default')); ?>
									<?php else: ?>
										<?php echo $this->Html->link('Generate Blank Invoice', array('controller' => 'invoices', 'action' => 'generate', $project['Project']['id']), array('class' => 'btn btn-default')); ?>
									<?php endif; ?>
								<?php endif; ?>
							</div>
					</div>
				<?php endif; ?>
				<div class="box">
					<div class="box-header">
						<span class="title">Project Links</span>
						<ul class="box-toolbar">
							<li><?php echo $this->Html->link('Copy to clipboard', '#', array(
								'class' => 'btn btn-success btn-mini',
								'id' => 'copy_addr'
							)); ?></li>
						</ul>
					</div>
					<div class="padded">
						<?php if ($project['Project']['landerable']): ?>
							<?php echo $this->Form->input('lander', array(
								'label' => 'FB Lander Page:',
								'value' => HOSTNAME_WWW.'/landers/fb/'.$project['Project']['id'],
								'onclick' => '$(this).select()'
							)); ?> 
						<?php endif; ?>
						<?php echo $this->Form->input('success', array(
							'label' => 'Success page:',
							'value' => HOSTNAME_REDIRECT.'/success/?uid=',
							'onclick' => '$(this).select()'
						)); ?> 
						<?php echo $this->Form->input('dq', array(
							'label' => 'Disqualification page:',
							'value' => HOSTNAME_REDIRECT.'/nq/?uid=',
							'onclick' => '$(this).select()'
							
						)); ?> 
						<?php echo $this->Form->input('oq', array(
							'label' => 'Quota full page:',
							'value' => HOSTNAME_REDIRECT.'/quota/?uid=',
							'onclick' => '$(this).select()'
						)); ?> 
						
						<textarea id="url_to_copy" style="display: none;">
Success page:
<?php echo HOSTNAME_REDIRECT;?>/success/?uid=
Disqualification page:
<?php echo HOSTNAME_REDIRECT;?>/nq/?uid=
Overquota page:
<?php echo HOSTNAME_REDIRECT;?>/quota/?uid=
						</textarea>
						
					</div>
				</div>
			</div>
		</div>
				
		<?php if ($project['Project']['temp_qualifications'] || $project['Group']['key'] == 'points2shop' || ($project['Group']['key'] == 'mintvine' && empty($queries))): ?>
			<div class="row-fluid">
				<h3>Qualifications</h3>

				<?php
					if ($qualifications) {
						echo $this->Form->create(null, array(
							'class' => 'pull-right',
							'style' => 'margin-left: 8px;',
							'url' => array('controller' => 'qualifications', 'action' => 'refresh_users', '?' => array('project_id' => $project['Project']['id']))
						));
						echo $this->Form->submit('Refresh Qualification Users', array('class' => 'btn btn-default btn-sm pull-right'));
						echo $this->Form->end(null);
					}
				?>
					
				<p class="pull-right">
					<?php echo $this->Html->link(
						'Recontact Users', 
						array('action' => 'retarget', $project['Project']['id']), 
						array('class' => 'btn btn-default')
					); ?>
					
					<?php if ($project['Group']['key'] == 'mintvine' && empty($queries)): ?>
						<?php echo $this->Html->link(
							'Invite Panelists', array('action' => 'invite_panelists', $project['Project']['id']), array('class' => 'btn btn-default')
						); ?>
					<?php endif; ?>
				</p>
				
				<p>
					<?php if (in_array($project['Project']['country'], array_keys(unserialize(SUPPORTED_COUNTRIES))) && (in_array($project['Group']['key'], array('socialglimpz', 'mintvine')) && empty($queries))): ?>
						<?php echo $this->Html->link('Create Qualification', 
								array('controller' => 'qualifications', 'action' => 'query', strtolower($project['Project']['country']), '?' => array('project_id' => $project['Project']['id'])),
								array('class' => 'btn btn-default btn-sm')
							); 
						?>
					<?php endif; ?>
					<?php if (!empty($project['FedSurvey']['id'])): ?>
						<?php 
							echo $this->Html->link(
								'See Lucid Qualifications', 
								array(
									'action' => 'ajax_fed_qualifications',
									$project['FedSurvey']['fed_survey_id']
								), 
								array(
									'class' => 'btn btn-default',
									'data-target' => '#modal-fed-qualifications',
									'data-toggle' => 'modal',
								)
							); 
						?> 
					<?php endif; ?>
				
					<?php if (!empty($project['CintSurvey']['id'])): ?>
						<?php 
							echo $this->Html->link(
								'See Cint Qualifications', 
								array(
									'action' => 'ajax_cint_qualifications',
									$project['CintSurvey']['cint_survey_id'],
									$project['Project']['country']
								), 
								array(
									'class' => 'btn btn-default',
									'data-target' => '#modal-cint-qualifications',
									'data-toggle' => 'modal', 
								)
							); 
						?> 
					<?php endif; ?>
				
					<?php if ($project['Group']['key'] == 'points2shop'): ?>
							<?php echo $this->Html->link('Points2Shop API Log', array(
									'controller' => 'points2shop_logs',
									'action' => 'search',
									'?' => array('project_id' =>  $project['Project']['mask']),
								),
								array(
									'class' => 'btn btn-default btn-p2s-log',
									'target' => '_blank'
								)
							); ?>
					<?php endif; ?>
				
					<?php if (!empty($project['CintSurvey']['id']) && isset($project['ProjectOption']['cint_required_capabilities'])): ?>
						<div class="alert alert-warning">
							This Cint project has the following requirements: <strong><?php 
								echo $project['ProjectOption']['cint_required_capabilities'];
							?></strong>
						</div>
					<?php endif; ?>
				</p>				
			</div>
			<div class="clearfix"></div>
			
			<?php if (!in_array($project['Project']['country'], array_keys(unserialize(SUPPORTED_COUNTRIES)))): ?>
				<p class="muted">Only the following countries can be queried: <?php 
					echo implode(', ', array_keys(unserialize(SUPPORTED_COUNTRIES))); 
				?>.</p>
			<?php else: ?>
				<?php if ($qualifications) : ?>
					<div class="box">
						<table class="table table-normal query_history" id="qualifications_table">
							<colgroup>
								<col/>
								<col/>
								<col/>
								<col/>
								<col/>
								<col/>
								<col/>
								<col/>
								<col/>
								<col/>
								<col/>
								<col/>
								<col width="20"/>
								<col width="175"/>
							</colgroup>
							<thead>
								<tr>
									<td>Name</td>
									<td>Modified</td>
									<td>Quota</td>
									<td>CPI</td>
									<td>Award</td>
									<td>Total</td>
									<td>Invited</td>
									<td>Emailed</td>
									<td>C</td>
									<td>CL</td>
									<td>NQ</td>
									<td>OQ</td>
									<td class="actions"></td>
									<td class="create_sub_quota"></td>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($qualifications as $qualification): ?>
									<?php
										$processing_flag = is_null($qualification['Qualification']['processing']) ? false : true;
										$id = $qualification['Qualification']['id'];
									?>
									<tr id="qualification_<?php echo $id; ?>" class="parent">
										<td class="name">
											<?php if (!empty($project['SpectrumProject']['spectrum_survey_id'])): ?>
												<?php $qualification_name = (!empty($qualification['Qualification']['partner_qualification_id'])) ? $qualification['Qualification']['partner_qualification_id'] : $qualification['Qualification']['name'];
												echo $this->Html->link($qualification_name , array(
													'controller' => 'projects',
													'action' => 'ajax_qualification_information_spectrum',
													$qualification['Qualification']['id'],
													$project['SpectrumProject']['spectrum_survey_id'],
												),
												array(
													'data-target' => '#modal-qualifications',
													'data-toggle' => 'modal', 
												)); ?>
											<?php elseif (!empty($project['FedSurvey']['fed_survey_id'])): ?>
												<?php echo $this->Html->link($qualification['Qualification']['partner_qualification_id'], array(
													'controller' => 'projects',
													'action' => 'ajax_qualification_information',
													$qualification['Qualification']['id'],
													(isset($project['FedSurvey']['id'])) ? $project['FedSurvey']['fed_survey_id'] : '',
												),
												array(
													'data-target' => '#modal-qualifications',
													'data-toggle' => 'modal', 
												)); ?>
											<?php elseif ($project['Group']['key'] == 'points2shop'): ?>
												<?php echo $this->Html->link($qualification['Qualification']['name'], array(
													'controller' => 'projects',
													'action' => 'ajax_qualification_information_points2shop',
													$qualification['Qualification']['id'],
													$project['Project']['id']
												),
												array(
													'data-target' => '#modal-view-qualification',
													'data-toggle' => 'modal'
												)); ?>
											<?php else: ?>
												<?php echo $this->Html->link($qualification['Qualification']['name'], array(
													'controller' => 'surveys',
													'action' => 'ajax_view_qualification',
													$qualification['Qualification']['id']
												),
												array(
													'data-target' => '#modal-view-qualification',
													'data-toggle' => 'modal'
												)); ?>
											<?php endif; ?>
										</td>
										<td style="white-space: nowrap;"><?php echo $this->Time->format($qualification['Qualification']['modified'], Utils::dateFormatToStrftime('M j h:i A'), false, $timezone); ?></td>
										<td class="quota">
											<?php echo $this->Html->link($qualification['Qualification']['quota'], array(
												'controller' => 'surveys',
												'action' => 'ajax_edit_quotas',
												$qualification['Qualification']['id']
											),
											array(
												'data-target' => '#modal-edit-quotas',
												'data-toggle' => 'modal',
											)); ?>
										</td>
										<td class="cpi">
											<?php echo $this->Html->link($this->App->dollarize($qualification['Qualification']['cpi'], 2), array(
												'controller' => 'surveys',
												'action' => 'ajax_edit_quotas',
												$qualification['Qualification']['id']
											),
											array(
												'data-target' => '#modal-edit-quotas',
												'data-toggle' => 'modal',
											)); ?>
										</td>
										<td class="award">
											<?php echo $this->Html->link($qualification['Qualification']['award'], array(
												'controller' => 'surveys',
												'action' => 'ajax_edit_quotas',
												$qualification['Qualification']['id']
											),
											array(
												'data-target' => '#modal-edit-quotas',
												'data-toggle' => 'modal',
											)); ?>
										</td>
										<td>
											<?php if (!empty($qualification['Qualification']['total'])): ?>
												<?php echo number_format($qualification['Qualification']['total']); ?>
											<?php else: ?>
												<?php echo number_format($qualification['QualificationStatistic']['invited']); ?>
											<?php endif; ?>
										</td>
										<td class="detail_group_<?php echo $id; ?>">
											<?php if ($qualification['QualificationStatistic']['invited'] > 0): ?>
												<?php echo $this->Html->link(number_format($qualification['QualificationStatistic']['invited']), array(
													'controller' => 'reports',
													'action' => 'qualification_users',
													$qualification['Qualification']['id'],
												)); ?>
											<?php else: ?>
												<?php echo number_format($qualification['QualificationStatistic']['invited']); ?>
											<?php endif; ?>
										</td>
										<td colspan="7" style="display: none;" id="invited_processing_<?php echo $id;?>">
											<span class="muted">We are processing this request...</span>
										</td>
										<td class="detail_group_<?php echo $id; ?>">
											<?php if ($qualification['QualificationStatistic']['notified_email'] > 0): ?>
												<?php echo $this->Html->link(number_format($qualification['QualificationStatistic']['notified_email']), array(
													'controller' => 'reports',
													'action' => 'qualification_users',
													$qualification['Qualification']['id'],
													'?' => array(
														'emailed' => true
													)
												)); ?>
											<?php else: ?>
												<?php echo number_format($qualification['QualificationStatistic']['notified_email']); ?>
											<?php endif; ?>
										</td>
										<td class="detail_group_<?php echo $id; ?>"><?php echo number_format($qualification['QualificationStatistic']['completes']); ?></td>
										<td class="detail_group_<?php echo $id; ?>"><?php echo number_format($qualification['QualificationStatistic']['clicks']); ?></td>
										<td class="detail_group_<?php echo $id; ?>"><?php echo number_format($qualification['QualificationStatistic']['nqs']); ?></td>
										<td class="detail_group_<?php echo $id; ?>"><?php echo number_format($qualification['QualificationStatistic']['oqs']); ?></td>
										<td class="action detail_group_<?php echo $id; ?>">
											<?php echo $this->Html->link(
												'<span class="icon-'.($qualification['Qualification']['active'] ? 'play': 'pause').'"></span>',
												'#',
												array(
													'class' => 'btn '.($qualification['Qualification']['active'] ? 'btn-success': 'btn-danger').' btn-small',
													'escape' => false,
													'onclick' => 'return MintVine.ToggleQualificationActive(this, '.$qualification['Qualification']['id'].')'
												)
											); ?>
										</td>
										<td>
											<?php
												echo $this->Html->link('<i class="icon-screenshot"></i>', array(
													'controller' => 'qualifications',
													'action' => 'child_qualification',
													$qualification['Qualification']['id']
												), array(
													'escape' => false,
													'title' => 'Target Quotas',
													'class' => 'btn btn-small btn-default'
												));
											?> 
											<?php	
												echo $this->Html->link('<i class="icon-user"></i>', array(
													'controller' => 'reports', 
													'action' => 'survey_coverage', 
													$qualification['Qualification']['id']
												), array(
													'escape' => false,
													'title' => 'View Invited Panelists',
													'target' => '_blank',
													'class' => 'btn btn-small btn-default'
												)); 
											?>
											<?php
												echo $this->Html->link('<i class="icon-edit"></i>', array(
													'controller' => 'surveys',
													'action' => 'edit_user_qualifications',
													$qualification['Qualification']['id']
												), array(
													'escape' => false,
													'title' => 'Edit Qualification Filters',
													'data-target' => '#modal-edit-userids',
													'data-toggle' => 'modal',
													'class' => 'btn btn-small btn-default'
												));
											?>
											<?php
												echo $this->Html->link('<i class="icon-refresh"></i>', array(
													'controller' => 'surveys',
													'action' => 'refresh_qualification',
													$qualification['Qualification']['id']
												), array(
													'title' => 'Refresh Qualification',
													'escape' => false,
													'class' => 'btn btn-small btn-default'
												));
											?>
										</td>
									</tr>
									<?php if (isset($child_qualifications[$qualification['Qualification']['id']])) : ?>
										<?php foreach ($child_qualifications[$qualification['Qualification']['id']] as $child_qualification): ?>
											<tr id="qualification_<?php echo $child_qualification['Qualification']['id']; ?>" class="<?php echo $qualification['Qualification']['id'] . '_children'; ?> child">
												<td class="name">
													<?php if (!empty($project['SpectrumProject']['spectrum_survey_id'])): ?>
														<?php echo $this->Html->link($child_qualification['Qualification']['partner_qualification_id'] , array(
															'controller' => 'projects',
															'action' => 'ajax_qualification_information_spectrum',
															$child_qualification['Qualification']['id'],
															$project['SpectrumProject']['spectrum_survey_id'],
														),
														array(
															'data-target' => '#modal-qualifications',
															'data-toggle' => 'modal', 
														)); ?>
													<?php elseif (!empty($project['FedSurvey']['fed_survey_id'])): ?>
														<?php echo $this->Html->link($child_qualification['Qualification']['partner_qualification_id'], array(
															'controller' => 'projects',
															'action' => 'ajax_qualification_information',
															$child_qualification['Qualification']['id'],
															(isset($project['FedSurvey']['id'])) ? $project['FedSurvey']['fed_survey_id'] : '',
														),
														array(
															'data-target' => '#modal-qualifications',
															'data-toggle' => 'modal', 
														)); ?>
													<?php elseif ($project['Group']['key'] == 'points2shop'): ?>
														<span><?php echo $qualification['Qualification']['name']; ?></span> &#187;
														<?php echo $this->Html->link($child_qualification['Qualification']['name'], array(
															'controller' => 'projects',
															'action' => 'ajax_qualification_information_points2shop',
															$child_qualification['Qualification']['id'],
															$project['Project']['id']
														),
														array(
															'data-target' => '#modal-view-qualification',
															'data-toggle' => 'modal'
														)); ?>	
													<?php else: ?>
														<span><?php echo $qualification['Qualification']['name']; ?></span> &#187;
														<?php echo $this->Html->link($child_qualification['Qualification']['name'], array(
															'controller' => 'surveys',
															'action' => 'ajax_view_qualification',
															$child_qualification['Qualification']['id']
														),
														array(
															'data-target' => '#modal-view-qualification',
															'data-toggle' => 'modal'
														)); ?>
													<?php endif; ?>
												</td>
												<td style="white-space: nowrap;"><?php echo $this->Time->format($child_qualification['Qualification']['modified'], Utils::dateFormatToStrftime('M j h:i A'), false, $timezone); ?></td>
												<td class="quota">
													<?php echo $this->Html->link($child_qualification['Qualification']['quota'], array(
														'controller' => 'surveys',
														'action' => 'ajax_edit_quotas',
														$child_qualification['Qualification']['id']
													),
													array(
														'data-target' => '#modal-edit-quotas',
														'data-toggle' => 'modal',
													)); ?>
												</td>
												<td class="cpi">
													<?php echo $this->Html->link($this->App->dollarize($child_qualification['Qualification']['cpi'], 2), array(
														'controller' => 'surveys',
														'action' => 'ajax_edit_quotas',
														$child_qualification['Qualification']['id']
													),
													array(
														'data-target' => '#modal-edit-quotas',
														'data-toggle' => 'modal',
													)); ?>
												</td>
												<td class="award">
													<?php echo $this->Html->link($qualification['Qualification']['award'], array(
														'controller' => 'surveys',
														'action' => 'ajax_edit_quotas',
														$qualification['Qualification']['id']
													),
													array(
														'data-target' => '#modal-edit-quotas',
														'data-toggle' => 'modal',
													)); ?>
												</td>
												<td>-</td>
												<td>-</td>
												<td>-</td>
												<td><?php echo number_format($child_qualification['QualificationStatistic']['completes']); ?></td>
												<td><?php echo number_format($child_qualification['QualificationStatistic']['clicks']); ?></td>
												<td><?php echo number_format($child_qualification['QualificationStatistic']['nqs']); ?></td>
												<td><?php echo number_format($child_qualification['QualificationStatistic']['oqs']); ?></td>
												<td class="action">
													<?php echo $this->Html->link(
														'<span class="icon-'.($child_qualification['Qualification']['active'] ? 'play': 'pause').'"></span>',
														'#',
														array(
															'class' => 'btn '.($child_qualification['Qualification']['active'] ? 'btn-success': 'btn-danger').' btn-sm',
															'escape' => false,
															'onclick' => 'return MintVine.ToggleQualificationActive(this, '.$child_qualification['Qualification']['id'].')'
														)
													); ?>
												</td>
												<td>
													<?php echo $this->Html->link('Delete', '#', array('class' => 'btn btn-small btn-danger', 'onclick' => 'return MintVine.DeleteSubQuota('.$child_qualification['Qualification']['id'].', this)')); ?>
												</td>
											</tr>
										<?php endforeach; ?>
									<?php endif; ?>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		<?php endif; ?>

		<?php if (false && $project['Group']['key'] == 'mintvine'): ?>
			<div class="row-fluid">
				<div class="span9">
					<h3>Click Balancing</h3>
					<?php
					echo $this->Html->link('Add Default Templates', array(
						'controller' => 'surveys',
						'action' => 'ajax_click_templates',
						$project['Project']['id']
					), array(
						'escape' => false,
						'title' => 'Add Default Template',
						'data-target' => '#modal-click-templates',
						'data-toggle' => 'modal',
						'class' => 'btn btn-small btn-default'
					));
					?>
					<?php if ($project_click_distributions): ?>
						<div class="box" style="margin-top: 10px;">
							<table class="table table-normal" id="click_distribution_table">
								<thead>
									<tr>
										<td>Qualification</td>
										<td>Value</td>
										<td>Percentage</td>
										<td>Click Quota</td>
										<td>Clicks</td>
										<td>Click Percentage</td>
										<td>Completes</td>
										<td>Completes %</td>
										<td></td>
										<td></td>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($project_click_distributions as $project_click_distribution): ?>
										<tr id="distribution_<?php echo $project_click_distribution['ProjectClickDistribution']['id']; ?>">
											<?php $qualification_name_arr = array(
												'age' => 'Age',
												'gender' => 'Gender',
												'age_gender' => 'Age + Gender',
												'ethnicity' => 'Ethnicity',
												'hhi' => 'HHI',
												'hispanic' => 'Hispanic',
												'geo_region' => 'Region',
												'geo_state' => 'State',
											); ?>
											<td><?php echo $qualification_name_arr[$project_click_distribution['ProjectClickDistribution']['key']] ?></td>
											<td>
												<?php
												$key = $project_click_distribution['ProjectClickDistribution']['key'];
												$question_arr = array('hhi', 'ethnicity', 'hispanic');
												if (in_array($key, $question_arr)) {
													if ($project_click_distribution['ProjectClickDistribution']['other']) {
														echo 'Other';
													}
													else {
														$answers = $questions[$key]['Answers'];
														echo $answers[$project_click_distribution['ProjectClickDistribution']['answer_id']];
													}
												}
												elseif ($key == 'geo_state' || $key == 'geo_region') {
													if ($project_click_distribution['ProjectClickDistribution']['other']) {
														echo 'Other';
													}
													else {
														$geo_key = str_replace('geo_', '', $key);
														echo $geo[$geo_key][$project_click_distribution['ProjectClickDistribution']['answer_id']];
													}
												}
												elseif ($key == 'age_gender') {
													if ($project_click_distribution['ProjectClickDistribution']['other']) {
														echo 'Other';
													}
													else {
														$text = $project_click_distribution['ProjectClickDistribution']['gender'] == 1 ? 'Male' : 'Female';
														$text .= ': ' . $project_click_distribution['ProjectClickDistribution']['age_from'] . ' - ';
														$text .= $project_click_distribution['ProjectClickDistribution']['age_to'];
														echo $text;
													}
												}
												elseif ($key == 'gender') {
													echo $project_click_distribution['ProjectClickDistribution']['gender'] == 1 ? 'Male' : 'Female';
												}
												elseif ($key == 'age') {
													if ($project_click_distribution['ProjectClickDistribution']['other']) {
														echo 'Other';
													}
													else {
														echo $project_click_distribution['ProjectClickDistribution']['age_from'] . ' - ' . $project_click_distribution['ProjectClickDistribution']['age_to'];
													}
												}
												?>
											</td>
											<td><?php echo $project_click_distribution['ProjectClickDistribution']['percentage']; ?></td>
											<td><?php echo $project_click_distribution['ProjectClickDistribution']['click_quota']; ?></td>
											<td><?php echo $project_click_distribution['ProjectClickDistribution']['clicks']; ?></td>
											<td></td>
											<td></td>
											<td></td>
											<td class="action">
												<?php echo $this->Html->link(
													'<span class="icon-'.($project_click_distribution['ProjectClickDistribution']['active'] ? 'play': 'pause').'"></span>',
													'#',
													array(
														'class' => 'btn '.($project_click_distribution['ProjectClickDistribution']['active'] ? 'btn-success': 'btn-danger').' btn-small',
														'escape' => false,
														'onclick' => 'return MintVine.ToggleQualificationActive(this, '.$project_click_distribution['ProjectClickDistribution']['id'].')'
													)
												); ?>
											</td>
											<td>
												<?php if ($project_click_distribution['ProjectClickDistribution']['key'] != 'gender' && !$project_click_distribution['ProjectClickDistribution']['other']): ?>
													<?php echo $this->Html->link('Delete', '#', array('class' => 'btn btn-small btn-danger', 'onclick' => 'return MintVine.DeleteClickDistribution('.$project_click_distribution['ProjectClickDistribution']['id'].', this)')); ?>
												<?php endif; ?>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if (!empty($queries)): ?>
			<h3>Queries</h3>

			<?php if (!empty($project['CintSurvey']['id']) && isset($project['ProjectOption']['cint_required_capabilities'])): ?>
				<div class="alert alert-warning">
					This Cint project has the following requirements: <strong><?php 
						echo $project['ProjectOption']['cint_required_capabilities'];
					?></strong>
				</div>
			<?php endif; ?>
	
			<p class="pull-right"><?php echo $this->Html->link(
				'Exclude Users', 
				array('action' => 'exclude', $project['Project']['id']), 
				array('class' => 'btn btn-default')
			); ?> <?php echo $this->Html->link(
				'Recontact Users', 
				array('action' => 'retarget', $project['Project']['id']), 
				array('class' => 'btn btn-default')
			); ?></p>
			<?php if (!in_array($project['Project']['country'], array_keys(unserialize(SUPPORTED_COUNTRIES)))): ?>

				<p class="muted">Only the following countries can be queried: <?php 
					echo implode(', ', array_keys(unserialize(SUPPORTED_COUNTRIES))); 
				?>.</p>
			<?php else: ?>		
				<p><?php echo $this->Html->link('Create Query',
					array('controller' => 'queries', 'action' => 'add', $project['Project']['id'], '?' => array('type' => 'survey')),
					array('class' => 'btn btn-default btn-sm')
					); ?>
		
				<?php if (!empty($project['FedSurvey']['id'])): ?>
					<?php 
						echo $this->Html->link(
							'See Lucid Qualifications', 
							array(
								'action' => 'ajax_fed_qualifications',
								$project['FedSurvey']['fed_survey_id']
							), 
							array(
								'class' => 'btn btn-default',
								'data-target' => '#modal-fed-qualifications',
								'data-toggle' => 'modal',
							)
						); 
					?> 
				<?php endif; ?>
				<?php if (!empty($project['RfgSurvey']['id'])): ?>
					<?php 
						echo $this->Html->link('See Rfg Qualifications',
							array(
								'action' => 'ajax_rfg_qualifications',
								$project['RfgSurvey']['rfg_survey_id']
							), 
							array(
								'class' => 'btn btn-default',
								'data-target' => '#modal-rfg-quali	fications',
								'data-toggle' => 'modal', 
							)
						); 
					?> 
				<?php endif; ?>
				<?php if (!empty($project['CintSurvey']['id'])): ?>
					<?php 
						echo $this->Html->link(
							'See Cint Qualifications', 
							array(
								'action' => 'ajax_cint_qualifications',
								$project['CintSurvey']['cint_survey_id'],
								$project['Project']['country']
							), 
							array(
								'class' => 'btn btn-default',
								'data-target' => '#modal-cint-qualifications',
								'data-toggle' => 'modal', 
							)
						); 
					?> 
				<?php endif; ?>
				<?php if (!empty($project['SpectrumProject']['id'])): ?>
					<?php 
						echo $this->Html->link(
							'See Spectrum Qualifications', 
							array(
								'action' => 'ajax_spectrum_qualifications',
								$project['SpectrumProject']['spectrum_survey_id']
							), 
							array(
								'class' => 'btn btn-default',
								'data-target' => '#modal-spectrum-qualifications',
								'data-toggle' => 'modal', 
							)
						); 
					?> 
				<?php endif; ?>
				<?php echo $this->Html->link('Send', 
					array('controller' => 'queries', 'action' => 'ajax_send', $project['Project']['id']),
					array(
						'class' => 'btn btn-primary btn-sm',								
						'data-target' => '#modal-query-send',
						'data-toggle' => 'modal'
					)
				); ?> 
				<?php if ($project['Client']['key'] == 'cint') : ?>
					<?php echo $this->Html->link('Quick Send', 
						array('controller' => 'queries', 'action' => 'ajax_quick_send', $project['Project']['id']),
						array(
							'class' => 'btn btn-default btn-sm',								
							'data-target' => '#modal-query-quick-send',
							'data-toggle' => 'modal'
						)
					); ?>
				<?php endif; ?>
				</p>
				
				<div class="box">
					<table class="table table-normal query_history">
						<colgroup>
							<col />
							<col width="80" />
							<col width="80" />
							<col width="80" />
							<col width="120" />
							<col width="40" />
							<col width="40" />
							<col width="40" />
							<col width="40" />
							<col width="40" />
							<col />
						</colgroup>
						<thead>
							<tr>
								<td>Query</td>
								<td>Quota</td>
								<td>Matched #</td>
								<td>Sent #</td>
								<td>Last Sent</td>
								<td>C</td>
								<td>CL</td>
								<td>OQ</td>
								<td>NQ</td>
								<td></td>
								<td></td>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($queries as $key => $query): ?>
								<?php 
									if (empty($query['Query']['parent_id'])) {
										$bg_class = $key % 2 == 0 ? '': 'alt';
									}
								?>
								<tr class="<?php echo $bg_class; ?>">
									<td>
										<?php if (!empty($query['Query']['parent_id'])): ?>
											<?php echo $last_query_name; ?> &gt; 
										<?php else: ?>
											<?php $last_query_name = $query['Query']['query_name']; ?>
										<?php endif; ?>
								
										<?php
											$query_ajax_view = ($query['Query']['engine'] == 'qe2') ? 'ajax_view_qe2' : 'ajax_view';
											echo $this->Html->link(
												$query['Query']['query_name'].' <span class="icon-search"></span>',
												array('controller' => 'queries', 'action' => $query_ajax_view, $query['Query']['id']),
												array('escape' => false, 'data-target' => '#modal-view-query', 'data-toggle' => 'modal')
											); 
										?>
									</td>
									<td>
										<?php if (isset($query['QueryStatistic']['id']) && !empty($query['QueryStatistic']['id'])): ?>
											<?php if (!is_null($query['QueryStatistic']['quota'])): ?>
												<?php $query_quota = $query['QueryStatistic']['quota']; ?>
											<?php else: ?>
												<?php $query_quota = 'None'; ?>
											<?php endif; ?>
								
											<?php echo $this->Html->link($query_quota, array(
												'controller' => 'queries',
												'action' => 'ajax_update_quota',
												$query['QueryStatistic']['id']
											), array(
												'escape' => false,
												'data-target' => '#modal-query-statistics',
												'data-toggle' => 'modal'
											)); ?>
										<?php else: ?>
											<small>-</small>
										<?php endif; ?>
									</td>
									<td><?php
										echo number_format($query['Query']['total']); 
									?></td>
									<td>
										<?php if (is_null($query['Query']['sent'])): ?>
									
										<?php else: ?>
											<?php echo number_format($query['Query']['sent']); ?>
											<?php if (!empty($query['Query']['sent']) && !empty($query['Query']['total'])): ?>
												(<?php
													echo (round($query['Query']['sent'] / $query['Query']['total'], 2)) * 100
												?>%)
											<?php endif; ?>
										<?php endif; ?>
									</td>
									<td>
										<?php if (is_null($query['Query']['last_sent'])): ?>
									
										<?php else: ?>
											<?php echo $this->Time->format($query['Query']['last_sent'], Utils::dateFormatToStrftime('F j h:i A'), false, $timezone); ?>
										<?php endif; ?>
									</td>
									<?php if (!empty($query['QueryStatistic']['id'])): ?>
										<td><?php echo $query['QueryStatistic']['completes'] ?></td>
										<td><?php echo $query['QueryStatistic']['clicks'] ?></td>
										<td><?php echo $query['QueryStatistic']['oqs'] ?></td>
										<td><?php echo $query['QueryStatistic']['nqs'] ?></td>
									<?php else: ?>
										<td></td>
										<td></td>
										<td></td>
										<td></td>
									<?php endif; ?>
									<td>
										<?php if (!is_null($query['Query']['sent'])): ?>
											<?php
												echo $this->Html->link(
													'<span class="'.($query['Query']['active'] ? 'icon-play': 'icon-stop').'"></span>', 
													'#',
													array(
														'data-parent' => $query['Query']['parent_id'],
														'data-active' => $query['Query']['active'] ? '1' : '0',
														'class' => 'btn btn-small '.($query['Query']['active'] ? 'btn-success': 'btn-danger'),
														'escape' => false,
														'onclick' => 'return MintVine.PauseQueryByQuery(this, '.$query['Query']['id'].')'
													)
												)
											?>
										<?php endif; ?>
									</td>
									<td>
										<?php if (!is_null($query['Query']['sent'])): ?>
											<?php
												echo $this->Html->link('Report', array(
													'controller' => 'reports', 
													'action' => 'queries', 
													'?' => array(
														'query_id' => $query['Query']['id']
													)
												), array(
													'target' => '_blank',
													'class' => 'btn btn-small btn-default'
												)); 
											?>
											<?php
												echo $this->Html->link(
													'Resend', 
													array(
														'controller' => 'queries', 
														'action' => 'ajax_resend',
														$query['Query']['id']
													), 
													array(
														'class' => 'btn btn-default btn-small',						
														'data-target' => '#modal-resend-users',
														'data-toggle' => 'modal'
													)
												); 
											?>
										<?php endif; ?>
										<?php if (empty($query['Query']['parent_id'])): ?>
											<?php 										
												echo $this->Html->link('Create Filter', array(
													'controller' => 'queries', 
													'action' => 'filter', $query['Query']['id']
												), array(
													'class' => 'btn btn-default btn-small'
												)); 
											?>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		<?php endif; ?>
				
		<h3>Reports</h3>
		<p><?php 
			echo $this->Html->link(
				'Generate report', 
				array(
					'controller' => 'reports', 
					'action' => 'generate',
					'?' => array(
						'project' => $project['Project']['id']
					)
				), 
				array(
					'class' => 'btn btn-sm btn-default'
				)
			);
		?> <?php 
			echo $this->Html->link(
				'Fingerprint Logs', 
				array(
					'controller' => 'reports', 
					'action' => 'project_fingerprints',
					$project['Project']['id']
				), 
				array(
					'class' => 'btn btn-sm btn-default'
				)
			);
		?>  <?php 
			echo $this->Html->link(
				'IP Dupe Logs', 
				array(
					'controller' => 'surveys', 
					'action' => 'survey_dupes',
					$project['Project']['id']
				), 
				array(
					'class' => 'btn btn-sm btn-default'
				)
			);
		?> <?php 
			if ($project['SurveyVisitCache']['complete'] > 0) {
				echo $this->Html->link(
					'Time-Based Performance', 
					array(
						'controller' => 'reports', 
						'action' => 'survey',
						$project['Project']['id']
					), 
					array(
						'class' => 'btn btn-sm btn-default'
					)
				);
			}
		?> 
		<?php if ($project['Project']['status'] == PROJECT_STATUS_CLOSED || $project['Project']['status'] == PROJECT_STATUS_INVOICED): ?>
			<?php echo $this->Html->link(
				'TrueSample Panelist Export', array(
					'controller' => 'surveys',
					'action' => 'get_respondents', $project['Project']['id']
				),						
				array(
					'class' => 'btn btn-default'
				)
			); ?>
		<?php endif; ?>
		</p>
		
		<?php if (!empty($reports)): ?>
			<div class="box">				
				<table class="table table-normal">
					<thead>
						<tr>
							<td>Status</td>
							<td>Project</td>
							<td>Partner</td>
							<td>Generated By</td>
							<td>Generated</td>
							<td></td>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($reports as $report): ?>
							<?php echo $this->Element('row_report', array('report' => $report)); ?>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php else: ?>
			<p>No reports have been generated for this project yet.</p>
		<?php endif; ?>
		
		<?php if (!empty($project['SurveyVisitCache'])): ?>
			<?php $hidden_data = unserialize(SURVEY_HIDDEN); ?>
			<?php $total_hidden = $project['SurveyVisitCache']['hidden_no_reason'] + 
					$project['SurveyVisitCache']['hidden_too_long'] +
					$project['SurveyVisitCache']['hidden_too_small'] +
					$project['SurveyVisitCache']['hidden_not_working'] +
					$project['SurveyVisitCache']['hidden_do_not_want'] +
					$project['SurveyVisitCache']['hidden_other'];
			?>
			<?php if ($total_hidden > 0): ?>
				<div class="row-fluid">
					<div class="span4">
						<h3>Survey Hidden Reasons</h3>
						<div class="box">				
							<table class="table table-normal">
								<thead>
									<tr>
										<td>Reason</td>
										<td>Total</td>
										<td>% of hidden</td>
										<td>% of total</td>
									</tr>
								</thead>
								<tbody>
									<tr>
										<td><?php echo $hidden_data[1] ?></td>
										<td><?php echo $project['SurveyVisitCache']['hidden_no_reason']; ?></td>
										<td><?php echo round(($project['SurveyVisitCache']['hidden_no_reason'] * 100) / $total_hidden, 2); ?> %</td>
										<td><?php
											echo round(($project['SurveyVisitCache']['hidden_no_reason'] * 100) / $total_count, 2);
										?>%</td>
									</tr>
									<tr>
										<td><?php echo $hidden_data[2]; ?></td>
										<td><?php echo $project['SurveyVisitCache']['hidden_too_long']; ?></td>
										<td><?php echo round(($project['SurveyVisitCache']['hidden_too_long'] * 100) / $total_hidden, 2); ?> %</td>
										<td><?php
											echo round(($project['SurveyVisitCache']['hidden_too_long'] * 100) / $total_count, 2);
										?>%</td>
									</tr>
									<tr>
										<td><?php echo $hidden_data[3]; ?></td>
										<td><?php echo $project['SurveyVisitCache']['hidden_too_small']; ?></td>
										<td><?php echo round(($project['SurveyVisitCache']['hidden_too_small'] * 100) / $total_hidden, 2); ?> %</td>
										<td><?php
											echo round(($project['SurveyVisitCache']['hidden_too_small'] * 100) / $total_count, 2);
										?>%</td>
									</tr>
									<tr>
										<td><?php echo $hidden_data[4]; ?></td>
										<td><?php echo $project['SurveyVisitCache']['hidden_not_working']; ?></td>
										<td><?php echo round(($project['SurveyVisitCache']['hidden_not_working'] * 100) / $total_hidden, 2); ?> %</td>
										<td><?php
											echo round(($project['SurveyVisitCache']['hidden_not_working'] * 100) / $total_count, 2);
										?>%</td>
									</tr>
									<tr>
										<td><?php echo $hidden_data[5]; ?></td>
										<td><?php echo $project['SurveyVisitCache']['hidden_do_not_want']; ?></td>
										<td><?php echo round(($project['SurveyVisitCache']['hidden_do_not_want'] * 100) / $total_hidden, 2); ?> %</td>
										<td><?php
											echo round(($project['SurveyVisitCache']['hidden_do_not_want'] * 100) / $total_count, 2);
										?>%</td>
									</tr>
									<tr>
										<td><?php echo $hidden_data[6]; ?></td>
										<td><?php echo $project['SurveyVisitCache']['hidden_other']; ?></td>
										<td><?php echo round(($project['SurveyVisitCache']['hidden_other'] * 100) / $total_hidden, 2); ?> %</td>
										<td><?php
											echo round(($project['SurveyVisitCache']['hidden_other'] * 100) / $total_count, 2);
										?>%</td>
									</tr>
									<tr>
										<td></td>
										<td><?php
											$total = 0;
											foreach ($project['SurveyVisitCache'] as $key => $val) {
												if (substr($key, 0, 6) == 'hidden') {
													$total = $total + $val;
												}
											}
											echo '<strong>'.$total.'</strong>';
										?></td>
										<td></td>
										<td><?php
											echo round(($total * 100) / $total_count, 2);
										?>%</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>	
				</div>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>

<?php echo $this->Element('modal_add_partner'); ?>
<?php echo $this->Element('modal_project_status'); ?>
<?php echo $this->Element('modal_query_send'); ?>

<div id="modal-survey-links" class="modal hide" style="width: 750px;">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Survey Links</h6>
	</div>
	<div class="modal-body nopadding"></div>
	<div class="modal-footer">
		<button class="btn btn-default" data-dismiss="modal">Close</button>
	</div>
</div>

<div id="modal-view-query" class="modal hide" style="width: 750px;">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">View Query</h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<button class="btn btn-default" data-dismiss="modal">Close</button>
	</div>
</div>

<?php echo $this->Form->create(null, array(
	'url' => array('controller' => 'queries', 'action' => 'ajax_resend')
)); ?>
<div id="modal-resend-users" class="modal hide" style="width: 750px;">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Resend Emails</h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<button class="btn btn-default" data-dismiss="modal">Cancel</button> 
		<?php echo $this->Form->submit('Resend Emails', array('class' => 'btn btn-primary', 'div' => false, 'onclick' => '$(this).attr("disabled", true); $(this).parents("form").submit(); ')); ?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>

<div id="modal-fed-qualifications" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Lucid Qualifications from their API</h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<button class="btn btn-default" data-dismiss="modal">Close</button> 
	</div>
</div>

<div id="modal-qualifications" style="width:60%;left:40%" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Qualification Information</h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<button class="btn btn-default" data-dismiss="modal">Close</button> 
	</div>
</div>

<div id="modal-cint-qualifications" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Cint Qualifications from cint respondent quota</h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<button class="btn btn-default" data-dismiss="modal">Close</button> 
	</div>
</div>

<div id="modal-rfg-qualifications" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Rfg Qualifications from Rfg Api</h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<button class="btn btn-default" data-dismiss="modal">Close</button> 
	</div>
</div>

<div id="modal-spectrum-qualifications" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Spectrum Qualifications from Spectrum Api</h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<button class="btn btn-default" data-dismiss="modal">Close</button> 
	</div>
</div>

<div id="modal-spectrum-api-json" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Spectrum API Json from Live Spectrum Api</h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<button class="btn btn-default" data-dismiss="modal">Close</button> 
	</div>
</div>
<div id="modal-view-qualification" style="width:60%; left:40%;" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Qualification Information</h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<button class="btn btn-default" data-dismiss="modal">Close</button>
	</div>
</div>
<div id="modal-edit-quotas" style="width:40%; left:50%;" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Qualification Information</h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<button class="btn btn-primary" id="save_btn" data-dismiss="modal">Save</button>
	</div>
</div>

<?php echo $this->Form->create('Qualification', array('url' => array('controller' => 'surveys', 'action' => 'edit_user_qualifications'))); ?>
<div id="modal-edit-userids" style="width:65%; left:37%;" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Edit Qualification Filters</h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">
		<?php echo $this->Form->submit('Save', array('class' => 'btn btn-primary')); ?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>
<?php echo $this->Form->create('Clicks', array('url' => array('controller' => 'surveys', 'action' => 'ajax_click_templates', $project['Project']['id']))); ?>
	<div id="modal-click-templates" style="width:80%; left:30%;" class="modal hide">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
			<h6 id="modal-tablesLabel">Set Total Clicks Allowed</h6>
		</div>
		<div class="modal-body">
		</div>
		<div class="modal-footer">
			<?php echo $this->Form->submit('Add Distribution', array('class' => 'btn btn-primary pull-left')); ?>
		</div>
	</div>
<?php echo $this->Form->end(null); ?>

<?php echo $this->Element('modal_query_statistics'); ?>
<?php echo $this->Element('modal_rates'); ?>
<?php if ($project['Project']['router']): ?>
	<?php echo $this->Element('model_pushed_status'); ?>
<?php endif; ?>

<?php if (!empty($project['ProjectIr'])): ?>
	<?php echo $this->Element('modal_project_irs'); ?>
<?php endif; ?>