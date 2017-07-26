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
<h3>Groups</h3>
<div class="row-fluid">
	<div class="span8">
		<p><?php echo $this->Html->link('New Group', array('action' => 'add'), array('class' => 'btn btn-mini btn-success')); ?></p>
		<p class="count">Showing <?php
			echo number_format($this->Paginator->counter(array('format' => '{:current}')));
			?> of <?php
			echo number_format($this->Paginator->counter(array('format' => '{:count}')));
			?> matches
		</p>
	</div>
</div>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td><?php echo $this->Paginator->sort('Group.name', 'Group Name'); ?></td>
				<td>Key</td>				
				<td>prefix</td>				
				<td>Router Priority</td>
				<td>Max LOI</td>
				<td>Max Clicks with no Completes</td>
				<td>EPC Floor</td>
				<td>Performance Checks</td>				
				<td>Use Mask</td>		
				<td>Calculate Margin</td>		
				<td>Filter Panelists</td>
				<td>Check Links</td>
				<td></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($groups as $group): ?>
				<tr>
					<td><?php echo $group['Group']['name'];?></td>
					<td><?php echo $group['Group']['key'];?></td>
					<td><?php echo $group['Group']['prefix'];?></td>
					<td><?php echo $group['Group']['router_priority'];?></td>
					<td><?php echo $group['Group']['max_loi_minutes'];?></td>
					<td><?php echo $group['Group']['max_clicks_with_no_completes'];?></td>
					<td><?php 
						if (!empty($group['Group']['epc_floor_cents'])) {
							echo '$'.number_format($group['Group']['epc_floor_cents'] / 100, 2); 
						}
					?></td>
					<td><?php echo $group['Group']['performance_checks'];?></td>
					<td><?php echo ($group['Group']['use_mask']) ? 'Yes' : 'No';?></td>
					<td><?php echo ($group['Group']['calculate_margin']) ? 'Yes' : 'No';?></td>
					<td><?php echo ($group['Group']['filter_panelists']) ? 'Yes' : 'No';?></td>
					<td><?php echo ($group['Group']['check_links']) ? 'Yes' : 'No';?></td>
					<td>
						<?php echo $this->Html->link('Edit', array('action' => 'edit', $group['Group']['id']), array('class' => 'btn btn-mini btn-primary')); ?> 
					</td>					
				</tr>
			<?php endforeach;?>
		</tbody>
	</table>
</div>
		