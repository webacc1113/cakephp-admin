<div class="box">
	<div class="box-header">
		<span class="title">Affiliate Networks</span>
		<ul class="box-toolbar">
			<li><?php echo $this->Html->link('Create Campaign', array('action' => 'add'), array('class' => 'btn btn-small btn-primary')); ?></li>
			<li><?php if ($active == true) : ?>
				<?php echo $this->Html->link('Deactivated Campaigns', array('action' => 'index', '?' => array('deactivated' => 1)), array('class' => 'btn btn-small btn-danger')); ?>
			<?php else : ?>
				<?php echo $this->Html->link('Active Campaigns', array('action' => 'index'), array('class' => 'btn btn-small btn-success')); ?>
			<?php endif;?></li>
		</ul>
	</div>
	<div class="box-content">
		<table class="table table-normal">
			<tr>
				<th>Campaign Name</th>
				<th>Affiliate</th>
				<th>Lander</th>
				<th>URL</th>
				<th>Internal Name</th>
				<th></th>
			</tr>
			<?php foreach ($sources as $source) : ?>
				<tr>
					<td><?php echo $source['Source']['name']; ?></td>
					<td><?php echo $source['AcquisitionPartner']['name']; ?></td>
					<td>
						<?php if (!empty($source['Source']['lander_url_id'])): ?>
							<?php echo $source['LanderUrl']['name']; ?>		
						<?php endif; ?>
					</td>
					<td>
						<?php if (!empty($source['Source']['lander_url_id'])): ?>						
							<?php echo $this->Form->input('url', array(
								'label' => false,
								'style' => 'width: 400px',
								'type' => 'text',
								'value' => MintVine::construct_lander_url($source),
								'div' => false
							)); ?>
						<?php else: ?>
							Not a lander source
						<?php endif; ?>
					</td>
					<td class="muted"><?php echo $source['Source']['abbr']; ?></td>
					<td><?php 
						echo $this->Html->link('Export', array('action' => 'export', $source['Source']['id']), array('class' => 'btn btn-small btn-default')); 
					?> <?php
							echo $this->Html->link('Reports', array('controller' => 'sources', 'action' => 'reports', $source['Source']['id']), array('class' => 'btn btn-small btn-default', 'title' => 'Download')); 						
					?> <?php 
						echo $this->Html->link('Edit', array('action' => 'edit', $source['Source']['id']), array('class' => 'btn btn-small btn-default')); 
					?> <?php if ($active == true) : ?>
							<?php echo $this->Html->link('Deactivate', array('action' => 'deactivate', $source['Source']['id']), array('class' => 'btn btn-small btn-danger'));?>
						<?php else : ?>
							<?php echo $this->Html->link('Reactivate', array('action' => 'activate', $source['Source']['id']), array('class' => 'btn btn-small btn-success'));?>
						<?php endif; ?>	
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
	</div>
</div>