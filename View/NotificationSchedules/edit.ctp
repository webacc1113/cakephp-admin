<?php echo $this->Form->create('NotificationTemplate'); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Edit Notification Template</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="padded">
				<?php echo $this->Form->input('name', array('label' => 'Name')); ?>
				<?php echo $this->Form->input('description', array('type' => 'text')); ?>
				<?php echo $this->Form->input('key', array('after' => '<small class="muted">Used in the code to retrieve this record; do not modify</small>', 'readonly' => 'readonly')); ?>
				<div class="box">
				<table cellpadding="0" cellspacing="0" class="table table-normal">
					<thead>
						<tr>
							<td>GMT</td>
							<?php for ($i = 0; $i < 24; $i++): ?>
								<td><?php echo str_pad($i, 2, '0', STR_PAD_LEFT); ?>:00</td>
							<?php endfor; ?>
						</tr>
						<tr>
							<td>Local</td>
							<?php for ($i = 0; $i < 24; $i++): ?>
								<?php 
									$local_offset = $i + $hour_offset; 
									if ($local_offset < 0) {
										$local_offset = $local_offset + 24;
									}
									if ($local_offset >= 24) {
										$local_offset = $local_offset - 24;
									}
								?>
								<td><?php echo date("H:i", $local_offset * 3600); ?></td>
							<?php endfor; ?>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>#Emails</td>
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
			<?php echo $this->Html->link('Cancel', array('action' => 'index'), array('class' => 'btn btn-default'));?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>