<div class="row-fluid">
	<div class="span12 text-right input">
		<?php echo $this->Html->link('View approved completes', array(
				'controller' => 'reconciliations',
				'action' => 'approved_completes',
				$reconciliation['Reconciliation']['id']
			), array(
				'class' => 'btn btn-success'
			));
		?>
	</div>
</div>
<?php echo $this->Form->create('Reconciliation'); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Approve missing completes</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<table cellpadding="0" cellspacing="0" class="table table-normal">
				<thead>
					<tr>
						<th><?php echo $this->Form->input('null', array(
							'type' => 'checkbox', 
							'label' => 'User ID',
							'onclick' => 'return toggleChecked(this.checked)'
						)); ?></th>
						<th>Email</th>
						<th><?php echo in_array($reconciliation['Reconciliation']['type'], unserialize(RECONCILE_PROJECTS)) ? 'Project' : 'Offer'; ?></th>
						<th>Amount</th>
						<th>Date</th>
						<th>Hash</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($reconciliation_rows as $reconciliation_row): ?>
						<tr>
							<td><?php echo $this->Form->input('reconcile][', array(
								'type' => 'checkbox', 
								'value' => $reconciliation_row['ReconciliationRow']['id'],
								'id' => 'reconcile'.$reconciliation_row['ReconciliationRow']['id'],
								'label' => '#'.$reconciliation_row['ReconciliationRow']['user_id'],
								'hiddenField' => false
							)); ?></td>
							<td><?php echo $this->Html->link($reconciliation_row['User']['email'], array(
								'controller' => 'transactions', 
								'action' => 'index', 
								'?' => array(
									'user' => urlencode('#'.$reconciliation_row['User']['id']), 
									'type' => TRANSACTION_SURVEY,
									'linked_to_id' => $reconciliation_row['ReconciliationRow']['survey_id']
								))); ?></td>
							<td><?php 
								if (in_array($reconciliation['Reconciliation']['type'], unserialize(RECONCILE_PROJECTS))) :
									echo $this->Html->link('#'.$reconciliation_row['ReconciliationRow']['survey_id'], array('controller' => 'surveys', 'action' => 'dashboard', $reconciliation_row['ReconciliationRow']['survey_id']));
								elseif (isset($offers[$reconciliation_row['ReconciliationRow']['offer_id']])) :
									echo '#' . $offers[$reconciliation_row['ReconciliationRow']['offer_id']];
								else :
									echo '--';
								endif;?>
							</td>
							<td><?php 
								if (in_array($reconciliation['Reconciliation']['type'], unserialize(RECONCILE_PROJECTS))) :
									echo $project_awards[$reconciliation_row['ReconciliationRow']['survey_id']];
								else :
									echo $reconciliation_row['ReconciliationRow']['offer_amount'];
								endif;?>
							</td>
							<td><?php echo $reconciliation_row['ReconciliationRow']['timestamp']; ?></td>
							<td><?php echo $reconciliation_row['ReconciliationRow']['hash']; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<div class="padded">
				<?php echo $this->Form->input('mark', array(
					'type' => 'checkbox',
					'label' => 'Mark this report as complete'
				)); ?>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Pay Transactions', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<span class="pull-right label label-info"><?php echo count($reconciliation_rows)?> missing completes found out of <?php echo $count; ?></span>
<?php echo $this->Form->end(null); ?>