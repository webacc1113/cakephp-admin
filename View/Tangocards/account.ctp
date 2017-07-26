<h3>Tango Card Account</h3>
<p>
	<?php echo $this->Html->link('Register credit card', array('action' => 'add_cc'), array('class' => 'btn btn-mini btn-success')); ?> 
	<?php echo $this->Html->link('Fund account', array('action' => 'fund_cc'), array('class' => 'btn btn-mini btn-success')); ?> 
	<?php echo $this->Html->link('Tango cards', array('action' => 'index'), array('class' => 'btn btn-mini btn-success')); ?> 
</p>
<div class="box">
	<div class="padded">
		<?php if (isset($account)): ?>
			<dl>
				<dt>Customer</dt>
				<dd><?php echo $account->customer; ?></dd>
				<dt>Identifier</dt>
				<dd><?php echo $account->identifier; ?></dd>
				<dt>email</dt>
				<dd><?php echo $account->email; ?></dd>
				<dt>Available balance</dt>
				<dd>$<?php echo round($account->available_balance / 100, 2); ?></dd>
				<?php if (isset($active_date)): ?>
					<dt>Active date</dt>
					<dd><?php echo Utils::change_tz_from_utc(date(DB_DATETIME, $active_date), 'F jS, Y H:i:s'); ?></dd>
				<?php endif; ?>
			</dl>
		<?php endif; ?>
	</div>
</div>