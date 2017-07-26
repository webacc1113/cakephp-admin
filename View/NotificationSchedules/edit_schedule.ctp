<?php echo $this->Form->create('NotificationSchedule'); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Edit Email Schedule for user #<?php echo $user['User']['id'];?></span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="padded">
				<div class="box">
					<table cellpadding="0" cellspacing="0" class="table table-normal" id="notification_schedule_table">
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
							<tr id="schedule_row">
								<td><strong>#Emails</strong></td>
								<?php for ($i = 0; $i < 24; $i ++): ?>
									<td><?php echo $this->Form->input(str_pad($i, 2, '0', STR_PAD_LEFT), array(
										'type' => 'text',
										'label' => false,
										'div' => false
									)); ?></td>
								<?php endfor; ?>
							</tr>
						</tbody>
					</table>
				</div>	
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Save', array('class' => 'btn btn-primary')); ?>
			</div>
		</div>
	</div>
</div>