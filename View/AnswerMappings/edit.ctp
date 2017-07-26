<h3><?php echo $question['Question']['partner'] . ' #' . $question['Question']['partner_question_id'] . ' - ' . $question['QuestionText'][0]['text']; ?></h3>
<div class="row-fluid">
	<p><?php echo $this->Html->link('New answer map', array('action' => 'add', '?' => array('question_id' => $question['Question']['id'])), array('class' => 'btn btn-success')); ?></p>
	<?php foreach($question['Answer'] as $answer): ?>
		<?php if (empty($answer['AnswerMapping'])): ?>
			<?php continue; ?>
		<?php endif;  ?>
		
		<div class="new">
			<div class="span4">
				<div class="box">
					<div class="box-content">
						<table cellpadding="0" cellspacing="0" class="table table-normal">
							<thead>
								<tr>
									<td>Code</td>
									<td>Answer</td>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td><?php echo $answer['partner_answer_id']?></td>
									<td><?php echo $answer['AnswerText'][0]['text']?></td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
			<div class="span8">
				<div class="box">
					<div class="box-content">
						<table  cellpadding="0" cellspacing="0" class="table table-normal">
							<thead>
								<tr>
									<td>Question</td>
									<td>Code</td>
									<td>Answer</td>
									<td>Options</td>
								</tr>
							</thead>
							<tbody>
							<?php foreach ($answer['AnswerMapping'] as $answer_mapping): ?>
								<tr>
									<td><?php echo $answer_mapping['Answer']['ToQuestion']['partner'].' #'.$answer_mapping['Answer']['ToQuestion']['partner_question_id'].' - '.$answer_mapping['Answer']['ToQuestion']['QuestionText'][0]['text']; ?></td>
									<td><?php echo $answer_mapping['Answer']['partner_answer_id'] ?></td>
									<td><?php echo $answer_mapping['Answer']['AnswerText'][0]['text'] ?></td>
									<td>
										<?php if ($answer_mapping['active']): ?>
											<?php echo $this->Html->link('Active', '#', array('class' => 'btn btn-mini btn-success', 'onclick' => 'return MintVine.ActiveAnswerMapping(' . $answer_mapping['id'] . ', this)')); ?>
										<?php else: ?>
											<?php echo $this->Html->link('Inactive', '#', array('class' => 'btn btn-mini btn-default', 'onclick' => 'return MintVine.ActiveAnswerMapping(' . $answer_mapping['id'] . ', this)')); ?>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	<?php endforeach; ?>
</div>
