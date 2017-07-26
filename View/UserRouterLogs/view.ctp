<h3><?php echo __('User Logs')?></h3>
<p class="text-center">
	<?php if (!empty($next_log)) : ?>
		<?php 
			echo $this->Html->link(__('Previous'), array(
				'controller' => 'user_router_logs',
				'action' => 'view',
				$user['User']['id'],
				$next_log['UserRouterLog']['id']
			));
		?>
	<?php endif; ?>
		
	<?php echo (!empty($prev_log) && !empty($next_log)) ? ' | ' : '';?>
	
	<?php if (!empty($prev_log)) : ?>
		<?php
			echo $this->Html->link(__('Next'), array(
				'controller' => 'user_router_logs',
				'action' => 'view',
				$user['User']['id'],
				$prev_log['UserRouterLog']['id']
			));
		?>
	<?php endif; ?>	
</p>
<div class="row-fluid">
	<div class="span3">
		<?php echo $this->Element('user_router_log', array(
			'type' => 'next log',
			'log' => $next_log
		));?>
	</div>
	<div class="span6">
		<?php if (!empty($current_log)) : ?>
			<div class="box">
				<div class="padded">
					<span class="muted">Taken : <?php 
						echo $this->Time->format($current_log['UserRouterLog']['created'], Utils::dateFormatToStrftime('F j h:i A'), false, $timezone); 
					?>
					<?php if (!empty($current_log['UserRouterLog']['time_milliseconds'])): ?>
						 Execution Time: <?php echo round($current_log['UserRouterLog']['time_milliseconds'] / 1000, 4).' seconds'; ?>
					<?php endif; ?>
					</span>
					<h4><?php 
							echo $this->Html->link($current_log['UserRouterLog']['survey_id'], array(
								'controller' => 'surveys',
								'action' => 'dashboard',
								$current_log['UserRouterLog']['survey_id']
							));
						?> 
						<?php 
							echo $current_log['Project']['prj_name'];
						?> -
						<?php 
							echo !empty($current_log['Project']['Client']['client_name']) ? $current_log['Project']['Client']['client_name'] : '';
						?>
					</h4>
					<h5><span class="muted">
						CPI: <?php echo $current_log['UserRouterLog']['cpi'];?>,
						IR: <?php echo !empty($current_log['UserRouterLog']['ir']) ? $current_log['UserRouterLog']['ir'] . '%' : '&nbsp;';?>,
						LOI: <?php echo $current_log['UserRouterLog']['loi'];?>
					</span></h5>
					<h6>
						Score: <?php echo $current_log['UserRouterLog']['score'];?>,
						EPC: <?php echo round($current_log['UserRouterLog']['epc'] / 100, 2);?>,
						EPCM: <?php echo round($current_log['UserRouterLog']['epcm'] / 100, 2);?>
					</h6>
				</div>
			</div>
			<div class="box">
				<table cellpadding="0" cellspacing="0" class="table table-normal">
					<thead>
						<tr>
							<td>Other Surveys</td>
							<td>Group</td>
							<td>Score</td>
							<td>IR</td>
							<td>LOI</td>
							<td>EPC</td>
							<td>EPCM</td>
						</tr>
					</thead>
					<tbody><?php
						if (!empty($current_log['ChildLog'])) :
							foreach ($current_log['ChildLog'] as $child_log) :?>
								<tr>
									<td><?php 
										echo $this->Html->link($child_log['survey_id'], array(
											'controller' => 'surveys',
											'action' => 'dashboard',
											$child_log['survey_id']
										));
										echo !empty($child_log['client_name']) ? ' - ' . $child_log['client_name'] : '';
									?>
									</td>
									<td><?php echo $child_log['group_name'];?></td>
									<td><?php echo $child_log['score'];?></td>
									<td><?php echo !empty($child_log['ir']) ? $child_log['ir'] . '%' : '';?></td>
									<td><?php echo $child_log['loi'];?></td>
									<td><?php echo round($child_log['epc'] / 100, 2);?></td>
									<td><?php echo round($child_log['epcm'] / 100, 2);?></td>
								</tr>
							<?php
							endforeach;
						endif;
						?>
					</tbody>
				</table>
			</div>
		<?php else: ?>
			<h4><?php echo __('No log found.');?></h4>
		<?php endif; ?>
	</div>
	<div class="span3">
		<?php echo $this->Element('user_router_log', array(
			'type' => 'previous log',
			'log' => $prev_log
		));?>
	</div>
</div>
<div class="clearfix"></div>