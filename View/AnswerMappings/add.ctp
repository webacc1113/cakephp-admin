<h3>Create Answer Map</h3>
<?php echo $this->Form->create(); ?>
<div class="row-fluid">
	<div>
		<div class="span6">
			<div class="box">
				<div class="box-header">
					<span class="title">From</span>
				</div>
				<div class="box-content">
					<div class="padded">
						<?php echo $this->Form->input('from_partner.', array(
							'type' => 'select',
							'label' => 'Partner',
							'options' => $partners,
							'empty' => 'Select',
							'selected' => (isset($selected_question)) ? $selected_question['Question']['partner']: ''
						)); ?>
						<?php echo $this->Form->input('from_partner_question_id.', array(
							'type' => 'text', 
							'label' => 'Partner Question ID',
							'value' => (isset($selected_question)) ? $selected_question['Question']['partner_question_id']: ''
						)); ?>
						<?php echo $this->Form->input('from_partner_answer_id.', array(
							'type' => 'text', 
							'label' => 'Partner Answer ID'
						)); ?>
					</div>
				</div>
			</div>
		</div>
		<div class="span6">
			<div class="box">
				<div class="box-header">
					<span class="title">To</span>
				</div>
				<div class="box-content">
					<div class="padded">
						<?php echo $this->Form->input('to_partner.', array(
							'type' => 'select',
							'label' => 'Partner',
							'options' => $partners,
							'empty' => 'Select',
						)); ?>
						<?php echo $this->Form->input('to_partner_question_id.', array('type' => 'text', 'label' => 'Partner Question ID')); ?>
						<?php echo $this->Form->input('to_partner_answer_id.', array('type' => 'text', 'label' => 'Partner Answer ID')); ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<div class="new" style="display: none">
		<div class="span6">
			<div class="box">
				<div class="box-header">
					<span class="title">From</span>
				</div>
				<div class="box-content">
					<div class="padded">
						<?php echo $this->Form->input('from_partner.', array(
							'type' => 'select',
							'label' => 'Partner',
							'options' => $partners,
							'empty' => 'Select',
						)); ?>
						<?php echo $this->Form->input('from_partner_question_id.', array('type' => 'text', 'label' => 'Partner Question ID')); ?>
						<?php echo $this->Form->input('from_partner_answer_id.', array('type' => 'text', 'label' => 'Partner Answer ID')); ?>
					</div>
				</div>
			</div>
		</div>
		<div class="span6">
			<div class="box">
				<div class="box-header">
					<span class="title">To</span>
				</div>
				<div class="box-content">
					<div class="padded">
						<?php echo $this->Form->input('to_partner.', array(
							'type' => 'select',
							'label' => 'Partner',
							'options' => $partners,
							'empty' => 'Select',
						)); ?>
						<?php echo $this->Form->input('to_partner_question_id.', array('type' => 'text', 'label' => 'Partner Question ID')); ?>
						<?php echo $this->Form->input('to_partner_answer_id.', array('type' => 'text', 'label' => 'Partner Answer ID')); ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<p>
		<a href="#" class="btn btn-mini btn-success" onclick = "return MintVine.ShowAnswerMappingRow(this);"><span class="icon-plus"></span></a>
		<a href="#" onclick = "return MintVine.ShowAnswerMappingRow(this);">Map to another answer</a>
	</p>
</div>

<?php echo $this->Form->submit('Save', array('class' => 'btn btn-primary')); ?>
<?php echo $this->Form->end(null); ?>
