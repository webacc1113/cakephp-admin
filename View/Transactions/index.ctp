<?php if (!empty($master_dwolla_error)): ?>
	<div class="alert alert-error">
		<?php echo $master_dwolla_error; ?>
	</div>
<?php endif; ?>

<h3>Transactions</h3>

<?php echo $this->Form->create('Transaction', array(
	'type' => 'get', 
	'url' => array('action' => 'inspect', (isset($is_user_filter) && isset($user['User'])) ? $user['User']['id']: null)
)); ?>
<p><?php 
	$pending_class = isset($this->data['paid']) && $this->data['paid'] == '0' && count($this->data) == 1 
		? 'btn-success'
		: 'btn-default';
	echo $this->Html->link('All Pending', 
		array('?' => array('paid' => '0')), 
		array('class' => 'btn btn-small '.$pending_class)
	); 
?> 
<?php 
	$pending_class = isset($this->data['paid']) && $this->data['paid'] == '0' && count($this->data) == 2 && $this->data['type'] == TRANSACTION_WITHDRAWAL 
		? 'btn-success'
		: 'btn-default';
	echo $this->Html->link('Pending Withdrawals', 
		array('?' => array('type' => TRANSACTION_WITHDRAWAL, 'paid' => '0')), 
		array('class' => 'btn btn-small '.$pending_class)
	); 
?>
<?php
	$pending_class = isset($this->data['unprocessed']) && $this->data['unprocessed'] == false ? 'btn-success' : 'btn-default';
		echo $this->Html->link('Un-processed', array('?' => array('unprocessed' => false)), array('class' => 'btn btn-small ' . $pending_class)
	);
?> 
<?php 
	echo $this->Html->link('Gift Points', 
		array('action' => 'add'), 
		array('class' => 'btn btn-small btn-default')
	); 
?> 
<?php 
	echo $this->Html->link('Mass Incentive', 
		array('action' => 'mass_add'), 
		array('class' => 'btn btn-small btn-default')
	); 
?> 
<?php 
	echo $this->Html->link('Fix Project Payouts', 
		array('action' => 'fix'), 
		array('class' => 'btn btn-small btn-default')
	); 
?>
<?php if (isset($is_user_filter) && $is_user_filter == true): ?>
	<?php 
		echo $this->Form->submit('Inspect User Survey Takes', 
			array(
				'class' => 'btn btn-small btn-success', 
				'div' => false, 
				'rel' => 'tooltip',
				'data-original-title' => 'This will do an automated deep dive into a user\'s history and try to find irregularities.'
			)
		); 
	?> 
<?php endif; ?></p>
<?php echo $this->Form->end(null); ?>

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
						<?php echo $this->Form->input('type', array(
							'id' => 'transaction-type',
							'type' => 'select', 
							'class' => 'uniform',
							'label' => '&nbsp;',
							'empty' => 'All types',
							'options' => unserialize(TRANSACTION_TYPES),
							'value' => isset($this->data['type']) ? $this->data['type']: null
						)); ?>
					</div>
					<div class="filter">
						<?php echo $this->Form->input('paid', array(
							'id' => 'transaction-paid',
							'type' => 'select', 
							'class' => 'uniform',
							'label' => '&nbsp;',
							'empty' => 'All transactions',
							'options' => array(
								'0' => 'Pending Transactions',
								'1' => 'Approved Transactions',
								'2' => 'Rejected Transactions'
							),
							'value' => isset($this->data['paid']) ? $this->data['paid']: null
						)); ?>
					</div>
					
					<?php if (isset($this->request->query['type']) && $this->request->query['type'] == TRANSACTION_WITHDRAWAL): ?>
						<div class="filter">
							<?php echo $this->Form->input('payment_method', array(
								'id' => 'transaction-payment_method',
								'type' => 'select',
								'class' => 'uniform',
								'label' => '&nbsp;',
								'empty' => 'All payment methods',
								'options' => unserialize(PAYMENT_METHODS),
								'value' => isset($this->data['payment_method']) ? $this->data['payment_method'] : null
							)); ?>
						</div>
					<?php endif; ?>
					
					<div class="filter">
					<?php echo $this->Form->input('user', array(
						'placeholder' => 'Email address or #userid',
						'value' => isset($this->data['user']) ? $this->data['user']: null
					)); ?>
					</div>
				
					<div class="filter date-group">
						<label>Transaction amount between:</label>
						<?php echo $this->Form->input('amount_from', array(
							'label' => false, 
							'class' => 'amount',
							'value' => isset($this->data['amount_from']) ? $this->data['amount_from']: null
						)); ?> 
						<?php echo $this->Form->input('amount_to', array(
							'label' => false, 
							'class' => 'amount',
							'value' => isset($this->data['amount_to']) ? $this->data['amount_to']: null
						)); ?>
					</div>
					
					<div class="filter date-group">
						<label>Transaction date between:</label>
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

