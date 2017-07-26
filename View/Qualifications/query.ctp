<h4><?php echo empty($project_id) ? 'Run Feasibility Query' : 'Create Query for ' . $this->App->project_name($project); ?></h4>
<?php if (!empty($project_id)): ?>
	<?php echo $this->Form->create('Query'); ?>
<?php endif; ?>
<div class="row-fluid query">
	<div class="span9">
		<div class="box">
			<div class="box-header">
				<span class="title">Panelists</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<div class="row-fluid row-query user-filters" id="gender_area">
						<div class="span2">
							<label class="pull-right">
								Gender
							</label>
						</div>
						<div class="span10 button-radio-list">
							<div class="btn btn-primary btn-small pull-right" id="panelist_clear_btn">Clear</div>
							<?php
							$options = array();
							$options[''] = 'All';
							foreach ($gender_question['Answers'] as $id => $value) {
								$options[$id] = $value;
							}
							echo $this->Form->radio($gender_question['Question']['partner_question_id'],
								$options,
								array(
									'class' => 'input-radio',
									'legend' => false,
									'value' => 'All'
								)
							);
							?>
						</div>
					</div>
					<div class="row-fluid row-query user-filters" id="age_area">
						<div class="span2">
							<label class="pull-right" for="QueryAgeFrom">
								Age
							</label>
						</div>
						<div class="span10" id="age_line">
							<div class="form-group">
								<?php echo $this->Form->input('age_from', array(
									'label' => false,
									'value' => '18',
									'class' => 'validate[required,custom[onlyNumberSp],min[13]]',
									'style' => 'width: 55px;'
								)); ?>
								<?php echo $this->Form->input('age_to', array(
									'label' => false,
									'value' => '99',
									'class' => 'validate[required,custom[onlyNumberSp],max[99]]',
									'style' => 'width: 55px;'
								)); ?>
							</div>
						</div>
					</div>
					<div class="row-fluid row-query user-filters" id="location_area">
						<div class="span2">
							<label class="pull-right" for="location_filter_dropdown">
								Location
							</label>
						</div>
						<div class="span10" id="location_line">
							<?php
							if ($country == 'us') {
								$options = array(
									array('name' => 'States', 'value' => 'states', 'partner_question_id' => 96),
									array('name' => 'Regions', 'value' => 'regions', 'partner_question_id' => 96),
									array('name' => 'Zip Codes', 'value' => 'zip_codes', 'partner_question_id' => 45),
									array('name' => 'DMAs', 'value' => 'dmas', 'partner_question_id' => 97),
									array('name' => 'Counties', 'value' => 'counties', 'partner_question_id' => 98),
								);
							}
							else if ($country == 'gb') {
								$options = array(
									array('name' => 'Regions', 'value' => 'regions', 'partner_question_id' => 12452),
									array('name' => 'Postal Codes', 'value' => 'zip_codes', 'partner_question_id' => 12370),
									array('name' => 'Counties', 'value' => 'counties', 'partner_question_id' => 12453),
								);
							}
							else {
								$options = array(
									array('name' => 'Regions', 'value' => 'regions', 'partner_question_id' => 29459),
									array('name' => 'Postal Codes', 'value' => 'zip_codes', 'partner_question_id' => 1008),
									array('name' => 'Provinces', 'value' => 'counties', 'partner_question_id' => 1015),
								);
							}
							echo $this->Form->input(null, array(
								'name' => false,
								'label' => false,
								'type' => 'select',
								'options' => $options,
								'empty' => 'Select by location parameter:',
								'id' => 'location_filter_dropdown',
								'div' => array(
									'style' => 'display: inline-block;'
							 	),
								'style' => 'margin-bottom: 0px;'
							));
							?>
						</div>
					</div>
					<div class="row-fluid row-query" id="location_section" style="display: none;">
						<div class="span10 offset2 location" id="<?php echo strtoupper($country); ?>">

						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="box">
			<div class="box-header">
				<span class="title">User Profile Filters</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<div class="row-fluid" style="margin-bottom: 10px;">
						<div class="btn btn-primary btn-small pull-right" id="user_profile_clear_btn">Clear</div>
					</div>
					<div class="row-fluid row-query" id="profile_filter_section">
						<div class="span3" id="profile_filter_titles">
							<ul class="pull-right">
								<?php foreach ($questions as $question_key => $question):
									$question_key = str_replace(' ', '_', $question_key);
									if ($question_key == 'GENDER') {
										continue;
									}
									?>
									<li partner_question_id="<?php echo $question['Question']['partner_question_id'] ;?>" id="<?php echo $question_key; ?>"><?php echo $question['QuestionText']['cp_text']; ?></li>
								<?php endforeach ?>
								<li id="more_options">More Options</li>
							</ul>
						</div>
						<div class="span9">
							<div class="row-fluid" id="profile_search_section">
								<div class="row-fulid">
									<div class="span5 searchbox-area" style="margin-left:0">
										<?php echo $this->Form->input('keyword', array(
											'label' => false,
											'id' => 'search_keyword'
										)); ?>
									</div>
									<div class="span3 searchbox-area" style="margin-left:0">
										<div class="btn btn-primary" id="search_button" style="margin-left: 10px;">
											Search
										</div>
									</div>
								</div>
							</div>
							<?php foreach ($questions as $question_key => $question) {
								$question_key = str_replace(' ', '_', $question_key);
								if ($question_key == 'gender') {
									continue;
								}
								?>
								<div class="row-fluid profile_options" id="<?php echo $question_key; ?>_options"
									 style="display: none;">
									<div class="question-line"><?php echo $question['QuestionText']['text']; ?></div>
									<?php if (count($question['Answers']) <= 28) {
										foreach ($question['Answers'] as $id => $value) {
											echo $this->Form->input($question['Question']['partner_question_id'] . '.' . $id, array(
												'type' => 'checkbox',
												'data-filter' => $question_key,
												'partner_question_id' => $question['Question']['partner_question_id'],
												'answer_id' => $id,
												'high_usage' => '1',
												'label' => $value
											));
										}
									} else {
										$answers = $question['Answers'];
										$answer_chunked = array_chunk($answers, ceil(count($answers) / 2), true);
										foreach ($answer_chunked as $answers) {
											?>
											<div class="span6" style="margin-left: 0">
												<?php
												foreach ($answers as $id => $value) {
													echo $this->Form->input($question['Question']['partner_question_id'] . '.' . $id, array(
														'type' => 'checkbox',
														'data-filter' => $question_key,
														'partner_question_id' => $question['Question']['partner_question_id'],
														'answer_id' => $id,
														'high_usage' => '1',
														'label' => $value
													));
												}
												?>
											</div>
											<?php
										}
									}
									?>
								</div>
							<?php } ?>
							<div class="row-fulid">
								<div id="searched_questions">

								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="box">
			<div class="box-header">
				<span class="title">User IDs</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<div class="row-fluid row-query user-ids">
						<div class="span3">
						</div>
						<div class="span8">
							These will not update the query in real-time; the rules will be executed upon save.
						</div>
					</div>
					<div class="row-fluid row-query user-ids">
						<div class="span3">
							<label class="pull-right" for="QueryUserId">
								Target Additional User IDs
							</label>
						</div>
						<div class="span6">
							<?php echo $this->Form->input('user_id', array(
								'label' => false,
								'type' => 'textarea',
								'style' => 'height: 52px'
							)); ?>
						</div>
					</div>
					<div class="row-fluid row-query user-ids">
						<div class="span3">
							<label class="pull-right" for="QueryExcludeUserId">
								<span class="text-error">Exclude</span> User IDs
							</label>
						</div>
						<div class="span6">
							<?php echo $this->Form->input('exclude_user_id', array(
								'label' => false,
								'type' => 'textarea',
								'style' => 'height: 52px'
							)); ?>
						</div>
					</div>
					<div class="row-fluid row-query user-ids">
						<div class="span3">
							<label class="pull-right" for="QueryCompleteExistingProjectId">
								<span class="text-error">Exclude</span> Completes from Project(s)
							</label>
						</div>
						<div class="span6">
							<?php echo $this->Form->input('existing_complete_project_id', array(
								'label' => false,
								'type' => 'textarea',
								'style' => 'height: 52px;'
							)); ?>
						</div>
					</div>
					<div class="row-fluid row-query user-ids">
						<div class="span3">
							<label class="pull-right" for="QueryClickExistingProjectId">
								<span class="text-error">Exclude</span> Clicks from Project(s)
							</label>
						</div>
						<div class="span6">
							<?php echo $this->Form->input('existing_click_project_id', array(
								'label' => false,
								'type' => 'textarea',
								'style' => 'height: 52px'
							)); ?>
						</div>
					</div>					
				</div>
			</div>
		</div>
	</div>
	<div class="span3" id="query-save-form">
		<div class="box">
			<div class="box-header">
				<span class="title">Your Query</span>
			</div>
			<div class="box-content">
				<div class="row-fluid row-query" id="checked_filter_options" style="padding: 10px">
					<div id="panelist_checked_options">
						<div id="selected_gender" class="checked_options" style="display: none;">
							<strong>Gender: </strong>
							<span></span>
						</div>
						<div id="checked_age" class="checked_options">
							<strong>Age: </strong>
							<span>18</span> - <span>99</span>
						</div>
					</div>
					<div id="profile_checked_options">

					</div>
					<div id="additional_info" style="margin-bottom: 10px;">

					</div>
					<?php echo $this->Html->link('Show query debug mode',
						'#',
						array(
							'id' => 'toggle_debug_mode',
							'class' => 'show',
							'style' => 'color: #004FCC;',
							'onclick' => 'return toggle_debug_mode()'
						)
					);?>
					<div class="row-fluid" id="query_debug_textarea" style="padding: 0; display: none;">
						<label for="display">Query</label>
						<textarea rows="6" id="display" style="width: 97%;"></textarea>
					</div>
					<?php if (empty($project_id)): ?>
					<div class="row-fulid">
						<?php
							$options = array(
								array('name' => 'Last Week', 'value' => 'active_within_week'),
								array('name' => 'Last Month', 'value' => 'active_within_month', 'selected' => true),
								array('name' => 'Last 60 days', 'value' => 'active_within_60_days'),
								array('name' => 'Last 90 days', 'value' => 'active_within_90_days'),
							);
							echo $this->Form->input(null, array(
								'name' => false,
								'label' => 'Select Active Time Range',
								'type' => 'select',
								'options' => $options,
								'id' => 'time_range_dropdown',
								'div' => array(
									'style' => 'display: inline-block; width: 100%;'
								),
								'style' => 'margin-bottom: 0px;'
							));
						?>
					</div>
					<?php endif; ?>
				</div>
				<?php if (!empty($project_id)): ?>
					<div class="row-fluid row-qualification" id="qualification_section">
						<?php echo $this->Form->create('Qualification'); ?>
						<?php echo $this->Form->input('name', array(
							'label' => 'Name',
							'type' => 'text',
							'class' => 'validate[required]'
						)); ?>
						<div class="span4">
						<?php echo $this->Form->input('quota', array(
							'label' => 'Quota',
							'value' => $project['Project']['quota'],
							'type' => 'text',
							'style' => 'margin-bottom: 0',
							'class' => 'validate[required,custom[onlyNumberSp]'
						)); ?>
						</div>
						<div class="span4">
						<?php echo $this->Form->input('cpi', array(
							'label' => 'CPI',
							'value' => $project['Project']['client_rate'],
							'between' => '<div class="input-prepend"><span class="add-on" href="#"><i class="icon-none">$</i></span>',
							'after' => '</div>',
							'type' => 'text',
						)); ?>
						</div>
						<div class="span4">
						<?php echo $this->Form->input('award', array(
							'label' => 'Award',
							'value' => $project['Project']['award'],
							'type' => 'text',
							'class' => 'validate[required,custom[onlyNumberSp]'
						)); ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
			<div id="count_line" style="padding: 5px; background-color: #eaeaea;font-size: 14px; text-align: center">
				Matched: <strong><?php echo $count; ?></strong> Panelists
				<?php echo '<span class="btn-waiting" style="display: none;">' . $this->Html->image('ajax-loader.gif') . '</span>'; ?>
			</div>
		</div>
		<?php if (!empty($project_id)): ?>
			<div style="margin-top: 10px;">
				<?php echo $this->Form->submit('Save Qualification', array('class' => 'btn btn-large btn-primary', 'style' => 'width: 100%;')); ?>
			</div>
		<?php endif; ?>
	</div>
