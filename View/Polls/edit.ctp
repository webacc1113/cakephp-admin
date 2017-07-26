<?php echo $this->Form->create(null); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Edit Poll</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span4">
				<div class="padded">
					<?php $answers_arr = array(); ?>
					<?php $answers = ''; ?>
					<?php foreach ($this->request->data['PollAnswer'] as $anwser): ?>
						<?php $answers_arr[] = $anwser['answer']; ?>
					<?php endforeach;?>
					<?php if ($answers_arr): ?>
						<?php $answers = implode("\r\n", $answers_arr); ?>
					<?php endif; ?>
					<?php echo $this->Form->input('poll_question', array('label' => 'Question')); ?>
					<?php echo $this->Form->input('award', array('type' => 'text', 'label' => 'Award', 'after' => ' points')); ?>
					<?php
					echo $this->Form->input('publish_date', array(
						'label' => 'Publish Date',
						'class' => 'datepicker',
						'type' => 'text',
						'data-date-autoclose' => true,
						'value' => isset($this->request->data['Poll']['publish_date']) ? date('m/d/Y', strtotime($this->request->data['Poll']['publish_date'])) : date('m/d/Y', mktime(0, 0, 0, date('m'), date('d'), date('Y')))
					));
					?>
					<?php echo $this->Form->input('answers', array('rows' => '10', 'cols' => '10', 'label' => 'Answers', 'default' => $answers, 'after' => '(each on a separate row)')); ?>
					<?php echo $this->Form->input('id', array('type' => 'hidden')); ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Save', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>