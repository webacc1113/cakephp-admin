<div class="padded">
	<?php if (!$good_links): ?>
		<div class="alert alert-error">
			Bad links detected (see below)
		</div>
	<?php endif; ?>

	<div><?php echo $this->Html->link('Download Partner Links', array('action' => 'download_links', $project['Project']['id']), array('class' => 'btn btn-primary btn-small')); ?></div>
	<table cellpadding="0" cellspacing="0" border="0" class="table table-striped">
		<thead>
			<tr>
				<?php if (!is_null($project['Project']['recontact_id'])): ?>
					<th>Partner</th>
					<th>Partner User ID</th>
				<?php endif; ?>
				<th>Link</th>
				<th>Status</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($survey_links as $survey_link) : ?>
				<tr <?php echo $survey_link['SurveyLink']['used'] ? 'class="muted"': ''; ?>>
					<?php if (!is_null($project['Project']['recontact_id'])): ?>
						<?php if (is_null($survey_link['SurveyLink']['partner_id'])): ?>
							<td>MintVine</td>
						<?php else: ?>
							<td><?php echo $survey_link['Partner']['partner_name']; ?></td>
						<?php endif; ?>
						<?php if ($survey_link['SurveyLink']['user_id'] > 0): ?>
							<td><?php echo $survey_link['SurveyLink']['user_id']; ?></td>
						<?php else: ?>
							<td><?php echo $survey_link['SurveyLink']['partner_user_id']; ?></td>
						<?php endif; ?>
					<?php endif; ?>
					<td><?php echo $this->Form->input('link', array(
						'label' => false,
						'disabled' => $survey_link['SurveyLink']['used'],
						'div' => array(
							'class' => false
						),
						'style' => 'width: 320px; margin-bottom: 0px',
						'after' => isset($errors[$survey_link['SurveyLink']['link']]) 
							? '<small class="text-error">'.$errors[$survey_link['SurveyLink']['link']].'</small>'
							: '',
						'value' => $survey_link['SurveyLink']['link']
					)); 
					?></td>
					<td><?php echo !$survey_link['SurveyLink']['used'] ? 'Unused': 'Used'; ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>