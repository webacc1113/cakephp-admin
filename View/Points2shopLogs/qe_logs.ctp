<h3>Qe Panelists Logs</h3>
<div class="row-fluid">
	<div class="span6">
		<?php echo $this->Form->create(null, array(
			'class' => 'clearfix form-inline',
			'type' => 'get',
			'url' => array(
				'controller' => 'points2shop_logs',
				'action' => 'qe_logs',
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
				<td>Project ID</td>
				<td>Panelists Count</td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($qe_logs as $qe_log): ?>
				<tr>
					<td><?php echo $qe_log['Points2shopPanelistsLog']['created']; ?></td>
					<td><?php echo $this->Html->link($qe_log['Points2shopPanelistsLog']['project_id'], array('controller' => 'surveys', 'action' => 'dashboard', $qe_log['Points2shopPanelistsLog']['project_id']), array('target' => '_blank')); ?></td>
					<td><?php echo $this->Html->link($qe_log['Points2shopPanelistsLog']['panelists_count'], array('action' => 'qe_log', $qe_log['Points2shopPanelistsLog']['id']), array('target' => '_blank')); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php echo $this->Element('pagination'); ?>