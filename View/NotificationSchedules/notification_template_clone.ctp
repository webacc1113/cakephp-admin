<?php echo $this->Form->create('NotificationTemplate'); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Create Notification Template</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="padded">
				<?php echo $this->Form->input('name', array('value' => '', 'label' => 'Name')); ?>
				<?php echo $this->Form->input('description', array('value' => '', 'type' => 'text')); ?>
				<?php echo $this->Form->input('key', array('value' => '', 'after' => '<small class="muted">Used in the code to retrieve this record; do not modify</small>')); ?>
				<p>Time in GMT</p>
				<div class="box">
				<table cellpadding="0" cellspacing="0" class="table table-normal">
					<thead>
						<tr>
							<?php for ($i = 0; $i < 24; $i++): ?>
								<td><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>:00</td>
							<?php endfor; ?>
						</tr>
					</thead>
					<tbody>
						<tr>
							<?php for ($i = 0; $i < 24; $i++): ?>
								<td><?php echo $this->Form->input(str_pad($i, 2, '0', STR_PAD_LEFT), array(
									'type' => 'text',
									'label' => false,
									'div' => false
								)); ?></td>
							<?php endfor; ?>
						</tr>
					</tbody>
				</table></div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Save', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>