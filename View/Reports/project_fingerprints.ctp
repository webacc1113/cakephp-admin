<?php $project_id = $this->params['pass'][0]; ?>
<h3>Fingerprint Log for #<?php echo $this->Html->link($project_id, array('controller' => 'surveys', 'action' => 'dashboard', $project_id)); ?></h3>
<?php if ($project_fingerprints) : ?>
	<div class="row-fluid">
		<div class="span12">
			<?php $project_id = $this->params['pass'][0]; ?>
			<p><?php echo $this->Html->link('Export to CSV', array('action' => 'project_fingerprints', $project_id, '?' => array('export' => true)), array('class' => 'btn btn-success')); ?></p>
		</div>
	</div>
	<div class="row-fluid">
		<div class="span9">
			<div class="box">
				<table cellpadding="0" cellspacing="0" class="table table-normal">
					<thead>
						<tr>
							<td>Is Dupe</td>
							<td>Hash</td>
							<td>IP Address</td>
							<td>Fingerprint</td>
							<td>User Agent</td>
							<td>Created (GMT)</td>
						</tr>
					</thead>
					<tbody>
						<?php $counts = array_count_values($fingerprints); ?>
						<?php foreach ($project_fingerprints as $project_fingerprint): ?>
							<?php $is_dupe = $counts[$project_fingerprint['ProjectFingerprint']['fingerprint']] > 1; ?>
							<tr>
								<td>
									<?php echo $is_dupe ? '<span class="label label-red">DUPE</span>': ''; ?>
								</td>
								<td><?php echo $project_fingerprint['SurveyVisit']['hash']; ?><br/>
									<?php $partner = $partners[$project_fingerprint['SurveyVisit']['partner_id']]; ?>
									<?php echo $partner['Partner']['partner_name']; ?>: 
									<?php if ($partner['Partner']['key'] == 'mintvine'): ?>
										<?php list($project_id, $user_id, $trash) = explode('-', $project_fingerprint['ProjectFingerprint']['partner_user_id']); ?>
										<?php echo $this->Html->link(
											'#'.$user_id, 
											array(
												'controller' => 'panelist_histories', 
												'action' => 'user', 
												'?' => array('user_id' => $user_id)
											)
										); ?>
									<?php else: ?>
										<?php echo $project_fingerprint['ProjectFingerprint']['partner_user_id']; ?>
									<?php endif; ?>
								</td>
								<td><?php echo $project_fingerprint['ProjectFingerprint']['ip_address']; ?></td>
								<td><?php echo $project_fingerprint['ProjectFingerprint']['fingerprint']; ?></td>
								<td>
									<?php $info = Utils::print_r_reverse($project_fingerprint['SurveyVisit']['info']); ?>
									<?php if (isset($info) && isset($info['HTTP_USER_AGENT'])): ?>
										<?php
										echo $this->Form->input('text', array(
											'type' => 'textarea',
											'value' => $info['HTTP_USER_AGENT'],
											'style' => 'width: 200px; height: 60px',
											'label' => false
										));
										?>
									<?php endif; ?>
								</td>
								<td><?php echo $project_fingerprint['ProjectFingerprint']['created']; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php echo $this->Element('pagination'); ?>
		</div>
		<div class="span3">
		<div class="box">
			<div class="box-header">
				<span class="title">Understanding this log</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<p>Each entry into a project is logged with a fingerprint. This report will show you the full user-agent, IP address and stored fingerprint of every entry so you can connect disparate accounts as a single browser.</p>
					<p>Highlighted rows are a duplicate 
				</div>
			</div>
		</div>
		</div>
	</div>
<?php else: ?>
	<div class="alert alert-info">Data not found!</div>
<?php endif; ?>