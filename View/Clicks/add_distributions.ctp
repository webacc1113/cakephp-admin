<?php
	echo $this->Html->script('/assets/javascripts/vendor/validation/jquery.validationEngine.js');
	echo $this->Html->script('/assets/javascripts/vendor/validation/jquery.validationEngine-en.js');
?>
<h3>Click Template Distributions for template #<?php echo $click_template_id; ?></h3>
<div class="row-fluid">
	<div class="box">
		<div class="box-header">
			<span class="title">Add Distributions</span>
			<ul class="box-toolbar">
				<li style="cursor: pointer;"><i class="icon-remove-sign"></i> Clear</li>
			</ul>
		</div>
		<div class="box-content">
			<?php echo $this->Form->create('ClickDistributionTemplate'); ?>
			<div class="padded">
				<div id="add_dropdown">
					<?php
						$options = array(
							array('name' => 'Age', 'value' => 'age'),
							array('name' => 'Gender', 'value' => 'gender'),
							array('name' => 'Age + Gender', 'value' => 'age_gender'),
							array('name' => 'HHI', 'value' => 'hhi'),
							array('name' => 'Ethnicity', 'value' => 'ethnicity'),
							array('name' => 'Hispanic', 'value' => 'hispanic'),
							array('name' => 'Geo - Region', 'value' => 'geo_region'),
							array('name' => 'Geo - State', 'value' => 'geo_state'),
						);
						echo $this->Form->input(null, array(
							'name' => 'key',
							'label' => false,
							'type' => 'select',
							'options' => $options,
							'empty' => 'Select distribution type:',
							'id' => 'distribution_dropdown',
							'div' => array(
								'style' => 'display: inline-block;'
							),
							'style' => 'margin-bottom: 0px;'
						));
					?>
				</div>
				<div id="edit_area" style="min-height: 30px; overflow: auto; display: block;">

				</div>
			</div>
			<div class="form-actions">
				<?php echo $this->Form->submit('Add Distribution', array('class' => 'btn btn-primary')); ?>
			</div>
			<?php echo $this->Form->end(null); ?>
		</div>
	</div>
</div>
<h5>Distributions</h5>
<div class="row-fluid">
	<div class="box">
		<table class="table table-normal">
			<thead>
				<tr>
					<td>Click Template</td>
					<td>Qualification</td>
					<td>Value</td>
					<td>Percentage (%)</td>
					<td></td>
				</tr>
			</thead>
			<tbody>
				<?php $i = 0; ?>
				<?php foreach ($click_template_distributions as $click_template_distribution): ?>
					<tr id="distribution_<?php echo $click_template_distribution['ClickTemplateDistribution']['id']; ?>">
						<td><?php echo $click_template_distribution['ClickTemplate']['name']; ?></td>
						<?php $qualification_name_arr = array(
							'age' => 'Age',
							'gender' => 'Gender',
							'age_gender' => 'Age + Gender',
							'ethnicity' => 'Ethnicity',
							'hhi' => 'HHI',
							'hispanic' => 'Hispanic',
							'geo_region' => 'Region',
							'geo_state' => 'State',
						); ?>
						<td><?php echo $qualification_name_arr[$click_template_distribution['ClickTemplateDistribution']['key']]; ?></td>
						<td>
							<?php
							$key = $click_template_distribution['ClickTemplateDistribution']['key'];
							$question_arr = array('hhi', 'ethnicity', 'hispanic');
							if (in_array($key, $question_arr)) {
								if ($click_template_distribution['ClickTemplateDistribution']['other']) {
									echo 'Other';
								}
								else {
									$answers = $questions[$key]['Answers'];
									echo $answers[$click_template_distribution['ClickTemplateDistribution']['answer_id']];
								}
							}
							elseif ($key == 'geo_state' || $key == 'geo_region') {
								if ($click_template_distribution['ClickTemplateDistribution']['other']) {
									echo 'Other';
								}
								else {
									$geo_key = str_replace('geo_', '', $key);
									echo $geo[$geo_key][$click_template_distribution['ClickTemplateDistribution']['answer_id']];
								}
							}
							elseif ($key == 'age_gender') {
								if ($click_template_distribution['ClickTemplateDistribution']['other']) {
									echo 'Other';
								}
								else {
									$text = $click_template_distribution['ClickTemplateDistribution']['gender'] == 1 ? 'Male' : 'Female';
									$text .= ': ' . $click_template_distribution['ClickTemplateDistribution']['age_from'] . ' - ';
									$text .= $click_template_distribution['ClickTemplateDistribution']['age_to'];
									echo $text;
								}
							}
							elseif ($key == 'gender') {
								echo $click_template_distribution['ClickTemplateDistribution']['gender'] == 1 ? 'Male' : 'Female';
							}
							elseif ($key == 'age') {
								if ($click_template_distribution['ClickTemplateDistribution']['other']) {
									echo 'Other';
								}
								else {
									echo $click_template_distribution['ClickTemplateDistribution']['age_from'] . ' - ' . $click_template_distribution['ClickTemplateDistribution']['age_to'];
								}
							}
							?>
						</td>
						<td><?php echo $click_template_distribution['ClickTemplateDistribution']['percentage']; ?></td>
						<td>
							<?php if ($click_template_distribution['ClickTemplateDistribution']['key'] != 'gender' && !$click_template_distribution['ClickTemplateDistribution']['other']): ?>
								<?php
									echo $this->Html->link('Delete', '#', array(
										'class' => 'btn btn-mini btn-danger',
										'onclick' => 'return delete_distribution(' . $click_template_distribution['ClickTemplateDistribution']['id'] .  ', this)'
									));
								?>
							<?php endif; ?>
						</td>
					</tr>
					<?php $i ++; ?>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
