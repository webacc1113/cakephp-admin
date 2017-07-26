
	<?php if ($this->Paginator->counter(array('format' => '{:pages}')) > 1) : ?>
	<div class="pagination">
		<ul>
		<?php
			echo $this->Paginator->prev('&laquo;', 
				array('escape' => false, 'tag' => 'li'), 
				null, 
				array('escape' => false, 'tag' => 'li', 'class' => 'prev disabled', 'disabledTag' => 'a')
			);
			
			echo $this->Paginator->numbers(array(
				'separator' => '',
				'tag' => 'li',
				'currentTag' => 'a',
				'currentClass' => 'active'
			));
			
			echo $this->Paginator->next('&raquo;', 
				array('escape' => false, 'tag' => 'li'), 
				null, 
				array('escape' => false, 'tag' => 'li', 'class' => 'next disabled', 'disabledTag' => 'a')
			);
		?></ul>
	</div>
	<?php endif; ?>
	
	<p><?php
		echo $this->Paginator->counter(array(
		'format' => __('Total: {:count}')
		));
	?></p>