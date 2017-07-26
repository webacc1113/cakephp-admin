<h5>Current Survey Issue Report</h5>
<p class="pull-left">
	Status: 
	<?php 
		if ($history_request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_APPROVED) { ?>
			<b>Approved</b>
			<br/>by <?php echo $history_request['Admin']['admin_user'];
		} 
		elseif ($history_request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_REJECTED) { ?>
			<b>Rejected</b>
			<br/>by <?php echo $history_request['Admin']['admin_user'];
		}
		else { ?>
			<b>Pending</b><?php
		}
	?>
</p>
<p class="pull-right">
<?php
	if ($history_request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_REJECTED || $history_request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_PENDING) {
		echo $this->Html->link('Approve', array(
			'action' => 'ajax_approve',
			$history_request['HistoryRequest']['id'],
			'?' => array('submit_to_next' => true)
		), array(
			'div' => false,
			'class' => 'btn btn-success',
			'style' => 'margin-right:5px;',
			'data-toggle' => 'modal',
			'data-target' => '#modal-approve-history_request',
			'id' => 'approve-history-'.$history_request['HistoryRequest']['id']
		));
	}
	
	if ($history_request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_APPROVED || $history_request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_PENDING) {
		echo $this->Html->link('Reject', array(
			'action' => 'ajax_reject',
			$history_request['HistoryRequest']['id'],
			'?' => array('submit_to_next' => true) 
		), array(
			'div' => false,
			'class' => 'btn btn-danger',
			'style' => 'margin-right:5px;',
			'data-toggle' => 'modal',
			'data-target' => '#modal-reject-history_request',
			'id' => 'reject-history-'.$history_request['HistoryRequest']['id']
		));
	} 
	
	echo $this->Html->link('Change Project', array(
		'action' => 'ajax_change_project',
		$history_request['HistoryRequest']['id']
	), array(
		'div' => false,
		'class' => 'btn btn-default',
		'style' => 'margin-right:5px;',
		'data-toggle' => 'modal',
		'data-target' => '#modal-change-history_request',
		'id' => 'change-history-'.$history_request['HistoryRequest']['id']
	));
	
	echo $this->Html->link('Skip', array(
		'action' => 'next_history_request',
		$history_request['HistoryRequest']['id']
	), array(
		'div' => false,
		'class' => 'btn btn-default'
	)); ?>
</p>
<div class="clearfix"></div>
<div class="box">
	<?php $REPORTS = unserialize(SURVEY_REPORT_TYPES); ?>
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>User</td>
				<td>Project #</td>
				<td>Requested Points</td>
				<td>Awarded Points</td>
				<td>User Report</td>
				<td>User Statement</td>
				<td>Link</td>
				<td>Attachment</td>
				<td>Date</td>
				<td>Total Awarded Missing Points</td>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td>
					<?php echo $this->Element('user_dropdown', array('user' => $history_request['User'])); ?>
					<small><?php echo $history_request['User']['email']; ?></small>
				</td>
				<td>
					<?php echo $this->Html->link('#'.$history_request['HistoryRequest']['project_id'], array('controller' => 'surveys', 'action' => 'dashboard', $history_request['HistoryRequest']['project_id'])); ?>
				</td>
				<td><?php echo $history_request['Project']['award']; ?></td>
				<td>
					<?php if ($history_request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_REJECTED) : ?>
						0
					<?php else: ?>
						<?php echo $history_request['Transaction']['amount'];?>
					<?php endif; ?>
				</td>
				<td><?php 
					echo $REPORTS[$history_request['HistoryRequest']['report']]; 
					if ($history_request['HistoryRequest']['report'] == SURVEY_REPORT_LATE_NQ_OQ) {
						echo '<br />(Answered questions : <b>'. $history_request['HistoryRequest']['answered'].'</b>)';
					}
				?></td>
				<td><?php echo $history_request['HistoryRequest']['statement']; ?></td>
				<td><?php 
					if (!empty($history_request['HistoryRequest']['link'])) {
						$url = parse_url($history_request['HistoryRequest']['link']);
						if (isset($url['host']) && !empty($url['host'])) { ?>
							<span class="tt" title="" data-toggle="tooltip" data-original-title="<?php echo $history_request['HistoryRequest']['link']; ?>">
								<?php echo $url['host']?>/...
							</span><?php
						}
						else {
							echo $history_request['HistoryRequest']['link'];
						}
					} ?>
				</td>
				<td style="text-align: center;"><?php
					if (!empty($history_request['HistoryRequest']['attachment'])) {
						echo $this->Html->link('<i class="icon-download-alt"></i>', array(
							'action' => 'ajax_attachment',
							$history_request['HistoryRequest']['id']
						), array(
							'div' => false,
							'escape' => false,
							'data-toggle' => 'modal',
							'data-target' => '#modal-attachment-history_request',
							'id' => 'attachment-history-'.$history_request['HistoryRequest']['id']
						));
					}
					else {
						echo '--';
					}?></td>
				<td>
					<?php echo $this->Time->format($history_request['HistoryRequest']['created'], Utils::dateFormatToStrftime('Y-m-d h:i A'), false, $timezone); ?>
				</td>
				<td>
					<?php echo $history_request['User']['missing_points']; ?>
				</td>
			</tr>
		</tbody>
	</table>
