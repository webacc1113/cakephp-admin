<h3>Answer Maps</h3>
<div class="row-fluid">
	<div class="span6">
		<?php echo $this->Html->link('Add new', array('action' => 'add'), array('class' => 'btn btn-success')); ?>
	</div>
	<div class="span6">
		<?php echo $this->Form->create('AnswerMapping', array('url' => array('action' => 'index'), 'type' => 'get', 'class' => 'clearfix form-inline')); ?>
		<div class="user-groups">
			<div class="form-group">
				<?php
				echo $this->Form->input('partner', array(
					'type' => 'select',
					'label' => false,
					'class' => 'select',
					'default' => 'core',
					'options' => $partners,
					'value' => isset($partner) ? $partner : 'core',
				));
				?>
				<?php echo $this->Form->submit('Show', array('class' => 'btn btn-default')); ?>
			</div>
		</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Question</td>
				<td width="25%">Partners mapped to</td>
				<td>Options</td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($questions as $question): ?>
				<tr>
					<td><?php echo $question['Question']['partner'] . ' #' . $question['Question']['partner_question_id'] . ' - ' . $question['QuestionText'][0]['text'] ?></td>
					<td><?php echo implode(', ', $question['Partner']); ?></td>
					<td><?php echo $this->Html->link('Edit', array('action' => 'edit',  $question['Question']['id']), array('class' => 'btn btn-mini btn-default')); ?> </td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php echo $this->Element('pagination'); ?>