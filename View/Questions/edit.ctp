<?php echo $this->Form->create(null); ?>
	<div class="box">
		<table cellpadding="0" cellspacing="0" class="table table-normal">
			<colgroup>
				<col width="200" />
				<col width="40%" />
				<col width="40%" />
			</colgroup>
			<thead>
				<tr>
					<td>Question Text</td>
					<td>Panelist Question</td>
					<td>PM Question (Shortened; leave blank to keep same)</td>
				</tr>
			</thead>
			<?php foreach ($question['QuestionText'] as $question_text): ?>
				<tr>
					<td><?php echo $question['Question']['question']; ?> (<?php echo $question['Question']['question_type']; ?>)</td>
					<td><?php 
						echo $this->Form->input('QuestionText.text.'.$question_text['id'], array(
							'label' => $question_text['country'],
							'value' => $question_text['text']
						)); 
					?></td><td><?php 
						echo $this->Form->input('QuestionText.cp_text.'.$question_text['id'], array(
							'label' => $question_text['country'],
							'value' => $question_text['cp_text']
						)); 
					?></td>
				</tr>
			<?php endforeach; ?>
		</table>
	</div>
<?php echo $this->Form->submit('Save', array('class' => 'btn btn-primary')); ?>
<?php echo $this->Form->end(null); ?>