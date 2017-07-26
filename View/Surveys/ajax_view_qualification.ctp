<div class="padded" id="qualification_info_view">
	<div class="row-fluid">
		<div class="span6">
			<h4 style="margin-top: 0;"><?php echo $qualification_name; ?></h4>
			<?php if ($partner == 'mintvine'): ?>
				<?php if (isset($json) && count($json['qualifications']) > 0): ?>
					<?php foreach ($json['qualifications'] as $question => $answers): ?>
						<div class="question_line"><?php echo ucfirst(str_replace('_', ' ', $question)) ?></div>
						<?php if ($question == 'birthdate') : ?>
							<div class="answer-line"><?php echo $answers[0] . ' - ' . $answers[count($answers) - 1]; ?></div>
						<?php elseif ($question == 'postal_code'): ?>
							<div class="answer-line"><?php echo implode(', ', $answers)?></div>
						<?php else: ?>
							<?php foreach ($answers as $answer) : ?>
								<div class="answer-line"><?php echo $answer; ?></div>
							<?php endforeach; ?>
						<?php endif; ?>
					<?php endforeach; ?>
				<?php else: ?>
					<div class="alert alert-warning">Qualifications not found!</div>
				<?php endif; ?>
			<?php else: ?>
				<?php foreach ($qualifications as $qualification) : ?>
					<?php if (count($qualification) > 0) : ?>
						<div class="question_line"><?php echo $qualification['QuestionText']['text']?></div>
						<?php foreach ($qualification['Answer'] as $answer) : ?>
							<div class="answer-line"><?php echo $answer['AnswerText']['text']; ?></div>
						<?php endforeach; ?>
					<?php endif; ?>
				<?php endforeach; ?>
			<?php endif; ?>
			<?php if (count($additional_info) > 0): ?>
				<hr />
				<?php foreach ($additional_info as $key => $value): ?>
					<?php if (!empty($value['answers'])): ?>
						<div class="question_line"><?php echo $value['label']?></div>
						<?php foreach ($value['answers'] as $answer): ?>
							<div class="answer-line"><?php echo $answer; ?></div>
						<?php endforeach; ?>
					<?php endif; ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
		<div class="span6">
			<h4 style="margin-top: 0;">Qualification json</h4>
			<?php if (!$json) : ?>
				<div class="alert alert-error">This query was misformatted and could not be displayed.</div>
			<?php else: ?>
				<textarea rows="12"><?php print_r($json); ?></textarea>
			<?php endif; ?>
		</div>
	</div>
</div>