<?php if (isset($saved) && $saved > 0): ?>
	<div class="alert alert-success"><?php echo $saved. ' User(s) filtered.';?></div>
<?php endif; ?>

<?php if (isset($errors) && count($errors) > 0): ?>
	<div class="alert alert-danger">
		The following Users could not be filtered. <br />
		<pre><?php print_r($errors); ?></pre>
	</div>
<?php endif; ?>
<div class="row-fluid">
	<div class="span8">
		<p><?php echo $this->Html->link('View Filtered Panelists', array('action' => 'index'), array('class' => 'btn btn-success')); ?></p>
	</div>
</div>
<?php echo $this->Form->create(); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Filter Panelists</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span4">
				<div class="padded">
					<?php echo $this->Form->input('partner', array(
							'type' => 'select',
							'options' => $groups,
							'empty' => 'Select partner',
							'required' => true
					)); ?>
					<?php echo $this->Form->input('user_ids', array(
						'type' => 'textarea',
						'after' => '<small>Can be MintVine user_id or Survey hash, enter one per line.</small>'
					)); ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Filter panelists', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>