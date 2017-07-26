<?php $this->Html->script('jquery.timer', array('inline' => false)); ?>
<script type="text/javascript">
	$(document).ready(function() {
		$('div.tt').tooltip({
		});
	});
	

	var timer = $.timer(function () {
		$('p[data-status="queued"]').each(function () {
			var $node = $(this);
			var id = $node.data('id');
			$.ajax({
				type: "GET",
				url: "/reports/check/" + id,
				statusCode: {
					201: function (data) {
						if (data.status == 'complete') {
							$node.data('status', 'complete');
							$('p[data-id="' + id + '"]').data('status', 'complete');
							$('a.btn-download', $node).attr('href', data.file).show();
							$('span.btn-waiting', $node).hide();
						}
					}
				}
			});
		});
	});
	timer.set({time: 4000, autostart: true});
</script>
<h3>User History</h3>
<p <?php echo (!empty($report['Report']['id'])) ? 'data-id="'.$report['Report']['id'].'" data-status="'.$report['Report']['status'].'"' : ''; ?>>
	<?php echo $this->Html->link('Export', array('action' => 'ip_address', $this->request->params['pass'][0], true), array('class' => 'btn btn-default')); ?>
	<?php if ($report && $report['Report']['status'] == 'complete'): ?>
		<?php echo $this->Html->link('Download Report', array('controller' => 'reports', 'action' => 'download', $report['Report']['id']), array('class' => 'btn btn-default', 'target' => '_blank')); ?>
	<?php elseif ($report && $report['Report']['status'] == 'queued'): ?>
		<?php echo '<span class="btn-waiting">' . $this->Html->image('ajax-loader.gif') . ' Generating report... please wait</span>'; ?>
		<?php echo $this->Html->link('Download Report', '#', array('style' => 'display: none;', 'class' => 'btn btn-default btn-download', 'target' => '_blank')); ?>
	<?php endif; ?>
</p>
<?php 
	$SURVEY_STATUSES = unserialize(SURVEY_STATUSES); 
	$shown_surveys = array();
?>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>User</td>
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
							<?php echo $this->Element('user_dropdown', array('user' => $user_ip['User'])); ?>
						</td>
						<td class="nowrap">
							<?php if ($user_ip['UserIp']['created'] != '0000-00-00 00:00:00') : ?>
								<?php echo $this->Time->format($user_ip['UserIp']['created'], Utils::dateFormatToStrftime('M d h:i:A'), false, $timezone); ?>
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
								echo '#'.$user_ip['Project']['id'].': '.(!empty($user_ip['Project']['description']) ? $user_ip['Project']['description']: $user_ip['Project']['survey_name']);
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
						<td><?php 
							echo $this->Html->link($user_ip['UserIp']['ip_address'], array(
								'controller' => 'users', 'action' => 'ip_address', $user_ip['UserIp']['ip_address']
							)); 
						?></td>
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
						<td style="white-space: normal;"><?php echo $user_ip['UserIp']['user_agent']; ?></td>
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
<?php if (!empty($user_ips)): ?>
	<?php foreach ($user_ips as $user_ip): ?>
		<?php echo $this->Element('modal_user_hellban', array('user' => $user_ip['User'])); ?>
		<?php echo $this->Element('modal_user_remove_hellban', array('user' => $user_ip['User'])); ?>
	<?php endforeach; ?>
<?php endif; ?>
<?php echo $this->Element('modal_user_quickprofile'); ?>
<?php echo $this->Element('modal_user_referrer'); ?>	
<div class="pagination">
	<ul>
		<li><?php echo $this->Paginator->prev('« Prev', array(), null, array('class'=>'disabled'));?></li>
		<li><?php echo $this->Paginator->next('» Next', array(), null, array('class' => 'disabled'));?></li>
	</ul>
</div>