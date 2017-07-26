<div class="span8">
	<div class="box">
		<div class="box-header">
			<span class="title">Filters</span>
			<ul class="box-toolbar">
				<li>
					<?php echo $this->Html->link('<i class="icon-remove-sign"></i> Clear filters', array('action' => 'lucid_epc_statistics'), array('escape' => false)); ?>
				</li>
			</ul>
		</div>
		<div class="box-content">
			<?php echo $this->Form->create(null, array('type' => 'get', 'class' => 'filter')); ?>
				<div class="padded separate-sections">
					<div class="row-fluid">
						<div class="filter date-group">
							<label>Date</label>
							<?php
								echo $this->Form->input('date', array(
									'label' => false,
									'class' => 'datepicker',
									'data-date-autoclose' => true,
									'value' => isset($this->request->query['date']) ? $this->request->query['date'] : date('m/d/Y')
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
	<?php if (isset($reported_projects) && !empty($reported_projects)): ?>
		<div class="box">
			<div class="box-header">

				<?php echo $this->Form->create('Report', array(
					'url' => array(
						'controller' => 'reports', 
						'action' => 'export_statistics_by_day'
					),
					'class' => 'pull-right',
					'style' => 'margin-top: 5px; margin-right: 5px;'
				)); ?>
					<?php echo $this->Form->input('group', array(
						'type' => 'hidden',
						'value' => $lucid_group['Group']['id']
					)); ?>
					<?php echo $this->Form->input('date', array(
						'type' => 'hidden',
						'value' => date('m/d/Y', strtotime($date))
					)); ?>
					<?php echo $this->Form->input('filter_ids', array(
						'type' => 'hidden',
						'value' => implode("\n", $project_ids)
					)); ?>
					<?php echo $this->Form->submit('Export MV Statistics', array('class' => 'btn btn-default btn-small')); ?>
				<?php echo $this->Form->end(null); ?>
				
				<span class="title">
					Lucid EPC statistics on 
					<?php if (isset($this->request->query['date']) && !empty($this->request->query['date'])): ?>
						<?php echo date('F jS, Y', strtotime($this->request->query['date'])); ?>
					<?php endif; ?>
				</span>
			</div>
			<div class="box-content">
				<table class="table table-normal">
					<tr>
						<th>Project ID</th>
						<th>Lucid ID</th>
						<th>Client Rate</th>
						<th>Implied IR*</th>
						<th>Trailing EPC Cents (Highest)</th>
					</tr>
					<?php foreach ($reported_projects as $lucid_epc_statistic): ?>
						<tr>
							<td>
								<?php
								echo $this->Html->link($lucid_epc_statistic['LucidEpcStatistic']['project_id'], array(
									'controller' => 'surveys',
									'action' => 'dashboard',
									$lucid_epc_statistic['LucidEpcStatistic']['project_id']
										), array(
									'target' => '_blank'
								));
								?>
							</td>
							<td>
								<?php echo $lucid_epc_statistic['Project']['mask']; ?>
							</td>
							<td>$<?php echo number_format($lucid_epc_statistic['Project']['client_rate'], 2); ?></td>
							<td>
								<?php
									$ir = $lucid_epc_statistic['LucidEpcStatistic']['trailing_epc_cents'] / ( 100 * $lucid_epc_statistic['Project']['client_rate']); 
									$ir = round($ir * 100);
								?>
								<?php echo $ir; ?>%
							</td>
							<td>$<?php echo number_format($lucid_epc_statistic['LucidEpcStatistic']['trailing_epc_cents'] / 100, 2); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
		</div>
		<p>* This may be inaccurate based on different CPIs for different suppliers, as well as changing CPIs throughout a project's lifetime</p>
	<?php endif; ?>
</div>
<div class="span4">
	<div class="box">
		<div class="box-header">
			<span class="title">Understanding the Lucid EPC Report</span>
		</div>
		<div class="box-content">
			<div class="padded">
				<p>This report - takes on a given day - the highest Lucid trailing EPC associated with a project that MintVine had no clicks or completes on.</p>
				<p>The report will pull only MV project data for the day; and excludes MV projects that performed well on previous days.</p>
				<p>The EPC threshold for a "good" project is set to <strong>$<?php echo $epc_threshold; ?></strong>.</p>
				<P>These projects performed well for other suppliers on the exchange, but did not for us. These projects should be examined on a project-by-project basis to see how to improve targeting and exposure.</p>
			</div>
		</div>
	</div>
</div>