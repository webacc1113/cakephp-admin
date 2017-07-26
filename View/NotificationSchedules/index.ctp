<h3>Notification Templates</h3>

<p><?php echo $this->Html->link('New Template', array('action' => 'add'), array('class' => 'btn btn-mini btn-primary')); ?></p>

<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Name</td>
				<td>Description</td>
				<td>Key</td>
				<td>Total Emails</td>
				<td>Modified</td>
				<td class="actions"></td>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($notification_templates as $notification_template): ?>
				<tr>
					<td><?php echo $notification_template['NotificationTemplate']['name']; ?></td>
					<td><?php echo $notification_template['NotificationTemplate']['description']; ?></td>
					<td><?php echo $notification_template['NotificationTemplate']['key']; ?></td>
					<td><?php echo $notification_template['NotificationTemplate']['total_emails']; ?></td>

					<td><?php echo $this->Time->format($notification_template['NotificationTemplate']['modified'], Utils::dateFormatToStrftime('F jS, Y h:i A'), false, $timezone); ?></td>
					<td class="nowrap">
						<?php echo $this->Html->link('View', array('action' => 'view', $notification_template['NotificationTemplate']['id']), array('class' => 'btn btn-mini btn-default')); ?> 
						<?php echo $this->Html->link('Edit', array('action' => 'edit', $notification_template['NotificationTemplate']['id']), array('class' => 'btn btn-mini btn-default')); ?> 
						<?php echo $this->Html->link('Clone', array('action' => 'notification_template_clone', $notification_template['NotificationTemplate']['id']), array('class' => 'btn btn-mini btn-default')); ?> 
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php echo $this->Form->end(null); ?>