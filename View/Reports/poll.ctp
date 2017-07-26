<?php echo $this->Form->create(); ?>
<div class="box reports">
	<div class="box-header">
		<span class="title">Generate Poll Report</span>
	</div>
	<div class="box-content">
		<div class="padded">
			<div class="span4 ml0">
				<?php echo $this->Form->input('poll_id', array(
					'value' => isset($poll_id) ? $poll_id: null,
					'label' => 'Poll ID',
					'class' => 'auto',
					'type' => 'text',
					'required' => true,
				)); ?>
			</div>
			<div class="clearfix"></div>			
			<?php echo $this->Form->input('options', array(
				'multiple' => 'checkbox', 
				'options' => array(
					'email' => 'Email', // special case
					'name' => 'Name', // special case
					'age' => 'Age', // special case
					'created' => 'Created', // special case
					'gender' => 'Gender',
					'hhi' => 'Household Income',
					'education' => 'Education Level',
					'children' => 'Has children under age 18', 
					'employment' => 'Employment Status', 
					'industry' => 'Employment Industry', 
					'relationship' => 'Marital Status',
					'ethnicity' => 'Ethnicity',
					'housing_own' => 'Home - Rent or own?',
					'smartphone' => 'Smartphone',
					'tablet' => 'Tablet',
					'country' => 'Country',
					'state' => 'State',
					'postal_code' => 'ZIP',
					'dma_code' => 'DMA',
					'organization_size' => 'Organization Size',
					'organization_revenue' => 'Organization Revenue',
					'job' => 'Job title',
					'department' => 'Department',
					'housing_purchased' => 'Own any homes?',
					'housing_plans' => 'Housing Plans',
					'airlines' => 'Have traveled?',
					'address' => 'Address',
				)
			)); ?>
		</div>
	</div>
	<div class="form-actions">	
		<?php echo $this->Form->submit('Generate Report', array('class' => 'btn btn-sm btn-primary')); ?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>