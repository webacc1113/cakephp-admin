<h4><?php echo empty($survey_id) ? 'Run Feasibility Query': 'Create Query for '.$this->App->project_name($project); ?></h4>
<div class="row-fluid query">
	<div class="span8"> 
		<?php echo $this->Form->create('Query', array('type' => 'file', 'onsubmit' => 'return MintVine.RunQuery(this)')); ?>
		<?php 
		
		echo $this->Form->input('survey_id', array(
			'type' => 'hidden', 
			'value' => $survey_id
		));
		
		if (isset($this->request->query['type'])) {
			echo $this->Form->input('type', array(
				'type' => 'hidden', 
				'value' => $this->request->query['type']
			)); 
		}
		?>
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
						
						<div class="span2">
							<?php echo $this->Form->input('gender', array(
								'label' => 'Gender', 
								'type' => 'select', 
								'options' => unserialize(USER_PROFILE_GENDERS),
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
							<?php $hhis = unserialize(USER_HHI); ?>							
							<label class="title">Household Income</label>
							<div class="scrollbox">
							<?php foreach ($hhis as $id => $hhi): ?>
								<?php echo $this->Form->input('Query.hhi.'.$id, array(
									'type' => 'checkbox', 
									'label' => $hhi
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
						<div class="span3">
							<label class="title">Education Level</label>
							<div class="scrollbox">
							<?php $edus = unserialize(USER_EDU); ?>
							<?php foreach ($edus as $id => $edu): ?>
								<?php echo $this->Form->input('Query.education.'.$id, array(
									'type' => 'checkbox', 
									'label' => $edu
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
						
						<div class="span3">
							<label class="title">Marital Status</label>
							<div class="scrollbox">
							<?php $martial_statuses = unserialize(USER_MARITAL); ?>
							<?php foreach ($martial_statuses as $id => $marital): ?>
								<?php echo $this->Form->input('Query.relationship.'.$id, array(
									'type' => 'checkbox', 
									'label' => $marital
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
						
						<div class="span3">
							<label class="title">Ethnicity</label>
							<div class="scrollbox">
							<?php $ethnicities = unserialize(USER_ETHNICITY); ?>
							<?php foreach ($ethnicities as $id => $ethnicity): ?>
								<?php echo $this->Form->input('Query.ethnicity.'.$id, array(
									'type' => 'checkbox', 
									'label' => $ethnicity
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
					</div>
					
					<div class="row-fluid row-query">						
						<div class="span3">
							<label class="title">Employment Status</label>
							<div class="scrollbox">
							<?php $values = unserialize(USER_EMPLOYMENT); ?>
							<?php foreach ($values as $id => $value): ?>
								<?php echo $this->Form->input('Query.employment.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
						<div class="span3">
							<label class="title">Job Industry</label>
							<div class="scrollbox">
							<?php $values = unserialize(USER_INDUSTRY); ?>
							<?php foreach ($values as $id => $value): ?>
								<?php echo $this->Form->input('Query.industry.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
						<div class="span3">
							<label class="title">Job Department</label>
							<div class="scrollbox">
							<?php $values = unserialize(USER_DEPARTMENT); ?>
							<?php foreach ($values as $id => $value): ?>
								<?php echo $this->Form->input('Query.department.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
						<div class="span3">
							<label class="title">Hispanic from:</label>
							<div class="scrollbox">
							<?php $values = unserialize(USER_ORIGIN); ?>
							<?php foreach ($values as $id => $value): ?>
								<?php echo $this->Form->input('Query.hispanic.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
					</div>
					<div class="row-fluid row-query">
						<div class="span3">		
							<label class="title">Job Title</label>
							<div class="scrollbox">
							<?php $values = unserialize(USER_JOB); ?>
							<?php foreach ($values as $id => $value): ?>
								<?php echo $this->Form->input('Query.job.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
						<div class="span3">								
							<label class="title">Rent or own?</label>
							<div>
							<?php $values = unserialize(USER_HOME); ?>
							<?php foreach ($values as $id => $value): ?>
								<?php echo $this->Form->input('Query.housing_own.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>				
							<label class="title">Home purchased in last 3 years?</label>
							<div>
							<?php $values = unserialize(USER_HOME_OWNERSHIP); ?>
							<?php foreach ($values as $id => $value): ?>
								<?php echo $this->Form->input('Query.housing_purchased.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
						<div class="span3">							
							<label class="title">Home plans:</label>
							<div class="scrollbox">
							<?php $values = unserialize(USER_HOME_PLANS); ?>
							<?php foreach ($values as $id => $value): ?>
								<?php echo $this->Form->input('Query.housing_plans.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
						<div class="span3">				
							<label class="title">Has children:</label>
							<div class="scrollbox">
							<?php $values = unserialize(USER_CHILDREN); ?>
							<?php foreach ($values as $id => $value): ?>
								<?php echo $this->Form->input('Query.children.'.$id, array(
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
							<?php $values = unserialize(USER_ORG_SIZE); ?>
							<?php foreach ($values as $id => $value): ?>
								<?php echo $this->Form->input('Query.organization_size.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
						<div class="span3">				
							<label class="title">Organization Revenue:</label>
							<div class="scrollbox">
							<?php $values = unserialize(USER_ORG_REVENUE); ?>
							<?php foreach ($values as $id => $value): ?>
								<?php echo $this->Form->input('Query.organization_revenue.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>
						</div>
						<div class="span3">				
							<label class="title">Has Smartphone:</label>
							<div>
							<?php $values = unserialize(USER_SMARTPHONE); ?>
							<?php foreach ($values as $id => $value): ?>
								<?php echo $this->Form->input('Query.smartphone.'.$id, array(
									'type' => 'checkbox', 
									'label' => $value
								)); ?>
							<?php endforeach; ?>
							</div>
									
							<label class="title">Has Tablet:</label>
							<div>
							<?php $values = unserialize(USER_TABLET); ?>
							<?php foreach ($values as $id => $value): ?>
								<?php echo $this->Form->input('Query.tablet.'.$id, array(
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
						$.each(data.counties, function(key, county) {
							if (!$('.checkbox_'+county).length) {
								$('#counties_container').append('<div class="input checkbox checkbox_'+ county +'"><input name="data[Query][county_fips][]" value="'+key+'" id="QueryCounty'+ county +'" type="checkbox"><label for="QueryCounty'+ county +'">'+ county +'</label></div>');
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