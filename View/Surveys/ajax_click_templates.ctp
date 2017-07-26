<div class="row-fluid">
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
					'empty' => 'Select Qualification:',
					'id' => 'qualification_dropdown',
					'div' => array(
						'style' => 'display: inline-block;'
					),
					'style' => 'margin-bottom: 0px;'
				));
			?>
		</div>
		<div id="edit_area" style="min-height: 30px; overflow: auto; display: block;">

		</div>
	<?php echo $this->Form->end(null); ?>
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
	var distributions = <?php echo json_encode($project_click_distributions); ?>;
	$(document).ready(function() {
		$('#qualification_dropdown').change(function() {
			if ($(this).val() != '') {
				add_input_area($(this).val());
			}
			else {
				$('#edit_area').html('');
				$('#edit_area').css('border', 'none');
			}
		});
	});

	function add_input_area(qualification) {
		$('#edit_area').html('');
		var html_str = '';
		if (qualification == 'age') {
			html_str += '<div class="row-fluid">';
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
			html_str += '</div></div></div>';
			html_str += '<div class="row-fluid">';
			html_str += '<div class="form-group"><div>';
			html_str += '<div>';
			html_str += '<label style="float:left" for="percentage">Percentage:</label>';
			html_str += '<?php echo $this->Form->input('percentage', array(
				'label' => false,
				'class' => 'validate[required,custom[onlyNumberSp],max[100]]',
				'style' => 'width: 55px;'
			)); ?>';
			html_str += '</div>';
			html_str += '<div>';
			html_str += '<label style="float:left" for="click_quota">Click Quota:</label>';
			html_str += '<?php echo $this->Form->input('click_quota', array(
				'label' => false,
				'class' => 'validate[required,custom[onlyNumberSp]]',
				'style' => 'width: 55px;'
			)); ?>';
			html_str += '</div>';
			html_str += '<div>';
			html_str += '<label style="float:left" for="clicks">Clicks:</label>';
			html_str += '<?php echo $this->Form->input('clicks', array(
				'label' => false,
				'class' => 'validate[required,custom[onlyNumberSp]]',
				'style' => 'width: 55px;'
			)); ?>';
			html_str += '</div></div></div>';
		}
		if (qualification == 'gender') {
			var prefill_values = get_prefill_values(qualification);
			html_str += '<div class="row-fluid">';
			html_str += '<div class="form-group"><div>';
			html_str += '<label style="float:left" for="male">Male:</label>';
			html_str += '<div class="input text">';
			html_str += '<input name="data[male][percentage]" class="validate[required,custom[onlyNumberSp],max[100]]" value="' + prefill_values.male.percentage + '" style="width: 55px;" type="text" id="male">';
			html_str += '</div></div>';
			html_str += '<div>';
			html_str += '<label style="float:left" for="male_click_quota">Click Quota:</label>';
			html_str += '<div class="input text">';
			html_str += '<input name="data[male][click_quota]" class="validate[required,custom[onlyNumberSp]]" value="' + prefill_values.male.click_quota + '" style="width: 55px;" type="text" id="male_click_quota">';
			html_str += '</div></div>';
			html_str += '<div>';
			html_str += '<label style="float:left" for="male_clicks">Clicks:</label>';
			html_str += '<div class="input text">';
			html_str += '<input name="data[male][clicks]" class="validate[required,custom[onlyNumberSp]]" value="' + prefill_values.male.clicks + '" style="width: 55px;" type="text" id="male_clicks">';
			html_str += '</div></div></div></div>';
			html_str += '<div class="row-fluid">';
			html_str += '<div class="form-group"><div>';
			html_str += '<label style="float:left" for="female">Female:</label>';
			html_str += '<div class="input text">';
			html_str += '<input name="data[female][percentage]" class="validate[required,custom[onlyNumberSp],max[100]]" value="' + prefill_values.female.percentage + '" style="width: 55px;" type="text" id="female">';
			html_str += '</div></div>';
			html_str += '<div>';
			html_str += '<label style="float:left" for="female_click_quota">Click Quota:</label>';
			html_str += '<div class="input text">';
			html_str += '<input name="data[female][click_quota]" class="validate[required,custom[onlyNumberSp]]" value="' + prefill_values.female.click_quota + '" style="width: 55px;" type="text" id="female_click_quota">';
			html_str += '</div></div>';
			html_str += '<div>';
			html_str += '<label style="float:left" for="female_clicks">Clicks:</label>';
			html_str += '<div class="input text">';
			html_str += '<input name="data[female][clicks]" class="validate[required,custom[onlyNumberSp]]" value="' + prefill_values.female.clicks + '" style="width: 55px;" type="text" id="female_clicks">';
			html_str += '</div></div></div></div>';
		}
		if (qualification == 'age_gender') {
			html_str += '<div class="row-fluid">';
			html_str += '<div class="form-group"><div>';
			html_str += '<label style="float:left" for="age_from">Age From:</label>';
			html_str += '<div class="input text">';
			html_str += '<input name="data[age_from]" class="validate[required,custom[onlyNumberSp],min[13]]" value="" style="width: 55px;" type="text" id="age_from">';
			html_str += '</div></div>';
			html_str += '<div>';
			html_str += '<label style="float:left" for="age_to">Age To:</label>';
			html_str += '<div class="input text">';
			html_str += '<input name="data[age_to]" class="validate[required,custom[onlyNumberSp],max[99]]" value="" style="width: 55px;" type="text" id="age_to">';
			html_str += '</div></div>';
			html_str += '<div>';
			html_str += '<label style="float:left" for="gender_dropdown">Gender:</label>';
			html_str += '<div class="input select" style="display: inline-block;"><select name="gender" id="gender_dropdown" style="margin-bottom: 0px; width: 100px;">';
			html_str += '<option value="male">Male</option>';
			html_str += '<option value="female">Female</option>';
			html_str += '</select></div></div>';
			html_str += '<div>';
			html_str += '<label style="float:left" for="percentage">Percentage:</label>';
			html_str += '<div class="input text">';
			html_str += '<input name="data[percentage]" class="validate[required,custom[onlyNumberSp]]" value="" style="width: 55px;" type="text" id="percentage">';
			html_str += '</div></div>';
			html_str += '<div>';
			html_str += '<label style="float:left" for="click_quota">Click Quota:</label>';
			html_str += '<div class="input text">';
			html_str += '<input name="data[click_quota]" class="validate[required,custom[onlyNumberSp]]" value="" style="width: 55px;" type="text" id="click_quota">';
			html_str += '</div></div>';
			html_str += '<div>';
			html_str += '<label style="float:left" for="clicks">Clicks:</label>';
			html_str += '<div class="input text">';
			html_str += '<input name="data[clicks]" class="validate[required,custom[onlyNumberSp]]" value="" style="width: 55px;" type="text" id="clicks">';
			html_str += '</div></div></div></div>';
		}
		if (qualification == 'hhi') {
			var prefill_values = get_prefill_values(qualification);
			var hhi_answers = questions.hhi.Answers;
			var temp_arr = $.map(hhi_answers, function(value, index) {
				return [value];
			});
			var chunk = Math.ceil(temp_arr.length / 2);
			var hhi_chunked = [];
			for (i = 0, j = temp_arr.length; i < j; i += chunk) {
				hhi_chunked.push(temp_arr.slice(i, i + chunk));
			}
			for (var key in hhi_chunked) {
				html_str += '<div class="span6" style="margin-left: 0px;">';
				for (var i in hhi_chunked[key]) {
					var id = getKeyFromValue(hhi_answers, hhi_chunked[key][i]);
					var percentage = '';
					var click_quota = '';
					var clicks = '';
					if (prefill_values && prefill_values[id] != undefined) {
						percentage = prefill_values[id].percentage;
						click_quota = prefill_values[id].click_quota;
						clicks = prefill_values[id].clicks;
					}
					html_str += '<div>';
					html_str += '<div class="span4">';
					html_str += '<label for="hhi_' + id + '">' + hhi_chunked[key][i] + '</label>';
					html_str += '</div>';
					html_str += '<div class="span2" style="margin: 0;">';
					html_str += '<div class="input text" style="margin: 0;">';
					html_str += '<input placeholder="Percentage" class="validate[custom[onlyNumberSp],max[100]]" name="data[hhi][' + id + '][percentage]" value="' + percentage + '" style="width: 70px;" type="text" id="hhi_' + id +'">';
					html_str += '</div></div>';
					html_str += '<div class="span2" style="margin-left: 10px;">';
					html_str += '<div class="input text" style="margin: 0;">';
					html_str += '<input placeholder="Click Quota" class="validate[custom[onlyNumberSp]]" name="data[hhi][' + id + '][click_quota]" value="' + click_quota + '" style="width: 70px;" type="text" id="hhi_' + id +'_click_quota">';
					html_str += '</div></div>';
					html_str += '<div class="span2" style="margin-left: 10px;">';
					html_str += '<div class="input text" style="margin: 0;">';
					html_str += '<input placeholder="Clicks" class="validate[custom[onlyNumberSp]]" name="data[hhi][' + id + '][clicks]" value="' + clicks + '" style="width: 70px;" type="text" id="hhi_' + id +'_clicks">';
					html_str += '</div></div></div>';
				}
				html_str += '</div>';
			}
		}
		if (qualification == 'ethnicity') {
			var prefill_values = get_prefill_values(qualification);
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
				html_str += '<div class="span6" style="margin-left: 0px;">';
				for (var i in ethnicity_chunked[key]) {
					var id = getKeyFromValue(ethnicity_answers, ethnicity_chunked[key][i]);
					var percentage = '';
					var click_quota = '';
					var clicks = '';
					if (prefill_values && prefill_values[id] != undefined) {
						percentage = prefill_values[id].percentage;
						click_quota = prefill_values[id].click_quota;
						clicks = prefill_values[id].clicks;
					}					html_str += '<div>';
					html_str += '<div class="span6">';
					html_str += '<label for="ethnicity_' + id + '">' + ethnicity_chunked[key][i] + '</label>';
					html_str += '</div>';
					html_str += '<div class="span2" style="margin: 0;">';
					html_str += '<div class="input text" style="margin: 0;">';
					html_str += '<input placeholder="Percentage" class="validate[custom[onlyNumberSp],max[100]]" name="data[ethnicity][' + id + '][percentage]" value="' + percentage + '" style="width: 70px;" type="text" id="ethnicity_' + id +'">';
					html_str += '</div></div>';
					html_str += '<div class="span2" style="margin-left: 10px;">';
					html_str += '<div class="input text" style="margin: 0;">';
					html_str += '<input placeholder="Click Quota" class="validate[custom[onlyNumberSp]]" name="data[ethnicity][' + id + '][click_quota]" value="' + click_quota + '" style="width: 70px;" type="text" id="ethnicity_' + id + '_click_quota">';
					html_str += '</div></div>';
					html_str += '<div class="span2" style="margin-left: 10px;">';
					html_str += '<div class="input text" style="margin: 0;">';
					html_str += '<input placeholder="Clicks" class="validate[custom[onlyNumberSp]]" name="data[ethnicity][' + id + '][clicks]" value="' + clicks + '" style="width: 70px;" type="text" id="ethnicity_' + id +'_clicks">';
					html_str += '</div></div></div>';
				}
				html_str += '</div>';
			}
		}
		if (qualification == 'hispanic') {
			var prefill_values = get_prefill_values(qualification);
			var hispanic_answers = questions.hispanic.Answers;
			for (var id in hispanic_answers) {
				var percentage = '';
				var click_quota = '';
				var clicks = '';
				if (prefill_values && prefill_values[id] != undefined) {
					percentage = prefill_values[id].percentage;
					click_quota = prefill_values[id].click_quota;
					clicks = prefill_values[id].clicks;
				}
				html_str += '<div class="row-fluid">';
				html_str += '<div class="span5">';
				html_str += '<label for="hispanic_' + id + '">' + hispanic_answers[id] + '</label>';
				html_str += '</div>';
				html_str += '<div class="span1" style="margin: 0;">';
				html_str += '<div class="input text" style="margin: 0;">';
				html_str += '<input placeholder="Percentage" class="validate[custom[onlyNumberSp],max[100]]" name="data[hispanic][' + id + '][percentage]" value="' + percentage + '" style="width: 70px;" type="text" id="hispanic_' + id +'">';
				html_str += '</div></div>';
				html_str += '<div class="span1" style="margin-left: 20px;">';
				html_str += '<div class="input text" style="margin: 0;">';
				html_str += '<input placeholder="Click Quota" class="validate[custom[onlyNumberSp]]" name="data[hispanic][' + id + '][click_quota]" value="' + click_quota + '" style="width: 70px;" type="text" id="hispanic_' + id +'_click_quota">';
				html_str += '</div></div>';
				html_str += '<div class="span1" style="margin-left: 20px;">';
				html_str += '<div class="input text" style="margin: 0;">';
				html_str += '<input placeholder="Clicks" class="validate[custom[onlyNumberSp]]" name="data[hispanic][' + id + '][clicks]" value="' + clicks + '" style="width: 70px;" type="text" id="hispanic_' + id +'_clicks">';
				html_str += '</div></div>';
				html_str += '</div>';
			}
		}
		if (qualification == 'geo_region') {
			var prefill_values = get_prefill_values(qualification);
			var regions = geo.region;

			for (var region in regions) {
				var percentage = '';
				var click_quota = '';
				var clicks = '';
				if (prefill_values && prefill_values[region] != undefined) {
					percentage = prefill_values[region].percentage;
					click_quota = prefill_values[region].click_quota;
					clicks = prefill_values[region].clicks;
				}
				html_str += '<div class="row-fluid">';
				html_str += '<div class="span1">';
				html_str += '<label for="region_' + region + '">' + regions[region] + '</label>';
				html_str += '</div>';
				html_str += '<div class="span1" style="margin: 0;">';
				html_str += '<div class="input text" style="margin: 0;">';
				html_str += '<input placeholder="Percentage" class="validate[custom[onlyNumberSp],max[100]]" name="data[geo_region][' + region + '][percentage]" value="' + percentage + '" style="width: 70px;" type="text" id="region_' + region +'">';
				html_str += '</div></div>';
				html_str += '<div class="span1" style="margin-left: 20px;">';
				html_str += '<div class="input text" style="margin: 0;">';
				html_str += '<input placeholder="Click Quota" class="validate[custom[onlyNumberSp]]" name="data[geo_region][' + region + '][click_quota]" value="' + click_quota + '" style="width: 70px;" type="text" id="region_' + region +'_click_quota">';
				html_str += '</div></div>';
				html_str += '<div class="span1" style="margin-left: 20px;">';
				html_str += '<div class="input text" style="margin: 0;">';
				html_str += '<input placeholder="Clicks" class="validate[custom[onlyNumberSp]]" name="data[geo_region][' + region + '][clicks]" value="' + clicks + '" style="width: 70px;" type="text" id="region_' + region +'_clicks">';
				html_str += '</div></div></div>';
			}
		}
		if (qualification == 'geo_state') {
			var prefill_values = get_prefill_values(qualification);
			console.log(prefill_values);
			var states = geo.state;
			var temp_arr = $.map(states, function(value, index) {
				return [value];
			});
			var chunk = Math.ceil(temp_arr.length / 3);
			var states_chunked = [];
			for (i = 0, j = temp_arr.length; i < j; i += chunk) {
				states_chunked.push(temp_arr.slice(i, i + chunk));
			}
			for (var key in states_chunked) {
				html_str += '<div class="span4" style="margin-left: 0px;">';
				for (var i in            states_chunked[key]) {
					var id = getKeyFromValue(states, states_chunked[key][i]);
					var percentage = '';
					var click_quota = '';
					var clicks = '';
					if (prefill_values && prefill_values[id] != undefined) {
						percentage = prefill_values[id].percentage;
						click_quota = prefill_values[id].click_quota;
						clicks = prefill_values[id].clicks;
					}
					var state = states_chunked[key][i].substring(0, 2);
					html_str += '<div class="row-fluid">';
					html_str += '<div class="span2">';
					html_str += '<label for="state_' + id + '">' + state + '</label>';
					html_str += '</div>';
					html_str += '<div class="span3" style="margin: 0;">';
					html_str += '<div class="input text" style="margin: 0;">';
					html_str += '<input placeholder="Percentage" class="validate[custom[onlyNumberSp],max[100]]" name="data[geo_state][' + id + '][percentage]" value="' + percentage + '" style="width: 70px;" type="text" id="state_' + id +'">';
					html_str += '</div></div>';
					html_str += '<div class="span3" style="margin-left: 10px;">';
					html_str += '<div class="input text" style="margin: 0;">';
					html_str += '<input placeholder="Click Quota" class="validate[custom[onlyNumberSp]]" name="data[geo_state][' + id + '][click_quota]" value="' + click_quota + '" style="width: 70px;" type="text" id="state_' + id +'_click_quota">';
					html_str += '</div></div>';
					html_str += '<div class="span3" style="margin-left: 10px;">';
					html_str += '<div class="input text" style="margin: 0;">';
					html_str += '<input placeholder="Clicks" class="validate[custom[onlyNumberSp]]" name="data[geo_state][' + id + '][clicks]" value="' + clicks + '" style="width: 70px;" type="text" id="state_' + id +'_clicks">';
					html_str += '</div></div></div>';
				}
				html_str += '</div>';
			}
		}
		$('#edit_area').append(html_str);
		$('#edit_area').css('border', '1px solid #ccc');
		$('#edit_area').css('border-radius', '5px');
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
				if (distributions[i]['ProjectClickDistribution'].key == 'gender') {
					if (distributions[i]['ProjectClickDistribution'].gender == 1) {
						return_value.male = {};
						return_value.male.percentage = distributions[i]['ProjectClickDistribution'].percentage;
						return_value.male.click_quota = distributions[i]['ProjectClickDistribution'].click_quota;
						return_value.male.clicks = distributions[i]['ProjectClickDistribution'].clicks;
					}
					else {
						return_value.female = {};
						return_value.female.percentage = distributions[i]['ProjectClickDistribution'].percentage;
						return_value.female.click_quota = distributions[i]['ProjectClickDistribution'].click_quota;
						return_value.female.clicks = distributions[i]['ProjectClickDistribution'].clicks;
					}
				}
			}
			else {
				if (distributions[i]['ProjectClickDistribution'].key == key && distributions[i]['ProjectClickDistribution'].other == '0') {
					return_value[distributions[i]['ProjectClickDistribution'].answer_id] = {};
					return_value[distributions[i]['ProjectClickDistribution'].answer_id].percentage = distributions[i]['ProjectClickDistribution'].percentage;
					return_value[distributions[i]['ProjectClickDistribution'].answer_id].click_quota = distributions[i]['ProjectClickDistribution'].click_quota;
					return_value[distributions[i]['ProjectClickDistribution'].answer_id].clicks = distributions[i]['ProjectClickDistribution'].clicks;
				}
			}
		}
		if ($.isEmptyObject(return_value)) {
			return false;
		}
		else {
			return return_value;
		}
	}
</script>