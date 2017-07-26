<script type="text/javascript">
	$(document).ready(function() {
		$('div.tt').tooltip({
		});
	});
</script>
<h3>Inspecting Survey History for <?php echo $this->App->username($user['User']); ?></h3>

<div class="box">	
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td class="gender"></td>
				<td>Name</td>
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
			<?php echo $this->Element('user_row', array('user' => $user)); ?>
		</tbody>
	</table>
</div>
<?php echo $this->Element('modal_user_hellban', array('user' => $user['User'])); ?>
<?php echo $this->Element('modal_user_remove_hellban', array('user' => $user['User'])); ?>
<?php echo $this->Element('modal_user_score'); ?>
<?php echo $this->Element('modal_user_quickprofile'); ?>
<?php echo $this->Element('modal_user_referrer'); ?>

<div class="box">	
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Status</td>
				<td>IP</td>
				<td>Location</td>
				<td>Survey</td>
				<td>Started</td>
				<td>Ended</td>
				<td>Duration</td>
				<td>Failed Checks</td>
			</tr>
		</thead>
		<tbody>
			<?php $STATUSES = unserialize(SURVEY_STATUSES); ?>
			<?php foreach ($user_survey_visits as $user_survey_visit) : ?>
				<tr>
					<?php 
						switch ($user_survey_visit['SurveyUserVisit']['status']) { 
							case SURVEY_COMPLETED: 
								$label = 'label label-green';
							break;
							case SURVEY_NQ: 
								$label = 'label label-blue';
							break;
							case SURVEY_OVERQUOTA: 
								$label = 'label label-blue';
							break;
							case SURVEY_DUPE: 
							case SURVEY_INTERNAL_NQ: 
							case SURVEY_DUPE_FP: 
								$label = 'label label-blue';
							break;
							default: 
								$label = 'label label-gray';
							
						}
					?>
					<td><span class="label-transaction <?php echo $label; ?>">
						<?php echo $STATUSES[$user_survey_visit['SurveyUserVisit']['status']]; ?>
					</span></td>
					<td><?php echo $user_survey_visit['SurveyUserVisit']['ip']; ?></td>
					<td><?php echo $user_survey_visit['SurveyUserVisit']['region']; ?></td>
					<td>
						#<?php echo $user_survey_visit['Project']['id']; ?>: <?php echo $user_survey_visit['Project']['prj_name']; ?> 
						<?php if (!empty($user_survey_visit['Project']['description'])) : ?>
							(<?php echo $user_survey_visit['Project']['description']; ?>)
						<?php else: ?>
							(<?php echo $user_survey_visit['Project']['survey_name']; ?>)
						<?php endif; ?>
					</td>
					<td><?php 
						echo $this->Time->format($user_survey_visit['SurveyUserVisit']['created'], '%b %d %l:%M:%S %p', false, $timezone); 
					?></td>
					<td><?php echo $user_survey_visit['SurveyUserVisit']['status'] != SURVEY_CLICK 
						? $this->Time->format($user_survey_visit['SurveyUserVisit']['modified'], '%b %d %l:%M:%S %p', false, $timezone)
						: ''; ?></td>
					<td><?php
					if ($user_survey_visit['SurveyUserVisit']['status'] != SURVEY_CLICK) {
						$diff = strtotime($user_survey_visit['SurveyUserVisit']['modified']) - strtotime($user_survey_visit['SurveyUserVisit']['created']);
						$minutes = round($diff / 60, 1); 
						echo $minutes.' minutes';
					}
					?></td>
					<td style="white-space: normal;"><?php 
					$checks = array();
					if (isset($user_survey_visit['SurveyFlag']) && !empty($user_survey_visit['SurveyFlag'])) {
						foreach ($user_survey_visit['SurveyFlag'] as $survey_flag) {
							if (!$survey_flag['passed'] && $survey_flag['flag'] == 'other-language') {
								$checks[] = '<span class="text-error">Non-english language set as primary browser language</span> <span class="muted">'.htmlspecialchars($survey_flag['description']).'</span>';
							}
						}
					} 
					echo implode('<br/>', $checks);
					?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>