<div class="box">
	<div class="box-header">
		<span class="title">Delete Mapping Rule</span>
	</div>
	<?php echo $this->Form->create(); ?>
	<div class="box-content">
		<div class="padded">
			<div class="alert alert-danger">
				You are about to delete this rule!
			</div>
			
			<table class="table table-normal">
				<tr>
					<th>UTM Source</th>
					<th>Maps to Affiliate</th>
					<th>Publisher ID mapped to GET parameter</th>
				</tr>
				<tr>
					<td><?php echo $source_mapping['SourceMapping']['utm_source']; ?></td>
					<td><?php echo $source_mapping['AcquisitionPartner']['name']; ?></td>
					<td><?php echo $source_mapping['SourceMapping']['publisher_id']; ?></td>
				</tr>
			</table>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Delete Rule', array('class' => 'btn btn-danger')); ?>
		</div>
	</div>
	<?php echo $this->Form->end(null); ?>
</div>