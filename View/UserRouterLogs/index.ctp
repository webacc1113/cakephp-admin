<h3>Latest 100 Router Entries</h3>

<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Date</td>
				<td>User ID</td>
				<td>Items Count</td>
				<td>Execution Time</td>
				<td></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($user_router_logs as $user_router_log): ?>
				<tr>
					<td>
						<?php echo $this->Time->format($user_router_log['UserRouterLog']['created'], Utils::dateFormatToStrftime(DB_DATETIME), false, $timezone); ?>
					</td>
					<td>
						<?php echo $this->Html->link('#'.$user_router_log['UserRouterLog']['user_id'], array(
							'controller' => 'users',
							'action' => 'history',
							$user_router_log['UserRouterLog']['user_id']
						)); ?>
					</td>
					<td>
						<?php echo $user_router_log['UserRouterLog']['count']; ?>
					</td>
					<td>
						<?php echo round($user_router_log['UserRouterLog']['time_milliseconds'] / 1000, 4); ?>
					</td>
					<td><?php
						echo $this->Html->link('View', array(
							'controller' => 'user_router_logs',
							'action' => 'view',
							$user_router_log['UserRouterLog']['user_id'],
							$user_router_log['UserRouterLog']['id']
						), array(
							'class' => 'btn btn-sm btn-default'
						)); 
					?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>