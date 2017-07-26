<?php if (isset($rows)): ?>
	<div class="span12">
		<div class="box">
			<div class="box-header">
				<span class="title">Lucid OQ checks</span>
			</div>
			<div class="box-content">
				<table cellpadding="0" cellspacing="0" class="table table-normal">
					<thead>
						<tr>
							<td>User ID</td>
							<td>User Disqualified</td>
							<td>Query ID</td>
							<td>Current Quota</td>
							<td>Quota Closed Date</td>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($rows as $row): ?>
						<tr>
							<td><?php echo $row[0]; ?></td>
							<td><?php echo $row[1]; ?></td>
							<td><?php echo $row[2]; ?></td>
							<td><?php echo $row[3]; ?></td>
							<td><?php echo $row[4]; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
<?php else: ?>
	<div class="span6">
		<div class="box">
			<div class="box-header">
				<span class="title">Lucid OQ checks</span>
			</div>
			<div class="box-content">
				<?php echo $this->Form->create('Report', array('type' => 'get')); ?>
				<div class="padded"><?php 
					echo $this->Form->input('project', array(
						'label' => false, 
						'placeholder' => 'Project ID',
						'style' => 'width: auto;',
						'value' => isset($this->request->query['project']) ? $this->request->query['project'] : null
					)); 
				?></div>
		
				<div class="form-actions">	
					<?php echo $this->Form->submit('Generate Report', array(
						'class' => 'btn btn-sm btn-primary',
						'disabled' => false
					)); ?>
					<?php echo $this->Form->end(null); ?>
				</div>
			</div>
		</div>
	</div>
<?php endif; ?>