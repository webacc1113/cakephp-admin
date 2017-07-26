<div class="padded">
	<div class="row-fluid row-qualification" id="qualification_section">
		<?php echo $this->Form->input('Qualification.id', array(
			'label' => 'Name',
			'type' => 'text',
			'id' => 'qualification_id',
			'value' => $qualification_info['id'],
			'type' => 'hidden',
			'required' => true
		)); ?>
		<?php echo $this->Form->input('Qualification.name', array(
			'label' => 'Name',
			'type' => 'text',
			'id' => 'qualification_name',
			'value' => $qualification_info['name'],
			'required' => true
		)); ?>
		<div class="span4">
			<?php echo $this->Form->input('Qualification.quota', array(
				'label' => 'Quota',
				'value' => $qualification_info['quota'],
				'id' => 'qualification_quota',
				'type' => 'text',
				'style' => 'margin-bottom: 0; margin-right: 5px;'
			)); ?>
		</div>
		<div class="span4">
			<?php echo $this->Form->input('Qualification.cpi', array(
				'label' => 'CPI',
				'value' => $qualification_info['cpi'],
				'between' => '<div class="input-prepend"><span class="add-on" href="#"><i class="icon-none">$</i></span>',
				'after' => '</div>',
				'id' => 'qualification_cpi',
				'type' => 'text',
			)); ?>
		</div>
		<div class="span4">
			<?php echo $this->Form->input('Qualification.award', array(
				'label' => 'Award',
				'value' => $qualification_info['award'],
				'id' => 'qualification_award',
				'type' => 'text',
			)); ?>
		</div>
	</div>
</div>
