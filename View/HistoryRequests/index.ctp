<h3>Project Missing Points</h3>

<p class="count">Showing <?php 
	echo number_format($this->Paginator->counter(array('format' => '{:current}')));
?> of <?php
	echo number_format($this->Paginator->counter(array('format' => '{:count}')));
?></p>
<?php $REPORTS = unserialize(SURVEY_REPORT_TYPES); ?>
<div class="row-fluid" style="margin-bottom: 10px;">
	<div class="span6"><?php
		echo $this->Html->link(
			'All Requests', 
			array(
				'action' => 'index', 
				'?' => array(
					'status' => 'all',
					'project_id' => (isset($this->request->query['project_id']) && !empty($this->request->query['project_id'])) ? $this->request->query['project_id'] : null,
					'user_id' => (isset($this->request->query['user_id']) && !empty($this->request->query['user_id'])) ? $this->request->query['user_id'] : null
				)
			), 
			array(
				'class' => 'btn btn-' . (($status_filter == 'all') ? 'primary' : 'default')
			)
		);
		?> <?php 
		echo $this->Html->link(
			'Pending', 
			array(
				'action' => 'index', 
				'?' => array(
					'status' => SURVEY_REPORT_REQUEST_PENDING,
					'project_id' => (isset($this->request->query['project_id']) && !empty($this->request->query['project_id'])) ? $this->request->query['project_id'] : null,
					'user_id' => (isset($this->request->query['user_id']) && !empty($this->request->query['user_id'])) ? $this->request->query['user_id'] : null
				)
			), 
			array(
				'class' => 'btn btn-' . (($status_filter == SURVEY_REPORT_REQUEST_PENDING && $status_filter != 'all') ? 'primary' : 'default')
			)
		); ?> <?php 
		echo $this->Html->link(
			'Approved', 
			array(
				'action' => 'index', 
				'?' => array(
					'status' => SURVEY_REPORT_REQUEST_APPROVED,
					'project_id' => (isset($this->request->query['project_id']) && !empty($this->request->query['project_id'])) ? $this->request->query['project_id'] : null,
					'user_id' => (isset($this->request->query['user_id']) && !empty($this->request->query['user_id'])) ? $this->request->query['user_id'] : null
				)
			), 
			array(
				'class' => 'btn btn-' . ($status_filter == SURVEY_REPORT_REQUEST_APPROVED ? 'primary' : 'default')
			)
		); ?>  <?php 
		echo $this->Html->link(
			'Rejected', 
			array(
				'action' => 'index', 
				'?' => array(
					'status' => SURVEY_REPORT_REQUEST_REJECTED,
					'project_id' => (isset($this->request->query['project_id']) && !empty($this->request->query['project_id'])) ? $this->request->query['project_id'] : null,
					'user_id' => (isset($this->request->query['user_id']) && !empty($this->request->query['user_id'])) ? $this->request->query['user_id'] : null
				)
			), 
			array(
				'class' => 'btn btn-' . ($status_filter == SURVEY_REPORT_REQUEST_REJECTED ? 'primary' : 'default')
			)
		);  ?>  <?php 
		echo $this->Form->create('Search', array(
			'type' => 'get',
			'class' => 'missing-point-search',
			'url' => array('controller' => 'history_requests', 'action' => 'index')
		));  ?>  <?php 
		echo $this->Form->input('project_id', array(
			'type' => 'text',
			'placeholder' => 'Project ID Search',
			'label' => false,
			'value' => (isset($this->request->query['project_id']) && !empty($this->request->query['project_id'])) ? $this->request->query['project_id'] : null,
			'div' => false
		)); ?>  <?php 
		echo $this->Form->input('user_id', array(
			'type' => 'text',
			'placeholder' => 'User ID Search',
			'label' => false,
			'value' => (isset($this->request->query['user_id']) && !empty($this->request->query['user_id'])) ? $this->request->query['user_id'] : null,
			'div' => false
		));  ?>  <?php 
		echo $this->Form->submit('submit', array('style' => 'display:none;'));
		echo $this->Form->end(null); ?>
	</div>
