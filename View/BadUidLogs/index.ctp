<h3>All Bad Uid Logs</h3>
<div class="box">
	<div class="box-header">
		<span class="title">Filters</span>
		<ul class="box-toolbar">
			<li>
				<?php echo $this->Html->link('<i class="icon-remove-sign"></i> Clear filters', array('action' => 'index'), array('escape' => false)); ?>
			</li>
		</ul>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create(null, array(
			'class' => 'filter',
			'type' => 'get',
			'url' => array(
				'controller' => 'bad_uid_logs',
				'action' => 'index'
			),
		)); ?>
			<div class="padded separate-sections">
				<div class="row-fluid">
					<div class="filter">
						<label>Type</label>
						<?php echo $this->Form->input('type', array(
							'label' => false,
							'type' => 'select',
							'options' => array(
								'success' => 'success',
								'nq' => 'nq',
								'sec' => 'sec',
								'sec-speed' => 'sec-speed',
								'quota' => 'quota',
								'null' => 'null'
							),
							'selected' => (isset($this->request->query['type']) && $this->request->query['type']) ? $this->request->query['type'] : 'success'
						)); ?>
					</div>
					<div class="filter">
						<label>Query String</label>
						<?php echo $this->Form->input('query_string', array(
							'label' => false,
							'type' => 'text',
							'style' => 'width: 300px',
							'placeholder' => 'Query String',
							'value' => (isset($this->request->query['query_string']) && !empty($this->request->query['query_string'])) ? trim($this->request->query['query_string']) : ''
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
<div class="box">
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
					<th>Processed</th>
					<th>Created</th>
					<th>Matched Types</th>
					<th></th>
				</tr>
				<?php $ip_address_types = unserialize(IP_ADDRESS_TYPES);
					$ip_address_types = array_flip($ip_address_types);
				?>
				<?php foreach ($bad_uid_logs as $bad_uid_log): ?>
					<tr>
						<td>
							<?php echo $bad_uid_log['BadUidLog']['end_action']; ?>
						</td>
						<td>
							<?php if (!empty($bad_uid_log['BadUidLog']['query_string'])): ?>
								<?php
								echo $this->Form->input('text', array(
									'type' => 'textarea',
									'value' => $bad_uid_log['BadUidLog']['query_string'],
									'rows' => 3,
									'style' => 'width: 160px',
									'label' => false
								));
								?>
							<?php endif; ?>
						</td>
						<td>
							<?php if (!empty($bad_uid_log['BadUidLog']['referrer'])): ?>
								<?php echo $this->Form->input('text', array(
									'type' => 'textarea',
									'value' => $bad_uid_log['BadUidLog']['referrer'],
									'rows' => 3,
									'style' => 'width: 160px',
									'label' => false
								)); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php if(!empty($bad_uid_log['BadUidLog']['hash'])): ?>
								<?php
								echo $this->Form->input('text', array(
									'type' => 'textarea',
									'value' => $bad_uid_log['BadUidLog']['hash'],
									'rows' => 3,
									'style' => 'width: 160px',
									'label' => false
								));
								?>
							<?php endif; ?>
						</td>
						<td>
							<span class="tt" data-toggle="tooltip" title="<?php echo isset($ip_address_types[$bad_uid_log['BadUidLog']['ip_address_type']]) ? $ip_address_types[$bad_uid_log['BadUidLog']['ip_address_type']] : ''?>">
								<?php echo $bad_uid_log['BadUidLog']['ip_address']; ?>
							</span>
							<a href="http://whatismyipaddress.com/ip/<?php echo $bad_uid_log['BadUidLog']['ip_address']; ?>" target="_blank" class="icon-wrench"></a>
						</td>
						<td>
							<?php echo $this->Html->link('View Server Info', array(
								'controller' => 'bad_uid_logs',
								'action' => 'view_server_info',
								$bad_uid_log['BadUidLog']['id']
							), array(
								'data-target' => '#modal-server-info', 
								'data-toggle' => 'modal'
							));
							?>
						</td>
						<td>
							<?php if ($bad_uid_log['BadUidLog']['processed']): ?>
								<span class="label label-success">Processed</span>
							<?php else: ?>
								<span class="label label-red">Unprocessed</span>
							<?php endif; ?>
						</td>
						<td>
							<?php echo $this->Time->format($bad_uid_log['BadUidLog']['created'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, $timezone); ?>
						</td>
						<td><?php 
							if ($bad_uid_log['BadUidLog']['ip_address_match'] == 0 && $bad_uid_log['BadUidLog']['user_agent_match'] == 0) {
								echo '--';
							}
							if ($bad_uid_log['BadUidLog']['ip_address_match'] > 0) {
								echo 'IP address ('.$bad_uid_log['BadUidLog']['ip_address_match'].')';
							}
							if ($bad_uid_log['BadUidLog']['ip_address_match'] > 0 || $bad_uid_log['BadUidLog']['user_agent_match'] > 0) {
								echo '<br>';
							}
							if ($bad_uid_log['BadUidLog']['user_agent_match'] > 0) {
								echo 'User Agent ('.$bad_uid_log['BadUidLog']['user_agent_match'].')';
							} ?>
						</td>
						<td><?php 
							if ($bad_uid_log['BadUidLog']['ip_address_match'] > 0 || $bad_uid_log['BadUidLog']['user_agent_match'] > 0):
								echo $this->Html->link('Search', array(
									'controller' => 'bad_uid_logs',
									'action' => 'matches',
									$bad_uid_log['BadUidLog']['id']
								), array(
									'class' => 'btn btn-mini btn-default',
									'target' => '_blank'
								));
							endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
		<?php endif; ?>
		<div class="form-actions">
			<?php echo $this->Element('pagination'); ?>
		</div>
	</div>
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