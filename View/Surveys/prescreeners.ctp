<?php echo $this->Form->create('Prescreener'); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Prescreener Questions</span>
	</div>
	<div class="box-content padded">
		<?php 
			echo $this->Form->input('prescreen_type', array(
				'label' => 'Prescreener Type',
				'options' => unserialize(PRESCREEN_TYPE_OPTIONS)
			));	
		?> 
		<p><strong>Instructions</strong>: For each answer box, input a single answer per line. To disqualify a user who answers that question, prefix the answer with <span class="label label-red">[x]</span>.</p>	
		<table cellpadding="0" cellspacing="0" class="table table-striped" id="prescreeners">
			<thead>
				<tr>
					<td>Question</td>
					<td>Answers</td>
				</tr>
			</thead>
			<tbody>
				<?php if (isset($this->request->data['Prescreener']) && !empty($this->request->data['Prescreener'])): ?>
					<?php foreach ($this->request->data['Prescreener']['question'] as $key => $question): ?>
						<tr>
							<td><?php echo $this->Form->input('question.', array(
									'value' => $question,
									'required' => false,
									'id' => false,
									'div' => array(
										'class' => ''
									)
								)); ?>
								<small><?php echo $this->Html->link('Remove Question', '#', array(
									'onclick' => 'return MintVine.RemoveQuestion(this)',
									'tabindex' => '-1',
								)); ?></small></td>
							<td><?php echo $this->Form->input('answers.', array(
								'value' => $this->request->data['Prescreener']['answers'][$key],
								'type' => 'textarea',
								'required' => false,
								'id' => false
							)); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else: ?>
				<tr>
					<td><?php echo $this->Form->input('question.', array(
							'required' => false,
							'id' => false,
						)); ?>
						<?php echo $this->Html->link('Remove Question', '#', array(
							'onclick' => 'return MintVine.RemoveQuestion(this)'
						)); ?></td>
					<td><?php echo $this->Form->input('answers.', array(
						'type' => 'textarea',
						'required' => false,
						'id' => false
					)); ?></td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<?php echo $this->Html->link('+ Add row', '#', array(
			'onclick' => 'return MintVine.AddQuestion(this)'
		)); ?>
	</div>
	<div class="form-actions">
		<?php echo $this->Form->submit('Save Prescreeners', array('class' => 'btn btn-primary')); ?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>