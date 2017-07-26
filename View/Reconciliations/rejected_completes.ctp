<div class="row-fluid">
	<div class="span12 text-right input">
		<?php echo $this->Html->link('Reject extra completes', array(
				'controller' => 'reconciliations',
				'action' => 'reject_extra_completes',
				$reconciliation['Reconciliation']['id']
			), array(
				'class' => 'btn btn-success'
			));
		?>
	</div>
</div>
<div class="box">
	<div class="box-header">
		<span class="title">Rejected completes</span>
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
					<?php foreach ($extra_completes as $complete): ?>
						<tr>
							<td><?php echo $complete['ExtraComplete']['user_id']; ?></td>
							<td><?php echo $complete['ExtraComplete']['transaction_id']; ?></td>
							<td><?php 
								if (in_array($reconciliation['Reconciliation']['type'], unserialize(RECONCILE_PROJECTS))) :
									echo '#' . $complete['ExtraComplete']['survey_id'];
								else :
									echo '#' . $complete['ExtraComplete']['offer_id'];
								endif;?>
							</td>
							<td><?php echo $complete['ExtraComplete']['timestamp']; ?></td>
							<td><?php echo $complete['ExtraComplete']['hash']; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>