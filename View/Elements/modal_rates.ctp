<div id="modal-rates" class="modal hide">
	<div class="modal-header">
		<button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
		<h6 id="modal-tablesLabel">Rates</h6>
	</div>
	<div class="modal-body">
		<table class="table table-normal">
			<thead>
				<tr>
					<td>Client rate</td>
					<td>User award</td>
					<td>created</td>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($project['HistoricalRates'] as $rate): ?>
				<tr>
					<td><?php echo $this->App->dollarize($rate['client_rate'], 2); ?></td>
					<td><?php echo $rate['award']; ?> points</td>
					<td><?php echo $this->Time->format($rate['created'], Utils::dateFormatToStrftime('M jS, Y<br />h:i A'), false, $timezone); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<div class="modal-footer">
		<button class="btn btn-default" data-dismiss="modal">Close me</button>
	</div>
</div>