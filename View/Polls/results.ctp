<?php echo $this->Html->css('chartist.min'); ?>
<?php echo $this->Html->script('chartist.min'); ?>
<?php
$answers = array();
foreach ($poll['PollAnswer'] as $answer) {
	$answers[] = $answer['answer'];
}
?>
<dl>
	<dt>Poll Question</dt>
	<dd><?php echo $poll['Poll']['poll_question']; ?></dd>
	<dt>Poll Answers</dt>
	<dd><?php echo implode('; ', $answers); ?></dd>
	<dt>Poll Created</dt>
	<dd><?php echo date('F jS, Y', strtotime($poll['Poll']['created'])); ?></dd>
	<dt>Total Respondents</dt>
	<dd><?php echo number_format(count($user_answers)); ?></dd>
</dl>
<div class="row-fluid">
	<div class="span5 offset1">
		<div class="box">
			<div class="box-header">
				<span class="title">Total Responses</span>
			</div>
			<div class="box-content">
				<div id="overall-legend"></div>
				<div class="ct-chart text-center" id="answers" style="height:300px;"></div>
			</div>
		</div>
	</div>

	<div class="span5">
		<div class="box">
			<div class="box-header">
				<span class="title">Ages</span>
			</div>
			<div class="box-content">
				<div id="age-legend"></div>
				<div class="ct-chart text-center" id="ages" style="height:300px;"></div>
			</div>
		</div>
	</div>
</div>

<div class="row-fluid">
	<div class="span5 offset1">
		<div class="box">
			<div class="box-header">
				<span class="title">Household Income</span>
			</div>
			<div class="box-content">
				<div id="hhi-legend"></div>
				<div class="ct-chart text-center" id="hhi" style="height:300px;"></div>
			</div>
		</div>
	</div>

	<div class="span5">
		<div class="box">
			<div class="box-header">
				<span class="title">Genders</span>
			</div>
			<div class="box-content">
				<div id="gender-legend"></div>
				<div class="ct-chart text-center" id="genders" style="height:300px;"></div>
			</div>
		</div>
	</div>
</div>

