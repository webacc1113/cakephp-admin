<?php
$parent_panelists = $parent_qualification_info['panelists'];
$parent_questions = $parent_qualification_info['questions'];
$country = $parent_qualification_info['country'];
$default_question_texts = array(
	'US' => array('STANDARD_HHI_US_v2' => 'HHI', 'ETHNICITY' => 'Race', 'HISPANIC' => 'Hispanic'),
	'GB' => array('STANDARD_HHI_INT' => 'HHI', 'STANDARD_UK_ETHNICITY' => 'Race'),
	'CA' => array('STANDARD_HHI_INT' => 'HHI', 'STANDARD_CANADA_ETHNICITY' => 'Race')
);
$default_question_ids = array(
	'US' => array('STANDARD_HHI_US_v2' => '48740', 'ETHNICITY' => '113', 'HISPANIC' => '47'),
	'GB' => array('STANDARD_HHI_INT' => '14887', 'STANDARD_UK_ETHNICITY' => '34430'),
	'CA' => array('STANDARD_HHI_INT' => '14887', 'STANDARD_CANADA_ETHNICITY' => '32353')
);
echo $this->Html->script('/assets/javascripts/vendor/validation/jquery.validationEngine.js');
echo $this->Html->script('/assets/javascripts/vendor/validation/jquery.validationEngine-en.js');
?>
<h4>Target Quotas for "<?php echo $parent_qualification['Qualification']['name']; ?>"</h4>
<div class="row-fluid child-qualification">
	<div class="span3">
		<?php echo $this->Form->create('Query'); ?>
		<div class="box" style="margin-bottom: 0;">
			<div class="box-header">
				<span class="title">Target Quota</span>
				<div class="btn btn-default btn-small pull-right" id="reset_btn">Reset</div>
			</div>
			<div class="box-content">
				<div class="padded">
					<div class="row-fluid qualification-filters" id="qualification_filters">
						<?php foreach ($parent_panelists as $key => $panelist): ?>
							<?php if ($key == 'has_location') continue; ?>
							<div class="box filter-<?php echo $key; ?>">
								<div class="box-header <?php echo ($key == 'age' || $key == 'gender') ? 'opened' : ''; ?>">
									<span class="title"><?php echo $panelist['label']; ?></span>
									<i class="icon-caret-up pull-right"></i>
									<i class="icon-caret-down pull-right" style="display: none"></i>
								</div>
								<div class="box-content filter-<?php echo $key; ?>-content" <?php echo ($key == 'age' || $key == 'gender') ? 'style="display: block;"' : ''; ?>>
									<div class="padded">
										<div class="row-fluid">
											<?php if ($key == 'gender'): ?>
												<?php if ($panelist['answers'] == 'all') {
														echo $this->Form->input('Query.43.1', array(
															'type' => 'checkbox',
															'partner_question_id' => 43,
															'label' => 'Male'
														));
														echo $this->Form->input('Query.43.2', array(
															'type' => 'checkbox',
															'partner_question_id' => 43,
															'label' => 'Female'
														));
													}
													else if ($panelist['answers'] == '1') {
														echo 'Male';
													}
													else {
														echo 'Female';
													}
												?>
											<?php elseif ($key == 'age'): ?>
												<div class="form-group">
													<?php
														echo $this->Form->input('age_from', array(
															'label' => false,
															'value' => min($panelist['answers']),
															'class' => 'validate[required,custom[onlyNumberSp],min[' . min($panelist['answers']) . ']]',
															'style' => 'width: 55px;'
														));
														echo $this->Form->input('age_to', array(
															'label' => false,
															'value' => max($panelist['answers']),
															'class' => 'validate[required,custom[onlyNumberSp],max[' . max($panelist['answers']) . ']]',
															'style' => 'width: 55px;'
														));
													?>
												</div>
											<?php elseif ($key == 'zip_codes' || $key == 'postal_prefixes'): ?>
												<?php echo $this->Form->input('Query.' . $panelist['partner_question_id'], array(
													'type' => 'textarea',
													'label' => false,
													'partner_question_id' => $panelist['partner_question_id'],
													'rows' => '4'
												)); ?>
											<?php else: ?>
												<?php foreach ($panelist['answers'] as $id => $answer) {
													if (count($panelist['answers']) > 1 ) {
														echo $this->Form->input('Query.'.$panelist['partner_question_id'] . '.' . $id, array(
															'type' => 'checkbox',
															'label' => $answer,
															'answer_id' => $id,
															'patner_question_id' => $panelist['partner_question_id']
														));
													}
													else {
														echo $answer;
													}
												}?>
											<?php endif; ?>
										</div>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
						<?php if (count($parent_questions) > 0): ?>
							<?php foreach ($parent_questions as $real_question_key => $question):
								$question_key = str_replace(' ', '_', $real_question_key);
								if ($question_key == 'has_HHI') {
									$question_key = $default_questions['HHI']['Question']['question'];
									$question = $default_questions['HHI'];
								}
								else if ($question_key == 'has_Race') {
									$question_key = $default_questions['Race']['Question']['question'];
									$question = $default_questions['Race'];
								}
								else if ($question_key == 'has_Hispanic') {
									$question_key = $default_questions['Hispanic']['Question']['question'];
									$question = $default_questions['Hispanic'];
								}
								?>
								<div class="box filter-<?php echo $question_key; ?>">
									<div class="box-header">
										<span class="title"><?php echo $question['QuestionText']['cp_text']; ?></span>
										<i class="icon-caret-up pull-right"></i>
										<i class="icon-caret-down pull-right" style="display: none"></i>
									</div>
									<div class="box-content">
										<div class="padded">
											<div class="row-fluid">
												<?php foreach ($question['Answers'] as $id => $value) {
													if (count($question['Answers']) > 1) {
														echo $this->Form->input($question['Question']['partner_question_id'] . '.' . $id, array(
															'type' => 'checkbox',
															'partner_question_id' => $question['Question']['partner_question_id'],
															'answer_id' => $id,
															'label' => $value
														));
													}
													else {
														echo $value;
													}
												}?>
											</div>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						<?php endif; ?>
						<?php if (isset($parent_panelists['has_location'])): ?>
							<div class="box filter-location">
								<div class="box-header">
									<span class="title">Location</span>
									<i class="icon-caret-up pull-right"></i>
									<i class="icon-caret-down pull-right" style="display: none"></i>
								</div>
								<div class="box-content filter-location-content">
									<div class="padded">
										<div class="row-fluid">
											<?php
												if ($country == 'US') {
													$options = array(
														array('name' => 'States', 'value' => 'states', 'partner_question_id' => 96),
														array('name' => 'Zip Codes', 'value' => 'zip_codes', 'partner_question_id' => 45),
														array('name' => 'DMAs', 'value' => 'dmas', 'partner_question_id' => 97),
														array('name' => 'Counties', 'value' => 'counties', 'partner_question_id' => 98),
													);
												}
												else if ($country == 'GB') {
													$options = array(
														array('name' => 'Regions', 'value' => 'regions', 'partner_question_id' => 12452),
														array('name' => 'Postal Codes', 'value' => 'postal_prefixes', 'partner_question_id' => 12370),
														array('name' => 'Counties', 'value' => 'counties', 'partner_question_id' => 12453),
													);
												}
												else {
													$options = array(
														array('name' => 'Regions', 'value' => 'regions', 'partner_question_id' => 29459),
														array('name' => 'Postal Codes', 'value' => 'postal_prefixes', 'partner_question_id' => 1008),
														array('name' => 'Provinces', 'value' => 'counties', 'partner_question_id' => 1015),
													);
												}
												echo $this->Form->input(null, array(
													'name' => false,
													'label' => false,
													'type' => 'select',
													'options' => $options,
													'empty' => 'Add Location Filter',
													'id' => 'location_filter_dropdown',
													'div' => array(
														'style' => 'display: inline-block'
													),
													'style' => 'margin-bottom: 0px;'
												));
											?>
										</div>
									</div>
								</div>
							</div>
						<?php endif; ?>
					</div>
					<div class="row-fluid row-qualification" id="qualification_section">
						<?php echo $this->Form->input('Qualification.name', array(
							'label' => 'Name',
							'type' => 'text',
							'class' => 'validate[required]',
							'style' => 'width: 97%;'
						)); ?>
					</div>
				</div>
			</div>
		</div>
		<div style="margin-top: 10px;">
			<?php echo $this->Form->submit('Target Panelists', array('class' => 'btn btn-large btn-primary', 'style' => 'width: 100%;')); ?>
		</div>
		<?php echo $this->Form->end(null); ?>
	</div>
	<div class="span9">
		<div class="row-fluid" style="margin-bottom: 5px;">
			<?php echo $this->Html->link('To Dashboard',
				array(
					'controller' => 'surveys',
					'action' => 'dashboard',
					$parent_qualification['Qualification']['project_id'],
					'?' => array(
						'group_id' => 15
					)
				),
				array(
					'class' => 'btn btn-sm btn-default pull-right',
					'escape' => false
				)
			); ?>
		</div>
		<div class="box">
			<div class="box-content">
				<table class="table table-normal" id="qualification_table">
					<thead style="font-weight: bold;">
						<tr>
							<?php foreach ($targetable_fields['panelists'] as $key => $value): ?>
								<?php if ($key == 'age'): ?>
									<td><?php echo ucfirst($key); ?></td>
								<?php elseif ($key == 'postal_prefixes'): ?>
									<td>Postal Prefixes</td>
								<?php elseif ($key == 'zip_codes'): ?>
									<td>Zip Codes</td>
								<?php else: ?>
									<?php $count = count($value['answers']); ?>
									<td colspan="<?php echo $count; ?>"><?php echo ucfirst($key); ?></td>
								<?php endif; ?>
							<?php endforeach; ?>

							<?php if (isset($targetable_fields['questions'])): ?>
								<?php foreach ($targetable_fields['questions'] as $key => $value) : ?>
									<?php $count = count($value); ?>
									<?php if (array_key_exists($key, $default_question_texts[$country])): ?>
										<td colspan="<?php echo $count; ?>"><?php echo $default_question_texts[$country][$key]; ?></td>
									<?php else: ?>
										<td colspan="<?php echo $count; ?>"><?php echo $parent_questions[$key]['QuestionText']['cp_text']; ?></td>
									<?php endif; ?>
								<?php endforeach; ?>
							<?php endif; ?>
						</tr>
					</thead>
					<?php
						$total_colspan = 0;
						foreach ($targetable_fields['panelists'] as $key => $value) {
							$total_colspan += count($value['answers']);
						}
						if (isset($targetable_fields['questions'])) {
							foreach ($targetable_fields['questions'] as $key => $answers) {
								$total_colspan += count($answers);
							}
						}
					?>
					<tbody>
						<tr style="background-color: #eaeaea; height: 2px; line-height: 2px;">
							<td colspan="<?php echo $total_colspan;?>" style="padding: 0px;"></td>
						</tr>
						<tr style="background-color: #F3F4F8;">
							<td colspan="<?php echo $total_colspan;?>">
								<strong><?php echo $parent_qualification['Qualification']['name']; ?></strong><br/>
								<small>Quota: <?php
									echo $parent_qualification['Qualification']['quota'];
								?>; CPI: <?php
									echo $this->App->dollarize($parent_qualification['Qualification']['cpi']);
								?>; Award: <?php
									echo number_format($parent_qualification['Qualification']['award']);
								?></small>
							</td>
						</tr>
						<tr style="background-color: #eaeaea; height: 2px; line-height: 2px;">
							<td colspan="<?php echo $total_colspan;?>" style="padding: 0px;"></td>
						</tr>
						<tr style="background-color: #fff;">
							<?php foreach ($targetable_fields['panelists'] as $key => $value) : ?>
								<?php if ($key == 'age'): ?>
									<td style="white-space: nowrap;"><?php echo $value['answers']['min'] . ' - ' . $value['answers']['max']; ?></td>
								<?php elseif ($key == 'postal_prefixes'): ?>
									<td><?php echo min($value['answers']) . ' - ' . max($value['answers']) . " (" . count($value['answers']) . " Postals)" ?></td>
								<?php elseif ($key == 'zip_codes'): ?>
									<td><?php echo min($value['answers']) . ' - ' . max($value['answers']) . " (" . count($value['answers']) . " Zips)" ?></td>
								<?php else: ?>
									<?php foreach ($value['answers'] as $id => $answer): ?>
										<td><?php echo $answer; ?></td>
									<?php endforeach; ?>
								<?php endif; ?>
							<?php endforeach; ?>

							<?php if (isset($targetable_fields['questions'])): ?>
								<?php foreach ($targetable_fields['questions'] as $key => $answers) : ?>
									<?php foreach ($answers as $answer): ?>
										<td><?php echo $answer; ?></td>
									<?php endforeach; ?>
								<?php endforeach; ?>
							<?php endif; ?>
						</tr>

						<?php if (count($child_qualifications) > 0): ?>
							<?php foreach ($child_qualifications as $id => $child_qualification):
								$child_panelists = $child_qualifications_info[$id]['panelists'];
								$child_questions = $child_qualifications_info[$id]['questions'];
							?>
							<tr class="<?php echo "qualification_" . $id; ?>" style="background-color: #eaeaea; height: 2px; line-height: 2px;">
								<td colspan="<?php echo $total_colspan;?>" style="padding: 0px;"></td>
							</tr>
							<tr class="<?php echo "qualification_" . $id; ?>" style="background-color: #F3F4F8">
								<td colspan="<?php echo $total_colspan;?>">
									<div class="delete-cell pull-right">
										<div class="btn btn-mini btn-danger" qualification_id="<?php echo $child_qualification['Qualification']['id']; ?>">Delete</div>
									</div>
									<span class="muted"><?php
										echo $parent_qualification['Qualification']['name'];
									?></span> &#187; <?php
										echo $child_qualification['Qualification']['name'];
									?><br/>
									<small>Quota: <?php
										echo $child_qualification['Qualification']['quota'];
									?>; CPI: <?php
										echo $this->App->dollarize($child_qualification['Qualification']['cpi']);
									?>; Award: <?php
										echo number_format($child_qualification['Qualification']['award']);
									?></small>
								</td>
							</tr>
							<tr class="<?php echo "qualification_" . $id; ?>" style="background-color: #eaeaea; height: 2px; line-height: 2px;">
								<td colspan="<?php echo $total_colspan;?>" style="padding: 0px;"></td>
							</tr>
							<tr class="<?php echo "qualification_" . $id; ?>" style="background-color: #fff">
								<?php foreach ($targetable_fields['panelists'] as $key => $value) : ?>
									<?php if ($key == 'age'): ?>
										<td><?php echo min($child_panelists['age']['answers']) . " - " . max($child_panelists['age']['answers']); ?></td>
									<?php elseif ($key == 'postal_prefixes'):
										$postal_prefixes = $child_panelists['postal_prefixes']['answers'];?>
										<td><?php echo min($postal_prefixes) . ' - ' . max($postal_prefixes) . " (" . count($postal_prefixes) . " Postals)" ?></td>
									<?php elseif ($key == 'zip_codes'):
										$zip_codes = $child_panelists['zip_codes']['answers'];?>
										<td><?php echo min($zip_codes) . ' - ' . max($zip_codes) . " (" . count($zip_codes) . " Zips)" ?></td>
									<?php elseif ($key == 'gender'): ?>
										<?php if ($parent_panelists['gender']['answers'] == 'all'): ?>
											<td>
												<?php
												$checked = true;
												if ($child_panelists['gender']['answers'] == 2) {
													$checked = false;
												}
												echo $this->Form->input('', array(
													'type' => 'checkbox',
													'label' => false,
													'checked' => $checked,
													'partner_question_id' => 43,
													'answer_id' => 1
												));?>
											</td>
											<td>
												<?php
												$checked = true;
												if ($child_panelists['gender']['answers'] == 1) {
													$checked = false;
												}
												echo $this->Form->input('', array(
													'type' => 'checkbox',
													'label' => false,
													'checked' => $checked,
													'partner_question_id' => 43,
													'answer_id' => 2
												));?>
											</td>
										<?php elseif ($parent_panelists['gender']['answers'] == '1'): ?>
											<td>M</td>
										<?php else: ?>
											<td>F</td>
										<?php endif; ?>
									<?php else: ?>
										<?php foreach ($value['answers'] as $id => $answer): ?>
											<td>
												<?php
												if (isset($child_panelists[$key])) {
													$checked = in_array($answer, $child_panelists[$key]['answers']) ? true : false;
												}
												else {
													$checked = true;
												}
												echo $this->Form->input('', array(
													'type' => 'checkbox',
													'label' => false,
													'checked' => $checked,
													'partner_question_id' => $value['partner_question_id'],
													'answer_id' => $id
												));?>
											</td>
										<?php endforeach; ?>
									<?php endif; ?>
								<?php endforeach; ?>
								<?php if (isset($targetable_fields['questions'])): ?>
									<?php foreach ($targetable_fields['questions'] as $key => $answers) : ?>
										<?php foreach ($answers as $answer_id => $answer): ?>
											<td>
												<?php
												if (isset($child_questions[$key])) {
													$checked = in_array($answer, $child_questions[$key]['Answers']) ? true : false;
												}
												else {
													$checked = true;
												}
												if (array_key_exists($key, $default_question_ids[$country])) {
													$partner_question_id = $default_question_ids[$country][$key];
												}
												else {
													$partner_question_id = $parent_questions[$key]['Question']['partner_question_id'];
												}
												echo $this->Form->input('', array(
													'type' => 'checkbox',
													'label' => false,
													'checked' => $checked,
													'partner_question_id' => $partner_question_id,
													'answer_id' => $answer_id
												));?>
											</td>
										<?php endforeach; ?>
									<?php endforeach; ?>
								<?php endif; ?>
							</tr>
							<?php endforeach; ?>
						<?php else: ?>
							<tr style="background-color: #eaeaea; height: 2px; line-height: 2px;">
								<td colspan="<?php echo $total_colspan;?>" style="padding: 0px;"></td>
							</tr>
							<td colspan="<?php echo $total_colspan;?>">
								<div style="font-size: 16px; text-align: center; margin: 40px 0;">
									You can create targeted quotas based on your original qualification. Start by selecting a subset of qualifications from the left.
								</div>
							</td>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>
