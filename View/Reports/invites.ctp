<h3>Invites Report</h3>
<div class="box">
	<div class="box-header">
		<span class="title">Filters</span>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create('Report', array('type' => 'get', 'class' => 'filter')); ?>
			<div class="padded separate-sections">					
				<div class="row-fluid">
					<div class="filter date-group">
						<label>Date between:</label>
						<?php echo $this->Form->input('from', array(
							'label' => false, 
							'class' => 'datepicker',
							'data-date-autoclose' => true,
							'placeholder' => 'Start date',
							'value' => isset($this->data['from']) ? $this->data['from']: null
						)); ?> 
						<?php echo $this->Form->input('to', array(
							'label' => false, 
							'class' => 'datepicker',
							'data-date-autoclose' => true,
							'placeholder' => 'End date',
							'value' => isset($this->data['to']) ? $this->data['to']: null
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
<?php if (!empty($notification_logs)): ?>
	<p class="count">Showing <?php 
		echo number_format($this->Paginator->counter(array('format' => '{:current}')));
		?> of <?php
			echo number_format($this->Paginator->counter(array('format' => '{:count}')));
		?> matches
	</p>
	<p>
		<div class="label label-info">Total invites sent: <?php echo number_format($this->Paginator->counter(array('format' => '{:count}'))); ?></div>
		<div class="label label-success">Effective invites: <?php echo number_format($effective_invites_count); ?></div>
		<div class="label label-red">Ineffective invites: <?php echo number_format($this->Paginator->counter(array('format' => '{:count}')) - $effective_invites_count); ?></div>
	</p>
	<div class="box">
		<table cellpadding="0" cellspacing="0" class="table table-normal">
			<thead>
				<tr>
					<td><?php echo $this->Paginator->sort('project_id', 'Survey'); ?></td>
					<td><?php echo $this->Paginator->sort('type', 'Invite type'); ?></td>
					<td><?php echo $this->Paginator->sort('email'); ?></td>
					<td><?php echo $this->Paginator->sort('mobile_number'); ?></td>
					<td><?php echo $this->Paginator->sort('status'); ?></td>
					<td><?php echo $this->Paginator->sort('click_timestamp'); ?></td>
					<td><?php echo $this->Paginator->sort('created'); ?></td>
				</tr>
			</thead>
			<tbody>
				<?php $statuses = unserialize(SURVEY_STATUSES); ?>
				<?php foreach ($notification_logs as $log): ?>
					<tr>
						<td><?php echo $this->Html->link($log['NotificationLog']['project_id'], array(
							'controller' => 'surveys',
							'action' => 'dashboard',
							$log['NotificationLog']['project_id']
						), array('target' => '_blank')); ?>
						<b>User payout:</b> <?php echo $this->App->dollarize($log['Project']['user_payout'], 2);?>, 
						<b>LOI:</b> <?php echo $log['Project']['est_length']; ?>  / 
						<?php if (!empty($log['Project']['SurveyVisitCache']['loi_seconds'])) : ?>
							<?php echo round($log['Project']['SurveyVisitCache']['loi_seconds'] / 60); ?>
						<?php else: ?>
							<span class="muted">-</span>
						<?php endif; ?>
						</td>
						<td><?php echo $log['NotificationLog']['type']; ?></td>
						<td><?php echo $log['NotificationLog']['email']; ?></td>
						<td><?php echo $log['NotificationLog']['mobile_number']; ?></td>
						<td><?php echo ($log['NotificationLog']['status'] > 0) ? $statuses[$log['NotificationLog']['status']] : '-'; ?></td>
						<td><?php echo (!empty($log['NotificationLog']['click_timestamp'])) ? $this->Time->format($log['NotificationLog']['click_timestamp'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, $timezone) : '-' ?></td>
						<td><?php echo $this->Time->format($log['NotificationLog']['created'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, $timezone); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		
		<div class="form-actions">
			<?php echo $this->Element('pagination'); ?>
		</div>
	</div>
<?php endif; ?>
