<h3>FlexID Requests</h3>
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
        <?php echo $this->Form->create('FlexidRequest', array('type' => 'get', 'class' => 'filter')); ?>
        <div class="padded">
            <div class="row-fluid" style="margin-bottom: 0">
                <div class="filter date-group">
                    <label>Flexid Requests From:</label>
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
<div class="box">
    <table cellpadding="0" cellspacing="0" class="table table-normal">
        <thead>
            <tr>
                <td>User</td>
                <td>Transaction Id</td>
                <td>First Name</td>
                <td>Last Name</td>
                <td>Address Line1</td>
                <td>Address Line2</td>
                <td>City</td>
                <td>State Code</td>
                <td>Zip5</td>
                <td>Birth Date</td>
                <td>Phone Number</td>
                <td>Email</td>
                <td>Status</td>
                <td>Verification Result</td>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($flexid_requests as $flexid_request): ?>
                <tr>
                    <td><?php echo $flexid_request['User']['username']; ?></td>
                    <td><?php echo $flexid_request['FlexidRequest']['transaction_id']; ?></td>
                    <td><?php echo $flexid_request['FlexidRequest']['first_name']; ?></td>
                    <td><?php echo $flexid_request['FlexidRequest']['last_name']; ?></td>
                    <td><?php echo $flexid_request['FlexidRequest']['address_line1']; ?></td>
                    <td><?php echo $flexid_request['FlexidRequest']['address_line2']; ?></td>
                    <td><?php echo $flexid_request['FlexidRequest']['city']; ?></td>
                    <td><?php echo $flexid_request['FlexidRequest']['state_code']; ?></td>
                    <td><?php echo $flexid_request['FlexidRequest']['zip5']; ?></td>
                    <td><?php echo $flexid_request['FlexidRequest']['birth_year']; ?></td>
                    <td><?php echo $flexid_request['FlexidRequest']['phone_number']; ?></td>
                    <td><?php echo $flexid_request['FlexidRequest']['email']; ?></td>
                    <td><?php echo $flexid_request['FlexidResponse']['status']; ?></td>
                    <td><?php echo $verification_indexes[$flexid_request['FlexidResponse']['verification_index']]; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php echo $this->Element('pagination'); ?>