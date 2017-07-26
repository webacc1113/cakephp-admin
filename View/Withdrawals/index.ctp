<h3>Withdrawals</h3>

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
		<?php echo $this->Form->create('Filter', array('type' => 'get', 'class' => 'filter')); ?>
			<div class="padded separate-sections">
				<div class="row-fluid">
					<div class="filter">
						<?php echo $this->Form->input('status', array(
							'id' => 'withdrawal-status',
							'type' => 'select', 
							'class' => 'uniform',
							'label' => '&nbsp;',
							'empty' => 'All Withdrawals',
							'options' => array(
								WITHDRAWAL_PENDING => 'Pending',
								WITHDRAWAL_REJECTED => 'Rejected',
								WITHDRAWAL_PAYOUT_UNPROCESSED => 'Payout Unprocessed',
								WITHDRAWAL_PAYOUT_SUCCEEDED => 'Payout Succeeded',
								WITHDRAWAL_PAYOUT_FAILED => 'Payout Failed'
							),
							'value' => isset($this->data['status']) ? $this->data['status']: null
						)); ?>
					</div>
					
					<div class="filter">
						<?php echo $this->Form->input('payment_method', array(
							'id' => 'withdrawal-payment_method',
							'type' => 'select',
							'class' => 'uniform',
							'label' => '&nbsp;',
							'empty' => 'All payment methods',
							'options' => unserialize(PAYMENT_METHODS),
							'value' => isset($this->data['payment_method']) ? $this->data['payment_method'] : null
						)); ?>
					</div>
					
					<div class="filter">
					<?php echo $this->Form->input('user', array(
						'placeholder' => 'Email address or #userid',
						'value' => isset($this->data['user']) ? $this->data['user']: null
					)); ?>
					</div>
				
					<div class="filter date-group">
						<label>Withdrawal amount between:</label>
						<?php echo $this->Form->input('amount_from', array(
							'label' => false, 
							'class' => 'amount',
							'placeholder' => '$0',
							'value' => isset($this->data['amount_from']) ? $this->data['amount_from']: null
						)); ?> 
						<?php echo $this->Form->input('amount_to', array(
							'label' => false, 
							'class' => 'amount',
							'placeholder' => '-',
							'value' => isset($this->data['amount_to']) ? $this->data['amount_to']: null
						)); ?>
					</div>
					
					<div class="filter date-group">
						<label>Withdrawal date between:</label>
						<?php echo $this->Form->input('date_from', array(
							'label' => false, 
							'class' => 'datepicker',
							'data-date-autoclose' => true,
							'placeholder' => 'Start date',
							'value' => isset($this->data['date_from']) ? $this->data['date_from']: null
						)); ?> 
						<?php echo $this->Form->input('date_to', array(
							'label' => false, 
							'class' => 'datepicker',
							'placeholder' => 'End date',
							'data-date-autoclose' => true,
							'value' => isset($this->data['date_to']) ? $this->data['date_to']: null
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

<?php if (isset($withdrawals)): ?>

	<p class="count">Showing <?php 
		echo number_format($this->Paginator->counter(array('format' => '{:current}')));
	?> of <?php
		echo number_format($this->Paginator->counter(array('format' => '{:count}')));
	?> matches 
		<?php if (isset($sums) && isset($sums[0]['sum_amount'])): ?>
			<em><strong>(Total Sum: <?php echo $this->App->dollarize(abs(round($sums[0]['sum_amount'] / 100))); ?>)</em></strong>
		<?php endif; ?>
	</p>

	<?php echo $this->Form->create('Withdrawal'); ?>
	<div class="box">
		<table cellpadding="0" cellspacing="0" class="table table-normal">
			<thead>
				<tr>
					<td class="checkbox"><?php echo $this->Form->input('null', array(
						'type' => 'checkbox', 
						'label' => false,
						'onclick' => 'return toggleChecked(this.checked)'
					)); ?></td>
					<td class="status"><?php echo $this->Paginator->sort('Withdrawal.status', 'Status'); ?></td>
					<td><?php echo $this->Paginator->sort('User.email', 'User'); ?></td>
					<td>User Score</td>
					<td>Note</td>
					<td><?php echo $this->Paginator->sort('amount_cents', 'Amount'); ?></td>
					<td><?php echo $this->Paginator->sort('processed', 'Processed'); ?></td>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($withdrawals as $withdrawal): ?>
					<?php
						$convert_to_ustime = $this->Time->format(strtotime($withdrawal['Withdrawal']['created']), Utils::dateFormatToStrftime(DB_DATETIME), false, 'America/Los_Angeles');
						$x_business_day = $this->Calculator->get_business_date_after_x_days($convert_to_ustime, 5);
						$given = new DateTime($x_business_day, new DateTimeZone('America/Los_Angeles'));
						$given->setTimezone(new DateTimeZone('UTC'));
						$withdrawal_date_utc = $given->format("Y-m-d H:i:s");
						$past_due = ($withdrawal['Withdrawal']['status'] == WITHDRAWAL_PENDING && strtotime($withdrawal_date_utc) < strtotime(date(DB_DATE))); 
					?>
					<tr class="<?php echo $withdrawal['Withdrawal']['status'] == WITHDRAWAL_PAYOUT_FAILED ? 'rejected muted': '';
						echo $past_due ? 'past-due-row' : ''?>">
						<td class="checkbox"><?php echo $this->Form->input('Withdrawal.'.$withdrawal['Withdrawal']['id'], array(
							'label' => false,
							'type' => 'checkbox'
						)); ?></td>
						<td class="status"><?php 
							$status = $withdrawal['Withdrawal']['status']; 
							switch ($status) {
								case WITHDRAWAL_REJECTED:
									$label = 'label-red';
									break;
								case WITHDRAWAL_PAYOUT_SUCCEEDED:
									$label = 'label-green';
									break;
								case WITHDRAWAL_PAYOUT_FAILED:
									$label = 'label-warning';
									break;
								default: 
									$label = '';
							}
							echo '<span class="'.(!empty($label) ? 'label '.$label: '').' label-transaction">' . $status . '</span>'; 
						?>
						</td>
						<td>
							<?php echo $this->Element('user_dropdown', array('user' => $withdrawal['User'])); ?>
							<?php echo $this->Element('user_delete_flag', array('deleted' => $withdrawal['User']['deleted_on']));?>
							<small><?php echo $withdrawal['User']['email']; ?></small>
						</td>
						<td>
							<?php if (isset($withdrawal['UserAnalysis'][0])): ?>
								<?php
									echo $this->Html->link($withdrawal['UserAnalysis'][0]['score'], 
										array('controller' => 'users', 'action' => 'quickscores', $withdrawal['User']['id']), 
										array(
											'data-target' => '#modal-user-scores',
											'data-toggle' => 'modal', 
										)
									); 
								?>
							<?php endif; ?>
						</td>
						<td>
							<?php if ($past_due):
									echo '<strong class="text-error">Past Due</strong><br/>';
								endif;
							?>
							<?php if (empty($withdrawal['Withdrawal']['note']) && (time() - strtotime($withdrawal['Withdrawal']['created'])) < 3600) : ?>
								<?php echo $withdrawal['Withdrawal']['note']; ?><br/><span class="muted">Analysis not run on this withdrawal yet</span>
							<?php else: ?>
								<?php echo $withdrawal['Withdrawal']['note']; ?>
							<?php endif; ?>
						</td>
						<td><?php 						
							$amount = '$'.number_format(round(-1 * $withdrawal['Withdrawal']['amount_cents'] / 100, 2), 2);
							echo $this->App->negatize($amount, $withdrawal['Withdrawal']['amount_cents']); 
						?></td>
						<td>
							<?php if ($past_due):
									echo '<strong class="text-error">Past Due</strong><br/>';
								endif;
							?>
							<?php echo $this->Time->format($withdrawal['Withdrawal']['processed'], Utils::dateFormatToStrftime('Y-m-d h:i A'), false, $timezone); ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<!-- action buttons begins -->
		<div class="form-actions">
			<?php echo $this->Form->submit('Payout', array(
				'name' => 'approve', 
				'rel' => 'tooltip', 
				'data-original-title' => 'Withdrawals will be immediately paid', 
				'class' => 'btn btn-success'
			)); ?> 
			<?php echo $this->Form->submit('Reject', array(
				'rel' => 'tooltip', 
				'data-original-title' => 'Rejected withdrawals remain in the transaction history', 
				'name' => 'reject', 'class' => 'btn btn-danger'
			)); ?> 
			<?php echo $this->Form->submit('Delete', array(
				'name' => 'delete', 
				'class' => 'btn btn-warning',
				'rel' => 'tooltip', 
				'data-original-title' => 'Deleting withdrawals removes the withdrawal from the user history', 
			)); ?>	
			<?php echo $this->Form->submit('Hellban + Reject', array(
				'name' => 'hellban', 'class' => 'btn btn-danger'
			)); ?> 
		</div>
		<!-- action buttons ends -->
	</div>
	<?php echo $this->Form->end(null); ?>

	<!-- Hellbanned begins -->
	<?php if ($withdrawals) : ?>
		<?php foreach ($withdrawals as $withdrawal): ?>
			<?php echo $this->Element('modal_user_hellban', array('user' => $withdrawal['User'])); ?>
			<?php echo $this->Element('modal_user_remove_hellban', array('user' => $withdrawal['User'])); ?>
		<?php endforeach; ?>
	<?php endif; ?>
	<!-- Hellbanned ends -->

	<?php echo $this->Element('pagination'); ?>
	<?php echo $this->Element('modal_user_quickprofile'); ?>
	<?php echo $this->Element('modal_user_referrer'); ?>
	<?php echo $this->Element('modal_user_score'); ?>
	<?php echo $this->Element('modal_user_scores'); ?>

	<script>
		var TYPE_WITHDRAWAL = <?php echo TRANSACTION_WITHDRAWAL ?>;
		var WITHDRAWAL_REJECTED = 'Rejected',
			WITHDRAWAL_PAYOUT_FAILED = 'Payout Failed';
		var enablePaymentMethodFilter = function() {
			var status = $('#withdrawal-status').val(),
				$paymentMethod = $('#withdrawal-payment_method');

			if (status != WITHDRAWAL_REJECTED && status != WITHDRAWAL_PAYOUT_FAILED) {
				$paymentMethod.prop('disabled', false);
				$paymentMethod.closest('div.selector').removeClass('disabled');
			}
			else {
				$paymentMethod.prop('disabled', true);
				$paymentMethod.closest('div.selector').addClass('disabled');
			}
		};

		$(document).ready(function() {
			enablePaymentMethodFilter();

			$('#withdrawal-status').change(function (e) {
				enablePaymentMethodFilter();
			});
		});
	</script>
<?php else: ?>
	<div class="alert alert-info">Don't be a stupid twat like melder and hack URLs and cause the system to crash - select a search term above to see the withdrawals</div> 
<?php endif; ?>