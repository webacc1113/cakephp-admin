<?php if (!empty($log)) : ?>
	<div class="box">
		<div class="padded">
			<span class="muted">
				Taken : <?php echo $this->Time->format($log['UserRouterLog']['created'], Utils::dateFormatToStrftime('F j h:i A'), false, $timezone); ?>
			</span>
			<h5><?php 
					echo $this->Html->link($log['UserRouterLog']['survey_id'], array(
						'controller' => 'surveys',
						'action' => 'dashboard',
						$log['UserRouterLog']['survey_id']
					));
				?> 
				<?php 
					echo !empty($log['Project']['Client']['client_name']) ? $log['Project']['Client']['client_name'] : '';
				?>
			</h5>
			<h5><span class="muted">
				CPI: <?php echo $log['UserRouterLog']['cpi'];?>,
				IR: <?php echo !empty($log['UserRouterLog']['ir']) ? $log['UserRouterLog']['ir'] . '%' : '&nbsp;';?>,
				LOI: <?php echo $log['UserRouterLog']['loi'];?>
			</span></h5>
			<h6>
				Score: <?php echo $log['UserRouterLog']['score'];?>,
				EPC: <?php echo round($log['UserRouterLog']['epc'] / 100, 2);?>,
				EPCM: <?php echo round($log['UserRouterLog']['epcm'] / 100, 2);?>
			</h6>
		</div>
	</div>
	<div class="box">
		<table cellpadding="0" cellspacing="0" class="table table-normal">
			<thead>
				<tr>
					<td>Other Surveys</td>
					<td>Score</td>
					<td>IR</td>
					<td>LOI</td>
					<td>EPC</td>
				</tr>
			</thead>
			<tbody><?php
				if (!empty($log['ChildLog'])) :
					foreach ($log['ChildLog'] as $child_log) :?>
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
							<td><?php echo $child_log['score'];?></td>
							<td><?php echo !empty($child_log['ir']) ? $child_log['ir'] . '%' : '&nbsp;';?></td>
							<td><?php echo $child_log['loi'];?></td>
							<td><?php echo round($child_log['epc'] / 100, 2);?></td>
						</tr>
					<?php
					endforeach;
				endif;
				?>
			</tbody>
		</table>
	</div>
<?php else: ?>
	<h4><?php echo __('No ' . $type . ' found.');?></h4>
<?php endif; ?>