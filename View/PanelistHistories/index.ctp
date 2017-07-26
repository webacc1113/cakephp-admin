
<div class="box">
	<div class="padded">
		<?php echo $this->Form->create('PanelistHistory', array('type' => 'get', 'url' => array('controller' => 'panelist_histories', 'action' => 'user'))); ?>
			<?php echo $this->Form->input('user_id', array(
					'type' => 'text',
					'label' => 'User ID'
				));?>
			<?php echo $this->Form->submit('Search', array(
				'class' => 'btn btn-primary',
			)); ?>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>