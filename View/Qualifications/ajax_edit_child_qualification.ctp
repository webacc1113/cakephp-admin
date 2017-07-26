<?php
	$parent_panelists = $parent_qualification_info['panelists'];
	$parent_questions = $parent_qualification_info['questions'];
	$child_panelists = $child_qualification_info['panelists'];
	$child_questions = $child_qualification_info['questions'];
?>
<div class="padded">
	<?php echo $this->Form->input('Query.child_qualification_id', array(
		'type' => 'hidden',
		'value' => $child_qualification['Qualification']['id']
	));
	?>
	<div class="row-fluid" id="filter-gender">
		<div class="filter-labels">
			<label>Gender</label>
		</div>
		<div class="button-radio-list filter-contents">
			<?php
			if ($parent_panelists['gender'] == 'all') {
				$options = array();
				$options[''] = 'All';
				$options[1] = 'Male';
				$options[2] = 'Female';
				echo $this->Form->radio('Query.43',
					$options,
					array(
						'class' => 'input-radio',
						'legend' => false,
						'value' => 'All'
					)
				);
			}
			else {
				if ($parent_panelists['gender'] == '1') {
					echo 'Male';
				}
				else {
					echo 'Female';
				}
			}
			?>
		</div>
	</div>
	<div class="row-fluid" id="filter-age">
		<div class="filter-labels">
			<label>Age</label>
		</div>
		<div class="filter-contents">
			<div class="form-group">
				<?php echo $this->Form->input('Query.age_from', array(
					'label' => false,
					'value' => $child_panelists['age'][0],
					'style' => 'width: 55px; margin-bottom: 0;'
				)); ?>
				<?php echo $this->Form->input('Query.age_to', array(
					'label' => false,
					'value' => $child_panelists['age'][count($child_panelists['age']) - 1],
					'style' => 'width: 55px; margin-bottom: 0;'
				)); ?>
			</div>
		</div>
	</div>
	<?php if (isset($parent_panelists['states'])): ?>
	<div class="row-fluid filters" id="filter-states">
		<div class="filter-labels">
			<label>States</label>
		</div>
		<div class="filter-contents span11">
			<?php
			if (count($parent_panelists['states']) <= 5 ) {
				foreach ($parent_panelists['states'] as $id => $state) {
					if (in_array($state, $child_panelists['states'])) {
						$checked = true;
					}
					else {
						$checked = false;
					}
					echo $this->Form->input('Query.96.' . $id, array(
						'id' => 'edit_states_' . $id,
						'type' => 'checkbox',
						'label' => $state,
						'answer_id' => $id,
						'checked' => $checked,
						'patner_question_id' => 96
					));
				}
			}
			else {
				$states_chunked = array_chunk($parent_panelists['states'], ceil(count($parent_panelists['states']) / 3), true);
				foreach ($states_chunked as $states) {?>
			<div class="span4" style="margin-left: 0">
				<?php foreach ($states as $id => $state) {
					if (in_array($state, $child_panelists['states'])) {
						$checked = true;
					}
					else {
						$checked = false;
					}
					echo $this->Form->input('Query.96.' . $id, array(
						'type' => 'checkbox',
						'label' => $state,
						'answer_id' => $id,
						'checked' => $checked,
						'patner_question_id' => 96
					));
				}?>
			</div>
			<?php
				}
			}
			?>
		</div>
	</div>
	<?php endif; ?>
	<?php if (isset($parent_panelists['zip_codes'])): ?>
		<div class="row-fluid filters" id="filter-zipcodes">
			<div class="filter-labels">
				<label>Zip Codes</label>
			</div>
			<div class="filter-contents span11">
				<?php
				$value = implode("\n", $child_panelists['zip_codes']);
				echo $this->Form->input('Query.45', array(
					'type' => 'textarea',
					'label' => false,
					'partner_question_id' => 45,
					'rows' => '4',
					'value' => $value
				));
				?>
			</div>
		</div>
	<?php endif; ?>
	<?php if (isset($parent_panelists['dmas'])): ?>
		<div class="row-fluid filters" id="filter-dmas">
			<div class="filter-labels">
				<label>DMAs</label>
			</div>
			<div class="filter-contents span11">
				<?php
				if (count($parent_panelists['dmas']) <= 5 ) {
					foreach ($parent_panelists['dmas'] as $id => $dma) {
						if (in_array($dma, $child_panelists['dmas'])) {
							$checked = true;
						}
						else {
							$checked = false;
						}
						echo $this->Form->input('Query.97.' . $id, array(
							'type' => 'checkbox',
							'label' => $dma,
							'answer_id' => $id,
							'checked' => $checked,
							'patner_question_id' => 97
						));
					}
				}
				else {
					$dmas_chunked = array_chunk($parent_panelists['dmas'], ceil(count($parent_panelists['dmas']) / 3), true);
					foreach ($dmas_chunked as $dmas) {?>
						<div class="span4" style="margin-left: 0">
							<?php foreach ($dmas as $id => $dma) {
								if (in_array($dma, $child_panelists['dmas'])) {
									$checked = true;
								}
								else {
									$checked = false;
								}
								echo $this->Form->input('Query.97.' . $id, array(
									'type' => 'checkbox',
									'label' => $dma,
									'answer_id' => $id,
									'checked' => $checked,
									'patner_question_id' => 97
								));
							}?>
						</div>
				<?php
					}
				}
				?>
			</div>
		</div>
	<?php endif; ?>
	<?php if (isset($parent_panelists['regions'])): ?>
		<div class="row-fluid filters" id="filter-regions">
			<div class="filter-labels">
				<label>Regions</label>
			</div>
			<div class="filter-contents span11">
				<?php
				if ($country == 'GB') {
					$partner_question_id = 12453;
				}
				else {
					$partner_question_id = 29459;
				}
				if (count($parent_panelists['regions']) <= 5) {
					foreach ($parent_panelists['regions'] as $id => $region) {
						if (in_array($region, $child_panelists['regions'])) {
							$checked = true;
						}
						else {
							$checked = false;
						}
						echo $this->Form->input('Query.' . $partner_question_id . '.' . $id, array(
							'type' => 'checkbox',
							'label' => $region,
							'answer_id' => $id,
							'checked' => $checked,
							'patner_question_id' => $partner_question_id
						));
					}
				}
				else {
					$regions_chunked = array_chunk($parent_panelists['regions'], ceil(count($parent_panelists['regions']) / 3), true);
					foreach ($regions_chunked as $regions) {?>
						<div class="span4" style="margin-left: 0">
							<?php foreach ($regions as $id => $region) {
								if (in_array($region, $child_panelists['regions'])) {
									$checked = true;
								}
								else {
									$checked = false;
								}
								echo $this->Form->input('Query.' . $partner_question_id . '.' . $id, array(
									'type' => 'checkbox',
									'label' => $region,
									'answer_id' => $id,
									'checked' => $checked,
									'patner_question_id' => $partner_question_id
								));
							}?>
						</div>
						<?php
					}
				}
				?>
			</div>
		</div>
	<?php endif; ?>
	<?php if (isset($parent_panelists['postal_prefixes'])): ?>
		<div class="row-fluid filters" id="filter-postalprefixes">
			<div class="filter-labels">
				<label>Postal Prefixes</label>
			</div>
			<div class="filter-contents span11">
				<?php
				if ($country == 'GB') {
					$partner_question_id = 12370;
				}
				else if ($country == 'CA') {
					$partner_question_id = 1008;
				}
				$value = implode("\n", $child_panelists['postal_prefixes']);
				echo $this->Form->input('Query.' . $partner_question_id, array(
					'type' => 'textarea',
					'label' => false,
					'partner_question_id' => $partner_question_id,
					'rows' => '4',
					'value' => $value
				));
				?>
			</div>
		</div>
	<?php endif; ?>
	<?php if (isset($parent_panelists['provinces'])): ?>
		<div class="row-fluid filters" id="filter-provinces">
			<div class="filter-labels">
				<label>Provinces</label>
			</div>
			<div class="filter-contents span11">
				<?php
				$partner_question_id = 1015;
				if (count($parent_panelists['provinces']) <= 5) {
					foreach ($parent_panelists['provinces'] as $id => $province) {
						if (in_array($province, $child_panelists['provinces'])) {
							$checked = true;
						}
						else {
							$checked = false;
						}
						echo $this->Form->input('Query.' . $partner_question_id . '.' . $id, array(
							'type' => 'checkbox',
							'label' => $province,
							'answer_id' => $id,
							'checked' => $checked,
							'patner_question_id' => $partner_question_id
						));
					}
				}
				else {
					$provinces_chunked = array_chunk($parent_panelists['provinces'], ceil(count($parent_panelists['provinces']) / 3), true);
					foreach ($provinces_chunked as $provinces) {?>
						<div class="span4" style="margin-left: 0">
							<?php foreach ($provinces as $id => $province) {
								if (in_array($province, $child_panelists['provinces'])) {
									$checked = true;
								}
								else {
									$checked = false;
								}
								echo $this->Form->input('Query.' . $partner_question_id . '.' . $id, array(
									'type' => 'checkbox',
									'label' => $province,
									'answer_id' => $id,
									'checked' => $checked,
									'patner_question_id' => $partner_question_id
								));
							}?>
						</div>
						<?php
					}
				}
				?>
			</div>
		</div>
	<?php endif; ?>
	<?php if (isset($parent_panelists['counties'])): ?>
		<div class="row-fluid filters" id="filter-counties">
			<div class="filter-labels">
				<label>Counties</label>
			</div>
			<div class="filter-contents span11">
				<?php
				if ($country == 'US') {
					$partner_question_id = 98;
				}
				else if ($country == 'GB') {
					$partner_question_id = 12452;
				}
				if (count($parent_panelists['counties']) <= 5) {
					foreach ($parent_panelists['counties'] as $id => $county) {
						if (in_array($county, $child_panelists['counties'])) {
							$checked = true;
						} else {
							$checked = false;
						}
						echo $this->Form->input('Query.' . $partner_question_id . '.' . $id, array(
							'type' => 'checkbox',
							'label' => $county,
							'answer_id' => $id,
							'checked' => $checked,
							'patner_question_id' => $partner_question_id
						));
					}
				}
				else {
					$counties_chunked = array_chunk($parent_panelists['counties'], ceil(count($parent_panelists['counties']) / 3), true);
					foreach ($counties_chunked as $counties) {?>
						<div class="span4" style="margin-left: 0">
							<?php foreach ($counties as $id => $county) {
								if (in_array($county, $child_panelists['counties'])) {
									$checked = true;
								}
								else {
									$checked = false;
								}
								echo $this->Form->input('Query.' . $partner_question_id . '.' . $id, array(
									'type' => 'checkbox',
									'label' => $county,
									'answer_id' => $id,
									'checked' => $checked,
									'patner_question_id' => $partner_question_id
								));
							}?>
						</div>
						<?php
					}
				}
				?>
			</div>
		</div>
	<?php endif; ?>
	<?php if (count($parent_questions) > 0): ?>
		<?php foreach ($parent_questions as $real_question_key => $question):
			$question_key = str_replace(' ', '_', $real_question_key);?>
			<div class="row-fluid filters" id="<?php echo 'filter-' . $question_key; ?>">
				<div class="filter-labels">
					<label><?php echo $question['QuestionText']['cp_text']; ?></label>
				</div>
				<div class="filter-contents span11">
					<?php
					foreach ($question['Answers'] as $id => $value) {
						if (in_array($value, $child_questions[$real_question_key]['Answers'])) {
							$checked = true;
						}
						else {
							$checked = false;
						}
						echo $this->Form->input($question['Question']['partner_question_id'] . '.' . $id, array(
							'type' => 'checkbox',
							'data-filter' => $question_key,
							'partner_question_id' => $question['Question']['partner_question_id'],
							'answer_id' => $id,
							'high_usage' => '1',
							'checked' => $checked,
							'label' => $value
						));
					}
					?>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
	<div class="row-fluid row-qualification" id="qualification_section" style="padding: 0;">
		<?php echo $this->Form->input('Qualification.id', array(
			'label' => 'Name',
			'type' => 'text',
			'id' => 'qualification_id',
			'value' => $child_qualification['Qualification']['id'],
			'type' => 'hidden',
			'required' => true
		)); ?>
		<?php echo $this->Form->input('Qualification.name', array(
			'label' => 'Name',
			'type' => 'text',
			'id' => 'qualification_name',
			'value' => $child_qualification['Qualification']['name'],
			'required' => true
		)); ?>
		<div class="span4">
			<?php echo $this->Form->input('Qualification.quota', array(
				'label' => 'Quota',
				'value' => $child_qualification['Qualification']['quota'],
				'id' => 'qualification_quota',
				'type' => 'text',
				'style' => 'margin-bottom: 0; margin-right: 5px;'
			)); ?>
		</div>
		<div class="span4">
			<?php
			echo $this->Form->input('Qualification.cpi', array(
				'label' => 'CPI',
				'value' => $child_qualification['Qualification']['cpi'],
				'between' => '<div class="input-prepend"><span class="add-on" href="#"><i class="icon-none">$</i></span>',
				'after' => '</div>',
				'type' => 'text',
			)); ?>
		</div>
		<div class="span4">
			<?php echo $this->Form->input('Qualification.award', array(
				'label' => 'Award',
				'value' => $child_qualification['Qualification']['award'],
				'id' => 'qualification_award',
				'type' => 'text',
			)); ?>
		</div>
	</div>
</div>
<script language="text/javascript">
	var parent_panelists = <?php echo json_encode($parent_panelists); ?>;
	var child_panelists = <?php echo json_encode($child_panelists); ?>;
	$(document).ready(function() {
		if (child_panelists['gender'] == 'all') {
			var index = 0;
		}
		else {
			var index = child_panelists['gender'];
		}
		$('.button-radio-list input[type="radio"]').eq(index).attr('checked', true);
		$('.button-radio-list label').eq(index).addClass('btn btn-primary');
		$('.button-radio-list label').eq(index).addClass('selected');
		$('.button-radio-list label').eq(0).css('border-right', 'none');
		$('.button-radio-list label').eq(0).css('border-top-left-radius', '3px');
		$('.button-radio-list label').eq(0).css('border-bottom-left-radius', '3px');
		$('.button-radio-list label').click(function() {
			$('.button-radio-list label').removeClass('selected');
			$('.button-radio-list label').removeClass('btn btn-primary');
			$(this).addClass('selected');
			$(this).addClass('btn btn-primary');
			$(this).prev().attr('checked', true);
		});
		$('.input label').click(function() {
			$(this).prev('input').trigger('click');
		});
	});
</script>