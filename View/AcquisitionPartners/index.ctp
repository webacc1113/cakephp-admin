<?php echo $this->Form->create('AcquisitionPartner'); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Acquisition Partners</span>
		<ul class="box-toolbar">
			<li><?php echo $this->Html->link('Create Partner', array('action' => 'add'), array('class' => 'btn btn-small btn-primary')); ?></li>
		</ul>
	</div>
	<div class="box-content">
		<table class="table table-normal">
			<tr>
				<?php if (!empty($acquisition_partners)): ?>
					<td class="checkbox"><?php
						echo $this->Form->input('null', array(
							'type' => 'checkbox',
							'label' => false,
							'onclick' => 'return toggleChecked(this.checked)'
						));
					?></td>
				<?php endif; ?>
				<th>Name</th>
				<th>Pixels?</th>
				<th>Affiliate Network?</th>
				<th>Key</th>
				<th></th>
			</tr>
			<?php foreach ($acquisition_partners as $acquisition_partner) : ?>
				<tr>
					<td class="checkbox"><?php
						echo $this->Form->input('AcquisitionPartner.' . $acquisition_partner['AcquisitionPartner']['id'], array(
							'label' => false,
							'type' => 'checkbox'
						));
					?></td>
					<td><?php echo $acquisition_partner['AcquisitionPartner']['name']; ?></td>
					<td><?php echo !empty($acquisition_partner['AcquisitionPartner']['post_registration_pixel']) ? 'Y': 'N'; ?></td>
					<td><?php echo !empty($acquisition_partner['AcquisitionPartner']['affiliate_network']) ? 'Y': 'N'; ?></td>
					<td class="muted"><?php echo $acquisition_partner['AcquisitionPartner']['source']; ?></td>
					<td><?php echo $this->Html->link('Edit', array('action' => 'edit', $acquisition_partner['AcquisitionPartner']['id']), array('class' => 'btn btn-mini btn-default')); ?></td>
				</tr>
			<?php endforeach; ?>
		</table>
		<div class="form-actions">
			<?php if (!empty($acquisition_partners)): ?>
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
<?php echo $this->Element('modal_delete_acquisition_partner');?>