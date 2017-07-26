<script type="text/javascript">
	$(document).ready(function() {
		$('div.tt').tooltip({
		});
	});
</script>
<h3>User History</h3>

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

<?php 
	$SURVEY_STATUSES = unserialize(SURVEY_STATUSES); 
	$shown_surveys = array();
?>
<p><?php 
	echo $this->Html->link('Show User History', array(
		'controller' => 'users', 
		'action' => 'history',
		$user['User']['id'],
	), array(
		'class' => 'btn '.($filter ? 'btn-default': 'btn-primary')
	)); 
?> <?php
	echo $this->Html->link('Show Survey History', array(
		'controller' => 'users', 
		'action' => 'history',
		$user['User']['id'], 
		'?' => array('filter' => 'surveys')
	), array(
		'class' => 'btn '.(!$filter ? 'btn-default': 'btn-primary')
	)); 
?> <?php
	echo $this->Html->link('Show Survey History (Beta)', array(
		'controller' => 'panelist_histories',
		'action' => 'user', 
		'?' => array(
			'user_id' => $user['User']['id']
		)), 
		array(
			'class' => 'btn btn-default'
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
?></p>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Date</td>
				<td>Timezone</td>
				<td>Activity</td>
				<td style="width: 100px;">End Result</td>
				<td>IP Address</td>
				<td>Location</td>
				<td>Proxy</td>
				<td>User Agent</td>
				<td>Languages</td>
			</tr>
		</thead>
		<tbody>
			<?php if (!empty($user_ips)): ?>
				<?php foreach ($user_ips as $user_ip): ?>
					<?php 
						if ($user_ip['UserIp']['type'] == 'survey' && in_array($user_ip['Project']['id'], $shown_surveys)) {
							continue;
						}
						$shown_surveys[] = $user_ip['Project']['id'];
					?>
					<tr>
						<td class="nowrap">
							<?php if ($user_ip['UserIp']['created'] != '0000-00-00 00:00:00') : ?>
								<?php echo $this->Time->format($user_ip['UserIp']['created'], Utils::dateFormatToStrftime('M d h:i A'), false, $timezone); ?>
							<?php endif; ?>
							<!-- <?php echo $user_ip['UserIp']['id']; ?>-->
						</td>
						<td>
							<?php if (!empty($user_ip['UserIp']['timezone']) && strpos($user_ip['UserIp']['timezone'], 'Asia/') !== false): ?>
								<span class="label label-red"><?php echo $user_ip['UserIp']['timezone']; ?></span>
							<?php else: ?>
								<?php echo $user_ip['UserIp']['timezone']; ?>
							<?php endif; ?>
						</td>
						<td><?php 
							if ($user_ip['UserIp']['type'] == 'survey') {
								echo $this->Html->link('#'.$user_ip['Project']['id'], array('controller' => 'surveys', 'action' => 'dashboard', $user_ip['Project']['id'])).': '.(!empty($user_ip['Project']['description']) ? $user_ip['Project']['description']: $user_ip['Project']['survey_name']);
							}
							else {
								echo $user_ip['UserIp']['type']; 
							}
						?></td>
						<td><?php
							if ($user_ip['UserIp']['type'] == 'survey' && isset($user_ip['SurveyUserVisit']) && !empty($user_ip['SurveyUserVisit']['status'])) {
								if ($user_ip['SurveyUserVisit']['status'] == SURVEY_COMPLETED) {
									echo '<span class="label label-success">'.$SURVEY_STATUSES[$user_ip['SurveyUserVisit']['status']].'</span>';
								}
								else {
									echo $SURVEY_STATUSES[$user_ip['SurveyUserVisit']['status']];
								}
								if ($user_ip['SurveyUserVisit']['status'] == SURVEY_COMPLETED) {
									$diff = strtotime($user_ip['SurveyUserVisit']['modified']) - strtotime($user_ip['SurveyUserVisit']['created']);
									$minutes = round($diff / 60, 1); 
									if (isset($user_ip['SurveyVisitCache']['loi_seconds']) && !empty($user_ip['SurveyVisitCache']['loi_seconds']) && ($minutes < ($user_ip['SurveyVisitCache']['loi_seconds'] / 120))) {
										echo '<br/><span class="label label-red">'.$minutes.' minutes</span>';
									}
									else {
										echo '<br/>'.$minutes.' minutes';
									}
									if (isset($user_ip['SurveyVisitCache']['loi_seconds']) && !empty($user_ip['SurveyVisitCache']['loi_seconds'])) {
										echo '<br/><small class="text-muted">Survey LOI: '.round($user_ip['SurveyVisitCache']['loi_seconds'] / 60).' minutes</small>';
									}
								}
						}
						?></td>
						<td style="white-space: nowrap;"><?php 
							echo $this->Html->link($user_ip['UserIp']['ip_address'], array(
								'controller' => 'users', 'action' => 'ip_address', $user_ip['UserIp']['ip_address']
							)); 
						?> <a href="http://whatismyipaddress.com/ip/<?php echo $user_ip['UserIp']['ip_address']; ?>" target="_blank" class="icon-wrench"></a></td>
						<td><?php
							$location = array();
							if (!empty($user_ip['UserIp']['state'])) {
								$location[] = $user_ip['UserIp']['state'];
							}
							if (!empty($user_ip['UserIp']['country'])) {
								$location[] = $user_ip['UserIp']['country'];
							}
							echo implode(', ', $location); 
						?></td>
						<td>
							<?php if (!is_null($user_ip['UserIp']['proxy'])) : ?>
								<?php if ($user_ip['UserIp']['proxy'] == 1): ?>
									<span class="label label-important"><?php echo $user_ip['IpProxy']['proxy_score']; ?></span>
								<?php else: ?>
									<span class="label label-success"><?php echo $user_ip['IpProxy']['proxy_score']; ?></span>
								<?php endif; ?>
							<?php else: ?>
								<span class="muted">Unchecked</span>
							<?php endif; ?>
						</td>
						<td style="white-space: normal;">
							<?php if (isset($user_agents[$user_ip['UserIp']['user_agent']])): ?>
								<?php $user_agent = $agents[$user_agents[$user_ip['UserIp']['user_agent']]]; ?>
								<span title="<?php echo $user_ip['UserIp']['user_agent']; ?>">
									<?php echo $user_agent['UserAgentValue']['platform_type']; ?> · 
									<?php echo $user_agent['UserAgentValue']['platform_name']; ?> · 
									<?php echo isset($user_agent['UserAgentValue']['browser_name']) ? $user_agent['UserAgentValue']['browser_name']: '<span class="muted">Unknown</span>'; ?>
								</span>
							<?php else: ?>
								<?php echo $user_ip['UserIp']['user_agent']; ?>
							<?php endif; ?>
						</td>
						<td><?php 								
							$languages = Utils::http_languages($user_ip['UserIp']['user_language']);
							$other_language = false;
							if (!empty($languages)) {
								$english = false;
								foreach ($languages as $language => $score) {
									if (strpos($language, 'en') !== false) {
										if ($score == 1) {
											$other_language = false;
											$english = true;
											break;
										}
									}
									if (strpos($language, 'en') === false) {
										$other_language = true;
									}
								}
							}
							if ($other_language) {
								echo '<span class="label label-important">'.$user_ip['UserIp']['user_language'].'</span>';
							}
							else {
								echo $user_ip['UserIp']['user_language']; 
							}
						?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
</div>
	
	<div class="pagination">
		<ul>
			<li><?php echo $this->Paginator->prev('« Prev', array(), null, array('class'=>'disabled'));?></li>
			<li><?php echo $this->Paginator->next('» Next', array(), null, array('class' => 'disabled'));?></li>
		</ul>
	</div>