</div>
<?php echo $this->Form->create('HistoryRequest'); ?>
<div class="box">	
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td><?php echo $this->Paginator->sort('User.email', 'User'); ?></td>
				<td>Project #</td>
				<td>Requested Points</td>
				<?php if (($status_filter != SURVEY_REPORT_REQUEST_PENDING && $status_filter != SURVEY_REPORT_REQUEST_REJECTED) || $status_filter == 'all') { ?>
					<td>Awarded Points</td>
				<?php } ?>
				<td>User Report</td>
				<td>User Statement</td>
				<td>Link</td>
				<td>Attachment</td>
				<td>Date</td>
				<td>Total Awarded Missing Points</td>
				<td>Status</td>
				<td></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($history_requests as $request): ?>
				<tr>
					<td>
						<?php echo $this->Element('user_dropdown', array('user' => $request['User'])); ?>
						<small><?php echo $request['User']['email']; ?></small>
					</td>
					<td>
						<?php echo $this->Html->link('#'.$request['HistoryRequest']['project_id'], array('controller' => 'surveys', 'action' => 'dashboard', $request['HistoryRequest']['project_id'])); ?>
					</td>
					<td><?php echo $request['Project']['award']; ?></td>
					<?php 
						if (($status_filter != SURVEY_REPORT_REQUEST_PENDING && $status_filter != SURVEY_REPORT_REQUEST_REJECTED) || $status_filter == 'all') { 
							if ($request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_REJECTED) { ?>
								<td>0</td><?php 
							}
							else { ?>
								<td><?php echo $request['Transaction']['amount']; ?></td><?php
							}
						}
					?>
					<td><?php 
						echo $REPORTS[$request['HistoryRequest']['report']]; 
						if ($request['HistoryRequest']['report'] == SURVEY_REPORT_LATE_NQ_OQ) {
							echo '<br />(Answered questions : <b>'. $request['HistoryRequest']['answered'].'</b>)';
						}
					?></td>
					<td><?php echo $request['HistoryRequest']['statement']; ?></td>
					<td><?php 
						if (!empty($request['HistoryRequest']['link'])) {
							$url = parse_url($request['HistoryRequest']['link']);
							if (isset($url['host']) && !empty($url['host'])) { ?>
								<span class="tt" title="" data-toggle="tooltip" data-original-title="<?php echo $request['HistoryRequest']['link']; ?>">
									<?php echo $url['host']?>/...
								</span><?php
							}
							else {
								echo $request['HistoryRequest']['link'];
							}
						} ?>
					</td>
					<td style="text-align: center;"><?php
						if (!empty($request['HistoryRequest']['attachment'])) {
							echo $this->Html->link('<i class="icon-download-alt"></i>', array(
								'action' => 'ajax_attachment',
								$request['HistoryRequest']['id']
							), array(
								'div' => false,
								'escape' => false,
								'data-toggle' => 'modal',
								'data-target' => '#modal-attachment-history_request',
								'id' => 'attachment-history-'.$request['HistoryRequest']['id']
							));
						}
						else {
							echo '--';
						}?></td>
					<td>
						<?php echo $this->Time->format($request['HistoryRequest']['created'], Utils::dateFormatToStrftime('Y-m-d h:i A'), false, $timezone); ?>
					</td>
					<td>
						<?php echo $request['User']['missing_points']; ?>
					</td>
					<td><?php 
						if ($request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_APPROVED) { ?>
							<b>Approved</b>
							<p>by <?php echo $request['Admin']['admin_user']; ?><p><?php 
						} 
						elseif ($request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_REJECTED) { ?>
							<b>Rejected</b>
							<p>by <?php echo $request['Admin']['admin_user']; ?><p><?php 
						}
						else {
							echo 'Pending';
						} ?>
					</td>
					<td style="white-space: nowrap;"><?php 
						echo $this->Html->link('Search', array(
							'controller' => 'history_requests',
							'action' => 'info', 
							$request['HistoryRequest']['id']
						), array(
							'class' => 'btn btn-default btn-mini',
							'target' => '_blank'
						)); ?> <?php
						if ($request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_REJECTED || $request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_PENDING) {
							echo $this->Html->link('Approve', array(
								'action' => 'ajax_approve',
								$request['HistoryRequest']['id']
							), array(
								'div' => false,
								'class' => 'btn btn-mini btn-success',
								'data-toggle' => 'modal',
								'data-target' => '#modal-approve-history_request',
								'id' => 'approve-history-'.$request['HistoryRequest']['id']
							));
						} ?> <?php
						if ($request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_APPROVED || $request['HistoryRequest']['status'] == SURVEY_REPORT_REQUEST_PENDING) {
							echo $this->Html->link('Reject', array(
								'action' => 'ajax_reject',
								$request['HistoryRequest']['id']
							), array(
								'div' => false,
								'class' => 'btn btn-mini btn-danger',
								'data-toggle' => 'modal',
								'data-target' => '#modal-reject-history_request',
								'id' => 'reject-history-'.$request['HistoryRequest']['id']
							));
						} ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php echo $this->Form->end(null); ?>
<?php echo $this->Element('pagination'); ?>
<?php echo $this->Element('modal_user_quickprofile'); ?>
<?php echo $this->Element('modal_reject_history_request'); ?>
<?php echo $this->Element('modal_approve_history_request'); ?>
<?php echo $this->Element('modal_history_request_attachment'); ?>
<?php echo $this->Element('modal_user_quickprofile'); ?>