<div id="modal-project-irs" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Project IRs</h6>
	</div>
	<div class="modal-body">
		<table class="table table-normal">
			<thead>
				<tr>
					<th>IR</th>
					<th>Created</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($project['ProjectIr'] as $project_ir): ?>
					<tr>
						<td><?php echo $project_ir['ir'].'%'; ?></td>
						<td><?php echo $this->Time->format($project_ir['created'], Utils::dateFormatToStrftime('M jS, Y h:i A'), false, $timezone); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<div class="modal-footer">
		<button class="btn btn-default" data-dismiss="modal">Close me</button>
	</div>
</div>




