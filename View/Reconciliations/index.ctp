<?php $this->Html->script('jquery.timer', array('inline' => false)); ?>
<script type="text/javascript">
	var timer = $.timer(function() {
		$('tr[data-status=""]').each(function() {
			var $node = $(this);
			var id = $node.data('id');
			$.ajax({
				type: "GET",
				url: "/reconciliations/check/" + id,
				statusCode: {
					201: function(data) {
						if (data.status == '') {
							$('span.label', $node).text('Imported '+data.count+ ' rows');
						}
						else {
							$node.data('status', data.status);
							$('tr[data-id="'+id+'"]').data('status', data.status);
							$('span.label', $node).removeClass('label-warning').addClass('label-info').text('READY');
							$('a.btn-review', $node).show();
						}
					}
				}
			});
		});
	});

	timer.set({ time : 4000, autostart : true });
</script>

<?php $reconcile_types = unserialize(RECONCILE_TYPES);?>
<div>
	<h3>Reconciliation </h3>
	<div class="well">
		<div class="row-fluid">
			<div class="span4">
				<p>
					<span class="label label-warning">IMPORTING</span> means the reconciliation report file has been uploaded, however the actual import of data to database is not yet done.
				</p>
			</div>	
			<div class="span4">
				<p>
					<span class="label label-info">READY</span> [for offers, ssi and points2shop only] means the data has been imported to database, and is ready for admin to reconcile - lucid need more detailed analysis before the actual reconciliation to happen.
				</p>
				
			</div>	
			<div class="span4">
				<p>
					<span class="label label-red">ERROR</span> means an error has occurred during the import or analysis. Please check 'reconciliation' logs in this case.
				</p>
			</div>	
		</div>
		<div class="row-fluid">
			<div class="span4">
				<p>
					<span class="label label-info">ANALYZED</span> [for lucid only] means the report has been analyzed and can now be reconciled. 
				</p>
			</div>	
			<div class="span4">
				<p>
					<span class="label label-success">COMPLETED</span> means the report has been reconciled successfully.
				</p>
			</div>	
		</div>
	</div>
	<div class="row-fluid">
		<div class="span6 pull-right">
			<?php
			echo $this->Form->create(null, array(
				'class' => 'clearfix form-inline',
				'type' => 'get',
				'url' => array(
					'controller' => 'reconciliations',
					'action' => 'index',
				),
			));
			?>
			<div class="user-groups">
				<div class="form-group">
					<?php
					echo $this->Form->input('type', array(
						'empty' => 'All partners',
						'options' => $reconcile_types,
						'label' => false,
						'value' => isset($this->request->query['type']) ? $this->request->query['type'] : null
					));
					?>
					<?php echo $this->Form->submit('Show', array('class' => 'btn btn-default')); ?>
				</div>
			</div>
			<?php echo $this->Form->end(null); ?>
		</div>
	</div>
	<div class="box">
		<table cellpadding="0" cellspacing="0" class="table table-normal table-fixed-header">
			<thead class="header">
				<tr>
					<td>Status</td>
					<td><?php echo $this->Paginator->sort('type', 'Partner'); ?> </td>
					<td><?php echo $this->Paginator->sort('created', 'Reconciliation Run Date'); ?> </td>
					<td>Date Range</td>
					<td><?php echo $this->Paginator->sort('total_completes'); ?> </td>
					<td><?php echo $this->Paginator->sort('total_approved'); ?> </td>
					<td><?php echo $this->Paginator->sort('total_rejected'); ?> </td>
					<td>Action</td>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($reconciliations as $reconciliation): ?>
					<tr data-id="<?php echo $reconciliation['Reconciliation']['id']; ?>" data-status="<?php echo $reconciliation['Reconciliation']['status'];?>">
						<td>
							<?php if (is_null($reconciliation['Reconciliation']['status'])): ?>
								<span class="label label-warning">IMPORTING</span>
							<?php elseif ($reconciliation['Reconciliation']['status'] == RECONCILIATION_IMPORTED): ?>
								<span class="label label-info">IMPORTED</span>
							<?php elseif ($reconciliation['Reconciliation']['status'] == RECONCILIATION_ANALYZED): ?>
								<span class="label label-info">ANALYZED</span>
							<?php elseif ($reconciliation['Reconciliation']['status'] == RECONCILIATION_COMPLETED): ?>
								<span class="label label-success">COMPLETE</span>
							<?php elseif ($reconciliation['Reconciliation']['status'] == RECONCILIATION_ERROR): ?>
								<span class="label label-red">ERROR</span>
							<?php endif; ?>
						</td>	
						<td>
							<?php echo $reconcile_types[$reconciliation['Reconciliation']['type']]; ?>
						</td>
						<td class="nowrap">
							<?php $date = $this->Time->format($reconciliation['Reconciliation']['created'], Utils::dateFormatToStrftime('M jS, Y h:i A'), false, $timezone); ?>
							<?php if (isset($settings['s3.bucket']) && !empty($reconciliation['Reconciliation']['filepath'])) : ?>
								<?php echo $this->Html->link($date, array(
									'action' => 'download',
									'?' => array(
										'path' => urlencode($reconciliation['Reconciliation']['filepath'])
									)
								), array(
									'target' => '_blank'
								)); ?>
							<?php else: ?>
								<?php echo $date; ?>
							<?php endif; ?>
						</td>
						<td class="nowrap">
							<?php if (!empty($reconciliation['Reconciliation']['min_transaction_date']) && !empty($reconciliation['Reconciliation']['max_transaction_date'])): ?>
								<?php echo $reconciliation['Reconciliation']['min_transaction_date']; ?> - 
								<?php echo $reconciliation['Reconciliation']['max_transaction_date']; ?>
							<?php endif; ?>
						</td>
						<td>
							<?php if ($reconciliation['Reconciliation']['total_completes'] > 0): ?>
								<?php echo $reconciliation['Reconciliation']['total_completes']; ?>
							<?php else: ?>
								<span class="muted">-</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ($reconciliation['Reconciliation']['total_approved'] > 0 && $reconciliation['Reconciliation']['total_completes'] > 0): ?>
								<?php echo round(($reconciliation['Reconciliation']['total_approved'] * 100) / $reconciliation['Reconciliation']['total_completes']).'%'; ?>
							<?php else: ?>
								<span class="muted">-</span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ($reconciliation['Reconciliation']['total_rejected'] > 0 && $reconciliation['Reconciliation']['total_completes'] > 0): ?>
								<?php echo round(($reconciliation['Reconciliation']['total_rejected'] * 100) / $reconciliation['Reconciliation']['total_completes']).'%'; ?>
							<?php else: ?>
								<span class="muted">-</span>
							<?php endif; ?>
						</td>
						<td>
							<!-- Used for ajax -->
							<?php if (is_null($reconciliation['Reconciliation']['status'])): ?>
								<?php echo $this->Html->link('Missing completes', array(
									'controller' => 'reconciliations',
									'action' => 'approve_missing_completes', 
									$reconciliation['Reconciliation']['id']
								), array(
									'style' => 'display: none;', 
									'class' => 'btn btn-default btn-review'
								)); ?>
							<?php endif; ?>
							<?php echo $this->Html->link('Logs', array(
								'controller' => 'reconciliation_logs',
								'action' => 'index', 
								$reconciliation['Reconciliation']['id']
							), array(
								'class' => 'btn btn-default',
								'target' => '_blank'
							)); ?>
							
							<?php if (in_array($reconciliation['Reconciliation']['status'], array(RECONCILIATION_IMPORTED, RECONCILIATION_ANALYZED))): ?>
								<?php echo $this->Html->link('Missing completes', array(
									'controller' => 'reconciliations',
									'action' => 'approve_missing_completes', 
									$reconciliation['Reconciliation']['id']
								), array(
									'class' => 'btn btn-default'
								)); ?>
							<?php endif; ?>
							<?php if ($reconciliation['Reconciliation']['status'] == RECONCILIATION_ANALYZED): ?>
								<?php echo $this->Html->link('Extra completes', array(
										'controller' => 'reconciliations',
										'action' => 'reject_extra_completes', 
										$reconciliation['Reconciliation']['id'],
									), array(
										'class' => 'btn btn-default'
									)); ?>
							<?php endif; ?>
							<?php if ($reconciliation['Reconciliation']['status'] != RECONCILIATION_COMPLETED): ?>
								<?php echo $this->Html->link('Delete', array(
									'controller' => 'reconciliations',
									'action' => 'delete',
									$reconciliation['Reconciliation']['id']
								), array(
									'class' => 'btn btn-danger'
								)); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<?php echo $this->Element('pagination'); ?>