</div>

<h5>Project Information</h5>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>ID</td>
				<td>Name</td>
				<td>Group</td>
				<td>Client</td>
				<td>CL</td>
				<td>C</td>
				<td>NQ</td>
				<td>OQ</td>
				<td>LOI</td>
				<td>IR</td>
				<td>EPC</td>
				<td>Drop %</td>
				<td>Client Rate</td>
				<td>Award</td>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><?php echo $history_request['Project']['id']; ?></td>
				<td><?php echo $history_request['Project']['prj_name']; ?></td>
				<td><?php echo $history_request['Project']['Group']['name']; ?></td>
				<td><?php echo $history_request['Project']['Client']['client_name']; ?></td>
				<td><?php echo number_format($history_request['Project']['SurveyVisitCache']['click']); ?></td>
				<td><?php echo number_format($history_request['Project']['SurveyVisitCache']['complete']); ?></td>
				<td><?php echo number_format($history_request['Project']['SurveyVisitCache']['nq']); ?></td>
				<td><?php echo number_format($history_request['Project']['SurveyVisitCache']['overquota']); ?></td>
				<td><?php echo $history_request['Project']['est_length']; ?>  / 
				<?php if (!empty($history_request['Project']['SurveyVisitCache']['loi_seconds'])) : ?>
					<?php echo round($history_request['Project']['SurveyVisitCache']['loi_seconds'] / 60); ?>
				<?php else: ?>
					<span class="muted">-</span>
				<?php endif; ?></td>
				<td><?php 
					$project['Project'] = $history_request['Project'];
					$project['SurveyVisitCache'] = $history_request['Project']['SurveyVisitCache'];
					echo $this->App->ir($project); 
				?></td>
				<td><?php echo $this->App->epc($project); ?></td>
				<td><?php echo $this->App->drops($project); ?></td>
				<td>
					<?php if (count($history_request['Project']['HistoricalRates']) > 1): ?>
						<?php echo $this->Html->link($this->App->dollarize($history_request['Project']['client_rate'], 2), '#', array(
							'escape' => false,
							'data-target' => '#modal-rates',
							'data-toggle' => 'modal',
						)); ?>
					<?php else: ?>
						<?php echo $this->App->dollarize($history_request['Project']['client_rate'], 2);?>
					<?php endif; ?>
				</td>
				<td><?php echo $history_request['Project']['award']; ?></td>
			</tr>
		</tbody>
	</table>
</div>

