<?php echo $this->Form->create('LanderUrl'); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Lander URLs</span>
		<ul class="box-toolbar">
			<li><?php echo $this->Html->link('Create URL', array('action' => 'add'), array('class' => 'btn btn-small btn-primary')); ?></li>
		</ul>
	</div>
	<div class="box-content">
		<table class="table table-normal">
			<tr>
				<?php if (!empty($lander_urls)): ?>
				<td class="checkbox"><?php
					echo $this->Form->input('null', array(
						'type' => 'checkbox',
						'label' => false,
						'onclick' => 'return toggleChecked(this.checked)'
					));
				?></td>
				<?php endif; ?>
				<th>Name</th>
				<th>Note</th>
				<th>Path</th>
				<th></th>
			</tr>
			<?php foreach ($lander_urls as $lander_url) : ?>
				<tr>
					<td class="checkbox"><?php
						echo $this->Form->input('LanderUrl.' . $lander_url['LanderUrl']['id'], array(
							'label' => false,
							'type' => 'checkbox'
						));
					?></td>	
					<td><?php echo $lander_url['LanderUrl']['name']; ?></td>
					<td><?php echo nl2br($lander_url['LanderUrl']['description']); ?></td>
					<td><?php echo $this->Html->link(
						$lander_url['LanderUrl']['path'], 
						HOSTNAME_WWW.$lander_url['LanderUrl']['path'],
						array('target' => '_blank')
					); ?></td>
					<td><?php echo $this->Html->link('Edit', array('action' => 'edit', $lander_url['LanderUrl']['id']), array('class' => 'btn btn-small btn-default')); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
		<div class="form-actions">
			<?php if (!empty($lander_url)): ?>
				<?php echo $this->Form->submit('Delete', array(
					'name' => 'delete',
					'class' => 'btn btn-danger',
					'rel' => 'tooltip',
					'data-original-title' => 'Clicking this button will delete the selected records, This is IRREVERSIBLE.',
				));
				?>
			<?php endif; ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>