<div class="padded">
	<?php echo $this->Form->input('Qualification.id', array(
		'type' => 'text',
		'id' => 'qualification_id',
		'value' => $qualification_id,
		'type' => 'hidden',
		'required' => true
	)); ?>
	<div class="row-fluid row-query user-ids">
		<div class="span4">
			<label class="text-right" for="user_id">
				Target Additional User IDs<br/>
				<small>One per line; can use user ids or session ids</small>
			</label>
		</div>
		<div class="span6">
			<?php echo $this->Form->input('user_id', array(
				'label' => false,
				'type' => 'textarea',
				'style' => 'height: 52px',
				'value' => !empty($additional_json['append']['user_ids']) ? implode("\n", $additional_json['append']['user_ids']) : ''
			)); ?>
		</div>
	</div>
	<div class="row-fluid row-query user-ids">
		<div class="span4">
			<label class="text-right" for="exclude_user_id">
				<span class="text-error">Exclude</span> User IDs<br/>
				<small>One per line; can use user ids or session ids</small>
			</label>
		</div>
		<div class="span6">
			<?php echo $this->Form->input('exclude_user_id', array(
				'label' => false,
				'type' => 'textarea',
				'style' => 'height: 52px',
				'value' => !empty($additional_json['exclude']['user_ids']) ? implode("\n", $additional_json['exclude']['user_ids']) : ''
			)); ?>
		</div>
	</div>
	<div class="row-fluid row-query user-ids">
		<div class="span4">
			<label class="text-right" for="existing_complete_project_id">
				<span class="text-error">Exclude</span> Completes from Project(s)<br/>
				<small>One project id per line</small>
			</label>
		</div>
		<div class="span6">
			<?php echo $this->Form->input('existing_complete_project_id', array(
				'label' => false,
				'type' => 'textarea',
				'style' => 'height: 52px;',
				'value' => !empty($additional_json['exclude']['completes_from_project']) ? implode("\n", $additional_json['exclude']['completes_from_project']) : ''
			)); ?>
		</div>
	</div>
	<div class="row-fluid row-query user-ids">
		<div class="span4">
			<label class="text-right" for="existing_click_project_id">
				<span class="text-error">Exclude</span> Clicks from Project(s)<br/>
				<small>One project ID per line</small>
			</label>
		</div>
		<div class="span6">
			<?php echo $this->Form->input('existing_click_project_id', array(
				'label' => false,
				'type' => 'textarea',
				'style' => 'height: 52px',
				'value' => !empty($additional_json['exclude']['clicks_from_project']) ? implode("\n", $additional_json['exclude']['clicks_from_project']) : ''
			)); ?>
		</div>
	</div>
</div>