<h5>Panelist History</h5>
<?php $STATUSES = unserialize(SURVEY_STATUSES); ?>
<div class="box">	
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Date</td>
				<td>IP Address</td>
				<td>Started</td>
				<td>Termed</td>
				<td>LOI</td>
				<td>User Agent</td>
				<td>Language</td>
				<td>Country</td>
				<td>State</td>
				<td>Proxy?</td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($panelist_histories as $panelist_history): ?>
				<tr data-id="<?php echo $panelist_history['PanelistHistory']['id']; ?>">
					<td>
						<?php echo $this->Time->format($panelist_history['PanelistHistory']['created'], Utils::dateFormatToStrftime('Y-m-d h:i A'), false, $timezone); ?>
					</td>
					<td><?php 
							echo $this->Html->link($panelist_history['PanelistHistory']['ip_address'], array(
								'controller' => 'users', 'action' => 'ip_address', $panelist_history['PanelistHistory']['ip_address']
							)); 
						?> <a href="http://whatismyipaddress.com/ip/<?php echo $panelist_history['PanelistHistory']['ip_address']; ?>" target="_blank" class="icon-wrench"></a>
					</td>
					<td>
						<?php if (is_null($panelist_history['PanelistHistory']['click_status'])): ?>
							Skipped
						<?php elseif ($panelist_history['PanelistHistory']['click_status'] > 0): ?>
							<?php echo $STATUSES[$panelist_history['PanelistHistory']['click_status']]; ?>
						<?php elseif (!is_null($panelist_history['PanelistHistory']['click_status'])): ?>
							<span class="label label-red">Error</span> - <?php echo $click_failures[$panelist_history['PanelistHistory']['click_failure']]; ?>
						<?php endif; ?>
					</td>
					<td>
						<?php if ($panelist_history['PanelistHistory']['term_status'] > 0): ?>
							<?php echo $STATUSES[$panelist_history['PanelistHistory']['term_status']]; ?>
						<?php elseif ($panelist_history['PanelistHistory']['term_status'] == '0'): ?>
							<span class="label label-red">Error</span> - <?php echo $term_failures[$panelist_history['PanelistHistory']['term_failure']]; ?>
						<?php endif; ?>
					</td>
					<td style="white-space: nowrap;">
						<?php if (!is_null($panelist_history['PanelistHistory']['panelist_loi'])): ?>
							<?php if ($panelist_history['PanelistHistory']['late_term'] && $panelist_history['PanelistHistory']['term_status'] == SURVEY_NQ): ?>
								<span class="label label-red" title="Late Term"><?php echo round($panelist_history['PanelistHistory']['panelist_loi'] / 60); ?></span>
							<?php else: ?>
								<?php echo round($panelist_history['PanelistHistory']['panelist_loi'] / 60); ?>
							<?php endif; ?>
							<?php if ($panelist_history['PanelistHistory']['term_status'] == SURVEY_COMPLETED): ?>
								 / 
								<?php if (isset($panelist_history['SurveyVisitCache']['loi_seconds']) && !empty($panelist_history['SurveyVisitCache']['loi_seconds'])): ?>
									<?php echo round($panelist_history['SurveyVisitCache']['loi_seconds'] / 60); ?>
								<?php else: ?>
									<span class="muted">-</span>
								<?php endif; ?>
							<?php endif; ?>
						<?php endif; ?>
					</td>
					<td>
						<?php if (isset($user_agents[$panelist_history['UserIp']['user_agent']])): ?>
							<?php $user_agent = $agents[$user_agents[$panelist_history['UserIp']['user_agent']]]; ?>
							<span title="<?php echo $panelist_history['UserIp']['user_agent']; ?>">
								<?php echo $user_agent['UserAgentValue']['platform_type']; ?> · 
								<?php echo $user_agent['UserAgentValue']['platform_name']; ?> · 
								<?php echo isset($user_agent['UserAgentValue']['browser_name']) ? $user_agent['UserAgentValue']['browser_name']: '<span class="muted">Unknown</span>'; ?>
							</span>
						<?php else: ?>
							<?php echo $panelist_history['UserIp']['user_agent']; ?>
						<?php endif; ?>
					</td>
					<td><?php echo $panelist_history['UserIp']['user_language']; ?></td>
					<td><?php echo $panelist_history['UserIp']['country']; ?></td>
					<td><?php echo $panelist_history['UserIp']['state']; ?></td>
					<td>
						<?php if (!is_null($panelist_history['UserIp']['proxy'])) : ?>
							<?php if ($panelist_history['UserIp']['proxy'] == 1): ?>
								<?php if (isset($panelist_history['IpProxy']) && !empty($panelist_history['IpProxy'])): ?>
									<span class="label label-important"><?php echo $panelist_history['IpProxy']['proxy_score']; ?></span>
								<?php endif; ?>
							<?php else: ?>
								<?php if (isset($panelist_history['IpProxy']) && !empty($panelist_history['IpProxy'])): ?>
									<span class="label label-success"><?php echo $panelist_history['IpProxy']['proxy_score']; ?></span>
								<?php endif; ?>
							<?php endif; ?>
						<?php else: ?>
							<span class="muted">Unchecked</span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<h5>Survey Visits</h5>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Timestamp</td>
				<td>Hash</td>
				<td>Action</td>
				<td>Link</td>
				<td>IP Address</td>
				<td>Referrer</td>
				<td>User Agent</td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($survey_visits as $survey_visit): ?>
				<tr>
					<td><?php echo $this->Time->format($survey_visit['SurveyVisit']['created'], Utils::dateFormatToStrftime('Y-m-d h:i:s A'), false, $timezone); ?></td>
					<td><?php echo $this->Form->input('hash', array(
						'value' => $survey_visit['SurveyVisit']['hash']
					)); ?></td>
					<td><?php echo $STATUSES[$survey_visit['SurveyVisit']['type']]; ?></td>
					<td><?php echo $survey_visit['SurveyVisit']['link']; ?></td>
					<td><?php 
						echo $this->Html->link($survey_visit['SurveyVisit']['ip'], array(
							'controller' => 'users', 'action' => 'ip_address', $survey_visit['SurveyVisit']['ip']
						)); 
					?> <a href="http://whatismyipaddress.com/ip/<?php echo $survey_visit['SurveyVisit']['ip']; ?>" target="_blank" class="icon-wrench"></a></td>
					<td><?php echo $survey_visit['SurveyVisit']['referrer']; ?></td>
					<td>
						<?php if (isset($user_agents[$survey_visit['SurveyVisit']['user_agent']])): ?>
							<?php $user_agent = $agents[$user_agents[$survey_visit['SurveyVisit']['user_agent']]]; ?>
							<span title="<?php echo $survey_visit['SurveyVisit']['user_agent']; ?>">
								<?php echo $user_agent['UserAgentValue']['platform_type']; ?> · 
								<?php echo $user_agent['UserAgentValue']['platform_name']; ?> · 
								<?php echo isset($user_agent['UserAgentValue']['browser_name']) ? $user_agent['UserAgentValue']['browser_name']: '<span class="muted">Unknown</span>'; ?>
							</span>
						<?php else: ?>
							<?php echo $survey_visit['SurveyVisit']['user_agent']; ?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<h5>Currently Paid Transaction</h5>