<?php if (isset($transactions)): ?>
	
	<p class="count">Showing <?php 
		echo number_format($this->Paginator->counter(array('format' => '{:current}')));
	?> of <?php
		echo number_format($this->Paginator->counter(array('format' => '{:count}')));
	?> matches 
		<?php if (isset($sums) && isset($sums[0]['sum_amount'])): ?>
			<em><strong>(Total Sum: <?php echo $this->App->dollarize(abs(round($sums[0]['sum_amount'] / 100))); ?>)</em></strong>
		<?php endif; ?>
	</p>

	<?php 
		$STATUSES = unserialize(TRANSACTION_STATUSES); 
		$running_total = 0;
	?>
	<?php echo $this->Form->create('Transaction'); ?>
	<div class="box">	
		<table cellpadding="0" cellspacing="0" class="table table-normal">
			<thead>
				<tr>
					<td class="checkbox"><?php echo $this->Form->input('null', array(
						'type' => 'checkbox', 
						'label' => false,
						'onclick' => 'return toggleChecked(this.checked)'
					)); ?></td>
					<td class="status"></td>
					<td><?php echo $this->Paginator->sort('User.email', 'User'); ?></td>
					<?php if (isset($this->request->query['type']) && $this->request->query['type'] == TRANSACTION_WITHDRAWAL): ?>
						<td>User Score</td>
					<?php else: ?>
						<td><?php echo $this->Paginator->sort('linked_to_id'); ?></td>
						<td><?php echo $this->Paginator->sort('name', 'Description'); ?></td>
					<?php endif; ?>
					<td>Note</td>
					<td><?php echo $this->Paginator->sort('amount', 'Amount'); ?></td>
					<td><?php echo $this->Paginator->sort('executed'); ?></td>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($transactions as $transaction): ?>
					<?php
						$convert_to_ustime = $this->Time->format(strtotime($transaction['Transaction']['created']), Utils::dateFormatToStrftime(DB_DATETIME), false, 'America/Los_Angeles');
						$x_business_day = $this->Calculator->get_business_date_after_x_days($convert_to_ustime, 5);

						$given = new DateTime($x_business_day, new DateTimeZone('America/Los_Angeles'));
						$given->setTimezone(new DateTimeZone('UTC'));
						$transaction_date_utc = $given->format("Y-m-d H:i:s");
					?>
					<?php $past_due = ($transaction['Transaction']['status'] == TRANSACTION_PENDING && $transaction['Transaction']['type_id'] == TRANSACTION_WITHDRAWAL && strtotime($transaction_date_utc) < strtotime(date(DB_DATE))); ?>
					<tr class="<?php echo $transaction['Transaction']['status'] == TRANSACTION_REJECTED ? 'rejected muted': '';
						echo $past_due ? 'past-due-row' : ''?>">
						<td class="checkbox"><?php echo $this->Form->input('Transaction.'.$transaction['Transaction']['id'], array(
							'label' => false,
							'type' => 'checkbox',
							'disabled' => ($transaction['PaymentMethod']['payment_method'] == 'mvpay' && !empty($master_dwolla_error)) ? true: false,
						)); ?></td>	
						<td class="status"><?php 
							$status = $transaction['Transaction']['status']; 
							if ($status == TRANSACTION_APPROVED) {
								$label = 'label-green';							
								if (!$transaction['Transaction']['paid']) {
									$status = TRANSACTION_PENDING;
									$label = '';
								}
							}
							elseif ($status == TRANSACTION_REJECTED) {
								$label = 'label-red';
								$status = TRANSACTION_REJECTED;
							}
							else {
								$label = '';
							}
							echo '<span class="'.(!empty($label) ? 'label '.$label: '').' label-transaction">'.$STATUSES[$status].'</span>'; 
							if ($status == TRANSACTION_APPROVED && $transaction['Transaction']['type'] == TRANSACTION_WITHDRAWAL && $transaction['Transaction']['payout_processed'] == PAYOUT_FAILED) {
								echo '<br/><span class = "muted">Failed</span>';
							}
						?>
					
						</td>
						<td>
							<?php echo $this->Element('user_dropdown', array('user' => $transaction['User'])); ?>
							<?php echo $this->Element('user_delete_flag', array('deleted' => $transaction['User']['deleted_on']));?>
							<small><?php echo $transaction['User']['email']; ?></small>
						</td>
						<?php if (isset($this->request->query['type']) && $this->request->query['type'] == TRANSACTION_WITHDRAWAL) : ?>
							<td>
								<?php if (isset($transaction['UserAnalysis'][0])): ?>
									<?php
										echo $this->Html->link($transaction['UserAnalysis'][0]['score'], 
											array('controller' => 'users', 'action' => 'quickscores', $transaction['User']['id']), 
											array(
												'data-target' => '#modal-user-scores',
												'data-toggle' => 'modal', 
											)
										); 
									?>
								<?php endif; ?>
							</td>
						<?php else: ?>
						<td><?php 
							if ($transaction['Transaction']['type_id'] == TRANSACTION_SURVEY || $transaction['Transaction']['type_id'] == TRANSACTION_SURVEY_NQ) {
								echo $this->Html->link('#'.$transaction['Transaction']['linked_to_id'], array('controller' => 'surveys', 'action' => 'dashboard', $transaction['Transaction']['linked_to_id'])).': '.$transaction['Transaction']['linked_to_name']; 
							}
							elseif ($transaction['Transaction']['type_id'] == TRANSACTION_POLL) {
								echo 'Poll: '.$transaction['Transaction']['linked_to_name']; 
							}
							elseif ($transaction['Transaction']['type_id'] == TRANSACTION_POLL_STREAK) {
								echo 'Poll Streak at: '.$transaction['Transaction']['linked_to_name'];
							}
							elseif ($transaction['Transaction']['type_id'] == TRANSACTION_OFFER) {
								echo 'Offer: '.$this->Html->link('#'.$transaction['Transaction']['linked_to_id'], array('controller' => 'offers', 'action' => 'edit', $transaction['Transaction']['linked_to_id'])).': '.$transaction['Transaction']['linked_to_name']; 
							}
							elseif ($transaction['Transaction']['type_id'] == TRANSACTION_CODE) {
								echo 'Promo Code: '.$transaction['Transaction']['linked_to_name']; 
							}
							elseif ($transaction['Transaction']['type_id'] == TRANSACTION_PROFILE) {
								if (empty($transaction['Transaction']['linked_to_id'])) {
									echo 'Registration bonus';
								}
							}
							elseif ($transaction['Transaction']['type_id'] == TRANSACTION_REFERRAL) {
								echo 'Referral: '.$this->Html->link(
									$transaction['Transaction']['referer_username'],
									array('?' => array('user' => '#'.$transaction['Transaction']['referrer_id'])), 
									array('escape' => false)
								). ' ('.$transaction['Transaction']['linked_to_name'].')'; 
							}
						?></td>
						<td><?php echo $transaction['Transaction']['name']; ?></td>
						<?php endif; ?>
						<td>
							<?php if ($past_due):
									echo '<strong class="text-error">Past Due</strong><br/>';
								endif;
							?>
						
							<?php if (empty($transaction['Transaction']['note']) && (time() - strtotime($transaction['Transaction']['created'])) < 3600) : ?>
								<?php echo $transaction['Transaction']['name']; ?><br/><span class="muted">Analysis not run on this withdrawal yet</span>
							<?php else: ?>
								<?php echo $transaction['Transaction']['name']; ?><br/><span class="text-error"><?php echo nl2br($transaction['Transaction']['note']); ?></span>
							<?php endif; ?>
						</td>
						<td><?php 						
							if ($transaction['Transaction']['type_id'] == TRANSACTION_WITHDRAWAL) {
								$amount = '$'.number_format(round(-1 * $transaction['Transaction']['amount'] / 100, 2), 2);
							}
							else {
								$amount = $transaction['Transaction']['amount'];
							}
							if ($status == TRANSACTION_APPROVED && $transaction['Transaction']['type_id'] != TRANSACTION_WITHDRAWAL) {
								$running_total = $running_total + $transaction['Transaction']['amount'];
							}
							echo $this->App->negatize($amount, $transaction['Transaction']['amount']); 
						?></td>
						<td>
							<?php if ($past_due):
									echo '<strong class="text-error">Past Due</strong><br/>';
								endif;
							?>
							<?php echo $this->Time->format($transaction['Transaction']['executed'], Utils::dateFormatToStrftime('Y-m-d h:i A'), false, $timezone); ?>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php if (isset($is_user_filter) && $is_user_filter === true): ?>
					<tr>
						<td colspan="6" class="total">Sum of non-withdrawal approved transactions:</td>
						<td><?php echo $this->App->negatize(abs($running_total), $running_total); ?></td>
						<td></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
		<div class="form-actions">
			<?php echo $this->Form->submit('Payout', array(
				'name' => 'approve', 
				'rel' => 'tooltip', 
				'data-original-title' => 'Withdrawals will be immediately paid - and other transactions will be cleared from their pending state', 
				'class' => 'btn btn-success'
			)); ?> 
			<?php echo $this->Form->submit('Reject', array(
				'rel' => 'tooltip', 
				'data-original-title' => 'Rejecting transactions does not return points to the user - rejected transactions remain in the transaction history', 
				'name' => 'reject', 'class' => 'btn btn-danger'
			)); ?> 
			<?php echo $this->Form->submit('Delete', array(
				'name' => 'delete', 
				'class' => 'btn btn-warning',
				'rel' => 'tooltip', 
				'data-original-title' => 'Deleting transactions removes the transaction from the user history and recalculates the user balance', 
			)); ?>	
			<?php echo $this->Form->submit('Hellban + Reject', array(
				'name' => 'hellban', 'class' => 'btn btn-danger'
			)); ?> 
		</div>
	</div>
	<?php echo $this->Form->end(null); ?>
	<?php if ($transactions) : ?>
		<?php foreach ($transactions as $transaction): ?>
			<?php echo $this->Element('modal_user_hellban', array('user' => $transaction['User'])); ?>
			<?php echo $this->Element('modal_user_remove_hellban', array('user' => $transaction['User'])); ?>
		<?php endforeach; ?>
	<?php endif; ?>
	<?php echo $this->Element('pagination'); ?>
	<?php echo $this->Element('modal_user_quickprofile'); ?>
	<?php echo $this->Element('modal_user_referrer'); ?>
	<?php if (isset($this->request->query['type']) && $this->request->query['type'] == TRANSACTION_WITHDRAWAL) : ?>
		<?php echo $this->Element('modal_user_score'); ?>
		<?php echo $this->Element('modal_user_scores'); ?>
	<?php endif ;?>

	<script>
		var TYPE_WITHDRAWAL = <?php echo TRANSACTION_WITHDRAWAL ?>;
		var PAY_APPROVED = '1';
		var PAY_PENDING = '0';
		var enablePaymentMethodFilter = function() {
			var type = $('#transaction-type').val(),
				paid = $('#transaction-paid').val(),
				$paymentMethod = $('#transaction-payment_method');

			if (type == TYPE_WITHDRAWAL && (paid == PAY_APPROVED || paid == PAY_PENDING)) {
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

			$('#transaction-type').change(function (e) {
				enablePaymentMethodFilter();
			});

			$('#transaction-paid').change(function (e) {
				enablePaymentMethodFilter();
			});
		});
		
		function toggleChecked(status) {
			$('tbody input[type="checkbox"]').not("[disabled]").each(function() {
				$(this).prop("checked", status);
			})
		}
	</script>
<?php else: ?>
	<div class="alert alert-info">Don't be a stupid twat like melder and hack URLs and cause the system to crash - select a search term above to see the transactions</div> 
<?php endif; ?>