<script type="text/javascript">
	var parent_qualification_info = <?php echo json_encode($parent_qualification_info); ?>;
	var child_qualifications_json = <?php echo json_encode($child_qualifications_json); ?>;
	var parent_qualification_json = <?php echo json_encode($parent_qualification_json); ?>;
	var ajax_request;
	var country = "<?php echo $country; ?>";
	$(document).ready(function () {
		$('#QueryChildQualificationForm').validationEngine();
		var ageFrom = Math.min.apply(null, parent_qualification_info['panelists']['age']);
		var ageTo = Math.max.apply(null, parent_qualification_info['panelists']['age']);

		$('#reset_btn').click(function() {
			$('#qualification_filters input[type="checkbox"]').prop('checked', false);
			if (parent_qualification_info['panelists']['age'] != undefined) {
				var age = parent_qualification_info['panelists']['age'];
				$('#QueryAgeFrom').val(age[0]);
				$('#QueryAgeTo').val(age[age.length - 1]);
			}
			if (parent_qualification_info['panelists']['zip_codes'] != undefined) {
				$('#qualification_filters textarea').val("");
			}
			if (parent_qualification_info['panelists']['postal_prefixes'] != undefined) {
				$('#qualification_filters textarea').val('');
			}
		});
		filter_slide_toggle();
		$('#qualification_table input[type="checkbox"]').change(function() {
			var qualification_id = $(this).parent().closest('tr').attr('class').split('_')[1];
			var partner_question_id = $(this).attr('partner_question_id');
			var answer_id = $(this).attr('answer_id');
			var qualification_json = {};
			qualification_json['country'] = parent_qualification_json['qualifications']['country'];
			var partner_question_ids = [];
			$(this).parent().closest('tr').find('input[type="checkbox"]').each(function() {
				if ($(this).prop('checked')) {
					partner_question_ids.push($(this).attr('partner_question_id'));
				}
			});
			partner_question_ids = $.unique(partner_question_ids);
			for (var i = 0; i < partner_question_ids.length; i ++) {
				qualification_json[partner_question_ids[i]] = [];
			}
			$(this).parent().closest('tr').find('input[type="checkbox"]').each(function() {
				if ($(this).prop('checked')) {
					qualification_json[$(this).attr('partner_question_id')].push($(this).attr('answer_id'));
				}
			});
			var child_qualification_json = {};
			child_qualification_json['partner'] = child_qualifications_json[qualification_id]['partner']
			qualification_json[42] = child_qualifications_json[qualification_id]['qualifications'][42];
			child_qualification_json['qualifications'] = qualification_json;
			$(this).prop('disabled', true);
			save_change(child_qualification_json, qualification_id);
		});
		$('.delete-cell div').click(function() {
			var qualification_id = $(this).attr('qualification_id');
			$.ajax({
				type: 'POST',
				url: '/qualifications/ajax_delete_qualification/',
				data: {qualification_id: qualification_id},
				statusCode: {
					201: function(data) {
						$('.qualification_' + data).remove();
						if ($('#qualification_table').find('.delete-cell').length == 0) {
							var html_str = '<tr style="background-color: #eaeaea; height: 2px; line-height: 2px;">';
							html_str += '<td colspan="<?php echo $total_colspan;?>" style="padding: 0px;"></td></tr>';
							html_str += '<td colspan="<?php echo $total_colspan;?>"><div style="font-size: 16px; text-align: center; margin: 40px 0;">';
							html_str += 'You can create targeted quotas based on your original qualification. Start by selecting a subset of qualifications from the left.';
							html_str += '</div></td>';
							$('#qualification_table').append(html_str);
						}
					}
				}
			});
		});
		$('#location_filter_dropdown').change(function() {
			var filter_option = $(this).val();
			var partner_question_id = $('option:selected', this).attr('partner_question_id');
			var html_str = '';
			if (filter_option == 'zip_codes') {
				html_str += '<div class="box filter-zipcodes">';
				html_str += '<div class="box-header"><span class="title">Zip Codes</span>';
				html_str += '<i class="icon-caret-up pull-right"></i><i class="icon-caret-down pull-right" style="display: none"></i>';
				html_str += '</div><div class="box-content filter-zipcodes-content"><div class="padded"><div class="row-fluid">';
				html_str += '<div class="input textarea">';
				html_str += '<textarea name="data[Query][45]" partner_question_id="45" rows="4" cols="30" id="Query45"></textarea></div>';
				html_str += '</div></div></div></div>';
			}
			else if (filter_option == 'postal_prefixes') {
				html_str += '<div class="box filter-postalprefixes">';
				html_str += '<div class="box-header"><span class="title">Postal Prefixes</span>';
				html_str += '<i class="icon-caret-up pull-right"></i><i class="icon-caret-down pull-right" style="display: none"></i>';
				html_str += '</div><div class="box-content filter-postalprefixes-content"><div class="padded"><div class="row-fluid">';
				html_str += '<div class="input textarea">';
				html_str += '<textarea name="data[Query][' + partner_question_id + ']" partner_question_id=' + partner_question_id;
				html_str += ' rows="4" cols="30" id="Query' + partner_question_id + '"></textarea></div>';
				html_str += '</div></div></div></div>';
			}
			else {
				call_ajax(filter_option);
			}
			$('.filter-age').after(html_str);
			filter_slide_toggle();
			$(this).remove();
		});
	});

	function get_index(array, element) {
		element = element * 1;
		for (var i = 0; i < array.length; i ++) {
			var changed_value = array[i] * 1;
			if (changed_value == element) {
				return i;
			}
		}
		return -1;
	}

	function call_ajax(filter_option) {
		var url;
		if (filter_option == 'states') {
			url = '/qualifications/ajax_get_states/';
		}
		else if (filter_option == 'regions') {
			url = '/qualifications/ajax_get_regions/?country=' + country.toLowerCase();
		}
		else if (filter_option == 'dmas') {
			url = '/qualifications/ajax_get_dmas/';
		}
		else {
			url = '/qualifications/ajax_get_counties/?country=' + country.toLowerCase();
		}
		$.post(url, function(data) {
			call_back(data, filter_option);
		});
	}

	function call_back(filter_data, filter_option) {
		var html_str = "";
		if (filter_option == 'dmas') {
			var dmas = filter_data.dmas;
			html_str += '<div class="box filter-dmas">';
			html_str += '<div class="box-header opened"><span class="title">DMAs</span>';
			html_str += '<i class="icon-caret-up pull-right"></i><i class="icon-caret-down pull-right" style="display: none"></i>';
			html_str += '</div><div class="box-content filter-dmas-content" style="display: block;"><div class="padded"><div class="row-fluid">';
			for (var i = 0; i < dmas.length; i ++) {
				var name = "data[Query][97][" + dmas[i]['LucidZip'].dma + "]";
				var element_id = "QueryDmaCode" + dmas[i]['LucidZip'].dma;
				var dma_code = dmas[i]['LucidZip'].dma;
				html_str += '<div class="input checkbox">';
				html_str += '<input type="hidden" name="' + name + '" id="' + element_id + '_" value="0">';
				html_str += '<input type="checkbox" name="' + name + '" answer_id="' + dma_code + '" partner_question_id="97" id="' + element_id + '" value="1">';
				html_str += '<label for="' + element_id + '">';
				html_str += dmas[i]['LucidZip'].dma_name;
				html_str += '</label></div>';
			}
			html_str += '</div></div></div>';
		}
		else if (filter_option == 'regions') {
			var regions = filter_data['regions'];
			var partner_question_id = regions[0].partner_question_id;
			html_str += '<div class="box filter-regions">';
			html_str += '<div class="box-header opened"><span class="title">Regions</span>';
			html_str += '<i class="icon-caret-up pull-right"></i><i class="icon-caret-down pull-right" style="display: none"></i>';
			html_str += '</div><div class="box-content filter-regions-content" style="display: block;"><div class="padded"><div class="row-fluid">';
			for (var i = 0; i < regions.length; i ++) {
				var name = "data[Query][" + partner_question_id + "][" + regions[i].answer_id + "]";
				var element_id = "QueryRegions" + regions[i].answer_id;
				var answer_id = regions[i].answer_id;
				html_str += '<div class="input checkbox">';
				html_str += '<input type="hidden" name="' + name + '" id="' + element_id + '_" value="0">';
				html_str += '<input type="checkbox" name="' + name + '" answer_id="' + answer_id + '" partner_question_id=' + partner_question_id + ' id="' + element_id + '" value="1">';
				html_str += '<label for="' + element_id + '">';
				html_str += regions[i].label;
				html_str += '</label></div>';
			}
			html_str += '</div></div></div>';
		}
		else if (filter_option == 'counties') {
			if (country == 'US') {
				var states_list = filter_data['states_list'];
				html_str += '<div class="box filter-counties">';
				html_str += '<div class="box-header opened"><span class="title">Counties</span>';
				html_str += '<i class="icon-caret-up pull-right"></i><i class="icon-caret-down pull-right" style="display: none"></i>';
				html_str += '</div><div class="box-content filter-counties-content" style="display: block;"><div class="padded"><div class="row-fluid">';
				html_str += '<div class="input select" id="states_list">';
				html_str += '<select name id="state_dropdown">';
				html_str += '<option>Select State:</option>';
				for (var value in states_list) {
					html_str += '<option value="' + value + '">' + states_list[value] + '</option>';
				}
				html_str += '</select></div>';
				html_str += '<div id="counties_area"></div>';
				html_str += '</div></div></div>';
			}
			else {
				var counties = filter_data['counties'];
				var partner_question_id = counties[0].partner_question_id;
				var label = country == 'GB' ? "Counites" : "Provinces";
				html_str += '<div class="box filter-counties">';
				html_str += '<div class="box-header opened"><span class="title">' + label + '</span>';
				html_str += '<i class="icon-caret-up pull-right"></i><i class="icon-caret-down pull-right" style="display: none"></i>';
				html_str += '</div><div class="box-content filter-counties-content" style="display: block;"><div class="padded"><div class="row-fluid">';
				for (var i = 0; i < counties.length; i ++) {
					var name = "data[Query][" + partner_question_id + "][" + counties[i].answer_id + "]";
					var element_id = "QueryCounties" + counties[i].answer_id;
					var answer_id = counties[i].answer_id;
					html_str += '<div class="input checkbox">';
					html_str += '<input type="hidden" name="' + name + '" id="' + element_id + '_" value="0">';
					html_str += '<input type="checkbox" name="' + name + '" answer_id="' + answer_id + '" partner_question_id=' + partner_question_id + ' id="' + element_id + '" value="1">';
					html_str += '<label for="' + element_id + '">';
					html_str += counties[i].label;
					html_str += '</label></div>';
				}
				html_str += '</div></div></div>';
			}
		}
		else if (filter_option == 'states') {
			var states_list = filter_data['states_list'];
			var state_regions = filter_data['state_regions'];
			var sub_region_list = filter_data['sub_region_list'];
			var partner_question_id = 96;
			html_str += '<div class="box filter-states">';
			html_str += '<div class="box-header opened"><span class="title">States</span>';
			html_str += '<i class="icon-caret-up pull-right"></i><i class="icon-caret-down pull-right" style="display: none"></i>';
			html_str += '</div><div class="box-content filter-states-content" style="display: block;"><div class="padded"><div class="row-fluid">';
			for (c in states_list) {
				html_str += '<div class="input checkbox"><input type="hidden" name="data[Query][96][' + c + ']" id="Query96' + c + '_" value="0">';
				html_str += '<input type="checkbox" name="data[Query][96][' + c + ']" answer_id="' + c + '" patner_question_id="96" value="1" id="Query96' + c + '">';
				html_str += '<label for="Query96' + c + '">' + states_list[c].substr(0, 2) + '</label></div>';
			}
			html_str += '</div></div></div>';
		}
		$('.filter-location').remove();
		$('#qualification_filters').append(html_str);
		filter_slide_toggle();

		// Event
		$('#state_dropdown').change(function() {
			show_counties($(this).val());
		});
	}

	function show_counties(state) {
		var checked_counties = {};
		$('#counties_area input[type="checkbox"]').each(function() {
			if ($(this).prop('checked')) {
				checked_counties[$(this).attr('answer_id')] = $(this).next().text();
			}
		});
		$.ajax({
			type: 'GET',
			url: '/queries/ajax_get_counties/' + state,
			statusCode: {
				201: function(data) {
					var counties = data.counties;
					var partner_question_id = 98;
					var html_str = '';
					for (var answer_id in checked_counties) {
						var name = "data[Query][" + partner_question_id + "][" + answer_id + "]";
						var element_id = "QueryCounties" + answer_id;
						html_str += '<div class="input checkbox">';
						html_str += '<input type="hidden" name="' + name + '" id="' + element_id + '_" value="0">';
						html_str += '<input type="checkbox" name="' + name + '" answer_id="' + answer_id + '" partner_question_id=' + partner_question_id + ' id="' + element_id + '" value="1" checked>';
						html_str += '<label for="' + element_id + '">';
						html_str += checked_counties[answer_id];
						html_str += '</label></div>';
					}
					for (var answer_id in counties) {
						var name = "data[Query][" + partner_question_id + "][" + answer_id + "]";
						var element_id = "QueryCounties" + answer_id;
						html_str += '<div class="input checkbox">';
						html_str += '<input type="hidden" name="' + name + '" id="' + element_id + '_" value="0">';
						html_str += '<input type="checkbox" name="' + name + '" answer_id="' + answer_id + '" partner_question_id=' + partner_question_id + ' id="' + element_id + '" value="1">';
						html_str += '<label for="' + element_id + '">';
						html_str += counties[answer_id];
						html_str += '</label></div>';
					}
					$('#counties_area').html('');
					$('#counties_area').append(html_str);
				}
			}
		});
	}

	var last_clicked = 0;
	function save_change(child_qualification_json, qualification_id) {
		last_clicked = (new Date).getTime();
		setTimeout(function() {
			ajax_edit_child_qualifications(child_qualification_json, qualification_id);
		}, 500);
	}

	function ajax_edit_child_qualifications(child_qualification_json, qualification_id) {
		if ((new Date).getTime() - last_clicked < 500) {
			return;
		}
		if (ajax_request && ajax_request.readyState != 4) {
			ajax_request.abort();
		}
		post_data = {
			qualification_id: qualification_id,
			child_qualification_json: child_qualification_json
		}
		ajax_request = $.ajax({
			type: 'POST',
			url: '/qualifications/ajax_edit_qualifications/',
			data: post_data,
			statusCode: {
				201: function(data) {
					$('#qualification_table input[type="checkbox"]').prop('disabled', false);
				}
			}
		});
	}

	function filter_slide_toggle() {
		$('#qualification_filters .box .box-header').unbind().bind('click', function() {
			$(this).next('.box-content').slideToggle();
			$(this).find('i').toggle();
			$(this).toggleClass('opened');
		});
	}
</script>