<h4>Add Filtered Query</h4>
<div class="row-fluid">
	<div class="span8">
		<div class="alert alert-warning">
			You are currently creating a filter on query <strong><?php echo $query['Query']['query_name']; ?></strong>. 
		</div>
		<?php echo $this->Form->create('Query', array('type' => 'file', 'onsubmit' => 'return MintVine.RunQuery(this)')); ?>
		<?php echo $this->Form->input('survey_id', array(
			'type' => 'hidden',
			'value' => $query['Query']['survey_id']
		)); ?>
		<?php echo $this->Form->input('parent_id', array(
			'type' => 'hidden',
			'value' => $query['Query']['id']
		)); ?>
		<div class="box">
			<div class="box-header">
				<span class="title">User Filters</span>
			</div>
			<div class="box-content">
				<div class="padded">
					<div class="row-fluid row-query">						
						<div class="span2">
							<?php echo $this->Form->input('gender', array(
								'label' => 'Gender', 
								'type' => 'select', 
								'options' => $query_string['gender'],
								'empty' => 'Select:',
								'style' => 'width: auto'
							)); ?>
						</div>
						
						<div class="span5" id="age">							
							<div class="form-group">
								<?php echo $this->Form->input('age_from', array(
									'label' => 'Age From',
									'style' => 'width: 55px;',
									'after' => isset($query_string['birthday']) ? '<div class="muted">Min: '.min($query_string['birthday']).'</div>' : '',
									'onblur' => isset($query_string['birthday']) ? 'validate_age(this, "'. min($query_string['birthday']) .'", "min")' : 'return true;'
								)); ?>
								<?php echo $this->Form->input('age_to', array(
									'label' => 'Age To',
									'style' => 'width: 55px;',
									'after' => isset($query_string['birthday']) ? '<div class="muted">Max: ' . max($query_string['birthday']) . '</div>' : '',
									'onblur' => isset($query_string['birthday']) ? 'validate_age(this, "' . max($query_string['birthday']) . '", "max")' : 'return true;'
							)); ?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
			
		
		<?php 
			$items_to_filter = array(
				array('hhi', 'education', 'relationship', 'ethnicity'),
				array('employment', 'industry', 'department', 'hispanic'),
				array('job', 'housing_own', 'housing_purchased', 'housing_plans', 'children'),
				array('organization_size', 'organization_revenue', 'smartphone', 'tablet')
			);
			$show_all = false;
			foreach ($items_to_filter as $items) {
				foreach ($items as $item) {
					if (isset($query_string[$item])) {
						$show_all = true; 
						break;
					}
				}
			}
		?>
		
		<?php if ($show_all): ?>
			<div class="box">
				<div class="box-header">
					<span class="title">User Profile Filters</span>
				</div>
				<div class="box-content">
					<div class="padded">
					
						<?php 
							$show_row = false;
							foreach ($items_to_filter[0] as $item) {
								if (isset($query_string[$item])) {
									$show_row = true; 
									break;
								}
							}
						?>
						<?php if ($show_row): ?>
							<div class="row-fluid row-query">
								<div class="span3">
									<?php $hhis = unserialize(USER_HHI); ?>							
									<label class="title">Household Income</label>
									<?php if (isset($query_string['hhi'])): ?>
										<div class="scrollbox">
										<?php foreach ($hhis as $id => $hhi): ?>
											<?php if (array_key_exists($id, $query_string['hhi'])): ?>
												<?php echo $this->Form->input('Query.hhi.'.$id, array(
													'type' => 'checkbox', 
													'label' => $hhi
												)); ?>
											<?php endif; ?>
										<?php endforeach; ?>
										</div>
									<?php else: ?>
										<span class="muted">None selected in query</span>
									<?php endif; ?>
								</div>
						
								<div class="span3">
									<label class="title">Education Level</label>
									<?php if (isset($query_string['education'])): ?>
										<div class="scrollbox">
										<?php $edus = unserialize(USER_EDU); ?>
										<?php foreach ($edus as $id => $edu): ?>
											<?php if (array_key_exists($id, $query_string['education'])): ?>
												<?php echo $this->Form->input('Query.education.'.$id, array(
													'type' => 'checkbox', 
													'label' => $edu
												)); ?>
											<?php endif; ?>
										<?php endforeach; ?>
										</div>
									<?php else: ?>
										<span class="muted">None selected in query</span>
									<?php endif; ?>
								</div>
					
								<div class="span3">
									<label class="title">Marital Status</label>
									<?php if (isset($query_string['relationship'])): ?>
										<div class="scrollbox">
										<?php $martial_statuses = unserialize(USER_MARITAL); ?>
										<?php foreach ($martial_statuses as $id => $marital): ?>
											<?php if (array_key_exists($id, $query_string['relationship'])): ?>
												<?php echo $this->Form->input('Query.relationship.'.$id, array(
													'type' => 'checkbox', 
													'label' => $marital
												)); ?>
											<?php endif; ?>
										<?php endforeach; ?>
										</div>
									<?php else: ?>
										<span class="muted">None selected in query</span>
									<?php endif; ?>
								</div>
						
								<div class="span3">
									<label class="title">Ethnicity</label>
									<?php if (isset($query_string['ethnicity'])): ?>
										<div class="scrollbox">
										<?php $ethnicities = unserialize(USER_ETHNICITY); ?>
										<?php foreach ($ethnicities as $id => $ethnicity): ?>
											<?php if (array_key_exists($id, $query_string['ethnicity'])): ?>
												<?php echo $this->Form->input('Query.ethnicity.'.$id, array(
													'type' => 'checkbox', 
													'label' => $ethnicity
												)); ?>
											<?php endif; ?>
										<?php endforeach; ?>
										</div>
									<?php else: ?>
										<span class="muted">None selected in query</span>
									<?php endif; ?>
								</div>
							</div>
						<?php endif; ?>
					
						<?php 
							$show_row = false;
							foreach ($items_to_filter[1] as $item) {
								if (isset($query_string[$item])) {
									$show_row = true; 
									break;
								}
							}
						?>
						<?php if ($show_row): ?>
							<div class="row-fluid row-query">						
								<div class="span3">
									<label class="title">Employment Status</label>
									<?php if (isset($query_string['employment'])): ?>
										<div class="scrollbox">
										<?php $values = unserialize(USER_EMPLOYMENT); ?>
										<?php foreach ($values as $id => $value): ?>
											<?php if (array_key_exists($id, $query_string['employment'])): ?>
												<?php echo $this->Form->input('Query.employment.'.$id, array(
													'type' => 'checkbox', 
													'label' => $value
												)); ?>
											<?php endif; ?>
										<?php endforeach; ?>
										</div>
									<?php else: ?>
										<span class="muted">None selected in query</span>
									<?php endif; ?>
								</div>
						
								<div class="span3">
									<label class="title">Job Industry</label>
									<?php if (isset($query_string['industry'])): ?>
										<div class="scrollbox">
										<?php $values = unserialize(USER_INDUSTRY); ?>
										<?php foreach ($values as $id => $value): ?>
											<?php if (array_key_exists($id, $query_string['industry'])): ?>
												<?php echo $this->Form->input('Query.industry.'.$id, array(
													'type' => 'checkbox', 
													'label' => $value
												)); ?>
											<?php endif; ?>
										<?php endforeach; ?>
										</div>
									<?php else: ?>
										<span class="muted">None selected in query</span>
									<?php endif; ?>
								</div>
						
								<div class="span3">
									<label class="title">Job Department</label>
									<?php if (isset($query_string['department'])): ?>
										<div class="scrollbox">
										<?php $values = unserialize(USER_DEPARTMENT); ?>
										<?php foreach ($values as $id => $value): ?>
											<?php if (array_key_exists($id, $query_string['department'])): ?>
												<?php echo $this->Form->input('Query.department.'.$id, array(
													'type' => 'checkbox', 
													'label' => $value
												)); ?>
											<?php endif; ?>
										<?php endforeach; ?>
										</div>
									<?php else: ?>
										<span class="muted">None selected in query</span>
									<?php endif; ?>
								</div>
						
								<div class="span3">
									<label class="title">Hispanic origin:</label>
									<?php if (isset($query_string['hispanic'])): ?>
										<div class="scrollbox">
										<?php $values = unserialize(USER_ORIGIN); ?>
										<?php foreach ($values as $id => $value): ?>
											<?php if (array_key_exists($id, $query_string['hispanic'])): ?>
												<?php echo $this->Form->input('Query.hispanic.'.$id, array(
													'type' => 'checkbox', 
													'label' => $value
												)); ?>
											<?php endif; ?>
										<?php endforeach; ?>
										</div>
									<?php else: ?>
										<span class="muted">None selected in query</span>
									<?php endif; ?>
								</div>
							</div>
						<?php endif; ?>
					
						<?php 
							$show_row = false;
							foreach ($items_to_filter[2] as $item) {
								if (isset($query_string[$item])) {
									$show_row = true; 
									break;
								}
							}
						?>
						<?php if ($show_row): ?>
							<div class="row-fluid row-query">
								<div class="span3">		
									<label class="title">Job Title</label>
									<?php if (isset($query_string['job'])): ?>
										<div class="scrollbox">
										<?php $values = unserialize(USER_JOB); ?>
										<?php foreach ($values as $id => $value): ?>
											<?php if (array_key_exists($id, $query_string['job'])): ?>
												<?php echo $this->Form->input('Query.job.'.$id, array(
													'type' => 'checkbox', 
													'label' => $value
												)); ?>
											<?php endif; ?>
										<?php endforeach; ?>
										</div>
									<?php else: ?>
										<span class="muted">None selected in query</span>
									<?php endif; ?>
								</div>
						
								<div class="span3">								
									<label class="title">Rent or own?</label>
									<?php if (isset($query_string['housing_own'])): ?>
										<div>
										<?php $values = unserialize(USER_HOME); ?>
										<?php foreach ($values as $id => $value): ?>
											<?php echo $this->Form->input('Query.housing_own.'.$id, array(
												'type' => 'checkbox', 
												'label' => $value
											)); ?>
										<?php endforeach; ?>
										</div>
									<?php else: ?>
										<span class="muted">None selected in query</span>
									<?php endif; ?>
							
									<label class="title">Home purchased in last 3 years?</label>
									<?php if (isset($query_string['housing_purchased'])): ?>
										<div>
										<?php $values = unserialize(USER_HOME_OWNERSHIP); ?>
										<?php foreach ($values as $id => $value): ?>
											<?php if (array_key_exists($id, $query_string['housing_purchased'])): ?>
												<?php echo $this->Form->input('Query.housing_purchased.'.$id, array(
													'type' => 'checkbox', 
													'label' => $value
												)); ?>
											<?php endif; ?>
										<?php endforeach; ?>
										</div>
									<?php else: ?>
										<span class="muted">None selected in query</span>
									<?php endif; ?>
								</div>
						
								<div class="span3">							
									<label class="title">Home plans:</label>
									<?php if (isset($query_string['housing_plans'])): ?>
										<div class="scrollbox">
										<?php $values = unserialize(USER_HOME_PLANS); ?>
										<?php foreach ($values as $id => $value): ?>
											<?php if (array_key_exists($id, $query_string['housing_plans'])): ?>
												<?php echo $this->Form->input('Query.housing_plans.'.$id, array(
													'type' => 'checkbox', 
													'label' => $value
												)); ?>
											<?php endif; ?>
										<?php endforeach; ?>
										</div>
									<?php else: ?>
										<span class="muted">None selected in query</span>
									<?php endif; ?>
								</div>
								<div class="span3">				
									<label class="title">Has children:</label>
									<?php if (isset($query_string['children'])): ?>
										<div class="scrollbox">
										<?php $values = unserialize(USER_CHILDREN); ?>
										<?php foreach ($values as $id => $value): ?>
											<?php if (array_key_exists($id, $query_string['children'])): ?>
												<?php echo $this->Form->input('Query.children.'.$id, array(
													'type' => 'checkbox', 
													'label' => $value
												)); ?>
											<?php endif; ?>
										<?php endforeach; ?>
										</div>							
									<?php else: ?>
										<span class="muted">None selected in query</span>
									<?php endif; ?>
								</div>
							</div>
						<?php endif; ?>
					
						<?php 
							$show_row = false;
							foreach ($items_to_filter[3] as $item) {
								if (isset($query_string[$item])) {
									$show_row = true; 
									break;
								}
							}
						?>
					
						<?php if ($show_row): ?>
							<div class="row-fluid row-query">
								<div class="span3">				
									<label class="title">Organization Size:</label>
									<?php if (isset($query_string['organization_size'])): ?>
										<div class="scrollbox">
										<?php $values = unserialize(USER_ORG_SIZE); ?>
										<?php foreach ($values as $id => $value): ?>
											<?php if (array_key_exists($id, $query_string['organization_size'])): ?>
												<?php echo $this->Form->input('Query.organization_size.'.$id, array(
													'type' => 'checkbox', 
													'label' => $value
												)); ?>
											<?php endif; ?>
										<?php endforeach; ?>
										</div>
									<?php else: ?>
										<span class="muted">None selected in query</span>
									<?php endif; ?>
								</div>
						
								<div class="span3">				
									<label class="title">Organization Revenue:</label>
									<?php if (isset($query_string['organization_revenue'])): ?>
										<div class="scrollbox">
										<?php $values = unserialize(USER_ORG_REVENUE); ?>
										<?php foreach ($values as $id => $value): ?>
											<?php if (array_key_exists($id, $query_string['organization_revenue'])): ?>
												<?php echo $this->Form->input('Query.organization_revenue.'.$id, array(
													'type' => 'checkbox', 
													'label' => $value
												)); ?>
											<?php endif; ?>
										<?php endforeach; ?>
										</div>
									<?php else: ?>
										<span class="muted">None selected in query</span>
									<?php endif; ?>
								</div>
						
								<div class="span3">				
									<label class="title">Has Smartphone:</label>
									<?php if (isset($query_string['smartphone'])): ?>
										<div>
										<?php $values = unserialize(USER_SMARTPHONE); ?>
										<?php foreach ($values as $id => $value): ?>
											<?php if (array_key_exists($id, $query_string['smartphone'])): ?>
												<?php echo $this->Form->input('Query.smartphone.'.$id, array(
													'type' => 'checkbox', 
													'label' => $value
												)); ?>
											<?php endif; ?>
										<?php endforeach; ?>
										</div>
									<?php else: ?>
										<span class="muted">None selected in query</span>
									<?php endif; ?>
									
									<label class="title">Has Tablet:</label>
									<?php if (isset($query_string['tablet'])): ?>
										<div>
										<?php $values = unserialize(USER_TABLET); ?>
										<?php foreach ($values as $id => $value): ?>
											<?php if (array_key_exists($id, $query_string['tablet'])): ?>
												<?php echo $this->Form->input('Query.tablet.'.$id, array(
													'type' => 'checkbox', 
													'label' => $value
												)); ?>
											<?php endif; ?>
										<?php endforeach; ?>
										</div>
									<?php else: ?>
										<span class="muted">None selected in query</span>
									<?php endif; ?>
								</div>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		<?php endif; ?>	
		
		<div class="box">
			<div class="box-header">
				<span class="title">Location Filter</span>
			</div>
			<div class="box-content">
				<div class="padded">
					
					<div class="row-fluid">
						<div class="span4">
							<?php echo $this->Form->input('country', array(
								'options' => array($query_string['country'])
							)); ?>
						</div>
					</div>
					<div class="row-fluid">
						<?php if (isset($query_string['state'])): ?>
							<?php 			
							$states_chunked = array_chunk($states_with_regions, ceil(count($states_with_regions) / 3), true);
							foreach ($states_chunked as $region): ?>
							<div class="span3">
								<?php								
								foreach ($region as $region_name => $states): ?>
								
									<label><strong><input type="checkbox" class="group-select" data-ref="<?php echo $region_name;?>"/> <?php echo $region_name;?></strong></label>
									<?php foreach ($states as $id => $state): ?>
										<?php if (array_key_exists($id, $query_string['state'])): ?>
											<?php echo $this->Form->input('Query.state.'.$id, array(
												'type' => 'checkbox',
												'label' => $state,
												'class' => $region_name
											)); ?>
										<?php endif;?>	
									<?php endforeach; ?>								
							<?php endforeach; ?>
							</div>
						<?php endforeach; ?>
						<?php endif; ?>
						
						<?php if (isset($query_string['dma_code'])): ?>
							<div class="span6" style="overflow: auto; height: 400px;">
								<label>DMAs</label>
								<?php foreach ($dmas as $id => $dma): ?>
									<?php if (array_key_exists($id, $query_string['dma_code'])): ?>
										<?php echo $this->Form->input('Query.dma_code.'.$id, array(
											'type' => 'checkbox',
											'label' => $dma
										)); ?>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
		
		<?php echo $this->Form->submit('Run Query', array('class' => 'btn btn-primary')); ?>
	</div>
	<div class="span4">
		<div class="box">
			<div class="box-header">
				<span class="title"><?php echo $query['Query']['query_name']; ?></span>
			</div>
			<div class="box-content">
				<div class="padded">
					<?php foreach ($query_string as $key => $value) : ?>
						<?php if ($key == 'keyword'): ?>
							<p><strong>Keyword Search:</strong> Matched keyword <code><?php echo $value; ?></code></p>
						<?php elseif ($key == 'user_id'): ?>
							<p><strong>Users:</strong> Matched <strong><?php echo number_format(count($value)); ?></strong> user IDs</p>
						<?php elseif ($key == 'gender'): ?>
							<p><strong>Gender</strong>: <?php
								echo implode(', ', $value); 
							?></p>
						<?php elseif ($key == 'birthday'): ?>
							<?php if (count($value) == 2) : ?>
								<p><strong>Age Range:</strong> <?php echo $value[0].' - '.$value[1]; ?></p>
							<?php else: ?>
								<p><strong>Age:</strong> <?php echo $value[0]; ?></p>
							<?php endif; ?>
						<?php elseif ($key == 'country'): ?>
							<p><strong>Country:</strong> <?php echo $value; ?></p>
						<?php elseif ($key == 'state'): ?>
							<p><strong>States:</strong> <?php echo implode(', ', $value); ?></p>
						<?php elseif ($key == 'dma_code'): ?>
							<p><strong>DMA Regions:</strong> <?php echo implode(', ', $value); ?></p>
						<?php elseif ($key == 'postal_code'): ?>
							<p><strong>Postal Codes:</strong> <?php echo $value; ?></p>
						<?php elseif (array_key_exists($key, $mappings)): ?>
							<p><strong><?php echo $mappings[$key]['title']; ?>:</strong> <?php echo implode(', ', $value); ?></p>
						<?php endif; ?>
					<?php endforeach; ?>

					<?php if (isset($query_string['age_to']) && $query_string['age_from']): ?>
						<p><strong>Ages:</strong> <?php echo $query_string['age_from']; ?> - <?php echo $query_string['age_to']; ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
		
		<?php if ($other_queries && !empty($other_queries)): ?>
			<div class="box">
				<div class="box-header">
					<span class="title">Other Filters</span>
				</div>
				<div class="box-content">
					<div class="padded">
						<ul>
							<?php foreach ($other_queries as $other_query): ?>
								<li><strong><?php echo $other_query['Query']['query_name']; ?></strong>
								<?php if (!is_null($other_query['QueryStatistic']['quota'])): ?>
									(quota: <?php echo number_format($other_query['QueryStatistic']['quota']); ?>)
								<?php endif; ?>	
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				</div>
			</div>
		<?php endif; ?>
		
		<div id="query-save-form"></div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>

<script type="text/javascript">
	function validate_age(node, value, type) {
		if (type == 'min' && parseInt($(node).val()) < value) {
			//$('#alert-age'). text('Age from must be greater then or equal to '.value);
			$(node).val(value); 
			$("#age .alert").remove();
			$('<div class="alert alert-danger"/>')
			.text('"Age From" must be greater then or equal to ' + value)
			.prependTo('#age');
		}
		else if (type == 'max' && parseInt($(node).val()) > value) {
			$(node).val(value); 
			$("#age .alert").remove();
			$('<div class="alert alert-danger"/>')
			.text('"Age To" must be less then or equal to ' + value)
			.prependTo('#age');
		}
		else {
			$("#age .alert").remove();
		}
	}
</script>	
<script type="text/javascript">
	$(document).ready(function() {
		$('.group-select').click(function() {			
			if ($(this).prop('checked')) {
				$('.' + $(this).data('ref')).prop('checked', true)
			}
			else {
				$('.' + $(this).data('ref')).prop('checked', false)
			}
		})
	})
</script>