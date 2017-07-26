<script type="text/javascript">
	$(document).ready(function() {
		$('div.tt').tooltip({
		});
	});
</script>
<div class="box">
	<table cellpadding="0" cellspacing="0" class="table table-normal">
		<thead>
			<tr>
				<td>Date</td>
				<td>Score</td>
			</tr>
		</thead>
		<tbody>
			<?php if (!empty($scores)): ?>
				<?php foreach ($scores as $score): ?>
					<tr>
						<td class="nowrap">
							<?php if ($score['UserAnalysis']['created'] != '0000-00-00 00:00:00') : ?>
								<?php echo $this->Time->format($score['UserAnalysis']['created'], Utils::dateFormatToStrftime('M d, Y H:i:s A'), false, $timezone); ?>
							<?php endif; ?>
						</td>
						<td>
							<?php
								echo $score['UserAnalysis']['score'];
							?>
						</td>
					</tr>
				<?php endforeach;?>
			<?php endif;?>
		</tbody>
	</table>
</div>