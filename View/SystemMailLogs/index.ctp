<style type="text/css">
    #system_mail_logs div.input {
        margin-bottom: 0;
    }
</style>
<h3>System Mail Logs</h3>
<p><?php echo $this->Html->link('Daily Summaries', array('controller' => 'system_mail_logs', 'action' => 'summary'), array('class' => 'btn btn-sm btn-default')); ?></p>
<div class="row-fluid" id="system_mail_logs">
    <div class="span6">
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
                <?php echo $this->Form->create('SystemMailLog', array('type' => 'get', 'class' => 'filter')); ?>
                    <div class="padded">
                        <div class="row-fluid" style="margin-bottom: 0">
                            <div class="filter date-group">
                                <label>Show Mail Logs From:</label>
                                <?php echo $this->Form->input('date', array(
                                    'label' => false,
                                    'class' => 'datepicker',
                                    'data-date-autoclose' => true,
                                    'placeholder' => 'Date',
                                    'value' => isset($this->request->query['date']) ? $this->request->query['date']: null
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
    </div>
    <?php if (isset($system_mail_log) && $system_mail_log): ?>
        <div class="span6">
            <label>Current Date Data</label>
            <div class="box">
                <table cellpadding="0" cellspacing="0" class="table table-normal">
                    <thead>
                        <tr>
                            <td>Avg Execution Time (min)</td>
                            <td>Stuck Emails</td>
                            <td>Processed Emails</td>
                            <td>Sent Emails</td>
                            <td>Suppressed Emails</td>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><?php echo number_format(round($system_mail_log['SystemMailLog']['execution_time_ms'] / 60, 2), 2); ?></td>
                            <td><?php echo number_format($system_mail_log['SystemMailLog']['stuck_emails']); ?></td>
                            <td><?php echo number_format($system_mail_log['SystemMailLog']['processing_emails']); ?></td>
                            <td>
								<?php echo number_format($system_mail_log['SystemMailLog']['sent_emails']); ?>
                                <?php if ($system_mail_log['SystemMailLog']['processing_emails'] > 0): ?>
                                    <?php $pct = $system_mail_log['SystemMailLog']['sent_emails'] / $system_mail_log['SystemMailLog']['processing_emails'] * 100; ?>
                                    (<?php echo round($pct); ?>%)
                                <?php endif; ?>
							</td>
                            <td>
								<?php echo number_format($system_mail_log['SystemMailLog']['suppressed_emails']); ?>
                                <?php if ($system_mail_log['SystemMailLog']['processing_emails'] > 0): ?>
                                    <?php $pct = $system_mail_log['SystemMailLog']['suppressed_emails'] / $system_mail_log['SystemMailLog']['processing_emails'] * 100; ?>
                                    (<?php echo round($pct); ?>%)
                                <?php endif; ?>
							</td>
						</tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
<div class="box">
    <table cellpadding="0" cellspacing="0" class="table table-normal">
        <thead>
            <tr>
                <td>Started</td>
                <td>Ended</td>
                <td>Execution Time (mins)</td>
                <td>Stuck Emails</td>
                <td>Processed Emails</td>
                <td>Sent Emails</td>
                <td>Suppressed Emails</td>
				<td>Sent %</td>
				<td>Suppressed %</td>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($system_mail_logs as $system_mail_log): ?>
                <tr>
                    <td><?php echo $system_mail_log['SystemMailLog']['started']; ?></td>
                    <td><?php echo $system_mail_log['SystemMailLog']['ended']; ?></td>
                    <td><?php echo number_format(round($system_mail_log['SystemMailLog']['execution_time_ms'] / 60, 2), 2); ?></td>
                    <td><?php echo number_format($system_mail_log['SystemMailLog']['stuck_emails']); ?></td>
                    <td><?php echo number_format($system_mail_log['SystemMailLog']['processing_emails']); ?></td>
                    <td><?php echo number_format($system_mail_log['SystemMailLog']['sent_emails']); ?></td>
                    <td><?php echo number_format($system_mail_log['SystemMailLog']['suppressed_emails']); ?></td>
					<td>
						<?php $pct = $system_mail_log['SystemMailLog']['sent_emails'] / $system_mail_log['SystemMailLog']['processing_emails'] * 100; ?>
						<?php echo round($pct, 2); ?>%
					</td>
					<td>
						<?php $pct = $system_mail_log['SystemMailLog']['suppressed_emails'] / $system_mail_log['SystemMailLog']['processing_emails'] * 100; ?>
						<?php echo round($pct, 2); ?>%
					</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php echo $this->Element('pagination'); ?>