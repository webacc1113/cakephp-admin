<?php if ($profile_questions): ?>
	<?php foreach ($profile_questions as $profile_question): ?>
		<div class="block" data-id="<?php echo $profile_question['ProfileQuestion']['id'];?>">
			<p><span class="label label-default"><?php 
				echo $profile_question['Profile']['name']; 
			?></span> <strong><?php 
				echo $profile_question['ProfileQuestion']['name']; 
			?></strong></p>
			
			<?php if (!empty($profile_question['ProfileAnswer'])): ?>
				<?php foreach ($profile_question['ProfileAnswer'] as $profile_answer): ?>
					<?php echo $this->Form->input('profile_answer.'.$profile_answer['id'], array(
						'type' => 'checkbox',
						'hiddenField' => false,
						'label' => $profile_answer['name']
					)); ?>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
<?php else: ?>
	<div class="alert alert-danger">
		No questions found.
	</div>
<?php endif; ?>