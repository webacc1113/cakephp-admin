<?php echo $this->Html->script('/js/table-fixed-header'); ?>
<script type="text/javascript">
	$(document).ready(function () {
		$('.table-fixed-header').fixedHeader();
	});
</script>
<?php $reportHelper = $this->Report; ?>
<h2><?php echo date('Y/m/d', strtotime($report_date));
          if ($do_compare) : ?> 
                <span class="muted">Compared To</span> <?php echo date('Y/m/d', strtotime($compare_date)); ?>
    <?php endif ; ?>
</h2>

<div class="box statistic-data" style="margin-bottom: 0;">
	<table cellpadding="0" cellspacing="0" class="table table-normal table-fixed-header">
		<thead class="header">
			<tr>
				<td>Partner</td>
				<td>Total Earnings</td>
				<td>EPC</td>
				<td>Invites</td>
				<td>Clicks</td>
				<td>Completes</td>
				<td>OQ</td>
				<td>NQ</td>
				<td>Projects</th>
				<td>Unique Panelists</th>
			</tr>
		</thead>
		<tbody>
			<?php 
				$total_earnings = 0;
				$total_epc = 0;
			    $total_invites = 0;
			  	$total_clicks = 0;
				$total_completes = 0;
				$total_oqs = 0;
				$total_nqs = 0;
				$total_projects = 0;
				$total_uniques = 0;
				if ($do_compare) { 
					$total_compare_earnings = 0; 
					$total_compare_invites = 0;
					$total_compare_clicks = 0;
					$total_compare_completes = 0;
					$total_compare_oqs = 0;
					$total_compare_nqs = 0;
					$total_compare_projects = 0;
					$total_compare_uniques = 0;
				}
			?>
			<?php foreach ($rows as $partner => $data_rows) : ?>
				<?php foreach ($data_rows as $key => $row) : ?>
					<?php if ($row[9] == '') : ?>
						<?php $total_earnings = $total_earnings + $row[2];
							$total_invites += $row[4];
							$total_clicks += $row[0];
							$total_epc = ($total_clicks > 0) ? $total_earnings / $total_clicks : 0;
							$total_completes += $row[1];
							$total_oqs += $row[7];
							$total_nqs += $row[8];
							$total_projects += $row[5];
							$total_uniques += $row[6]; ?>
					<?php endif; ?>
					<?php if ($do_compare) { 
							$compare = isset($compare_data_rows[$partner][$key]) ? $compare_data_rows[$partner][$key] : '';
							if (empty($compare)) {
								continue;
							}
							if ($compare[9] == '') {
								$total_compare_earnings = $total_compare_earnings + $compare[2];
								$total_compare_invites += $compare[4];
								$total_compare_clicks += $compare[0];
								$total_compare_epc = ($total_compare_clicks > 0) ? $total_compare_earnings / $total_compare_clicks : 0;
								$total_compare_completes += $compare[1];
								$total_compare_oqs += $compare[7];
								$total_compare_nqs += $compare[8];
								$total_compare_projects += $compare[5];
								$total_compare_uniques += $compare[6];
							}

							$diff_earnings = $reportHelper->calculatePercentage($compare[2], $row[2]);
							$diff_epc = $reportHelper->calculatePercentage($compare[3], $row[3]);
							$diff_invites = $reportHelper->calculatePercentage($compare[4], $row[4]);
							$diff_clicks = $reportHelper->calculatePercentage($compare[0], $row[0]);
							$diff_completes = $reportHelper->calculatePercentage($compare[1], $row[1]);
							$diff_oqs = $reportHelper->calculatePercentage($compare[7], $row[7]);
							$diff_nqs = $reportHelper->calculatePercentage($compare[8], $row[8]);
							$diff_projects = $reportHelper->calculatePercentage($compare[5], $row[5]);
							$diff_unique = $reportHelper->calculatePercentage($compare[6], $row[6]);
							$total_diff_earnings = $reportHelper->calculatePercentage($total_compare_earnings, $total_earnings);
							$total_diff_invites = $reportHelper->calculatePercentage($total_compare_invites, $total_invites);
							$total_diff_clicks = $reportHelper->calculatePercentage($total_compare_clicks, $total_clicks);
							$total_diff_epc = $reportHelper->calculatePercentage($total_compare_epc, $total_epc);
							$total_diff_completes = $reportHelper->calculatePercentage($total_compare_completes, $total_completes);
							$total_diff_oqs = $reportHelper->calculatePercentage($total_compare_oqs, $total_oqs);
							$total_diff_nqs = $reportHelper->calculatePercentage($total_compare_nqs, $total_nqs);
							$total_diff_projects = $reportHelper->calculatePercentage($total_compare_projects, $total_projects);
							$total_diff_uniques = $reportHelper->calculatePercentage($total_compare_uniques, $total_uniques);
						} ?>
					<?php $class = (empty($row[9])) ? 'total' : 'country muted'; ?>
					<tr class="<?php echo $class; ?>">
						<?php $group = isset($groups[$partner]) ? $groups[$partner] : $partner; ?>
						<td><?php echo !empty($row[9]) ? $group .' - '.$row[9] : $group; ?></td>
						<?php if ($do_compare) : ?>
							<td>$<?php echo number_format($row[2], 2); ?>
							<span class="muted"> / $<?php echo number_format($compare[2], 2); ?></span>
							<span class="<?php echo $reportHelper->getPercentageClass($diff_earnings); ?>"><?php echo $diff_earnings; ?></span>
							</td>
							<td>$<?php echo number_format($row[3], 2); ?>
								<span class="muted"> / $<?php echo number_format($compare[3], 2); ?></span>
								<span class="<?php echo $reportHelper->getPercentageClass($diff_epc); ?>"><?php echo $diff_epc; ?></span>
							</td>
							<td><?php echo number_format($row[4]); ?>
								<span class="muted"> / <?php echo number_format($compare[4]); ?></span>
								<span class="<?php echo $reportHelper->getPercentageClass($diff_invites); ?>"><?php echo $diff_invites; ?></span>
							</td>
							<td><?php echo number_format($row[0]); ?>
								<span class="muted"> / <?php echo number_format($compare[0]); ?></span>
								<span class="<?php echo $reportHelper->getPercentageClass($diff_clicks); ?>"><?php echo $diff_clicks; ?></span>
							</td>
							<td><?php echo number_format($row[1]); ?>
								<span class="muted"> / <?php echo number_format($compare[1]); ?></span>
								<span class="<?php echo $reportHelper->getPercentageClass($diff_completes); ?>"><?php echo $diff_completes; ?></span>
							</td>
							<td><?php echo number_format($row[7]); ?>
								<span class="muted"> / <?php echo number_format($compare[7]); ?></span>
								<span class="<?php echo $reportHelper->getPercentageClass($diff_oqs); ?>"><?php echo $diff_oqs; ?></span>
							</td>
							<td><?php echo number_format($row[8]); ?>
								<span class="muted"> / <?php echo number_format($compare[8]); ?></span>
								<span class="<?php echo $reportHelper->getPercentageClass($diff_nqs); ?>"><?php echo $diff_nqs; ?></span>
							</td>
							<td><?php echo number_format($row[5]); ?>
								<span class="muted"> / <?php echo number_format($compare[5]); ?></span>
								<span class="<?php echo $reportHelper->getPercentageClass($diff_projects); ?>"><?php echo $diff_projects; ?></span>
							</td>
							<td><?php echo number_format($row[6]); ?>
								<span class="muted"> / <?php echo number_format($compare[6]); ?></span>
								<span class="<?php echo $reportHelper->getPercentageClass($diff_unique); ?>"><?php echo $diff_unique; ?></span>
							</td>
						<?php else : ?>
							<td>$<?php echo number_format($row[2], 2); ?></td>
							<td>$<?php echo number_format($row[3], 2); ?></td>
							<td><?php echo number_format($row[4]); ?></td>
							<td><?php echo number_format($row[0]); ?></td>
							<td><?php echo number_format($row[1]); ?></td>
							<td><?php echo number_format($row[7]); ?></td>
							<td><?php echo number_format($row[8]); ?></td>
							<td><?php echo number_format($row[5]); ?></td>
							<td><?php echo number_format($row[6]); ?></td>
						<?php endif; ?>
					</tr>
				<?php endforeach; ?>
			<?php endforeach; ?>
				<tr>
					<td>Sum:</td>
					<td><strong>$<?php echo number_format($total_earnings, 2); ?></strong>
						<?php if ($do_compare) : ?>
						    	<span class="muted"> / </span>$<?php echo number_format($total_compare_earnings, 2); ?></span>
							<span class="<?php echo $reportHelper->getPercentageClass($total_diff_earnings); ?>"><?php echo $total_diff_earnings; ?></span>
						<?php endif; ?>
                    			</td>
					<td><strong>$<?php echo number_format($total_epc, 2); ?></strong>
						<?php if ($do_compare) : ?>
						    	<span class="muted"> / </span>$<?php echo number_format($total_compare_epc, 2); ?></span>
							<span class="<?php echo $reportHelper->getPercentageClass($total_diff_epc); ?>"><?php echo $total_diff_epc; ?></span>
						<?php endif; ?>
                    			</td>
					<td><strong><?php echo number_format($total_invites); ?></strong>
						<?php if ($do_compare) : ?>
							<span class="muted"> / </span><?php echo number_format($total_compare_invites); ?></span>
							<span class="<?php echo $reportHelper->getPercentageClass($total_diff_invites); ?>"><?php echo $total_diff_invites; ?></span>
						<?php endif; ?>
                    			</td>
					<td><strong><?php echo number_format($total_clicks); ?></strong>
						<?php if ($do_compare) : ?>
						    	<span class="muted"> / </span><?php echo number_format($total_compare_clicks); ?></span>
							<span class="<?php echo $reportHelper->getPercentageClass($total_diff_clicks); ?>"><?php echo $total_diff_clicks; ?></span>
						<?php endif; ?>
                    			</td>
					<td><strong><?php echo number_format($total_completes); ?></strong>
						<?php if ($do_compare) : ?>
						    	<span class="muted"> / </span><?php echo number_format($total_compare_completes); ?></span>
							<span class="<?php echo $reportHelper->getPercentageClass($total_diff_completes); ?>"><?php echo $total_diff_completes; ?></span>
						<?php endif; ?>
                    			</td>
					<td><strong><?php echo number_format($total_oqs); ?></strong>
						<?php if ($do_compare) : ?>
						    	<span class="muted"> / </span><?php echo number_format($total_compare_oqs); ?></span>
							<span class="<?php echo $reportHelper->getPercentageClass($total_diff_oqs); ?>"><?php echo $total_diff_oqs; ?></span>
						<?php endif; ?>
                    			</td>
					<td><strong><?php echo number_format($total_nqs); ?></strong>
						<?php if ($do_compare) : ?>
						    	<span class="muted"> / </span><?php echo number_format($total_compare_nqs); ?></span>
							<span class="<?php echo $reportHelper->getPercentageClass($total_diff_nqs); ?>"><?php echo $total_diff_nqs; ?></span>
						<?php endif; ?>
                    			</td>
					<td><strong><?php echo number_format($total_projects); ?></strong>
						<?php if ($do_compare) : ?>
						    	<span class="muted"> / </span><?php echo number_format($total_compare_projects); ?></span>
							<span class="<?php echo $reportHelper->getPercentageClass($total_diff_projects); ?>"><?php echo $total_diff_projects; ?></span>
						<?php endif; ?>
                    			</td>
					<td><strong><?php echo number_format($total_uniques); ?></strong>
						<?php if ($do_compare) : ?>
						    	<span class="muted"> / </span><?php echo number_format($total_compare_uniques); ?></span>
							<span class="<?php echo $reportHelper->getPercentageClass($total_diff_uniques); ?>"><?php echo $total_diff_uniques; ?></span>
						<?php endif; ?>
                    			</td>
				</tr>
		</tbody>
	</table>
