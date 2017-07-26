<style type="text/css">
	span.label {
		font-weight: normal;
		text-transform: uppercase;
	}
	table td.id {
		width: 20px;
	}
	table tr.closed {
		color: #999;
	}
</style>
<h3>Advertising Spend</h3>
<div class="row-fluid">
	<div class="span8">
		<p><?php echo $this->Html->link('New Advertising Spend', array('action' => 'add'), array('class' => 'btn btn-mini btn-success')); ?></p>
	</div>
</div>
<div class="box">
	<div class="box-header">
		<span class="title">Filters</span>
		<ul class="box-toolbar">
			<li>
				<?php echo $this->Html->link('<i class="icon-remove-sign"></i> Clear filters', array('action' => 'index'), array('escape' => false)); ?>
			</li>
		</ul>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create('AdvertisingSpend', array('type' => 'get', 'class' => 'filter')); ?>
			<div class="padded separate-sections">
				<div class="row-fluid">
					<div class="filter date-group">
						<label>Advertising spend date between:</label>
						<?php echo $this->Form->input('date_from', array(
							'label' => false, 
							'class' => 'datepicker',
							'data-date-autoclose' => true,
							'placeholder' => 'Start date',
							'value' => isset($this->data['date_from']) ? $this->data['date_from']: null
						)); ?> 
						<?php echo $this->Form->input('date_to', array(
							'label' => false, 
							'class' => 'datepicker',
							'placeholder' => 'End date',
							'data-date-autoclose' => true,
							'value' => isset($this->data['date_to']) ? $this->data['date_to']: null
						)); ?>
					</div>
					<div class="filter">
						<label>Acquisition Partner:</label>
						<?php echo $this->Form->input('acquisition_partner_id', array(
							'class' => 'uniform',
							'label' => false,
							'required' => false,
							'div' => false,
							'empty' => 'All',
							'value' => isset($this->data['acquisition_partner_id']) ? $this->data['acquisition_partner_id']: null,
							'options' => $acquisition_partners
						)); ?>
					</div>
                     <?php echo $this->Form->input('country', array(
                         'type' => 'select',
                         'label' => 'Country',
                         'options' => array_merge(array('' => 'All'), $countries),
                         'value' => (isset($this->data['country']) ? $this->data['country'] : null),
                     )); ?>
                     </div>
				</div>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Search', array('class' => 'btn btn-primary')); ?>
			</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>
<div class="row-fluid">
	<div class="span8">
		<p class="count">Showing <?php
			echo number_format($this->Paginator->counter(array('format' => '{:current}')));
			?> of <?php
			echo number_format($this->Paginator->counter(array('format' => '{:count}')));
			?> matches
		</p>
	</div>
</div>
<?php echo $this->Form->create('AdvertisingSpend'); ?>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<?php if (!empty($advertising_spends)): ?>
				<td class="checkbox"><?php
					echo $this->Form->input('null', array(
						'type' => 'checkbox',
						'label' => false,
						'onclick' => 'return toggleChecked(this.checked)'
					));
				?></td>
				<?php endif; ?>
				<td><?php echo $this->Paginator->sort('AcquisitionPartner.name', 'Partner Name'); ?></td>
				<td><?php echo $this->Paginator->sort('AdvertisingSpend.country', 'Country'); ?></td>
				<td><?php echo $this->Paginator->sort('AdvertisingSpend.date', 'Date'); ?></td>
				<td><?php echo $this->Paginator->sort('AdvertisingSpend.spend', 'Spend'); ?></td>
				<td></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($advertising_spends as $advertising_spend): ?>
				<tr>
					<td class="checkbox"><?php
						echo $this->Form->input('AdvertisingSpend.' . $advertising_spend['AdvertisingSpend']['id'], array(
							'label' => false,
							'type' => 'checkbox'
						));
					?></td>	
					<td><?php echo $advertising_spend['AcquisitionPartner']['name']; ?></td>
					<td><?php echo $advertising_spend['AdvertisingSpend']['country']; ?></td>
					<td><?php echo date('m/d/Y', strtotime($advertising_spend['AdvertisingSpend']['date'])); ?></td>
					<td>
						$<?php echo number_format((float)$advertising_spend['AdvertisingSpend']['spend'], 2, '.', ''); ?>
					</td>
					<td>
						<?php echo $this->Html->link('Edit', array('action' => 'edit', $advertising_spend['AdvertisingSpend']['id']), array('class' => 'btn btn-mini btn-primary')); ?> 
					</td>					
				</tr>
			<?php endforeach;?>
		</tbody>
		<tfoot>
			<tr>
				<td colspan="4"><b>Total</b></td>
				<td colspan="2"><b>$<?php echo number_format((float)$spend, 2, '.', ''); ?></b></td>
			</tr>
		</tfoot>
	</table>
	<div class="form-actions">
		<?php if (!empty($advertising_spends)): ?>
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
<?php echo $this->Form->end(null); ?>
<?php echo $this->Element('pagination'); ?>
