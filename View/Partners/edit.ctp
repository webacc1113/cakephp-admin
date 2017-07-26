<?php echo $this->Form->create(); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Edit Partner</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span6">
				<div class="padded">
					<?php echo $this->Form->input('partner_name', array('label' => 'Partner Name'));?>
					<?php echo $this->Form->input('notes', array('rows' => 3, 'label' => 'Notes'));?>
					<?php echo $this->Form->input('complete_url'); ?>
					<?php echo $this->Form->input('nq_url'); ?>
					<?php echo $this->Form->input('oq_url'); ?>
					<?php echo $this->Form->input('id', array('type' => 'hidden'));?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Save', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>