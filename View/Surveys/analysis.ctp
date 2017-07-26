<h2>Run Analysis on Completes</h2>

<?php if (isset($visits)): ?>
	
	<?php $STATUSES = unserialize(SURVEY_STATUSES); ?>
	<?php $GENDERS = unserialize(USER_GENDERS); ?>
	<div class="box">
		<table cellpadding="0" cellspacing="0" class="table table-normal">
			<thead>
				<tr>
					<td>Hash</td>
					<td>Partner</td>
					<td>IP</td>
					<td>Started</td>
					<td>Completed</td>
					<td>Time Spent</td>
					<td>Flagged</td>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($visits as $visit) : ?>
					<tr>
						<td><?php echo $visit['SurveyReport']['hash']; ?></td>
						<td><?php echo $visit['Partner']['partner_name']; ?></td>
						<td><?php echo $visit['SurveyReport']['ip']; ?></td>
						<td><?php
							echo $visit['SurveyReport']['started']; 
						?></td>
						<td><?php
							echo $visit['SurveyReport']['completed']; 
						?></td>
						<td><?php
							if (!empty($visit['SurveyReport']['started']) && !empty($visit['SurveyReport']['completed'])) {
								$diff = strtotime($visit['SurveyReport']['completed']) - strtotime($visit['SurveyReport']['started']);
								$minutes = round($diff / 60, 1); 
								echo $minutes.' minutes';
							}
						?></td>
						<td>
							<?php echo $visit['SurveyReport']['flagged'] == 1 ? '1': '-'; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php else: ?>

<p class="lead">This will run an analysis on complete data for this particular survey.</p>
<p>We currently check against: </p>
<ul>
	<li>Survey speed</li>
	<li>Duplicate survey IDs</li>
</ul>

<p class="text-error"><strong>This must be run after an "internal report" has been generated from the old control panel.</strong></p>
<p>Note: this process can take a while.</p>

<?php echo $this->Form->create(); ?>
<?php echo $this->Form->submit('Analyze', array('class' => 'btn btn-primary')); ?>
<?php echo $this->Form->end(null); ?>

<?php endif; ?>