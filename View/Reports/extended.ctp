<?php echo $this->Form->create(); ?>
<div class="box reports">
	<div class="box-header">
		<span class="title">Generate Extended Report</span>
	</div>
	<div class="box-content">
		<div class="padded">
			<p>This report generator will take an <strong>existing report</strong>, and append MintVine user information to the data.</p>
			<div class="span4 ml0">
				<?php echo $this->Form->input('project_id', array(
					'label' => 'Project ID',
					'class' => 'auto',
					'type' => 'text',
					'required' => true,
					'value' => $project_id
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
					'user_id' => 'User ID',
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
					'http_agent' => 'HTTP User Agent',
					'address' => 'Address',
				)
			)); ?>
			
			<?php echo $this->Form->input('hashes', array(
				'label' => 'Limit to these respondent IDs',
				'type' => 'textarea'
			)); ?>
		</div>
	</div>
	<div class="form-actions">	
		<?php echo $this->Form->submit('Generate Extended Report', array(
			'class' => 'btn btn-sm btn-primary',
			'disabled' => false
		)); ?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>