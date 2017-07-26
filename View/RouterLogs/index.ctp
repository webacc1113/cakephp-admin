<?php echo $this->Html->script('/js/chart.min', array('inline' => false)); ?>
<h3><?php echo __('Survey Router Report')?></h3>
<div class="row-fluid">
	<div class="span6">
		<?php echo $this->Form->create(null, array(
			'class' => 'clearfix form-inline',
			'type' => 'get',
			'url' => array(
				'controller' => 'router_logs',
				'action' => 'index',
			),
		));
		?>
		<div class="form-group">
			<?php
			echo $this->Form->input('q', array(
				'label' => false,
				'placeholder' => 'Project #',
				'value' => isset($this->request->query['q']) ? $this->request->query['q'] : null
			));
			?>
		</div>
		<div class="form-group">
		<?php echo $this->Form->submit('Search', array('class' => 'btn btn-default')); ?>
		</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>

<?php if (!empty($this->request->query['q'])) : ?>
	<div class="row-fluid">
		<div class="span12">
			<h3><?php echo $project['Project']['survey_name']; ?></h3>
		</div>
		<div class="row-fluid">
			<div class="span6 box">
				<table cellpadding="0" cellspacing="0" class="table table-normal">
					<thead>
						<tr>
							<td><?php echo __('Mean');?></td>
							<td><?php echo __('Mode');?></td>
							<td><?php echo __('Median');?></td>
							<td><?php echo __('Min');?></td>
							<td><?php echo __('Max');?></td>
							<td><?php echo __('Count');?></td>
						</tr>
					</thead>
					<tbody>
						<?php if (!empty($statistics)) : ?>
							<tr>
								<td><?php echo $statistics['mean']; ?></td>
								<td><?php echo $statistics['mode']; ?></td>
								<td><?php echo $statistics['median']; ?></td>
								<td><?php echo $statistics['min']; ?></td>
								<td><?php echo $statistics['max']; ?></td>
								<td><?php echo $statistics['count']; ?></td>
							</tr>
						<?php else : ?>
							<tr>
								<td colspan="4"><?php echo __('No statistics available');?></td>
							</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
			<div class="span6 box">
				<table cellpadding="0" cellspacing="0" class="table table-normal">
					<thead>
						<tr>
							<td><?php echo __('Clicks');?></td>
							<td><?php echo __('Completes');?></td>
							<td><?php echo __('NQs');?></td>
							<td><?php echo __('OQs');?></td>
							<td><?php echo __('NQ-S');?></td>
							<td><?php echo __('NQ-F');?></td>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td><?php echo number_format($project['SurveyVisitCache']['click']); ?></td>
							<td><?php echo number_format($project['SurveyVisitCache']['complete']); ?></td>
							<td><?php echo number_format($project['SurveyVisitCache']['nq']); ?></td>
							<td><?php echo number_format($project['SurveyVisitCache']['overquota']); ?></td>
							<td><?php echo number_format($project['SurveyVisitCache']['speed']); ?></td>
							<td><?php echo number_format($project['SurveyVisitCache']['fraud']); ?></td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
		<?php if (!empty($statistics['router_positions'])) : ?>
			<div class="row-fluid">
				<div class="span6 box">
					<div class="box-header">
						<span class="title"></span>
					</div>
					<div class="box-content">
						<div class="padded">
							<p><?php echo __('Router Positions');?></p>
							<script>
								var data = {
									labels: <?php echo json_encode(array_keys($statistics['router_positions'])); ?>,
									datasets: [
										{
											label: "Statistics",
											fillColor: "rgba(220,220,220,0.5)",
											strokeColor: "rgba(220,220,220,0.8)",
											highlightFill: "rgba(220,220,220,0.75)",
											highlightStroke: "rgba(220,220,220,1)",
											data: <?php echo json_encode(array_values($statistics['router_positions'])); ?>
										}
									]
								};
								$(document).ready(function() {
									var ctx =  document.getElementById("statistics").getContext("2d");
									var myBarChart = new Chart(ctx).Bar(data);
								});
							</script>
							<canvas id="statistics" height="300" width="625">
						</div>
					</div>
				</div>
			</div>
		<?php endif; ?>
	</div>
<?php endif; ?>
<div class="clearfix"></div>