<div class="box">	
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Note</td>
				<td>Amount</td>
				<td>Executed</td>
			</tr>
		</thead>
		<tbody>
			<?php if (!empty($paid_transaction)): ?>
				<tr>
					<td><?php echo $paid_transaction['Transaction']['name']; ?></td>
					<td><?php echo $paid_transaction['Transaction']['amount']; ?></td>
					<td><?php echo $this->Time->format($paid_transaction['Transaction']['executed'], Utils::dateFormatToStrftime('Y-m-d h:i A'), false, $timezone); ?></td>
				</tr>
			<?php endif; ?>
		</tbody>
		</tbody>
	</table>
</div>

<h5>Other Reports On This Survey</h5>
<div class="box">	
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>User</td>
				<td>Awarded Points</td>
				<td>User Report</td>
				<td>User Statement</td>
				<td>Date</td>
				<td>Status</td>
				<td></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($other_requests as $other): ?>
				<tr>
					<td>
						<?php echo $this->Element('user_dropdown', array('user' => $other['User'])); ?>
						<small><?php echo $other['User']['email']; ?></small>
					</td>
					<td><?php
						if ($other['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_REJECTED) { 
							echo '0';
						}
						else { 
							echo $other['Transaction']['amount']; 
						}
					?></td>
					<td><?php 
						echo $REPORTS[$other['HistoryRequest']['report']]; 
						if ($other['HistoryRequest']['report'] == SURVEY_REPORT_LATE_NQ_OQ) {
							echo '<br />(Answered questions : <b>'. $other['HistoryRequest']['answered'].'</b>)';
						}
					?></td>
					<td><?php echo $other['HistoryRequest']['statement']; ?></td>
					<td>
						<?php echo $this->Time->format($other['HistoryRequest']['created'], Utils::dateFormatToStrftime('Y-m-d h:i A'), false, $timezone); ?>
					</td>
					<td><?php 
						if ($other['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_APPROVED) { ?>
							<b class="label label-green">Approved</b><?php
						} 
						elseif ($other['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_REJECTED) { ?>
							<b class="label label-red">Rejected</b><?php
						}
						else {?>
							<b class="label">Pending</b><?php
						} ?>
					</td>
					<td style="white-space: nowrap;"><?php
						echo $this->Html->link('Search', array(
							'controller' => 'history_requests',
							'action' => 'info', 
							$other['HistoryRequest']['id']
						), array(
							'class' => 'btn btn-default btn-mini',
							'target' => '_blank'
						)); ?> <?php
						if ($other['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_REJECTED || $other['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_PENDING) {
							echo $this->Html->link('Approve', array(
								'action' => 'ajax_approve',
								$other['HistoryRequest']['id'], '?' => array(
									'submit_update_row' => true,
									'report_type' => 'other'
								)
							), array(
								'escape' => false,
								'div' => false,
								'class' => 'btn btn-mini btn-success',
								'data-toggle' => 'modal',
								'data-target' => '#modal-approve-history_request',
								'id' => 'approve-history-'.$other['HistoryRequest']['id']
							));
						} ?> <?php
						if ($other['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_APPROVED || $other['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_PENDING) {
							echo $this->Html->link('Reject', array(
								'action' => 'ajax_reject',
								$other['HistoryRequest']['id'], '?' => array(
									'submit_update_row' => true,
									'report_type' => 'other'
								)
							), array(
								'div' => false,
								'class' => 'btn btn-mini btn-danger',
								'data-toggle' => 'modal',
								'data-target' => '#modal-reject-history_request',
								'id' => 'reject-history-'.$other['HistoryRequest']['id']
							));
						} ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<h5>Other Reports From This User</h5>
<?php $REPORTS = unserialize(SURVEY_REPORT_TYPES); ?>
<div class="box">	
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Project #</td>
				<td>Requested Points</td>
				<td>Awarded Points</td>
				<td>User Report</td>
				<td>User Statement</td>
				<td>Date</td>
				<td>Status</td>
				<td></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($recent_history_requests as $request): ?>
				<tr>
					<td>
						<?php echo $this->Html->link('#'.$request['HistoryRequest']['project_id'], array('controller' => 'surveys', 'action' => 'dashboard', $request['HistoryRequest']['project_id'])); ?>
					</td>
					<td><?php echo $request['Project']['award']; ?></td>
					<td><?php echo ($request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_REJECTED) ? 0 : $request['Transaction']['amount']; ?></td>
					<td><?php 
						echo $REPORTS[$request['HistoryRequest']['report']]; 
						if ($request['HistoryRequest']['report'] == SURVEY_REPORT_LATE_NQ_OQ) {
							echo '<br />(Answered questions : <b>'. $request['HistoryRequest']['answered'].'</b>)';
						}
					?></td>
					<td><?php echo $request['HistoryRequest']['statement']; ?></td>
					<td>
						<?php echo $this->Time->format($request['HistoryRequest']['created'], Utils::dateFormatToStrftime('Y-m-d h:i A'), false, $timezone); ?>
					</td>
					<td><?php 
						if ($request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_APPROVED) { ?>
							<b class="label label-green">Approved</b><?php
						} 
						elseif ($request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_REJECTED) { ?>
							<b class="label label-red">Rejected</b><?php
						}
						else { ?>
							<b class="label">Pending</b><?php
						} ?>
					</td>
					<td style="white-space: nowrap;"><?php
						echo $this->Html->link('Search', array(
							'controller' => 'history_requests',
							'action' => 'info', 
							$request['HistoryRequest']['id']
						), array(
							'class' => 'btn btn-default btn-mini',
							'target' => '_blank'
						)); ?> <?php 
						if ($request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_REJECTED || $request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_PENDING) {
							echo $this->Html->link('Approve', array(
								'action' => 'ajax_approve',
								$request['HistoryRequest']['id'], '?' => array(
									'submit_update_row' => true,
									'report_type' => 'redeemed'
								)
							), array(
								'div' => false,
								'class' => 'btn btn-mini btn-success',
								'data-toggle' => 'modal',
								'data-target' => '#modal-approve-history_request',
								'id' => 'approve-history-'.$request['HistoryRequest']['id']
							));
						} ?> <?php 
						if ($request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_APPROVED || $request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_PENDING) {
							echo $this->Html->link('Reject', array(
								'action' => 'ajax_reject',
								$request['HistoryRequest']['id'], '?' => array(
									'submit_update_row' => true,
									'report_type' => 'redeemed'
								)
							), array(
								'div' => false,
								'class' => 'btn btn-mini btn-danger',
								'data-toggle' => 'modal',
								'data-target' => '#modal-reject-history_request',
								'id' => 'reject-history-'.$request['HistoryRequest']['id']
							));
						} ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<?php echo $this->Element('modal_reject_history_request'); ?>
<?php echo $this->Element('modal_approve_history_request'); ?>
<?php echo $this->Element('modal_history_request_attachment'); ?>
<?php echo $this->Element('modal_change_history_request'); ?>
<?php echo $this->Element('modal_user_quickprofile'); ?>