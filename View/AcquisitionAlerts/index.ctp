<?php echo $this->Form->create('AcquisitionAlert'); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Acquisition Partner Alerts</span>
		<ul class="box-toolbar">
			<li><?php echo $this->Html->link('Create Alert', array('action' => 'add'), array('class' => 'btn btn-small btn-primary')); ?></li>
		</ul>
	</div>
	<div class="box-content">
		<table class="table table-normal">
			<tr>
				<?php if (!empty($acquisition_alerts)): ?>
				<td class="checkbox"><?php
					echo $this->Form->input('null', array(
						'type' => 'checkbox',
						'label' => false,
						'onclick' => 'return toggleChecked(this.checked)'
					));
				?></td>
				<?php endif; ?>
				<th>Name</th>
				<th>Source Mapping / Campaign</th>
				<th>Trigger</th>
				<th>Current Count</th>
				<th>Trigger Amount</th>
				<th>Retrigger Threshold (in minutes)</th>
				<th>Last Triggered</th>
				<th style="width: 120px;"></th>
			</tr>
			<?php foreach ($acquisition_alerts as $acquisition_alert) : ?>
				<tr>
					<td class="checkbox"><?php
						echo $this->Form->input('AcquisitionAlert.' . $acquisition_alert['AcquisitionAlert']['id'], array(
							'label' => false,
							'type' => 'checkbox'
						));
					?></td>	
					<td><?php echo $acquisition_alert['AcquisitionAlert']['name']; ?></td>
					<td>
						<?php if ($acquisition_alert['AcquisitionAlert']['source_id'] > 0):  ?>
							Campaign: <?php echo $acquisition_alert['Source']['name']; ?>
						<?php elseif ($acquisition_alert['AcquisitionAlert']['source_mapping_id'] > 0): ?>
							Mapping: <?php echo $acquisition_alert['SourceMapping']['name']; ?>
						<?php endif; ?>
					</td>
					<td><?php echo $events[$acquisition_alert['AcquisitionAlert']['event']]; ?></td>
					<td><?php echo $acquisition_alert['AcquisitionAlert']['amount']; ?></td>
					<td><?php echo $acquisition_alert['AcquisitionAlert']['trigger']; ?></td>
					<td><?php echo $acquisition_alert['AcquisitionAlert']['alert_threshold_minutes']; ?> minutes</td>
					<td>
						<?php if (!empty($acquisition_alert['AcquisitionAlert']['alerted'])): ?>
							<?php echo $acquisition_alert['AcquisitionAlert']['alerted']; ?>
						<?php endif; ?>
					</td>
					<td>
						<?php echo $this->Html->link('Edit', array('action' => 'edit', $acquisition_alert['AcquisitionAlert']['id']), array('class' => 'btn btn-small btn-default')); ?> 
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<div class="form-actions">
			<?php if (!empty($acquisition_alerts)): ?>
				<?php echo $this->Form->submit('Delete', array(
					'name' => 'delete',
					'class' => 'btn btn-danger',
					'rel' => 'tooltip',
					'data-original-title' => 'Clicking this button will delete the selected records, This is IRREVERSIBLE.',
				));
				?>
			<?php endif; ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>