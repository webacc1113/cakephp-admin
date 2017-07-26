<?php echo $this->Form->create(null, array('type' => 'file')); ?>
<div class="box">
	<div class="box-header">
		<span class="title">Edit Lander URL</span>
	</div>
	<div class="box-content">
		<div class="row-fluid">
			<div class="span4">
				<div class="padded">
					<?php echo $this->Form->input('id'); ?>
					<?php echo $this->Form->input('path', array('label' => 'Path', 'after' => '<br/><small>All paths must be prefixed with /landers/index/ and have a trailing slash</small>')); ?>
					<?php echo $this->Form->input('name', array('label' => 'Name')); ?>
					<?php echo $this->Form->input('description', array('label' => 'Note')); ?>
					<?php echo $this->Form->input('browser_title', array('label' => 'Browser Title')); ?>
					<?php echo $this->Form->input('heading', array('label' => 'Heading')); ?>
					<?php echo $this->Form->input('content', array('label' => 'Content')); ?>	
					<?php echo $this->Form->input('source_name', array(
						'label' => 'Source',
						'type' => 'select',
						'options' => unserialize(LANDER_SOURCE_NAME),
						'empty' => false,
						'default' => 'on'
						)); ?>	
				</div>
			</div>
		</div>
		<div class="form-actions">
			<?php echo $this->Form->submit('Edit', array('class' => 'btn btn-primary')); ?>
		</div>
	</div>
</div>
<?php echo $this->Form->end(null); ?>