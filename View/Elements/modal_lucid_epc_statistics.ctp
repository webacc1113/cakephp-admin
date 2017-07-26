<div id="modal-lucid-epc-statistics" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Lucid EPC Statistics</h6>
	</div>
	<div class="modal-body">
		<table class="table table-normal">
			<thead>
				<tr>
					<td>Trailing EPC Cents</td>
					<td>Created</td>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($lucid_epc_statistics as $lucid_epc_statistic): ?>
					<tr>
						<td>$<?php echo number_format($lucid_epc_statistic['LucidEpcStatistic']['trailing_epc_cents'] / 100, 2); ?></td>
						<td><?php echo $this->Time->format($lucid_epc_statistic['LucidEpcStatistic']['created'], Utils::dateFormatToStrftime('M jS, Y h:i A'), false, $timezone); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<div class="modal-footer">
		<button class="btn btn-default" data-dismiss="modal">Close me</button>
	</div>
</div>