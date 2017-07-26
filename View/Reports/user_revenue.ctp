<?php echo $this->Form->create(null); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Revenue Report</span>
	</div>
	<div class="box-content">
		<div class="padded">
			<div class="alert alert-warning">This feature will be removed in a few weeks. Please let Roy know if you need it</div>
			<p>Creates a CSV of all active users and their revenue impact.</p>
			<p>Note:</p>
			<ul>
				<li>The data behind this report is generated once a week, on Saturday night. <strong>This data was last generated <?php 
					echo date(DB_DATE, strtotime($modified)); 
				?></strong>.</li>
				<li>All values are in dollars</li>
				<li>Revenue fields are derived from client rate values for surveys and offers</li>
				<li>Cost fields are the awards granted to users who complete the surveys</li>
			</ul>
			<?php echo $this->Form->input('hellban', array(
				'type' => 'checkbox', 
				'checked' => true,
				'label' => 'Ignore hellbanned users'
			)); ?>
			<?php echo $this->Form->input('net', array(
				'type' => 'checkbox',
				'checked' => true,
				'label' => 'Ignore net-zero users'
			)); ?>		
			<?php echo $this->Form->input('verifiedinactive', array(
				'type' => 'checkbox',
				'checked' => true,
				'label' => 'Ignore verified non-active users'
			)); ?>		
			<?php echo $this->Form->input('payout', array(
				'type' => 'checkbox',
				'checked' => false,
				'label' => 'Ignore users who have not received a payout'
			)); ?>		
		</div>
	</div>
	<div class="form-actions">
		<?php echo $this->Form->submit('Download CSV Report', array('class' => 'btn btn-primary')); ?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>