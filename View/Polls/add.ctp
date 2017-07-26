<?php echo $this->Form->create(null); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Create New Poll</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span4">
				<div class="padded">
					<?php echo $this->Form->input('poll_question', array('label' => 'Question')); ?>
					<?php echo $this->Form->input('award', array('type' => 'text', 'label' => 'Award', 'default' => '0', 'after' => ' points')); ?>
					<?php
					echo $this->Form->input('publish_date', array(
						'label' => 'Publish Date',
						'class' => 'datepicker',
						'type' => 'text',
						'data-date-autoclose' => true,
					));
					?>
					<?php echo $this->Form->input('answers', array('rows' => '10', 'cols' => '10', 'label' => 'Answers', 'after' => '(each on a separate row)')); ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Save', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>