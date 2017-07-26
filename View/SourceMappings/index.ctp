<div class="row-fluid">
	<div class="span8">
		<div class="box">
			<div class="box-header">
				<span class="title">UTM Source Mappings</span>
				<ul class="box-toolbar">
					<li><?php echo $this->Html->link('Create Mapping', array('action' => 'add'), array('class' => 'btn btn-small btn-primary')); ?></li>
				</ul>
			</div>
			<div class="box-content">
				<table class="table table-normal">
					<tr>
						<th>Name</th>
						<th>UTM Source</th>
						<th>Acquisition Partner</th>
						<th>Publisher ID Key</th>
						<th></th>
					</tr>
					<?php foreach ($source_mappings as $source_mapping) : ?>
						<tr>
							<td><?php echo $source_mapping['SourceMapping']['name']; ?></td>
							<td><?php echo $source_mapping['SourceMapping']['utm_source']; ?></td>
							<td><?php echo $source_mapping['AcquisitionPartner']['name']; ?></td>
							<td><?php echo $source_mapping['SourceMapping']['publisher_id']; ?></td>
							<td class="nowrap text-right" style="width: 90px;">
								<?php echo $this->Html->link('Edit', array('action' => 'edit', $source_mapping['SourceMapping']['id']), array('class' => 'btn btn-small btn-default')); ?> 
								<?php echo $this->Html->link('Export', array('action' => 'export', $source_mapping['SourceMapping']['id']), array('class' => 'btn btn-small btn-default')); ?> 
								<?php echo $this->Html->link('Reports', array('action' => 'reports', $source_mapping['SourceMapping']['id']), array('class' => 'btn btn-small btn-default')); ?>
								<?php echo $this->Html->link('Delete', array('action' => 'delete', $source_mapping['SourceMapping']['id']), array('class' => 'btn btn-small btn-default')); ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
			</div>
		</div>
	</div>

	<div class="span4">
		<div class="box">
			<div class="box-header">
				<span class="title">Map UTM Source</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<p>This feature allows you to take any utm_source value and correctly bucket the data for analysis.</p>
					<p>The matching value will be utm_source: from there, you define which Acquisition Partner to map that utm_source to; you can also define which URL parameter (utm_campaign or any other) is utilized for the "Publisher" breakdown in our internal reporting.</p>
				</div>
			</div>
		</div>
	</div>
</div>