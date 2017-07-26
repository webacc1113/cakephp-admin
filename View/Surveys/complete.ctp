<?php echo $this->Form->create('Project'); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Set Hashes from Client</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span4">
				<div class="padded">
					<?php echo $this->Form->input('hashes', array(
						'type' => 'textarea',
						'placeholder' => 'Hashes should begin with '.$project['Project']['id'],
						'label' => 'Input complete hashes from client, one per line'
					)); ?>
					<?php if ($completes && count($completes) > 0): ?>
						<?php echo $this->Form->input('clear', array(
							'type' => 'checkbox',
							'label' => 'Delete previously imported <strong>'.count($completes).'</strong> completes'
						)); ?>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Set Hashes from Client', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?Php echo $this->Form->end(null); ?>