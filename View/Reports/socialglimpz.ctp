<?php echo $this->Form->create(null); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Generate SocialGlimpz Report</span>
	</div>
	<div class="box-content">
		<div class="padded">
			<p>Input the Socialglimpz project mask or the MintVine project id (prefixed with #) to generate the report of rejected/accepted respondents.</p>
			<?php echo $this->Form->input('project_id', array(
				'type' => 'text', 
				'placeholder' => 'Example: 0159 or #41528',
				'label' => 'Project ID'
			)); ?>
		</div>
	</div>
	<div class="form-actions">
		<?php echo $this->Form->submit('Generate Report', array('class' => 'btn btn-primary')); ?>
	</div>
</div>
<?php echo $this->Form->end(null); ?>