</div>

<?php if (isset($launched_rows) && !empty($launched_rows)): ?>
	<div class="box" style="margin-bottom: 0;">
		<table cellpadding="0" cellspacing="0" class="table table-normal table-fixed-header">
			<thead class="header">
				<tr>
					<td>Partner</td>
					<td>Projects Imported</td>
					<td>Projects Launched</td>
					<td>Launch %</td>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($launched_rows as $partner => $row) :
					if ($do_compare) :
						$compare = (isset($compare_launched_rows[$partner]) ? $compare_launched_rows[$partner] : array(0 => 0, 1 => 0)); 
						$diff_pimports = $reportHelper->calculatePercentage($compare[0], $row[0]);
						$diff_plaunched = $reportHelper->calculatePercentage($compare[1], $row[1]);
						$diff_launch = $reportHelper->calculatePercentage($compare[1], $compare[0]); 
					endif;
					?>
					<tr>
						<td><?php echo isset($groups[$partner]) ? $groups[$partner]: $partner; ?></td>
						<?php if ($do_compare) : ?>
						    <td><?php echo number_format($row[0]); ?>
							<span class="muted"> / <?php echo number_format($compare[0]); ?><span>
							<span class="<?php echo $reportHelper->getPercentageClass($diff_pimports); ?>"><?php echo $diff_pimports; ?></span>
						    </td>
						    <td><?php echo number_format($row[1]); ?>
							<span class="muted"> / <?php echo number_format($compare[1]); ?></span>
							<span class="<?php echo $reportHelper->getPercentageClass($diff_plaunched); ?>"><?php echo $diff_plaunched; ?></span>
						    </td>
						    </td>
						<?php else : ?>
						    <td><?php echo number_format($row[0]); ?></td>
						    <td><?php echo number_format($row[1]); ?></td>
						<?php endif; ?>
							<td>
								<?php if (!empty($row[0]) && !empty($row[1])): ?>
									<?php echo number_format($row[1] / $row[0] * 100, 2); ?>%
								<?php else : ?>
									0%
								<?php endif; 
									if ($do_compare) : ?>
										<span class="muted"> / 
											<?php if (!empty($compare[0]) && !empty($compare[1])): 
												echo number_format($compare[1] / $compare[0] * 100, 2) . '%'; ?>
											<?php else : 
												echo '0%';
											endif; ?>
										</span>
										<span class="<?php echo $reportHelper->getPercentageClass($diff_launch); ?>"><?php echo $diff_launch; ?></span>
									<?php endif; ?>
							</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
<?php endif; ?>
<?php if (isset($partner_revenues)): ?>
	<div class="box">
		<table cellpadding="0" cellspacing="0" class="table table-normal">
			<thead>
				<tr>
					<td>Date</td>
					<?php foreach ($offer_partners as $partner): ?>
						<td><?php echo ucfirst($partner); ?></td>
					<?php endforeach; ?>
					<td>&nbsp;</td>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($partner_revenues as $created_date => $revenues): ?>
					<tr>
						<td>
							<?php echo $this->Time->format($created_date, Utils::dateFormatToStrftime('m/d/Y'), false); ?>
						</td>
						<?php foreach ($offer_partners as $partner): ?>
							<td><?php echo (isset($revenues[$partner])) ? '$' . number_format($revenues[$partner], 2) : 0; ?></td>
						<?php endforeach; ?>
						<td class="total"><?php echo '$' . number_format($line_totals[$created_date], 2); ?></td>					
					</tr>
				<?php endforeach; ?>
				<tr>
					<td colspan="<?php echo count($offer_partners) + 2; ?>" class="total"><?php echo '$' . number_format($grand_total, 2); ?></td>
				</tr>
			</tbody>
		</table>
	</div>
<?php endif; ?>