<?php $poll_colors = array('#F7464A', '#46BFBD', '#FDB45C', '#949FB1', '#4D5360'); ?>
<script type="text/javascript">
	var $poll_colors = <?php echo json_encode($poll_colors);?>;
	
	//Overall chart
	var over_all_chart= {
		labels : [],
		series : []
	};
	var over_all_legend = '';
	<?php
		$i = 0;
		$total = array_sum($answer_count);
	?>
	<?php foreach ($answer_count as $label => $value) : ?>
		over_all_chart['series'].push(<?php echo $value; ?>);
		over_all_chart['labels'].push('<?php echo str_replace("'", "\'", $label); ?> - <?php echo number_format($value); ?> (<?php echo round(($value / $total) * 100, 2); ?>%)');
	<?php endforeach; ?>
		
	

	var options = {
		labelInterpolationFnc: function(value) {
			return value[0]
		}
	};

	var responsiveOptions = [
		['screen and (min-width: 640px)', {
			chartPadding: 30,
			labelOffset: 100,
			labelDirection: 'explode',
			labelInterpolationFnc: function(value) {
				return value;
			}
		}],
		['screen and (min-width: 1024px)', {
			labelOffset: 80,
			chartPadding: 20
		}]
	];
	
	new Chartist.Pie('#answers', over_all_chart, options, responsiveOptions);
	var lagend = '';
	$.each(over_all_chart.labels, function(i, label) {
		var alpha = String.fromCharCode(97 + i);
		lagend += '<li><span class="lagend-box '+ alpha +'"></span>' + label + '</li>';
	});
	$('#overall-legend').html('<ul class="bar-legend">' + lagend + '</ul>');
	
	
	//Ages chart
	var labels_for_age = [];
	var ages_chart = {
		labels : [
			"14-19 years",
			"20-24 years",
			"25-34 years",
			"35-44 years",
			"45-54 years",
			"55-64 years",
			"65 years and old"
		],
		series : []
	};
	<?php 
	$overall_age_formated_data = array(); 
	for ($i = 0; $i < 7; $i++) {
		if (!empty($overall_age_data[$i])) {
			$overall_age_formated_data[$i] = $overall_age_data[$i];
		}
		else {
			$overall_age_formated_data[$i] = 0;
		}
	}?>
	ages_chart['series'].push(<?php echo json_encode(array_values($overall_age_formated_data))?>);
	labels_for_age.push('Overall ages');
	<?php foreach ($poll_answers as $answer_id => $answer_value) {?>
			<?php $formated_age_row = array();
			for ($i = 0; $i < 7; $i++) {
				if (!empty($age_data[$answer_id][$i])) {
					$formated_age_row[$i] = $age_data[$answer_id][$i];
				}
				else {
					$formated_age_row[$i] = 0;
				}
			}?>
			labels_for_age.push('<?php echo str_replace("'", "\'", $answer_value); ?>');
			ages_chart['series'].push(<?php echo json_encode(array_values($formated_age_row)); ?>);
	<?php }?>
	
	var options = {
		seriesBarDistance: 15,
		axisX: {
			offset: 60
		},
		axisY: {
			offset: 80,
			labelInterpolationFnc: function(value) {
				return value
			},
			scaleMinSpace: 40
		}
	};
	new Chartist.Bar('#ages', ages_chart, options);	
	
	var lagend = '';
	$.each(labels_for_age, function(i, label) {
		var alpha = String.fromCharCode(97 + i);
		lagend += '<li><span class="lagend-box '+ alpha +'"></span>' + label + '</li>';
	});
	$('#age-legend').html('<ul class="bar-legend">' + lagend + '</ul>');
	
	
	//Gender chart
	<?php $genders = unserialize(USER_PROFILE_GENDERS);?>
	var gender_chart = {
		labels : <?php echo json_encode(array_values($genders)); ?>,
		series : []
	};
	var labels_for_gender = [];
	
	<?php foreach ($poll_answers as $answer_id => $answer_value) {?>
			<?php $formated_gender_row = array();
			foreach ($genders as $gender_key => $gender) {
				$formated_gender_row[] = (isset($gender_data[$answer_id][$gender_key])) ? $gender_data[$answer_id][$gender_key] : 0;
			}?>
			gender_chart['series'].push(<?php echo json_encode(array_values($formated_gender_row));?>);
			labels_for_gender.push('<?php  echo str_replace("'", "\'", $answer_value); ?>');
	<?php }?>
	
	var options = {
		seriesBarDistance: 15,
		axisX: {
			offset: 60
		},
		axisY: {
			offset: 80,
			labelInterpolationFnc: function(value) {
				return value
			},
			scaleMinSpace: 40
		}
	};
	new Chartist.Bar('#genders', gender_chart, options);
	
	var lagend = '';
	$.each(labels_for_gender, function(i, label) {
		var alpha = String.fromCharCode(97 + i);
		lagend += '<li><span class="lagend-box '+ alpha +'"></span>' + label + '</li>';
	});
	$('#gender-legend').html('<ul class="bar-legend">' + lagend + '</ul>');
	$('#hhi-legend').html('<ul class="bar-legend">' + lagend + '</ul>');

	//HHI chart
	<?php $hhis = unserialize(USER_HHI);?>
	var hhi_chart = {
		labels : <?php echo json_encode(array_values($hhis)); ?>,
		series : []
	};
	
	<?php foreach ($poll_answers as $answer_id => $answer_value) {?>
			<?php $formated_hhi_row = array();
			foreach ($hhis as $hhi_key => $hhi_value) {
				$formated_hhi_row[] = (isset($hhi_data[$answer_id][$hhi_key])) ? $hhi_data[$answer_id][$hhi_key] : 0;
			}?>
			hhi_chart['series'].push(<?php echo json_encode(array_values($formated_hhi_row));?>);
	<?php }?>
	
	var options = {
		seriesBarDistance: 15,
		axisX: {
			offset: 60
		},
		axisY: {
			offset: 80,
			labelInterpolationFnc: function(value) {
				return value
			},
			scaleMinSpace: 40
		}
	};
	new Chartist.Bar('#hhi', hhi_chart, options);
	
	function objectSum(obj) {
		var sum = 0;
		for (var el in obj) {
			if (obj.hasOwnProperty(el)) {
				sum += parseFloat(obj[el]);
			}
		}
		return sum;
	}
</script>