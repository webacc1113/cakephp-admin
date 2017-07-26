<?php echo $this->Form->create(); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Edit Group</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span4">
				<div class="padded">
					<?php echo $this->Form->input('id', array('type' => 'hidden')); ?>
					<?php echo $this->Form->input('name', array('label' => 'Group Name')); ?>
					<?php echo $this->Form->input('code_name', array('label' => 'Code Name (Seen by Panelists)')); ?>
					<?php echo $this->Form->input('key', array('label' => 'Group Key')); ?>
					<?php echo $this->Form->input('prefix', array('label' => 'Prefix (Used in project mask)')); ?>
					<?php echo $this->Form->input('router_priority', array('type' => 'text')); ?>
					<?php echo $this->Form->input('max_loi_minutes', array(
						'type' => 'text', 
						'label' => 'Max LOI (in minutes)', 
						'after' => '<small class="muted">Leave blank for unlimited</small>',
					)); ?>
					<?php echo $this->Form->input('max_clicks_with_no_completes', array(
						'type' => 'text', 
						'label' => 'Max clicks allowed with no completes', 
						'after' => '<small class="muted">Leave blank for unlimited</small>',
					)); ?>
					<?php echo $this->Form->input('epc_floor_cents', array(
						'maxlength' => '5', 
						'type' => 'text', 
						'label' => 'Floor EPC', 
						'between' => '<div class="input-prepend"><span class="add-on" href="#"><i class="icon-none">$</i></span>',
						'after' => '</div><small class="muted">Leave blank for no floor</small>',
					)); ?>
					<?php echo $this->Form->input('performance_checks', array('label' => 'Performance Checks', 'type' => 'select', 'options' => array('0' => 'No', '1' => 'Yes'))); ?>
					<?php echo $this->Form->input('use_mask', array('label' => 'Use Mask', 'type' => 'select', 'options' => array('0' => 'No', '1' => 'Yes'))); ?>
					<?php echo $this->Form->input('calculate_margin', array('label' => 'Calculate Margin', 'type' => 'select', 'options' => array('0' => 'No', '1' => 'Yes'))); ?>
					<?php echo $this->Form->input('check_links', array('label' => 'Check Links', 'type' => 'select', 'options' => array('0' => 'No', '1' => 'Yes'))); ?>
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Save Changes', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>