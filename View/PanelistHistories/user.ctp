<h3>Survey History</h3>

<div class="box">	
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td class="gender"></td>
				<td>Name</td>
				<td>Score</td>
				<td>Level</td>
				<td>Age</td>
				<td>Country</td>
				<td>State</td>
				<td>Created</td>
				<td>Verified</td>
				<td>Last Touched</td>
				<td>Active</td>
				<td>Origin</td>
				<td>Balance</td>
				<td>Pending</td>
				<td><div class="tt" data-placement="top" data-toggle="tooltip" title="Calculated once per 24 hours">Lifetime <sup>*</sup></div></td>				
			</tr>
		</thead>
		<tbody>
			<?php echo $this->Element('user_row', array('user' => $user, 'user_analysis' => $user_analysis)); ?>
		</tbody>
	</table>
</div>
<?php echo $this->Element('modal_user_hellban', array('user' => $user['User'])); ?>
<?php echo $this->Element('modal_user_remove_hellban', array('user' => $user['User'])); ?>
<?php echo $this->Element('modal_user_score'); ?>
<?php echo $this->Element('modal_user_quickprofile'); ?>
<?php echo $this->Element('modal_user_referrer'); ?>

<p><?php 
	echo $this->Html->link('Show User History', array(
		'controller' => 'users', 
		'action' => 'history',
		$user['User']['id'],
	), array(
		'class' => 'btn btn-default'
	)); 
?> <?php
	echo $this->Html->link('Show Survey History', array(
		'controller' => 'users', 
		'action' => 'history',
		$user['User']['id'], 
		'?' => array('filter' => 'surveys')
	), array(
		'class' => 'btn btn-default'
	)); 
?> <?php
	echo $this->Html->link('Show Survey History (Beta)', array(
		'controller' => 'panelist_histories',
		'action' => 'user', 
		'?' => array(
			'user_id' => $user['User']['id']
		)), 
		array(
			'class' => 'btn btn-primary'
		)
	); 
?> <?php
	if (!empty($user['User']['verified'])) {
		echo $this->Html->link('Show Survey Quality', array(
				'controller' => 'users',
				'action' => 'survey_quality', 
				$user['User']['id']
			), 
			array(
				'class' => 'btn btn-default'
			)
		); 
	}
?> <?php
	echo $this->Html->link('<i style="font-size:25px;" class="icon-download-alt"></i>', array(
							'controller' => 'PanelistHistories',
							'action' => 'user',
							'export',
							'?' => array(
								'user_id' => $user['User']['id']
							)
						), array(
							'div' => false,
							'title' => 'Export panelist click/user history',
							'escape' => false,
							'style' => 'float:right;',
						));
?>
</p>
	
<?php $STATUSES = unserialize(SURVEY_STATUSES); ?>
<div class="box">	
	<table cellpadding="0" cellspacing="0" class="table table-normal panelist-history">
		<thead>
			<tr>
				<td>Date</td>
				<td>IP Address</td>
				<td>Project</td>
				<td>Points</td>
				<td>Group</td>
				<td>Client</td>
				<td>Started</td>
				<td>Termed</td>
				<td>LOI</td>
				<td>User Agent</td>
				<td>Language</td>
				<td>Country</td>
				<td>State</td>
				<td>Proxy?</td>
				<td>Report Type</td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($panelist_histories as $panelist_history): ?>
				<?php
					$ip_address_index = $panelist_ip_address_group[$panelist_history['PanelistHistory']['ip_address']];
					$ip_address_alert_class = $country_alert_class = $state_alert_class = $session_alert_class = $click_alert_class = '';
					if ($ip_address_index) {
						switch ($ip_address_index) {
							case 1:
							case 2:
								$ip_address_alert_class = 'label label-green';
							break;
							case 3:
							case 4:
								$ip_address_alert_class = 'label label-yellow';
								break;
							case 5:
							case 6:
								$ip_address_alert_class = 'label label-orange';
								break;
							default:
								$ip_address_alert_class = 'label label-red';
								break;
						}	
					}	
					if ($panelist_history['UserIp']['country'] != $query_profile['QueryProfile']['country']) {
						$country_alert_class = 'label label-red';
					}
					if ($panelist_history['UserIp']['state'] != $query_profile['QueryProfile']['state']) {
						$state_alert_class = 'label label-red';
					}
					if ($panelist_history['PanelistHistory']['is_session_active']) {
						$session_alert_class = 'label label-blue';
					}
					else {
						$session_alert_class = 'label label-dark-blue';
					}
					if (is_null($panelist_history['PanelistHistory']['term_status'])) {
						if (!empty($panelist_history['PanelistHistory']['click_status']) && $panelist_history['PanelistHistory']['click_status'] == SURVEY_CLICK) {
							$click_alert_class = 'label label-purple';
						}
					}
				?>
				<tr data-id="<?php echo $panelist_history['PanelistHistory']['id']; ?>">
					<td>
						<span class="<?php echo $session_alert_class; ?>"><?php echo $this->Time->format($panelist_history['PanelistHistory']['created'], Utils::dateFormatToStrftime('M d h:i:s A'), false, $timezone); ?></span>
					</td>
					<td><?php 
							echo $this->Html->link($panelist_history['PanelistHistory']['ip_address'], array(
								'controller' => 'users', 'action' => 'ip_address', $panelist_history['PanelistHistory']['ip_address']
							), array('class' => $ip_address_alert_class)); 
						?> <a href="http://whatismyipaddress.com/ip/<?php echo $panelist_history['PanelistHistory']['ip_address']; ?>" target="_blank" class="icon-wrench"></a>
					</td>
					<td>
						<?php echo $this->Html->link('#'.$panelist_history['Project']['id'], array('controller' => 'surveys', 'action' => 'dashboard', $panelist_history['Project']['id'])); ?>
					</td>
					<td>
						<?php echo $panelist_history['PanelistHistory']['project_points']; ?>
					</td>
					<td><?php echo $panelist_history['Group']['name']; ?></td>
					<td><?php echo $panelist_history['Client']['client_name']; ?></td>
					<td>
						<?php if (is_null($panelist_history['PanelistHistory']['click_status'])): ?>
							Skipped
						<?php elseif ($panelist_history['PanelistHistory']['click_status'] > 0): ?>
							<span class="<?php echo $click_alert_class; ?>"><?php echo $STATUSES[$panelist_history['PanelistHistory']['click_status']]; ?></span>
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
					<td><span class="<?php echo $country_alert_class; ?>"><?php echo $panelist_history['UserIp']['country']; ?></span></td>
					<td><span class="<?php echo $state_alert_class; ?>"><?php echo $panelist_history['UserIp']['state']; ?></span></td>
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
					<td></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<div class="pagination">
	<ul>
		<li><?php echo $this->Paginator->prev('« Prev', array(), null, array('class'=>'disabled'));?></li>
		<li><?php echo $this->Paginator->next('» Next', array(), null, array('class' => 'disabled'));?></li>
	</ul>
</div>