<div class="box">
	<div class="box-header">
		<?php if (isset($has_filter) && $has_filter): ?>
			<span class="title">Save Query Filter</span>
		<?php else: ?>
			<span class="title"><?php echo empty($survey_id) ? 'Query Results': 'Save Query ('.$this->App->project_name($project).')'; ?></span>
		<?php endif; ?>
	</div>
	<div class="box-content">
		<div class="padded">
			<?php if (strlen(json_encode($results['query'])) >= 65000): ?>
				<div class="alert alert-error">The query you generated is too large. Please break it down in size.</div>
			<?php else: ?>
				
			<?php if (!empty($survey_id)): ?>
			<?php echo $this->Form->create('Query', array('url' => array('action' => 'add', $survey_id))); ?>
			<?php if (isset($has_filter) && $has_filter): ?>
				<?php echo $this->Form->input('parent_id', array(
					'type' => 'hidden', 
					'value' => $parent_id
				)); ?>
			<?php endif; ?>
			<?php echo $this->Form->input('survey_id', array(
				'type' => 'hidden',
				'value' => $survey_id,
			)); ?>
			<?php echo $this->Form->input('name', array(
				'required' => true,
				'label' => $has_filter ? 'Filter name': 'Query name',
			)); ?>
			<?php echo $this->Form->input('QueryStatistic.quota', array(
				'type' => 'text',
				'maxlength' => 8,
				'required' => $has_filter,
				'label' => $has_filter ? 'Filter quota': 'Query quota',
				'after' => $has_filter ? '': '<small>Leave blank if there is no specific quota for this query</small>'
			)); ?>
			<?php endif; ?>
			
			<?php if (isset($this->request->data['Query']['zip_file']['tmp_name'])): ?>
				<?php echo $this->Form->input('zips_csv', array(
					'type' => 'hidden', 
					'value' => $this->request->data['Query']['zip_file']['name']
				)); ?>
			<?php endif; ?>
			<p><strong>Total users matched:</strong><br/>
				<?php echo number_format($results['count']['total']); ?></p>
			
			<p><?php echo $this->Form->input('display', array(
				'type' => 'textarea',
				'label' => 'Query',
				'value' => Utils::prettify(json_encode($results['query']))
			)); ?></p>
			
			<?php echo $this->Form->input('string', array(
				'type' => 'hidden', 
				'value' => json_encode($results['query'])
			)); ?>
			
			<?php if (isset($this->request->data['Query']['profiles']) && !empty($this->request->data['Query']['profiles'])): ?>
				<?php echo $this->Form->input('profiles', array(
					'type' => 'hidden', 
					'value' => json_encode(array_keys($this->request->data['Query']['profiles']))
				)); ?>
			<?php endif; ?>
			<?php if (defined('IS_DEV_INSTANCE') && IS_DEV_INSTANCE): ?>
			<?php echo $this->Form->input('dbquery', array(
				'type' => 'text', 
				'label' => 'DB Query (Debug)',
				'value' => $results['dbquery']
			)); ?>
			<?php endif; ?>
			
			<?php if (!empty($survey_id)): ?>
				<?php if (isset($has_filter) && $has_filter): ?>
					<?php echo $this->Form->input('redirect', array(
						'type' => 'checkbox',
						'label' => 'Create another filter after this one'
					)); ?>
				<?php endif; ?>
			<?php echo $this->Form->submit('Save', array('class' => 'btn btn-primary')); ?>
			<?php echo $this->Form->end(null); ?>
			<?php endif; ?>
			<?php endif; ?>

		</div>
	</div>
</div>