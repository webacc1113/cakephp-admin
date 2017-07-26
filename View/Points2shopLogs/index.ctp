<div class="row-fluid">
	<?php echo $this->element('nav_api_logs', array('nav' => 'points2shop')); ?>
</div>
<p>
	<?php echo $this->Html->link('US', array('?' => array('country' => 'US'))); ?> | 
	<?php echo $this->Html->link('CA', array('?' => array('country' => 'CA'))); ?> |
	<?php echo $this->Html->link('GB', array('?' => array('country' => 'GB'))); ?> 
</p>
<div class="row-fluid">
	<div class="span6">
		<?php echo $this->Form->create(null, array(
			'class' => 'clearfix form-inline',
			'type' => 'get',
			'url' => array(
				'controller' => 'points2shop_logs',
				'action' => 'index',
			),
		));
		?>
		<div class="form-group">
			<?php
			echo $this->Form->input('project_id', array(
				'label' => false,
				'type' => 'text',
				'placeholder' => 'Project #',
				'value' => isset($this->request->query['project_id']) ? $this->request->query['project_id'] : null
			));
			?>
		</div>
		<div class="form-group">
		<?php echo $this->Form->submit('Search', array('class' => 'btn btn-default')); ?>
		</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td width="130px">Created</td>
				<td>Country</td>
				<td>P2s Project ID</td>
				<td>Remaining Completes</td>
				<td>Loi</td>
				<td>Conversion Rate</td>
				<td>Cpi</td>
				<td>Entry Link</td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($points2shop_logs as $points2shop_log): ?>
				<tr>
					<td><?php echo $points2shop_log['Points2shopLog']['created']; ?></td>
					<td><?php echo $points2shop_log['Points2shopLog']['country']; ?></td>
					<td><?php echo $this->Html->link($points2shop_log['Points2shopLog']['p2s_project_id'], array('action' => 'search', '?' => array('project_id' => $points2shop_log['Points2shopLog']['p2s_project_id'])), array('target' => '_blank')); ?></td>
					<td><?php echo number_format($points2shop_log['Points2shopLog']['remaining_completes']); ?></td>
					<td><?php echo $points2shop_log['Points2shopLog']['loi']; ?></td>
					<td><?php echo $points2shop_log['Points2shopLog']['conversion_rate']; ?></td>
					<td><?php echo $points2shop_log['Points2shopLog']['cpi']; ?></td>
					<td><?php echo $points2shop_log['Points2shopLog']['entry_link']; ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php echo $this->Element('pagination'); ?>