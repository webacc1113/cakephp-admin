<div class="row-fluid">
	<div class="span6">
		<div class="box">
			<div class="box-header">
				<span class="title">Reconcile <?php echo ucfirst($type);?></span>
			</div>
			<div class="box-content">
				<?php echo $this->Form->create('Reconciliation', array('type' => 'file')); ?>
					<div class="row-fluid">
						<div class="span4">
							<div class="padded">
								<?php echo $this->Form->input('file', array('label' => 'File', 'type' => 'file')); ?>
							</div>
						</div>
					</div>
					<div class="form-actions">
						<?php echo $this->Form->submit('Submit', array('class' => 'btn btn-primary')); ?>
					</div>
				<?php echo $this->Form->end(null); ?>
			</div>
		</div>
	</div>
	<div class="span6">
		<div class="box">
			<div class="box-header">
				<span class="title"></span>
			</div>
			<div class="box-content">
				<div class="padded">
					<div class="alert alert-warning">
						Future note: If you see a lot of mis-reconciled items, and daylight savings recently occurred, please let Roy know and do not reconcile those transactions.
					</div>
					<p>Upload an <?php echo ucfirst($type);?> lead report to add missing transactions for users.</p>
					<p>After submitting, you will see a list of missing transactions for you to verify and add.</p>
					<p>Any transactions that are added through this tool will be auto-approved.</p>
					<p>This tool does not yet reject transactions.</p>
					<br/>
					<h5>Reconcile file</h5>
					<p>Required File format : csv</p>
					<p>Following headers must be in csv file(headings are case sensitive) :</p>
					<div class="box">
						<table cellpadding="0" cellspacing="0" class="table table-normal">
							<thead class="header">
								<tr>
									<td>Offer ID</td>
									<td>User ID</td>
									<td>Amount</td>
									<td>Date</td>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td>Partner Offer id</td>
									<td>MV user id</td>
									<td>Panelist payout in points</td>
									<td>Date</td>
								</tr>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>