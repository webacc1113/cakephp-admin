<h3>Filtered Panelists</h3>
<div class="row-fluid">
	<div class="span8">
		<p><?php echo $this->Html->link('Filter Panelists', array('action' => 'add'), array('class' => 'btn btn-danger')); ?></p>
		<p class="count">Showing <?php
			echo number_format($this->Paginator->counter(array('format' => '{:current}')));
			?> of <?php
			echo number_format($this->Paginator->counter(array('format' => '{:count}')));
			?> matches
		</p>
	</div>
</div>
<?php echo $this->Form->create('Page'); ?>
<div class="row-fluid">
	<div class="span6">
		<div class="box">
			<table cellpadding="0" cellspacing="0" class="table table-normal">
				<thead>
					<tr>
						<?php if (!empty($filtered_panelists)): ?>
							<td class="checkbox"><?php
								echo $this->Form->input('null', array(
									'type' => 'checkbox',
									'label' => false,
									'onclick' => 'return toggleChecked(this.checked)'
								));
							?></td>
						<?php endif; ?>
						<td><?php echo $this->Paginator->sort('partner'); ?></td>
						<td><?php echo $this->Paginator->sort('user_id'); ?></td>
						<td><?php echo $this->Paginator->sort('created'); ?></td>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($filtered_panelists as $panelist): ?>
						<tr>
							<td class="checkbox"><?php
								echo $this->Form->input('FilteredPanelist.' . $panelist['FilteredPanelist']['id'], array(
									'label' => false,
									'type' => 'checkbox'
								));
							?></td>	
							<td><?php echo $panelist['FilteredPanelist']['partner'];?></td>
							<td><?php echo $panelist['FilteredPanelist']['user_id'];?></td>
							<td><?php echo $this->Time->format($panelist['FilteredPanelist']['created'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, $timezone); ?></td>
						</tr>
					<?php endforeach;?>
				</tbody>
			</table>
			<div class="form-actions">
				<?php if (!empty($filtered_panelists)): ?>
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
</div>
<?php echo $this->Form->end(null); ?>
<?php echo $this->Element('pagination'); ?>