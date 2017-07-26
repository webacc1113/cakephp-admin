<div class="row-fluid">
	<div class="span12 text-right input">
		<?php echo $this->Html->link('View rejected completes', array(
				'controller' => 'reconciliations',
				'action' => 'rejected_completes',
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
		<span class="title">Reject completes - (These completes are not found in partner report)</span>
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
						<th>Project</th>
						<th>Transaction id</th>
						<th>Amount</th>
						<th>Date</th>
						<th>Hash</th>
						<th>Description</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($extra_completes as $extra_complete): ?>
						<tr>
							<td><?php echo $this->Form->input('reconcile][', array(
								'type' => 'checkbox', 
								'value' => $extra_complete['ExtraComplete']['id'],
								'id' => 'reconcile'.$extra_complete['ExtraComplete']['id'],
								'label' => '#'.$extra_complete['ExtraComplete']['user_id'],
								'hiddenField' => false
							)); ?></td>
							<td><?php echo $extra_complete['User']['email']; ?></td>
							<td><?php echo $this->Html->link($extra_complete['ExtraComplete']['survey_id'], array(
								'controller' => 'surveys', 
								'action' => 'dashboard', 
								$extra_complete['ExtraComplete']['survey_id']
								), array('target' => '_blank')); ?>
							</td>
							<td><?php echo $this->Html->link($extra_complete['ExtraComplete']['transaction_id'], array(
								'controller' => 'transactions', 
								'action' => 'index', 
								'?' => array(
									'user' => urlencode('#'.$extra_complete['ExtraComplete']['user_id']), 
									'type' => TRANSACTION_SURVEY,
									'linked_to_id' => $extra_complete['ExtraComplete']['survey_id']
								)), array('target' => '_blank')); ?>
							</td>
							<td><?php echo $projects[$extra_complete['ExtraComplete']['survey_id']]; ?></td>
							<td><?php echo $extra_complete['ExtraComplete']['timestamp']; ?></td>
							<td><?php echo $extra_complete['ExtraComplete']['hash']; ?></td>
							<td><?php echo $extra_complete['ExtraComplete']['description']; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Submit', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<span class="pull-right label label-info"><?php echo count($extra_completes)?> extra completes found from <?php echo $reconciliation['Reconciliation']['min_transaction_date']; ?> to <?php echo $reconciliation['Reconciliation']['max_transaction_date']; ?></span>
<?php echo $this->Form->end(null); ?>