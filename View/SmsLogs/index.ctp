<style type="text/css">
    #system_mail_logs div.input {
        margin-bottom: 0;
    }
</style>
<h3>Verification SMS Logs</h3>
<div class="row-fluid" id="system_mail_logs">
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
            <?php echo $this->Form->create('VerificationSmsLog', array('type' => 'get', 'class' => 'filter')); ?>
                <div class="padded">
                    <div class="row-fluid" style="margin-bottom: 0">
                        <div class="filter date-group">
                            <label>Phone Number:</label>
                            <?php echo $this->Form->input('from', array(
                                'label' => false,
                                'value' => isset($this->request->query['phone_number']) ? $this->request->query['phone_number']: null
                            )); ?>
                        </div>
                        <div class="filter">
                            <label>Type:</label>
                            <?php echo $this->Form->input('type', array(
                                'class' => 'uniform',
                                'label' => false,
                                'required' => false,
                                'empty' => 'All',
                                'value' => isset($this->request->query['type']) ? $this->request->query['type']: null,
                                'options' => array('admin' => 'Admin', 'user' => 'User')
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
<div class="box">
    <table cellpadding="0" cellspacing="0" class="table table-normal">
        <thead>
            <tr>
                <td>User</td>
                <td>From</td>
                <td>To</td>
                <td>Sms Sid</td>
                <td><?php echo $this->Paginator->sort('VerificationSmsLog.sms_status', 'Sms Status'); ?></td>
                <td><?php echo $this->Paginator->sort('VerificationSmsLog.error_code', 'Error Code'); ?></td>
                <td><?php echo $this->Paginator->sort('VerificationSmsLog.created', 'Created'); ?></td>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sms_logs as $sms_log): ?>
                <tr>
                    <td><?php echo ($sms_log['VerificationSmsLog']['type'] == 'admin') ? $sms_log['Admin']['admin_user'] : $sms_log['User']['username'] ; ?></td>
                    <td><?php echo $sms_log['VerificationSmsLog']['from']; ?></td>
                    <td><?php echo $sms_log['VerificationSmsLog']['to']; ?></td>
                    <td><?php echo $sms_log['VerificationSmsLog']['sms_sid']; ?></td>
                    <td><?php echo $sms_log['VerificationSmsLog']['sms_status']; ?></td>
                    <td><?php echo $sms_log['VerificationSmsLog']['error_code']; ?></td>
                    <td><?php echo $this->Time->format($sms_log['VerificationSmsLog']['created'], Utils::dateFormatToStrftime(DB_DATETIME), false, $timezone); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php echo $this->Element('pagination'); ?>