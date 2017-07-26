<h3>Daily Summaries</h3>
<div class="row-fluid" style="margin-bottom: 10px;">
    <?php echo $this->Html->link('To Mainpage', array('controller' => 'system_mail_logs', 'action' => 'index'), array('class' => 'btn btn-sm btn-default pull-right')); ?>
</div>
<div class="box">
    <table cellpadding="0" cellspacing="0" class="table table-normal">
        <thead>
	        <tr>
	            <td>Date</td>
	            <td>Average Execution Time (minutes)</td>
	            <td>Stuck Emails</td>
	            <td>Total Processed</td>
	            <td>Sent Emails</td>
	            <td>Suppressed Emails</td>
				<td>Sent %</td>
				<td>Suppressed %</td>
	        </tr>
        </thead>
        <tbody>
        <?php foreach ($system_mail_logs as $system_mail_log): ?>
            <tr>
				<?php $date = date(DB_DATE, strtotime($system_mail_log['SystemMailLog']['started'])); ?>
                <td><?php echo $this->Html->link($date, array(
					'action' => 'index', 
					'?' => array(
						'date' => date_format(date_create($date), 'm/d/Y')
					)
				)); ?></td>
                <td><?php echo number_format(round($system_mail_log['SystemMailLog']['execution_time_ms'] / 60, 2), 2); ?> minutes</td>
                <td><?php echo number_format($system_mail_log['SystemMailLog']['stuck_emails']); ?></td>
                <td><?php echo number_format($system_mail_log['SystemMailLog']['processing_emails']); ?></td>
                <td><?php echo number_format($system_mail_log['SystemMailLog']['sent_emails']); ?></td>
                <td><?php echo number_format($system_mail_log['SystemMailLog']['suppressed_emails']); ?></td>
				<td>
					<?php if ($system_mail_log['SystemMailLog']['processing_emails'] > 0): ?>
						<?php $pct = $system_mail_log['SystemMailLog']['sent_emails'] / $system_mail_log['SystemMailLog']['processing_emails'] * 100; ?>
						<?php echo round($pct, 2); ?>%
					<?php endif; ?>
				</td>
				<td>
					<?php if ($system_mail_log['SystemMailLog']['processing_emails'] > 0): ?>
						<?php $pct = $system_mail_log['SystemMailLog']['suppressed_emails'] / $system_mail_log['SystemMailLog']['processing_emails'] * 100; ?>
						<?php echo round($pct, 2); ?>%
					<?php endif; ?>
				</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php echo $this->Element('pagination'); ?>