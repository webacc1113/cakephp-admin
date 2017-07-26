<?php echo $this->Form->create(); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Manually Send Payment</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span4">
				<div class="padded">
					<?php echo $this->Form->input('email', array(
						'label' => 'User Email',
						'after' => '<br /><small>Card can only be sent to registered active users</small>'
					)); ?>
					<?php echo $this->Form->input('payment_method', array(
							'type' => 'select',
							'class' => 'uniform',
							'empty' => 'Select Payment Method',
							'options' => $payment_methods,
							'onchange' => 'return selectPaymentMethod()'
						)); ?>
					<?php echo $this->Form->input('tangocard', array(
						'div' => array( 'id' => 'WithdrawalTangocard_wrapper' ),
						'type' => 'select', 
						'class' => 'uniform',
						'empty' => 'Select',
						'options' => $tangocards,
						'onchange' => 'return MintVine.TangocardAmount(this)',
						'after' => ' <span id="waiting" style="display: none;">Loading... please wait</span>'
					)); ?>
					<?php echo $this->Form->input('amount', array('label' => 'Amount in Cents', 'type' => 'text')); ?>
					<?php echo $this->Form->input('description', array('type' => 'text')); ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Send', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>

<script type="text/javascript">
	function selectPaymentMethod() {
		var $payment_method_node = $('select[name="data[Withdrawal][payment_method]"]'),
			$tangocard_node = $('select[name="data[Withdrawal][tangocard]"]');

		if ($payment_method_node.val() === 'tango') {
			$('#WithdrawalTangocard_wrapper').show();
			MintVine.TangocardAmount($tangocard_node);
		}
		else {
			$('#WithdrawalTangocard_wrapper').hide();
			$('#WithdrawalAmount').attr('readonly', false);
			$('#WithdrawalAmount').val('');
		}
		return true;
	}
	selectPaymentMethod();
</script>