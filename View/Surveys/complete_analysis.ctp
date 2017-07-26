<?php echo $this->Form->create(null); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Generate Client Report</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span6">
				<div class="padded">
					<p>Once you have imported a list of accepted hashes from the client, this report will go through and generate statistics on completions per partner.</p>
					<p>For MintVine users, it will auto-reject transactions that were not found in this list of accepted hashes.</p>
					<p><strong><span class="text-error">Be sure you have received the full report from the client.</span></strong></p>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Generate Client Report', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?Php echo $this->Form->end(null); ?>