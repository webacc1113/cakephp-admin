<div class="box">
	<div class="box-header">
		<span class="title">
			Bad Uid Log
		</span>
	</div>
	<div class="box-content">
		<?php if ($bad_uid_logs): ?>
			<table class="table table-normal">
				<tr>
					<th>End Action</th>
					<th>Query String</th>
					<th>Referrer</th>
					<th>Hash</th>
					<th>IP</th>
					<th>Server Info</th>
					<th>Created</th>
				</tr>
				<?php $ip_address_types = unserialize(IP_ADDRESS_TYPES);
					$ip_address_types = array_flip($ip_address_types);
				?>
				<tr>
					<td>
						<?php echo $bad_uid_logs['BadUidLog']['end_action']; ?>
					</td>
					<td>
						<?php echo $bad_uid_logs['BadUidLog']['query_string']; ?>
					</td>
					<td>
						<?php echo $bad_uid_logs['BadUidLog']['referrer']; ?>
					</td>
					<td>
						<?php echo $bad_uid_logs['BadUidLog']['hash']; ?>
					</td>
					<td>
						<span class="tt" data-toggle="tooltip" title="<?php echo isset($ip_address_types[$bad_uid_logs['BadUidLog']['ip_address_type']]) ? $ip_address_types[$bad_uid_logs['BadUidLog']['ip_address_type']] : ''?>">
							<?php echo $bad_uid_logs['BadUidLog']['ip_address']; ?>
						</span>
						<a href="http://whatismyipaddress.com/ip/<?php echo $bad_uid_logs['BadUidLog']['ip_address']; ?>" target="_blank" class="icon-wrench"></a>
					</td>
					<td>
						<?php echo $this->Html->link('View Server Info', array(
							'controller' => 'bad_uid_logs',
							'action' => 'view_server_info',
							$bad_uid_logs['BadUidLog']['id']
						), array(
							'data-target' => '#modal-server-info', 
							'data-toggle' => 'modal'
						));
						?>
					</td>
					<td>
						<?php echo $this->Time->format($bad_uid_logs['BadUidLog']['created'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, $timezone); ?>
					</td>
				</tr>
			</table>
		<?php endif; ?>
	</div>
</div>

<h5>Survey Visits Matches</h5>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Match Type</td>
				<td>User</td>
				<td>Project #</td>
				<td>Hash</td>
				<td>Action</td>
				<td>Result</td>
				<td>Link</td>
				<td>IP Address</td>
				<td>Referrer</td>
				<td>User Agent</td>
				<?php if ($bad_uid_logs['BadUidLog']['processed']): ?>
					<td>Status</td>
				<?php endif; ?>
				<td>Timestamp</td>
			</tr>
		</thead>
		<?php if (!empty($bad_uid_logs['BadUidMatch'])): ?>
			<tbody>
				<?php $STATUSES = unserialize(SURVEY_STATUSES); ?>
				<?php foreach ($bad_uid_logs['BadUidMatch'] as $bad_uid_log): ?>
					<?php if (isset($bad_uid_log['SurveyVisit']) && !empty($bad_uid_log['SurveyVisit'])): ?>	
						<tr>
							<td>
								<?php echo $bad_uid_log['type'] == 'ip_address' ? 'IP address' : 'User Agent'; ?>
							</td>
							<td>
								<?php if (isset($bad_uid_log['User']) && !empty($bad_uid_log['User'])): ?>
									<?php echo $this->Element('user_dropdown', array('user' => $bad_uid_log['User'])); ?>
									<small><?php echo $bad_uid_log['User']['email']; ?></small>
								<?php endif; ?>
							</td>
							<td><?php echo $this->Html->link('#'.$bad_uid_log['SurveyVisit']['survey_id'], array('controller' => 'surveys', 'action' => 'dashboard', $bad_uid_log['SurveyVisit']['survey_id'])); ?></td>
							<td>
								<?php if (!empty($bad_uid_log['SurveyVisit']['hash'])): ?>
									<?php
									echo $this->Form->input('text', array(
										'type' => 'textarea',
										'value' => $bad_uid_log['SurveyVisit']['hash'],
											'style' => 'width: 150px',
										'label' => false
									));
									?>
								<?php endif; ?>	
							</td>
							<td><?php echo $STATUSES[$bad_uid_log['SurveyVisit']['type']]; ?></td>
							<td><?php echo isset($STATUSES[$bad_uid_log['SurveyVisit']['result']]) ? $STATUSES[$bad_uid_log['SurveyVisit']['result']] : ''; ?></td>
							<td>
								<?php if (!empty($bad_uid_log['SurveyVisit']['link'])): ?>
									<?php echo $this->Form->input('text', array(
										'type' => 'textarea',
										'value' => $bad_uid_log['SurveyVisit']['link'],
										'style' => 'width: 150px',
										'label' => false
									)); ?>
								<?php endif; ?>
							</td>
							<td><?php 
								echo $this->Html->link($bad_uid_log['SurveyVisit']['ip'], array(
									'controller' => 'users', 'action' => 'ip_address', $bad_uid_log['SurveyVisit']['ip']
								)); 
							?> <a href="http://whatismyipaddress.com/ip/<?php echo $bad_uid_log['SurveyVisit']['ip']; ?>" target="_blank" class="icon-wrench"></a></td>
							<td>
								<?php if (!empty($bad_uid_log['SurveyVisit']['referrer'])): ?>
									<?php echo $this->Form->input('text', array(
										'type' => 'textarea',
										'value' => $bad_uid_log['SurveyVisit']['referrer'],
										'style' => 'width: 150px',
										'label' => false
									)); ?>
								<?php endif; ?>
							</td>
							<td><?php
								$info = Utils::print_r_reverse($bad_uid_log['SurveyVisit']['info']);
								if (isset($info) && isset($info['HTTP_USER_AGENT'])) {
									echo $info['HTTP_USER_AGENT'];
								} ?>
							</td>
							<?php if ($bad_uid_logs['BadUidLog']['processed']): ?>
								<td>
									<?php if ($bad_uid_log['matched']): ?>
										<span class="label label-success">Matched</span>
									<?php else: ?>
										<span class="label label-red">Unmatched</span>
									<?php endif; ?>
								</td>
							<?php endif; ?>
							<td>
								<?php echo $this->Time->format($bad_uid_log['SurveyVisit']['created'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, $timezone); ?>
							</td>
						</tr>
					<?php endif; ?>
				<?php endforeach; ?>
			</tbody>
		<?php endif; ?>
	</table>
</div>
<div id="modal-server-info" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Server Info</h6>
	</div>
	<div class="modal-body">
	</div>
	<div class="modal-footer">		
	</div>
</div>
<?php echo $this->Element('modal_user_quickprofile'); ?>