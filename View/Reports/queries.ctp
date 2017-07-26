<?php echo $this->Html->script('/js/chart.min', array('inline' => false)); ?>

<div class="box">
	<div class="box-header">
		<span class="title">Filters</span>
	</div>
	<div class="box-content">
		<?php echo $this->Form->create('Filter', array('type' => 'get', 'class' => 'filter')); ?>
		<div class="padded separate-sections">
			<div class="row-fluid">
				<div class="filter">
					<?php echo $this->Form->input('date_field', array(
						'id' => 'date_field',
						'type' => 'select',
						'class' => 'uniform',
						'label' => '&nbsp;',
						'options' => array(
							'created' => 'Created',
							'last_touched' => 'Last touched'
						),
						'value' => isset($this->data['date_field']) ? $this->data['date_field']: null
					)); ?>
				</div>
				<div class="filter">
					<?php echo $this->Form->hidden('query_id', array(
						'id' => 'query_id',
						'label' => '&nbsp;',
						'value' => $this->data['query_id']
					)); ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Search', array('class' => 'btn btn-primary')); ?>
		</div>
		<?php echo $this->Form->end(null); ?>
	</div>
</div>
<?php if (isset($users_all) && isset($users_matched)): ?>
<div class="row-fluid">
	<div class="span6">
		<div class="box">
			<div class="box-header">
				<span class="title">All Matched Users (<?php echo number_format($users_all['count']); ?>)</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<p>This chart shows the average age of accounts for all users who matched into the query.</p>
					<dl>
						<dt>Average Balance</dt>
						<dd><?php echo number_format($users_all['balance']); ?> points</dd>
						<dt>Average Account Lifetime</dt>
						<dd><?php echo number_format($users_all['days']); ?> days</dd>
					</dl>
					<?php 
						$breakdown = $users_all['breakdown'];
						$charts = array();
						foreach ($breakdown as $key => $val) {
							if ($key == '361') {
								$name = '361+ days';
							}
							else {
								$name = $key.' to '.($key + 30).' days';
							}
							$charts[$name] = $val;
						}
					?>
					<script>
						var data = {
  						  labels: <?php echo json_encode(array_keys($charts)); ?>,
						    datasets: [
						        {
						            label: "Account Age Distribution",
						            fillColor: "rgba(220,220,220,0.5)",
						            strokeColor: "rgba(220,220,220,0.8)",
						            highlightFill: "rgba(220,220,220,0.75)",
						            highlightStroke: "rgba(220,220,220,1)",
						            data: <?php echo json_encode(array_values($charts)); ?>
						        }
						    ]
						};
						$(document).ready(function() {
							var ctx =  document.getElementById("all-age").getContext("2d");;
							var myBarChart = new Chart(ctx).Bar(data);
						});
					</script>					
					<div>
						<div id="all-age-legend"></div>
						<canvas id="all-age" height="450" width="550">
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="span6">
		<div class="box">
			<div class="box-header">
				<span class="title">Invited Users (<?php echo number_format($users_matched['count']); ?>)</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<p>This chart shows the average age of accounts for all users who were actually invited into the project from query.</p>
					<dl>
						<dt>Average Balance</dt>
						<dd><?php echo number_format($users_matched['balance']); ?> points</dd>
						<dt>Average Account Lifetime</dt>
						<dd><?php echo number_format($users_matched['days']); ?> days</dd>
					</dl>
					
					<?php 
						$breakdown = $users_matched['breakdown'];
						$charts = array();
						foreach ($breakdown as $key => $val) {
							if ($key == '361') {
								$name = '361+ days';
							}
							else {
								$name = $key.' to '.($key + 30).' days';
							}
							$charts[$name] = $val;
						}
					?>
					<script>
						var mdata = {
  						  labels: <?php echo json_encode(array_keys($charts)); ?>,
						    datasets: [
						        {
						            label: "Account Age Distribution",
						            fillColor: "rgba(220,220,220,0.5)",
						            strokeColor: "rgba(220,220,220,0.8)",
						            highlightFill: "rgba(220,220,220,0.75)",
						            highlightStroke: "rgba(220,220,220,1)",
						            data: <?php echo json_encode(array_values($charts)); ?>
						        }
						    ]
						};
						$(document).ready(function() {
							var mctx =  document.getElementById("matched-age").getContext("2d");;
							new Chart(mctx).Bar(mdata);
						});
					</script>					
					<div>
						<div id="all-age-legend"></div>
						<canvas id="matched-age" height="450" width="550">
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<div class="row-fluid">
	<div class="span12">
		<div class="box">
			<div class="box-header">
				<span class="title">Combined Data (in percentages)</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<p>Shows the percentage of account ages for both all matched (gray) vs. queried (blue).</p>
					<?php
						// convert data to percentages
						$percentages = array('all' => array(), 'matched' => array());
						foreach ($users_matched['breakdown'] as $key => $val) {
							if ($key == '361') {
								$name = '361+ days';
							}
							else {
								$name = $key.' to '.($key + 30).' days';
							}
							if ($users_matched['count'] > 0) {
								$percentages['matched'][$name] = round($val / $users_matched['count'], 2) * 100;
							}
							else {
								$percentages['matched'][$name] = 0;
							}
						}
						foreach ($users_all['breakdown'] as $key => $val) {
							if ($users_all['count'] > 0) {
								$percentages['all'][$key.' to '.($key + 30).' days'] = round($val / $users_all['count'], 2) * 100;
							}
							else {
								$percentages['all'][$key.' to '.($key + 30).' days'] = '0';
							}
						}
					?>
					
					<script>
						$(document).ready(function() {
							var mctx =  document.getElementById("combined-age").getContext("2d");;
							new Chart(mctx).Bar({
		  					  labels: <?php echo json_encode(array_keys($charts)); ?>,
							    datasets: [
							        {
							            label: "All Matched",
							            fillColor: "rgba(220,220,220,0.5)",
							            strokeColor: "rgba(220,220,220,0.8)",
							            highlightFill: "rgba(220,220,220,0.75)",
							            highlightStroke: "rgba(220,220,220,1)",
							            data: <?php echo json_encode(array_values($percentages['all'])); ?>
							        },
									{
							            label: "Queried",
							            fillColor: "rgba(151,187,205,0.5)",
          							  	strokeColor: "rgba(151,187,205,0.8)",
							            highlightFill: "rgba(151,187,205,0.75)",
							            highlightStroke: "rgba(151,187,205,1)",
							            data: <?php echo json_encode(array_values($percentages['matched'])); ?>
							        }
							    ]
							});
						});
					</script>					
					<div>
						<div id="all-age-legend"></div>
						<canvas id="combined-age" height="350" width="800">
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<div class="row-fluid">
	<div class="span6">
		<div class="box">
			<div class="box-header">
				<span class="title">All Matched Users (<?php echo number_format($users_all['count']); ?>)</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<p>This chart shows the levels for all users who matched into the query.</p>
					<script>
						var ldata = {
						  labels: <?php echo json_encode(array_keys($user_levels_all)); ?>,
							datasets: [
								{
									label: "Account Age Distribution",
									fillColor: "rgba(220,220,220,0.5)",
									strokeColor: "rgba(220,220,220,0.8)",
									highlightFill: "rgba(220,220,220,0.75)",
									highlightStroke: "rgba(220,220,220,1)",
									data: <?php echo json_encode(array_values($user_levels_all)); ?>
								}
							]
						};
						$(document).ready(function() {
							var mctx =  document.getElementById("all-levels").getContext("2d");;
							new Chart(mctx).Bar(ldata);
						});
					</script>
					<div>
						<div id="all-age-legend"></div>
						<canvas id="all-levels" height="450" width="550">
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="span6">
		<div class="box">
			<div class="box-header">
				<span class="title">Invited Users (<?php echo number_format($users_matched['count']); ?>)</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<p>This chart shows the levels for all users who were actually invited into the project from query.</p>
					<script>
						var ldatam = {
						  labels: <?php echo json_encode(array_keys($user_levels_matched)); ?>,
							datasets: [
								{
									label: "Account Age Distribution",
									fillColor: "rgba(220,220,220,0.5)",
									strokeColor: "rgba(220,220,220,0.8)",
									highlightFill: "rgba(220,220,220,0.75)",
									highlightStroke: "rgba(220,220,220,1)",
									data: <?php echo json_encode(array_values($user_levels_matched)); ?>
								}
							]
						};
						$(document).ready(function() {
							var mctx =  document.getElementById("matched-levels").getContext("2d");;
							new Chart(mctx).Bar(ldatam);
						});
					</script>					
					<div>
						<div id="all-age-legend"></div>
						<canvas id="matched-levels" height="450" width="550">
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<?php endif; ?>