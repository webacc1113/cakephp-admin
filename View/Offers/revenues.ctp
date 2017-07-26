<h3>Offer Revenues</h3>
<div class="box">
	<div class="box-header">
		<span class="title">Filters</span>
		<ul class="box-toolbar">
			<li>
				<?php echo $this->Html->link('<i class="icon-remove-sign"></i> Clear filters', array('action' => 'revenues'), array('escape' => false)); ?>
			</li>
		</ul>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create('Filter', array('type' => 'get', 'class' => 'filter')); ?>
			<div class="padded separate-sections">
				<div class="row-fluid">
					<div class="filter date-group">
						<label>Offer date between:</label>
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
				</div>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Search', array('class' => 'btn btn-primary')); ?>
			</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>
<?php if (isset($partner_revenues)): ?>
	<div class="box">
		<table cellpadding="0" cellspacing="0" class="table table-normal">
			<thead>
				<tr>
					<td>Date</td>
					<?php foreach ($partners as $partner): ?>
						<td><?php echo ucfirst($partner); ?></td>
					<?php endforeach; ?>
					<td>&nbsp;</td>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($partner_revenues as $created_date => $revenues): ?>
					<tr>
						<td>
							<?php echo $this->Time->format($created_date, Utils::dateFormatToStrftime('m/d/Y'), false); ?>
						</td>
						<?php foreach ($partners as $partner): ?>
							<td><?php echo (isset($revenues[$partner])) ? '$' . number_format($revenues[$partner], 2) : 0; ?></td>
						<?php endforeach; ?>
						<td class="total"><?php echo '$' . number_format($line_totals[$created_date], 2); ?></td>					
					</tr>
				<?php endforeach; ?>
				<tr>
					<td colspan="<?php echo count($partners) + 2; ?>" class="total"><?php echo '$' . number_format($grand_total, 2); ?></td>
				</tr>
			</tbody>
		</table>
	</div>
<?php endif; ?>