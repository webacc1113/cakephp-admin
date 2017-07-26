<table cellpadding="0" cellspacing="0" class="table table-normal">
	<tr>
		<td width="50%" style="vertical-align: top;">
			<h4>"Points2shop API" pull</h4>
			<?php if (!empty($p2s_qualifications)): ?>
				<dl>
				<?php foreach($p2s_qualifications as $qualification): ?>
					<dt><?php echo $qualification['QuestionText']['text'];?></dt>
					<?php if (!empty($qualification['Answer'])): ?>
						<dd>
						<?php foreach($qualification['Answer'] as $answer): ?>
							<?php echo $answer['AnswerText']['text']; ?><br />
						<?php endforeach; ?>
						</dd>
					<?php endif; ?>
				<?php endforeach; ?>
				</dl>
			<?php endif; ?>

			<h4>SurveyQuotas</h4>
			<?php if (!empty($quotas)): ?>
				<?php foreach ($quotas as $key => $quota): ?>
					<?php if (!empty($quota)): ?>
						<h5>Qualification for Quota id: <?php echo $key; ?></h5>
						<dl>
							<?php foreach ($quota as $qualification): ?>
								<dt><?php echo $qualification['QuestionText']['text']; ?></dt>
								<?php if (!empty($qualification['Answer'])): ?>
									<dd>
										<?php foreach ($qualification['Answer'] as $answer): ?>
											<?php echo $answer['AnswerText']['text']; ?><br />
										<?php endforeach; ?>
									</dd>
								<?php endif; ?>
							<?php endforeach; ?>
						</dl>
					<?php endif; ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</td>
		<td width="50%" style="vertical-align: top;">
			<h4>Survey Qualifications</h4>
			<?php if (!empty($qualifications)): ?>
				<dl>
				<?php foreach($qualifications as $qualification): ?>
					<dt><?php echo (isset($qualification['QuestionText']['text'])) ? $qualification['QuestionText']['text'] : '';?></dt>
					<?php if (!empty($qualification['Answer'])): ?>
						<dd>
						<?php foreach($qualification['Answer'] as $answer): ?>
							<?php echo (isset($answer['AnswerText']['text'])) ? $answer['AnswerText']['text'] : ''; ?><br />
						<?php endforeach; ?>
						</dd>
					<?php endif; ?>
				<?php endforeach; ?>
				</dl>
			<?php endif; ?>
			<h4>Qualification json</h4>
			<?php if (!$query_json): ?>
				<div class="alert alert-error">This query was misformatted and could not be displayed.</div>
			<?php else: ?>
				<textarea rows="10"><?php echo $query_json; ?></textarea>
			<?php endif; ?>
		</td>
	</tr>
</table>	