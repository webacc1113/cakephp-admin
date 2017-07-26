<h3>Answers</h3>

<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Partner</td>
				<td>Question (Internal)</td>
				<td>Question (Display)</td>
				<td>Type</td>
				<td>Logic Group</td>
				<td>Behavior</td>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><?php echo $question['Question']['partner']; ?> #<?php echo $question['Question']['partner_question_id']; ?></td>
				<td><?php echo $question['Question']['question']; ?></td>
				<td>
					<?php 
					$question_texts = array();
					foreach ($question['QuestionText'] as $question_text) {
						$question_texts[] = $question_text['country'].': '.$question_text['text']; 
					}
					echo implode('<br/>', $question_texts); 
					?>
				</td>
				<td><?php echo $question['Question']['question_type']; ?></td>
				<td><?php echo $question['Question']['logic_group']; ?></td>
				<td><?php echo $question['Question']['behavior']; ?></td>
				</tr>
		</tbody>
	</table>
</div>

<?php echo $this->Form->create(null); ?>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Partner</td>
				<td>Existing</td>
				<td>Answers</td>
				<td>Ignore</td>
			</tr>
		</thead>
		<?php foreach ($question['Answer'] as $answer): ?>
			<tr>
				<td><?php echo $question['Question']['partner']; ?></td>
				<td>
					<?php foreach ($answer['AnswerText'] as $answer_text): ?>
						<p><?php echo $answer_text['country']; ?><br/>
						<?php echo $answer_text['text']; ?></p>
					<?php endforeach; ?>
				</td>
				<td><?php 
					foreach ($answer['AnswerText'] as $answer_text) {
						echo $this->Form->input('AnswerText.'.$answer_text['id'], array(
							'type' => 'text',
							'label' => $answer_text['country'], 
							'value' => $answer_text['text'],
							'required' => true
						)); 
					}
				?></td>
				<td><?php echo $answer['ignore'] ? '<span class="label label-success">Y</label>': '<span class="label label-danger">N</span>'; ?></td>
			</tr>
		<?php endforeach; ?>
	</table>
</div>
<?php echo $this->Form->submit('Save', array('class' => 'btn btn-primary')); ?>
<?php echo $this->Form->end(null); ?>