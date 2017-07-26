<div class="box">
	<div class="box-header">
		<span class="title">Missing completes</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span12 padded">
				<?php $reconcile_types = unserialize(RECONCILE_TYPES); ?>
				<?php foreach ($reconcile_types as $key => $type): ?>
					<?php echo $this->Html->link($type, array(
							'controller' => 'reconciliations',
							'action' => 'reconcile_'.$key,
						), array(
							'class' => 'btn btn-default'
						));
					?>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>