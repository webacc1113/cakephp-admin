<div class="row-fluid">
	<div class="span6">
		<?php echo $this->Form->create(null, array(
			'class' => 'clearfix form-inline',
			'type' => 'get',
			'url' => array(
				'controller' => 'points2shop_session_logs',
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
			<?php
			echo $this->Form->input('user_id', array(
				'label' => false,
				'type' => 'text',
				'placeholder' => 'User #',
				'value' => isset($this->request->query['user_id']) ? $this->request->query['user_id'] : null
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
				<td>User ID</td>
				<td>Project ID</td>
				<td>Requested URL</td>
				<td>Filtered Values</td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($points2shop_session_logs as $points2shop_session_log): ?>
				<tr>
					<td valign="top"><?php echo $this->Html->link($points2shop_session_log['Points2shopSessionLog']['created'], array('action' => 'detail', $points2shop_session_log['Points2shopSessionLog']['id']), array('target' => '_blank')); ?></td>
					<td valign="top"><?php echo $points2shop_session_log['Points2shopSessionLog']['user_id']; ?></td>
					<td valign="top">
						<?php if (!empty($points2shop_session_log['Points2shopSessionLog']['project_id'])) : ?>
							<?php echo $this->Html->link($points2shop_session_log['Points2shopSessionLog']['project_id'], array('controller' => 'surveys', 'action' => 'dashboard', $points2shop_session_log['Points2shopSessionLog']['project_id']), array('target' => '_blank')); ?>
						<?php endif; ?>
					</td>
					<td><input type="text" value="<?php echo $points2shop_session_log['Points2shopSessionLog']['requested_url']; ?>" /></td>
					<td valign="top"><?php 
						$filter_values = json_decode($points2shop_session_log['Points2shopSessionLog']['filtered_values'], true);
						if ($filter_values) {
							foreach ($filter_values as $stored_project_id => $filter_value) {
								if ($stored_project_id{0} == '#' || isset($project_ids[$stored_project_id])) {
									if ($stored_project_id{0} == '#') {
										$project_id = substr($stored_project_id, 1, strlen($stored_project_id)); 
									}
									else {
										$project_id = $project_ids[$stored_project_id];
									}
									echo $this->Html->link('#'.$project_id, array(
										'controller' => 'surveys',
										'action' => 'dashboard', 
										$project_id
									), array('target' => '_blank')); 
									echo ' (#S'.$stored_project_id.')';
								}
								else {
									echo $project_id; 
								}
								echo ' -> ' . $filter_value . '<br />';
							}
						}
						?>						
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php echo $this->Element('pagination'); ?>