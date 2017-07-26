<?php echo $this->Form->create(); ?>
<?php if (isset($errors) && !empty($errors)): ?>
	<div class="alert alert-error">
		<?php echo implode('<br />', $errors)?>
	</div>
<?php endif; ?>
<div class="box">
	<div class="box-header">
		<span class="title">Create QE2 Question</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span6">
				<div class="padded">
					<?php echo $this->Form->input('partner', array(
						'type' => 'select', 
						'options' => $partners
					)); ?>
					<?php echo $this->Form->input('partner_question_id', array(
						'type' => 'text', 
						'label' => 'Partner Question ID'
					)); ?>
					<?php echo $this->Form->input('question'); ?>
					<?php echo $this->Form->input('question_type', array(
						'label' => 'Type',
						'type' => 'select',
						'options' => unserialize(QUESTION_TYPES)
					)); ?>
					<?php echo $this->Form->input('behavior', array(
						'label' => 'Behavior (Optional)',
						'after' => 'Any particular special behavior, e.g. "date"'
					)); ?>
					<?php echo $this->Form->input('country', array(
						'after' => '&nbsp; <small>Sets the country of the Question(QuestionText) and answers (AnswerTexts)</small>',
						'type' => 'select',
						'options' => array(
							'US' => 'US',
							'GB' => 'GB',
							'CA' => 'CA',
						)
					)); ?>
					
					<?php echo $this->Form->input('answers', array(
						'rows' => '10', 
						'cols' => '10', 
						'label' => 'Answers', 
						'after' => 'Each on a separate row, with <b>partner_answer_id</b> in the begining followed by "-" e.g<br />1-answer1 text</br />2-answer2 text'
					)); ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Save', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>