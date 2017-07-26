<div class="user-groups">
	<?php if (isset($groups)): ?>
		<div class="form-group">
			<?php
			
			if (isset($this->request->query['group_id'])) {
				$selected_id = $this->request->query['group_id']; 
			}
			elseif (isset($mintvine_group)) {
				$selected_id = $mintvine_group['Group']['id'];
			}
			else {
				$selected_id = null;
			}
			echo $this->Form->input('group_id', array(
				'label' => false,
				'value' => $selected_id
			));
			?>
			<?php echo $this->Form->submit('Show', array('class' => 'btn btn-default')); ?>
		</div>
	<?php endif; ?>
</div>