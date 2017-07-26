<script type="text/javascript">
	$(document).ready(function() {
		$('div.tt').tooltip({
		});
	});
</script>
<h3>User Survey Quality</h3>

<div class="box">	
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td></td>
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
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Start</td>
				<td>Actual LOI</td>
				<td>Actual IR</td>
				<td>Result</td>
				<td>Date</td>
			</tr>
		</thead>
		<tbody>
			<?php if (!empty($user_surveys)): ?>
				<?php foreach ($user_surveys as $user_survey): ?>
					<tr>
						<td class="nowrap">
							<?php if (!empty($user_survey['Project']['SurveyVisitCache']) && $user_survey['Project']['SurveyVisitCache']['created'] != '0000-00-00 00:00:00') : ?>
								<?php echo $this->Time->format($user_survey['Project']['SurveyVisitCache']['created'], Utils::dateFormatToStrftime('M d h:i A'), false, $timezone); ?>
							<?php endif; ?>
						</td>
						<td class="nowrap">
							<?php if (!empty($user_survey['Project']['SurveyVisitCache']['loi_seconds'])) : ?>
								<?php echo round($user_survey['Project']['SurveyVisitCache']['loi_seconds'] / 60); ?>
							<?php else: ?>
								<span class="muted">-</span>
							<?php endif; ?></td>
						<td class="nowrap"><?php 
							$show_warning_label = false;
							if (!empty($user_survey['Project']['SurveyVisitCache']['ir'])) {
								$actual_ir = $user_survey['Project']['SurveyVisitCache']['ir'];
							}
							elseif (!empty($user_survey['Project']['bid_ir']) && !empty($user_survey['Project']['SurveyVisitCache']['complete'])) {
								$actual_ir = round($user_survey['Project']['SurveyVisitCache']['complete'] / $user_survey['Project']['SurveyVisitCache']['click'], 2) * 100;
							}
							if (isset($actual_ir) && $actual_ir <= ($user_survey['Project']['bid_ir'] / 2)) {
								$show_warning_label = true;
							}
							if (!empty($user_survey['Project']['SurveyVisitCache']['complete'])) {
								if (empty($user_survey['Project']['SurveyVisitCache']['click'])) {
									$user_survey['Project']['SurveyVisitCache']['click'] = 1;
								}
								$actual_ir = (round($user_survey['Project']['SurveyVisitCache']['complete'] / $user_survey['Project']['SurveyVisitCache']['click'], 2) * 100).'%';
								if ($show_warning_label) {
									echo '<span class="label label-red"><strong>'.$actual_ir.'</strong></span>';
								}
								else {
									echo $actual_ir;
								}
							}
							else {
								echo '<span class="muted">-</span>';
							}
						?></td>
						<td><?php
							if (!empty($user_survey['SurveyUserVisit']['status'])) {
								if ($user_survey['SurveyUserVisit']['status'] == SURVEY_COMPLETED) {
									echo '<span class="label label-success">'.$SURVEY_STATUSES[$user_survey['SurveyUserVisit']['status']].'</span>';
								}
								else {
									echo $SURVEY_STATUSES[$user_survey['SurveyUserVisit']['status']];
								}
								if ($user_survey['SurveyUserVisit']['status'] == SURVEY_COMPLETED) {
									$diff = strtotime($user_survey['SurveyUserVisit']['modified']) - strtotime($user_survey['SurveyUserVisit']['created']);
									$minutes = round($diff / 60, 1); 
									if (isset($user_survey['Project']['SurveyVisitCache']['loi_seconds']) && !empty($user_survey['Project']['SurveyVisitCache']['loi_seconds']) && ($minutes < ($user_survey['Project']['SurveyVisitCache']['loi_seconds'] / 120))) {
										echo '<br/><span class="label label-red">'.$minutes.' minutes</span>';
									}
									else {
										echo '<br/>'.$minutes.' minutes';
									}
									if (isset($user_survey['Project']['SurveyVisitCache']['loi_seconds']) && !empty($user_survey['Project']['SurveyVisitCache']['loi_seconds'])) {
										echo '<br/><small class="text-muted">Survey LOI: '.round($user_survey['Project']['SurveyVisitCache']['loi_seconds'] / 60).' minutes</small>';
									}
								}
						}
						?></td>
						<td class="nowrap">
							<?php if (!empty($user_survey['Project']['SurveyVisitCache']) && $user_survey['Project']['SurveyVisitCache']['created'] != '0000-00-00 00:00:00') : ?>
								<?php echo $this->Time->format($user_survey['Project']['SurveyVisitCache']['created'], Utils::dateFormatToStrftime('M d h:i A'), false, $timezone); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else: ?>
				<tr>
					<td class="nowrap" colspan="4">No servey starts after verification.<td>
				</tr>
			<?php endif; ?>
			
		</tbody>
	</table>
</div>