</div>
<?php if (!empty($project_id)): ?>
	<?php echo $this->Form->end(null); ?>
<?php endif; ?>

<script type="text/javascript">
	var questions = <?php echo json_encode($questions); ?>;
	var question_keys = <?php echo json_encode($question_keys); ?>;
	var gender_question = <?php echo json_encode($gender_question); ?>;
	var is_added = [];
	var country = "<?php echo $country; ?>";
	var post_data = {};
	var query_json = {};
	var query_arr = [];
	var default_query_json = {};
	var ajax_requests = [];
	var count = '<?php echo $count; ?>';
	var project_id = '<?php echo empty($project_id) ? 'null': $project_id; ?>';
	$(document).ready(function() {
		$('form').validationEngine();
		// make age post array(temp)
		for (var i = 18; i <= 99; i++) {
			query_arr.push(i);
		}
		query_json['42'] = query_arr;
		default_query_json['42'] = query_arr;
		if (project_id == 'null') {
			query_json['active_within_month'] = true;
			default_query_json['active_within_month'] = true;
		}
		get_panelist_count(true);

		$('#checked_filter_options').css('max-height', (($(window).height() * 2) / 3) - 20);
		$('#checked_filter_options').css({
			'width': $('#query-save-form').width() - 15
		});

		var changed_question_keys = [];
		for (var i = 0; i < question_keys.length; i ++) {
			changed_question_key = question_keys[i].replace(/ /g, '_');
			changed_question_keys.push(changed_question_key);
		}
		question_keys = changed_question_keys;

		$('.button-radio-list input[type="radio"]').eq(0).attr('checked', true);
		$('.button-radio-list label').eq(0).addClass('btn btn-primary');
		$('.button-radio-list label').eq(0).addClass('selected');
		$('.button-radio-list label').eq(0).css('border-right', 'none');
		$('.button-radio-list label').eq(0).css('border-top-left-radius', '3px');
		$('.button-radio-list label').eq(0).css('border-bottom-left-radius', '3px');
		$('.button-radio-list label').click(function() {
			var gender_partner_question_id = gender_question['Question']['partner_question_id'];
			$('.button-radio-list label').removeClass('selected');
			$('.button-radio-list label').removeClass('btn btn-primary');
			$(this).addClass('selected');
			$(this).addClass('btn btn-primary');
			if ($(this).prev().val() == '1') {
				$('#selected_gender').show();
				$('#selected_gender span').text('Male');
			}
			else if ($(this).prev().val() == '2') {
				$('#selected_gender').show();
				$('#selected_gender span').text('Female');
			}
			else {
				$('#selected_gender').hide();
				$('#selected_gender span').text('');
			}
			$(this).prev().attr('checked', true);
			if ($(this).prev().val() != "") {
				query_json[gender_partner_question_id] = $(this).prev().val();
			}
			else {
				delete query_json[gender_partner_question_id];
			}
			get_panelist_count();
		});

		$('#profile_filter_section .span9').css('height', $('#profile_filter_titles').height());

		// Fix position Checked Filter Options
		$(window).scroll(fixCheckedOptionsDiv);
		fixCheckedOptionsDiv();
		$('#age_line input[type="text"]').keypress(function(e) {
			if (e.which == 13) {
				return false;
			}
		});
		// Age Input Keyup Event
		$('#age_line input[type="text"]').keyup(function(event) {
			var index = $('#age_line input[type="text"]').index(this);
			$('#checked_age span').eq(index).text($(this).val());
		});
		$('#age_line input[type="text"]').change(function() {
			var id = $(this).attr('id');
			query_arr = [];
			var age_from = parseInt($('#age_line input[type="text"]').eq(0).val());
			var age_to = parseInt($('#age_line input[type="text"]').eq(1).val());
			for (var i = age_from; i <= age_to; i ++) {
				query_arr.push(i);
			}
			if (query_arr.length > 0) {
				query_json['42'] = query_arr;
				get_panelist_count();
			}
			return false;
		});
		filter_title_click();
		filter_option_change();

		// Location Filter Dropdown Change Event
		$('#location_filter_dropdown').change(function() {
			var filter_option = $(this).val();
			if (filter_option != "") {
				$('#location_area').css('margin-bottom', '10px');
				$('#location_section').show();
			}
			if ($.inArray(filter_option, is_added) != -1) {
				return;
			}
			is_added.push(filter_option);
			call_ajax(filter_option);
		});

		$('#search_keyword').bind('keypress', function(e) {
			if (e.keyCode == 13) {
				more_options();
				return false;
			}
		});
		// Search Button Click Event
		$('#search_button').click(function() {
			more_options();
		});

		// Time Range Dropdown Change Event
		$('#time_range_dropdown').change(function() {
			var selected_range = $(this).val();
			$('#time_range_dropdown > option').each(function() {
				if (selected_range == $(this).val()) {
					query_json[selected_range] = true;
				}
				else {
					delete query_json[$(this).val()];
				}
			});
			get_panelist_count();
		});
		clear_panelist_filter();
		clear_user_profile_filter();
	});

	function clear_panelist_filter() {
		$('#panelist_clear_btn').unbind().bind('click', function() {
			$('.button-radio-list input[type="radio"]').eq(0).attr('checked', true);
			$('.button-radio-list label').removeClass('btn btn-primary');
			$('.button-radio-list label').eq(0).addClass('btn btn-primary');

			$('#age_line input[type="text"]').eq(0).val('18');
			$('#age_line input[type="text"]').eq(1).val('99');

			$('.location .span11').remove();
			$('#location_section').hide();
			$('#location_area').css('margin-bottom', '0');
			$("#location_filter_dropdown").val($("#location_filter_dropdown option:first").val());
			$('#location_filter_dropdown option').each(function() {
				if ($(this).val() != '') {
					var partner_question_id = $(this).attr('partner_question_id');
					if (!(_.isEqual(query_json, default_query_json))) {
						if (query_json[partner_question_id] != undefined) {
							delete query_json[partner_question_id];
						}
					}
				}
			});
			$('#panelist_checked_options .checked_options').each(function() {
				if ($(this).attr('id') == 'selected_gender') {
					$(this).find('span').text('');
				}
				else if ($(this).attr('id') == 'checked_age') {
					$(this).find('span').eq(0).text('18');
					$(this).find('span').eq(1).text('99');
				}
				else {
					$(this).remove();
				}
			});
			if (!(_.isEqual(query_json, default_query_json))) {
				query_json['42'] = default_query_json['42'];
				delete query_json['43'];
				get_panelist_count();
			}
		});
	}

	function clear_user_profile_filter() {
		$('#user_profile_clear_btn').unbind().bind('click', function() {
			$('#profile_filter_section input[type="checkbox"]').each(function() {
				$(this).attr('checked', false);
			});
			$('#profile_filter_section .profile_options').hide();
			$('#profile_checked_options .checked_options').remove();
			$('#profile_search_section .searchbox-area').hide();
			$('#profile_filter_titles ul li').each(function() {
				var question_key = $(this).attr('id');
				if (question_key != 'more_options') {
					if ($.inArray(question_key, question_keys) == -1) {
						$(this).hide();
					}
					$(this).css('font-weight', 'normal');
				}
				if ($(this).hasClass('list-selected')) {
					$(this).removeClass('list-selected');
				}
			});
			var flag = false;
			$('#profile_filter_section .profile_options').each(function() {
				if (flag) {
					flag = flag;
				}
				var id = $(this).attr('id');
				var partner_question_id = $('#' + id + ' input[type="checkbox"]').eq(0).attr('partner_question_id');
				if (query_json[partner_question_id] != undefined) {
					delete query_json[partner_question_id];
					flag = true;
				}
			});
			if (flag) {
				get_panelist_count();
			}
			else {
				if (!(_.isEqual(query_json,default_query_json))) {
					get_panelist_count();
				}
			}
			$('#profile_filter_section .span9').css('height', $('#profile_filter_titles ul').height());
		});
	}

	var last_clicked = 0;
	function get_panelist_count(first_load_flag) {
		$('#display').val(format_json(query_json));
		if (!first_load_flag) {
			last_clicked = (new Date).getTime();
			setTimeout(function() {
				ajax_for_get_panelist_count();
			}, 500);
		}
	}

	function ajax_for_get_panelist_count() {
		if ((new Date).getTime() - last_clicked < 500) {
			return;
		}
		if (!_.isEqual(query_json, default_query_json)) {
			post_data['Query'] = query_json;
			$('.btn-waiting').show();
			ajax_requests.push(
				$.post('/qualifications/query_api_count/' + country, {data: post_data}, function(data) {
					var count = data.count;
					var formatted_count = count.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
					$('#count_line strong').text(formatted_count);
					$('.btn-waiting').hide();
				})
			);
		}
		else {
			$('.btn-waiting').show();
			$('#count_line strong').text(count);
			$('.btn-waiting').hide();
		}
		for (var i = 0; i < ajax_requests.length - 1; i ++) {
			ajax_requests[i].abort();
		}
	}

	function format_json(json_obj) {
		var output = "{";
		var line = 0;
		for (c in json_obj) {
			if (line > 0) {
				output += ",";
			}
			line ++;
			output += "\n" + '"' + c + '": ' + JSON.stringify(json_obj[c]);
		}
		output += "\n}";
		return output;
	}

	function more_options() {
		var search_keyword = $('#search_keyword').val();
		if (search_keyword == '') {
			return;
		}
		$.post('/qualifications/ajax_search_question/', {keyword: search_keyword, country: country}, function(data) {
			var searched_questions = data.questions;
			var searched_answers = data.answers;
			if ($('#searched_questions').children('label').length > 0) {
				$('#searched_questions label').remove();
			}

			$('.profile_options').hide();
			if (searched_questions.length == 0) {
				var html_str = '<label style="color:#b94a48">No result found!</label>';
				$('#searched_questions').append(html_str);
			}
			var html_str = '';
			for (var question_id in searched_questions) {
				var answers = searched_answers[question_id];
				var answersCount = _.keys(answers).length;
				var chunk = Math.ceil(answersCount / 2);

				var partner_question_id = searched_questions[question_id]['partner_question_id'];
				var question_key = searched_questions[question_id]['question'];
				if ($.inArray(question_key, question_keys) != -1) {
					question_key = question_key.replace(/ /g, '_');
					$('#' + question_key + '_options').show();
					continue;
				}
				question_key = question_key.replace(/ /g, '_');
				html_str += '<div class="row-fluid profile_options" id="' + question_key + '_options">';
				html_str += '<div class="question-line">' + searched_questions[question_id]['text'] + '</div>';
				if (answersCount <= 28) {
					for (var id in answers) {
						html_str += '<div class="input checkbox">';
						html_str += '<input type="hidden" name="data[' + partner_question_id + '][' + id + ']" id="' + partner_question_id + id + '_" value="0">';
						html_str += '<input type="checkbox" answer_id="' + id + '" partner_question_id="' + partner_question_id + '" ';
						html_str += 'name="data[' + partner_question_id + '][' + id + ']" answer_type="searched" question_id="' + question_id + '" ';
						html_str += 'id="' + partner_question_id + id + '" value="1" data-filter="' + question_key + '" high_usage="0">';
						html_str += '<label for="' + partner_question_id + id + '">' + answers[id] + '</label>';
						html_str += '</div>';
					}
				}
				else {
					html_str += '<div class="span6" style="margin-left:0;">';
					var count = 0;
					for (var id in answers) {
						if (count < chunk) {
							html_str += '<div class="input checkbox">';
							html_str += '<input type="hidden" name="data[' + partner_question_id + '][' + id + ']" id="' + partner_question_id + id + '_" value="0">';
							html_str += '<input type="checkbox" answer_id="' + id + '" partner_question_id="' + partner_question_id + '" ';
							html_str += 'name="data[' + partner_question_id + '][' + id + ']" answer_type="searched" question_id="' + question_id + '" ';
							html_str += 'id="' + partner_question_id + id + '" value="1" data-filter="' + question_key + '" high_usage="0">';
							html_str += '<label for="' + partner_question_id + id + '">' + answers[id] + '</label>';
							html_str += '</div>';
						}
						else {
							break;
						}
						count++;
					}
					html_str += '</div>';
					html_str += '<div class="span6" style="margin-left:0;">';
					count = 0;
					for (var id in answers) {
						if (count >= chunk) {
							html_str += '<div class="input checkbox">';
							html_str += '<input type="hidden" name="data[' + partner_question_id + '][' + id + ']" id="' + partner_question_id + id + '_" value="0">';
							html_str += '<input type="checkbox" answer_id="' + id + '" partner_question_id="' + partner_question_id + '" ';
							html_str += 'name="data[' + partner_question_id + '][' + id + ']" answer_type="searched" question_id="' + question_id + '" ';
							html_str += 'id="' + partner_question_id + id + '" value="1" data-filter="' + question_key + '" high_usage="0">';
							html_str += '<label for="' + partner_question_id + id + '">' + answers[id] + '</label>';
							html_str += '</div>';
						}
						count++;
					}
					html_str += '</div>';
				}
				html_str += '</div>';
			}
			$('#profile_search_section').after(html_str);

			// Filter Title Click and Filter Options Change event
			filter_title_click();
			filter_option_change(searched_questions);
		});
	}

	function fixCheckedOptionsDiv() {
		if ($(window).scrollTop() > 100) {
			$('#query-save-form').css({
				'position': 'fixed',
				'right': '15px',
				'top': '10px',
				'width': '23%'
			});

		}
		else {
			$('#query-save-form').css({
				'position': 'relative',
				'right': '0',
				'top': 'auto',
			});
		}
		$('#checked_filter_options').css({
			'width': $('#query-save-form').width() - 20
		});
	}

	function filter_title_click() {
		// Profile Filter Titles Click Event
		$('#profile_filter_titles ul li').unbind().bind('click', function() {
			var id = $(this).attr('id');
			$('#profile_filter_titles ul li').removeClass('list-selected');
			$(this).addClass('list-selected');
			if (id == "more_options") {
				$('.profile_options').hide();
				$('#search_keyword').val('');
				$('#profile_search_section .searchbox-area').show();
				$('#search_keyword').focus();
			}
			else {
				$('#profile_search_section .searchbox-area').hide();
				$('.profile_options').hide();
				$('#' + id + '_options').show();
				$('.checked_options').each(function() {
					if ($(this).children().length == 0) {
						$(this).remove();
					}
				});
			}
		});
	}

	function filter_option_change(questions) {
		// Profile Option Change Event
		$('.profile_options input[type="checkbox"]').unbind().bind('change', function() {
			var data_filter = $(this).attr('data-filter');
			var partner_question_id = $(this).attr('partner_question_id');
			var answer_id = $(this).attr('answer_id');
			var cp_text = $('#' + data_filter).text().toLowerCase();
			if (this.checked) {
				var label = $(this).next().text();
				if (questions != undefined) {
					if ($('#profile_filter_titles').find($('#' + data_filter)).length == 0) {
						var question_id = $(this).attr('question_id');
						var html_str = '<li partner_question_id="' + partner_question_id + '" id="' + data_filter + '">' + questions[question_id]['cp_text'] + '</li>';
						$('#more_options').before(html_str);
						$('#profile_filter_section .span9').css('height', $('#profile_filter_titles ul').height());
						filter_title_click();
					}
				}
				if ($('#profile_checked_options').find($('#checked_' + data_filter)).length == 0) {
					query_arr = [];
					var html_str = '<div id="checked_' + data_filter + '" class="checked_options">';
					html_str += $('#' + data_filter).text() + ": ";
					html_str += '</div>';
					$('#profile_checked_options').append(html_str);
				}
				if ($('#checked_' + data_filter).find('span').length == 0) {
					var html_str = '<span id="' + data_filter + '_' + $(this).attr('id') + '">' + label + '</span></div>';
				}
				else {
					var html_str = '<span id="' + data_filter + '_' + $(this).attr('id') + '">, ' + label + '</span></div>';
				}
				$('#checked_' + data_filter).append(html_str);
				$('#' + data_filter).css('font-weight', 'bold');
				query_arr.push(answer_id);
			}
			else {
				$('#' + data_filter + '_' + $(this).attr('id')).remove();
				query_arr = remove_value(query_arr, answer_id);
				if ($('#checked_' + data_filter).children().length == 0) {
					$('#checked_' + data_filter).remove();
				}
				else {
					var current_text = $('#checked_' + data_filter).find('span').eq(0).text();
					var new_text = current_text.replace(/, /g, '');
					$('#checked_' + data_filter).find('span').eq(0).text(new_text);
				}
				if ($('#profile_checked_options').find($('#checked_' + data_filter)).length == 0) {
					$('#' + data_filter).css('font-weight', 'normal');
				}
			}
			if (query_arr.length > 0) {
				query_json[partner_question_id] = query_arr;
			}
			else {
				delete query_json[partner_question_id];
			}
			get_panelist_count();
		});
	}

	function remove_value(arr, element) {
		arr = $.grep(arr, function(value) {
			return value != element;
		});
		return arr;
	}

	function call_ajax(filter_option) {
		var url;
		if (filter_option == 'states') {
			url = '/qualifications/ajax_get_states/';
		}
		else if (filter_option == 'regions') {
			url = '/qualifications/ajax_get_regions/?country=' + country;
		}
		else if (filter_option == 'zip_codes') {
			if (country == 'us') {
				var partner_question_id = 45;
				var add_btn_name = "Add Zips";
				var label = "Zip Codes";
				var span_text = 'Zip';
			}
			else if (country == 'gb') {
				var partner_question_id = 12370;
				var add_btn_name = "Add Postals";
				var label = "Postal Codes";
				var span_text = 'Postal';
			}
			else {
				var partner_question_id = 1008;
				var add_btn_name = "Add Postals";
				var label = "Postal Codes";
				var span_text = 'Postal';
			}
			var html_str = '<div class="span11" id="zipcode_section">';
			html_str += '<i class="icon-trash pull-right" id="zip_codes" partner_question_id=' + partner_question_id + '></i>';
			html_str += '<div class="span10">';
			html_str += '<textarea rows="3" id="zip_textarea" partner_question_id=' + partner_question_id + ' name="data[Query][' + partner_question_id + ']"></textarea>';
			html_str += '</div>';
			html_str += '<div class="input file">';
			html_str += '<div class="span10">';
			html_str += '<div class="uploader" id="uniform-QueryZipFile">';
			html_str += '<input type="file" name="data[Query][zip_file]" id="QueryZipFile">';
			html_str += '<span class="filename" style="user-select: none;">No file selected</span>';
			html_str += '<span class="action" style="user-select: none">+</span>';
			html_str += '</div></div>';
			html_str += '<div class="span2">';
			html_str += '<div class="btn btn-primary" id="add_zip_file">' + add_btn_name + '</div>';
			html_str += '</div></div></div>';
			$('.location').prepend(html_str);
			$('#add_zip_file').addClass('add_zip_file_disabled');
			$('#QueryZipFile').change(function(e) {
				var filename = $(this).val().split('\\').pop();
				var extension = filename.split('.').pop();
				if (extension.toLowerCase() == 'csv') {
					$('#zipcode_section .filename').text(filename);
					$('#add_zip_file').removeClass('add_zip_file_disabled');
				}
			});

			$('#zip_textarea').keyup(function(e) {
				var value = $(this).val().replace(/ /g, '');
				if (value != '') {
					var zipcodes = value.split("\n");
					zipcodes = clean_array(zipcodes);
					zipcodes = zipcodes.sort();
					$('#checked_zips').remove();
					var html_str = '<div id="checked_zips" class="checked_options"><strong>' + label + ': </strong>';
					if (zipcodes.length == 1) {
						html_str += '<span id="zips_first">' + zipcodes[0] + '</span> (1 ' + span_text + ')';
					}
					else {
						html_str += '<span id="zips_first">' + zipcodes[0] + '</span> - ';
						html_str += '<span id="zips_last">' + zipcodes[zipcodes.length - 1] + '</span> (';
						html_str += '<span id="zips_count">' + zipcodes.length + '</span> ' + span_text +'s)';
					}
					$('#panelist_checked_options').append(html_str);
				}
				else {
					$('#checked_zips').remove();
				}
			});

			$('#zip_textarea').bind('paste', function(e) {
				setTimeout(function() {
					var zipcodes = $('#zip_textarea').val().split("\n");
					zipcodes = clean_array(zipcodes);
					zipcodes = zipcodes.sort();
					$('#checked_zips').remove();
					var html_str = '<div id="checked_zips" class="checked_options"><strong>' + label + ': </strong>';
					if (zipcodes.length == 1) {
						html_str += '<span id="zips_first">' + zipcodes[0] + '</span> (1 ' + span_text + ')';
					}
					else {
						html_str += '<span id="zips_first">' + zipcodes[0] + '</span> - ';
						html_str += '<span id="zips_last">' + zipcodes[zipcodes.length - 1] + '</span> (';
						html_str += '<span id="zips_count">' + zipcodes.length + '</span> ' + span_text + 's)';
					}
					$('#panelist_checked_options').append(html_str);
				}, 100);
			});

			$('#add_zip_file').click(function() {
				if ($(this).hasClass('add_zip_file_disabled')) {
					return;
				}
				var file_data = $('#QueryZipFile').prop('files')[0];
				var form_data = new FormData();
				form_data.append("data[Query][zip_file]", file_data);
				$.ajax({
					url: '/qualifications/ajax_parse_zipcodes',
					dataType: 'script',
					cache: false,
					contentType: false,
					processData: false,
					data: form_data,
					type: 'POST',
					statusCode: {
						201: function(data) {
							query_arr = [];
							var partner_question_id = $('#zip_textarea').attr('partner_question_id');
							var zipcodes = JSON.parse(data.responseText);
							zipcodes = zipcodes.zipcodes;
							zipcodes = clean_array(zipcodes);
							zipcodes = zipcodes.sort();
							zipcodes = zipcodes.join("\n");
							if ($('#zip_textarea').val() != '') {
								var value = zipcodes + "\n" + $('#zip_textarea').val();
							}
							else {
								var value = zipcodes;
							}
							$('#zip_textarea').val(value);
							zipcodes = value.split("\n");
							zipcodes = clean_array(zipcodes);
							zipcodes = zipcodes.sort();
							if (country == 'us') {
								query_arr = zipcodes;
							}
							else if (country == 'gb'){
								query_arr =  [];
								for (var i = 0; i < zipcodes.length; i ++) {
									if (zipcodes[i].search(' ') > 0) {
										var postal_prefix = zipcodes[i].split(' ');
										postal_prefix = postal_prefix[0];
									}
									else {
										var count = zipcodes[i].length;
										if (count == 7) {
											var length = 4;
										}
										else if (count == 5) {
											var length = 2;
										}
										else {
											var length = 3;
										}
										var postal_prefix = zipcodes[i].substr(0, length);
									}
									query_arr.push(postal_prefix);
								}
							}
							else if (country == 'ca') {
								query_arr = [];
								for (var i = 0; i < zipcodes.length; i ++) {
									query_arr.push(zipcodes[i].substr(0, 3).toUpperCase());
								}
							}
							if (query_arr.length > 0) {
								query_json[partner_question_id] = query_arr;
							}
							else {
								delete query_json[partner_question_id];
							}
							get_panelist_count();
							if ($('#panelist_checked_options').find($('#checked_zips')).length > 0) {
								$('#checked_zips').remove();
							}
							var html_str = '<div id="checked_zips" class="checked_options"><strong>' + label + ': </strong>';
							if (zipcodes.length == 1) {
								html_str += '<span id="zips_first">' + zipcodes[0] + '</span> (1 ' + span_text + ')';
							}
							else {
								html_str += '<span id="zips_first">' + zipcodes[0] + '</span> - ';
								html_str += '<span id="zips_last">' + zipcodes[zipcodes.length - 1] + '</span> (';
								html_str += '<span id="zips_count">' + zipcodes.length + '</span> ' + span_text + 's)';
							}
							$('#panelist_checked_options').append(html_str);
							$('#QueryZipFile').val('');
							$('#zipcode_section .filename').text("No file selected");
							$('#add_zip_file').addClass('add_zip_file_disabled');
						}
					}
				});
			});

			$('#zip_textarea').bind('change', function() {
				var zipcodes = $('#zip_textarea').val().split("\n");
				zipcodes = clean_array(zipcodes);
				zipcodes = zipcodes.sort();
				var partner_question_id = $(this).attr('partner_question_id');
				if (country == 'us') {
					query_arr = zipcodes;
				}
				else if (country == 'gb'){
					query_arr =  [];
					for (var i = 0; i < zipcodes.length; i ++) {
						if (zipcodes[i].search(' ') > 0) {
							var postal_prefix = zipcodes[i].split(' ');
							postal_prefix = postal_prefix[0];
						}
						else {
							var count = zipcodes[i].length;
							if (count == 7) {
								var length = 4;
							}
							else if (count == 5) {
								var length = 2;
							}
							else {
								var length = 3;
							}
							var postal_prefix = zipcodes[i].substr(0, length);
						}
						query_arr.push(postal_prefix);
					}
				}
				else if (country == 'ca') {
					query_arr = [];
					for (var i = 0; i < zipcodes.length; i ++) {
						query_arr.push(zipcodes[i].substr(0, 3).toUpperCase());
					}
				}
				if (query_arr.length > 0) {
					query_json[partner_question_id] = query_arr;
				}
				else {
					delete query_json[partner_question_id];
				}
				get_panelist_count();
			});
		}
		else if (filter_option == 'dmas') {
			url = '/qualifications/ajax_get_dmas/';
		}
		else {
			url = '/qualifications/ajax_get_counties/?country=' + country;
		}
		if (filter_option != 'zip_codes') {
			$.post(url, function (data) {
				call_back(data, filter_option);
			});
		}
	}

	function clean_array(arr) {
		var newArr = new Array();
		for (var i = 0; i < arr.length; i++) {
			if (arr[i] == "") {
				continue;
			}
			newArr.push(arr[i]);
		}
		return newArr;
	}

	function call_back(filter_data, filter_option) {
		var country = "<?php echo $country; ?>";
		var html_str = "";
		if (filter_option == 'dmas') {
			var dmas = filter_data.dmas;
			var chunk = Math.ceil(dmas.length / 3);
			var dmas_chunked = [];
			for (i = 0, j = dmas.length; i < j; i += chunk) {
				dmas_chunked.push(dmas.slice(i, i + chunk));
			}

			html_str += '<div class="span11" id="dmas-section">';
			html_str += '<i class="icon-trash pull-right" id="dmas" partner_question_id="97"></i>';
			html_str += '<label><strong>DMAs</strong></label>';
			for (var i = 0; i < dmas_chunked.length; i++) {
				html_str += '<div class="span4">';
				for (var j = 0; j < dmas_chunked[i].length; j++) {
					var name = "data[Query][97][" + dmas_chunked[i][j]['LucidZip'].dma + "]";
					var element_id = "QueryDmaCode" + dmas_chunked[i][j]['LucidZip'].dma;
					var dma_code = dmas_chunked[i][j]['LucidZip'].dma;
					html_str += '<div class="input checkbox">';
					html_str += '<input type="hidden" name="' + name + '" id="' + element_id + '_" value="0">';
					html_str += '<input type="checkbox" name="' + name + '" answer_id="' + dma_code + '" partner_question_id="97" id="' + element_id + '" value="1">';
					html_str += '<label for="' + element_id + '">';
					html_str += dmas_chunked[i][j]['LucidZip'].dma_name;
					html_str += '</label></div>';
				}
				html_str += '</div>';
			}
			html_str += '</div></div>';
			$('#US').prepend(html_str);
		}
		else if (filter_option == "regions") {
			if (country == 'us') {
				var sub_regions = filter_data['sub_regions'];
				var states_precodes = filter_data['states_precodes'];
				html_str += '<div class="span11" id="regions-section">';
				html_str += '<i class="icon-trash pull-right" partner_question_id="96" id="regions"></i>';
				html_str += '<label><strong>Regions</strong></label>';
				for (region in sub_regions) {
					html_str += '<div class="span3">';
					html_str += '<label>';
					html_str += '<input type="checkbox" class="group-select" scope="parent" data-ref="' + region + '" partner_question_id=96>';
					html_str += ' ' + region;
					html_str += '</label>';
					for (i in sub_regions[region]) {
						var answer_ids = JSON.stringify(states_precodes[sub_regions[region][i]]);
						var answer_id_arr = states_precodes[sub_regions[region][i]];
						var name = '';
						for (var j = 0; j < answer_id_arr.length; j++) {
							name += parseInt(answer_id_arr[j]) + ',';
						}
						name = name.slice(0, -1);
						html_str += '<label class="sub-region">';
						html_str += '<input type="checkbox" class="group-select ' + region + '" parent="' + region + '" scope="child" partner_question_id=96 ';
						html_str += "answer_ids='" + answer_ids + "' data-ref='" + sub_regions[region][i].replace(/ /g, "_") + "' name='data[Query][96][" + name + "]'>";
						html_str += ' ' + sub_regions[region][i];
						html_str += '</label>';
					}
					html_str += '</div>';
				}
				html_str += '</div>';
				$('#US').prepend(html_str);
			}
			else {
				var regions = filter_data['regions'];
				var partner_question_id = regions[0].partner_question_id;
				var chunk = Math.ceil(regions.length / 3);
				var regions_chunked = [];
				for (i = 0, j = regions.length; i < j; i += chunk) {
					regions_chunked.push(regions.slice(i, i + chunk));
				}

				html_str += '<div class="span11" id="regions-section">';
				html_str += '<i class="icon-trash pull-right" id="regions" partner_question_id=' + partner_question_id + '></i>';
				html_str += '<label><strong>Regions</strong></label>';
				for (var i = 0; i < regions_chunked.length; i ++) {
					html_str += '<div class="span4">';
					for (var j = 0; j < regions_chunked[i].length; j ++) {
						var name = "data[Query][" + partner_question_id + "][" + regions_chunked[i][j].answer_id + "]";
						var element_id = "QueryRegions" + regions_chunked[i][j].answer_id;
						var answer_id = regions_chunked[i][j].answer_id;
						html_str += '<div class="input checkbox">';
						html_str += '<input type="hidden" name="' + name + '" id="' + element_id + '_" value="0">';
						html_str += '<input type="checkbox" name="' + name + '" answer_id="' + answer_id + '" partner_question_id=' + partner_question_id + ' id="' + element_id + '" value="1">';
						html_str += '<label for="' + element_id + '">';
						html_str += regions_chunked[i][j].label;
						html_str += '</label></div>';
					}
					html_str += '</div>';
				}
				html_str += '</div></div>';
				$('#' + country.toUpperCase()).prepend(html_str);
			}
		}
		else if (filter_option == 'states') {
			var states_list = filter_data['states_list'];
			var state_regions = filter_data['state_regions'];
			var sub_region_list = filter_data['sub_region_list'];
			var partner_question_id = 96;
			var temp_arr = $.map(states_list, function(value, index) {
				return [value];
			});
			var chunk = Math.ceil(temp_arr.length / 3);
			var states_chunked = [];
			for (i = 0, j = temp_arr.length; i < j; i += chunk) {
				states_chunked.push(temp_arr.slice(i, i + chunk));
			}
			html_str += '<div class="span11" id="states-section">';
			html_str += '<i class="icon-trash pull-right" id="states" partner_question_id="96"></i>';
			html_str += '<label><strong>States</strong></label>';

			for (var key in states_chunked) {
				html_str += '<div class="span4" style="margin-left: 0px;">';
				for (var i in states_chunked[key]) {
					var id = getKeyFromValue(states_list, states_chunked[key][i]);
					var checked = "";
					var state = states_chunked[key][i].substring(0, 2);
					var state_class = state_regions[state] + " " + sub_region_list[state];
					html_str += '<div class="input checkbox">';
					html_str += '<input type="hidden" name="data[Query][96][' + id + ']" id="QueryState' + id + '_">';
					html_str += '<input type="checkbox" name="data[Query][96][' + id + ']" answer_id="' + id + '" partner_question_id="' + 96 + '" ';
					html_str += 'value="1" id="QueryState' + id + '" ' + checked + '>';
					html_str += '<label for="QueryState' + id + '">' + states_chunked[key][i] + '</label>';
					html_str += '</div>';
				}
				html_str += '</div>';
			}
			$('#US').prepend(html_str);
		}
		else if (filter_option == 'counties') {
			if (country == 'us') {
				var states_list = filter_data['states_list'];
				html_str += '<div class="span11" id="counties-section">';
				html_str += '<i class="icon-trash pull-right" id="counties" partner_question_id="98"></i>';
				html_str += '<label><strong>Counties</strong></label>';
				html_str += '<div class="input select">';
				html_str += '<select name id="state_dropdown">';
				html_str += '<option>Select State:</option>';
				for (var value in states_list) {
					html_str += '<option value="' + value + '">' + states_list[value] + '</option>';
				}
				html_str += '</select></div>';
				html_str += '<div id="counties_container" style="overflow: auto;"></div>';
				html_str += '<div class="span4" id="counties_1"></div>';
				html_str += '<div class="span4" id="counties_2"></div>';
				html_str += '<div class="span4" id="counties_3"></div>';
				html_str += '</div>';
				$('#US').prepend(html_str);
			}
			else {
				var counties = filter_data['counties'];
				var partner_question_id = counties[0].partner_question_id;
				var chunk = Math.ceil(counties.length / 3);
				var counties_chunked = [];
				for (i = 0, j = counties.length; i < j; i += chunk) {
					counties_chunked.push(counties.slice(i, i + chunk));
				}

				html_str += '<div class="span11" id="counties-section">';
				html_str += '<i class="icon-trash pull-right" id="counties" partner_question_id=' + partner_question_id + '></i>';
				var label = country == 'gb' ? "Counites" : "Provinces";
				html_str += '<label><strong>' + label + '</strong></label>';
				for (var i = 0; i < counties_chunked.length; i ++) {
					html_str += '<div class="span4">';
					for (var j = 0; j < counties_chunked[i].length; j ++) {
						var name = "data[Query][" + partner_question_id + "][" + counties_chunked[i][j].answer_id + "]";
						var element_id = "QueryCounties" + counties_chunked[i][j].answer_id;
						var answer_id = counties_chunked[i][j].answer_id;
						html_str += '<div class="input checkbox">';
						html_str += '<input type="hidden" name="' + name + '" id="' + element_id + '_" value="0">';
						html_str += '<input type="checkbox" name="' + name + '" answer_id="' + answer_id + '" partner_question_id=' + partner_question_id + ' id="' + element_id + '" value="1">';
						html_str += '<label for="' + element_id + '">';
						html_str += counties_chunked[i][j].label;
						html_str += '</label></div>';
					}
					html_str += '</div>';
				}
				html_str += '</div></div>';
				$('#' + country.toUpperCase()).prepend(html_str);
			}
		}

		// Event
		$('.group-select').click(function() {
			if ($(this).prop('checked')) {
				$('.' + $(this).data('ref')).prop('checked', true);
			}
			else {
				$('.' + $(this).data('ref')).prop('checked', false);
			}
		});

		$('#state_dropdown').change(function() {
			show_counties($(this).val());
		});

		// Trash Icon Click Event
		$('.location .icon-trash').click(function() {
			var filter_option = $(this).prop('id');
			var selected_filter_option = $('#location_filter_dropdown').val();
			is_added = $.grep(is_added, function(value) {
				return value != filter_option;
			});
			var partner_question_id = $(this).attr('partner_question_id');
			var parent_id = $(this).parent('div').attr('id');
			if (parent_id == 'states-section') {
				if ($('#panelist_checked_options').find($('#checked_states')).length > 0) {
					$('#checked_states').remove();
				}
				var region_answer_ids = [];
				if ($('#US').find($('#regions-section')).length > 0) {
					$('.sub-region input[type="checkbox"]').each(function() {
						if ($(this).prop('checked')) {
							region_answer_ids = region_answer_ids.concat(JSON.parse($(this).attr('answer_ids')));
						}
					});
				}
				if (region_answer_ids.length > 0) {
					query_json[partner_question_id] = region_answer_ids;
					get_panelist_count();
				}
				else {
					if (query_json[partner_question_id] != undefined) {
						delete query_json[partner_question_id];
						get_panelist_count();
					}
				}
			}
			if (parent_id == 'dmas-section') {
				if ($('#panelist_checked_options').find($('#checked_dmas')).length > 0) {
					$('#checked_dmas').remove();
				}
				if (query_json[partner_question_id] != undefined) {
					delete query_json[partner_question_id];
					get_panelist_count();
				}
			}
			if (parent_id == 'counties-section') {
				if ($('#panelist_checked_options').find($('#checked_counties')).length > 0) {
					$('#checked_counties').remove();
				}
				if (query_json[partner_question_id] != undefined) {
					delete query_json[partner_question_id];
					get_panelist_count();
				}
			}
			if (parent_id == 'regions-section') {
				if ($('#panelist_checked_options').find($('#checked_regions')).length > 0) {
					$('#checked_regions').remove();
				}
				if (country == 'us') {
					var states_answer_ids = [];
					if ($('#US').find($('#states-section')).length > 0) {
						$('#states-section input[type="checkbox"]').each(function() {
							if ($(this).prop('checked')) {
								states_answer_ids.push($(this).attr('answer_id'));
							}
						});
					}
					if (states_answer_ids.length > 0) {
						query_json[partner_question_id] = states_answer_ids;
						get_panelist_count();
					}
					else {
						if (query_json[partner_question_id] != undefined) {
							delete query_json[partner_question_id];
							get_panelist_count();
						}
					}
				}
				else {
					if (query_json[partner_question_id] != undefined) {
						delete query_json[partner_question_id];
						get_panelist_count();
					}
				}
			}
			if (parent_id == 'zipcode_section') {
				if ($('#panelist_checked_options').find($('#checked_zips')).length > 0) {
					$('#checked_zips').remove();
				}
				if (query_json[partner_question_id] != undefined) {
					delete query_json[partner_question_id];
					get_panelist_count();
				}
			}
			$(this).parent('div').remove();
			$("#location_filter_dropdown").val($("#location_filter_dropdown option:first").val());
			if ($('#US').children('div').length == 0) {
				$('#location_section').hide();
				$('#location_area').css('margin-bottom', '0');
			}
		});

		// States Checkbox Change Event
		$('#states-section input[type="checkbox"]').change(function() {
			var labels = [];
			var ids = [];
			var partner_question_id = $(this).attr('partner_question_id');
			var region_states = [];
			var answer_id = $(this).attr('answer_id');
			$('.sub-region input[type="checkbox"]').each(function() {
				if ($(this).prop('checked')) {
					var region_answer_ids = JSON.parse($(this).attr('answer_ids'));
					region_states = region_states.concat(region_answer_ids);
				}
			});
			$('#states-section input[type="checkbox"]').each(function() {
				if ($(this).prop('checked')) {
					labels.push($(this).next().text().substr(0, 2));
					ids.push($(this).attr('id'));
				}
			});
			if (labels.length > 0) {
				$('#checked_states').remove();
				var html_str = '<div id="checked_states" class="checked_options"><strong>States: </strong>';
				for (var i = 0; i < labels.length; i ++) {
					if (i == 0) {
						html_str += '<span id="states_' + ids[i] + '">' + labels[i] + '</span>';
					}
					else {
						html_str += '<span id="states_' + ids[i] + '">, ' + labels[i] + '</span>';
					}
				}
				$('#panelist_checked_options').append(html_str);
			}
			else {
				$('#checked_states').remove();
			}
			if ($(this).prop('checked')) {
				if ($.inArray(answer_id, region_states) == -1) {
					if (query_json[partner_question_id] == undefined) {
						query_json[partner_question_id] = [];
					}
					query_json[partner_question_id].push(answer_id);
					query_json[partner_question_id] = $.unique(query_json[partner_question_id]);
					get_panelist_count();
				}
			}
			else {
				if ($.inArray(answer_id, region_states) == -1) {
					query_json[partner_question_id] = remove_value(query_json[partner_question_id], answer_id);
					if (query_json[partner_question_id].length == 0) {
						delete query_json[partner_question_id];
					}
					get_panelist_count();
				}
			}
		});

		// Dmas Checkbox Change Event
		$('#dmas-section input[type="checkbox"]').change(function() {
			var labels = [];
			var ids = [];
			query_arr = [];
			var partner_question_id = $(this).attr('partner_question_id');
			$('#dmas-section input[type="checkbox"]').each(function() {
				if ($(this).prop('checked')) {
					labels.push($(this).next().text());
					ids.push($(this).attr('id'));
					query_arr.push($(this).attr('answer_id'));
				}
			});
			if (labels.length > 0) {
				$('#checked_dmas').remove();
				var html_str = '<div id="checked_dmas" class="checked_options"><strong>Dmas: </strong>';
				for (var i = 0; i < labels.length; i++) {
					if (i == 0) {
						html_str += '<span id="dmas_' + ids[i] + '">' + labels[i] + '</span>';
					}
					else {
						html_str += '<span id="dmas_' + ids[i] + '">, ' + labels[i] + '</span>';
					}
				}
				$('#panelist_checked_options').append(html_str);
				query_json[partner_question_id] = query_arr;
			}
			else {
				$('#checked_dmas').remove();
				delete query_json[partner_question_id];
			}
			get_panelist_count();
		});

		// Regions Checkbox Change Event
		$('#regions-section input[type="checkbox"]').change(function() {
			var labels = [];
			var ids = [];
			var partner_question_id = $(this).attr('partner_question_id');
			query_arr = [];
			if (country == 'us') {
				var state_answer_ids = [];
				$('#states-section input[type="checkbox"]').each(function() {
					if ($(this).prop('checked')) {
						state_answer_ids.push($(this).attr('answer_id'));
					}
				});
				var selected_answer_ids = [];
				$('#regions-section input[type="checkbox"]').each(function() {
					if ($(this).prop('checked')) {
						if ($(this).attr('scope') == 'parent') {
							labels.push($(this).parent('label').text());
							ids.push($(this).data('ref'));
						}
						else {
							var label = $(this).parent('label').text();
							var parent = $(this).attr('parent');
							var states_precode = $(this).attr('states_precode');
							label = label + ' (' + parent + ')';
							labels.push(label);
							ids.push($(this).data('ref'));
							selected_answer_ids = selected_answer_ids.concat(JSON.parse($(this).attr('answer_ids')));
						}
					}
				});
				if (labels.length > 0) {
					$('#checked_regions').remove();
					var html_str = '<div id="checked_regions" class="checked_options"><strong>Regions: </strong>';
					for (var i = 0; i < labels.length; i++) {
						if (i == 0) {
							html_str += '<span id="regions_' + ids[i] + '">' + labels[i] + '</span>';
						}
						else {
							html_str += '<span id="regions_' + ids[i] + '">, ' + labels[i] + '</span>';
						}
					}
					$('#panelist_checked_options').append(html_str);
				}
				else {
					$('#checked_regions').remove();
				}
				var diff = state_answer_ids.filter(x => selected_answer_ids.indexOf(x) < 0);
				if (state_answer_ids.length > 0) {
					if (diff.length > 0) {
						selected_answer_ids = selected_answer_ids.concat(diff);
					}
				}
				if (selected_answer_ids.length > 0) {
					query_arr = selected_answer_ids;
					query_json[partner_question_id] = query_arr;
				}
				else {
					delete query_json[partner_question_id];
				}
				get_panelist_count();
			}
			else {
				$('#regions-section input[type="checkbox"]').each(function() {
					if ($(this).prop('checked')) {
						labels.push($(this).next().text());
						ids.push($(this).attr('id'));
						query_arr.push($(this).attr('answer_id'));
					}
				});
				if (labels.length > 0) {
					$('#checked_regions').remove();
					var html_str = '<div id="checked_regions" class="checked_options"><strong>Regions: </strong>';
					for (var i = 0; i < labels.length; i++) {
						if (i == 0) {
							html_str += '<span id="regions_' + ids[i] + '">' + labels[i] + '</span>';
						}
						else {
							html_str += '<span id="regions_' + ids[i] + '">, ' + labels[i] + '</span>';
						}
					}
					$('#panelist_checked_options').append(html_str);
					query_json[partner_question_id] = query_arr;
				}
				else {
					$('#checked_regions').remove();
					delete query_json[partner_question_id];
				}
				get_panelist_count();
			}
		});

		// Counties, Provinces Checkbox Change Event
		$('#counties-section input[type="checkbox"]').change(function() {
			if (country != 'us') {
				var label = (country == 'gb') ? "Counties" : "Provinces";
				var labels = [];
				var ids = [];
				query_arr = [];
				var partner_question_id = $(this).attr('partner_question_id');
				$('#counties-section input[type="checkbox"]').each(function() {
					if ($(this).prop('checked')) {
						labels.push($(this).next().text());
						ids.push($(this).attr('id'));
						query_arr.push($(this).attr('answer_id'));
					}
				});
				if (labels.length > 0) {
					$('#checked_counties').remove();
					var html_str = '<div id="checked_counties" class="checked_options"><strong>' + label + ': </strong>';
					for (var i = 0; i < labels.length; i++) {
						if (i == 0) {
							html_str += '<span id="counties_' + ids[i] + '">' + labels[i] + '</span>';
						}
						else {
							html_str += '<span id="counties_' + ids[i] + '">, ' + labels[i] + '</span>';
						}
					}
					$('#panelist_checked_options').append(html_str);
					query_json[partner_question_id] = query_arr;
				}
				else {
					$('#checked_counties').remove();
					delete query_json[partner_question_id];
				}
				get_panelist_count();
					}
		});
	}

	function getKeyFromValue(object, value) {
		for (key in object) {
																																																																																																																																																																																																																																																																																																																																																														if (object[key] == value) {
				return key;
			}
		}
		return "";
	}

	function show_counties(state) {
		var checked_counties = {};
		if ($('.counties_checkbox').length) {
			$('.counties_checkbox').each(function() {
				if ($(this).prop('checked')) {
					checked_counties[$(this).attr('answer_id')] = $(this).next().text();
				}
				$(this).parent('div').remove();
			});
		}
		if (!state) {
			return false;
		}
		$.ajax({
			type: 'GET',
			url: '/queries/ajax_get_counties/' + state,
			statusCode: {
				201: function(data) {
					var counties = data.counties;
					var county_keys = Object.keys(counties);
					var county_values = Object.values(counties);
					if (Object.keys(checked_counties).length > 0) {
						Object.keys(checked_counties).reverse().forEach(function(key) {
							var id = getKeyFromValue(counties, checked_counties[key]);
							if (id == '') {
								county_keys.unshift(key);
								county_values.unshift(checked_counties[key]);
							}
						});
						var new_counties = {};
						for (var i = 0; i < county_keys.length; i++) {
							new_counties[county_keys[i]] = county_values[i];
						}
						counties = new_counties;
					}
					var chunk = Math.ceil(county_values.length / 3);
					var counties_chunked = [];
					for (i = 0, j = county_values.length; i < j; i += chunk) {
						counties_chunked.push(county_values.slice(i, i + chunk));
					}
					if (!$.isEmptyObject(counties)) {
						for (var i = 0; i < counties_chunked.length; i ++) {
							var html_str = '';
							for (c in counties_chunked[i]) {
								var county = counties_chunked[i][c];
								var id = getKeyFromValue(counties, county);
								html_str += '<div class="input checkbox checkbox_' + county + '">';
								if ($.inArray(id, Object.keys(checked_counties)) != -1) {
									html_str += '<input class="counties_checkbox" answer_id="' + id + '" partner_question_id="98" name="data[Query][98][' + id + ']" value="1" id="QueryCounty' + county + '" type="checkbox" checked>';
								}
								else {
									html_str += '<input class="counties_checkbox" answer_id="' + id + '" partner_question_id="98" name="data[Query][98][' + id + ']" value="1" id="QueryCounty' + county + '" type="checkbox">';
								}
								html_str += '<label for="QueryCounty' + county + '">' + county + '</label></div>';
							}
							$('#counties_' + (i + 1)).append(html_str);
						}
					}
					// Counties Checkbox Change Event
					$('#counties-section input[type="checkbox"]').change(function() {
						var labels = [];
						var ids = [];
						var partner_question_id = $(this).attr('partner_question_id');
						query_arr = [];
						$('#counties-section input[type="checkbox"]').each(function() {
							if ($(this).prop('checked')) {
								labels.push($(this).next().text());
								ids.push($(this).attr('id'));
								query_arr.push($(this).attr('answer_id'));
							}
						});
						if (labels.length > 0) {
							$('#checked_counties').remove();
							var html_str = '<div id="checked_counties" class="checked_options"><strong>Counties: </strong>';
							for (var i = 0; i < labels.length; i++) {
								if (i == 0) {
									html_str += '<span id="counties_' + ids[i] + '">' + labels[i] + '</span>';
								}
								else {
									html_str += '<span id="counties_' + ids[i] + '">, ' + labels[i] + '</span>';
								}
							}
							$('#panelist_checked_options').append(html_str);
							query_json[partner_question_id] = query_arr;
						}
						else {
							$('#checked_counties').remove();
							delete query_json[partner_question_id];
						}
						get_panelist_count();
					});
				}
			}
		});
	}

	function toggle_debug_mode() {
		$('#toggle_debug_mode').toggleClass('show');
		$('#query_debug_textarea').slideToggle();
		if ($('#toggle_debug_mode').hasClass('show')) {
			$('#toggle_debug_mode').text('Show query debug mode');
		}
		else {
			$('#toggle_debug_mode').text('Hide query debug mode');
		}
		return false;
	}
</script>

<?php echo $this->Element('modal_query_profile'); ?>
