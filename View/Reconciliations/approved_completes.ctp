<div class="row-fluid">
	<div class="span12 text-right input">
		<?php echo $this->Html->link('Approve missing completes', array(
				'controller' => 'reconciliations',
				'action' => 'approve_missing_completes',
				$reconciliation['Reconciliation']['id']
			), array(
				'class' => 'btn btn-success'
			));
		?>
	</div>
</div>
<div class="box">
	<div class="box-header">
		<span class="title">Approved completes</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<table cellpadding="0" cellspacing="0" class="table table-normal">
				<thead>
					<tr>
						<th>User id</th>
						<th>Transaction id</th>
						<th><?php echo in_array($reconciliation['Reconciliation']['type'], unserialize(RECONCILE_PROJECTS)) ? 'Project' : 'Offer'; ?></th>
						<th>Timestamp of complete</th>
						<th>Hash</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($reconciliation_rows as $reconciliation_row): ?>
						<tr>
							<td><?php echo $reconciliation_row['ReconciliationRow']['user_id']; ?></td>
							<td><?php echo $reconciliation_row['ReconciliationRow']['transaction_id']; ?></td>
							<td><?php 
								if (in_array($reconciliation['Reconciliation']['type'], unserialize(RECONCILE_PROJECTS))) :
									echo '#' . $reconciliation_row['ReconciliationRow']['survey_id'];
								else :
									echo '#' . $reconciliation_row['ReconciliationRow']['offer_id'];
								endif;?>
							</td>
							<td><?php echo $reconciliation_row['ReconciliationRow']['timestamp']; ?></td>
							<td><?php echo $reconciliation_row['ReconciliationRow']['hash']; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

<?php echo $this->Element('pagination'); ?>