<div class="box">
	<div class="box-header">

		<div class="pull-right" style="margin: 6px 6px 0 0;">
			<?php if (!isset($this->request->query['all']) || $this->request->query['all'] == false): ?>
				<?php echo $this->Html->link('Show project record updates', array('action' => 'project_logs', $this->request->params['pass'][0], '?' => array('all' => true)), array('class' => 'btn btn-primary btn-small')) ?>
			<?php else: ?>
				<?php echo $this->Html->link('Hide project record updates', array('action' => 'project_logs', $this->request->params['pass'][0]), array('class' => 'btn btn-primary btn-small')) ?>
			<?php endif; ?>
		</div>
		
		<span class="title">
			<?php echo (!isset($this->request->query['all']) || $this->request->query['all'] == false) ? 'Project Logs ( Updated only )' : 'All Project Logs' ?>
		</span>
	</div>
	<div class="box-content">
		<?php if ($project_logs): ?>
			<table class="table table-normal">
				<tr>
					<th>Type</th>
					<th>Description</th>
					<th>User</th>
					<th>Failed Rule</th>
					<th>Failed Values</th>
					<th>Action Taken</th>
				</tr>
				<?php foreach ($project_logs as $project_log): ?>
					<?php echo $this->Element('row_project_log', array('project_log' => $project_log)); ?>
				<?php endforeach; ?>
			</table>
		<?php endif; ?>
		<div class="form-actions">
			<?php echo $this->Element('pagination'); ?>
		</div>
	</div>
</div>
<?php echo $this->Element('modal_project_log'); ?>