<style>
	div.form-group div.input {
		margin-bottom: 7px;
		margin-right: 15px;
	}
	label {
		margin-right: 10px;
	}
	#edit_area {
		padding: 15px 5px 5px 10px;
	}
</style>
<script type="text/javascript">
	var questions = <?php echo json_encode($questions); ?>;
	var geo = <?php echo json_encode($geo); ?>;
	var distributions = <?php echo json_encode($click_template_distributions); ?>;
	$(document).ready(function() {
		$('#ClickDistributionTemplateAddDistributionsForm').validationEngine();
		$('#distribution_dropdown').change(function() {
			if ($(this).val() != '') {
				add_input_area($(this).val());
			}
			else {
				$('#edit_area').html('');
				$('#edit_area').css('border', 'none');
			}
		});
		$('ul li').click(function() {
			$('#edit_area').html('');
			$('#edit_area').css('border', 'none');
			$('#distribution_dropdown').val(0);
		});
	});

	function add_input_area(distribution_type) {
		$('#edit_area').html('');
		var html_str = '';
		if (distribution_type == 'age') {
			html_str += '<div class="form-group"><div>';
			html_str += '<label style="float:left" for="age_from">Age From:</label>';
			html_str += '<?php echo $this->Form->input('age_from', array(
				'label' => false,
				'class' => 'validate[required,custom[onlyNumberSp],min[13]]',
				'style' => 'width: 55px;'
			)); ?>';
			html_str += '</div>';
			html_str += '<div>';
			html_str += '<label style="float:left" for="age_to">Age To:</label>';
			html_str += '<?php echo $this->Form->input('age_to', array(
				'label' => false,
				'class' => 'validate[required,custom[onlyNumberSp],max[99]]',
				'style' => 'width: 55px;'
			)); ?>';
			html_str += '</div>';
			html_str += '<div>';
			html_str += '<label style="float:left" for="percentage">Percentage:</label>';
			html_str += '<?php echo $this->Form->input('percentage', array(
				'label' => false,
				'class' => 'validate[required,custom[onlyNumberSp],max[100]]',
				'style' => 'width: 55px;'
			)); ?>';
			html_str += '</div></div>';
		}
		if (distribution_type == 'gender') {
			var prefill_values = get_prefill_values(distribution_type);
			html_str += '<div class="form-group"><div>';
			html_str += '<label style="float:left" for="male">Male:</label>';
			html_str += '<div class="input text">';
			html_str += '<input name="data[male]" class="validate[required,custom[onlyNumberSp],max[100]]" value="' + prefill_values.male + '" style="width: 55px;" type="text" id="male">';
			html_str += '</div></div>';
			html_str += '<div>';
			html_str += '<label style="float:left" for="female">Female:</label>';
			html_str += '<div class="input text">';
			html_str += '<input name="data[female]" class="validate[required,custom[onlyNumberSp],max[100]]" value="' + prefill_values.female + '" style="width: 55px;" type="text" id="female">';
			html_str += '</div></div></div>';
		}
		if (distribution_type == 'age_gender') {
			html_str += '<div class="form-group"><div>';
			html_str += '<label style="float:left" for="age_from">Age From:</label>';
			html_str += '<?php echo $this->Form->input('age_from', array(
				'label' => false,
				'class' => 'validate[required,custom[onlyNumberSp],min[13]]',
				'style' => 'width: 55px;'
			)); ?>';
			html_str += '</div>';
			html_str += '<div>';
			html_str += '<label style="float:left" for="age_to">Age To:</label>';
			html_str += '<?php echo $this->Form->input('age_to', array(
				'label' => false,
				'class' => 'validate[required,custom[onlyNumberSp],max[99]]',
				'style' => 'width: 55px;'
			)); ?>';
			html_str += '</div>';
			html_str += '<div>';

			html_str += '</div>';
			html_str += '<div>';
			html_str += '<label style="float:left" for="gender_dropdown">Gender:</label>';
			html_str += '<div class="input select" style="display: inline-block;"><select name="gender" id="gender_dropdown" style="margin-bottom: 0px; width: 100px;">';
			html_str += '<option value="male">Male</option>';
			html_str += '<option value="female">Female</option>';
			html_str += '</select></div>';
			html_str += '<div>';
			html_str += '<label style="float:left" for="percentage">Percentage:</label>';
			html_str += '<?php echo $this->Form->input('percentage', array(
				'label' => false,
				'class' => 'validate[required,custom[onlyNumberSp],max[100]]',
				'style' => 'width: 55px;'
			)); ?>';
			html_str += '</div></div>';
		}
		if (distribution_type == 'hhi') {
			var prefill_values = get_prefill_values(distribution_type);
			var hhi_answers = questions.hhi.Answers;
			var temp_arr = $.map(hhi_answers, function(value, index) {
				return [value];
			});
			var chunk = Math.ceil(temp_arr.length / 3);
			var hhi_chunked = [];
			for (i = 0, j = temp_arr.length; i < j; i += chunk) {
				hhi_chunked.push(temp_arr.slice(i, i + chunk));
			}
			for (var key in hhi_chunked) {
				html_str += '<div class="span3" style="margin-left: 0px;">';
				for (var i in hhi_chunked[key]) {
					var id = getKeyFromValue(hhi_answers, hhi_chunked[key][i]);
					var value = '';
					if (prefill_values && prefill_values[id] != undefined) {
						value = prefill_values[id];
					}
					html_str += '<div>';
					html_str += '<div class="span6">';
					html_str += '<label for="hhi_' + id + '">' + hhi_chunked[key][i] + '</label>';
					html_str += '</div>';
					html_str += '<div class="span6" style="margin: 0;">';
					html_str += '<div class="input text" style="margin: 0;">';
					html_str += '<input class="validate[custom[onlyNumberSp],max[100]]" name="data[hhi][' + id + ']" value="' + value + '" style="width: 55px;" type="text" id="hhi_' + id +'">';
					html_str += '</div></div></div>';
				}
				html_str += '</div>';
			}
		}
		if (distribution_type == 'ethnicity') {
			var prefill_values = get_prefill_values(distribution_type);
			var ethnicity_answers = questions.ethnicity.Answers;
			var temp_arr = $.map(ethnicity_answers, function(value, index) {
				return [value];
			});
			var chunk = Math.ceil(temp_arr.length / 2);
			var ethnicity_chunked = [];
			for (i = 0, j = temp_arr.length; i < j; i += chunk) {
				ethnicity_chunked.push(temp_arr.slice(i, i + chunk));
			}
			for (var key in ethnicity_chunked) {
				html_str += '<div class="span4" style="margin-left: 0px;">';
				for (var i in ethnicity_chunked[key]) {
					var id = getKeyFromValue(ethnicity_answers, ethnicity_chunked[key][i]);
					var value = '';
					if (prefill_values && prefill_values[id] != undefined) {
						value = prefill_values[id];
					}
					html_str += '<div>';
					html_str += '<div class="span7">';
					html_str += '<label for="ethnicity_' + id + '">' + ethnicity_chunked[key][i] + '</label>';
					html_str += '</div>';
					html_str += '<div class="span5" style="margin: 0;">';
					html_str += '<div class="input text" style="margin: 0;">';
					html_str += '<input class="validate[custom[onlyNumberSp],max[100]]" name="data[ethnicity][' + id + ']" value="' + value + '" style="width: 55px;" type="text" id="ethnicity_' + id +'">';
					html_str += '</div></div></div>';
				}
				html_str += '</div>';
			}
		}
		if (distribution_type == 'hispanic') {
			var prefill_values = get_prefill_values(distribution_type);
			var hispanic_answers = questions.hispanic.Answers;
			var temp_arr = $.map(hispanic_answers, function(value, index) {
				return [value];
			});
			var chunk = Math.ceil(temp_arr.length / 2);
			var hispanic_chunked = [];
			for (i = 0, j = temp_arr.length; i < j; i += chunk) {
				hispanic_chunked.push(temp_arr.slice(i, i + chunk));
			}
			for (var key in hispanic_chunked) {
				html_str += '<div class="span4" style="margin-left: 0px;">';
				for (var i in hispanic_chunked[key]) {
					var id = getKeyFromValue(hispanic_answers, hispanic_chunked[key][i]);
					var value = '';
					if (prefill_values && prefill_values[id] != undefined) {
						value = prefill_values[id];
					}
					html_str += '<div>';
					html_str += '<div class="span9">';
					html_str += '<label for="hispanic_' + id + '">' + hispanic_chunked[key][i] + '</label>';
					html_str += '</div>';
					html_str += '<div class="span3" style="margin: 0;">';
					html_str += '<div class="input text" style="margin: 0;">';
					html_str += '<input class="validate[custom[onlyNumberSp],max[100]]" name="data[hispanic][' + id + ']" value="' + value + '" style="width: 55px;" type="text" id="hispanic_' + id +'">';
					html_str += '</div></div></div>';
				}
				html_str += '</div>';
			}
		}
		if (distribution_type == 'geo_region') {
			var prefill_values = get_prefill_values(distribution_type);
			var regions = geo.region;

			for (var region in regions) {
				var value = '';
				if (prefill_values && prefill_values[region] != undefined) {
					value = prefill_values[region];
				}
				html_str += '<div>';
				html_str += '<div class="span1">';
				html_str += '<label for="region_' + region + '">' + regions[region] + '</label>';
				html_str += '</div>';
				html_str += '<div class="span11" style="margin: 0;">';
				html_str += '<div class="input text" style="margin: 0;">';
				html_str += '<input class="validate[custom[onlyNumberSp],max[100]]" name="data[geo_region][' + region + ']" value="' + value + '" style="width: 55px;" type="text" id="region_' + region +'">';
				html_str += '</div></div></div>';
			}
		}
		if (distribution_type == 'geo_state') {
			var prefill_values = get_prefill_values(distribution_type);
			var states = geo.state;
			var temp_arr = $.map(states, function(value, index) {
				return [value];
			});
			var chunk = Math.ceil(temp_arr.length / 6);
			var states_chunked = [];
			for (i = 0, j = temp_arr.length; i < j; i += chunk) {
				states_chunked.push(temp_arr.slice(i, i + chunk));
			}
			for (var key in states_chunked) {
				html_str += '<div class="span2" style="margin-left: 0px;">';
				for (var i in states_chunked[key]) {
					var id = getKeyFromValue(states, states_chunked[key][i]);
					var value = '';
					if (prefill_values && prefill_values[id] != undefined) {
						value = prefill_values[id];
					}
					var state = states_chunked[key][i].substring(0, 2);
					html_str += '<div>';
					html_str += '<div class="span2">';
					html_str += '<label for="state_' + id + '">' + state + '</label>';
					html_str += '</div>';
					html_str += '<div class="span10" style="margin: 0;">';
					html_str += '<div class="input text" style="margin: 0;">';
					html_str += '<input class="validate[custom[onlyNumberSp],max[100]]" name="data[geo_state][' + id + ']" value="' + value + '" style="width: 55px;" type="text" id="state_' + id +'">';
					html_str += '</div></div></div>';
				}
				html_str += '</div>'
			}
		}

		$('#edit_area').append(html_str);
		$('#edit_area').css('border', '1px solid #ccc');
	}

	function getKeyFromValue(object, value) {
		for (key in object) {
			if (object[key] == value) {
				return key;
			}
		}
		return "";
	}

	function get_prefill_values(key) {
		var return_value = {};
		for (var i = 0; i < distributions.length; i ++) {
			if (key == 'gender') {
				if (distributions[i]['ClickTemplateDistribution'].key == 'gender') {
					if (distributions[i]['ClickTemplateDistribution'].gender == 1) {
						return_value.male = distributions[i]['ClickTemplateDistribution'].percentage;
					}
					else {
						return_value.female = distributions[i]['ClickTemplateDistribution'].percentage;
					}
				}
			}
			else {
				if (distributions[i]['ClickTemplateDistribution'].key == key && distributions[i]['ClickTemplateDistribution'].other == '0') {
					return_value[distributions[i]['ClickTemplateDistribution'].answer_id] = distributions[i]['ClickTemplateDistribution'].percentage;
				}
			}
		}
		if ($.isEmptyObject(return_value)) {
			if (key == 'gender') {
				return_value.male = return_value.female = 50;
				return return_value;
			}
			return false;
		}
		else {
			return return_value;
		}
	}

	function delete_distribution(id, node) {
		if (confirm('Are you SURE you want to remove this distribution?')) {
			var $node = $(node);
			$.ajax({
				type: 'POST',
				url: '/clicks/ajax_delete_distribution/',
				data: {id: id},
				statusCode: {
					201: function (data) {
						for (var row_id in data) {
							if (data[row_id] == 0) {
								$('table tbody #distribution_' + row_id).remove();
							}
							else {
								$('table tbody #distribution_' + row_id + ' td').eq(3).text(data[row_id]);
							}
						}
					}
				}
			});
		}
		return false;
	}
</script>