<?php echo $this->Form->input('ProfileQuestion.id'); ?>
<?php echo $this->Form->input('ProfileQuestion.type', array(
	'type' => 'select',
	'options' => $question_types,
	'value' => 'radio' // default
)); ?>
<?php echo $this->Form->input('ProfileQuestion.name', array(
	'type' => 'text',
	'label' => 'Question'
)); ?>

<div class="answers">
	<h4>Answers</h4>
	<p><small><?php echo $this->Html->link('Add another answer', '#', array('onclick' => 'return MintVine.AddAnswer(this)')); ?></small></p>
	<table class="table table-striped">
		<?php if (isset($this->data['ProfileAnswer']) && !empty($this->data['ProfileAnswer'])): ?>
			<?php foreach ($this->data['ProfileAnswer'] as $answer): ?>
				<tr>
					<td><?php echo $this->Form->input('ProfileAnswer.existing.'.$answer['id'], array(
						'type' => 'text',
						'label' => false,
						'value' => $answer['name'],
						'div' => false
					)); ?></td>
					<td style="width: 40px;"><small><?php 
						echo $this->Html->link(
							'<i class="icon-trash"></i>', 
							'#', 
							array(
								'tabindex' => -1,
								'escape' => false, 
								'onclick' => 'return MintVine.RemoveExistingAnswer(this, '.$answer['id'].')'
							)
						); 
					?></small></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		<tr>
			<td><?php 
				echo $this->Form->input('answer.', array(
					'div' => false
				)); 
			?></td>
			<td style="width: 40px;"><small><?php 
				echo $this->Html->link(
					'<i class="icon-trash"></i>', 
					'#', 
					array(
						'tabindex' => -1,
						'escape' => false, 
						'onclick' => 'return MintVine.RemoveAnswer(this)'
					)
				); 
			?></small></td>
		</tr>
		<tr class="base" style="display: none;">
			<td><?php 
				echo $this->Form->input('answer.', array(
					'div' => false
				)); 
			?></td>
			<td style="width: 40px;"><small><?php 
				echo $this->Html->link(
					'<i class="icon-trash"></i>', 
					'#', 
					array(
						'tabindex' => -1,
						'escape' => false, 
						'onclick' => 'return MintVine.RemoveAnswer(this)'
					)
				); 
			?></small></td>
		</tr>
	</table>
</div>
