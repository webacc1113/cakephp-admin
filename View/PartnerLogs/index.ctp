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
		<?php echo $this->Form->create('PartnerLog', array('type' => 'get', 'class' => 'filter', 'url' => array('action' => 'index'))); ?>
			<div class="padded separate-sections">
				<div class="row-fluid">
					<div class="filter">
						<label>&nbsp;</label>
						<?php echo $this->Form->input('partner', array(
							'label' => false,
							'class' => 'uniform',
							'empty' => 'Select Partner',
							'options' => array('cint' => 'Cint', 'precision' => 'Precision', 'ssi' => 'SSI', 'toluna' => 'Toluna'),
							'selected' => !empty($this->request->query['partner']) ? urldecode($this->request->query['partner']) : '',
							'div' => false					
						));?>	
					</div>
					<div class="filter">
						<label>Status</label>
						<?php echo $this->Form->input('status', array(
							'label' => false,
							'class' => 'uniform',
							'empty' => 'Select',
							'options' => array('error' => 'Failed', 'success' => 'Completed'),
							'selected' => !empty($this->request->query['status']) ? urldecode($this->request->query['status']) : '',
							'div' => false					
						));?>	
					</div>
					<div class="filter">
					<?php echo $this->Form->input('keyword', array(
						'placeholder' => 'Search keyword',
						'value' => isset($this->request->query['keyword']) ? $this->request->query['keyword']: null
					)); ?>
					</div>
					<div class="filter date-group">
						<label>Logs between:</label>
						<?php echo $this->Form->input('date_from', array(
							'label' => false, 
							'class' => 'datepicker',
							'data-date-autoclose' => true,
							'placeholder' => 'Start date',
							'value' => isset($this->request->query['date_from']) ? $this->request->query['date_from']: null
						)); ?> 
						<?php echo $this->Form->input('date_to', array(
							'label' => false, 
							'class' => 'datepicker',
							'data-date-autoclose' => true,
							'placeholder' => 'End date',
							'value' => isset($this->request->query['date_to']) ? $this->request->query['date_to']: null
						)); ?>
					</div>
				</div>
							
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Search', array(
					'class' => 'btn btn-primary', 
					'onclick' => 'return Chart.searchFilter()'
				)); ?>
			</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>
<div class="box">
	<div class="box-header">
		<span class="title">Partner Logs</span>
	</div>
	<div class="box-content">
		<table class="table table-normal">
			<tr>
				<th>Partner</th>
				<th>User Id</th>
				<th>Response Code</th>
				<th>Log</th>
				<th>Date</th>
			</tr>
			<?php if (isset($partner_logs) && !empty($partner_logs)): ?>
				<?php foreach ($partner_logs as $partner_log): ?>
					<tr class="<?php echo !empty($partner_log['PartnerUser']['id']) ? 'success' : '' ;?>">
						<td><?php echo $partner_log['PartnerLog']['partner']; ?></td>
						<td><?php echo $this->Html->link($partner_log['PartnerLog']['user_id'], array(
							'controller' => 'users',
							'action' => 'history',
							$partner_log['PartnerLog']['user_id']
						), array(
							'target' => '_blank'
						)); ?></td>
						<td><?php echo $partner_log['PartnerLog']['result_code'];?></td>
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
						<td><?php echo $this->Time->format('d M, y H:i A', $partner_log['PartnerLog']['modified']);?></td>
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