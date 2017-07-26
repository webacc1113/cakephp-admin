<div class="box">
	<div class="box-header">
		<span class="title">Filters</span>
		<ul class="box-toolbar">
			<li>
				<?php echo $this->Html->link('<i class="icon-remove-sign"></i> Clear filters', array('action' => 'history_request_analytics'), array('escape' => false)); ?>
			</li>
		</ul>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create('Report', array('type' => 'get', 'class' => 'filter')); ?>
			<div class="padded separate-sections">					
				<div class="row-fluid">
					<div class="filter">
						<label>View report for last:</label>
						<?php echo $this->Form->input('period_span', array(
							'label' => false,
							'value' => (isset($this->request->query['period_span']) && $this->request->query['period_span']) ? $this->request->query['period_span'] : '7'
						)); ?>
					</div>
					<div class="filter">
						<label>&nbsp;</label>
						<?php echo $this->Form->input('period', array(
							'label' => false,
							'type' => 'select',
							'options' => array(
								'days' => 'Days',
								'weeks' => 'Weeks',
								'months' => 'Months'
							),
							'selected' => (isset($this->request->query['period']) && $this->request->query['period']) ? $this->request->query['period'] : 'days'
						)); ?>
					</div>
				</div>	
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Search', array('class' => 'btn btn-primary')); ?>
			</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>
<div class="row-fluid">
	<p class="pull-right"><?php 
		echo $this->Html->link('Export as CSV', array(
			'action' => 'history_request_analytics',
			'?' => http_build_query($this->request->query) . '&export=1'
		), array(
			'div' => false,
			'class' => 'btn btn-primary',
			'style' => 'margin-right:5px;',
		));
	?></p>
</div>
<?php if (isset($reports) && !empty($reports)): ?>
	<div class="box statistic-data" style="margin-bottom: 0;">
		<table cellpadding="0" cellspacing="0" class="table table-normal table-fixed-header">
			<thead class="header">
				<tr>
					<td></td>
					<td>Reported Issues</td>
					<td>Resolved Issues</td>
					<td>Time to Resolved Issue (Median)</td>
					<td>Total Missing Points Paid Out</td>
					<td style="width: 200px;"></td>
				</tr>
			</thead>
			<tbody>
				<?php if (!empty($reports)): ?>
					<?php
						$total_reported_issues = 0;
						$total_resolved_issues = 0;
						$total_average_time = array();
						$total_paid_points = 0;
					?>
					<?php foreach ($reports as $key => $report): ?>
						<tr>
							<?php
								if (isset($report['HistoryRequestReport']['total_reported_issues'])) {
									$total_reported_issues += $report['HistoryRequestReport']['total_reported_issues'];
								}
								if (isset($report['HistoryRequestReport']['total_resolved_issues'])) {
									$total_resolved_issues += $report['HistoryRequestReport']['total_resolved_issues'];
								}	
								if (isset($report['HistoryRequestReport']['average_time'])) {
									$total_average_time[] = $report['HistoryRequestReport']['average_time'];
								}	
								if (isset($report['HistoryRequestReport']['total_paid_points'])) {
									$total_paid_points += $report['HistoryRequestReport']['total_paid_points'];
								}	
							?>
							<td><?php echo $key;?></td>
							<td><?php echo isset($report['HistoryRequestReport']['total_reported_issues']) ? $report['HistoryRequestReport']['total_reported_issues'] : 'N/A'; ?></td>
							<td><?php echo isset($report['HistoryRequestReport']['total_resolved_issues']) ? $report['HistoryRequestReport']['total_resolved_issues'] : 'N/A'; ?></td>
							<td>
								<?php
									if (isset($report['HistoryRequestReport']['average_time'])) {
										$average_time = $report['HistoryRequestReport']['average_time'];
										$days = floor($average_time / (24 * 60 * 60));
										$hours = floor(($average_time % (24 * 60 * 60)) / (60 * 60));
										$mins = intval(($average_time / 60) % 60);
										
										if ($days) {
											echo $days . ' day' . ( $days > 1 ? 's' : '' ) . ' ';
										}
										if ($hours) {
											echo $hours . ' hour' . ( $hours > 1 ? 's' : '' ) . ' ';
										}	
										if ($mins) {
											echo $mins . ' minute' . ( $mins > 1 ? 's' : '' );
										}
										if (!$days && !$hours && !$mins) {
											echo '0';
										}
									}
									else {
										echo 'N/A';
									}
								?>
							</td>
							<td><?php echo isset($report['HistoryRequestReport']['total_paid_points']) ? number_format($report['HistoryRequestReport']['total_paid_points']) : 'N/A';?></td>
							<td style="white-space: nowrap; text-align: right;"><?php 
								echo $this->Html->link('View Records', array(
									'controller' => 'reports',
									'action' => 'history_request_analytics_by_day', 
									'?' => array(
										'date' => $key,
										'period' => (isset($this->request->query['period']) && $this->request->query['period']) ? $this->request->query['period'] : 'days'
									)
								), array(
									'class' => 'btn btn-default btn-mini',
									'target' => '_blank'
								)); ?>
								<?php 
								echo $this->Html->link('Export Records', array(
									'controller' => 'reports',
									'action' => 'history_request_analytics_by_day', 
									'?' => array(
										'date' => $key,
										'period' => (isset($this->request->query['period']) && $this->request->query['period']) ? $this->request->query['period'] : 'days',
										'export' => true
									)
								), array(
									'class' => 'btn btn-default btn-mini'
								)); ?>
							</td>
						</tr>
					<?php endforeach; ?>
					<tr>
						<td><strong>Sum:</strong></td>
						<td><strong><?php echo $total_reported_issues;?></strong></td>
						<td><strong><?php echo $total_resolved_issues;?></strong></td>
						<td><strong>
							<?php
								
								$average_time = Utils::calculate_median($total_average_time);
								$days    = floor($average_time / (24 * 60 * 60));
								$hours   = floor(($average_time % (24 * 60 * 60)) / (60 * 60));
								$mins = intval(($average_time / 60) % 60);
								
								if ($days) {
									echo $days . ' day' . ( $days > 1 ? 's' : '' ) . ' ';
								}
								if ($hours) {
									echo $hours . ' hour' . ( $hours > 1 ? 's' : '' ) . ' ';
								}	
								if ($mins) {
									echo $mins . ' minute' . ( $mins > 1 ? 's' : '' );
								}
								if (!$days && !$hours && !$mins) {
									echo '0';
								}
								
							?>
						</strong></td>
						<td><strong><?php echo number_format($total_paid_points);?></strong></td>
						<td></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>	
<?php endif; ?>