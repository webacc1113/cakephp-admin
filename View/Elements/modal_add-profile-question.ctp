<?php echo $this->Form->create('ProfileQuestion', array()); ?>
<div id="modal-add-profile-question" class="modal hide">
	<div class="modal-header">
	<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
	<h6 id="modal-tablesLabel">Add Profile Question</h6>
	</div>
	<div class="modal-body">
		<?php echo $this->Form->input('profile_id', array(
			'type' => 'hidden', 
			'value' => $profile['Profile']['id']
		)); ?>	
		<?php echo $this->Form->input('type', array(
			'type' => 'select',
			'options' => $question_types,
			'value' => 'radio' // default
		)); ?>
		<?php echo $this->Form->input('name', array(
			'type' => 'text',
			'label' => 'Question'
		)); ?>
		
		<div class="answers">
			<h4>Answers</h4>
			<p><small><?php echo $this->Html->link('Add another answer', '#', array('onclick' => 'return MintVine.AddAnswer(this)')); ?></small></p>
			<table class="table table-striped">
				<tr>
					<td><?php 
						echo $this->Form->input('answer.', array(
							'div' => false
						)); 
					?></td>
					<td style="width: 40px;"><small><?php 
						echo $this->Html->link(
							'<i class="icon-trash"></i>', 
							'#', array(
								'tabindex' => '-1',
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
							'#', array(
								'tabindex' => '-1',
								'escape' => false, 
								'onclick' => 'return MintVine.RemoveAnswer(this)'
							)
						); 
					?></small></td>
				</tr>
			</table>
		</div>
	</div>
	<div class="modal-footer">
	<button class="btn btn-default" data-dismiss="modal">Cancel</button>
	<button type="button" class="btn btn-primary" onclick="return MintVine.SaveProfileQuestion(this);">Add Question</button>
	</div>
</div>
<?php echo $this->Form->end(null); ?>