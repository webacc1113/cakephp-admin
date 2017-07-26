<div class="box">
	<div class="box-header">
		<span class="title">Daily Analysis Data on <?php echo (isset($this->data['date'])) ? $this->data['date'] : '';?></span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span12">
				<div class="padded">
					<?php
						$find = array('day-of-week', 'topbottom', 'monthly');
						$replace = array('Day of Week', 'Top Bottom', 'Monthly');
						if (isset($analysis_list) && !empty($analysis_list)) {
							foreach ($analysis_list as $key => $list) {
								echo "<b>".str_replace($find, $replace, $key). "</b>:<br/><br/>";
								$high1 = array();
								$low1 = array();
								$counter_low = 0;
								$counter_high = 0;
								foreach ($list as $framekey => $frame) {
									if (strpos($framekey, 'high') or strpos($framekey, 'top')) {
										$high2 = explode(', ', $frame);
										if (empty($high1) && empty($counter_high)) {
											$high1 = explode(', ', $frame);
										}
										else {
											$high1 = array_intersect($high1, $high2);	
										}
										$counter_high++;
									}
									if (strpos($framekey, 'bottom') or strpos($framekey, 'low')) {
										$low2 = explode(', ', $frame);
										if (empty($low1) && empty($counter_low)) {
											$low1 = explode(', ', $frame);
										}
										else {
											$low1 = array_intersect($low1, $low2);	
										}
										$counter_low++;
									}
									echo "<b>".ucwords(str_replace('_', ' ', $framekey))."</b>: ".$frame. "<br/><br/>";	
								}

								echo "<br/>";
								if (!empty($high1)) {
									echo "<b>Summary Highs:</b> ". implode(', ',$high1);
									echo "<br/><br/>";
								}
								if (!empty($low1)) {
									echo "<b>Summary Lows:</b> ". implode(', ',$low1);
									echo "<br/><br/>";
								}
								echo "<br/>";
							}
						}
						else {
							echo "No records found.";
						}
					?>
				</div>
			</div>
		</div>
	</div>
</div>	