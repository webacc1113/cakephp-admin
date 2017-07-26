<?php echo $this->Form->create(); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Manually Send Tango Cards</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span4">
				<div class="padded">
					<?php echo $this->Form->input('email', array(
						'label' => 'User Email',
						'after' => '<br /><small>Card can only be sent to registered active users</small>'
					)); ?>
					<?php echo $this->Form->input('tangocard', array(
						'type' => 'select', 
						'options' => $tangocards,
						'empty' => 'Select',
						'onchange' => 'return MintVine.TangocardValue(this)',
						'after' => ' <span id="waiting" style="display: none;">Loading... please wait</span>'
					)); ?>
					<?php echo $this->Form->input('amount', array('type' => 'text')); ?>
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