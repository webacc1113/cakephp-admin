<p>
	<?php echo $this->Html->link('User export statistics', array('controller' => 'reports', 'action' => 'user_export_statistics'), array('class' => 'btn btn-primary')); ?>
</p>
<div class="box">
	<div class="box-header">
		<span class="title">Filters</span>
		<ul class="box-toolbar">
			<li>
				<?php echo $this->Html->link('<i class="icon-remove-sign"></i> Clear filters', array('action' => 'unsuccessful'), array('escape' => false)); ?>
			</li>
		</ul>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create('Filter', array('type' => 'get', 'class' => 'filter')); ?>
			<div class="padded separate-sections">
				<div class="row-fluid">
					<div class="filter date-group">
						<label>Date</label>
						<?php
							echo $this->Form->input('date', array(
								'label' => false,
								'class' => 'datepicker',
								'data-date-autoclose' => true,
								'value' => isset($this->request->query['date']) ? $this->request->query['date'] : null
							));
						?>
					</div>
				</div>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Search', array('class' => 'btn btn-primary')); ?>
			</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>
<div class="box">
	<div class="box-header">
		<span class="title">
			Unsuccessful User exports on 
			<?php if(isset($this->request->query['date']) && !empty($this->request->query['date'])): ?>
				<?php echo date('F jS, Y', strtotime($this->request->query['date'])); ?>
			<?php else: ?>
				<?php echo date('F jS, Y', strtotime('-1 day')); ?>
			<?php endif; ?>
		</span>
	</div>
	<div class="box-content">
		<table class="table table-normal">
			<tr>
				<th><?php echo $this->Paginator->sort('partner'); ?></th>
				<th><?php echo $this->Paginator->sort('user_id'); ?></th>
				<th><?php echo $this->Paginator->sort('result_code'); ?></th>
				<th>Log</th>
				<th><?php echo $this->Paginator->sort('created'); ?></th>
			</tr>
			<?php if (isset($partner_logs) && !empty($partner_logs)): ?>
				<?php foreach ($partner_logs as $partner_log): ?>
					<tr class="error">
						<td><?php echo $partner_log['PartnerLog']['partner']; ?></td>
						<td><?php echo $this->Html->link($partner_log['PartnerLog']['user_id'], array(
							'controller' => 'users',
							'action' => 'history',
							$partner_log['PartnerLog']['user_id']
						), array(
							'target' => '_blank'
						)); ?></td>
						<td><?php echo $partner_log['PartnerLog']['result_code']; ?></td>
						<td>
							<?php echo $this->Html->link('View', array(
								'controller' => 'partner_logs',
								'action' => 'raw',
								$partner_log['PartnerLog']['id']
							), array(
								'data-target' => '#modal-partner-log', 
								'data-toggle' => 'modal'
							)); ?>
						</td>
						<td><?php echo $this->Time->format('d M, y H:i A', $partner_log['PartnerLog']['created']);?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</table>
		<div class="form-actions">
			<?php echo $this->Element('pagination'); ?>
		</div>
	</div>
</div>
<?php echo $this->Element('modal_partner_log'); ?>