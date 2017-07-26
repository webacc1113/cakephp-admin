<h3>Project Invites Report</h3>

<div class="box">
	<div class="box-header">
		<span class="title">Filters</span>
		<ul class="box-toolbar">
			<li>
				<?php echo $this->Html->link('<i class="icon-remove-sign"></i> Clear filters', array('action' => 'report'), array('escape' => false)); ?>
			</li>
		</ul>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create('Filter', array('type' => 'get', 'class' => 'filter')); ?>
			<div class="padded separate-sections">
				<div class="row-fluid">
					<div class="filter date-group">
						<label>Report date between:</label>
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

<p class="count">Showing <?php
	echo number_format($this->Paginator->counter(array('format' => '{:current}')));
?> of <?php
	echo number_format($this->Paginator->counter(array('format' => '{:count}')));
?> matches
</p>

<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td></td>
				<td colspan="5">Users by Activity Level</td>
				<td colspan="3">Invites Received By User</td>
				<td colspan="2">Total</td>
			</tr>
			<tr>
				<td><?php echo $this->Paginator->sort('ProjectInviteReport.date', 'Date'); ?></td>
				<td><?php echo $this->Paginator->sort('ProjectInviteReport.runners', 'Runners'); ?></td>
				<td><?php echo $this->Paginator->sort('ProjectInviteReport.walkers', 'Walkers'); ?></td>
				<td><?php echo $this->Paginator->sort('ProjectInviteReport.living', 'Living'); ?></td>
				<td><?php echo $this->Paginator->sort('ProjectInviteReport.zombies', 'Zombies'); ?></td>
				<td><?php echo $this->Paginator->sort('ProjectInviteReport.dead', 'Dead'); ?></td>
				<td><?php echo $this->Paginator->sort('ProjectInviteReport.max_invites_received_by_user', 'Max'); ?></td>
				<td><?php echo $this->Paginator->sort('ProjectInviteReport.median_invites_received_by_user', 'Median'); ?></td>
				<td><?php echo $this->Paginator->sort('ProjectInviteReport.mean_invites_received_by_user', 'Mean'); ?></td>
				<td><?php echo $this->Paginator->sort('ProjectInviteReport.total_invites_sent', 'Invites Sent'); ?></td>
				<td><?php echo $this->Paginator->sort('ProjectInviteReport.total_users_received', 'Users Hit'); ?></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($reports as $report): ?>
				<tr>
					<td><?php echo $report['ProjectInviteReport']['date']; ?></td>
					<td><?php echo number_format($report['ProjectInviteReport']['runners']); ?></td>
					<td><?php echo number_format($report['ProjectInviteReport']['walkers']); ?></td>
					<td><?php echo number_format($report['ProjectInviteReport']['living']); ?></td>
					<td><?php echo number_format($report['ProjectInviteReport']['zombies']); ?></td>
					<td><?php echo number_format($report['ProjectInviteReport']['dead']); ?></td>
					<td><?php echo number_format($report['ProjectInviteReport']['max_invites_received_by_user']); ?></td>
					<td><?php echo number_format($report['ProjectInviteReport']['median_invites_received_by_user']); ?></td>
					<td><?php echo number_format($report['ProjectInviteReport']['mean_invites_received_by_user']); ?></td>
					<td><?php echo number_format($report['ProjectInviteReport']['total_invites_sent']); ?></td>
					<td><?php echo number_format($report['ProjectInviteReport']['total_users_received']); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php echo $this->Element('pagination'); ?>
