<h4><?php echo empty($survey_id) ? 'Run Feasibility Query': 'Create Query for '.$this->App->project_name($project); ?></h4>
<div class="row-fluid query">
	<div class="span8"> 
		<?php echo $this->Form->create('Query', array('type' => 'file', 'onsubmit' => 'return MintVine.RunQuery(this)')); ?>
		<div class="box">
			<div class="box-header">
				<span class="title">User Filters</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<div class="row-fluid row-query">
						<div class="span3">
							<?php echo $this->Form->input('keyword', array(
								'label' => 'Search Keyword'
							)); ?>
						</div>
						
						<div class="span3">
							<?php echo $this->Form->input('question.'.$questions['gender']['Question']['partner_question_id'], array(
								'label' => 'Gender', 
								'type' => 'select', 
								'options' => $questions['gender']['Answers'],
								'empty' => 'Select:',
								'style' => 'width: auto'
							)); ?>
						</div>
						
						<div class="span3">							
							<div class="form-group">
								<?php echo $this->Form->input('age_from', array(
									'label' => 'Age From',
									'style' => 'width: 55px;'
								)); ?>
								<?php echo $this->Form->input('age_to', array(
									'label' => 'Age To',
									'style' => 'width: 55px;'
								)); ?>
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
					<div class="row-fluid row-query">
						<div class="span4">
							<?php echo $this->Form->input('user_id', array(
								'label' => 'Target User IDs',
								'type' => 'textarea',
								'style' => 'height: 52px'
							)); ?>
						</div>		
						<div class="span4">
							<?php echo $this->Form->input('exclude_user_id', array(
								'label' => '<span class="text-error">Exclude</span> User IDs',
								'type' => 'textarea',
								'style' => 'height: 52px'
							)); ?>
						</div>
						<div class="span4">
							<?php echo $this->Form->input('existing_project_id', array(
								'label' => '<span class="text-error">Exclude</span> Completes from Project',
								'type' => 'text',
								'after' => '<br/><small class="text-muted">Separate multiple projects with comma</small>'
							)); ?>
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
					
					<div class="row-fluid row-query">
						<div class="span3">					
							<label class="title">Household Income</label>
							<div class="scrollbox">
							<?php foreach ($questions['hhi']['Answers'] as $id => $hhi): ?>
								<?php echo $this->Form->input($questions['hhi']['Question']['partner_question_id'].'.'.$id, array(
									'type' => 'checkbox', 
									'label' => $hhi
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
						<div class="span3">
							<label class="title">Education Level</label>
							<div class="scrollbox">
							<?php foreach ($questions['education']['Answers'] as $id => $value): ?>
								<?php echo $this->Form->input($questions['hhi']['Question']['partner_question_id'].'.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
						
						<div class="span3">
							<label class="title">Marital Status</label>
							<div class="scrollbox">
							<?php foreach ($questions['relationship']['Answers'] as $id => $value): ?>
								<?php echo $this->Form->input($questions['hhi']['Question']['partner_question_id'].'.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
						
						<div class="span3">
							<label class="title">Ethnicity</label>
							<div class="scrollbox">
							<?php foreach ($questions['ethnicity']['Answers'] as $id => $value): ?>
								<?php echo $this->Form->input($questions['hhi']['Question']['partner_question_id'].'.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
					</div>
					
					<div class="row-fluid row-query">						
						<div class="span3">
							<label class="title">Employment Status</label>
							<div class="scrollbox">
							<?php foreach ($questions['employment']['Answers'] as $id => $value): ?>
								<?php echo $this->Form->input($questions['hhi']['Question']['partner_question_id'].'.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
						<div class="span3">
							<label class="title">Job Industry</label>
							<div class="scrollbox">
							<?php foreach ($questions['industry']['Answers'] as $id => $value): ?>
								<?php echo $this->Form->input($questions['hhi']['Question']['partner_question_id'].'.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
						<div class="span3">
							<label class="title">Job Department</label>
							<div class="scrollbox">
							<?php foreach ($questions['department']['Answers'] as $id => $value): ?>
								<?php echo $this->Form->input($questions['hhi']['Question']['partner_question_id'].'.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
						<div class="span3">
							<label class="title">Job Title</label>
							<div class="scrollbox">
							<?php foreach ($questions['job']['Answers'] as $id => $value): ?>
								<?php echo $this->Form->input($questions['hhi']['Question']['partner_question_id'].'.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
					</div>
					<div class="row-fluid row-query">
						<div class="span3">								
							<label class="title">Living Situation</label>
							<div class="scrollbox">
							<?php foreach ($questions['homeowner']['Answers'] as $id => $value): ?>
								<?php echo $this->Form->input($questions['homeowner']['Question']['partner_question_id'].'.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>	
						</div>
						<div class="span3">				
							<label class="title">Children:</label>
							<div class="scrollbox">
							<?php foreach ($questions['children']['Answers'] as $id => $value): ?>
								<?php echo $this->Form->input($questions['children']['Question']['partner_question_id'].'.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
						<div class="span3">			
							<label class="title"># Children under 18:</label>
							<div class="scrollbox">
							<?php foreach ($questions['children_under_18']['Answers'] as $id => $value): ?>
								<?php echo $this->Form->input($questions['children_under_18']['Question']['partner_question_id'].'.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
					</div>
					<div class="row-fluid row-query">
						<div class="span3">				
							<label class="title">Organization Size:</label>
							<div class="scrollbox">
							<?php foreach ($questions['org_size']['Answers'] as $id => $value): ?>
								<?php echo $this->Form->input($questions['org_size']['Question']['partner_question_id'].'.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
						<div class="span3">				
							<label class="title">Organization Revenue:</label>
							<div class="scrollbox">
							<?php foreach ($questions['org_rev']['Answers'] as $id => $value): ?>
								<?php echo $this->Form->input($questions['org_rev']['Question']['partner_question_id'].'.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
						<div class="span3">				
							<label class="title">B2B Decisionmaker:</label>
							<div class="scrollbox">
							<?php foreach ($questions['org_decisions']['Answers'] as $id => $value): ?>
								<?php echo $this->Form->input($questions['org_decisions']['Question']['partner_question_id'].'.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
						<div class="span3">				
							<label class="title">Has Smartphone:</label>
							<div class="scrollbox">
							<?php foreach ($questions['smartphone']['Answers'] as $id => $value): ?>
								<?php echo $this->Form->input($questions['smartphone']['Question']['partner_question_id'].'.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>	
		
		<div class="box">
			<div class="box-header">
				<span class="title">Location Filter</span>
			</div>
			<div class="box-content">
				<div class="padded">
					
					<div class="row-fluid">
						<div class="span4">
							<?php echo $this->Form->input('country', array(
								'value' => 'US',
								'onChange' => 'select_country(this.value)'
							)); ?>
						</div>
						<div class="span8">
							<?php echo $this->Form->input('zip_file', array(
								'type' => 'file', 
								'label' => 'Zips CSV file'
							)); ?>
						</div>
					</div>
					<div class="row-fluid">
						<div class="location" id="US">
							<div class="span3">
								<?php $region_list = array_unique(array_values($state_regions)); ?>
								<?php sort($region_list); ?>
								<label><strong>Regions</strong></label>
								<?php foreach ($region_list as $region): ?>
									<label><input type="checkbox" class="group-select" data-ref="<?php echo $region;?>"/> <?php echo $region;?></label>
									<?php foreach ($sub_regions[$region] as $sub_region): ?>
										<label class="sub-region"><input type="checkbox" class="group-select <?php echo $region ?>" data-ref="<?php echo str_replace(' ', '_', $sub_region);?>"/> <?php echo $sub_region;?></label>
									<?php endforeach; ?>
								<?php endforeach; ?>
							</div>
							<?php $states_chunked = array_chunk($states_list, ceil(count($states_list) / 3), true);
							foreach ($states_chunked as $key => $states): ?>
								<div class="span3">
									<?php if ($key == 0): ?>
										<label><strong>States</strong></label>
									<?php endif; ?>
										
									<?php foreach ($states as $id => $state): ?>
										<?php echo $this->Form->input('Query.state.'.$id, array(
											'type' => 'checkbox',
											'label' => $state,
											'class' => array($state_regions[$id], $sub_region_list[$id])
										)); ?>
									<?php endforeach; ?>	
								</div>							
							<?php endforeach; ?>
							
							<div class="clearfix"></div>
							<div class="span6" style="margin-left:0px;">
								<label><strong>DMAs</strong></label>
								<div style="overflow: auto; height: 400px;">
									<?php foreach ($dmas as $id => $dma): ?>
										<?php echo $this->Form->input('Query.dma_code.'.$id, array(
											'type' => 'checkbox',
											'label' => $dma
										)); ?>
									<?php endforeach; ?>
								</div>
							</div>
							<div class="span6">
								<label><strong>Counties</strong></label>
								<?php echo $this->Form->input(null, array(
									'name' => false,
									'label' => false,
									'type' => 'select',
									'options' => $states_list,
									'empty' => 'Select State:',
									'id' => 'state_dropdown'
								)); ?>
								<div id="counties_container" style="overflow: auto; height: 345px;">
								</div>
							</div>
						</div>
						<div class="location" id="GB">
							<div class="span3">
								<label><strong>Regions</strong></label>
								<?php foreach ($regions_gb as $value): ?>
									<?php echo $this->Form->input('Query.regionGB.'.$value['RegionMapping']['region'], array(
										'type' => 'checkbox',
										'label' => $value['RegionMapping']['region'],
									)); ?>
								<?php endforeach; ?>
							</div>
						</div>
						<div class="location" id="CA">
							<div class="span3">
								<label><strong>Regions</strong></label>
								<?php foreach ($regions_ca as $value): ?>
									<?php echo $this->Form->input('Query.regionCA.'.$value['RegionMapping']['region'], array(
										'type' => 'checkbox',
										'label' => $value['RegionMapping']['region'],
									)); ?>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		
		<div class="box">
			<div class="box-header">
				<ul class="box-toolbar">
					<li><?php 
						echo $this->Html->link('Add Profile Question', '#', array(
							'class' => 'btn btn-mini btn-primary',						
							'data-target' => '#modal-query-profile',
							'data-toggle' => 'modal'
						)); 
					?></li>
				</ul>
				<span class="title">Profile Questions</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<div id="query-profile-questions"></div>
				</div>
			</div>
		</div>
		<?php echo $this->Form->submit('Run Query', array('class' => 'btn btn-primary')); ?>
	</div>
	<div class="span4" id="query-save-form">
		
	</div>
</div>
<script type="text/javascript">
	$(document).ready(function() {
		$('.group-select').click(function() {			
			if ($(this).prop('checked')) {
				$('.' + $(this).data('ref')).prop('checked', true)
			}
			else {
				$('.' + $(this).data('ref')).prop('checked', false)
			}
		});
		
		select_country($('#QueryCountry').val());
		
		$('#state_dropdown').change(function() {
			show_counties($(this).val());
		});
		show_counties($('#state_dropdown').val());
	});
	
	function show_counties(state) {
		if ($('#counties_container input[type=checkbox]').length) {
			$('#counties_container input[type=checkbox]').each(function() {
				if (!$(this).prop('checked')) {
					$(this).parent('div').remove();
				}
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
					if (!$.isEmptyObject(data.counties)) {
						$.each(data.counties, function(i, county) {
							if (!$('.checkbox_'+county).length) {
								$('#counties_container').append('<div class="input checkbox checkbox_'+ county +'"><input name="data[Query][county]['+ county +']" value="1" id="QueryCounty'+ county +'" type="checkbox"><label for="QueryCounty'+ county +'">'+ county +'</label></div>');
							}
						})
					}
				}
			}
		});
	}
	
	function select_country(val) {
		$('.location').hide();
		if (val == 'CA') {
			$('#CA').show();
			$('#GB input[type="checkbox"]').prop('checked', false);
		}
		else if (val == 'US') {
			$('#US').show();
			$('#GB input[type="checkbox"]').prop('checked', false);
			$('#CA input[type="checkbox"]').prop('checked', false);
		}
		else if (val == 'GB') {
			$('#GB').show();
			$('#CA input[type="checkbox"]').prop('checked', false);
		}
	}
</script>
<?php echo $this->Form->end(null); ?>

<?php echo $this->Element('modal_query_profile'); ?>