<div class="span4">
	<div class="box">
		<div class="box-header">
			<span class="title">Export Resurrected Users</span>
		</div>
		<div class="form-actions">
			<p>Here you can export all the resurrected users till now, to add to Customer.io </p>
			<?php echo $this->Html->link('Export', array(
					'controller' => 'reports',
					'action' => 'resurrected_users',
					true
				),
				array(
				'class' => 'btn btn-sm btn-primary',
			)); ?>
		</div>
	</div>
</div>