<h5>
	Survey Issue Report: 
	<?php 
		if ($this->request->data['period'] == 'days') {
			echo 'For '. $this->request->data['date'];	
		}
		elseif ($this->request->data['period'] == 'weeks') {
			echo date(DB_DATE, strtotime($this->request->data['date'].' -6 days')). ' - '. date(DB_DATE, strtotime($this->request->data['date']));
		}
		else {
			echo date("Y-m-01", strtotime($this->request->data['date'])). ' - '. date("Y-m-t", strtotime($this->request->data['date']));
		}
	?>
</h5>
<div class="row-fluid">
	<p class="pull-right"><?php echo $this->Html->link('Export as CSV', array(
		'controller' => 'reports',
		'action' => 'history_request_analytics_by_day', 
		'?' => array(
			'date' => $this->request->data['date'],
			'period' => (isset($this->request->query['period']) && $this->request->query['period']) ? $this->request->query['period'] : 'days',
			'export' => 1
		)
	), array(
		'div' => false,
		'class' => 'btn btn-primary',
		'style' => 'margin-right:5px;',
	)); ?></p>
</div>
<div class="box statistic-data">
	<table cellpadding="0" cellspacing="0" class="table table-normal table-fixed-header">
		<thead class="header">
			<tr>
				<td width="20%">User</td>
				<td>Project #</td>
				<td>Status</td>
				<td>Date</td>
				<td>time to Resolved Issue</td>
				<td></td>
			</tr>
		</thead>
		<tbody>
			<?php if (isset($history_requests) && !empty($history_requests)): ?>
				<?php foreach ($history_requests as $key => $request): ?>
					<tr>
						<td>
							<?php echo $this->Element('user_dropdown', array('user' => $request['User'])); ?>
							<small><?php echo $request['User']['email']; ?></small>
						</td>	
						<td>
							<?php echo $this->Html->link('#'.$request['HistoryRequest']['project_id'], array('controller' => 'surveys', 'action' => 'dashboard', $request['HistoryRequest']['project_id'])); ?>
						</td>
						<td><?php 
							if ($request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_APPROVED) { 
								echo 'Approved';
							} 
							elseif ($request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_REJECTED) { 
								echo 'Rejected';
							}
							else {
								echo 'Pending';
							} ?>
						</td>
						<td>
							<?php echo $this->Time->format($request['HistoryRequest']['created'], Utils::dateFormatToStrftime('Y-m-d h:i A'), false, $timezone); ?>
						</td>
						<td>
							<?php
								if ($request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_APPROVED || $request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_REJECTED) {
									$time_taken = strtotime($request['HistoryRequest']['modified']) - strtotime($request['HistoryRequest']['created']);
									$days = floor($time_taken / (24 * 60 * 60));
									$hours = floor(($time_taken % (24 * 60 * 60)) / (60 * 60));
									$mins = intval(($time_taken / 60) % 60);
									
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
						<td style="white-space: nowrap;"><?php 
							echo $this->Html->link('Search', array(
								'controller' => 'history_requests',
								'action' => 'info', 
								$request['HistoryRequest']['id']
							), array(
								'class' => 'btn btn-default btn-mini',
								'target' => '_blank'
							)); ?> 
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else: ?>
				<td colspan="5"><span class="muted">There are no reports that match your query.</span></td>	
			<?php endif; ?>	
		</tbody>
	</table>
</div>	
<?php echo $this->Element('pagination'); ?>
<?php echo $this->Element('modal_user_quickprofile'); ?>