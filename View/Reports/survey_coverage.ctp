<?php $SURVEY_STATUSES = unserialize(SURVEY_STATUSES);  ?>
<h3>Invited Panelists for <?php 
	echo $this->Html->link('#'.$qualification['Project']['id'].' ' .$qualification['Project']['prj_name'], array('controller' => 'surveys', 'action' => 'dashboard', $qualification['Project']['id'])); 
?></h3>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>User</td>
				<td>Result</td>
				<td style="width: 40px;">Router Hits</td>
				<td style="width: 40px;">Router High</td>
				<td style="width: 40px;">Router Low</td>
				<td>Last Touched</td>
				<td>Invited</td>
				<td>Email Sent</td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($qualification_users as $qualification_user): ?>
				<?php 
					$class = $qualification_user['User']['last_touched'] >= $qualification_user['QualificationUser']['created'] ? '': 'class="muted"';  
				?>
				<tr <?php echo $class; ?>>
					<td>
						<?php echo $this->Html->link('#'.$qualification_user['QualificationUser']['user_id'], array(
							'controller' => 'panelist_histories',
							'action' => 'user',
							'?' => array('user_id' => $qualification_user['QualificationUser']['user_id'])
						), array(
							'target' => '_blank'
						)); ?>
					</td>
					<td>
						<?php 
							if (isset($qualification_user['SurveyUserVisit']) && !empty($qualification_user['SurveyUserVisit'])) {
								if ($qualification_user['SurveyUserVisit']['status'] == SURVEY_COMPLETED) {
									echo '<span class="label label-success">'.$SURVEY_STATUSES[$qualification_user['SurveyUserVisit']['status']].'</span>';
								}
								else {
									echo $SURVEY_STATUSES[$qualification_user['SurveyUserVisit']['status']];
								}
							}
						?>
					</td>
					<td>
						<?php echo isset($qualification_user['UserRouterLog']) ? $qualification_user['UserRouterLog']['count'] : ''; ?>
					</td>
					<td>
						<?php echo isset($qualification_user['UserRouterLog']) ? $qualification_user['UserRouterLog']['max'] : ''; ?>
					</td>
					<td>
						<?php echo isset($qualification_user['UserRouterLog']) ? $qualification_user['UserRouterLog']['min'] : ''; ?>
					</td>
					<td>
						<?php echo $this->Time->format($qualification_user['User']['last_touched'], Utils::dateFormatToStrftime('d M, y h:i:s A'), false, $timezone); ?>
					</td>
					<td>
						<?php echo $this->Time->format($qualification_user['QualificationUser']['created'], Utils::dateFormatToStrftime('d M, y h:i:s A'), false, $timezone); ?>
					</td>
					<td>
						<?php echo ($qualification_user['QualificationUser']['notification_timestamp']) ? $this->Time->format($qualification_user['QualificationUser']['notification_timestamp'], Utils::dateFormatToStrftime('d M, y h:i:s A'), false, $timezone) : 'Email not sent'; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<?php echo $this->Element('pagination'); ?>